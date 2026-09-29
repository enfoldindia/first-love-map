<?php
/**
 * Plugin Name:       First Love Map
 * Description:       A moderated map of India where visitors pin a place and share a short first-love story. Use the [first_love_map] shortcode.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Enfold
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       first-love-map
 *
 * @package First_Love_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLM_VERSION', '1.0.1' );
define( 'FLM_FILE', __FILE__ );
define( 'FLM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLM_URL', plugin_dir_url( __FILE__ ) );
define( 'FLM_CPT', 'flm_memory' );
define( 'FLM_OPTION', 'flm_settings' );

require_once FLM_DIR . 'includes/helpers.php';
require_once FLM_DIR . 'includes/cpt.php';
require_once FLM_DIR . 'includes/rest.php';
require_once FLM_DIR . 'includes/frontend.php';
require_once FLM_DIR . 'includes/settings.php';
