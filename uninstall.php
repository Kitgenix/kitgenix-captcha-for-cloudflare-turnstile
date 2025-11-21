<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Kitgenix CAPTCHA for Cloudflare Turnstile
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Remove all plugin data from the database.
 */
function kitgenix_captcha_for_cloudflare_turnstile_remove_plugin_data() {
    // Remove plugin settings from options table
    delete_option('kitgenix_captcha_for_cloudflare_turnstile_settings');
    
    // Remove any transients or user meta if used
    

    // Multisite support: remove settings from all sites
    if (is_multisite()) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query is required for multisite uninstall and caching is not needed for this one-time operation.
        $site_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);
            delete_option('kitgenix_captcha_for_cloudflare_turnstile_settings');
            
            restore_current_blog();
        }
    }
}

kitgenix_captcha_for_cloudflare_turnstile_remove_plugin_data();
