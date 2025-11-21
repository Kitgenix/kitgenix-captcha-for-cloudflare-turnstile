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

        // If shortcode-only mode is selected for FluentForms, ensure shortcodes inside
        // custom HTML field content are processed when Fluent renders fields.
        $mode = $settings['mode_fluentforms'] ?? 'auto';
        if ( $mode === 'shortcode' && function_exists( 'add_filter' ) ) {
            add_filter( 'fluentform_rendering_field_data', [ __CLASS__, 'process_shortcodes_in_fluent_field' ], 9, 3 );
        }

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

        // Respect per-integration mode: skip auto-inject if shortcode-only is selected.
        $mode = $settings['mode_fluentforms'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            return $fields;
        }

        // Avoid duplicates if already injected or a rendered widget container is present in fields/form.
        // We deliberately ignore literal shortcode tokens here (pass false) so auto-mode isn't blocked
        // by users leaving the shortcode text in form content.
        if ( \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $fields, false )
            || \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $form, false ) ) {
            return $fields;
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

        // Respect per-integration mode: skip auto-inject if shortcode-only is selected.
        $mode = $settings['mode_fluentforms'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
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
     * Process potential shortcodes inside FluentForms field HTML.
     *
     * @param array $field
     * @param array|object $form
     * @param int $form_id
     * @return array
     */
    public static function process_shortcodes_in_fluent_field( $field, $form, $form_id ) {
        if ( ! is_array( $field ) ) {
            return $field;
        }
        if ( ! function_exists( 'do_shortcode' ) ) {
            return $field;
        }

        if ( isset( $field['settings']['html'] ) && is_string( $field['settings']['html'] ) && strpos( $field['settings']['html'], '[' ) !== false ) {
            $field['settings']['html'] = \do_shortcode( $field['settings']['html'] );
        }

        return $field;
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

        // Prefer Fluent Forms' provided $formData array instead of direct
        // $_POST access — this avoids PHPCS nonce warnings for POST reads
        // while still allowing Fluent's AJAX validation path to provide the token.
        $token = '';
        if ( is_array( $formData ) && isset( $formData['cf-turnstile-response'] ) ) {
            $token = sanitize_text_field( (string) $formData['cf-turnstile-response'] );
        } elseif ( isset( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) ) {
            // Also support header-style tokens (fetch/Blocks flows).
            $token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) );
        }

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
