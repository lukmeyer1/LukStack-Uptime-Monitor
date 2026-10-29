<?php
/**
 * Main admin page (Dashboard) with security and error handling
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Render main admin page
 */
function lukstack_main_page() {
	lukstack_verify_admin_access();

	$error_message = '';
	$success_message = '';

	// Handle form submission
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
		if (!check_admin_referer('lukstack_add_site', '_wpnonce', false)) {
			$error_message = __('Security check failed. Please try again.', 'lukstack-uptime-monitor');
		} else {
			// Check site limit before processing.
			if ( ! lukstack_can_add_site() ) {
				$error_message = sprintf(
					/* translators: %1$d: site limit, %2$d: current count */
					__( 'You have reached the maximum of %1$d monitored websites on the %2$s plan. Upgrade to Pro to monitor up to %3$d websites.', 'lukstack-uptime-monitor' ),
					lukstack_get_site_limit(),
					lukstack_get_current_plan_name(),
					LUKSTACK_PRO_SITE_LIMIT
				);
			} else {
				$url = isset($_POST['url']) ? sanitize_text_field(wp_unslash($_POST['url'])) : '';
				$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

				// Validate URL
				$url_validation = lukstack_validate_url($url);

				if (!$url_validation['valid']) {
					$error_message = $url_validation['message'];
				} else {
					// Validate email
					$email_validation = lukstack_validate_email($email);

					if (!$email_validation['valid']) {
						$error_message = $email_validation['message'];
					} else {
						// Add site using database function
						$new_id = lukstack_add_site(
							$url_validation['url'],
							$email_validation['email']
						);

						if ($new_id) {
							$success_message = sprintf(
								__('Website added successfully! (ID: %d)', 'lukstack-uptime-monitor'),
								$new_id
							);
							lukstack_log(sprintf(
								'Site added by user %d - ID: %d, URL: %s',
								get_current_user_id(),
								$new_id,
								$url_validation['url']
							));
						} else {
							$error_message = __('Database error: Could not add website.', 'lukstack-uptime-monitor');
						}
					}
				}
			}
		}
	}

	// Get data for view
	$sites = lukstack_get_sites(array('orderby' => 'id', 'order' => 'DESC'));
	$stats = lukstack_get_stats();
	$cron_status = lukstack_get_cron_status();

	include LUKSTACK_PLUGIN_DIR . 'includes/admin/views/main-page-view.php';
}