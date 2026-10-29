<?php
/**
 * Main page view template with improved UI, security, and plan gating
 *
 * @package LukStack_Uptime_Monitor
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lukstack_can_performance = lukstack_can_use_performance_tracking();
$lukstack_can_ssl         = lukstack_can_use_ssl_monitoring();
$lukstack_can_add         = lukstack_can_add_site();
$lukstack_col_count       = 6; // URL, Status, SSL, Uptime, Last Checked, Actions
if ( $lukstack_can_performance ) {
	$lukstack_col_count++;
}
// Notify Email column (hide-tablet but still counted)
$lukstack_col_count++;
?>

<div class="wrap lukstack-wrap">
	<h1>
		<?php esc_html_e( 'LukStack Uptime Monitor', 'lukstack-uptime-monitor' ); ?>
		<span class="lukstack-version">v<?php echo esc_html( LUKSTACK_VERSION ); ?></span>
	</h1>

	<?php if ( ! empty( $error_message ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><strong><?php esc_html_e( 'Error:', 'lukstack-uptime-monitor' ); ?></strong> <?php echo esc_html( $error_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $success_message ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $success_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	// Display cron warning only if BOTH the remote cron server is not registered
	// AND WordPress Cron is disabled. If either trigger mechanism is working,
	// monitoring runs fine.
	$lukstack_reg           = function_exists( 'lukstack_get_registration_status' ) ? lukstack_get_registration_status() : array();
	$lukstack_is_registered = isset( $lukstack_reg['status'] ) && 'registered' === $lukstack_reg['status'];
	$lukstack_wp_cron_off   = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

	if ( ! $lukstack_is_registered && $lukstack_wp_cron_off ) :
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Warning:', 'lukstack-uptime-monitor' ); ?></strong>
				<?php esc_html_e( 'The LukStack remote cron server is not registered and WordPress Cron is disabled on this site. Automatic monitoring checks will not run until one of these trigger mechanisms is restored.', 'lukstack-uptime-monitor' ); ?>
				<a href="<?php echo esc_url( lukstack_admin_url( 'settings' ) ); ?>">
					<?php esc_html_e( 'Open settings to re-register', 'lukstack-uptime-monitor' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<!-- Statistics Dashboard -->
	<?php if ( ! empty( $sites ) ) : ?>
		<div class="lukstack-stats">
			<div class="lukstack-stat-card">
				<h3><?php esc_html_e( 'Total Sites', 'lukstack-uptime-monitor' ); ?></h3>
				<div class="stat-value"><?php echo intval( $stats['total'] ); ?></div>
			</div>

			<div class="lukstack-stat-card stat-up">
				<h3><?php esc_html_e( 'Online', 'lukstack-uptime-monitor' ); ?></h3>
				<div class="stat-value">
					<?php echo intval( $stats['up'] ); ?>
				</div>
			</div>

			<div class="lukstack-stat-card stat-down">
				<h3><?php esc_html_e( 'Offline', 'lukstack-uptime-monitor' ); ?></h3>
				<div class="stat-value">
					<?php echo intval( $stats['down'] ); ?>
				</div>
			</div>

			<?php if ( $lukstack_can_ssl && $stats['ssl_expiring_soon'] > 0 ) : ?>
				<div class="lukstack-stat-card stat-warning">
					<h3><?php esc_html_e( 'SSL Expiring', 'lukstack-uptime-monitor' ); ?></h3>
					<div class="stat-value">
						<?php echo intval( $stats['ssl_expiring_soon'] ); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- Add Website Form -->
	<h2><?php esc_html_e( 'Add Website', 'lukstack-uptime-monitor' ); ?></h2>

	<!-- Plan Info Bar -->
	<div class="lukstack-plan-info">
		<?php
		printf(
			/* translators: %1$s: plan name, %2$d: current site count, %3$d: site limit */
			esc_html__( 'Plan: %1$s | Sites: %2$d / %3$d', 'lukstack-uptime-monitor' ),
			esc_html( lukstack_get_current_plan_name() ),
			intval( lukstack_get_site_count() ),
			intval( lukstack_get_site_limit() )
		);
		if ( ! lukstack_is_paid_plan() ) {
			printf(
				' | <a href="%s">%s</a>',
				esc_url( lukstack_get_upgrade_url() ),
				esc_html__( 'Upgrade to Pro', 'lukstack-uptime-monitor' )
			);
		}
		?>
	</div>

	<?php if ( ! $lukstack_can_add ) : ?>
		<div class="notice notice-warning" style="margin-bottom: 16px;">
			<p>
				<?php
				printf(
					/* translators: %d: site limit */
					esc_html__( 'You have reached the limit of %d websites on the Free plan.', 'lukstack-uptime-monitor' ),
					lukstack_get_site_limit()
				);
				?>
				<a href="<?php echo esc_url( lukstack_get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;">
					<?php esc_html_e( 'Upgrade to Pro', 'lukstack-uptime-monitor' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" id="lukstack-add-form" class="lukstack-form" action="">
		<?php wp_nonce_field( 'lukstack_add_site' ); ?>
		<input type="hidden" name="lukstack_action" value="add_site">

		<div class="form-row">
			<label for="lukstack_url" class="screen-reader-text"><?php esc_html_e( 'Website URL', 'lukstack-uptime-monitor' ); ?></label>
			<input
				type="url"
				name="url"
				id="lukstack_url"
				required
				placeholder="https://example.com"
				class="regular-text"
				value=""
				<?php disabled( ! $lukstack_can_add ); ?>
			>

			<label for="lukstack_email" class="screen-reader-text"><?php esc_html_e( 'Notification email', 'lukstack-uptime-monitor' ); ?></label>
			<input
				type="email"
				name="email"
				id="lukstack_email"
				placeholder="<?php esc_attr_e( 'alert@email.com (optional)', 'lukstack-uptime-monitor' ); ?>"
				class="regular-text"
				value=""
				<?php disabled( ! $lukstack_can_add ); ?>
			>

			<button class="button button-primary" name="add_site" type="submit" value="1" <?php disabled( ! $lukstack_can_add ); ?>>
				<?php esc_html_e( 'Add Website', 'lukstack-uptime-monitor' ); ?>
			</button>
		</div>

		<p class="description">
			<?php esc_html_e( 'Enter the full URL including http:// or https://. The optional email will receive alerts for this specific website.', 'lukstack-uptime-monitor' ); ?>
		</p>
	</form>

	<hr>

	<!-- Monitored Websites -->
	<div class="lukstack-header">
		<h2>
			<?php
			printf(
				/* translators: %d: Number of monitored websites */
				esc_html__( 'Monitored Websites (%d)', 'lukstack-uptime-monitor' ),
				count( $sites )
			);
			?>
		</h2>

		<?php if ( ! empty( $sites ) ) : ?>
			<button
				class="button lukstack-bulk-check"
				type="button"
				title="<?php esc_attr_e( 'Check all websites now', 'lukstack-uptime-monitor' ); ?>"
			>
				<?php esc_html_e( 'Check All Now', 'lukstack-uptime-monitor' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $lukstack_is_registered ) : ?>
		<p class="description lukstack-cron-info">
			<?php
			printf(
				/* translators: %d: plan check interval in minutes */
				esc_html__( 'Automatic checks run every %d minutes via the LukStack remote cron server.', 'lukstack-uptime-monitor' ),
				intval( lukstack_get_plan_check_interval() )
			);
			?>
		</p>
	<?php elseif ( ! empty( $cron_status ) && $cron_status['is_scheduled'] ) : ?>
		<p class="description lukstack-cron-info">
			<?php
			printf(
				/* translators: %s: Date and time of next automatic check */
				esc_html__( 'Next automatic check (WP Cron fallback): %s', 'lukstack-uptime-monitor' ),
				'<strong>' . esc_html( $cron_status['next_run_formatted'] ) . '</strong>'
			);
			?>
		</p>
	<?php endif; ?>

	<div class="table-container">
		<table class="widefat striped lukstack-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'lukstack-uptime-monitor' ); ?></th>
				<th><?php esc_html_e( 'Status', 'lukstack-uptime-monitor' ); ?></th>
				<?php if ( $lukstack_can_performance ) : ?>
					<th class="hide-tablet"><?php esc_html_e( 'Performance', 'lukstack-uptime-monitor' ); ?></th>
				<?php endif; ?>
				<?php if ( $lukstack_can_ssl ) : ?>
					<th><?php esc_html_e( 'SSL Certificate', 'lukstack-uptime-monitor' ); ?></th>
				<?php else : ?>
					<th><?php esc_html_e( 'SSL Certificate', 'lukstack-uptime-monitor' ); ?> <span class="lukstack-pro-badge">PRO</span></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Uptime', 'lukstack-uptime-monitor' ); ?></th>
				<th class="hide-tablet"><?php esc_html_e( 'Notify Email', 'lukstack-uptime-monitor' ); ?></th>
				<th><?php esc_html_e( 'Last Checked', 'lukstack-uptime-monitor' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'lukstack-uptime-monitor' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $sites ) ) : ?>
				<?php foreach ( $sites as $lukstack_site ) : ?>
					<tr data-id="<?php echo absint( $lukstack_site->id ); ?>">
						<td class="site-url" data-label="<?php esc_attr_e( 'URL', 'lukstack-uptime-monitor' ); ?>">
							<a href="<?php echo esc_url( $lukstack_site->url ); ?>"
							   target="_blank"
							   rel="noopener noreferrer"
							   title="<?php esc_attr_e( 'Open website in new tab', 'lukstack-uptime-monitor' ); ?>">
								<?php echo esc_html( $lukstack_site->url ); ?>
							</a>
						</td>

						<td class="status" data-label="<?php esc_attr_e( 'Status', 'lukstack-uptime-monitor' ); ?>">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render function returns escaped HTML.
							echo lukstack_render_status( $lukstack_site->status );
							?>
						</td>

						<?php if ( $lukstack_can_performance ) : ?>
							<td class="response-time hide-tablet" data-label="<?php esc_attr_e( 'Performance', 'lukstack-uptime-monitor' ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render function returns escaped HTML.
								echo lukstack_render_response_time( $lukstack_site->response_time );
								?>
							</td>
						<?php endif; ?>

						<?php if ( $lukstack_can_ssl ) : ?>
							<td class="ssl-expiry" data-label="<?php esc_attr_e( 'SSL', 'lukstack-uptime-monitor' ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render function returns escaped HTML.
								echo lukstack_render_ssl_expiry( $lukstack_site->ssl_days_remaining, $lukstack_site->ssl_expiry_date );
								?>
							</td>
						<?php else : ?>
							<td class="ssl-expiry" data-label="<?php esc_attr_e( 'SSL', 'lukstack-uptime-monitor' ); ?>">
								<a href="<?php echo esc_url( lukstack_get_upgrade_url() ); ?>" title="<?php esc_attr_e( 'Upgrade to Pro for SSL monitoring', 'lukstack-uptime-monitor' ); ?>">
									<span style="color: var(--gray-400);"><?php esc_html_e( 'Pro', 'lukstack-uptime-monitor' ); ?></span>
								</a>
							</td>
						<?php endif; ?>

						<td class="uptime" data-label="<?php esc_attr_e( 'Uptime', 'lukstack-uptime-monitor' ); ?>">
							<?php
							$lukstack_uptime = lukstack_calculate_uptime( $lukstack_site );
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Render function returns escaped HTML.
							echo lukstack_render_uptime( $lukstack_uptime );
							?>
						</td>

						<td class="notify-email hide-tablet" data-label="<?php esc_attr_e( 'Email', 'lukstack-uptime-monitor' ); ?>">
							<?php
							if ( ! empty( $lukstack_site->notify_email ) ) {
								printf(
									'<span class="lukstack-email" title="%s">&#9993; %s</span>',
									esc_attr( $lukstack_site->notify_email ),
									esc_html( $lukstack_site->notify_email )
								);
							} else {
								echo '<span class="lukstack-email-empty">&mdash;</span>';
							}
							?>
						</td>

						<td class="last-checked" data-label="<?php esc_attr_e( 'Last Checked', 'lukstack-uptime-monitor' ); ?>">
							<?php
							if ( $lukstack_site->last_checked ) {
								echo '<span title="' . esc_attr( $lukstack_site->last_checked ) . '">' .
									esc_html( lukstack_time_ago( $lukstack_site->last_checked ) ) . '</span>';
							} else {
								esc_html_e( 'Never', 'lukstack-uptime-monitor' );
							}
							?>
						</td>

						<td class="actions" data-label="">
							<div class="lukstack-action-buttons">
								<button
									class="button lukstack-check"
									data-id="<?php echo absint( $lukstack_site->id ); ?>"
									type="button"
									title="<?php esc_attr_e( 'Check this website now', 'lukstack-uptime-monitor' ); ?>"
								>
									<?php esc_html_e( 'Check now', 'lukstack-uptime-monitor' ); ?>
								</button>
								<button
									class="button lukstack-delete"
									data-id="<?php echo absint( $lukstack_site->id ); ?>"
									type="button"
									title="<?php esc_attr_e( 'Remove from monitoring', 'lukstack-uptime-monitor' ); ?>"
								>
									<?php esc_html_e( 'Delete', 'lukstack-uptime-monitor' ); ?>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="<?php echo intval( $lukstack_col_count ); ?>" class="no-sites">
						<?php esc_html_e( 'No websites added yet. Add your first website above!', 'lukstack-uptime-monitor' ); ?>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
