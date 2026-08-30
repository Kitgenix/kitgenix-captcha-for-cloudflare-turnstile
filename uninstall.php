<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Kitgenix CAPTCHA for Cloudflare Turnstile
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Delete all transients (and their timeout rows) with a given key prefix.
 *
 * Supports dynamic keys like replay-protection hashes.
 */
/* phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching */
function kitgenix_captcha_for_cloudflare_turnstile_delete_transients_by_prefix( string $prefix ): void {
    global $wpdb;
    if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
        return;
    }

    $transient_like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
    $timeout_like   = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

    // Fetch matching option names and delete via WP API to ensure proper cache handling.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    // Reason: There is no WP API to list option names by prefix using LIKE. We only SELECT
    // the `option_name` column and then delete each matching entry via `delete_transient`
    // / `delete_option`, which properly clears caches. Using a direct SELECT here is
    // minimal and safer than issuing a broad DELETE query directly.
    $option_names = /* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching */ $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $transient_like,
            $timeout_like
        )
    );

    if ( ! empty( $option_names ) ) {
        foreach ( $option_names as $option_name ) {
            if ( strpos( $option_name, '_transient_timeout_' ) === 0 ) {
                $transient = substr( $option_name, strlen( '_transient_timeout_' ) );
            } elseif ( strpos( $option_name, '_transient_' ) === 0 ) {
                $transient = substr( $option_name, strlen( '_transient_' ) );
            } else {
                delete_option( $option_name );
                continue;
            }

            // Use the API to remove the transient and its timeout, which clears caches.
            delete_transient( $transient );
        }
    }
}
/* phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching */

/**
 * Remove all plugin data from the database.
 */
function kitgenix_captcha_for_cloudflare_turnstile_remove_plugin_data() {
    // Remove plugin settings + plugin-only diagnostics.
    delete_option('kitgenix_captcha_for_cloudflare_turnstile_settings');
    delete_site_option('kitgenix_captcha_for_cloudflare_turnstile_settings');

    delete_option('kitgenix_turnstile_last_verify');
    delete_site_option('kitgenix_turnstile_last_verify');

    delete_option('kitgenix_captcha_for_cloudflare_turnstile_metrics');
    delete_site_option('kitgenix_captcha_for_cloudflare_turnstile_metrics');

    delete_option('kitgenix_turnstile_recent_event_log');
    delete_site_option('kitgenix_turnstile_recent_event_log');

    // Setup_Verification's gate state (Site Key/Secret Key pair hash + last verification result).
    delete_option('kitgenix_turnstile_setup_verification');
    delete_site_option('kitgenix_turnstile_setup_verification');

    // Remove plugin-owned transients (including dynamic replay-protection keys).
    kitgenix_captcha_for_cloudflare_turnstile_delete_transients_by_prefix( 'kitgenix_captcha_for_cloudflare_turnstile_' );
    kitgenix_captcha_for_cloudflare_turnstile_delete_transients_by_prefix( 'kitgenix_turnstile_' );
    

    // Multisite support: remove per-site options from all sites.
    if (is_multisite() && function_exists('get_sites')) {
        $sites = get_sites(['fields' => 'ids']);
        foreach ((array) $sites as $site_id) {
            switch_to_blog((int) $site_id);

            delete_option('kitgenix_captcha_for_cloudflare_turnstile_settings');
            delete_option('kitgenix_turnstile_last_verify');
            delete_option('kitgenix_captcha_for_cloudflare_turnstile_metrics');
            delete_option('kitgenix_turnstile_recent_event_log');
            delete_option('kitgenix_turnstile_setup_verification');
            kitgenix_captcha_for_cloudflare_turnstile_delete_transients_by_prefix( 'kitgenix_captcha_for_cloudflare_turnstile_' );
            kitgenix_captcha_for_cloudflare_turnstile_delete_transients_by_prefix( 'kitgenix_turnstile_' );

            restore_current_blog();
        }
    }
}

kitgenix_captcha_for_cloudflare_turnstile_remove_plugin_data();
