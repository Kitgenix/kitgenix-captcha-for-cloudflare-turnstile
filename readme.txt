=== Kitgenix CAPTCHA for Cloudflare Turnstile ===
Contributors: kitgenix
Donate link: https://www.paypal.com/donate/?hosted_button_id=KALF36K6JJ9B2
Tags: captcha, cloudflare turnstile, spam protection, anti-spam, woocommerce, contact form 7, gravity forms, wpforms, elementor, login security
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
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

Cloudflare Turnstile CAPTCHA and spam protection for WordPress, WooCommerce, and popular form plugins, with privacy-first server-side verification.

== Description ==

**Kitgenix CAPTCHA for Cloudflare Turnstile** adds Cloudflare Turnstile – a privacy-focused CAPTCHA alternative – to your WordPress login, registration, comment, checkout, and form pages. Every submission is verified server-side against Cloudflare's official `siteverify` endpoint before it's allowed through, so spam bots are blocked without forcing real visitors to solve image puzzles.

This plugin is built for site owners and store admins who are tired of spam registrations, fake comments, junk form submissions, or bot-driven checkout abuse, and want a CAPTCHA that stays out of the way for legitimate users.

You'll need a free Cloudflare account and a Turnstile Site Key and Secret Key to use this plugin – see "External Services" below for exactly what data is sent to Cloudflare and when.

= Key features =

* **Server-side verification** – every token is checked against Cloudflare's `siteverify` endpoint before a form is allowed to proceed; nothing is trusted client-side alone.
* **Conditional script loading** – the Turnstile API script only loads on pages where a widget will actually render, keeping unrelated pages free of the extra request.
* **Auto-inject or shortcode-only** – most integrations can either add the widget automatically or leave placement to you via the `[kitgenix_turnstile]` shortcode.
* **Per-integration widget overrides** – give one integration (e.g. a dark checkout page) its own Theme, Widget Size, or Language without changing the site-wide Display Settings.
* **Replay protection** – rejects a Turnstile token that's already been used, on by default, with a filterable time window.
* **Honeypot fallback field** – an optional hidden trap field that rejects bots which submit forms without loading JavaScript, checked before Cloudflare is even contacted.
* **Developer/warn-only mode** – test a rollout site-wide or per integration without blocking real submissions while you verify everything renders correctly.
* **Whitelisting** – skip verification for logged-in users, specific IPs (wildcards and CIDR, including IPv6), or User-Agent strings.
* **Proxy-aware IP detection** – resolves the real visitor IP behind Cloudflare or another reverse proxy, but only trusts forwarded headers from proxies you've explicitly configured.
* **Setup verification gate** – login-sensitive protections (login, registration, password reset, membership sign-up) stay inactive until your current key pair passes a real end-to-end verification test, so a typo in your Secret Key can't silently lock out real visitors.
* **Diagnostics and Site Health** – a dedicated Site Health test, a recent verification log, per-integration analytics, and automatic alerts when failures spike or Cloudflare requests start failing.
* **Settings portability** – export settings to JSON (with or without your keys) and import them on another site for repeatable deployments.

= Where it protects your site =

Each integration is off by default and only loads its code when you enable it and the related plugin is active.

**WordPress Core**

* Login (including custom login forms rendered with `wp_login_form()`)
* Registration
* Lost password and reset password
* Comments (standard WordPress comment forms)

**WooCommerce**

* Classic Checkout, near the Place Order button
* Product reviews
* My Account login, registration, and lost/reset password
* WooCommerce Blocks (Store API) checkout – the widget renders inside the block-based checkout UI and the token is validated server-side against the Store API request

**Easy Digital Downloads**

* Checkout, login, registration, and the profile editor

**Form plugins**

* Contact Form 7, WPForms, Fluent Forms, Formidable Forms, Forminator, Gravity Forms, JetFormBuilder, Jetpack Forms, Kadence Forms (Advanced Form block), Ninja Forms, and Elementor Pro Forms (including popups and AJAX submissions; requires Elementor Pro's Form widget)

**Membership, community, and newsletters**

* Ultimate Member (login, registration, password reset)
* MemberPress (checkout/signup)
* Paid Memberships Pro (checkout/registration)
* MailPoet subscription forms
* wpDiscuz comment forms
* bbPress (topic and reply forms) and BuddyPress (registration and activity posting)

**Kitgenix Plugin Score**

* Protects the login, registration, and forgot-password pages rendered by the separate Kitgenix Plugin Score plugin, via output buffering – no theme or Plugin Score changes required.

= Manual placement (shortcode) =

For a custom form or an unsupported plugin, add the widget manually with:

`[kitgenix_turnstile]`

The shortcode renders a nonce field, a hidden `cf-turnstile-response` input, and the widget container, and accepts extra HTML attributes if you need them. Many integrations also support a "shortcode-only" mode, so you control exactly where the widget appears while the plugin still validates the submission server-side.

= Security =

* Every settings save, AJAX action, and admin form is nonce-protected and capability-checked (`manage_options`, or `manage_woocommerce` for the shared Kitgenix admin menu when only WooCommerce management access is granted).
* The stored Secret Key is never printed in the settings page HTML; revealing it uses a dedicated, nonce-protected AJAX action available to administrators only.
* Trusted-proxy IP headers (`CF-Connecting-IP`, `True-Client-IP`, `X-Forwarded-For`, `X-Real-IP`) are only honoured when the request originates from an address or CIDR range you've explicitly added to the trusted proxy list, and only public, routable addresses are accepted from them.
* Site Key and Secret Key can be defined outside the database via the `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` and `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY` constants (in `wp-config.php`) or matching environment variables.
* Settings exports exclude your Site Key and Secret Key by default; including them is an explicit opt-in on export.

= Diagnostics =

* A Site Health test ("Cloudflare Turnstile readiness") checks that keys are present, flags duplicate Turnstile loaders from other plugins/themes, and shows your last verification outcome.
* A recent diagnostic log records the last 50 verification events (outcome, integration, error category, and Cloudflare round-trip latency) without storing raw IP addresses, request URLs, or submitted form values.
* Per-integration analytics and both the log and the analytics summary can be exported as CSV from the Support tab.
* Automatic admin and Site Health alerts fire when recent verification failures spike or Cloudflare's `siteverify` endpoint starts failing at the HTTP layer, so a broken integration doesn't fail silently.

= Performance and caching notes =

Turnstile is lightweight, but aggressive optimisation plugins can interfere with rendering or token freshness. If you run a caching/optimisation plugin:

* Allow `https://challenges.cloudflare.com` through any "Delay JS" / "Defer JS" / "Combine JS" rule.
* Avoid full-page caching on login, account, and checkout pages.
* Make sure outbound HTTPS requests to Cloudflare aren't blocked – they're required for server-side verification.

= Quick start =

1. Install and activate the plugin.
2. Open the Turnstile settings under the Kitgenix menu in wp-admin.
3. Add your Cloudflare Turnstile Site Key and Secret Key, then run the setup verification test.
4. Configure widget options (theme, size, language) and messaging if needed.
5. Enable the integrations and per-form toggles you want.
6. Test the key journeys on your site: login, registration, checkout, and your main contact form.

Consider starting with Developer mode (warn-only) while you roll out, then disabling it once you've confirmed everything verifies correctly.

== Installation ==

1. In wp-admin, go to Plugins → Add New and search for "Kitgenix Turnstile", or upload the plugin ZIP.
2. Activate the plugin.
3. Open the settings page under the Kitgenix menu in wp-admin.
4. Get a Site Key and Secret Key from your Cloudflare dashboard at https://dash.cloudflare.com/?to=/:account/turnstile (a free Cloudflare account is enough) and enter both on the Site Keys tab.
5. Run the setup verification test so login-sensitive protections can activate.
6. Enable the integrations you want under the Integrations tab, and save.

== Frequently Asked Questions ==

= Do I need a Cloudflare account? =

Yes. Cloudflare Turnstile requires a Site Key and Secret Key from a Cloudflare account. A free account is sufficient.

= Is this plugin free? =

Yes, this plugin is free on WordPress.org, and Cloudflare Turnstile has a free tier.

= Which forms does it protect? =

WordPress login, registration, comments, and password reset/reset; WooCommerce Classic and Blocks checkout, product reviews, and My Account forms; Easy Digital Downloads checkout and account forms; Contact Form 7, WPForms, Fluent Forms, Formidable Forms, Forminator, Gravity Forms, JetFormBuilder, Jetpack Forms, Kadence Forms, Ninja Forms, and Elementor Pro Forms; Ultimate Member, MemberPress, Paid Memberships Pro, MailPoet, wpDiscuz, bbPress, and BuddyPress; and the login/registration/forgot-password pages of the separate Kitgenix Plugin Score plugin. Each is off until you enable it.

= What happens if verification fails? =

The submission is blocked with an error message (customisable in Settings), unless Developer mode (warn-only) is enabled site-wide or for that specific integration – in which case the failure is logged but the submission is still allowed through, so you can test safely.

= Do you verify tokens on the server? =

Yes. Every supported integration validates the Turnstile token server-side against Cloudflare's `siteverify` endpoint; nothing relies on client-side JavaScript alone.

= What is replay protection? =

It rejects a Turnstile token that has already been used once, which blocks a common bot technique of resubmitting a captured token. It's on by default, and the time window is filterable for developers.

= What is the honeypot fallback field? =

An optional hidden field (Settings → Security) that a real visitor never sees or fills in. A bot that submits a form without loading JavaScript typically fills every field blindly, so a filled honeypot is rejected immediately, before Cloudflare's `siteverify` endpoint is even contacted.

= I'm behind Cloudflare or another reverse proxy – is IP detection correct? =

Yes, but only once you enable proxy trust and list your proxy's IP or CIDR range in Settings. Forwarded headers are otherwise ignored, and only public, routable addresses are accepted from them.

= Can I whitelist logged-in users, IPs, or User-Agents? =

Yes. You can skip Turnstile for logged-in users, specific IPs (exact, wildcard, or CIDR, including IPv6), and User-Agent strings, and developers can further adjust the decision with a filter.

= Can different integrations use a different theme, size, or language? =

Yes. The Display Settings tab includes per-integration overrides – leave a field on "Inherit global setting" to keep your site-wide Display Settings, or choose a specific Theme, Widget Size, or Language for one integration only.

= Can I export or import my settings? =

Yes, from the Portability tab. You choose whether the export includes your Site Key and Secret Key, and can import as a full replace or a merge with existing settings.

= Can I define keys outside wp-admin? =

Yes, via the `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` and `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY` constants in `wp-config.php`, or matching environment variables.

= Will Turnstile slow down my site? =

The Turnstile script only loads on pages where a widget is actually needed, loads asynchronously, and the plugin adds preconnect/dns-prefetch hints for Cloudflare's domain to speed up the first request. See "Performance and caching notes" above if you run aggressive caching or optimisation plugins.

= Does this plugin store or share personal data? =

The plugin doesn't add tracking cookies and doesn't sell or share personal data. The diagnostic log is privacy-safe: it avoids storing raw IP addresses, request URLs, or submitted form values. See "External Services" below for what's sent to Cloudflare during verification.

= The widget isn't showing. What should I check? =

Confirm your Site Key is entered, that the relevant integration and per-form toggle are enabled, and clear any page caches. If a caching/optimisation plugin heavily delays scripts, allowlist `https://challenges.cloudflare.com`.

= Users keep seeing verification errors. Why? =

Common causes are cached form pages (an expired security token), aggressive script delay/defer, blocked outbound requests to Cloudflare, a duplicate Turnstile loader from another plugin/theme, or misconfigured proxy trust settings. Developer mode (warn-only) can help you diagnose the cause without blocking real users while you investigate.

== Screenshots ==

1. WordPress login form protected by Cloudflare Turnstile.
2. WordPress registration form protected by Cloudflare Turnstile.
3. WooCommerce Classic checkout, with the widget near the Place Order button.
4. WooCommerce Blocks (Store API) checkout, with the widget rendered inside the block-based checkout UI.
5. WooCommerce My Account login form protected by Cloudflare Turnstile.
6. A contact form protected by Cloudflare Turnstile.
7. A WPForms form protected by Cloudflare Turnstile.
8. An Elementor Pro form protected by Cloudflare Turnstile.
9. Settings overview: Site Key and Secret Key management with a live test widget.
10. Security settings: replay protection and whitelist configuration (logged-in users, IPs, User-Agents).

== Settings Overview ==

**Site Keys**

* Site Key and Secret Key (with a "secret present" state, plus clear/reveal controls)
* Live test widget to confirm your keys work, and the setup verification gate status

**Display**

* Theme (auto/light/dark), Widget Size (normal/compact/flexible), Appearance, Language
* Per-integration Theme/Widget Size/Language overrides – one row per integration, each defaulting to "Inherit global setting"
* Disable submit until completed, custom error message, extra message text

**Integrations**

* Enable/disable each integration and, where available, its Auto vs Shortcode-only injection mode and per-form toggles (WordPress Core, WooCommerce, WooCommerce Blocks, Easy Digital Downloads, and every supported form/membership/community plugin)

**Security**

* Replay protection, honeypot fallback field, Developer mode (warn-only) and per-integration Test Mode
* Whitelist for logged-in users, IPs, and User-Agents
* Proxy trust (enable/disable) and trusted proxy IP/CIDR list

**Portability**

* Export settings to JSON (choosing whether to include keys) and import from JSON
* `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` / `_SECRET_KEY` constant and environment-variable support

**Log**

* Active alerts, per-integration analytics, and the recent diagnostic log, each with CSV export

== Developers ==

Shortcode:

`[kitgenix_turnstile]`

Server-side verification endpoint:

`https://challenges.cloudflare.com/turnstile/v0/siteverify`

Filters (script/loading):

* `kitgenix_captcha_for_cloudflare_turnstile_script_url( $url, $settings )`
* `kitgenix_turnstile_freshness_ms`
* `kitgenix_turnstile_inline_style`

Filters (verification / request handling):

* `kitgenix_turnstile_siteverify_url`
* `kitgenix_turnstile_siteverify_timeout`
* `kitgenix_turnstile_siteverify_sslverify`
* `kitgenix_turnstile_siteverify_http_args`
* `kitgenix_turnstile_send_remoteip`
* `kitgenix_turnstile_remote_ip`
* `kitgenix_turnstile_token_from_request`
* `kitgenix_turnstile_handle_comment_form`
* `kitgenix_turnstile_error_codes`
* `kitgenix_turnstile_error_message`
* `kitgenix_turnstile_replay_message`
* `kitgenix_captcha_for_cloudflare_turnstile_{context}_turnstile_error_message`
* `kitgenix_turnstile_skip_wp_login_validation( $skip, $user )` – return true to skip the WordPress Core login integration's Turnstile check for a specific `authenticate` callback invocation (already skipped by default for XML-RPC and REST-context requests, where no widget exists to solve).

Filters (replay protection):

* `kitgenix_turnstile_replay_ttl`

Filters (operational alerts):

* `kitgenix_turnstile_alert_window_seconds`
* `kitgenix_turnstile_alert_failure_spike_min_failures`
* `kitgenix_turnstile_alert_failure_spike_failure_rate`
* `kitgenix_turnstile_alert_http_error_min_failures`

Filters (whitelist / proxy trust):

* `kitgenix_turnstile_is_whitelisted( $is_whitelisted, $details )`
* `kitgenix_turnstile_trust_headers`
* `kitgenix_turnstile_trusted_proxies`

Per-integration widget overrides:

Settings keys follow the pattern `theme_override_{key}`, `size_override_{key}`, `language_override_{key}` inside the main settings option, where `{key}` matches the existing `enable_{key}` integration suffix (e.g. `theme_override_gravityforms`, `language_override_elementor`). An empty string means "inherit the global Display Setting". `Script_Handler::get_override_integration_keys()` returns the canonical list of keys/labels; `get_effective_theme()`, `get_effective_size()`, `get_effective_appearance()`, and `get_effective_language_override()` resolve the effective value for a given key.

Developer Mode / Test Mode per integration:

* Settings key (global warn-only): `dev_mode_warn_only` (0/1). Applies to every integration.
* Settings key (per-integration warn-only): `test_mode_integrations` – an array of canonical integration keys (see `Turnstile_Validator::get_all_integration_keys()`, e.g. `wordpress-login`, `woocommerce-checkout`, `ninjaforms`). An integration listed here is warn-only even while the rest of the site enforces normally.
* Both `Turnstile_Validator::is_valid_submission()` and `validate_token()` check the global setting first, then the per-integration list, before returning `false` on any failure – a `false` return from either method in normal (non-warn-only) operation is authoritative and integrations must treat it as a hard block.

Honeypot fallback field:

* Settings key: `honeypot_enabled` (0/1)
* Field name: `Turnstile_Validator::honeypot_field_name()` (constant value: `kitgenix_captcha_for_cloudflare_turnstile_hp_field`)
* Checked at the very top of `Turnstile_Validator::is_valid_submission()`/`validate_token()`, before Cloudflare's `siteverify` is contacted; a tripped honeypot is logged with error code `honeypot_tripped` / category `honeypot-blocked`.

Internal identifiers (options / transients / cookies / meta):

* Option: `kitgenix_captcha_for_cloudflare_turnstile_settings`
* Settings group (Settings API): `kitgenix_captcha_for_cloudflare_turnstile_settings_group`
* Option: `kitgenix_captcha_for_cloudflare_turnstile_metrics`
* Option: `kitgenix_turnstile_recent_event_log`
* Option: `kitgenix_turnstile_last_verify`
* Option: `kitgenix_turnstile_setup_verification` (the setup-verification gate's key-pair hash and last result)
* Transient: `kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect`
* Transient: `kitgenix_turnstile_duplicate_scripts`
* Transient prefix (replay protection): `kitgenix_captcha_for_cloudflare_turnstile_ts_`
* Cookie (replay notice): `kitgenix_captcha_for_cloudflare_turnstile_ts_replay`
* WooCommerce order meta (Blocks/Store API verification): `_kitgenix_turnstile_verified`

Internal nonces / actions:

* Shortcode/form nonce field name: `kitgenix_captcha_for_cloudflare_turnstile_nonce`
* Shortcode/form nonce action: `kitgenix_captcha_for_cloudflare_turnstile_action`
* Settings save nonce field name: `kitgenix_captcha_for_cloudflare_turnstile_settings_nonce`
* Settings save nonce action: `kitgenix_captcha_for_cloudflare_turnstile_settings_save`
* Admin AJAX action (reveal saved secret): `kitgenix_turnstile_get_secret` (WordPress hook: `wp_ajax_kitgenix_turnstile_get_secret`)
* Admin AJAX nonce action (reveal saved secret): `kitgenix_turnstile_reveal_secret`
* Admin-post action (analytics exports): `kitgenix_turnstile_export_analytics`
* Admin-post nonce action (analytics exports): `kitgenix_turnstile_export_analytics`
* Duplicate-loader notice dismiss query arg: `kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe`
* Duplicate-loader notice dismiss nonce action: `kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss`

Actions (developer logging):

* `kitgenix_turnstile_dev_log`

== External Services ==

This plugin uses **Cloudflare Turnstile** to verify form submissions and prevent spam and abuse. A free Cloudflare account and a Turnstile Site Key/Secret Key pair are required for the plugin to function.

The plugin may:

* Load the Turnstile widget script from `https://challenges.cloudflare.com/turnstile/v0/api.js` on any page where a protected form renders, so the visitor can complete the challenge.
* Submit a server-side verification request to `https://challenges.cloudflare.com/turnstile/v0/siteverify` whenever a protected form is submitted.

When verification runs, the plugin sends Cloudflare:

* Your Turnstile Secret Key
* The Turnstile response token generated by the visitor's browser
* The visitor's IP address, as the optional `remoteip` parameter (this can be disabled with the `kitgenix_turnstile_send_remoteip` filter)

The plugin does not send the visitor's browser user agent to Cloudflare as part of the verification payload.

If proxy trust is enabled in Settings, the plugin may read forwarding headers (e.g. `CF-Connecting-IP`, `X-Forwarded-For`) to determine the visitor's real IP address, but only when the request originates from a proxy address you've configured as trusted.

The plugin does not add tracking cookies itself and does not sell or share personal data.

Cloudflare Turnstile documentation: https://developers.cloudflare.com/turnstile/
Cloudflare Terms of Service: https://www.cloudflare.com/website-terms/
Cloudflare Privacy Policy: https://www.cloudflare.com/privacypolicy/

This plugin also includes a shared "Kitgenix hub" screen in wp-admin, which may fetch publicly available plugin metadata from WordPress.org using WordPress core's `plugins_api()` function.

* When it runs: only in wp-admin, on Kitgenix plugin admin pages.
* Data sent: plugin slug(s) only – no personal data.
* Data received: publicly available plugin information (e.g. active install counts, ratings).
* Caching: responses are cached locally as transients for around a day (`kitgenix_hub_wporg_active_installs_v1`, `kitgenix_hub_wporg_ratings_v1`, `kitgenix_hub_wporg_media_v1`).

== Trademark Notice ==

"Cloudflare" and the Cloudflare logo are trademarks of Cloudflare, Inc. This plugin is not affiliated with or endorsed by Cloudflare, Inc.

== Support Development ==

If this plugin helps keep spam away without slowing your site down, you can support ongoing development here:
https://www.paypal.com/donate/?hosted_button_id=KALF36K6JJ9B2

== Credits ==
Built with ❤︎ by @kitgenix - https://kitgenix.com

== Upgrade Notice ==

= 2.0.0 =
Security fix: EDD login, Fluent Forms, Kadence Forms, Ninja Forms, Contact Form 7, and Elementor Forms now properly enforce Turnstile server-side (previously some did not; Elementor hid the retry widget after failure). Also adds WP 7.1/WooCommerce HPOS support and a Protection health dashboard.

== Changelog ==

= 2.0.0 (25 August 2026) =

* New: Redesigned the admin settings interface around the shared Kitgenix design system with a sticky topbar, cross-plugin branding, grouped navigation, an Advanced dropdown for Security, Advanced, and Portability, and a light/dark theme toggle that remembers the selected preference.
* New: Added in-page settings search with "/" and Cmd/Ctrl+K keyboard shortcuts, filtering the active tab's cards and rows as you type and displaying a dedicated no-results state when nothing matches.
* New: Added a central Kitgenix Hub page for discovering, installing, activating, and reviewing Kitgenix plugins from one screen, integrated into the same topbar and navigation shell as the plugin settings.
* New: Added a shared Kitgenix component library providing reusable modals, collapsible sections, copy-to-clipboard controls, sortable/searchable tables, and toast notifications.
* New: Added a dedicated Log tab containing active alerts, site-impact statistics, per-integration analytics, and the recent diagnostic log, moving diagnostics out of the Support tab.
* New: Added Ninja Forms integration with Turnstile tokens delivered through a request header rather than a hidden field, matching Ninja Forms' JSON-based submission architecture.
* New: Documented and re-verified the existing Kitgenix Plugin Score integration for login, registration, and forgotten-password protection.
* New: Added per-integration widget overrides for Theme, Widget Size, and Language, allowing individual integrations to override the global display settings or inherit them unchanged.
* New: Added an optional honeypot fallback under Settings → Security that rejects bots which blindly populate hidden fields before any request is sent to Cloudflare's `siteverify` endpoint.
* New: Added Test Mode per Integration under Settings → Developer Mode, allowing individual integrations to operate in warn-only mode while the rest of the site remains fully enforced.
* New: Diagnostic log entries now record Cloudflare `siteverify` round-trip latency so slow successful responses can be distinguished from failures and timeouts.
* New: Added a Protection Health card to the Log tab with actionable states including Healthy, No recent traffic, High failure rate, Cloudflare unavailable, Duplicate Turnstile loader, and Configuration problem.
* New: Added Integration, Result, Category, and Time Period filters to the recent diagnostic log alongside the existing free-text search.
* New: Renamed the per-integration analytics table to "Integration health matrix" and added each integration's Enabled/Disabled state and Auto/Shortcode injection mode.
* New: Settings imports now provide a client-side preview showing setting-key count, export date, whether Site/Secret keys are included, included settings groups, and plugin-identifier mismatch warnings before Replace or Merge is committed.
* New: On WordPress 7.1 and later, the Kitgenix brand icon is registered with the WordPress Icon Registration API as `kitgenix/mark` for discovery through `wp_get_icon()` and the icons REST endpoint.
* Improved: All settings areas – Site Keys, Display, Integrations, Security, Advanced, and Portability – now use consistent cards, labelled rows, toggle switches, and unified alert components.
* Improved: Refreshed the admin and public-facing Turnstile widget colours from the previous purple palette to the updated Kitgenix blue palette, including light and dark mode variants.
* Improved: The plugin-specific admin stylesheet now builds on the shared Kitgenix admin UI stylesheet for consistent spacing, typography, and component styling.
* Improved: The recent diagnostic log is now displayed as a searchable, paginated table with time, integration, outcome, and a plain-English note instead of a read-only text block.
* Improved: Retained the "Copy recent log" action for quickly copying the raw diagnostic log for troubleshooting or support.
* Improved: Per-integration analytics now supports live search and pagination.
* Improved: The Support tab is now three focused cards – a donate card with a collapsible monthly-amount picker, a "what your support funds" summary, and a "get involved" panel for reviews and plugin links.
* Improved: Stale WordPress security tokens are no longer counted as blocked attempts or included in the failure-spike alert threshold because they represent recoverable session or caching friction rather than genuine security rejections.
* Improved: The replayed-token diagnostic message now explains that the event can result from legitimate actions such as double-clicks or back-button resubmissions as well as malicious replay attempts.
* Improved: Both Turnstile validation entry points now consistently respect site-wide Developer Mode and the new per-integration Test Mode.
* Fix: WooCommerce lost/reset-password protection is now self-contained. Enabling only the WooCommerce lost-password integration now validates its own My Account reset-request form without depending on the separate WordPress Core integration.
* Fix: WooCommerce lost-password validation is scoped to WooCommerce's own form and does not interfere with the native `wp-login.php` lost-password flow.
* Fix: WooCommerce login verification now runs only once per request when both modern and legacy WooCommerce login-error filters fire, preventing legitimate submissions from being incorrectly rejected as replayed or expired.
* Fix: bbPress forum creation now validates Turnstile exactly once on the correct pre-insert action instead of running a second redundant validation through `bbp_new_forum_pre_insert`.
* Fix: Removed incorrect handling that could replace bbPress forum post data with an unexpected validation return value.
* Fix: WPForms Turnstile errors now render the actual error message instead of the literal text "Array".
* Fix: The admin language allow-list is now sourced from `Script_Handler::get_allowed_languages()` so global and per-integration language settings cannot drift apart.
* Fix: Elementor AJAX handling is now scoped to the form that actually submitted a solved Turnstile token instead of affecting every Elementor form on the page.
* Fix: Elementor widgets are now hidden or cleared only after a confirmed successful form submission, preserving the widget after validation failures so visitors can retry.
* Security: Completed a full hook-by-hook enforcement audit of every supported integration to verify that a failed Turnstile check in enforcement mode actually blocks the protected action rather than merely being logged.
* Security: Fixed Contact Form 7 shortcode-only mode so the `wpcf7_validate` validation filter is always registered. Previously shortcode-only placement could render the widget while allowing submissions to bypass validation completely.
* Security: Contact Form 7 shortcode mode now correctly respects the integration's enable/disable setting.
* Security: Removed request-method fail-open checks from Contact Form 7 and Elementor validation. Validation now runs whenever the host plugin invokes the relevant validation hook rather than trusting `$_SERVER['REQUEST_METHOD']` as a security boundary.
* Security: Fixed Easy Digital Downloads login protection by replacing the nonexistent `edd_process_login_form` hook with WordPress's real `authenticate` filter, scoped to EDD login submissions through EDD's own nonce field.
* Security: Fixed Fluent Forms enforcement by replacing the nonexistent `fluentform_submit_validation` hook with Fluent Forms' actual `fluentform/validation_errors` validation filter.
* Security: Rebuilt Kadence Forms protection against the current Kadence Blocks "Form (Adv)" implementation using `kadence_blocks_advanced_form_submission_reject`, replacing dead hooks and an invalid class check that meant validation previously never ran.
* Security: Kadence Advanced Form widget injection now uses an appropriate frontend script bridge because the block does not expose a suitable PHP render filter.
* Security: Fixed Ninja Forms token delivery by attaching the token to the relevant AJAX request header rather than inserting a hidden DOM field that Ninja Forms never included in its JSON submission payload.
* Security: Ninja Forms failed Turnstile checks now terminate the submission request directly instead of writing to an error-array structure that Ninja Forms does not consult.
* Security: `Turnstile_Validator::validate_token()` now respects site-wide Developer Mode and per-integration Test Mode consistently with `is_valid_submission()`.
* Security: Confirmed diagnostic and analytics CSV exports do not introduce spreadsheet formula/DDE injection because they contain plugin-generated labels and counters rather than raw user-submitted form values.
* Security: Confirmed settings exports continue to exclude the Site Key and Secret Key unless an administrator explicitly opts to include them.
* Security: Re-verified trusted-proxy client IP resolution against spoofed proxy headers and out-of-range CIDRs.
* Security: Proxy headers including `CF-Connecting-IP`, `True-Client-IP`, `X-Forwarded-For`, and `X-Real-IP` are only trusted when `REMOTE_ADDR` matches an administrator-configured trusted proxy address or CIDR.
* Security: Client IP values extracted from trusted proxy headers must be valid public addresses; private and reserved addresses are rejected.
* Performance: Frontend Turnstile script loading remains unchanged. Cloudflare `api.js` continues to load once and only when a widget will actually render for the current visitor.
* Performance: Whitelisted requests continue to skip Turnstile frontend script loading entirely.
* Performance: The existing duplicate Turnstile loader detector remains unchanged.
* Compatibility: Confirmed compatibility with WordPress 7.1.
* Compatibility: Declared WooCommerce High-Performance Order Storage (HPOS / `custom_order_tables`) compatibility.
* Compatibility: Re-verified that Checkout Blocks order verification metadata uses WooCommerce's `WC_Order` CRUD API through `update_meta_data()` and `save()` rather than direct database access.
* Compatibility: Verified WooCommerce 11.x compatibility across Classic Checkout, Checkout Blocks/Store API, and WooCommerce My Account login, registration, and lost-password flows against current WooCommerce source.
* Dev: Added `Turnstile_Validator::get_all_integration_keys()` as the canonical list of integrations eligible for Test Mode and per-integration settings, sourced consistently with `get_integration_label()`.
* Dev: Updated Kitgenix logo and favicon filenames and references throughout the plugin and Kitgenix Hub to use the shared brand asset set.
* Dev: Refreshed the Kitgenix Hub plugin directory listing, including the renamed MultiStore for WooCommerce entry and the new Image Optimizer listing.
* Dev: Added automated tests covering enforcement regressions discovered during the integration audit.
* Dev: Added automated tests covering spoofed trusted-proxy headers and out-of-range CIDR scenarios.
* Documentation: Documented the Kitgenix Plugin Score integration in the readme for the first time.
* Documentation: Expanded the changelog to distinguish security-sensitive enforcement fixes from general bug fixes.
* Documentation: Documented the previously undocumented `kitgenix_turnstile_skip_wp_login_validation` filter under Developers → Filters.
