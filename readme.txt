=== Kitgenix CAPTCHA for Cloudflare Turnstile ===
Contributors: kitgenix
Donate link: https://buymeacoffee.com/kitgenix
Tags: cloudflare, turnstile, captcha, anti-spam, woocommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.1.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Plugin URI: https://wordpress.org/plugins/kitgenix-captcha-for-cloudflare-turnstile/
Author: Kitgenix
Author URI: https://kitgenix.com/
Author Plugin URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile
Documentation URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation
Support URI: https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/
Author Support URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/support
Feature Request URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/feature-request

Add Cloudflare Turnstile CAPTCHA to WordPress, WooCommerce, Elementor, and popular form plugins with privacy-first server-side verification.

== Description ==

Spam is expensive: it wastes time, clogs inboxes, creates fake accounts, and on stores it can lead to abandoned checkout noise and fraudulent activity. Traditional CAPTCHA solutions can also hurt conversions by adding friction.

**Cloudflare Turnstile** is a modern, privacy-first CAPTCHA alternative designed to reduce friction for real people while still blocking bots.

**Kitgenix CAPTCHA for Cloudflare Turnstile** is a production-ready Turnstile integration for WordPress that focuses on reliability in real-world setups:
- Server-side token verification (using Cloudflare’s official endpoint)
- Fast, conditional loading (only where needed)
- Support for dynamic/AJAX forms and modern WooCommerce Blocks / Store API checkout
- Security features: replay protection, proxy-aware IP handling, whitelisting, and developer mode (warn-only)

You can enable/disable each integration (and many per-form toggles), choose auto-injection vs shortcode-only placement, customise display and messaging, and use built-in diagnostics and Site Health checks to troubleshoot.

The plugin also includes a real setup-verification gate for sensitive flows, a Portability tab for JSON export/import, a Support tab with active protection alerts, per-integration analytics, CSV exports, privacy-safe metrics, and recent verification events, and support for defining keys through `wp-config.php` constants or environment variables.

= Supported integrations (where Turnstile can be added) =

All integrations are enable-able from settings. Many also support **Mode: Auto vs Shortcode**.

**WordPress Core**
- Login
- Custom login screens rendered with `wp_login_form()`
- Registration
- Lost password
- Reset password
- Comments (standard WordPress comment forms, including safe handling for comment failures/redirects)

**WooCommerce (Classic)**
- Checkout
- Product reviews
- My Account login
- My Account registration
- Lost password

**WooCommerce Blocks (Store API / Block Checkout)**
- UI rendering inside block-based checkout
- Adds token to Store API requests (header and/or extensions payload when available)
- Server-side validation of Store API checkout requests
- Supports “shortcode-only mode” behaviour so you can control placement

**Easy Digital Downloads (EDD)**
- Checkout
- Login
- Register
- Profile editor

**Form plugins**
- Contact Form 7 (CF7)
- WPForms
- Fluent Forms
- Formidable Forms
- Forminator
- Gravity Forms
- JetFormBuilder
- Jetpack Forms
- Kadence Forms
- Elementor Forms (including popups and AJAX submissions)

**Membership / community / newsletters**
- Ultimate Member (login, registration, password reset)
- MemberPress (signup / checkout)
- Paid Memberships Pro (checkout / registration)
- MailPoet forms
- wpDiscuz comment forms

**Community / forums**
- bbPress (topic/reply flows where applicable)
- BuddyPress (flows where applicable)

= Core features (site-wide) =

**Turnstile widget rendering**
- Uses Cloudflare’s official Turnstile API script
- Widget options:
  - Theme: auto / light / dark
  - Size: normal / compact / flexible
  - Appearance: stored as Turnstile “appearance” option (defaults to always)
  - Language: auto or explicit locale (passed via `hl=...`)

**Settings & admin experience**
- Settings page under the shared Kitgenix WP admin menu
- Live “test widget” preview on the settings screen (renders when a Site Key is present)
- Setup verification gate helps confirm the widget works before auth-sensitive integrations are relied on
- Site Key + Secret Key storage (secret not printed in HTML by default)
- “Reveal secret key” (admins only, nonce-protected AJAX action)

**Messaging & UX**
- Custom error message (admin-configurable, used across integrations)
- Extra message text (optional text displayed alongside/under the widget)
- “Disable submit until completed” option (frontend behaviour via plugin JS)

**Replay protection (enabled by default)**
- Detects re-used tokens (hash stored in transients) and blocks replays
- TTL is filterable
- Stores hashed token markers under the transient prefix `kitgenix_captcha_for_cloudflare_turnstile_ts_`
- Sets a short-lived cookie (`kitgenix_captcha_for_cloudflare_turnstile_ts_replay`, ~120s) when replay is detected (for frontend behaviour/messages)
- Dedicated replay message (filterable)

**Developer mode (warn-only)**
- Verification failures do **not** block submissions
- Failures are logged (and emitted via a developer log action)
- Optional inline warning annotation for admins (frontend config)

**Whitelisting (skip Turnstile + skip loading API script)**
- Whitelist logged-in users
- Whitelist by IP (exact, wildcards, CIDR — including IPv6)
- Whitelist by User-Agent (substring or wildcard matching)
- Filter hook to override whitelist decision

**Proxy / real-IP handling**
- Optional trust of proxy headers (Cloudflare / X-Forwarded-For style)
- Trusted proxy IP list / trust controls
- Forwarded headers are only honoured when the request originates from a trusted proxy

**Performance & resilience**
- Conditional script loading only where needed
- Async/strategy-based script loading (depending on WP version)
- Adds resource hints (preconnect / dns-prefetch) for Turnstile domain
- Detects duplicate Turnstile API loaders (if another plugin/theme enqueues `api.js`):
  - Stores detection in the transient `kitgenix_turnstile_duplicate_scripts`
  - Shows admin notice on settings and Plugins screen
  - Includes dismiss link (nonce-protected, uses `kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe=1`)

**Site Health + diagnostics**
- Adds a Site Health test: “Cloudflare Turnstile readiness”
- Checks:
  - Keys present
  - Duplicate API loader transient (`kitgenix_turnstile_duplicate_scripts`)
  - Last verification success/failure snapshot
  - Heuristic warning if common optimisation/caching plugins are active
- Stores the last verify outcome (success, time, error codes) for Site Health display
- Tracks privacy-safe counters in `kitgenix_captcha_for_cloudflare_turnstile_metrics` (checks total/passed/failed/retries plus per-integration breakdowns)
- Raises automatic admin and Site Health alerts when recent verification failures spike or Cloudflare `siteverify` requests start failing at the HTTP layer
- Shows per-integration analytics in the Support tab so admins can compare passes, failures, retries, and friction by protected flow
- Shows active protection alerts in the Support tab for verification spikes, blocked API requests, and duplicate loader conflicts
- Exports per-integration analytics and the recent diagnostic log as CSV from the Support tab
- Shows a recent diagnostic log in the Support tab so admins can review the last verification events without storing raw IPs or URLs

= Portability and rollout =

- Export settings to JSON and import them into another site from the Portability tab.
- Choose whether exported settings include your Turnstile keys.
- Import settings in a controlled way for repeatable deployments.
- Define `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` and `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY` in `wp-config.php` or your environment when you want keys managed outside the database.

= Manual placement (shortcode) =

If you have a custom form or an unsupported plugin, you can manually render the widget:

[kitgenix_turnstile]

Shortcode output includes:
- a nonce field
- a hidden `cf-turnstile-response` input
- the widget container (with `data-sitekey`)
- support for passing arbitrary attributes via shortcode attributes

Many supported integrations also offer **Shortcode-only** mode (you place the shortcode where you want; the plugin validates server-side without auto-injection).

= Quick Start =

1. Install and activate the plugin.
2. Open the Turnstile settings under the Kitgenix hub in wp-admin.
3. Add your Cloudflare Turnstile Site Key and Secret Key.
4. Configure widget options (theme/size/appearance/language) and messaging if needed.
5. Enable the integrations (and per-form toggles) you want.
6. Save, then test the key user journeys: login, registration, checkout, and your main contact form.

Tip: Start with **Developer mode (warn-only)** on staging or during rollout. Once you’re satisfied, disable warn-only to enforce blocking.

= Performance and caching notes (important for stores) =

Turnstile is lightweight, but aggressive optimisation can break rendering or token freshness.

If you use caching/optimisation plugins:
- Allowlist https://challenges.cloudflare.com
- Avoid full-page caching on login/account/checkout pages
- Avoid combining/inlining the Turnstile loader
- Avoid heavily delaying Elementor/form plugin scripts
- Ensure outbound HTTP requests to Cloudflare are not blocked (needed for server-side verification)

== Installation ==

1. Go to Plugins → Add New.
2. Search for “Kitgenix Turnstile” and click Install Now.
3. Activate the plugin.
4. Open the settings under the Kitgenix hub.
5. Enter your Site Key and Secret Key from Cloudflare Turnstile.
6. Enable your integrations and save.

== Frequently Asked Questions ==

= Do I need a Cloudflare account? =
Yes. You need Turnstile keys from Cloudflare. A free account is enough.

= Is Cloudflare Turnstile a reCAPTCHA alternative? =
Yes. Turnstile is widely used as a privacy-first alternative to Google reCAPTCHA and typically offers a smoother experience for real users.

= Do you verify tokens on the server? =
Yes. Tokens are verified server-side using Cloudflare’s official `siteverify` endpoint (for supported forms/integrations).

= Does this plugin support WooCommerce checkout? =
Yes. It supports WooCommerce Classic checkout, **WooCommerce product reviews**, and **WooCommerce Blocks / Store API checkout**.

= What is “Auto vs Shortcode-only” mode? =
Auto mode injects the widget automatically (and avoids duplicates if it detects existing shortcode/widget markers). Shortcode-only mode requires you to place `[kitgenix_turnstile]` manually.

= What is replay protection? =
Replay protection blocks re-used tokens (a common bot technique). It’s enabled by default and can be tuned via a filter.

= I’m behind Cloudflare / a reverse proxy. Is IP handling correct? =
Yes. The plugin supports proxy-aware IP detection and lets you configure trusted proxies so forwarded headers are only honoured safely.

= Can I whitelist logged-in users or certain IPs/User-Agents? =
Yes. You can whitelist logged-in users, IPs (CIDR/wildcards, including IPv6), and user agents. Developers can also filter whitelist behaviour.

= Can I export or import my settings? =
Yes. The Portability tab lets you export settings as JSON and import them again when you are moving between environments or standardising multiple sites.

= Can I export Turnstile analytics by integration? =
Yes. The Support tab now includes per-integration analytics plus CSV exports for both the integration summary and the recent diagnostic log, so you can review passes, failures, retries, and friction outside wp-admin.

= Can the plugin warn me when Turnstile starts failing? =
Yes. The plugin now raises automatic admin and Site Health alerts when recent verification failures spike, Cloudflare `siteverify` requests are being blocked, or duplicate Turnstile loaders are detected, so you can investigate before forms quietly stop working.

= Can I define keys outside wp-admin? =
Yes. You can define `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` and `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY` in `wp-config.php` or your environment so keys are managed outside the database.

= The widget isn’t showing. What should I check? =
Check your Site Key, confirm the relevant integration and per-form toggle are enabled, clear caches, and review optimisation settings. If scripts are heavily delayed, allowlist Cloudflare’s Turnstile domain.

= Users keep seeing verification errors. Why? =
Common causes include cached form pages (token expiry), aggressive script delay/defer, blocked outbound requests to Cloudflare, duplicate Turnstile loaders, or misconfigured proxy trust settings. Developer mode (warn-only) can help diagnose without blocking users.

== Screenshots ==

1. WordPress login form protected by Cloudflare Turnstile.
2. WordPress registration form protected.
3. WooCommerce Classic checkout protected near the Place order area.
4. WooCommerce Blocks / Store API checkout protected inside the block-based checkout UI.
5. WooCommerce My Account login/register protected.
6. Contact Form 7 form protected.
7. WPForms form protected (AJAX and standard submissions).
8. Elementor form protected (including popup/AJAX behaviour).
9. Settings overview: keys, widget options, integration toggles and security features.
10. Security/advanced settings: replay protection, proxy trust configuration and whitelisting rules.
11. Site Health “Cloudflare Turnstile readiness” test view (keys, last verify snapshot, duplicate loader notice).
12. Portability tab: export/import settings and environment handoff.
13. Support tab: active alerts, per-integration analytics, CSV exports, and recent diagnostic log.

== Settings Overview ==

Main settings:
- Site Key
- Secret Key (with “secret present” state, clear/reveal)
- Theme (auto/light/dark)
- Size (normal/compact/flexible)
- Appearance (Turnstile appearance option)
- Language (auto or specific locale)
- Disable submit until completed
- Custom error message
- Extra message text

Security & advanced:
- Replay protection (on/off)
- Developer mode (warn-only)
- Whitelist logged-in users
- Whitelist IPs (wildcards/CIDR, including IPv6)
- Whitelist user agents
- Proxy trust (enable/disable)
- Trusted proxy IPs / trust controls
- Setup verification before sensitive rollouts

Portability & operations:
- Export settings to JSON
- Import settings from JSON
- Optionally include or exclude site keys during transfer
- Support `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` and `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY` as constants or environment-variable overrides for keys

Integrations (enable + per-form toggles where available):
- WordPress Core (login/register/lost password/reset password/standard comments)
- WooCommerce (checkout/product reviews/login/register/lost password)
- WooCommerce Blocks mode (auto vs shortcode-only)
- Easy Digital Downloads (checkout/login/register/profile)
- Contact Form 7
- WPForms
- Fluent Forms
- Formidable Forms
- Forminator
- Gravity Forms
- Jetpack Forms
- Kadence Forms
- Elementor Forms
- bbPress
- BuddyPress

== Developers ==

Shortcode:
[kitgenix_turnstile]

Server-side verification endpoint:
https://challenges.cloudflare.com/turnstile/v0/siteverify

Filters (script/loading):
- kitgenix_captcha_for_cloudflare_turnstile_script_url( $url, $settings )
- kitgenix_turnstile_freshness_ms
- kitgenix_turnstile_inline_style

Filters (verification / request handling):
- kitgenix_turnstile_siteverify_url
- kitgenix_turnstile_siteverify_timeout
- kitgenix_turnstile_siteverify_sslverify
- kitgenix_turnstile_siteverify_http_args
- kitgenix_turnstile_send_remoteip
- kitgenix_turnstile_remote_ip
- kitgenix_turnstile_token_from_request
- kitgenix_turnstile_handle_comment_form
- kitgenix_turnstile_error_codes
- kitgenix_turnstile_error_message
- kitgenix_turnstile_replay_message
- kitgenix_captcha_for_cloudflare_turnstile_{context}_turnstile_error_message

Filters (replay protection):
- kitgenix_turnstile_replay_ttl

Filters (operational alerts):
- kitgenix_turnstile_alert_window_seconds
- kitgenix_turnstile_alert_failure_spike_min_failures
- kitgenix_turnstile_alert_failure_spike_failure_rate
- kitgenix_turnstile_alert_http_error_min_failures

Filters (whitelist / proxy trust):
- kitgenix_turnstile_is_whitelisted( $is_whitelisted, $details )
- kitgenix_turnstile_trust_headers
- kitgenix_turnstile_trusted_proxies

Internal identifiers (options / transients / cookies / meta):
- Option: kitgenix_captcha_for_cloudflare_turnstile_settings
- Settings group (Settings API): kitgenix_captcha_for_cloudflare_turnstile_settings_group
- Option: kitgenix_captcha_for_cloudflare_turnstile_metrics
- Option: kitgenix_turnstile_recent_event_log
- Option: kitgenix_turnstile_last_verify
- Transient: kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect
- Transient: kitgenix_turnstile_duplicate_scripts
- Transient prefix (replay protection): kitgenix_captcha_for_cloudflare_turnstile_ts_
- Cookie (replay notice): kitgenix_captcha_for_cloudflare_turnstile_ts_replay
- WooCommerce order meta (Blocks/Store API verification): _kitgenix_turnstile_verified

Internal nonces / actions:
- Shortcode/form nonce field name: kitgenix_captcha_for_cloudflare_turnstile_nonce
- Shortcode/form nonce action: kitgenix_captcha_for_cloudflare_turnstile_action
- Settings save nonce field name: kitgenix_captcha_for_cloudflare_turnstile_settings_nonce
- Settings save nonce action: kitgenix_captcha_for_cloudflare_turnstile_settings_save
- Admin AJAX action (reveal saved secret): kitgenix_turnstile_get_secret (WordPress hook: wp_ajax_kitgenix_turnstile_get_secret)
- Admin AJAX nonce action (reveal saved secret): kitgenix_turnstile_reveal_secret
- Admin-post action (analytics exports): kitgenix_turnstile_export_analytics
- Admin-post nonce action (analytics exports): kitgenix_turnstile_export_analytics
- Duplicate-loader notice dismiss query arg: kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe
- Duplicate-loader notice dismiss nonce action: kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss

Actions (developer logging):
- kitgenix_turnstile_dev_log

== External Services ==

This plugin uses **Cloudflare Turnstile** to verify requests and prevent spam and abuse.

The plugin may:
- Load the Turnstile script:
  https://challenges.cloudflare.com/turnstile/v0/api.js
- Submit verification requests server-side to:
  https://challenges.cloudflare.com/turnstile/v0/siteverify

When verification is enabled, the plugin sends to Cloudflare:
- Your Turnstile secret key
- The Turnstile response token
- The visitor IP address (as the optional `remoteip` parameter, when enabled)

The plugin does not send the visitor's browser user agent to Cloudflare as part of the verification payload (the HTTP request itself is made server-side by WordPress).

If proxy trust is enabled, the plugin may read forwarding headers (e.g. `CF-Connecting-IP`, `X-Forwarded-For`) to determine the client IP, but only when requests originate from configured trusted proxies.

The plugin does not add tracking cookies itself and does not sell or share personal data.

Cloudflare Turnstile Terms: https://developers.cloudflare.com/turnstile/
Cloudflare Privacy Policy: https://www.cloudflare.com/privacypolicy/

This plugin also includes a shared “Kitgenix hub” component in wp-admin which may fetch publicly available plugin metadata from WordPress.org using the WordPress core `plugins_api()` function (WordPress.org Plugins API).

* When it runs: only in wp-admin (Kitgenix plugin admin pages)
* Data sent: plugin slug(s) (no personal data)
* Data received: publicly available plugin information (e.g. active installs, ratings)
* Caching: responses are cached locally using transients for ~1 day:
  * `kitgenix_hub_wporg_active_installs_v1`
  * `kitgenix_hub_wporg_ratings_v1`

== Trademark Notice ==

“Cloudflare” and the Cloudflare logo are trademarks of Cloudflare, Inc. This plugin is not affiliated with or endorsed by Cloudflare, Inc.

== Support Development ==

If this plugin helps keep spam away without slowing your site down, you can support ongoing development here:
https://buymeacoffee.com/kitgenix

== Credits ==
Built with ❤︎ by @kitgenix - https://kitgenix.com

== Upgrade Notice ==

= 1.1.3 =
Recommended for all websites.

== Changelog ==
= 1.1.3 (26 May 2026) =
* Compatibility: Confirmed compatibility with WordPress 7.0.
* Fix: WooCommerce My Account registration validation now correctly blocks bot registrations. The previous hook (`woocommerce_register_post` + `wc_add_notice`) only queued a frontend notice but did not prevent account creation; validation is now wired to the `woocommerce_registration_errors` filter so failed Turnstile challenges add a WP_Error that WooCommerce checks before creating the account.
* Fix: WooCommerce reset-password validation now receives the WP_Error object and adds errors directly to it, ensuring a failed Turnstile challenge blocks the password reset instead of only showing a notice.
* Improvement: Diagnostic log now shows a plain-English category and explanatory note for every entry instead of the internal retry-required / first-pass-or-hard-fail classification. False positives (e.g. stale nonce on a cached My Account page) are now clearly labelled as `cached-or-expired-page` with guidance, so they are not mistaken for attacks. A category legend table is displayed alongside the log in the Support tab.
* Fix: Diagnostic log data is now fully cleaned up when the plugin is uninstalled.

= 1.1.2 (26 May 2026) =
* Dev: Skipped to be in line with other Kitgenix Plugins

= 1.1.1 (26 May 2026) =
* Dev: Skipped to be in line with other Kitgenix Plugins

= 1.1.0 (7 May 2026) =
* New: Added MailPoet integration with Turnstile injection and server-side newsletter subscription validation.
* New: Added Ultimate Member integration covering login, registration, and password reset forms.
* New: Added MemberPress integration for signup and checkout flows.
* New: Added Paid Memberships Pro integration for checkout and registration flows.
* New: Added wpDiscuz integration for comment and reply forms.
* New: Added environment-managed and wp-config-managed key overrides for the Turnstile Site Key and Secret Key.
* New: Added a dedicated Portability tab with JSON export/import tools for reusing settings across sites.
* New: Added an end-to-end setup verification gate that validates the current Site Key and Secret Key through a real server-side Cloudflare siteverify check before login-sensitive flows are allowed to load.
* New: Added a privacy-safe recent diagnostic log for admins with copyable timestamps, integration labels, outcomes, and Cloudflare error codes.
* New: Added automatic admin and Site Health alerts for sudden verification failure spikes and blocked Cloudflare `siteverify` requests.
* New: Added per-integration analytics in the Support tab with pass, failure, retry, and friction reporting for each protected flow.
* New: Added CSV exports for the per-integration analytics table and the recent diagnostic log.
* Improvement: Added new admin integration toggles for MailPoet, Ultimate Member, MemberPress, Paid Memberships Pro, and wpDiscuz.
* Improvement: MailPoet forms now receive the Turnstile token through the expected nested request field for reliable validation.
* Improvement: Site Key and Secret Key admin fields now become read-only when a constant or environment variable override is active.
* Improvement: Settings exports can omit sensitive keys by default, while imports support replace or merge workflows for agency rollouts.
* Improvement: The admin test widget now performs server-side setup verification, records the result for the current key pair, and warns admins when auth-sensitive protections are still gated.
* Improvement: The Support tab now surfaces active protection alerts, including duplicate Turnstile loader conflicts, with quick triage actions.
* Improvement: WordPress core login protection now supports `wp_login_form()` custom login screens and skips REST/XML-RPC auth edge cases for better compatibility with hidden-login URLs, theme modals, and 2FA flows.
* Improvement: Recent diagnostic logging deliberately avoids storing raw IP addresses, request URIs, or submitted values.
* Improvement: Privacy-safe metrics now store per-integration retry counts so admins can quantify challenge friction without storing raw visitor identifiers.
* Fix: Turnstile API script loading now avoids adding WordPress `?ver=` query arguments to Cloudflare `api.js`, removing the browser console warning about unknown API parameters.
* Fix: Widget size handling now uses Cloudflare-supported values (`normal`, `compact`, `flexible`) with backward-compatible mapping for legacy saved sizes.
* Fix: WooCommerce Classic checkout now validates Turnstile only once per submission, preventing false “Your verification expired. Please complete the Turnstile challenge.” errors when enforcement is enabled.
* Fix: WooCommerce Classic Checkout with replay protection now allows checkout retries and only marks the token as used after successful order creation, preventing "replay_detected" errors during payment failures or validation retries.
* Fix: Easy Digital Downloads (EDD) checkout with replay protection now allows checkout retries and only marks the token as used after successful purchase, preventing "replay_detected" errors during payment failures or validation retries.
* Fix: Paid Memberships Pro checkout with replay protection now allows checkout retries and only marks the token as used after successful membership creation, preventing "replay_detected" errors during payment failures or validation retries.
* Fix: WooCommerce Blocks checkout with replay protection now allows checkout retries and only marks the token as used after successful order creation via Store API.
* Fix: Standard WordPress comments handling now defers to wpDiscuz-specific validation when wpDiscuz protection is enabled, preventing duplicate handling.
* Fix: All integration files (EDD, Elementor, WP Core, Paid Memberships Pro, Ultimate Member, MemberPress, wpDiscuz, MailPoet, BuddyPress, bbPress, Fluent Forms, WPForms, Contact Form 7, Kadence Forms, JetFormBuilder, Gravity Forms, Formidable Forms, Forminator, Jetpack Forms) now pass widget size through the `normalize_widget_size()` helper, ensuring only Cloudflare-supported values (`normal`, `compact`, `flexible`) are ever rendered in `data-size` attributes.
* Fix: Cleared stale `_lastToken` on Turnstile reset or expiry — the stored token is now always synced with the hidden input value, preventing a stale token being replayed by the WooCommerce Blocks fetch bridge after a checkout error.
* Fix: WooCommerce Classic Checkout now resets the Turnstile widget automatically after a failed submission (`checkout_error` event) and re-initialises it after checkout fragment refreshes (`updated_checkout` event), ensuring users always have a fresh token ready for retry.
* Docs: Updated the readme supported integrations list to include the new membership, newsletter, and community coverage.

= 1.0.18 (19 March 2026) =
* UI: Improved the Kitgenix admin header layout for better alignment and less clutter.
* UI: Social links in admin headers now render as compact icon buttons (with accessible labels).
* UI: Added responsive header helpers so titles/description and actions/links lay out consistently.
* UI: Admin tables inside Kitgenix pages now use Kitgenix styling for a more consistent branded look.
* Fix: Admin notices now display above the Kitgenix header using the WordPress standard notice area.
* Fix: Removed custom notice moving/styling so core WordPress notices keep their default appearance.
* Fix: Added defensive notice normalization to prevent notices being relocated into the header by other scripts.
* Fix: Normalised settings page card spacing so it matches other Kitgenix plugins.
* Fix: Added spacing between adjacent action links/buttons (e.g., Edit/Delete).
* Improvement: Validate Store API POSTs early via a single REST pre-dispatch path; token accepted from X-Turnstile-Token header or canonical extensions payload (WooCommerce Blocks).
* Cleanup: Normalised nonce verification and request handling across admin and validation flows for WordPress.org review compliance.
* Maintenance: Updated the plugin Author URI to the public Kitgenix WordPress.org profile and replaced the old custom admin-menu icon CSS with the native Dashicons icon.
* New: Added a dedicated WooCommerce Product Reviews integration toggle so store reviews can be protected independently of standard WordPress comments.
* Fix: Split standard WordPress comments from WooCommerce product reviews so enabling blog comments protection no longer captures product review submissions unless the WooCommerce reviews toggle is enabled.
* Improvement: WooCommerce product reviews now follow the WooCommerce Classic injection mode and error-message context while still using the shared comment form hooks internally.
* Docs: Updated the bundled documentation and package readme to describe the new WooCommerce Product Reviews coverage and the `kitgenix_turnstile_handle_comment_form` routing filter.

= 1.0.17 (18 February 2026) =
* New: Added JetFormBuilder integration (auto-inject and shortcode-only modes).
* New: JetFormBuilder server-side validation during submission handling (AJAX compatible).
* New: Added JetFormBuilder toggle + injection mode to the settings page.
* Improvement: JetFormBuilder auto-inject places the widget near the submit button row and avoids multi-step next/prev actions.
* Fix: Support tab “Your site impact” metrics now update as Turnstile checks run (total/passed/failed).
* UI: Added Stock Sync for WooCommerce to the Kitgenix hub cards.
* Docs: Overhauled readme.txt.
* Docs: Updated WordPress.org screenshots.
* Docs: JetFormBuilder includes its own Turnstile/CAPTCHA option; use one Turnstile provider per form to avoid duplicates.
* Dev: Regenerated /languages/kitgenix-captcha-for-cloudflare-turnstile.pot translation template.

= 1.0.16 (27 January 2026) =
* Improvement: Small admin UI tweaks and performance refinements.
* Change: Declared PHP requirement as 8.1.
* Cleanup: Minor compatibility and stability fixes, plus i18n/translation updates.
* Cleanup: PHPCS/i18n/security fixes across admin and core files (output escaping, translator comments, optional nonce checks).
* Fix: Hardened admin asset enqueues to prefer $_GET['page'] with a fallback to hook-suffix so assets load reliably on existing installs.
* Fix: Localized admin JS now exposes AJAX action and nonce for the reveal-secret flow to securely fetch stored secret keys.

= 1.0.15 (01 January 2026) =
* New: Added Easy Digital Downloads integration (checkout, login, registration, and profile editor) with per-form toggles and a dedicated mode setting (Auto vs Shortcode-only).
* New: Added a shared Kitgenix top-level wp-admin menu + hub page, and moved Turnstile settings to Kitgenix → Cloudflare Turnstile (activation redirect + “Settings” link updated accordingly).
* Security: Secret key is no longer printed into the settings page HTML by default; “Reveal secret key” now fetches it on-demand via authenticated AJAX + nonce.
* Improvement: bbPress integration now avoids duplicate widget output on themes that fire multiple hooks, adds support for the forum form, and validates forum creation flows.
* Improvement: Fluent Forms rendering is now more resilient when the Turnstile API loads late (prevents “stuck rendering” states and allows clean retries).
* Improvement: Standardized internal widget owner attribute + dynamic-render event naming, reducing render misses in dynamic/AJAX contexts.
* Improvement: WordPress comments widget placement is now consistently injected above the submit button across themes; comment widget now has a stable ID for easier targeting.
* Fix: Replay protection setting now persists correctly when you disable it (checkbox omission on save no longer forces it back on).
* UI: Updated Kitgenix branding (admin + public CSS tokens), added shared hub stylesheet, refreshed plugin banners, and added Kitgenix logo assets.
* Cleanup: Removed onboarding strings and updated translations; plugin headers/requirements updated (Tested up to 6.9, requires PHP 8.0).

= 1.0.14 (09 December 2025) =
* UI: Split WooCommerce settings into two blocks — “WooCommerce Classic” and “WooCommerce Blocks (Store API)” — with separate injection mode controls and clearer guidance.
* UI: Modernized settings page with sidebar navigation (icons), status overview card, accessible collapsible sections, and improved layout. Kept the floating “Unsaved changes” bar.
* UI: Added a copy button next to [kitgenix_turnstile] in the settings for easy manual placement.
* UI: Updated brand colors across admin and public CSS to main #4f2a9a and accent #f364dd.
* Improvement: Public JS detects data-kitgenix-captcha-for-cloudflare-turnstile-owner="woocommerce-blocks" and performs an immediate render, then falls back to visibility guard for other owners.
* Fix: WooCommerce Blocks checkout widget now renders reliably even when Classic Checkout is disabled. The renderer no longer waits for the container to be visible before calling turnstile.render() for Blocks, preventing missed render windows.
* Change: Respect Shortcode-only — when Blocks is set to “Shortcode only”, auto-rendering is suppressed and server-side validation only enforces when a token is present (i.e. when you place the shortcode). Without a shortcode/token, checkout proceeds without Turnstile.
* Change: Clarification — unchecking “Checkout Form (Classic)” does not affect Blocks Checkout; disable Blocks auto-injection via its “Shortcode only” mode if desired.
* Cleanup: Removed Export/Import Settings feature — UI removed and handlers disabled (class-settings-transfer.php no longer registers actions). Any old direct Import/Export URLs are no-ops.
* Cleanup: Removed the Simple/Advanced mode toggle from the settings UI and scripts.
* Dev: Dropped the unused kitgenix_turnstile_validate_keys AJAX nonce localization from admin scripts.
* Preparation: Placement — ensures the widget is injected directly above the “Place order” area in WooCommerce Blocks checkout (handles submit button, text node, and actions wrapper variants).
* Preparation: Stability — keeps existing behaviour for Classic, core, and form plugins; no changes to validation flows or token forwarding (header + Store API extensions).

= 1.0.13 (22 November 2025) =
* Security: Critical validation bypass in Elementor Pro Forms and Forminator Forms where missing tokens were incorrectly allowing form submissions instead of blocking them.
* Security: Audit confirmed all other integrations (Contact Form 7, Gravity Forms, Formidable Forms, WPForms, Fluent Forms, Jetpack Forms, Kadence Forms, WooCommerce, WordPress core, bbPress, BuddyPress) correctly validate and fail when tokens are missing.
* Security: This update fixes a vulnerability where forms could be submitted without completing CAPTCHA verification. Update immediately.
* Fix: Elementor Pro Forms now properly fail validation when the Turnstile token is missing or empty (previously skipped validation entirely).
* Fix: Forminator Forms now properly fail validation when the Turnstile token is missing or empty (previously skipped validation entirely).
* Fix: Removed the wp_kses_post() wrapper from Forminator submit button HTML that could strip required attributes.

= 1.0.12.1 (22 November 2025) =
* Fix: Reverted to 1.0.11 until the security update was released.

= 1.0.12 (21 November 2025) =
* New: Global shortcode [kitgenix_turnstile] to render the Turnstile widget manually inside custom HTML fields, form content, or page templates.
* Improvement: Auto-inject vs Shortcode behavior is now mutually exclusive and consistent across integrations.
* Improvement: Ensured Shortcode-only mode works across all supported form plugins via defensive do_shortcode() passthroughs and field-level filters, while Auto mode detection ignores literal shortcode tokens.
* UI: Only show the global Shortcode guidance card when at least one supported forms integration is present. Removed Auto/Shortcode radio controls from the WordPress Core card; core forms use the Enable checkbox and per-form toggles only.
* Dev: Reworked temporary shortcode removal logic to guarantee re-registration after do_shortcode(). Fixed edge-case uninitialised variable and parse issues.
* Dev: Standardised detection and injection semantics and added comments and guards for missing site keys, filters, and plugin version differences.
* Fix: CF7 shortcode rendering in Shortcode-only mode — Contact Form 7 form HTML is now passed through do_shortcode() when the integration is set to Shortcode-only.
* Change: Added includes/core/class-turnstile-shortcode.php with a robust shortcode renderer and recursive detection helper has_shortcode_in() that detects literal shortcodes and rendered widget markers (class="cf-turnstile", data-kitgenix-shortcode, or hidden name="cf-turnstile-response").
* Change: Integration adapters now use the new helper and treat literal shortcode text separately from rendered markup so Auto mode is not blocked by leftover shortcode tokens.
* Change: When an integration needs to run do_shortcode() in Auto mode, it temporarily removes the plugin shortcode, runs do_shortcode(), then immediately re-registers the shortcode so it is never left unregistered.
* Docs: Note — the stored mode_wp_core setting is retained for compatibility but no longer exposed in the UI. It can be removed in a future release if needed.

= 1.0.11 (19 October 2025) =
* Fix: Elementor AJAX regression — prevented a brief layout “bump” where Interaction Only lost .kitgenix-ts-collapsed during the * AJAX send; the container now stays collapsed unless a visible challenge is explicitly required.

= 1.0.10 (16 October 2025) =
* Improvement: Event-driven rendering — added kitgenix:turnstile-containers-added event from injectors; public script listens and re-initializes rendering automatically for dynamically added containers.
* Improvement: Stability and UX — defensive re-render guards, explicit data-rendered attribute for CSS control, and safer visibility checks to avoid rendering inside hidden containers.
* Fix: Elementor Popups — reliably initializes the Turnstile challenge when a popup opens (even if the widget was inserted while hidden). Clears stale render flags, resets hidden iframes, and triggers a fresh render on show.
* Fix: Hidden input — always ensures input[name="cf-turnstile-response"] exists for Elementor forms (including popups) so the token is properly captured and validated.
* Fix: Interaction Only empty gaps — placeholders are now fully collapsed until the widget actually renders (via data-rendered). After successful AJAX submits, the container is collapsed/hidden to prevent any blank space.
* Fix: Multiple forms on a page — consistent collapsed behavior across instances; prevents duplicate containers in Elementor popups and re-renders only when needed.

= 1.0.9 (15 October 2025) =
* Improvement: Proactive reveal for Interaction Only — if auto-verification doesn’t complete after a short period (~5s), the widget is surfaced and the challenge is triggered so users aren’t left waiting.
* Improvement: Streamlined inline messaging to align with Cloudflare’s own phrasing; reduced redundant prompts to let Cloudflare’s UI lead the experience.
* Improvement: Submit-time guards — for regular forms and Elementor AJAX; when no token is present, we halt that submission, reveal the widget, scroll it into view, and start a fresh challenge.
* Dev: Standardized render locks and defensive pre-render cleanup across remaining integrations to prevent duplicate iframes and race conditions.
* Fix: “Disable Submit Button” now respects “Interaction Only” — submit stays enabled when Turnstile can verify invisibly, and is disabled only if a visible challenge is actually required (unsupported/timeout/error). Applies to Elementor, WordPress core forms, WooCommerce, Gravity Forms, Formidable, Forminator, Jetpack, Fluent Forms, and Kadence.

= 1.0.8 (15 October 2025) =
* Improvement: Deferred render — widgets now render when their container is visible (Elementor + generic paths), reducing layout thrash and improving perceived load times across dynamic UIs.
* Dev: Simplified collapse logic by removing the previous mutation-based watcher and relying on Turnstile callbacks + visibility checks.
* Fix: Elementor popup — reliably renders Turnstile when popups open after page load (e.g., delayed by timer); if a widget initialized while hidden, it is reset and re-rendered on open.
* Fix: Elementor popup duplicates — de-duplicated popup/form event listeners and centralized rendering to avoid multiple widget instances; idempotent guards ensure one render per container.
* Fix: Interaction Only placeholder stays collapsed (no gap/shadow) after invisible validation; it only expands when UI is truly required (via unsupported/timeout/error callbacks or actual visible challenge).
* Fix: Prevent duplicate renders on Gravity Forms, Formidable, Forminator, and Jetpack by adding per-element render locks and pre-render cleanup.
* Fix: Prevent loader overlay — no spinner is injected for Interaction Only while the API loads; collapsed state fully hides any inner spinner and spinners never intercept clicks.

= 1.0.7 (14 October 2025) =
* New: Added “Flexible (100% width)” widget size (Cloudflare Turnstile data-size="flexible") for fully responsive, container-width layouts.
* New: Interaction Only UX refinement — collapses the initial blank gap (no more 50+px empty space) until the user interacts or the widget needs to expand.
* Improvement: Consistent collapsed/expand logic across Elementor, Gravity Forms, Formidable, Forminator, Jetpack, Fluent Forms, Kadence, WPForms, and core render paths.
* Improvement: CSS enhancements for flexible width + reduced gap state (.kitgenix-ts-collapsed).
* Improvement: Unified size handling in JS (flexible passes straight through; existing custom sizes still map to Cloudflare equivalents).
* Preparation: Foundation laid for upcoming modal/delayed form robustness (MutationObserver structure ready for attribute watching & visibility checks in a future release).
* Dev: Sanitization now allows flexible; admin settings UI updated with help text.

= 1.0.6 (10 September 2025) =
* Improvement: Updated plugin assets (banners, icons, screenshots with clearer cropping/labels).
* Improvement: Updated readme.txt — full integrations list, screenshot captions, Support Development section, improved tags/short description, and clarified WooCommerce Blocks/Store API notes.

= 1.0.5 (10 September 2025) =
* Improvement: More reliable widget injection and cleanup on AJAX/dynamic DOM events; tighter re-render/reset behavior.
* Security: Replay protection enabled by default (TTL filterable via kitgenix_turnstile_replay_ttl).
* Fix: Admin: detect duplicate Turnstile API loader and show a dismissible notice on Settings and Plugins screens.
* Fix: Contact Form 7 injects once and resets cleanly on CF7 validation/error events.
* Fix: Exposed window.KitgenixCaptchaForCloudflareTurnstile so Cloudflare onload can reliably call renderWidgets() (prevents “no widget → no token”).
* Fix: Guard Elementor script enqueue to avoid PHP warnings in REST/AJAX or early hooks.
* Fix: Guarded “render once” logic to prevent duplicate widget rendering across core, WooCommerce, and form plugins.
* Fix: Prevent Turnstile overlapping submit buttons for Gravity Forms and WPForms; adjusted spacing and placement heuristics.
* Fix: Sanitization & import/export hardening — preserve CIDR & wildcard IP patterns.
* Fix: “Disable Submit Until Verified” now disables buttons on render and re-enables only after a valid token callback.
* Fix: Token handling — canonical token channel, auto-create hidden cf-turnstile-response input, getLastToken() helper, and kitgenixcaptchaforcloudflareturnstile:token-updated event.
* Fix: WooCommerce login/checkout placement (Classic & Blocks / Store API), including correct “Place order” positioning.

= 1.0.4 (17 August 2025) =
* Fix: Added spacing so Turnstile no longer overlaps the WPForms submit button.
* Fix: Positioned Turnstile above the WooCommerce reviews submit button.
* Fix: Prevented Turnstile from rendering inline with the submit button on Gravity Forms.

= 1.0.3 (12 August 2025) =
* Fix: Fixed the “Save Settings” button not working after a few attempts.

= 1.0.2 (12 August 2025) =
* New: Added advanced fields: respect_proxy_headers and trusted_proxy_ips (legacy), plus trust_proxy and trusted_proxies (current).
* New: Developer Mode (warn-only) — Turnstile failures are logged and annotated inline for admins but do not block submissions (useful for staging/troubleshooting).
* New: Replay protection — caches recent Turnstile tokens (hashed) for ~10 minutes and rejects re-use. Enabled by default; duration filterable via kitgenix_turnstile_replay_ttl.
* Improvement: Added canonical token channel (getLastToken() helper and kitgenixcaptchaforcloudflareturnstile:token-updated event dispatched on each token change). Hidden cf-turnstile-response input is auto-created in forms that don’t already have it.
* Improvement: Added preconnect/dns-prefetch resource hints for https://challenges.cloudflare.com to speed up first paint.
* Improvement: Added Site Health test (“Cloudflare Turnstile readiness”) reporting keys presence, duplicate loader detection, last verification snapshot, and possible JS delay/defer from optimization plugins (with guidance).
* Improvement: Admin CSS fully scoped to the settings wrapper, compact modern fields, focus-visible styles, and reduced-motion fallback.
* Improvement: Checkout protected via woocommerce_checkout_process and woocommerce_after_checkout_validation (WooCommerce Classic).
* Improvement: Consistent widget + validation across checkout/login/register/lost password (WooCommerce Classic).
* Improvement: Ensure hidden input + container are present; don’t inject a container if no site key is available (Elementor).
* Improvement: Export / Import JSON for settings (merge/replace). Optional inclusion of Secret Key (explicitly allowed).
* Improvement: Guardrails and housekeeping — centralized render flow, lightweight MutationObserver to catch dynamically added forms, and safer class/existence guards.
* Improvement: Include token in Elementor Pro AJAX payloads; re-render in popups and dynamic forms; reset widget on submit/errors.
* Improvement: Improved Disable Submit Button behavior — submit buttons are disabled immediately on render and re-enabled only after a valid token callback (previously disabled only on error/expired).
* Improvement: Inject container next to the “Place order” area via render_block_woocommerce/checkout-actions-block (WooCommerce Blocks).
* Improvement: Late alignment helpers for consistent widget placement on login/admin.
* Improvement: Preserve CIDR and wildcard IP patterns instead of stripping them; sanitize lines while keeping valid patterns.
* Improvement: Public CSS greatly reduced in scope (fewer global !importants), small min-height to prevent CLS, better RTL + reduced-motion support, and per-integration spacing.
* Improvement: Reliable widget injection before submit, spinner cleanup, and re-render on each plugin’s AJAX/DOM events.
* Improvement: Server-side validation hook support (elementor_pro/forms/validation).
* Improvement: Server-side validation mapped to each plugin’s native API.
* Improvement: “Test widget” is rendered only via a tight inline onload callback (prevents double-render / undefined globals).
* Improvement: Token freshness & UX — idle timer and token-age timer auto-reset widgets after ~150s (filterable via kitgenix_turnstile_freshness_ms), plus a gentle inline “Expired / Verification error — please verify again.” message beside the widget.
* Improvement: Validate Store API POSTs early via REST auth filter; token accepted from X-Turnstile-Token header or extensions (WooCommerce Blocks).
* Improvement: Widget injection and validation improvements across WooCommerce Blocks and Classic flows.
* Security: Added Cloudflare/Proxy-aware client IP handling with Trust Cloudflare/Proxy headers + Trusted Proxy IPs/CIDRs settings. Only honors CF-Connecting-IP / X-Forwarded-For when the request comes from a trusted proxy; otherwise falls back to REMOTE_ADDR.
* Security: Validator accepts token from POST, X-Turnstile-Token header, or custom filter; memoized siteverify; robust HTTP args; remote IP + URL + timeouts filterable; friendly error mapping; last verify snapshot stored for diagnostics.
* Security: Whitelist supports logged-in bypass, IPs with exact/wildcard/CIDR (IPv4/IPv6), and UA wildcards; decision cached per request and filterable via kitgenix_turnstile_is_whitelisted.
* Fix: Added widget render on resetpass_form and proper validation via validate_password_reset; lost password now validates via lostpassword_post.
* Fix: Contact Form 7 integrates cleanly (single injection, resets on CF7 error events).
* Fix: Duplicate Turnstile API loader detection with a dismissible admin notice (surfaces on the Settings page and Plugins screen).
* Fix: Exposed the public module globally as window.KitgenixCaptchaForCloudflareTurnstile so the Cloudflare API onload callback can call renderWidgets() (prevents “no widget → no token” failures).
* Fix: Guarded “render once” logic so widgets don’t duplicate across hooks (core + WooCommerce + form plugins).
* Fix: Reintroduced inline centering on wp-login.php / wp-admin to stabilize layout across all auth screens.
* Fix: Run Turnstile validation only on POST submissions for core forms (login, register, lost password, reset password, comments). Prevents the “Please complete the Turnstile challenge” message on refresh or wrong password.
* Fix: WooCommerce login handles both modern woocommerce_process_login_errors and legacy woocommerce_login_errors.

= 1.0.1 (11 August 2025) =
* Change: Overhauled includes/core/class-script-handler.php to use the modern Script API (async strategy on WP 6.3+, attribute helpers on 5.7–6.2) and eliminated raw <script> output.
* Docs: Expanded readme and updated links.
* Dev: Added filter kitgenix_captcha_for_cloudflare_turnstile_script_url for advanced control.
* Dev: Public/admin assets now use filemtime() for cache-busting.
* Fix: Centered Cloudflare Turnstile on all wp-login.php variants (login, lost password, reset, register) and across wp-admin.

= 1.0.0 (11 August 2025) =
* New: Initial Release
* New: Admin Notices and Settings Errors
* New: Admin UI (Modern)
* New: AJAX and Dynamic Form Rendering Support
* New: Caching, AJAX, and Dynamic Forms Optimizations
* New: Conditional Script Loading for Performance
* New: Contact Form 7 Integration
* New: CSRF Protection (Nonce Fields)
* New: Custom Error and Fallback Messages
* New: Elementor Forms Integration
* New: Error Handling and User Feedback
* New: Fluent Forms Integration
* New: Formidable Forms Integration
* New: Forminator Forms Integration
* New: GDPR-friendly (No Cookies or Tracking)
* New: Gravity Forms Integration
* New: IP / User Agent / Logged-in User Whitelisting
* New: Jetpack Forms Integration
* New: Kadence Forms Integration
* New: Language Selection for Widget
* New: Multisite Support
* New: Optional Plugin Badge
* New: Per-Form and Per-Integration Enable/Disable
* New: Plugin Translations/Localization
* New: Server-Side Validation for All Supported Forms
* New: Site Key & Secret Key Management
* New: Widget Appearance Customization
* New: Widget Options (Size, Theme, Appearance)
* New: WooCommerce Checkout Integration
* New: WooCommerce Login Integration
* New: WooCommerce Lost Password Integration
* New: WooCommerce Registration Integration
* New: Works With Elementor Element Cache
* New: WPForms Integration
* New: WordPress Comment Integration
* New: WordPress Login Integration
* New: WordPress Lost Password Integration
* New: WordPress Registration Integration
* New: “Defer Scripts” and “Disable Submit” Logic
* New: No Impact on Core Web Vitals