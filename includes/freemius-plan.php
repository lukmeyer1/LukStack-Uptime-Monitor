<?php
/**
 * Freemius Plan Configuration & Feature Gating
 *
 * Centralized plan detection and feature availability.
 * All plan-related checks should go through functions in this file.
 *
 * @package LukStack_Uptime_Monitor
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plan Limits
 */
define( 'LUKSTACK_FREE_SITE_LIMIT', 2 );
define( 'LUKSTACK_PRO_SITE_LIMIT', 100 );
define( 'LUKSTACK_FREE_CHECK_INTERVAL', 15 ); // minutes
define( 'LUKSTACK_PRO_CHECK_INTERVAL', 5 );   // minutes

/**
 * Check if the current user has an active paid plan or trial.
 *
 * This is the core detection function. All other plan checks rely on it.
 * Results are cached per request via a static variable.
 *
 * @since 2.1.0
 *
 * @param bool $force_refresh Force bypass of static cache (used by plan-change hooks).
 * @return bool True if user has Pro plan or active trial.
 */
function lukstack_is_paid_plan( $force_refresh = false ) {
	static $is_paid = null;

	if ( null !== $is_paid && ! $force_refresh ) {
		return $is_paid;
	}

	// Default to free if Freemius is not available.
	if ( ! function_exists( 'wpls_fm' ) ) {
		$is_paid = false;
		return $is_paid;
	}

	try {
		$is_paid = wpls_fm()->is_paying_or_trial();
	} catch ( Exception $e ) {
		lukstack_log( 'Freemius plan detection failed: ' . $e->getMessage(), 'error' );
		$is_paid = false;
	}

	return $is_paid;
}

/**
 * Get the display name of the current plan.
 *
 * @since 2.1.0
 *
 * @return string Plan name ("Pro" or "Free").
 */
function lukstack_get_current_plan_name() {
	if ( lukstack_is_paid_plan() ) {
		return __( 'Pro', 'lukstack-uptime-monitor' );
	}

	return __( 'Free', 'lukstack-uptime-monitor' );
}

/**
 * Get the maximum number of monitored sites allowed on the current plan.
 *
 * @since 2.1.0
 *
 * @return int Site limit.
 */
function lukstack_get_site_limit() {
	return lukstack_is_paid_plan() ? LUKSTACK_PRO_SITE_LIMIT : LUKSTACK_FREE_SITE_LIMIT;
}

/**
 * Get the minimum check interval in minutes for the current plan.
 *
 * @since 2.1.0
 *
 * @return int Check interval in minutes.
 */
function lukstack_get_plan_check_interval() {
	return lukstack_is_paid_plan() ? LUKSTACK_PRO_CHECK_INTERVAL : LUKSTACK_FREE_CHECK_INTERVAL;
}

/**
 * Check if SSL certificate monitoring is available on the current plan.
 *
 * @since 2.1.0
 *
 * @return bool True if SSL monitoring is available.
 */
function lukstack_can_use_ssl_monitoring() {
	return lukstack_is_paid_plan();
}

/**
 * Check if performance tracking (response time display) is available.
 *
 * @since 2.1.0
 *
 * @return bool True if performance tracking is available.
 */
function lukstack_can_use_performance_tracking() {
	return lukstack_is_paid_plan();
}

/**
 * Get the current number of monitored sites.
 *
 * @since 2.1.0
 *
 * @return int Number of sites currently monitored.
 */
function lukstack_get_site_count() {
	$stats = lukstack_get_stats();
	return isset( $stats['total'] ) ? intval( $stats['total'] ) : 0;
}

/**
 * Check if the user can add another site (within plan limit).
 *
 * @since 2.1.0
 *
 * @return bool True if another site can be added.
 */
function lukstack_can_add_site() {
	return lukstack_get_site_count() < lukstack_get_site_limit();
}

/**
 * Get the number of remaining site slots on the current plan.
 *
 * @since 2.1.0
 *
 * @return int Number of remaining slots (0 or more).
 */
function lukstack_get_remaining_slots() {
	return max( 0, lukstack_get_site_limit() - lukstack_get_site_count() );
}

/**
 * Get the Freemius upgrade/pricing page URL.
 *
 * @since 2.1.0
 *
 * @return string Upgrade URL.
 */
function lukstack_get_upgrade_url() {
	if ( function_exists( 'wpls_fm' ) ) {
		try {
			return wpls_fm()->get_upgrade_url();
		} catch ( Exception $e ) {
			// Fall through to fallback.
		}
	}

	return admin_url( 'admin.php?page=lukstack-uptime-monitor-pricing' );
}

// -------------------------------------------------------------------------
// Plan Change Hooks
// -------------------------------------------------------------------------

/**
 * Handle plan/license change: clear cache and reschedule cron.
 *
 * @since 2.1.0
 *
 * @param string|null $plan_change Type of change (upgraded, downgraded, etc.).
 * @param object|null $plan        New plan object.
 */
function lukstack_handle_plan_change( $plan_change = null, $plan = null ) {
	// Clear the static cache so next call re-evaluates.
	lukstack_is_paid_plan( true );

	// Reschedule cron with the correct interval for the new plan.
	lukstack_schedule_cron();

	lukstack_log( sprintf(
		'Plan changed (%s) - new interval: %d minutes, site limit: %d',
		$plan_change ? sanitize_text_field( $plan_change ) : 'sync',
		lukstack_get_plan_check_interval(),
		lukstack_get_site_limit()
	) );
}

/**
 * Handle plan sync from Freemius server.
 *
 * @since 2.1.0
 *
 * @param string|null $plan_name New plan name.
 */
function lukstack_handle_plan_sync( $plan_name = null ) {
	lukstack_handle_plan_change( 'sync', null );
}

// -------------------------------------------------------------------------
// Freemius Account State Helpers
// -------------------------------------------------------------------------

/**
 * Get a coarse-grained label for the current Freemius account state.
 *
 * Used for funnel telemetry sent to the cron server. Order matters — the
 * strongest commitment wins (paying → trial → pending → registered → anonymous).
 *
 * @since 2.3.0
 *
 * @return string One of: 'paying', 'trial', 'pending', 'registered', 'anonymous', 'unknown'.
 */
function lukstack_get_freemius_status() {
	if ( ! function_exists( 'wpls_fm' ) ) {
		return 'unknown';
	}

	try {
		$fs = wpls_fm();

		if ( $fs->is_paying() ) {
			return 'paying';
		}
		if ( $fs->is_trial() ) {
			return 'trial';
		}
		if ( $fs->is_pending_activation() ) {
			return 'pending';
		}
		if ( $fs->is_registered() ) {
			return 'registered';
		}
		if ( $fs->is_anonymous() ) {
			return 'anonymous';
		}
	} catch ( Exception $e ) {
		lukstack_log( 'Freemius status detection failed: ' . $e->getMessage(), 'warning' );
	}

	return 'unknown';
}

/**
 * Get the Freemius user ID for the current install, but only if the user
 * has explicitly opted in. Used for funnel telemetry.
 *
 * @since 2.3.0
 *
 * @return int|null User ID if registered, null otherwise.
 */
function lukstack_get_freemius_user_id() {
	if ( ! function_exists( 'wpls_fm' ) ) {
		return null;
	}

	try {
		$fs = wpls_fm();
		if ( ! $fs->is_registered() ) {
			return null;
		}

		$user = $fs->get_user();
		if ( $user && ! empty( $user->id ) ) {
			return (int) $user->id;
		}
	} catch ( Exception $e ) {
		lukstack_log( 'Freemius user_id lookup failed: ' . $e->getMessage(), 'warning' );
	}

	return null;
}

// -------------------------------------------------------------------------
// Freemius Opt-in Dialog Message
// -------------------------------------------------------------------------

/**
 * Override the default Freemius connect message with a value-oriented framing.
 *
 * Default Freemius copy reads like telemetry consent, which suppresses opt-in
 * conversion. We re-frame it around the concrete features the user unlocks by
 * connecting (email alerts, Pro trial).
 *
 * The Freemius activation terms HTML (which contains the required
 * permissions/privacy link) is preserved at the end — removing it would
 * violate Freemius's UX contract.
 *
 * @since 2.3.0
 *
 * @param string $message    Default opt-in message (HTML).
 * @param string $first_name User first name.
 * @param string $plugin_name Plugin name (raw, not bolded).
 * @param string $user_login WP user login.
 * @param string $site_link  Anchor tag pointing at the site URL.
 * @param string $terms_html Freemius activation/terms snippet — must be preserved.
 * @return string Replacement HTML.
 */
function lukstack_freemius_connect_message( $message, $first_name, $plugin_name, $user_login, $site_link, $terms_html ) {
	if ( empty( $first_name ) ) {
		$first_name = __( 'there', 'lukstack-uptime-monitor' );
	}

	$greeting = sprintf(
		/* translators: %s: user first name */
		esc_html__( 'Hey %s,', 'lukstack-uptime-monitor' ),
		esc_html( $first_name )
	);

	$body = sprintf(
		/* translators: %1$s: plugin name (already bolded), %2$s: site link (anchor tag) */
		__( 'connect your account with %1$s on %2$s to unlock <strong>downtime alerts by email</strong>, a free 7-day Pro trial, and priority support. You stay in full control and can disconnect at any time.', 'lukstack-uptime-monitor' ),
		'<strong>' . esc_html( $plugin_name ) . '</strong>',
		$site_link
	);

	return $greeting . ' ' . $body . ' ' . $terms_html;
}

// Register Freemius hooks.
// Safe to call here because this file is loaded inside the else block
// in the main plugin file where wpls_fm() is guaranteed to exist.
if ( function_exists( 'wpls_fm' ) ) {
	wpls_fm()->add_action( 'after_license_change', 'lukstack_handle_plan_change', 10, 2 );
	wpls_fm()->add_action( 'after_account_plan_sync', 'lukstack_handle_plan_sync', 10, 1 );

	wpls_fm()->add_filter( 'connect_message', 'lukstack_freemius_connect_message', 10, 6 );
	wpls_fm()->add_filter( 'connect_message_on_update', 'lukstack_freemius_connect_message', 10, 6 );
}
