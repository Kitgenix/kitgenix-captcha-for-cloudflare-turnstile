<?php
// Contact Form 7 integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function esc_attr;
use function esc_html__;
use function get_option;
use function sanitize_text_field;
use function wp_nonce_field;
use function wp_unslash;

class ContactForm7 {

    public static function init() {
        if ( ! defined('WPCF7_VERSION') || Whitelist::is_whitelisted() ) {
            return;
        }
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        // Respect per-integration mode: 'auto' (auto-inject) or 'shortcode' (manual placement only).
        $mode = $settings['mode_cf7'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            // Shortcode-only mode: do not register auto-inject or validation hooks,
            // but still allow shortcodes placed inside the CF7 form editor to render.
            if ( function_exists( 'add_filter' ) ) {
                add_filter( 'wpcf7_form_elements', [ __CLASS__, 'process_shortcodes_in_form' ], 9 );
            }
            return;
        }
        if ( empty($settings['enable_cf7']) ) {
            return;
        }

        // Inject widget before the submit control.
        add_filter('wpcf7_form_elements', [__CLASS__, 'inject_turnstile'], 20, 1);

        // Validate on submit (AJAX + non-AJAX).
        add_filter('wpcf7_validate', [__CLASS__, 'validate_turnstile'], 10, 2);
    }

    /**
     * Insert hidden token + Turnstile container before the first submit control.
     * Handles both <input type="submit"> and <button type="submit">.
     *
     * @param string $content Raw CF7 form HTML.
     * @return string
     */
    public static function inject_turnstile($content) {
        // Respect per-integration mode and avoid rendering the kitgenix shortcode in auto mode.
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $mode = $settings['mode_cf7'] ?? 'auto';
        if ( function_exists( 'do_shortcode' ) ) {
            // If auto mode is active, temporarily remove our shortcode so it won't render
            // when CF7 runs do_shortcode (admins expect auto-inject to control placement).
            if ( $mode === 'auto' && function_exists( 'shortcode_exists' ) && shortcode_exists( 'kitgenix_turnstile' ) ) {
                if ( function_exists( 'remove_shortcode' ) ) {
                    remove_shortcode( 'kitgenix_turnstile' );
                    $shortcode_removed = true;
                }
            }
            $content = \do_shortcode( $content );

            // Re-register the shortcode immediately if we temporarily removed it so
            // early returns below don't leave it unregistered.
            if ( ! empty( $shortcode_removed ) ) {
                if ( class_exists( '\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode' ) ) {
                    \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::register_shortcode();
                } elseif ( function_exists( 'add_shortcode' ) ) {
                    add_shortcode( 'kitgenix_turnstile', [ '\KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode', 'render_shortcode' ] );
                }
            }
        }
        
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $content;
        }

        // Don’t inject if there’s already a rendered container in this form. When running in
        // auto mode we intentionally ignore literal shortcode tokens so admins can choose
        // the integration mode and have a clear single-source of placement.
        $has_container = ( strpos( $content, 'class="cf-turnstile"' ) !== false )
            || ( strpos( $content, 'data-kitgenix-shortcode' ) !== false )
            || ( strpos( $content, 'name="cf-turnstile-response"' ) !== false );

        if ( $has_container ) {
            return $content;
        }

        ob_start();

        // Our CSRF nonce (used by Turnstile_Validator::is_valid_submission()).
        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        // Hidden token field populated by public JS after challenge success.
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Container only; global renderer loads/handles the Turnstile widget.
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'    . esc_attr($site_key) . '"'
           . ' data-theme="'      . esc_attr($settings['theme']       ?? 'auto') . '"'
           . ' data-size="'       . esc_attr($settings['widget_size'] ?? 'normal') . '"'
           . ' data-appearance="' . esc_attr($settings['appearance']  ?? 'always') . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="cf7"></div>';

        $injection = ob_get_clean();

        // Try to place right before the first submit control.
        $patterns = [
            '/(<input[^>]+type=["\']submit["\'][^>]*>)/i',
            '/(<button[^>]+type=["\']submit["\'][^>]*>)/i',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                return preg_replace( $pattern, $injection . '$1', $content, 1 );
            }
        }

        // Fallback: append before closing form tag.
        return str_ireplace('</form>', $injection . '</form>', $content);
    }

    /**
     * Ensure shortcodes inside CF7 form editor are processed so shortcode-only mode works.
     *
     * @param string $content
     * @return string
     */
    public static function process_shortcodes_in_form( $content ) {
        if ( function_exists( 'do_shortcode' ) ) {
            return \do_shortcode( $content );
        }
        return $content;
    }

    /**
     * Validation hook (runs for the form as a whole).
     * We add a form-level error so CF7 shows the red message box and blocks submit.
     *
     * @param \WPCF7_Validation  $result
     * @param \WPCF7_ContactForm $contact_form
     * @return \WPCF7_Validation
     */
    public static function validate_turnstile($result, $contact_form) {
        // Only validate on POST (sanitize access to $_SERVER).
        if ( self::request_method() !== 'POST' ) {
            return $result;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            // Use a generic form-level error (no specific tag).
            $result->invalidate( 'form', Turnstile_Validator::get_error_message('cf7') );
        }

        return $result;
    }

    /**
     * Sanitize request method (PHPCS-friendly).
     */
    private static function request_method(): string {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

ContactForm7::init();
