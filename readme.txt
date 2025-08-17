=== Kitgenix CAPTCHA for Cloudflare Turnstile ===
Contributors: kitgenix, carlbensy16
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
Stable tag: 1.0.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: cloudflare, captcha, anti-spam, security, woocommerce

Add Cloudflare Turnstile to WordPress, WooCommerce, Elementor & top form plugins. Fast, privacy-first spam protection with server-side validation.

== Description ==

**Stop spam without punishing real users.** Kitgenix CAPTCHA for Cloudflare Turnstile integrates Cloudflare’s privacy-preserving CAPTCHA alternative with WordPress in a way that’s fast, stable, and production-ready. Protect WordPress login, registration, lost/reset password, and comments — plus WooCommerce checkout/auth flows and popular form builders — using **server-side validation**, **replay protection**, and **proxy-aware IP detection**. The plugin is engineered for performance (async scripts, conditional loading) and compliance (no cookies or tracking added by the plugin, GDPR-friendly).

Turnstile is a modern, low-friction, **reCAPTCHA-free** experience that keeps bots out while keeping conversions high. This plugin gives you a clean UI, sensible defaults, and per-integration controls so you can deploy protection confidently across your site.

## Highlights ##

- **Ultra-lightweight & fast** – WP 6.3+ Script API (`strategy=async`), avoids render-blocking, loads only where needed.
- **Privacy-first** – No cookies or tracking added by the plugin; GDPR-friendly data flow.
- **Rock-solid server-side validation** – Tokens verified via Cloudflare’s official `siteverify` endpoint.
- **Replay protection** – Recent tokens cached (hashed) to prevent re-use.
- **Cloudflare/Proxy-aware IP handling** – Honors CF/Proxy headers only from trusted proxies.
- **Seamless integrations** – WordPress Core, WooCommerce (Classic + Blocks), Elementor Pro, and major form plugins.
- **Smart UX** – Optional “disable submit until verified”, token freshness timers, inline error hints.
- **Production-ready admin** – Onboarding, Site Health test, JSON import/export, accessible UI.
- **Multisite aware** – Clean uninstall removes settings site-wide (and network-wide on Multisite).

## Supported Forms & Integrations (v1.0.4) ##

**WordPress Core:** Login, Registration, Lost/Reset Password, Comment Form  
**WooCommerce:** Checkout (Classic & Blocks / Store API), Login, Registration, Lost Password  
**Elementor Pro:** Forms & Popups (with dynamic re-render support)  
**Form Plugins:** WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Contact Form 7, Forminator, Jetpack Forms, Kadence Forms (Kadence Blocks)

> Enable/disable each integration and location in **Settings → Cloudflare Turnstile**.

## How It Works (Technical) ##

1. Enqueues `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit` using async strategy (WP 6.3+).  
2. Injects a widget into enabled forms; re-renders for dynamic loads (AJAX, popups, multi-step).  
3. Submissions are validated server-side via **`/v0/siteverify`** with your secret key and request IP (where appropriate).  
4. On failure (invalid/expired/reused token), submission is blocked with clear, customizable messaging.

---

## Quick Start ##

1. **Install & Activate** → **Plugins → Add New** → search “Kitgenix Turnstile”.  
2. **Add Keys** → **Settings → Cloudflare Turnstile** → paste **Site Key** & **Secret Key** from [Cloudflare Dashboard](https://dash.cloudflare.com/).  
3. **Choose Integrations** → toggle WordPress/WooCommerce/Form plugins and specific locations.  
4. **Save & Test** → try login/register/comments/checkout + your form pages.  
5. **Optional Hardening** → enable **Disable Submit Until Verified** and review **Tools → Site Health** hints.

---

## Full Setup Guide (Step by Step) ##

### A. Generate Cloudflare Turnstile Keys ###
1. In Cloudflare, create a **Turnstile** site for your domain.  
2. Copy the **Site Key** and **Secret Key**.

### B. Configure the Plugin in WordPress ###
1. Go to **Settings → Cloudflare Turnstile**.  
2. Paste **Site Key** and **Secret Key** and click **Save**.  
3. Under **Integrations**, toggle **WordPress**, **WooCommerce**, **Elementor Pro**, and/or your form plugins.  
4. For WordPress/WooCommerce, enable specific locations (e.g., **Login**, **Register**, **Checkout**, **Comments**).  
5. In **Display & Behavior**, choose **Theme** (`auto/light/dark`), **Appearance** (`always/interaction-only`), **Size** (`small/normal/large`), and **Language** (`auto` or locale).  
6. (Optional) **Disable Submit Until Verified** for high-risk flows like checkout.  
7. (Optional) **Defer Scripts** to suit your optimizer.  
8. (Optional) Configure **Access Controls** (whitelist logged-in users, IPs with exact/wildcard/CIDR, and user-agents).

---

## How-to Guides (Common Setups) ##

### 1) WordPress Core Forms ###
- **Enable**: **Settings → Cloudflare Turnstile → Integrations → WordPress**.  
- **Pick locations**: Login, Register, Lost/Reset Password, Comments.  
- **Test**: Open `/wp-login.php` and your comment form; verify the widget appears and blocks invalid tokens.  
- **Tip**: Avoid full-page caching for `wp-login.php` and admin/auth pages.

### 2) WooCommerce (Classic Checkout) ###
- **Enable**: **Integrations → WooCommerce** and toggle **Checkout**, **Login**, **Register**, **Lost Password**.  
- **Check placement**: Turnstile renders near the **Place order** area.  
- **Best practices**:  
  - Do **not** cache mini-cart/checkout fragments.  
  - If using page caching/CDN, exclude the checkout, cart, and account endpoints.  
  - Consider **Disable Submit Until Verified** to prevent premature submits.

### 3) WooCommerce (Blocks / Store API Checkout) ###
- **Enable**: Same as Classic; Store API requests are validated server-side.  
- **Header support**: Turnstile token can be provided via `X-Turnstile-Token` (handled automatically by the plugin/extension).  
- **Troubleshooting**: If your custom checkout injects requests, ensure the token is present and not stale (avoid long-lived cache on checkout).

### 4) Elementor Pro Forms (including Popups) ###
- **Enable**: **Integrations → Elementor Pro**.  
- **Usage**: Open your form in Elementor; the widget is placed before/after the submit area as needed.  
- **Popups & AJAX**: The plugin re-renders Turnstile on popup open and AJAX transitions.  
- **Tips**:  
  - Give forms **unique names/IDs** to avoid collisions.  
  - Don’t defer Elementor’s own JS in a way that stops its events from firing.  
  - If using strict optimizers, allowlist `challenges.cloudflare.com`.

### 5) Contact Form 7 ###
- **Enable**: **Integrations → Contact Form 7**.  
- **Behavior**: Automatically injects on all CF7 forms; no shortcode tag needed.  
- **AJAX errors**: The widget re-renders after failed submissions.  
- **Tip**: If a form uses heavy HTML caching, exclude that page or reduce TTL.

### 6) WPForms / Fluent Forms / Gravity Forms / Formidable / Forminator / Jetpack / Kadence Forms ###
- **Enable**: Toggle each in **Integrations**.  
- **Behavior**: Auto-inject across forms for that plugin.  
- **AJAX & Multi-step**: The widget resets and re-renders on step changes or validation errors.  
- **If a single form should not use Turnstile**: Disable the integration temporarily while editing, or create form/plugin-level conditions (varies by plugin).

---

## Performance Playbook (Speed & Core Web Vitals) ##

- **Async by default**: We use the Script API (`strategy=async`) on WP 6.3+.  
- **Conditional loading**: Scripts load only where the integration is active.  
- **Optimization plugins**:  
  - Do **not** inline or block the Turnstile script; allowlist `https://challenges.cloudflare.com`.  
  - Exclude **login**, **checkout**, and **account** pages from full-page caching.  
  - If you defer JS globally, ensure your form plugin’s events still fire.  
- **Resource hints**: We add **preconnect/dns-prefetch** for faster first paint.

---

## Security Hardening Tips ##

- **Replay protection**: Enabled by default; adjust TTL via `kitgenix_turnstile_replay_ttl`.  
- **Trusted proxies**: If behind Cloudflare/NGINX proxy, configure **Trusted Proxies** in settings so request IPs are accurate.  
- **Developer Mode (warn-only)**: On staging, log failures without blocking to diagnose issues safely.  
- **Whitelisting**: Use sparingly (logged-in/IP/UA). Overuse reduces protection.

---

## Troubleshooting ##

- **Widget not showing**  
  - Verify keys and that the integration/location is enabled.  
  - Make sure you’re not whitelisted (logged-in/IP/UA).  
  - Clear caches; allowlist `challenges.cloudflare.com`.  
  - Check console for third-party blockers.

- **“Please verify you are human”**  
  - Token expired/invalid: reduce cache TTL on form pages, avoid caching auth/checkout, ensure server can reach Cloudflare.

- **Elementor popups / AJAX**  
  - Ensure Elementor/Form plugin JS isn’t over-deferred; the plugin listens for their events to re-render.

- **WooCommerce checkout**  
  - Don’t cache fragments; confirm widget renders before clicking **Place order**.  
  - For custom checkouts, ensure token is attached or forwarded correctly.

---

## Frequently Asked Questions ##

= Do I need a Cloudflare account? =  
Yes. You only need a free Cloudflare account to generate a **Turnstile Site Key** and **Secret Key**.

= Does this support Elementor Free? =  
We officially support **Elementor Pro Forms**. A fallback injector helps on general Elementor forms (including popups), but **Pro Forms** is the target for reliability.

= Is this compatible with caching/optimization plugins? =  
Yes. Scripts are async/deferred and the widget re-renders after dynamic events. If your optimizer inlines/defers third-party scripts, ensure `challenges.cloudflare.com` isn’t blocked.

= Can I skip validation for certain users? =  
Yes — whitelist logged-in users, IPs (exact/wildcard/CIDR), or user agents.

= How is this different from Google reCAPTCHA? =  
Cloudflare Turnstile is a **privacy-first**, low-friction alternative that avoids user tracking while blocking bots.

= Which form plugins are supported? =  
WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Contact Form 7, Forminator, Jetpack Forms, Kadence Forms — plus Elementor Pro Forms.

= Can I change theme/size/language? =  
Yes — choose `auto/light/dark`, `small/normal/large`, `always/interaction-only`, and language (`auto` or locale code).

= Can I pin/self-host the Turnstile script? =  
Yes, via the `kitgenix_captcha_for_cloudflare_turnstile_script_url` filter.

= Does it work on Multisite? =  
Yes. Settings are per-site. Uninstall removes settings site-wide (and network-wide when run network-wide).

= Is the plugin GDPR compliant? =  
The plugin doesn’t store personal data. Cloudflare Turnstile processes IP and user-agent for verification. Consult legal counsel and Cloudflare’s docs for your use case.

---

## Developers ##

**Filters**  
- `kitgenix_captcha_for_cloudflare_turnstile_script_url( $url, $settings )` – Override the Turnstile script URL or add params.  
- `kitgenix_turnstile_freshness_ms` – Control token auto-reset interval (ms).  
- `kitgenix_turnstile_replay_ttl` – Adjust replay-protection cache duration (seconds).  
- `kitgenix_turnstile_is_whitelisted( $is_whitelisted, $context )` – Modify whitelist decisions programmatically.

**Server-side endpoint**  
- Validates via `https://challenges.cloudflare.com/turnstile/v0/siteverify`.

**Text domain**  
- `kitgenix-captcha-for-cloudflare-turnstile` (POT included).

---

== Screenshots ==

1. WordPress login page with a Turnstile widget enabled.  
2. Elementor Pro Form (including popup) with Turnstile active.  
3. WooCommerce registration/checkout protected by Turnstile.  
4. Whitelist and access control settings.  
5. General settings with onboarding and Site Health hints.

== Minimum Requirements ==

- WordPress 5.0+  
- PHP 7.0+  
- Cloudflare Turnstile site & secret keys (free)

== Installation ==

1. Install via **Plugins → Add New** (search “Kitgenix Turnstile”) or upload the ZIP.  
2. Activate the plugin.  
3. Go to **Settings → Cloudflare Turnstile**.  
4. Enter your **Site Key** and **Secret Key** from the [Cloudflare Dashboard](https://dash.cloudflare.com/).  
5. Enable the integrations/forms you want to protect and **Save**.

== Roadmap ==

- Additional per-form controls and UI refinements  
- More granular placement options for additional builders  
- Expanded compatibility notes per optimization plugin

== Changelog ==

= 1.0.4 =  
* Fix: Position Turnstile above the WooCommerce reviews submit button. Thank You @carlbensy16.
* Fix: Prevent Turnstile from rendering inline with the submit button on Gravity Forms. Thank You @carlbensy16.
* Fix: Add spacing so Turnstile no longer overlaps the WPForms submit button. Thank You @carlbensy16.

= 1.0.3 =
* Fix: "Save Settngs" button not working after a few attempts

= 1.0.2 =
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

= 1.0.1 =
* Fix: Center Cloudflare Turnstile on all `wp-login.php` variants (login, lost password, reset, register) and across wp-admin.
* Change: Overhauled includes/core/class-script-handler.php to use the modern Script API (async strategy on WP 6.3+, attribute helpers on 5.7–6.2) and eliminated raw <script> output.
* Dev: Public/admin assets now use filemtime() for cache-busting.
* Dev: Added filter `kitgenix_captcha_for_cloudflare_turnstile_script_url` for advanced control.
* Docs: Expanded readme and updated links.

= 1.0.0 =
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

= 1.0.4 =  
No major changes. Recommended for fixes and stability.

== Copyright ==
Kitgenix CAPTCHA for Cloudflare Turnstile is built with ❤️ by Kitgenix.

== Disclaimer ==
This plugin is not affiliated with or endorsed by Cloudflare, Inc. “Cloudflare” is a registered trademark of Cloudflare, Inc.

== Credits ==
Cloudflare Turnstile — https://www.cloudflare.com/products/turnstile/  
Plugin developed by https://kitgenix.com

== External Services ==
This plugin connects to Cloudflare Turnstile for spam prevention. It sends:
- Site key
- Response token
- User IP and user-agent (via the Cloudflare SDK)

No data is stored or processed by Kitgenix.

Cloudflare Turnstile Terms: https://developers.cloudflare.com/turnstile/  
Privacy Policy: https://www.cloudflare.com/privacypolicy/
