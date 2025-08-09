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

        // Turnstile script URL
        $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        if (!empty($settings['language']) && $settings['language'] !== 'auto') {
            $url .= '&hl=' . \esc_attr($settings['language']);
        }
        // Allow filtering of the Turnstile script URL
        $url = \apply_filters('kitgenix_captcha_for_cloudflare_turnstile_script_url', $url, $settings);

        // Use null for version to prevent WordPress from appending ?ver=...
        \wp_enqueue_script('kitgenix-captcha-for-cloudflare-turnstile', $url, [], null, true);

        // Defer Turnstile script if enabled
        if (!empty($settings['defer_scripts'])) {
            \add_filter('script_loader_tag', function ($tag, $handle) {
                if ('kitgenix-captcha-for-cloudflare-turnstile' === $handle) {
                    // Use async instead of defer for Turnstile script
                    $tag = str_replace(' src', ' async src', $tag);
                }
                return $tag;
            }, 10, 2);
        }

        // Load frontend JS/CSS
        \wp_enqueue_script('kitgenix-captcha-for-cloudflare-turnstile-public', KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'js/public.js', ['jquery'], KitgenixCaptchaForCloudflareTurnstileVERSION, true);
        \wp_enqueue_style('kitgenix-captcha-for-cloudflare-turnstile-public', KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'css/public.css', [], KitgenixCaptchaForCloudflareTurnstileVERSION);

        // Output config as inline script before public.js
        $config = [
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
