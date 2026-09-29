<?php
/**
 * The flm_memory post type, its admin list and title handling.
 *
 * @package First_Love_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the memory post type. It is never public: no front-end URL, no
 * search, no archives, no REST exposure through the core endpoints.
 *
 * @return void
 */
function flm_register_post_type() {
	$cap = 'edit_others_posts';

	register_post_type(
		FLM_CPT,
		array(
			'labels'              => array(
				'name'               => __( 'Love Map Memories', 'first-love-map' ),
				'singular_name'      => __( 'Memory', 'first-love-map' ),
				'menu_name'          => __( 'Love Map Memories', 'first-love-map' ),
				'all_items'          => __( 'All Memories', 'first-love-map' ),
				'edit_item'          => __( 'Edit Memory', 'first-love-map' ),
				'view_item'          => __( 'View Memory', 'first-love-map' ),
				'search_items'       => __( 'Search Memories', 'first-love-map' ),
				'not_found'          => __( 'No memories found.', 'first-love-map' ),
				'not_found_in_trash' => __( 'No memories in the trash.', 'first-love-map' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-heart',
			'menu_position'       => 25,
			'supports'            => array( 'editor' ),
			'capability_type'     => array( 'flm_memory', 'flm_memories' ),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'edit_posts'             => $cap,
				'edit_others_posts'      => $cap,
				'edit_private_posts'     => $cap,
				'edit_published_posts'   => $cap,
				'publish_posts'          => $cap,
				'read_private_posts'     => $cap,
				'delete_posts'           => $cap,
				'delete_private_posts'   => $cap,
				'delete_published_posts' => $cap,
				'delete_others_posts'    => $cap,
				'create_posts'           => 'do_not_allow',
			),
		)
	);
}
add_action( 'init', 'flm_register_post_type' );

/**
 * Builds the admin-facing title: "Memory #<id> — <first 40 characters>".
 *
 * @param int    $post_id Post ID.
 * @param string $story   Story text.
 * @return string
 */
function flm_build_title( $post_id, $story ) {
	$flat = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $story ) ) );
	return sprintf( 'Memory #%d — %s', $post_id, flm_substr( (string) $flat, 40 ) );
}

/**
 * Keeps the title in step with the story whenever a memory is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function flm_sync_title( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$title = flm_build_title( $post_id, $post->post_content );
	if ( $title === $post->post_title ) {
		return;
	}
	remove_action( 'save_post_' . FLM_CPT, 'flm_sync_title', 10 );
	wp_update_post(
		wp_slash(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		)
	);
	add_action( 'save_post_' . FLM_CPT, 'flm_sync_title', 10, 2 );
}
add_action( 'save_post_' . FLM_CPT, 'flm_sync_title', 10, 2 );

/**
 * Stores memory text as plain text. WordPress's HTML filter would otherwise
 * rewrite characters such as "&" for users without the unfiltered_html
 * capability; memories are plain text and are escaped wherever they are shown.
 *
 * @param array $data       Sanitized post data (slashed).
 * @param array $postarr    Post data as passed to wp_insert_post().
 * @param array $unsanitized Post data before sanitization (slashed).
 * @return array
 */
function flm_filter_post_data( $data, $postarr, $unsanitized = array() ) {
	if ( ! isset( $data['post_type'] ) || FLM_CPT !== $data['post_type'] ) {
		return $data;
	}
	if ( isset( $unsanitized['post_content'] ) ) {
		$data['post_content'] = wp_slash( sanitize_textarea_field( wp_unslash( $unsanitized['post_content'] ) ) );
	}
	if ( isset( $unsanitized['post_title'] ) ) {
		$data['post_title'] = wp_slash( sanitize_text_field( wp_unslash( $unsanitized['post_title'] ) ) );
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'flm_filter_post_data', 10, 3 );

/**
 * Admin list columns.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function flm_admin_columns( $columns ) {
	return array(
		'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '',
		'title'      => __( 'Memory', 'first-love-map' ),
		'flm_story'  => __( 'Story', 'first-love-map' ),
		'flm_year'   => __( 'Year', 'first-love-map' ),
		'flm_where'  => __( 'Location (rounded)', 'first-love-map' ),
		'date'       => __( 'Date', 'first-love-map' ),
		'flm_status' => __( 'Status', 'first-love-map' ),
	);
}
add_filter( 'manage_' . FLM_CPT . '_posts_columns', 'flm_admin_columns' );

/**
 * Renders the custom admin list columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function flm_admin_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'flm_story':
			$story = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
			$story = flm_substr( trim( preg_replace( '/\s+/u', ' ', $story ) ), 140 );
			echo esc_html( $story );
			break;
		case 'flm_year':
			echo esc_html( (string) get_post_meta( $post_id, 'flm_year', true ) );
			break;
		case 'flm_where':
			$lat = get_post_meta( $post_id, 'flm_lat', true );
			$lon = get_post_meta( $post_id, 'flm_lon', true );
			if ( '' !== $lat && '' !== $lon ) {
				echo esc_html( $lat . ', ' . $lon );
			}
			break;
		case 'flm_status':
			$labels = array(
				'pending' => __( 'Pending review', 'first-love-map' ),
				'publish' => __( 'Published (on the map)', 'first-love-map' ),
				'trash'   => __( 'Removed', 'first-love-map' ),
				'draft'   => __( 'Draft', 'first-love-map' ),
			);
			$status = get_post_status( $post_id );
			echo esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status );
			break;
	}
}
add_action( 'manage_' . FLM_CPT . '_posts_custom_column', 'flm_admin_column_content', 10, 2 );

/**
 * Adds a read-only details box to the edit screen.
 *
 * @return void
 */
function flm_add_meta_box() {
	add_meta_box( 'flm_details', __( 'Memory details', 'first-love-map' ), 'flm_render_meta_box', FLM_CPT, 'side' );
}
add_action( 'add_meta_boxes_' . FLM_CPT, 'flm_add_meta_box' );

/**
 * Renders the details box.
 *
 * @param WP_Post $post Post object.
 * @return void
 */
function flm_render_meta_box( $post ) {
	$year = (string) get_post_meta( $post->ID, 'flm_year', true );
	$lat  = (string) get_post_meta( $post->ID, 'flm_lat', true );
	$lon  = (string) get_post_meta( $post->ID, 'flm_lon', true );
	echo '<p><strong>' . esc_html__( 'Year:', 'first-love-map' ) . '</strong> ' . esc_html( $year ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Location:', 'first-love-map' ) . '</strong> ' . esc_html( $lat . ', ' . $lon ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Edit the story text to remove identifying details, then publish. Pending = awaiting review, Published = on the map, Trash = removed.', 'first-love-map' ) . '</p>';
}
