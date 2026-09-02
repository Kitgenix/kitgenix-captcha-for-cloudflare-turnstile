<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

use function get_option;
use function apply_filters;
use function wp_remote_get;
use function is_wp_error;
use function wp_remote_retrieve_response_code;
use function get_transient;
use function set_transient;

/**
 * Independent, server-side health probe for Cloudflare's Turnstile service.
 *
 * Backs the "outage failsafe": when this plugin's own settings opt in, and this
 * probe independently confirms Cloudflare's Turnstile endpoint is unreachable or
 * erroring, protected forms are allowed to submit without a token instead of
 * being permanently blocked by something outside anyone's control. The probe is
 * server-side and cached, not a client-reported flag, so a visitor can't simply
 * claim Cloudflare is down to bypass verification.
 */
class Cloudflare_Health {
    private const STATUS_TRANSIENT = 'kitgenix_turnstile_cf_status';

    /**
     * Whether the outage failsafe is enabled in settings.
     */
    public static function failsafe_enabled(): bool {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        return ! empty( $settings['cf_failsafe_enabled'] );
    }

    /**
     * Whether Cloudflare's Turnstile endpoint currently appears unreachable/erroring.
     * Result is cached for a couple of minutes so this never adds a probe request
     * to every page load or form submission.
     */
    public static function is_down(): bool {
        $cached = get_transient( self::STATUS_TRANSIENT );
        if ( false !== $cached ) {
            return 'down' === $cached;
        }

        $is_down = self::probe();

        set_transient(
            self::STATUS_TRANSIENT,
            $is_down ? 'down' : 'up',
            (int) apply_filters( 'kitgenix_turnstile_cf_status_ttl', 2 * MINUTE_IN_SECONDS )
        );

        return $is_down;
    }

    /**
     * Whether the failsafe should actively bypass verification right now
     * (opted in AND Cloudflare confirmed down). Single call sites use this
     * rather than combining failsafe_enabled() + is_down() themselves.
     */
    public static function failsafe_active(): bool {
        return self::failsafe_enabled() && self::is_down();
    }

    /**
     * Perform the live probe. Uses Cloudflare's own API script as a lightweight,
     * unauthenticated endpoint that's always expected to respond quickly when the
     * service is healthy.
     */
    private static function probe(): bool {
        $url  = apply_filters( 'kitgenix_turnstile_cf_status_probe_url', 'https://challenges.cloudflare.com/turnstile/v0/api.js' ); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- health probe against Cloudflare Turnstile's own script endpoint, the plugin's core third-party CAPTCHA service; there is no self-hosted alternative.
        $resp = wp_remote_get( $url, [
            'timeout'   => (int) apply_filters( 'kitgenix_turnstile_cf_status_timeout', 5 ),
            'sslverify' => apply_filters( 'kitgenix_turnstile_cf_status_sslverify', true ),
        ] );

        if ( is_wp_error( $resp ) ) {
            return true;
        }

        return wp_remote_retrieve_response_code( $resp ) >= 500;
    }

    /**
     * Clear the cached status so the next check() call re-probes immediately.
     * Used by the admin UI (e.g. a "recheck now" action) and tests.
     */
    public static function clear_cache(): void {
        \delete_transient( self::STATUS_TRANSIENT );
    }
}
