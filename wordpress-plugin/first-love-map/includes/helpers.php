<?php
/**
 * Options, validation, client-IP and rate-limit helpers.
 *
 * @package First_Love_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maximum story length in characters, measured after trimming. */
const FLM_MAX_STORY = 5000;

/** Maximum length of the free-text year field. */
const FLM_MAX_YEAR = 20;

/** Minimum time the form must have been open, in milliseconds. */
const FLM_MIN_FORM_OPEN_MS = 3000;

/** Submissions allowed per client within the rate-limit window. */
const FLM_RATE_MAX = 5;

/** Rate-limit window in seconds. */
const FLM_RATE_WINDOW = 600;

/** Most memories returned by the public list. */
const FLM_PUBLIC_LIMIT = 1000;

/**
 * Generous bounding box around India, in degrees.
 *
 * @return array
 */
function flm_india_bounds() {
	return array(
		'lat_min' => 6,
		'lat_max' => 38,
		'lon_min' => 67,
		'lon_max' => 98,
	);
}

/**
 * Default plugin settings.
 *
 * @return array
 */
function flm_default_options() {
	return array(
		'premoderation' => 1,
		'decimals'      => 2,
		'notify_email'  => '',
		'embed_origins' => 'https://sazanahisupport.framer.website',
	);
}

/**
 * Current settings merged over the defaults.
 *
 * @return array
 */
function flm_get_options() {
	$saved = get_option( FLM_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, flm_default_options() );
}

/**
 * Origins allowed to frame the embed view, as a list of scheme://host[:port].
 *
 * @return string[]
 */
function flm_allowed_origins() {
	$opts    = flm_get_options();
	$origins = flm_parse_origins( (string) $opts['embed_origins'] );
	return $origins;
}

/**
 * Parses newline/space/comma separated origins, keeping only well-formed ones.
 *
 * @param string $raw Raw text.
 * @return string[]
 */
function flm_parse_origins( $raw ) {
	$out   = array();
	$parts = preg_split( '/[\s,]+/', trim( $raw ), -1, PREG_SPLIT_NO_EMPTY );
	foreach ( $parts as $part ) {
		$part = strtolower( rtrim( $part, '/' ) );
		if ( preg_match( '#^https?://[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:[0-9]{1,5})?$#', $part ) ) {
			$out[ $part ] = $part;
		}
	}
	return array_values( $out );
}

/**
 * Trims ASCII and Unicode whitespace from both ends.
 *
 * @param string $value Input.
 * @return string
 */
function flm_trim( $value ) {
	$value = (string) $value;
	$trim  = preg_replace( '/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $value );
	return null === $trim ? trim( $value ) : $trim;
}

/**
 * Character length of a string (multibyte aware).
 *
 * @param string $value Input.
 * @return int
 */
function flm_strlen( $value ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
}

/**
 * Substring by characters (multibyte aware).
 *
 * @param string $value  Input.
 * @param int    $length Characters to keep.
 * @return string
 */
function flm_substr( $value, $length ) {
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length, 'UTF-8' ) : substr( $value, 0, $length );
}

/**
 * Whether a value is a finite JSON number (int or float).
 *
 * @param mixed $value Input.
 * @return bool
 */
function flm_is_number( $value ) {
	return ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value );
}

/**
 * Validates and sanitizes the story fields shared by the public form and the importer.
 *
 * @param mixed $story    Raw story.
 * @param mixed $year     Raw year.
 * @param mixed $lat      Raw latitude.
 * @param mixed $lon      Raw longitude.
 * @param int   $decimals Decimal places kept for lat/lon.
 * @return array { ok: true, clean: {lat, lon, year, story} } or { ok: false, error }.
 */
function flm_validate_content( $story, $year, $lat, $lon, $decimals ) {
	if ( ! is_string( $story ) ) {
		return array( 'ok' => false, 'error' => 'story_required' );
	}
	$story = flm_trim( $story );
	if ( flm_strlen( $story ) < 1 ) {
		return array( 'ok' => false, 'error' => 'story_required' );
	}
	if ( flm_strlen( $story ) > FLM_MAX_STORY ) {
		return array( 'ok' => false, 'error' => 'story_too_long' );
	}
	$story = flm_trim( sanitize_textarea_field( $story ) );
	if ( flm_strlen( $story ) < 1 ) {
		return array( 'ok' => false, 'error' => 'story_required' );
	}

	if ( null === $year ) {
		$year = '';
	}
	if ( ! is_string( $year ) && ! is_int( $year ) && ! is_float( $year ) ) {
		return array( 'ok' => false, 'error' => 'bad_year' );
	}
	$year = flm_trim( (string) $year );
	if ( flm_strlen( $year ) > FLM_MAX_YEAR ) {
		return array( 'ok' => false, 'error' => 'year_too_long' );
	}
	$year = sanitize_text_field( $year );

	if ( ! flm_is_number( $lat ) || ! flm_is_number( $lon ) ) {
		return array( 'ok' => false, 'error' => 'bad_location' );
	}
	$b = flm_india_bounds();
	if ( $lat < $b['lat_min'] || $lat > $b['lat_max'] || $lon < $b['lon_min'] || $lon > $b['lon_max'] ) {
		return array( 'ok' => false, 'error' => 'outside_india' );
	}

	return array(
		'ok'    => true,
		'clean' => array(
			'lat'   => round( (float) $lat, $decimals ),
			'lon'   => round( (float) $lon, $decimals ),
			'year'  => $year,
			'story' => $story,
		),
	);
}

/**
 * Validates a public submission body (parsed JSON object).
 *
 * @param mixed $body     Parsed request body.
 * @param int   $decimals Decimal places kept for lat/lon.
 * @return array See flm_validate_content().
 */
function flm_validate_submission( $body, $decimals ) {
	if ( ! is_array( $body ) ) {
		return array( 'ok' => false, 'error' => 'bad_request' );
	}

	if ( isset( $body['website'] ) && '' !== $body['website'] ) {
		return array( 'ok' => false, 'error' => 'rejected' );
	}

	if ( ! isset( $body['openedAt'], $body['sentAt'] )
		|| ! flm_is_number( $body['openedAt'] ) || ! flm_is_number( $body['sentAt'] )
		|| $body['sentAt'] - $body['openedAt'] < FLM_MIN_FORM_OPEN_MS ) {
		return array( 'ok' => false, 'error' => 'too_fast' );
	}

	return flm_validate_content(
		isset( $body['story'] ) ? $body['story'] : null,
		isset( $body['year'] ) ? $body['year'] : '',
		isset( $body['lat'] ) ? $body['lat'] : null,
		isset( $body['lon'] ) ? $body['lon'] : null,
		$decimals
	);
}

/**
 * Cloudflare's published proxy ranges. Used only to decide whether the
 * CF-Connecting-IP header can be trusted.
 *
 * @return string[]
 */
function flm_cloudflare_ranges() {
	return array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
		'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
		'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
		'2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	);
}

/**
 * Whether an IP address falls inside a CIDR range.
 *
 * @param string $ip   IP address.
 * @param string $cidr Range such as 10.0.0.0/8.
 * @return bool
 */
function flm_ip_in_cidr( $ip, $cidr ) {
	list( $subnet, $bits ) = explode( '/', $cidr );
	$ip_bin     = @inet_pton( $ip );
	$subnet_bin = @inet_pton( $subnet );
	if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
		return false;
	}
	$bits  = (int) $bits;
	$bytes = intdiv( $bits, 8 );
	if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
		return false;
	}
	$rest = $bits % 8;
	if ( 0 === $rest ) {
		return true;
	}
	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
	return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $subnet_bin[ $bytes ] ) & $mask );
}

/**
 * The visitor's IP address. Behind Cloudflare the connecting address is a
 * Cloudflare edge, so CF-Connecting-IP is used, but only when the request
 * really arrived from a Cloudflare range.
 *
 * @return string
 */
function flm_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
	$ip     = $remote;

	if ( '' !== $remote && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		if ( filter_var( $forwarded, FILTER_VALIDATE_IP ) ) {
			foreach ( flm_cloudflare_ranges() as $range ) {
				if ( flm_ip_in_cidr( $remote, $range ) ) {
					$ip = $forwarded;
					break;
				}
			}
		}
	}

	return (string) apply_filters( 'flm_client_ip', $ip );
}

/**
 * Transient key for the current client. Keyed on a salted hash so raw IPs are never stored.
 *
 * @return string
 */
function flm_rate_key() {
	return 'flm_rl_' . substr( hash_hmac( 'sha256', flm_client_ip(), wp_salt( 'auth' ) ), 0, 32 );
}

/**
 * Seconds until the client may submit again, or 0 when under the limit.
 *
 * @return int
 */
function flm_rate_limited_for() {
	$state = get_transient( flm_rate_key() );
	if ( ! is_array( $state ) || ! isset( $state['count'], $state['start'] ) ) {
		return 0;
	}
	if ( $state['count'] < FLM_RATE_MAX ) {
		return 0;
	}
	return max( 1, (int) $state['start'] + FLM_RATE_WINDOW - time() );
}

/**
 * Records one accepted submission for the current client.
 *
 * @return void
 */
function flm_rate_record() {
	$key   = flm_rate_key();
	$state = get_transient( $key );
	$now   = time();
	if ( ! is_array( $state ) || ! isset( $state['count'], $state['start'] ) || $state['start'] + FLM_RATE_WINDOW <= $now ) {
		$state = array(
			'count' => 0,
			'start' => $now,
		);
	}
	++$state['count'];
	set_transient( $key, $state, max( 1, $state['start'] + FLM_RATE_WINDOW - $now ) );
}
