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

            // NOTE: EDD's own edd_process_login_form() calls edd_log_user_in() – and therefore
            // wp_signon() – unconditionally, with NO edd_get_errors() check beforehand (only
            // AFTER, to decide whether to redirect). `edd_process_login_form` is also a plain
            // function name, not an action EDD ever fires – add_action() on it silently never
            // runs. Calling edd_set_error() from any real EDD hook on this path cannot block
            // the login. Instead, hook WordPress core's own `authenticate` filter (same
            // mechanism wp_signon()/wp_authenticate() itself consults) so a WP_Error returned
            // here is authoritative and wp_signon() cannot proceed to log the user in – scoped
            // to EDD's own login form via its dedicated 'edd_login_nonce' field so this never
            // interferes with wp-login.php's native form or other integrations.
            add_filter('authenticate', [__CLASS__, 'validate_login'], 30, 3);
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

        // Zero-JS honeypot trap (empty markup when the setting is off)
        echo \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::render_honeypot_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler.

        echo '<div class="cf-turnstile"'
           . ' data-hook="'      . esc_attr($hook) . '"'
           . \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::get_widget_data_attributes( 'edd', $site_key ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler.
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
     * Validate Turnstile for EDD's own login form via WordPress core's `authenticate` filter.
     *
     * Scoped to EDD's login submission via its own dedicated 'edd_login_nonce' field (the
     * exact nonce field edd_process_login_form() itself checks) so this never affects
     * wp-login.php's native form or unrelated authenticate-filter callbacks. Returning a
     * WP_Error here is authoritative: wp_signon()/wp_authenticate() will not treat the
     * login as successful, and edd_log_user_in() checks `$user instanceof WP_User` before
     * setting the current user – a WP_Error fails that check, so the login is genuinely
     * blocked, not just logged as an error.
     *
     * @param \WP_User|\WP_Error|null $user
     * @param string                  $username
     * @param string                  $password
     * @return \WP_User|\WP_Error|null
     */
    public static function validate_login( $user, $username, $password ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only, used to scope this handler to EDD's own login form; the nonce value itself is verified by is_valid_submission() (our nonce) and by edd_process_login_form() (EDD's own 'edd-login-nonce' action).
        if ( ! isset( $_POST['edd_login_nonce'] ) ) {
            return $user;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'edd-account' ) ) {
            return new \WP_Error( 'turnstile_failed', esc_html( Turnstile_Validator::get_error_message( 'edd' ) ) );
        }
        return $user;
    }

    /**
     * Generic validator for register/profile flows.
     */
    public static function validate_generic() {
        if ( ! Turnstile_Validator::is_valid_submission( true, 'edd-account' ) ) {
            edd_set_error('turnstile_failed', Turnstile_Validator::get_error_message('edd'));
        }
    }
}
