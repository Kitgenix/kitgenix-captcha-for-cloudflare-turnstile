<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forums;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;

defined('ABSPATH') || exit;

class BuddyPress {

    public static function init() {
        if ( Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = function_exists('get_option') ? (array) get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []) : [];
        if ( empty($settings['enable_buddypress']) ) {
            return;
        }

        if ( ! defined('BP_VERSION') ) {
            return;
        }

        // Render widget on BuddyPress registration forms
        add_action( 'bp_before_registration_submit_buttons', [ __CLASS__, 'render_widget' ] );

        // Also render on Activity post forms (activity stream / post update box).
        // Attach only to the "before" hook so the widget appears above the "Post Update" button
        // and to avoid duplicate insertions from multiple hook points.
        add_action( 'bp_before_activity_post_form', [ __CLASS__, 'render_widget' ] );

        // Validate registration submissions (server-side)
        add_action( 'bp_actions', [ __CLASS__, 'maybe_validate_registration' ], 1 );
    }

    public static function render_widget() {
        $settings = function_exists('get_option') ? (array) get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []) : [];
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">' . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</p>';
            return;
        }

        if ( function_exists('wp_nonce_field') ) {
            wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce' );
        }

        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Default to left-aligned so the activity stream widget matches other forms
        $inline_style = (string) apply_filters( 'kitgenix_turnstile_inline_style', 'display: flex; justify-content: flex-start;', 'buddypress' );

          // Mark this container so our JS can relocate it to just before the Post Update button
          echo '<div class="cf-turnstile"'
              . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
              . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
              . ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"'
              . ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
              . ' data-placement="buddypress-post-update"'
              . '></div>';
    }

    public static function maybe_validate_registration() {
        if ( self::request_method() !== 'POST' ) {
            return;
        }

        if ( function_exists('bp_is_register_page') && ! bp_is_register_page() ) {
            return;
        }

        if ( ! Turnstile_Validator::is_valid_submission() ) {
            $message = Turnstile_Validator::get_error_message( 'buddypress' );
            if ( function_exists('bp_core_add_message') ) {
                bp_core_add_message( $message, 'error' );
            }

            $redirect = wp_get_referer() ?: home_url();
            if ( function_exists('bp_core_redirect') ) {
                bp_core_redirect( $redirect );
            } else {
                wp_safe_redirect( $redirect );
                exit;
            }
        }
    }

    private static function request_method(): string {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

