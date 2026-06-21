<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Client_IP {

    /**
     * Get the best client IP:
     * - Default: REMOTE_ADDR (sanitized & validated)
     * - If proxy trust is enabled AND REMOTE_ADDR is a trusted proxy:
     *   prefer CF/True-Client-IP/XFF/X-Real-IP, taking the first PUBLIC routable IP.
     */
    public static function get(): string {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        $settings = function_exists('get_option') ? \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []) : [];

        // Back-compat: support both key pairs.
        $trust_flag = !empty($settings['trust_proxy']) || !empty($settings['respect_proxy_headers']);
        /** Allow integrators to force/disable trust for special stacks. */
        $trust_flag = (bool) \apply_filters('kitgenix_turnstile_trust_headers', $trust_flag, $settings);

        if (!$trust_flag || !self::is_trusted_proxy($remote, $settings)) {
            return $remote;
        }

        // Prefer Cloudflare & proxy headers (PUBLIC IPs only).
        $cfc = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';
        if ($cfc && self::is_public_ip($cfc)) {
            return $cfc;
        }

        $tci = isset($_SERVER['HTTP_TRUE_CLIENT_IP']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_TRUE_CLIENT_IP'] ) ) : '';
        if ($tci && self::is_public_ip($tci)) {
            return $tci;
        }

        // Left-most client in XFF that is PUBLIC.
        $xff = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
        if ($xff) {
            foreach (explode(',', $xff) as $ip) {
                $ip = trim($ip);
                if ($ip && self::is_public_ip($ip)) {
                    return $ip;
                }
            }
        }

        $xri = isset($_SERVER['HTTP_X_REAL_IP']) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) : '';
        if ($xri && self::is_public_ip($xri)) {
            return $xri;
        }

        // Fallback to the proxy address
        return $remote;
    }

    /**
     * Is REMOTE_ADDR in the trusted proxy list?
     */
    private static function is_trusted_proxy(string $remote, array $settings): bool {
        $list = self::trusted_proxies($settings);
        $list = \apply_filters('kitgenix_turnstile_trusted_proxies', $list, $settings);
        if (!$list) return false;

        foreach ($list as $entry) {
            if (strpos($entry, '/') !== false) {
                if (self::ip_in_cidr($remote, $entry)) return true;
            } else {
                if (strcasecmp($remote, $entry) === 0) return true;
            }
        }
        return false;
    }

    /**
     * Build trusted proxy list from settings (supports both legacy/new keys).
     * Accepts newlines OR commas; supports exact IPs and CIDR (v4/v6).
     */
    private static function trusted_proxies(array $settings): array {
        $raw_1 = (string) ($settings['trusted_proxies']   ?? '');
        $raw_2 = (string) ($settings['trusted_proxy_ips'] ?? ''); // back-compat
        $raw   = trim($raw_1 . "\n" . $raw_2);

        $lines = array_map('trim', preg_split('/[\r\n,]+/', $raw));
        $out = [];
        foreach ($lines as $line) {
            if ($line === '') continue;
            if (strpos($line, '/') !== false) {
                // CIDR
                if (self::is_valid_cidr($line)) $out[] = $line;
            } else {
                if (filter_var($line, FILTER_VALIDATE_IP)) $out[] = $line;
            }
        }
        return array_values(array_unique($out));
    }

    private static function is_valid_cidr(string $cidr): bool {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) return false;
        [$subnet, $mask] = $parts;
        if (!filter_var($subnet, FILTER_VALIDATE_IP)) return false;
        $mask = (int) $mask;
        $bits = strpos($subnet, ':') !== false ? 128 : 32;
        return $mask >= 0 && $mask <= $bits;
    }

    private static function ip_in_cidr(string $ip, string $cidr): bool {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $ip_bin     = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);
        if ($ip_bin === false || $subnet_bin === false || $ip_bin === null || $subnet_bin === null) return false;

        $mask = (int) $mask;
        $len_bytes = strlen($ip_bin);
        $total_bits = $len_bytes * 8;
        if ($mask < 0 || $mask > $total_bits) return false;

        $ip_bytes     = unpack('C*', $ip_bin);
        $subnet_bytes = unpack('C*', $subnet_bin);

        $full = intdiv($mask, 8);
        $rest = $mask % 8;

        for ($i = 1; $i <= $full; $i++) {
            if ($ip_bytes[$i] !== $subnet_bytes[$i]) return false;
        }
        if ($rest) {
            $maskByte = ~((1 << (8 - $rest)) - 1) & 0xFF;
            if ( ($ip_bytes[$full + 1] & $maskByte) !== ($subnet_bytes[$full + 1] & $maskByte) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Accept only public, routable IPs from headers.
     */
    private static function is_public_ip(string $ip): bool {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * wp_unslash → sanitize every superglobal read.
     */
    private static function clean(string $s): string {
        return \sanitize_text_field( \wp_unslash( $s ) );
    }
}
