<?php
/**
 * Plugin Name:     Cryptocurrency Data Logger & Chart
 * Description:     Fetches data daily and exposes it via REST + shortcode for Chart.js graph.
 * Version:         1.3
 * Author:          aleks-jgn
 * Author URI:      https://github.com/aleks-jgn
 * License:         GNU AFFERO GENERAL PUBLIC LICENSE Version 3 (AGPL-3.0 license)
 * Text Domain:     token-holder-data
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ============================================================================
 * PLUGIN ACTIVATION / DEACTIVATION
 * ============================================================================
 */

/**
 * Create the JSON data file and schedule the daily cron event.
 */
function crypto_activate_plugin() {
	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['basedir'] ) . 'cdlc-data.json';

	// Create an empty JSON file if missing
	if ( ! file_exists( $file ) ) {
		file_put_contents( $file, wp_json_encode( [], JSON_PRETTY_PRINT ), LOCK_EX );
	}

	// Schedule the daily fetch at midnight site time
	if ( ! wp_next_scheduled( 'crypto_daily_fetch' ) ) {
		$now_ts    = current_time( 'timestamp' );
		$tomorrow  = strtotime( 'tomorrow midnight', $now_ts );
		wp_schedule_event( $tomorrow, 'daily', 'crypto_daily_fetch' );
	}
}
register_activation_hook( __FILE__, 'crypto_activate_plugin' );

/**
 * Clear the scheduled cron event on deactivation.
 */
function crypto_deactivate_plugin() {
	wp_clear_scheduled_hook( 'crypto_daily_fetch' );
}
register_deactivation_hook( __FILE__, 'crypto_deactivate_plugin' );

/**
 * ============================================================================
 * ADMIN SETTINGS PAGE
 * ============================================================================
 */

/**
 * Add a sub‑page under "Settings" in the admin dashboard.
 */
function crypto_add_admin_menu() {
	add_options_page(
		__( 'Crypto Data Logger Settings', 'token-holder-data' ),
		__( 'Crypto Data Logger', 'token-holder-data' ),
		'manage_options',
		'crypto-data-logger',
		'crypto_render_settings_page'
	);
}
add_action( 'admin_menu', 'crypto_add_admin_menu' );

/**
 * Register the plugin settings with sanitization.
 */
function crypto_register_settings() {
	register_setting(
		'crypto_data_logger_settings',
		'crypto_api_url',
		[
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => 'https://polygon.blockscout.com/api/v2/tokens/0xc2132D05D31c914a87C6611C10748AEb04B58e8F/',
		]
	);
	register_setting(
		'crypto_data_logger_settings',
		'crypto_manual_holders_count',
		[
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		]
	);
	register_setting(
		'crypto_data_logger_settings',
		'crypto_chart_width',
		[
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 500,
		]
	);
	register_setting(
		'crypto_data_logger_settings',
		'crypto_chart_height',
		[
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 200,
		]
	);
}
add_action( 'admin_init', 'crypto_register_settings' );

/**
 * Render the settings page HTML.
 */
function crypto_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Crypto Data Logger Settings', 'token-holder-data' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'crypto_data_logger_settings' );
			do_settings_sections( 'crypto-data-logger' );
			?>
			<table class="form-table">
					<th scope="row">
						<label for="crypto_api_url"><?php esc_html_e( 'API URL', 'token-holder-data' ); ?></label>
					</th>
					<td>
						<input type="url" name="crypto_api_url" id="crypto_api_url"
							value="<?php echo esc_attr( get_option( 'crypto_api_url' ) ); ?>"
							class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Endpoint that returns token data (must include "holders_count" field).', 'token-holder-data' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="crypto_manual_holders_count"><?php esc_html_e( 'Manual Holders Count', 'token-holder-data' ); ?></label>
					</th>
					<td>
						<input type="number" name="crypto_manual_holders_count" id="crypto_manual_holders_count"
							value="<?php echo esc_attr( get_option( 'crypto_manual_holders_count' ) ); ?>"
							class="small-text" />
						<p class="description">
							<?php esc_html_e( 'If set (non‑zero), this value will be used instead of the API response. Leave 0 to use the live API.', 'token-holder-data' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="crypto_chart_width"><?php esc_html_e( 'Chart Width (px)', 'token-holder-data' ); ?></label>
					</th>
					<td>
						<input type="number" name="crypto_chart_width" id="crypto_chart_width"
							value="<?php echo esc_attr( get_option( 'crypto_chart_width' ) ); ?>"
							class="small-text" />
						<p class="description"><?php esc_html_e( 'Width of the chart container in pixels.', 'token-holder-data' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="crypto_chart_height"><?php esc_html_e( 'Chart Height (px)', 'token-holder-data' ); ?></label>
					</th>
					<td>
						<input type="number" name="crypto_chart_height" id="crypto_chart_height"
							value="<?php echo esc_attr( get_option( 'crypto_chart_height' ) ); ?>"
							class="small-text" />
						<p class="description"><?php esc_html_e( 'Height of the chart container in pixels.', 'token-holder-data' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * ============================================================================
 * DATA FETCHING AND STORAGE (CRON JOB)
 * ============================================================================
 */

/**
 * Retrieve the current holders count – either from the manual override or the API.
 *
 * @return int|false The holders count, or false on failure.
 */
function crypto_get_current_holders_count() {
	$manual = get_option( 'crypto_manual_holders_count', 0 );
	if ( $manual > 0 ) {
		return $manual;
	}

	$api_url = get_option( 'crypto_api_url', 'https://polygon.blockscout.com/api/v2/tokens/0xc2132D05D31c914a87C6611C10748AEb04B58e8F/' );
	$response = wp_remote_get( $api_url );

	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( empty( $data['holders_count'] ) ) {
		return false;
	}

	return intval( $data['holders_count'] );
}

/**
 * Fetch the latest holders count and append it to the JSON history file.
 * This function is triggered by the daily cron event.
 */
function crypto_fetch_and_store_data() {
	$holders = crypto_get_current_holders_count();
	if ( false === $holders ) {
		return; // Nothing to store
	}

	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['basedir'] ) . 'cdlc-data.json';

	// Read existing history (fallback to empty array)
	$history = @json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $history ) ) {
		$history = [];
	}

	// Use site-local date
	$today = date( 'Y-m-d', current_time( 'timestamp' ) );

	// Append only if today's entry is missing
	if ( empty( $history ) || end( $history )['date'] !== $today ) {
		$history[] = [
			'date'  => $today,
			'value' => $holders,
		];

		file_put_contents( $file, wp_json_encode( $history, JSON_PRETTY_PRINT ), LOCK_EX );
	}
}
add_action( 'crypto_daily_fetch', 'crypto_fetch_and_store_data' );

/**
 * ============================================================================
 * REST API ENDPOINT
 * ============================================================================
 */

/**
 * Register a public REST endpoint that returns the entire history.
 */
function crypto_register_rest_routes() {
	register_rest_route( 'token-data/v1', '/history', [
		'methods'             => 'GET',
		'callback'            => 'crypto_rest_history',
		'permission_callback' => '__return_true',
	] );
}
add_action( 'rest_api_init', 'crypto_register_rest_routes' );

/**
 * REST callback: read and return the JSON history file.
 *
 * @return WP_REST_Response
 */
function crypto_rest_history() {
	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['basedir'] ) . 'cdlc-data.json';

	if ( ! file_exists( $file ) ) {
		return rest_ensure_response( [] );
	}

	$data = json_decode( file_get_contents( $file ), true );
	return rest_ensure_response( is_array( $data ) ? $data : [] );
}

/**
 * ============================================================================
 * FRONT‑END SCRIPTS AND SHORTCODE
 * ============================================================================
 */

/**
 * Register the Chart.js library and our custom chart script.
 * Localize the REST endpoint URL for use in JavaScript.
 */
function crypto_enqueue_scripts() {
	// Chart.js from CDN
	wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true );

	// Our chart helper – keep the same object name as the original JS expects
	wp_register_script(
		'crypto-chart',
		plugins_url( 'customized-chart.js', __FILE__ ),
		[ 'chartjs' ],
		'1.0',
		true
	);

	// Pass REST endpoint URL into JS using the object name expected by the unchanged JS file
	wp_localize_script( 'crypto-chart', 'CRYPTO_SETTINGS', [
		'endpoint' => esc_url_raw( rest_url( 'token-data/v1/history' ) ),
	] );
}
add_action( 'wp_enqueue_scripts', 'crypto_enqueue_scripts' );

/**
 * Shortcode to display the chart.
 *
 * Usage: [crypto_chart]
 *
 * @return string HTML output.
 */
function crypto_chart_shortcode() {
	wp_enqueue_script( 'chartjs' );
	wp_enqueue_script( 'crypto-chart' );

	$width  = get_option( 'crypto_chart_width', 500 );
	$height = get_option( 'crypto_chart_height', 200 );

	$style = sprintf(
		'width:%dpx; height:%dpx; position:relative;',
		absint( $width ),
		absint( $height )
	);

	return sprintf(
		'<div style="%s"><canvas id="tokenChart"></canvas></div>',
		esc_attr( $style )
	);
}
add_shortcode( 'crypto_chart', 'crypto_chart_shortcode' );