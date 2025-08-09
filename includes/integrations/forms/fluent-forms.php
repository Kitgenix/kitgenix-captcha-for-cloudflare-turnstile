<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

class FluentForms {
    public static function init() {
        // error_log('[KitgenixCaptchaForCloudflareTurnstile] FluentForms::init() called.'); // Removed debug log
        if (!(defined('FLUENTFORM') || class_exists('FluentForm')) || \KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist::is_whitelisted()) {
            return;
        }

        // Inject Turnstile widget before submit button (field-based)
        \add_filter('fluentform_rendering_field_data', [__CLASS__, 'inject_turnstile_field'], 10, 3);
        // Fallback: Render Turnstile above submit button for 100% placement control
        \add_action('fluentform/render_item_submit_button', [__CLASS__, 'render_turnstile_above_submit'], 5, 2);

        // Validate Turnstile on submission
        \add_action('fluentform_before_insert_submission', [__CLASS__, 'validate_turnstile'], 10, 3);
        \add_filter('fluentform_submit_validation', [__CLASS__, 'validate_turnstile_filter'], 10, 3);
    }

    public static function inject_turnstile_field($fields, $form, $form_id) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        $enabled = !empty($settings['enable_fluentforms']);
        if (!$enabled || !$site_key) return $fields;

        $turnstile_field = [
            'element' => 'custom_html',
            'settings' => [
                'label' => '',
                'html' => '<div class="cf-turnstile" data-sitekey="' . \esc_attr($site_key) . '" 
                    data-theme="' . \esc_attr($settings['theme'] ?? 'auto') . '" 
                    data-size="' . \esc_attr($settings['widget_size'] ?? 'normal') . '" 
                    data-appearance="' . \esc_attr($settings['appearance'] ?? 'always') . '"></div>'
            ]
        ];

        // Insert before the submit button
        foreach ($fields as $i => $field) {
            if (($field['element'] ?? '') === 'submit') {
                array_splice($fields, $i, 0, [$turnstile_field]);
                return $fields;
            }
        }
        // If no submit button found, append at end
        $fields[] = $turnstile_field;
        return $fields;
    }

    public static function validate_turnstile($insertData, $data, $form) {
        if (!\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator::is_valid_submission(false)) {
            \wp_die(\esc_html(\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator::get_error_message('fluentforms')), 403);
        }
    }

    public static function validate_turnstile_filter($errors, $formData, $form) {
        // Nonce verification for security
        $nonce = '';
        if (isset($_POST['_wpnonce'])) {
            $nonce = \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) );
        }
        if (empty($nonce) || !\wp_verify_nonce($nonce, 'fluentform_submit')) {
            $errors['turnstile'] = \__('Security check failed. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
            return $errors;
        }
        $token = isset($_POST['cf-turnstile-response']) ? \sanitize_text_field(\wp_unslash($_POST['cf-turnstile-response'])) : '';
        if (!\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator::validate_token($token)) {
            $errors['turnstile'] = \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator::get_error_message('fluentforms');
        }
        return $errors;
    }

    public static function render_turnstile_above_submit($item, $form) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        $enabled = !empty($settings['enable_fluentforms']);
        if (!$enabled || !$site_key) return;

        echo '<div class="cf-turnstile" data-sitekey="' . \esc_attr($site_key) . '" 
            data-theme="' . \esc_attr($settings['theme'] ?? 'auto') . '" 
            data-size="' . \esc_attr($settings['widget_size'] ?? 'normal') . '" 
            data-appearance="' . \esc_attr($settings['appearance'] ?? 'always') . '"></div>';
    }
}

FluentForms::init();
