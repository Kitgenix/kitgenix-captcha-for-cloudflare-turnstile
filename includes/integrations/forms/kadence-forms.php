<?php
// Kadence Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
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

defined('ABSPATH') || exit;

class KadenceForms {
    public static function init() {
        if (!class_exists('Kadence_Blocks_Form') || Whitelist::is_whitelisted()) {
            return;
        }
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        if (empty($settings['enable_kadenceforms'])) {
            return;
        }
        // Inject widget before submit button
        add_filter('kadence_blocks_form_submit_button_html', [__CLASS__, 'inject_turnstile'], 10, 2);
        // Validate on submit
        add_filter('kadence_blocks_form_pre_process', [__CLASS__, 'validate_turnstile'], 10, 2);
    }

    public static function inject_turnstile($button_html, $form_id) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if (!$site_key) return $button_html;
        $widget = '';
        if (function_exists('wp_nonce_field')) {
            ob_start();
            wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce');
            $widget .= ob_get_clean();
        }
        $widget .= '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '" data-theme="' . esc_attr($settings['theme'] ?? 'auto') . '" data-size="' . esc_attr($settings['widget_size'] ?? 'normal') . '" data-appearance="' . esc_attr($settings['appearance'] ?? 'always') . '"></div>';
        return $widget . $button_html;
    }

    public static function validate_turnstile($should_process, $form_instance) {
        if (!Turnstile_Validator::is_valid_submission()) {
            if (is_array($should_process)) {
                $should_process['error'] = Turnstile_Validator::get_error_message('kadenceforms');
                return $should_process;
            }
            return false;
        }
        return $should_process;
    }
}

KadenceForms::init();
