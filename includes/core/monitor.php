<?php
/**
 * Core monitoring functions with robust error handling
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Check a single website with performance and SSL data
 *
 * @param string $url The URL to check
 * @return array Status and performance data
 */
function lukstack_check_website($url) {
	// Validate URL
	if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
		return lukstack_get_failed_check_result('Invalid URL');
	}

	$start_time = microtime(true);

	// Default result structure
	$result = array(
		'status' => 'DOWN',
		'response_time' => null,
		'ssl_expiry_date' => null,
		'ssl_issuer' => null,
		'ssl_days_remaining' => null,
		'error_message' => null
	);

	try {
		// Make HTTP request with proper error handling
		$response = wp_remote_get($url, array(
			'timeout' => LUKSTACK_HTTP_TIMEOUT,
			'redirection' => LUKSTACK_HTTP_MAX_REDIRECTS,
			'user-agent' => LUKSTACK_USER_AGENT . ' (' . get_bloginfo('url') . ')',
			'sslverify' => true,
			'blocking' => true,
			'headers' => array(
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
			)
		));

		$end_time = microtime(true);
		$response_time = round(($end_time - $start_time) * 1000, 2); // milliseconds

		// Check for WP_Error
		if (is_wp_error($response)) {
			$result['error_message'] = $response->get_error_message();
			$result['response_time'] = $response_time;
			lukstack_log(sprintf(
				'Check failed for %s - %s',
				$url,
				$result['error_message']
			), 'error');
			return $result;
		}

		// Get response code
		$code = wp_remote_retrieve_response_code($response);
		$result['response_time'] = $response_time;

		// Determine status based on HTTP code
		if ($code >= LUKSTACK_HTTP_SUCCESS_MIN && $code <= LUKSTACK_HTTP_SUCCESS_MAX) {
			$result['status'] = 'UP';
		} elseif ($code >= LUKSTACK_HTTP_REDIRECT_MIN && $code <= LUKSTACK_HTTP_REDIRECT_MAX) {
			$result['status'] = 'UP';
		} elseif ($code >= LUKSTACK_HTTP_CLIENT_ERROR_MIN && $code <= LUKSTACK_HTTP_CLIENT_ERROR_MAX) {
			$result['status'] = 'ERROR ' . $code;
			$result['error_message'] = 'Client error: ' . $code;
		} elseif ($code >= LUKSTACK_HTTP_SERVER_ERROR_MIN && $code <= LUKSTACK_HTTP_SERVER_ERROR_MAX) {
			$result['status'] = 'ERROR ' . $code;
			$result['error_message'] = 'Server error: ' . $code;
		} else {
			$result['status'] = 'ERROR';
			$result['error_message'] = 'Unknown HTTP code: ' . $code;
		}

		// Get SSL certificate info for HTTPS sites (Pro feature)
		if ( lukstack_can_use_ssl_monitoring() && lukstack_is_https($url)) {
			$ssl_data = lukstack_get_ssl_info($url);
			if ($ssl_data && !is_wp_error($ssl_data)) {
				$result['ssl_expiry_date'] = $ssl_data['expiry_date'];
				$result['ssl_issuer'] = $ssl_data['issuer'];
				$result['ssl_days_remaining'] = $ssl_data['days_remaining'];
			}
		}

	} catch (Exception $e) {
		lukstack_log('Exception during check for ' . $url . ' - ' . $e->getMessage(), 'error');
		$result['error_message'] = $e->getMessage();
	}

	return $result;
}

/**
 * Get failed check result structure
 *
 * @param string $error_message Error message
 * @return array Failed result array
 */
function lukstack_get_failed_check_result($error_message = '') {
	return array(
		'status' => 'DOWN',
		'response_time' => null,
		'ssl_expiry_date' => null,
		'ssl_issuer' => null,
		'ssl_days_remaining' => null,
		'error_message' => $error_message
	);
}

/**
 * Get SSL certificate information with proper error handling
 *
 * @param string $url The HTTPS URL to check
 * @return array|WP_Error SSL info or error
 */
function lukstack_get_ssl_info($url) {
	// Parse URL
	$parsed = wp_parse_url($url);

	if (!isset($parsed['host'])) {
		return new WP_Error('invalid_url', __('Invalid URL - no host found', 'lukstack-uptime-monitor'));
	}

	$host = $parsed['host'];
	$port = isset($parsed['port']) ? intval($parsed['port']) : LUKSTACK_SSL_PORT;

	// Validate port
	if ($port < 1 || $port > 65535) {
		$port = LUKSTACK_SSL_PORT;
	}

	try {
		// Create stream context with SSL options
		$context = stream_context_create(array(
			'ssl' => array(
				'capture_peer_cert' => true,
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			)
		));

		// Set error handler to catch warnings
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler(function($errno, $errstr) {
			throw new Exception(esc_html($errstr), $errno);
		});

		// Try to connect and get certificate
		$client = @stream_socket_client(
			"ssl://{$host}:{$port}",
			$errno,
			$errstr,
			LUKSTACK_SSL_TIMEOUT,
			STREAM_CLIENT_CONNECT,
			$context
		);

		// Restore error handler
		restore_error_handler();

		if (!$client) {
			lukstack_log("SSL connection failed for {$host}:{$port} - {$errstr}", 'error');
			return new WP_Error('ssl_connection_failed', $errstr);
		}

		// Get stream context parameters
		$params = stream_context_get_params($client);

		// Close connection
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose($client);

		// Check if certificate was captured
		if (!isset($params['options']['ssl']['peer_certificate'])) {
			return new WP_Error('no_certificate', __('No SSL certificate found', 'lukstack-uptime-monitor'));
		}

		// Parse certificate
		$cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

		if (!$cert || !isset($cert['validTo_time_t'])) {
			return new WP_Error('invalid_certificate', __('Invalid SSL certificate', 'lukstack-uptime-monitor'));
		}

		// Calculate days remaining
		$expiry_timestamp = $cert['validTo_time_t'];
		$expiry_date = gmdate('Y-m-d H:i:s', $expiry_timestamp);
		$days_remaining = floor(($expiry_timestamp - time()) / 86400);

		// Get issuer organization
		$issuer = 'Unknown';
		if (isset($cert['issuer']['O'])) {
			$issuer = $cert['issuer']['O'];
		} elseif (isset($cert['issuer']['CN'])) {
			$issuer = $cert['issuer']['CN'];
		} elseif (isset($cert['issuer']['OU'])) {
			$issuer = $cert['issuer']['OU'];
		}

		return array(
			'expiry_date' => $expiry_date,
			'issuer' => sanitize_text_field($issuer),
			'days_remaining' => $days_remaining,
			'valid_from' => gmdate('Y-m-d H:i:s', $cert['validFrom_time_t']),
			'subject' => isset($cert['subject']['CN']) ? $cert['subject']['CN'] : $host
		);

	} catch (Exception $e) {
		restore_error_handler();
		lukstack_log('SSL check exception for ' . $host . ' - ' . $e->getMessage(), 'error');
		return new WP_Error('ssl_exception', $e->getMessage());
	}
}

/**
 * Render status badge HTML
 *
 * @param string|null $status The status to render
 * @return string HTML badge
 */
function lukstack_render_status($status) {
	if (empty($status)) {
		return '<span class="lukstack-badge unknown" title="' . esc_attr__('Not checked yet', 'lukstack-uptime-monitor') . '">' .
			esc_html__('UNKNOWN', 'lukstack-uptime-monitor') . '</span>';
	}

	$status = sanitize_text_field($status);

	if ($status === 'UP') {
		return '<span class="lukstack-badge up" title="' . esc_attr__('Website is online', 'lukstack-uptime-monitor') . '">' .
			esc_html__('UP', 'lukstack-uptime-monitor') . '</span>';
	}

	if ($status === 'DOWN') {
		return '<span class="lukstack-badge down" title="' . esc_attr__('Website is not responding', 'lukstack-uptime-monitor') . '">' .
			esc_html__('DOWN', 'lukstack-uptime-monitor') . '</span>';
	}

	// Handle error codes
	/* translators: %s: HTTP status code */
	$title = sprintf(__('Website returned status: %s', 'lukstack-uptime-monitor'), $status);
	return '<span class="lukstack-badge error" title="' . esc_attr($title) . '">' .
		esc_html($status) . '</span>';
}

/**
 * Render response time badge
 *
 * @param float|null $response_time Response time in milliseconds
 * @return string HTML output
 */
function lukstack_render_response_time($response_time) {
	if ($response_time === null) {
		return '<span style="color: ' . LUKSTACK_COLOR_GRAY . ';" title="' . esc_attr__('No data', 'lukstack-uptime-monitor') . '">—</span>';
	}

	$response_time = floatval($response_time);
	$formatted_time = number_format($response_time, 0);

	// Determine color based on speed
	$color = LUKSTACK_COLOR_SUCCESS;
	$label = __('Fast', 'lukstack-uptime-monitor');

	if ($response_time > LUKSTACK_RESPONSE_VERY_SLOW) {
		$color = LUKSTACK_COLOR_ERROR;
		$label = __('Very Slow', 'lukstack-uptime-monitor');
	} elseif ($response_time > LUKSTACK_RESPONSE_FAST) {
		$color = LUKSTACK_COLOR_WARNING;
		$label = __('Slow', 'lukstack-uptime-monitor');
	}

	/* translators: %1$s: response time in ms, %2$s: performance label */
	$title = sprintf(__('Response time: %1$s (%2$s)', 'lukstack-uptime-monitor'), $formatted_time . 'ms', $label);

	return sprintf(
		'<span style="color: %s; font-weight: 500;" title="%s">%s ms</span>',
		esc_attr($color),
		esc_attr($title),
		esc_html($formatted_time)
	);
}

/**
 * Render SSL expiry badge
 *
 * @param int|null $days_remaining Days until SSL expires
 * @param string|null $expiry_date Expiry date
 * @return string HTML output
 */
function lukstack_render_ssl_expiry($days_remaining, $expiry_date = null) {
	if ($days_remaining === null) {
		return '<span style="color: ' . LUKSTACK_COLOR_GRAY . ';" title="' . esc_attr__('No SSL certificate or HTTP site', 'lukstack-uptime-monitor') . '">' .
			esc_html__('No SSL', 'lukstack-uptime-monitor') . '</span>';
	}

	$days_remaining = intval($days_remaining);

	// Handle expired certificates
	if ($days_remaining < 0) {
		$title = __('SSL certificate has EXPIRED!', 'lukstack-uptime-monitor');
		if ($expiry_date) {
			/* translators: %s: SSL certificate expiry date */
			$title .= ' ' . sprintf(__('Expired on: %s', 'lukstack-uptime-monitor'), date_i18n(get_option('date_format'), strtotime($expiry_date)));
		}

		return sprintf(
			'<span style="color: %s; font-weight: 600;" title="%s">⚠️ %s</span>',
			esc_attr(LUKSTACK_COLOR_ERROR),
			esc_attr($title),
			esc_html__('EXPIRED', 'lukstack-uptime-monitor')
		);
	}

	// Determine urgency
	$color = LUKSTACK_COLOR_SUCCESS;
	$icon = '';
	/* translators: %d: number of days remaining */
	$status = sprintf(_n('%d day', '%d days', $days_remaining, 'lukstack-uptime-monitor'), $days_remaining);

	if ($days_remaining <= LUKSTACK_SSL_CRITICAL_DAYS) {
		$color = LUKSTACK_COLOR_ERROR;
		$icon = '⚠️ ';
	} elseif ($days_remaining <= LUKSTACK_SSL_WARNING_DAYS) {
		$color = LUKSTACK_COLOR_WARNING;
		$icon = '⚠ ';
	}

	// Build title
	/* translators: %d: number of days until SSL certificate expires */
	$title = sprintf(__('%d days until SSL expiration', 'lukstack-uptime-monitor'), $days_remaining);
	if ($expiry_date) {
		/* translators: %s: SSL certificate expiry date */
		$title .= ' - ' . sprintf(__('Expires: %s', 'lukstack-uptime-monitor'), date_i18n(get_option('date_format'), strtotime($expiry_date)));
	}

	return sprintf(
		'<span style="color: %s; font-weight: 500;" title="%s">%s%s</span>',
		esc_attr($color),
		esc_attr($title),
		$icon,
		esc_html($status)
	);
}

/**
 * Calculate uptime percentage for a site
 *
 * @param object $site Site object from database
 * @return float Uptime percentage (0-100)
 */
function lukstack_calculate_uptime($site) {
	if (!$site || !isset($site->check_count) || $site->check_count == 0) {
		return 100.0;
	}

	$check_count = intval($site->check_count);
	$down_count = isset($site->down_count) ? intval($site->down_count) : 0;

	if ($check_count == 0) {
		return 100.0;
	}

	$up_count = $check_count - $down_count;
	$uptime = ($up_count / $check_count) * 100;

	return round($uptime, 2);
}

/**
 * Format uptime percentage with color
 *
 * @param float $uptime Uptime percentage
 * @return string HTML output
 */
function lukstack_render_uptime($uptime) {
	$uptime = floatval($uptime);

	// Determine color
	if ($uptime >= LUKSTACK_UPTIME_EXCELLENT) {
		$color = LUKSTACK_COLOR_SUCCESS;
	} elseif ($uptime >= LUKSTACK_UPTIME_GOOD) {
		$color = '#22c55e'; // light green
	} elseif ($uptime >= LUKSTACK_UPTIME_FAIR) {
		$color = LUKSTACK_COLOR_WARNING;
	} else {
		$color = LUKSTACK_COLOR_ERROR;
	}

	return sprintf(
		'<span style="color: %s; font-weight: 600;" title="%s">%s%%</span>',
		esc_attr($color),
		/* translators: %s: uptime percentage */
		esc_attr(sprintf(__('Uptime: %s%%', 'lukstack-uptime-monitor'), $uptime)),
		esc_html(number_format($uptime, 2))
	);
}

/**
 * Get human-readable time difference
 *
 * @param string $datetime MySQL datetime string
 * @return string Human-readable time difference
 */
function lukstack_time_ago($datetime) {
	if (empty($datetime)) {
		return __('Never', 'lukstack-uptime-monitor');
	}

	$timestamp = strtotime($datetime);
	if (!$timestamp) {
		return __('Unknown', 'lukstack-uptime-monitor');
	}

	$diff = time() - $timestamp;

	if ($diff < 60) {
		return __('Just now', 'lukstack-uptime-monitor');
	} elseif ($diff < 3600) {
		$minutes = floor($diff / 60);
		/* translators: %d: number of minutes */
		return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'lukstack-uptime-monitor'), $minutes);
	} elseif ($diff < 86400) {
		$hours = floor($diff / 3600);
		/* translators: %d: number of hours */
		return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'lukstack-uptime-monitor'), $hours);
	} else {
		$days = floor($diff / 86400);
		/* translators: %d: number of days */
		return sprintf(_n('%d day ago', '%d days ago', $days, 'lukstack-uptime-monitor'), $days);
	}
}

/**
 * Validate check result structure
 *
 * @param array $result Check result array
 * @return bool True if valid
 */
function lukstack_is_valid_check_result($result) {
	if (!is_array($result)) {
		return false;
	}

	$required_keys = array('status', 'response_time', 'ssl_expiry_date', 'ssl_issuer', 'ssl_days_remaining');

	foreach ($required_keys as $key) {
		if (!array_key_exists($key, $result)) {
			return false;
		}
	}

	return true;
}