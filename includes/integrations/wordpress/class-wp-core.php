<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use function add_action;
use function add_filter;
use function apply_filters;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_option;
use function sanitize_text_field;
use function wp_die;
use function wp_nonce_field;
use function wp_unslash;
use function is_admin;
use function current_user_can;
use \WP_Error;

defined('ABSPATH') || exit;

class WP_Core {

    /**
     * Initialize hooks.
     */
    public static function init() {
        if ( Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );

        // Add Turnstile widget (only for enabled core forms)
        if ( ! empty( $settings['wp_login_form'] ) ) {
            add_action( 'login_form', [ __CLASS__, 'render_widget' ] );
            add_filter( 'login_form_middle', [ __CLASS__, 'filter_login_form_middle' ], 10, 2 );
        }
        if ( ! empty( $settings['wp_register_form'] ) ) {
            add_action( 'register_form', [ __CLASS__, 'render_widget' ] );
        }
        if ( ! empty( $settings['wp_lostpassword_form'] ) ) {
            add_action( 'lostpassword_form', [ __CLASS__, 'render_widget' ] );
            // Also output on the actual reset form
            add_action( 'resetpass_form', [ __CLASS__, 'render_widget' ] );
        }
        if ( ! empty( $settings['wp_comments_form'] ) || self::woocommerce_reviews_enabled( $settings ) ) {
            // Comments: inject immediately before the submit button.
            // Some themes output the comment textarea AFTER `comment_form_after_fields`,
            // which can make the widget appear above the comment box. Using the submit
            // field filter ensures consistent placement above the submit button.
            add_filter( 'comment_form_submit_field', [ __CLASS__, 'inject_widget_before_submit' ], 10, 2 );
        }

        // Validate submissions (POST-only, per context)
        if ( ! empty( $settings['wp_login_form'] ) ) {
            add_filter( 'authenticate', [ __CLASS__, 'validate_login' ], 30, 3 ); // login
        }
        if ( ! empty( $settings['wp_register_form'] ) ) {
            add_filter( 'registration_errors', [ __CLASS__, 'validate_registration' ], 30, 3 ); // register
        }
        if ( ! empty( $settings['wp_lostpassword_form'] ) ) {
            add_action( 'lostpassword_post',       [ __CLASS__, 'validate_lostpassword' ], 10, 2 ); // lost password
            add_action( 'validate_password_reset', [ __CLASS__, 'validate_reset' ],        10, 2 ); // reset password
        }
        if ( ! empty( $settings['wp_comments_form'] ) || self::woocommerce_reviews_enabled( $settings ) ) {
            add_filter( 'preprocess_comment', [ __CLASS__, 'validate_comment' ] ); // comments
        }
    }

    /**
     * Inject the Turnstile widget immediately before the comment submit button.
     * Used to ensure the widget appears above the submit control and left-aligned
     * on product review forms where themes place the default comment fields elsewhere.
     *
     * @param string $submit_field The existing submit field HTML.
     * @param array  $args         Args passed to comment_form (may be empty).
     * @return string Modified submit field HTML.
     */
    public static function inject_widget_before_submit( $submit_field, $args = [] ) {
        $args = is_array( $args ) ? $args : [];
        if ( ! self::should_handle_comment_form( [], $args ) ) {
            return $submit_field;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $submit_field;
        }

        // Respect the integration-specific mode: product reviews follow WooCommerce Classic,
        // while standard comments follow WordPress Core.
        $mode = self::get_comment_form_mode( $settings, [], $args );
        if ( $mode === 'shortcode' ) {
            return $submit_field;
        }

        // If markup already contains a container, don't inject.
        if ( is_string( $submit_field ) && strpos( $submit_field, 'cf-turnstile' ) !== false ) {
            return $submit_field;
        }

        static $injected = false;
        if ( $injected ) {
            return $submit_field;
        }
        $injected = true;

        ob_start();

        // CSRF nonce for validator
        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token input
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Left-align the widget for submit-area placement.
        $inline_style = (string) apply_filters(
            'kitgenix_turnstile_inline_style',
            'display: flex; justify-content: flex-start;',
            'comment_submit'
        );

        echo '<div id="cf-turnstile-comment" class="cf-turnstile" style="' . esc_attr( $inline_style ) . '"'
           . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
           . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
           . ' data-size="'       . esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
           . ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
           . '></div>';

        $injection = ob_get_clean();

        // Prepend the widget so it appears above the submit button.
        return $injection . $submit_field;
    }

    /**
     * Render the Turnstile widget HTML.
     * NOTE: Inline styles are intentionally kept here to ensure centering on core forms.
     */
    public static function render_widget() {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';

        if ( ! $site_key ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">'
               . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' )
               . '</p>';
            return;
        }

        // CSRF nonce for validator()
        wp_nonce_field(
            'kitgenix_captcha_for_cloudflare_turnstile_action',
            'kitgenix_captcha_for_cloudflare_turnstile_nonce'
        );

        // Determine context for a unique id
        global $wp_current_filter;
        $context = 'login';
        if ( is_array( $wp_current_filter ) ) {
            foreach ( $wp_current_filter as $filter ) {
                if ( strpos( $filter, 'register' ) !== false )     { $context = 'register';     break; }
                if ( strpos( $filter, 'lostpassword' ) !== false ) { $context = 'lostpassword'; break; }
                if ( strpos( $filter, 'resetpass' ) !== false )    { $context = 'resetpass';    break; }
                if ( strpos( $filter, 'comment' ) !== false )      { $context = 'comment';      break; }
            }
        }
        $unique_id = 'cf-turnstile-' . $context;

        // Comments are injected via `comment_form_submit_field` for reliable placement.
        if ( $context === 'comment' ) {
            return;
        }

        // Keep inline centering style (as requested), but allow filters for future tweaks.
        $inline_style = (string) apply_filters(
            'kitgenix_turnstile_inline_style',
            'display: flex; justify-content: center;',
            $context
        );

        $markup = self::get_widget_markup( $unique_id, $inline_style, $settings, $site_key );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is assembled in get_widget_markup() with escaped attributes and a WordPress nonce field.
        echo $markup;
    }

    /**
     * Add Turnstile to custom forms rendered with wp_login_form().
     *
     * @param string $content Existing middle-of-form content.
     * @param array  $args    Arguments passed to wp_login_form().
     * @return string
     */
    public static function filter_login_form_middle( $content, $args = [] ) {
        $content = is_string( $content ) ? $content : '';
        if ( strpos( $content, 'cf-turnstile' ) !== false ) {
            return $content;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! is_string( $site_key ) || $site_key === '' ) {
            return $content;
        }

        $inline_style = (string) apply_filters(
            'kitgenix_turnstile_inline_style',
            'display: flex; justify-content: center;',
            'login_form_middle'
        );

        return $content . self::get_widget_markup( 'cf-turnstile-login-form', $inline_style, $settings, $site_key );
    }

    /**
     * Validate Turnstile on login (POST + expected fields only).
     */
    public static function validate_login( $user, $username, $password ) {
        if ( self::request_method() !== 'POST' ) {
            return $user;
        }
        if ( self::should_skip_login_validation( $user ) ) {
            return $user;
        }
        // WordPress login form posts these fields.
        if ( ! isset( $_POST['log'], $_POST['pwd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return $user;
        }
        if ( ! self::is_wordpress_login_request() && ! self::has_turnstile_login_fields() ) {
            return $user;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'wordpress-login' ) ) {
            return new WP_Error( 'turnstile_failed', esc_html( Turnstile_Validator::get_error_message( 'wp_core' ) ) );
        }
        return $user;
    }

    /**
     * Validate registration (POST only).
     */
    public static function validate_registration( $errors, $sanitized_user_login, $user_email ) {
        if ( self::request_method() !== 'POST' ) {
            return $errors;
        }
        if ( self::is_non_browser_auth_request() ) {
            return $errors;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'wordpress-register' ) ) {
            $errors->add( 'turnstile_failed', esc_html( Turnstile_Validator::get_error_message( 'wp_core' ) ) );
        }
        return $errors;
    }

    /**
     * Validate lost password (POST only).
     * Hook: lostpassword_post( WP_Error $errors, WP_User|false $user_data )
     */
    public static function validate_lostpassword( $errors, $user_data ) {
        if ( self::request_method() !== 'POST' ) {
            return;
        }
        if ( self::is_non_browser_auth_request() ) {
            return;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'wordpress-lostpassword' ) ) {
            $errors->add( 'turnstile_failed', esc_html( Turnstile_Validator::get_error_message( 'wp_core' ) ) );
        }
    }

    /**
     * Validate reset password (POST only).
     * Hook: validate_password_reset( WP_Error $errors, WP_User|WP_Error $user )
     */
    public static function validate_reset( $errors, $user ) {
        if ( self::request_method() !== 'POST' ) {
            return;
        }
        if ( self::is_non_browser_auth_request() ) {
            return;
        }
        // If this is an admin-initiated password reset (user edit screen), skip
        // Turnstile validation. Admins manage users from wp-admin and there is
        // no front-end Turnstile widget or nonce present.
        if ( is_admin() && current_user_can('edit_users') ) {
            return;
        }
        if ( ! Turnstile_Validator::is_valid_submission( true, 'wordpress-resetpassword' ) ) {
            $errors->add( 'turnstile_failed', esc_html( Turnstile_Validator::get_error_message( 'wp_core' ) ) );
        }
    }

    /**
     * Validate comment form (POST only).
     */
    public static function validate_comment( $commentdata ) {
        if ( self::request_method() !== 'POST' ) {
            return $commentdata;
        }
        $commentdata = is_array( $commentdata ) ? $commentdata : [];
        if ( ! self::should_handle_comment_form( $commentdata, [] ) ) {
            return $commentdata;
        }
        $error_context = self::is_product_review_form( $commentdata, [] ) ? 'woocommerce' : 'wp_core';
        $error_title   = $error_context === 'woocommerce'
            ? esc_html__( 'Review submission blocked', 'kitgenix-captcha-for-cloudflare-turnstile' )
            : esc_html__( 'Comment submission blocked', 'kitgenix-captcha-for-cloudflare-turnstile' );
        if ( ! Turnstile_Validator::is_valid_submission( true, $error_context === 'woocommerce' ? 'woocommerce-review' : 'wordpress-comment' ) ) {
            // Second wp_die() parameter is the TITLE.
            wp_die(
                esc_html( Turnstile_Validator::get_error_message( $error_context ) ),
                esc_html( $error_title ),
                [ 'response' => 403, 'back_link' => true ]
            );
        }
        return $commentdata;
    }

    /**
     * Get sanitized request method (satisfies PHPCS about $_SERVER access).
     */
    private static function request_method(): string {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }

    /**
     * Skip global WordPress auth validation for non-browser or pre-failed requests.
     *
     * @param mixed $user Current authenticate payload.
     * @return bool
     */
    private static function should_skip_login_validation( $user ): bool {
        if ( self::is_non_browser_auth_request() ) {
            return true;
        }

        if ( $user instanceof WP_Error && isset( $user->errors['empty_username'], $user->errors['empty_password'] ) ) {
            return true;
        }

        return (bool) apply_filters( 'kitgenix_turnstile_skip_wp_login_validation', false, $user );
    }

    /**
     * Skip requests that do not come from normal browser-rendered auth screens.
     */
    private static function is_non_browser_auth_request(): bool {
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return true;
        }

        return defined( 'REST_REQUEST' ) && REST_REQUEST;
    }

    /**
     * Detect login requests hitting the current WordPress login endpoint.
     */
    private static function is_wordpress_login_request(): bool {
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';

        $current_path = function_exists( 'wp_parse_url' ) ? (string) wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
        $login_path   = function_exists( 'wp_parse_url' ) ? (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) : '';

        return $current_path !== '' && $login_path !== '' && $current_path === $login_path;
    }

    /**
     * Detect submissions from core login forms that include the Turnstile fields.
     */
    private static function has_turnstile_login_fields(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence checks are used only to detect whether Turnstile fields exist on the submitted form.
        $has_nonce = isset( $_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence checks are used only to detect whether Turnstile fields exist on the submitted form.
        $has_token = isset( $_POST['cf-turnstile-response'] );

        return $has_nonce || $has_token;
    }

    /**
     * Build shared Turnstile markup for core auth forms.
     */
    private static function get_widget_markup( string $unique_id, string $inline_style, array $settings, string $site_key ): string {
        ob_start();
        wp_nonce_field(
            'kitgenix_captcha_for_cloudflare_turnstile_action',
            'kitgenix_captcha_for_cloudflare_turnstile_nonce'
        );
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';
        echo '<div id="' . esc_attr( $unique_id ) . '" class="cf-turnstile" style="' . esc_attr( $inline_style ) . '"'
           . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
           . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
           . ' data-size="'       . esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
           . ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
           . '></div>';

        return (string) ob_get_clean();
    }

    /**
    * Decide whether the comments integration should run for the current form.
    * WooCommerce product reviews use the comment system internally, but they
    * should only be handled when the dedicated WooCommerce reviews setting is on.
     *
     * @param array $commentdata Comment payload when validating a submission.
     * @param array $args        Args passed to comment_form() when rendering.
     * @return bool
     */
    private static function should_handle_comment_form( array $commentdata = [], array $args = [] ): bool {
        $settings  = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $post_id   = self::get_comment_form_post_id( $commentdata, $args );
        $post_type = self::get_comment_form_post_type( $post_id );
        $is_product_review = self::is_product_review_form( $commentdata, $args, $post_id, $post_type );

        if ( ! $is_product_review && self::wpdiscuz_comments_enabled( $settings ) ) {
            return false;
        }

        $should_handle = $is_product_review
            ? self::woocommerce_reviews_enabled( $settings )
            : ! empty( $settings['wp_comments_form'] );

        return (bool) apply_filters(
            'kitgenix_turnstile_handle_comment_form',
            $should_handle,
            $post_id,
            $post_type,
            $commentdata,
            $args
        );
    }

    /**
     * Resolve the injection mode for the current comment-like form.
     *
     * @param array $settings    Plugin settings.
     * @param array $commentdata Comment payload when validating a submission.
     * @param array $args        Args passed to comment_form() when rendering.
     * @return string
     */
    private static function get_comment_form_mode( array $settings = [], array $commentdata = [], array $args = [] ): string {
        return self::is_product_review_form( $commentdata, $args )
            ? ( $settings['mode_woocommerce'] ?? 'auto' )
            : ( $settings['mode_wp_core'] ?? 'auto' );
    }

    /**
     * Determine whether the current comment-like form is a WooCommerce product review.
     *
     * @param array       $commentdata Comment payload when validating a submission.
     * @param array       $args        Args passed to comment_form() when rendering.
     * @param int|null    $post_id     Optional resolved post ID.
     * @param string|null $post_type   Optional resolved post type.
     * @return bool
     */
    private static function is_product_review_form( array $commentdata = [], array $args = [], ?int $post_id = null, ?string $post_type = null ): bool {
        $post_id   = $post_id ?? self::get_comment_form_post_id( $commentdata, $args );
        $post_type = $post_type ?? self::get_comment_form_post_type( $post_id );

        if ( $post_type === 'product' ) {
            return true;
        }

        return function_exists( 'is_singular' ) && is_singular( 'product' );
    }

    /**
     * Resolve the post type attached to the current comment form/submission.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private static function get_comment_form_post_type( int $post_id ): string {
        if ( $post_id > 0 && function_exists( 'get_post_type' ) ) {
            return (string) get_post_type( $post_id );
        }

        return '';
    }

    /**
     * Determine whether the dedicated WooCommerce product reviews integration is enabled.
     *
     * @param array $settings Plugin settings.
     * @return bool
     */
    private static function woocommerce_reviews_enabled( array $settings = [] ): bool {
        return class_exists( 'WooCommerce' )
            && ! empty( $settings['enable_woocommerce'] )
            && ! empty( $settings['wc_reviews_form'] );
    }

    /**
     * Determine whether wpDiscuz should own non-product comment protection.
     *
     * @param array $settings Plugin settings.
     * @return bool
     */
    private static function wpdiscuz_comments_enabled( array $settings = [] ): bool {
        return ! empty( $settings['enable_wpdiscuz'] )
            && ( class_exists( 'WpdiscuzCore' ) || class_exists( '\\WpdiscuzCore' ) || defined( 'WPDISCUZ_VERSION' ) );
    }

    /**
     * Resolve the post ID attached to the current comment form or submission.
     *
     * @param array $commentdata Comment payload when validating a submission.
     * @param array $args        Args passed to comment_form() when rendering.
     * @return int
     */
    private static function get_comment_form_post_id( array $commentdata = [], array $args = [] ): int {
        if ( isset( $commentdata['comment_post_ID'] ) ) {
            return \absint( $commentdata['comment_post_ID'] );
        }

        if ( isset( $args['comment_post_ID'] ) ) {
            return \absint( $args['comment_post_ID'] );
        }

        if ( isset( $args['post_id'] ) ) {
            return \absint( $args['post_id'] );
        }

        if ( function_exists( 'get_the_ID' ) ) {
            $post_id = (int) get_the_ID();
            if ( $post_id > 0 ) {
                return $post_id;
            }
        }

        global $post;
        if ( isset( $post->ID ) ) {
            return \absint( $post->ID );
        }

        return 0;
    }
}
