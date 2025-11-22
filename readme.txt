=== Kitgenix CAPTCHA for Cloudflare Turnstile ===
Contributors: kitgenix
Plugin URI: https://wordpress.org/plugins/kitgenix-captcha-for-cloudflare-turnstile
Author: Kitgenix
Author URI: https://kitgenix.com
Documentation URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation
Support URI: https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/
Feature Request URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/feature-request
Donate link: https://buymeacoffee.com/kitgenix
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.13
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: cloudflare, turnstile, captcha, anti-spam, woocommerce

Cloudflare Turnstile CAPTCHA for WordPress, WooCommerce, Elementor and popular forms. Fast, privacy-first anti-spam with server-side validation.

== Description ==

**Cloudflare Turnstile, done properly for WordPress.**

Kitgenix CAPTCHA for Cloudflare Turnstile is a lightweight, privacy-first **reCAPTCHA alternative** that adds Cloudflare Turnstile to your **WordPress, WooCommerce and form plugins** with **server-side validation**, **replay protection** and **proxy-aware IP detection**.

Protect:

* **WordPress core forms** – login, registration, lost/reset password, comments
* **WooCommerce** – checkout, account login/registration, lost password (Classic and Blocks / Store API)
* **Form plugins** – WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Forminator, Contact Form 7, Jetpack Forms, Kadence Forms
* **Elementor Pro Forms and Popups**
* **Forums** – bbPress topic and reply forms

All with **conditional, async loading**, **no extra cookies or tracking** and zero unnecessary front-end bloat.

= Why Kitgenix? =

* **Ultra-lightweight and fast**
	* Uses the modern WordPress Script API (6.3+) with `strategy=async`
	* Scripts load **only** on protected pages and forms

* **Privacy-first**
	* The plugin itself does not add cookies or tracking
	* Cloudflare Turnstile is designed to minimise data collection

* **Rock-solid server-side validation**
	* Uses Cloudflare’s official `siteverify` endpoint
	* Validates tokens server-side for all supported forms

* **Replay protection**
	* Rejects **reused tokens** by default (TTL is filterable)

* **Proxy-aware client IP**
	* Correctly resolves IPs behind Cloudflare, reverse proxies and load balancers
	* Honours proxy headers only from configured trusted proxies

* **Deep integrations**
	* WordPress core, WooCommerce (Classic and Blocks), Elementor Pro
	* WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Forminator, Contact Form 7, Jetpack Forms, Kadence Forms
	* bbPress forums

* **Smart UX**
	* Optional “disable submit until verified”
	* Token freshness timers and auto-resets
	* Clean inline error hints designed to match Cloudflare’s own UI

* **Production-ready admin**
	* Onboarding wizard and Site Health integration
	* JSON export/import (with optional secret key)
	* Collapsible sections, search/filter box and an “Unsaved changes” bar

* **Multisite-aware**
	* Per-site settings
	* Clean uninstall removes settings site-wide (and network-wide if run via Network Admin)

= Supported Forms and Integrations =

**WordPress Core**

* Login (`wp-login.php`)
* Registration
* Lost and Reset Password
* Comments

Turnstile is injected into each core form and validated **only on POST submissions**. Invalid, expired or reused tokens block the action with a clear message.

**WooCommerce (Classic)**

* Checkout
* Login
* Registration
* Lost Password

Turnstile appears near the **Place order** button and WooCommerce account forms. Validation runs during checkout and account actions. Designed to work safely with checkout fragments and avoids duplicate rendering.

**WooCommerce (Blocks / Store API)**

* Checkout (Blocks / Store API)

The widget renders in the Blocks checkout UI and validates Store API requests server-side. Tokens can be forwarded via `X-Turnstile-Token` or similar headers and are handled automatically by the plugin.

**Elementor Pro (Forms and Popups)**

* Elementor Pro Forms (including popups and dynamically loaded forms)

The widget injects before or after the submit area, listens for Elementor popup and AJAX events and ensures a fresh token for each attempt. Handles multiple forms, popups and delayed popups reliably.

**Contact Form 7**

* All CF7 forms

Auto-injects the widget and re-renders after AJAX errors. A shortcode `[kitgenix_turnstile]` is available for **manual placement**. In Shortcode-only mode, CF7 forms are passed through `do_shortcode()` so manual placement works reliably.

**Fluent Forms**

* All Fluent Forms

Auto-injects, validates server-side and handles AJAX and multi-step flows with automatic re-renders.

**Formidable Forms**

* All Formidable Forms

Injects near the submit area, validates on submit and re-renders after client or server validation errors.

**Forminator Forms**

* All Forminator forms

Works with regular and AJAX forms, including multi-step flows. Tokens are reset on failed submissions.

**Gravity Forms**

* All Gravity Forms

Widget placement before or after submit, with server-side validation on Gravity’s native hooks. Handles AJAX and multi-page forms with safe re-render and no overlapping buttons.

**Jetpack Forms**

* Jetpack Contact Forms

Adds Turnstile to Jetpack forms with proper validation and AJAX behaviour.

**Kadence Forms (Kadence Blocks)**

* Kadence form blocks

Auto-injects on Kadence form blocks, validates server-side and re-renders on validation errors.

**WPForms**

* WPForms Lite and Pro

Injects before or after the submit area. Optional “disable submit until verified” UX, auto-resets on AJAX errors and prevents overlap or layout issues.

**Forums – bbPress**

* Create Topic and Reply forms

Adds Turnstile to bbPress posting forms to reduce automated spam topics and replies, validating before content is saved.

You can enable or disable each integration and location under **Settings → Cloudflare Turnstile**.

= How It Works =

1. Loads the Cloudflare Turnstile API  
   `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit`  
   using the WordPress Script API (async for WordPress 6.3+).

2. Injects a widget into enabled forms  
   Handles AJAX, multi-step forms, popups and other dynamic DOM changes.

3. Verifies tokens server-side  
   Uses Cloudflare’s `/v0/siteverify` endpoint with your secret key and request IP (when appropriate).

4. Enforces replay protection  
   Caches recent tokens (hashed) and rejects re-use (TTL is filterable).

5. Blocks on failure  
   Submissions with invalid, expired or reused tokens are blocked with a clear, user-friendly message.

= Quick Start =

1. Install and activate from **Plugins → Add New** by searching for “Kitgenix Turnstile”.
2. Go to **Settings → Cloudflare Turnstile**.
3. Enter your **Site Key** and **Secret Key** from the Cloudflare Turnstile dashboard.
4. Enable the integrations and specific forms you want to protect.
5. Save changes, then test login, registration, comments, checkout and form pages.

= Performance and Security =

**Performance playbook**

* Async by default using the WordPress Script API with `strategy=async` on WordPress 6.3+.
* Conditional loading so Turnstile assets load only where protection is enabled.
* Works with caching and optimisation plugins:
	* Allowlist `https://challenges.cloudflare.com` in defer/optimise settings.
	* Avoid inlining, combining or heavily delaying the Turnstile script.
	* Exclude login, account and checkout pages from full-page caching where possible.
* Resource hints (preconnect/dns-prefetch) for `https://challenges.cloudflare.com` to speed up first paint.

**Security tips**

* Replay protection is enabled by default; adjust duration using `kitgenix_turnstile_replay_ttl`.
* Configure trusted proxies when using Cloudflare/CDN/reverse-proxy so the correct client IP is used.
* Use Developer Mode (warn-only) on staging to log failures without blocking users.
* Whitelisting (logged-in users, IPs and user agents) is supported but should be used sparingly.

= Troubleshooting =

**Widget not showing**

* Confirm Site and Secret Keys are correct.
* Check that the integration and specific form location are enabled.
* Verify you are not whitelisted (user, IP or user agent).
* Clear caches (page, object, CDN).
* Allowlist `challenges.cloudflare.com` in optimisation plugins.
* Check the browser console for blocked or failed scripts.

**Always seeing “Please verify you are human”**

* Token may be expired or invalid.
* Reduce page cache TTL on form pages.
* Do not full-page cache auth or checkout pages.
* Ensure your server can reach Cloudflare (no firewall blocks).

**Elementor popups or AJAX forms**

* Avoid over-deferring Elementor or form plugin JavaScript.
* The plugin listens to Elementor events and re-renders containers dynamically.

**WooCommerce checkout issues**

* Avoid caching dynamic fragments.
* Confirm the widget renders before the **Place order** button.
* If using custom checkout flows, ensure the token is forwarded correctly.

== Installation ==

1. Install via **Plugins → Add New** and search for “Kitgenix Turnstile”, or upload the ZIP file to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen.
3. Go to **Settings → Cloudflare Turnstile**.
4. Enter your **Site Key** and **Secret Key** from the Cloudflare Turnstile dashboard.
5. Enable the integrations and forms you want to protect.
6. Save and test your login, registration, comments, checkout and form pages.

== Frequently Asked Questions ==

= Do I need a Cloudflare account? =

Yes. A free Cloudflare account is enough to create a **Turnstile Site Key** and **Secret Key**.

= Does this support Elementor Free? =

We officially support **Elementor Pro Forms**. A fallback injector helps with general Elementor forms (including popups), but **Elementor Pro Forms** is the primary target for reliability.

= Is this compatible with caching and optimisation plugins? =

Yes. Scripts are async and conditionally loaded. You may need to allowlist `challenges.cloudflare.com` and avoid over-aggressive script deferral. Avoid full-page caching for login, account and checkout pages.

= Can I skip validation for certain users? =

Yes. You can whitelist logged-in users, IPs (exact, wildcard or CIDR) and user agents. There is also a filter to adjust whitelist decisions programmatically.

= How is this different from Google reCAPTCHA? =

Cloudflare Turnstile is a **privacy-first**, low-friction alternative. It avoids user tracking, aims to minimise data collection and still blocks bots effectively.

= Which form plugins are supported? =

WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Forminator, Contact Form 7, Jetpack Forms, Kadence Forms, plus Elementor Pro Forms and core WordPress and WooCommerce forms.

= Can I change the widget theme, size and language? =

Yes. You can choose `auto`, `light` or `dark`, select `small`, `normal`, `large` or `flexible` sizes, switch interaction mode (`always` or `interaction-only`) and set language (`auto` or a specific locale code).

= Why are there collapsible sections on the Settings page? =

To make the page easier to scan. Groups such as Shortcode, Display, Security and Integrations are collapsible, and their open or closed state is remembered per browser.

= How do I quickly find a setting? =

Use the filter box on the left side of the settings page. Typing text hides non-matching navigation links and cards. Clear it with the “×” button.

= What does the floating “Unsaved changes” bar mean? =

It appears after you modify at least one field and reminds you to save. Click the Save button in the bar or the normal Save Settings button. Once saved, the bar disappears.

= How do I copy the Turnstile shortcode? =

Open the **Shortcode and Manual Placement** section and click the Copy button next to `[kitgenix_turnstile]`.

= Can I disable the collapsible behaviour? =

Yes. A developer can dequeue the admin JavaScript or filter the settings page output to remove `<details>` wrappers. Native `<details>` elements degrade gracefully if scripting is disabled.

= Can I pin or self-host the Turnstile script? =

Yes. Use the `kitgenix_captcha_for_cloudflare_turnstile_script_url` filter to override the script URL or append query arguments.

= Does it work on Multisite? =

Yes. Settings are per-site. Uninstalling via Network Admin removes settings network-wide.

= Is the plugin GDPR compliant? =

The plugin itself does not store personal data. Cloudflare Turnstile processes IP and user agent to verify challenges. Review Cloudflare’s documentation and your legal requirements for your specific use case.

== Screenshots ==

1. WordPress Login protected by Cloudflare Turnstile (success state).
2. Elementor contact form protected by Turnstile, displayed before the submit button.
3. WooCommerce “My Account → Register” form with Turnstile verification.
4. Security settings: whitelist logged-in users, IPs and user agents; per-form WordPress toggles.
5. Quick setup screen: enter Cloudflare Turnstile keys, choose widget theme, size and mode, then test the widget.

== Minimum Requirements ==

* WordPress 5.0 or higher
* PHP 7.0 or higher
* Cloudflare Turnstile Site and Secret Keys (free)

== Developers ==

**Key filters**

* `kitgenix_captcha_for_cloudflare_turnstile_script_url( $url, $settings )`  
  Override the Turnstile script URL or append query arguments.

* `kitgenix_turnstile_freshness_ms`  
  Control token auto-reset interval (milliseconds).

* `kitgenix_turnstile_replay_ttl`  
  Adjust replay protection cache duration (seconds).

* `kitgenix_turnstile_is_whitelisted( $is_whitelisted, $context )`  
  Modify whitelist decisions programmatically.

**Shortcode**

* `[kitgenix_turnstile]` – Render the Turnstile widget manually. Integrations detect the shortcode or rendered widget container and skip auto-injection to prevent duplicates.

**Server-side endpoint**

* Validates via `https://challenges.cloudflare.com/turnstile/v0/siteverify`.

**Text domain**

* `kitgenix-captcha-for-cloudflare-turnstile` (POT file included).

== Integration Files ==

Each integration maps to a dedicated adapter class for maintainability.

* **WordPress Core:**
	* `includes/integrations/wordpress/class-wp-core.php`

* **WooCommerce (Classic):**
	* `includes/integrations/ecommerce/class-woocommerce.php`

* **WooCommerce (Blocks / Store API):**
	* `includes/integrations/ecommerce/class-woocommerce-blocks.php`

* **Elementor Pro:**
	* `includes/integrations/page-builder/class-elementor.php`

* **Form plugins:**
	* Contact Form 7 — `includes/integrations/forms/contact-form-7.php`
	* Fluent Forms — `includes/integrations/forms/fluent-forms.php`
	* Formidable Forms — `includes/integrations/forms/formidable-forms.php`
	* Forminator Forms — `includes/integrations/forms/forminator-forms.php`
	* Gravity Forms — `includes/integrations/forms/gravity-forms.php`
	* Jetpack Forms — `includes/integrations/forms/jetpack-forms.php`
	* Kadence Forms — `includes/integrations/forms/kadence-forms.php`
	* WPForms — `includes/integrations/forms/wpforms.php`

* **Forums:**
	* bbPress — `includes/integrations/forums/bbpress.php`

* **Integration folders:**
	* `includes/integrations/`
	* `includes/integrations/ecommerce/`
	* `includes/integrations/forms/`
	* `includes/integrations/forums/`
	* `includes/integrations/page-builder/`
	* `includes/integrations/wordpress/`

== Roadmap ==

* Per-form controls and additional UI refinements
* More granular placement options for page builders and theme forms
* Expanded compatibility notes and presets for popular optimisation and caching plugins
* Additional developer hooks and integration examples


== Changelog ==

= 1.0.13 (22 November 2025) =
* Security Fix: Critical validation bypass in Elementor Pro Forms and Forminator Forms where missing tokens were incorrectly allowing form submissions instead of blocking them.
* Fix: Elementor Pro Forms now properly fail validation when Turnstile token is missing or empty (previously skipped validation entirely).
* Fix: Forminator Forms now properly fail validation when Turnstile token is missing or empty (previously skipped validation entirely).
* Fix: Removed `wp_kses_post()` wrapper from Forminator submit button HTML that could strip required attributes.
* Security: Audit confirmed all other integrations (Contact Form 7, Gravity Forms, Formidable Forms, WPForms, Fluent Forms, Jetpack Forms, Kadence Forms, WooCommerce, WordPress Core, bbPress, BuddyPress) correctly validate and fail when tokens are missing.
* Important: This update fixes a security vulnerability where forms could be submitted without completing CAPTCHA verification. **Update immediately.**

= 1.0.12.1 (22 November 2025) =
* Fix: Reverted to 1.0.11 until secuirty update was released.

= 1.0.12 (21 November 2025) =
* New: Global shortcode `[kitgenix_turnstile]` to render the Turnstile widget manually inside custom HTML fields, form content or page templates.
* Improvement: Auto-inject versus Shortcode behaviour is now mutually exclusive and consistent across integrations.
* Added: `includes/core/class-turnstile-shortcode.php` with a robust shortcode renderer and recursive detection helper `has_shortcode_in()` that detects literal shortcodes and rendered widget markers (`class="cf-turnstile"`, `data-kitgenix-shortcode` or hidden `name="cf-turnstile-response"`).
* Updated: Integration adapters to use the new helper and treat literal shortcode text separately from rendered markup so Auto mode is not blocked by leftover shortcode tokens.
* Updated: When an integration needs to run `do_shortcode()` in Auto mode, it temporarily removes the plugin shortcode, runs `do_shortcode()` and then immediately re-registers the shortcode so it is never left unregistered.
* Fix: CF7 shortcode rendering in Shortcode-only mode – Contact Form 7 form HTML is now passed through `do_shortcode()` when the integration is set to Shortcode-only.
* Improvement: Ensured Shortcode-only mode works across all supported form plugins via defensive `do_shortcode()` passthroughs and field-level filters, while Auto mode detection ignores literal shortcode tokens.
* UI: Only show the global Shortcode guidance card when at least one supported forms integration is present. Removed Auto/Shortcode radio controls from the WordPress Core card; core forms use the Enable checkbox and per-form toggles only.
* Dev: Reworked temporary shortcode removal logic to guarantee re-registration after `do_shortcode()`. Fixed edge-case uninitialised variable and parse issues.
* Dev: Standardised detection and injection semantics and added comments and guards for missing site keys, filters and plugin version differences.
* Note: The stored `mode_wp_core` setting is retained for compatibility but no longer exposed in the UI. It can be removed in a future release if needed.

= 1.0.11 (19 October 2025) =
* Fix: Elementor AJAX regression — prevented a brief layout “bump” where interaction-only lost `.kitgenix-ts-collapsed` during the AJAX send; the container now stays collapsed unless a visible challenge is explicitly required.

= 1.0.10 (16 October 2025) =
* Fix: Elementor Popups — reliably initializes the Turnstile challenge when a popup opens (even if the widget was inserted while hidden). Clears stale render flags, resets hidden iframes, and triggers a fresh render on show.
* Fix: Hidden input — always ensures `input[name="cf-turnstile-response"]` exists for Elementor forms (including popups) so the token is properly captured and validated.
* Fix: Interaction Only empty gaps — placeholders are now fully collapsed until the widget actually renders (via `data-rendered`). After successful AJAX submits, the container is collapsed/hidden to prevent any blank space.
* Fix: Multiple forms on a page — consistent collapsed behavior across instances; prevents duplicate containers in Elementor popups and re-renders only when needed.
* Improvement: Event-driven rendering — added `kgx:turnstile-containers-added` event from injectors; public script listens and re-initializes rendering automatically for dynamically added containers.
* Improvement: Stability and UX — defensive re-render guards, explicit `data-rendered` attribute for CSS control, and safer visibility checks to avoid rendering inside hidden containers.

= 1.0.9 (15 October 2025) =
* Fix: “Disable Submit Button” now respects “Interaction Only” — submit stays enabled when Turnstile can verify invisibly, and is disabled only if a visible challenge is actually required (unsupported/timeout/error). Applies to Elementor, WordPress Core forms, WooCommerce, Gravity Forms, Formidable, Forminator, Jetpack, Fluent Forms, and Kadence.
* Improvement: Proactive reveal for Interaction Only — if auto-verification doesn’t complete after a short period (~5s), the widget is surfaced and the challenge is triggered so users aren’t left waiting.
* Improvement: Submit-time guards — for regular forms and Elementor AJAX; when no token is present, we halt that submission, reveal the widget, scroll it into view, and start a fresh challenge.
* Improvement: Streamlined inline messaging to align with Cloudflare’s own phrasing; reduced redundant prompts to let Cloudflare’s UI lead the experience.
* Dev: Standardized render locks and defensive pre-render cleanup across remaining integrations to prevent duplicate iframes and race conditions.

= 1.0.8 (15 October 2025) =
* Fix: Interaction Only placeholder stays collapsed (no gap/shadow) after invisible validation; it only expands when UI is truly required (via `unsupported`/`timeout`/`error` callbacks or actual visible challenge).
* Fix: Prevent loader overlay, no spinner is injected for Interaction Only while the API loads; collapsed state fully hides any inner spinner and spinners never intercept clicks.
* Fix: Elementor popup - reliably renders Turnstile when popups open after page load (e.g., delayed by timer); if a widget initialized while hidden, it is reset and re-rendered on open.
* Fix: Elementor popup duplicates - de-duplicated popup/form event listeners and centralized rendering to avoid multiple widget instances; idempotent guards ensure one render per container.
* Fix: Prevent duplicate renders on Gravity Forms, Formidable, Forminator, and Jetpack by adding per-element render locks and pre-render cleanup.
* Improvement: Deferred render - widgets now render when their container is visible (Elementor + generic paths), reducing layout thrash and improving perceived load times across dynamic UIs.
* Dev: Simplified collapse logic by removing the previous mutation-based watcher and relying on Turnstile callbacks + visibility checks.

= 1.0.7 (14 October 2025) =
* New: Added "Flexible (100% width)" widget size (Cloudflare Turnstile `data-size="flexible"`) for fully responsive, container-width layouts. (Thank You: @kammsw)
* New: Interaction Only UX refinement – collapses initial blank gap (no more 50+px empty space) until the user interacts or the widget needs to expand. (Thank You: @kammsw)
* Improvement: Unified size handling in JS (`flexible` passes straight through; existing custom sizes still map to Cloudflare equivalents).
* Improvement: Consistent collapsed/expand logic across Elementor, Gravity Forms, Formidable, Forminator, Jetpack, Fluent Forms, Kadence, WPForms, and core render paths.
* Improvement: CSS enhancements for flexible width + reduced gap state (`.kitgenix-ts-collapsed`).
* Prep: Foundation laid for upcoming modal/delayed form robustness (MutationObserver structure ready for attribute watching & visibility checks in a future release).
* Dev: Sanitization now allows `flexible`; admin settings UI updated with help text.

= 1.0.6 (10 September 2025) =
* Improvement: Updated plugin assets (banners, icons, screenshots with clearer cropping/labels).
* Improvement: Updated readme.txt — full integrations list, screenshot captions, Support Development section, improved tags/short description, and clarified WooCommerce Blocks/Store API notes.

= 1.0.5 (10 September 2025) =
* Fix: Expose `window.KitgenixCaptchaForCloudflareTurnstile` so Cloudflare onload can reliably call `renderWidgets()` (prevents “no widget → no token”).
* Fix: Guarded “render once” logic to prevent duplicate widget rendering across core, WooCommerce and form plugins.
* Fix: Contact Form 7 injects once and resets cleanly on CF7 validation/error events.
* Fix: WooCommerce login/checkout placement (Classic & Blocks / Store API), including correct “Place order” positioning.
* Fix: Prevent Turnstile overlapping submit buttons for Gravity Forms and WPForms; adjusted spacing and placement heuristics.
* Fix: Admin: detect duplicate Turnstile API loader and show a dismissible notice on Settings and Plugins screens.
* Fix: “Disable Submit Until Verified” now disables buttons on render and re-enables only after a valid token callback.
* Fix: Token handling — canonical token channel, auto-create hidden `cf-turnstile-response` input, `getLastToken()` helper, and `kitgenixcaptchaforcloudflareturnstile:token-updated` event.
* Fix: Sanitization & import/export hardening — preserve CIDR & wildcard IP patterns.
* Fix: Guard Elementor script enqueue to avoid PHP warnings in REST/AJAX or early hooks.
* Improvement: More reliable widget injection and cleanup on AJAX/dynamic DOM events; tighter re-render/reset behavior.
* Security: Replay protection enabled by default (TTL filterable via `kitgenix_turnstile_replay_ttl`).

= 1.0.4 (17 August 2025) = 
* Fix: Position Turnstile above the WooCommerce reviews submit button. Thank You @carlbensy16.
* Fix: Prevent Turnstile from rendering inline with the submit button on Gravity Forms. Thank You @carlbensy16.
* Fix: Add spacing so Turnstile no longer overlaps the WPForms submit button. Thank You @carlbensy16.

= 1.0.3 (12 August 2025) =
* Fix: "Save Settngs" button not working after a few attempts

= 1.0.2 (12 August 2025) =
* Fix: Run Turnstile validation only on POST submissions for core forms (login, register, lost password, reset password, comments). Prevents the “Please complete the Turnstile challenge” message on refresh or wrong password.
* Fix: Added widget render on resetpass_form and proper validation via validate_password_reset; lost password now validates via lostpassword_post.
* Fix: Reintroduced inline centering on wp-login/wp-admin to stabilize layout across all auth screens.
* Fix: Expose the public module globally as window.KitgenixCaptchaForCloudflareTurnstile so the Cloudflare API onload callback can actually call renderWidgets() (prevents “no widget → no token” failures).
* Fix: Guarded “render once” logic so widgets don’t duplicate across hooks (core + WooCommerce + form plugins).
* Fix: Contact Form 7 integrates cleanly (single injection, resets on CF7 error events).
* Fix: WooCommerce login handles both modern woocommerce_process_login_errors and legacy woocommerce_login_errors.
* Fix: Duplicate Turnstile API loader detection with a dismissible admin notice (surfaces on our Settings page and Plugins screen).
* Improvement: When Disable Submit Button is enabled, submit buttons are now disabled immediately on render and re-enabled only after a valid token callback (previously disabled only on error/expired).
* Improvement: Added a canonical token channel. (getLastToken() helper and kitgenixcaptchaforcloudflareturnstile:token-updated event dispatched on each token change.) (Hidden cf-turnstile-response input is auto-created in forms that don’t already have it.)
* Improvement: Token freshness & UX. (Idle timer and token-age timer auto-reset widgets after ~150s (filterable via kitgenix_turnstile_freshness_ms).) (Gentle inline “Expired / Verification error — please verify again.” message displayed next to the widget.)
* Improvement: Add preconnect/dns-prefetch resource hints for https://challenges.cloudflare.com to speed up first paint.
* Improvement: Public CSS greatly reduced in scope (fewer global !importants), small min-height to prevent CLS, better RTL + reduced-motion support, and per-integration spacing.
* Improvement: Admin CSS fully scoped to the settings wrapper, compact modern fields, focus-visible styles, and reduced-motion fallback.
* Improvement: “Test widget” is rendered only via a tight inline onload callback (prevents double-render / undefined globals).
* Improvement: Site Health test (“Cloudflare Turnstile readiness”) reporting keys presence, duplicate loader detection, last verification snapshot, and possible JS delay/defer from optimization plugins (with guidance).
* Improvement: Export / Import JSON for settings (merge/replace). Optional inclusion of Secret Key (explicitly allowed).
* Improvement: Onboarding screen with one-time activation redirect, quick start steps, and helpful links.
* Improvement: Late alignment helpers for consistent widget placement on login/admin.
* Improvement: Housekeeping—centralized render flow, lightweight MutationObserver to catch dynamically added forms, safer class/existence guards.
* Improvement: Ensure hidden input + container are present; don’t inject a container if no site key is available. (Elementor)
* Improvement: Include token in Elementor Pro AJAX payloads; re-render in popups and dynamic forms; reset widget on submit/errors.
* Improvement: Server-side validation hook support (elementor_pro/forms/validation).
* Improvement: Consistent widget + validation across checkout/login/register/lost password. (WooCommerce Classic)
* Improvement: Checkout protected via woocommerce_checkout_process and woocommerce_after_checkout_validation. (WooCommerce Classic)
* Improvement: Inject container next to the “Place order” area via render_block_woocommerce/checkout-actions-block. (WooCommerce Blocks)
* Improvement: Validate Store API POSTs early via REST auth filter; token accepted from X-Turnstile-Token header or extensions. (WooCommerce Blocks)
* Improvement: Reliable widget injection before submit, spinner cleanup, and re-render on each plugin’s AJAX/DOM events.
* Improvement: Server-side validation mapped to each plugin’s native API.
* Improvement: Preserve CIDR and wildcard IP patterns instead of stripping them; sanitize lines while keeping valid patterns.
* New: Added advanced fields - respect_proxy_headers and trusted_proxy_ips (legacy), plus new trust_proxy and trusted_proxies (current).
* New: Developer Mode (warn-only) — Turnstile failures are logged and annotated inline for admins but do not block submissions (great for staging/troubleshooting).
* New: Replay protection — caches recent Turnstile tokens (hashed) for ~10 minutes and rejects re-use. Enabled by default; duration filterable via kitgenix_turnstile_replay_ttl.
* Security: Added Cloudflare/Proxy-aware client IP handling. New Trust Cloudflare/Proxy headers + Trusted Proxy IPs/CIDRs settings. We only honor CF-Connecting-IP / X-Forwarded-For when the request comes from a trusted proxy; otherwise fall back to REMOTE_ADDR.
* Security: Whitelist supports logged-in bypass, IPs with exact/wildcard/CIDR (IPv4/IPv6), and UA wildcards; decision cached per request and filterable via kitgenix_turnstile_is_whitelisted.
* Security: Validator accepts token from POST, X-Turnstile-Token header, or custom filter; memoized siteverify; robust HTTP args; remote IP + URL + timeouts filterable; friendly error mapping; last verify snapshot stored for diagnostics.

= 1.0.1 (11 August 2025) =
* Fix: Center Cloudflare Turnstile on all `wp-login.php` variants (login, lost password, reset, register) and across wp-admin.
* Change: Overhauled includes/core/class-script-handler.php to use the modern Script API (async strategy on WP 6.3+, attribute helpers on 5.7–6.2) and eliminated raw <script> output.
* Dev: Public/admin assets now use filemtime() for cache-busting.
* Dev: Added filter `kitgenix_captcha_for_cloudflare_turnstile_script_url` for advanced control.
* Docs: Expanded readme and updated links.

= 1.0.0 (11 August 2025) =
* New: Initial release
* New: WordPress Login Integration
* New: WordPress Registration Integration
* New: WordPress Lost Password Integration
* New: WordPress Comment Integration
* New: WooCommerce Checkout Integration
* New: WooCommerce Login Integration
* New: WooCommerce Registration Integration
* New: WooCommerce Lost Password Integration
* New: Elementor Forms Integration
* New: WPForms Integration
* New: Kandence Forms Integration
* New: Jetpack Forms Integration
* New: Gravity Forms Integration
* New: Forminator Forms Integration
* New: Formidable Forms Integration
* New: Fluent Forms Integration
* New: Contact Form 7 Integration
* New: Conditional Script Loading for Performance
* New: Widget Size, Theme, and Appearance Options
* New: Defer Scripts and Disable-Submit Logic
* New: Whitelist by IP, User Agent, or Logged-in Users
* New: Custom Error and Fallback Messages
* New: Modern Admin UI with Onboarding Wizard
* New: Optional Plugin Badge
* New: Multisite Support
* New: Works With Elementor Element Cache
* New: GDPR-friendly, No Cookies or Tracking
* New: Optimized for Caching, AJAX, and Dynamic Forms
* New: No Impact on Core Web Vitals
* New: Site Key & Secret Key Management
* New: Per-Form and Per-Integration Enable/Disable
* New: Language Selection for Widget
* New: Customizable Widget Appearance
* New: Server-Side Validation for All Supported Forms
* New: CSRF Protection (Nonce Fields)
* New: Error Handling and User Feedback
* New: Support for AJAX and Dynamic Form Rendering
* New: Admin Notices and Settings Errors
* New: Plugin Translations/Localization

== Upgrade Notice ==

= 1.0.13 =
**SECURITY UPDATE - Update immediately.** Fixes critical validation bypass in Elementor Pro Forms and Forminator Forms where missing CAPTCHA tokens were incorrectly allowing submissions. All users should update immediately to ensure proper CAPTCHA protection.


== External Services ==

This plugin connects to **Cloudflare Turnstile** to perform spam and abuse prevention checks. It sends:

* Turnstile site key
* Turnstile response token
* User IP address and user agent (used by Cloudflare for verification)

No personal data is stored or processed by Kitgenix.

Cloudflare Turnstile Terms: https://developers.cloudflare.com/turnstile/  
Cloudflare Privacy Policy: https://www.cloudflare.com/privacypolicy/

== Trademark Notice ==

“Cloudflare” and the Cloudflare logo are trademarks of Cloudflare, Inc. This plugin is not affiliated with or endorsed by Cloudflare, Inc.

== Copyright ==

Kitgenix CAPTCHA for Cloudflare Turnstile is built with ❤️ by Kitgenix.

== Credits ==

Cloudflare Turnstile – https://www.cloudflare.com/products/turnstile/  
Built with ❤️ by https://kitgenix.com

== Support Development ==

If this plugin helps you stop spam and keep your forms fast and user-friendly, you can support ongoing development here:

https://buymeacoffee.com/kitgenix
