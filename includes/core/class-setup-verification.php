<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Setup_Verification {

    private const OPTION_NAME = 'kitgenix_turnstile_setup_verification';
    private const AJAX_ACTION = 'kitgenix_turnstile_verify_setup';
    private const AJAX_NONCE  = 'kitgenix_turnstile_verify_setup';

    private const AUTH_SETTING_KEYS = [
        'wp_login_form',
        'wp_register_form',
        'wp_lostpassword_form',
        'wc_login_form',
        'wc_register_form',
        'wc_lostpassword_form',
        'edd_login_form',
        'edd_register_form',
        'edd_profile_form',
        'enable_ultimatemember',
        'enable_memberpress',
        'enable_paidmembershipspro',
        'kgps_login_form',
        'kgps_register_form',
        'kgps_lostpassword_form',
    ];

    public static function init(): void {
        \add_action( 'wp_ajax_' . self::AJAX_ACTION, [ __CLASS__, 'ajax_verify_setup' ] );
        \add_action( 'admin_notices', [ __CLASS__, 'render_admin_notice' ] );
    }

    public static function get_ajax_action(): string {
        return self::AJAX_ACTION;
    }

    public static function get_ajax_nonce(): string {
        return self::AJAX_NONCE;
    }

    public static function get_status(): array {
        [ $site_key, $secret ] = self::get_effective_keys();
        $keys_hash = self::keys_hash( $site_key, $secret );

        $base = [
            'state'            => 'not_configured',
            'verified'         => false,
            'checked_at'       => 0,
            'message'          => \__( 'Add both the Site Key and Secret Key, then complete the setup verification test.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'codes'            => [],
            'keys_hash'        => $keys_hash,
            'outbound_http_ok' => false,
            'secret_valid'     => false,
            'gate_active'      => false,
        ];

        if ( '' === $keys_hash ) {
            return $base;
        }

        $stored = \get_option( self::OPTION_NAME, [] );
        $stored = \is_array( $stored ) ? $stored : [];

        if ( empty( $stored['keys_hash'] ) || ! \hash_equals( (string) $stored['keys_hash'], $keys_hash ) ) {
            return [
                'state'            => 'pending',
                'verified'         => false,
                'checked_at'       => 0,
                'message'          => \__( 'Login-sensitive protections stay gated until this key pair passes a full server-side verification test.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'codes'            => [],
                'keys_hash'        => $keys_hash,
                'outbound_http_ok' => false,
                'secret_valid'     => false,
                'gate_active'      => true,
            ];
        }

        $verified = ! empty( $stored['verified'] );

        return [
            'state'            => $verified ? 'verified' : 'failed',
            'verified'         => $verified,
            'checked_at'       => isset( $stored['checked_at'] ) ? (int) $stored['checked_at'] : 0,
            'message'          => isset( $stored['message'] ) ? (string) $stored['message'] : '',
            'codes'            => isset( $stored['codes'] ) && \is_array( $stored['codes'] ) ? \array_values( \array_map( 'strval', $stored['codes'] ) ) : [],
            'keys_hash'        => $keys_hash,
            'outbound_http_ok' => ! empty( $stored['outbound_http_ok'] ),
            'secret_valid'     => ! empty( $stored['secret_valid'] ),
            'gate_active'      => ! $verified,
        ];
    }

    public static function mask_runtime_settings( array $settings ): array {
        if ( \is_admin() ) {
            return $settings;
        }

        $status = self::get_status();
        if ( empty( $status['gate_active'] ) ) {
            return $settings;
        }

        foreach ( self::AUTH_SETTING_KEYS as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $settings[ $key ] = 0;
            }
        }

        return $settings;
    }

    public static function is_any_auth_flow_enabled( ?array $settings = null ): bool {
        $settings = \is_array( $settings ) ? $settings : Settings_Overrides::get_stored_settings();
        foreach ( self::AUTH_SETTING_KEYS as $key ) {
            if ( ! empty( $settings[ $key ] ) ) {
                return true;
            }
        }

        return false;
    }

    public static function ajax_verify_setup(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Forbidden', 'kitgenix-captcha-for-cloudflare-turnstile' ) ], 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '';
        if ( '' === $nonce || ! \wp_verify_nonce( $nonce, self::AJAX_NONCE ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Invalid nonce', 'kitgenix-captcha-for-cloudflare-turnstile' ) ], 403 );
        }

        $token = isset( $_POST['token'] ) ? \sanitize_text_field( \wp_unslash( $_POST['token'] ) ) : '';
        $result = Turnstile_Validator::perform_setup_check( $token );
        $status = self::store_result( $result );

        \wp_send_json_success(
            [
                'verified' => ! empty( $status['verified'] ),
                'status'   => $status,
            ]
        );
    }

    public static function render_admin_notice(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! self::is_any_auth_flow_enabled() ) {
            return;
        }

        $status = self::get_status();
        if ( empty( $status['gate_active'] ) ) {
            return;
        }

        $settings_url = \admin_url( 'admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=site-keys#kitgenix-tab-site-keys' );
        echo '<div class="notice notice-warning is-dismissible"><p><strong>'
            . \esc_html__( 'Turnstile setup verification is still required.', 'kitgenix-captcha-for-cloudflare-turnstile' )
            . '</strong> '
            . \esc_html__( 'Login-sensitive protections remain inactive until the current Site Key and Secret Key pass the end-to-end server-side verification test.', 'kitgenix-captcha-for-cloudflare-turnstile' )
            . ' <a href="' . \esc_url( $settings_url ) . '">' . \esc_html__( 'Open the settings page to verify setup.', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a></p></div>';
    }

    private static function store_result( array $result ): array {
        [ $site_key, $secret ] = self::get_effective_keys();
        $payload = [
            'verified'         => ! empty( $result['success'] ) ? 1 : 0,
            'checked_at'       => \time(),
            'message'          => isset( $result['message'] ) ? (string) $result['message'] : '',
            'codes'            => isset( $result['codes'] ) && \is_array( $result['codes'] ) ? \array_values( \array_map( 'strval', $result['codes'] ) ) : [],
            'keys_hash'        => self::keys_hash( $site_key, $secret ),
            'outbound_http_ok' => ! empty( $result['outbound_http_ok'] ) ? 1 : 0,
            'secret_valid'     => ! empty( $result['secret_valid'] ) ? 1 : 0,
        ];

        \update_option( self::OPTION_NAME, $payload, false );

        return self::get_status();
    }

    private static function get_effective_keys(): array {
        $settings = Settings_Overrides::get_stored_settings();
        $details  = Settings_Overrides::get_override_details();

        $site_key = ! empty( $details['site_key']['is_overridden'] )
            ? (string) $details['site_key']['value']
            : (string) ( $settings['site_key'] ?? '' );

        $secret = ! empty( $details['secret_key']['is_overridden'] )
            ? (string) $details['secret_key']['value']
            : (string) ( $settings['secret_key'] ?? '' );

        return [ $site_key, $secret ];
    }

    private static function keys_hash( string $site_key, string $secret ): string {
        $site_key = \trim( $site_key );
        $secret   = \trim( $secret );
        if ( '' === $site_key || '' === $secret ) {
            return '';
        }

        return \hash( 'sha256', $site_key . '|' . $secret . '|' . \wp_salt( 'auth' ) );
    }
}