<?php
/**
 * REST API: POST /flm/v1/memories (public submit) and GET /flm/v1/memories (published list).
 *
 * @package First_Love_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the routes.
 *
 * @return void
 */
function flm_register_routes() {
	register_rest_route(
		'flm/v1',
		'/memories',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'flm_rest_list_memories',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'flm_rest_create_memory',
				'permission_callback' => '__return_true',
			),
		)
	);
}
add_action( 'rest_api_init', 'flm_register_routes' );

/**
 * User-facing messages for validation error codes.
 *
 * @return array
 */
function flm_error_messages() {
	return array(
		'bad_request'    => __( 'The request could not be understood.', 'first-love-map' ),
		'rejected'       => __( 'Your submission could not be accepted.', 'first-love-map' ),
		'too_fast'       => __( 'Please take a moment to write your story, then try again.', 'first-love-map' ),
		'story_required' => __( 'Please write your story.', 'first-love-map' ),
		'story_too_long' => __( 'Your story is too long.', 'first-love-map' ),
		'bad_year'       => __( 'Please check the year.', 'first-love-map' ),
		'year_too_long'  => __( 'The year is too long.', 'first-love-map' ),
		'bad_location'   => __( 'Click the map to choose a location.', 'first-love-map' ),
		'outside_india'  => __( 'Please choose a location within India.', 'first-love-map' ),
		'rate_limited'   => __( 'Too many submissions. Please try again later.', 'first-love-map' ),
	);
}

/**
 * Builds a REST error for a validation code.
 *
 * @param string $code   Error code.
 * @param int    $status HTTP status.
 * @return WP_Error
 */
function flm_rest_error( $code, $status = 400 ) {
	$messages = flm_error_messages();
	return new WP_Error(
		'flm_' . $code,
		isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['bad_request'],
		array( 'status' => $status )
	);
}

/**
 * Handles a public submission.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function flm_rest_create_memory( WP_REST_Request $request ) {
	$wait = flm_rate_limited_for();
	if ( $wait > 0 ) {
		$error = flm_rest_error( 'rate_limited', 429 );
		add_filter(
			'rest_post_dispatch',
			function ( $response ) use ( $wait ) {
				$response->header( 'Retry-After', (string) $wait );
				return $response;
			}
		);
		return $error;
	}

	$body = $request->get_json_params();
	if ( ! is_array( $body ) ) {
		return flm_rest_error( 'bad_request' );
	}

	$opts   = flm_get_options();
	$result = flm_validate_submission( $body, max( 0, min( 5, (int) $opts['decimals'] ) ) );
	if ( ! $result['ok'] ) {
		return flm_rest_error( $result['error'] );
	}

	$pending = ! empty( $opts['premoderation'] );
	$post_id = flm_insert_memory( $result['clean'], $pending ? 'pending' : 'publish' );
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'flm_server_error', __( 'Your memory could not be saved. Please try again later.', 'first-love-map' ), array( 'status' => 500 ) );
	}

	flm_rate_record();

	if ( $pending ) {
		flm_notify_new_memory();
	}

	$response = new WP_REST_Response(
		array(
			'ok'      => true,
			'pending' => $pending,
		)
	);
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/**
 * Inserts a memory. Text is stored as sanitized plain text and is always escaped on output.
 *
 * @param array       $clean  Validated fields: lat, lon, year, story.
 * @param string      $status Post status.
 * @param string|null $gmt    Optional creation time as a GMT "Y-m-d H:i:s" string; defaults to now.
 * @return int|WP_Error
 */
function flm_insert_memory( array $clean, $status, $gmt = null ) {
	$args = array(
		'post_type'    => FLM_CPT,
		'post_status'  => $status,
		'post_title'   => 'Memory',
		'post_content' => $clean['story'],
		'post_author'  => 0,
	);
	// An explicit date keeps the submission time on pending memories, which
	// would otherwise get no date until they are published.
	if ( null === $gmt ) {
		$gmt = gmdate( 'Y-m-d H:i:s' );
	}
	$args['post_date_gmt'] = $gmt;
	$args['post_date']     = get_date_from_gmt( $gmt );

	$post_id = wp_insert_post( wp_slash( $args ), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, 'flm_lat', $clean['lat'] );
	update_post_meta( $post_id, 'flm_lon', $clean['lon'] );
	update_post_meta( $post_id, 'flm_year', wp_slash( $clean['year'] ) );
	return (int) $post_id;
}

/**
 * Emails the configured address that a memory is waiting. The message links to
 * the pending list and never includes story text.
 *
 * @return void
 */
function flm_notify_new_memory() {
	$opts = flm_get_options();
	$to   = is_email( $opts['notify_email'] );
	if ( ! $to ) {
		return;
	}
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$link    = admin_url( 'edit.php?post_status=pending&post_type=' . FLM_CPT );
	$subject = sprintf( '[%s] New First Love Map memory awaiting review', $site );
	$message = "A new memory has been submitted to the First Love Map and is waiting for review.\n\n"
		. "Review it here (login required):\n" . $link . "\n";
	wp_mail( $to, $subject, $message );
}

/**
 * Returns the published memories. Pending, draft and trashed items are never included.
 *
 * @return WP_REST_Response
 */
function flm_rest_list_memories() {
	$query = new WP_Query(
		array(
			'post_type'           => FLM_CPT,
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => (int) apply_filters( 'flm_public_limit', FLM_PUBLIC_LIMIT ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);

	$out = array();
	foreach ( $query->posts as $post ) {
		if ( FLM_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			continue;
		}
		$lat = get_post_meta( $post->ID, 'flm_lat', true );
		$lon = get_post_meta( $post->ID, 'flm_lon', true );
		if ( ! is_numeric( $lat ) || ! is_numeric( $lon ) ) {
			continue;
		}
		$out[] = array(
			'id'        => (int) $post->ID,
			'lat'       => (float) $lat,
			'lon'       => (float) $lon,
			'year'      => (string) get_post_meta( $post->ID, 'flm_year', true ),
			'story'     => (string) $post->post_content,
			'createdAt' => mysql2date( 'Y-m-d\TH:i:s\Z', $post->post_date_gmt, false ),
		);
	}

	$response = new WP_REST_Response( $out );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}
