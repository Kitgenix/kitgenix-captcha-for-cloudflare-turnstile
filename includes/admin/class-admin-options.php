<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

defined('ABSPATH') || exit;

class Admin_Options {

    /**
     * Option name used in the database.
     */
    const OPTION_NAME = 'kitgenix_captcha_for_cloudflare_turnstile_settings';

    /**
     * Initialize settings registration.
     */
    public static function init(): void {
        \add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register plugin settings using WordPress Settings API.
     */
    public static function register_settings(): void {
        \register_setting(
            'kitgenix_captcha_for_cloudflare_turnstile_settings_group',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'description'       => \__('Kitgenix CAPTCHA for Cloudflare Turnstile Settings', 'kitgenix-captcha-for-cloudflare-turnstile'),
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'show_in_rest'      => false,
                'default'           => [],
            ]
        );
    }

    /**
     * Sanitize the settings before saving.
     *
     * @param array $settings Raw settings array from the form (may be slashed).
     * @return array Clean settings to be stored.
     */
    public static function sanitize($settings): array {
        // Verify nonce (do not wipe options on failure)
        $nonce = '';
        if (isset($_POST['kitgenix_captcha_for_cloudflare_turnstile_settings_nonce'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- we verify below
            $nonce = \sanitize_text_field(\wp_unslash($_POST['kitgenix_captcha_for_cloudflare_turnstile_settings_nonce']));
        }
        if (empty($nonce) || !\function_exists('wp_verify_nonce') || !\wp_verify_nonce($nonce, 'kitgenix_captcha_for_cloudflare_turnstile_settings_save')) {
            return \function_exists('get_option') ? (array) \get_option(self::OPTION_NAME, []) : [];
        }

        $settings = \is_array($settings) ? $settings : [];
        $clean    = [];

        // --- Site keys ---
        $clean['site_key'] = \sanitize_text_field($settings['site_key'] ?? '');

        // Secret handling: For safety we do not rely on the form field being
        // populated when a secret already exists. If the admin leaves the
        // secret input empty but `secret_key_present` was set, preserve the
        // existing stored secret unless `secret_key_clear` is explicitly set.
        $incoming_secret = isset($settings['secret_key']) ? \sanitize_text_field($settings['secret_key']) : '';
        $secret_present_flag = !empty($settings['secret_key_present']);
        $clear_secret = !empty($settings['secret_key_clear']);

        if ($clear_secret) {
            $clean['secret_key'] = '';
        } elseif ($incoming_secret === '' && $secret_present_flag) {
            // Preserve previously stored secret when the admin didn't submit a new one.
            $current = \get_option(self::OPTION_NAME, []);
            $clean['secret_key'] = \sanitize_text_field($current['secret_key'] ?? '');
        } else {
            $clean['secret_key'] = $incoming_secret;
        }

        // --- Master toggles (integrations) ---
        $clean['enable_wordpress']       = !empty($settings['enable_wordpress']) ? 1 : 0;
        $clean['enable_woocommerce']     = !empty($settings['enable_woocommerce']) ? 1 : 0;
        $clean['enable_elementor']       = !empty($settings['enable_elementor']) ? 1 : 0;
        $clean['enable_wpforms']         = !empty($settings['enable_wpforms']) ? 1 : 0;
        $clean['enable_fluentforms']     = !empty($settings['enable_fluentforms']) ? 1 : 0;
        $clean['enable_gravityforms']    = !empty($settings['enable_gravityforms']) ? 1 : 0;
        $clean['enable_cf7']             = !empty($settings['enable_cf7']) ? 1 : 0;
        $clean['enable_formidableforms'] = !empty($settings['enable_formidableforms']) ? 1 : 0;
        $clean['enable_forminator']      = !empty($settings['enable_forminator']) ? 1 : 0;
        $clean['enable_jetpackforms']    = !empty($settings['enable_jetpackforms']) ? 1 : 0;
        $clean['enable_kadenceforms']    = !empty($settings['enable_kadenceforms']) ? 1 : 0;
        $clean['enable_buddypress']      = !empty($settings['enable_buddypress']) ? 1 : 0;
        $clean['enable_bbpress']         = !empty($settings['enable_bbpress']) ? 1 : 0;

        // --- Per-form toggles (WordPress Core) ---
        $clean['wp_login_form']        = !empty($settings['wp_login_form']) ? 1 : 0;
        $clean['wp_register_form']     = !empty($settings['wp_register_form']) ? 1 : 0;
        $clean['wp_lostpassword_form'] = !empty($settings['wp_lostpassword_form']) ? 1 : 0;
        $clean['wp_comments_form']     = !empty($settings['wp_comments_form']) ? 1 : 0;

        // --- Per-form toggles (WooCommerce) ---
        $clean['wc_checkout_form']     = !empty($settings['wc_checkout_form']) ? 1 : 0;
        $clean['wc_login_form']        = !empty($settings['wc_login_form']) ? 1 : 0;
        $clean['wc_register_form']     = !empty($settings['wc_register_form']) ? 1 : 0;
        $clean['wc_lostpassword_form'] = !empty($settings['wc_lostpassword_form']) ? 1 : 0;

        // --- Display settings ---
        $theme = $settings['theme'] ?? 'auto';
        $clean['theme'] = \in_array($theme, ['auto', 'light', 'dark'], true) ? $theme : 'auto';

    $size = $settings['widget_size'] ?? 'normal';
    // Allow new 'flexible' size (full-width) per Cloudflare docs.
    $clean['widget_size'] = \in_array($size, ['small', 'medium', 'large', 'normal', 'flexible'], true) ? $size : 'normal';

        $appearance = $settings['appearance'] ?? 'always';
        $clean['appearance'] = \in_array($appearance, ['always', 'interaction-only'], true) ? $appearance : 'always';

        $allowed_langs = ['auto','en','es','fr','de','it','pt','ru','zh-CN','zh-TW','ja','ko','ar','tr','pl','nl','sv','fi','da','no','cs','hu','el','he','uk','ro','bg','id','th','vi'];
        $lang = \sanitize_text_field($settings['language'] ?? 'auto');
        $clean['language'] = \in_array($lang, $allowed_langs, true) ? $lang : 'auto';

        $clean['disable_submit'] = !empty($settings['disable_submit']) ? 1 : 0;
        $clean['defer_scripts']  = !empty($settings['defer_scripts']) ? 1 : 0;

        // Replay protection: default ON unless explicitly disabled.
        $clean['replay_protection'] = isset($settings['replay_protection'])
            ? (!empty($settings['replay_protection']) ? 1 : 0)
            : 1;

        // Dev mode (warn-only)
        $clean['dev_mode_warn_only'] = !empty($settings['dev_mode_warn_only']) ? 1 : 0;

        // --- Messages ---
        $clean['error_message'] = \sanitize_text_field($settings['error_message'] ?? '');
        $clean['extra_message'] = \sanitize_text_field($settings['extra_message'] ?? '');

        // --- Whitelist (logged-in + IPs + UAs) ---
        $clean['whitelist_loggedin']      = !empty($settings['whitelist_loggedin']) ? 1 : 0;
        $clean['whitelist_ips']           = self::sanitize_ip_patterns($settings['whitelist_ips'] ?? '');
        $clean['whitelist_user_agents']   = self::sanitize_lines($settings['whitelist_user_agents'] ?? '');

        // --- Legacy/compat flags (kept to support old imports/UI) ---
        // Not used by core logic, but we persist if provided so exports round-trip cleanly.
        $clean['respect_proxy_headers'] = !empty($settings['respect_proxy_headers']) ? 1 : 0;
        $clean['trusted_proxy_ips']     = self::sanitize_ip_patterns($settings['trusted_proxy_ips'] ?? '');

        // --- Proxy / Cloudflare trust (used by Client_IP) ---
        $clean['trust_proxy'] = !empty($settings['trust_proxy']) ? 1 : 0;

        // Prefer new key 'trusted_proxies'; if absent, fall back to legacy 'trusted_proxy_ips'
        $trusted_input = $settings['trusted_proxies'] ?? ($settings['trusted_proxy_ips'] ?? '');
        $clean['trusted_proxies'] = self::sanitize_trusted_proxies_block($trusted_input);

    // --- Per-integration mode flags ('auto' or 'shortcode') ---
    $allowed_modes = ['auto', 'shortcode'];

    $mode = \sanitize_text_field( $settings['mode_wpforms'] ?? 'auto' );
    $clean['mode_wpforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_fluentforms'] ?? 'auto' );
    $clean['mode_fluentforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_gravityforms'] ?? 'auto' );
    $clean['mode_gravityforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_forminator'] ?? 'auto' );
    $clean['mode_forminator'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_formidableforms'] ?? 'auto' );
    $clean['mode_formidableforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_cf7'] ?? 'auto' );
    $clean['mode_cf7'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_jetpackforms'] ?? 'auto' );
    $clean['mode_jetpackforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_kadenceforms'] ?? 'auto' );
    $clean['mode_kadenceforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    // Non-form integrations
    $mode = \sanitize_text_field( $settings['mode_woocommerce'] ?? 'auto' );
    $clean['mode_woocommerce'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_woocommerce_blocks'] ?? 'auto' );
    $clean['mode_woocommerce_blocks'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_elementor'] ?? 'auto' );
    $clean['mode_elementor'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_wp_core'] ?? 'auto' );
    $clean['mode_wp_core'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

        return $clean;
    }

    /**
     * Sanitize a textarea of IP patterns (one per line). We allow:
     *  - exact IPv4/IPv6
     *  - CIDR (v4/v6), e.g. 203.0.113.0/24 or 2001:db8::/32
     *  - wildcards using * (v4/v6), e.g. 203.0.113.* or 2001:db8::*
     */
    private static function sanitize_ip_patterns(string $text): string {
        $text  = (string) $text;
        $lines = preg_split('/[\r\n,]+/', $text);
        $out   = [];

        foreach ($lines as $raw) {
            $line = trim((string) $raw);
            if ($line === '') {
                continue;
            }
            // Keep wildcards/CIDR chars; sanitize to safe subset
            $line = \wp_kses_post($line);
            $line = preg_replace('~[^\w\.\:\*\/\-]+~u', '', (string) $line);
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            // Basic sanity for CIDR suffix if present: "/0".."128" max (IPv6)
            if (strpos($line, '/') !== false) {
                [$addr, $bits] = array_pad(explode('/', $line, 2), 2, '');
                $bits = trim($bits);
                if ($bits === '' || !ctype_digit($bits)) {
                    continue;
                }
                $bits = (int) $bits;
                if ($bits < 0 || $bits > 128) {
                    continue;
                }
                // Allow IPv4/IPv6 in $addr; we don't over-validate here.
            }

            $out[] = $line;
        }

        return implode("\n", array_unique($out));
    }

    /**
     * Sanitize a textarea into clean lines (one per line).
     */
    private static function sanitize_lines(string $text): string {
        $text  = (string) $text;
        $lines = preg_split('/[\r\n]+/', $text);
        $out   = [];
        foreach ($lines as $raw) {
            $line = trim((string) $raw);
            if ($line === '') {
                continue;
            }
            $out[] = \sanitize_text_field($line);
        }
        return implode("\n", array_unique($out));
    }

    /**
     * Sanitize "trusted proxies" block (IPs or CIDRs, one per line).
     */
    private static function sanitize_trusted_proxies_block(string $text): string {
        $text  = (string) $text;
        $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $text));
        $out   = [];
        foreach ($lines as $line) {
            if ($line === '') { continue; }
            if (strpos($line, '/') !== false) {
                // CIDR
                if (self::is_valid_cidr_format($line)) {
                    $out[] = $line;
                }
            } else {
                if (filter_var($line, FILTER_VALIDATE_IP)) {
                    $out[] = $line;
                }
            }
        }
        return implode("\n", array_values(array_unique($out)));
    }

    /**
     * Retrieve plugin settings (safe fallback).
     */
    public static function get_settings(): array {
        if (!\function_exists('get_option')) {
            return [];
        }
        $settings = \get_option(self::OPTION_NAME, []);
        return \is_array($settings) ? $settings : [];
    }

    /**
     * Validate a CIDR string (e.g. 192.168.0.0/24 or 2001:db8::/32).
     */
    private static function is_valid_cidr_format(string $cidr): bool {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) { return false; }
        [$subnet, $mask] = $parts;
        if (!filter_var($subnet, FILTER_VALIDATE_IP)) { return false; }
        $mask = (int) $mask;
        $bits = strpos($subnet, ':') !== false ? 128 : 32;
        return $mask >= 0 && $mask <= $bits;
    }
}
