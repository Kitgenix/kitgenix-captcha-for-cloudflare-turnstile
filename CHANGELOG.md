# Changelog

## 2.0.2 – 2 September 2026

- **New:** Added an optional Cloudflare outage failsafe (Settings → Advanced → Reliability, off by default) that independently checks Cloudflare Turnstile's reachability server-side and, once enabled, lets protected forms submit instead of blocking every visitor during a confirmed Cloudflare outage. A visitor cannot trigger this bypass themselves; it only activates when the plugin's own probe confirms Cloudflare is unreachable.
- **New:** Added a live "Cloudflare outage failsafe is active" alert on the Log tab and in Site Health for as long as the failsafe is bypassing verification, and failsafe bypasses are logged distinctly from genuine Cloudflare-verified passes in the recent events log and per-integration metrics.
- **Fix:** Resolved an issue where the WooCommerce Blocks Checkout widget could stop rendering after the checkout block re-renders during a normal page load (cart totals, shipping rates and payment methods resolving in sequence). A container that was emptied out by one of these re-renders could be left behind still marked as already rendered, so it silently stayed blank instead of showing the widget. The self-healing recovery logic now recognises a genuinely emptied container and re-renders into it, gated by a grace period so it never interrupts a widget that is still loading or has already been solved.
- **Fix:** The plugin now properly calls Cloudflare's `turnstile.remove()` before clearing and re-rendering any widget container, across every integration (WooCommerce, Elementor, Gravity Forms, Formidable Forms, Forminator, Jetpack Forms, Fluent Forms, Kadence Forms). Previously the container was cleared directly without unregistering the widget first, which could leave a stale widget reference behind and produce "Cannot find Widget" warnings after repeated re-renders.
- **Fix:** The WooCommerce Blocks Checkout container-recovery observer now unregisters a widget from Cloudflare's script when the block's own re-render removes its container from the DOM, instead of leaving an orphaned widget reference behind for the replacement container to collide with.
- **Fix:** Removed an unnecessary plugin-version query parameter that WordPress's automatic asset versioning was appending to Cloudflare's third-party `api.js` script URL. Turnstile's own script logged this as an unrecognised parameter; the URL now matches what Cloudflare's CDN expects.

## 2.0.0 – 22 August 2026

- **New:** Redesigned the admin settings interface around the shared Kitgenix design system with a sticky topbar, cross-plugin branding, grouped navigation, an Advanced dropdown for Security, Advanced, and Portability, and a light/dark theme toggle that remembers the selected preference.
- **New:** Added in-page settings search with "/" and Cmd/Ctrl+K keyboard shortcuts, filtering the active tab's cards and rows as you type and displaying a dedicated no-results state when nothing matches.
- **New:** Added a central Kitgenix Hub page for discovering, installing, activating, and reviewing Kitgenix plugins from one screen, integrated into the same topbar and navigation shell as the plugin settings.
- **New:** Added a shared Kitgenix component library providing reusable modals, collapsible sections, copy-to-clipboard controls, sortable/searchable tables, and toast notifications.
- **New:** Added a dedicated Log tab containing active alerts, site-impact statistics, per-integration analytics, and the recent diagnostic log, moving diagnostics out of the Support tab.
- **New:** Added Ninja Forms integration with Turnstile tokens delivered through a request header rather than a hidden field, matching Ninja Forms' JSON-based submission architecture.
- **New:** Documented and re-verified the existing Kitgenix Plugin Score integration for login, registration, and forgotten-password protection.
- **New:** Added per-integration widget overrides for Theme, Widget Size, and Language, allowing individual integrations to override the global display settings or inherit them unchanged.
- **New:** Added an optional honeypot fallback under Settings → Security that rejects bots which blindly populate hidden fields before any request is sent to Cloudflare's `siteverify` endpoint.
- **New:** Added Test Mode per Integration under Settings → Developer Mode, allowing individual integrations to operate in warn-only mode while the rest of the site remains fully enforced.
- **New:** Diagnostic log entries now record Cloudflare `siteverify` round-trip latency so slow successful responses can be distinguished from failures and timeouts.
- **New:** Added a Protection Health card to the Log tab with actionable states including Healthy, No recent traffic, High failure rate, Cloudflare unavailable, Duplicate Turnstile loader, and Configuration problem.
- **New:** Added Integration, Result, Category, and Time Period filters to the recent diagnostic log alongside the existing free-text search.
- **New:** Renamed the per-integration analytics table to "Integration health matrix" and added each integration's Enabled/Disabled state and Auto/Shortcode injection mode.
- **New:** Settings imports now provide a client-side preview showing setting-key count, export date, whether Site/Secret keys are included, included settings groups, and plugin-identifier mismatch warnings before Replace or Merge is committed.
- **New:** On WordPress 7.1 and later, the Kitgenix brand icon is registered with the WordPress Icon Registration API as `kitgenix/mark` for discovery through `wp_get_icon()` and the icons REST endpoint.
- **Improved:** All settings areas – Site Keys, Display, Integrations, Security, Advanced, and Portability – now use consistent cards, labelled rows, toggle switches, and unified alert components.
- **Improved:** Refreshed the admin and public-facing Turnstile widget colours from the previous purple palette to the updated Kitgenix blue palette, including light and dark mode variants.
- **Improved:** The plugin-specific admin stylesheet now builds on the shared Kitgenix admin UI stylesheet for consistent spacing, typography, and component styling.
- **Improved:** The recent diagnostic log is now displayed as a searchable, paginated table with time, integration, outcome, and a plain-English note instead of a read-only text block.
- **Improved:** Retained the "Copy recent log" action for quickly copying the raw diagnostic log for troubleshooting or support.
- **Improved:** Per-integration analytics now supports live search and pagination.
- **Improved:** The Support tab is now three focused cards – a donate card with a collapsible monthly-amount picker, a "what your support funds" summary, and a "get involved" panel for reviews and plugin links.
- **Improved:** Stale WordPress security tokens are no longer counted as blocked attempts or included in the failure-spike alert threshold because they represent recoverable session or caching friction rather than genuine security rejections.
- **Improved:** The replayed-token diagnostic message now explains that the event can result from legitimate actions such as double-clicks or back-button resubmissions as well as malicious replay attempts.
- **Improved:** Both Turnstile validation entry points now consistently respect site-wide Developer Mode and the new per-integration Test Mode.
- **Performance:** Frontend Turnstile script loading remains unchanged. Cloudflare `api.js` continues to load once and only when a widget will actually render for the current visitor.
- **Performance:** Whitelisted requests continue to skip Turnstile frontend script loading entirely.
- **Performance:** The existing duplicate Turnstile loader detector remains unchanged.
- **Fix:** WooCommerce lost/reset-password protection is now self-contained. Enabling only the WooCommerce lost-password integration now validates its own My Account reset-request form without depending on the separate WordPress Core integration.
- **Fix:** WooCommerce lost-password validation is scoped to WooCommerce's own form and does not interfere with the native `wp-login.php` lost-password flow.
- **Fix:** WooCommerce login verification now runs only once per request when both modern and legacy WooCommerce login-error filters fire, preventing legitimate submissions from being incorrectly rejected as replayed or expired.
- **Fix:** bbPress forum creation now validates Turnstile exactly once on the correct pre-insert action instead of running a second redundant validation through `bbp_new_forum_pre_insert`.
- **Fix:** Removed incorrect handling that could replace bbPress forum post data with an unexpected validation return value.
- **Fix:** WPForms Turnstile errors now render the actual error message instead of the literal text "Array".
- **Fix:** The admin language allow-list is now sourced from `Script_Handler::get_allowed_languages()` so global and per-integration language settings cannot drift apart.
- **Fix:** Elementor AJAX handling is now scoped to the form that actually submitted a solved Turnstile token instead of affecting every Elementor form on the page.
- **Fix:** Elementor widgets are now hidden or cleared only after a confirmed successful form submission, preserving the widget after validation failures so visitors can retry.
- **Security:** Completed a full hook-by-hook enforcement audit of every supported integration to verify that a failed Turnstile check in enforcement mode actually blocks the protected action rather than merely being logged.
- **Security:** Fixed Contact Form 7 shortcode-only mode so the `wpcf7_validate` validation filter is always registered. Previously shortcode-only placement could render the widget while allowing submissions to bypass validation completely.
- **Security:** Contact Form 7 shortcode mode now correctly respects the integration's enable/disable setting.
- **Security:** Removed request-method fail-open checks from Contact Form 7 and Elementor validation. Validation now runs whenever the host plugin invokes the relevant validation hook rather than trusting `$_SERVER['REQUEST_METHOD']` as a security boundary.
- **Security:** Fixed Easy Digital Downloads login protection by replacing the nonexistent `edd_process_login_form` hook with WordPress's real `authenticate` filter, scoped to EDD login submissions through EDD's own nonce field.
- **Security:** Fixed Fluent Forms enforcement by replacing the nonexistent `fluentform_submit_validation` hook with Fluent Forms' actual `fluentform/validation_errors` validation filter.
- **Security:** Rebuilt Kadence Forms protection against the current Kadence Blocks "Form (Adv)" implementation using `kadence_blocks_advanced_form_submission_reject`, replacing dead hooks and an invalid class check that meant validation previously never ran.
- **Security:** Kadence Advanced Form widget injection now uses an appropriate frontend script bridge because the block does not expose a suitable PHP render filter.
- **Security:** Fixed Ninja Forms token delivery by attaching the token to the relevant AJAX request header rather than inserting a hidden DOM field that Ninja Forms never included in its JSON submission payload.
- **Security:** Ninja Forms failed Turnstile checks now terminate the submission request directly instead of writing to an error-array structure that Ninja Forms does not consult.
- **Security:** `Turnstile_Validator::validate_token()` now respects site-wide Developer Mode and per-integration Test Mode consistently with `is_valid_submission()`.
- **Security:** Confirmed diagnostic and analytics CSV exports do not introduce spreadsheet formula/DDE injection because they contain plugin-generated labels and counters rather than raw user-submitted form values.
- **Security:** Confirmed settings exports continue to exclude the Site Key and Secret Key unless an administrator explicitly opts to include them.
- **Security:** Re-verified trusted-proxy client IP resolution against spoofed proxy headers and out-of-range CIDRs.
- **Security:** Proxy headers including `CF-Connecting-IP`, `True-Client-IP`, `X-Forwarded-For`, and `X-Real-IP` are only trusted when `REMOTE_ADDR` matches an administrator-configured trusted proxy address or CIDR.
- **Security:** Client IP values extracted from trusted proxy headers must be valid public addresses; private and reserved addresses are rejected.
- **Compatibility:** Confirmed compatibility with WordPress 7.1.
- **Compatibility:** Declared WooCommerce High-Performance Order Storage (HPOS / `custom_order_tables`) compatibility.
- **Compatibility:** Re-verified that Checkout Blocks order verification metadata uses WooCommerce's `WC_Order` CRUD API through `update_meta_data()` and `save()` rather than direct database access.
- **Compatibility:** Verified WooCommerce 11.x compatibility across Classic Checkout, Checkout Blocks/Store API, and WooCommerce My Account login, registration, and lost-password flows against current WooCommerce source.
- **Dev:** Added `Turnstile_Validator::get_all_integration_keys()` as the canonical list of integrations eligible for Test Mode and per-integration settings, sourced consistently with `get_integration_label()`.
- **Dev:** Updated Kitgenix logo and favicon filenames and references throughout the plugin and Kitgenix Hub to use the shared brand asset set.
- **Dev:** Refreshed the Kitgenix Hub plugin directory listing, including the renamed MultiStore for WooCommerce entry and the new Image Optimizer listing.
- **Dev:** Added automated tests covering enforcement regressions discovered during the integration audit.
- **Dev:** Added automated tests covering spoofed trusted-proxy headers and out-of-range CIDR scenarios.
- **Documentation:** Documented the Kitgenix Plugin Score integration in the readme for the first time.
- **Documentation:** Expanded the changelog to distinguish security-sensitive enforcement fixes from general bug fixes.
- **Documentation:** Documented the previously undocumented `kitgenix_turnstile_skip_wp_login_validation` filter under Developers → Filters.

## 1.1.3 – 26 May 2026

- **Improvement:** Diagnostic log now shows a plain-English category and explanatory note for every entry instead of the internal retry-required / first-pass-or-hard-fail classification. False positives (e.g. stale nonce on a cached My Account page) are now clearly labelled as `cached-or-expired-page` with guidance, so they are not mistaken for attacks. A category legend table is displayed alongside the log in the Support tab.
- **Fix:** WooCommerce My Account registration validation now correctly blocks bot registrations. The previous hook (`woocommerce_register_post` + `wc_add_notice`) only queued a frontend notice but did not prevent account creation; validation is now wired to the `woocommerce_registration_errors` filter so failed Turnstile challenges add a `WP_Error` that WooCommerce checks before creating the account.
- **Fix:** WooCommerce reset-password validation now receives the `WP_Error` object and adds errors directly to it, ensuring a failed Turnstile challenge blocks the password reset instead of only showing a notice.
- **Fix:** Diagnostic log data is now fully cleaned up when the plugin is uninstalled.
- **Compatibility:** Confirmed compatibility with WordPress 7.0.

## 1.1.2 – 26 May 2026

- **Dev:** Skipped to be in line with other Kitgenix Plugins.

## 1.1.1 – 26 May 2026

- **Dev:** Skipped to be in line with other Kitgenix Plugins.

## 1.1.0 – 7 May 2026

- **New:** Added MailPoet integration with Turnstile injection and server-side newsletter subscription validation.
- **New:** Added Ultimate Member integration covering login, registration, and password reset forms.
- **New:** Added MemberPress integration for signup and checkout flows.
- **New:** Added Paid Memberships Pro integration for checkout and registration flows.
- **New:** Added wpDiscuz integration for comment and reply forms.
- **New:** Added environment-managed and wp-config-managed key overrides for the Turnstile Site Key and Secret Key.
- **New:** Added a dedicated Portability tab with JSON export/import tools for reusing settings across sites.
- **New:** Added an end-to-end setup verification gate that validates the current Site Key and Secret Key through a real server-side Cloudflare siteverify check before login-sensitive flows are allowed to load.
- **New:** Added a privacy-safe recent diagnostic log for admins with copyable timestamps, integration labels, outcomes, and Cloudflare error codes.
- **New:** Added automatic admin and Site Health alerts for sudden verification failure spikes and blocked Cloudflare `siteverify` requests.
- **New:** Added per-integration analytics in the Support tab with pass, failure, retry, and friction reporting for each protected flow.
- **New:** Added CSV exports for the per-integration analytics table and the recent diagnostic log.
- **Improvement:** Added new admin integration toggles for MailPoet, Ultimate Member, MemberPress, Paid Memberships Pro, and wpDiscuz.
- **Improvement:** MailPoet forms now receive the Turnstile token through the expected nested request field for reliable validation.
- **Improvement:** Site Key and Secret Key admin fields now become read-only when a constant or environment variable override is active.
- **Improvement:** Settings exports can omit sensitive keys by default, while imports support replace or merge workflows for agency rollouts.
- **Improvement:** The admin test widget now performs server-side setup verification, records the result for the current key pair, and warns admins when auth-sensitive protections are still gated.
- **Improvement:** The Support tab now surfaces active protection alerts, including duplicate Turnstile loader conflicts, with quick triage actions.
- **Improvement:** WordPress core login protection now supports `wp_login_form()` custom login screens and skips REST/XML-RPC auth edge cases for better compatibility with hidden-login URLs, theme modals, and 2FA flows.
- **Improvement:** Recent diagnostic logging deliberately avoids storing raw IP addresses, request URIs, or submitted values.
- **Improvement:** Privacy-safe metrics now store per-integration retry counts so admins can quantify challenge friction without storing raw visitor identifiers.
- **Fix:** Turnstile API script loading now avoids adding WordPress `?ver=` query arguments to Cloudflare `api.js`, removing the browser console warning about unknown API parameters.
- **Fix:** Widget size handling now uses Cloudflare-supported values (`normal`, `compact`, `flexible`) with backward-compatible mapping for legacy saved sizes.
- **Fix:** WooCommerce Classic checkout now validates Turnstile only once per submission, preventing false “Your verification expired. Please complete the Turnstile challenge.” errors when enforcement is enabled.
- **Fix:** WooCommerce Classic Checkout with replay protection now allows checkout retries and only marks the token as used after successful order creation, preventing `replay_detected` errors during payment failures or validation retries.
- **Fix:** Easy Digital Downloads (EDD) checkout with replay protection now allows checkout retries and only marks the token as used after successful purchase, preventing `replay_detected` errors during payment failures or validation retries.
- **Fix:** Paid Memberships Pro checkout with replay protection now allows checkout retries and only marks the token as used after successful membership creation, preventing `replay_detected` errors during payment failures or validation retries.
- **Fix:** WooCommerce Blocks checkout with replay protection now allows checkout retries and only marks the token as used after successful order creation via Store API.
- **Fix:** Standard WordPress comments handling now defers to wpDiscuz-specific validation when wpDiscuz protection is enabled, preventing duplicate handling.
- **Fix:** All integration files (EDD, Elementor, WP Core, Paid Memberships Pro, Ultimate Member, MemberPress, wpDiscuz, MailPoet, BuddyPress, bbPress, Fluent Forms, WPForms, Contact Form 7, Kadence Forms, JetFormBuilder, Gravity Forms, Formidable Forms, Forminator, Jetpack Forms) now pass widget size through the `normalize_widget_size()` helper, ensuring only Cloudflare-supported values (`normal`, `compact`, `flexible`) are ever rendered in `data-size` attributes.
- **Fix:** Cleared stale `_lastToken` on Turnstile reset or expiry – the stored token is now always synced with the hidden input value, preventing a stale token being replayed by the WooCommerce Blocks fetch bridge after a checkout error.
- **Fix:** WooCommerce Classic Checkout now resets the Turnstile widget automatically after a failed submission (`checkout_error` event) and re-initialises it after checkout fragment refreshes (`updated_checkout` event), ensuring users always have a fresh token ready for retry.
- **Docs:** Updated the readme supported integrations list to include the new membership, newsletter, and community coverage.

## 1.0.18 – 19 March 2026

- **New:** Added a dedicated WooCommerce Product Reviews integration toggle so store reviews can be protected independently of standard WordPress comments.
- **Improvement:** Validate Store API POSTs early via a single REST pre-dispatch path; token accepted from `X-Turnstile-Token` header or canonical extensions payload (WooCommerce Blocks).
- **Improvement:** WooCommerce product reviews now follow the WooCommerce Classic injection mode and error-message context while still using the shared comment form hooks internally.
- **UI:** Improved the Kitgenix admin header layout for better alignment and less clutter.
- **UI:** Social links in admin headers now render as compact icon buttons with accessible labels.
- **UI:** Added responsive header helpers so titles/description and actions/links lay out consistently.
- **UI:** Admin tables inside Kitgenix pages now use Kitgenix styling for a more consistent branded look.
- **Fix:** Admin notices now display above the Kitgenix header using the WordPress standard notice area.
- **Fix:** Removed custom notice moving/styling so core WordPress notices keep their default appearance.
- **Fix:** Added defensive notice normalization to prevent notices being relocated into the header by other scripts.
- **Fix:** Normalised settings page card spacing so it matches other Kitgenix plugins.
- **Fix:** Added spacing between adjacent action links/buttons (e.g. Edit/Delete).
- **Fix:** Split standard WordPress comments from WooCommerce product reviews so enabling blog comments protection no longer captures product review submissions unless the WooCommerce reviews toggle is enabled.
- **Cleanup:** Normalised nonce verification and request handling across admin and validation flows for WordPress.org review compliance.
- **Maintenance:** Updated the plugin Author URI to the public Kitgenix WordPress.org profile and replaced the old custom admin-menu icon CSS with the native Dashicons icon.
- **Docs:** Updated the bundled documentation and package readme to describe the new WooCommerce Product Reviews coverage and the `kitgenix_turnstile_handle_comment_form` routing filter.

## 1.0.17 – 18 February 2026

- **New:** Added JetFormBuilder integration (auto-inject and shortcode-only modes).
- **New:** JetFormBuilder server-side validation during submission handling (AJAX compatible).
- **New:** Added JetFormBuilder toggle + injection mode to the settings page.
- **Improvement:** JetFormBuilder auto-inject places the widget near the submit button row and avoids multi-step next/prev actions.
- **Fix:** Support tab “Your site impact” metrics now update as Turnstile checks run (total/passed/failed).
- **UI:** Added Stock Sync for WooCommerce to the Kitgenix Hub cards.
- **Dev:** Regenerated `/languages/kitgenix-captcha-for-cloudflare-turnstile.pot` translation template.
- **Docs:** Overhauled `readme.txt`.
- **Docs:** Updated WordPress.org screenshots.
- **Docs:** JetFormBuilder includes its own Turnstile/CAPTCHA option; use one Turnstile provider per form to avoid duplicates.

## 1.0.16 – 27 January 2026

- **Improvement:** Small admin UI tweaks and performance refinements.
- **Fix:** Hardened admin asset enqueues to prefer `$_GET['page']` with a fallback to hook-suffix so assets load reliably on existing installs.
- **Fix:** Localized admin JS now exposes AJAX action and nonce for the reveal-secret flow to securely fetch stored secret keys.
- **Change:** Declared PHP requirement as 8.1.
- **Cleanup:** Minor compatibility and stability fixes, plus i18n/translation updates.
- **Cleanup:** PHPCS/i18n/security fixes across admin and core files (output escaping, translator comments, optional nonce checks).

## 1.0.15 – 1 January 2026

- **New:** Added Easy Digital Downloads integration (checkout, login, registration, and profile editor) with per-form toggles and a dedicated mode setting (Auto vs Shortcode-only).
- **New:** Added a shared Kitgenix top-level wp-admin menu + Hub page, and moved Turnstile settings to Kitgenix → Cloudflare Turnstile (activation redirect + “Settings” link updated accordingly).
- **Improvement:** bbPress integration now avoids duplicate widget output on themes that fire multiple hooks, adds support for the forum form, and validates forum creation flows.
- **Improvement:** Fluent Forms rendering is now more resilient when the Turnstile API loads late (prevents “stuck rendering” states and allows clean retries).
- **Improvement:** Standardized internal widget owner attribute + dynamic-render event naming, reducing render misses in dynamic/AJAX contexts.
- **Improvement:** WordPress comments widget placement is now consistently injected above the submit button across themes; comment widget now has a stable ID for easier targeting.
- **Fix:** Replay protection setting now persists correctly when you disable it (checkbox omission on save no longer forces it back on).
- **Security:** Secret key is no longer printed into the settings page HTML by default; “Reveal secret key” now fetches it on-demand via authenticated AJAX + nonce.
- **UI:** Updated Kitgenix branding (admin + public CSS tokens), added shared Hub stylesheet, refreshed plugin banners, and added Kitgenix logo assets.
- **Cleanup:** Removed onboarding strings and updated translations; plugin headers/requirements updated (Tested up to 6.9, requires PHP 8.0).

## 1.0.14 – 9 December 2025

- **Improvement:** Public JS detects `data-kitgenix-captcha-for-cloudflare-turnstile-owner="woocommerce-blocks"` and performs an immediate render, then falls back to visibility guard for other owners.
- **UI:** Split WooCommerce settings into two blocks – “WooCommerce Classic” and “WooCommerce Blocks (Store API)” – with separate injection mode controls and clearer guidance.
- **UI:** Modernized settings page with sidebar navigation (icons), status overview card, accessible collapsible sections, and improved layout. Kept the floating “Unsaved changes” bar.
- **UI:** Added a copy button next to `[kitgenix_turnstile]` in the settings for easy manual placement.
- **UI:** Updated brand colors across admin and public CSS to main `#4f2a9a` and accent `#f364dd`.
- **Fix:** WooCommerce Blocks checkout widget now renders reliably even when Classic Checkout is disabled. The renderer no longer waits for the container to be visible before calling `turnstile.render()` for Blocks, preventing missed render windows.
- **Change:** Respect Shortcode-only – when Blocks is set to “Shortcode only”, auto-rendering is suppressed and server-side validation only enforces when a token is present (i.e. when you place the shortcode). Without a shortcode/token, checkout proceeds without Turnstile.
- **Change:** Clarification – unchecking “Checkout Form (Classic)” does not affect Blocks Checkout; disable Blocks auto-injection via its “Shortcode only” mode if desired.
- **Cleanup:** Removed Export/Import Settings feature – UI removed and handlers disabled (`class-settings-transfer.php` no longer registers actions). Any old direct Import/Export URLs are no-ops.
- **Cleanup:** Removed the Simple/Advanced mode toggle from the settings UI and scripts.
- **Dev:** Dropped the unused `kitgenix_turnstile_validate_keys` AJAX nonce localization from admin scripts.
- **Preparation:** Placement – ensures the widget is injected directly above the “Place order” area in WooCommerce Blocks checkout (handles submit button, text node, and actions wrapper variants).
- **Preparation:** Stability – keeps existing behaviour for Classic, core, and form plugins; no changes to validation flows or token forwarding (header + Store API extensions).

## 1.0.13 – 22 November 2025

- **Fix:** Elementor Pro Forms now properly fail validation when the Turnstile token is missing or empty (previously skipped validation entirely).
- **Fix:** Forminator Forms now properly fail validation when the Turnstile token is missing or empty (previously skipped validation entirely).
- **Fix:** Removed the `wp_kses_post()` wrapper from Forminator submit button HTML that could strip required attributes.
- **Security:** Critical validation bypass in Elementor Pro Forms and Forminator Forms where missing tokens were incorrectly allowing form submissions instead of blocking them.
- **Security:** Audit confirmed all other integrations (Contact Form 7, Gravity Forms, Formidable Forms, WPForms, Fluent Forms, Jetpack Forms, Kadence Forms, WooCommerce, WordPress core, bbPress, BuddyPress) correctly validate and fail when tokens are missing.
- **Security:** This update fixes a vulnerability where forms could be submitted without completing CAPTCHA verification. Update immediately.

## 1.0.12.1 – 22 November 2025

- **Fix:** Reverted to 1.0.11 until the security update was released.

## 1.0.12 – 21 November 2025

- **New:** Global shortcode `[kitgenix_turnstile]` to render the Turnstile widget manually inside custom HTML fields, form content, or page templates.
- **Improvement:** Auto-inject vs Shortcode behavior is now mutually exclusive and consistent across integrations.
- **Improvement:** Ensured Shortcode-only mode works across all supported form plugins via defensive `do_shortcode()` passthroughs and field-level filters, while Auto mode detection ignores literal shortcode tokens.
- **UI:** Only show the global Shortcode guidance card when at least one supported forms integration is present. Removed Auto/Shortcode radio controls from the WordPress Core card; core forms use the Enable checkbox and per-form toggles only.
- **Fix:** CF7 shortcode rendering in Shortcode-only mode – Contact Form 7 form HTML is now passed through `do_shortcode()` when the integration is set to Shortcode-only.
- **Change:** Added `includes/core/class-turnstile-shortcode.php` with a robust shortcode renderer and recursive detection helper `has_shortcode_in()` that detects literal shortcodes and rendered widget markers (`class="cf-turnstile"`, `data-kitgenix-shortcode`, or hidden `name="cf-turnstile-response"`).
- **Change:** Integration adapters now use the new helper and treat literal shortcode text separately from rendered markup so Auto mode is not blocked by leftover shortcode tokens.
- **Change:** When an integration needs to run `do_shortcode()` in Auto mode, it temporarily removes the plugin shortcode, runs `do_shortcode()`, then immediately re-registers the shortcode so it is never left unregistered.
- **Dev:** Reworked temporary shortcode removal logic to guarantee re-registration after `do_shortcode()`. Fixed edge-case uninitialised variable and parse issues.
- **Dev:** Standardised detection and injection semantics and added comments and guards for missing site keys, filters, and plugin version differences.
- **Docs:** Note – the stored `mode_wp_core` setting is retained for compatibility but no longer exposed in the UI. It can be removed in a future release if needed.

## 1.0.11 – 19 October 2025

- **Fix:** Elementor AJAX regression – prevented a brief layout “bump” where Interaction Only lost `.kitgenix-ts-collapsed` during the AJAX send; the container now stays collapsed unless a visible challenge is explicitly required.

## 1.0.10 – 16 October 2025

- **Improvement:** Event-driven rendering – added `kitgenix:turnstile-containers-added` event from injectors; public script listens and re-initializes rendering automatically for dynamically added containers.
- **Improvement:** Stability and UX – defensive re-render guards, explicit `data-rendered` attribute for CSS control, and safer visibility checks to avoid rendering inside hidden containers.
- **Fix:** Elementor Popups – reliably initializes the Turnstile challenge when a popup opens (even if the widget was inserted while hidden). Clears stale render flags, resets hidden iframes, and triggers a fresh render on show.
- **Fix:** Hidden input – always ensures `input[name="cf-turnstile-response"]` exists for Elementor forms (including popups) so the token is properly captured and validated.
- **Fix:** Interaction Only empty gaps – placeholders are now fully collapsed until the widget actually renders (via `data-rendered`). After successful AJAX submits, the container is collapsed/hidden to prevent any blank space.
- **Fix:** Multiple forms on a page – consistent collapsed behavior across instances; prevents duplicate containers in Elementor popups and re-renders only when needed.

## 1.0.9 – 15 October 2025

- **Improvement:** Proactive reveal for Interaction Only – if auto-verification doesn’t complete after a short period (~5s), the widget is surfaced and the challenge is triggered so users aren’t left waiting.
- **Improvement:** Streamlined inline messaging to align with Cloudflare’s own phrasing; reduced redundant prompts to let Cloudflare’s UI lead the experience.
- **Improvement:** Submit-time guards – for regular forms and Elementor AJAX; when no token is present, we halt that submission, reveal the widget, scroll it into view, and start a fresh challenge.
- **Fix:** “Disable Submit Button” now respects “Interaction Only” – submit stays enabled when Turnstile can verify invisibly, and is disabled only if a visible challenge is actually required (unsupported/timeout/error). Applies to Elementor, WordPress core forms, WooCommerce, Gravity Forms, Formidable, Forminator, Jetpack, Fluent Forms, and Kadence.
- **Dev:** Standardized render locks and defensive pre-render cleanup across remaining integrations to prevent duplicate iframes and race conditions.

## 1.0.8 – 15 October 2025

- **Improvement:** Deferred render – widgets now render when their container is visible (Elementor + generic paths), reducing layout thrash and improving perceived load times across dynamic UIs.
- **Fix:** Elementor popup – reliably renders Turnstile when popups open after page load (e.g. delayed by timer); if a widget initialized while hidden, it is reset and re-rendered on open.
- **Fix:** Elementor popup duplicates – de-duplicated popup/form event listeners and centralized rendering to avoid multiple widget instances; idempotent guards ensure one render per container.
- **Fix:** Interaction Only placeholder stays collapsed (no gap/shadow) after invisible validation; it only expands when UI is truly required (via unsupported/timeout/error callbacks or actual visible challenge).
- **Fix:** Prevent duplicate renders on Gravity Forms, Formidable, Forminator, and Jetpack by adding per-element render locks and pre-render cleanup.
- **Fix:** Prevent loader overlay – no spinner is injected for Interaction Only while the API loads; collapsed state fully hides any inner spinner and spinners never intercept clicks.
- **Dev:** Simplified collapse logic by removing the previous mutation-based watcher and relying on Turnstile callbacks + visibility checks.

## 1.0.7 – 14 October 2025

- **New:** Added “Flexible (100% width)” widget size (`data-size="flexible"`) for fully responsive, container-width layouts.
- **New:** Interaction Only UX refinement – collapses the initial blank gap (no more 50+px empty space) until the user interacts or the widget needs to expand.
- **Improvement:** Consistent collapsed/expand logic across Elementor, Gravity Forms, Formidable, Forminator, Jetpack, Fluent Forms, Kadence, WPForms, and core render paths.
- **Improvement:** CSS enhancements for flexible width + reduced gap state (`.kitgenix-ts-collapsed`).
- **Improvement:** Unified size handling in JS (`flexible` passes straight through; existing custom sizes still map to Cloudflare equivalents).
- **Dev:** Sanitization now allows `flexible`; admin settings UI updated with help text.
- **Preparation:** Foundation laid for upcoming modal/delayed form robustness (MutationObserver structure ready for attribute watching & visibility checks in a future release).

## 1.0.6 – 10 September 2025

- **Improvement:** Updated plugin assets (banners, icons, screenshots with clearer cropping/labels).
- **Improvement:** Updated `readme.txt` – full integrations list, screenshot captions, Support Development section, improved tags/short description, and clarified WooCommerce Blocks/Store API notes.

## 1.0.5 – 10 September 2025

- **Improvement:** More reliable widget injection and cleanup on AJAX/dynamic DOM events; tighter re-render/reset behavior.
- **Fix:** Admin: detect duplicate Turnstile API loader and show a dismissible notice on Settings and Plugins screens.
- **Fix:** Contact Form 7 injects once and resets cleanly on CF7 validation/error events.
- **Fix:** Exposed `window.KitgenixCaptchaForCloudflareTurnstile` so Cloudflare onload can reliably call `renderWidgets()` (prevents “no widget → no token”).
- **Fix:** Guard Elementor script enqueue to avoid PHP warnings in REST/AJAX or early hooks.
- **Fix:** Guarded “render once” logic to prevent duplicate widget rendering across core, WooCommerce, and form plugins.
- **Fix:** Prevent Turnstile overlapping submit buttons for Gravity Forms and WPForms; adjusted spacing and placement heuristics.
- **Fix:** Sanitization & import/export hardening – preserve CIDR & wildcard IP patterns.
- **Fix:** “Disable Submit Until Verified” now disables buttons on render and re-enables only after a valid token callback.
- **Fix:** Token handling – canonical token channel, auto-create hidden `cf-turnstile-response` input, `getLastToken()` helper, and `kitgenixcaptchaforcloudflareturnstile:token-updated` event.
- **Fix:** WooCommerce login/checkout placement (Classic & Blocks / Store API), including correct “Place order” positioning.
- **Security:** Replay protection enabled by default (TTL filterable via `kitgenix_turnstile_replay_ttl`).

## 1.0.4 – 17 August 2025

- **Fix:** Added spacing so Turnstile no longer overlaps the WPForms submit button.
- **Fix:** Positioned Turnstile above the WooCommerce reviews submit button.
- **Fix:** Prevented Turnstile from rendering inline with the submit button on Gravity Forms.

## 1.0.3 – 12 August 2025

- **Fix:** Fixed the “Save Settings” button not working after a few attempts.

## 1.0.2 – 12 August 2025

- **New:** Added advanced fields: `respect_proxy_headers` and `trusted_proxy_ips` (legacy), plus `trust_proxy` and `trusted_proxies` (current).
- **New:** Developer Mode (warn-only) – Turnstile failures are logged and annotated inline for admins but do not block submissions (useful for staging/troubleshooting).
- **New:** Replay protection – caches recent Turnstile tokens (hashed) for ~10 minutes and rejects re-use. Enabled by default; duration filterable via `kitgenix_turnstile_replay_ttl`.
- **Improvement:** Added canonical token channel (`getLastToken()` helper and `kitgenixcaptchaforcloudflareturnstile:token-updated` event dispatched on each token change). Hidden `cf-turnstile-response` input is auto-created in forms that don’t already have it.
- **Improvement:** Added preconnect/dns-prefetch resource hints for `https://challenges.cloudflare.com` to speed up first paint.
- **Improvement:** Added Site Health test (“Cloudflare Turnstile readiness”) reporting keys presence, duplicate loader detection, last verification snapshot, and possible JS delay/defer from optimization plugins (with guidance).
- **Improvement:** Admin CSS fully scoped to the settings wrapper, compact modern fields, focus-visible styles, and reduced-motion fallback.
- **Improvement:** Checkout protected via `woocommerce_checkout_process` and `woocommerce_after_checkout_validation` (WooCommerce Classic).
- **Improvement:** Consistent widget + validation across checkout/login/register/lost password (WooCommerce Classic).
- **Improvement:** Ensure hidden input + container are present; don’t inject a container if no site key is available (Elementor).
- **Improvement:** Export / Import JSON for settings (merge/replace). Optional inclusion of Secret Key (explicitly allowed).
- **Improvement:** Guardrails and housekeeping – centralized render flow, lightweight MutationObserver to catch dynamically added forms, and safer class/existence guards.
- **Improvement:** Include token in Elementor Pro AJAX payloads; re-render in popups and dynamic forms; reset widget on submit/errors.
- **Improvement:** Improved Disable Submit Button behavior – submit buttons are disabled immediately on render and re-enabled only after a valid token callback (previously disabled only on error/expired).
- **Improvement:** Inject container next to the “Place order” area via `render_block_woocommerce/checkout-actions-block` (WooCommerce Blocks).
- **Improvement:** Late alignment helpers for consistent widget placement on login/admin.
- **Improvement:** Preserve CIDR and wildcard IP patterns instead of stripping them; sanitize lines while keeping valid patterns.
- **Improvement:** Public CSS greatly reduced in scope (fewer global `!important`s), small min-height to prevent CLS, better RTL + reduced-motion support, and per-integration spacing.
- **Improvement:** Reliable widget injection before submit, spinner cleanup, and re-render on each plugin’s AJAX/DOM events.
- **Improvement:** Server-side validation hook support (`elementor_pro/forms/validation`).
- **Improvement:** Server-side validation mapped to each plugin’s native API.
- **Improvement:** “Test widget” is rendered only via a tight inline onload callback (prevents double-render / undefined globals).
- **Improvement:** Token freshness & UX – idle timer and token-age timer auto-reset widgets after ~150s (filterable via `kitgenix_turnstile_freshness_ms`), plus a gentle inline “Expired / Verification error – please verify again.” message beside the widget.
- **Improvement:** Validate Store API POSTs early via REST auth filter; token accepted from `X-Turnstile-Token` header or extensions (WooCommerce Blocks).
- **Improvement:** Widget injection and validation improvements across WooCommerce Blocks and Classic flows.
- **Fix:** Added widget render on `resetpass_form` and proper validation via `validate_password_reset`; lost password now validates via `lostpassword_post`.
- **Fix:** Contact Form 7 integrates cleanly (single injection, resets on CF7 error events).
- **Fix:** Duplicate Turnstile API loader detection with a dismissible admin notice (surfaces on the Settings page and Plugins screen).
- **Fix:** Exposed the public module globally as `window.KitgenixCaptchaForCloudflareTurnstile` so the Cloudflare API onload callback can call `renderWidgets()` (prevents “no widget → no token” failures).
- **Fix:** Guarded “render once” logic so widgets don’t duplicate across hooks (core + WooCommerce + form plugins).
- **Fix:** Reintroduced inline centering on `wp-login.php` / wp-admin to stabilize layout across all auth screens.
- **Fix:** Run Turnstile validation only on POST submissions for core forms (login, register, lost password, reset password, comments). Prevents the “Please complete the Turnstile challenge” message on refresh or wrong password.
- **Fix:** WooCommerce login handles both modern `woocommerce_process_login_errors` and legacy `woocommerce_login_errors`.
- **Security:** Added Cloudflare/Proxy-aware client IP handling with Trust Cloudflare/Proxy headers + Trusted Proxy IPs/CIDRs settings. Only honors `CF-Connecting-IP` / `X-Forwarded-For` when the request comes from a trusted proxy; otherwise falls back to `REMOTE_ADDR`.
- **Security:** Validator accepts token from POST, `X-Turnstile-Token` header, or custom filter; memoized siteverify; robust HTTP args; remote IP + URL + timeouts filterable; friendly error mapping; last verify snapshot stored for diagnostics.
- **Security:** Whitelist supports logged-in bypass, IPs with exact/wildcard/CIDR (IPv4/IPv6), and UA wildcards; decision cached per request and filterable via `kitgenix_turnstile_is_whitelisted`.

## 1.0.1 – 11 August 2025

- **Fix:** Centered Cloudflare Turnstile on all `wp-login.php` variants (login, lost password, reset, register) and across wp-admin.
- **Change:** Overhauled `includes/core/class-script-handler.php` to use the modern Script API (async strategy on WP 6.3+, attribute helpers on 5.7–6.2) and eliminated raw `<script>` output.
- **Dev:** Added filter `kitgenix_captcha_for_cloudflare_turnstile_script_url` for advanced control.
- **Dev:** Public/admin assets now use `filemtime()` for cache-busting.
- **Docs:** Expanded readme and updated links.

## 1.0.0 – 11 August 2025

- **New:** Initial Release.
- **New:** Admin Notices and Settings Errors.
- **New:** Admin UI (Modern).
- **New:** AJAX and Dynamic Form Rendering Support.
- **New:** Caching, AJAX, and Dynamic Forms Optimizations.
- **New:** Conditional Script Loading for Performance.
- **New:** Contact Form 7 Integration.
- **New:** CSRF Protection (Nonce Fields).
- **New:** Custom Error and Fallback Messages.
- **New:** Elementor Forms Integration.
- **New:** Error Handling and User Feedback.
- **New:** Fluent Forms Integration.
- **New:** Formidable Forms Integration.
- **New:** Forminator Forms Integration.
- **New:** GDPR-friendly (No Cookies or Tracking).
- **New:** Gravity Forms Integration.
- **New:** IP / User Agent / Logged-in User Whitelisting.
- **New:** Jetpack Forms Integration.
- **New:** Kadence Forms Integration.
- **New:** Language Selection for Widget.
- **New:** Multisite Support.
- **New:** Optional Plugin Badge.
- **New:** Per-Form and Per-Integration Enable/Disable.
- **New:** Plugin Translations/Localization.
- **New:** Server-Side Validation for All Supported Forms.
- **New:** Site Key & Secret Key Management.
- **New:** Widget Appearance Customization.
- **New:** Widget Options (Size, Theme, Appearance).
- **New:** WooCommerce Checkout Integration.
- **New:** WooCommerce Login Integration.
- **New:** WooCommerce Lost Password Integration.
- **New:** WooCommerce Registration Integration.
- **New:** Works With Elementor Element Cache.
- **New:** WPForms Integration.
- **New:** WordPress Comment Integration.
- **New:** WordPress Login Integration.
- **New:** WordPress Lost Password Integration.
- **New:** WordPress Registration Integration.
- **New:** “Defer Scripts” and “Disable Submit” Logic.
- **New:** No Impact on Core Web Vitals.