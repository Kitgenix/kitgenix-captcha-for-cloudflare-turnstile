<?php
// WPForms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use function add_action;
use function get_option;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function __;
use function wp_nonce_field;
use function wpforms;
use function sanitize_text_field;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function apply_filters;

defined('ABSPATH') || exit;

class WPForms {
    public static function init() {
        if (!class_exists('WPForms') || Whitelist::is_whitelisted()) {
            return;
        }
        // Add Turnstile widget to WPForms frontend forms (after fields and before submit for compatibility)
        add_action('wpforms_display_after_fields', [__CLASS__, 'render_widget'], 10, 2);
        add_action('wpforms_display_submit_before', [__CLASS__, 'render_widget'], 10, 2);
        // Validate Turnstile on submit
        add_action('wpforms_process', [__CLASS__, 'validate_turnstile'], 9, 3);
    }

    public static function render_widget($fields, $form_data = null) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        // Debug marker for output
        echo '<!-- KitgenixCaptchaForCloudflareTurnstile: WPForms Turnstile widget output (render_widget called) -->';
        if (!$site_key) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">' . esc_html__('Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile') . '</p>';
            return;
        }
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce');
        }
        // Add a visible marker for debugging
        echo '<div class="cf-turnstile kitgenix-captcha-for-cloudflare-turnstile-wpforms-marker" data-sitekey="' . esc_attr($site_key) . '" data-theme="' . esc_attr($settings['theme'] ?? 'auto') . '" data-size="' . esc_attr($settings['widget_size'] ?? 'normal') . '" data-appearance="' . esc_attr($settings['appearance'] ?? 'always') . '"></div>';
    }

    public static function validate_turnstile($fields, $entry, $form_data) {
        if (!Turnstile_Validator::is_valid_submission()) {
            wpforms()->process->errors[$form_data['id']]['footer'] = Turnstile_Validator::get_error_message('wpforms');
        }
    }
}

WPForms::init();
