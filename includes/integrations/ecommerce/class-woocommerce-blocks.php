<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function add_filter;
use function esc_attr;
use function get_option;
use function is_wp_error;
use function sanitize_text_field;
use function wp_unslash;

class WooCommerce_Blocks {

    public static function init() {
        // Inject our container near the Place Order button in the Checkout Block.
        // Filter receives ($block_content, $block), so accept 2 args.
        add_filter( 'render_block_woocommerce/checkout-actions-block', [ __CLASS__, 'inject_turnstile' ], 10, 2 );

        // Fail-closed validation for Store API checkout requests.
        add_filter( 'rest_authentication_errors', [ __CLASS__, 'rest_protect_store_api' ], 12 );

        // (Optional) Mark the order as verified (handy for audits).
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ __CLASS__, 'annotate_order' ], 10, 2 );
    }

    /**
     * Append a Turnstile container to the Checkout Actions block output.
     *
     * @param string $block_content
     * @param array  $block
     * @return string
     */
    public static function inject_turnstile( $block_content, $block ) {
        if ( Whitelist::is_whitelisted() ) {
            return $block_content;
        }

        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        $site_key = $settings['site_key'] ?? '';
        if ( ! $site_key ) {
            return $block_content;
        }

        // Respect per-integration mode: allow shortcode-only placement for blocks if configured.
        $mode_blocks = $settings['mode_woocommerce_blocks'] ?? 'auto';
        if ( $mode_blocks === 'shortcode' ) {
            return $block_content;
        }

    // Avoid duplicates or if a rendered widget/container is already present in the block HTML.
    // Ignore literal shortcode tokens so auto-mode isn't blocked by leftover shortcode text.
    if ( is_string( $block_content ) && ( strpos( $block_content, 'class="cf-turnstile' ) !== false
        || \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Shortcode::has_shortcode_in( $block_content, false ) ) ) {
            return $block_content;
        }

        $widget  = '<div class="cf-turnstile kitgenix-captcha-for-cloudflare-turnstile-wc-blocks"';
        $widget .= ' data-sitekey="'    . esc_attr( $site_key ) . '"';
        $widget .= ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"';
        $widget .= ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"';
        $widget .= ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"';
        $widget .= '></div>';

        return (string) $block_content . $widget;
    }

    /**
     * Block checkout: validate token early for Store API checkout POST.
     * Reads token from Checkout data store "extensions" payload.
     *
     * @param mixed $result
     * @return mixed
     */
    public static function rest_protect_store_api( $result ) {
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Only POST
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is a REST/Store API endpoint, nonce not applicable
        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : 'GET';
        if ( strtoupper( $method ) !== 'POST' ) {
            return $result;
        }

        // Detect Store API checkout route (supports both pretty and ?rest_route=)
        $route = '';
        // Determine the current REST route/URI. This runs very early in the
        // request lifecycle (authentication stage) where a nonce is not
        // applicable. Silence PHPCS with a short rationale.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- REST/Store API early auth; nonce not applicable here
        if ( isset( $_GET['rest_route'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- REST/Store API early auth; nonce not applicable here
            $route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );
        } elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- REST/Store API early auth; nonce not applicable here
            $route = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
        }

        if ( ! preg_match( '#/wc/store(?:/v\d+)?/checkout$#', $route ) ) {
            return $result;
        }

        // Respect whitelist
        if ( Whitelist::is_whitelisted() ) {
            return $result;
        }

        // Parse JSON body
        $raw  = \WP_REST_Server::get_raw_data();
        $json = json_decode( $raw, true );
        if ( ! is_array( $json ) ) {
            $json = [];
        }

        // Our namespace in extensions
        $ns         = 'kitgenix-captcha-for-cloudflare-turnstile';
        $extensions = isset( $json['extensions'] ) && is_array( $json['extensions'] ) ? $json['extensions'] : [];
        $token      = '';

        if ( isset( $extensions[ $ns ] ) && is_array( $extensions[ $ns ] ) && isset( $extensions[ $ns ]['token'] ) ) {
            // Value comes from JSON (no magic slashes), sanitize anyway.
            $token = sanitize_text_field( (string) $extensions[ $ns ]['token'] );
        }

        if ( $token === '' ) {
            if ( self::dev_mode_enabled() ) {
                self::dev_log( 'wc_blocks_missing_token', [ 'route' => $route ] );
                return $result;
            }
            return new \WP_Error(
                'kitgenix_turnstile_missing',
                Turnstile_Validator::get_error_message( 'woocommerce' ),
                [ 'status' => 403 ]
            );
        }

        if ( ! Turnstile_Validator::validate_token( $token ) ) {
            if ( self::dev_mode_enabled() ) {
                self::dev_log( 'wc_blocks_invalid_token' );
                return $result;
            }
            return new \WP_Error(
                'kitgenix_turnstile_failed',
                Turnstile_Validator::get_error_message( 'woocommerce' ),
                [ 'status' => 403 ]
            );
        }

        return $result;
    }

    /**
     * (Optional) Persist a note on the order after a valid token.
     *
     * @param \WC_Order        $order
     * @param \WP_REST_Request $request
     */
    public static function annotate_order( $order, $request ) {
        $ext = $request->get_param( 'extensions' );
        if ( is_array( $ext )
            && isset( $ext['kitgenix-captcha-for-cloudflare-turnstile']['token'] )
            && $ext['kitgenix-captcha-for-cloudflare-turnstile']['token'] !== ''
        ) {
            // Use GMT to avoid runtime timezone side effects.
            $order->update_meta_data( '_kitgenix_turnstile_verified', gmdate( 'Y-m-d H:i:s' ) );
            $order->save();
        }
    }

    private static function dev_mode_enabled(): bool {
        $settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        return ! empty( $settings['dev_mode_warn_only'] );
    }

    private static function dev_log( $msg, $context = [] ) {
        if ( ! self::dev_mode_enabled() ) {
            return;
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[Kitgenix Turnstile DEV] ' . $msg . ' ' . wp_json_encode( $context ) );
        }
        do_action( 'kitgenix_turnstile_dev_log', $msg, $context );
    }
}
