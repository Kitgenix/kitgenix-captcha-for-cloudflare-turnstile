<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\PageBuilder;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function esc_attr;
use function esc_html__;
use function get_option;
use function is_admin;
use function defined;
use function function_exists;
use function wp_doing_ajax;
use function wp_doing_cron;
use function sanitize_text_field;
use function wp_enqueue_script;
use function wp_nonce_field;
use function wp_verify_nonce;
use function wp_unslash;
use function is_object;

class Elementor {

    public static function init() {
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return;
        }

        // Allow Elementor editor/preview even when whitelisted (for UX), otherwise bail if whitelisted.
        if ( Whitelist::is_whitelisted() ) {
            $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            $is_elementor_preview = ( ! is_admin() ) && ( strpos( $uri, 'elementor' ) !== false );
            if ( ! $is_elementor_preview ) {
                return;
            }
        }

        // Render the widget container after fields (Elementor Pro)
        add_action( 'elementor_pro/forms/render_form_after_fields', [ __CLASS__, 'render_widget' ], 10, 1 );

        // Elementor Pro forms (server-side validation)
        add_action( 'elementor_pro/forms/validation', [ __CLASS__, 'validate_turnstile' ], 10, 2 );

        // Keep the Elementor JS *container* helper only (no rendering here)
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

        // Fallback to inject a container for Elementor (free) forms before submit
        add_action( 'wp_footer', [ __CLASS__, 'fallback_inject_widget' ], 20 );
    }

    /**
     * Output only the Turnstile container + the hidden input.
     * Rendering is handled centrally in public.js (do NOT call turnstile.render here).
     */
    public static function render_widget( $form ) {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';

        // Respect per-integration mode: allow admins to disable auto-inject and use shortcode-only placement.
        $mode = $settings['mode_elementor'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            return;
        }

        if ( ! $site_key ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">'
               . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' )
               . '</p>';
            return;
        }

        // FIXED: Wrap in field group so Elementor includes hidden fields
        echo '<div class="elementor-field-group elementor-column elementor-col-100">';
        
        // CSRF nonce (Elementor Pro posts all fields via AJAX)
        if ( function_exists( 'wp_nonce_field' ) ) {
            wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce' );
        }

        // Hidden input for token (add elementor-field class for serialization)
        echo '<input type="hidden" name="cf-turnstile-response" value="" class="elementor-field" />';

        // Placeholder Turnstile container (NO rendering done here)
        echo '<div class="cf-turnstile"'
           . ' data-sitekey="'   . esc_attr( $site_key ) . '"'
           . ' data-theme="'     . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
           . ' data-size="'      . esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
           . ' data-appearance="'. esc_attr( $settings['appearance']  ?? 'always' ) . '"'
           . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="elementor"'
           . '></div>';
        
        echo '</div>';
    }

    /**
     * Fallback for Elementor (free) forms or cases where the widget didn’t render:
     * create a container before the submit button. Global renderer will pick it up.
     */
    public static function fallback_inject_widget() {
        $settings   = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key   = $settings['site_key'] ?? '';
        $theme      = $settings['theme'] ?? 'auto';
        $size       = \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) );
        $appearance = $settings['appearance'] ?? 'always';

        // Respect per-integration mode: if shortcode-only is selected for Elementor, skip the fallback injection.
        $mode = $settings['mode_elementor'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            return;
        }

        if ( ! $site_key ) {
            return;
        }

        $handle   = 'kitgenix-captcha-for-cloudflare-turnstile-elementor';
        $base_url = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            ? (string) constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            : '';
        $ver      = defined( 'KitgenixCaptchaForCloudflareTurnstile_Version' )
            ? (string) constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' )
            : null;

        if ( $base_url && function_exists( 'wp_script_is' ) && ! wp_script_is( $handle, 'registered' ) ) {
            wp_register_script(
                $handle,
                $base_url . 'js/elementor.js',
                [ 'jquery', 'elementor-frontend' ],
                $ver,
                true
            );
        }

        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script( $handle );
        }

        if ( function_exists( 'wp_add_inline_script' ) ) {
            static $inline_added = false;
            if ( ! $inline_added ) {
                $inline_added = true;

                $inline =
                    'document.addEventListener("DOMContentLoaded", function () {'
                    . 'document.querySelectorAll(".elementor-form-fields-wrapper").forEach(function(wrapper){'
                        . 'var form = wrapper.closest("form");'
                        . 'if (!form) return;'

                        . 'if (form.querySelector(".cf-turnstile")) {'
                            . 'if (!form.querySelector("input[name=\\"cf-turnstile-response\\"]")) {'
                                . 'var inputExisting = document.createElement("input");'
                                . 'inputExisting.type = "hidden";'
                                . 'inputExisting.name = "cf-turnstile-response";'
                                . 'form.appendChild(inputExisting);'
                            . '}'
                            . 'return;'
                        . '}'

                        . 'if (!form.querySelector("input[name=\\"cf-turnstile-response\\"]")) {'
                            . 'var input = document.createElement("input");'
                            . 'input.type = "hidden";'
                            . 'input.name = "cf-turnstile-response";'
                            . 'form.appendChild(input);'
                        . '}'

                        . 'var submitGroup = wrapper.querySelector(".elementor-field-type-submit");'
                        . 'if (!submitGroup) return;'

                        . 'var container = document.createElement("div");'
                        . 'container.className = "cf-turnstile";'
                        . 'container.setAttribute("data-sitekey", ' . wp_json_encode( (string) $site_key ) . ');'
                        . 'container.setAttribute("data-theme", ' . wp_json_encode( (string) $theme ) . ');'
                        . 'container.setAttribute("data-size", ' . wp_json_encode( (string) $size ) . ');'
                        . 'container.setAttribute("data-appearance", ' . wp_json_encode( (string) $appearance ) . ');'
                        . 'container.setAttribute("data-kitgenix-captcha-for-cloudflare-turnstile-owner", "elementor");'
                        . 'submitGroup.parentNode.insertBefore(container, submitGroup);'

                        . 'try{document.dispatchEvent(new CustomEvent("KitgenixCaptchaForCloudflareTurnstile:turnstile-containers-added", { detail: { source: "elementor" } }));}catch(e){}'
                    . '});'
                    . '});';

                wp_add_inline_script( $handle, $inline, 'after' );
            }
        }
    }

    /**
     * Server-side validation for Elementor Pro forms (AJAX).
     */
    public static function validate_turnstile( $record, $ajax_handler ) {
        if ( self::request_method() !== 'POST' ) {
            return;
        }

        // FIXED: Try multiple methods to get the token - POST first (most reliable), then Elementor record, then header
        $token = '';
        $nonce = '';
        
        // Method 1: Try $_POST first (most direct and reliable for Elementor AJAX)
        if ( isset( $_POST['cf-turnstile-response'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) );
        }
        if ( isset( $_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'] ) );
        }

        // Method 2: Try to get from Elementor $record payload (backup method)
        if ( $token === '' && is_object( $record ) ) {
            if ( method_exists( $record, 'get_formatted_data' ) ) {
                $data = $record->get_formatted_data();
                if ( is_array( $data ) && ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
                    foreach ( $data['fields'] as $field ) {
                        $name = $field['id'] ?? $field['name'] ?? '';
                        $value = $field['value'] ?? '';
                        if ( $name === 'cf-turnstile-response' ) {
                            $token = sanitize_text_field( (string) $value );
                        }
                        if ( $name === 'kitgenix_captcha_for_cloudflare_turnstile_nonce' ) {
                            $nonce = sanitize_text_field( (string) $value );
                        }
                    }
                }
            } elseif ( method_exists( $record, 'get' ) ) {
                $fields = $record->get( 'fields' );
                if ( is_array( $fields ) ) {
                    foreach ( $fields as $field ) {
                        $name = $field['id'] ?? $field['name'] ?? '';
                        $value = $field['value'] ?? '';
                        if ( $name === 'cf-turnstile-response' ) {
                            $token = sanitize_text_field( (string) $value );
                        }
                        if ( $name === 'kitgenix_captcha_for_cloudflare_turnstile_nonce' ) {
                            $nonce = sanitize_text_field( (string) $value );
                        }
                    }
                }
            }
        }

        // Method 3: Fallback to header token (fetch/Blocks flows)
        if ( $token === '' && isset( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TURNSTILE_TOKEN'] ) );
        }

        // If a nonce was provided by the form, verify it. If verification fails,
        // treat the submission as invalid. If no nonce is present, continue —
        // Elementor may rely on its own CSRF protections in some flows.
        if ( $nonce !== '' ) {
            if ( function_exists( 'wp_verify_nonce' ) && ! wp_verify_nonce( $nonce, 'kitgenix_captcha_for_cloudflare_turnstile_action' ) ) {
                $ajax_handler->add_error_message( Turnstile_Validator::get_error_message( 'elementor' ) );
                $ajax_handler->add_error( '__all__' );
                return;
            }
        }

        // Validate the token (will fail if empty)
        $ok = Turnstile_Validator::validate_token( $token, 'elementor' );

        if ( ! $ok ) {
            $ajax_handler->add_error_message( Turnstile_Validator::get_error_message( 'elementor' ) );
            $ajax_handler->add_error( '__all__' );
        }
    }

    public static function enqueue_scripts() {
        // Do NOT load the Cloudflare API here; it is loaded once globally by the public script.
        // Guard enqueuing to frontend page loads that have a valid global $post. Elementor's
        // adapter may attempt to read global post properties (e.g. post_title). When there is
        // no post (REST/AJAX/cron requests or some early hooks), that triggers a PHP warning
        // in the Elementor plugin. Skip enqueuing in those contexts to avoid the warning.
        if ( is_admin() ) {
            return;
        }

        if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
            return;
        }

        global $post;
        if ( empty( $post ) || ! is_object( $post ) ) {
            // No global post context — skip to avoid Elementor reading null post properties.
            return;
        }

        wp_enqueue_script(
            'kitgenix-captcha-for-cloudflare-turnstile-elementor',
            KitgenixCaptchaForCloudflareTurnstile_Assets_URL . 'js/elementor.js',
            [ 'jquery', 'elementor-frontend' ],
            KitgenixCaptchaForCloudflareTurnstile_Version,
            true
        );
    }

    /**
     * Get sanitized request method to satisfy PHPCS for $_SERVER access.
     */
    private static function request_method(): string {
        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        return strtoupper( $method ?: 'GET' );
    }
}

Elementor::init();
