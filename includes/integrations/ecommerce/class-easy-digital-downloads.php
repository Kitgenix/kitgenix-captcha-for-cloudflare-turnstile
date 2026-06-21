<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function esc_attr;
use function esc_html__;
use function get_option;
use function wp_nonce_field;
use function edd_set_error;

class Easy_Digital_Downloads {

    /**
     * Initialize integration.
     */
    public static function init() {
        // Bail if EDD is not present or the request is whitelisted.
        if ( ( ! class_exists('Easy_Digital_Downloads') && ! defined('EDD_VERSION') ) || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);

        // Checkout form (purchase form)
        if ( ! empty($settings['edd_checkout_form']) ) {
            // Render widget near the Complete Purchase button.
            add_action('edd_purchase_form_before_submit', [__CLASS__, 'render_widget']);

            // Validate on checkout submission (skip replay protection to allow retries).
            add_action('edd_checkout_error_checks', [__CLASS__, 'validate_checkout'], 10, 2);

            // Mark token as used after successful purchase.
            add_action('edd_complete_purchase', [__CLASS__, 'mark_token_used_after_purchase']);
        }

        // Login form (front-end EDD login, including checkout login area).
        if ( ! empty($settings['edd_login_form']) ) {
            // Inject widget into the login form markup so it appears above the submit button.
            add_filter('edd_login_form', [__CLASS__, 'filter_login_form']);
            add_action('edd_process_login_form', [__CLASS__, 'validate_generic']);
        }

        // Registration form (EDD register form and checkout registration area).
        if ( ! empty($settings['edd_register_form']) ) {
            add_action('edd_register_fields_after', [__CLASS__, 'render_widget']);
            add_action('edd_process_register_form', [__CLASS__, 'validate_generic']);
        }

        // Profile editor / account form (optional; only when enabled).
        if ( ! empty($settings['edd_profile_form']) ) {
            // Place the widget inside the profile form, just before the submit button fieldset.
            // Template hook reference: edd_profile_editor_after_password_fields fires right above
            // <fieldset id="edd_profile_submit_fieldset"> in shortcode-profile-editor.php.
            add_action('edd_profile_editor_after_password_fields', [__CLASS__, 'render_widget']);
            add_action('edd_pre_update_user_profile', [__CLASS__, 'validate_generic']);
        }
    }

    /**
     * Render the Turnstile widget and hidden token field.
     */
    public static function render_widget() {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';

        // Respect per-integration mode: allow shortcode-only placement.
        $mode = $settings['mode_edd'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            return;
        }

        if ( ! $site_key ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">'
               . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' )
               . '</p>';
            return;
        }

        // Ensure we only render once per hook per request.
        static $rendered = [];
        $hook = function_exists('current_filter') ? current_filter() : 'global';
        if ( isset($rendered[$hook]) ) {
            return;
        }
        $rendered[$hook] = true;

        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token field consumed by Turnstile_Validator::is_valid_submission().
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        echo '<div class="cf-turnstile"'
           . ' data-hook="'      . esc_attr($hook) . '"'
           . ' data-sitekey="'    . esc_attr($site_key) . '"'
           . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
           . ' data-size="'       . esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
           . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="edd"></div>';
    }

    /**
     * Checkout validation callback.
     * Skip replay protection to allow retries due to payment/shipping validation.
     * Mark token as used after successful purchase via edd_complete_purchase hook.
     *
     * @param array $valid_data Validated data (unused).
     * @param array $post_data  Raw POST data (unused).
     */
    public static function validate_checkout($valid_data, $post_data) {
        if ( ! Turnstile_Validator::is_valid_submission( true, 'edd-checkout', false ) ) {
            edd_set_error('turnstile_failed', Turnstile_Validator::get_error_message('edd'));
        }
    }

    /**
     * After successful purchase, mark the Turnstile token as used.
     * This prevents the token from being reused even if the customer comes back to the success page.
     */
    public static function mark_token_used_after_purchase() {
        Turnstile_Validator::mark_submission_token_used();
    }

    // Note: profile editor widget is injected via the
    // 'edd_profile_editor_after_password_fields' action to keep placement
    // inside the form and directly above the Save Changes button.

    /**
     * Filter the rendered EDD login form HTML to inject the widget
     * immediately above the login submit button.
     *
     * @param string $html Login form HTML.
     * @return string
     */
    public static function filter_login_form($html) {
        if ( ! is_string($html) || $html === '' ) {
            return $html;
        }

        // Build widget markup using the same renderer used for other EDD forms.
        ob_start();
        self::render_widget();
        $widget = trim(ob_get_clean());

        if ( $widget === '' ) {
            return $html;
        }

        // Avoid duplicates if a Turnstile container already exists or shortcode is present.
        if ( strpos($html, 'class="cf-turnstile"') !== false
            || \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $html, false ) ) {
            return $html;
        }

        // Insert widget immediately before the login submit <p> wrapper.
        $pattern = '/(<p\s+class=["\']edd-login-submit["\'][^>]*>)/i';
        if ( preg_match($pattern, $html) ) {
            return preg_replace($pattern, $widget . '$1', $html, 1);
        }

        // Fallback: prepend widget at the top of the form.
        return $widget . $html;
    }

    /**
     * Generic validator for login/register/profile flows.
     */
    public static function validate_generic() {
        if ( ! Turnstile_Validator::is_valid_submission( true, 'edd-account' ) ) {
            edd_set_error('turnstile_failed', Turnstile_Validator::get_error_message('edd'));
        }
    }
}
