=== LukStack Uptime Monitor ===
Contributors: lukmeyer
Tags: monitoring, uptime, ssl, alerts, webhook
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor multiple websites for uptime, performance, and SSL certificate expiration. Built-in remote cron for reliable checks — no third-party services needed.

== Description ==

LukStack Uptime Monitor is a lightweight yet powerful website monitoring solution built for agencies, freelancers, and web professionals who manage multiple websites.

Track uptime, response times, and SSL certificate expiration for all your client sites from a single WordPress dashboard. When something goes wrong, you will know immediately through email notifications or webhook integrations with Slack, Discord, and other services.

= Reliable Monitoring Out of the Box =

Unlike plugins that rely on WordPress Cron (which only runs when someone visits your site), LukStack Uptime Monitor includes a **built-in remote cron server** operated by the plugin author and hosted on AWS in the EU (Frankfurt) region. Your site is automatically registered on plugin activation and the server triggers your monitoring checks every minute — reliably, even on low-traffic sites. Aggregate environment data (WordPress/PHP versions, locale, site count) is transmitted to keep the service compatible; no admin emails, user accounts, or visitor data are sent. See the Privacy section for the full field list. No third-party cron services like cron-job.org or UptimeRobot required.

= Free Plan Features =

* **Uptime Monitoring** - Automatic checks every 15 minutes
* **Monitor up to 2 websites**
* **Webhook Support** - Native integration with Slack, Discord, Microsoft Teams, and generic webhooks
* **Manual Checks** - Test any site instantly with one click
* **Dashboard Widget** - Quick status overview right on your WordPress dashboard

= Pro Plan Features =

* **Everything in Free**
* **Monitor up to 100 websites**
* **5 minute check interval** for faster downtime detection
* **SSL Certificate Monitoring** - Get warned before certificates expire
* **Website Performance Tracking** - Monitor response times with millisecond precision

= Who Is This For? =

* **Web Agencies** managing client websites
* **Freelancers** maintaining multiple projects
* **Site Owners** who want peace of mind
* **DevOps Teams** needing a simple monitoring solution

= How It Works =

1. Add a website URL to monitor
2. LukStack Uptime Monitor checks the site automatically
3. If the site goes down or returns an error, you get notified
4. When the site recovers, you get a recovery notification

= Webhook Integrations =

LukStack Uptime Monitor automatically formats notifications for popular services:

* **Slack** - Rich message attachments with color-coded severity
* **Discord** - Embedded messages with status information
* **Microsoft Teams** - Via generic webhook connector
* **Zapier / Make** - JSON payload for custom automations

= Privacy =

LukStack Uptime Monitor stores the URLs you choose to monitor and their status data. No personal information (admin emails, user accounts, site content) is collected by the plugin itself.

The plugin does transmit data to the following external services:

* **LukStack Cron Server** (operated by the plugin author, hosted on AWS EU Frankfurt) — receives your site URL, a locally generated shared secret, and aggregate environment data (WordPress/PHP versions, locale, site count, multisite flag, Freemius opt-in status) on plugin activation and on each authenticated trigger response, then triggers monitoring checks on your site every minute. Your Freemius user ID is included **only** if you have actively opted in via the Freemius dialog. See the External services section below for the full field list.
* **Freemius** (license management and opt-in usage tracking) — anonymized data only when you explicitly opt in. See https://freemius.com/privacy/
* **Webhook URLs** (Slack, Discord, etc.) — only when you configure them and only when a monitored site changes status.

All external transmissions are described in detail in the External services section below.

== External services ==

This plugin connects to external services as part of its core monitoring functionality. Below is a description of each service, what data is sent, and when.

= Monitored Websites =

LukStack Uptime Monitor sends HTTP requests to the website URLs you add for monitoring. This is the core functionality of the plugin and is required to check uptime and response times. An SSL connection on port 443 is also made to check the SSL certificate expiration date for HTTPS sites (Pro). These requests are sent automatically by the built-in cron server (see below) and when you manually click "Check now". The data sent is a standard HTTP GET request with a custom user agent header. No personal data is transmitted.

= LukStack Cron Server =

This plugin connects to the LukStack cron server to ensure reliable monitoring checks even when your WordPress site has no visitors. Because no dedicated privacy policy URL is published for this service, the full data-handling details are documented below.

**Service operator**

Operated by Luk Meyer (plugin author). Contact: via the WordPress.org support forum for this plugin (https://wordpress.org/support/plugin/lukstack-uptime-monitor/).

**Hosting and location**

Hosted on Amazon Web Services (AWS), region eu-central-1 (Frankfurt, Germany). Data does not leave the EU. The service uses AWS Lambda, AWS DynamoDB, and AWS EventBridge Scheduler. AWS acts as a data processor under a standard data processing agreement.

Server endpoint: https://gt5zheubf8.execute-api.eu-central-1.amazonaws.com

**What data is transmitted FROM your site TO the cron server**

On plugin activation and when an admin visits the WordPress admin (throttled with exponential backoff):

* Your site URL (value of WordPress home_url())
* A 48-character shared secret, randomly generated on your site, stored in your own wp_options table. This secret is used only to authenticate incoming trigger requests. It is not a WordPress password or account credential.
* The plugin version (e.g. "2.4.0")
* Aggregate environment fields used to understand which WordPress/PHP combinations are in use: WordPress version, PHP version, site locale (e.g. "en_US"), multisite flag, and the number of websites you have added to monitoring.
* A coarse Freemius opt-in status label: one of "paying", "trial", "pending", "registered", "anonymous", or "unknown". This lets the operator distinguish anonymous from opted-in installs in aggregate metrics.
* Your Freemius user ID (numeric) — **only** if you have actively opted in via the Freemius dialog. Anonymous installs never transmit this field.
* The same payload is included in the response of each authenticated trigger request, so the operator sees up-to-date environment data over time.

No admin email, no user account email, no site content, no visitor data, and no credentials are transmitted. Sites running on `localhost`, `127.0.0.1`, or reserved TLDs (`.local`, `.test`, `.localhost`, `.example`, `.invalid`) are excluded from registration entirely.

**What data is transmitted FROM the cron server TO your site**

Every minute, the cron server sends an HTTP POST request to the REST endpoint /wp-json/lukstack/v1/trigger on your site, authenticated via the X-LukStack-Secret header. The request body is empty. The sole purpose of this request is to trigger the plugin's internal monitoring logic.

**What data is stored on the cron server**

* Site URL
* Shared secret (needed to authenticate triggers — transmitted over HTTPS only)
* A SHA-256 hash of the site URL used as record key
* Plan type (Free or Pro) — used to gate trigger frequency server-side
* Plugin version, WordPress version, PHP version, site locale (e.g. "en_US"), multisite flag, and the number of monitored sites — used to understand environment compatibility and prioritize fixes
* Freemius opt-in status label ("paying", "trial", "pending", "registered", "anonymous", "unknown") — used to distinguish opted-in installs from anonymous in aggregate metrics
* Your Freemius user ID — **only** if you have actively opted in via the Freemius dialog
* Timestamps: registration time, last successful trigger, last failed trigger, consecutive failure counter
* Last error message if any

No logs of monitored sites, no uptime data, no visitor data, no admin emails, no user account emails, no site content.

**Data retention**

Data is kept only as long as the plugin is installed on your site. When the plugin is uninstalled, the plugin best-effort calls a `/v1/deregister` endpoint on the cron server with your site URL and secret, after which the record is removed. If the deregister call fails (e.g. network error or transient backend issue), the site is additionally marked inactive on the cron server after approximately 100 consecutive failed trigger attempts (about 100 minutes) and no further requests are sent. Inactive records may be purged periodically.

**Your rights and how to exercise them**

You can at any time:

* Disable the remote cron by deactivating the plugin — triggers stop, but the record remains on the server until auto-expiry. To remove the record immediately, fully uninstall the plugin: the plugin sends a deregister call on uninstall.
* Request deletion of your site's record by posting in the plugin's WordPress.org support forum with your site URL (or a hash of it). Records are typically deleted within 7 days of a confirmed request.
* Rotate the shared secret by re-registering (the settings page exposes a "Force re-register now" button when WP_DEBUG is enabled in your wp-config.php).

**Security**

* All transmissions are over HTTPS (TLS).
* Incoming triggers to your site are validated using hash_equals() (constant-time comparison) to prevent timing attacks.
* The shared secret is stored with autoload disabled and is not exposed in the admin UI unless WP_DEBUG is enabled.

**Legal basis (GDPR)**

The processing described above is necessary for the performance of the service you enabled by installing this plugin (Art. 6 (1) (b) GDPR). The data processed consists of technical identifiers (URL, secret, timestamps), aggregate environment metadata (versions, locale, site count, multisite flag, opt-in status), and — only when you opt in via Freemius — your Freemius user ID. No special categories of personal data (Art. 9 GDPR) are involved.

= Freemius =

This plugin uses Freemius SDK for license management, usage tracking (opt-in only), and the upgrade flow. When a user opts in, anonymized data such as the site URL, WordPress version, PHP version, and plugin version may be sent to Freemius servers. Users can opt out at any time.

This service is provided by Freemius, Inc.
Terms of Service: https://freemius.com/terms/
Privacy Policy: https://freemius.com/privacy/

= Slack (optional) =

If you configure a Slack webhook URL in the plugin settings, LukStack Uptime Monitor sends POST requests to the Slack Incoming Webhooks API when a monitored site changes status (goes down, recovers, or has SSL issues). The data sent includes the website URL, its status, response time, and a timestamp. No personal data is transmitted.

This service is provided by Slack Technologies, LLC / Salesforce, Inc.
Terms of Service: https://slack.com/terms-of-service
Privacy Policy: https://slack.com/privacy-policy

= Discord (optional) =

If you configure a Discord webhook URL in the plugin settings, LukStack Uptime Monitor sends POST requests to the Discord Webhooks API when a monitored site changes status. The data sent includes the website URL, its status, response time, and a timestamp. No personal data is transmitted.

This service is provided by Discord, Inc.
Terms of Service: https://discord.com/terms
Privacy Policy: https://discord.com/privacy

= Generic Webhooks (optional) =

You may configure any third-party webhook URL (e.g. Microsoft Teams, Zapier, Make, or a custom endpoint). When a monitored site changes status, a POST request with a JSON payload is sent to that URL. The data sent includes the website URL, its status, response time, and a timestamp. No personal data is transmitted. Please refer to the terms of service and privacy policy of the respective service you configure.

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "LukStack Uptime Monitor"
3. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Go to Plugins > Add New > Upload Plugin
3. Select the ZIP file and click "Install Now"
4. Activate the plugin

= After Activation =

1. Go to LukStack in the admin menu
2. Add your first website URL
3. (Optional) Configure webhook notifications in Settings
4. Click "Check now" to verify monitoring is working

== Frequently Asked Questions ==

= How many websites can I monitor? =

The free version supports up to 2 websites. The Pro plan supports up to 100 websites.

= How often are websites checked? =

The free plan checks every 15 minutes. The Pro plan checks every 5 minutes for faster downtime detection.

= Can I monitor SSL certificates? =

SSL certificate monitoring is available in the Pro plan. You will receive warnings when a certificate is within 30 days of expiring, with critical alerts at 7 days.

= Why does my site show as DOWN when it is actually online? =

This can happen if:

* Your site blocks automated requests or specific user agents
* A firewall or security plugin is blocking the monitoring server
* The site requires authentication
* There are geographic restrictions

Try adding your monitoring server IP to any whitelist or firewall rules.

= Will this slow down my WordPress site? =

No. The monitoring checks run in the background, triggered by the remote cron server every minute (with plan-gated intervals). The actual HTTP checks run asynchronously and do not affect your site frontend performance.

= Can I monitor non-WordPress sites? =

Yes. LukStack Uptime Monitor can monitor any publicly accessible website, regardless of the platform.

= How do I set up Slack notifications? =

1. Create an Incoming Webhook in your Slack workspace
2. Copy the webhook URL
3. Paste it into LukStack > Settings > Webhook URL
4. Click "Send Test Notification" to verify

= How do I set up Discord notifications? =

1. Go to your Discord server settings
2. Navigate to Integrations > Webhooks
3. Create a new webhook and copy the URL
4. Paste it into LukStack > Settings > Webhook URL

= Why are automatic checks not running? =

This plugin uses a built-in remote cron server (hosted on AWS, EU Frankfurt) that automatically triggers checks on your site every minute. No setup is required; your site is registered automatically on plugin activation. If checks are not running, verify that:

* Your site is publicly reachable (the cron server cannot trigger sites behind basic auth, IP whitelists, or private networks)
* Your firewall or security plugin is not blocking the user agent <code>LukStack-Cron-Runner</code>
* The plugin is up to date

WordPress Cron remains scheduled as a fallback, so checks will also run when visitors hit your site.

= Can I monitor localhost or internal sites? =

No. For security reasons, localhost (127.0.0.1) and private IP ranges are blocked from monitoring.

= Does this plugin send any data externally? =

The plugin makes outbound requests to:

* The websites you choose to monitor (to check their status)
* The LukStack cron server (hosted on AWS EU Frankfurt) — site URL, shared secret, plugin version, and aggregate environment data (WordPress/PHP versions, locale, site count, multisite flag, Freemius opt-in status) on registration, plus the same payload on each authenticated trigger response. Your Freemius user ID is only included if you've opted in. See the Privacy section for the complete field list.
* Your configured webhook URL (to send notifications, if configured)
* Freemius servers for license management (opt-in only)

The plugin also receives incoming requests on its REST endpoint (/wp-json/lukstack/v1/trigger) from the LukStack cron server, authenticated by a shared secret. See the External services section for full details.

== Screenshots ==

1. Main dashboard showing all monitored websites with status, response time, and uptime
2. Adding a new website to monitor
3. Settings page with webhook configuration
4. Dashboard widget with quick status overview
5. Discord notification example
6. Slack notification example
7. Help and documentation page

== Changelog ==

= 2.4.0 =
* IMPROVED: Verified compatibility with WordPress 7.0 — "Tested up to" bumped accordingly
* IMPROVED: Refactored inline status badges in the Settings page Email Notifications block to reusable CSS classes (`.lukstack-chip`), giving better visual consistency with the new Modern Admin Theme introduced in WP 7.0

= 2.3.0 =
* NEW: Plugin now deregisters from the LukStack cron server on uninstall, removing your record from the backend immediately instead of waiting for the inactive-sweep
* NEW: Localhost and reserved-TLD installs (localhost, .test, .local, .localhost, .example, .invalid) no longer register with the cron server — keeps dev installs out of the production backend
* NEW: Settings page now shows an Email Notifications block with a "Connect account" CTA for users who haven't opted in via Freemius
* IMPROVED: Freemius opt-in dialog now explains the concrete value of connecting (email alerts, free 7-day Pro trial) instead of generic telemetry copy
* IMPROVED: Aggregate environment telemetry (WordPress version, PHP version, locale, site count, multisite, Freemius opt-in status) is now sent to the cron server alongside registration so the operator can understand which installs are anonymous vs. opted in. No personal data added. See readme Privacy section for the full field list.
* IMPROVED: `lukstack_skip_cron_registration` filter lets advanced users override the registration skip for non-standard production TLDs

= 2.2.2 =
* FIXED: Remote cron server registration now also triggers on plugin version upgrades (previously only fired on manual activation, leaving many auto-updated sites unregistered)
* FIXED: Pro users with a pre-existing 15-minute setting were stuck on the Free-tier cadence — the plan interval is now the single source of truth
* FIXED: Overly strict registration response validation caused the plugin to treat successful HTTP 2xx responses with a slightly different payload shape as failures, producing infinite retry loops
* FIXED: Failed registration attempts now use exponential backoff (5 min → 10 min → … → 1 h) instead of a fixed 1-hour throttle
* FIXED: remove_all_actions('in_admin_header') could strip header hooks registered by other plugins (Yoast, Elementor, etc.) — the dead code block that called it has been removed
* FIXED: Plugin uninstall now also removes lukstack_cron_server_registration, lukstack_remote_cron_secret, and lukstack_last_cron_run options
* IMPROVED: Registration state now includes the plugin version, so subsequent upgrades refresh the remote registration automatically
* IMPROVED: Info-level log messages are now gated behind WP_DEBUG to reduce production error_log noise

= 2.2.1 =
* FIXED: Misleading "WordPress Cron is disabled" warning on the main plugin page — it now only appears when both the remote cron server is unregistered AND WordPress Cron is disabled, i.e. when monitoring actually cannot run
* FIXED: Broken "Learn how to fix this" link that pointed to a removed help-page anchor — now links to the settings page where the remote cron status and re-register button live
* FIXED: Stale admin notice that suggested setting up an external cron job — now reflects the built-in remote cron as the primary trigger mechanism
* IMPROVED: Main page now shows "Automatic checks run every X minutes via the LukStack remote cron server" when registration is active, and clearly labels the WP Cron time as "fallback" when it is not

= 2.2.0 =
* NEW: Built-in remote cron server (AWS, EU Frankfurt) — reliable monitoring without third-party cron services like cron-job.org or UptimeRobot
* NEW: Sites are automatically registered with the LukStack cron server on plugin activation and admin access
* NEW: Monitoring is triggered externally every minute — works even when no visitors hit your WordPress site
* NEW: Secure REST API endpoint (/wp-json/lukstack/v1/trigger) authenticated with a locally generated 48-character shared secret
* NEW: Help & Info page rewritten to document the new cron architecture
* IMPROVED: Plan interval gating (Free 15 min / Pro 5 min) still applies — surplus triggers become cheap no-ops
* SECURITY: Shared-secret authentication uses hash_equals() for constant-time comparison, preventing timing attacks
* SECURITY: Remote cron secret is stored with autoload=false and never exposed in the admin UI (except with WP_DEBUG enabled)

= 2.1.1 =
* Fixed Freemius SDK configuration for WordPress.org compliance
* Full internationalization (i18n) for all admin pages, settings, and help documentation
* Fixed deprecated current_time() usage in dashboard widget
* Fixed duplicate bulk-check event handler causing double AJAX calls
* Improved JavaScript localization for settings and admin scripts

= 2.1.0 =
* Added Freemius SDK integration for license management and opt-in usage analytics
* Added Pro plan with 5-minute check interval, SSL monitoring, performance tracking, and up to 100 websites
* Free plan now supports up to 2 websites with 15-minute check interval
* Added deactivation feedback dialog to better understand user needs
* Improved uninstall handling via Freemius after_uninstall hook

= 2.0.2 =
* Fixed critical timezone mismatch causing monitoring checks to run every ~70 minutes instead of every 5 minutes
* Fixed XSS vulnerability in webhook test response handling
* Improved SSRF protection with DNS rebinding prevention
* Fixed database format string mismatch in status update function
* Improved SQL security with prepared statements and esc_like() for all queries
* Removed redundant nonce/capability checks in AJAX helper function
* Consolidated statistics queries from 5 separate queries into 1 for better performance

= 2.0.1 =
* Added Dashboard Widget for quick website status overview
* Improved responsive design with 3 breakpoints (1024px, 782px, 600px)
* Improved mobile card-style table layout
* Improved cron locking with atomic database-based approach
* Improved code quality (variable naming, CSS organization)

= 2.0.0 =
* Initial public release
* Uptime monitoring with automatic intervals
* Response time tracking
* SSL certificate expiration monitoring
* Email notifications for status changes
* Webhook support for Slack, Discord, and generic endpoints
* Per-site notification email configuration
* Manual check functionality
* Bulk check all sites
* Uptime percentage tracking
* Comprehensive help documentation
* Full internationalization support

== Upgrade Notice ==

= 2.4.0 =
Compatibility update for WordPress 7.0. Settings page status badges refactored to internal CSS classes for cleaner rendering under the new Modern Admin Theme.

= 2.3.0 =
Adds clean uninstall (deregisters from the LukStack cron server), a Connect-account CTA in Settings, and a sharper opt-in dialog. Also adds aggregate environment telemetry on registration — see Privacy section.

= 2.2.2 =
Reliability release: fixes the registration gap that prevented many auto-updated sites from registering with the remote cron server, plus a bug that kept Pro users on the Free-tier 15-minute cadence. Recommended for all users.

= 2.2.1 =
Fixes a misleading "WordPress Cron is disabled" warning introduced by the 2.2.0 remote cron rollout. Users with DISABLE_WP_CRON set (managed hosts, performance setups) will no longer see a false alarm when the remote cron server is handling their checks. Also fixes a broken "Learn how to fix this" link.

= 2.2.0 =
Major improvement: built-in remote cron server (AWS, EU Frankfurt) eliminates the need for external cron services. Reliable monitoring even for low-traffic sites. Auto-registers on activation — no setup required.

= 2.1.1 =
Fixes Freemius SDK configuration for WordPress.org, adds full i18n support, and resolves a duplicate AJAX call bug.

= 2.1.0 =
Introduces the Pro plan with faster monitoring, SSL checks, and support for up to 100 websites. Free plan continues to work with up to 2 websites and 15-minute intervals. Freemius SDK added for license management.

= 2.0.2 =
Critical fix: monitoring checks now run at the correct interval. Security improvements including XSS and SSRF fixes. Recommended update for all users.

= 2.0.1 =
New Dashboard Widget, improved responsive design, and better cron reliability with atomic locking.

== Additional Information ==

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* PHP extensions: curl, openssl, json

= Support =

For support questions, please use the WordPress.org support forum for this plugin.

= Credits =

Developed by Luk Meyer.
