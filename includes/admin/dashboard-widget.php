<?php
/**
 * WordPress Dashboard Widget for LukStack Uptime Monitor
 *
 * Displays a quick status overview of all monitored websites
 * directly on the WordPress admin dashboard.
 *
 * @package LukStack_Uptime_Monitor
 * @since   2.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the dashboard widget.
 *
 * @since 2.0.1
 */
add_action( 'wp_dashboard_setup', 'lukstack_register_dashboard_widget' );

/**
 * Register dashboard widget.
 *
 * @since 2.0.1
 */
function lukstack_register_dashboard_widget() {
	// Only show to users who can manage options.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'lukstack_dashboard_widget',
		__( 'LukStack - Website Status', 'lukstack-uptime-monitor' ),
		'lukstack_dashboard_widget_content',
		'lukstack_dashboard_widget_config'
	);
}

/**
 * Dashboard widget content.
 *
 * @since 2.0.1
 */
function lukstack_dashboard_widget_content() {
	// Get stats.
	$stats = lukstack_get_stats();
	$sites = lukstack_get_sites();

	// Get widget options.
	$options = get_option( 'lukstack_dashboard_widget_options', array(
		'show_count' => 5,
		'show_ssl'   => true,
	) );

	$show_count = isset( $options['show_count'] ) ? intval( $options['show_count'] ) : 5;
	$show_ssl   = isset( $options['show_ssl'] ) ? (bool) $options['show_ssl'] : true;

	// SSL monitoring availability depends on plan.
	$ssl_monitoring_available = lukstack_can_use_ssl_monitoring();

	?>
	<style>
		.lukstack-widget-stats {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 10px;
			margin-bottom: 15px;
		}
		.lukstack-widget-stat {
			text-align: center;
			padding: 12px 8px;
			background: #f6f7f7;
			border-radius: 4px;
		}
		.lukstack-widget-stat-number {
			font-size: 24px;
			font-weight: 600;
			line-height: 1.2;
		}
		.lukstack-widget-stat-label {
			font-size: 11px;
			color: #646970;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.lukstack-widget-stat.up .lukstack-widget-stat-number { color: #00a32a; }
		.lukstack-widget-stat.down .lukstack-widget-stat-number { color: #d63638; }
		.lukstack-widget-stat.warning .lukstack-widget-stat-number { color: #dba617; }
		.lukstack-widget-stat.total .lukstack-widget-stat-number { color: #2271b1; }

		.lukstack-widget-sites {
			margin: 0;
			padding: 0;
			list-style: none;
		}
		.lukstack-widget-sites li {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 8px 0;
			border-bottom: 1px solid #f0f0f1;
		}
		.lukstack-widget-sites li:last-child {
			border-bottom: none;
		}
		.lukstack-widget-site-info {
			display: flex;
			align-items: center;
			gap: 8px;
			min-width: 0;
			flex: 1;
		}
		.lukstack-widget-site-url {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			color: #1d2327;
			text-decoration: none;
			font-size: 13px;
		}
		.lukstack-widget-site-url:hover {
			color: #2271b1;
		}
		.lukstack-widget-badge {
			display: inline-block;
			padding: 2px 8px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: 500;
			text-transform: uppercase;
			flex-shrink: 0;
		}
		.lukstack-widget-badge.up {
			background: #d7f4dc;
			color: #00700e;
		}
		.lukstack-widget-badge.down {
			background: #fcdddd;
			color: #b32d2e;
		}
		.lukstack-widget-badge.error {
			background: #fef0d5;
			color: #996800;
		}
		.lukstack-widget-badge.unknown {
			background: #f0f0f1;
			color: #646970;
		}
		.lukstack-widget-badge.ssl-warning {
			background: #fef0d5;
			color: #996800;
		}
		.lukstack-widget-badge.ssl-critical {
			background: #fcdddd;
			color: #b32d2e;
		}
		.lukstack-widget-meta {
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.lukstack-widget-response {
			font-size: 11px;
			color: #646970;
		}
		.lukstack-widget-empty {
			text-align: center;
			padding: 20px;
			color: #646970;
		}
		.lukstack-widget-empty .dashicons {
			font-size: 32px;
			width: 32px;
			height: 32px;
			margin-bottom: 10px;
			color: #c3c4c7;
		}
		.lukstack-widget-footer {
			margin-top: 12px;
			padding-top: 12px;
			border-top: 1px solid #f0f0f1;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.lukstack-widget-footer a {
			text-decoration: none;
		}
		.lukstack-widget-last-check {
			font-size: 11px;
			color: #646970;
		}
		.lukstack-widget-alert {
			background: #fcdddd;
			border-left: 4px solid #d63638;
			padding: 10px 12px;
			margin-bottom: 15px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.lukstack-widget-alert .dashicons {
			color: #d63638;
		}
		.lukstack-widget-alert-text {
			font-size: 13px;
			color: #1d2327;
		}
	</style>

	<?php if ( empty( $sites ) ) : ?>
		<div class="lukstack-widget-empty">
			<span class="dashicons dashicons-chart-line"></span>
			<p><?php esc_html_e( 'No websites monitored yet.', 'lukstack-uptime-monitor' ); ?></p>
			<a href="<?php echo esc_url( lukstack_admin_url() ); ?>" class="button button-primary">
				<?php esc_html_e( 'Add First Website', 'lukstack-uptime-monitor' ); ?>
			</a>
		</div>
	<?php else : ?>

		<?php
		// Show alert if sites are down.
		if ( $stats['down'] > 0 ) :
			?>
			<div class="lukstack-widget-alert">
				<span class="dashicons dashicons-warning"></span>
				<span class="lukstack-widget-alert-text">
					<?php
					printf(
						/* translators: %d: Number of sites that are down */
						esc_html( _n( '%d website is currently down!', '%d websites are currently down!', $stats['down'], 'lukstack-uptime-monitor' ) ),
						intval( $stats['down'] )
					);
					?>
				</span>
			</div>
		<?php endif; ?>

		<!-- Stats Grid -->
		<div class="lukstack-widget-stats">
			<div class="lukstack-widget-stat total">
				<div class="lukstack-widget-stat-number"><?php echo intval( $stats['total'] ); ?></div>
				<div class="lukstack-widget-stat-label"><?php esc_html_e( 'Total', 'lukstack-uptime-monitor' ); ?></div>
			</div>
			<div class="lukstack-widget-stat up">
				<div class="lukstack-widget-stat-number"><?php echo intval( $stats['up'] ); ?></div>
				<div class="lukstack-widget-stat-label"><?php esc_html_e( 'Online', 'lukstack-uptime-monitor' ); ?></div>
			</div>
			<div class="lukstack-widget-stat down">
				<div class="lukstack-widget-stat-number"><?php echo intval( $stats['down'] ); ?></div>
				<div class="lukstack-widget-stat-label"><?php esc_html_e( 'Offline', 'lukstack-uptime-monitor' ); ?></div>
			</div>
			<?php if ( $show_ssl && $ssl_monitoring_available ) : ?>
			<div class="lukstack-widget-stat warning">
				<div class="lukstack-widget-stat-number"><?php echo intval( $stats['ssl_expiring_soon'] ); ?></div>
				<div class="lukstack-widget-stat-label"><?php esc_html_e( 'SSL Soon', 'lukstack-uptime-monitor' ); ?></div>
			</div>
			<?php else : ?>
			<div class="lukstack-widget-stat">
				<div class="lukstack-widget-stat-number"><?php echo intval( $stats['error'] ); ?></div>
				<div class="lukstack-widget-stat-label"><?php esc_html_e( 'Errors', 'lukstack-uptime-monitor' ); ?></div>
			</div>
			<?php endif; ?>
		</div>

		<!-- Sites List -->
		<?php
		// Sort sites: down first, then by last_checked desc.
		usort( $sites, function ( $a, $b ) {
			$a_status = strtoupper( $a->status ?? '' );
			$b_status = strtoupper( $b->status ?? '' );

			// Down sites first.
			if ( 'DOWN' === $a_status && 'DOWN' !== $b_status ) {
				return -1;
			}
			if ( 'DOWN' === $b_status && 'DOWN' !== $a_status ) {
				return 1;
			}
			// Then error sites.
			if ( false !== strpos( $a_status, 'ERROR' ) && false === strpos( $b_status, 'ERROR' ) ) {
				return -1;
			}
			if ( false !== strpos( $b_status, 'ERROR' ) && false === strpos( $a_status, 'ERROR' ) ) {
				return 1;
			}
			// Then by last checked (most recent first).
			return strtotime( $b->last_checked ?? '0' ) - strtotime( $a->last_checked ?? '0' );
		} );

		// Limit display.
		$display_sites = array_slice( $sites, 0, $show_count );
		?>

		<ul class="lukstack-widget-sites">
			<?php foreach ( $display_sites as $lukstack_widget_site ) : ?>
				<li>
					<div class="lukstack-widget-site-info">
						<?php
						$status_class = 'unknown';
						$status_text  = __( 'Unknown', 'lukstack-uptime-monitor' );
						$site_status  = strtoupper( $lukstack_widget_site->status ?? '' );

						if ( 'UP' === $site_status ) {
							$status_class = 'up';
							$status_text  = __( 'Up', 'lukstack-uptime-monitor' );
						} elseif ( 'DOWN' === $site_status ) {
							$status_class = 'down';
							$status_text  = __( 'Down', 'lukstack-uptime-monitor' );
						} elseif ( false !== strpos( $site_status, 'ERROR' ) ) {
							$status_class = 'error';
							$status_text  = __( 'Error', 'lukstack-uptime-monitor' );
						}
						?>
						<span class="lukstack-widget-badge <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_text ); ?>
						</span>
						<a href="<?php echo esc_url( $lukstack_widget_site->url ); ?>" target="_blank" rel="noopener noreferrer" class="lukstack-widget-site-url" title="<?php echo esc_attr( $lukstack_widget_site->url ); ?>">
							<?php echo esc_html( lukstack_get_display_url( $lukstack_widget_site->url ) ); ?>
						</a>
					</div>
					<div class="lukstack-widget-meta">
						<?php if ( $lukstack_widget_site->response_time ) : ?>
							<span class="lukstack-widget-response">
								<?php echo esc_html( lukstack_format_response_time( $lukstack_widget_site->response_time ) ); ?>
							</span>
						<?php endif; ?>
						<?php
						// Show SSL warning badge if applicable.
						if ( $show_ssl && $ssl_monitoring_available && null !== $lukstack_widget_site->ssl_days_remaining ) :
							if ( $lukstack_widget_site->ssl_days_remaining <= LUKSTACK_SSL_CRITICAL_DAYS ) :
								?>
								<span class="lukstack-widget-badge ssl-critical" title="<?php esc_attr_e( 'SSL expires soon!', 'lukstack-uptime-monitor' ); ?>">
									<?php
									/* translators: %d: Number of days until SSL expiry */
									printf( esc_html__( '%dd SSL', 'lukstack-uptime-monitor' ), intval( $lukstack_widget_site->ssl_days_remaining ) );
									?>
								</span>
								<?php
							elseif ( $lukstack_widget_site->ssl_days_remaining <= LUKSTACK_SSL_WARNING_DAYS ) :
								?>
								<span class="lukstack-widget-badge ssl-warning" title="<?php esc_attr_e( 'SSL expiring soon', 'lukstack-uptime-monitor' ); ?>">
									<?php
									/* translators: %d: Number of days until SSL expiry */
									printf( esc_html__( '%dd SSL', 'lukstack-uptime-monitor' ), intval( $lukstack_widget_site->ssl_days_remaining ) );
									?>
								</span>
								<?php
							endif;
						endif;
						?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( count( $sites ) > $show_count ) : ?>
			<p style="text-align: center; margin: 10px 0 0; font-size: 12px; color: #646970;">
				<?php
				printf(
					/* translators: %d: Number of additional sites not shown */
					esc_html( _n( '+ %d more site', '+ %d more sites', count( $sites ) - $show_count, 'lukstack-uptime-monitor' ) ),
					count( $sites ) - $show_count
				);
				?>
			</p>
		<?php endif; ?>

		<!-- Footer -->
		<div class="lukstack-widget-footer">
			<a href="<?php echo esc_url( lukstack_admin_url() ); ?>" class="button">
				<?php esc_html_e( 'View All Sites', 'lukstack-uptime-monitor' ); ?>
			</a>
			<?php
			$last_cron = get_option( 'lukstack_last_cron_run' );
			if ( $last_cron && is_array( $last_cron ) && isset( $last_cron['timestamp'] ) ) :
				$timestamp = strtotime( $last_cron['timestamp'] );
				?>
				<span class="lukstack-widget-last-check">
					<?php
					printf(
						/* translators: %s: Human-readable time difference */
						esc_html__( 'Last check: %s ago', 'lukstack-uptime-monitor' ),
						esc_html( human_time_diff( $timestamp, time() ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>

	<?php endif; ?>
	<?php
}

/**
 * Dashboard widget configuration form.
 *
 * @since 2.0.1
 */
function lukstack_dashboard_widget_config() {
	// Save options if form submitted.
	if ( isset( $_POST['lukstack_widget_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lukstack_widget_nonce'] ) ), 'lukstack_widget_config' ) ) {
		$options = array(
			'show_count' => isset( $_POST['lukstack_show_count'] ) ? intval( $_POST['lukstack_show_count'] ) : 5,
			'show_ssl'   => isset( $_POST['lukstack_show_ssl'] ) ? true : false,
		);
		update_option( 'lukstack_dashboard_widget_options', $options );
	}

	// Get current options.
	$options = get_option( 'lukstack_dashboard_widget_options', array(
		'show_count' => 5,
		'show_ssl'   => true,
	) );
	?>
	<p>
		<label for="lukstack_show_count">
			<?php esc_html_e( 'Number of sites to display:', 'lukstack-uptime-monitor' ); ?>
		</label>
		<select id="lukstack_show_count" name="lukstack_show_count">
			<?php
			foreach ( array( 3, 5, 10, 15, 20 ) as $count ) :
				?>
				<option value="<?php echo intval( $count ); ?>" <?php selected( $options['show_count'], $count ); ?>>
					<?php echo intval( $count ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label>
			<input type="checkbox" name="lukstack_show_ssl" value="1" <?php checked( $options['show_ssl'], true ); ?>>
			<?php esc_html_e( 'Show SSL expiry warnings', 'lukstack-uptime-monitor' ); ?>
		</label>
	</p>
	<?php wp_nonce_field( 'lukstack_widget_config', 'lukstack_widget_nonce' ); ?>
	<?php
}

if ( ! function_exists( 'lukstack_get_display_url' ) ) {
	/**
	 * Get display-friendly URL (removes protocol and www).
	 *
	 * @since 2.0.1
	 *
	 * @param string $url Full URL.
	 * @return string Shortened display URL.
	 */
	function lukstack_get_display_url( $url ) {
		$url = preg_replace( '#^https?://#', '', $url );
		$url = preg_replace( '#^www\.#', '', $url );
		$url = rtrim( $url, '/' );
		return $url;
	}
}

if ( ! function_exists( 'lukstack_format_response_time' ) ) {
	/**
	 * Format response time for display.
	 *
	 * Note: Response time is stored in milliseconds in the database.
	 *
	 * @since 2.0.1
	 *
	 * @param float $response_time Response time in milliseconds.
	 * @return string Formatted response time.
	 */
	function lukstack_format_response_time( $response_time ) {
		if ( null === $response_time || '' === $response_time ) {
			return '';
		}

		$response_time = floatval( $response_time );

		// Values are in milliseconds.
		if ( $response_time >= 1000 ) {
			// Show as seconds if >= 1000ms.
			return round( $response_time / 1000, 2 ) . 's';
		}

		// Show as milliseconds.
		return round( $response_time ) . 'ms';
	}
}
