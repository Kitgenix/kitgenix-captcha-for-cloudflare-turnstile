<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function esc_attr;
use function get_option;
use function sanitize_text_field;
use function wp_nonce_field;
use function wp_unslash;

class FluentForms {

    public static function init() {
        if ( ! ( defined('FLUENTFORM') || class_exists('FluentForm') ) || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        if ( empty($settings['enable_fluentforms']) ) {
            return;
        }

        // Inject Turnstile (field-based, before submit).
        add_filter('fluentform_rendering_field_data', [__CLASS__, 'inject_turnstile_field'], 10, 3);

        // Fallback: render container right above the submit button (covers custom layouts).
        add_action('fluentform/render_item_submit_button', [__CLASS__, 'render_turnstile_above_submit'], 5, 2);

        // Validation (AJAX-friendly).
        add_filter('fluentform_submit_validation', [__CLASS__, 'validate_turnstile_filter'], 10, 3);
    }

    /**
     * Insert Turnstile container + hidden token BEFORE the submit button.
     * Runs on the field array so it works with AJAX and multi-step forms.
     *
     * @param array $fields
     * @param array $form
     * @param int   $form_id
     * @return array
     */
    public static function inject_turnstile_field($fields, $form, $form_id) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $fields;
        }

        // Avoid duplicates if already injected.
        foreach ($fields as $f) {
            if (!empty($f['settings']['html']) && strpos($f['settings']['html'], 'class="cf-turnstile"') !== false) {
                return $fields;
            }
        }

        $hidden_input = [
            'element'  => 'custom_html',
            'settings' => [
                'label' => '',
                'html'  => '<input type="hidden" name="cf-turnstile-response" value="" />',
            ],
        ];

        $turnstile_field = [
            'element'  => 'custom_html',
            'settings' => [
                'label' => '',
                'html'  => '<div class="cf-turnstile"'
                    . ' data-sitekey="'    . esc_attr($site_key) . '"'
                    . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
                    . ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"'
                    . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
                    . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="fluentforms"></div>',
            ],
        ];

        // Insert both immediately before the submit field.
        foreach ($fields as $i => $field) {
            if (($field['element'] ?? '') === 'submit') {
                array_splice($fields, $i, 0, [$hidden_input, $turnstile_field]);
                return $fields;
            }
        }

        // If no submit field found, append.
        $fields[] = $hidden_input;
        $fields[] = $turnstile_field;
        return $fields;
    }

    /**
     * Fallback renderer right above the submit button (HTML echo).
     * Guarded per-form to avoid duplicates.
     *
     * @param array|object $item
     * @param array|object $form
     * @return void
     */
    public static function render_turnstile_above_submit($item, $form) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return;
        }

        $form_id = isset($form->id) ? (int) $form->id : (isset($form['id']) ? (int) $form['id'] : 0);
        static $rendered_for = [];
        if ($form_id && isset($rendered_for[$form_id])) {
            return;
        }
        if ($form_id) {
            $rendered_for[$form_id] = true;
        }

        // Our CSRF nonce (optional but harmless here).
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce');
        }

        // Hidden token + container (global public JS will render and populate).
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'    . esc_attr($site_key) . '"'
           . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
           . ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"'
           . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="fluentforms"></div>';
    }

    /**
     * AJAX-friendly validation hook. Returns errors array; no wp_die.
     *
     * @param array $errors
     * @param array $formData
     * @param array $form
     * @return array
     */
    public static function validate_turnstile_filter($errors, $formData, $form) {
        if ( self::request_method() !== 'POST' ) {
            return $errors;
        }

        $token = isset($_POST['cf-turnstile-response'])
            ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) )
            : '';

        // Validate token directly (no plugin nonce requirement for Fluent’s AJAX path).
        if ( ! $token || ! Turnstile_Validator::validate_token($token) ) {
            if ( ! is_array($errors) ) {
                $errors = [];
            }
            $errors['turnstile'] = Turnstile_Validator::get_error_message('fluentforms');
        }

        return $errors;
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

FluentForms::init();
