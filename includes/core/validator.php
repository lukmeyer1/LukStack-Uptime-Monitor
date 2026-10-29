<?php
/**
 * Validation functions
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Check if host is blocked (localhost, private IPs, DNS rebinding)
 *
 * @param string $host Hostname or IP
 * @return bool True if blocked
 */
function lukstack_is_blocked_host($host) {
	$host = strtolower($host);

	// Check blocked hostnames
	if (in_array($host, LUKSTACK_BLOCKED_HOSTS, true)) {
		return true;
	}

	// Check if it's a direct IP address
	if (filter_var($host, FILTER_VALIDATE_IP)) {
		// Block private and reserved ranges
		if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			return true;
		}
	}

	// Resolve DNS and check resolved IPs to prevent DNS rebinding attacks
	$resolved_ips = gethostbynamel($host);
	if ($resolved_ips) {
		foreach ($resolved_ips as $ip) {
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Validate URL
 *
 * @param string $url URL to validate
 * @return array Validation result with 'valid' and 'message' or 'url'
 */
function lukstack_validate_url($url) {
	if (empty($url)) {
		return array(
			'valid' => false,
			'message' => __('URL cannot be empty.', 'lukstack-uptime-monitor')
		);
	}

	$url = trim($url);

	if (!preg_match('/^https?:\/\//i', $url)) {
		return array(
			'valid' => false,
			'message' => __('URL must start with http:// or https://', 'lukstack-uptime-monitor')
		);
	}

	$url = esc_url_raw($url);

	if (empty($url)) {
		return array(
			'valid' => false,
			'message' => __('URL contains invalid characters.', 'lukstack-uptime-monitor')
		);
	}

	$parsed = wp_parse_url($url);
	if (!isset($parsed['host'])) {
		return array(
			'valid' => false,
			'message' => __('URL must have a valid domain name.', 'lukstack-uptime-monitor')
		);
	}

	if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $parsed['host'])) {
		return array(
			'valid' => false,
			'message' => __('Invalid domain name format.', 'lukstack-uptime-monitor')
		);
	}

	// Use shared function for blocked host check
	if (lukstack_is_blocked_host($parsed['host'])) {
		return array(
			'valid' => false,
			'message' => __('Cannot monitor localhost or private IP addresses.', 'lukstack-uptime-monitor')
		);
	}

	if (lukstack_url_exists($url)) {
		return array(
			'valid' => false,
			'message' => __('This URL is already being monitored.', 'lukstack-uptime-monitor')
		);
	}

	if (strlen($url) > LUKSTACK_URL_MAX_LENGTH) {
		return array(
			'valid' => false,
			'message' => __('URL is too long (maximum 255 characters).', 'lukstack-uptime-monitor')
		);
	}

	return array(
		'valid' => true,
		'url' => $url
	);
}

/**
 * Validate email
 *
 * @param string $email Email to validate
 * @return array Validation result
 */
function lukstack_validate_email($email) {
	if (empty($email)) {
		return array(
			'valid' => true,
			'email' => ''
		);
	}

	$email = sanitize_email(trim($email));

	if (!is_email($email)) {
		return array(
			'valid' => false,
			'message' => __('Please enter a valid email address.', 'lukstack-uptime-monitor')
		);
	}

	if (strlen($email) > LUKSTACK_EMAIL_MAX_LENGTH) {
		return array(
			'valid' => false,
			'message' => __('Email address is too long (maximum 100 characters).', 'lukstack-uptime-monitor')
		);
	}

	$email_parts = explode('@', $email);
	if (isset($email_parts[1]) && in_array(strtolower($email_parts[1]), LUKSTACK_DISPOSABLE_DOMAINS)) {
		return array(
			'valid' => false,
			'message' => __('Disposable email addresses are not allowed.', 'lukstack-uptime-monitor')
		);
	}

	return array(
		'valid' => true,
		'email' => $email
	);
}

/**
 * Validate webhook URL
 *
 * @param string $url Webhook URL to validate
 * @return array Validation result
 */
function lukstack_validate_webhook_url($url) {
	if (empty($url)) {
		return array(
			'valid' => true,
			'url' => ''
		);
	}

	$url = trim($url);

	if (!filter_var($url, FILTER_VALIDATE_URL)) {
		return array(
			'valid' => false,
			'message' => __('Please enter a valid webhook URL.', 'lukstack-uptime-monitor')
		);
	}

	if (!preg_match('/^https?:\/\//i', $url)) {
		return array(
			'valid' => false,
			'message' => __('Webhook URL must start with http:// or https://', 'lukstack-uptime-monitor')
		);
	}

	$parsed = wp_parse_url($url);
	if (!isset($parsed['host'])) {
		return array(
			'valid' => false,
			'message' => __('Webhook URL must have a valid domain.', 'lukstack-uptime-monitor')
		);
	}

	// Use shared function for blocked host check
	if (lukstack_is_blocked_host($parsed['host'])) {
		return array(
			'valid' => false,
			'message' => __('Cannot use localhost or private IPs for webhook URL.', 'lukstack-uptime-monitor')
		);
	}

	if (strlen($url) > LUKSTACK_WEBHOOK_MAX_LENGTH) {
		return array(
			'valid' => false,
			'message' => __('Webhook URL is too long (maximum 500 characters).', 'lukstack-uptime-monitor')
		);
	}

	$result = array(
		'valid' => true,
		'url' => esc_url_raw($url)
	);

	if (strpos($url, 'https://') !== 0) {
		$result['warning'] = __('HTTPS is recommended for webhook URLs for better security.', 'lukstack-uptime-monitor');
	}

	return $result;
}

/**
 * Sanitize settings array
 *
 * @param array $settings Settings to sanitize
 * @return array Sanitized settings
 */
function lukstack_sanitize_settings($settings) {
	$sanitized = array();

	if (isset($settings['webhook_url'])) {
		$webhook_validation = lukstack_validate_webhook_url($settings['webhook_url']);
		$sanitized['webhook_url'] = $webhook_validation['valid'] ? $webhook_validation['url'] : '';
	}

	if (isset($settings['check_interval'])) {
		$interval = intval($settings['check_interval']);
		$min_interval = lukstack_get_plan_check_interval();
		$sanitized['check_interval'] = max( $min_interval, min( 60, $interval ) );
	}

	if (isset($settings['notification_cooldown'])) {
		$cooldown = intval($settings['notification_cooldown']);
		$sanitized['notification_cooldown'] = max(1, min(168, $cooldown));
	}

	return $sanitized;
}