<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;

defined( 'ABSPATH' ) || exit;

final class Paid_Memberships_Pro {
    public static function init(): void {
        if ( Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = self::get_settings();
        if ( empty( $settings['enable_paidmembershipspro'] ) ) {
            return;
        }

        \add_action( 'pmpro_checkout_before_submit_button', [ __CLASS__, 'render_widget' ], 10 );
        \add_filter( 'pmpro_registration_checks', [ __CLASS__, 'validate_checkout' ] );
        // Mark token as used after successful membership creation
        \add_action( 'pmpro_after_change_membership_level', [ __CLASS__, 'mark_token_used_after_membership' ], 10, 2 );
    }

    public static function render_widget(): void {
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
        echo \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::render_honeypot_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler.
        echo '<div class="cf-turnstile"'
            . \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::get_widget_data_attributes( 'paidmembershipspro', $site_key ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler.
            . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="paid-memberships-pro"></div>';
    }

    public static function validate_checkout() {
        if ( self::request_method() !== 'POST' || Whitelist::is_whitelisted() ) {
            return true;
        }

        if ( Turnstile_Validator::is_valid_submission( true, 'paid-memberships-pro-checkout', false ) ) {
            return true;
        }

        if ( function_exists( 'pmpro_setMessage' ) ) {
            \pmpro_setMessage( Turnstile_Validator::get_error_message( 'paidmembershipspro' ), 'pmpro_error' );
        }

        return false;
    }

    /**
     * After successful membership creation, mark the Turnstile token as used.
     * This prevents the token from being reused even if the customer comes back.
     *
     * @param int $user_id User ID
     * @param int $level_id Membership level ID
     */
    public static function mark_token_used_after_membership( $user_id, $level_id ): void {
        Turnstile_Validator::mark_submission_token_used();
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