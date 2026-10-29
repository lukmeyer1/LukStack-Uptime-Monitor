<?php
/**
 * Plugin Constants
 * All magic numbers and configuration values centralized
 */

if (!defined('ABSPATH')) {
	exit;
}

// Already defined in main file:
// LUKSTACK_VERSION
// LUKSTACK_PLUGIN_DIR
// LUKSTACK_PLUGIN_URL
// LUKSTACK_PLUGIN_FILE
// LUKSTACK_PLUGIN_BASENAME
// LUKSTACK_MIN_PHP_VERSION
// LUKSTACK_MIN_WP_VERSION

/**
 * Database
 */
define('LUKSTACK_TABLE_NAME', 'lukstack_sites');

/**
 * Cron & Intervals (in seconds)
 */
define('LUKSTACK_CRON_FIVE_MINUTES', 300);
define('LUKSTACK_CRON_ONE_MINUTE', 60);
define('LUKSTACK_CRON_THIRTY_SECONDS', 30);

/**
 * Batch Processing
 */
define('LUKSTACK_BATCH_SIZE_DEFAULT', 10);
define('LUKSTACK_BATCH_SIZE_FAST', 5);
define('LUKSTACK_BATCH_SIZE_VERY_FAST', 3);

/**
 * Timeouts (in seconds)
 */
define('LUKSTACK_HTTP_TIMEOUT', 30);
define('LUKSTACK_WEBHOOK_TIMEOUT', 10);
define('LUKSTACK_SSL_TIMEOUT', 10);
define('LUKSTACK_CRON_LOCK_DURATION', 300); // 5 minutes
define('LUKSTACK_HTTP_MAX_REDIRECTS', 5);

/**
 * Performance Thresholds (in milliseconds)
 */
define('LUKSTACK_RESPONSE_FAST', 1000);      // < 1s = fast
define('LUKSTACK_RESPONSE_SLOW', 3000);      // < 3s = medium
define('LUKSTACK_RESPONSE_VERY_SLOW', 5000); // < 5s = slow

/**
 * SSL Certificate
 */
define('LUKSTACK_SSL_CRITICAL_DAYS', 7);  // Red alert
define('LUKSTACK_SSL_WARNING_DAYS', 30);  // Orange warning
define('LUKSTACK_SSL_PORT', 443);

/**
 * Validation Limits
 */
define('LUKSTACK_URL_MAX_LENGTH', 255);
define('LUKSTACK_EMAIL_MAX_LENGTH', 100);
define('LUKSTACK_WEBHOOK_MAX_LENGTH', 500);

/**
 * Default Settings
 */
define('LUKSTACK_DEFAULT_CHECK_INTERVAL', 15);     // minutes (free plan default)
define('LUKSTACK_DEFAULT_COOLDOWN', 24);           // hours
define('LUKSTACK_DEFAULT_NOTIFICATION_DELAY', DAY_IN_SECONDS);

/**
 * HTTP Status Code Ranges
 */
define('LUKSTACK_HTTP_SUCCESS_MIN', 200);
define('LUKSTACK_HTTP_SUCCESS_MAX', 299);
define('LUKSTACK_HTTP_REDIRECT_MIN', 300);
define('LUKSTACK_HTTP_REDIRECT_MAX', 399);
define('LUKSTACK_HTTP_CLIENT_ERROR_MIN', 400);
define('LUKSTACK_HTTP_CLIENT_ERROR_MAX', 499);
define('LUKSTACK_HTTP_SERVER_ERROR_MIN', 500);
define('LUKSTACK_HTTP_SERVER_ERROR_MAX', 599);

/**
 * Uptime Quality Thresholds (percentage)
 */
define('LUKSTACK_UPTIME_EXCELLENT', 99.9);  // Green
define('LUKSTACK_UPTIME_GOOD', 99.0);       // Light green
define('LUKSTACK_UPTIME_FAIR', 95.0);       // Orange

/**
 * Logging
 */
define('LUKSTACK_ENABLE_LOGGING', true);
define('LUKSTACK_LOG_PREFIX', 'LukStack Uptime Monitor');

/**
 * AJAX & UI
 */
define('LUKSTACK_AJAX_TIMEOUT', 30000);     // milliseconds
define('LUKSTACK_TOAST_DURATION', 3000);    // milliseconds

/**
 * Colors (Hex)
 */
define('LUKSTACK_COLOR_SUCCESS', '#10b981');
define('LUKSTACK_COLOR_ERROR', '#ef4444');
define('LUKSTACK_COLOR_WARNING', '#f59e0b');
define('LUKSTACK_COLOR_INFO', '#3b82f6');
define('LUKSTACK_COLOR_GRAY', '#9ca3af');

/**
 * Discord Webhook Colors (Decimal for embeds)
 */
define('LUKSTACK_DISCORD_COLOR_BLUE', 0x3B82F6);
define('LUKSTACK_DISCORD_COLOR_GREEN', 0x10B981);
define('LUKSTACK_DISCORD_COLOR_RED', 0xEF4444);
define('LUKSTACK_DISCORD_COLOR_ORANGE', 0xF59E0B);

/**
 * Blocked Hosts (Security)
 */
define('LUKSTACK_BLOCKED_HOSTS', array('localhost', '127.0.0.1', '0.0.0.0'));

/**
 * Disposable Email Domains (Optional - can be expanded)
 */
define('LUKSTACK_DISPOSABLE_DOMAINS', array(
	'tempmail.com',
	'10minutemail.com',
	'guerrillamail.com'
));

/**
 * User Agent
 */
define('LUKSTACK_USER_AGENT', 'LukStack-Uptime-Monitor/' . LUKSTACK_VERSION);