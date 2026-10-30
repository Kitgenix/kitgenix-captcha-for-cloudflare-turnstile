<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

use function get_option;
use function is_user_logged_in;
use function is_admin;
use function wp_unslash;
use function wp_safe_redirect;
use function home_url;
use function add_filter;
use function add_action;
use function __;

/**
 * REST user-enumeration hardening (roadmap §3). Blocks two well-known ways to
 * discover valid WordPress usernames without any credentials:
 *
 * 1. The REST API's `/wp/v2/users` collection and `/wp/v2/users/<id>` routes,
 *    which by default list every user's public display name/slug (and, with a
 *    configured login name as the "nicename", the login itself) to anyone,
 *    unauthenticated.
 * 2. The legacy `?author=<id>` query parameter, which redirects to that user's
 *    author archive – revealing their login-derived nicename in the
 *    resulting URL even if the theme never links to author archives.
 *
 * Both are off by default (opt-in via the "Block User Enumeration" setting)
 * since some sites intentionally expose author info or rely on the users
 * REST route from a logged-out context (e.g. a headless frontend); a
 * logged-in request is never blocked either way.
 */
class Enumeration_Guard {
    private const SETTINGS_OPTION = 'kitgenix_captcha_for_cloudflare_turnstile_settings';

    public static function init(): void {
        if ( ! self::enabled() ) {
            return;
        }

        add_filter( 'rest_pre_dispatch', [ __CLASS__, 'block_rest_users_route' ], 10, 3 );
        add_action( 'template_redirect', [ __CLASS__, 'block_legacy_author_param' ] );
    }

    private static function enabled(): bool {
        $settings = get_option( self::SETTINGS_OPTION, [] );
        return is_array( $settings ) && ! empty( $settings['block_user_enumeration'] );
    }

    /**
     * Block unauthenticated access to the REST `/wp/v2/users` collection and
     * individual user routes. Logged-in requests (any capability) pass through
     * untouched, since the REST API's own capability checks already govern
     * what a logged-in user may see.
     *
     * @param mixed            $result
     * @param \WP_REST_Server  $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public static function block_rest_users_route( $result, $server, $request ) {
        if ( \is_wp_error( $result ) || is_user_logged_in() ) {
            return $result;
        }

        $route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
        if ( ! preg_match( '#^/wp/v2/users(?:/|$)#', $route ) ) {
            return $result;
        }

        return new \WP_Error(
            'kitgenix_rest_user_enumeration_blocked',
            __( 'Listing or viewing users via the REST API is disabled on this site.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            [ 'status' => 401 ]
        );
    }

    /**
     * Block the classic `?author=<numeric id>` username-enumeration trick
     * (visiting it redirects to `/author/<nicename>/`, revealing the login
     * name) for logged-out visitors. A non-numeric `author` query var (most
     * themes use pretty permalinks instead) is left alone.
     */
    public static function block_legacy_author_param(): void {
        if ( is_admin() || is_user_logged_in() ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of a GET query var, not a form action.
        if ( ! isset( $_GET['author'] ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of a GET query var, not a form action.
        $author = wp_unslash( $_GET['author'] );
        if ( ! is_string( $author ) || ! ctype_digit( $author ) ) {
            return;
        }

        wp_safe_redirect( home_url( '/' ), 302 );
        exit;
    }
}
