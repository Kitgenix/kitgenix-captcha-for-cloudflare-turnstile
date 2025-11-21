<?php
/**
 * Plugin Name: Kitgenix CAPTCHA for Cloudflare Turnstile
 * Plugin URI: https://wordpress.org/plugins/kitgenix-captcha-for-cloudflare-turnstile
 * Description: Seamlessly integrate Cloudflare Turnstile with WordPress, WooCommerce, and Elementor forms.
 * Version: 1.0.12
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.0
 * Author: Kitgenix
 * Author URI: https://kitgenix.com/
 * Support Us: https://buymeacoffee.com/kitgenix
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: kitgenix-captcha-for-cloudflare-turnstile
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

/**
 * Constants (guarded)
 */
if ( ! defined('KitgenixCaptchaForCloudflareTurnstileVERSION') ) {
    define('KitgenixCaptchaForCloudflareTurnstileVERSION', '1.0.12');
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstileFILE') ) {
    define('KitgenixCaptchaForCloudflareTurnstileFILE', __FILE__);
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstilePATH') ) {
    define('KitgenixCaptchaForCloudflareTurnstilePATH', plugin_dir_path(__FILE__));
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstileURL') ) {
    define('KitgenixCaptchaForCloudflareTurnstileURL', plugin_dir_url(__FILE__));
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH') ) {
    define('KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH', KitgenixCaptchaForCloudflareTurnstilePATH . 'includes/');
}
if ( ! defined('KitgenixCaptchaForCloudflareTurnstileASSETS_URL') ) {
    define('KitgenixCaptchaForCloudflareTurnstileASSETS_URL', KitgenixCaptchaForCloudflareTurnstileURL . 'assets/');
}

/**
 * Requires
 */
require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-turnstile-loader.php';
require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-admin-options.php';
require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-settings-ui.php';

/**
 * Admin boot (menus/options)
 */
KitgenixCaptchaForCloudflareTurnstile\Admin\Admin_Options::init();
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
    $min_php = '7.4'; // recommended (header remains 7.0 for compatibility)
    $min_wp  = '5.8';

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
 * Perform the activation redirect once (leave onboarding class to own flow)
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
    $target = admin_url('options-general.php?page=' . $slug);

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
    $uninstall = KitgenixCaptchaForCloudflareTurnstilePATH . 'uninstall.php';
    if ( file_exists($uninstall) ) {
        include $uninstall; // uninstall.php should check defined('WP_UNINSTALL_PLUGIN')
    }
}

/**
 * “Settings” link on the Plugins screen
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $slug = 'kitgenix-captcha-for-cloudflare-turnstile';
    $url  = admin_url('options-general.php?page=' . $slug);
    $links[] = '<a href="' . esc_url($url) . '">' . esc_html__( 'Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</a>';
    return $links;
});
