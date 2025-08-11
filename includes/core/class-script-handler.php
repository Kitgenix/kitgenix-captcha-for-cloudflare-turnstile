<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Script_Handler {

    public static function init() {
        // Frontend + login
        \add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);
        \add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);

        // Admin assets and alignment
        \add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // Late alignment fixes so they win the cascade
        \add_action('login_enqueue_scripts', [__CLASS__, 'align_login_turnstile'], 99);
        \add_action('admin_enqueue_scripts', [__CLASS__, 'align_admin_turnstile'], 99);
    }

    /**
     * Public-facing assets (frontend + login).
     */
    public static function enqueue_public_assets() {
        $settings = self::get_settings();

        // --- Cloudflare Turnstile -----------------------------------------------------------
        $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=KGX_TurnstileOnLoad';
        if ( ! empty( $settings['language'] ) && 'auto' !== $settings['language'] ) {
            $url .= '&hl=' . rawurlencode( $settings['language'] );
        }
        $url = \apply_filters( 'kitgenix_captcha_for_cloudflare_turnstile_script_url', $url, $settings );

        $args = \version_compare( \get_bloginfo( 'version' ), '6.3', '>=' )
            ? [ 'in_footer' => true, 'strategy' => 'async' ]
            : true;

        // Register first
        \wp_register_script(
            'kitgenix-captcha-for-cloudflare-turnstile',
            $url,
            [],
            null,
            $args
        );

        // On WP < 6.3 but >= 5.7, add async via the official helper.
        if ( \version_compare( \get_bloginfo( 'version' ), '6.3', '<' ) && \version_compare( \get_bloginfo( 'version' ), '5.7', '>=' ) ) {
            \wp_script_add_data( 'kitgenix-captcha-for-cloudflare-turnstile', 'async', true );
        }

        // Define onload callback BEFORE the external script tag (handle must be registered already)
        \wp_add_inline_script(
            'kitgenix-captcha-for-cloudflare-turnstile',
            'window.KGX_TurnstileOnLoad = function(){try{if(window.KitgenixCaptchaForCloudflareTurnstile && typeof window.KitgenixCaptchaForCloudflareTurnstile.renderWidgets==="function"){window.KitgenixCaptchaForCloudflareTurnstile.renderWidgets();}}catch(e){if(window.console)console.error(e);}};',
            'before'
        );

        \wp_enqueue_script( 'kitgenix-captcha-for-cloudflare-turnstile' );

        // --- Public JS/CSS with cache-busting ----------------------------------------------
        $base_path = \trailingslashit( constant( 'KitgenixCaptchaForCloudflareTurnstilePATH' ) );
        $base_url  = constant( 'KitgenixCaptchaForCloudflareTurnstileASSETS_URL' );

        $public_css_path = $base_path . 'assets/css/public.css';
        $public_js_path  = $base_path . 'assets/js/public.js';

        $css_ver = \file_exists( $public_css_path ) ? \filemtime( $public_css_path ) : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );
        $js_ver  = \file_exists( $public_js_path )  ? \filemtime( $public_js_path )  : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );

        \wp_register_style(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            $base_url . 'css/public.css',
            [],
            $css_ver
        );
        \wp_register_script(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            $base_url . 'js/public.js',
            [ 'jquery' ],
            $js_ver,
            true
        );

        // Config BEFORE public.js
        $config = [
            'site_key'       => $settings['site_key'] ?? '',
            'disable_submit' => ! empty( $settings['disable_submit'] ),
            'appearance'     => $settings['appearance'] ?? 'always',
            'size'           => $settings['widget_size'] ?? 'normal',
            'theme'          => $settings['theme'] ?? 'auto',
            'extra_message'  => $settings['extra_message'] ?? '',
        ];
        \wp_add_inline_script(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            'window.KitgenixCaptchaForCloudflareTurnstileConfig=' . \wp_json_encode( $config ) . ';',
            'before'
        );

        \wp_enqueue_style(  'kitgenix-captcha-for-cloudflare-turnstile-public' );
        \wp_enqueue_script( 'kitgenix-captcha-for-cloudflare-turnstile-public' );
    }

    /**
     * Admin assets (heavy assets only on our pages; alignment fix everywhere via align_admin_turnstile()).
     */
    public static function enqueue_admin_assets( $hook ) {
        // Heavy assets only on our plugin screens
        if ( \strpos( (string) $hook, 'kitgenix-captcha-for-cloudflare-turnstile' ) === false ) {
            return;
        }

        $base_path = \trailingslashit( constant( 'KitgenixCaptchaForCloudflareTurnstilePATH' ) );
        $base_url  = constant( 'KitgenixCaptchaForCloudflareTurnstileASSETS_URL' );

        $admin_css_path = $base_path . 'assets/css/admin.css';
        $admin_js_path  = $base_path . 'assets/js/admin.js';

        $css_ver = \file_exists( $admin_css_path ) ? \filemtime( $admin_css_path ) : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );
        $js_ver  = \file_exists( $admin_js_path )  ? \filemtime( $admin_js_path )  : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );

        \wp_enqueue_style(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            $base_url . 'css/admin.css',
            [],
            $css_ver
        );
        \wp_enqueue_script(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            $base_url . 'js/admin.js',
            [ 'jquery' ],
            $js_ver,
            true
        );
    }

    /**
     * Late CSS for ALL wp-login.php screens (login, lost password, reset, register).
     * Runs with priority 99 so it overrides earlier rules.
     */
    public static function align_login_turnstile() {
        \wp_register_style( 'kitgenix-turnstile-login-align', false, [], constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' ) );
        \wp_enqueue_style( 'kitgenix-turnstile-login-align' );

        \wp_add_inline_style(
            'kitgenix-turnstile-login-align',
            // General catch-all on wp-login pages:
            'body.login .cf-turnstile{width:100% !important;display:grid !important;place-items:center !important;text-align:center !important;margin:8px 0 16px}
             body.login .cf-turnstile > div, body.login .cf-turnstile iframe{margin:0 auto !important;float:none !important}
             /* Explicitly cover each core form ID */
             body.login #loginform .cf-turnstile,
             body.login #lostpasswordform .cf-turnstile,
             body.login #resetpassform .cf-turnstile,
             body.login #registerform .cf-turnstile{width:100% !important;}'
        );
    }

    /**
     * Late CSS for ALL wp-admin screens to center Turnstile.
     */
    public static function align_admin_turnstile() {
        \wp_register_style( 'kitgenix-turnstile-admin-align', false, [], constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' ) );
        \wp_enqueue_style( 'kitgenix-turnstile-admin-align' );

        \wp_add_inline_style(
            'kitgenix-turnstile-admin-align',
            'body.wp-admin .cf-turnstile{width:100% !important;display:grid !important;place-items:center !important}
             body.wp-admin .cf-turnstile > div, body.wp-admin .cf-turnstile iframe{margin:0 auto !important;float:none !important}'
        );
    }

    /**
     * Retrieve plugin settings.
     */
    private static function get_settings() {
        $opts = \get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        return \is_array( $opts ) ? $opts : [];
    }
}
