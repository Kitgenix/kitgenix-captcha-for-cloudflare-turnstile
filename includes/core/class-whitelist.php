<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Whitelist {

    /**
     * Initialize if needed.
     */
    public static function init() {
        // Currently no global hooks needed. Class is utility-style for now.
    }

    /**
     * Check if the current user/IP/User Agent is whitelisted.
     *
     * @return bool
     */
    public static function is_whitelisted(): bool {
        $settings = \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);

        // 1. Logged-in user
        if (!empty($settings['whitelist_loggedin']) && \is_user_logged_in()) {
            return true;
        }

        // 2. Whitelisted IPs
        if (!empty($settings['whitelist_ips'])) {
            $client_ip = self::get_ip_address();
            $ips = array_filter(array_map('trim', explode("\n", $settings['whitelist_ips'])));
            if (in_array($client_ip, $ips, true)) {
                return true;
            }
        }

        // 3. Whitelisted User Agents
        if (!empty($settings['whitelist_user_agents']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $ua = strtolower(\sanitize_text_field(\wp_unslash($_SERVER['HTTP_USER_AGENT'])));
            $patterns = array_filter(array_map('trim', explode("\n", strtolower($settings['whitelist_user_agents']))));
            foreach ($patterns as $pattern) {
                if (stripos($ua, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retrieve the client IP address.
     *
     * @return string
     */
    private static function get_ip_address(): string {
        foreach ([
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ] as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', \sanitize_text_field(\wp_unslash($_SERVER[$key]))) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        return '0.0.0.0';
    }
}
