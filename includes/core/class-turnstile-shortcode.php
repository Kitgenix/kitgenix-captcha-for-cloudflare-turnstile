<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Turnstile_Shortcode {

    public static function init() {
        \add_action('init', [__CLASS__, 'register_shortcode']);
    }

    public static function register_shortcode() {
        \add_shortcode('kitgenix_turnstile', [__CLASS__, 'render_shortcode']);
    }

    /**
     * Shortcode renderer. Returns the same markup used by auto-inject integrations
     * so both methods are interchangeable and detectable.
     *
     * @param array $atts
     * @return string
     */
    public static function render_shortcode($atts = []) {
        $settings = \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return '';
        }

        ob_start();

        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field(
                'kitgenix_captcha_for_cloudflare_turnstile_action',
                'kitgenix_captcha_for_cloudflare_turnstile_nonce'
            );
        }

        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Use same class and data attributes so auto-inject detection works.
        echo '<div class="cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '" data-kitgenix-shortcode="1"';
        if ( ! empty( $atts ) && is_array( $atts ) ) {
            foreach ( $atts as $k => $v ) {
                echo ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
            }
        }
        echo '></div>';

        return (string) ob_get_clean();
    }

    /**
     * Recursively scan an array/string for the shortcode token or the widget marker.
     * Useful for integrations that receive structured form data instead of raw HTML.
     *
     * @param mixed $haystack
     * @return bool
     */
    /**
     * Recursively scan an array/string for the shortcode token or the widget marker.
     * Useful for integrations that receive structured form data instead of raw HTML.
     *
     * @param mixed $haystack
     * @param bool  $detect_shortcode_tag Whether to treat the literal shortcode tag (e.g. "[kitgenix_turnstile]") as a marker.
     *                                    Set to false when running in auto-inject mode so textual shortcode tokens
     *                                    won't prevent automatic injection (the shortcode will be ignored).
     * @return bool
     */
    public static function has_shortcode_in( $haystack, $detect_shortcode_tag = true ) {
        if ( is_string( $haystack ) ) {
            if ( $detect_shortcode_tag && \has_shortcode( $haystack, 'kitgenix_turnstile' ) ) {
                return true;
            }
            if ( strpos( $haystack, 'cf-turnstile' ) !== false || strpos( $haystack, 'data-kitgenix-shortcode' ) !== false || strpos( $haystack, 'name="cf-turnstile-response"' ) !== false ) {
                return true;
            }
            return false;
        }

        if ( is_array( $haystack ) ) {
            foreach ( $haystack as $v ) {
                if ( self::has_shortcode_in( $v, $detect_shortcode_tag ) ) {
                    return true;
                }
            }
            return false;
        }

        if ( is_object( $haystack ) ) {
            foreach ( (array) $haystack as $v ) {
                if ( self::has_shortcode_in( $v, $detect_shortcode_tag ) ) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }
}

Turnstile_Shortcode::init();
