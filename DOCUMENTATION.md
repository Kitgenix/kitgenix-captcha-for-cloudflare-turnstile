# Kitgenix CAPTCHA for Cloudflare Turnstile

## Overview

**Kitgenix CAPTCHA for Cloudflare Turnstile** adds Cloudflare Turnstile CAPTCHA/anti-bot verification to WordPress core forms, WooCommerce, Easy Digital Downloads, Elementor, and a long list of third-party form, membership, community and newsletter plugins, with mandatory server-side verification against Cloudflare's `siteverify` endpoint.

Read from the main plugin file's docblock (`kitgenix-captcha-for-cloudflare-turnstile.php`, lines 2–22):

| Field | Value |
|---|---|
| Plugin Name | Kitgenix CAPTCHA for Cloudflare Turnstile |
| Plugin URI | https://wordpress.org/plugins/kitgenix-captcha-for-cloudflare-turnstile/ |
| Description | Add Cloudflare Turnstile CAPTCHA to WordPress, WooCommerce, Elementor, and popular form plugins with privacy-first server-side verification. |
| Version | 2.1.0 |
| Requires at least (WP) | 6.0 |
| Tested up to (WP) | 7.1 |
| Requires PHP | 8.1 |
| Author | Kitgenix |
| License | GPLv3 or later |
| Text Domain | `kitgenix-captcha-for-cloudflare-turnstile` |
| Domain Path | `/languages` |

Note: `readme.txt`'s `Stable tag` and `CHANGELOG.md`'s latest heading (2.1.0, 10 October 2026) are in sync with the plugin header's `Version` constant (2.1.0), which added the "Clear log" button and the 7/14/30-day log retention setting.

The plugin's activation hook additionally enforces a runtime PHP ≥ 8.1 / WP ≥ 5.0 floor (`kitgenix_captcha_for_cloudflare_turnstile_activate_plugin()`), which is looser on the WP side than the `Requires at least: 6.0` declared in the header.

No Kitgenix-hosted verification proxy is used: the only external service required for CAPTCHA functionality is Cloudflare Turnstile itself. The plugin also talks to `wordpress.org` (via core's `plugins_api()`) to populate the shared "Kitgenix Hub" admin page, and loads Google Fonts (Inter/Manrope) on Kitgenix admin screens — both documented in `readme.txt`'s "External Services" section.

## Architecture

### Folder / file layout

```
kitgenix-captcha-for-cloudflare-turnstile.php   Main bootstrap file (constants, activation/deactivation/uninstall hooks, Kitgenix Hub page, shared admin menu)
uninstall.php                                    Uninstall cleanup (options, transients, cron)
readme.txt                                       WordPress.org readme (FAQ, external services, changelog)
CHANGELOG.md                                     Human-maintained changelog (more granular than readme.txt's)
languages/kitgenix-captcha-for-cloudflare-turnstile.pot  Translation template (no .po/.mo shipped)

includes/core/
  class-turnstile-loader.php        Central bootstrap: loads core, admin (if is_admin()), and conditionally loads each integration
  class-turnstile-validator.php     Cloudflare siteverify client, replay protection, honeypot, dev/test mode, metrics, recent-event log, alerts
  class-script-handler.php          Enqueues public/admin/Turnstile assets, widget attribute helpers, per-integration overrides, duplicate-loader detection
  class-turnstile-shortcode.php     Registers [kitgenix_turnstile] shortcode + shortcode/marker detection helper
  class-client-ip.php               Trusted-proxy-aware client IP resolution
  class-cloudflare-health.php       Independent Cloudflare Turnstile reachability probe (outage failsafe)
  class-whitelist.php               Logged-in/IP/User-Agent whitelist evaluation
  class-setup-verification.php      Gates login-sensitive integrations behind a passed end-to-end Cloudflare verification test
  class-settings-overrides.php      wp-config.php constant / environment variable overrides for Site Key & Secret Key

includes/admin/
  class-admin-options.php           Registers the settings option + sanitizes the entire settings payload
  class-settings-ui.php             The full tabbed settings page (~2,945 lines): rendering, AJAX handlers, CSV export, sidebar
  class-settings-transfer.php       JSON export/import of settings (admin-post handlers)
  class-site-health.php             WP Site Health test + "Protection Health" state computation

includes/integrations/
  wordpress/class-wp-core.php               WP login/register/lost-password/reset-password/comments (+ wp_login_form())
  ecommerce/class-woocommerce.php           WooCommerce Classic + Blocks (Store API) checkout, login, register, lost password, reviews
  ecommerce/class-easy-digital-downloads.php EDD checkout, login, register, profile editor
  page-builder/class-elementor.php          Elementor Pro Forms + Elementor (free) fallback injection
  forms/contact-form-7.php                  Contact Form 7
  forms/wpforms.php                         WPForms
  forms/fluent-forms.php                    Fluent Forms
  forms/gravity-forms.php                   Gravity Forms
  forms/formidable-forms.php                Formidable Forms
  forms/forminator-forms.php                Forminator
  forms/jetpack-forms.php                   Jetpack Forms
  forms/kadence-forms.php                   Kadence Blocks "Form (Adv)"
  forms/jetformbuilder.php                  JetFormBuilder
  forms/ninja-forms.php                     Ninja Forms
  forums/bbpress.php                        bbPress topic/reply/forum forms
  forums/buddypress.php                     BuddyPress registration + activity post form
  community/class-wpdiscuz.php              wpDiscuz comment forms
  membership/class-memberpress.php          MemberPress checkout/signup
  membership/class-paid-memberships-pro.php Paid Memberships Pro checkout
  membership/class-ultimate-member.php      Ultimate Member login/register/password-reset
  newsletters/class-mailpoet.php            MailPoet subscription forms
  kitgenix/class-plugin-score.php           Kitgenix Plugin Score custom login/register/forgot-password pages

assets/js/
  public.js                    Frontend widget renderer + per-plugin render/reset glue (1,406 lines)
  elementor.js                 Elementor container-ensure bridge (does NOT call turnstile.render)
  admin.js                     Settings page behaviour (reveal/copy secret, setup verification AJAX, unsaved-changes bar, import preview)
  kitgenix-admin-tabs.js       Shared Kitgenix topbar/tabs/search controller (identical across Kitgenix plugins)
  kitgenix-admin-components.js Shared modal/collapsible/copy/table-search/table-filter/table-pagination/toast library

assets/css/
  public.css                   Front-end widget styling (light/dark tokens, RTL, reduced-motion, per-integration spacing)
  admin.css                    Plugin-specific additions on top of the shared admin UI stylesheet
  kitgenix-admin-ui.css        Shared Kitgenix design-system stylesheet (2,673 lines; identical across Kitgenix plugins)
  kitgenix-hub.css             Styling for the shared "Kitgenix" top-level hub page

assets/images/                 Banners, icons, screenshots, brand logos, social-media icons
```

### Bootstrap sequence

1. **File load** (`kitgenix-captcha-for-cloudflare-turnstile.php`, always executed on every request once the plugin is active):
   - Defines the shared Kitgenix top-level admin menu helper functions (`kitgenix_get_admin_menu_icon()`, `kitgenix_ensure_admin_menu()` — hooked to `admin_menu` priority 5) and the Kitgenix Hub renderer (`kitgenix_render_admin_page()`), used identically across all Kitgenix plugins so only one "Kitgenix" top-level menu item is ever created regardless of how many Kitgenix plugins are active.
   - Registers `kitgenix_captcha_for_cloudflare_turnstile_register_icons()` on `init` (WP 7.1+ Icon Registration API — `wp_get_icon('kitgenix/mark')`).
   - Registers admin-UI stylesheet + Kitgenix Hub asset enqueues (`admin_enqueue_scripts`).
   - Defines version/path/URL constants (guarded with `defined()` checks): `KitgenixCaptchaForCloudflareTurnstile_Version`, `KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_VERSION`, `_File`, `_Path`, `_URL`, `_Includes_Path`, `_Assets_URL`.
   - Declares WooCommerce HPOS (`custom_order_tables`) compatibility via `before_woocommerce_init`.
   - `require_once`s and immediately calls `::init()` on: `Turnstile_Loader`, `Setup_Verification`, `Settings_Overrides`, `Admin_Options`, `Settings_Transfer`, `Settings_UI` — note `Turnstile_Loader::init()` itself is deferred to `plugins_loaded` (see below), while the other five run their `init()` calls immediately at file-load time (each registers its own hooks, so this is safe before `plugins_loaded`).
   - Registers `plugins_loaded` → `kitgenix_captcha_for_cloudflare_turnstile_init_plugin()`, which calls `Turnstile_Loader::init()` (or shows an admin error notice if the class failed to load).
   - Registers activation hook (`register_activation_hook`) — enforces PHP ≥ 8.1 / WP ≥ 5.0, `deactivate_plugins()` + `wp_die()` if not met; otherwise sets a 30-second `kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect` transient.
   - Registers `admin_init` → one-time redirect to the plugin's settings page (skipped on bulk activation via `$_GET['activate-multi']`).
   - Registers deactivation hook — clears the activation-redirect transient and the `kitgenix_turnstile_prune_event_log` cron event.
   - Registers uninstall hook (`register_uninstall_hook`) that `include`s `uninstall.php` (though WordPress prefers `uninstall.php` directly when present, per its own docblock comment).
   - Adds a "Settings" link to the Plugins screen row via `plugin_action_links_{basename}`.

2. **`Turnstile_Loader::init()`** (on `plugins_loaded`):
   - `load_core()` — always runs: requires `class-client-ip.php`, `class-cloudflare-health.php`, `class-turnstile-validator.php`, `class-script-handler.php`, `class-turnstile-shortcode.php`, `class-whitelist.php`; calls `Script_Handler::init()` and `Whitelist::init()`; registers the `kitgenix_turnstile_prune_event_log` cron callback unconditionally (not gated on `is_admin()`) so the scheduled event has something to run even outside wp-admin.
   - `load_admin()` — only when `is_admin()`: requires `class-admin-options.php`, `class-settings-ui.php`, `class-site-health.php`; calls `Site_Health::init()` (the other two classes were already `init()`'d by the main file).
   - `load_integrations()` — reads the stored settings array once, then for each integration checks **both** the matching `enable_*` setting **and** presence of the third-party plugin (class/function/constant existence checks specific to each plugin) before `require_once`-ing and initializing that integration file. Integration files fall into two patterns: classes with an explicit static `::init()` call from the loader (WP Core, WooCommerce, EDD, MailPoet, Paid Memberships Pro, Ultimate Member, MemberPress, wpDiscuz, BuddyPress, bbPress, Kitgenix Plugin Score), and files that self-initialize at the bottom of the file with a bare `ClassName::init();` call once `require_once`d (Elementor, WPForms, Fluent Forms, Gravity Forms, Contact Form 7, Formidable Forms, Forminator, Jetpack Forms, Kadence Forms, JetFormBuilder, Ninja Forms).

### Autoloading / class-loading approach

No PSR-4 autoloader or Composer — every class file is `require_once`d explicitly (from the main file or from `Turnstile_Loader`), gated by conditional checks (`is_admin()`, `class_exists()`, `defined()`, setting flags) so unused integrations never load their PHP or register hooks. All classes live under the `KitgenixCaptchaForCloudflareTurnstile\` namespace root, with sub-namespaces `Core`, `Admin`, `Integrations\WordPress`, `Integrations\Ecommerce`, `Integrations\PageBuilder`, `Integrations\Forms`, `Integrations\Forums`, `Integrations\Community`, `Integrations\Membership`, `Integrations\Newsletters`, `Integrations\Kitgenix`.

### Key classes and responsibilities

| Class | Responsibility |
|---|---|
| `Core\Turnstile_Loader` | Central conditional bootstrap for core/admin/integrations |
| `Core\Turnstile_Validator` | The single canonical entry point every integration calls to validate a submission: nonce check, honeypot check, token extraction, Cloudflare `siteverify` call (memoized per-request), replay protection, dev-mode/test-mode warn-only logic, metrics + recent-event-log recording, error-code → message mapping, alert computation |
| `Core\Script_Handler` | All asset enqueuing (public/admin/Turnstile API script), widget `data-*` attribute builder, per-integration theme/size/language override resolution, honeypot field renderer, duplicate-Turnstile-loader detection |
| `Core\Cloudflare_Health` | Server-side, cached (2 min) probe of `https://challenges.cloudflare.com/turnstile/v0/api.js`; backs the opt-in "Cloudflare outage failsafe" |
| `Core\Client_IP` | Resolves the "real" client IP, trusting `CF-Connecting-IP`/`True-Client-IP`/`X-Forwarded-For`/`X-Real-IP` only when `REMOTE_ADDR` matches an admin-configured trusted-proxy IP/CIDR |
| `Core\Whitelist` | Per-request-cached decision: is this request exempt from Turnstile (logged-in bypass, IP match, User-Agent match)? |
| `Core\Setup_Verification` | Gates every "login-sensitive" integration (WP login/register/lost-password, WooCommerce/EDD account forms, membership plugins, Kitgenix Plugin Score) behind a real, admin-triggered Cloudflare verification test tied to a hash of the current Site Key + Secret Key pair |
| `Core\Settings_Overrides` | Transparently substitutes `site_key`/`secret_key` from wp-config.php constants or environment variables via `pre_option_*`/`pre_update_option_*` filters |
| `Core\Turnstile_Shortcode` | `[kitgenix_turnstile]` shortcode + `has_shortcode_in()` helper used by every "auto vs shortcode" integration to detect existing widget markup |
| `Admin\Admin_Options` | WordPress Settings API registration (`register_setting`) + the single `sanitize()` callback that cleans the entire settings array on every save |
| `Admin\Settings_UI` | The entire tabbed settings screen, AJAX handlers, CSV export, sidebar |
| `Admin\Settings_Transfer` | JSON export/import (`admin-post.php` actions) |
| `Admin\Site_Health` | WP Site Health "Cloudflare Turnstile readiness" test + discrete Protection Health states used on the Log tab |

## Features

- **Server-side verification for every supported integration** — no integration trusts a client-side widget alone; each calls `Turnstile_Validator::is_valid_submission()` or `::validate_token()`.
- **Honeypot fallback field** (`Settings → Security → Enable Honeypot Fallback Field`) — a CSS-hidden, `tabindex="-1"` text field rendered alongside every widget. A non-empty value short-circuits validation before Cloudflare is even contacted, recorded as error code `honeypot_tripped`.
- **Replay protection** — SHA-256 hash of `token|wp_salt('auth')` stored as a transient for a filterable TTL (`kitgenix_turnstile_replay_ttl`, default 10 minutes); a repeated token is rejected with `replay_detected`. Checkout-style flows pass `$check_replay = false` and instead mark the token used only after the order/purchase/membership is actually created (`mark_submission_token_used()` / `mark_token_used()`), so a payment retry doesn't get rejected as a replay.
- **Cloudflare outage failsafe** (`Settings → Advanced → Reliability`, off by default) — when enabled *and* `Cloudflare_Health::is_down()` (cached probe) reports Cloudflare unreachable, every protected form is allowed through without a token (`allow_via_failsafe()`), logged distinctly with error code `cloudflare_failsafe_bypass` so it is never confused with a genuine pass. A visitor cannot trigger this themselves — it is entirely server-side and probe-driven.
- **Developer Mode (warn-only)** (`Settings → Advanced → Developer Mode`) — site-wide switch that turns every validation failure into a logged-but-allowed pass.
- **Test Mode per Integration** — a multi-select of the same warn-only behaviour scoped to individual integration keys (`test_mode_integrations` setting), so one integration can be trialled without disabling enforcement everywhere.
- **Whitelisting** — logged-in users, IP addresses/ranges (exact, `*` wildcard, CIDR v4/v6), and User-Agent strings (substring or `*` wildcard). Whitelisted requests skip both the frontend Turnstile script enqueue and every integration's validation.
- **Trusted-proxy-aware client IP** — `CF-Connecting-IP`, `True-Client-IP`, `X-Forwarded-For`, `X-Real-IP` are honoured only when `REMOTE_ADDR` matches a configured trusted proxy IP or CIDR; header values must additionally be valid public (non-private/non-reserved) addresses.
- **Setup verification gate** — until an admin runs the test widget on the Site Keys tab and the resulting server-side check succeeds for the *current* Site Key + Secret Key pair (tracked by a SHA-256 hash), every "auth-sensitive" setting (`wp_login_form`, `wp_register_form`, `wp_lostpassword_form`, WooCommerce/EDD account-form settings, membership-plugin enable flags, Kitgenix Plugin Score form settings) is forced to `0` at read time via `Setup_Verification::mask_runtime_settings()` on the frontend (admin screens are exempt so the admin can still see/edit the real stored values).
- **Per-integration widget overrides** — Theme/Widget Size/Language can be overridden per integration (`theme_override_{key}`, `size_override_{key}`, `language_override_{key}`) instead of following the global Display settings.
- **Auto-inject vs Shortcode-only modes** — most integrations support `mode_{integration}` = `auto` (PHP auto-injects the widget) or `shortcode` (admin must place `[kitgenix_turnstile]` manually); validation is always registered regardless of mode.
- **Diagnostics** — a capped (50-entry), privacy-safe recent event log (`kitgenix_turnstile_recent_event_log` option), auto-pruned by age (configurable 7/14/30-day retention, default 30) both on every write and via a daily cron job, plus a manual "Clear log" action. Aggregate pass/fail/retry metrics are kept per-integration (`kitgenix_captcha_for_cloudflare_turnstile_metrics` option). Neither store contains raw IPs, request URIs, or the raw Turnstile token — only a SHA-256 hash is ever used internally for replay-dedup keys.
- **Operational alerts** — computed live from the recent event log: a "blocked-siteverify-requests" alert (HTTP errors reaching Cloudflare) and a "verification-failure-spike" alert (genuine — not retry-classified — failures exceeding a rate/count threshold within a rolling window), both filterable via `kitgenix_turnstile_alert_*` filters, surfaced in wp-admin notices, the Log tab, and WP Site Health.
- **Duplicate Turnstile loader detection** — `Script_Handler::detect_duplicate_loader()` inspects `wp_scripts()->registered` on `wp_print_scripts` (frontend only) for any other enqueued script whose `src` contains `challenges.cloudflare.com/turnstile/v0/api.js`, stores the match in a 12-hour transient, and surfaces a dismissible admin notice + Site Health warning.
- **Settings export/import (Portability tab)** — JSON export (Site Key/Secret Key excluded by default, opt-in inclusion checkbox) and import in Replace or Merge mode, with a client-side (never-submitted) preview of what the uploaded JSON contains before the admin commits it.
- **Multisite support** — `uninstall.php` iterates every site via `get_sites()` + `switch_to_blog()` to remove per-site options and transients in addition to network-wide cleanup.
- **CSV export** — Integration analytics and the recent diagnostic log can each be downloaded as CSV from the Log tab (`admin-post.php?action=kitgenix_turnstile_export_analytics`).

## Admin Interface

### Menu structure

- A shared top-level **Kitgenix** menu (slug `kitgenix`, capability `manage_woocommerce` when WooCommerce is active and the admin lacks it falls back to `manage_options`, otherwise `manage_options`) is registered once by `kitgenix_ensure_admin_menu()` (guarded against double-registration via `$admin_page_hooks['kitgenix']`) and shared across all installed Kitgenix plugins. Its page (`kitgenix_render_admin_page()`) is a plugin-discovery "Hub" grid listing Kitgenix's plugin catalogue (Turnstile, Custom Tabs for WooCommerce, Document Manager, Order Tracking, PDF Invoicing, Stock Sync, MultiStore, Image Optimizer, Affiliate Link Manager) with Install/Activate/Open/Details/Review/Support Forum actions per card, backed by cached (`DAY_IN_SECONDS` transients) `plugins_api()` lookups for active-install counts, ratings, and banner/icon media.
- A submenu page **Cloudflare Turnstile** (slug `kitgenix-captcha-for-cloudflare-turnstile`, capability `manage_options`) registered by `Settings_UI::register_menu()` via `add_submenu_page('kitgenix', ...)`.

### Settings page tabs

The settings page (`Settings_UI::render_page()`) uses a single `<form method="post" action="options.php">` wrapping the Site Keys, Display, Advanced (Developer Mode + Reliability), Security, and Integrations tab panels (all stored under one option via the WordPress Settings API), plus three panels **outside** that form that use their own `admin-post.php` actions: Log, Support, and Portability.

#### Site Keys tab

| Field | Option key | Notes |
|---|---|---|
| Site Key | `site_key` | Text input; read-only when overridden by constant/env var |
| Secret Key | `secret_key` | Password input, never pre-filled in HTML; "Reveal secret key" fetches it via AJAX on demand; "Clear saved secret" checkbox (`secret_key_clear`); `secret_key_present` hidden flag preserves the stored secret when the field is left blank |
| Test Cloudflare Turnstile Response | — | Renders a live Turnstile test widget; on solve, POSTs the token to the `kitgenix_turnstile_verify_setup` AJAX action, which calls `Turnstile_Validator::perform_setup_check()` and persists the result via `Setup_Verification::store_result()` |
| Managed Key Overrides | — | Informational card listing the supported constant/env var names |

#### Display tab

| Field | Option key | Values / default |
|---|---|---|
| Theme | `theme` | `auto` (default) / `light` / `dark` |
| Widget Size | `widget_size` | `normal` (default) / `compact` (accepts legacy `small`) / `flexible` |
| Appearance | `appearance` | `always` (default) / `interaction-only` |
| Language | `language` | free text validated against `Script_Handler::get_allowed_languages()`; default `auto` |
| Disable Submit Button | `disable_submit` | checkbox, default off |
| Custom Error Message | `error_message` | free text, overrides all mapped error messages when non-empty |
| Extra Failure Message | `extra_message` | free text appended to the frontend config, surfaced to public.js |
| Per-Integration Widget Overrides | `theme_override_{key}`, `size_override_{key}`, `language_override_{key}` | one row per key from `Script_Handler::get_override_integration_keys()`; empty = inherit global |

#### Integrations tab

Master `enable_{integration}` toggle per plugin, each with a card only rendered when that plugin is detected active (mirroring `Turnstile_Loader`'s own presence checks), containing: per-form checkboxes where applicable (see the [Form Integrations](#form-integrations) table below for every checkbox and hook), and — for most — an Auto-inject/Shortcode-only radio (`mode_{integration}`). WordPress Core, WooCommerce (Classic + Blocks separately), Easy Digital Downloads, Elementor, and every form-builder plugin listed under Form Integrations each get their own card.

#### Security tab

| Field | Option key |
|---|---|
| Enable Replay Protection | `replay_protection` (default on for fresh installs) |
| Enable Honeypot Fallback Field | `honeypot_enabled` |
| Skip for Logged-in Users | `whitelist_loggedin` |
| IP Address Whitelist | `whitelist_ips` (textarea, one pattern per line) |
| User Agent Whitelist | `whitelist_user_agents` (textarea) |
| Trust Cloudflare/Proxy Headers | `trust_proxy` |
| Trusted Proxy IPs | `trusted_proxies` (textarea, IPs/CIDRs) |

#### Advanced tab (via the "Advanced" dropdown)

| Field | Option key |
|---|---|
| Development Mode (Warn-only) | `dev_mode_warn_only` |
| Test Mode (per integration) | `test_mode_integrations` (multi-select against `Turnstile_Validator::get_all_integration_keys()`) |
| Cloudflare outage failsafe | `cf_failsafe_enabled` |

#### Log tab

Not part of the settings form — reads live data via `Turnstile_Validator::get_metrics_snapshot()`, `::get_integration_analytics()`, `::get_recent_event_log()`, `Site_Health::get_protection_states()`, and `Settings_UI::get_operational_alerts()`. Renders: a Protection Health state list, Active protection alerts, five "Your site impact" stat tiles (checks/passed/failed/retries/friction-rate), a searchable/paginated Integration Health Matrix table, and a searchable/filterable/paginated Recent Diagnostic Log table with a category legend. Includes: "Clear log" (`admin-post.php?action=kitgenix_turnstile_clear_event_log`), an auto-delete retention `<select>` (7/14/30 days, `admin-post.php?action=kitgenix_turnstile_save_log_retention`), "Copy recent log" (client-side clipboard), and two CSV export links.

#### Support tab

Donate/marketing content (PayPal donate link, documentation/support links, Facebook group, social links) — no settings stored here.

#### Portability tab

Export form (`admin-post.php?action=kitgenix_turnstile_export_settings`, checkbox to include keys) and Import form (`admin-post.php?action=kitgenix_turnstile_import_settings`, file upload + Replace/Merge radio), both nonce-protected, handled by `Admin\Settings_Transfer`.

### Sidebar

Rendered on every tab via `Settings_UI::render_sidebar()`: "Need Help?" (documentation + support links), "Visit Our Official Facebook Group", and a "Follow Us" social-icon grid.

### Option storage

All of the above (except `kitgenix_turnstile_log_retention_days` and a handful of diagnostic/transient options — see [Data Storage](#data-storage)) is stored as **one** serialized array option: `kitgenix_captcha_for_cloudflare_turnstile_settings`, registered via `register_setting('kitgenix_captcha_for_cloudflare_turnstile_settings_group', ...)` with `Admin_Options::sanitize()` as its `sanitize_callback`.

## Turnstile Integration

### Client-side widget rendering

Every integration echoes the same three-part markup (built by shared helpers, never hand-rolled per integration):

1. A WordPress nonce field: `wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce')` (Ninja Forms and WooCommerce Blocks are exceptions — see their sections below, since they don't submit as classic forms).
2. A hidden `<input type="hidden" name="cf-turnstile-response" value="" />` that JS populates once solved.
3. A container `<div class="cf-turnstile" data-sitekey="..." data-theme="..." data-size="..." data-appearance="..." [data-language="..."]></div>`, built by `Script_Handler::get_widget_data_attributes($integrationKey, $siteKey)`, which resolves theme/size/language per-integration overrides (falling back to the global Display settings) via `get_effective_theme()`, `get_effective_size()`, `get_effective_appearance()`, `get_effective_language_override()`.

The container is deliberately **never** rendered with `turnstile.render()` in PHP — every integration only emits the placeholder `<div>`; actual rendering happens centrally in `assets/js/public.js`'s `KitgenixCaptchaForCloudflareTurnstile.renderWidgets()` (and its per-plugin variants: `renderElementorWidgets()`, `renderGravityFormsWidgets()`, `renderFormidableFormsWidgets()`, `renderForminatorWidgets()`, `renderJetpackFormsWidgets()`, `renderKadenceFormsWidgets()` — the last defined conditionally only if Kadence is enabled), which:

- Calls `turnstile.render(el, params)` with `sitekey`, `theme`, `size` (mapped through `sizeMap` to Cloudflare's `compact`/`normal`/`flexible`), `appearance`, optional `language`, and `callback`/`expired-callback`/`error-callback`/`unsupported-callback`/`timeout-callback` handlers.
- Always calls `turnstile.remove(el)` (`_safeRemoveWidget()`) before re-rendering into a container, to avoid Cloudflare's "Cannot find Widget" warnings from a stale registered widget ID (fixed in 2.0.2).
- Writes the solved token into the nearest `input[name="cf-turnstile-response"]` (`setResponseInput()`), and — for MailPoet forms specifically — also into `input[name="data[cf-turnstile-response]"]`.
- Optionally disables the submit button until a token is present (`disable_submit` setting), unless `appearance === 'interaction-only'`, in which case the submit stays enabled and is only disabled if a visible challenge actually becomes necessary (unsupported/timeout/error callbacks).
- Auto-resets the widget after an idle period with no token, or after a token-age period once solved, both governed by the filterable `kitgenix_turnstile_freshness_ms` (default 150000ms / 2.5 minutes).
- Watches the DOM via `MutationObserver` for dynamically added `.cf-turnstile` containers (AJAX-rendered forms, popups) and re-initializes.
- Exposes `window.KitgenixCaptchaForCloudflareTurnstile` globally, with `getLastToken()` used by the WooCommerce Blocks `fetch()`-patching bridge (see below) to attach the token to Store API requests without a DOM lookup.

The Cloudflare script itself is loaded from `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=kitgenix_captcha_for_cloudflare_turnstile_TurnstileOnLoad` (with `&hl={language}` appended when a non-`auto` language is set), registered with **no version query string** (`$ver = null`) deliberately — appending `?ver=` to Cloudflare's own CDN URL previously triggered a console warning from Turnstile's own script about an unrecognized parameter (fixed in 2.0.2). It loads with `strategy: 'async'` on WP ≥ 6.3, or `wp_script_add_data($handle, 'async', true)` on WP 5.7–6.2. `wp_resource_hints` is filtered to add a `preconnect`/`dns-prefetch` hint for `https://challenges.cloudflare.com`.

### Server-side verification

The single verification entry point is `Turnstile_Validator::verify_with_site(string $token): bool` (private, called internally by `is_valid_submission()` and `validate_token()`), memoized per-request per-token (`self::$verified_tokens`):

- **Endpoint**: `POST https://challenges.cloudflare.com/turnstile/v0/siteverify` (filterable via `kitgenix_turnstile_siteverify_url`), called through `wp_remote_post()`.
- **Request body**: `secret` (the configured/overridden Secret Key), `response` (the token), and `remoteip` (the resolved client IP from `Client_IP::get()`, filterable via `kitgenix_turnstile_remote_ip`) — `remoteip` is omitted entirely when the `kitgenix_turnstile_send_remoteip` filter returns `false`.
- **Request args**: `timeout` (filterable `kitgenix_turnstile_siteverify_timeout`, default 10s), `headers` (`User-Agent: kitgenix-captcha-for-cloudflare-turnstile/{version}`, `Accept: application/json`), `sslverify` (filterable `kitgenify_turnstile_siteverify_sslverify` — sic, actually `kitgenix_turnstile_siteverify_sslverify`, default `true`), all assembled into an `$args` array further filterable wholesale via `kitgenix_turnstile_siteverify_http_args`.
- **Response handling**: on `is_wp_error()` or no response, records `http_error` and returns `false`. Otherwise JSON-decodes the body; `success` (bool) determines the return value; `error-codes` (array) is captured for diagnostics/messages.
- **Latency**: round-trip time in milliseconds is measured (`microtime(true)` before/after) and stored as `self::$last_latency_ms`, surfaced in the recent event log and CSV exports, reset to `0` at the start of every public call so `0` reliably means "no siteverify call was made this request" (honeypot/nonce/token-missing short-circuits).

`Turnstile_Validator::is_valid_submission(bool $require_nonce = true, string $integration = 'general', bool $check_replay = true): bool` is the primary integration entry point (form-based flows with a nonce field). Its order of checks:

1. `Cloudflare_Health::failsafe_active()` — if the outage failsafe is enabled and Cloudflare is confirmed down, allow through immediately (`allow_via_failsafe()`).
2. Honeypot check (`is_honeypot_tripped()`) — before Cloudflare is contacted at all.
3. Nonce check (`wp_verify_nonce($nonce, 'kitgenix_captcha_for_cloudflare_turnstile_action')`) when `$require_nonce`.
4. Token extraction (`get_token_from_request()`) — prefers `$_SERVER['HTTP_X_TURNSTILE_TOKEN']`, then `$_POST['cf-turnstile-response']` (re-verifying the plugin nonce if present), then the `kitgenix_turnstile_token_from_request` filter.
5. `verify_with_site($token)`.
6. Replay check (`is_replay()`) when `$check_replay` is true; otherwise the token is left unmarked for the caller to mark used later.
7. On any failure step, returns `dev_mode_enabled($integration)` (true only if site-wide Developer Mode or that integration's Test Mode is on) instead of hard `false` — meaning the *effective* return value can be `true` even though validation genuinely failed, all failures/passes are still recorded either way.

`Turnstile_Validator::validate_token(string $token, string $integration = 'general', bool $check_replay = true): bool` is the header/JSON-payload variant used by integrations that don't submit classic `$_POST` fields (Ninja Forms via `X-Turnstile-Token` header, WooCommerce Blocks Store API, MailPoet, Elementor's token-agnostic paths) — same failsafe/honeypot/verify/replay/dev-mode flow, minus the nonce step.

### Error handling

Cloudflare's documented `error-codes` (and several plugin-internal markers) are mapped to friendly, localized, filterable messages by `message_from_error_codes()` (first matching code wins) and exposed via `Turnstile_Validator::get_error_message(string $context = '')`, which additionally:
- Special-cases `replay_detected` with its own filterable message (`kitgenix_turnstile_replay_message`).
- Applies the admin's `error_message` setting override when non-empty.
- Applies a context-specific filter `kitgenix_captcha_for_cloudflare_turnstile_{context}_turnstile_error_message` (e.g. `..._wp_core_turnstile_error_message`, `..._woocommerce_turnstile_error_message`) so each integration's displayed message can be customized independently.

Every failure and success is additionally classified into a human-readable `{category, note}` pair by `get_event_category_and_note()` for the Log tab (categories: `passed`, `honeypot-blocked`, `cached-or-expired-page`, `widget-not-loaded`, `token-rejected`, `token-expired`, `replay-blocked`, `config-error`, `network-error`, `cloudflare-error`, `bad-request`, `blocked` (fallback)).

### Configuration options affecting widget rendering

Theme (`auto`/`light`/`dark`), Widget Size (`normal`/`compact`/`flexible` — legacy `small` maps to `compact`), Appearance (`always`/`interaction-only`), Language (30 total options, `auto` plus 29 Turnstile-supported language codes) — each settable globally under Display and overridable per integration under "Per-Integration Widget Overrides".

## Form Integrations

Every integration below follows the same shape: render the widget (auto-inject unless "Shortcode only" mode is selected), and call `Turnstile_Validator::is_valid_submission()`/`::validate_token()` on the plugin's own real submit/validation hook. "Auto vs Shortcode" only ever controls *placement*; validation is registered unconditionally in every case reviewed.

| Integration | Enable option(s) | Render hook(s) | Validation hook(s) | Notes |
|---|---|---|---|---|
| **WordPress Core** — Login | `enable_wordpress`, `wp_login_form` | `login_form`, `login_form_middle` filter (for `wp_login_form()` custom screens) | `authenticate` filter (priority 30) | Skips XML-RPC/REST auth requests and empty-username/password pre-fails; `kitgenix_turnstile_skip_wp_login_validation` filter available |
| **WordPress Core** — Register | `wp_register_form` | `register_form` | `registration_errors` filter | |
| **WordPress Core** — Lost/Reset Password | `wp_lostpassword_form` | `lostpassword_form`, `resetpass_form` | `lostpassword_post`, `validate_password_reset` | Skips when an admin is resetting another user's password from wp-admin |
| **WordPress Core** — Comments | `wp_comments_form` | `comment_form_submit_field` filter | `preprocess_comment` filter | Shares the comment system with WooCommerce product reviews and wpDiscuz; routing decided by `should_handle_comment_form()` + the `kitgenix_turnstile_handle_comment_form` filter; defers entirely to wpDiscuz when that integration is active |
| **WooCommerce** — Classic Checkout | `enable_woocommerce`, `wc_checkout_form` | `woocommerce_review_order_before_submit` | `woocommerce_checkout_process`, `woocommerce_after_checkout_validation` (once per request, replay check skipped; token marked used on `woocommerce_thankyou`) | |
| **WooCommerce** — Blocks Checkout | `mode_woocommerce_blocks` | `render_block_woocommerce/checkout-actions-block` filter (regex-inserted before the Place Order button) | `rest_request_before_callbacks` filter, scoped to POST `/wc/store/*checkout*` routes | Token via `X-Turnstile-Token` header or `extensions.kitgenix_captcha_for_cloudflare_turnstile_turnstile.token`; verified marker written to order meta `_kitgenix_turnstile_verified` via `WC_Order::update_meta_data()`+`save()` (HPOS-safe) on `woocommerce_store_api_checkout_update_order_from_request` |
| **WooCommerce** — Login | `wc_login_form` | `woocommerce_login_form` | `woocommerce_process_login_errors` filter + legacy `woocommerce_login_errors` filter (memoized once per request) | |
| **WooCommerce** — Registration | `wc_register_form` | `woocommerce_register_form` | `woocommerce_registration_errors` filter | Replaced the broken `woocommerce_register_post`+`wc_add_notice()` pattern which never actually blocked account creation |
| **WooCommerce** — Lost/Reset Password | `wc_lostpassword_form` | `woocommerce_lostpassword_form`, `woocommerce_resetpassword_form`, `woocommerce_reset_password_form` | `woocommerce_reset_password_validation` (2nd step); `lostpassword_post` (1st step, only registered when WP Core's own lost-password setting is off, scoped via WooCommerce's `wc_reset_password` POST marker) | |
| **WooCommerce** — Product Reviews | `wc_reviews_form` | shared with WP Core comments (`comment_form_submit_field`) | shared with WP Core comments (`preprocess_comment`) | Separate toggle from blog comments; uses the `woocommerce` message context |
| **Easy Digital Downloads** — Checkout | `enable_edd`, `edd_checkout_form` | `edd_purchase_form_before_submit` | `edd_checkout_error_checks` (replay check skipped; marked used on `edd_complete_purchase`) | |
| **Easy Digital Downloads** — Login | `edd_login_form` | `edd_login_form` filter | `authenticate` filter (priority 30, scoped via `$_POST['edd_login_nonce']`) | Replaced the non-existent `edd_process_login_form` action |
| **Easy Digital Downloads** — Registration | `edd_register_form` | `edd_register_fields_after` | `edd_process_register_form` | |
| **Easy Digital Downloads** — Profile Editor | `edd_profile_form` | `edd_profile_editor_after_password_fields` | `edd_pre_update_user_profile` | |
| **Elementor Pro Forms** | `enable_elementor` | `elementor_pro/forms/render_form_after_fields` | `elementor_pro/forms/validation` | Also a `wp_footer` fallback injector (`fallback_inject_widget()`) for Elementor free forms lacking the Pro render hook |
| **Contact Form 7** | `enable_cf7` | `wpcf7_form_elements` filter | `wpcf7_validate` filter | Shortcode-only mode still registers a `wpcf7_form_elements` filter to run `do_shortcode()` (fixed a 2.0.0 bypass where shortcode-only submissions skipped validation entirely) |
| **WPForms** | `enable_wpforms` | `wpforms_display_after_fields`, `wpforms_display_submit_before` | `wpforms_process` (priority 9) | Shortcode-only mode additionally registers a `wpforms_display_fields` filter (`process_shortcodes_in_wpforms_fields`) to run `do_shortcode()` on field output |
| **Fluent Forms** | `enable_fluentforms` | `fluentform_rendering_field_data` filter + `fluentform/render_item_submit_button` fallback | `fluentform/validation_errors` filter | Replaced the non-existent `fluentform_submit_validation` hook |
| **Gravity Forms** | `enable_gravityforms` | `gform_submit_button` filter | `gform_validation` filter | |
| **Formidable Forms** | `enable_formidableforms` | `frm_submit_button_html` filter | `frm_validate_entry` action | |
| **Forminator** | `enable_forminator` | `forminator_render_form_submit_markup` filter | `forminator_custom_form_submit_errors` filter | |
| **Jetpack Forms** | `enable_jetpackforms` | `jetpack_contact_form_html` filter | `jetpack_contact_form_is_spam` filter (priority 9) | |
| **Kadence Forms** | `enable_kadenceforms` | `wp_footer` inline-script injector (no PHP render filter exists for the Advanced Form block) | `kadence_blocks_advanced_form_submission_reject` filter | Only the "Form (Adv)" block (`Kadence_Blocks_Advanced_Form_Block`) is protectable — the classic Kadence "Form" block has no pre-actions rejection hook at all |
| **JetFormBuilder** | `enable_jetformbuilder` | `jet-form-builder/after-start-form-row` (submit-row heuristic) | `jet-form-builder/form-handler/before-send`, throws `Jet_Form_Builder\Exceptions\Action_Exception` on failure | |
| **Ninja Forms** | `enable_ninjaforms` | `wp_footer` inline script + `MutationObserver` (Ninja Forms is fully client-rendered) | `ninja_forms_submit_data` filter; failure short-circuits via `wp_send_json()` directly (writing to `errors.form` is silently ignored by NF) | No nonce field is submitted (JSON blob transport); token instead sent via `X-Turnstile-Token` header through a patched `XMLHttpRequest.prototype.send` |
| **bbPress** | `enable_bbpress` | `bbp_theme_before_{topic,reply}_form_submit_{wrapper,button}`, `bbp_theme_before_forum_form_submit_wrapper` (forum only gets the wrapper hook, no button hook), `bbp_theme_after_{topic,reply,forum}_form` | `bbp_new_topic_pre_extras`, `bbp_new_reply_pre_extras`, `bbp_new_forum_pre_extras` | `bbp_new_forum_pre_insert` deliberately not used — it's a filter, not an action, and hooking it broke forum data |
| **BuddyPress** | `enable_buddypress` | `bp_before_registration_submit_buttons`, `bp_before_activity_post_form` | `bp_actions` (priority 1), scoped to the registration page via `bp_is_register_page()` | Redirects with `bp_core_add_message()`/`bp_core_redirect()` (or `wp_safe_redirect()` fallback) on failure rather than blocking inline |
| **wpDiscuz** | `enable_wpdiscuz` | `wpdiscuz_submit_button_before` | `wpdiscuz_before_comment_post` | WP Core comments defers to this integration when active, to avoid double handling |
| **MailPoet** | `enable_mailpoet` | `mailpoet_form_widget_post_process` filter | `mailpoet_subscription_before_subscribe` action, throws `MailPoet\UnexpectedValueException` on failure | No WP nonce is exposed on this hook; token read directly from `$_POST['data']['cf-turnstile-response']` or `$_POST['cf-turnstile-response']` |
| **MemberPress** | `enable_memberpress` | `mepr-checkout-before-submit` | `mepr-validate-signup` filter | |
| **Paid Memberships Pro** | `enable_paidmembershipspro` | `pmpro_checkout_before_submit_button` | `pmpro_registration_checks` filter (replay check skipped; marked used on `pmpro_after_change_membership_level`) | |
| **Ultimate Member** | `enable_ultimatemember` | `um_after_login_fields`, `um_after_register_fields`, `um_after_password_reset_fields` | `um_submit_form_errors_hook_login`, `um_submit_form_errors_hook__registration`, `um_reset_password_errors_hook` (priority 20, memoized once per request) | |
| **Kitgenix Plugin Score** | `enable_kitgenix_plugin_score`, `kgps_login_form`/`kgps_register_form`/`kgps_lostpassword_form` | Output-buffer injection (`ob_start()` on `template_redirect`) into the theme's rendered login/register/forgot-password page HTML — no template edits required | `template_redirect` (priority 5), intercepts POST before the page template runs; errors passed back via a short-lived transient + redirect | Entirely non-invasive to the third-party "Kitgenix Plugin Score" plugin's own code |

## Hooks Reference

### WordPress/WooCommerce/third-party hooks this plugin consumes

The tables above list every third-party action/filter each integration hooks into. Additional plugin-internal/WordPress-core hooks used outside the per-integration tables:

| Hook | Type | Used for |
|---|---|---|
| `admin_menu` (priority 5) | action | `kitgenix_ensure_admin_menu()` |
| `admin_menu` | action | `Settings_UI::register_menu()` |
| `init` | action | `kitgenix_captcha_for_cloudflare_turnstile_register_icons()`, `Turnstile_Shortcode::register_shortcode()` |
| `admin_enqueue_scripts` | action | Kitgenix admin-UI stylesheet registration, Kitgenix Hub assets, `Script_Handler::enqueue_admin_assets()`, `Settings_UI::enqueue_assets()` |
| `wp_enqueue_scripts` / `login_enqueue_scripts` | action | `Script_Handler::enqueue_public_assets()` |
| `wp_print_scripts` (priority 99) | action | `Script_Handler::detect_duplicate_loader()` |
| `login_enqueue_scripts` / `admin_enqueue_scripts` (priority 99) | action | Late centering CSS (`align_login_turnstile()`, `align_admin_turnstile()`) |
| `wp_resource_hints` | filter | Preconnect/dns-prefetch hint for Cloudflare's domain |
| `admin_notices` | action | Duplicate-loader notice, operational alerts, dev-mode notice, setup-verification notice, import/export result notices |
| `admin_init` | action | Activation-redirect handler, duplicate-notice dismissal, `Admin_Options::register_settings()`, `Settings_UI::maybe_schedule_log_pruning()` |
| `admin_post_{action}` | action | `kitgenix_turnstile_export_settings`, `kitgenix_turnstile_import_settings`, `kitgenix_turnstile_export_analytics`, `kitgenix_turnstile_clear_event_log`, `kitgenix_turnstile_save_log_retention` |
| `wp_ajax_kitgenix_turnstile_get_secret` | action | Reveal-secret AJAX |
| `wp_ajax_{Setup_Verification::AJAX_ACTION}` (`kitgenix_turnstile_verify_setup`) | action | Setup verification AJAX |
| `pre_option_kitgenix_captcha_for_cloudflare_turnstile_settings` | filter | `Settings_Overrides::filter_pre_option()` — merges constant/env overrides, applies `Setup_Verification::mask_runtime_settings()` |
| `pre_update_option_kitgenix_captcha_for_cloudflare_turnstile_settings` | filter | `Settings_Overrides::filter_pre_update()` — re-forces overridden key values on save |
| `before_woocommerce_init` | action | HPOS `custom_order_tables` compatibility declaration |
| `site_status_tests` | filter | `Site_Health::register_tests()` |
| `plugin_action_links_{basename}` | filter | Settings link on Plugins screen |
| `register_activation_hook` / `register_deactivation_hook` / `register_uninstall_hook` | — | Lifecycle |

### Custom actions/filters this plugin fires (for other developers)

| Hook | Type | Arguments | Purpose |
|---|---|---|---|
| `kitgenix_captcha_for_cloudflare_turnstile_script_url` | filter | `$url, $settings` | Alter the Cloudflare `api.js` URL before enqueue |
| `kitgenix_turnstile_freshness_ms` | filter | `$ms` (default 150000) | Idle/token-age auto-reset window |
| `kitgenix_turnstile_inline_style` | filter | `$style, $context` | Inline CSS on the widget container per context (login/register/comment/buddypress/bbpress/etc.) |
| `kitgenix_turnstile_siteverify_url` | filter | `$url` | Override the siteverify endpoint |
| `kitgenix_turnstile_siteverify_timeout` | filter | `$timeout` (default 10) | HTTP timeout for siteverify |
| `kitgenix_turnstile_siteverify_sslverify` | filter | `$verify` (default true) | SSL verification for siteverify |
| `kitgenix_turnstile_siteverify_http_args` | filter | `$args, $token` | Full `wp_remote_post()` args array |
| `kitgenix_turnstile_send_remoteip` | filter | `$send` (default true) | Whether to include `remoteip` in the siteverify body |
| `kitgenix_turnstile_remote_ip` | filter | `$ip` | Override the resolved client IP before sending |
| `kitgenix_turnstile_token_from_request` | filter | `''` | Last-resort token source when neither header nor POST field is present |
| `kitgenix_turnstile_error_codes` | filter | `$codes, $context, $response` | Adjust Cloudflare error codes before message mapping |
| `kitgenix_turnstile_error_message` | filter | `$message, $codes, $context, $response` | Post-process the mapped friendly message |
| `kitgenix_captcha_for_cloudflare_turnstile_{context}_turnstile_error_message` | filter | `$message` | Per-context (dynamic) error message override, e.g. `..._wp_core_turnstile_error_message` |
| `kitgenix_turnstile_replay_message` | filter | `$message, $context` | Replay-specific error message |
| `kitgenix_turnstile_replay_ttl` | filter | `$ttl` (default `10 * MINUTE_IN_SECONDS`) | Replay-protection transient TTL |
| `kitgenix_turnstile_skip_wp_login_validation` | filter | `$skip, $user` | Let developers bypass WP Core login validation entirely |
| `kitgenix_turnstile_is_whitelisted` | filter | `$result, ['reason','client_ip','user_agent']` | Final override of the whitelist decision |
| `kitgenix_turnstile_trust_headers` | filter | `$trust_flag, $settings` | Force/disable proxy-header trust |
| `kitgenix_turnstile_trusted_proxies` | filter | `$list, $settings` | Adjust the trusted-proxy IP/CIDR list |
| `kitgenix_turnstile_alert_window_seconds` | filter | `$seconds` (default 900) | Rolling window for alert computation |
| `kitgenix_turnstile_alert_failure_spike_min_failures` | filter | `$count` (default 5) | Minimum failures to trigger a failure-spike alert |
| `kitgenix_turnstile_alert_failure_spike_failure_rate` | filter | `$rate` (default 60.0) | Minimum failure-rate percentage to trigger the alert |
| `kitgenix_turnstile_alert_http_error_min_failures` | filter | `$count` (default 3) | Minimum HTTP errors to trigger the blocked-siteverify alert |
| `kitgenix_turnstile_dev_log` | action | `$what, $data` | Fires whenever `log_dev()` runs (dev-mode diagnostic logging) |
| `kitgenix_turnstile_handle_comment_form` | filter | `$should_handle, $post_id, $post_type, $commentdata, $args` | Override whether the WP Core comments integration should act on a given comment form |
| `kitgenix_turnstile_cf_status_ttl` | filter | `$ttl` (default `2 * MINUTE_IN_SECONDS`) | `Cloudflare_Health` probe cache duration |
| `kitgenix_turnstile_cf_status_probe_url` | filter | `$url` | URL used for the outage-failsafe reachability probe |
| `kitgenix_turnstile_cf_status_timeout` | filter | `$timeout` (default 5) | Probe HTTP timeout |
| `kitgenix_turnstile_cf_status_sslverify` | filter | `$verify` (default true) | Probe SSL verification |

## Shortcodes / Blocks / Widgets

- **Shortcode**: `[kitgenix_turnstile]` — registered on `init` by `Turnstile_Shortcode::register_shortcode()`. Renders the same nonce + hidden input + `.cf-turnstile[data-kitgenix-shortcode="1"]` markup used by every auto-inject integration, so it is interchangeable/detectable. Accepts arbitrary shortcode attributes, which are echoed verbatim as additional HTML attributes on the container `<div>` (`esc_attr()`-escaped). Returns an empty string when no Site Key is configured.
- No Gutenberg blocks are registered.
- No WordPress widgets (`WP_Widget`) are registered.
- No REST routes are registered (`register_rest_route()` is never called anywhere in the codebase) — WooCommerce Blocks validation instead hooks the generic `rest_request_before_callbacks` filter, scoped in PHP to matching routes.

## Data Storage

No custom database tables are created (no `dbDelta()`/`CREATE TABLE` anywhere in the codebase). All persistent state is WordPress options and transients.

### Options

| Option name | Autoload | Purpose |
|---|---|---|
| `kitgenix_captcha_for_cloudflare_turnstile_settings` | default (yes) — registered via `register_setting()` | The entire settings array (see [Admin Interface](#admin-interface) for every key) |
| `kitgenix_turnstile_last_verify` | `false` | Snapshot of the most recent siteverify outcome: `time`, `success`, `codes`, `latency_ms` |
| `kitgenix_captcha_for_cloudflare_turnstile_metrics` | `false` | Aggregate pass/fail/retry counters, both global and per-integration (`checks_total`, `checks_passed`, `checks_failed`, `checks_retries`, `last_checked`, `last_outcome`, `last_codes`) |
| `kitgenix_turnstile_recent_event_log` | `false` | Array of up to 50 recent events: `time`, `integration`, `outcome`, `codes`, `latency_ms` |
| `kitgenix_turnstile_log_retention_days` | default | Integer, one of `[7, 14, 30]`, default `30` |
| `kitgenix_turnstile_setup_verification` | `false` | `Setup_Verification`'s gate state: `verified`, `checked_at`, `message`, `codes`, `keys_hash` (SHA-256 of `site_key|secret_key|wp_salt('auth')`), `outbound_http_ok`, `secret_valid` |

### Transients

| Transient (prefix) | TTL | Purpose |
|---|---|---|
| `kitgenix_captcha_for_cloudflare_turnstile_ts_{hash}` | replay TTL (default 10 min, filterable) | One per accepted-and-not-yet-reused token, keyed by `sha256(token|wp_salt('auth'))` — replay-protection marker |
| `kitgenix_turnstile_duplicate_scripts` | 12 hours | Duplicate Turnstile API loader detection payload |
| `kitgenix_turnstile_cf_status` | 2 minutes (filterable) | `Cloudflare_Health`'s cached "up"/"down" probe result |
| `kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect` | 30 seconds | One-time post-activation redirect flag |
| `kitgenix_hub_wporg_active_installs_v1`, `kitgenix_hub_wporg_ratings_v1`, `kitgenix_hub_wporg_media_v1` | 1 day | Cached `plugins_api()` lookups for the Kitgenix Hub page |
| `kgps_turnstile_error_{md5(ip)}` | 60 seconds | Kitgenix Plugin Score integration's pending error-message handoff across a redirect |

### Postmeta / usermeta

- **WooCommerce order meta**: `_kitgenix_turnstile_verified` — a GMT timestamp string written by `WooCommerce::annotate_blocks_order()` on orders created through Checkout Blocks, exclusively via `WC_Order::update_meta_data()` + `save()` (HPOS-compatible, no direct `$wpdb`/postmeta queries).
- No usermeta keys are used anywhere in the codebase.

### Uninstall cleanup

`kitgenix_captcha_for_cloudflare_turnstile_remove_plugin_data()` (in `uninstall.php`) deletes every option above (both `delete_option()` and `delete_site_option()`), clears the `kitgenix_turnstile_prune_event_log` cron event, and deletes every transient matching the `kitgenix_captcha_for_cloudflare_turnstile_` and `kitgenix_turnstile_` prefixes (via a direct `$wpdb->get_col()` `SELECT` on `option_name LIKE`, then removed one-by-one through `delete_transient()`/`delete_option()` for proper cache invalidation — the direct query is explicitly justified in a code comment as the only way to discover dynamic replay-protection transient names). On multisite, this entire routine is additionally repeated per-site via `get_sites()` + `switch_to_blog()`/`restore_current_blog()`.

## AJAX / REST Endpoints

No REST API routes are registered. Three `admin-ajax.php` actions and five `admin-post.php` actions exist, all admin-only:

| Action | Type | Permission | Nonce | Handler | Purpose |
|---|---|---|---|---|---|
| `kitgenix_turnstile_get_secret` | `wp_ajax_` | `current_user_can('manage_options')`; refuses if secret is overridden by constant/env | `kitgenix_turnstile_reveal_secret` | `Settings_UI::ajax_get_secret()` | Returns the stored secret key JSON (`{secret_key}`) for the "Reveal" button, so it is never printed into page HTML by default |
| `kitgenix_turnstile_verify_setup` | `wp_ajax_` | `current_user_can('manage_options')` | `kitgenix_turnstile_verify_setup` | `Setup_Verification::ajax_verify_setup()` | Runs `Turnstile_Validator::perform_setup_check($token)` against a real token from the admin test widget and stores/returns the result |
| `kitgenix_turnstile_export_settings` | `admin_post_` | `current_user_can('manage_options')` | `kitgenix_turnstile_export_settings` | `Settings_Transfer::handle_export()` | Streams a JSON file download of stored settings (keys omitted unless `include_keys=1`) |
| `kitgenix_turnstile_import_settings` | `admin_post_` | `current_user_can('manage_options')` | `kitgenix_turnstile_import_settings` | `Settings_Transfer::handle_import()` | Validates/sanitizes an uploaded JSON file (≤2MB), merges or replaces settings, redirects with a result flag |
| `kitgenix_turnstile_export_analytics` | `admin_post_` | `current_user_can('manage_options')` | `kitgenix_turnstile_export_analytics` | `Settings_UI::handle_export_analytics()` | Streams a CSV of either integration analytics or the recent event log (`?export=integrations`\|`recent-log`) |
| `kitgenix_turnstile_clear_event_log` | `admin_post_` | `current_user_can('manage_options')` | `kitgenix_turnstile_clear_event_log` | `Settings_UI::handle_clear_event_log()` | Deletes the `kitgenix_turnstile_recent_event_log` option entirely |
| `kitgenix_turnstile_save_log_retention` | `admin_post_` | `current_user_can('manage_options')` | `kitgenix_turnstile_save_log_retention` | `Settings_UI::handle_save_log_retention()` | Saves the log auto-delete window (clamped to 7/14/30) |

Additionally, `Script_Handler::handle_dup_notice_dismiss()` (hooked on `admin_init`, keyed off `$_GET['kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe']`) is a nonce-protected (`kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss`), `manage_options`-gated GET action that clears the duplicate-loader transient and redirects.

## Cron / Scheduled Events

| Hook | Schedule | Registered by | Callback |
|---|---|---|---|
| `kitgenix_turnstile_prune_event_log` | `daily` (via `wp_schedule_event(time(), 'daily', ...)`, guarded by `wp_next_scheduled()`) | `Settings_UI::maybe_schedule_log_pruning()` (on `admin_init`) | `[Turnstile_Validator::class, 'prune_event_log']` — registered unconditionally in `Turnstile_Loader::load_core()` (not gated on `is_admin()`), so it still has a callback to run outside wp-admin | Removes recent-event-log entries older than the configured retention window; complements the same age-filtering already applied on every write inside `record_recent_event()` |

The event is scheduled only from an admin request but registered to run correctly regardless of how `wp-cron.php` is triggered; it is unscheduled on both plugin deactivation (`wp_clear_scheduled_hook()` in the main file's deactivation callback) and uninstall.

## Frontend Behavior

- **Public assets** (`Script_Handler::enqueue_public_assets()`, hooked to `wp_enqueue_scripts` and `login_enqueue_scripts`) load: the Cloudflare `api.js` script (only when a Site Key is configured, the request isn't whitelisted, and the outage failsafe isn't currently bypassing verification — loading a script from a service just confirmed unreachable would only strand widgets in a broken loading state), `assets/css/public.css`, and `assets/js/public.js` (jQuery dependency), each versioned by `filemtime()` for cache-busting. A `window.KitgenixCaptchaForCloudflareTurnstileConfig` object is inlined before `public.js` with site key, disable_submit, appearance, size, theme, extra_message, debug flag, dev_mode/annotate_dev flags, replay message, freshness_ms, per-integration `modes` (currently just `woocommerce_blocks`), and effective WooCommerce Blocks widget attributes.
- A small inline script patches `window.fetch` to attach `X-Turnstile-Token` and a JSON `extensions.kitgenix_captcha_for_cloudflare_turnstile_turnstile.token` field to any `fetch()` call whose URL matches `/wc/store/` + `checkout|cart` — the WooCommerce Blocks Store API bridge.
- Widgets render wherever a `.cf-turnstile` container exists in the DOM, across every enabled integration (see [Turnstile Integration](#turnstile-integration) above for the full render/reset lifecycle).
- **wp-login.php / wp-admin centering CSS** is force-applied late (priority 99) on `login_enqueue_scripts`/`admin_enqueue_scripts` via `wp_add_inline_style()` on a dummy registered style handle (`kitgenix-captcha-for-cloudflare-turnstile-login-align` / `-admin-align`), so it wins the CSS cascade regardless of theme/plugin ordering.
- **Admin assets** (`Script_Handler::enqueue_admin_assets()`) load only on screens whose hook suffix contains `kitgenix-captcha-for-cloudflare-turnstile`: `assets/css/admin.css` (depends on the shared `kitgenix-admin-ui.css`), `assets/js/kitgenix-admin-tabs.js`, `assets/js/admin.js` (jQuery dependency, localized as `KitgenixTurnstileAdmin` with `ajax_url`, reveal-secret action/nonce, setup-verify action/nonce).

## Assets

### JavaScript

| File | Enqueued on | Purpose |
|---|---|---|
| `assets/js/public.js` | Every frontend page + all `wp-login.php` screens | Central Turnstile widget renderer/reset engine for every integration; WooCommerce Blocks self-healing container logic; global submit guard for Interaction-Only appearance; per-plugin AJAX/DOM-event listeners (CF7, WPForms, Gravity Forms, Forminator, Fluent Forms, Elementor, WooCommerce checkout fragments, bfcache `pageshow` restore) |
| `assets/js/elementor.js` | Frontend pages only, and only when a global `$post` context exists (guards against Elementor's own null-post PHP warnings in REST/AJAX/cron) | Ensures a hidden input + `.cf-turnstile` container exist in Elementor forms/popups (does not itself render — rendering is centralized in `public.js`); clears/collapses the widget after a *confirmed successful* AJAX submission only (reads the JSON response body, never infers success from "request completed") |
| `assets/js/admin.js` | Only the plugin's own settings screen | Reveal/copy secret key (with on-demand AJAX fetch of the stored secret), setup-verification AJAX handler + status rendering, "Copy recent log" clipboard action, unsaved-changes floating bar, shortcode-copy button, client-side JSON import preview (never submits the file, just parses it locally) |
| `assets/js/kitgenix-admin-tabs.js` | Only the plugin's own settings screen | Shared, cross-plugin Kitgenix topbar/tab/search controller (URL hash + `?tab=` sync, `_wp_http_referer` rewriting so a tab is preserved across a settings save) — explicitly documented in-file as "keep identical between plugins" |
| `assets/js/kitgenix-admin-components.js` | Only the plugin's own settings screen | Shared, cross-plugin component library: toast notifications, modal open/close, collapsible sections, copy-to-clipboard, live table search/filter/pagination — also documented as kept identical across Kitgenix plugins |

### CSS

| File | Enqueued on | Purpose |
|---|---|---|
| `assets/css/public.css` | Every frontend page + login screens | Widget container styling, light/dark CSS custom-property tokens (`--kitgenix-*`), RTL support, reduced-motion fallback, minimal `!important` usage, small `min-height` to reduce CLS |
| `assets/css/admin.css` | Plugin settings screen | Plugin-specific additions layered on top of the shared design system |
| `assets/css/kitgenix-admin-ui.css` | Every Kitgenix admin screen (registered on `admin_enqueue_scripts` priority 5, so it's available even before the settings page itself enqueues it) | The full shared Kitgenix design system (cards, settings rows, toggles, tables, notices, stat tiles, topbar, tabs) — imports Google Fonts (`fonts.googleapis.com`/`fonts.gstatic.com`: Inter, Manrope) |
| `assets/css/kitgenix-hub.css` | Only the shared `kitgenix` top-level Hub page | Plugin-discovery grid styling |

## Internationalization

- **Text domain**: `kitgenix-captcha-for-cloudflare-turnstile` (matches the plugin slug; `Domain Path: /languages`).
- Translations are loaded automatically by WordPress.org's language-pack system — the main file explicitly notes "Translations are loaded automatically by WordPress.org; no manual call required" (no `load_plugin_textdomain()` call in the codebase).
- `wp_set_script_translations()` is called for both the public script (`kitgenix-captcha-for-cloudflare-turnstile-public`) and the admin script (`kitgenix-captcha-for-cloudflare-turnstile-admin`), pointing at the `languages/` directory, so JS-side strings can be translated via WordPress.org language packs too.
- **Translation files present**: only `languages/kitgenix-captcha-for-cloudflare-turnstile.pot` (70,863 bytes, `POT-Creation-Date: 2026-08-30`, generated by `WP-CLI 2.12.0`). No `.po`/`.mo` files are bundled in the plugin directory — translations rely entirely on WordPress.org-hosted language packs at runtime.
- All user-facing strings throughout the codebase consistently use `__()`/`_e()`/`esc_html__()`/`esc_attr__()`/`_n()` with the correct text domain and translator comments on strings containing placeholders.

## Security

- **Nonces**: A single custom nonce action, `kitgenix_captcha_for_cloudflare_turnstile_action` (field name `kitgenix_captcha_for_cloudflare_turnstile_nonce`), is used across almost every integration's rendered form and verified inside `Turnstile_Validator::is_valid_submission()`/`get_token_from_request()`. Separate nonces are used for: settings save (`kitgenix_captcha_for_cloudflare_turnstile_settings_save`), AJAX secret reveal (`kitgenix_turnstile_reveal_secret`), AJAX setup verification (`kitgenix_turnstile_verify_setup`), settings export/import (`kitgenix_turnstile_export_settings`/`kitgenix_turnstile_import_settings`), CSV export/log-clear/log-retention (`kitgenix_turnstile_export_analytics`/`kitgenix_turnstile_clear_event_log`/`kitgenix_turnstile_save_log_retention`), and duplicate-loader-notice dismissal (`kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss`). Integrations whose host plugin transports data as JSON rather than a classic form (Ninja Forms, WooCommerce Blocks, MailPoet's AJAX path) deliberately skip the WP nonce and rely on the Cloudflare token check itself plus (where applicable) a header-based transport that a cross-site request cannot forge without also solving Turnstile.
- **Capability checks**: every admin-facing action (settings page render, all AJAX handlers, all `admin-post.php` handlers, the duplicate-notice dismiss link) requires `current_user_can('manage_options')`; the shared Kitgenix Hub top-level menu additionally accepts `manage_woocommerce` as an alternate capability when WooCommerce is active.
- **Sanitization**: `Admin_Options::sanitize()` is the single choke point for the entire settings array — every field is cast/whitelisted against an explicit allowed-value set (radios/selects), booleans are coerced to `1`/`0`, IP/CIDR/wildcard patterns are validated character-by-character (`sanitize_ip_patterns()`, `is_valid_cidr_format()`), and the secret key has dedicated logic to avoid ever accidentally wiping a previously-saved value when the field is left blank on save. All `$_POST`/`$_SERVER`/`$_GET` superglobal reads throughout the codebase are consistently wrapped in `sanitize_text_field(wp_unslash())` (with `// phpcs:ignore` comments explaining why nonce verification is intentionally skipped for read-only presence checks).
- **Escaping**: output consistently uses `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`, `esc_textarea()`, or `wp_kses()`/`wp_kses_post()` with explicit allowed-tag lists where raw HTML must be echoed (e.g. the Kitgenix Hub card markup, admin notice lists).
- **Enforcement audit (2.0.0)**: the changelog documents a "full hook-by-hook enforcement audit" that fixed several integrations where a failed Turnstile check was previously only *logged* rather than actually blocking the action — including Contact Form 7's shortcode-only mode, Easy Digital Downloads login (wrong hook), Fluent Forms (wrong hook), Kadence Forms (dead hooks), and Ninja Forms (wrong error-array key). A prior release (1.0.13) fixed a critical bypass where Elementor Pro Forms and Forminator accepted submissions with a missing/empty token instead of rejecting them.
- **Request-method gating removed as a security boundary**: several integrations (CF7, Elementor) deliberately do *not* gate validation on `$_SERVER['REQUEST_METHOD']`, since that value is attacker-controlled and using it to skip validation would be a fail-open bypass; validation instead runs unconditionally whenever the host plugin's own hook fires.
- **Secret key never printed to HTML** by default — the settings page renders an empty `<input type="password">` with a "Saved (hidden)" placeholder; the actual value is fetched only on demand via the authenticated, nonced `kitgenix_turnstile_get_secret` AJAX action.
- **Trusted-proxy hardening**: `Client_IP` only trusts forwarded-IP headers when `REMOTE_ADDR` itself matches a configured trusted proxy IP/CIDR, and further requires header-supplied IPs to be public/routable (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`), preventing a spoofed header from an untrusted source or a private/reserved address from being accepted as the "real" client IP.
- **CSV injection**: the changelog notes diagnostic/analytics CSV exports were confirmed not to introduce spreadsheet formula/DDE injection, since exported fields are plugin-generated labels/counters rather than raw user-submitted values.

## Uninstall / Cleanup

- **Deactivation** (`kitgenix_captcha_for_cloudflare_turnstile_deactivate_plugin()`): deletes the activation-redirect transient and clears the `kitgenix_turnstile_prune_event_log` cron event. No settings/data are removed on deactivation.
- **Uninstall** (`uninstall.php`, invoked by WordPress in preference to the `register_uninstall_hook()` callback in the main file, per WordPress's own uninstall-file precedence): see [Data Storage → Uninstall cleanup](#uninstall-cleanup) above for the complete list of options/transients removed and the multisite-wide loop.

## Known Limitations / TODOs

- **No automated tests ship in the plugin package** — the changelog (2.0.0) mentions "Added automated tests covering enforcement regressions" and "spoofed trusted-proxy headers and out-of-range CIDR scenarios," but no `tests/` directory exists anywhere under the plugin root reviewed here; these tests apparently live outside the distributed plugin.
- **Stale fallback version string in code**: `includes/core/class-turnstile-validator.php` hardcodes a fallback `'2.0.2'` for the siteverify `User-Agent` header if the version constant is somehow undefined. Harmless in practice (the constant is always defined), but it's a small drift point worth cleaning up.
- **`languages/` ships only a `.pot` template**, no compiled `.po`/`.mo` translations — non-English strings depend entirely on WordPress.org's hosted language-pack infrastructure being available and up to date for this specific plugin/version.
- **Kadence Forms**: by the integration's own in-code documentation, only the newer "Form (Adv)" block (`Kadence_Blocks_Advanced_Form_Block`) can be genuinely protected; Kadence's older/classic "Form" block has no pre-actions rejection filter in Kadence Blocks' current source, so that block type cannot be validated server-side by this plugin at all (only a caveat noted in code comments, not surfaced in the admin UI).
- **Ninja Forms honeypot field is rendered but non-functional**: the integration's own comment explains the honeypot `<input>` it appends to the DOM is never actually submitted (Ninja Forms posts a JSON blob assembled from its own Backbone field model, not the raw DOM form), so `Turnstile_Validator`'s honeypot check can never trip for Ninja Forms submissions specifically — the code leaves the field rendered anyway ("harmless either way to a real visitor") rather than removing it, relying on the primary Turnstile check for actual protection on this integration.
- **No Gutenberg block, widget, or REST API surface** — despite the plugin's scope, integration with block-based custom forms or headless/REST-driven form submissions outside the specifically-supported plugins above would require a developer to call the shortcode-rendered widget manually and wire up their own server-side `Turnstile_Validator::validate_token()` call; the readme explicitly states rendering the shortcode alone is "not sufficient security" for a custom handler.
- **Kitgenix Plugin Score integration is entirely convention-based** (matches page slugs `login`/`register`/`forgot-password` and specific nonce/submit field names hard-coded in `Plugin_Score::$form_map`) — a theme customization to the Kitgenix Plugin Score plugin's page templates that renames those slugs or fields would silently stop widget injection and validation without any admin-visible warning beyond the general "setup verification" gate.
