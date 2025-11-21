<?php
/**
 * Admin Settings UI
 *
 * @package KitgenixCaptchaForCloudflareTurnstile
 */

namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

use function add_action;
use function add_options_page;
use function current_user_can;
use function apply_filters;
use function get_option;
use function settings_fields;
use function wp_nonce_field;
use function checked;
use function selected;
use function esc_attr;
use function esc_html__;
use function esc_textarea;
use function submit_button;
use function in_array;
use function defined;
use function __;
use function esc_url;

defined( 'ABSPATH' ) || exit;

class Settings_UI {

    /**
     * The page hook suffix returned by add_options_page().
     *
     * @var string|null
     */
    private static $page_hook = null;

    /**
     * Initialize admin menu and page rendering.
     */
    public static function init(): void {
        \add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        \add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    /**
     * Register the plugin settings page.
     */
    public static function register_menu(): void {
        self::$page_hook = \add_options_page(
            \__( 'Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            \__( 'Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'manage_options',
            'kitgenix-captcha-for-cloudflare-turnstile',
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Enqueue scripts/styles only on our settings page.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public static function enqueue_assets( $hook ): void {
        if ( empty( self::$page_hook ) || $hook !== self::$page_hook ) {
            return;
        }

        $settings = Admin_Options::get_settings();
        $site_key = $settings['site_key'] ?? '';

        // Admin CSS for the UI (enqueue if your plugin registers it elsewhere).

        // Bail if no site key yet — the test widget can't render.
        if ( ! $site_key ) {
            return;
        }

        $ver = defined( 'KitgenixCaptchaForCloudflareTurnstileVERSION' )
            ? \constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' )
            : null;

        // Tiny shim handle just to attach our onload callback before the API.
        \wp_register_script(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            false,
            [],
            $ver,
            true
        );

        $theme      = $settings['theme']        ?? 'auto';
        $size       = $settings['widget_size']  ?? 'normal';
        $appearance = $settings['appearance']   ?? 'always';

        \wp_add_inline_script(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            'window.KitgenixCaptchaForCloudflareTurnstileAdminTurnstileReady = function () {' .
                'try {' .
                    'var el = document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-widget");' .
                    'if (!el || typeof turnstile === "undefined" || el.dataset.rendered) { return; }' .
                    'turnstile.render(el, {' .
                        'sitekey: ' . \wp_json_encode( $site_key ) . ',' .
                        'theme: ' . \wp_json_encode( $theme ) . ',' .
                        'size: ' . \wp_json_encode( $size ) . ',' .
                        'appearance: ' . \wp_json_encode( $appearance ) . ',' .
                        'callback: function(){' .
                            'var ok = document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-success");' .
                            'if (ok) { ok.style.display = "block"; ok.setAttribute("aria-hidden","false"); }' .
                        '}' .
                    '});' .
                    'el.dataset.rendered = "true";' .
                '} catch (e) { if (window.console) console.error(e); }' .
            '};',
            'before'
        );
        \wp_enqueue_script( 'kitgenix-captcha-for-cloudflare-turnstile-admin' );

        // Load Turnstile API with onload pointing at our callback.
        $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=KitgenixCaptchaForCloudflareTurnstileAdminTurnstileReady';
        if ( ! empty( $settings['language'] ) && 'auto' !== $settings['language'] ) {
            $url .= '&hl=' . rawurlencode( (string) $settings['language'] );
        }

        \wp_enqueue_script(
            'kitgenix-captcha-for-cloudflare-turnstile-admin-api',
            $url,
            [],
            $ver,
            true
        );

        // Hint to load non-blocking on newer WP (falls back gracefully).
        if ( function_exists( '\wp_script_add_data' ) ) {
            \wp_script_add_data( 'kitgenix-captcha-for-cloudflare-turnstile-admin-api', 'strategy', 'defer' );
        }
    }

    /**
     * Render the settings page.
     */
    public static function render_page(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = Admin_Options::get_settings();
        $ver = defined( 'KitgenixCaptchaForCloudflareTurnstileVERSION' ) ? \constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' ) : '';

        // Active plugins (single site) — include plugin.php for is_plugin_active support if needed.
        if ( ! function_exists( '\is_plugin_active' ) ) {
            @include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $active_plugins = (array) \get_option( 'active_plugins', [] );

        // Admin notices area. Intentionally firing core admin notices hook to render any queued notices.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- calling core hook intentionally
        do_action( 'admin_notices' );

        // Developer mode warning (global top).
        if ( ! empty( $settings['dev_mode_warn_only'] ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
                \esc_html__( 'Developer Mode (warn-only) is enabled.', 'kitgenix-captcha-for-cloudflare-turnstile' ) .
                '</strong> ' .
                \esc_html__( 'Turnstile failures will be logged but will not block form submissions.', 'kitgenix-captcha-for-cloudflare-turnstile' ) .
                '</p></div>';
        }
        ?>
        <div class="wrap" id="kitgenix-captcha-for-cloudflare-turnstile-admin-app">
            <div class="kitgenix-captcha-for-cloudflare-turnstile-settings-intro kitgenix-settings-header">
                <h1 class="kitgenix-captcha-for-cloudflare-turnstile-admin-title"><?php echo \esc_html( \__( 'Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h1>
                <p><?php echo \esc_html__( 'Seamlessly integrate Cloudflare’s free Turnstile CAPTCHA into your WordPress forms to enhance security and reduce spam – without compromising user experience.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-intro-links">
                    <a href="<?php echo \esc_url( 'https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'View Plugin Documentation', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                    <a href="<?php echo \esc_url( 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/reviews/#new-post' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Consider Leaving Us a Review', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                    <a href="<?php echo \esc_url( 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Get Support', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                    <a href="<?php echo \esc_url( 'https://buymeacoffee.com/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer">☕ <?php echo \esc_html( \__( 'Buy us a coffee', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                </div>
                <div class="kitgenix-settings-meta">
                    <span class="kitgenix-settings-version" aria-label="Plugin version">v<?php echo esc_html( $ver ); ?></span>
                </div>
            </div>
            <div class="kitgenix-settings-layout">
                <aside class="kitgenix-settings-sidebar" aria-label="Settings navigation">
                
                    <nav class="kitgenix-settings-nav" id="kitgenix-settings-nav">
                        <ul>
                            <li><a class="kitgenix-nav-link" href="#section-site-keys"><?php echo \esc_html__( 'Site Keys', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-shortcode"><?php echo \esc_html__( 'Shortcode', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-display"><?php echo \esc_html__( 'Display', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-developer"><?php echo \esc_html__( 'Developer', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-security"><?php echo \esc_html__( 'Security', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-whitelist"><?php echo \esc_html__( 'Whitelist', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-proxy"><?php echo \esc_html__( 'Proxy / Cloudflare', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-integrations-wp"><?php echo esc_html__( 'WordPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-integrations-wc"><?php echo esc_html__( 'WooCommerce', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-integrations-elementor"><?php echo esc_html__( 'Elementor', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-integrations-forms"><?php echo esc_html__( 'Form Plugins', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-integrations-bbpress"><?php echo esc_html__( 'bbPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-integrations-buddypress"><?php echo esc_html__( 'BuddyPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-import-export"><?php echo esc_html__( 'Import / Export', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                            <li><a class="kitgenix-nav-link" href="#section-support"><?php echo esc_html__( 'Support', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a></li>
                        </ul>
                    </nav>
                </aside>
                <div class="kitgenix-settings-content" id="kitgenix-settings-content" tabindex="-1">
            <?php
            // Quick status overview variables.
            $site_key_val       = $settings['site_key'] ?? '';
            $site_key_display   = $site_key_val ? substr( (string) $site_key_val, 0, 12 ) . ( strlen( (string) $site_key_val ) > 12 ? '…' : '' ) : \__( 'Missing', 'kitgenix-captcha-for-cloudflare-turnstile' );
            $secret_present     = ! empty( $settings['secret_key'] );
            $replay_status      = ! empty( $settings['replay_protection'] ) ? \__( 'On', 'kitgenix-captcha-for-cloudflare-turnstile' ) : \__( 'Off', 'kitgenix-captcha-for-cloudflare-turnstile' );
            $dev_mode_status    = ! empty( $settings['dev_mode_warn_only'] ) ? \__( 'Enabled', 'kitgenix-captcha-for-cloudflare-turnstile' ) : \__( 'Disabled', 'kitgenix-captcha-for-cloudflare-turnstile' );

            // Determine available integrations based on environment.
            $available_integrations = 1; // WordPress core forms always available.
            $available_integrations += ( ( function_exists( '\is_plugin_active' ) && \is_plugin_active( 'woocommerce/woocommerce.php' ) ) || in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) ? 1 : 0;
            $available_integrations += defined( 'ELEMENTOR_VERSION' ) ? 1 : 0;
            $available_integrations += class_exists( 'WPForms' ) ? 1 : 0;
            $available_integrations += ( defined( 'FLUENTFORM' ) || class_exists( 'FluentForm' ) ) ? 1 : 0;
            $available_integrations += class_exists( 'GFForms' ) ? 1 : 0;
            $available_integrations += ( in_array( 'contact-form-7/wp-contact-form-7.php', $active_plugins, true ) || defined( 'WPCF7_VERSION' ) ) ? 1 : 0;
            $available_integrations += class_exists( 'FrmForm' ) ? 1 : 0;
            $available_integrations += function_exists( 'forminator' ) ? 1 : 0;
            $available_integrations += class_exists( 'Jetpack' ) ? 1 : 0;
            $available_integrations += class_exists( 'Kadence_Blocks_Form' ) ? 1 : 0;

            // Count enabled integrations.
            $enabled_integrations = 0;
            $enabled_integrations += ! empty( $settings['enable_wordpress'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_woocommerce'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_elementor'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_wpforms'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_fluentforms'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_gravityforms'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_cf7'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_formidableforms'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_forminator'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_jetpackforms'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_kadenceforms'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_bbpress'] ) ? 1 : 0;
            $enabled_integrations += ! empty( $settings['enable_buddypress'] ) ? 1 : 0;
            ?>
            
            
            <form method="post" action="options.php" autocomplete="off" novalidate>
                <?php \settings_fields( 'kitgenix_captcha_for_cloudflare_turnstile_settings_group' ); ?>
                <?php \wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_settings_save', 'kitgenix_captcha_for_cloudflare_turnstile_settings_nonce' ); ?>

                <!-- Site Keys -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-site-keys" data-section>
                    <h2><?php echo \esc_html__( 'Cloudflare Turnstile Site Key & Secret Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description">
                            <?php echo \esc_html__( 'You can obtain your Site Key and Secret Key by visiting:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?><br>
                            <a href="<?php echo \esc_url( 'https://dash.cloudflare.com/?to=/:account/turnstile' ); ?>" target="_blank" rel="noopener noreferrer">https://dash.cloudflare.com/?to=/:account/turnstile</a>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th><label for="site_key"><?php echo \esc_html__( 'Site Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="text" id="site_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[site_key]" value="<?php echo \esc_attr( $settings['site_key'] ?? '' ); ?>" class="regular-text" required autocomplete="off" /></td>
                            </tr>
                            <tr>
                                <th><label for="secret_key"><?php echo \esc_html__( 'Secret Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <div class="kitgenix-secret-key-wrapper">
                                        <input type="password" id="secret_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key]" value="" class="regular-text" autocomplete="off" aria-describedby="secret-key-help" />
                                        <?php if ( $secret_present ) : ?>
                                            <input type="hidden" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key_present]" value="1" />
                                            <label style="display:inline-block;margin-left:12px;">
                                                <input type="checkbox" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key_clear]" value="1" />
                                                <?php echo \esc_html__( 'Clear saved secret', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                            </label>
                                        <?php endif; ?>
                                        <button type="button"
                                            class="button button-secondary kitgenix-reveal-secret"
                                            data-target="secret_key"
                                            data-label-show="<?php echo \esc_attr__( 'Reveal secret key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                            data-label-hide="<?php echo \esc_attr__( 'Hide secret key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                            data-text-show="<?php echo \esc_attr__( 'Show', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                            data-text-hide="<?php echo \esc_attr__( 'Hide', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                            aria-pressed="false"
                                            aria-label="<?php echo \esc_attr__( 'Reveal secret key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>">
                                            <span class="kitgenix-reveal-secret-text" aria-hidden="true"><?php echo \esc_html__( 'Show', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                        </button>
                                        <button type="button"
                                            class="button button-secondary kitgenix-copy-secret"
                                            data-target="secret_key"
                                            aria-label="<?php echo \esc_attr__( 'Copy secret key to clipboard', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>">
                                            <span aria-hidden="true">Copy</span>
                                        </button>
                                    </div>
                                    <p id="secret-key-help" class="description"><?php echo \esc_html__( 'Your Secret Key is sensitive. For safety this screen does not expose the stored secret. Enter a new secret to replace the stored value, or check "Clear saved secret" to remove it.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <!-- Test widget -->
                        <table class="form-table">
                            <tr class="kitgenix-has-turnstile-test">
                                <th scope="row"><label><?php echo \esc_html__( 'Test Cloudflare Turnstile Response', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <div id="kitgenix-captcha-for-cloudflare-turnstile-test-widget" aria-label="<?php echo \esc_attr__( 'Turnstile test widget', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"></div>
                                    <p id="kitgenix-captcha-for-cloudflare-turnstile-test-success" aria-hidden="true"><?php echo \esc_html__( 'Verification successful.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                    <?php if ( empty( $settings['site_key'] ) ) : ?>
                                        <div class="kitgenix-captcha-for-cloudflare-turnstile-warning description"><?php echo \esc_html__( 'Enter your Site Key above to test Turnstile.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php
                // Only show global Shortcode / Manual Placement guidance when a forms
                // integration is present (Contact Form 7, WPForms, FluentForms, Gravity, Forminator, Formidable, Jetpack, Kadence, Elementor).
                $is_cf7       = in_array( 'contact-form-7/wp-contact-form-7.php', $active_plugins, true ) || defined( 'WPCF7_VERSION' );
                $is_wpforms   = class_exists( 'WPForms' );
                $is_fluent    = defined( 'FLUENTFORM' ) || class_exists( 'FluentForm' );
                $is_gravity   = class_exists( 'GFForms' );
                $is_formidable= class_exists( 'FrmForm' );
                $is_forminator= function_exists( 'forminator' );
                $is_jetpack   = class_exists( 'Jetpack' );
                $is_kadence   = class_exists( 'Kadence_Blocks_Form' );
                $is_elementor = defined( 'ELEMENTOR_VERSION' );
                $show_shortcode_card = ( $is_cf7 || $is_wpforms || $is_fluent || $is_gravity || $is_formidable || $is_forminator || $is_jetpack || $is_kadence || $is_elementor );
                if ( $show_shortcode_card ) :
                ?>
                <!-- Shortcode / Manual placement info -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-shortcode" data-section>
                    <h2><?php echo \esc_html__( 'Shortcode & Manual Placement', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description">
                            <?php echo \esc_html__( 'You can manually place the Turnstile widget in custom HTML fields or form content using the shortcode below. When a shortcode or existing widget container is detected inside a form, the plugin will skip automatic injection to avoid duplicate widgets.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th><label><?php echo \esc_html__( 'Shortcode', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <code>[kitgenix_turnstile]</code>
                                    <p class="description"><?php echo \esc_html__( 'Place this shortcode in a custom HTML field (where the form plugin supports HTML/shortcodes) to render the widget exactly where you want it.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                    <?php endif; ?>

                    <!-- Display Settings -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-display" data-section>
                    <h2><?php echo \esc_html__( 'Display Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label><?php echo \esc_html__( 'Theme', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]" value="auto" <?php checked( $settings['theme'] ?? 'auto', 'auto' ); ?> /> <?php echo \esc_html__( 'Auto', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]" value="light" <?php checked( $settings['theme'] ?? '', 'light' ); ?> /> <?php echo \esc_html__( 'Light', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]" value="dark" <?php checked( $settings['theme'] ?? '', 'dark' ); ?> /> <?php echo \esc_html__( 'Dark', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </fieldset>
                                    <p class="description"><?php echo \esc_html__( 'Select the visual style for the widget.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo \esc_html__( 'Widget Size', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label style="margin-right:10px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="normal" <?php checked( $settings['widget_size'] ?? 'normal', 'normal' ); ?> /> <?php echo \esc_html__( 'Normal', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label style="margin-right:10px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="small" <?php checked( $settings['widget_size'] ?? '', 'small' ); ?> /> <?php echo \esc_html__( 'Small', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label style="margin-right:10px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="medium" <?php checked( $settings['widget_size'] ?? '', 'medium' ); ?> /> <?php echo \esc_html__( 'Medium', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label style="margin-right:10px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="large" <?php checked( $settings['widget_size'] ?? '', 'large' ); ?> /> <?php echo \esc_html__( 'Large', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="flexible" <?php checked( $settings['widget_size'] ?? '', 'flexible' ); ?> /> <?php echo \esc_html__( 'Flexible (100% width)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </fieldset>
                                    <p class="description"><?php echo \esc_html__( 'Pick a size that fits your layout. "Flexible" makes the iframe scale to 100% of its container (Cloudflare Turnstile data-size=flexible).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo \esc_html__( 'Appearance Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[appearance]" value="always" <?php checked( $settings['appearance'] ?? 'always', 'always' ); ?> /> <?php echo \esc_html__( 'Always', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[appearance]" value="interaction-only" <?php checked( $settings['appearance'] ?? '', 'interaction-only' ); ?> /> <?php echo \esc_html__( 'Interaction Only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </fieldset>
                                    <p class="description"><?php echo \esc_html__( 'Control how the widget is displayed.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="language"><?php echo \esc_html__( 'Language', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="text" id="language" name="kitgenix_captcha_for_cloudflare_turnstile_settings[language]" value="<?php echo \esc_attr( $settings['language'] ?? 'auto' ); ?>" class="regular-text" />
                                    <p class="description"><?php echo \esc_html__( 'Enter a language code (e.g. "en", "fr", "zh-CN") or use "auto" to detect. Common codes: en, es, fr, de, it, pt, ru, ja, ko, zh-CN, zh-TW.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="disable_submit"><?php echo \esc_html__( 'Disable Submit Button', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="disable_submit" name="kitgenix_captcha_for_cloudflare_turnstile_settings[disable_submit]" value="1" <?php checked( ! empty( $settings['disable_submit'] ) ); ?> />
                                        <span class="description"><?php echo \esc_html__( 'Keep the submit button inactive until Turnstile is solved.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="error_message"><?php echo \esc_html__( 'Custom Error Message', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="text" id="error_message" name="kitgenix_captcha_for_cloudflare_turnstile_settings[error_message]" value="<?php echo \esc_attr( $settings['error_message'] ?? '' ); ?>" class="regular-text" />
                                    <p class="description"><?php echo \esc_html__( 'Override the default inline error shown to users when verification fails.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="extra_message"><?php echo \esc_html__( 'Extra Failure Message', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="text" id="extra_message" name="kitgenix_captcha_for_cloudflare_turnstile_settings[extra_message]" value="<?php echo \esc_attr( $settings['extra_message'] ?? '' ); ?>" class="regular-text" />
                                    <p class="description"><?php echo \esc_html__( 'Optional extra text appended to error messages (e.g., support instructions).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Developer Mode -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-developer" data-section data-kgx-group="developer">
                    <h2><?php echo \esc_html( \__( 'Developer Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="dev_mode_warn_only"><?php echo \esc_html( \__( 'Development Mode (Warn-only)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="dev_mode_warn_only" name="kitgenix_captcha_for_cloudflare_turnstile_settings[dev_mode_warn_only]" value="1" <?php checked( ! empty( $settings['dev_mode_warn_only'] ) ); ?> />
                                        <span class="description">
                                            <?php echo \esc_html( \__( 'Do not block submissions if Turnstile fails. Instead, log the failure and show an inline warning (admins only). Ideal for staging.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                        </span>
                                    </label>
                                    <?php if ( ! empty( $settings['dev_mode_warn_only'] ) ) : ?>
                                        <div class="notice notice-warning" style="margin-top:10px;">
                                            <p><strong><?php echo \esc_html__( 'Developer Mode is active', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong> — <?php echo \esc_html__( 'Turnstile failures will not block submissions until you disable this option.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Security -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-security" data-section data-kgx-group="security">
                    <h2><?php echo \esc_html( \__( 'Security', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="replay_protection"><?php echo \esc_html( \__( 'Enable Replay Protection', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="replay_protection" name="kitgenix_captcha_for_cloudflare_turnstile_settings[replay_protection]" value="1" <?php checked( ! empty( $settings['replay_protection'] ) ); ?> />
                                        <span class="description">
                                            <?php echo \esc_html( \__( 'Rejects reused Turnstile tokens for a short period (default 10 minutes). Prevents replays and accidental double-submits.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Whitelist -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-whitelist" data-section data-kgx-group="whitelist">
                    <h2><?php echo \esc_html__( 'Whitelist Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="whitelist_loggedin"><?php echo \esc_html__( 'Skip for Logged-in Users', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="whitelist_loggedin" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_loggedin]" value="1" <?php checked( ! empty( $settings['whitelist_loggedin'] ) ); ?> />
                                        <span class="description"><?php echo \esc_html__( 'Useful for membership sites or intranets. Applies to all integrations.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whitelist_ips"><?php echo \esc_html__( 'IP Address Whitelist', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <textarea id="whitelist_ips" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_ips]" rows="2" class="large-text code"><?php echo \esc_textarea( $settings['whitelist_ips'] ?? '' ); ?></textarea><br />
                                        <span class="description"><?php echo \esc_html__( 'One per line. Supports exact IPs, wildcards (e.g. 203.0.113.*) and CIDR (e.g. 203.0.113.0/24, 2001:db8::/32).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                        <div style="margin-top:6px;display:flex;gap:8px;align-items:center;">
                                            <button type="button" id="kgx-whitelist-ips-preview-btn" class="button"><?php echo \esc_html__( 'Preview', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></button>
                                            <small class="description"><?php echo \esc_html__( 'Preview how whitelist lines will be parsed (client-side).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></small>
                                        </div>
                                        <pre id="kgx-whitelist-ips-preview" class="kgx-whitelist-preview code" style="margin-top:8px;display:none;white-space:pre-wrap;padding:8px;border:1px solid #e1e1e1;background:#fafafa;"></pre>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whitelist_user_agents"><?php echo \esc_html__( 'User Agent Whitelist', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                        <textarea id="whitelist_user_agents" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_user_agents]" rows="2" class="large-text code"><?php echo \esc_textarea( $settings['whitelist_user_agents'] ?? '' ); ?></textarea><br />
                                        <span class="description"><?php echo \esc_html__( 'One per line. Supports * wildcards. Use cautiously—UAs can be spoofed.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                        <div style="margin-top:6px;display:flex;gap:8px;align-items:center;">
                                            <button type="button" id="kgx-whitelist-uas-preview-btn" class="button"><?php echo \esc_html__( 'Preview', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></button>
                                            <small class="description"><?php echo \esc_html__( 'Preview parsed UA lines.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></small>
                                        </div>
                                        <pre id="kgx-whitelist-uas-preview" class="kgx-whitelist-preview code" style="margin-top:8px;display:none;white-space:pre-wrap;padding:8px;border:1px solid #e1e1e1;background:#fafafa;"></pre>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Reverse Proxy / Cloudflare -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-proxy" data-section data-kgx-group="proxy">
                    <h2><?php echo \esc_html( \__( 'Reverse Proxy / Cloudflare', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="trust_proxy"><?php echo \esc_html( \__( 'Trust Cloudflare/Proxy Headers', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="trust_proxy" name="kitgenix_captcha_for_cloudflare_turnstile_settings[trust_proxy]" value="1" <?php checked( ! empty( $settings['trust_proxy'] ) ); ?> />
                                        <span class="description">
                                            <?php echo \esc_html( \__( 'When enabled, the plugin will trust CF-Connecting-IP / X-Forwarded-For (etc.) only if the request comes from a trusted proxy below. Otherwise, REMOTE_ADDR is used.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="trusted_proxies"><?php echo \esc_html__( 'Trusted Proxy IPs (one per line)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <textarea id="trusted_proxies" name="kitgenix_captcha_for_cloudflare_turnstile_settings[trusted_proxies]" rows="4" class="large-text code"><?php echo \esc_textarea( $settings['trusted_proxies'] ?? '' ); ?></textarea>
                                    <p class="description">
                                        <?php echo \esc_html__( 'Accepts IPv4/IPv6 or CIDR ranges, e.g. 203.0.113.10 or 2001:db8::/32. Only when REMOTE_ADDR matches one of these will proxy headers be used.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- WordPress Integration -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-wp" data-section>
                    <h2><?php echo \esc_html__( 'WordPress Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Renders Turnstile on core WordPress forms:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                            <strong><?php echo \esc_html__( 'Login, Register, Lost Password, Reset Password, and Comments.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_wordpress"><?php echo \esc_html__( 'Enable for WordPress Core Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="enable_wordpress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wordpress]" value="1" <?php checked( ! empty( $settings['enable_wordpress'] ) ); ?> />
                                        <span class="description"><?php echo \esc_html__( 'Adds a Turnstile widget to the forms listed below and validates on POST only.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        <table class="form-table">
                            <tr>
                                <th><label for="wp_login_form"><?php echo \esc_html__( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_login_form]" value="1" <?php checked( ! empty( $settings['wp_login_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'wp-login.php – below the password field.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wp_register_form"><?php echo \esc_html__( 'Registration Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_register_form]" value="1" <?php checked( ! empty( $settings['wp_register_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'wp-login.php?action=register – above the submit button.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wp_lostpassword_form"><?php echo \esc_html__( 'Password Reset Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_lostpassword_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_lostpassword_form]" value="1" <?php checked( ! empty( $settings['wp_lostpassword_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Lost/Reset password screens – beneath email/new password fields.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wp_comments_form"><?php echo \esc_html__( 'Comments Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_comments_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_comments_form]" value="1" <?php checked( ! empty( $settings['wp_comments_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Below comment fields (for guests and logged-in users).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php $is_buddypress = defined( 'BP_VERSION' ); ?>
                <?php if ( $is_buddypress ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-buddypress" data-section>
                    <h2><?php echo \esc_html__( 'BuddyPress Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Adds Turnstile to BuddyPress registration and validates submissions server-side.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_buddypress"><?php echo \esc_html__( 'Enable for BuddyPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_buddypress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_buddypress]" value="1" <?php checked( ! empty( $settings['enable_buddypress'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php $is_bbpress = in_array( 'bbpress/bbpress.php', $active_plugins, true ) || defined( 'BBPRESS_VERSION' ); ?>
                <?php if ( $is_bbpress ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-bbpress" data-section>
                    <h2><?php echo \esc_html__( 'bbPress Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Adds Turnstile to bbPress topic and reply forms and validates submissions server-side.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_bbpress"><?php echo \esc_html__( 'Enable for bbPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_bbpress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_bbpress]" value="1" <?php checked( ! empty( $settings['enable_bbpress'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>



                <!-- WooCommerce Integration -->
                <?php $is_wc_active = ( function_exists( '\is_plugin_active' ) && \is_plugin_active( 'woocommerce/woocommerce.php' ) ) || in_array( 'woocommerce/woocommerce.php', $active_plugins, true ); ?>
                <?php if ( $is_wc_active ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-wc" data-section>
                    <h2><?php echo \esc_html__( 'WooCommerce Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description">
                            <?php echo \esc_html__( 'Classic Checkout + My Account screens:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                            <strong><?php echo \esc_html__( 'Checkout (Place order area), My Account Login/Registration, Lost/Reset Password.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                            <?php echo ' '; ?>
                            <?php echo \esc_html__( 'Blocks Checkout is also supported via a JS bridge that attaches the token to Store API requests.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_woocommerce"><?php echo \esc_html__( 'Enable for WooCommerce Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_woocommerce" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_woocommerce]" value="1" <?php checked( ! empty( $settings['enable_woocommerce'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <table class="form-table">
                            <tr>
                                <th><label for="wc_checkout_form"><?php echo \esc_html__( 'Checkout Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_checkout_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_checkout_form]" value="1" <?php checked( ! empty( $settings['wc_checkout_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Classic checkout: widget renders before “Place order”. Blocks checkout: container is injected; token is sent via header and extensions.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wc_login_form"><?php echo \esc_html__( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_login_form]" value="1" <?php checked( ! empty( $settings['wc_login_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'My Account → Login.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wc_register_form"><?php echo \esc_html__( 'Registration Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_register_form]" value="1" <?php checked( ! empty( $settings['wc_register_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'My Account → Register.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wc_lostpassword_form"><?php echo \esc_html__( 'Password Reset Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_lostpassword_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_lostpassword_form]" value="1" <?php checked( ! empty( $settings['wc_lostpassword_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'My Account → Lost/Reset password.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                        </table>

                                    <p class="description">
                                        <strong><?php echo esc_html__( 'Injection Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong><br />
                                        <span style="display:block;margin-top:6px;">
                                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce]" value="auto" <?php checked( $settings['mode_woocommerce'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject for Classic My Account & Checkout (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce]" value="shortcode" <?php checked( $settings['mode_woocommerce'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        </span>
                                        <span style="display:block;margin-top:10px;">
                                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce_blocks]" value="auto" <?php checked( $settings['mode_woocommerce_blocks'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject for Blocks Checkout (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce_blocks]" value="shortcode" <?php checked( $settings['mode_woocommerce_blocks'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        </span>
                                        <br />
                                        <small class="description"><?php echo esc_html__( 'When Shortcode only is selected, auto-injection for the chosen WooCommerce area will be disabled. Use ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?><code>[kitgenix_turnstile]</code><?php echo esc_html__( ' in a compatible HTML area to manually place the widget.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></small>
                                    </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Elementor Integration -->
                <?php if ( defined( 'ELEMENTOR_VERSION' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-elementor" data-section>
                    <h2><?php echo \esc_html__( 'Elementor Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Elementor Pro Forms: container renders after fields; server-side validation via Elementor hooks. Elementor (free): auto-injection above the submit button.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_elementor"><?php echo \esc_html__( 'Enable for Elementor Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_elementor" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_elementor]" value="1" <?php checked( ! empty( $settings['enable_elementor'] ) ); ?> /></td>
                            </tr>
                        </table>

                                    <p class="description">
                                        <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_elementor]" value="auto" <?php checked( $settings['mode_elementor'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_elementor]" value="shortcode" <?php checked( $settings['mode_elementor'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <br />
                                        <?php echo esc_html__( 'Use ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?><code>[kitgenix_turnstile]</code><?php echo esc_html__( ' in custom HTML to manually place the widget when Shortcode only is selected.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                    </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- WPForms -->
                <?php if ( class_exists( 'WPForms' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-forms" data-section>
                    <h2><?php echo \esc_html__( 'WPForms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget renders near the submit area; server-side validation uses WPForms process hook (works with AJAX).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_wpforms"><?php echo \esc_html__( 'Enable for WPForms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_wpforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wpforms]" value="1" <?php checked( ! empty( $settings['enable_wpforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_wpforms]" value="auto" <?php checked( $settings['mode_wpforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_wpforms]" value="shortcode" <?php checked( $settings['mode_wpforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — place in a custom HTML field or form content. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Fluent Forms -->
                <?php if ( defined( 'FLUENTFORM' ) || class_exists( 'FluentForm' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section>
                    <h2><?php echo \esc_html__( 'Fluent Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is inserted before the submit button; AJAX-friendly validation via Fluent’s submit filter.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_fluentforms"><?php echo \esc_html__( 'Enable for Fluent Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_fluentforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_fluentforms]" value="1" <?php checked( ! empty( $settings['enable_fluentforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_fluentforms]" value="auto" <?php checked( $settings['mode_fluentforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_fluentforms]" value="shortcode" <?php checked( $settings['mode_fluentforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — add to a custom HTML field or HTML block in your form. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Gravity Forms -->
                <?php if ( class_exists( 'GFForms' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section>
                    <h2><?php echo \esc_html__( 'Gravity Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget renders immediately before the submit button; server-side validation sets the top-level error container.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_gravityforms"><?php echo \esc_html__( 'Enable for Gravity Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_gravityforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_gravityforms]" value="1" <?php checked( ! empty( $settings['enable_gravityforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_gravityforms]" value="auto" <?php checked( $settings['mode_gravityforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_gravityforms]" value="shortcode" <?php checked( $settings['mode_gravityforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — place inside an HTML block or custom HTML field in Gravity Forms (if supported). When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formidable -->
                <?php if ( class_exists( 'FrmForm' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section>
                    <h2><?php echo \esc_html__( 'Formidable Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget renders before the submit button; validation runs during entry validation.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_formidableforms"><?php echo \esc_html__( 'Enable for Formidable Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_formidableforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_formidableforms]" value="1" <?php checked( ! empty( $settings['enable_formidableforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_formidableforms]" value="auto" <?php checked( $settings['mode_formidableforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_formidableforms]" value="shortcode" <?php checked( $settings['mode_formidableforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — insert into a HTML field or custom content area in Formidable Forms. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact Form 7 -->
                <?php if ( in_array( 'contact-form-7/wp-contact-form-7.php', $active_plugins, true ) || defined( 'WPCF7_VERSION' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section>
                    <h2><?php echo \esc_html__( 'Contact Form 7 Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is injected before the first submit control; validation uses the CF7 validation filter (AJAX and non-AJAX).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_cf7"><?php echo \esc_html__( 'Enable for Contact Form 7', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_cf7" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_cf7]" value="1" <?php checked( ! empty( $settings['enable_cf7'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_cf7]" value="auto" <?php checked( $settings['mode_cf7'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_cf7]" value="shortcode" <?php checked( $settings['mode_cf7'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — while CF7 auto-inject remains the default, you can place the shortcode in a HTML field or form content to control widget placement. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Forminator -->
                <?php if ( function_exists( 'forminator' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section>
                    <h2><?php echo \esc_html__( 'Forminator Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is added alongside the submit markup; validation uses Forminator’s submit errors filter (AJAX-safe).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_forminator"><?php echo \esc_html__( 'Enable for Forminator Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_forminator" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_forminator]" value="1" <?php checked( ! empty( $settings['enable_forminator'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_forminator]" value="auto" <?php checked( $settings['mode_forminator'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_forminator]" value="shortcode" <?php checked( $settings['mode_forminator'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — paste into a custom HTML block or field in Forminator; when present the plugin will not auto-inject a second widget. When Shortcode only is selected, auto-inject is disabled for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Jetpack -->
                <?php if ( class_exists( 'Jetpack' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section>
                    <h2><?php echo \esc_html__( 'Jetpack Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is injected into Jetpack contact forms; validation occurs via the spam check hook and blocks submission with a surfaced error.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_jetpackforms"><?php echo \esc_html__( 'Enable for Jetpack Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_jetpackforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_jetpackforms]" value="1" <?php checked( ! empty( $settings['enable_jetpackforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_jetpackforms]" value="auto" <?php checked( $settings['mode_jetpackforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_jetpackforms]" value="shortcode" <?php checked( $settings['mode_jetpackforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — add to a custom HTML area if Jetpack supports it. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Kadence -->
                <?php if ( class_exists( 'Kadence_Blocks_Form' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section>
                    <h2><?php echo \esc_html__( 'Kadence Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is prepended before the submit button in Kadence Blocks Form; validation returns a form-level error without killing AJAX.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_kadenceforms"><?php echo \esc_html__( 'Enable for Kadence Forms (Kadence Blocks)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_kadenceforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_kadenceforms]" value="1" <?php checked( ! empty( $settings['enable_kadenceforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_kadenceforms]" value="auto" <?php checked( $settings['mode_kadenceforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_kadenceforms]" value="shortcode" <?php checked( $settings['mode_kadenceforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <?php echo esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <code>[kitgenix_turnstile]</code>
                            <?php echo esc_html__( ' — use inside a custom HTML field or block in Kadence Forms. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Save (end of main settings form) -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-save-row" id="section-save" data-section>
                    <?php submit_button( \__( 'Save Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'primary', 'submit', false, [ 'style' => 'min-width:160px;font-size:17px;' ] ); ?>
                </div>
            </form>

            <!-- Export / Import -->
            <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-import-export" data-section>
                <h2><?php echo \esc_html( \__( 'Export / Import Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">

                    <!-- Export -->
                    <h3><?php echo \esc_html( \__( 'Export', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h3>
                    <p class="description"><?php echo \esc_html( \__( 'Download your current settings as JSON for backup or migration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></p>
                    <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="kitgenix_turnstile_export" />
                        <?php \wp_nonce_field( 'kitgenix_turnstile_export' ); ?>
                        <label style="display:inline-flex;gap:8px;align-items:center;margin:8px 0;">
                            <input type="checkbox" name="include_secret" value="1" />
                            <span><?php echo \esc_html( \__( 'Include Secret Key (sensitive)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                        </label>
                        <p><button type="submit" class="button button-secondary"><?php echo \esc_html( \__( 'Download JSON', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></button></p>
                    </form>

                    <hr style="margin:18px 0;border:none;border-top:1px solid #e5e7eb;" />

                    <!-- Import -->
                    <h3><?php echo \esc_html( \__( 'Import', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h3>
                    <p class="description"><?php echo \esc_html( \__( 'Upload a previously exported JSON file or paste JSON below.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></p>
                    <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="kitgenix_turnstile_import" />
                        <?php \wp_nonce_field( 'kitgenix_turnstile_import' ); ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="kitgenix_captcha_for_cloudflare_turnstile_ts_import_file"><?php echo \esc_html( \__( 'JSON File', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="file" id="kitgenix_captcha_for_cloudflare_turnstile_ts_import_file" name="kitgenix_captcha_for_cloudflare_turnstile_ts_import_file" accept="application/json,.json" /></td>
                            </tr>
                            <tr>
                                <th><label for="kitgenix_captcha_for_cloudflare_turnstile_ts_import_text"><?php echo \esc_html( \__( 'Or paste JSON', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><textarea id="kitgenix_captcha_for_cloudflare_turnstile_ts_import_text" name="kitgenix_captcha_for_cloudflare_turnstile_ts_import_text" rows="6" class="large-text code" placeholder="{ ... }"></textarea></td>
                            </tr>
                            <tr>
                                <th><label><?php echo \esc_html( \__( 'Import Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label style="margin-right:12px;"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode" value="merge" <?php checked( $settings['kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode'] ?? 'merge', 'merge' ); ?> /> <?php echo \esc_html( \__( 'Merge with existing (recommended)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label>
                                    <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode" value="replace" <?php checked( $settings['kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode'] ?? '', 'replace' ); ?> /> <?php echo \esc_html( \__( 'Replace existing (overwrite)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label>
                                    <p class="description"><?php echo \esc_html( \__( '“Merge” only updates provided keys. “Replace” overwrites the full settings object.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="kitgenix_captcha_for_cloudflare_turnstile_ts_allow_secret"><?php echo \esc_html( \__( 'Secret Key Handling', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label style="display:inline-flex;gap:8px;align-items:center;">
                                        <input type="checkbox" id="kitgenix_captcha_for_cloudflare_turnstile_ts_allow_secret" name="kitgenix_captcha_for_cloudflare_turnstile_ts_allow_secret" value="1" />
                                        <span><?php echo \esc_html( \__( 'Allow import to overwrite my Secret Key (sensitive).', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p><button type="submit" class="button button-primary"><?php echo \esc_html( \__( 'Import Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></button></p>
                    </form>
                </div>
            </div>

            <div class="kitgenix-captcha-for-cloudflare-turnstile-settings-intro" id="section-support" data-section style="margin-top:0;margin-bottom:24px;">
                <h2 style="font-size:1.3em;font-weight:700;margin-bottom:6px;"><?php echo \esc_html__( 'Support Active Development', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                <p class="description"><?php echo \esc_html__( 'If you find Kitgenix CAPTCHA for Cloudflare Turnstile useful, please consider buying us a coffee! Your support helps us maintain and actively develop this plugin for the WordPress community.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                <a href="<?php echo \esc_url( 'https://buymeacoffee.com/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer" class="kitgenix-captcha-for-cloudflare-turnstile-review-link">☕ <?php echo \esc_html__( 'Buy us a coffee', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
            </div>
            <!-- Unsaved changes floating bar (progressive enhancement via JS) -->
            <div id="kgx-unsaved-bar" class="kgx-unsaved-bar" aria-hidden="true">
                <strong><?php echo \esc_html__( 'Unsaved changes', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                <button type="button" id="kgx-unsaved-save" class="button button-secondary"><?php echo \esc_html__( 'Save now', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></button>
            </div>
            </div><!-- /.kitgenix-settings-content -->
        </div><!-- /.kitgenix-settings-layout -->
        <script>
        // Fallback logic: ensure reveal/copy buttons function even if admin.js failed.
        (function(){
            function onReady(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
            onReady(function(){
                var revealBtn = document.querySelector('.kitgenix-reveal-secret');
                var copyBtn   = document.querySelector('.kitgenix-copy-secret');
                var input     = document.getElementById('secret_key');
                if(revealBtn && input){
                    revealBtn.addEventListener('click', function(){
                        var isPw = input.getAttribute('type') === 'password';
                        input.setAttribute('type', isPw ? 'text' : 'password');
                        this.setAttribute('aria-pressed', isPw ? 'true' : 'false');
                        var showLabel = this.getAttribute('data-label-show') || 'Reveal secret key';
                        var hideLabel = this.getAttribute('data-label-hide') || 'Hide secret key';
                        this.setAttribute('aria-label', isPw ? hideLabel : showLabel);
                        var showText = this.getAttribute('data-text-show') || 'Show';
                        var hideText = this.getAttribute('data-text-hide') || 'Hide';
                        var span = this.querySelector('.kitgenix-reveal-secret-text');
                        if(span){ span.textContent = isPw ? hideText : showText; } else { this.textContent = isPw ? hideText : showText; }
                    });
                }
                if(copyBtn && input){
                    copyBtn.addEventListener('click', function(){
                        var val = input.value || '';
                        if(!val){ return; }
                        function feedback(){
                            var original = copyBtn.innerHTML;
                            copyBtn.innerHTML = '✓';
                            copyBtn.setAttribute('aria-label','Copied');
                            setTimeout(function(){ copyBtn.innerHTML = original; copyBtn.setAttribute('aria-label','Copy secret key'); },1200);
                        }
                        if(navigator.clipboard && navigator.clipboard.writeText){
                            navigator.clipboard.writeText(val).then(feedback).catch(fallback);
                        } else { fallback(); }
                        function fallback(){
                            try {
                                var origType = input.getAttribute('type');
                                input.setAttribute('type','text');
                                input.select();
                                document.execCommand('copy');
                                input.setAttribute('type', origType);
                                feedback();
                            } catch(e){ /* ignore */ }
                        }
                    });
                }
            });
        })();
        </script>
        </div>
        <?php
    }
}

