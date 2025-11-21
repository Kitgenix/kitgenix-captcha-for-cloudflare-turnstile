<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use function add_action;
use function add_filter;
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
        }
        if ( ! empty( $settings['wp_register_form'] ) ) {
            add_action( 'register_form', [ __CLASS__, 'render_widget' ] );
        }
        if ( ! empty( $settings['wp_lostpassword_form'] ) ) {
            add_action( 'lostpassword_form', [ __CLASS__, 'render_widget' ] );
            // Also output on the actual reset form
            add_action( 'resetpass_form', [ __CLASS__, 'render_widget' ] );
        }
        if ( ! empty( $settings['wp_comments_form'] ) ) {
            // Default: output after fields for normal comment forms.
            add_action( 'comment_form_after_fields',    [ __CLASS__, 'render_widget' ] );
            add_action( 'comment_form_logged_in_after', [ __CLASS__, 'render_widget' ] );

            // For more precise placement on some themes (eg. WooCommerce product reviews)
            // inject immediately before the submit button so the widget appears above submit
            // and can be left-aligned. The inject helper will guard against duplicates.
            add_filter( 'comment_form_submit_field',   [ __CLASS__, 'inject_widget_before_submit' ], 10, 2 );
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
        if ( ! empty( $settings['wp_comments_form'] ) ) {
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
        // Only target WooCommerce product pages to avoid changing general comment placement.
        if ( function_exists('is_product') && ! is_product() ) {
            return $submit_field;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $submit_field;
        }

        // Respect per-integration mode: allow admin to switch WP core forms to shortcode-only.
        $mode = $settings['mode_wp_core'] ?? 'auto';
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

        echo '<div class="cf-turnstile" style="' . esc_attr( $inline_style ) . '"'
           . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
           . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
           . ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"'
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

        // If this is the comment context on a WooCommerce product page, skip here.
        // We prefer injecting before the submit button (see inject_widget_before_submit) so
        // the widget appears directly above the submit control and can be left-aligned.
        if ( $context === 'comment' && function_exists('is_product') && is_product() ) {
            return;
        }

        // Keep inline centering style (as requested), but allow filters for future tweaks.
        $inline_style = (string) apply_filters(
            'kitgenix_turnstile_inline_style',
            'display: flex; justify-content: center;',
            $context
        );

        echo '<div id="' . esc_attr( $unique_id ) . '" class="cf-turnstile" style="' . esc_attr( $inline_style ) . '"'
           . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
           . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
           . ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"'
           . ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
           . '></div>';
    }

    /**
     * Validate Turnstile on login (POST + expected fields only).
     */
    public static function validate_login( $user, $username, $password ) {
        if ( self::request_method() !== 'POST' ) {
            return $user;
        }
        // WordPress login form posts these fields.
        if ( ! isset( $_POST['log'], $_POST['pwd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return $user;
        }
        if ( ! Turnstile_Validator::is_valid_submission() ) {
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
        if ( ! Turnstile_Validator::is_valid_submission() ) {
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
        if ( ! Turnstile_Validator::is_valid_submission() ) {
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
        // If this is an admin-initiated password reset (user edit screen), skip
        // Turnstile validation. Admins manage users from wp-admin and there is
        // no front-end Turnstile widget or nonce present.
        if ( is_admin() && current_user_can('edit_users') ) {
            return;
        }
        if ( ! Turnstile_Validator::is_valid_submission() ) {
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
        if ( ! Turnstile_Validator::is_valid_submission() ) {
            // Second wp_die() parameter is the TITLE.
            wp_die(
                esc_html( Turnstile_Validator::get_error_message( 'wp_core' ) ),
                esc_html__( 'Comment submission blocked', 'kitgenix-captcha-for-cloudflare-turnstile' ),
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
}
