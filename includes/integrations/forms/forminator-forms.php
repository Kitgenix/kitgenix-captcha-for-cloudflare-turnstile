<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use function add_action;
use function add_filter;
use function get_option;
use function wp_nonce_field;
use function esc_attr;
use function esc_js;
use function wp_kses_post;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Just_Cloudflare_Turnstile_Forminator_Integration {
    /**
     * Init Forminator integration.
     */
    public static function init() {
        if ( ! function_exists( 'forminator' ) || Whitelist::is_whitelisted() ) {
            return;
        }
        $settings = \get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        if ( empty( $settings['enable_forminator'] ) ) {
            return;
        }
        // Inject widget by replacing submit markup (reliable for AJAX)
        add_filter( 'forminator_render_form_submit_markup', [ __CLASS__, 'inject_turnstile_markup' ], 10, 4 );
        // Validate on submit
        add_filter( 'forminator_custom_form_submit_errors', [ __CLASS__, 'validate_turnstile' ], 10, 3 );
    }

    /**
     * Inject Turnstile widget into Forminator form submit markup.
     *
     * @param string $html Submit button HTML.
     * @param int $form_id Form ID.
     * @param int $post_id Post ID.
     * @param string $nonce Nonce.
     * @return string Modified submit markup.
     */
    public static function inject_turnstile_markup( $html, $form_id, $post_id, $nonce ) {
        $settings = \get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) return \wp_kses_post($html);

        $widget_id = 'cf-turnstile-fmntr-' . $form_id;
        $widget_id_attr = \esc_attr( $widget_id );
        ob_start();
        // Add nonce field for CSRF protection
        if ( function_exists( 'wp_nonce_field' ) ) {
            \wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce' );
        }
        echo '<div class="kitgenix-captcha-for-cloudflare-turnstile-forminator-wrapper">';
        echo '<div id="' . esc_attr( $widget_id_attr ) . '" class="cf-turnstile" '
            . 'data-sitekey="' . \esc_attr( $site_key ) . '" '
            . 'data-theme="' . \esc_attr( $settings['theme'] ?? 'auto' ) . '" '
            . 'data-size="' . \esc_attr( $settings['widget_size'] ?? 'normal' ) . '" '
            . 'data-callback="turnstileForminatorCallback"></div>';
        echo \wp_kses_post( $html );
        echo '</div>';
        $widget = ob_get_clean();

        // Add the JS as inline script on the public handle
        if ( function_exists( 'wp_add_inline_script' ) ) {
            \wp_add_inline_script(
                'kitgenix-captcha-for-cloudflare-turnstile-public',
                'jQuery(document).ajaxComplete(function(){setTimeout(function(){' .
                    'var el=document.getElementById("' . \esc_js( $widget_id ) . '");' .
                    'if(window.turnstile && el){try{window.turnstile.render(el);}catch(e){}}' .
                    'document.querySelectorAll(".kitgenix-ts-disable-submit").forEach(function(el){el.disabled=false;});' .
                '},0);});function turnstileForminatorCallback(){document.querySelectorAll(".forminator-button, .forminator-button-submit").forEach(function(el){el.disabled=false;});}',
                'after'
            );
        }
        // Render both the widget and the submit button together
        return \wp_kses_post($widget);
    }

    /**
     * Validate Turnstile response on Formnator form submission.
     *
     * @param array $submit_errors Existing submission errors.
     * @param int $form_id Form ID.
     * @param array $field_data_array Submitted field data.
     * @return array Modified submission errors.
     */
    public static function validate_turnstile( $submit_errors, $form_id, $field_data_array ) {
        if ( ! Turnstile_Validator::is_valid_submission() ) {
            $submit_errors[] = Turnstile_Validator::get_error_message('forminator');
        }
        return $submit_errors;
    }
}

Just_Cloudflare_Turnstile_Forminator_Integration::init();