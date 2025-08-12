<?php
// Contact Form 7 integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function esc_attr;
use function esc_html__;
use function get_option;
use function sanitize_text_field;
use function wp_nonce_field;
use function wp_unslash;

class ContactForm7 {

    public static function init() {
        if ( ! defined('WPCF7_VERSION') || Whitelist::is_whitelisted() ) {
            return;
        }
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        if ( empty($settings['enable_cf7']) ) {
            return;
        }

        // Inject widget before the submit control.
        add_filter('wpcf7_form_elements', [__CLASS__, 'inject_turnstile'], 20, 1);

        // Validate on submit (AJAX + non-AJAX).
        add_filter('wpcf7_validate', [__CLASS__, 'validate_turnstile'], 10, 2);
    }

    /**
     * Insert hidden token + Turnstile container before the first submit control.
     * Handles both <input type="submit"> and <button type="submit">.
     *
     * @param string $content Raw CF7 form HTML.
     * @return string
     */
    public static function inject_turnstile($content) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $content;
        }

        // Don’t inject if there’s already a container in this form.
        if ( strpos($content, 'class="cf-turnstile"') !== false ) {
            return $content;
        }

        ob_start();

        // Our CSRF nonce (used by Turnstile_Validator::is_valid_submission()).
        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token field populated by public JS after challenge success.
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Container only; global renderer loads/handles the Turnstile widget.
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'    . esc_attr($site_key) . '"'
           . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
           . ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"'
           . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="cf7"></div>';

        $injection = ob_get_clean();

        // Try to place right before the first submit control.
        $patterns = [
            '/(<input[^>]+type=["\']submit["\'][^>]*>)/i',
            '/(<button[^>]+type=["\']submit["\'][^>]*>)/i',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                return preg_replace( $pattern, $injection . '$1', $content, 1 );
            }
        }

        // Fallback: append before closing form tag.
        return str_ireplace('</form>', $injection . '</form>', $content);
    }

    /**
     * Validation hook (runs for the form as a whole).
     * We add a form-level error so CF7 shows the red message box and blocks submit.
     *
     * @param \WPCF7_Validation  $result
     * @param \WPCF7_ContactForm $contact_form
     * @return \WPCF7_Validation
     */
    public static function validate_turnstile($result, $contact_form) {
        // Only validate on POST (sanitize access to $_SERVER).
        if ( self::request_method() !== 'POST' ) {
            return $result;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            // Use a generic form-level error (no specific tag).
            $result->invalidate( 'form', Turnstile_Validator::get_error_message('cf7') );
        }

        return $result;
    }

    /**
     * Sanitize request method (PHPCS-friendly).
     */
    private static function request_method(): string {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

ContactForm7::init();
