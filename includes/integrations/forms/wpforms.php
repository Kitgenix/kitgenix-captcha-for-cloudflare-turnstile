<?php
// WPForms integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function esc_attr;
use function esc_html__;
use function get_option;
use function sanitize_text_field;
use function wp_nonce_field;
use function wp_unslash;
use function wpforms;

class WPForms {

    public static function init() {
        if ( ! class_exists( 'WPForms' ) || Whitelist::is_whitelisted() ) {
            return;
        }

        // Add Turnstile widget to WPForms frontend forms.
        // Hook in two places for markup compatibility; guard against duplicates per form.
        add_action( 'wpforms_display_after_fields',  [ __CLASS__, 'render_widget' ], 10, 2 );
        add_action( 'wpforms_display_submit_before', [ __CLASS__, 'render_widget' ], 10, 2 );

        // Validate Turnstile on submit (runs before processing entries).
        add_action( 'wpforms_process', [ __CLASS__, 'validate_turnstile' ], 9, 3 );
    }

    /**
     * Output Turnstile container + hidden input (guarded per form to avoid duplicates).
     *
     * @param array      $fields
     * @param array|null $form_data
     */
    public static function render_widget( $fields, $form_data = null ) {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';

        if ( ! $site_key ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">'
               . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' )
               . '</p>';
            return;
        }

        // Identify the current form to avoid duplicate rendering when both hooks fire.
        $form_id = ( is_array( $form_data ) && isset( $form_data['id'] ) ) ? (int) $form_data['id'] : 0;

        static $rendered_for = [];
        if ( $form_id && isset( $rendered_for[ $form_id ] ) ) {
            return; // already rendered for this form
        }
        if ( $form_id ) {
            $rendered_for[ $form_id ] = true;
        }

        // CSRF nonce for our validator
        if ( function_exists( 'wp_nonce_field' ) ) {
            wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce' );
        }

        // Hidden input for the token; public JS will populate it on successful challenge.
        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        // Turnstile container (global renderer will render the widget)
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'    . esc_attr( $site_key ) . '"'
           . ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
           . ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"'
           . ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
           . ' data-kgx-owner="wpforms"'
           . '></div>';
    }

    /**
     * Validate Turnstile on WPForms submit.
     *
     * @param array $fields
     * @param array $entry
     * @param array $form_data
     */
    public static function validate_turnstile( $fields, $entry, $form_data ) {
        if ( self::request_method() !== 'POST' ) {
            return;
        }

        // Validate using our central helper (handles dev warn-only + replay protection).
        if ( ! Turnstile_Validator::is_valid_submission() ) {
            $form_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
            if ( $form_id && function_exists( 'wpforms' ) ) {
                // Use a keyed footer error so WPForms displays it reliably.
                wpforms()->process->errors[ $form_id ]['footer']['turnstile'] =
                    Turnstile_Validator::get_error_message( 'wpforms' );
            }
        }
    }

    /**
     * Sanitize request method (PHPCS-friendly access to $_SERVER).
     */
    private static function request_method(): string {
        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

WPForms::init();
