<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

defined('ABSPATH') || exit;

class Site_Health {

    public static function init() {
        \add_filter('site_status_tests', [__CLASS__, 'register_tests']);
    }

    public static function register_tests($tests) {
        $tests['direct']['kitgenix_turnstile_readiness'] = [
            'label' => \__('Cloudflare Turnstile readiness', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'test'  => [__CLASS__, 'test_readiness'],
        ];
        return $tests;
    }

    /**
     * Main readiness test.
     * @return array{status:string,label:string,description:string,actions:string,badge:array,test:string}
     */
    public static function test_readiness(): array {
        $settings = \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = (string) ($settings['site_key'] ?? '');
        $secret   = (string) ($settings['secret_key'] ?? '');

        $issues = [];
        $status = 'good';

        // 1) Keys present
        if ($site_key === '' || $secret === '') {
            $status = 'critical';
            $issues[] = '<li>' . \esc_html__('Missing Site Key and/or Secret Key.', 'kitgenix-captcha-for-cloudflare-turnstile') . '</li>';
        }

        // 2) Duplicate API loader (set by Script_Handler::detect_duplicate_loader on frontend)
        $dupe = \get_transient('kitgenix_turnstile_duplicate_scripts');
        if (\is_array($dupe) && !empty($dupe['matches'])) {
            if ($status !== 'critical') { $status = 'recommended'; }
            $list = '';
            foreach ($dupe['matches'] as $handle => $src) {
                $list .= '<li><code>' . \esc_html((string) $handle) . '</code> — <span style="word-break:break-all;">' . \esc_html((string) $src) . '</span></li>';
            }
            $issues[] =
                '<li>' .
                \esc_html__('Duplicate Turnstile API detected (another plugin/theme also loads api.js).', 'kitgenix-captcha-for-cloudflare-turnstile') .
                '<ul style="margin:6px 0 0 18px;list-style:disc;">' . $list . '</ul>' .
                '</li>';
        }

        // 3) Last verification status (stored by validator)
        $diag = \get_option('kitgenix_turnstile_last_verify', []);
        if (\is_array($diag) && !empty($diag)) {
            $ok     = !empty($diag['success']);
            $ts     = isset($diag['time']) ? (int) $diag['time'] : 0;

            // Prefer wp_date() (WP 5.3+) and fall back to date_i18n().
            $format = (string) \get_option('date_format') . ' ' . (string) \get_option('time_format');
            if (\function_exists('wp_date')) {
                $when = $ts ? \wp_date($format, $ts) : '';
            } else {
                $when = $ts ? \date_i18n($format, $ts) : '';
            }

            $codes  = isset($diag['codes']) && \is_array($diag['codes']) ? implode(', ', array_map('sanitize_text_field', $diag['codes'])) : '';
            if (!$ok) {
                if ($status !== 'critical') { $status = 'recommended'; }
                /* translators: 1: date/time, 2: error codes list */
                $issues[] = '<li>' . \sprintf(
                    \esc_html__('Last Turnstile verification failed (%1$s). Error codes: %2$s', 'kitgenix-captcha-for-cloudflare-turnstile'),
                    \esc_html($when ?: \__('unknown time', 'kitgenix-captcha-for-cloudflare-turnstile')),
                    \esc_html($codes ?: \__('none reported', 'kitgenix-captcha-for-cloudflare-turnstile'))
                ) . '</li>';
            } else {
                if ($status === 'good') {
                    /* translators: %s: date/time of last successful verification */
                    $issues[] = '<li>' . \sprintf(
                        \esc_html__('Last Turnstile verification succeeded (%s).', 'kitgenix-captcha-for-cloudflare-turnstile'),
                        \esc_html($when ?: \__('recently', 'kitgenix-captcha-for-cloudflare-turnstile'))
                    ) . '</li>';
                }
            }
        } else {
            // No data yet — likely no submissions since install.
            $issues[] = '<li>' . \esc_html__('No verification data yet (no recent submissions).', 'kitgenix-captcha-for-cloudflare-turnstile') . '</li>';
        }

        // 4) Caching/optimization plugins possibly delaying JS (heuristic)
        if (self::maybe_js_delayed()) {
            if ($status !== 'critical') { $status = 'recommended'; }
            $issues[] = '<li>' .
                \esc_html__('A caching/optimization plugin is active and may be delaying or deferring the Turnstile API, which can break rendering.', 'kitgenix-captcha-for-cloudflare-turnstile') .
                ' ' .
                \esc_html__('Ensure the following URL is excluded from “Delay JS” / “Defer JS” / “Combine JS” rules:', 'kitgenix-captcha-for-cloudflare-turnstile') .
                '<br><code>' . \esc_html('https://challenges.cloudflare.com/turnstile/v0/api.js') . '</code>' .
                '</li>';
        }

        // Build HTML (keep as HTML; wp-core expects it)
        $desc  = '<p>' . \esc_html__('Checks your Turnstile configuration and common pitfalls.', 'kitgenix-captcha-for-cloudflare-turnstile') . '</p>';
        $desc .= '<ul style="margin-left:18px;list-style:disc;">' . implode('', $issues) . '</ul>';

        $settings_url = \admin_url('options-general.php?page=kitgenix-captcha-for-cloudflare-turnstile');
        $actions = '<p><a class="button button-primary" href="' . \esc_url($settings_url) . '">' .
            \esc_html__('Open Turnstile Settings', 'kitgenix-captcha-for-cloudflare-turnstile') .
        '</a></p>';

        return [
            'status'      => $status, // good | recommended | critical
            'label'       => \__('Cloudflare Turnstile readiness', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'description' => $desc,
            'actions'     => $actions,
            'badge'       => [
                'label' => \__('Security', 'kitgenix-captcha-for-cloudflare-turnstile'),
                'color' => 'blue',
            ],
            'test'        => 'kitgenix_turnstile_readiness',
        ];
    }

    /**
     * Heuristic: is any popular caching/optimization plugin active?
     */
    private static function maybe_js_delayed(): bool {
        $active = (array) \apply_filters('active_plugins', \get_option('active_plugins', []));
        if (\is_multisite()) {
            $network = (array) \get_site_option('active_sitewide_plugins', []);
            $active  = array_merge($active, array_keys($network));
        }
        $candidates = [
            'wp-rocket/wp-rocket.php',
            'autoptimize/autoptimize.php',
            'litespeed-cache/litespeed-cache.php',
            'w3-total-cache/w3-total-cache.php',
            'sg-cachepress/sg-cachepress.php',
            'hummingbird-performance/wp-hummingbird.php',
            'asset-cleanup/asset-cleanup.php',
            'perfmatters/perfmatters.php',
            'flying-press/flying-press.php',
        ];
        foreach ($candidates as $slug) {
            if (in_array($slug, $active, true)) {
                return true;
            }
        }
        return false;
    }
}
