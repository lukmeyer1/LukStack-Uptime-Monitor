<?php
/**
 * Cron scheduling and execution with batch processing
 *
 * @package LukStack_Uptime_Monitor
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register custom cron intervals.
 *
 * @since 2.0.0
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified cron schedules.
 */
add_filter( 'cron_schedules', 'lukstack_cron_schedules' );

function lukstack_cron_schedules( $schedules ) {
	// 15 minutes interval.
	if ( ! isset( $schedules['fifteen_minutes'] ) ) {
		$schedules['fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'lukstack-uptime-monitor' ),
		);
	}

	// 5 minutes interval.
	if ( ! isset( $schedules['five_minutes'] ) ) {
		$schedules['five_minutes'] = array(
			'interval' => LUKSTACK_CRON_FIVE_MINUTES,
			'display'  => __( 'Every 5 Minutes', 'lukstack-uptime-monitor' ),
		);
	}

	// 1 minute interval.
	if ( ! isset( $schedules['one_minute'] ) ) {
		$schedules['one_minute'] = array(
			'interval' => LUKSTACK_CRON_ONE_MINUTE,
			'display'  => __( 'Every Minute', 'lukstack-uptime-monitor' ),
		);
	}

	// 30 seconds interval.
	if ( ! isset( $schedules['thirty_seconds'] ) ) {
		$schedules['thirty_seconds'] = array(
			'interval' => LUKSTACK_CRON_THIRTY_SECONDS,
			'display'  => __( 'Every 30 Seconds', 'lukstack-uptime-monitor' ),
		);
	}

	return $schedules;
}

/**
 * Schedule cron on activation.
 *
 * @since 2.0.0
 */
function lukstack_schedule_cron() {
	// Clear existing schedule first.
	lukstack_clear_cron();

	// The plan dictates the interval. A stale check_interval left over from a
	// previous plan (e.g. 15 after a Free → Pro upgrade) used to win through
	// max(settings, plan), keeping Pro users on the Free-tier cadence.
	$interval = lukstack_get_plan_check_interval();

	// Determine schedule name based on interval.
	$schedule = 'five_minutes'; // Default.
	if ( $interval <= 0.5 ) {
		$schedule = 'thirty_seconds';
	} elseif ( $interval <= 1 ) {
		$schedule = 'one_minute';
	} elseif ( $interval <= 5 ) {
		$schedule = 'five_minutes';
	} else {
		$schedule = 'fifteen_minutes';
	}

	// Schedule event.
	if ( ! wp_next_scheduled( 'lukstack_cron_event' ) ) {
		$scheduled = wp_schedule_event( time(), $schedule, 'lukstack_cron_event' );

		if ( false === $scheduled ) {
			lukstack_log( 'Failed to schedule cron event', 'error' );
		} else {
			lukstack_log( 'Cron scheduled with interval: ' . $schedule );
		}
	}
}

/**
 * Clear cron on deactivation.
 *
 * @since 2.0.0
 */
function lukstack_clear_cron() {
	$timestamp = wp_next_scheduled( 'lukstack_cron_event' );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'lukstack_cron_event' );
	}

	// Also clear any lingering scheduled events.
	wp_clear_scheduled_hook( 'lukstack_cron_event' );

	lukstack_log( 'Cron events cleared' );
}

/**
 * Cron callback - runs every interval.
 *
 * @since 2.0.0
 */
add_action( 'lukstack_cron_event', 'lukstack_run_checks' );

/**
 * Run monitoring checks for sites (with batch processing).
 *
 * @since 2.0.0
 */
function lukstack_run_checks() {
	// Try to acquire lock atomically - prevents race conditions.
	if ( ! lukstack_acquire_cron_lock() ) {
		lukstack_log( 'Cron already running or could not acquire lock, skipping this execution' );
		return;
	}

	try {
		// Plan is the source of truth for the check interval — see lukstack_schedule_cron().
		$check_interval = lukstack_get_plan_check_interval();

		// Calculate batch size based on check interval (smallest first).
		$batch_size = LUKSTACK_BATCH_SIZE_DEFAULT;
		if ( $check_interval <= 0.5 ) {
			$batch_size = LUKSTACK_BATCH_SIZE_VERY_FAST;
		} elseif ( $check_interval <= 1 ) {
			$batch_size = LUKSTACK_BATCH_SIZE_FAST;
		}

		// Get sites that need checking.
		$sites = lukstack_get_sites_for_checking( $batch_size, $check_interval );

		if ( empty( $sites ) ) {
			lukstack_log( 'No sites need checking at this time' );
			lukstack_release_cron_lock();
			return;
		}

		lukstack_log( sprintf(
			'Starting check of %d sites (batch size: %d)',
			count( $sites ),
			$batch_size
		) );

		$start_time    = microtime( true );
		$checked_count = 0;
		$failed_count  = 0;

		foreach ( $sites as $site ) {
			try {
				$check_result = lukstack_check_and_update_site( $site->id, $site->url );

				if ( false !== $check_result ) {
					$checked_count++;

					if ( lukstack_is_debug() ) {
						lukstack_log( sprintf(
							'Checked %s - Status: %s, Time: %sms',
							$site->url,
							$check_result['status'],
							null !== $check_result['response_time'] ? round( $check_result['response_time'] ) : 'N/A'
						) );
					}
				} else {
					$failed_count++;
				}
			} catch ( Exception $e ) {
				lukstack_log( 'Exception checking site ID ' . $site->id . ': ' . $e->getMessage(), 'error' );
				$failed_count++;
			}
		}

		$end_time = microtime( true );
		$duration = round( ( $end_time - $start_time ), 2 );

		// Log summary.
		lukstack_log( sprintf(
			'Batch complete - Checked: %d, Failed: %d, Duration: %ss',
			$checked_count,
			$failed_count,
			$duration
		) );

		// Store last run info.
		update_option( 'lukstack_last_cron_run', array(
			'timestamp' => current_time( 'mysql' ),
			'checked'   => $checked_count,
			'failed'    => $failed_count,
			'duration'  => $duration,
		), false );

	} catch ( Exception $e ) {
		lukstack_log( 'Critical error in cron execution - ' . $e->getMessage(), 'error' );
	} finally {
		// Always release lock.
		lukstack_release_cron_lock();
	}
}

/**
 * Check if cron is currently running.
 *
 * @since 2.0.0
 *
 * @return bool True if running.
 */
function lukstack_is_cron_running() {
	return false !== lukstack_get_transient( 'cron_lock' );
}

/**
 * Attempt to acquire cron lock atomically.
 *
 * Uses database-level locking to prevent race conditions.
 *
 * @since 2.0.1
 *
 * @return bool True if lock was acquired, false if already locked.
 */
function lukstack_acquire_cron_lock() {
	global $wpdb;

	$lock_key        = '_transient_' . lukstack_transient_key( 'cron_lock' );
	$lock_timeout    = '_transient_timeout_' . lukstack_transient_key( 'cron_lock' );
	$current_time    = time();
	$expiration_time = $current_time + LUKSTACK_CRON_LOCK_DURATION;

	// Try to insert a new lock - this will fail if lock already exists.
	// Using INSERT IGNORE to make it atomic.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$inserted = $wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$lock_key,
			$current_time
		)
	);

	if ( $inserted ) {
		// Lock acquired, set the timeout.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no')
				 ON DUPLICATE KEY UPDATE option_value = %d",
				$lock_timeout,
				$expiration_time,
				$expiration_time
			)
		);
		return true;
	}

	// Lock exists - check if it's expired.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$existing_timeout = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			$lock_timeout
		)
	);

	// If timeout has passed, try to steal the lock.
	if ( $existing_timeout && intval( $existing_timeout ) < $current_time ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %d WHERE option_name = %s AND option_value < %d",
				$expiration_time,
				$lock_timeout,
				$current_time
			)
		);

		if ( $updated ) {
			// Successfully stole the expired lock.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->options,
				array( 'option_value' => $current_time ),
				array( 'option_name' => $lock_key ),
				array( '%s' ),
				array( '%s' )
			);
			return true;
		}
	}

	return false;
}

/**
 * Release cron lock.
 *
 * @since 2.0.1
 */
function lukstack_release_cron_lock() {
	global $wpdb;

	$lock_key     = '_transient_' . lukstack_transient_key( 'cron_lock' );
	$lock_timeout = '_transient_timeout_' . lukstack_transient_key( 'cron_lock' );

	// Delete both the lock and its timeout.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
			$lock_key,
			$lock_timeout
		)
	);

	// Also delete via transient API for good measure.
	lukstack_delete_transient( 'cron_lock' );
}

/**
 * Manual trigger for all checks (admin function).
 *
 * @since 2.0.0
 *
 * @return array Results summary.
 */
function lukstack_manual_check_all() {
	// Security check.
	lukstack_verify_admin_access();

	// Get all sites.
	$sites = lukstack_get_sites();

	if ( empty( $sites ) ) {
		return array(
			'success' => false,
			'message' => __( 'No sites to check', 'lukstack-uptime-monitor' ),
		);
	}

	$total   = count( $sites );
	$checked = 0;
	$failed  = 0;

	foreach ( $sites as $site ) {
		$result = lukstack_check_and_update_site( $site->id, $site->url );

		if ( false !== $result ) {
			$checked++;
		} else {
			$failed++;
		}
	}

	return array(
		'success' => true,
		'total'   => $total,
		'checked' => $checked,
		'failed'  => $failed,
		'message' => sprintf(
			/* translators: 1: Number of sites checked, 2: Total number of sites, 3: Number of failed checks */
			__( 'Checked %1$d of %2$d sites. Failed: %3$d', 'lukstack-uptime-monitor' ),
			$checked,
			$total,
			$failed
		),
	);
}

/**
 * Get cron status information.
 *
 * @since 2.0.0
 *
 * @return array Status information.
 */
function lukstack_get_cron_status() {
	$next_run   = wp_next_scheduled( 'lukstack_cron_event' );
	$last_run   = get_option( 'lukstack_last_cron_run', null );
	$is_running = lukstack_is_cron_running();

	// Format next run time - wp_next_scheduled returns UTC timestamp.
	// We need to convert it to local time for display.
	$next_run_formatted = __( 'Not scheduled', 'lukstack-uptime-monitor' );
	if ( false !== $next_run ) {
		// Convert UTC timestamp to local time using WordPress timezone.
		$next_run_formatted = wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$next_run
		);
	}

	return array(
		'is_scheduled'       => false !== $next_run,
		'next_run'           => $next_run ? $next_run : null,
		'next_run_formatted' => $next_run_formatted,
		'last_run'           => $last_run,
		'is_running'         => $is_running,
		'cron_enabled'       => ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON,
	);
}

/**
 * Display cron status in admin (for debugging).
 *
 * @since 2.0.0
 */
add_action( 'admin_notices', 'lukstack_cron_status_notice' );

function lukstack_cron_status_notice() {
	// Only show on LukStack Uptime Monitor pages.
	$screen = get_current_screen();
	if ( ! $screen || false === strpos( $screen->id, 'lukstack-uptime-monitor' ) ) {
		return;
	}

	// Only show to admins.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Determine whether the remote cron server is registered and active.
	// Since 2.2.0 the remote cron is the primary trigger mechanism;
	// WordPress Cron only acts as a fallback. Notices should reflect that.
	$registration  = function_exists( 'lukstack_get_registration_status' ) ? lukstack_get_registration_status() : array();
	$is_registered = isset( $registration['status'] ) && 'registered' === $registration['status'];

	// Always make sure the WP Cron fallback stays scheduled in the background,
	// but only nag the admin when BOTH trigger mechanisms are down.
	if ( ! wp_next_scheduled( 'lukstack_cron_event' ) ) {
		lukstack_schedule_cron();
	}

	$wp_cron_off      = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$wp_cron_unsched  = ! wp_next_scheduled( 'lukstack_cron_event' );
	$wp_cron_inactive = $wp_cron_off || $wp_cron_unsched;

	// If the remote cron is registered, monitoring runs via AWS every minute.
	// No admin notice is needed — the settings page shows the registration
	// status in detail for anyone who wants to look.
	if ( $is_registered ) {
		return;
	}

	// Remote cron is NOT registered. Fall back to WordPress Cron messaging.
	if ( $wp_cron_inactive ) {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'LukStack Uptime Monitor Warning:', 'lukstack-uptime-monitor' ); ?></strong>
				<?php esc_html_e( 'The remote cron server is not registered and the WordPress Cron fallback is unavailable. Automatic monitoring checks will not run until at least one trigger mechanism is restored.', 'lukstack-uptime-monitor' ); ?>
				<a href="<?php echo esc_url( lukstack_admin_url( 'settings' ) ); ?>">
					<?php esc_html_e( 'Open settings to re-register', 'lukstack-uptime-monitor' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

/**
 * Add cron status to admin footer (for debugging).
 *
 * @since 2.0.0
 *
 * @param string $text Existing footer text.
 * @return string Modified footer text.
 */
add_filter( 'admin_footer_text', 'lukstack_admin_footer_cron_info', 20 );

function lukstack_admin_footer_cron_info( $text ) {
	// Only on LukStack Uptime Monitor pages.
	$screen = get_current_screen();
	if ( ! $screen || false === strpos( $screen->id, 'lukstack-uptime-monitor' ) ) {
		return $text;
	}

	// Only for admins.
	if ( ! current_user_can( 'manage_options' ) ) {
		return $text;
	}

	$status = lukstack_get_cron_status();

	if ( ! $status['is_scheduled'] ) {
		return $text;
	}

	$cron_info = sprintf(
		/* translators: %s: Next scheduled check time */
		__( 'Next check: %s', 'lukstack-uptime-monitor' ),
		$status['next_run_formatted']
	);

	if ( $status['last_run'] ) {
		$cron_info .= ' | ' . sprintf(
			/* translators: 1: Timestamp, 2: Number checked, 3: Number failed */
			__( 'Last run: %1$s (%2$d checked, %3$d failed)', 'lukstack-uptime-monitor' ),
			$status['last_run']['timestamp'],
			$status['last_run']['checked'],
			$status['last_run']['failed']
		);
	}

	return $text . ' | ' . $cron_info;
}
