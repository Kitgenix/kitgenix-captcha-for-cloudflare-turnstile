<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;

defined( 'ABSPATH' ) || exit;

final class MemberPress {
    public static function init(): void {
        if ( Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = self::get_settings();
        if ( empty( $settings['enable_memberpress'] ) ) {
            return;
        }

        \add_action( 'mepr-checkout-before-submit', [ __CLASS__, 'render_widget' ], 10, 1 );
        \add_filter( 'mepr-validate-signup', [ __CLASS__, 'validate_signup' ], 20, 1 );
    }

    public static function render_widget( $membership_id = null ): void {
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
            . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="memberpress"></div>';
    }

    public static function validate_signup( $errors ) {
        if ( self::request_method() !== 'POST' || Whitelist::is_whitelisted() ) {
            return $errors;
        }

        if ( Turnstile_Validator::is_valid_submission( true, 'memberpress-signup' ) ) {
            return $errors;
        }

        $message = Turnstile_Validator::get_error_message( 'memberpress' );
        if ( is_array( $errors ) ) {
            $errors[] = $message;
            return $errors;
        }

        return [ $message ];
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