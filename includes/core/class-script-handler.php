<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Script_Handler {

    /**
     * Initialize hooks.
     */
    public static function init() {
        // Frontend and login pages
        \add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);
        \add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);

        // Admin settings panel
        \add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue public-facing assets (frontend and login).
     */
    public static function enqueue_public_assets() {
        $settings = self::get_settings();

        // Register Cloudflare Turnstile with async (WP 6.3+ supports array $args).
        $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=KGX_TurnstileOnLoad';
        if ( ! empty( $settings['language'] ) && 'auto' !== $settings['language'] ) {
            $url .= '&hl=' . \esc_attr( $settings['language'] );
        }
        // Allow filtering of the Turnstile script URL
        $url = \apply_filters( 'kitgenix_captcha_for_cloudflare_turnstile_script_url', $url, $settings );

        // Define the onload callback for Turnstile
        \wp_add_inline_script(
            'kitgenix-captcha-for-cloudflare-turnstile',
            'window.KGX_TurnstileOnLoad = function() { try { if (window.KitgenixCaptchaForCloudflareTurnstile && typeof window.KitgenixCaptchaForCloudflareTurnstile.renderWidgets === "function") { window.KitgenixCaptchaForCloudflareTurnstile.renderWidgets(); } } catch(e) { if(window.console) console.error(e); } };',
            'before'
        );

        // Use array args on WP 6.3+; fall back to boolean 'in_footer' on older WP.
        $args = \version_compare( \get_bloginfo( 'version' ), '6.3', '>=' )
            ? [ 'in_footer' => true, 'strategy' => 'async' ]
            : true;

        \wp_register_script(
            'kitgenix-captcha-for-cloudflare-turnstile',
            $url,
            [],
            null,
            $args
        );
        \wp_enqueue_script( 'kitgenix-captcha-for-cloudflare-turnstile' );

        // On WP < 6.3 but >= 5.7, add async via the official helper.
        if ( \version_compare( \get_bloginfo( 'version' ), '6.3', '<' ) && \version_compare( \get_bloginfo( 'version' ), '5.7', '>=' ) ) {
            \wp_script_add_data( 'kitgenix-captcha-for-cloudflare-turnstile', 'async', true );
        }

        // Load frontend JS/CSS
        \wp_enqueue_script('kitgenix-captcha-for-cloudflare-turnstile-public', KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'js/public.js', ['jquery'], KitgenixCaptchaForCloudflareTurnstileVERSION, true);
        \wp_enqueue_style('kitgenix-captcha-for-cloudflare-turnstile-public', KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'css/public.css', [], KitgenixCaptchaForCloudflareTurnstileVERSION);

        // Output config as inline script before public.js
        $config = [
            'site_key'       => $settings['site_key'] ?? '',
            'disable_submit' => !empty($settings['disable_submit']),
            'appearance'     => $settings['appearance'] ?? 'always',
            'size'           => $settings['widget_size'] ?? 'normal',
            'theme'          => $settings['theme'] ?? 'auto',
            'extra_message'  => $settings['extra_message'] ?? '',
        ];
        \wp_add_inline_script(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            'window.KitgenixCaptchaForCloudflareTurnstileConfig = ' . \wp_json_encode($config) . ';',
            'before'
        );
    }

    /**
     * Enqueue admin panel assets (only on plugin settings page).
     */
    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'kitgenix-captcha-for-cloudflare-turnstile') === false) {
            return;
        }
        $admin_css = KitgenixCaptchaForCloudflareTurnstilePATH . 'assets/css/admin.css';
        $admin_js = KitgenixCaptchaForCloudflareTurnstilePATH . 'assets/js/admin.js';
        $css_ver = file_exists($admin_css) ? filemtime($admin_css) : KitgenixCaptchaForCloudflareTurnstileVERSION;
        $js_ver = file_exists($admin_js) ? filemtime($admin_js) : KitgenixCaptchaForCloudflareTurnstileVERSION;
        \wp_enqueue_style('kitgenix-captcha-for-cloudflare-turnstile-admin', KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'css/admin.css', [], $css_ver);
        \wp_enqueue_script('kitgenix-captcha-for-cloudflare-turnstile-admin', KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'js/admin.js', ['jquery'], $js_ver, true);
    }



    /**
     * Retrieve plugin settings.
     */
    private static function get_settings() {
        return \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
    }
}
