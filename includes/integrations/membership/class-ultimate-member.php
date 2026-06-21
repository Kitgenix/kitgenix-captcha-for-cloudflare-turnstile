<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;

defined( 'ABSPATH' ) || exit;

final class Ultimate_Member {
    /** @var bool|null Cache validation result for hooks that may fire more than once per request. */
    private static $validation_result = null;

    public static function init(): void {
        if ( Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = self::get_settings();
        if ( empty( $settings['enable_ultimatemember'] ) ) {
            return;
        }

        \add_action( 'um_after_login_fields', [ __CLASS__, 'render_login_widget' ] );
        \add_action( 'um_after_register_fields', [ __CLASS__, 'render_register_widget' ] );
        \add_action( 'um_after_password_reset_fields', [ __CLASS__, 'render_password_widget' ] );

        \add_action( 'um_submit_form_errors_hook_login', [ __CLASS__, 'validate_submission' ], 20, 1 );
        \add_action( 'um_submit_form_errors_hook__registration', [ __CLASS__, 'validate_submission' ], 20, 1 );
        \add_action( 'um_reset_password_errors_hook', [ __CLASS__, 'validate_submission' ], 20, 1 );
    }

    public static function render_login_widget(): void {
        self::render_widget( 'ultimate-member-login' );
    }

    public static function render_register_widget(): void {
        self::render_widget( 'ultimate-member-register' );
    }

    public static function render_password_widget(): void {
        self::render_widget( 'ultimate-member-password' );
    }

    public static function validate_submission( $args = null ): void {
        if ( self::request_method() !== 'POST' || Whitelist::is_whitelisted() ) {
            return;
        }

        if ( self::$validation_result === null ) {
            self::$validation_result = Turnstile_Validator::is_valid_submission( true, 'ultimate-member' );
        }

        if ( self::$validation_result ) {
            return;
        }

        if ( function_exists( 'UM' ) && is_object( \UM() ) && method_exists( \UM()->form(), 'add_error' ) ) {
            \UM()->form()->add_error( 'turnstile_failed', Turnstile_Validator::get_error_message( 'ultimate_member' ) );
        }
    }

    private static function render_widget( string $owner ): void {
        $settings = self::get_settings();
        $site_key = $settings['site_key'] ?? '';
        if ( $site_key === '' ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">'
                . \esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' )
                . '</p>';
            return;
        }

        \wp_nonce_field(
            'kitgenix_captcha_for_cloudflare_turnstile_action',
            'kitgenix_captcha_for_cloudflare_turnstile_nonce'
        );

        echo '<input type="hidden" name="cf-turnstile-response" value="" />';
        echo '<div class="cf-turnstile"'
            . ' data-sitekey="' . \esc_attr( $site_key ) . '"'
            . ' data-theme="' . \esc_attr( $settings['theme'] ?? 'auto' ) . '"'
            . ' data-size="' . \esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
            . ' data-appearance="' . \esc_attr( $settings['appearance'] ?? 'always' ) . '"'
            . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="' . \esc_attr( $owner ) . '"></div>';
    }

    private static function request_method(): string {
        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';

        return strtoupper( $method ?: 'GET' );
    }

    private static function get_settings(): array {
        $settings = \get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        return is_array( $settings ) ? $settings : [];
    }
}