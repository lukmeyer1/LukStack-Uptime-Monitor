<?php
/**
 * Help & Info page view template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Allowed HTML tags for wp_kses in translatable strings.
$lukstack_allowed_tags = array(
	'strong' => array(),
	'code'   => array(),
	'a'      => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
	),
	'br'     => array(),
);
?>

<div class="wrap lukstack-wrap">
	<h1><?php esc_html_e( 'LukStack Uptime Monitor Documentation', 'lukstack-uptime-monitor' ); ?></h1>

	<div class="lukstack-help-container">

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Overview', 'lukstack-uptime-monitor' ); ?></h2>
			<p><?php esc_html_e( 'LukStack Uptime Monitor is a website monitoring plugin for agencies and web professionals. It tracks uptime, response times, and SSL certificate expiration for multiple websites from a single WordPress installation.', 'lukstack-uptime-monitor' ); ?></p>
			<p><?php esc_html_e( 'When a monitored site goes down or returns an error, you receive notifications via email and optional webhook integrations (Slack, Discord, Microsoft Teams, etc.).', 'lukstack-uptime-monitor' ); ?></p>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Features', 'lukstack-uptime-monitor' ); ?></h2>
			<table class="lukstack-info-table">
				<tr>
					<th><?php esc_html_e( 'Uptime Monitoring', 'lukstack-uptime-monitor' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %d: check interval in minutes */
							esc_html__( 'Automatic checks every %d minutes with UP/DOWN/ERROR status reporting', 'lukstack-uptime-monitor' ),
							lukstack_get_plan_check_interval()
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Response Time', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Measures server response time in milliseconds for performance tracking', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SSL Monitoring', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Tracks certificate expiration and warns before certificates expire', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email Alerts', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Sends notifications when status changes, with per-site email configuration', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Webhook Integration', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Native support for Slack, Discord, and generic webhooks (Zapier, Make, etc.)', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Manual Checks', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Test any website immediately without waiting for scheduled checks', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
			</table>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Getting Started', 'lukstack-uptime-monitor' ); ?></h2>

			<h3><?php esc_html_e( 'Adding a Website', 'lukstack-uptime-monitor' ); ?></h3>
			<ol>
				<li>
					<?php
					echo wp_kses(
						__( 'Navigate to <strong>LukStack Uptime Monitor</strong> in the admin menu', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'Enter the full URL including protocol (e.g., <code>https://example.com</code>)', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li><?php esc_html_e( 'Optionally specify an email address for notifications', 'lukstack-uptime-monitor' ); ?></li>
				<li>
					<?php
					echo wp_kses(
						__( 'Click <strong>Add Website</strong>', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
			</ol>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %d: check interval in minutes */
						__( 'The site will be checked automatically within %d minutes. Use the <strong>Check now</strong> button for immediate testing.', 'lukstack-uptime-monitor' ),
						lukstack_get_plan_check_interval()
					),
					$lukstack_allowed_tags
				);
				?>
			</p>

			<h3><?php esc_html_e( 'Understanding Status Indicators', 'lukstack-uptime-monitor' ); ?></h3>
			<table class="lukstack-info-table">
				<tr>
					<th><span class="lukstack-badge up"><?php esc_html_e( 'UP', 'lukstack-uptime-monitor' ); ?></span></th>
					<td><?php esc_html_e( 'Website responded with HTTP 2xx or 3xx status code', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><span class="lukstack-badge down"><?php esc_html_e( 'DOWN', 'lukstack-uptime-monitor' ); ?></span></th>
					<td><?php esc_html_e( 'Website is unreachable (connection failed, timeout, DNS error)', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><span class="lukstack-badge error"><?php esc_html_e( 'ERROR 4xx/5xx', 'lukstack-uptime-monitor' ); ?></span></th>
					<td><?php esc_html_e( 'Website responded but returned a client or server error', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><span class="lukstack-badge unknown"><?php esc_html_e( 'UNKNOWN', 'lukstack-uptime-monitor' ); ?></span></th>
					<td><?php esc_html_e( 'Website has not been checked yet', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Response Time Indicators', 'lukstack-uptime-monitor' ); ?></h3>
			<table class="lukstack-info-table">
				<tr>
					<th style="color: #10b981;"><?php esc_html_e( 'Green', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Fast response, under 1 second', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th style="color: #f59e0b;"><?php esc_html_e( 'Orange', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Slow response, 1-5 seconds', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th style="color: #ef4444;"><?php esc_html_e( 'Red', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Very slow response, over 5 seconds', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'SSL Certificate Status', 'lukstack-uptime-monitor' ); ?></h3>
			<table class="lukstack-info-table">
				<tr>
					<th style="color: #10b981;"><?php esc_html_e( 'Green', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Certificate valid for more than 30 days', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th style="color: #f59e0b;"><?php esc_html_e( 'Orange', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Certificate expires within 30 days', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th style="color: #ef4444;"><?php esc_html_e( 'Red', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Certificate expires within 7 days or has expired', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
			</table>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Notifications', 'lukstack-uptime-monitor' ); ?></h2>

			<h3><?php esc_html_e( 'Email Notifications', 'lukstack-uptime-monitor' ); ?></h3>
			<p><?php esc_html_e( 'When a website\'s status changes, LukStack Uptime Monitor sends an email to:', 'lukstack-uptime-monitor' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'The site-specific email address (if configured when adding the site)', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'The WordPress admin email (as fallback)', 'lukstack-uptime-monitor' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'Notifications are sent for status changes only, not for every check. This prevents inbox flooding.', 'lukstack-uptime-monitor' ); ?></p>

			<h3><?php esc_html_e( 'Webhook Notifications', 'lukstack-uptime-monitor' ); ?></h3>
			<p>
				<?php
				echo wp_kses(
					__( 'Configure a webhook URL in <strong>Settings</strong> to receive notifications in external services. LukStack Uptime Monitor automatically detects and formats messages for:', 'lukstack-uptime-monitor' ),
					$lukstack_allowed_tags
				);
				?>
			</p>
			<ul>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Discord</strong> – Rich embeds with color-coded severity', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Slack</strong> – Formatted attachments with fields', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Generic webhooks</strong> – Raw JSON for Zapier, Make, custom endpoints', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
			</ul>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Webhook Setup', 'lukstack-uptime-monitor' ); ?></h2>

			<h3><?php esc_html_e( 'Discord', 'lukstack-uptime-monitor' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Open Server Settings in your Discord server', 'lukstack-uptime-monitor' ); ?></li>
				<li>
					<?php
					echo wp_kses(
						__( 'Go to <strong>Integrations</strong> and click <strong>Webhooks</strong>', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'Click <strong>New Webhook</strong> and select a channel', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li><?php esc_html_e( 'Copy the webhook URL', 'lukstack-uptime-monitor' ); ?></li>
				<li>
					<?php
					echo wp_kses(
						__( 'Paste into <strong>LukStack Uptime Monitor &gt; Settings &gt; Webhook URL</strong>', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'Click <strong>Send Test Notification</strong> to verify', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
			</ol>

			<h3><?php esc_html_e( 'Slack', 'lukstack-uptime-monitor' ); ?></h3>
			<ol>
				<li>
					<?php
					printf(
						wp_kses(
							/* translators: %s: URL to Slack API apps page */
							__( 'Go to <a href="%s" target="_blank" rel="noopener">api.slack.com/apps</a>', 'lukstack-uptime-monitor' ),
							$lukstack_allowed_tags
						),
						'https://api.slack.com/apps'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Create a new app or select an existing one', 'lukstack-uptime-monitor' ); ?></li>
				<li>
					<?php
					echo wp_kses(
						__( 'Enable <strong>Incoming Webhooks</strong>', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li><?php esc_html_e( 'Add a new webhook to your workspace and select a channel', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Copy the webhook URL and paste into LukStack Uptime Monitor settings', 'lukstack-uptime-monitor' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Generic Webhooks (Zapier, Make, etc.)', 'lukstack-uptime-monitor' ); ?></h3>
			<p><?php esc_html_e( 'LukStack Uptime Monitor sends a POST request with JSON body. Create a webhook trigger in your automation tool and use the provided URL.', 'lukstack-uptime-monitor' ); ?></p>

			<h3><?php esc_html_e( 'Webhook Payload', 'lukstack-uptime-monitor' ); ?></h3>
			<p><?php esc_html_e( 'Status change notifications include the following data:', 'lukstack-uptime-monitor' ); ?></p>
			<pre><code>{
	"type": "status_change",
	"severity": "CRITICAL",
	"site_id": 1,
	"url": "https://example.com",
	"old_status": "UP",
	"new_status": "DOWN",
	"timestamp": "2025-01-31 12:30:00",
	"unix_timestamp": 1738327800,
	"site_name": "My WordPress Site"
}</code></pre>
			<p>
				<?php
				echo wp_kses(
					__( 'Severity levels: <code>CRITICAL</code> (site down), <code>ERROR</code> (4xx/5xx response), <code>RESOLVED</code> (site recovered), <code>INFO</code> (other changes)', 'lukstack-uptime-monitor' ),
					$lukstack_allowed_tags
				);
				?>
			</p>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'How Monitoring Runs', 'lukstack-uptime-monitor' ); ?></h2>
			<p><?php esc_html_e( 'LukStack Uptime Monitor uses a built-in remote cron server (hosted on AWS, EU Frankfurt region) to trigger checks on your site. No third-party cron services like cron-job.org or UptimeRobot are required — everything works out of the box.', 'lukstack-uptime-monitor' ); ?></p>

			<h3><?php esc_html_e( 'How It Works', 'lukstack-uptime-monitor' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'On plugin activation, your site is automatically registered with the LukStack cron server using a locally generated 48-character shared secret.', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Every minute, the cron server sends an authenticated POST request to a REST API endpoint on your site.', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'That request triggers the same monitoring logic as WordPress Cron — but on a precise, reliable schedule, independent of visitor traffic.', 'lukstack-uptime-monitor' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Check Interval', 'lukstack-uptime-monitor' ); ?></h3>
			<p>
				<?php
				echo wp_kses(
					__( 'The cron server calls your site every minute, but your configured plan interval still applies: <strong>Free = every 15 minutes, Pro = every 5 minutes</strong>. Surplus calls become cheap no-op database queries.', 'lukstack-uptime-monitor' ),
					$lukstack_allowed_tags
				);
				?>
			</p>

			<h3><?php esc_html_e( 'Security', 'lukstack-uptime-monitor' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'A 48-character shared secret is generated locally and stored in wp_options with autoload disabled.', 'lukstack-uptime-monitor' ); ?></li>
				<li>
					<?php
					echo wp_kses(
						__( 'Requests are authenticated via an <code>X-LukStack-Secret</code> header, compared using <code>hash_equals()</code> (constant-time) to prevent timing attacks.', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li><?php esc_html_e( 'Invalid or missing secrets result in HTTP 403, with the requesting IP logged.', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'The secret is transmitted to the cron server once during registration; subsequent requests only compare its hash.', 'lukstack-uptime-monitor' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Fallback: WordPress Cron', 'lukstack-uptime-monitor' ); ?></h3>
			<p>
				<?php
				echo wp_kses(
					__( 'WordPress Cron remains scheduled as a fallback. If the remote cron server is ever unreachable, WordPress Cron will still run checks whenever visitors hit your site. Do not disable it with <code>DISABLE_WP_CRON</code> unless you have a server cron replacement.', 'lukstack-uptime-monitor' ),
					$lukstack_allowed_tags
				);
				?>
			</p>

			<h3><?php esc_html_e( 'Advanced: Trigger Manually', 'lukstack-uptime-monitor' ); ?></h3>
			<p><?php esc_html_e( 'You can also trigger the monitoring endpoint from your own cron or infrastructure if you prefer. The endpoint URL and shared secret are only visible when WP_DEBUG is enabled in wp-config.php — check the Settings page in that case.', 'lukstack-uptime-monitor' ); ?></p>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Troubleshooting', 'lukstack-uptime-monitor' ); ?></h2>

			<h3><?php esc_html_e( 'Website shows DOWN but is actually online', 'lukstack-uptime-monitor' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'The website may block automated requests or specific user agents', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'A firewall or security plugin might be blocking the monitoring server', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'The site may require authentication or have geographic restrictions', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Verify the URL is correct and accessible from your server', 'lukstack-uptime-monitor' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Not receiving email notifications', 'lukstack-uptime-monitor' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Check spam/junk folders', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Verify WordPress can send emails (test with a plugin like WP Mail SMTP)', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Confirm the notification email address is correct', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Check your server\'s mail logs for delivery errors', 'lukstack-uptime-monitor' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Webhook notifications not arriving', 'lukstack-uptime-monitor' ); ?></h3>
			<ul>
				<li>
					<?php
					echo wp_kses(
						__( 'Use the <strong>Send Test Notification</strong> button to verify connectivity', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li><?php esc_html_e( 'Check that the webhook URL is correct and the service is active', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Ensure your server can make outbound HTTPS requests', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Check for firewall rules blocking outbound connections', 'lukstack-uptime-monitor' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Automatic checks not running', 'lukstack-uptime-monitor' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Your site is registered automatically with the remote cron server. If registration failed, open the plugin Settings page (with WP_DEBUG enabled) to see the registration status and retry.', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Ensure your site is publicly reachable — the cron server cannot trigger sites behind basic auth, IP whitelists, or private networks.', 'lukstack-uptime-monitor' ); ?></li>
				<li>
					<?php
					echo wp_kses(
						__( 'If your firewall or security plugin blocks unusual user agents, allow-list <code>LukStack-Cron-Runner</code>.', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'WordPress Cron remains as a fallback. If <code>DISABLE_WP_CRON</code> is set and the remote cron is unreachable, no checks will run.', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
			</ul>

			<h3><?php esc_html_e( 'Cannot add a website', 'lukstack-uptime-monitor' ); ?></h3>
			<ul>
				<li>
					<?php
					echo wp_kses(
						__( 'URL must include protocol (<code>http://</code> or <code>https://</code>)', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li><?php esc_html_e( 'Localhost and private IP addresses are blocked for security', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Each URL can only be monitored once', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'URL must be under 255 characters', 'lukstack-uptime-monitor' ); ?></li>
			</ul>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Best Practices', 'lukstack-uptime-monitor' ); ?></h2>
			<ul>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Monitor the homepage</strong> – Use the root domain rather than specific pages unless you need to monitor a particular endpoint', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Use HTTPS URLs</strong> – This enables SSL certificate monitoring and is more representative of user experience', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Keep your site publicly reachable</strong> – The remote cron server needs to reach your <code>/wp-json/lukstack/v1/trigger</code> endpoint. Avoid basic auth or IP whitelists on that path.', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Configure webhooks</strong> – Email can be slow or filtered; webhooks provide faster, more reliable alerts', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Use site-specific emails</strong> – Route alerts for different clients to the appropriate contacts', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Test after setup</strong> – Always verify webhooks work using the test button', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Review regularly</strong> – Remove sites that are no longer active to keep your dashboard clean', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
			</ul>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Technical Reference', 'lukstack-uptime-monitor' ); ?></h2>
			<table class="lukstack-info-table">
				<tr>
					<th><?php esc_html_e( 'Check Interval', 'lukstack-uptime-monitor' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %1$d: interval in minutes, %2$d: interval in seconds */
							esc_html__( '%1$d minutes (%2$d seconds)', 'lukstack-uptime-monitor' ),
							lukstack_get_plan_check_interval(),
							lukstack_get_plan_check_interval() * 60
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'HTTP Timeout', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( '30 seconds', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Webhook Timeout', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( '10 seconds', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SSL Check Timeout', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( '10 seconds', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Success Codes', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'HTTP 200-299 and 300-399', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SSL Warning Threshold', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( '30 days before expiration', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SSL Critical Threshold', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( '7 days before expiration', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Database Table', 'lukstack-uptime-monitor' ); ?></th>
					<td><code><?php echo esc_html( $GLOBALS['wpdb']->prefix ); ?>lukstack_sites</code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Cron Hook (fallback)', 'lukstack-uptime-monitor' ); ?></th>
					<td><code>lukstack_cron_event</code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Remote Cron Endpoint', 'lukstack-uptime-monitor' ); ?></th>
					<td><code>/wp-json/lukstack/v1/trigger</code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Remote Cron Region', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'AWS EU Frankfurt (eu-central-1)', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Remote Cron Frequency', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( 'Every 1 minute (plan interval still applies)', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Shared Secret Length', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php esc_html_e( '48 alphanumeric characters', 'lukstack-uptime-monitor' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'User Agent', 'lukstack-uptime-monitor' ); ?></th>
					<td><code>LukStack-Uptime-Monitor/<?php echo esc_html( LUKSTACK_VERSION ); ?></code></td>
				</tr>
			</table>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'URL Restrictions', 'lukstack-uptime-monitor' ); ?></h2>
			<p><?php esc_html_e( 'For security reasons, the following URLs cannot be monitored:', 'lukstack-uptime-monitor' ); ?></p>
			<ul>
				<li>
					<?php
					echo wp_kses(
						__( '<code>localhost</code> and <code>127.0.0.1</code>', 'lukstack-uptime-monitor' ),
						$lukstack_allowed_tags
					);
					?>
				</li>
				<li><?php esc_html_e( 'Private IP ranges (192.168.x.x, 10.x.x.x, 172.16-31.x.x)', 'lukstack-uptime-monitor' ); ?></li>
				<li><?php esc_html_e( 'Reserved IP addresses', 'lukstack-uptime-monitor' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'This prevents the plugin from being used to probe internal networks.', 'lukstack-uptime-monitor' ); ?></p>
		</div>

		<div class="lukstack-help-card">
			<h2><?php esc_html_e( 'Debug Information', 'lukstack-uptime-monitor' ); ?></h2>
			<?php
			$cron_status         = lukstack_get_cron_status();
			$lukstack_help_reg   = function_exists( 'lukstack_get_registration_status' ) ? lukstack_get_registration_status() : array();
			$lukstack_reg_status = isset( $lukstack_help_reg['status'] ) ? $lukstack_help_reg['status'] : 'not_registered';
			?>
			<table class="lukstack-info-table">
				<tr>
					<th><?php esc_html_e( 'Plugin Version', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( LUKSTACK_VERSION ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WordPress Version', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'PHP Version', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( PHP_VERSION ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Remote Cron Registration', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( strtoupper( $lukstack_reg_status ) ); ?></td>
				</tr>
				<?php if ( ! empty( $lukstack_help_reg['registered_at'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Registered at', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $lukstack_help_reg['registered_at'] ) ); ?></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'WP Cron Scheduled (fallback)', 'lukstack-uptime-monitor' ); ?></th>
					<td>
						<?php
						echo $cron_status['is_scheduled']
							? esc_html__( 'Yes', 'lukstack-uptime-monitor' )
							: esc_html__( 'No', 'lukstack-uptime-monitor' );
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Next Scheduled Check (WP Cron)', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( $cron_status['next_run_formatted'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WordPress Cron Enabled', 'lukstack-uptime-monitor' ); ?></th>
					<td>
						<?php
						if ( $cron_status['cron_enabled'] ) {
							esc_html_e( 'Yes', 'lukstack-uptime-monitor' );
						} else {
							esc_html_e( 'No (DISABLE_WP_CRON is set)', 'lukstack-uptime-monitor' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Timezone', 'lukstack-uptime-monitor' ); ?></th>
					<td><?php echo esc_html( wp_timezone_string() ); ?></td>
				</tr>
			</table>
		</div>

	</div>
</div>
