<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_filter;
use function esc_attr;
use function esc_js;
use function get_option;
use function sanitize_text_field;
use function wp_add_inline_script;
use function wp_nonce_field;
use function wp_unslash;

class ForminatorForms {

    /**
     * Init Forminator integration.
     */
    public static function init() {
        if ( ! function_exists( 'forminator' ) || Whitelist::is_whitelisted() ) {
            return;
        }
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        if ( empty( $settings['enable_forminator'] ) ) {
            return;
        }

        // Inject widget by replacing submit markup (reliable for AJAX).
        add_filter( 'forminator_render_form_submit_markup', [ __CLASS__, 'inject_turnstile_markup' ], 10, 4 );

        // Validate on submit (server-side).
        add_filter( 'forminator_custom_form_submit_errors', [ __CLASS__, 'validate_turnstile' ], 10, 3 );
    }

    /**
     * Inject Turnstile widget into Forminator form submit markup.
     *
     * @param string $html     Submit button HTML (raw).
     * @param int    $form_id  Form ID.
     * @param int    $post_id  Post ID.
     * @param string $nonce    Nonce (from Forminator).
     * @return string Modified submit markup.
     */
    public static function inject_turnstile_markup( $html, $form_id, $post_id, $nonce ) {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        // Respect per-integration mode: skip auto-inject if shortcode-only is selected.
        $mode = $settings['mode_forminator'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            // Still allow other shortcodes in the markup.
            if ( function_exists( 'do_shortcode' ) ) {
                $html = \do_shortcode( (string) $html );
            }
            return $html;
        }

        // When in auto mode, we want to allow other shortcodes but ensure our shortcode
        // does not render (admins asked for auto placement). Temporarily remove it.
        $shortcode_removed = false;
    if ( function_exists( 'do_shortcode' ) && function_exists( 'shortcode_exists' ) && shortcode_exists( 'kitgenix_turnstile' ) ) {
            if ( function_exists( 'remove_shortcode' ) ) {
                remove_shortcode( 'kitgenix_turnstile' );
                $shortcode_removed = true;
            }
        }

        if ( function_exists( 'do_shortcode' ) ) {
            $html = \do_shortcode( (string) $html );
        }
        // Re-register shortcode if we temporarily removed it so we don't leave it missing
        // in cases where we return early (e.g. no site key).
        if ( ! empty( $shortcode_removed ) ) {
            if ( class_exists( '\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode' ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::register_shortcode();
            } elseif ( function_exists( 'add_shortcode' ) ) {
                add_shortcode( 'kitgenix_turnstile', [ '\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode', 'render_shortcode' ] );
            }
        }
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            // Do not sanitize/alter original markup here—let Forminator output as-is.
            return $html;
        }

        // Respect per-integration mode: skip auto-inject if shortcode-only is selected.
        $mode = $settings['mode_forminator'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            return $html;
        }

        // Guard: avoid duplicate injection per form, or if a rendered shortcode/widget exists.
        if ( \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $html, false ) ) {
            return $html;
        }

        // Guard: avoid duplicate injection per form
        static $done = [];
        $fid = (int) $form_id;
        if ( isset( $done[ $fid ] ) ) {
            return $html;
        }
        $done[ $fid ] = true;

        $widget_id = 'cf-turnstile-fmntr-' . $fid;

        ob_start();

        // CSRF nonce for our validator.
        if ( function_exists( 'wp_nonce_field' ) ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token field (populated by global Turnstile callback).
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        echo '<div class="kitgenix-captcha-for-cloudflare-turnstile-forminator-wrapper">';
        echo '<div id="' . esc_attr( $widget_id ) . '" class="cf-turnstile"'
            . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
            . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
            . ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"'
            . ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
            . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="forminator"></div>';

        // Keep the original submit button markup verbatim (sanitize using wp_kses_post()).
        echo wp_kses_post( $html );
        echo '</div>';

        $widget = ob_get_clean();

        // Nudge re-render after AJAX steps without directly loading CF script here.
        if ( function_exists( 'wp_add_inline_script' ) ) {
            wp_add_inline_script(
                'kitgenix-captcha-for-cloudflare-turnstile-public',
                'document.addEventListener("DOMContentLoaded",function(){document.dispatchEvent(new CustomEvent("kitgenixcaptchaforcloudflareturnstile:turnstile-containers-added",{detail:{source:"forminator",id:"' . esc_js( $widget_id ) . '"}}));});'
                . 'document.addEventListener("forminator:render:after",function(){document.dispatchEvent(new CustomEvent("kitgenixcaptchaforcloudflareturnstile:turnstile-containers-added",{detail:{source:"forminator",id:"' . esc_js( $widget_id ) . '"}}));});',
                'after'
            );
        }

        // Re-register shortcode if we removed it temporarily.
        if ( ! empty( $shortcode_removed ) ) {
            if ( class_exists( '\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode' ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::register_shortcode();
            } elseif ( function_exists( 'add_shortcode' ) ) {
                add_shortcode( 'kitgenix_turnstile', [ '\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode', 'render_shortcode' ] );
            }
        }

        // Return widget + submit markup (no wp_kses_post — Forminator controls sanitization).
        return $widget;
    }

    /**
     * Validate Turnstile response on Forminator submission.
     *
     * @param array $submit_errors     Existing submission errors.
     * @param int   $form_id           Form ID.
     * @param array $field_data_array  Submitted field data.
     * @return array Modified submission errors.
     */
    public static function validate_turnstile( $submit_errors, $form_id, $field_data_array ) {
        // Only validate on POST
        if ( self::request_method() !== 'POST' ) {
            return $submit_errors;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            if ( ! is_array( $submit_errors ) ) {
                $submit_errors = [];
            }
            // Use a keyed error so Forminator shows it consistently in the UI.
            $submit_errors['turnstile'] = Turnstile_Validator::get_error_message( 'forminator' );
        }

        return $submit_errors;
    }

    /**
     * Sanitize request method (PHPCS-friendly access to $_SERVER).
     */
    private static function request_method(): string {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

ForminatorForms::init();
