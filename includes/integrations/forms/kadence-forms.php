<?php
// Kadence Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function esc_attr;
use function get_option;
use function sanitize_text_field;
use function wp_enqueue_script;
use function wp_add_inline_script;
use function wp_json_encode;
use function wp_create_nonce;
use function wp_unslash;

/**
 * Targets Kadence Blocks' "Form (Adv)" block only (class Kadence_Blocks_Advanced_Form_Block).
 * The older/classic "Form" block (Kadence_Blocks_Form_Block, processed by form-ajax.php) has
 * no pre-actions rejection filter at all – its only related hook,
 * kadence_blocks_form_submission_success, is applied AFTER do_action('kadence_blocks_form_submission', ...)
 * has already run the form's email/webhook/save actions, so nothing hooked there could ever
 * block those actions. Only the Advanced Form block exposes a genuine pre-actions gate
 * (kadence_blocks_advanced_form_submission_reject, consulted before
 * do_action('kadence_blocks_advanced_form_submission', ...) in advanced-form-ajax.php), so
 * that is the only Kadence form type this integration can honestly protect.
 */
class KadenceForms {

    public static function init() {
        if ( ! class_exists( 'Kadence_Blocks_Advanced_Form_Block' ) || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        if ( empty( $settings['enable_kadenceforms'] ) ) {
            return;
        }

        $mode = $settings['mode_kadenceforms'] ?? 'auto';
        if ( $mode !== 'shortcode' ) {
            // Advanced Form is a fully client-rendered dynamic block with no PHP filter over
            // its submit-button markup, so the widget is injected via a small footer script
            // (mirroring the same safe fallback pattern used by other page-builder-style
            // integrations in this plugin) rather than guessing at a PHP render filter.
            add_action( 'wp_footer', [ __CLASS__, 'enqueue_fallback_injector' ], 20 );
        }

        // Validate submission server-side, before Kadence runs the form's actions
        // (email/webhook/save) – see class docblock for why this is the Advanced Form's
        // submission_reject filter specifically, not the classic form's hooks.
        add_filter( 'kadence_blocks_advanced_form_submission_reject', [ __CLASS__, 'validate_turnstile' ], 10, 4 );
    }

    /**
     * Validate Turnstile before Kadence's Advanced Form runs its submit actions.
     *
     * Hook: kadence_blocks_advanced_form_submission_reject (filter). Default is `false`;
     * KB_Ajax_Advanced_Form::process_ajax() checks `if ($submission_rejected) { ...bail... }`
     * immediately after applying this filter and BEFORE
     * do_action('kadence_blocks_advanced_form_submission', ...) – so returning true here
     * genuinely halts the submission, matching Kadence's own native CAPTCHA implementation's
     * use of the exact same gate.
     *
     * @param bool  $submission_rejected Current rejection state (default false).
     * @param array $form_args
     * @param array $processed_fields
     * @param int   $post_id
     * @return bool|string True (or a truthy value) rejects; kadence_blocks_advanced_form_submission_reject_message supplies the message.
     */
    public static function validate_turnstile( $submission_rejected, $form_args, $processed_fields, $post_id ) {
        if ( $submission_rejected ) {
            return $submission_rejected;
        }
        if ( self::request_method() !== 'POST' ) {
            return $submission_rejected;
        }

        if ( ! Turnstile_Validator::is_valid_submission( true, 'kadenceforms' ) ) {
            add_filter( 'kadence_blocks_advanced_form_submission_reject_message', [ __CLASS__, 'reject_message' ] );
            return true;
        }

        return $submission_rejected;
    }

    /**
     * Supplies the Turnstile-specific rejection message once validate_turnstile() has
     * already determined the submission should be rejected.
     */
    public static function reject_message() {
        return Turnstile_Validator::get_error_message( 'kadenceforms' );
    }

    /**
     * Enqueue a small inline script that injects the Turnstile container + hidden token
     * input into every Kadence Advanced Form on the page, before its submit button.
     * Kadence's Advanced Form reads submitted fields directly from $_POST (a normal
     * serialized form post, not a JSON blob), so a plain appended <input> is delivered
     * correctly – unlike form builders that serialize their own JS field model instead
     * of the DOM.
     */
    public static function enqueue_fallback_injector() {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return;
        }

        $theme      = \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::get_effective_theme( 'kadenceforms' );
        $size       = \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::get_effective_size( 'kadenceforms' );
        $appearance = \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::get_effective_appearance();
        $language_override  = \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::get_effective_language_override( 'kadenceforms' );
        $honeypot_field_name = ! empty( $settings['honeypot_enabled'] )
            ? Turnstile_Validator::honeypot_field_name()
            : '';
        $nonce = wp_create_nonce( 'kitgenix_captcha_for_cloudflare_turnstile_action' );

        $handle   = 'kitgenix-captcha-for-cloudflare-turnstile-kadence';
        $base_url = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            ? (string) constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            : '';
        $ver = defined( 'KitgenixCaptchaForCloudflareTurnstile_Version' )
            ? (string) constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' )
            : null;

        if ( ! $base_url ) {
            return;
        }

        wp_register_script( $handle, false, [], $ver, true );
        wp_enqueue_script( $handle );

        $inline =
            'document.addEventListener("DOMContentLoaded", function () {'
            . 'document.querySelectorAll(".kb-advanced-form").forEach(function(wrapper){'
                . 'var form = wrapper.tagName === "FORM" ? wrapper : wrapper.querySelector("form");'
                . 'if (!form || form.querySelector(".cf-turnstile")) { return; }'

                . 'var nonce = document.createElement("input");'
                . 'nonce.type = "hidden";'
                . 'nonce.name = "kitgenix_captcha_for_cloudflare_turnstile_nonce";'
                . 'nonce.value = ' . wp_json_encode( (string) $nonce ) . ';'
                . 'form.appendChild(nonce);'

                . 'var input = document.createElement("input");'
                . 'input.type = "hidden";'
                . 'input.name = "cf-turnstile-response";'
                . 'form.appendChild(input);'

                . ( $honeypot_field_name !== ''
                    ? 'var hp = document.createElement("input");'
                        . 'hp.type = "text";'
                        . 'hp.name = ' . wp_json_encode( $honeypot_field_name ) . ';'
                        . 'hp.tabIndex = -1;'
                        . 'hp.autocomplete = "off";'
                        . 'hp.setAttribute("aria-hidden", "true");'
                        . 'hp.className = "kitgenix-captcha-for-cloudflare-turnstile-hp-wrap";'
                        . 'form.appendChild(hp);'
                    : '' )

                . 'var container = document.createElement("div");'
                . 'container.className = "cf-turnstile";'
                . 'container.setAttribute("data-sitekey", ' . wp_json_encode( (string) $site_key ) . ');'
                . 'container.setAttribute("data-theme", ' . wp_json_encode( (string) $theme ) . ');'
                . 'container.setAttribute("data-size", ' . wp_json_encode( (string) $size ) . ');'
                . 'container.setAttribute("data-appearance", ' . wp_json_encode( (string) $appearance ) . ');'
                . ( $language_override !== '' ? 'container.setAttribute("data-language", ' . wp_json_encode( $language_override ) . ');' : '' )
                . 'container.setAttribute("data-kitgenix-captcha-for-cloudflare-turnstile-owner", "kadence");'

                . 'var submitBtn = form.querySelector("button[type=submit], input[type=submit]");'
                . 'if (submitBtn && submitBtn.parentNode) { submitBtn.parentNode.insertBefore(container, submitBtn); }'
                . 'else { form.appendChild(container); }'

                . 'try{document.dispatchEvent(new CustomEvent("KitgenixCaptchaForCloudflareTurnstile:turnstile-containers-added", { detail: { source: "kadence" } }));}catch(e){}'
            . '});'
            . '});';

        wp_add_inline_script( $handle, $inline, 'after' );
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

KadenceForms::init();
