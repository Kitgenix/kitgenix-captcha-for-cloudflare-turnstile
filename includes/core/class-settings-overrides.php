<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Settings_Overrides {

    private const OPTION_NAME = 'kitgenix_captcha_for_cloudflare_turnstile_settings';

    private const SITE_KEY_CONSTANTS = [
        'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY',
    ];

    private const SECRET_KEY_CONSTANTS = [
        'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ];

    private const SITE_KEY_ENV_VARS = [
        'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SITE_KEY',
    ];

    private const SECRET_KEY_ENV_VARS = [
        'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ];

    public static function init(): void {
        \add_filter( 'pre_option_' . self::OPTION_NAME, [ __CLASS__, 'filter_pre_option' ], 10, 3 );
        \add_filter( 'pre_update_option_' . self::OPTION_NAME, [ __CLASS__, 'filter_pre_update' ], 10, 3 );
    }

    public static function filter_pre_option( $pre_value, $option, $default_value ) {
        $overrides = self::get_override_values();
        $settings = \is_array( $pre_value ) ? $pre_value : self::get_stored_settings();
        if ( ! empty( $overrides ) ) {
            $settings = self::merge_settings( $settings, $overrides );
        }

        return Setup_Verification::mask_runtime_settings( $settings );
    }

    public static function filter_pre_update( $value, $old_value, $option ) {
        $details = self::get_override_details();
        if ( empty( $details['site_key']['is_overridden'] ) && empty( $details['secret_key']['is_overridden'] ) ) {
            return $value;
        }

        $value        = \is_array( $value ) ? $value : [];
        $stored_value = self::get_stored_settings();

        if ( ! empty( $details['site_key']['is_overridden'] ) ) {
            $value['site_key'] = (string) ( $stored_value['site_key'] ?? '' );
        }

        if ( ! empty( $details['secret_key']['is_overridden'] ) ) {
            $value['secret_key'] = (string) ( $stored_value['secret_key'] ?? '' );
        }

        return $value;
    }

    public static function get_settings(): array {
        if ( ! \function_exists( 'get_option' ) ) {
            return self::merge_settings( [], self::get_override_values() );
        }

        $settings = \get_option( self::OPTION_NAME, [] );
        return \is_array( $settings ) ? $settings : [];
    }

    public static function get_stored_settings(): array {
        if ( ! \function_exists( 'get_option' ) ) {
            return [];
        }

        \remove_filter( 'pre_option_' . self::OPTION_NAME, [ __CLASS__, 'filter_pre_option' ], 10 );

        try {
            $settings = \get_option( self::OPTION_NAME, [] );
        } finally {
            \add_filter( 'pre_option_' . self::OPTION_NAME, [ __CLASS__, 'filter_pre_option' ], 10, 3 );
        }

        return \is_array( $settings ) ? $settings : [];
    }

    public static function get_override_details(): array {
        return [
            'site_key'   => self::resolve_override( self::SITE_KEY_CONSTANTS, self::SITE_KEY_ENV_VARS ),
            'secret_key' => self::resolve_override( self::SECRET_KEY_CONSTANTS, self::SECRET_KEY_ENV_VARS ),
        ];
    }

    public static function get_supported_key_sources(): array {
        return [
            'site_key_constants'   => self::SITE_KEY_CONSTANTS,
            'secret_key_constants' => self::SECRET_KEY_CONSTANTS,
            'site_key_env_vars'    => self::SITE_KEY_ENV_VARS,
            'secret_key_env_vars'  => self::SECRET_KEY_ENV_VARS,
        ];
    }

    private static function get_override_values(): array {
        $details = self::get_override_details();
        $values  = [];

        if ( ! empty( $details['site_key']['is_overridden'] ) ) {
            $values['site_key'] = (string) $details['site_key']['value'];
        }

        if ( ! empty( $details['secret_key']['is_overridden'] ) ) {
            $values['secret_key'] = (string) $details['secret_key']['value'];
        }

        return $values;
    }

    private static function merge_settings( array $settings, array $overrides ): array {
        if ( isset( $overrides['site_key'] ) ) {
            $settings['site_key'] = $overrides['site_key'];
        }

        if ( isset( $overrides['secret_key'] ) ) {
            $settings['secret_key'] = $overrides['secret_key'];
        }

        return $settings;
    }

    private static function resolve_override( array $constant_names, array $env_names ): array {
        foreach ( $constant_names as $constant_name ) {
            if ( ! \defined( $constant_name ) ) {
                continue;
            }

            $value = \trim( (string) \constant( $constant_name ) );
            if ( '' !== $value ) {
                return [
                    'is_overridden' => true,
                    'value'         => $value,
                    'source_type'   => 'constant',
                    'source_name'   => $constant_name,
                ];
            }
        }

        foreach ( $env_names as $env_name ) {
            $value = self::get_env_value( $env_name );
            if ( '' !== $value ) {
                return [
                    'is_overridden' => true,
                    'value'         => $value,
                    'source_type'   => 'environment',
                    'source_name'   => $env_name,
                ];
            }
        }

        return [
            'is_overridden' => false,
            'value'         => '',
            'source_type'   => '',
            'source_name'   => '',
        ];
    }

    private static function get_env_value( string $name ): string {
        $value = false;
        if ( \function_exists( 'getenv' ) ) {
            $value = \getenv( $name );
        }

        if ( false === $value && isset( $_ENV[ $name ] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is sanitized immediately via sanitize_env_value().
            $value = self::sanitize_env_value( $_ENV[ $name ] );
        }

        if ( false === $value && isset( $_SERVER[ $name ] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Value is unslashed and sanitized immediately via sanitize_env_value().
            $value = self::sanitize_env_value( $_SERVER[ $name ] );
        }

        return \is_scalar( $value ) ? \trim( (string) $value ) : '';
    }

    private static function sanitize_env_value( $value ): string {
        if ( ! \is_scalar( $value ) ) {
            return '';
        }

        return \sanitize_text_field( \wp_unslash( (string) $value ) );
    }
}