<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

use KitgenixCaptchaForCloudflareTurnstile\Core\Settings_Overrides;

defined('ABSPATH') || exit;

class Settings_Transfer {

    private const EXPORT_ACTION = 'kitgenix_turnstile_export_settings';
    private const IMPORT_ACTION = 'kitgenix_turnstile_import_settings';
    private const EXPORT_NONCE  = 'kitgenix_turnstile_export_settings';
    private const IMPORT_NONCE  = 'kitgenix_turnstile_import_settings';
    private const PAGE_SLUG     = 'kitgenix-captcha-for-cloudflare-turnstile';
    private const OPTION_NAME   = 'kitgenix_captcha_for_cloudflare_turnstile_settings';

    public static function init(): void {
        \add_action( 'admin_post_' . self::EXPORT_ACTION, [ __CLASS__, 'handle_export' ] );
        \add_action( 'admin_post_' . self::IMPORT_ACTION, [ __CLASS__, 'handle_import' ] );
        \add_action( 'admin_notices', [ __CLASS__, 'render_admin_notices' ] );
    }

    public static function handle_export(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \esc_html__( 'You do not have permission to export settings.', 'kitgenix-captcha-for-cloudflare-turnstile' ) );
        }

        \check_admin_referer( self::EXPORT_NONCE );

        $settings     = Settings_Overrides::get_stored_settings();
        $include_keys = false;
        if ( isset( $_POST['include_keys'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- validated above.
            $include_keys = \rest_sanitize_boolean( \wp_unslash( $_POST['include_keys'] ) );
        }

        if ( ! $include_keys ) {
            unset( $settings['site_key'], $settings['secret_key'] );
        }

        $payload = [
            'plugin'      => 'kitgenix-captcha-for-cloudflare-turnstile',
            'version'     => \defined( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) ? (string) \constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) : '',
            'exported_at' => \function_exists( 'current_time' ) ? \current_time( 'mysql' ) : \gmdate( 'Y-m-d H:i:s' ),
            'settings'    => $settings,
        ];

        $json = \wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        if ( ! \is_string( $json ) || '' === $json ) {
            \wp_safe_redirect( \add_query_arg( [ 'tab' => 'portability', 'kitgenix_transfer' => 'error', 'reason' => 'export' ], self::get_settings_page_url() ) );
            exit;
        }

        \nocache_headers();
        \header( 'Content-Description: File Transfer' );
        \header( 'Content-Type: application/json; charset=' . \get_option( 'blog_charset' ) );
        \header( 'Content-Disposition: attachment; filename=kitgenix-turnstile-settings-' . \gmdate( 'Ymd-His' ) . '.json' );
        \header( 'Content-Length: ' . \strlen( $json ) );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is being sent as a file download, not rendered into HTML.
        echo $json;
        exit;
    }

    public static function handle_import(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \esc_html__( 'You do not have permission to import settings.', 'kitgenix-captcha-for-cloudflare-turnstile' ) );
        }

        \check_admin_referer( self::IMPORT_NONCE );

        if ( ! isset( $_FILES['kitgenix_turnstile_import_file'] ) || ! \is_array( $_FILES['kitgenix_turnstile_import_file'] ) ) {
            self::redirect_import_error( 'nofile' );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload arrays are validated field-by-field below.
        $file = $_FILES['kitgenix_turnstile_import_file'];
        if ( ! empty( $file['error'] ) ) {
            self::redirect_import_error( 'upload' );
        }

        if ( ! empty( $file['size'] ) && (int) $file['size'] > 2 * 1024 * 1024 ) {
            self::redirect_import_error( 'size' );
        }

        $tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        if ( '' === $tmp_name || ! \is_uploaded_file( $tmp_name ) ) {
            self::redirect_import_error( 'upload' );
        }

        $contents = \file_get_contents( $tmp_name );
        if ( ! \is_string( $contents ) || '' === $contents ) {
            self::redirect_import_error( 'empty' );
        }

        $data = \json_decode( $contents, true );
        if ( ! \is_array( $data ) ) {
            self::redirect_import_error( 'json' );
        }

        if ( isset( $data['plugin'] ) && 'kitgenix-captcha-for-cloudflare-turnstile' !== $data['plugin'] ) {
            self::redirect_import_error( 'plugin' );
        }

        $imported_settings = isset( $data['settings'] ) && \is_array( $data['settings'] ) ? $data['settings'] : $data;
        if ( ! \is_array( $imported_settings ) ) {
            self::redirect_import_error( 'json' );
        }

        $mode = 'replace';
        if ( isset( $_POST['kitgenix_turnstile_import_mode'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- validated above.
            $mode = \sanitize_key( \wp_unslash( $_POST['kitgenix_turnstile_import_mode'] ) );
        }

        $stored_settings = Settings_Overrides::get_stored_settings();
        $settings_to_save = 'merge' === $mode
            ? \array_merge( $stored_settings, $imported_settings )
            : $imported_settings;

        // If keys were omitted from the import file, preserve the current stored values.
        if ( ! \array_key_exists( 'site_key', $imported_settings ) ) {
            $settings_to_save['site_key'] = $stored_settings['site_key'] ?? '';
        }
        if ( ! \array_key_exists( 'secret_key', $imported_settings ) ) {
            $settings_to_save['secret_key'] = $stored_settings['secret_key'] ?? '';
        }

        $sanitized = Admin_Options::sanitize_imported_settings( $settings_to_save );
        $updated   = \update_option( self::OPTION_NAME, $sanitized, false );

        \wp_safe_redirect(
            \add_query_arg(
                [
                    'tab'                => 'portability',
                    'kitgenix_transfer'  => 'success',
                    'import_mode'        => $mode,
                    'updated'            => $updated ? '1' : '0',
                ],
                self::get_settings_page_url()
            )
        );
        exit;
    }

    public static function render_admin_notices(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! self::is_settings_screen_now() ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $state = isset( $_GET['kitgenix_transfer'] ) ? \sanitize_key( \wp_unslash( $_GET['kitgenix_transfer'] ) ) : '';
        if ( '' === $state ) {
            return;
        }

        if ( 'success' === $state ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $mode = isset( $_GET['import_mode'] ) ? \sanitize_key( \wp_unslash( $_GET['import_mode'] ) ) : 'replace';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $updated = isset( $_GET['updated'] ) && '1' === \sanitize_text_field( \wp_unslash( $_GET['updated'] ) );

            $message = 'merge' === $mode
                ? \__( 'Settings imported successfully using merge mode.', 'kitgenix-captcha-for-cloudflare-turnstile' )
                : \__( 'Settings imported successfully using replace mode.', 'kitgenix-captcha-for-cloudflare-turnstile' );

            if ( ! $updated ) {
                $message = \__( 'Import completed, but no stored settings changed.', 'kitgenix-captcha-for-cloudflare-turnstile' );
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html( $message ) . '</p></div>';
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $reason = isset( $_GET['reason'] ) ? \sanitize_key( \wp_unslash( $_GET['reason'] ) ) : 'unknown';
        $message = \__( 'Import failed.', 'kitgenix-captcha-for-cloudflare-turnstile' );

        switch ( $reason ) {
            case 'nofile':
                $message = \__( 'Import failed: No JSON file was uploaded.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                break;
            case 'upload':
                $message = \__( 'Import failed: The upload could not be processed.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                break;
            case 'size':
                $message = \__( 'Import failed: The JSON file is too large.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                break;
            case 'empty':
                $message = \__( 'Import failed: The uploaded file was empty.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                break;
            case 'json':
                $message = \__( 'Import failed: The file does not contain valid JSON settings.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                break;
            case 'plugin':
                $message = \__( 'Import failed: The file is not a Kitgenix Turnstile settings export.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                break;
            case 'export':
                $message = \__( 'Export failed: The settings payload could not be generated.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                break;
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . \esc_html( $message ) . '</p></div>';
    }

    public static function get_export_action(): string {
        return self::EXPORT_ACTION;
    }

    public static function get_import_action(): string {
        return self::IMPORT_ACTION;
    }

    public static function get_export_nonce(): string {
        return self::EXPORT_NONCE;
    }

    public static function get_import_nonce(): string {
        return self::IMPORT_NONCE;
    }

    private static function redirect_import_error( string $reason ): void {
        \wp_safe_redirect( \add_query_arg( [ 'tab' => 'portability', 'kitgenix_transfer' => 'error', 'reason' => $reason ], self::get_settings_page_url() ) );
        exit;
    }

    private static function get_settings_page_url(): string {
        return \admin_url( 'admin.php?page=' . self::PAGE_SLUG );
    }

    private static function is_settings_screen_now(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : '';
        return self::PAGE_SLUG === $page;
    }
}