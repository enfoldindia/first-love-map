<?php
/**
 * Front end: assets, the [first_love_map] shortcode and the ?flm_embed=1 view.
 *
 * @package First_Love_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the bundled Leaflet and plugin assets. Nothing is loaded from a CDN.
 *
 * @return void
 */
function flm_register_assets() {
	wp_register_style( 'flm-leaflet', FLM_URL . 'assets/leaflet/leaflet.css', array(), '1.9.4' );
	wp_register_style( 'flm-style', FLM_URL . 'assets/flm.css', array( 'flm-leaflet' ), FLM_VERSION );
	wp_register_script( 'flm-leaflet', FLM_URL . 'assets/leaflet/leaflet.js', array(), '1.9.4', true );
	wp_register_script( 'flm-app', FLM_URL . 'assets/flm.js', array( 'flm-leaflet' ), FLM_VERSION, true );
	wp_localize_script(
		'flm-app',
		'FLM_CONFIG',
		array(
			'restUrl' => esc_url_raw( rest_url( 'flm/v1/memories' ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'flm_register_assets', 5 );

/**
 * Enqueues the assets, registering them first if the shortcode runs before wp_enqueue_scripts.
 *
 * @return void
 */
function flm_enqueue_assets() {
	if ( ! wp_script_is( 'flm-app', 'registered' ) ) {
		flm_register_assets();
	}
	wp_enqueue_style( 'flm-style' );
	wp_enqueue_script( 'flm-app' );
}

/**
 * Markup for the map widget.
 *
 * @param bool $embed True for the full-page embed view.
 * @return string
 */
function flm_render_widget( $embed = false ) {
	ob_start();
	?>
<div class="flm-wrap<?php echo $embed ? ' flm-embed' : ''; ?>" data-flm-root>
	<div class="flm-app">
		<header class="flm-header">
			<div>
				<div class="flm-title">&#9825; <?php esc_html_e( 'First Love Map of India', 'first-love-map' ); ?></div>
				<p><?php esc_html_e( 'Pin the place · Share the feeling · Let India remember', 'first-love-map' ); ?></p>
			</div>
			<div class="flm-header-right">
				<div class="flm-count"><span data-flm="count">0</span> <?php esc_html_e( 'memories pinned', 'first-love-map' ); ?></div>
			</div>
		</header>
		<div class="flm-main">
			<div class="flm-map-wrap">
				<div class="flm-map" data-flm="map"></div>
				<div class="flm-hint" data-flm="hint">&#10022; <?php esc_html_e( 'Click anywhere on India to pin your memory', 'first-love-map' ); ?></div>
				<div class="flm-toast" data-flm="toast" role="status"></div>
			</div>
			<div class="flm-side">
				<div class="flm-tabs">
					<button class="flm-tab active" data-flm="tab-share" type="button"><?php esc_html_e( 'Share your story', 'first-love-map' ); ?></button>
					<button class="flm-tab" data-flm="tab-read" type="button"><?php esc_html_e( 'Read stories', 'first-love-map' ); ?></button>
				</div>
				<div class="flm-panel" data-flm="panel-share">
					<div class="flm-warning">
						<strong><?php esc_html_e( 'Please do not add any identifying information.', 'first-love-map' ); ?></strong>
						<?php esc_html_e( 'No names, school names, phone numbers, social media handles or other details that could identify you or anyone else.', 'first-love-map' ); ?>
					</div>
					<p class="flm-intro"><?php esc_html_e( 'Drop a heart on the map and tell us all about your high school love. Stories are reviewed before they appear.', 'first-love-map' ); ?></p>
					<div class="flm-pin-badge" data-flm="pin-badge"><span class="flm-pin-icon">&#9825;</span><span data-flm="pin-text"><?php esc_html_e( 'No location yet — click the map', 'first-love-map' ); ?></span></div>
					<div class="flm-field">
						<label class="flm-label" for="flm-story"><?php esc_html_e( 'Your story', 'first-love-map' ); ?> <span class="flm-label-note"><?php esc_html_e( '(up to 5,000 characters)', 'first-love-map' ); ?></span></label>
						<textarea class="flm-textarea" id="flm-story" data-flm="story" maxlength="5000" placeholder="<?php echo esc_attr__( 'We sat in the last bench of chemistry class. She never knew I existed, but every single day felt like the most important day of my life...', 'first-love-map' ); ?>"></textarea>
						<div class="flm-chars" data-flm="chars">0 / 5,000</div>
					</div>
					<div class="flm-field">
						<label class="flm-label" for="flm-year"><?php esc_html_e( 'Approx. year', 'first-love-map' ); ?></label>
						<input type="text" class="flm-input" id="flm-year" data-flm="year" maxlength="20" placeholder="<?php echo esc_attr__( 'e.g. 2008, early 2000s...', 'first-love-map' ); ?>">
					</div>
					<div class="flm-hp" aria-hidden="true">
						<label><?php esc_html_e( 'Leave this empty', 'first-love-map' ); ?> <input type="text" data-flm="website" tabindex="-1" autocomplete="off"></label>
					</div>
					<p class="flm-error" data-flm="form-error" role="alert"></p>
					<button class="flm-submit" data-flm="submit" type="button" disabled>&#9825; <?php esc_html_e( 'Pin this memory', 'first-love-map' ); ?></button>
				</div>
				<div class="flm-panel" data-flm="panel-read" hidden></div>
			</div>
		</div>
	</div>
</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * The [first_love_map] shortcode.
 *
 * @return string
 */
function flm_shortcode() {
	flm_enqueue_assets();
	return flm_render_widget( false );
}
add_shortcode( 'first_love_map', 'flm_shortcode' );

/**
 * Registers the embed query variable.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function flm_query_vars( $vars ) {
	$vars[] = 'flm_embed';
	return $vars;
}
add_filter( 'query_vars', 'flm_query_vars' );

/**
 * Serves ?flm_embed=1 as a bare page containing only the map widget.
 *
 * @return void
 */
function flm_maybe_render_embed() {
	if ( '1' !== (string) get_query_var( 'flm_embed' ) ) {
		return;
	}

	$ancestors = array_merge( array( "'self'" ), flm_allowed_origins() );
	if ( ! headers_sent() ) {
		header_remove( 'X-Frame-Options' );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Security-Policy: frame-ancestors ' . implode( ' ', $ancestors ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}

	flm_register_assets();
	wp_enqueue_style( 'flm-style' );
	wp_enqueue_script( 'flm-app' );

	echo "<!doctype html>\n";
	echo '<html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '" class="flm-embed-html"><head>';
	echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<meta name="robots" content="noindex, nofollow">';
	echo '<title>' . esc_html( get_bloginfo( 'name' ) ) . ' – ' . esc_html__( 'First Love Map of India', 'first-love-map' ) . '</title>';
	wp_print_styles( array( 'flm-style' ) );
	echo '</head><body class="flm-embed-page">';
	echo flm_render_widget( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in flm_render_widget().
	wp_print_scripts( array( 'flm-app' ) );
	echo '</body></html>';
	exit;
}
add_action( 'template_redirect', 'flm_maybe_render_embed', 1 );
