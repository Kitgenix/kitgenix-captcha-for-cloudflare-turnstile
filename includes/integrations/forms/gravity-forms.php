<?php
// Gravity Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
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

class GravityForms {
    public static function init() {
        if ( ! class_exists('GFForms') || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        if ( empty($settings['enable_gravityforms']) ) {
            return;
        }

        // Inject widget before the submit button
        add_filter('gform_submit_button', [__CLASS__, 'inject_turnstile_before_submit'], 9, 2);

        // Validate on submit (works for normal + AJAX submits)
        add_filter('gform_validation', [__CLASS__, 'validate_turnstile'], 10, 1);
    }

    /**
     * Inject Turnstile markup directly before the submit button.
     * Adds our nonce + hidden token input; container is rendered by the global public JS.
     *
     * @param string $button Raw button HTML from GF.
     * @param array  $form   Form array.
     * @return string
     */
    public static function inject_turnstile_before_submit($button, $form) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $button;
        }

        // Guard against duplicate injection per form ID
        $form_id = isset($form['id']) ? (int) $form['id'] : 0;
        static $rendered_for = [];
        if ( $form_id && isset($rendered_for[$form_id]) ) {
            return $button;
        }
        if ( $form_id ) {
            $rendered_for[$form_id] = true;
        }

        ob_start();

        // CSRF nonce for our validator
        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token field (populated by global Turnstile callback)
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Turnstile container; global renderer will handle the widget
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'    . esc_attr($site_key) . '"'
           . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
           . ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"'
           . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="gravityforms"'
           . '></div>';

        $widget = '<div class="kitgenix-turnstile-wrap">' . ob_get_clean() . '</div>';

        // Return our widget + original button (GF expects raw HTML here)
        return $widget . $button;
    }

    /**
     * Server-side validation.
     * Sets top-level validation message so GF displays the error consistently.
     *
     * @param array $validation_result
     * @return array
     */
    public static function validate_turnstile($validation_result) {
        // Only validate on POST
        if ( self::request_method() !== 'POST' ) {
            return $validation_result;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            $validation_result['is_valid'] = false;
            if ( isset($validation_result['form']) && is_array($validation_result['form']) ) {
                $validation_result['form']['failed_validation']  = true;
                $validation_result['form']['validation_message'] = Turnstile_Validator::get_error_message('gravityforms');
            }
        }

        return $validation_result;
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

GravityForms::init();
