<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

use KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

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
        $dupe = Script_Handler::get_duplicate_loader_detection();
        if ( ! empty( $dupe['matches'] ) && \is_array( $dupe['matches'] ) ) {
            if ($status !== 'critical') { $status = 'recommended'; }
            $list = '';
            foreach ($dupe['matches'] as $handle => $src) {
                $list .= '<li><code>' . \esc_html((string) $handle) . '</code> – <span style="word-break:break-all;">' . \esc_html((string) $src) . '</span></li>';
            }
            $issues[] =
                '<li>' .
                \esc_html__('Duplicate Turnstile API detected (another plugin/theme also loads api.js).', 'kitgenix-captcha-for-cloudflare-turnstile') .
                '<ul style="margin:6px 0 0 18px;list-style:disc;">' . $list . '</ul>' .
                '</li>';
        }

        foreach ( Turnstile_Validator::get_active_alerts() as $alert ) {
            $severity = (string) ( $alert['severity'] ?? 'warning' );

            if ( 'error' === $severity ) {
                $status = 'critical';
            } elseif ( $status !== 'critical' ) {
                $status = 'recommended';
            }

            $issues[] = '<li><strong>'
                . \esc_html( (string) ( $alert['title'] ?? __( 'Turnstile alert', 'kitgenix-captcha-for-cloudflare-turnstile' ) ) )
                . '</strong> '
                . \esc_html( (string) ( $alert['message'] ?? '' ) )
                . '</li>';
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
                $issues[] = '<li>' . \sprintf(
                    /* translators: 1: date/time, 2: error codes list */
                    \esc_html__('Last Turnstile verification failed (%1$s). Error codes: %2$s', 'kitgenix-captcha-for-cloudflare-turnstile'),
                    \esc_html($when ?: \__('unknown time', 'kitgenix-captcha-for-cloudflare-turnstile')),
                    \esc_html($codes ?: \__('none reported', 'kitgenix-captcha-for-cloudflare-turnstile'))
                ) . '</li>';
            } else {
                if ($status === 'good') {
                    $issues[] = '<li>' . \sprintf(
                        /* translators: %s: date/time of last successful verification */
                        \esc_html__('Last Turnstile verification succeeded (%s).', 'kitgenix-captcha-for-cloudflare-turnstile'),
                        \esc_html($when ?: \__('recently', 'kitgenix-captcha-for-cloudflare-turnstile'))
                    ) . '</li>';
                }
            }
        } else {
            // No data yet – likely no submissions since install.
            $issues[] = '<li>' . \esc_html__('No verification data yet (no recent submissions).', 'kitgenix-captcha-for-cloudflare-turnstile') . '</li>';
        }

        // 4) Caching/optimization plugins possibly delaying JS (heuristic)
        if (self::maybe_js_delayed()) {
            if ($status !== 'critical') { $status = 'recommended'; }
            $issues[] = '<li>' .
                \esc_html__('A caching/optimization plugin is active and may be delaying or deferring the Turnstile API, which can break rendering.', 'kitgenix-captcha-for-cloudflare-turnstile') .
                ' ' .
                \esc_html__('Ensure the following URL is excluded from “Delay JS” / “Defer JS” / “Combine JS” rules:', 'kitgenix-captcha-for-cloudflare-turnstile') .
                '<br><code>' . \esc_html('https://challenges.cloudflare.com/turnstile/v0/api.js') . '</code>' . // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Plain-text mention of Cloudflare's own widget script URL in Site Health troubleshooting copy, not an offloaded asset.
                '</li>';
        }

        // Build HTML (keep as HTML; wp-core expects it)
        $desc  = '<p>' . \esc_html__('Checks your Turnstile configuration and common pitfalls.', 'kitgenix-captcha-for-cloudflare-turnstile') . '</p>';
        $desc .= '<ul style="margin-left:18px;list-style:disc;">' . implode('', $issues) . '</ul>';

        $settings_url = \admin_url('admin.php?page=kitgenix-captcha-for-cloudflare-turnstile');
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
     * Compute the discrete, actionable protection-health states for the admin
     * dashboard (distinct from test_readiness()'s single good/recommended/critical
     * verdict for WP Site Health – a dashboard card can show several states at
     * once, e.g. both "High failure rate" and "Duplicate Turnstile loader").
     *
     * "Widget not detected" is deliberately NOT one of these states: nothing in
     * this plugin currently observes whether the Turnstile widget actually
     * mounted client-side (that would need a new frontend beacon), so a claim
     * of "not detected" here would be a guess dressed up as a finding. "No
     * recent traffic" is the honest, verifiable state for that symptom.
     *
     * @return array<int,array{key:string,label:string,severity:string,description:string}>
     */
    public static function get_protection_states(): array {
        $settings = \get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = (string) ( $settings['site_key'] ?? '' );
        $secret   = (string) ( $settings['secret_key'] ?? '' );

        $states = [];

        if ( '' === $site_key || '' === $secret ) {
            $states[] = [
                'key'         => 'configuration_problem',
                'label'       => \__( 'Configuration problem', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'severity'    => 'critical',
                'description' => \__( 'Site Key and/or Secret Key is missing, so Turnstile cannot verify anything yet.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ];
        }

        $dupe = Script_Handler::get_duplicate_loader_detection();
        if ( ! empty( $dupe['matches'] ) && \is_array( $dupe['matches'] ) ) {
            $states[] = [
                'key'         => 'duplicate_loader',
                'label'       => \__( 'Duplicate Turnstile loader', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'severity'    => 'warning',
                'description' => \__( 'Another plugin or theme is also loading the Cloudflare Turnstile API script, which can break rendering or callbacks.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ];
        }

        foreach ( Turnstile_Validator::get_active_alerts() as $alert ) {
            $alert_key = (string) ( $alert['key'] ?? '' );
            if ( 'blocked-siteverify-requests' === $alert_key ) {
                $states[] = [
                    'key'         => 'cloudflare_unavailable',
                    'label'       => \__( 'Cloudflare unavailable', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    'severity'    => 'critical',
                    'description' => (string) ( $alert['message'] ?? '' ),
                ];
            } elseif ( 'verification-failure-spike' === $alert_key ) {
                $states[] = [
                    'key'         => 'high_failure_rate',
                    'label'       => \__( 'High failure rate', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    'severity'    => 'warning',
                    'description' => (string) ( $alert['message'] ?? '' ),
                ];
            }
        }

        // "No recent traffic": at least one integration is enabled and both keys
        // are configured, yet not a single check has ever been recorded. Only
        // meaningful once configuration is actually complete – an unconfigured
        // site already gets 'configuration_problem' above instead.
        if ( '' !== $site_key && '' !== $secret ) {
            $any_integration_enabled = false;
            foreach ( $settings as $setting_key => $setting_value ) {
                if ( 0 === strpos( (string) $setting_key, 'enable_' ) && ! empty( $setting_value ) ) {
                    $any_integration_enabled = true;
                    break;
                }
            }

            $metrics = Turnstile_Validator::get_metrics_snapshot();
            if ( $any_integration_enabled && (int) ( $metrics['checks_total'] ?? 0 ) === 0 ) {
                $states[] = [
                    'key'         => 'no_recent_traffic',
                    'label'       => \__( 'No recent traffic', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    'severity'    => 'info',
                    'description' => \__( 'At least one integration is enabled, but no Turnstile checks have been recorded yet. Visit a protected page to confirm the widget renders and submits correctly.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                ];
            }
        }

        if ( empty( $states ) ) {
            $states[] = [
                'key'         => 'healthy',
                'label'       => \__( 'Healthy', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'severity'    => 'good',
                'description' => \__( 'No configuration problems, verification failures, or duplicate loaders currently detected.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ];
        }

        return $states;
    }

    /**
     * Heuristic: is any popular caching/optimization plugin active?
     */
    private static function maybe_js_delayed(): bool {
        $active = (array) \get_option('active_plugins', []);
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
