<?php
/**
 * Settings page (Settings -> First Love Map) and the one-time CSV importer.
 *
 * @package First_Love_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Largest CSV accepted by the importer, in bytes. */
const FLM_IMPORT_MAX_BYTES = 5242880;

/**
 * Adds the settings page.
 *
 * @return void
 */
function flm_add_settings_page() {
	add_options_page(
		__( 'First Love Map', 'first-love-map' ),
		__( 'First Love Map', 'first-love-map' ),
		'manage_options',
		'flm-settings',
		'flm_render_settings_page'
	);
}
add_action( 'admin_menu', 'flm_add_settings_page' );

/**
 * Registers the settings with their sanitizer.
 *
 * @return void
 */
function flm_register_settings() {
	register_setting(
		'flm_settings_group',
		FLM_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'flm_sanitize_settings',
			'default'           => flm_default_options(),
		)
	);
}
add_action( 'admin_init', 'flm_register_settings' );

/**
 * Sanitizes submitted settings.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function flm_sanitize_settings( $input ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return flm_get_options();
	}
	$input = is_array( $input ) ? $input : array();

	$email = isset( $input['notify_email'] ) ? sanitize_email( wp_unslash( $input['notify_email'] ) ) : '';
	if ( '' !== $email && ! is_email( $email ) ) {
		$email = '';
	}

	$origins_raw = isset( $input['embed_origins'] ) ? sanitize_textarea_field( wp_unslash( $input['embed_origins'] ) ) : '';

	return array(
		'premoderation' => empty( $input['premoderation'] ) ? 0 : 1,
		'decimals'      => isset( $input['decimals'] ) ? max( 0, min( 5, absint( $input['decimals'] ) ) ) : 2,
		'notify_email'  => $email,
		'embed_origins' => implode( "\n", flm_parse_origins( $origins_raw ) ),
	);
}

/**
 * Renders the settings page.
 *
 * @return void
 */
function flm_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'first-love-map' ), 403 );
	}
	$opts = flm_get_options();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only result counters after a redirect.
	$done = isset( $_GET['flm_imported'] );
	$n_in = isset( $_GET['flm_imported'] ) ? absint( $_GET['flm_imported'] ) : 0;
	$n_dp = isset( $_GET['flm_dupes'] ) ? absint( $_GET['flm_dupes'] ) : 0;
	$n_bd = isset( $_GET['flm_invalid'] ) ? absint( $_GET['flm_invalid'] ) : 0;
	$err  = isset( $_GET['flm_import_error'] ) ? sanitize_key( wp_unslash( $_GET['flm_import_error'] ) ) : '';
	// phpcs:enable
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'First Love Map', 'first-love-map' ); ?></h1>

		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: imported count, 2: duplicates skipped, 3: invalid rows skipped */
						__( 'Import finished: %1$d imported, %2$d duplicates skipped, %3$d invalid rows skipped.', 'first-love-map' ),
						$n_in,
						$n_dp,
						$n_bd
					)
				);
				?>
			</p></div>
		<?php elseif ( '' !== $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( flm_import_error_message( $err ) ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'flm_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Review before publishing', 'first-love-map' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( FLM_OPTION ); ?>[premoderation]" value="1" <?php checked( 1, (int) $opts['premoderation'] ); ?>>
							<?php esc_html_e( 'New memories wait as Pending until a staff member publishes them (recommended).', 'first-love-map' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="flm_decimals"><?php esc_html_e( 'Location decimals', 'first-love-map' ); ?></label></th>
					<td>
						<input type="number" id="flm_decimals" name="<?php echo esc_attr( FLM_OPTION ); ?>[decimals]" min="0" max="5" value="<?php echo esc_attr( (string) (int) $opts['decimals'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Pins are rounded to this many decimal places before saving. 2 is about 1 km, so a pin marks an area, not an address.', 'first-love-map' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="flm_notify"><?php esc_html_e( 'Notification email', 'first-love-map' ); ?></label></th>
					<td>
						<input type="email" id="flm_notify" name="<?php echo esc_attr( FLM_OPTION ); ?>[notify_email]" value="<?php echo esc_attr( (string) $opts['notify_email'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Receives a "new memory awaiting review" email with a link to the pending list. The story text is never included. Leave blank for no emails.', 'first-love-map' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="flm_origins"><?php esc_html_e( 'Allowed embed origins', 'first-love-map' ); ?></label></th>
					<td>
						<textarea id="flm_origins" name="<?php echo esc_attr( FLM_OPTION ); ?>[embed_origins]" rows="4" class="large-text code"><?php echo esc_textarea( (string) $opts['embed_origins'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Sites allowed to show the map in an iframe via ?flm_embed=1, one per line, for example https://example.framer.website. This site itself is always allowed.', 'first-love-map' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Import existing stories', 'first-love-map' ); ?></h2>
		<p><?php esc_html_e( 'Upload a CSV with the columns id, createdAt, lat, lon, year, story, approved. Rows marked approved (TRUE) are published with their original date; other rows are added as Pending. Rows already imported are skipped, so it is safe to import the same file twice.', 'first-love-map' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="flm_import_csv">
			<?php wp_nonce_field( 'flm_import_csv', 'flm_import_nonce' ); ?>
			<input type="file" name="flm_csv" accept=".csv,text/csv" required>
			<?php submit_button( __( 'Import CSV', 'first-love-map' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Message for an importer error code.
 *
 * @param string $code Error code.
 * @return string
 */
function flm_import_error_message( $code ) {
	$messages = array(
		'nofile'  => __( 'Choose a CSV file to import.', 'first-love-map' ),
		'toobig'  => __( 'That file is too large.', 'first-love-map' ),
		'badtype' => __( 'That does not look like a CSV file.', 'first-love-map' ),
		'header'  => __( 'The CSV header is missing required columns (createdAt, lat, lon, story).', 'first-love-map' ),
	);
	return isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'The import failed.', 'first-love-map' );
}

/**
 * Redirects back to the settings page with result parameters.
 *
 * @param array $args Query args (numbers or short codes only).
 * @return void
 */
function flm_import_redirect( array $args ) {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php?page=flm-settings' ) ) );
	exit;
}

/**
 * Stable key identifying an imported row, used to skip duplicates.
 *
 * @param string $created Original createdAt value.
 * @param string $story   Story text.
 * @return string
 */
function flm_import_key( $created, $story ) {
	return hash( 'sha256', $created . "\n" . $story );
}

/**
 * Handles the CSV upload.
 *
 * @return void
 */
function flm_handle_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'first-love-map' ), 403 );
	}
	check_admin_referer( 'flm_import_csv', 'flm_import_nonce' );

	if ( empty( $_FILES['flm_csv'] ) || ! isset( $_FILES['flm_csv']['tmp_name'], $_FILES['flm_csv']['error'], $_FILES['flm_csv']['size'], $_FILES['flm_csv']['name'] )
		|| UPLOAD_ERR_OK !== (int) $_FILES['flm_csv']['error'] ) {
		flm_import_redirect( array( 'flm_import_error' => 'nofile' ) );
	}
	$tmp  = (string) $_FILES['flm_csv']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- server-generated temp path, checked with is_uploaded_file().
	$name = sanitize_file_name( wp_unslash( $_FILES['flm_csv']['name'] ) );
	if ( ! is_uploaded_file( $tmp ) ) {
		flm_import_redirect( array( 'flm_import_error' => 'nofile' ) );
	}
	if ( (int) $_FILES['flm_csv']['size'] > FLM_IMPORT_MAX_BYTES ) {
		flm_import_redirect( array( 'flm_import_error' => 'toobig' ) );
	}
	if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
		flm_import_redirect( array( 'flm_import_error' => 'badtype' ) );
	}

	$result = flm_import_csv_file( $tmp );
	if ( is_string( $result ) ) {
		flm_import_redirect( array( 'flm_import_error' => $result ) );
	}
	flm_import_redirect(
		array(
			'flm_imported' => $result['imported'],
			'flm_dupes'    => $result['dupes'],
			'flm_invalid'  => $result['invalid'],
		)
	);
}
add_action( 'admin_post_flm_import_csv', 'flm_handle_import' );

/**
 * Imports memories from a CSV file.
 *
 * @param string $path Path to the CSV.
 * @return array|string Counts (imported, dupes, invalid) or an error code.
 */
function flm_import_csv_file( $path ) {
	$handle = fopen( $path, 'r' );
	if ( ! $handle ) {
		return 'nofile';
	}

	$header = fgetcsv( $handle, 0, ',', '"', '' );
	if ( ! is_array( $header ) ) {
		fclose( $handle );
		return 'header';
	}
	$cols = array();
	foreach ( $header as $i => $label ) {
		$label = strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $label ) ) );
		if ( '' !== $label ) {
			$cols[ $label ] = $i;
		}
	}
	foreach ( array( 'createdat', 'lat', 'lon', 'story' ) as $required ) {
		if ( ! isset( $cols[ $required ] ) ) {
			fclose( $handle );
			return 'header';
		}
	}

	$existing = flm_existing_import_keys();
	$decimals = max( 0, min( 5, (int) flm_get_options()['decimals'] ) );
	$counts   = array(
		'imported' => 0,
		'dupes'    => 0,
		'invalid'  => 0,
	);

	$get = function ( array $row, $col ) use ( $cols ) {
		return isset( $cols[ $col ], $row[ $cols[ $col ] ] ) ? $row[ $cols[ $col ] ] : '';
	};

	while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
		if ( array( null ) === $row ) {
			continue;
		}
		$created = trim( (string) $get( $row, 'createdat' ) );
		$story   = (string) $get( $row, 'story' );
		$lat     = $get( $row, 'lat' );
		$lon     = $get( $row, 'lon' );

		$check = flm_validate_content(
			$story,
			$get( $row, 'year' ),
			is_numeric( $lat ) ? $lat + 0 : null,
			is_numeric( $lon ) ? $lon + 0 : null,
			$decimals
		);
		if ( ! $check['ok'] ) {
			++$counts['invalid'];
			continue;
		}

		$key = flm_import_key( $created, $check['clean']['story'] );
		if ( isset( $existing[ $key ] ) ) {
			++$counts['dupes'];
			continue;
		}

		$time = strtotime( $created );
		if ( false === $time || $time > time() ) {
			$time = time();
		}
		$approved = isset( $cols['approved'] ) ? 'TRUE' === strtoupper( trim( (string) $get( $row, 'approved' ) ) ) : true;
		$post_id  = flm_insert_memory( $check['clean'], $approved ? 'publish' : 'pending', gmdate( 'Y-m-d H:i:s', $time ) );
		if ( is_wp_error( $post_id ) ) {
			++$counts['invalid'];
			continue;
		}
		update_post_meta( $post_id, 'flm_import_key', $key );
		$existing[ $key ] = true;
		++$counts['imported'];
	}
	fclose( $handle );
	return $counts;
}

/**
 * Import keys of memories already in the database, in any status including the trash.
 *
 * @return array Map of key => true.
 */
function flm_existing_import_keys() {
	$ids = get_posts(
		array(
			'post_type'      => FLM_CPT,
			'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => 'flm_import_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off admin import.
		)
	);
	$keys = array();
	foreach ( $ids as $id ) {
		$key = get_post_meta( $id, 'flm_import_key', true );
		if ( is_string( $key ) && '' !== $key ) {
			$keys[ $key ] = true;
		}
	}
	return $keys;
}
