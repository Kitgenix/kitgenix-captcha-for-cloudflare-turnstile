<?php
// Jetpack Forms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function esc_attr;
use function get_option;
use function sanitize_text_field;
use function wp_nonce_field;
use function wp_unslash;

class JetpackForms {
    public static function init() {
        if ( ! class_exists( 'Jetpack' ) || Whitelist::is_whitelisted() ) {
            return;
        }
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        if ( empty( $settings['enable_jetpackforms'] ) ) {
            return;
        }

        // Inject container + hidden token before submit
        add_filter( 'jetpack_contact_form_html', [ __CLASS__, 'inject_turnstile_markup' ], 10, 1 );

        // Validate submissions server-side
        add_filter( 'jetpack_contact_form_is_spam', [ __CLASS__, 'validate_turnstile' ], 9, 2 );
        // Note: This filter can run on Classic and Block forms; return WP_Error to abort with message
    }

    /**
     * Inject Turnstile markup into Jetpack form HTML.
     *
     * @param string $html Full form HTML (provided by Jetpack).
     * @return string Modified HTML. (Do NOT wp_kses_post here or you’ll strip required inputs/data-attrs.)
     */
    public static function inject_turnstile_markup( $html ) {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $html;
        }

        // Avoid injecting twice
        if ( strpos( (string) $html, 'class="cf-turnstile"' ) !== false ) {
            return $html;
        }

        ob_start();

        // CSRF nonce for our validator
        if ( function_exists( 'wp_nonce_field' ) ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token input + container (rendered by our public JS)
        ?>
        <input type="hidden" name="cf-turnstile-response" value="" />
        <div class="cf-turnstile"
             data-sitekey="<?php echo esc_attr( $site_key ); ?>"
             data-theme="<?php echo esc_attr( $settings['theme'] ?? 'auto' ); ?>"
             data-size="<?php echo esc_attr( $settings['widget_size'] ?? 'normal' ); ?>"
             data-appearance="<?php echo esc_attr( $settings['appearance'] ?? 'always' ); ?>"
             data-kgx-owner="jetpack"></div>
        <?php
        $injection = ob_get_clean();

        // Prefer to inject before a submit control; otherwise append before </form>.
        $pos_button = strpos( (string) $html, '<button' );
        if ( $pos_button !== false ) {
            return substr_replace( $html, $injection, $pos_button, 0 );
        }

        $pos_input_submit = stripos( (string) $html, '<input type="submit"' );
        if ( $pos_input_submit !== false ) {
            return substr_replace( $html, $injection, $pos_input_submit, 0 );
        }

        return str_replace( '</form>', $injection . '</form>', (string) $html );
    }

    /**
     * Validate via Jetpack's spam check hook.
     * Return WP_Error to stop submission and show our message.
     *
     * @param bool|\WP_Error $is_spam Incoming value from other checks.
     * @param array|bool     $form    Submitted feedback array.
     * @return bool|\WP_Error
     */
    public static function validate_turnstile( $is_spam, $form ) {
        if ( self::request_method() !== 'POST' ) {
            return $is_spam;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            return new \WP_Error(
                'turnstile_failed',
                Turnstile_Validator::get_error_message( 'jetpackforms' )
            );
        }

        return $is_spam;
    }

    /**
     * Sanitize request method (PHPCS-friendly access to $_SERVER).
     */
    private static function request_method(): string {
        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

JetpackForms::init();
