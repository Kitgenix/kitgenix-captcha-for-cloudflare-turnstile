<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Newsletters;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;

defined( 'ABSPATH' ) || exit;

final class MailPoet {
    public static function init(): void {
        if ( Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = self::get_settings();
        if ( empty( $settings['enable_mailpoet'] ) ) {
            return;
        }

        \add_filter( 'mailpoet_form_widget_post_process', [ __CLASS__, 'inject_widget' ], 10, 1 );
        \add_action( 'mailpoet_subscription_before_subscribe', [ __CLASS__, 'validate_submission' ], 10, 3 );
    }

    public static function inject_widget( $html ) {
        if ( ! is_string( $html ) || $html === '' ) {
            return $html;
        }

        $settings = self::get_settings();
        $site_key = $settings['site_key'] ?? '';
        if ( $site_key === '' ) {
            return $html;
        }

        if ( strpos( $html, 'class="cf-turnstile"' ) !== false
            || \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $html, false ) ) {
            return $html;
        }

        ob_start();
        ?>
        <input type="hidden" name="cf-turnstile-response" value="" />
        <input type="hidden" name="data[cf-turnstile-response]" value="" />
        <?php echo \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::render_honeypot_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler. ?>
        <div class="cf-turnstile"<?php echo \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::get_widget_data_attributes( 'mailpoet', $site_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped markup from Script_Handler. ?>
             data-kitgenix-captcha-for-cloudflare-turnstile-owner="mailpoet"></div>
        <?php
        $injection = trim( (string) ob_get_clean() );

        $patterns = [
            '/(<input[^>]*class=["\'][^"\']*mailpoet_submit[^"\']*["\'][^>]*>)/i',
            '/(<button[^>]*class=["\'][^"\']*mailpoet_submit[^"\']*["\'][^>]*>.*?<\/button>)/is',
        ];

        foreach ( $patterns as $pattern ) {
            if ( \preg_match( $pattern, $html ) ) {
                return (string) \preg_replace( $pattern, $injection . '$1', $html, 1 );
            }
        }

        if ( strpos( $html, '</form>' ) !== false ) {
            return str_replace( '</form>', $injection . '</form>', $html );
        }

        return $html . $injection;
    }

    public static function validate_submission( $data, $segment_ids, $form ): void {
        if ( self::request_method() !== 'POST' || Whitelist::is_whitelisted() ) {
            return;
        }

        if ( Turnstile_Validator::validate_token( self::request_token(), 'mailpoet' ) ) {
            return;
        }

        $message = Turnstile_Validator::get_error_message( 'mailpoet' );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Turnstile_Validator::get_error_message() already returns an escaped user-facing string.
        throw new \MailPoet\UnexpectedValueException( $message );
    }

    private static function request_token(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- MailPoet submissions do not expose a WordPress nonce for this validation hook.
        if ( isset( $_POST['data']['cf-turnstile-response'] ) && ! is_array( $_POST['data']['cf-turnstile-response'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- MailPoet submissions do not expose a WordPress nonce for this validation hook.
            return \sanitize_text_field( \wp_unslash( $_POST['data']['cf-turnstile-response'] ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- MailPoet submissions do not expose a WordPress nonce for this validation hook.
        if ( isset( $_POST['cf-turnstile-response'] ) && ! is_array( $_POST['cf-turnstile-response'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- MailPoet submissions do not expose a WordPress nonce for this validation hook.
            return \sanitize_text_field( \wp_unslash( $_POST['cf-turnstile-response'] ) );
        }

        return '';
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