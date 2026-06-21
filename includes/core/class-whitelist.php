<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Whitelist {

    /** @var null|bool Cache the decision per request */
    private static $cached_is_whitelisted = null;

    /**
     * Initialize if needed (utility style for now).
     */
    public static function init() {
        // no global hooks required
    }

    /**
     * Check if the current user/IP/User Agent is whitelisted.
     *
     * @return bool
     */
    public static function is_whitelisted(): bool {
        if (self::$cached_is_whitelisted !== null) {
            return (bool) self::$cached_is_whitelisted;
        }

        $settings = \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);

        // 1) Logged-in users
        if (!empty($settings['whitelist_loggedin']) && \is_user_logged_in()) {
            return self::cache_and_filter(true, 'logged_in');
        }

        // 2) IP whitelist (exact, wildcard "*", or CIDR v4/v6)
        $client_ip = self::get_client_ip();
        if ($client_ip && !empty($settings['whitelist_ips'])) {
            $ip_patterns = self::split_lines((string) $settings['whitelist_ips']);
            foreach ($ip_patterns as $pattern) {
                if ($pattern === '') {
                    continue;
                }
                if (self::ip_matches($client_ip, $pattern)) {
                    return self::cache_and_filter(true, 'ip');
                }
            }
        }

        // 3) User-Agent whitelist (substring or "*" wildcard, case-insensitive)
        if (!empty($settings['whitelist_user_agents']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $ua       = strtolower(\sanitize_text_field(\wp_unslash($_SERVER['HTTP_USER_AGENT'])));
            $patterns = array_map('strtolower', self::split_lines((string) $settings['whitelist_user_agents']));
            foreach ($patterns as $pattern) {
                if ($pattern === '') {
                    continue;
                }
                if (self::ua_matches($ua, $pattern)) {
                    return self::cache_and_filter(true, 'user_agent');
                }
            }
        }

        return self::cache_and_filter(false, 'none');
    }

    /**
     * Determine client IP using the hardened helper (trusts CF/XFF only when remote is a trusted proxy).
     *
     * @return string
     */
    private static function get_client_ip(): string {
        // Prefer the dedicated helper if available (added in this version).
        if (\class_exists(__NAMESPACE__ . '\\Client_IP')) {
            return Client_IP::get();
        }

        // Fallback: REMOTE_ADDR only (no proxy trust).
        $remote = isset($_SERVER['REMOTE_ADDR'])
            ? \sanitize_text_field(\wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        return \filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    /**
     * Exact, wildcard, or CIDR match for IPs (IPv4/IPv6).
     */
    private static function ip_matches(string $ip, string $pattern): bool {
        $ip      = \trim($ip);
        $pattern = \trim($pattern);

        // CIDR?
        if (\strpos($pattern, '/') !== false) {
            return self::ip_in_cidr($ip, $pattern);
        }

        // Wildcard?
        if (\strpos($pattern, '*') !== false) {
            // Use fnmatch if available (handles dots/colons).
            if (\function_exists('fnmatch')) {
                return \fnmatch($pattern, $ip, FNM_CASEFOLD);
            }
            // Fallback to regex
            $quoted = \preg_quote($pattern, '~');
            $regex  = '~^' . \str_replace('\*', '.*', $quoted) . '$~i';
            return (bool) \preg_match($regex, $ip);
        }

        // Exact
        return \strcasecmp($ip, $pattern) === 0;
    }

    /**
     * Check if $ip is within $cidr (supports v4 and v6).
     */
    private static function ip_in_cidr(string $ip, string $cidr): bool {
        $parts = \explode('/', $cidr, 2);
        if (\count($parts) !== 2) {
            return false;
        }

        $network = \trim($parts[0]);
        $bits    = \is_numeric($parts[1]) ? (int) $parts[1] : null;

        $ip_bin      = @\inet_pton($ip);
        $network_bin = @\inet_pton($network);

        if ($ip_bin === false || $network_bin === false || $ip_bin === null || $network_bin === null) {
            return false;
        }
        if (\strlen($ip_bin) !== \strlen($network_bin)) {
            // IPv4 vs IPv6 mismatch
            return false;
        }

        $max_bits = \strlen($ip_bin) * 8;
        if ($bits === null || $bits < 0 || $bits > $max_bits) {
            return false;
        }

        // Build mask
        $full_bytes = intdiv($bits, 8);
        $remainder  = $bits % 8;

        $mask = \str_repeat("\xff", $full_bytes);
        if ($remainder) {
            $mask .= \chr((0xff << (8 - $remainder)) & 0xff);
        }
        $mask = \str_pad($mask, \strlen($ip_bin), "\0");

        // Compare masked network addresses
        return ( ($ip_bin & $mask) === ($network_bin & $mask) );
    }

    /**
     * Simple UA match: supports "*" wildcard or substring (case-insensitive).
     */
    private static function ua_matches(string $ua, string $pattern): bool {
        $pattern = \trim($pattern);
        if ($pattern === '') {
            return false;
        }
        if (\strpos($pattern, '*') !== false) {
            if (\function_exists('fnmatch')) {
                return \fnmatch($pattern, $ua, FNM_CASEFOLD);
            }
            $quoted = \preg_quote($pattern, '~');
            $regex  = '~' . \str_replace('\*', '.*', $quoted) . '~i';
            return (bool) \preg_match($regex, $ua);
        }
        return (\strpos($ua, $pattern) !== false);
    }

    /**
     * Split textarea-like input into trimmed lines, accepting newlines or commas.
     *
     * @param string $text
     * @return array
     */
    private static function split_lines(string $text): array {
        $parts = \preg_split('/[\r\n,]+/', (string) $text);
        $parts = \array_map('trim', $parts);
        return \array_values(\array_filter($parts, static function ($v) {
            return $v !== '';
        }));
    }

    /**
     * Cache and filter the whitelist result.
     *
     * @param bool   $result
     * @param string $reason One of: logged_in, ip, user_agent, none
     * @return bool
     */
    private static function cache_and_filter(bool $result, string $reason): bool {
        $client_ip = self::get_client_ip();
        $ua        = isset($_SERVER['HTTP_USER_AGENT']) ? \sanitize_text_field(\wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $filtered = (bool) \apply_filters('kitgenix_turnstile_is_whitelisted', $result, [
            'reason'     => $reason,
            'client_ip'  => $client_ip,
            'user_agent' => $ua,
        ]);

        self::$cached_is_whitelisted = $filtered;
        return $filtered;
    }
}
