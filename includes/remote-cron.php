<?php
/**
 * Remote Cron REST Endpoint
 *
 * Allows an external cron server (e.g. AWS Lambda) to trigger site checks
 * via HTTPS. Authenticated via a per-install shared secret.
 *
 * The plan-level check interval (see freemius-plan.php) still gates how
 * often each site actually gets checked, so this endpoint can safely be
 * called more often than the plan allows — surplus calls become cheap
 * no-op DB queries.
 *
 * @package LukStack_Uptime_Monitor
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LUKSTACK_REMOTE_CRON_NAMESPACE', 'lukstack/v1' );
define( 'LUKSTACK_REMOTE_CRON_ROUTE', '/trigger' );
define( 'LUKSTACK_REMOTE_CRON_SECRET_OPTION', 'lukstack_remote_cron_secret' );
define( 'LUKSTACK_REMOTE_CRON_SECRET_LENGTH', 48 );

/**
 * URL of the central AWS cron server that orchestrates monitoring triggers.
 * Change here if the backend ever moves.
 */
define( 'LUKSTACK_CRON_SERVER_URL', 'https://gt5zheubf8.execute-api.eu-central-1.amazonaws.com' );
define( 'LUKSTACK_REGISTRATION_OPTION', 'lukstack_cron_server_registration' );
define( 'LUKSTACK_REGISTRATION_RETRY_BASE', 5 * MINUTE_IN_SECONDS );
define( 'LUKSTACK_REGISTRATION_RETRY_MAX', HOUR_IN_SECONDS );
define( 'LUKSTACK_REGISTRATION_TIMEOUT', 15 );

/**
 * Register REST route.
 *
 * @since 2.2.0
 */
add_action( 'rest_api_init', 'lukstack_register_remote_cron_routes' );

function lukstack_register_remote_cron_routes() {
	register_rest_route(
		LUKSTACK_REMOTE_CRON_NAMESPACE,
		LUKSTACK_REMOTE_CRON_ROUTE,
		array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => 'lukstack_remote_cron_handler',
			'permission_callback' => 'lukstack_remote_cron_permission',
		)
	);
}

/**
 * Permission callback – checks shared secret in X-LukStack-Secret header.
 *
 * Uses hash_equals() for constant-time comparison to prevent timing attacks.
 *
 * @since 2.2.0
 *
 * @param WP_REST_Request $request Incoming request.
 * @return bool|WP_Error True if authenticated, WP_Error otherwise.
 */
function lukstack_remote_cron_permission( $request ) {
	$expected = lukstack_get_or_create_remote_cron_secret();
	$provided = (string) $request->get_header( 'x-lukstack-secret' );

	if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
		lukstack_log( 'Remote cron: invalid or missing secret from ' . lukstack_get_request_ip(), 'warning' );
		return new WP_Error(
			'lukstack_forbidden',
			__( 'Invalid or missing secret.', 'lukstack-uptime-monitor' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Handle remote cron trigger.
 *
 * Runs the same batch logic as the WP-Cron event and returns a JSON summary.
 *
 * @since 2.2.0
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response
 */
function lukstack_remote_cron_handler( $request ) {
	$start = microtime( true );

	// Reuse existing cron logic – lock-protected, plan-gated.
	lukstack_run_checks();

	$status       = lukstack_get_cron_status();
	$duration_s   = round( microtime( true ) - $start, 2 );
	$last_run     = isset( $status['last_run'] ) ? $status['last_run'] : null;

	$response_body = array_merge(
		array(
			'success'        => true,
			'plan'           => lukstack_get_current_plan_name(),
			'check_interval' => lukstack_get_plan_check_interval(),
			'site_count'     => lukstack_get_site_count(),
			'site_limit'     => lukstack_get_site_limit(),
			'last_run'       => $last_run,
			'duration'       => $duration_s,
			'plugin_version' => LUKSTACK_VERSION,
		),
		lukstack_get_telemetry_payload()
	);

	return new WP_REST_Response( $response_body, 200 );
}

/**
 * Get or generate the shared secret for this install.
 *
 * Secret is created lazily on first access and persisted in wp_options.
 * It is autoload=false to avoid loading on every request.
 *
 * @since 2.2.0
 *
 * @return string 48-character alphanumeric secret.
 */
function lukstack_get_or_create_remote_cron_secret() {
	$secret = get_option( LUKSTACK_REMOTE_CRON_SECRET_OPTION );

	if ( empty( $secret ) || ! is_string( $secret ) || strlen( $secret ) < LUKSTACK_REMOTE_CRON_SECRET_LENGTH ) {
		$secret = wp_generate_password( LUKSTACK_REMOTE_CRON_SECRET_LENGTH, false, false );
		update_option( LUKSTACK_REMOTE_CRON_SECRET_OPTION, $secret, false );
	}

	return $secret;
}

/**
 * Rotate the shared secret. Used for security incident response.
 *
 * @since 2.2.0
 *
 * @return string New secret.
 */
function lukstack_rotate_remote_cron_secret() {
	$new_secret = wp_generate_password( LUKSTACK_REMOTE_CRON_SECRET_LENGTH, false, false );
	update_option( LUKSTACK_REMOTE_CRON_SECRET_OPTION, $new_secret, false );
	lukstack_log( 'Remote cron secret rotated' );
	return $new_secret;
}

/**
 * Best-effort client IP for logging.
 *
 * @since 2.2.0
 *
 * @return string
 */
function lukstack_get_request_ip() {
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		return trim( $parts[0] );
	}
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	return 'unknown';
}

// =============================================================================
// Auto-registration with central cron server
// =============================================================================

/**
 * Hook on admin_init – idempotently ensures this site is registered.
 *
 * Only registers if:
 *   - Not yet registered, OR
 *   - Site URL changed, OR
 *   - Secret changed
 * Throttled with exponential backoff (LUKSTACK_REGISTRATION_RETRY_BASE → LUKSTACK_REGISTRATION_RETRY_MAX).
 *
 * @since 2.2.0
 */
add_action( 'admin_init', 'lukstack_maybe_register_with_cron_server' );

function lukstack_maybe_register_with_cron_server() {
	// Only run for admins to avoid unnecessary traffic.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Skip non-production URLs (localhost, .test, .local, …). Avoids polluting
	// the cron-server DB with dev installs the operator can never trigger anyway.
	if ( lukstack_is_non_production_url( home_url() ) ) {
		return;
	}

	$registration   = get_option( LUKSTACK_REGISTRATION_OPTION, array() );
	$registration   = is_array( $registration ) ? $registration : array();
	$current_secret = lukstack_get_or_create_remote_cron_secret();
	$current_url    = home_url();
	$secret_hash    = hash( 'sha256', $current_secret );

	$already_registered = (
		isset( $registration['status'] )
		&& 'registered' === $registration['status']
		&& isset( $registration['site_url'] ) && $registration['site_url'] === $current_url
		&& isset( $registration['secret_hash'] ) && $registration['secret_hash'] === $secret_hash
		&& isset( $registration['plugin_version'] ) && $registration['plugin_version'] === LUKSTACK_VERSION
	);

	if ( $already_registered ) {
		return;
	}

	// Exponential backoff on repeated failures: 5min, 10min, 20min, 40min, …, capped at 1h.
	// Keeps transient network errors from causing hourly-only retries (previous behavior)
	// while preventing runaway retry loops for truly-broken installs.
	$last_attempt = isset( $registration['last_attempt_at'] ) ? (int) $registration['last_attempt_at'] : 0;
	$retry_count  = isset( $registration['retry_count'] ) ? (int) $registration['retry_count'] : 0;
	$backoff      = (int) min( LUKSTACK_REGISTRATION_RETRY_BASE * pow( 2, max( 0, $retry_count - 1 ) ), LUKSTACK_REGISTRATION_RETRY_MAX );
	if ( $retry_count > 0 && $last_attempt + $backoff > time() ) {
		return;
	}

	lukstack_register_with_cron_server();
}

/**
 * Perform the registration POST to the AWS cron server.
 *
 * @since 2.2.0
 *
 * @return bool True on success, false on failure.
 */
function lukstack_register_with_cron_server() {
	$site_url = home_url();

	if ( lukstack_is_non_production_url( $site_url ) ) {
		lukstack_log( 'Registration skipped: non-production URL (' . $site_url . ')' );
		return false;
	}

	$secret   = lukstack_get_or_create_remote_cron_secret();
	$endpoint = trailingslashit( LUKSTACK_CRON_SERVER_URL ) . 'v1/register';

	$body = array_merge(
		array(
			'site_url'       => $site_url,
			'secret'         => $secret,
			'plugin_version' => LUKSTACK_VERSION,
		),
		lukstack_get_telemetry_payload()
	);

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout'     => LUKSTACK_REGISTRATION_TIMEOUT,
			'redirection' => 0,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'        => wp_json_encode( $body ),
		)
	);

	$previous           = get_option( LUKSTACK_REGISTRATION_OPTION, array() );
	$previous           = is_array( $previous ) ? $previous : array();
	$previous_retries   = isset( $previous['retry_count'] ) ? (int) $previous['retry_count'] : 0;
	$registration_data  = array(
		'last_attempt_at' => time(),
		'retry_count'     => $previous_retries,
	);

	if ( is_wp_error( $response ) ) {
		$registration_data['status']      = 'failed';
		$registration_data['retry_count'] = $previous_retries + 1;
		$registration_data['error']       = $response->get_error_message();
		update_option( LUKSTACK_REGISTRATION_OPTION, $registration_data, false );
		lukstack_log( 'Cron server registration failed (network): ' . $response->get_error_message(), 'warning' );
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$body = is_array( $body ) ? $body : array();

	// Accept any 2xx as success — the cron server has evolved and may return
	// slightly different payload shapes across versions. A strict
	// empty($body['success']) check previously caused infinite retry loops.
	if ( $code < 200 || $code >= 300 ) {
		$registration_data['status']      = 'failed';
		$registration_data['retry_count'] = $previous_retries + 1;
		$registration_data['error']       = 'HTTP ' . $code . ' — ' . ( isset( $body['error'] ) ? $body['error'] : 'unknown' );
		update_option( LUKSTACK_REGISTRATION_OPTION, $registration_data, false );
		lukstack_log( 'Cron server registration failed: HTTP ' . $code, 'warning' );
		return false;
	}

	$registration_data['status']         = 'registered';
	$registration_data['site_id']        = isset( $body['site_id'] ) ? sanitize_text_field( $body['site_id'] ) : '';
	$registration_data['site_url']       = $site_url;
	$registration_data['secret_hash']    = hash( 'sha256', $secret );
	$registration_data['plan']           = isset( $body['plan'] ) ? sanitize_text_field( $body['plan'] ) : '';
	$registration_data['plugin_version'] = LUKSTACK_VERSION;
	$registration_data['retry_count']    = 0;
	$registration_data['registered_at']  = time();

	update_option( LUKSTACK_REGISTRATION_OPTION, $registration_data, false );
	lukstack_log( sprintf( 'Cron server registration successful: site_id=%s', $registration_data['site_id'] ) );
	return true;
}

/**
 * Best-effort deregister this site from the central cron server.
 *
 * Called from the Freemius `after_uninstall` hook so DynamoDB doesn't
 * accumulate zombie installs. Reads the secret via `get_option()` directly —
 * NOT via `lukstack_get_or_create_remote_cron_secret()`, because that would
 * mint a fresh secret for a site that's being torn down.
 *
 * Failures are silent (logged only) — a failed deregister falls back to the
 * cron server's existing "mark inactive after ~100 failed triggers" sweep.
 *
 * @since 2.3.0
 *
 * @return bool True if the HTTP call succeeded, false otherwise.
 */
function lukstack_deregister_from_cron_server() {
	$secret = get_option( LUKSTACK_REMOTE_CRON_SECRET_OPTION );

	if ( empty( $secret ) || ! is_string( $secret ) ) {
		// Site was never successfully registered — nothing to do.
		return false;
	}

	$endpoint = trailingslashit( LUKSTACK_CRON_SERVER_URL ) . 'v1/deregister';

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout'     => 5,
			'redirection' => 0,
			'blocking'    => true,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'        => wp_json_encode(
				array(
					'site_url' => home_url(),
					'secret'   => $secret,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		lukstack_log( 'Cron server deregister failed (network): ' . $response->get_error_message(), 'warning' );
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		lukstack_log( 'Cron server deregister failed: HTTP ' . $code, 'warning' );
		return false;
	}

	lukstack_log( 'Cron server deregister successful' );
	return true;
}

/**
 * Get current registration status (for debug/UI).
 *
 * @since 2.2.0
 *
 * @return array
 */
function lukstack_get_registration_status() {
	$registration = get_option( LUKSTACK_REGISTRATION_OPTION, array() );
	return is_array( $registration ) ? $registration : array();
}

/**
 * Detect non-production URLs that should never register with the cron server.
 *
 * Only matches unambiguous local/reserved hosts — staging on real TLDs (e.g.
 * `staging.example.com`) is intentionally NOT detected, because substring
 * heuristics produce false positives on legitimate production hostnames like
 * `dev.realcompany.com`. Sites that want to opt out anyway can override via
 * the `lukstack_skip_cron_registration` filter.
 *
 * @since 2.3.0
 *
 * @param string $url Site URL to evaluate.
 * @return bool True if the URL should NOT be registered with the cron server.
 */
function lukstack_is_non_production_url( $url ) {
	$parsed = wp_parse_url( $url );
	$host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';

	$skip = false;

	if ( '' === $host ) {
		$skip = true;
	} elseif ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		$skip = true;
	} else {
		$reserved_suffixes = array( '.local', '.test', '.localhost', '.example', '.invalid' );
		foreach ( $reserved_suffixes as $suffix ) {
			if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
				$skip = true;
				break;
			}
		}
	}

	return (bool) apply_filters( 'lukstack_skip_cron_registration', $skip, $url );
}
