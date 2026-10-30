<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

use function get_option;
use function get_transient;
use function set_transient;
use function delete_transient;
use function sanitize_user;
use function human_time_diff;
use function apply_filters;
use function add_filter;
use function add_action;
use function __;

/**
 * Login rate limiting / lockouts after repeated failed attempts, per IP and per
 * username (roadmap: "Login rate limiting and lockouts"). Hooks the core
 * `authenticate` filter at a very high priority so it has the final say over
 * whatever any earlier check (WordPress's own password check, this plugin's
 * own Turnstile check in WP_Core::validate_login(), or another plugin's)
 * decided – a locked-out IP/username is blocked even if the password and
 * Turnstile were both otherwise correct.
 *
 * Because `wp_authenticate()` – and therefore the `authenticate` filter and
 * the `wp_login_failed` action this class listens to – is also the mechanism
 * WordPress core uses for the REST API's Application Password auth AND for
 * XML-RPC logins (both ultimately call wp_authenticate()), this single
 * mechanism also covers the roadmap's "Block XML-RPC and application-password
 * brute force" item: a flood of XML-RPC or Application Password login
 * attempts from one IP (e.g. via an XML-RPC system.multicall batch) trips the
 * same per-IP bucket as a flood of wp-login.php attempts.
 */
class Login_Lockout {
    private const SETTINGS_OPTION = 'kitgenix_captcha_for_cloudflare_turnstile_settings';
    // Must start with 'kitgenix_turnstile_' – uninstall.php's transient cleanup
    // matches on that prefix, so a different prefix here would leave orphaned
    // wp_options rows behind after uninstall.
    private const BUCKET_PREFIX   = 'kitgenix_turnstile_lockout_';

    private const DEFAULT_MAX_ATTEMPTS   = 5;
    private const DEFAULT_LOCKOUT_MINUTES = 15;

    /** Our own lockout rejection's WP_Error code – never counted as a new failure. */
    private const ERROR_CODE = 'kitgenix_locked_out';

    public static function init(): void {
        if ( ! self::enabled() ) {
            return;
        }

        // Very high priority so this runs after every other `authenticate`
        // callback (core's password check, this plugin's own Turnstile check,
        // Application Password auth, any other security plugin) and can
        // override a result that would otherwise succeed.
        add_filter( 'authenticate', [ __CLASS__, 'enforce_lockout' ], 9999, 3 );
        add_action( 'wp_login_failed', [ __CLASS__, 'on_login_failed' ], 10, 2 );
        add_action( 'wp_login', [ __CLASS__, 'on_login_success' ], 10, 1 );
    }

    private static function settings(): array {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        return is_array( $settings ) ? $settings : [];
    }

    private static function enabled(): bool {
        return ! empty( self::settings()['login_lockout_enabled'] );
    }

    private static function max_attempts(): int {
        $settings = self::settings();
        $n = isset( $settings['login_lockout_max_attempts'] ) ? (int) $settings['login_lockout_max_attempts'] : self::DEFAULT_MAX_ATTEMPTS;
        $n = (int) apply_filters( 'kitgenix_turnstile_login_lockout_max_attempts', $n );
        return $n > 0 ? $n : self::DEFAULT_MAX_ATTEMPTS;
    }

    private static function lockout_seconds(): int {
        $settings = self::settings();
        $minutes  = isset( $settings['login_lockout_minutes'] ) ? (int) $settings['login_lockout_minutes'] : self::DEFAULT_LOCKOUT_MINUTES;
        $minutes  = (int) apply_filters( 'kitgenix_turnstile_login_lockout_minutes', $minutes );
        return max( 1, $minutes ) * MINUTE_IN_SECONDS;
    }

    private static function bucket_key( string $type, string $raw ): string {
        return self::BUCKET_PREFIX . $type . '_' . md5( $raw . '|' . \wp_salt( 'auth' ) );
    }

    /**
     * @return array{count:int,locked_until:int}
     */
    private static function get_bucket( string $key ): array {
        $data = get_transient( $key );
        if ( ! is_array( $data ) ) {
            return [ 'count' => 0, 'locked_until' => 0 ];
        }
        return [
            'count'        => max( 0, (int) ( $data['count'] ?? 0 ) ),
            'locked_until' => max( 0, (int) ( $data['locked_until'] ?? 0 ) ),
        ];
    }

    private static function is_locked( array $bucket ): bool {
        return $bucket['locked_until'] > time();
    }

    private static function normalized_username( string $username ): string {
        return strtolower( sanitize_user( $username, true ) );
    }

    /**
     * Final word on whether this IP/username may attempt to authenticate at all.
     * Runs at priority 9999 – after core's own password check and this plugin's
     * Turnstile check – so a lockout always wins.
     *
     * @param \WP_User|\WP_Error|null $user
     */
    public static function enforce_lockout( $user, string $username, string $password ) {
        if ( '' === trim( $username ) ) {
            return $user;
        }

        $ip_bucket   = self::get_bucket( self::bucket_key( 'ip', Client_IP::get() ) );
        $user_bucket = self::get_bucket( self::bucket_key( 'user', self::normalized_username( $username ) ) );

        if ( ! self::is_locked( $ip_bucket ) && ! self::is_locked( $user_bucket ) ) {
            return $user;
        }

        $retry_after = max( $ip_bucket['locked_until'], $user_bucket['locked_until'] ) - time();
        $retry_after = max( MINUTE_IN_SECONDS, $retry_after );

        return new \WP_Error(
            self::ERROR_CODE,
            sprintf(
                /* translators: %s: human-readable time until the lockout clears, e.g. "15 minutes" */
                __( 'Too many failed login attempts. Please try again in %s.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                human_time_diff( time(), time() + $retry_after )
            )
        );
    }

    /**
     * Count a failed login (wrong password OR failed Turnstile OR anything else
     * that made wp_authenticate() return a WP_Error) toward both the IP and the
     * username bucket – except our own lockout rejection, which must never
     * extend its own lockout indefinitely just because the same attacker (or a
     * confused real user) keeps retrying while already locked out.
     *
     * @param string         $username
     * @param \WP_Error|null $error Available since WP 5.9; degrades gracefully on older WP.
     */
    public static function on_login_failed( string $username, $error = null ): void {
        if ( '' === trim( $username ) ) {
            return;
        }
        if ( $error instanceof \WP_Error && self::ERROR_CODE === $error->get_error_code() ) {
            return;
        }

        self::bump( self::bucket_key( 'ip', Client_IP::get() ) );
        self::bump( self::bucket_key( 'user', self::normalized_username( $username ) ) );
    }

    private static function bump( string $key ): void {
        $bucket = self::get_bucket( $key );
        $bucket['count']++;

        if ( $bucket['count'] >= self::max_attempts() ) {
            $bucket['locked_until'] = time() + self::lockout_seconds();
        }

        // The bucket itself decays after one lockout window with no further
        // failures, which also doubles as the "attempt counting window".
        set_transient( $key, $bucket, self::lockout_seconds() );
    }

    public static function on_login_success( string $user_login ): void {
        if ( '' === trim( $user_login ) ) {
            return;
        }
        delete_transient( self::bucket_key( 'ip', Client_IP::get() ) );
        delete_transient( self::bucket_key( 'user', self::normalized_username( $user_login ) ) );
    }
}
