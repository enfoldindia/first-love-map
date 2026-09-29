<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Memories belong to the organisation and are kept by default. To remove the
 * settings and every memory on uninstall, define FLM_REMOVE_ALL_DATA as true in
 * wp-config.php before deleting the plugin.
 *
 * @package First_Love_Map
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'FLM_REMOVE_ALL_DATA' ) || true !== FLM_REMOVE_ALL_DATA ) {
	return;
}

delete_option( 'flm_settings' );

$flm_ids = get_posts(
	array(
		'post_type'      => 'flm_memory',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);
foreach ( $flm_ids as $flm_id ) {
	wp_delete_post( $flm_id, true );
}
