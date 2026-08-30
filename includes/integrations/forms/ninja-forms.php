<?php
// Ninja Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function get_option;
use function sanitize_text_field;
use function wp_add_inline_script;
use function wp_enqueue_script;
use function wp_json_encode;
use function wp_register_script;
use function wp_script_is;
use function wp_unslash;

/**
 * Ninja Forms renders its forms client-side (Backbone/React templates), so unlike
 * the other form integrations there is no server-rendered HTML string to filter
 * before the submit button. The widget, hidden token input, and honeypot field are
 * instead injected via a small footer script – the same technique already used for
 * Elementor's classic-form fallback (see class-elementor.php::fallback_inject_widget()).
 *
 * Server-side validation hooks 'ninja_forms_submit_data', which Ninja Forms filters
 * before running form actions; adding a message to $form_data['errors']['form']
 * blocks the submission and displays the message to the visitor.
 */
class NinjaForms {

    public static function init() {
        $present = class_exists( 'Ninja_Forms' ) || defined( 'NF_PLUGIN_DIR' );
        if ( ! $present || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        if ( empty( $settings['enable_ninjaforms'] ) ) {
            return;
        }

        // Run before core's wp_print_footer_scripts (priority 20) so the inline
        // script we register here is still queued when the footer group prints.
        add_action( 'wp_footer', [ __CLASS__, 'inject_widget' ], 5 );

        add_filter( 'ninja_forms_submit_data', [ __CLASS__, 'validate_turnstile' ], 10, 1 );
    }

    /**
     * Enqueue a small inline script that finds rendered Ninja Forms forms and
     * injects the hidden token input, optional honeypot field, and Turnstile
     * container before the submit button.
     */
    public static function inject_widget(): void {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';

        // Respect per-integration mode: skip auto-inject if shortcode-only is selected.
        $mode = $settings['mode_ninjaforms'] ?? 'auto';
        if ( $mode === 'shortcode' || ! $site_key ) {
            return;
        }

        $handle = 'kitgenix-captcha-for-cloudflare-turnstile-ninjaforms';
        if ( ! wp_script_is( $handle, 'registered' ) ) {
            wp_register_script( $handle, false, [], defined( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) ? KitgenixCaptchaForCloudflareTurnstile_Version : null, true );
        }
        wp_enqueue_script( $handle );

        $honeypot_field_name = ! empty( $settings['honeypot_enabled'] ) ? Turnstile_Validator::honeypot_field_name() : '';

        $attrs  = 'container.setAttribute("data-sitekey", ' . wp_json_encode( (string) $site_key ) . ');';
        $attrs .= 'container.setAttribute("data-theme", ' . wp_json_encode( Script_Handler::get_effective_theme( 'ninjaforms' ) ) . ');';
        $attrs .= 'container.setAttribute("data-size", ' . wp_json_encode( Script_Handler::get_effective_size( 'ninjaforms' ) ) . ');';
        $attrs .= 'container.setAttribute("data-appearance", ' . wp_json_encode( Script_Handler::get_effective_appearance() ) . ');';
        $language_override = Script_Handler::get_effective_language_override( 'ninjaforms' );
        if ( $language_override !== '' ) {
            $attrs .= 'container.setAttribute("data-language", ' . wp_json_encode( $language_override ) . ');';
        }

        // NOTE: like the token, this field is appended to the DOM <form> but – for the same
        // JSON-blob-transport reason documented below – never reaches $_POST, so it cannot
        // trip validate_token()'s honeypot check for Ninja Forms specifically. Left rendered
        // (harmless either way to a real visitor) rather than removed, since the primary
        // Turnstile check below still provides the actual protection via the header bridge.
        $honeypot_js = '';
        if ( $honeypot_field_name !== '' ) {
            $honeypot_js = 'if (!form.querySelector(' . wp_json_encode( 'input[name="' . $honeypot_field_name . '"]' ) . ')) {'
                . 'var hp = document.createElement("input");'
                . 'hp.type = "text";'
                . 'hp.name = ' . wp_json_encode( $honeypot_field_name ) . ';'
                . 'hp.tabIndex = -1;'
                . 'hp.autocomplete = "off";'
                . 'hp.setAttribute("aria-hidden", "true");'
                . 'hp.className = "kitgenix-captcha-for-cloudflare-turnstile-hp-wrap";'
                . 'form.appendChild(hp);'
                . '}';
        }

        // NOTE: no nonce hidden input is injected here (unlike other integrations). Ninja
        // Forms submits a JSON blob built from its own Backbone field-model collection
        // (POSTed as $_POST['formData']) rather than a normal serialized <form>, so a plain
        // appended <input> – nonce or token alike – never reaches the server through that
        // channel. The token is instead read directly from the DOM and sent as the
        // X-Turnstile-Token header (see the XHR patch below), which
        // Turnstile_Validator::validate_token() reads without requiring a WP nonce – the
        // same header-based, nonce-free path already used for the WooCommerce Store API
        // bridge in Script_Handler::enqueue_public_assets(), for the same underlying reason.
        $inline =
            '(function () {'
            . 'function kitgenixInjectNfWidget(wrap) {'
                . 'var form = wrap.querySelector("form");'
                . 'if (!form) return;'
                . 'if (form.querySelector(".cf-turnstile")) return;'

                . 'if (!form.querySelector("input[name=\\"cf-turnstile-response\\"]")) {'
                    . 'var input = document.createElement("input");'
                    . 'input.type = "hidden";'
                    . 'input.name = "cf-turnstile-response";'
                    . 'form.appendChild(input);'
                . '}'

                . $honeypot_js

                . 'var submitWrap = wrap.querySelector(".submit-wrap");'
                . 'if (!submitWrap) return;'

                . 'var container = document.createElement("div");'
                . 'container.className = "cf-turnstile";'
                . $attrs
                . 'container.setAttribute("data-kitgenix-captcha-for-cloudflare-turnstile-owner", "ninjaforms");'
                . 'submitWrap.parentNode.insertBefore(container, submitWrap);'

                . 'try{document.dispatchEvent(new CustomEvent("KitgenixCaptchaForCloudflareTurnstile:turnstile-containers-added", { detail: { source: "ninjaforms" } }));}catch(e){}'
            . '}'

            . 'function kitgenixScanNfForms() {'
                . 'document.querySelectorAll(".nf-form-cont").forEach(kitgenixInjectNfWidget);'
            . '}'

            . 'kitgenixScanNfForms();'
            . 'if (document.readyState === "loading") {'
                . 'document.addEventListener("DOMContentLoaded", kitgenixScanNfForms);'
            . '}'

            // Ninja Forms renders its fields into ".nf-form-cont" asynchronously via
            // Backbone (its own ready callback can finish after this script's initial
            // pass, and forms added later – AJAX pagination, multi-part forms, popups –
            // never fire DOMContentLoaded at all), so a single one-shot scan can run
            // before the <form>/.submit-wrap exist and then never retry, leaving the
            // widget permanently un-injected. Watch the DOM and re-scan whenever Ninja
            // Forms finishes rendering (or re-renders) a form; kitgenixInjectNfWidget()
            // is idempotent via its own ".cf-turnstile" guard above, so repeated scans
            // are harmless.
            . 'if (typeof MutationObserver !== "undefined") {'
                . 'var kitgenixNfScanTimer = null;'
                . 'var kitgenixNfObserver = new MutationObserver(function () {'
                    . 'clearTimeout(kitgenixNfScanTimer);'
                    . 'kitgenixNfScanTimer = setTimeout(kitgenixScanNfForms, 50);'
                . '});'
                . 'kitgenixNfObserver.observe(document.body, { childList: true, subtree: true });'
            . '}'
            . '})();'
            // Ninja Forms posts action=nf_ajax_submit to admin-ajax.php as a JSON
            // "formData" blob assembled from its own client-side field model, not a
            // serialized <form> – so the hidden cf-turnstile-response input above never
            // reaches $_POST. Patching XMLHttpRequest (jQuery\'s $.ajax uses XHR under the
            // hood, and this also covers a raw XHR call) lets us attach the token as a
            // request header instead, which the server reads regardless of body shape.
            . '(function(){'
                . 'if (window.__kitgenixNfXhrPatched) return;'
                . 'window.__kitgenixNfXhrPatched = true;'
                . 'var OrigOpen = XMLHttpRequest.prototype.open;'
                . 'var OrigSend = XMLHttpRequest.prototype.send;'
                . 'XMLHttpRequest.prototype.open = function(method, url) {'
                    . 'this.__kitgenixUrl = url;'
                    . 'return OrigOpen.apply(this, arguments);'
                . '};'
                . 'XMLHttpRequest.prototype.send = function(body) {'
                    . 'try {'
                        . 'var url = String(this.__kitgenixUrl || "");'
                        . 'var bodyStr = typeof body === "string" ? body : "";'
                        . 'var isNfSubmit = (url.indexOf("nf_ajax_submit") !== -1) || (bodyStr.indexOf("action=nf_ajax_submit") !== -1);'
                        . 'if (isNfSubmit) {'
                            . 'var tokenInput = document.querySelector("input[name=\\"cf-turnstile-response\\"]");'
                            . 'var token = tokenInput && tokenInput.value ? tokenInput.value : "";'
                            . 'if (token) { this.setRequestHeader("X-Turnstile-Token", token); }'
                        . '}'
                    . '} catch (e) {}'
                    . 'return OrigSend.apply(this, arguments);'
                . '};'
            . '})();';

        wp_add_inline_script( $handle, $inline, 'after' );
    }

    /**
     * Validate the Turnstile token during Ninja Forms submission processing.
     *
     * NF_AJAX_Controllers_Submission::process() (Ninja Forms core) only ever consults
     * $form_data['errors']['fields'][$field_id] – checked per real, schema-defined field
     * inside its field-processing loop – never $form_data['errors']['form']. Writing to
     * ['errors']['form'] here (as a generic key, with no real $field_id to attach to) would
     * therefore be silently ignored: the filtered value is returned and re-assigned to
     * $this->_form_data, but nothing downstream ever reads that particular key, so
     * processing (and the form's actions – email, save, webhooks) continues regardless of
     * the Turnstile result. To genuinely halt the request, respond and terminate PHP
     * execution directly here – the same effect NF's own internal errors have via
     * $this->_respond(), which itself is a wp_send_json() + wp_die() wrapper – instead of
     * relying on NF's error-array consultation, which does not cover this case.
     *
     * @param mixed $form_data
     * @return mixed
     */
    public static function validate_turnstile( $form_data ) {
        if ( self::request_method() !== 'POST' || ! is_array( $form_data ) ) {
            return $form_data;
        }

        // Read from the X-Turnstile-Token header (set by the XHR patch in inject_widget()),
        // not $_POST – Ninja Forms submits a JSON blob assembled from its own client-side
        // field model, so a normal hidden <input> (and any WP nonce alongside it) never
        // reaches $_POST here. validate_token() does not require a nonce, matching the
        // same header-based, nonce-free pattern already used for the WooCommerce Store API
        // bridge for the same underlying reason.
        $token = isset( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) )
            : '';

        if ( Turnstile_Validator::validate_token( $token, 'ninjaforms' ) ) {
            return $form_data;
        }

        if ( function_exists( 'wp_send_json' ) ) {
            wp_send_json(
                [
                    'errors' => [
                        'form' => [ Turnstile_Validator::get_error_message( 'ninjaforms' ) ],
                    ],
                ]
            );
        }

        // wp_send_json() always terminates execution (it wraps wp_die()); this is an
        // unreachable defensive fallback for the (practically impossible in WordPress)
        // case where that function doesn't exist.
        exit;
    }

    /**
     * Sanitize request method (PHPCS-friendly access to $_SERVER).
     */
    private static function request_method(): string {
        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

NinjaForms::init();
