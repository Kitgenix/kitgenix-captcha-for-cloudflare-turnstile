<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Turnstile_Loader {

    /**
     * Initialize the plugin.
     */
    public static function init() {
        self::load_core();
        self::load_admin();
        self::load_integrations();
    }

    /**
     * Load core functionality like script handler and whitelist checks.
     */
    private static function load_core() {
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-turnstile-validator.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-script-handler.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-whitelist.php';

        \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::init();
        \KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist::init();
    }

    /**
     * Load admin panel features like settings and onboarding.
     */
    private static function load_admin() {
        if (!\is_admin()) {
            return;
        }

        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-admin-options.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-settings-ui.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-onboarding.php';

        if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Admin\\Admin_Options')) {
            \KitgenixCaptchaForCloudflareTurnstile\Admin\Admin_Options::init();
        }
        if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Admin\\Settings_UI')) {
            \KitgenixCaptchaForCloudflareTurnstile\Admin\Settings_UI::init();
        }
        if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Admin\\Onboarding')) {
            \KitgenixCaptchaForCloudflareTurnstile\Admin\Onboarding::init();
        }
    }

    /**
     * Load supported third-party integrations.
     */
    private static function load_integrations() {
        $settings = function_exists('get_option') ? \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []) : [];
        // WordPress Core Forms
        if (!empty($settings['enable_wordpress'])) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/wordpress/class-wp-core.php';
            if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Integrations\\WordPress\\WP_Core')) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress\WP_Core::init();
            }
        }
        // WooCommerce Forms
        if (!empty($settings['enable_woocommerce']) && class_exists('WooCommerce')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/ecommerce/class-woocommerce.php';
            if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Integrations\\Ecommerce\\WooCommerce')) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\WooCommerce::init();
            }
        }
        // Elementor Forms
        if (!empty($settings['enable_elementor']) && defined('ELEMENTOR_VERSION')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/page-builder/class-elementor.php';
            if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Integrations\\PageBuilder\\Elementor')) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\PageBuilder\Elementor::init();
            }
        }
        // WPForms
        if (!empty($settings['enable_wpforms']) && class_exists('WPForms')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/wpforms.php';
        }
        // Fluent Forms
        if (!empty($settings['enable_fluentforms']) && (defined('FLUENTFORM') || class_exists('FluentForm'))) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/fluent-forms.php';
            if (class_exists('KitgenixCaptchaForCloudflareTurnstile\\Integrations\\Forms\\FluentForms')) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms\FluentForms::init();
            }
        }
        // Gravity Forms
        if (!empty($settings['enable_gravityforms']) && class_exists('GFForms')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/gravity-forms.php';
            if (class_exists('KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms\GravityForms')) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Forms\GravityForms::init();
            }
        }
        // Contact Form 7
        if (!empty($settings['enable_cf7']) && defined('WPCF7_VERSION')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/contact-form-7.php';
        }
        // Formidable Forms
        if (!empty($settings['enable_formidableforms']) && class_exists('FrmForm')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/formidable-forms.php';
        }
        // Forminator Forms
        if (!empty($settings['enable_forminator']) && function_exists('forminator')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/forminator-forms.php';
            if (class_exists('Just_Cloudflare_Turnstile_Forminator_Integration')) {
                Just_Cloudflare_Turnstile_Forminator_Integration::init();
            }
        }
        // Jetpack Forms
        if (!empty($settings['enable_jetpackforms']) && class_exists('Jetpack')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/jetpack-forms.php';
        }
        // Kadence Forms
        if (!empty($settings['enable_kadenceforms']) && class_exists('Kadence_Blocks_Form')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/kadence-forms.php';
        }
    }
}
