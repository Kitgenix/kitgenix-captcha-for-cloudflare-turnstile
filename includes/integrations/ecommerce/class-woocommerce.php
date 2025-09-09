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
use function sanitize_text_field;
use function wc_add_notice;
use function wp_nonce_field;
use function wp_unslash;

class WooCommerce {

    /**
     * Initialize integration.
     */
    public static function init() {
        if ( ! function_exists('is_woocommerce') || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);

        // Classic checkout (render once, right before "Place order" for consistency)
        if ( ! empty($settings['wc_checkout_form']) ) {
            add_action('woocommerce_review_order_before_submit', [__CLASS__, 'render_widget'], 9);
            add_action('woocommerce_checkout_process',            [__CLASS__, 'validate_turnstile']);
            add_action('woocommerce_after_checkout_validation',  [__CLASS__, 'validate_turnstile'], 10, 2);
        }

        // WooCommerce login (My Account)
        if ( ! empty($settings['wc_login_form']) ) {
            add_action('woocommerce_login_form', [__CLASS__, 'render_widget']);

            // Prefer modern hook (WP_Error), fall back to legacy if present.
            add_filter('woocommerce_process_login_errors', [__CLASS__, 'filter_login_errors'], 10, 2);
            add_filter('woocommerce_login_errors',         [__CLASS__, 'filter_login_errors_legacy']);
        }

        // Registration (My Account)
        if ( ! empty($settings['wc_register_form']) ) {
            add_action('woocommerce_register_form', [__CLASS__, 'render_widget']);
            add_action('woocommerce_register_post', [__CLASS__, 'validate_generic'], 9);
        }

        // Lost/reset password (My Account)
        if ( ! empty($settings['wc_lostpassword_form']) ) {
            // Primary hook used by WooCommerce when showing the lost-password form
            add_action('woocommerce_lostpassword_form', [__CLASS__, 'render_widget']);

            // Some WooCommerce versions/themes fire a slightly different action when
            // displaying the reset-password form. Add both common variants to be safe.
            add_action('woocommerce_resetpassword_form',     [__CLASS__, 'render_widget']);
            add_action('woocommerce_reset_password_form',    [__CLASS__, 'render_widget']);

            // Validation hook (server-side) when the reset is submitted.
            add_action('woocommerce_reset_password_validation', [__CLASS__, 'validate_generic']);
        }

        /**
         * Blocks checkout support – UI injection (PHP side)
         * Note: Full Blocks support needs a tiny JS bridge to include the token in Store API requests.
         * This filter places the container before the Place Order button in the Checkout Actions block.
         */
        add_filter('render_block_woocommerce/checkout-actions-block', [__CLASS__, 'blocks_inject_before_submit'], 10, 2);

        /**
         * Blocks checkout – server validation via REST pre-dispatch (works across Store API versions).
         * Intercept POST requests to /wc/store/*checkout* and require a valid token.
         * (Your public JS should send the token either as a request header or in the extensions payload.)
         */
        add_filter('rest_request_before_callbacks', [__CLASS__, 'blocks_rest_validate'], 10, 3);
    }

    /**
     * Output the Turnstile markup once per request:
     * - nonce (for our validator, harmless if unused)
     * - hidden token input (classic forms)
     * - container (global JS renders the widget)
     */
    public static function render_widget() {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';

        if ( ! $site_key ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">'
               . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' )
               . '</p>';
            return;
        }

        // Render once per request *per hook* to avoid duplicates while allowing
        // multiple widgets on the same page (for example WooCommerce's My Account
        // which can contain both login and register forms).
        static $rendered = [];
        $hook = function_exists('current_filter') ? current_filter() : 'global';
        if ( isset( $rendered[ $hook ] ) ) {
            return;
        }
        $rendered[ $hook ] = true;

        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token field (classic checkout + account forms will submit this).
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Container; global public JS renders Turnstile.
    echo '<div class="cf-turnstile"'
       . ' data-hook="'      . esc_attr( $hook ) . '"'
           . ' data-sitekey="'    . esc_attr($site_key) . '"'
           . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
           . ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"'
           . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="woocommerce"></div>';
    }

    /**
     * Classic checkout validation (runs for non-Blocks checkout).
     */
    public static function validate_turnstile() {
        static $added = false; // avoid duplicate notices when both checkout hooks fire
        if ( $added ) {
            return;
        }
        if ( ! Turnstile_Validator::is_valid_submission() ) {
            wc_add_notice( Turnstile_Validator::get_error_message('woocommerce'), 'error' );
            $added = true;
        }
    }

    /**
     * Generic validator for registration / reset flows (non-Blocks).
     */
    public static function validate_generic() {
        // If this is an admin-initiated user edit (eg. resetting a user's password
        // from wp-admin), skip Turnstile validation — there is no front-end widget.
        if ( is_admin() && function_exists('current_user_can') && current_user_can('edit_users') ) {
            return;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            wc_add_notice( Turnstile_Validator::get_error_message('woocommerce'), 'error' );
        }
    }

    /**
     * Login error filter (modern): woocommerce_process_login_errors
     * @param \WP_Error        $errors
     * @param \WP_User|false   $user
     * @return \WP_Error
     */
    public static function filter_login_errors($errors, $user) {
        if ( ! $errors instanceof \WP_Error ) {
            $errors = new \WP_Error();
        }
        if ( ! Turnstile_Validator::is_valid_submission() ) {
            $errors->add( 'turnstile_error', Turnstile_Validator::get_error_message('woocommerce') );
        }
        return $errors;
    }

    /**
     * Legacy login errors filter (string/HTML in older WooCommerce).
     * If the param is a WP_Error, treat it like modern; otherwise prepend a message blob.
     * @param mixed $error
     * @return mixed
     */
    public static function filter_login_errors_legacy($error) {
        if ( $error instanceof \WP_Error ) {
            return self::filter_login_errors($error, null);
        }
        if ( ! Turnstile_Validator::is_valid_submission() ) {
            $msg  = '<strong>' . esc_html__( 'Error:', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</strong> ';
            $msg .= esc_html( Turnstile_Validator::get_error_message('woocommerce') );
            // Prepend our message to existing HTML/string.
            $error = $msg . ( $error ? '<br>' . $error : '' );
        }
        return $error;
    }

    /**
     * WooCommerce Blocks: inject container before the Place Order button.
     * (Pure PHP injection; JS must still forward the token to the Store API.)
     *
     * @param string $content Rendered block HTML
     * @param array  $block   Block array (contains blockName, attrs, etc.)
     * @return string
     */
    public static function blocks_inject_before_submit($content, $block) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key || ! is_string($content) || $content === '' ) {
            return $content;
        }

        // Avoid duplicates if already present.
        if ( strpos($content, 'class="cf-turnstile"') !== false ) {
            return $content;
        }

        // Build markup (no nonce here; Blocks POST via Store API, not a classic form).
        $injection  = '<div class="cf-turnstile"';
        $injection .= ' data-sitekey="'    . esc_attr($site_key) . '"';
        $injection .= ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"';
        $injection .= ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"';
        $injection .= ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"';
        $injection .= ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="woocommerce-blocks"></div>';

        // Insert before first submit button if possible; otherwise append.
        if ( preg_match('/(<button[^>]+type=["\']submit["\'][^>]*>)/i', $content) ) {
            return preg_replace('/(<button[^>]+type=["\']submit["\'][^>]*>)/i', $injection . '$1', $content, 1);
        }

        return $content . $injection;
    }

    /**
     * WooCommerce Blocks / Store API validation (server-side).
     * Intercepts REST requests to /wc/store/*checkout* and requires a valid token.
     *
     * Your front-end should send the token either:
     *  - in the request body under extensions.kitgenix_captcha_for_cloudflare_turnstile_turnstile.token, OR
     *  - in the header: "X-Turnstile-Token: <token>"
     *
     * @param mixed                 $response Current pre-dispatch result.
     * @param array|\WP_REST_Server $handler
     * @param \WP_REST_Request      $request
     * @return mixed
     */
    public static function blocks_rest_validate($response, $handler, $request) {
        // Only handle POSTs to Store API checkout routes.
        if ( ! ( $request instanceof \WP_REST_Request ) || $request->get_method() !== 'POST' ) {
            return $response;
        }
        $route = $request->get_route();
        if ( strpos($route, '/wc/store') === false || strpos($route, 'checkout') === false ) {
            return $response;
        }

        // Try to read token from extensions payload first, then from header.
        $params = (array) $request->get_json_params();
        $token  = '';

        if ( isset($params['extensions']['kitgenix_captcha_for_cloudflare_turnstile_turnstile']['token']) ) {
            $token = (string) $params['extensions']['kitgenix_captcha_for_cloudflare_turnstile_turnstile']['token'];
        } elseif ( $request->get_header('X-Turnstile-Token') ) {
            $token = (string) $request->get_header('X-Turnstile-Token');
        } elseif ( isset($_POST['cf-turnstile-response']) ) {
            // Very rare with Blocks, but keep as last resort.
            $token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) );
        }

        if ( ! $token || ! Turnstile_Validator::validate_token($token) ) {
            return new \WP_Error(
                'turnstile_failed',
                Turnstile_Validator::get_error_message('woocommerce'),
                [ 'status' => 403 ]
            );
        }

        return $response;
    }
}
