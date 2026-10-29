<?php
/**
 * Plugin Name: LukStack Uptime Monitor
 * Plugin URI: https://wordpress.org/plugins/lukstack-uptime-monitor/
 * Description: Professional website monitoring for agencies: uptime, SSL, performance, instant alerts. Built-in remote cron server — no third-party services needed.
 * Version: 2.4.0
 * Author: Luk Meyer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lukstack-uptime-monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'wpls_fm' ) ) {
	/**
	 * If wpls_fm() already exists, the premium version is being activated
	 * while the free version is still active. Hand off the basename so
	 * Freemius can auto-deactivate the free version.
	 */
	wpls_fm()->set_basename( true, __FILE__ );
} else {

	/**
	 * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
	 * `function_exists` CALL ABOVE TO PROPERLY WORK.
	 */
	if ( ! function_exists( 'wpls_fm' ) ) {

		// Create a helper function for easy SDK access.
		function wpls_fm() {
			global $wpls_fm;

			if ( ! isset( $wpls_fm ) ) {
				// Activate multisite network integration.
				if ( ! defined( 'WP_FS__PRODUCT_23423_MULTISITE' ) ) {
					define( 'WP_FS__PRODUCT_23423_MULTISITE', true );
				}

				// Include Freemius SDK.
				require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

				$wpls_fm = fs_dynamic_init( array(
					'id'                  => '23423',
					'slug'                => 'lukstack-uptime-monitor',
					'type'                => 'plugin',
					'public_key'          => 'pk_2c837a3de8ac322ea0b7ed0f6144a',
					'is_premium'          => false,
					'has_premium_version' => false,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => true,
					'menu'                => array(
						'slug'    => 'lukstack-uptime-monitor',
						'support' => false,
					),
				) );
			}

			return $wpls_fm;
		}

		// Init Freemius.
		wpls_fm();
		// Signal that SDK was initiated.
		do_action( 'wpls_fm_loaded' );
	}

	// -------------------------------------------------------------------------
	// Plugin constants
	// -------------------------------------------------------------------------
	define( 'LUKSTACK_VERSION',        '2.4.0' );
	define( 'LUKSTACK_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
	define( 'LUKSTACK_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
	define( 'LUKSTACK_PLUGIN_FILE',    __FILE__ );
	define( 'LUKSTACK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	define( 'LUKSTACK_MIN_PHP_VERSION', '7.4' );
	define( 'LUKSTACK_MIN_WP_VERSION',  '5.8' );

	// -------------------------------------------------------------------------
	// Requirement checks
	// -------------------------------------------------------------------------

	/**
	 * Check minimum requirements.
	 *
	 * @return array Array of error messages, empty if all requirements met.
	 */
	function lukstack_check_requirements() {
		$errors = array();

		if ( version_compare( PHP_VERSION, LUKSTACK_MIN_PHP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: %1$s: required PHP version, %2$s: current PHP version */
				__( 'LukStack Uptime Monitor requires PHP version %1$s or higher. You are running version %2$s.', 'lukstack-uptime-monitor' ),
				LUKSTACK_MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		global $wp_version;
		if ( version_compare( $wp_version, LUKSTACK_MIN_WP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: %1$s: required WordPress version, %2$s: current WordPress version */
				__( 'LukStack Uptime Monitor requires WordPress version %1$s or higher. You are running version %2$s.', 'lukstack-uptime-monitor' ),
				LUKSTACK_MIN_WP_VERSION,
				$wp_version
			);
		}

		foreach ( array( 'curl', 'openssl', 'json' ) as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$errors[] = sprintf(
					/* translators: %s: PHP extension name */
					__( 'LukStack Uptime Monitor requires the PHP %s extension to be installed.', 'lukstack-uptime-monitor' ),
					$extension
				);
			}
		}

		return $errors;
	}

	/**
	 * Display admin notice for requirement errors.
	 */
	function lukstack_requirement_error_notice() {
		$errors = lukstack_check_requirements();
		if ( empty( $errors ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'LukStack Uptime Monitor Error:', 'lukstack-uptime-monitor' ) . '</strong></p><ul>';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';

		deactivate_plugins( LUKSTACK_PLUGIN_BASENAME );
	}

	// Bail early if requirements not met.
	$lukstack_requirement_errors = lukstack_check_requirements();
	if ( ! empty( $lukstack_requirement_errors ) ) {
		add_action( 'admin_notices', 'lukstack_requirement_error_notice' );
		return;
	}

	// -------------------------------------------------------------------------
	// Includes
	// -------------------------------------------------------------------------
	require_once LUKSTACK_PLUGIN_DIR . 'includes/constants.php';
	require_once LUKSTACK_PLUGIN_DIR . 'includes/helpers.php';
	require_once LUKSTACK_PLUGIN_DIR . 'includes/freemius-plan.php';

	require_once LUKSTACK_PLUGIN_DIR . 'includes/core/database.php';
	require_once LUKSTACK_PLUGIN_DIR . 'includes/core/validator.php';
	require_once LUKSTACK_PLUGIN_DIR . 'includes/core/monitor.php';

	require_once LUKSTACK_PLUGIN_DIR . 'includes/cron.php';
	require_once LUKSTACK_PLUGIN_DIR . 'includes/remote-cron.php';
	require_once LUKSTACK_PLUGIN_DIR . 'includes/alerts.php';

	if ( is_admin() ) {
		require_once LUKSTACK_PLUGIN_DIR . 'includes/admin/menu.php';
		require_once LUKSTACK_PLUGIN_DIR . 'includes/admin/main-page.php';
		require_once LUKSTACK_PLUGIN_DIR . 'includes/admin/settings-page.php';
		require_once LUKSTACK_PLUGIN_DIR . 'includes/admin/help-page.php';
		require_once LUKSTACK_PLUGIN_DIR . 'includes/admin/ajax-handlers.php';
		require_once LUKSTACK_PLUGIN_DIR . 'includes/admin/dashboard-widget.php';
	}

	// -------------------------------------------------------------------------
	// Lifecycle hooks
	// -------------------------------------------------------------------------
	register_activation_hook( __FILE__, 'lukstack_activate' );
	register_deactivation_hook( __FILE__, 'lukstack_deactivate' );

	// Uninstall via Freemius – fires after uninstall event is reported to server.
	wpls_fm()->add_action( 'after_uninstall', 'lukstack_uninstall' );

	// -------------------------------------------------------------------------
	// Activation
	// -------------------------------------------------------------------------

	/**
	 * Plugin activation.
	 */
	function lukstack_activate() {
		$errors = lukstack_check_requirements();
		if ( ! empty( $errors ) ) {
			wp_die(
				implode( '<br>', array_map( 'esc_html', $errors ) ),
				esc_html__( 'Plugin Activation Error', 'lukstack-uptime-monitor' ),
				array( 'back_link' => true )
			);
		}

		lukstack_create_table();
		lukstack_schedule_cron();

		if ( ! get_option( 'lukstack_settings' ) ) {
			update_option( 'lukstack_settings', array(
				'webhook_url'           => '',
				'check_interval'        => LUKSTACK_DEFAULT_CHECK_INTERVAL,
				'notification_cooldown' => LUKSTACK_DEFAULT_COOLDOWN,
				'version'               => LUKSTACK_VERSION,
			), false );
		}

		// Record install time once. add_option no-ops if it already exists,
		// so pre-2.3 sites won't get a misleading "installed today" timestamp
		// — they get backfilled in lukstack_check_version() instead.
		add_option( 'lukstack_installed_at', time(), '', false );

		set_transient( 'lukstack_activation_notice', true, MINUTE_IN_SECONDS );
		flush_rewrite_rules();

		// Register with central cron server (best-effort; admin_init retries on failure).
		if ( function_exists( 'lukstack_register_with_cron_server' ) ) {
			lukstack_register_with_cron_server();
		}

		lukstack_log( sprintf( 'Plugin v%s activated by user %d', LUKSTACK_VERSION, get_current_user_id() ) );
	}

	// -------------------------------------------------------------------------
	// Deactivation
	// -------------------------------------------------------------------------

	/**
	 * Plugin deactivation.
	 */
	function lukstack_deactivate() {
		lukstack_clear_cron();

		global $wpdb;
		$like_transient = $wpdb->esc_like( '_transient_lukstack_' ) . '%';
		$like_timeout   = $wpdb->esc_like( '_transient_timeout_lukstack_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like_transient,
				$like_timeout
			)
		);

		flush_rewrite_rules();
		lukstack_log( sprintf( 'Plugin deactivated by user %d', get_current_user_id() ) );
	}

	// -------------------------------------------------------------------------
	// Uninstall
	// -------------------------------------------------------------------------

	/**
	 * Plugin uninstall – hooked via Freemius after_uninstall action.
	 * Fires after the uninstall event is reported to Freemius server.
	 */
	function lukstack_uninstall() {
		global $wpdb;

		// Tell the cron server to drop this site BEFORE we delete the secret
		// it needs to authenticate the deregister request. Best-effort —
		// failures fall back to the server's inactive-sweep.
		if ( function_exists( 'lukstack_deregister_from_cron_server' ) ) {
			lukstack_deregister_from_cron_server();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . lukstack_get_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		delete_option( 'lukstack_settings' );
		delete_option( 'lukstack_version' );
		delete_option( 'lukstack_installed_at' );
		delete_option( 'lukstack_dashboard_widget_options' );
		delete_option( 'lukstack_cron_server_registration' );
		delete_option( 'lukstack_remote_cron_secret' );
		delete_option( 'lukstack_last_cron_run' );

		$like_transient = $wpdb->esc_like( '_transient_lukstack_' ) . '%';
		$like_timeout   = $wpdb->esc_like( '_transient_timeout_lukstack_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like_transient,
				$like_timeout
			)
		);

		wp_clear_scheduled_hook( 'lukstack_cron_event' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'LukStack Uptime Monitor: Plugin uninstalled – all data removed' );
		}
	}

	// -------------------------------------------------------------------------
	// i18n
	// -------------------------------------------------------------------------

	/**
	 * Load textdomain.
	 * WordPress automatically loads translations for wp.org-hosted plugins since WP 4.6.
	 */
	add_action( 'plugins_loaded', 'lukstack_load_textdomain' );
	function lukstack_load_textdomain() {
		// Intentionally minimal – handled automatically by WordPress.
	}

	// -------------------------------------------------------------------------
	// Admin notices & links
	// -------------------------------------------------------------------------

	/**
	 * Activation notice.
	 */
	add_action( 'admin_notices', 'lukstack_activation_notice' );
	function lukstack_activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'lukstack_activation_notice' ) ) {
			return;
		}

		delete_transient( 'lukstack_activation_notice' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php esc_html_e( 'LukStack Uptime Monitor activated successfully!', 'lukstack-uptime-monitor' ); ?></strong></p>
			<p>
				<?php
				printf(
					/* translators: %s: link to the LukStack dashboard */
					esc_html__( 'Get started by adding your first website in %s.', 'lukstack-uptime-monitor' ),
					'<a href="' . esc_url( lukstack_admin_url() ) . '">' . esc_html__( 'LukStack Uptime Monitor Dashboard', 'lukstack-uptime-monitor' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin action links.
	 */
	add_filter( 'plugin_action_links_' . LUKSTACK_PLUGIN_BASENAME, 'lukstack_action_links' );
	function lukstack_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( lukstack_admin_url() ) . '">' . esc_html__( 'Dashboard', 'lukstack-uptime-monitor' ) . '</a>',
			'<a href="' . esc_url( lukstack_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'lukstack-uptime-monitor' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Plugin row meta.
	 */
	add_filter( 'plugin_row_meta', 'lukstack_row_meta', 10, 2 );
	function lukstack_row_meta( $links, $file ) {
		if ( $file !== LUKSTACK_PLUGIN_BASENAME ) {
			return $links;
		}

		return array_merge( $links, array(
			'docs'    => '<a href="' . esc_url( lukstack_admin_url( 'help' ) ) . '">' . esc_html__( 'Documentation', 'lukstack-uptime-monitor' ) . '</a>',
			'support' => '<a href="https://wordpress.org/support/plugin/lukstack-uptime-monitor/" target="_blank">' . esc_html__( 'Support', 'lukstack-uptime-monitor' ) . '</a>',
		) );
	}

	// -------------------------------------------------------------------------
	// Version / migrations
	// -------------------------------------------------------------------------

	/**
	 * Check version and run updates if needed.
	 */
	add_action( 'plugins_loaded', 'lukstack_check_version' );
	function lukstack_check_version() {
		// Backfill installed_at for sites that activated before 2.3.0. Using the
		// current time is intentionally imprecise — for the funnel it's the
		// "we first observed this install" signal that matters, not the truth.
		add_option( 'lukstack_installed_at', time(), '', false );

		$saved_version = get_option( 'lukstack_version', '0' );
		if ( version_compare( $saved_version, LUKSTACK_VERSION, '<' ) ) {
			lukstack_update_plugin( $saved_version );
		}
	}

	/**
	 * Run plugin update routines.
	 *
	 * @param string $old_version Previous version number.
	 */
	function lukstack_update_plugin( $old_version ) {
		lukstack_maybe_update_table_structure();

		if ( version_compare( $old_version, '2.0.0', '<' ) ) {
			lukstack_migrate_to_2_0_0();
		}

		update_option( 'lukstack_version', LUKSTACK_VERSION );
		lukstack_log( sprintf( 'Updated from v%s to v%s', $old_version, LUKSTACK_VERSION ) );

		// register_activation_hook does not fire on auto-updates, and admin_init only
		// runs for logged-in admins inside wp-admin — many sites would otherwise never
		// register with the remote cron server. Run on shutdown so the HTTP call does
		// not block the current request.
		if ( function_exists( 'lukstack_register_with_cron_server' ) ) {
			delete_option( LUKSTACK_REGISTRATION_OPTION );
			add_action( 'shutdown', 'lukstack_register_with_cron_server', 999 );
		}
	}

	/**
	 * Migrate to 2.0.0.
	 */
	function lukstack_migrate_to_2_0_0() {
		lukstack_maybe_update_table_structure();
	}

	// -------------------------------------------------------------------------
	// Misc
	// -------------------------------------------------------------------------

	/**
	 * Add admin body class on plugin pages.
	 */
	add_filter( 'admin_body_class', 'lukstack_admin_body_class' );
	function lukstack_admin_body_class( $classes ) {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'lukstack-uptime-monitor' ) !== false ) {
			$classes .= ' lukstack-admin-page';
		}
		return $classes;
	}

} // end else – Freemius auto-deactivation wrapper
