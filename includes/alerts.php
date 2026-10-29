<?php
/**
 * Alert handling functions with Discord/Slack support
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handle status change alerts
 */
add_action('lukstack_status_changed', 'lukstack_handle_status_change', 10, 4);

function lukstack_handle_status_change($id, $url, $old_status, $new_status) {
	// Validate inputs
	if (empty($id) || empty($url) || empty($new_status)) {
		lukstack_log('Invalid parameters for status change alert', 'error');
		return;
	}

	try {
		// Get site data
		$site = lukstack_get_site($id);

		if (!$site) {
			lukstack_log('Site not found for status change alert: ID ' . $id, 'error');
			return;
		}

		// Build email subject and body
		$site_name = lukstack_get_site_name($url);
		$subject = sprintf(
			__('[LukStack Uptime Monitor] Status Alert: %s', 'lukstack-uptime-monitor'),
			$site_name
		);

		// Determine alert severity
		$severity = 'INFO';
		if ($new_status === 'DOWN') {
			$severity = 'CRITICAL';
		} elseif (strpos($new_status, 'ERROR') !== false) {
			$severity = 'ERROR';
		} elseif ($new_status === 'UP' && ($old_status === 'DOWN' || strpos($old_status, 'ERROR') !== false)) {
			$severity = 'RESOLVED';
		}

		$body = lukstack_build_status_alert_email($site, $old_status, $new_status, $severity);

		// Send email
		$email_sent = lukstack_send_email_alert($site, $subject, $body);

		// Send webhook notification
		$webhook_sent = lukstack_send_webhook_alert($id, $url, $old_status, $new_status, $severity);

		// Log the alert
		lukstack_log(sprintf(
			'Status change alert sent for %s - Status: %s → %s (Email: %s, Webhook: %s)',
			$url,
			$old_status ?: 'UNKNOWN',
			$new_status,
			$email_sent ? 'sent' : 'failed',
			$webhook_sent ? 'sent' : 'skipped/failed'
		));

	} catch (Exception $e) {
		lukstack_log('Exception in status change alert - ' . $e->getMessage(), 'error');
	}
}

/**
 * Build status change email body
 */
function lukstack_build_status_alert_email($site, $old_status, $new_status, $severity) {
	$lines = array();

	$lines[] = sprintf(__('Website Monitoring Alert - %s', 'lukstack-uptime-monitor'), strtoupper($severity));
	$lines[] = '';
	$lines[] = sprintf(__('Website: %s', 'lukstack-uptime-monitor'), $site->url);
	$lines[] = sprintf(__('Previous Status: %s', 'lukstack-uptime-monitor'), $old_status ?: __('UNKNOWN', 'lukstack-uptime-monitor'));
	$lines[] = sprintf(__('Current Status: %s', 'lukstack-uptime-monitor'), $new_status);
	$lines[] = sprintf(__('Time: %s', 'lukstack-uptime-monitor'), current_time('mysql'));

	// Add performance data if available (Pro feature)
	if ( lukstack_can_use_performance_tracking() && $site->response_time !== null ) {
		$lines[] = sprintf(__('Response Time: %s ms', 'lukstack-uptime-monitor'), number_format($site->response_time, 0));
	}

	// Add SSL info if available (Pro feature)
	if ( lukstack_can_use_ssl_monitoring() && $site->ssl_days_remaining !== null ) {
		$lines[] = sprintf(__('SSL Days Remaining: %d days', 'lukstack-uptime-monitor'), $site->ssl_days_remaining);
	}

	$lines[] = '';
	$lines[] = '---';
	$lines[] = sprintf(
		__('This is an automated message from LukStack Uptime Monitor on %s', 'lukstack-uptime-monitor'),
		get_bloginfo('name')
	);
	$lines[] = sprintf(
		__('Manage monitoring: %s', 'lukstack-uptime-monitor'),
		lukstack_admin_url()
	);

	return implode("\n", $lines);
}

/**
 * Send email alert
 */
function lukstack_send_email_alert($site, $subject, $body) {
	// Determine recipient
	$to = lukstack_get_alert_recipient($site);

	if (!$to) {
		lukstack_log('No valid email address for alert', 'error');
		return false;
	}

	// Prepare headers
	$headers = lukstack_get_email_headers();

	// Send email
	$sent = wp_mail($to, $subject, $body, $headers);

	if (!$sent) {
		lukstack_log('Failed to send email alert to ' . $to, 'error');
	}

	return $sent;
}

/**
 * Send webhook alert for status change
 */
function lukstack_send_webhook_alert($id, $url, $old_status, $new_status, $severity) {
	$webhook_url = lukstack_get_setting('webhook_url');

	if (empty($webhook_url)) {
		return false; // No webhook configured
	}

	$data = array(
		'type' => 'status_change',
		'severity' => $severity,
		'site_id' => absint($id),
		'url' => $url,
		'old_status' => $old_status ?: 'UNKNOWN',
		'new_status' => $new_status,
		'timestamp' => current_time('mysql'),
		'unix_timestamp' => time(),
		'site_name' => get_bloginfo('name')
	);

	return lukstack_send_webhook($webhook_url, $data);
}

/**
 * Handle SSL expiring soon alerts
 */
add_action('lukstack_ssl_expiring_soon', 'lukstack_handle_ssl_expiring', 10, 3);

function lukstack_handle_ssl_expiring($id, $url, $days_remaining) {
	// SSL monitoring is a Pro feature.
	if ( ! lukstack_can_use_ssl_monitoring() ) {
		return;
	}

	// Validate inputs
	if (empty($id) || empty($url) || !is_numeric($days_remaining)) {
		lukstack_log('Invalid parameters for SSL expiring alert', 'error');
		return;
	}

	try {
		// Get site data
		$site = lukstack_get_site($id);

		if (!$site) {
			lukstack_log('Site not found for SSL expiring alert: ID ' . $id, 'error');
			return;
		}

		// Check if we already sent a warning recently (don't spam)
		$transient_key = 'ssl_warning_' . $id;
		$last_warning = lukstack_get_transient($transient_key);

		if ($last_warning) {
			return; // Already sent warning in last 24 hours
		}

		// Build email
		$urgency = $days_remaining <= LUKSTACK_SSL_CRITICAL_DAYS ? 'URGENT' : 'WARNING';
		$site_name = lukstack_get_site_name($url);

		$subject = sprintf(
			__('[LukStack Uptime Monitor] %s: SSL Certificate Expiring - %s', 'lukstack-uptime-monitor'),
			$urgency,
			$site_name
		);

		$body = lukstack_build_ssl_alert_email($site, $url, $days_remaining, $urgency);

		// Send email
		$email_sent = lukstack_send_email_alert($site, $subject, $body);

		// Send webhook
		$webhook_sent = lukstack_send_ssl_webhook_alert($id, $url, $days_remaining, $site, $urgency);

		// Set transient to prevent spam (24 hours)
		lukstack_set_transient($transient_key, true, DAY_IN_SECONDS);

		// Log the alert
		lukstack_log(sprintf(
			'SSL expiring alert sent for %s - %d days remaining (Email: %s, Webhook: %s)',
			$url,
			$days_remaining,
			$email_sent ? 'sent' : 'failed',
			$webhook_sent ? 'sent' : 'skipped/failed'
		));

	} catch (Exception $e) {
		lukstack_log('Exception in SSL expiring alert - ' . $e->getMessage(), 'error');
	}
}

/**
 * Build SSL expiring email body
 */
function lukstack_build_ssl_alert_email($site, $url, $days_remaining, $urgency) {
	$lines = array();

	$lines[] = sprintf(__('SSL Certificate Expiration %s', 'lukstack-uptime-monitor'), $urgency);
	$lines[] = '';
	$lines[] = sprintf(__('Website: %s', 'lukstack-uptime-monitor'), $url);
	$lines[] = sprintf(__('Days Remaining: %d days', 'lukstack-uptime-monitor'), $days_remaining);

	if ($site->ssl_expiry_date) {
		$lines[] = sprintf(
			__('Expiry Date: %s', 'lukstack-uptime-monitor'),
			date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($site->ssl_expiry_date))
		);
	}

	if ($site->ssl_issuer) {
		$lines[] = sprintf(__('Issuer: %s', 'lukstack-uptime-monitor'), $site->ssl_issuer);
	}

	$lines[] = sprintf(__('Urgency: %s', 'lukstack-uptime-monitor'), $urgency);
	$lines[] = '';
	$lines[] = __('Please renew your SSL certificate as soon as possible to avoid service interruption.', 'lukstack-uptime-monitor');
	$lines[] = '';
	$lines[] = '---';
	$lines[] = sprintf(
		__('This is an automated message from LukStack Uptime Monitor on %s', 'lukstack-uptime-monitor'),
		get_bloginfo('name')
	);

	return implode("\n", $lines);
}

/**
 * Send webhook for SSL expiring
 */
function lukstack_send_ssl_webhook_alert($id, $url, $days_remaining, $site, $urgency) {
	$webhook_url = lukstack_get_setting('webhook_url');

	if (empty($webhook_url)) {
		return false;
	}

	$data = array(
		'type' => 'ssl_expiring',
		'severity' => $urgency,
		'site_id' => absint($id),
		'url' => $url,
		'days_remaining' => intval($days_remaining),
		'expiry_date' => $site->ssl_expiry_date,
		'issuer' => $site->ssl_issuer,
		'urgency' => $urgency,
		'timestamp' => current_time('mysql'),
		'unix_timestamp' => time(),
		'site_name' => get_bloginfo('name')
	);

	return lukstack_send_webhook($webhook_url, $data);
}

/**
 * Send webhook notification with Discord/Slack support
 */
function lukstack_send_webhook($webhook_url, $data) {
	// Validate webhook URL
	if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
		lukstack_log('Invalid webhook URL', 'error');
		return false;
	}

	// Format payload based on webhook service
	$payload = lukstack_format_webhook_payload($webhook_url, $data);

	// Send request
	$response = wp_remote_post($webhook_url, array(
		'timeout' => LUKSTACK_WEBHOOK_TIMEOUT,
		'headers' => array(
			'Content-Type' => 'application/json',
			'User-Agent' => LUKSTACK_USER_AGENT
		),
		'body' => wp_json_encode($payload),
		'sslverify' => true
	));

	// Check for errors
	if (is_wp_error($response)) {
		lukstack_log('Webhook error - ' . $response->get_error_message(), 'error');
		return false;
	}

	// Check response code
	$code = wp_remote_retrieve_response_code($response);

	if ($code < 200 || $code >= 300) {
		lukstack_log('Webhook failed with HTTP ' . $code, 'error');

		// Log response body in debug mode
		if (lukstack_is_debug()) {
			$body = wp_remote_retrieve_body($response);
			lukstack_log('Webhook response: ' . substr($body, 0, 500));
		}

		return false;
	}

	return true;
}

/**
 * Format webhook payload based on service type
 */
function lukstack_format_webhook_payload($webhook_url, $data) {
	// Detect service by URL pattern
	if (strpos($webhook_url, 'discord.com') !== false || strpos($webhook_url, 'discordapp.com') !== false) {
		return lukstack_format_discord_payload($data);
	}

	if (strpos($webhook_url, 'slack.com') !== false) {
		return lukstack_format_slack_payload($data);
	}

	// Generic webhook (Zapier, Make.com, etc.)
	return lukstack_format_generic_payload($data);
}

/**
 * Format Discord webhook payload with rich embeds
 */
function lukstack_format_discord_payload($data) {
	// Determine color based on severity
	$color = LUKSTACK_DISCORD_COLOR_BLUE; // Default info

	if ($data['severity'] === 'CRITICAL') {
		$color = LUKSTACK_DISCORD_COLOR_RED;
	} elseif ($data['severity'] === 'WARNING' || $data['severity'] === 'URGENT') {
		$color = LUKSTACK_DISCORD_COLOR_ORANGE;
	} elseif ($data['severity'] === 'RESOLVED') {
		$color = LUKSTACK_DISCORD_COLOR_GREEN;
	}

	$embed = array(
		'title' => '🔔 Website Monitoring Alert',
		'color' => $color,
		'timestamp' => gmdate('c', $data['unix_timestamp']),
		'footer' => array(
			'text' => 'LukStack Uptime Monitor v' . LUKSTACK_VERSION
		),
		'fields' => array()
	);

	// Add fields based on alert type
	if ($data['type'] === 'status_change') {
		$embed['description'] = '**' . $data['url'] . '**';
		$embed['fields'][] = array(
			'name' => 'Status Change',
			'value' => $data['old_status'] . ' → **' . $data['new_status'] . '**',
			'inline' => false
		);
		$embed['fields'][] = array(
			'name' => 'Severity',
			'value' => $data['severity'],
			'inline' => true
		);
	} elseif ($data['type'] === 'ssl_expiring') {
		$embed['description'] = '**SSL Certificate Expiring**';
		$embed['fields'][] = array(
			'name' => 'Website',
			'value' => $data['url'],
			'inline' => false
		);
		$embed['fields'][] = array(
			'name' => 'Days Remaining',
			'value' => $data['days_remaining'] . ' days',
			'inline' => true
		);
		if (!empty($data['expiry_date'])) {
			$embed['fields'][] = array(
				'name' => 'Expires',
				'value' => $data['expiry_date'],
				'inline' => true
			);
		}
		if (!empty($data['issuer'])) {
			$embed['fields'][] = array(
				'name' => 'Issuer',
				'value' => $data['issuer'],
				'inline' => true
			);
		}
	} elseif ($data['type'] === 'test') {
		$embed['title'] = '✅ Test Notification';
		$embed['description'] = $data['message'];
		$embed['fields'][] = array(
			'name' => 'From',
			'value' => $data['site'],
			'inline' => true
		);
	}

	return array('embeds' => array($embed));
}

/**
 * Format Slack webhook payload with attachments
 */
function lukstack_format_slack_payload($data) {
	// Determine color
	$color = 'good'; // green

	if ($data['severity'] === 'CRITICAL') {
		$color = 'danger'; // red
	} elseif ($data['severity'] === 'WARNING' || $data['severity'] === 'URGENT') {
		$color = 'warning'; // orange
	}

	$attachment = array(
		'color' => $color,
		'footer' => 'LukStack Uptime Monitor v' . LUKSTACK_VERSION,
		'ts' => $data['unix_timestamp'],
		'fields' => array()
	);

	if ($data['type'] === 'status_change') {
		$attachment['title'] = '🔔 Website Monitoring Alert';
		$attachment['text'] = '*' . $data['url'] . '*';
		$attachment['fields'][] = array(
			'title' => 'Status Change',
			'value' => $data['old_status'] . ' → *' . $data['new_status'] . '*',
			'short' => false
		);
		$attachment['fields'][] = array(
			'title' => 'Severity',
			'value' => $data['severity'],
			'short' => true
		);
	} elseif ($data['type'] === 'ssl_expiring') {
		$attachment['title'] = '⚠️ SSL Certificate Expiring';
		$attachment['text'] = '*' . $data['url'] . '*';
		$attachment['fields'][] = array(
			'title' => 'Days Remaining',
			'value' => $data['days_remaining'] . ' days',
			'short' => true
		);
		if (!empty($data['expiry_date'])) {
			$attachment['fields'][] = array(
				'title' => 'Expires',
				'value' => $data['expiry_date'],
				'short' => true
			);
		}
	} elseif ($data['type'] === 'test') {
		$attachment['title'] = '✅ Test Notification';
		$attachment['text'] = $data['message'];
		$attachment['fields'][] = array(
			'title' => 'From',
			'value' => $data['site'],
			'short' => true
		);
	}

	return array('attachments' => array($attachment));
}

/**
 * Format generic webhook payload
 */
function lukstack_format_generic_payload($data) {
	// Add common data
	$data['plugin_version'] = LUKSTACK_VERSION;
	$data['site_url'] = get_site_url();

	return $data;
}

/**
 * Test webhook connection
 */
function lukstack_test_webhook($webhook_url) {
	if (empty($webhook_url)) {
		return new WP_Error('empty_url', __('Webhook URL is empty', 'lukstack-uptime-monitor'));
	}

	$test_data = array(
		'type' => 'test',
		'severity' => 'INFO',
		'message' => 'Test notification from LukStack Uptime Monitor',
		'site' => get_bloginfo('name'),
		'site_url' => get_site_url(),
		'timestamp' => current_time('mysql'),
		'unix_timestamp' => time(),
		'user' => lukstack_get_user_name()
	);

	$result = lukstack_send_webhook($webhook_url, $test_data);

	if (!$result) {
		return new WP_Error('webhook_failed', __('Webhook test failed', 'lukstack-uptime-monitor'));
	}

	return true;
}