<?php
/**
 * Plugin Name:       Kitgenix CAPTCHA for Cloudflare Turnstile
 * Plugin URI:        https://wordpress.org/plugins/kitgenix-captcha-for-cloudflare-turnstile/
 * Author Plugin URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile
 * Documentation URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation
 * Support URI:       https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/
 * Author Support URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/support
 * Feature Request URI: https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/feature-request
 * Description:       Add Cloudflare Turnstile CAPTCHA to WordPress, WooCommerce, Elementor, and popular form plugins with privacy-first server-side verification.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            Kitgenix
 * Author URI:        https://kitgenix.com/
 * Donate link:       https://www.paypal.com/donate/?hosted_button_id=KALF36K6JJ9B2
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       kitgenix-captcha-for-cloudflare-turnstile
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// -----------------------------------------------------------------------------
// Shared Kitgenix admin menu (top-level) helper.
// Each Kitgenix plugin may call this; it is safe to call multiple times.
// -----------------------------------------------------------------------------
if ( ! function_exists( 'kitgenix_get_admin_menu_icon' ) ) {
    function kitgenix_get_admin_menu_icon( string $plugin_file ): string {
        $plugin_dir = dirname( $plugin_file ) . '/';
        $icon_paths = [
            $plugin_dir . 'assets/images/logos/kitgenix-wordpress-admin-menu-favicon.svg',
            $plugin_dir . 'assets/images/logos/kitgenix-black-favicon.svg',
        ];

        foreach ( $icon_paths as $icon_path ) {
            if ( ! is_readable( $icon_path ) ) {
                continue;
            }

            $svg = file_get_contents( $icon_path );
            if ( false !== $svg && '' !== trim( $svg ) ) {
                return 'data:image/svg+xml;base64,' . base64_encode( $svg );
            }
        }

        return 'dashicons-admin-generic';
    }
}

if ( ! function_exists( 'kitgenix_ensure_admin_menu' ) ) {
    function kitgenix_ensure_admin_menu(): void {
        if ( ! is_admin() ) {
            return;
        }

        global $admin_page_hooks;
        $slug = 'kitgenix';

        if ( isset( $admin_page_hooks[ $slug ] ) ) {
            return;
        }

        // Prefer WooCommerce managers when WooCommerce is active, otherwise admins.
        $capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( ! current_user_can( $capability ) && current_user_can( 'manage_options' ) ) {
            $capability = 'manage_options';
        }

        $icon_url = kitgenix_get_admin_menu_icon( __FILE__ );

        add_menu_page(
            __( 'Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            __( 'Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            $capability,
            $slug,
            'kitgenix_render_admin_page',
            $icon_url,
            58
        );
    }
}

// Ensure the shared Kitgenix top-level menu exists.
add_action( 'admin_menu', 'kitgenix_ensure_admin_menu', 5 );

if ( ! function_exists( 'kitgenix_captcha_for_cloudflare_turnstile_register_icons' ) ) {
    /**
     * Register this plugin's brand icon with WordPress 7.1's Icon Registration API
     * (wp_register_icon_collection()/wp_register_icon()), so it is discoverable via
     * wp_get_icon('kitgenix/mark') and the /wp-json/wp/v2/icons REST endpoint.
     *
     * This is unrelated to the wp-admin top-level menu icon (add_menu_page()'s
     * icon_url argument still only accepts a dashicon class, a URL, or a base64
     * data: URI – the Icon API does not feed into it), so the menu icon above keeps
     * using kitgenix_get_admin_menu_icon() as before, on every supported WP version.
     */
    function kitgenix_captcha_for_cloudflare_turnstile_register_icons(): void {
        if ( ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
            return;
        }

        wp_register_icon_collection(
            'kitgenix',
            [
                'label'       => __( 'Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'description' => __( 'Brand icons shared across Kitgenix plugins.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ]
        );

        $icon_path = dirname( __FILE__ ) . '/assets/images/logos/kitgenix-wordpress-admin-menu-favicon.svg';
        if ( is_readable( $icon_path ) ) {
            wp_register_icon(
                'kitgenix/mark',
                [
                    'label'     => __( 'Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    'file_path' => $icon_path,
                ]
            );
        }
    }
}
add_action( 'init', 'kitgenix_captcha_for_cloudflare_turnstile_register_icons' );

if ( ! function_exists( 'kitgenix_hub_get_wporg_active_installs' ) ) {
    /**
     * Fetch WP.org active install counts for a set of plugin slugs.
     * Cached to avoid repeated network calls.
     *
     * @param string[] $slugs
     * @return array<string,int> Map of slug => active_installs
     */
    function kitgenix_hub_get_wporg_active_installs( array $slugs ): array {
        if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
            return [];
        }

        $clean_slugs = [];
        foreach ( $slugs as $slug ) {
            $slug = is_string( $slug ) ? $slug : '';
            $slug = function_exists( 'sanitize_key' ) ? sanitize_key( $slug ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $slug ) );
            if ( $slug ) {
                $clean_slugs[] = $slug;
            }
        }

        $clean_slugs = array_values( array_unique( $clean_slugs ) );
        if ( empty( $clean_slugs ) ) {
            return [];
        }

        $cache_key = 'kitgenix_hub_wporg_active_installs_v1';
        $cached    = get_transient( $cache_key );
        $cached    = is_array( $cached ) ? $cached : [];

        $missing = [];
        foreach ( $clean_slugs as $slug ) {
            if ( ! array_key_exists( $slug, $cached ) ) {
                $missing[] = $slug;
            }
        }

        if ( ! empty( $missing ) ) {
            if ( ! function_exists( 'plugins_api' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            foreach ( $missing as $slug ) {
                $info = plugins_api(
                    'plugin_information',
                    [
                        'slug'   => $slug,
                        'fields' => [
                            'active_installs'   => true,
                            'short_description' => false,
                            'description'       => false,
                            'sections'          => false,
                            'versions'          => false,
                            'banners'           => false,
                            'rating'            => false,
                            'ratings'           => false,
                            'downloaded'        => false,
                            'last_updated'      => false,
                            'added'             => false,
                            'tags'              => false,
                            'requires'          => false,
                            'requires_php'      => false,
                            'tested'            => false,
                            'homepage'          => false,
                            'donate_link'       => false,
                        ],
                    ]
                );

                if ( function_exists( 'is_wp_error' ) && is_wp_error( $info ) ) {
                    continue;
                }

                if ( is_object( $info ) && isset( $info->active_installs ) ) {
                    $cached[ $slug ] = (int) $info->active_installs;
                }
            }

            $ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
            set_transient( $cache_key, $cached, $ttl );
        }

        $result = [];
        foreach ( $clean_slugs as $slug ) {
            if ( isset( $cached[ $slug ] ) && (int) $cached[ $slug ] > 0 ) {
                $result[ $slug ] = (int) $cached[ $slug ];
            }
        }

        return $result;
    }
}

if ( ! function_exists( 'kitgenix_hub_get_wporg_ratings' ) ) {
    /**
     * Fetch WP.org ratings (percentage) for a set of plugin slugs.
     *
     * @param array<int,string> $slugs Plugin slugs.
     * @return array<string,int> Map of slug => rating percentage (0-100)
     */
    function kitgenix_hub_get_wporg_ratings( array $slugs ): array {
        $slugs = array_values( array_unique( array_filter( array_map( 'strval', $slugs ) ) ) );
        if ( empty( $slugs ) ) {
            return [];
        }

        $cache_key = 'kitgenix_hub_wporg_ratings_v1';
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            $missing = array_diff( $slugs, array_keys( $cached ) );
            if ( empty( $missing ) ) {
                return $cached;
            }
        } else {
            $cached = [];
            $missing = $slugs;
        }

        if ( ! function_exists( 'plugins_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        foreach ( $missing as $slug ) {
            $info = plugins_api(
                'plugin_information',
                [
                    'slug'   => $slug,
                    'fields' => [
                        'rating' => true,
                    ],
                ]
            );

            if ( is_object( $info ) && isset( $info->rating ) ) {
                $cached[ $slug ] = (int) $info->rating;
            }
        }

        set_transient( $cache_key, $cached, DAY_IN_SECONDS );

        return $cached;
    }
}

if ( ! function_exists( 'kitgenix_hub_get_wporg_media' ) ) {
    /**
     * Fetch WP.org banner or icon artwork for a set of plugin slugs.
     *
     * @param array<int,string> $slugs Plugin slugs.
     * @return array<string,array{url:string,type:string}> Map of slug => media payload.
     */
    function kitgenix_hub_get_wporg_media( array $slugs ): array {
        if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
            return [];
        }

        $slugs = array_values( array_unique( array_filter( array_map( 'strval', $slugs ) ) ) );
        if ( empty( $slugs ) ) {
            return [];
        }

        $cache_key = 'kitgenix_hub_wporg_media_v1';
        $cached    = get_transient( $cache_key );
        $cached    = is_array( $cached ) ? $cached : [];
        $missing   = array_diff( $slugs, array_keys( $cached ) );

        if ( ! empty( $missing ) ) {
            if ( ! function_exists( 'plugins_api' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            foreach ( $missing as $slug ) {
                $info = plugins_api(
                    'plugin_information',
                    [
                        'slug'   => $slug,
                        'fields' => [
                            'icons'             => true,
                            'banners'           => true,
                            'active_installs'   => false,
                            'rating'            => false,
                            'ratings'           => false,
                            'short_description' => false,
                            'description'       => false,
                            'sections'          => false,
                            'versions'          => false,
                            'downloaded'        => false,
                            'last_updated'      => false,
                            'added'             => false,
                            'tags'              => false,
                            'requires'          => false,
                            'requires_php'      => false,
                            'tested'            => false,
                            'homepage'          => false,
                            'donate_link'       => false,
                        ],
                    ]
                );

                if ( function_exists( 'is_wp_error' ) && is_wp_error( $info ) ) {
                    continue;
                }

                $media_url  = '';
                $media_type = '';

                if ( is_object( $info ) && isset( $info->banners ) ) {
                    $banners = is_object( $info->banners ) ? get_object_vars( $info->banners ) : ( is_array( $info->banners ) ? $info->banners : [] );
                    foreach ( [ 'high', 'low' ] as $key ) {
                        if ( ! empty( $banners[ $key ] ) && is_string( $banners[ $key ] ) ) {
                            $media_url  = $banners[ $key ];
                            $media_type = 'banner';
                            break;
                        }
                    }
                }

                if ( '' === $media_url && is_object( $info ) && isset( $info->icons ) ) {
                    $icons = is_object( $info->icons ) ? get_object_vars( $info->icons ) : ( is_array( $info->icons ) ? $info->icons : [] );
                    foreach ( [ 'svg', '2x', '1x', 'default' ] as $key ) {
                        if ( ! empty( $icons[ $key ] ) && is_string( $icons[ $key ] ) ) {
                            $media_url  = $icons[ $key ];
                            $media_type = 'icon';
                            break;
                        }
                    }
                }

                $cached[ $slug ] = $media_url ? [
                    'url'  => $media_url,
                    'type' => $media_type,
                ] : [];
            }

            $ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
            set_transient( $cache_key, $cached, $ttl );
        }

        $result = [];
        foreach ( $slugs as $slug ) {
            if ( ! empty( $cached[ $slug ]['url'] ) ) {
                $result[ $slug ] = [
                    'url'  => (string) $cached[ $slug ]['url'],
                    'type' => ! empty( $cached[ $slug ]['type'] ) ? (string) $cached[ $slug ]['type'] : 'icon',
                ];
            }
        }

        return $result;
    }
}

if ( ! function_exists( 'kitgenix_render_admin_page' ) ) {
    function kitgenix_render_admin_page(): void {
        $allowed = current_user_can( 'manage_options' ) || ( class_exists( 'WooCommerce' ) && current_user_can( 'manage_woocommerce' ) );
        if ( ! $allowed ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'kitgenix-captcha-for-cloudflare-turnstile' ) );
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins_data = function_exists( 'get_plugins' ) ? get_plugins() : [];

        $plugins = [
            [
                'id'       => 'turnstile',
                'name'     => __( 'CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-captcha-for-cloudflare-turnstile',
                'file'     => 'kitgenix-captcha-for-cloudflare-turnstile/kitgenix-captcha-for-cloudflare-turnstile.php',
                'page'     => 'kitgenix-captcha-for-cloudflare-turnstile',
                'requires' => __( 'Add Cloudflare Turnstile CAPTCHA to WordPress, WooCommerce, Elementor, and popular form plugins.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'custom_tabs',
                'name'     => __( 'Custom Tabs for WooCommerce', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-custom-tabs-for-woocommerce',
                'file'     => 'kitgenix-custom-tabs-for-woocommerce/kitgenix-custom-tabs-for-woocommerce.php',
                'page'     => 'kitgenix-custom-tabs-for-woocommerce',
                'requires' => __( 'Add custom WooCommerce product tabs with per-product content, global tabs, and lightweight controls.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'documents',
                'name'     => __( 'Document Manager', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-document-manager',
                'file'     => 'kitgenix-document-manager/kitgenix-document-manager.php',
                'page'     => 'kitgenix-document-manager',
                'requires' => __( 'Manage document downloads with stable links, version history, and private file access.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'tracking',
                'name'     => __( 'Order Tracking for WooCommerce', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-order-tracking-for-woocommerce',
                'file'     => 'kitgenix-order-tracking-for-woocommerce/kitgenix-order-tracking-for-woocommerce.php',
                'page'     => 'kitgenix-order-tracking-for-woocommerce-analytics',
                'requires' => __( 'Add WooCommerce order tracking, multi-shipment support, email tracking links, and a public customer tracking page.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'pdf',
                'name'     => __( 'PDF Invoicing for WooCommerce', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-pdf-invoicing-for-woocommerce',
                'file'     => 'kitgenix-pdf-invoicing-for-woocommerce/kitgenix-pdf-invoicing-for-woocommerce.php',
                'page'     => 'kitgenix-pdf-invoicing-settings',
                'requires' => __( 'Generate WooCommerce PDF invoices, receipts, packing slips, and credit notes with secure downloads and configurable email attachments.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'stock',
                'name'     => __( 'Stock Sync for WooCommerce', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-stock-sync-for-woocommerce',
                'file'     => 'kitgenix-stock-sync-for-woocommerce/kitgenix-stock-sync-for-woocommerce.php',
                'page'     => 'kitgenix-stock-sync-for-woocommerce',
                'requires' => __( 'Sync WooCommerce stock between stores with secure master-child inventory updates and signed REST requests.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'multistore',
                'name'     => __( 'MultiStore for WooCommerce', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-multistore-sync-for-woocommerce',
                'file'     => 'kitgenix-multistore-sync-for-woocommerce/kitgenix-multistore-sync-for-woocommerce.php',
                'page'     => 'kitgenix-multistore-sync-for-woocommerce',
                'requires' => __( 'Sync WooCommerce products, prices, media, and metadata between multiple stores with a secure master-child architecture.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'image_optimizer',
                'name'     => __( 'Image Optimizer', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-image-optimizer',
                'file'     => 'kitgenix-image-optimizer/kitgenix-image-optimizer.php',
                'page'     => 'kitgenix-image-optimizer',
                'requires' => __( 'Optimize, compress, and resize images in your WordPress media library with automatic on-upload processing and bulk optimization tools.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
            [
                'id'       => 'affiliate',
                'name'     => __( 'Affiliate Link Manager', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                'slug'     => 'kitgenix-affiliate-link-manager',
                'file'     => 'kitgenix-affiliate-link-manager/kitgenix-affiliate-link-manager.php',
                'page'     => 'kitgenix-affiliate-link-manager',
                'requires' => __( 'Manage affiliate short links, branded redirects, and click tracking from one WordPress dashboard.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ],
        ];

        $slugs = [];
        foreach ( $plugins as $plugin ) {
            if ( ! empty( $plugin['slug'] ) ) {
                $slugs[] = (string) $plugin['slug'];
            }
        }
        $wporg_active_installs = kitgenix_hub_get_wporg_active_installs( $slugs );
        $wporg_ratings        = kitgenix_hub_get_wporg_ratings( $slugs );
        $wporg_media          = kitgenix_hub_get_wporg_media( $slugs );
        $logo_url             = plugins_url( 'assets/images/logos/kitgenix-primary-favicon.svg', __FILE__ );

        echo '<div class="wrap kitgenix-admin-app plugin-install-php">'
            . '<div class="kitgenix-hub-header">'
            . '<div class="kitgenix-hub-brand">'
            . '<span class="kitgenix-topbar-brand">'
            . '<img class="kitgenix-hub-logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '" width="30" height="30" />'
            . '</span>'
            . '<span class="kitgenix-topbar-divider" aria-hidden="true"></span>'
            . '<div class="kitgenix-hub-brand-copy">'
            . '<h1 class="kitgenix-hub-title">' . esc_html__( 'Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</h1>'
            . '<p class="kitgenix-hub-description">' . esc_html__( 'Install, activate, open, and review Kitgenix plugins.', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</p>'
            . '</div>'
            . '</div>'
            . '<div class="kitgenix-hub-social-links">'
            . '<a href="https://kitgenix.com" target="_blank" rel="noopener noreferrer" aria-label="Website" title="Website"><img src="' . esc_url( plugins_url( 'assets/images/social-media/globe-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Website</span></a>'
            . '<a href="https://www.facebook.com/groups/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook Community" title="Facebook Community"><img src="' . esc_url( plugins_url( 'assets/images/social-media/facebook-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Facebook Community</span></a>'
            . '<a href="https://www.facebook.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><img src="' . esc_url( plugins_url( 'assets/images/social-media/facebook-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Facebook</span></a>'
            . '<a href="https://www.instagram.com/kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><img src="' . esc_url( plugins_url( 'assets/images/social-media/instagram-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Instagram</span></a>'
            . '<a href="https://www.youtube.com/@Kitgenix" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><img src="' . esc_url( plugins_url( 'assets/images/social-media/youtube-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">YouTube</span></a>'
            . '<a href="https://www.reddit.com/r/Kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Reddit" title="Reddit"><img src="' . esc_url( plugins_url( 'assets/images/social-media/reddit-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Reddit</span></a>'
            . '<a href="https://www.linkedin.com/company/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><img src="' . esc_url( plugins_url( 'assets/images/social-media/linkedin-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">LinkedIn</span></a>'
            . '<a href="https://x.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="X" title="X"><img src="' . esc_url( plugins_url( 'assets/images/social-media/x-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">X</span></a>'
            . '<a href="https://www.tiktok.com/@kitgenix" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok"><img src="' . esc_url( plugins_url( 'assets/images/social-media/tiktok-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">TikTok</span></a>'
            . '<a href="https://github.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub"><img src="' . esc_url( plugins_url( 'assets/images/social-media/github-solid.svg', __FILE__ ) ) . '" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">GitHub</span></a>'
            . '</div>'
            . '</div>'
            . '<div class="kitgenix-hub-wrap">'
            . '<div class="kitgenix-hub">'
            . '<div class="kitgenix-hub-grid">';
        foreach ( $plugins as $p ) {
            $id = (string) $p['id'];
            $file = (string) $p['file'];
            $installed = isset( $plugins_data[ $file ] );
            $active = false;
            if ( $installed && function_exists( 'is_plugin_active' ) ) {
                $active = is_plugin_active( $file ) || ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file ) );
            }

            $version_badge = '';
            if ( $installed && ! empty( $plugins_data[ $file ]['Version'] ) ) {
                $version_badge = '<span class="kitgenix-badge muted">v' . esc_html( (string) $plugins_data[ $file ]['Version'] ) . '</span>';
            }

            $installs_badge = '';
            $slug = ! empty( $p['slug'] ) ? (string) $p['slug'] : '';
            if ( $slug && ! empty( $wporg_active_installs[ $slug ] ) ) {
                $count = (int) $wporg_active_installs[ $slug ];
                $count_text = function_exists( 'number_format_i18n' ) ? number_format_i18n( $count ) : (string) $count;
                /* translators: %s is the number of active installs and may include a thousands separator, e.g. "1,234". The "+" suffix is literal. */
                $installs_badge = '<span class="kitgenix-badge muted">' . esc_html( sprintf( __( '%s+ installs', 'kitgenix-captcha-for-cloudflare-turnstile' ), $count_text ) ) . '</span>';
            }

            $rating_badge = '';
            if ( $slug && isset( $wporg_ratings[ $slug ] ) && (int) $wporg_ratings[ $slug ] > 0 ) {
                $rating_percent = (int) $wporg_ratings[ $slug ];
                $stars = ( $rating_percent / 100 ) * 5;
                $stars_text = function_exists( 'number_format_i18n' ) ? number_format_i18n( $stars, 1 ) : number_format( $stars, 1 );
                /* translators: %s is the average rating out of 5 with one decimal place, e.g. "4.5". The star symbol (★) precedes the number. */
                $rating_badge = '<span class="kitgenix-badge muted">' . esc_html( sprintf( __( '★ %s/5', 'kitgenix-captcha-for-cloudflare-turnstile' ), $stars_text ) ) . '</span>';
            }

            $status_badge = '';
            if ( ! $installed ) {
                $status_badge = '<span class="kitgenix-badge muted">' . esc_html__( 'Not installed', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</span>';
            } elseif ( $active ) {
                $status_badge = '<span class="kitgenix-badge ok">' . esc_html__( 'Active', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</span>';
            } else {
                $status_badge = '<span class="kitgenix-badge warn">' . esc_html__( 'Installed (Inactive)', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</span>';
            }

            $card_media = '';
            if ( $slug && ! empty( $wporg_media[ $slug ]['url'] ) ) {
                $media_type = ( ! empty( $wporg_media[ $slug ]['type'] ) && 'banner' === (string) $wporg_media[ $slug ]['type'] ) ? 'banner' : 'icon';
                $card_media = '<div class="kitgenix-card-media kitgenix-card-media-' . esc_attr( $media_type ) . '"><img class="kitgenix-card-media-image" src="' . esc_url( (string) $wporg_media[ $slug ]['url'] ) . '" alt="" loading="lazy" /></div>';
            }

            $actions = '';
            if ( ! $installed ) {
                if ( current_user_can( 'install_plugins' ) ) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wp_nonce_url() generates a nonce-protected URL
                    $install_url = wp_nonce_url(
                        admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( (string) $p['slug'] ) ),
                        'install-plugin_' . (string) $p['slug']
                    );
                } else {
                    $install_url = admin_url( 'plugin-install.php?s=' . rawurlencode( 'kitgenix' ) . '&tab=search&type=term' );
                }
                $actions .= '<a class="button button-primary" href="' . esc_url( $install_url ) . '">' . esc_html__( 'Install', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
            } elseif ( ! $active ) {
                if ( current_user_can( 'activate_plugins' ) ) {
                    $activate_url = wp_nonce_url(
                        admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) . '&plugin_status=all&paged=1&s=' ),
                        'activate-plugin_' . $file
                    );
                    $actions .= '<a class="button button-primary" href="' . esc_url( $activate_url ) . '">' . esc_html__( 'Activate', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
                } else {
                    $actions .= '<span class="description">' . esc_html__( 'You do not have permission to activate plugins.', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</span>';
                }
            } else {
                $open_url = ! empty( $p['page'] ) ? admin_url( 'admin.php?page=' . rawurlencode( (string) $p['page'] ) ) : '';
                if ( $open_url ) {
                    $actions .= '<a class="button button-primary" href="' . esc_url( $open_url ) . '">' . esc_html__( 'Open', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
                }
            }

            $info_url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( (string) $p['slug'] ) . '&TB_iframe=true&width=600&height=550' );
            $actions .= ' <a class="button button-secondary thickbox open-plugin-details-modal" href="' . esc_url( $info_url ) . '">' . esc_html__( 'Details', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
            if ( $slug ) {
                $review_url  = 'https://wordpress.org/support/plugin/' . rawurlencode( $slug ) . '/reviews/#new-post';
                $support_url = 'https://wordpress.org/support/plugin/' . rawurlencode( $slug ) . '/';
                $actions    .= ' <a class="button button-secondary" href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Review', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
                $actions    .= ' <a class="button button-secondary" href="' . esc_url( $support_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support Forum', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
            }

            $kitgenix_hub_allowed_html = [
                'a'    => [
                    'class' => true,
                    'href'  => true,
                    'target' => true,
                    'rel' => true,
                ],
                'span' => [
                    'class' => true,
                ],
            ];

            $card_media_allowed_html = [
                'div' => [ 'class' => true ],
                'img' => [ 'class' => true, 'src' => true, 'alt' => true, 'loading' => true ],
            ];

            echo '<div class="kitgenix-card" data-kitgenix-plugin="' . esc_attr( sanitize_key( $id ) ) . '">'  
                . wp_kses( $card_media, $card_media_allowed_html )
                . '<div class="kitgenix-card-body">'
                . '<div class="kitgenix-card-badges">' . wp_kses( trim( $status_badge . ' ' . $version_badge . ' ' . $rating_badge . ' ' . $installs_badge ), $kitgenix_hub_allowed_html ) . '</div>'
                . '<p class="kitgenix-card-title">' . esc_html( (string) $p['name'] ) . '</p>'
                . '<p class="kitgenix-card-desc">' . esc_html( (string) $p['requires'] ) . '</p>'
                . '</div>'
                . '<div class="kitgenix-card-actions">' . wp_kses( $actions, $kitgenix_hub_allowed_html ) . '</div>'
                . '</div>';
        }

        echo '</div></div></div></div>';
    }
}

if ( ! function_exists( 'kitgenix_turnstile_register_admin_ui_style' ) ) {
    function kitgenix_turnstile_register_admin_ui_style(): void {
        if ( ! is_admin() ) {
            return;
        }

        if ( function_exists( 'wp_style_is' ) && wp_style_is( 'kitgenix-captcha-for-cloudflare-turnstile-admin-ui', 'registered' ) ) {
            return;
        }

        $ver      = defined( 'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_VERSION' ) ? (string) KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_VERSION : '2.0.0';
        $css_file = plugin_dir_path( __FILE__ ) . 'assets/css/kitgenix-admin-ui.css';
        $css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : $ver;

        wp_register_style( 'kitgenix-captcha-for-cloudflare-turnstile-admin-ui', plugins_url( 'assets/css/kitgenix-admin-ui.css', __FILE__ ), [], $css_ver );
    }
}
add_action( 'admin_enqueue_scripts', 'kitgenix_turnstile_register_admin_ui_style', 5 );

/**
 * Enqueue Kitgenix hub styles on the top-level Kitgenix page.
 */
function kitgenix_turnstile_enqueue_hub_assets( string $hook_suffix ): void {
    // Prefer checking the `page` query arg so assets load reliably across installs.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( 'kitgenix' !== $page && 'toplevel_page_kitgenix' !== $hook_suffix ) {
        return;
    }

    add_thickbox();
    wp_enqueue_style( 'plugin-install' );

    if ( function_exists( 'wp_style_is' ) && ( wp_style_is( 'kitgenix-hub', 'enqueued' ) || wp_style_is( 'kitgenix-hub', 'registered' ) ) ) {
        return;
    }

    $ver = defined( 'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_VERSION' ) ? (string) KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_VERSION : '2.0.0';
    wp_register_style( 'kitgenix-hub', plugins_url( 'assets/css/kitgenix-hub.css', __FILE__ ), [], $ver );
    wp_enqueue_style( 'kitgenix-hub' );

    wp_register_style( 'kitgenix-captcha-for-cloudflare-turnstile-admin-ui', plugins_url( 'assets/css/kitgenix-admin-ui.css', __FILE__ ), [], $ver );
    wp_enqueue_style( 'kitgenix-captcha-for-cloudflare-turnstile-admin-ui' );
}
add_action( 'admin_enqueue_scripts', 'kitgenix_turnstile_enqueue_hub_assets' );

/**
 * Constants (guarded)
 */
if ( ! defined('KitgenixCaptchaForCloudflareTurnstile_Version') ) {
    define('KitgenixCaptchaForCloudflareTurnstile_Version', '2.0.0');
}

// Also expose a conventional uppercase version constant for shared Kitgenix hub assets.
if ( ! defined( 'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_VERSION' ) ) {
    define( 'KITGENIX_CAPTCHA_FOR_CLOUDFLARE_TURNSTILE_VERSION', KitgenixCaptchaForCloudflareTurnstile_Version );
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstile_File') ) {
    define('KitgenixCaptchaForCloudflareTurnstile_File', __FILE__);
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstile_Path') ) {
    define('KitgenixCaptchaForCloudflareTurnstile_Path', plugin_dir_path(__FILE__));
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstile_URL') ) {
    define('KitgenixCaptchaForCloudflareTurnstile_URL', plugin_dir_url(__FILE__));
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstile_Includes_Path') ) {
    define('KitgenixCaptchaForCloudflareTurnstile_Includes_Path', KitgenixCaptchaForCloudflareTurnstile_Path . 'includes/');
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstile_Assets_URL') ) {
    define('KitgenixCaptchaForCloudflareTurnstile_Assets_URL', KitgenixCaptchaForCloudflareTurnstile_URL . 'assets/');
}

/**
 * Declare WooCommerce High-Performance Order Storage (HPOS / custom_order_tables)
 * compatibility. The only place this plugin touches order data is
 * WooCommerce::annotate_blocks_order(), which reads/writes exclusively through WC_Order's
 * own CRUD API (update_meta_data()/save()) – never direct $wpdb/postmeta queries – so it
 * works unmodified whether orders are stored in wp_posts or HPOS's custom tables.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

/**
 * Requires
 */
require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-turnstile-loader.php';
require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-setup-verification.php';
require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-settings-overrides.php';
require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'admin/class-admin-options.php';
require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'admin/class-settings-transfer.php';
require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'admin/class-settings-ui.php';

/**
 * Admin boot (menus/options)
 */
KitgenixCaptchaForCloudflareTurnstile\Core\Setup_Verification::init();
KitgenixCaptchaForCloudflareTurnstile\Core\Settings_Overrides::init();
KitgenixCaptchaForCloudflareTurnstile\Admin\Admin_Options::init();
KitgenixCaptchaForCloudflareTurnstile\Admin\Settings_Transfer::init();
KitgenixCaptchaForCloudflareTurnstile\Admin\Settings_UI::init();

// Translations are loaded automatically by WordPress.org; no manual call required.

/**
 * Initialize Plugin (after all plugins loaded)
 */
add_action('plugins_loaded', 'kitgenix_captcha_for_cloudflare_turnstile_init_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_init_plugin() {
    if ( class_exists('KitgenixCaptchaForCloudflareTurnstile\\Core\\Turnstile_Loader') ) {
        \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Loader::init();
        return;
    }

    // Fail loudly in admin so issues are visible
    if ( is_admin() ) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Kitgenix Turnstile: core loader not found. Please reinstall the plugin.', 'kitgenix-captcha-for-cloudflare-turnstile' )
                . '</p></div>';
        });
    }
}

/**
 * Activation: environment checks + post-activation redirect flag
 */
register_activation_hook(__FILE__, 'kitgenix_captcha_for_cloudflare_turnstile_activate_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_activate_plugin() {
    $min_php = '8.1';
    $min_wp  = '5.0';

    if ( version_compare(PHP_VERSION, $min_php, '<') || version_compare(get_bloginfo('version'), $min_wp, '<') ) {
        deactivate_plugins(plugin_basename(__FILE__));
        $msg = sprintf(
            /* translators: 1: PHP version, 2: WordPress version */
            esc_html__( 'Kitgenix Turnstile requires PHP %1$s+ and WordPress %2$s+.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            esc_html($min_php),
            esc_html($min_wp)
        );
        wp_die(
            esc_html( $msg ),
            esc_html__( 'Plugin Activation Error', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            ['back_link' => true]
        );
    }

    set_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect', 1, 30);
}

/**
 * Perform the activation redirect once
 */
add_action('admin_init', function () {
    if ( ! get_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect') ) {
        return;
    }
    delete_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect');

    // If bulk-activated, don't redirect.
    if ( isset($_GET['activate-multi']) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $slug   = 'kitgenix-captcha-for-cloudflare-turnstile';
    $target = admin_url('admin.php?page=' . $slug);

    wp_safe_redirect( esc_url_raw( $target ) );
    exit;
});

/**
 * Deactivation
 */
register_deactivation_hook(__FILE__, 'kitgenix_captcha_for_cloudflare_turnstile_deactivate_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_deactivate_plugin() {
    delete_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect');
}

/**
 * Uninstall
 *
 * Note: If uninstall.php exists, WordPress will run it INSTEAD of this hook.
 */
register_uninstall_hook(__FILE__, 'kitgenix_captcha_for_cloudflare_turnstile_uninstall_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_uninstall_plugin() {
    $uninstall = KitgenixCaptchaForCloudflareTurnstile_Path . 'uninstall.php';
    if ( file_exists($uninstall) ) {
        include $uninstall; // uninstall.php should check defined('WP_UNINSTALL_PLUGIN')
    }
}

/**
 * “Settings” link on the Plugins screen
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $slug = 'kitgenix-captcha-for-cloudflare-turnstile';
    $url  = admin_url('admin.php?page=' . $slug);
    $links[] = '<a href="' . esc_url($url) . '">' . esc_html__( 'Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
    return $links;
});
