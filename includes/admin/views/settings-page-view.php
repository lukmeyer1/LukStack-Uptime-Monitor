<?php
/**
 * Settings page view template
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap lukstack-wrap">
	<h1><?php esc_html_e( 'LukStack Uptime Monitor – Settings', 'lukstack-uptime-monitor' ); ?></h1>

	<!-- Plan Info Card -->
	<div class="lukstack-plan-info" style="margin-bottom: 20px; padding: 16px 20px;">
		<strong>
			<?php
			printf(
				/* translators: %s: plan name */
				esc_html__( 'Current Plan: %s', 'lukstack-uptime-monitor' ),
				esc_html( lukstack_get_current_plan_name() )
			);
			?>
		</strong>
		<span style="margin-left: 12px; color: var(--gray-600);">
			<?php
			printf(
				/* translators: %1$d: check interval in minutes, %2$d: current site count, %3$d: site limit */
				esc_html__( 'Check interval: every %1$d minutes | Sites: %2$d / %3$d', 'lukstack-uptime-monitor' ),
				lukstack_get_plan_check_interval(),
				lukstack_get_site_count(),
				lukstack_get_site_limit()
			);
			?>
		</span>
		<?php if ( ! lukstack_is_paid_plan() ) : ?>
			<a href="<?php echo esc_url( lukstack_get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 12px; vertical-align: baseline;">
				<?php esc_html_e( 'Upgrade to Pro', 'lukstack-uptime-monitor' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( $error_message ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><strong><?php esc_html_e( 'Error:', 'lukstack-uptime-monitor' ); ?></strong> <?php echo esc_html( $error_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $success_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $success_message ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'lukstack_settings' ); ?>
		<table class="form-table">
			<tr>
				<th><label for="webhook_url"><?php esc_html_e( 'Webhook URL', 'lukstack-uptime-monitor' ); ?></label></th>
				<td>
					<input type="url"
						   name="webhook_url"
						   id="webhook_url"
						   class="regular-text"
						   value="<?php echo esc_attr( $webhook_url ); ?>"
						   placeholder="https://hooks.slack.com/services/...">
					<p class="description">
						<?php esc_html_e( 'Optional: Send alerts to a webhook URL (Slack, Discord, Teams, etc.)', 'lukstack-uptime-monitor' ); ?><br>
						<?php esc_html_e( 'Leave empty to disable webhook notifications.', 'lukstack-uptime-monitor' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Email Notifications', 'lukstack-uptime-monitor' ); ?></th>
				<td>
					<?php
					$lukstack_fs_status = function_exists( 'lukstack_get_freemius_status' ) ? lukstack_get_freemius_status() : 'unknown';
					$lukstack_admin_email = get_option( 'admin_email' );

					if ( in_array( $lukstack_fs_status, array( 'paying', 'trial', 'registered' ), true ) ) :
						?>
						<p style="margin: 0;">
							<span class="lukstack-chip lukstack-chip--success">
								<?php esc_html_e( 'CONNECTED', 'lukstack-uptime-monitor' ); ?>
							</span>
							<?php
							printf(
								/* translators: %s: admin email address */
								esc_html__( 'Fallback alerts go to %s. Each monitored site can override this with its own email.', 'lukstack-uptime-monitor' ),
								'<code>' . esc_html( $lukstack_admin_email ) . '</code>'
							);
							?>
						</p>
					<?php elseif ( 'pending' === $lukstack_fs_status ) : ?>
						<p style="margin: 0;">
							<span class="lukstack-chip lukstack-chip--warning">
								<?php esc_html_e( 'PENDING', 'lukstack-uptime-monitor' ); ?>
							</span>
							<?php esc_html_e( 'Please confirm your email at Freemius to activate alerts.', 'lukstack-uptime-monitor' ); ?>
						</p>
					<?php else : ?>
						<p style="margin: 0 0 8px 0;">
							<?php
							printf(
								/* translators: %s: admin email address */
								esc_html__( 'Email alerts currently go to %s.', 'lukstack-uptime-monitor' ),
								'<code>' . esc_html( $lukstack_admin_email ) . '</code>'
							);
							?>
							<strong><?php esc_html_e( 'Connect your account', 'lukstack-uptime-monitor' ); ?></strong>
							<?php esc_html_e( 'to unlock status reports, advanced alert routing, and a free 7-day Pro trial.', 'lukstack-uptime-monitor' ); ?>
						</p>
						<?php
						if ( function_exists( 'wpls_fm' ) ) {
							try {
								$lukstack_connect_url = wpls_fm()->get_activation_url();
								if ( ! empty( $lukstack_connect_url ) ) {
									?>
									<a href="<?php echo esc_url( $lukstack_connect_url ); ?>" class="button">
										<?php esc_html_e( 'Connect account', 'lukstack-uptime-monitor' ); ?>
									</a>
									<?php
								}
							} catch ( Exception $e ) {
								// Fail silently — UI is non-essential.
							}
						}
					endif;
					?>
				</td>
			</tr>
		</table>
		<button class="button button-primary" name="save_settings" type="submit">
			<?php esc_html_e( 'Save Settings', 'lukstack-uptime-monitor' ); ?>
		</button>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Test Webhook', 'lukstack-uptime-monitor' ); ?></h2>
	<p><?php esc_html_e( 'Send a test notification to verify your webhook is working correctly:', 'lukstack-uptime-monitor' ); ?></p>

	<?php if ( empty( $webhook_url ) ) : ?>
		<p class="description" style="color: #dc3232;">
			<strong><?php esc_html_e( 'No webhook URL configured.', 'lukstack-uptime-monitor' ); ?></strong>
			<?php esc_html_e( 'Please save a webhook URL above before testing.', 'lukstack-uptime-monitor' ); ?>
		</p>
	<?php endif; ?>

	<button class="button"
			id="test-webhook"
			type="button"
		<?php echo empty( $webhook_url ) ? 'disabled' : ''; ?>>
		<?php esc_html_e( 'Send Test Notification', 'lukstack-uptime-monitor' ); ?>
	</button>
	<div id="webhook-test-result" style="margin-top: 10px;"></div>

	<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) : ?>
		<hr>
		<h2>🛠 <?php esc_html_e( 'Remote Cron (Debug)', 'lukstack-uptime-monitor' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'This section is only visible when WP_DEBUG is enabled. Use these values to trigger monitoring checks from an external cron server.', 'lukstack-uptime-monitor' ); ?>
		</p>
		<?php
		$lukstack_remote_secret   = lukstack_get_or_create_remote_cron_secret();
		$lukstack_remote_endpoint = rest_url( LUKSTACK_REMOTE_CRON_NAMESPACE . LUKSTACK_REMOTE_CRON_ROUTE );
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Endpoint URL', 'lukstack-uptime-monitor' ); ?></th>
				<td>
					<input type="text" readonly class="regular-text code" style="width: 100%; max-width: 600px;"
						value="<?php echo esc_attr( $lukstack_remote_endpoint ); ?>"
						onclick="this.select();">
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Shared Secret', 'lukstack-uptime-monitor' ); ?></th>
				<td>
					<input type="text" readonly class="regular-text code" style="width: 100%; max-width: 600px;"
						value="<?php echo esc_attr( $lukstack_remote_secret ); ?>"
						onclick="this.select();">
					<p class="description">
						<?php esc_html_e( 'Send this as an X-LukStack-Secret header. Keep it private.', 'lukstack-uptime-monitor' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Test with curl', 'lukstack-uptime-monitor' ); ?></th>
				<td>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:4px;overflow-x:auto;font-size:12px;">curl -X POST <?php echo esc_html( $lukstack_remote_endpoint ); ?> \
  -H "X-LukStack-Secret: <?php echo esc_html( $lukstack_remote_secret ); ?>"</pre>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Cron Server Registration', 'lukstack-uptime-monitor' ); ?></h3>
		<?php
		$lukstack_registration = lukstack_get_registration_status();
		$lukstack_reg_status   = isset( $lukstack_registration['status'] ) ? $lukstack_registration['status'] : 'not_registered';
		$lukstack_reg_color    = 'registered' === $lukstack_reg_status ? '#10b981' : ( 'failed' === $lukstack_reg_status ? '#ef4444' : '#9ca3af' );
		?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Status', 'lukstack-uptime-monitor' ); ?></th>
				<td>
					<strong style="color: <?php echo esc_attr( $lukstack_reg_color ); ?>;">
						<?php echo esc_html( strtoupper( $lukstack_reg_status ) ); ?>
					</strong>
				</td>
			</tr>
			<?php if ( ! empty( $lukstack_registration['site_id'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Site ID (AWS)', 'lukstack-uptime-monitor' ); ?></th>
					<td><code><?php echo esc_html( $lukstack_registration['site_id'] ); ?></code></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $lukstack_registration['plan'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Plan on server', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( $lukstack_registration['plan'] ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $lukstack_registration['registered_at'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Registered at', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $lukstack_registration['registered_at'] ) ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $lukstack_registration['error'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Last error', 'lukstack-uptime-monitor' ); ?></th>
					<td><code style="color:#ef4444;"><?php echo esc_html( $lukstack_registration['error'] ); ?></code></td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $lukstack_registration['last_attempt_at'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Last attempt', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $lukstack_registration['last_attempt_at'] ) ); ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Actions', 'lukstack-uptime-monitor' ); ?></th>
				<td>
					<form method="post" style="display:inline;">
						<?php wp_nonce_field( 'lukstack_force_register' ); ?>
						<button type="submit" name="lukstack_force_register" class="button">
							<?php esc_html_e( 'Force re-register now', 'lukstack-uptime-monitor' ); ?>
						</button>
					</form>
				</td>
			</tr>
		</table>
	<?php endif; ?>
</div>
