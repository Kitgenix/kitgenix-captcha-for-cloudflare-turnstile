<?php
// JetFormBuilder integration for Kitgenix CAPTCHA for Cloudflare Turnstile
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined( 'ABSPATH' ) || exit;

use function add_action;
use function esc_attr;
use function esc_html__;
use function get_option;
use function sanitize_text_field;
use function wp_nonce_field;
use function wp_unslash;

class JetFormBuilder {

    /**
     * Init JetFormBuilder integration.
     */
    public static function init() {
        $present = class_exists( '\\Jet_Form_Builder\\Plugin' )
            || defined( 'JET_FORM_BUILDER_VERSION' )
            || defined( 'JET_FORM_BUILDER_PATH' );

        if ( ! $present || Whitelist::is_whitelisted() ) {
            return;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        if ( empty( $settings['enable_jetformbuilder'] ) ) {
            return;
        }

        // Auto-inject the widget near the submit button row.
        // JetFormBuilder renders fields inside "form rows"; we inject when a submit field row begins.
        add_action( 'jet-form-builder/after-start-form-row', [ __CLASS__, 'maybe_render_widget_for_row' ], 10, 1 );

        // Validate Turnstile before JetFormBuilder processes actions.
        // This runs inside JetFormBuilder's submission pipeline; throwing an Action_Exception blocks submission.
        add_action( 'jet-form-builder/form-handler/before-send', [ __CLASS__, 'validate_turnstile' ], 9, 1 );
    }

    /**
     * Render widget for the submit row (auto mode only).
     *
     * @param object $block JetFormBuilder block instance (varies by version).
     */
    public static function maybe_render_widget_for_row( $block ): void {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );

        // Respect per-integration mode.
        $mode = $settings['mode_jetformbuilder'] ?? 'auto';
        if ( $mode === 'shortcode' ) {
            return;
        }

        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">'
                . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' )
                . '</p>';
            return;
        }

        // Only inject for the submit button field row.
        $name = '';
        if ( is_object( $block ) && method_exists( $block, 'get_name' ) ) {
            $name = (string) $block->get_name();
        }
        if ( ! in_array( $name, [ 'submit-field', 'submit' ], true ) ) {
            return;
        }

        // Heuristic: avoid injecting on multi-step "next"/"prev" buttons.
        $action_type = '';
        if ( is_object( $block ) && property_exists( $block, 'block_attrs' ) && is_array( $block->block_attrs ) ) {
            $action_type = isset( $block->block_attrs['action_type'] ) ? (string) $block->block_attrs['action_type'] : '';
        }
        if ( $action_type && $action_type !== 'submit' ) {
            return;
        }

        // Avoid duplicate injection per form render.
        // JetFormBuilder can render multiple forms on one page; key by form ID when possible.
        $form_key = '';
        if ( is_object( $block ) && method_exists( $block, 'get_form_id' ) ) {
            $form_key = (string) $block->get_form_id();
        } elseif ( is_object( $block ) && property_exists( $block, 'form_id' ) ) {
            $form_key = (string) $block->form_id;
        }

        static $rendered_for = [];
        $render_key = $form_key !== '' ? $form_key : '__unknown__';
        if ( isset( $rendered_for[ $render_key ] ) ) {
            return;
        }
        $rendered_for[ $render_key ] = true;

        echo '<div class="kitgenix-captcha-for-cloudflare-turnstile-wrap">';

        if ( function_exists( 'wp_nonce_field' ) ) {
            wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce' );
        }

        echo '<input type="hidden" name="cf-turnstile-response" value="" />';

        echo '<div class="cf-turnstile"'
            . ' data-sitekey="' . esc_attr( $site_key ) . '"'
            . ' data-theme="' . esc_attr( $settings['theme'] ?? 'auto' ) . '"'
            . ' data-size="' . esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
            . ' data-appearance="' . esc_attr( $settings['appearance'] ?? 'always' ) . '"'
            . ' data-kitgenix-captcha-for-cloudflare-turnstile-owner="jetformbuilder"'
            . '></div>';

        echo '</div>';
    }

    /**
     * Validate Turnstile during JetFormBuilder submission.
     *
     * @param object $handler JetFormBuilder Form_Handler.
     * @throws \Jet_Form_Builder\Exceptions\Action_Exception
     */
    public static function validate_turnstile( $handler ): void {
        if ( self::request_method() !== 'POST' ) {
            return;
        }

        // Always validate - if token/nonce are missing, validation will fail.
        if ( Turnstile_Validator::is_valid_submission( true, 'jetformbuilder' ) ) {
            return;
        }

        $message = Turnstile_Validator::get_error_message( 'jetformbuilder' );

        // JetFormBuilder supports dynamic errors prefixed like "derror|...".
        if ( class_exists( '\\Jet_Form_Builder\\Form_Messages\\Manager' )
            && method_exists( '\\Jet_Form_Builder\\Form_Messages\\Manager', 'dynamic_error' ) ) {
            $message = (string) \Jet_Form_Builder\Form_Messages\Manager::dynamic_error( $message );
        }

        // Prefer JetFormBuilder's exception flow so the frontend receives a proper failure response.
            if ( class_exists( '\\Jet_Form_Builder\\Exceptions\\Action_Exception' ) ) {
                // Ensure message is safe for output when JetFormBuilder surfaces it.
                throw new \Jet_Form_Builder\Exceptions\Action_Exception( esc_html( $message ) );
        }

        // Fallback: stop request if exception class is unavailable.
        if ( function_exists( 'wp_die' ) ) {
            wp_die( esc_html( $message ), 403 );
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

JetFormBuilder::init();
