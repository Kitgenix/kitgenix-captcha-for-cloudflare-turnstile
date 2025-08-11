=== Kitgenix CAPTCHA for Cloudflare Turnstile ===
Contributors: kitgenix
Plugin URI: https://wordpress.org/plugins/kitgenix-captcha-for-cloudflare-turnstile
Author: Kitgenix
Author URI: https://kitgenix.com
Documentation URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation
Support URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/support
Feature Request URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/feature-request
Donate link: https://kitgenix.com/plugins/support-us/
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: cloudflare turnstile, captcha, spam protection, wordpress security, turnstile

Cloudflare Turnstile CAPTCHA for WordPress core forms, WooCommerce, Elementor (Pro), and major form plugins. Lightweight, privacy-first bot & spam protection with server‑side validation.

== Description ==

**Stop spam, not your users.** Kitgenix CAPTCHA for Cloudflare Turnstile integrates Cloudflare Turnstile with WordPress in a way that is fast, privacy‑friendly, and rock‑solid. It protects your login, registration, lost password and comments, plus WooCommerce checkout/auth forms and popular form builders — with server‑side validation, whitelisting, and smart script loading.

**Highlights**  
- **Ultra‑lightweight & fast** — Async script loading (WP 6.3+ `strategy=async`), minimal footprint, no render‑blocking.  
- **Privacy‑first** — No cookies or tracking added by the plugin; GDPR‑friendly.  
- **Server‑side validation** — Every supported integration verifies tokens via the official `siteverify` endpoint.  
- **Seamless integrations** — WordPress Core, WooCommerce, Elementor Pro, and top form plugins.  
- **Smart loading** — Only loads where enabled; optional submit‑button disable until verified.  
- **Production‑ready** — CSRF protection (nonces), onboarding screen, clear settings, and robust error messages.  
- **Multisite aware** — Clean uninstall removes settings site‑wide (and network‑wide when run on Multisite).

### Supported Integrations (v1.0.1)
- **WordPress Core:** Login, Registration, Lost Password, Comment Form  
- **WooCommerce:** Checkout, Login, Registration, Lost Password  
- **Elementor Pro:** Forms & Popups (fallback injector for Elementor forms generally)  
- **WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Contact Form 7, Forminator, Jetpack Forms, Kadence Forms (Kadence Blocks):** All forms

> Tip: You can enable/disable each integration and per‑form location in **Settings → Cloudflare Turnstile**.

### How it works
1. The plugin enqueues Cloudflare Turnstile’s script (`https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit`) with async strategy where supported.  
2. Widgets are injected into the selected forms and re‑rendered when forms are loaded dynamically (Elementor popups, AJAX form renders, etc.).  
3. On submit, a server‑side request is sent to Cloudflare’s **`siteverify`** endpoint (`/v0/siteverify`) with your secret key and the user’s response token (and remote IP).  
4. If verification fails, submission is blocked with a clear error message and (optionally) the submit button remains disabled.

### Performance & Compatibility
- **Async/Deferred Scripts:** Uses modern enqueue args (on WP 6.3+) to prevent render‑blocking.
- **Conditional Loading:** Scripts only load when a relevant integration is active on the page.  
- **Dynamic/AJAX Forms:** Listens for common events (e.g., Elementor popups, CF7 submit, Gravity Forms render, Forminator render, Kadence render) and re‑renders Turnstile as needed.  
- **Caching/CDNs:** Designed to work with page caching and optimization plugins.  
- **Core Web Vitals:** No measurable impact when configured normally.

### Security
- **CSRF Protection:** Nonce checks in settings save and form submissions where applicable.  
- **Server‑Side Validation:** All supported integrations verify tokens on your server.  
- **Whitelisting:** Bypass validation for logged‑in users, specific IPs, or user agents.  
- **Error Handling:** Customizable messages with safe fallbacks.

### Privacy
This plugin itself does **not** store personal data about your visitors. For each verification, Cloudflare Turnstile receives:  
- site key  
- the user’s response token  
- IP address and user‑agent (via the Turnstile API/SDK)  

**Kitgenix does not store or process this data.** See Cloudflare’s [Privacy Policy](https://www.cloudflare.com/privacypolicy/) and [Turnstile Terms](https://developers.cloudflare.com/turnstile/).

### Settings Overview
**General**
- **Site Key / Secret Key** — From your Cloudflare dashboard.  
- **Enable Integrations:** WordPress, WooCommerce, Elementor, and specific form plugins.  
- **Per‑Form Toggles (WordPress/WooCommerce):** `wp_login_form`, `wp_register_form`, `wp_lostpassword_form`, `wp_comments_form`, `wc_checkout_form`, `wc_login_form`, `wc_register_form`, `wc_lostpassword_form`.

**Display & Behavior**
- **Theme:** `auto`, `light`, `dark`  
- **Appearance:** `always`, `interaction-only`  
- **Widget Size:** `small`, `normal`, `large`  
- **Language:** `auto` or a locale code (adds `hl=` to the script URL)  
- **Disable Submit Until Verified:** Optional stricter mode  
- **Defer Scripts:** Additional deferral to suit your stack

**Access Controls**
- **Whitelist logged‑in users**  
- **Whitelist IPs** (newline‑separated)  
- **Whitelist User‑Agents** (newline‑separated)

**Messages**
- **Error message** (shown when verification fails)  
- **Extra/fallback message** (optional helper text)

### Developer Notes
- **Filter:** `kitgenix_captcha_for_cloudflare_turnstile_script_url` lets you override the Turnstile script URL (e.g., self‑hosted or custom params).  
  ```php
  add_filter( 'kitgenix_captcha_for_cloudflare_turnstile_script_url', function( $url, $settings ) {
      // Example: pin to a specific version or add custom query params
      return $url;
  }, 10, 2 );
  ```
- **Text Domain:** `kitgenix-captcha-for-cloudflare-turnstile` (POT file included).  
- **Server‑side endpoint:** Uses `https://challenges.cloudflare.com/turnstile/v0/siteverify`.  
- **Nonces:** Used throughout the admin UI and form handlers where appropriate.

### Multisite
- Works on single and Multisite networks.  
- Uninstalling will delete `kitgenix_captcha_for_cloudflare_turnstile_settings` for the site; running uninstall on the network removes settings across all sites.

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

== Frequently Asked Questions ==

= Do I need a Cloudflare account? =
Yes. You only need a free Cloudflare account to create a **Turnstile Site Key** and **Secret Key**. No other Cloudflare products are required.

= Does this support Elementor Free? =
Elementor **Pro** Forms are supported directly. A fallback injector attempts to render Turnstile for Elementor forms generally (including popups), but we officially target **Elementor Pro Forms** for reliability.

= Is this compatible with caching and optimization plugins? =
Yes. Scripts are async/deferred and the widget re‑renders after dynamic events. If your optimization tool inlines or defers third‑party scripts, ensure `challenges.cloudflare.com` isn’t blocked.

= Can I skip validation for certain users? =
Yes — you can whitelist logged‑in users, specific IP addresses, or user agents.

= The widget doesn’t show up — what should I check? =
- Verify your Site/Secret keys and that the relevant integration is enabled.  
- Clear page/server caches and any CDN.  
- Make sure you’re not whitelisting yourself (IP/UA/logged‑in).  
- Check the browser console for errors related to blocked third‑party scripts.  
- For AJAX/dynamic forms, ensure the form plugin’s JS is loaded and not deferred in a way that breaks events.

= Users see an error when submitting — what does it mean? =
It usually means token verification failed or expired. Ensure the page isn’t cached between render and submit, and that your server can reach `challenges.cloudflare.com`.

= How is this different from Google reCAPTCHA? =
Cloudflare Turnstile focuses on **privacy** and **low friction**, avoiding user tracking while still blocking bots.

= Which form plugins are supported? =
WPForms, Fluent Forms, Gravity Forms, Formidable Forms, Contact Form 7, Forminator, Jetpack Forms, and Kadence Forms (Kadence Blocks) — plus Elementor Pro Forms.

= Can I change the widget theme/size/language? =
Yes — choose theme (`auto`, `light`, `dark`), size (`small`, `normal`, `large`), appearance (`always`, `interaction-only`), and language (`auto` or locale code) in settings.

= Can I self‑host the Turnstile script or pin its version? =
Use the `kitgenix_captcha_for_cloudflare_turnstile_script_url` filter to override the default URL.

= Does it work on Multisite? =
Yes. Settings are per‑site. The uninstall routine removes plugin settings site‑wide (and across the network when run network‑wide).

= Is the plugin GDPR compliant? =
The plugin itself does not store personal data. Cloudflare Turnstile processes IP and user agent for verification. Consult your legal counsel and Cloudflare’s privacy docs for your use case.

== Screenshots ==
1. WordPress login page with Cloudflare Turnstile widget.  
2. Elementor Pro Form with Turnstile enabled (including popups).  
3. WooCommerce registration/checkout with Turnstile.  
4. Whitelist settings (IPs, user agents, logged‑in users).  
5. General settings and onboarding screen.

== Troubleshooting ==
- **Elementor (free) forms not showing Turnstile:** Use Elementor Pro Forms for guaranteed support; the fallback injector aims to help but may be influenced by third‑party optimizers.  
- **“Please verify you are human” / submission blocked:** Token expired or verification failed. Reduce cache TTL on the form page, avoid full HTML caching on checkout/login, and ensure the server can perform outbound requests.  
- **Turnstile blocked by browser extensions:** Ask users to disable aggressive script blockers for your domain.  
- **WooCommerce checkout issues:** Make sure mini‑cart/checkout fragments aren’t cached, and that Turnstile renders before submit.

== Roadmap ==
- Additional per‑form controls and UI refinements  
- More granular placement options for additional builders  
- Expanded compatibility notes per optimization plugin

== Changelog ==

= 1.0.1 =
* Fix: Center Cloudflare Turnstile on all `wp-login.php` variants (login, lost password, reset, register) and across wp-admin.
* Change: Overhauled includes/core/class-script-handler.php to use the modern Script API (async strategy on WP 6.3+, attribute helpers on 5.7–6.2) and eliminated raw <script> output.
* Dev: Public/admin assets now use filemtime() for cache-busting.
* Dev: Added filter `kitgenix_captcha_for_cloudflare_turnstile_script_url` for advanced control.
* Docs: Expanded readme and updated links.

= 1.0.0 =
Initial release
WordPress Login Integration
WordPress Registration Integration
WordPress Lost Password Integration
WordPress Comment Integration
WooCommerce Checkout Integration
WooCommerce Login Integration
WooCommerce Registration Integration
WooCommerce Lost Password Integration
Elementor Forms Integration
WPForms Integration
Kandence Forms Integration
Jetpack Forms Integration
Gravity Forms Integration
Forminator Forms Integration
Formidable Forms Integration
Fluent Forms Integration
Contact Form 7 Integration
Conditional Script Loading for Performance
Widget Size, Theme, and Appearance Options
Defer Scripts and Disable-Submit Logic
Whitelist by IP, User Agent, or Logged-in Users
Custom Error and Fallback Messages
Modern Admin UI with Onboarding Wizard
Optional Plugin Badge
Multisite Support
Works With Elementor Element Cache
GDPR-friendly, No Cookies or Tracking
Optimized for Caching, AJAX, and Dynamic Forms
No Impact on Core Web Vitals
Site Key & Secret Key Management
Per-Form and Per-Integration Enable/Disable
Language Selection for Widget
Customizable Widget Appearance
Server-Side Validation for All Supported Forms
CSRF Protection (Nonce Fields)
Error Handling and User Feedback
Support for AJAX and Dynamic Form Rendering
Admin Notices and Settings Errors
Plugin Translations/Localization

== Upgrade Notice ==

= 1.0.1 =
No Major Changes.

== Copyright ==
Kitgenix CAPTCHA for Cloudflare Turnstile is built with ❤️ by Kitgenix.

== Disclaimer ==
This plugin is not affiliated with or endorsed by Cloudflare, Inc. “Cloudflare” is a registered trademark of Cloudflare, Inc.

== Credits ==
Cloudflare Turnstile is a product of https://www.cloudflare.com/products/turnstile/  
Plugin developed by https://kitgenix.com

== External Services ==
This plugin connects to Cloudflare Turnstile for spam prevention. It sends:
- Site key
- Response token
- User IP and user‑agent (via the Cloudflare SDK)

No data is stored or processed by Kitgenix.

Cloudflare Turnstile Terms: https://developers.cloudflare.com/turnstile/  
Privacy Policy: https://www.cloudflare.com/privacypolicy/
