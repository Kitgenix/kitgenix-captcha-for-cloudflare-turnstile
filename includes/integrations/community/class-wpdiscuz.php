<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Community;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;

defined( 'ABSPATH' ) || exit;

final class WpDiscuz {
    public static function init(): void {
        if ( Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = self::get_settings();
        if ( empty( $settings['enable_wpdiscuz'] ) ) {
            return;
        }

        \add_action( 'wpdiscuz_submit_button_before', [ __CLASS__, 'render_widget' ], 10, 3 );
        \add_action( 'wpdiscuz_before_comment_post', [ __CLASS__, 'validate_submission' ], 10 );
    }

    public static function render_widget( $current_user, $unique_id, $is_main_form ): void {
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
        echo '<div class="cf-turnstile kitgenix-ts-wpdiscuz" style="display:flex;justify-content:flex-start;margin:10px 0;"'
            . ' data-sitekey="' . \esc_attr( $site_key ) . '"'
            . ' data-theme="' . \esc_attr( $settings['theme'] ?? 'auto' ) . '"'
            . ' data-size="' . \esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
            . ' data-appearance="' . \esc_attr( $settings['appearance'] ?? 'always' ) . '"'
            . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="wpdiscuz"></div>';
    }

    public static function validate_submission(): void {
        if ( self::request_method() !== 'POST' || Whitelist::is_whitelisted() ) {
            return;
        }

        if ( Turnstile_Validator::is_valid_submission( true, 'wpdiscuz' ) ) {
            return;
        }

        \wp_die(
            \esc_html( Turnstile_Validator::get_error_message( 'wpdiscuz' ) ),
            \esc_html__( 'Comment submission blocked', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            [
                'response'  => 403,
                'back_link' => true,
            ]
        );
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