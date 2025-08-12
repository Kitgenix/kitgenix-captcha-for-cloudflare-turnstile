<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Turnstile_Loader {

    /**
     * Bootstrap plugin pieces.
     */
    public static function init() {
        self::load_core();
        self::load_admin();
        self::load_integrations();
    }

    /**
     * Core functionality (script handler, validator, IP helper, whitelist).
     */
    private static function load_core() {
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-client-ip.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-turnstile-validator.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-script-handler.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'core/class-whitelist.php';

        \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::init();
        \KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist::init();
    }

    /**
     * Admin features (settings UI, onboarding, site health, import/export).
     * Loaded only in wp-admin for performance.
     */
    private static function load_admin() {
        if (!\is_admin()) {
            return;
        }

        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-admin-options.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-settings-ui.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-onboarding.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-site-health.php';
        require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'admin/class-settings-transfer.php';

        // Main file already inits Admin_Options & Settings_UI.
        if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Admin\Onboarding::class)) {
            // Onboarding may self-init; leave commented unless needed.
            // \KitgenixCaptchaForCloudflareTurnstile\Admin\Onboarding::init();
        }

        if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Admin\Site_Health::class)) {
            \KitgenixCaptchaForCloudflareTurnstile\Admin\Site_Health::init();
        }

        if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Admin\Settings_Transfer::class)) {
            \KitgenixCaptchaForCloudflareTurnstile\Admin\Settings_Transfer::init();
        }
    }

    /**
     * Third-party integrations (conditional, based on settings + presence).
     */
    private static function load_integrations() {
        $settings = \function_exists('get_option')
            ? (array) \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', [])
            : [];

        // WordPress Core forms (explicit init)
        if (!empty($settings['enable_wordpress'])) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/wordpress/class-wp-core.php';
            if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress\WP_Core::class)) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress\WP_Core::init();
            }
        }

        // WooCommerce (classic + Blocks)
        if (!empty($settings['enable_woocommerce']) && class_exists('WooCommerce')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/ecommerce/class-woocommerce.php';
            if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\WooCommerce::class)) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\WooCommerce::init();
            }

            // Checkout/Cart Blocks bridge
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/ecommerce/class-woocommerce-blocks.php';
            if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\WooCommerce_Blocks::class)) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\WooCommerce_Blocks::init();
            }
        }

        // Elementor (file auto-inits)
        if (!empty($settings['enable_elementor']) && defined('ELEMENTOR_VERSION')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/page-builder/class-elementor.php';
        }

        // WPForms (auto-init)
        if (!empty($settings['enable_wpforms']) && class_exists('WPForms')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/wpforms.php';
        }

        // Fluent Forms (auto-init)
        if (!empty($settings['enable_fluentforms']) && (defined('FLUENTFORM') || class_exists('FluentForm'))) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/fluent-forms.php';
        }

        // Gravity Forms (auto-init)
        if (!empty($settings['enable_gravityforms']) && class_exists('GFForms')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/gravity-forms.php';
        }

        // Contact Form 7 (auto-init)
        if (!empty($settings['enable_cf7']) && defined('WPCF7_VERSION')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/contact-form-7.php';
        }

        // Formidable Forms (auto-init)
        if (!empty($settings['enable_formidableforms']) && class_exists('FrmForm')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/formidable-forms.php';
        }

        // Forminator (auto-init)
        if (!empty($settings['enable_forminator']) && \function_exists('forminator')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/forminator-forms.php';
        }

        // Jetpack Forms (auto-init)
        if (!empty($settings['enable_jetpackforms']) && class_exists('Jetpack')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/jetpack-forms.php';
        }

        // Kadence Forms (auto-init)
        if (!empty($settings['enable_kadenceforms']) && class_exists('Kadence_Blocks_Form')) {
            require_once KitgenixCaptchaForCloudflareTurnstileINCLUDES_PATH . 'integrations/forms/kadence-forms.php';
        }
    }
}
