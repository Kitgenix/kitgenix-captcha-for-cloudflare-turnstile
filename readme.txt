=== Kitgenix CAPTCHA for Cloudflare Turnstile ===
Contributors: kitgenix
Donate link: https://www.paypal.com/donate/?hosted_button_id=KALF36K6JJ9B2
Tags: cloudflare turnstile, captcha, anti spam, woocommerce, form security
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add Cloudflare Turnstile to WordPress, WooCommerce and popular forms with server-side verification and anti-spam controls.

== Description ==

**Kitgenix CAPTCHA for Cloudflare Turnstile** adds Cloudflare Turnstile CAPTCHA and anti-spam protection to WordPress, WooCommerce and a wide range of form, membership, community and ecommerce plugins. Challenges are not treated as a client-side decoration: submitted Turnstile tokens are verified server-side with Cloudflare before a protected action is accepted.

The plugin is designed for site owners who want to reduce automated login attempts, fake registrations, comment spam, bot-driven checkout abuse and unwanted form submissions while using Cloudflare's privacy-oriented Turnstile challenge rather than a traditional image CAPTCHA.

Configuration, integration controls, diagnostics and local verification metrics are managed inside WordPress. The only service required for CAPTCHA functionality is Cloudflare Turnstile itself; no Kitgenix verification proxy is used.

Learn more about Kitgenix WordPress plugins at [Kitgenix](https://kitgenix.com/).

= Supported WordPress and Plugin Integrations =

The codebase contains dedicated integrations for:

* WordPress login.
* WordPress registration.
* Lost-password and password-reset flows.
* WordPress comments.
* Custom login forms produced with `wp_login_form()`.
* WooCommerce login, registration, lost password, checkout and related account flows supported by the integration.
* Easy Digital Downloads.
* Elementor forms.
* Contact Form 7.
* WPForms.
* Gravity Forms.
* Fluent Forms.
* Formidable Forms.
* Forminator.
* Ninja Forms.
* Jetpack Forms.
* JetFormBuilder.
* Kadence Forms.
* MailPoet.
* bbPress.
* BuddyPress.
* wpDiscuz.
* Ultimate Member.
* MemberPress.
* Paid Memberships Pro.
* Kitgenix Plugin Score integration points included in the codebase.

Each integration is loaded conditionally and can use integration-specific display/validation behaviour rather than forcing one generic hook onto every form system.

= Server-Side Turnstile Verification =

The browser obtains a Turnstile response token from Cloudflare's official widget. When a protected form is submitted, the plugin sends that token to Cloudflare's official Siteverify endpoint using the WordPress HTTP API. The protected action is allowed only when the verification result satisfies the integration's validation flow.

This server-side step is important because simply placing a widget in the browser is not sufficient protection on its own. The plugin tracks the most recent verification response, error codes and latency for diagnostics and can record aggregate verification metrics locally.

= Setup Verification for Login-Sensitive Forms =

Login, registration and other account-sensitive protections can be gated behind a setup-verification state. The administrator can verify the configured Site Key and Secret Key before those protections are treated as ready.

This reduces the risk of enabling a broken key pair on a login screen and accidentally locking legitimate administrators or customers out of the site.

Site and secret keys can be supplied from plugin settings or from supported environment variables/constants, allowing security-conscious deployments to keep the secret outside the normal WordPress options table.

= Replay Protection =

Turnstile tokens are intended to be short lived and single use. The plugin includes optional replay protection that hashes accepted tokens and temporarily remembers that hash. A token that is submitted again during the replay window can be rejected rather than being accepted repeatedly.

The replay window is filterable for developers. Stored replay information is a hash/temporary value, not the raw challenge token itself.

= Honeypot and Layered Anti-Spam Controls =

An optional honeypot can be rendered alongside Turnstile. This adds a second low-friction signal for simple bots that fill fields a normal visitor never sees.

The plugin also supports whitelisting logic so trusted requests can bypass the challenge where appropriate. Whitelist decisions can take account of configured rules and developer filters rather than hard-coding one bypass mechanism for every site.

= Trusted Proxy and Client IP Handling =

Sites may sit behind Cloudflare, another reverse proxy or a load balancer. The client-IP component can be configured to trust proxy headers only when the request path matches the trusted-proxy configuration. This avoids blindly believing spoofable forwarding headers from arbitrary visitors.

Administrators can also choose whether the resolved visitor IP is included in the Siteverify request to Cloudflare. A developer filter is available to change that behaviour when required by a site's privacy or infrastructure policy.

= Widget Appearance and Placement =

The plugin supports central defaults plus integration-level overrides for Turnstile appearance. Depending on the supported integration, administrators can control options such as theme, size, appearance and language, and can choose placement behaviour where the integration exposes more than one suitable hook.

A manual shortcode is also registered:

`[kitgenix_turnstile]`

The shortcode is useful when the site owner needs to render the widget in a supported custom workflow. Rendering a widget alone does not automatically secure arbitrary custom PHP processing; custom form handlers must still validate the submitted token server-side.

= Diagnostics, Metrics and Site Health =

The plugin includes diagnostics for configuration and verification health, local counters for passed/failed checks, latency information, recent verification events and integration-level metrics. Site Health integration can surface configuration or connectivity issues to administrators.

Developer Mode adds additional troubleshooting detail without changing the fundamental requirement that live submissions be verified correctly when protection is active.

= Settings Portability =

Settings can be exported and imported for controlled migration between WordPress installations. The transfer system is designed for plugin configuration rather than for exporting visitor submissions or unrelated site data.

= Performance and Script Loading =

The public Cloudflare Turnstile script is loaded only for pages/contexts where the plugin determines that a Turnstile widget may be needed. The loader includes duplicate-script detection so multiple integrations do not intentionally enqueue several copies of the same Turnstile API script.

Public assets are kept separate from the admin interface, and admin-only diagnostics/settings code does not need to run as part of every anonymous form request.

= Privacy and Data Flow =

Turnstile is an external service provided by Cloudflare, so challenge rendering and server-side verification necessarily communicate with Cloudflare. The plugin itself stores configuration and limited diagnostic/aggregate verification data locally. It does not require a Kitgenix account and does not send form contents to Kitgenix for verification.

The exact Cloudflare data flow, WordPress.org Hub request and Google Fonts admin request are documented in the **External Services** section below.

= Common Uses =

* Protect a WordPress login page from automated credential attacks.
* Reduce spam registrations on WordPress or WooCommerce.
* Add anti-bot verification to WooCommerce checkout and account forms.
* Protect Elementor and popular WordPress form plugins with one central Turnstile configuration.
* Add a challenge to membership, forum and community registration/login flows.
* Replace more intrusive CAPTCHA experiences with Cloudflare Turnstile while keeping server-side validation.

== Installation ==

1. Upload the plugin through **Plugins → Add New → Upload Plugin**, or install it from the WordPress.org Plugin Directory.
2. Activate **Kitgenix CAPTCHA for Cloudflare Turnstile**.
3. Create a Turnstile widget in your Cloudflare account and copy its Site Key and Secret Key.
4. Open the plugin settings from the Kitgenix menu in wp-admin.
5. Enter the Site Key and Secret Key and run the setup verification test.
6. Enable only the integrations and forms you want to protect.
7. Test the protected forms while logged out and, where relevant, through checkout/account flows.

A Cloudflare account and Turnstile key pair are required. Your website does not need to use Cloudflare's CDN or proxy service to use Turnstile.

== Frequently Asked Questions ==

= What is Cloudflare Turnstile? =

Cloudflare Turnstile is a CAPTCHA alternative that runs a challenge in the visitor's browser and produces a token. The token must then be validated server-side before the protected action is accepted.

= Do I need to use Cloudflare DNS or the Cloudflare CDN? =

No. Turnstile can be used on a WordPress site even when the site's traffic is not proxied through Cloudflare.

= Do I need a Cloudflare account? =

Yes. You need a Cloudflare account and a Turnstile Site Key / Secret Key pair.

= Does the plugin verify Turnstile on the server? =

Yes. Supported integrations validate the token with Cloudflare's Siteverify endpoint before accepting the protected submission, unless Developer Mode or the relevant per-integration Test Mode is intentionally configured to warn rather than block.

= Which WordPress forms can it protect? =

Native WordPress login, registration, lost/reset password and comment forms are supported. The plugin also supports WooCommerce, Easy Digital Downloads, Elementor Pro Forms, Contact Form 7, WPForms, Fluent Forms, Formidable Forms, Forminator, Gravity Forms, JetFormBuilder, Jetpack Forms, Kadence Forms, Ninja Forms and several membership/community plugins.

= Does it support WooCommerce Checkout Blocks? =

Yes. The plugin can render Turnstile in block-based checkout and validates the token server-side during the WooCommerce Store API checkout request.

= Does it support WooCommerce HPOS? =

Yes. The plugin declares HPOS compatibility and uses WooCommerce order CRUD methods for its Checkout Blocks verification metadata.

= Can I choose where the Turnstile widget appears? =

Yes. Automatic placement is available for supported integrations, and many integrations include a shortcode-only placement option. The `[kitgenix_turnstile]` shortcode can also render a widget manually.

= Can I use the shortcode on any custom form? =

The shortcode can render the widget, but an unsupported custom form still needs a server-side validation integration. Rendering a widget alone is not sufficient security.

= Can different forms use different Turnstile themes or sizes? =

Yes. Global theme, size and language settings can be overridden per integration.

= What does Developer Mode do? =

Developer Mode is warn-only. Failed verification is recorded but does not block the submission. Individual integrations can also be placed in Test Mode without putting the entire site into warn-only mode.

= What is replay protection? =

Replay protection helps reject a Turnstile token that has already been accepted or processed. This reduces the usefulness of captured or repeatedly submitted tokens.

= Does the plugin include a honeypot? =

Yes. The optional honeypot can reject simple automated submissions before Cloudflare Siteverify is contacted.

= Can I whitelist administrators or trusted visitors? =

The plugin can whitelist logged-in users, configured IP addresses/ranges and User-Agent strings. Whitelisted visitors do not need to complete Turnstile, and the frontend Turnstile script is skipped for them.

= Does it work behind Cloudflare or another reverse proxy? =

Yes. Proxy-aware IP detection is included. For security, forwarded headers are trusted only when proxy trust is enabled and the connecting proxy matches your configured trusted proxy list.

= Can I stop the visitor IP address being sent to Cloudflare Siteverify? =

Developers can return `false` from the `kitgenix_turnstile_send_remoteip` filter. See the External Services section for the default data flow.

= Can I store the Site Key and Secret Key outside the WordPress database? =

Yes. The plugin supports the `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY` and `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY` constants and matching environment variables.

= Can I move settings between sites? =

Yes. Export settings to JSON and import them using Replace or Merge mode. Credentials are excluded by default unless you explicitly include them.

= Why is the widget not appearing? =

Check that the Site Key exists, the integration and relevant form toggle are enabled, the visitor is not whitelisted, and another plugin or optimisation rule is not blocking `https://challenges.cloudflare.com`. Also check the plugin's duplicate-loader warning and Site Health test.

= Why do I see expired, missing or replayed-token errors? =

Turnstile tokens are short-lived and single-use. Cached forms, back-button resubmissions, double-clicks, delayed JavaScript or submitting after a token has expired can all require a fresh Turnstile challenge.

== Screenshots ==

1. WordPress login protected with Cloudflare Turnstile.
2. WordPress registration protected with Cloudflare Turnstile.
3. Cloudflare Turnstile on WooCommerce Classic Checkout.
4. Cloudflare Turnstile on WooCommerce Checkout Blocks / Store API checkout.
5. WooCommerce My Account login protection.
6. Turnstile protection on a contact form.
7. WPForms protected with Cloudflare Turnstile.
8. Elementor Pro Forms protected with Cloudflare Turnstile.
9. Site Key, Secret Key and setup-verification settings.
10. Security controls.

== Developer Notes ==

= Shortcode =

`[kitgenix_turnstile]`

= Main settings option =

`kitgenix_captcha_for_cloudflare_turnstile_settings`

= Useful filters =

Script and display:

* `kitgenix_captcha_for_cloudflare_turnstile_script_url`
* `kitgenix_turnstile_freshness_ms`
* `kitgenix_turnstile_inline_style`

Verification:

* `kitgenix_turnstile_siteverify_url`
* `kitgenix_turnstile_siteverify_timeout`
* `kitgenix_turnstile_siteverify_sslverify`
* `kitgenix_turnstile_siteverify_http_args`
* `kitgenix_turnstile_send_remoteip`
* `kitgenix_turnstile_remote_ip`
* `kitgenix_turnstile_token_from_request`
* `kitgenix_turnstile_error_codes`
* `kitgenix_turnstile_error_message`
* `kitgenix_turnstile_replay_message`
* `kitgenix_turnstile_skip_wp_login_validation`

Replay protection:

* `kitgenix_turnstile_replay_ttl`

Whitelisting and proxy handling:

* `kitgenix_turnstile_is_whitelisted`
* `kitgenix_turnstile_trust_headers`
* `kitgenix_turnstile_trusted_proxies`

Operational alerts:

* `kitgenix_turnstile_alert_window_seconds`
* `kitgenix_turnstile_alert_failure_spike_min_failures`
* `kitgenix_turnstile_alert_failure_spike_failure_rate`
* `kitgenix_turnstile_alert_http_error_min_failures`

Developer logging action:

* `kitgenix_turnstile_dev_log`

The plugin also exposes context-specific error-message filtering through `kitgenix_captcha_for_cloudflare_turnstile_{context}_turnstile_error_message`.

== Privacy and Local Data ==

The plugin stores its configuration in the WordPress database. Depending on enabled features it also stores local operational data such as setup-verification state, aggregate integration metrics, the recent event log and replay-protection transients.

The recent event log is limited to 50 events and contains operational fields such as time, integration, success/failure, error codes and Siteverify latency. It does not store raw form submissions, the raw Turnstile response token, the visitor's raw IP address or the request URL in that log.

Turnstile itself is an external Cloudflare service and receives data when a widget is loaded and when the server validates a token. See **External Services** below.

== External Services ==

This plugin relies on third-party services for specific functionality. These connections are documented here so site owners can make an informed decision before enabling and using the plugin.

= Cloudflare Turnstile =

Cloudflare Turnstile is the CAPTCHA / bot-verification service that provides the plugin's core protection. A Cloudflare account and Turnstile Site Key / Secret Key are required.

When a protected widget is rendered, the visitor's browser loads Cloudflare Turnstile from:

`https://challenges.cloudflare.com/turnstile/v0/api.js`

The browser communicates with Cloudflare as part of the Turnstile challenge. As with normal web requests, Cloudflare can receive network/request information such as the visitor's IP address and browser/request metadata, and Turnstile evaluates browser signals to generate a verification token.

When a protected form is submitted, the WordPress server sends a POST request to:

`https://challenges.cloudflare.com/turnstile/v0/siteverify`

By default, that request contains:

* The configured Turnstile Secret Key
* The Turnstile response token
* The visitor IP address as Cloudflare's optional `remoteip` parameter when an address is available

The `remoteip` value can be disabled by developers with the `kitgenix_turnstile_send_remoteip` filter.

Cloudflare documentation: https://developers.cloudflare.com/turnstile/
Cloudflare Terms: https://www.cloudflare.com/website-terms/
Cloudflare Privacy Policy: https://www.cloudflare.com/privacypolicy/

= WordPress.org Plugin API =

The shared Kitgenix Hub in wp-admin uses WordPress core's `plugins_api()` functionality to request public WordPress.org plugin-directory information such as plugin details, active-install counts, ratings and media.

These requests occur on Kitgenix administration screens. The plugin supplies WordPress.org plugin slugs to WordPress core; the outbound request itself is handled by WordPress and can include normal HTTP request metadata generated by WordPress. Responses are cached locally with WordPress transients to reduce repeat requests.

WordPress.org: https://wordpress.org/
WordPress.org Privacy Policy: https://wordpress.org/about/privacy/

= Google Fonts =

The Kitgenix administration stylesheet imports the Inter and Manrope font families from Google Fonts. This occurs on Kitgenix plugin administration screens, not as part of the Turnstile verification request itself.

Loading those font resources causes the administrator's browser to connect to Google-hosted domains such as `fonts.googleapis.com` and `fonts.gstatic.com`, which can receive normal request information such as IP address and browser headers.

Google Fonts: https://fonts.google.com/
Google Privacy Policy: https://policies.google.com/privacy
Google Terms: https://policies.google.com/terms

== Trademark Notice ==

Cloudflare and Cloudflare Turnstile are trademarks or services of Cloudflare, Inc. This plugin is independently developed by Kitgenix and is not affiliated with or endorsed by Cloudflare, Inc.

WordPress and WooCommerce trademarks belong to their respective owners. References are descriptive and identify supported integrations.

== Support Development ==

Kitgenix CAPTCHA for Cloudflare Turnstile is free software. If the plugin is useful to you, you can support continued maintenance and development through the Donate link shown on the WordPress.org plugin page.

More WordPress plugins and development resources are available from [Kitgenix](https://kitgenix.com/).

== Upgrade Notice ==

= 2.0.2 =
Version 2.0.2 is recommended for all sites.

== Changelog ==

= 2.0.2 (2 September 2026) =
* New: Added an optional Cloudflare outage failsafe (Settings → Advanced → Reliability, off by default) that independently checks Cloudflare Turnstile's reachability server-side and, once enabled, lets protected forms submit instead of blocking every visitor during a confirmed Cloudflare outage. A visitor cannot trigger this bypass themselves; it only activates when the plugin's own probe confirms Cloudflare is unreachable.
* New: Added a live "Cloudflare outage failsafe is active" alert on the Log tab and in Site Health for as long as the failsafe is bypassing verification, and failsafe bypasses are logged distinctly from genuine Cloudflare-verified passes in the recent events log and per-integration metrics.
* Fix: Resolved an issue where the WooCommerce Blocks Checkout widget could stop rendering after the checkout block re-renders during a normal page load (cart totals, shipping rates and payment methods resolving in sequence). A container that was emptied out by one of these re-renders could be left behind still marked as already rendered, so it silently stayed blank instead of showing the widget. The self-healing recovery logic now recognises a genuinely emptied container and re-renders into it, gated by a grace period so it never interrupts a widget that is still loading or has already been solved.
* Fix: The plugin now properly calls Cloudflare's `turnstile.remove()` before clearing and re-rendering any widget container, across every integration (WooCommerce, Elementor, Gravity Forms, Formidable Forms, Forminator, Jetpack Forms, Fluent Forms, Kadence Forms). Previously the container was cleared directly without unregistering the widget first, which could leave a stale widget reference behind and produce "Cannot find Widget" warnings after repeated re-renders.
* Fix: The WooCommerce Blocks Checkout container-recovery observer now unregisters a widget from Cloudflare's script when the block's own re-render removes its container from the DOM, instead of leaving an orphaned widget reference behind for the replacement container to collide with.
* Fix: Removed an unnecessary plugin-version query parameter that WordPress's automatic asset versioning was appending to Cloudflare's third-party `api.js` script URL. Turnstile's own script logged this as an unrecognised parameter; the URL now matches what Cloudflare's CDN expects.

= 2.0.1 (2 September 2026) =
* Fix: Resolved an issue where the WooCommerce Block Checkout widget would not render consistently.

= 2.0.0 (31 August 2026) =

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
