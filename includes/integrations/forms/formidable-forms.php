<?php
// Formidable Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
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

class FormidableForms {
    public static function init() {
        if ( ! class_exists('FrmForm') || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        if ( empty($settings['enable_formidableforms']) ) {
            return;
        }

        // Inject widget before submit button.
        add_filter('frm_submit_button_html', [__CLASS__, 'inject_turnstile'], 10, 2);

        // Validate on submit.
        add_action('frm_validate_entry', [__CLASS__, 'validate_turnstile'], 10, 3);
    }

    /**
     * Prepend Turnstile markup before the submit button.
     * Adds nonce + hidden token field. Guarded per-form to avoid duplicates.
     *
     * @param string $button Submit button HTML (raw string from Formidable).
     * @param array  $form   Form config array.
     * @return string
     */
    public static function inject_turnstile($button, $form) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $button;
        }

        // Respect per-integration mode: when shortcode-only is selected, process
        // other shortcodes inside the button/form HTML so manual [kitgenix_turnstile]
        // placements render correctly. We do not auto-inject here.
        $mode = $settings['mode_formidableforms'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            if ( function_exists( 'do_shortcode' ) ) {
                return \do_shortcode( (string) $button );
            }
            return $button;
        }

        // If the button or form already contains a rendered widget container, do nothing.
        // Ignore literal shortcode tokens so auto-mode isn't blocked by leftover shortcode text.
        if ( \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $button, false )
            || \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $form, false ) ) {
            return $button;
        }

        $form_id = isset($form['id']) ? (int) $form['id'] : 0;

        // Guard: avoid duplicate injection per form.
        static $rendered_for = [];
        if ( $form_id && isset($rendered_for[$form_id]) ) {
            return $button;
        }
        if ( $form_id ) {
            $rendered_for[$form_id] = true;
        }

        ob_start();

        // Our nonce for Turnstile_Validator::is_valid_submission().
        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token input; populated by public JS once the challenge is solved.
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Turnstile container; rendered by the global public script.
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'    . esc_attr($site_key) . '"'
           . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
           . ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"'
           . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="formidable"></div>';

        $widget = ob_get_clean();

        // Return widget + original button markup verbatim.
        return $widget . $button;
    }

    /**
     * Validate Turnstile on submit.
     *
     * @param array $errors   Collects validation errors.
     * @param array $values   Submitted values.
     * @param int   $form_id  Form ID.
     * @return array
     */
    public static function validate_turnstile($errors, $values, $form_id) {
        // Validate only on POST.
        if ( self::request_method() !== 'POST' ) {
            return $errors;
        }

        if ( ! is_array($errors) ) {
            $errors = [];
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            // Top-level form error so Formidable displays it prominently.
            $errors['form'] = Turnstile_Validator::get_error_message('formidableforms');
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

FormidableForms::init();
