<?php
// Kadence Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_filter;
use function esc_attr;
use function get_option;
use function sanitize_text_field;
use function wp_nonce_field;
use function wp_unslash;

class KadenceForms {

    public static function init() {
        if ( ! class_exists( 'Kadence_Blocks_Form' ) || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        if ( empty( $settings['enable_kadenceforms'] ) ) {
            return;
        }

        // Inject Turnstile just before the submit button
        add_filter( 'kadence_blocks_form_submit_button_html', [ __CLASS__, 'inject_turnstile' ], 10, 2 );

        // Validate submission server-side
        add_filter( 'kadence_blocks_form_pre_process', [ __CLASS__, 'validate_turnstile' ], 10, 2 );
    }

    /**
     * Prepend Turnstile markup before the submit button.
     * Adds nonce + hidden token input so AJAX/normal posts always include the token.
     *
     * @param string $button_html
     * @param int    $form_id
     * @return string
     */
    public static function inject_turnstile( $button_html, $form_id ) {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $button_html;
        }

        // Guard: avoid duplicate injection per form
        static $done = [];
        if ( isset( $done[ $form_id ] ) ) {
            return $button_html;
        }
        $done[ $form_id ] = true;

        ob_start();

        // Our CSRF nonce (used by Turnstile_Validator::is_valid_submission()).
        if ( function_exists( 'wp_nonce_field' ) ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden input for the token (populated by public JS on success).
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Container only; global renderer handles Turnstile script/rendering.
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
           . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
           . ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"'
           . ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
           . ' data-kgx-owner="kadence"'
           . '></div>';

        $widget = ob_get_clean();

        // Return the widget + original button HTML (do not escape $button_html — it is expected markup).
        return $widget . $button_html;
    }

    /**
     * Validate Turnstile on submit.
     * Always return an array with 'error' for consistent UX in Kadence.
     *
     * @param bool|array $should_process
     * @param object     $form_instance
     * @return bool|array
     */
    public static function validate_turnstile( $should_process, $form_instance ) {
        if ( self::request_method() !== 'POST' ) {
            return $should_process;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            $message = Turnstile_Validator::get_error_message( 'kadenceforms' );

            if ( ! is_array( $should_process ) ) {
                $should_process = [];
            }
            $should_process['error'] = $message;

            return $should_process;
        }

        return $should_process;
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
