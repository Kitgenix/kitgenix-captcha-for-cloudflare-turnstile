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
Stable tag: 1.0.7
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: cloudflare, turnstile, captcha, anti-spam, woocommerce

Cloudflare Turnstile for WordPress, WooCommerce, Elementor & top form plugins. Fast, privacy-first anti-spam with server-side validation.

== Description ==

**Stop spam without punishing real users.** Kitgenix CAPTCHA for Cloudflare Turnstile integrates Cloudflare’s modern, low-friction, **reCAPTCHA-free** challenge with WordPress so you can block bots *and* keep conversions high.

Protect **WordPress login/registration/password/comments**, **WooCommerce checkout & account (Classic + Blocks / Store API)**, and **popular form builders** using **server-side verification**, **replay protection**, and **proxy-aware IP detection**. Built for performance (async/conditional loading) and privacy (no cookies or tracking added by the plugin; GDPR-friendly).

**Why Kitgenix**
- **Ultra-lightweight & fast** — Modern WP Script API (6.3+) with `strategy=async`; loads only where needed.
- **Privacy-first** — No cookies/tracking added by the plugin; Turnstile minimizes data collection.
- **Rock-solid server-side validation** — Official `siteverify` endpoint.
- **Replay protection** — Rejects reused tokens by default (TTL filterable).
- **Proxy-aware client IP** — Honors CF/Proxy headers only from **trusted** proxies.
- **Seamless integrations** — WordPress Core, WooCommerce (Classic & Blocks), Elementor Pro, and major form plugins.
- **Smart UX** — Optional “disable submit until verified”, token freshness timers, inline error hints.
- **Production-ready admin** — Onboarding, Site Health test, JSON import/export, accessible UI.
- **Multisite aware** — Clean uninstall removes settings site-wide (and network-wide on Multisite).

---

## Supported Forms & Integrations (with descriptions)

### WordPress Core
- **Login, Registration, Lost/Reset Password, Comments**  
  Adds a Turnstile widget to core auth and discussion forms. Validates tokens on submit (POST-only) and blocks invalid/expired/reused tokens with a clear message.

### WooCommerce (Classic)
- **Checkout, Login, Registration, Lost Password**  
  Renders near the **Place order** area and account/auth forms. Server-side verification runs during checkout validation and account actions. Designed to work with fragment reloads; avoids double-submit and duplicate renders.

### WooCommerce (Blocks / Store API)
- **Checkout (Blocks)**  
  Injects the widget in Blocks checkout UIs and validates **Store API** requests on the server. Token can be forwarded via header (e.g., `X-Turnstile-Token`) and is handled automatically by the plugin/extension.

### Elementor Pro (Forms & Popups)
- **Elementor Pro Forms (including Popups and dynamically rendered forms)**  
  Injects before/after the submit area. Listens to Elementor events to re-render on popups, AJAX submissions, and validation errors. Ensures a fresh token on each attempt.

### Contact Form 7
- **All CF7 forms**  
  Auto-injects the widget; resets and re-renders after AJAX errors. No special shortcode needed. Prevents send on failed verification.

### Fluent Forms
- **All Fluent Forms**  
  Auto-injects and validates server-side via the plugin’s hooks. Handles AJAX and multi-step flows with automatic re-render.

### Formidable Forms
- **All Formidable forms**  
  Auto-injects the widget; validates on submit; re-renders after client or server validation errors.

### Forminator Forms
- **All Forminator forms**  
  Works with regular and AJAX-loaded forms, including multi-step. Automatically resets token on failed submissions.

### Gravity Forms
- **All Gravity Forms**  
  Widget placement before/after submit; validates server-side on the form’s native hooks. Handles AJAX and multi-page with safe re-render.

### Jetpack Forms
- **Jetpack Contact Forms**  
  Adds Turnstile to Jetpack forms and validates on submit. Respects Jetpack’s AJAX behaviors.

### Kadence Forms (Kadence Blocks)
- **Kadence Forms**  
  Auto-injects on Kadence form blocks; validates server-side; re-renders on client errors.

### WPForms
- **All WPForms (Lite/Pro)**  
  Injects before/after the submit area; prevents send until verification passes (optional disable-until-verified UX); resets on AJAX errors.

### Forums: bbPress
- **Create Topic & Reply forms (bbPress)**  
  Adds Turnstile to bbPress posting forms to reduce automated spam topics/replies. Validates before content is saved.

> Enable/disable each integration and location in **Settings → Cloudflare Turnstile**.

---

## How It Works (Technical)
1. Loads `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit` with async strategy (WP ≥ 6.3).  
2. Injects a widget into enabled forms; re-renders for dynamic loads (AJAX, multi-step, popups).  
3. Validates server-side via **`/v0/siteverify`** with your secret key and request IP (when appropriate).  
4. On failure (invalid/expired/reused token) submission is blocked with clear, customizable messaging.

---

## Quick Start
1. **Install & Activate** → **Plugins → Add New** → search “Kitgenix Turnstile”.  
2. **Add Keys** → **Settings → Cloudflare Turnstile** → paste **Site Key** & **Secret Key** from your Cloudflare dashboard.  
3. **Choose Integrations** → toggle WordPress/WooCommerce/Form plugins and specific locations.  
4. **Save & Test** → try login/register/comments/checkout + your form pages.  
5. **Optional Hardening** → enable **Disable Submit Until Verified** and review **Tools → Site Health** hints.

---

## Performance Playbook
- **Async by default** with WP Script API (6.3+).  
- **Conditional loading** — scripts only where protection is active.  
- **Optimization plugins:**  
  - Allowlist `https://challenges.cloudflare.com`.  
  - Do **not** inline or block the Turnstile script.  
  - Exclude login, account, and checkout pages from full-page caching.  
- **Resource hints** — preconnect/dns-prefetch for faster first paint.

## Security Tips
- **Replay protection** — enabled by default; tune TTL via `kitgenix_turnstile_replay_ttl`.  
- **Trusted proxies** — configure **Trusted Proxies** for accurate client IPs behind Cloudflare/NGINX/etc.  
- **Developer Mode (warn-only)** — on staging, log failures without blocking.  
- **Whitelisting** — logged-in/IP/UA; use sparingly.

---

## Troubleshooting
**Widget not showing** → Check keys + enabled location; confirm you’re not whitelisted; clear caches; allowlist `challenges.cloudflare.com`; check console for blockers.  
**“Please verify you are human”** → Token expired/invalid; reduce page-cache TTL on form pages; don’t cache auth/checkout; ensure the server can reach Cloudflare.  
**Elementor popups/AJAX** → Don’t over-defer Elementor/form plugin JS; the plugin listens for those events.  
**WooCommerce checkout** → Don’t cache fragments; confirm widget renders before **Place order**; ensure token is forwarded on custom checkouts.

---

## Frequently Asked Questions

= Do I need a Cloudflare account? =  
Yes. A free Cloudflare account is enough to generate a **Turnstile Site Key** and **Secret Key**.

= Does this support Elementor Free? =  
We officially support **Elementor Pro Forms**. A fallback injector helps on general Elementor forms (including popups), but **Pro Forms** is the target for reliability.

= Is this compatible with caching/optimization plugins? =  
Yes. Scripts are async/conditional and the widget re-renders after dynamic events. If your optimizer inlines/defers third-party scripts, ensure `challenges.cloudflare.com` isn’t blocked.

= Can I skip validation for certain users? =  
Yes - whitelist logged-in users, IPs (exact/wildcard/CIDR), or user agents.

= How is this different from Google reCAPTCHA? =  
Cloudflare Turnstile is a **privacy-first**, low-friction alternative that avoids user tracking while blocking bots.

= Which form plugins are supported? =  
WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Contact Form 7, Forminator, Jetpack Forms, Kadence Forms — plus Elementor Pro Forms.

= Can I change theme/size/language? =  
Yes - choose `auto/light/dark`, `small/normal/large`, `always/interaction-only`, and language (`auto` or locale code).

= Can I pin/self-host the Turnstile script? =  
Yes, via the `kitgenix_captcha_for_cloudflare_turnstile_script_url` filter.

= Does it work on Multisite? =  
Yes. Settings are per-site. Uninstall removes settings site-wide (and network-wide when run network-wide).

= Is the plugin GDPR compliant? =  
The plugin itself doesn’t store personal data. Cloudflare Turnstile processes IP and user-agent for verification. Consult legal counsel and Cloudflare’s docs for your use case.

---

## Developers

**Filters**  
- `kitgenix_captcha_for_cloudflare_turnstile_script_url( $url, $settings )` - Override the Turnstile script URL or add params.  
- `kitgenix_turnstile_freshness_ms` - Control token auto-reset interval (ms).  
- `kitgenix_turnstile_replay_ttl` - Adjust replay-protection cache duration (seconds).  
- `kitgenix_turnstile_is_whitelisted( $is_whitelisted, $context )` - Modify whitelist decisions programmatically.

**Server-side endpoint**  
- Validates via `https://challenges.cloudflare.com/turnstile/v0/siteverify`.

**Text domain**  
- `kitgenix-captcha-for-cloudflare-turnstile` (POT included).

---

## Integration Files (for developers)
(Each integration listed above corresponds to the file(s) below.)

- **WordPress Core:**  
  `includes/integrations/wordpress/class-wp-core.php`

- **WooCommerce (Classic):**  
  `includes/integrations/ecommerce/class-woocommerce.php`

- **WooCommerce (Blocks / Store API):**  
  `includes/integrations/ecommerce/class-woocommerce-blocks.php`

- **Elementor Pro:**  
  `includes/integrations/page-builder/class-elementor.php`

- **Form Plugins:**  
  Contact Form 7 — `includes/integrations/forms/contact-form-7.php`  
  Fluent Forms — `includes/integrations/forms/fluent-forms.php`  
  Formidable Forms — `includes/integrations/forms/formidable-forms.php`  
  Forminator Forms — `includes/integrations/forms/forminator-forms.php`  
  Gravity Forms — `includes/integrations/forms/gravity-forms.php`  
  Jetpack Forms — `includes/integrations/forms/jetpack-forms.php`  
  Kadence Forms — `includes/integrations/forms/kadence-forms.php`  
  WPForms — `includes/integrations/forms/wpforms.php`

- **Forums:**  
  bbPress — `includes/integrations/forums/bbpress.php`

- **Integration folders:**  
  `includes/integrations/`  
  `includes/integrations/ecommerce/`  
  `includes/integrations/forms/`  
  `includes/integrations/forums/`  
  `includes/integrations/page-builder/`  
  `includes/integrations/wordpress/`

---

== Screenshots ==

1. WordPress Login protected by Cloudflare Turnstile (success state).
2. Contact Elementor form protected by Turnstile - widget shown before submit.
3. WooCommerce “My Account” → Register form with Turnstile verification.
4. Security settings: whitelist logged-in users, IPs and user agents; per-form WordPress toggles.
5. Quick setup: enter Cloudflare Turnstile Site & Secret keys; choose theme, widget size and appearance mode.

== Minimum Requirements ==

- WordPress 5.0+  
- PHP 7.0+  
- Cloudflare Turnstile site & secret keys (free)

== Installation ==

1. Install via **Plugins → Add New** (search “Kitgenix Turnstile”) or upload the ZIP.  
2. Activate the plugin.  
3. Go to **Settings → Cloudflare Turnstile**.  
4. Enter your **Site Key** and **Secret Key** from the Cloudflare dashboard.  
5. Enable the integrations/forms you want to protect and **Save**.

== Roadmap ==

- Per-form controls and UI refinements  
- More granular placement options for additional builders  
- Expanded compatibility notes for optimization plugins

== Changelog ==

= 1.0.7 (14 October 2025) =
* New: Added "Flexible (100% width)" widget size (Cloudflare Turnstile `data-size="flexible"`) for fully responsive, container-width layouts. (Thank You: @kammsw)
* New: Interaction Only UX refinement – collapses initial blank gap (no more 50+px empty space) until the user interacts or the widget needs to expand. (Thank You: @kammsw)
* Improvement: Unified size handling in JS (`flexible` passes straight through; existing custom sizes still map to Cloudflare equivalents).
* Improvement: Consistent collapsed/expand logic across Elementor, Gravity Forms, Formidable, Forminator, Jetpack, Fluent Forms, Kadence, WPForms, and core render paths.
* Improvement: CSS enhancements for flexible width + reduced gap state (`.kt-ts-collapsed`).
* Prep: Foundation laid for upcoming modal/delayed form robustness (MutationObserver structure ready for attribute watching & visibility checks in a future release).
* Dev: Sanitization now allows `flexible`; admin settings UI updated with help text.
* Note: If you previously forced width via custom CSS, review and remove redundant rules when using the new Flexible option.
* Safe Update: No database schema changes; only front-end assets and settings sanitization logic touched.

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

= 1.0.7 =
Adds Flexible (full-width) widget size and removes the large blank gap for Interaction Only mode. Update recommended for better UX. Clear/minify caches so new CSS/JS take effect.

== External Services ==

This plugin connects to Cloudflare Turnstile for spam prevention. It sends:
- Site key
- Response token
- User IP and user-agent (used by Cloudflare for verification)

No data is stored or processed by Kitgenix.

Cloudflare Turnstile Terms: https://developers.cloudflare.com/turnstile/  
Privacy Policy: https://www.cloudflare.com/privacypolicy/

== Trademark Notice ==

“Cloudflare” and the Cloudflare logo are trademarks of Cloudflare, Inc. This plugin is not affiliated with or endorsed by Cloudflare, Inc.

== Copyright ==

Kitgenix CAPTCHA for Cloudflare Turnstile is built with ❤️ by Kitgenix.

== Credits ==

Cloudflare Turnstile — https://www.cloudflare.com/products/turnstile/  
Built with ❤️ by https://kitgenix.com

== Support Development ==

Donate link: https://buymeacoffee.com/kitgenix
