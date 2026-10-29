<?php
/**
 * AJAX handlers for admin
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Validate and get site ID from POST request
 *
 * Note: Nonce and capability checks must be performed by the calling function
 * before invoking this helper.
 *
 * @return int|null Site ID or null (sends JSON error and exits on failure)
 */
function lukstack_ajax_get_site_id() {
	if (empty($_POST['id'])) {
		wp_send_json_error(array(
			'message' => __('Site ID is required.', 'lukstack-uptime-monitor')
		), 400);
	}

	$id = lukstack_sanitize_id(wp_unslash($_POST['id']));

	if (!$id) {
		wp_send_json_error(array(
			'message' => __('Invalid site ID.', 'lukstack-uptime-monitor')
		), 400);
	}

	return $id;
}

/**
 * AJAX: Check website now
 */
add_action('wp_ajax_lukstack_check_now', 'lukstack_ajax_check_now');

function lukstack_ajax_check_now() {
	lukstack_verify_admin_access(true);
	lukstack_verify_ajax_nonce();

	$id = lukstack_ajax_get_site_id();

	$site = lukstack_get_site($id);
	if (!$site) {
		wp_send_json_error(array(
			'message' => __('Website not found in database.', 'lukstack-uptime-monitor')
		), 404);
	}

	$check_result = lukstack_check_and_update_site($id, $site->url);

	if ($check_result === false) {
		wp_send_json_error(array(
			'message' => __('Error checking website.', 'lukstack-uptime-monitor')
		), 500);
	}

	wp_send_json_success(array(
		'status'             => $check_result['status'],
		'response_time'      => $check_result['response_time'],
		'ssl_days_remaining' => $check_result['ssl_days_remaining'],
		'ssl_expiry_date'    => $check_result['ssl_expiry_date'],
		'checked'            => current_time('mysql'),
		'status_html'        => lukstack_render_status( $check_result['status'] ),
		'response_time_html' => lukstack_can_use_performance_tracking()
			? lukstack_render_response_time( $check_result['response_time'] )
			: '',
		'ssl_html'           => lukstack_can_use_ssl_monitoring()
			? lukstack_render_ssl_expiry( $check_result['ssl_days_remaining'], $check_result['ssl_expiry_date'] )
			: '',
		'message'            => __( 'Website checked successfully!', 'lukstack-uptime-monitor' ),
	));
}

/**
 * AJAX: Delete website
 */
add_action('wp_ajax_lukstack_delete', 'lukstack_ajax_delete');

function lukstack_ajax_delete() {
	lukstack_verify_admin_access(true);
	lukstack_verify_ajax_nonce();

	$id = lukstack_ajax_get_site_id();

	$site = lukstack_get_site($id);
	if (!$site) {
		wp_send_json_error(array(
			'message' => __('Website not found.', 'lukstack-uptime-monitor')
		), 404);
	}

	if (!lukstack_delete_site($id)) {
		wp_send_json_error(array(
			'message' => __('Database error: Could not delete website.', 'lukstack-uptime-monitor')
		), 500);
	}

	lukstack_log(sprintf(
		'Site deleted by user %d - ID: %d, URL: %s',
		get_current_user_id(),
		$id,
		$site->url
	));

	wp_send_json_success(array(
		'message' => __('Website deleted successfully.', 'lukstack-uptime-monitor')
	));
}

/**
 * AJAX: Test webhook
 */
add_action('wp_ajax_lukstack_test_webhook', 'lukstack_ajax_test_webhook');

function lukstack_ajax_test_webhook() {
	lukstack_verify_admin_access(true);
	lukstack_verify_ajax_nonce();

	$webhook_url = lukstack_get_setting('webhook_url');

	if (empty($webhook_url)) {
		wp_send_json_error(array(
			'message' => __('No webhook URL configured. Please save a webhook URL in settings first.', 'lukstack-uptime-monitor')
		), 400);
	}

	$result = lukstack_test_webhook($webhook_url);

	if (is_wp_error($result)) {
		wp_send_json_error(array(
			'message' => $result->get_error_message()
		), 500);
	}

	wp_send_json_success(array(
		'message' => __('Test notification sent successfully!', 'lukstack-uptime-monitor')
	));
}

/**
 * AJAX: Bulk check all sites
 */
add_action('wp_ajax_lukstack_bulk_check', 'lukstack_ajax_bulk_check');

function lukstack_ajax_bulk_check() {
	lukstack_verify_admin_access(true);
	lukstack_verify_ajax_nonce();

	$result = lukstack_manual_check_all();

	if (is_wp_error($result)) {
		wp_send_json_error(array(
			'message' => $result->get_error_message()
		), 500);
	}

	if (!$result['success']) {
		wp_send_json_error(array(
			'message' => $result['message']
		), 400);
	}

	wp_send_json_success($result);
}