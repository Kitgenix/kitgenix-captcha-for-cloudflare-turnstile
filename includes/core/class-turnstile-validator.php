<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

use function wp_verify_nonce;
use function get_option;
use function sanitize_text_field;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function wp_unslash;
use function apply_filters;
use function esc_html;
use function __;

class Turnstile_Validator {
    /** Store a small diagnostic snapshot of the last siteverify outcome. */
    private static function record_last_verify(bool $ok, array $codes = []): void {
        $payload = [
            'time'    => time(), // stored as epoch for consistency; display with gmdate() when needed
            'success' => $ok ? 1 : 0,
            'codes'   => array_values(array_map('strval', $codes)),
        ];
        \update_option('kitgenix_turnstile_last_verify', $payload, false);
    }

    /** @var array<string,bool> In-request cache of verified tokens to avoid double verification */
    private static $verified_tokens = [];

    /** @var array Last raw response from siteverify */
    private static $last_response = [];

    /** @var array Last error codes array from siteverify (if any) */
    private static $last_error_codes = [];

    /** @var string Last error message (if any) */
    private static $last_error_msg = '';

    /* =========================
     * Dev mode (warn-only)
     * ========================= */

    private static function dev_mode_enabled(): bool {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        return !empty($settings['dev_mode_warn_only']);
    }

    private static function log_dev(string $what, array $data = []): void {
        if ( self::dev_mode_enabled() && defined('WP_DEBUG') && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[Kitgenix Turnstile DEV] ' . $what . ' :: ' . \wp_json_encode($data));
        }
        \do_action('kitgenix_turnstile_dev_log', $what, $data);
    }

    /* =========================
     * Replay protection
     * ========================= */

    private static function flag_replay_frontend(): void {
        if ( headers_sent() ) { return; }
        $path   = defined('COOKIEPATH')    ? COOKIEPATH    : '/';
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie('kitgenix_captcha_for_cloudflare_turnstile_ts_replay', '1', time() + 120, $path, $domain, \is_ssl(), false);
    }

    private static function replay_enabled(): bool {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        return array_key_exists('replay_protection', $settings) ? !empty($settings['replay_protection']) : true;
    }

    private static function replay_ttl(): int {
        $ttl = (int) apply_filters('kitgenix_turnstile_replay_ttl', 10 * MINUTE_IN_SECONDS);
        return $ttl > 0 ? $ttl : 10 * MINUTE_IN_SECONDS;
    }

    private static function token_hash(string $token): string {
        return hash('sha256', $token . '|' . \wp_salt('auth'));
    }

    private static function replay_key(string $hash): string {
        return 'kitgenix_captcha_for_cloudflare_turnstile_ts_' . $hash;
    }

    private static function is_replay(string $token): bool {
        if ( ! self::replay_enabled() ) {
            return false;
        }
        $h = self::token_hash($token);
        return (bool) \get_transient( self::replay_key($h) );
    }

    private static function mark_used(string $token): void {
        if ( ! self::replay_enabled() ) {
            return;
        }
        $h   = self::token_hash($token);
        $key = self::replay_key($h);
        \set_transient( $key, 1, self::replay_ttl() );
    }

    /* =========================
     * Public API
     * ========================= */

    /**
     * Validate Turnstile challenge (nonce + token from request).
     *
     * @param bool $require_nonce
     * @return bool
     */
    public static function is_valid_submission($require_nonce = true): bool {
        self::$last_error_codes = [];
        self::$last_error_msg   = '';
        self::$last_response    = [];

        // Nonce
        if ( $require_nonce ) {
            $nonce = isset($_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'])
                ? sanitize_text_field( wp_unslash( $_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'] ) )
                : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'kitgenix_captcha_for_cloudflare_turnstile_action' ) ) {
                self::$last_error_codes[] = 'nonce_invalid';
                self::$last_error_msg     = __('Security check failed (nonce).', 'kitgenix-captcha-for-cloudflare-turnstile');
                self::log_dev('nonce_invalid');
                self::record_last_verify(false, self::$last_error_codes);
                return self::dev_mode_enabled();
            }
        }

        // Token
        $response = self::get_token_from_request();
        if ( $response === '' ) {
            self::$last_error_codes[] = 'token_missing';
            self::$last_error_msg     = __('Turnstile token missing.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('token_missing');
            self::record_last_verify(false, self::$last_error_codes);
            return self::dev_mode_enabled();
        }

        // Cloudflare verify (memoized)
        $ok = self::verify_with_site($response);

        if ( ! $ok ) {
            if (self::$last_error_msg === '') {
                // Map codes to friendly message
                self::$last_error_msg = self::message_from_error_codes(self::$last_error_codes, '');
            }
            self::log_dev('verification_failed', [
                'codes'   => self::$last_error_codes,
                'message' => self::$last_error_msg,
                'resp'    => self::$last_response,
            ]);
            self::record_last_verify(false, self::$last_error_codes);
            return self::dev_mode_enabled();
        }

        // SUCCESS → replay check
        if ( self::is_replay( $response ) ) {
            self::$last_error_codes[] = 'replay_detected';
            self::$last_error_msg     = __('Security check failed: token reuse detected. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('replay_detected');
            self::flag_replay_frontend();
            self::record_last_verify(false, self::$last_error_codes);
            return self::dev_mode_enabled();
        }

        self::mark_used( $response );
        self::record_last_verify(true, []);
        return true;
    }

    /**
     * Validate a token directly (for integrations that only have the token).
     */
    public static function validate_token($token): bool {
        self::$last_error_codes = [];
        self::$last_error_msg   = '';
        self::$last_response    = [];

        $token = sanitize_text_field( (string) $token );
        if ($token === '') {
            self::$last_error_codes = ['missing-input-response'];
            self::$last_error_msg   = __('Turnstile token missing.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::record_last_verify(false, self::$last_error_codes);
            return false;
        }

        $ok = self::verify_with_site($token);
        if ( ! $ok ) {
            if (self::$last_error_msg === '') {
                self::$last_error_msg = self::message_from_error_codes(self::$last_error_codes, '');
            }
            self::record_last_verify(false, self::$last_error_codes);
            return false;
        }

        if ( self::is_replay( $token ) ) {
            self::$last_error_codes[] = 'replay_detected';
            self::$last_error_msg     = __('Security check failed: token reuse detected. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::log_dev('replay_detected');
            self::flag_replay_frontend();
            self::record_last_verify(false, self::$last_error_codes);
            return false;
        }

        self::mark_used( $token );
        self::record_last_verify(true, []);
        return true;
    }

    /**
     * Get the error message for Turnstile validation (shared utility).
     *
     * @param string $context Optional context for filter hook (e.g., 'wpforms')
     * @return string
     */
    public static function get_error_message($context = ''): string {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);

        // Replay gets a specific message
        if ( in_array('replay_detected', self::$last_error_codes, true) ) {
            $replay = __('Your verification expired. Please complete the Turnstile challenge again.', 'kitgenix-captcha-for-cloudflare-turnstile');
            $replay = apply_filters('kitgenix_turnstile_replay_message', $replay, $context);
            $message = $replay;
        } else {
            // Map codes → message; fall back to generic
            $mapped  = self::message_from_error_codes(self::$last_error_codes, $context);
            $generic = __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile');
            $message = $mapped ?: $generic;
        }

        // Admin custom message overrides
        if (!empty($settings['error_message'])) {
            $message = $settings['error_message'];
        }

        if ($context) {
            $filter  = 'kitgenix_captcha_for_cloudflare_turnstile_' . $context . '_turnstile_error_message';
            $message = apply_filters($filter, $message);
        }

        return esc_html($message);
    }

    /* ======= Introspection ======= */

    public static function get_last_error_codes(): array {
        return self::$last_error_codes;
    }

    public static function get_last_error_message(): string {
        return self::$last_error_msg ?: self::get_error_message();
    }

    public static function get_last_response(): array {
        return self::$last_response;
    }

    /* =========================
     * Internals
     * ========================= */

    /**
     * Pull token from POST or a custom header (helps fetch/Blocks flows).
     */
    private static function get_token_from_request(): string {
        if (isset($_POST['cf-turnstile-response'])) {
            return sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) );
        }
        if (isset($_SERVER['HTTP_X_TURNSTILE_TOKEN'])) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) );
        }
        $token = apply_filters('kitgenix_turnstile_token_from_request', '');
        return sanitize_text_field( (string) $token );
    }

    /**
     * Perform the siteverify call with memoization and robust HTTP args.
     */
    private static function verify_with_site(string $token): bool {
        if (array_key_exists($token, self::$verified_tokens)) {
            return self::$verified_tokens[$token];
        }

        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $secret   = $settings['secret_key'] ?? '';

        if ($secret === '') {
            self::$last_error_codes = ['missing-input-secret'];
            self::$last_error_msg   = __('Secret key missing.', 'kitgenix-captcha-for-cloudflare-turnstile');
            self::record_last_verify(false, self::$last_error_codes);
            return self::$verified_tokens[$token] = false;
        }

        $remoteip     = Client_IP::get();
        $remoteip     = apply_filters('kitgenix_turnstile_remote_ip', $remoteip);
        $send_remoteip = apply_filters('kitgenix_turnstile_send_remoteip', true);

        $body = [
            'secret'   => $secret,
            'response' => $token,
        ];
        if ($send_remoteip && $remoteip) {
            $body['remoteip'] = $remoteip;
        }

        $url  = apply_filters('kitgenix_turnstile_siteverify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
        $args = [
            'timeout'   => (int) apply_filters('kitgenix_turnstile_siteverify_timeout', 10),
            'headers'   => [
                'User-Agent' => 'kitgenix-captcha-for-cloudflare-turnstile/1.0',
                'Accept'     => 'application/json',
            ],
            'body'      => $body,
            'sslverify' => apply_filters('kitgenix_turnstile_siteverify_sslverify', true),
        ];
        $args = apply_filters('kitgenix_turnstile_siteverify_http_args', $args, $token);

        $resp = wp_remote_post($url, $args);

        if ( ! $resp || is_wp_error($resp) ) {
            $msg = $resp && is_wp_error($resp) ? $resp->get_error_message() : 'no-response';
            self::$last_response    = ['http_error' => $msg];
            self::$last_error_codes = ['http_error'];
            /* translators: %s: HTTP error detail returned when contacting Cloudflare's Turnstile verify endpoint */
            self::$last_error_msg   = sprintf(__('Verification request failed: %s', 'kitgenix-captcha-for-cloudflare-turnstile'), $msg);
            self::record_last_verify(false, self::$last_error_codes);
            return self::$verified_tokens[$token] = false;
        }

        $json = json_decode( (string) wp_remote_retrieve_body($resp), true );
        self::$last_response = is_array($json) ? $json : [];

        $codes = isset($json['error-codes']) && is_array($json['error-codes']) ? $json['error-codes'] : [];
        self::$last_error_codes = $codes;

        $ok = !empty($json['success']);
        if ( ! $ok ) {
            // Map codes → friendly message immediately
            self::$last_error_msg = self::message_from_error_codes(self::$last_error_codes, '');
            self::record_last_verify(false, self::$last_error_codes);
        } else {
            // success recorded after replay check in callers
        }

        return self::$verified_tokens[$token] = (bool) $ok;
    }

    /* =========================
     * Error code → message mapping
     * ========================= */

    /**
     * Central mapping for Cloudflare siteverify error codes.
     * Allows integrators to alter $codes before mapping via `kitgenix_turnstile_error_codes`.
     *
     * @param array  $codes
     * @param string $context
     * @return string Friendly, localized message (or empty if none)
     */
    private static function message_from_error_codes(array $codes, string $context = ''): string {
        if (empty($codes)) {
            return '';
        }

        // Normalize strings and allow custom filters to adjust
        $codes = array_values(array_unique(array_map('strval', $codes)));
        $codes = (array) apply_filters('kitgenix_turnstile_error_codes', $codes, $context, self::$last_response);

        // Known mappings (Cloudflare Turnstile)
        $map = [
            // Config / key issues
            'missing-input-secret'    => __('Turnstile secret key is missing. Please configure the plugin.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'invalid-input-secret'    => __('Turnstile secret key is invalid. Please check your key.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'sitekey-secret-mismatch' => __('Site key and secret do not match. Verify your keys.', 'kitgenix-captcha-for-cloudflare-turnstile'),

            // Token / response issues
            'missing-input-response'  => __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'invalid-input-response'  => __('Invalid Turnstile token. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'timeout-or-duplicate'    => __('Your verification expired. Please complete the Turnstile challenge again.', 'kitgenix-captcha-for-cloudflare-turnstile'),

            // Request issues
            'bad-request'             => __('Invalid verification request. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'internal-error'          => __('Verification service is temporarily unavailable. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),

            // Our own internal markers
            'http_error'              => __('Verification request failed. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'nonce_invalid'           => __('Security check failed. Please refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'token_missing'           => __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'replay_detected'         => __('Your verification expired. Please complete the Turnstile challenge again.', 'kitgenix-captcha-for-cloudflare-turnstile'),
        ];

        // First known code wins
        foreach ($codes as $c) {
            if (isset($map[$c])) {
                $msg = $map[$c];
                // Let integrators post-process the friendly message if needed
                $msg = apply_filters('kitgenix_turnstile_error_message', $msg, $codes, $context, self::$last_response);
                return (string) $msg;
            }
        }

        // Unknown code → generic
        $generic = __('Turnstile verification failed. Please try again.', 'kitgenix-captcha-for-cloudflare-turnstile');
        return (string) apply_filters('kitgenix_turnstile_error_message', $generic, $codes, $context, self::$last_response);
    }
}
