<?php
// Jetpack Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use function add_action;
use function add_filter;
use function get_option;
use function esc_attr;
use function esc_html__;
use function wp_nonce_field;
use function sanitize_text_field;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function apply_filters;
use function esc_html;
use function wp_kses_post;

defined('ABSPATH') || exit;

class JetpackForms {
    public static function init() {
        if (!class_exists('Jetpack') || Whitelist::is_whitelisted()) {
            return;
        }
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        if (empty($settings['enable_jetpackforms'])) {
            return;
        }
        // Inject widget before submit button (new Jetpack filter)
        add_filter('jetpack_contact_form_html', [__CLASS__, 'inject_turnstile_markup'], 10, 1);
        // Validate on submit
        add_action('jetpack_pre_message_sent', [__CLASS__, 'validate_turnstile'], 10, 1);
    }

    /**
     * Inject Turnstile widget markup into Jetpack form HTML.
     *
     * @param string $html Full form HTML.
     * @return string Modified HTML.
     */
    public static function inject_turnstile_markup($html) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if (! $site_key) return wp_kses_post($html);

        ob_start();
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce');
        }

        $turnstile_html = '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '" 
            data-theme="' . esc_attr($settings['theme'] ?? 'auto') . '" 
            data-size="' . esc_attr($settings['widget_size'] ?? 'normal') . '" 
            data-appearance="' . esc_attr($settings['appearance'] ?? 'always') . '"></div>';

        $injection = ob_get_clean() . $turnstile_html;

        // Try to inject before the submit button, fallback to end of form
        $needle = '<button class="wp-block-button__link';
        $pos = strpos($html, $needle);
        if ($pos !== false) {
            $html = substr_replace($html, $injection, $pos, 0);
        } else {
            $html = str_replace('</form>', $injection . '</form>', $html);
        }

        return wp_kses_post($html);
    }

    public static function validate_turnstile($form) {
        if (!Turnstile_Validator::is_valid_submission()) {
            // Jetpack forms: add error to global $errors
            global $errors;
            if (!is_array($errors)) $errors = [];
            $errors[] = Turnstile_Validator::get_error_message('jetpackforms');
        }
    }
}

JetpackForms::init();
