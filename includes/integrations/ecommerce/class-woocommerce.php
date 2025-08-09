<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use function add_action;
use function get_option;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function __;
use function wp_nonce_field;
use function wc_add_notice;
use function sanitize_text_field;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function apply_filters;

defined('ABSPATH') || exit;

class WooCommerce {

    /**
     * Initialize integration.
     */
    public static function init() {
        if (!function_exists('is_woocommerce') || \KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist::is_whitelisted()) {
            return;
        }

        $settings = \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);

        // Only add hooks for enabled forms
        if (!empty($settings['wc_checkout_form'])) {
            \add_action('woocommerce_after_checkout_billing_form', [__CLASS__, 'render_widget']);
            \add_action('woocommerce_after_order_notes', [__CLASS__, 'render_widget']);
            \add_action('woocommerce_checkout_process', [__CLASS__, 'validate_turnstile']);
            \add_action('woocommerce_after_checkout_validation', [__CLASS__, 'validate_turnstile'], 10, 2);
        }
        if (!empty($settings['wc_login_form'])) {
            \add_action('woocommerce_login_form', [__CLASS__, 'render_widget']);
            \add_filter('woocommerce_login_errors', [__CLASS__, 'catch_errors']);
        }
        if (!empty($settings['wc_register_form'])) {
            \add_action('woocommerce_register_form', [__CLASS__, 'render_widget']);
            \add_action('woocommerce_register_post', [__CLASS__, 'validate_generic'], 9);
        }
        if (!empty($settings['wc_lostpassword_form'])) {
            \add_action('woocommerce_lostpassword_form', [__CLASS__, 'render_widget']);
            \add_action('woocommerce_reset_password_validation', [__CLASS__, 'validate_generic']);
        }
    }

    /**
     * Output the Turnstile widget.
     */
    public static function render_widget() {
        $settings = \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';

        if (!$site_key) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">' . \esc_html( __( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ) . '</p>';
            return;
        }

        // Add a nonce field for CSRF protection
        if (function_exists('wp_nonce_field')) {
            \wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce');
        }

        echo '<div class="cf-turnstile" data-sitekey="' . \esc_attr($site_key) . '" data-theme="' . \esc_attr($settings['theme'] ?? 'auto') . '" data-size="' . \esc_attr($settings['widget_size'] ?? 'normal') . '" data-appearance="' . \esc_attr($settings['appearance'] ?? 'always') . '"></div>';
    }

    /**
     * Validate Turnstile on checkout.
     */
    public static function validate_turnstile() {
        if (!Turnstile_Validator::is_valid_submission()) {
            \wc_add_notice(Turnstile_Validator::get_error_message('woocommerce'), 'error');
        }
    }

    /**
     * Validate on registration and password reset.
     */
    public static function validate_generic() {
        if (!Turnstile_Validator::is_valid_submission()) {
            \wc_add_notice(Turnstile_Validator::get_error_message('woocommerce'), 'error');
        }
    }

    /**
     * Validate on login.
     */
    public static function catch_errors($error) {
        if (!Turnstile_Validator::is_valid_submission()) {
            $error->add('turnstile_error', Turnstile_Validator::get_error_message('woocommerce'));
        }
        return $error;
    }
}
