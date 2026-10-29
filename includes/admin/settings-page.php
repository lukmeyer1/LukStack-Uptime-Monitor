<?php
/**
 * Settings page with proper security and validation
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Render settings page
 */
function lukstack_settings_page() {
	// Security check
	lukstack_verify_admin_access();

	$error_message = '';
	$success_message = '';

	// Handle form submission
	if (isset($_POST['save_settings'])) {
		// Verify nonce
		if (!check_admin_referer('lukstack_settings')) {
			$error_message = __('Security check failed. Please try again.', 'lukstack-uptime-monitor');
		} else {
			// Process settings
			$result = lukstack_process_settings_form();

			if (is_wp_error($result)) {
				$error_message = $result->get_error_message();
			} elseif ($result === true) {
				$success_message = __('Settings saved successfully!', 'lukstack-uptime-monitor');
			}
		}
	}

	// Handle "Force re-register" button (debug only).
	if ( isset( $_POST['lukstack_force_register'] ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		if ( ! check_admin_referer( 'lukstack_force_register' ) ) {
			$error_message = __( 'Security check failed. Please try again.', 'lukstack-uptime-monitor' );
		} elseif ( function_exists( 'lukstack_register_with_cron_server' ) ) {
			$registered = lukstack_register_with_cron_server();
			if ( $registered ) {
				$success_message = __( 'Re-registration successful.', 'lukstack-uptime-monitor' );
			} else {
				$status = lukstack_get_registration_status();
				$error_message = sprintf(
					/* translators: %s: error details */
					__( 'Re-registration failed: %s', 'lukstack-uptime-monitor' ),
					isset( $status['error'] ) ? $status['error'] : 'unknown error'
				);
			}
		}
	}

	// Get current settings
	$webhook_url = lukstack_get_setting('webhook_url');

	// Get cron status
	$cron_status = lukstack_get_cron_status();

	// Render view
	include LUKSTACK_PLUGIN_DIR . 'includes/admin/views/settings-page-view.php';
}

/**
 * Process settings form submission
 *
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function lukstack_process_settings_form() {
	// Verify nonce (also checked in caller, but needed here for PHPCS)
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'lukstack_settings')) {
		return new WP_Error('security_failed', __('Security check failed.', 'lukstack-uptime-monitor'));
	}

	// Get and validate webhook URL
	$webhook_url = isset($_POST['webhook_url']) ? sanitize_text_field(wp_unslash($_POST['webhook_url'])) : '';

	// Allow empty URL (to disable webhook)
	if (!empty($webhook_url)) {
		$validation = lukstack_validate_webhook_url($webhook_url);

		if (!$validation['valid']) {
			return new WP_Error('invalid_webhook', $validation['message']);
		}

		$webhook_url = $validation['url'];
	}

	// Prepare new settings
	$new_settings = array(
		'webhook_url' => $webhook_url,
		'check_interval' => lukstack_get_plan_check_interval(),
		'notification_cooldown' => LUKSTACK_DEFAULT_COOLDOWN,
		'version' => LUKSTACK_VERSION
	);

	// Update settings
	update_option('lukstack_settings', $new_settings);

	// Force refresh the settings cache
	lukstack_get_settings(true);

	// Log the update
	lukstack_log(sprintf(
		'Settings updated by user %d - Webhook: %s',
		get_current_user_id(),
		!empty($webhook_url) ? 'configured' : 'disabled'
	));

	return true;
}