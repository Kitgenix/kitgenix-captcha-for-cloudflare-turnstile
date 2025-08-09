<?php
/**
 * Plugin Name: Kitgenix CAPTCHA for Cloudflare Turnstile
 * Plugin URI: https://wordpress.org/plugins/kitgenix-captcha-for-cloudflare-turnstile
 * Description: Seamlessly integrate Cloudflare Turnstile with WordPress, WooCommerce, and Elementor forms.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.0
 * Author: Kitgenix
 * Author URI: https://kitgenix.com/
 * Support Us: https://kitgenix.com/plugins/support-us/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: kitgenix-captcha-for-cloudflare-turnstile
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Plugin Constants
define('KitgenixCaptchaForCloudflareTurnstileVERSION', '1.0.0');
define('KitgenixCaptchaForCloudflareTurnstileFILE', __FILE__);
define('KitgenixCaptchaForCloudflareTurnstilePATH', plugin_dir_path(__FILE__));
define('KitgenixCaptchaForCloudflareTurnstileURL', plugin_dir_url(__FILE__));
define('KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH', KitgenixCaptchaForCloudflareTurnstilePATH . 'includes/');
define('KitgenixCaptchaForCloudflareTurnstileASSETS_URL', KitgenixCaptchaForCloudflareTurnstileURL . 'assets/');

// Autoload Core Loader
require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-turnstile-loader.php';
require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-admin-options.php';
KitgenixCaptchaForCloudflareTurnstile\Admin\Admin_Options::init();
require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-settings-ui.php';
KitgenixCaptchaForCloudflareTurnstile\Admin\Settings_UI::init();

// Initialize Plugin
add_action('plugins_loaded', 'kitgenix_captcha_for_cloudflare_turnstile_init_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_init_plugin() {
    if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Core\\Turnstile_Loader')) {
        \KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Loader::init();
    }
}

// Activation Hook
register_activation_hook(__FILE__, 'kitgenix_captcha_for_cloudflare_turnstile_activate_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_activate_plugin() {
    set_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect', true, 30);
}

// Deactivation Hook
register_deactivation_hook(__FILE__, 'kitgenix_captcha_for_cloudflare_turnstile_deactivate_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_deactivate_plugin() {
    // Placeholder: clean transient cache, if needed.
}

// Uninstall Hook (ensure uninstall.php is called)
register_uninstall_hook(__FILE__, 'kitgenix_captcha_for_cloudflare_turnstile_uninstall_plugin');
function kitgenix_captcha_for_cloudflare_turnstile_uninstall_plugin() {
    if (file_exists(KitgenixCaptchaForCloudflareTurnstilePATH . 'uninstall.php')) {
        include KitgenixCaptchaForCloudflareTurnstilePATH . 'uninstall.php';
    }
}
