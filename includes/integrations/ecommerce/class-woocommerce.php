<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce;

use KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler;
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
            // Mark token as used after successful order creation
            add_action('woocommerce_thankyou', [__CLASS__, 'mark_token_used_after_checkout']);
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
            // Use woocommerce_registration_errors filter so validation errors are added
            // to the WP_Error object that WooCommerce checks to block registration.
            // woocommerce_register_post + wc_add_notice() only queues a notice and does
            // NOT prevent the account from being created – a critical bypass vector.
            add_filter('woocommerce_registration_errors', [__CLASS__, 'validate_wc_registration_errors'], 9, 3);
        }

        // Lost/reset password (My Account)
        if ( ! empty($settings['wc_lostpassword_form']) ) {
            // Primary hook used by WooCommerce when showing the lost-password form
            add_action('woocommerce_lostpassword_form', [__CLASS__, 'render_widget']);

            // Some WooCommerce versions/themes fire a slightly different action when
            // displaying the reset-password form. Add both common variants to be safe.
            add_action('woocommerce_resetpassword_form',     [__CLASS__, 'render_widget']);
            add_action('woocommerce_reset_password_form',    [__CLASS__, 'render_widget']);

            // Validation hook (server-side) when the NEW password is submitted (2nd step).
            // Pass 2 args so we receive the WP_Error object and can add errors to it directly.
            add_action('woocommerce_reset_password_validation', [__CLASS__, 'validate_wc_reset_password'], 10, 2);

            // The "request a reset email" step (1st step) does not have a WooCommerce-specific
            // validation hook: WC_Form_Handler::process_lost_password() delegates straight to
            // WordPress core's retrieve_password(), which fires the shared `lostpassword_post`
            // action. Without this, the widget above renders and collects a token, but nothing
            // ever verifies it – the reset email sends regardless of the Turnstile result. Only
            // register our own handler here when the separate "WordPress Core → Lost password"
            // toggle ISN'T already covering that same shared hook, to avoid validating the same
            // submission twice; validate_wc_lostpassword_request() only ever acts on submissions
            // that carry WooCommerce's own `wc_reset_password` marker field, so it never touches
            // wp-login.php's native lost-password form.
            if ( empty( $settings['enable_wordpress'] ) || empty( $settings['wp_lostpassword_form'] ) ) {
                add_action( 'lostpassword_post', [ __CLASS__, 'validate_wc_lostpassword_request' ], 10, 2 );
            }
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

        // Store a verification marker on orders created through Checkout Blocks.
        add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'annotate_blocks_order'], 10, 2);
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

        // Respect per-integration mode: allow admins to disable auto-inject and use shortcode-only placement.
        $mode = $settings['mode_woocommerce'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            return;
        }

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

        // Zero-JS honeypot trap (empty markup when the setting is off)
        echo Script_Handler::render_honeypot_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler.

        // Container; global public JS renders Turnstile.
        echo '<div class="cf-turnstile"'
       . ' data-hook="'      . esc_attr( $hook ) . '"'
           . Script_Handler::get_widget_data_attributes( 'woocommerce', $site_key ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler.
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="woocommerce"></div>';
    }

    /**
     * Classic checkout validation (runs for non-Blocks checkout).
     * Skip replay protection here to allow retries due to payment/shipping validation.
     * Mark token as used after successful order creation via woocommerce_thankyou hook.
     */
    public static function validate_turnstile() {
        static $validated = false; // ensure one validation pass per request
        if ( $validated ) {
            return;
        }
        $validated = true;

        if ( ! Turnstile_Validator::is_valid_submission( true, 'woocommerce-checkout', false ) ) {
            wc_add_notice( Turnstile_Validator::get_error_message('woocommerce'), 'error' );
        }
    }

    /**
     * Validate WooCommerce registration form (My Account).
     *
     * Hook: woocommerce_registration_errors (filter, 3 args)
     * Errors added to the returned WP_Error object are checked by WooCommerce
     * immediately after this filter and will block account creation.
     *
     * @param \WP_Error $errors
     * @param string    $username
     * @param string    $email
     * @return \WP_Error
     */
    public static function validate_wc_registration_errors( $errors, $username, $email ) {
        if ( ! $errors instanceof \WP_Error ) {
            $errors = new \WP_Error();
        }
        if ( is_admin() && function_exists( 'current_user_can' ) && current_user_can( 'edit_users' ) ) {
            return $errors;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'woocommerce-register' ) ) {
            $errors->add( 'turnstile_error', Turnstile_Validator::get_error_message( 'woocommerce' ) );
        }
        return $errors;
    }

    /**
     * Validate WooCommerce reset-password form (My Account).
     *
     * Hook: woocommerce_reset_password_validation (action, 2 args)
     * WP_Error objects are passed as object handles; calling ->add() on $errors
     * is reflected in the caller without a separate return.
     *
     * @param \WP_Error        $errors
     * @param \WP_User|mixed   $user
     */
    public static function validate_wc_reset_password( $errors, $user ) {
        if ( is_admin() && function_exists( 'current_user_can' ) && current_user_can( 'edit_users' ) ) {
            return;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'woocommerce-account' ) ) {
            if ( $errors instanceof \WP_Error ) {
                $errors->add( 'turnstile_error', Turnstile_Validator::get_error_message( 'woocommerce' ) );
            } else {
                wc_add_notice( Turnstile_Validator::get_error_message( 'woocommerce' ), 'error' );
            }
        }
    }

    /**
     * Validate WooCommerce's "request a reset email" step (My Account → Lost password, 1st step).
     *
     * Hook: lostpassword_post (WP core action, 2 args) – this is the SAME hook wp-login.php's
     * native lost-password form triggers, since WC_Form_Handler::process_lost_password()
     * delegates to WordPress core's retrieve_password(). Gated on WooCommerce's own
     * `wc_reset_password` marker field so this only ever validates submissions that actually
     * came from WooCommerce's My Account form (the only place that field is rendered) –
     * wp-login.php's native form is left to the separate WordPress Core integration/setting.
     *
     * @param \WP_Error      $errors
     * @param \WP_User|mixed $user_data
     */
    public static function validate_wc_lostpassword_request( $errors, $user_data ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only, used to scope this handler to WooCommerce's own form; WC's own nonce ('lost_password') and our Turnstile nonce are verified by is_valid_submission()/WC_Form_Handler itself.
        if ( ! isset( $_POST['wc_reset_password'] ) ) {
            return;
        }
        if ( ! ( $errors instanceof \WP_Error ) ) {
            return;
        }
        if ( is_admin() && function_exists( 'current_user_can' ) && current_user_can( 'edit_users' ) ) {
            return;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'woocommerce-account' ) ) {
            $errors->add( 'turnstile_error', Turnstile_Validator::get_error_message( 'woocommerce' ) );
        }
    }

    /**
     * After successful order creation in classic checkout, mark the Turnstile token as used.
     * This prevents the token from being reused even if the customer comes back to the thank you page.
     *
     * @param int $order_id The WooCommerce order ID.
     */
    public static function mark_token_used_after_checkout($order_id) {
        // Mark the token used only on the thank you page (after successful payment/order)
        Turnstile_Validator::mark_submission_token_used();
    }

    /**
     * Run (and memoize) the login Turnstile check once per request. WooCommerce fires both
     * the modern `woocommerce_process_login_errors` and legacy `woocommerce_login_errors`
     * filters for the same login attempt on some versions/themes; without this guard the
     * second call would re-validate the SAME token, find it already marked used by the
     * first (successful) call's replay protection, and incorrectly report a failure –
     * blocking a legitimate login. One real check per request, reused by both filters.
     *
     * @return bool
     */
    private static function login_validation_result(): bool {
        static $result = null;
        if ( $result === null ) {
            $result = Turnstile_Validator::is_valid_submission( true, 'woocommerce-login' );
        }
        return $result;
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
        if ( ! self::login_validation_result() ) {
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
        if ( ! self::login_validation_result() ) {
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

        // Respect per-integration mode for Blocks: if admin chose shortcode-only, skip auto-inject here.
        $mode_blocks = $settings['mode_woocommerce_blocks'] ?? 'auto';
        if ( $mode_blocks === 'shortcode' ) {
            return $content;
        }

        // Avoid duplicates if already present or a rendered widget/container is present in the block content.
        // Ignore literal shortcode tokens so auto-mode isn't blocked by leftover shortcode text.
        if ( strpos($content, 'class="cf-turnstile"') !== false
            || \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $content, false ) ) {
            return $content;
        }

        // Build markup (no nonce/honeypot here; Blocks POST via the Store API's JSON
        // body, not a classic form, so a hidden $_POST-based honeypot field would
        // never reach the server and isn't rendered for this path).
        $injection  = '<div class="cf-turnstile"';
        $injection .= Script_Handler::get_widget_data_attributes( 'woocommerce_blocks', $site_key );
        $injection .= ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="woocommerce-blocks"></div>';

        // Prefer inserting above the Place Order UI in Blocks checkout.
        // Case 1: Standard submit button
        if ( preg_match('/(<button[^>]+type=["\']submit["\'][^>]*>)/i', $content) ) {
            return preg_replace('/(<button[^>]+type=["\']submit["\'][^>]*>)/i', $injection . '$1', $content, 1);
        }

        // Case 2: Blocks "Place Order" text element inside div
        if ( preg_match('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-place-order-button__text[^"\']*["\'][^>]*>)/i', $content) ) {
            return preg_replace('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-place-order-button__text[^"\']*["\'][^>]*>)/i', $injection . '$1', $content, 1);
        }

        // Case 3: Before the primary Place Order button wrapper
        if ( preg_match('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-place-order-button[^"\']*["\'][^>]*>)/i', $content) ) {
            return preg_replace('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-place-order-button[^"\']*["\'][^>]*>)/i', $injection . '$1', $content, 1);
        }

        // Case 4: Generic place-order container used in some versions/themes
        if ( preg_match('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-place-order[^"\']*["\'][^>]*>)/i', $content) ) {
            return preg_replace('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-place-order[^"\']*["\'][^>]*>)/i', $injection . '$1', $content, 1);
        }

        // Fallback A: try to inject before the actions wrapper if present
        if ( preg_match('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-actions[^"\']*["\'][^>]*>)/i', $content) ) {
            return preg_replace('/(<div[^>]*class=["\'][^"\']*wc-block-components-checkout-actions[^"\']*["\'][^>]*>)/i', $injection . '$1', $content, 1);
        }

        // Fallback B: As a last resort, prepend at the start of this block's content
        // to avoid ending up below the Place Order area.
        return $injection . $content;
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

        // Respect WooCommerce Blocks mode: in Shortcode-only, only enforce if a token is present.
        $settings    = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $mode_blocks = $settings['mode_woocommerce_blocks'] ?? 'auto';

        $token = Turnstile_Validator::get_rest_request_token(
            $request,
            [ 'kitgenix_captcha_for_cloudflare_turnstile_turnstile' ]
        );

        // If Shortcode-only and no token supplied, allow request without blocking.
        if ( $mode_blocks === 'shortcode' && $token === '' ) {
            return $response;
        }

        // Otherwise, require a valid token (skip replay check here, mark after order creation).
        if ( ! $token || ! Turnstile_Validator::validate_token($token, 'woocommerce-blocks-checkout', false) ) {
            return new \WP_Error(
                'turnstile_failed',
                Turnstile_Validator::get_error_message('woocommerce'),
                [ 'status' => 403 ]
            );
        }

        return $response;
    }

    /**
     * Persist a verification marker on orders created through Checkout Blocks.
     * Also mark the token as used to prevent replay.
     *
     * @param \WC_Order        $order
     * @param \WP_REST_Request $request
     */
    public static function annotate_blocks_order($order, $request) {
        $token = Turnstile_Validator::get_rest_request_token(
            $request,
            [ 'kitgenix_captcha_for_cloudflare_turnstile_turnstile' ]
        );

        if ( $token === '' ) {
            return;
        }

        // Use GMT to avoid runtime timezone side effects.
        $order->update_meta_data('_kitgenix_turnstile_verified', gmdate('Y-m-d H:i:s'));
        $order->save();

        // Mark the token as used to prevent replay after successful order creation
        Turnstile_Validator::mark_token_used($token);
    }
}
