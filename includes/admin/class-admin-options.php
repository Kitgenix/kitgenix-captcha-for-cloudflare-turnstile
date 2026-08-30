<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

use KitgenixCaptchaForCloudflareTurnstile\Core\Settings_Overrides;
use KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

class Admin_Options {

    /**
     * Option name used in the database.
     */
    const OPTION_NAME = 'kitgenix_captcha_for_cloudflare_turnstile_settings';

    /**
     * Initialize settings registration.
     */
    public static function init(): void {
        \add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register plugin settings using WordPress Settings API.
     */
    public static function register_settings(): void {
        \register_setting(
            'kitgenix_captcha_for_cloudflare_turnstile_settings_group',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'description'       => \__('Kitgenix CAPTCHA for Cloudflare Turnstile Settings', 'kitgenix-captcha-for-cloudflare-turnstile'),
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'show_in_rest'      => false,
                'default'           => [],
            ]
        );
    }

    /**
     * Sanitize the settings before saving.
     *
     * @param array $settings Raw settings array from the form (may be slashed).
     * @return array Clean settings to be stored.
     */
    public static function sanitize($settings): array {
        // Verify nonce (do not wipe options on failure)
        $nonce = '';
        if (isset($_POST['kitgenix_captcha_for_cloudflare_turnstile_settings_nonce'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- we verify below
            $nonce = \sanitize_text_field(\wp_unslash($_POST['kitgenix_captcha_for_cloudflare_turnstile_settings_nonce']));
        }
        if (empty($nonce)) {
            return \function_exists('get_option') ? (array) \get_option(self::OPTION_NAME, []) : [];
        }
        if ( ! \wp_verify_nonce($nonce, 'kitgenix_captcha_for_cloudflare_turnstile_settings_save') ) {
            return \function_exists('get_option') ? (array) \get_option(self::OPTION_NAME, []) : [];
        }

        $settings = \is_array($settings) ? $settings : [];
        $stored_settings  = Settings_Overrides::get_stored_settings();
        $override_details = Settings_Overrides::get_override_details();

        return self::sanitize_settings_payload( $settings, $stored_settings, $override_details );
    }

    /**
     * Sanitize imported settings payloads before saving via import/export tools.
     *
     * @param array $settings Imported raw settings.
     * @return array
     */
    public static function sanitize_imported_settings( array $settings ): array {
        return self::sanitize_settings_payload(
            $settings,
            Settings_Overrides::get_stored_settings(),
            Settings_Overrides::get_override_details()
        );
    }

    /**
     * Normalize a settings payload for storage.
     *
     * @param array $settings Raw settings payload.
     * @param array $stored_settings Existing stored settings.
     * @param array $override_details Active override metadata.
     * @return array
     */
    private static function sanitize_settings_payload( array $settings, array $stored_settings, array $override_details ): array {
        $clean = [];

        // --- Site keys ---
        if ( ! empty( $override_details['site_key']['is_overridden'] ) ) {
            $clean['site_key'] = \sanitize_text_field( $stored_settings['site_key'] ?? '' );
        } else {
            $clean['site_key'] = \sanitize_text_field( $settings['site_key'] ?? '' );
        }

        // Secret handling: For safety we do not rely on the form field being
        // populated when a secret already exists. If the admin leaves the
        // secret input empty but `secret_key_present` was set, preserve the
        // existing stored secret unless `secret_key_clear` is explicitly set.
        $incoming_secret = isset($settings['secret_key']) ? \sanitize_text_field($settings['secret_key']) : '';
        $secret_present_flag = !empty($settings['secret_key_present']);
        $clear_secret = !empty($settings['secret_key_clear']);

        if ( ! empty( $override_details['secret_key']['is_overridden'] ) ) {
            $clean['secret_key'] = \sanitize_text_field( $stored_settings['secret_key'] ?? '' );
        } elseif ($clear_secret) {
            $clean['secret_key'] = '';
        } elseif ($incoming_secret === '' && $secret_present_flag) {
            // Preserve previously stored secret when the admin didn't submit a new one.
            $current = $stored_settings;
            $clean['secret_key'] = \sanitize_text_field($current['secret_key'] ?? '');
        } else {
            $clean['secret_key'] = $incoming_secret;
        }

        // --- Master toggles (integrations) ---
        $clean['enable_wordpress']       = !empty($settings['enable_wordpress']) ? 1 : 0;
        $clean['enable_woocommerce']     = !empty($settings['enable_woocommerce']) ? 1 : 0;
        $clean['enable_edd']             = !empty($settings['enable_edd']) ? 1 : 0;
        $clean['enable_elementor']       = !empty($settings['enable_elementor']) ? 1 : 0;
        $clean['enable_wpforms']         = !empty($settings['enable_wpforms']) ? 1 : 0;
        $clean['enable_fluentforms']     = !empty($settings['enable_fluentforms']) ? 1 : 0;
        $clean['enable_gravityforms']    = !empty($settings['enable_gravityforms']) ? 1 : 0;
        $clean['enable_cf7']             = !empty($settings['enable_cf7']) ? 1 : 0;
        $clean['enable_formidableforms'] = !empty($settings['enable_formidableforms']) ? 1 : 0;
        $clean['enable_forminator']      = !empty($settings['enable_forminator']) ? 1 : 0;
        $clean['enable_jetpackforms']    = !empty($settings['enable_jetpackforms']) ? 1 : 0;
        $clean['enable_kadenceforms']    = !empty($settings['enable_kadenceforms']) ? 1 : 0;
        $clean['enable_jetformbuilder']  = !empty($settings['enable_jetformbuilder']) ? 1 : 0;
        $clean['enable_buddypress']      = !empty($settings['enable_buddypress']) ? 1 : 0;
        $clean['enable_bbpress']         = !empty($settings['enable_bbpress']) ? 1 : 0;
        $clean['enable_mailpoet']        = !empty($settings['enable_mailpoet']) ? 1 : 0;
        $clean['enable_memberpress']     = !empty($settings['enable_memberpress']) ? 1 : 0;
        $clean['enable_paidmembershipspro'] = !empty($settings['enable_paidmembershipspro']) ? 1 : 0;
        $clean['enable_ultimatemember']  = !empty($settings['enable_ultimatemember']) ? 1 : 0;
        $clean['enable_wpdiscuz']        = !empty($settings['enable_wpdiscuz']) ? 1 : 0;
        $clean['enable_ninjaforms']      = !empty($settings['enable_ninjaforms']) ? 1 : 0;

        // --- Per-form toggles (WordPress Core) ---
        $clean['wp_login_form']        = !empty($settings['wp_login_form']) ? 1 : 0;
        $clean['wp_register_form']     = !empty($settings['wp_register_form']) ? 1 : 0;
        $clean['wp_lostpassword_form'] = !empty($settings['wp_lostpassword_form']) ? 1 : 0;
        $clean['wp_comments_form']     = !empty($settings['wp_comments_form']) ? 1 : 0;

        // --- Per-form toggles (WooCommerce) ---
        $clean['wc_checkout_form']     = !empty($settings['wc_checkout_form']) ? 1 : 0;
        $clean['wc_reviews_form']      = !empty($settings['wc_reviews_form']) ? 1 : 0;
        $clean['wc_login_form']        = !empty($settings['wc_login_form']) ? 1 : 0;
        $clean['wc_register_form']     = !empty($settings['wc_register_form']) ? 1 : 0;
        $clean['wc_lostpassword_form'] = !empty($settings['wc_lostpassword_form']) ? 1 : 0;

        // --- Per-form toggles (Easy Digital Downloads) ---
        $clean['edd_checkout_form'] = !empty($settings['edd_checkout_form']) ? 1 : 0;
        $clean['edd_login_form']    = !empty($settings['edd_login_form']) ? 1 : 0;
        $clean['edd_register_form'] = !empty($settings['edd_register_form']) ? 1 : 0;
        $clean['edd_profile_form']  = !empty($settings['edd_profile_form']) ? 1 : 0;

        // --- Kitgenix Plugin Score ---
        $clean['enable_kitgenix_plugin_score'] = !empty($settings['enable_kitgenix_plugin_score']) ? 1 : 0;
        $clean['kgps_login_form']        = !empty($settings['kgps_login_form']) ? 1 : 0;
        $clean['kgps_register_form']     = !empty($settings['kgps_register_form']) ? 1 : 0;
        $clean['kgps_lostpassword_form'] = !empty($settings['kgps_lostpassword_form']) ? 1 : 0;


        // --- Display settings ---
        $theme = $settings['theme'] ?? 'auto';
        $clean['theme'] = \in_array($theme, ['auto', 'light', 'dark'], true) ? $theme : 'auto';

    $size = strtolower( (string) ( $settings['widget_size'] ?? 'normal' ) );
    // Cloudflare supports normal, compact, and flexible. Map legacy values for backward compatibility.
    if ( \in_array( $size, [ 'small', 'compact' ], true ) ) {
        $clean['widget_size'] = 'compact';
    } elseif ( 'flexible' === $size ) {
        $clean['widget_size'] = 'flexible';
    } else {
        $clean['widget_size'] = 'normal';
    }

        $appearance = $settings['appearance'] ?? 'always';
        $clean['appearance'] = \in_array($appearance, ['always', 'interaction-only'], true) ? $appearance : 'always';

        $allowed_langs = Script_Handler::get_allowed_languages();
        $lang = \sanitize_text_field($settings['language'] ?? 'auto');
        $clean['language'] = \in_array($lang, $allowed_langs, true) ? $lang : 'auto';

        $clean['disable_submit'] = !empty($settings['disable_submit']) ? 1 : 0;
        $clean['defer_scripts']  = !empty($settings['defer_scripts']) ? 1 : 0;

        // Replay protection: default ON for fresh installs, but do not force-enable
        // it on every save (unchecked checkboxes may omit the field entirely).
        $current_settings = $stored_settings;
        $replay_default   = \array_key_exists('replay_protection', $current_settings)
            ? (!empty($current_settings['replay_protection']) ? 1 : 0)
            : 1;

        $clean['replay_protection'] = \array_key_exists('replay_protection', $settings)
            ? (!empty($settings['replay_protection']) ? 1 : 0)
            : $replay_default;

        // Dev mode (warn-only) – global, applies to every integration.
        $clean['dev_mode_warn_only'] = !empty($settings['dev_mode_warn_only']) ? 1 : 0;

        // Per-integration test mode: warn-only for specific integrations without putting the
        // whole site into warn-only mode. Stored as a list of canonical integration keys (see
        // Script_Handler::get_override_integration_keys()); anything not in that list is dropped.
        $clean['test_mode_integrations'] = self::sanitize_integration_key_list(
            $settings['test_mode_integrations'] ?? []
        );

        // --- Messages ---
        $clean['error_message'] = \sanitize_text_field($settings['error_message'] ?? '');
        $clean['extra_message'] = \sanitize_text_field($settings['extra_message'] ?? '');

        // --- Whitelist (logged-in + IPs + UAs) ---
        $clean['whitelist_loggedin']      = !empty($settings['whitelist_loggedin']) ? 1 : 0;
        $clean['whitelist_ips']           = self::sanitize_ip_patterns($settings['whitelist_ips'] ?? '');
        $clean['whitelist_user_agents']   = self::sanitize_lines($settings['whitelist_user_agents'] ?? '');

        // --- Legacy/compat flags (kept to support old imports/UI) ---
        // Not used by core logic, but we persist if provided so exports round-trip cleanly.
        $clean['respect_proxy_headers'] = !empty($settings['respect_proxy_headers']) ? 1 : 0;
        $clean['trusted_proxy_ips']     = self::sanitize_ip_patterns($settings['trusted_proxy_ips'] ?? '');

        // --- Proxy / Cloudflare trust (used by Client_IP) ---
        $clean['trust_proxy'] = !empty($settings['trust_proxy']) ? 1 : 0;

        // Prefer new key 'trusted_proxies'; if absent, fall back to legacy 'trusted_proxy_ips'
        $trusted_input = $settings['trusted_proxies'] ?? ($settings['trusted_proxy_ips'] ?? '');
        $clean['trusted_proxies'] = self::sanitize_trusted_proxies_block($trusted_input);

    // --- Per-integration mode flags ('auto' or 'shortcode') ---
    $allowed_modes = ['auto', 'shortcode'];

    $mode = \sanitize_text_field( $settings['mode_wpforms'] ?? 'auto' );
    $clean['mode_wpforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_fluentforms'] ?? 'auto' );
    $clean['mode_fluentforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_gravityforms'] ?? 'auto' );
    $clean['mode_gravityforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_forminator'] ?? 'auto' );
    $clean['mode_forminator'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_formidableforms'] ?? 'auto' );
    $clean['mode_formidableforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_cf7'] ?? 'auto' );
    $clean['mode_cf7'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_jetpackforms'] ?? 'auto' );
    $clean['mode_jetpackforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_kadenceforms'] ?? 'auto' );
    $clean['mode_kadenceforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_jetformbuilder'] ?? 'auto' );
    $clean['mode_jetformbuilder'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_ninjaforms'] ?? 'auto' );
    $clean['mode_ninjaforms'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    // Non-form integrations
    $mode = \sanitize_text_field( $settings['mode_woocommerce'] ?? 'auto' );
    $clean['mode_woocommerce'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_woocommerce_blocks'] ?? 'auto' );
    $clean['mode_woocommerce_blocks'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_elementor'] ?? 'auto' );
    $clean['mode_elementor'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_wp_core'] ?? 'auto' );
    $clean['mode_wp_core'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    $mode = \sanitize_text_field( $settings['mode_edd'] ?? 'auto' );
    $clean['mode_edd'] = \in_array( $mode, $allowed_modes, true ) ? $mode : 'auto';

    // --- Per-integration widget overrides (theme/size/language) ---
    // Empty string means "inherit the global Display setting" for that integration.
    $allowed_themes = [ 'auto', 'light', 'dark' ];
    $allowed_sizes  = [ 'normal', 'compact', 'flexible' ];
    foreach ( \array_keys( Script_Handler::get_override_integration_keys() ) as $integration_key ) {
        $theme_override = \sanitize_key( (string) ( $settings[ 'theme_override_' . $integration_key ] ?? '' ) );
        $clean[ 'theme_override_' . $integration_key ] = \in_array( $theme_override, $allowed_themes, true ) ? $theme_override : '';

        $size_override = \sanitize_key( (string) ( $settings[ 'size_override_' . $integration_key ] ?? '' ) );
        if ( \in_array( $size_override, [ 'small', 'compact' ], true ) ) {
            $size_override = 'compact';
        } elseif ( 'flexible' !== $size_override && ! \in_array( $size_override, $allowed_sizes, true ) ) {
            $size_override = '';
        }
        $clean[ 'size_override_' . $integration_key ] = \in_array( $size_override, $allowed_sizes, true ) ? $size_override : '';

        $language_override = \sanitize_text_field( (string) ( $settings[ 'language_override_' . $integration_key ] ?? '' ) );
        $clean[ 'language_override_' . $integration_key ] = \in_array( $language_override, $allowed_langs, true ) ? $language_override : '';
    }

    // --- Honeypot fallback field (zero-JS defense-in-depth) ---
    $clean['honeypot_enabled'] = !empty($settings['honeypot_enabled']) ? 1 : 0;

    return $clean;
    }

    /**
     * Sanitize a submitted list of integration keys (e.g. Test Mode per Integration)
     * down to only the canonical keys Turnstile_Validator actually recognizes.
     *
     * @param mixed $raw
     * @return string[]
     */
    private static function sanitize_integration_key_list( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }

        $allowed = Turnstile_Validator::get_all_integration_keys();
        $clean   = [];

        foreach ( $raw as $key ) {
            $key = \sanitize_key( (string) $key );
            if ( '' !== $key && in_array( $key, $allowed, true ) && ! in_array( $key, $clean, true ) ) {
                $clean[] = $key;
            }
        }

        return $clean;
    }

    /**
     * Sanitize a textarea of IP patterns (one per line). We allow:
     *  - exact IPv4/IPv6
     *  - CIDR (v4/v6), e.g. 203.0.113.0/24 or 2001:db8::/32
     *  - wildcards using * (v4/v6), e.g. 203.0.113.* or 2001:db8::*
     */
    private static function sanitize_ip_patterns(string $text): string {
        $text  = (string) $text;
        $lines = preg_split('/[\r\n,]+/', $text);
        $out   = [];

        foreach ($lines as $raw) {
            $line = trim((string) $raw);
            if ($line === '') {
                continue;
            }
            // Keep wildcards/CIDR chars; sanitize to safe subset
            $line = \wp_kses_post($line);
            $line = preg_replace('~[^\w\.\:\*\/\-]+~u', '', (string) $line);
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            // Basic sanity for CIDR suffix if present: "/0".."128" max (IPv6)
            if (strpos($line, '/') !== false) {
                [$addr, $bits] = array_pad(explode('/', $line, 2), 2, '');
                $bits = trim($bits);
                if ($bits === '' || !ctype_digit($bits)) {
                    continue;
                }
                $bits = (int) $bits;
                if ($bits < 0 || $bits > 128) {
                    continue;
                }
                // Allow IPv4/IPv6 in $addr; we don't over-validate here.
            }

            $out[] = $line;
        }

        return implode("\n", array_unique($out));
    }

    /**
     * Sanitize a textarea into clean lines (one per line).
     */
    private static function sanitize_lines(string $text): string {
        $text  = (string) $text;
        $lines = preg_split('/[\r\n]+/', $text);
        $out   = [];
        foreach ($lines as $raw) {
            $line = trim((string) $raw);
            if ($line === '') {
                continue;
            }
            $out[] = \sanitize_text_field($line);
        }
        return implode("\n", array_unique($out));
    }

    /**
     * Sanitize "trusted proxies" block (IPs or CIDRs, one per line).
     */
    private static function sanitize_trusted_proxies_block(string $text): string {
        $text  = (string) $text;
        $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $text));
        $out   = [];
        foreach ($lines as $line) {
            if ($line === '') { continue; }
            if (strpos($line, '/') !== false) {
                // CIDR
                if (self::is_valid_cidr_format($line)) {
                    $out[] = $line;
                }
            } else {
                if (filter_var($line, FILTER_VALIDATE_IP)) {
                    $out[] = $line;
                }
            }
        }
        return implode("\n", array_values(array_unique($out)));
    }

    /**
     * Retrieve plugin settings (safe fallback).
     */
    public static function get_settings(): array {
        return Settings_Overrides::get_settings();
    }

    /**
     * Validate a CIDR string (e.g. 192.168.0.0/24 or 2001:db8::/32).
     */
    private static function is_valid_cidr_format(string $cidr): bool {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) { return false; }
        [$subnet, $mask] = $parts;
        if (!filter_var($subnet, FILTER_VALIDATE_IP)) { return false; }
        $mask = (int) $mask;
        $bits = strpos($subnet, ':') !== false ? 128 : 32;
        return $mask >= 0 && $mask <= $bits;
    }
}
