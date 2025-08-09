<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

defined('ABSPATH') || exit;

class Admin_Options {

    /**
     * Option name used in the database.
     */
    const OPTION_NAME = 'kitgenix_captcha_for_cloudflare_turnstile_settings';

    /**
     * Initialize settings registration.
     */
    public static function init() {
        \add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register plugin settings using WordPress Settings API.
     */
    public static function register_settings() {
        \register_setting('kitgenix_captcha_for_cloudflare_turnstile_settings_group', self::OPTION_NAME, [
            'type' => 'array',
            'description' => \__('Kitgenix CAPTCHA for Cloudflare Turnstile Settings', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'show_in_rest' => false,
            'default' => [],
        ]);
    }

    /**
     * Sanitize the settings before saving.
     *
     * @param array $settings
     * @return array
     */
    public static function sanitize($settings) {
        // Nonce verification for CSRF protection
        $nonce = '';
        if (isset($_POST['kitgenix_captcha_for_cloudflare_turnstile_settings_nonce'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is being verified below
            $nonce = \sanitize_text_field( \wp_unslash( $_POST['kitgenix_captcha_for_cloudflare_turnstile_settings_nonce'] ) );
        }
        if (empty($nonce) || !function_exists('wp_verify_nonce') || !function_exists('get_option') || !\wp_verify_nonce($nonce, 'kitgenix_captcha_for_cloudflare_turnstile_settings_save')) {
            // If nonce is invalid, return previous settings to avoid wiping options
            if (function_exists('get_option')) {
                return \get_option(self::OPTION_NAME, []);
            } else {
                return [];
            }
        }
        $clean = [];

        // Site Key & Secret
        $clean['site_key'] = function_exists('sanitize_text_field') ? \sanitize_text_field($settings['site_key'] ?? '') : ($settings['site_key'] ?? '');
        $clean['secret_key'] = function_exists('sanitize_text_field') ? \sanitize_text_field($settings['secret_key'] ?? '') : ($settings['secret_key'] ?? '');

        // Integrations toggles
        $clean['enable_wordpress'] = !empty($settings['enable_wordpress']) ? 1 : 0;
        $clean['enable_woocommerce'] = !empty($settings['enable_woocommerce']) ? 1 : 0;
        $clean['enable_elementor'] = !empty($settings['enable_elementor']) ? 1 : 0;
        $clean['enable_wpforms'] = !empty($settings['enable_wpforms']) ? 1 : 0;
        $clean['enable_fluentforms'] = !empty($settings['enable_fluentforms']) ? 1 : 0;
        $clean['enable_gravityforms'] = !empty($settings['enable_gravityforms']) ? 1 : 0;
        $clean['enable_cf7'] = !empty($settings['enable_cf7']) ? 1 : 0;
        $clean['enable_formidableforms'] = !empty($settings['enable_formidableforms']) ? 1 : 0;
        $clean['enable_forminator'] = !empty($settings['enable_forminator']) ? 1 : 0;
        $clean['enable_jetpackforms'] = !empty($settings['enable_jetpackforms']) ? 1 : 0;
        // Per-form toggles for WordPress
        $clean['wp_login_form'] = !empty($settings['wp_login_form']) ? 1 : 0;
        $clean['wp_register_form'] = !empty($settings['wp_register_form']) ? 1 : 0;
        $clean['wp_lostpassword_form'] = !empty($settings['wp_lostpassword_form']) ? 1 : 0;
        $clean['wp_comments_form'] = !empty($settings['wp_comments_form']) ? 1 : 0;
        // Per-form toggles for WooCommerce
        $clean['wc_checkout_form'] = !empty($settings['wc_checkout_form']) ? 1 : 0;
        $clean['wc_login_form'] = !empty($settings['wc_login_form']) ? 1 : 0;
        $clean['wc_register_form'] = !empty($settings['wc_register_form']) ? 1 : 0;
        $clean['wc_lostpassword_form'] = !empty($settings['wc_lostpassword_form']) ? 1 : 0;

        // Display Settings
        $clean['theme'] = in_array($settings['theme'] ?? '', ['auto', 'light', 'dark'], true) ? $settings['theme'] : 'auto';
        $clean['language'] = function_exists('sanitize_text_field') ? \sanitize_text_field($settings['language'] ?? 'auto') : ($settings['language'] ?? 'auto');
        $clean['disable_submit'] = !empty($settings['disable_submit']);
        $clean['widget_size'] = in_array($settings['widget_size'] ?? '', ['small', 'medium', 'large', 'normal'], true) ? $settings['widget_size'] : 'normal';
        $clean['appearance'] = in_array($settings['appearance'] ?? '', ['always', 'interaction-only'], true) ? $settings['appearance'] : 'always';
        $clean['defer_scripts'] = !empty($settings['defer_scripts']);

        // Messages
        $clean['error_message'] = function_exists('sanitize_text_field') ? \sanitize_text_field($settings['error_message'] ?? '') : ($settings['error_message'] ?? '');
        $clean['extra_message'] = function_exists('sanitize_text_field') ? \sanitize_text_field($settings['extra_message'] ?? '') : ($settings['extra_message'] ?? '');

        // Whitelist
        $clean['whitelist_loggedin'] = !empty($settings['whitelist_loggedin']);
        // Sanitize IPs: only allow valid IPs, one per line
        $ips = array_filter(array_map('trim', explode("\n", $settings['whitelist_ips'] ?? '')), function($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP);
        });
        $clean['whitelist_ips'] = implode("\n", $ips);
        // Sanitize user agents: strip tags, trim, one per line
        $uas = array_filter(array_map('trim', explode("\n", $settings['whitelist_user_agents'] ?? '')), function($ua) {
            return !empty($ua);
        });
        $uas = array_map(function($ua) {
            return function_exists('sanitize_text_field') ? \sanitize_text_field($ua) : $ua;
        }, $uas);
        $clean['whitelist_user_agents'] = implode("\n", $uas);

        return $clean;
    }

    /**
     * Retrieve plugin settings (safe fallback).
     */
    public static function get_settings(): array {
        if (!function_exists('get_option')) {
            return [];
        }
        $settings = \get_option(self::OPTION_NAME, []);
        return is_array($settings) ? $settings : [];
    }
}
