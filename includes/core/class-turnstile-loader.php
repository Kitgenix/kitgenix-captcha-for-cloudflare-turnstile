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
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-client-ip.php';
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-cloudflare-health.php';
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-turnstile-validator.php';
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-script-handler.php';
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-turnstile-shortcode.php';
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'core/class-whitelist.php';

        \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::init();
        \KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist::init();
    }

    /**
     * Admin features (settings UI, site health).
     * Loaded only in wp-admin for performance.
     */
    private static function load_admin() {
        if (!\is_admin()) {
            return;
        }

        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'admin/class-admin-options.php';
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'admin/class-settings-ui.php';
        require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'admin/class-site-health.php';

        // Main file already inits Admin_Options & Settings_UI.

        if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Admin\Site_Health::class)) {
            \KitgenixCaptchaForCloudflareTurnstile\Admin\Site_Health::init();
        }

    }

    /**
     * Third-party integrations (conditional, based on settings + presence).
     */
    private static function load_integrations() {
        $settings = \function_exists('get_option')
            ? (array) \get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', [])
            : [];

        // WordPress Core forms (explicit init). Also load the shared comments
        // integration when WooCommerce product reviews are enabled.
        $load_wp_core = ! empty( $settings['enable_wordpress'] )
            || ( ! empty( $settings['enable_woocommerce'] )
                && ! empty( $settings['wc_reviews_form'] )
                && class_exists( 'WooCommerce' ) );

        if ( $load_wp_core ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/wordpress/class-wp-core.php';
            if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress\WP_Core::class)) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress\WP_Core::init();
            }
        }

        // WooCommerce (classic + Blocks)
        if (!empty($settings['enable_woocommerce']) && class_exists('WooCommerce')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/ecommerce/class-woocommerce.php';
            if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\WooCommerce::class)) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\WooCommerce::init();
            }
        }

        // Easy Digital Downloads (checkout + account forms)
        $edd_present = class_exists('Easy_Digital_Downloads') || defined('EDD_VERSION');
        if (!empty($settings['enable_edd']) && $edd_present) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/ecommerce/class-easy-digital-downloads.php';
            if (class_exists(\KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\Easy_Digital_Downloads::class)) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Ecommerce\Easy_Digital_Downloads::init();
            }
        }

        // Elementor (file auto-inits)
        if (!empty($settings['enable_elementor']) && defined('ELEMENTOR_VERSION')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/page-builder/class-elementor.php';
        }

        // WPForms (auto-init)
        if (!empty($settings['enable_wpforms']) && class_exists('WPForms')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/wpforms.php';
        }

        // Fluent Forms (auto-init)
        if (!empty($settings['enable_fluentforms']) && (defined('FLUENTFORM') || class_exists('FluentForm'))) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/fluent-forms.php';
        }

        // Gravity Forms (auto-init)
        if (!empty($settings['enable_gravityforms']) && class_exists('GFForms')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/gravity-forms.php';
        }

        // Contact Form 7 (auto-init)
        if (!empty($settings['enable_cf7']) && defined('WPCF7_VERSION')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/contact-form-7.php';
        }

        // Formidable Forms (auto-init)
        if (!empty($settings['enable_formidableforms']) && class_exists('FrmForm')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/formidable-forms.php';
        }

        // Forminator (auto-init)
        if (!empty($settings['enable_forminator']) && \function_exists('forminator')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/forminator-forms.php';
        }

        // Jetpack Forms (auto-init)
        if (!empty($settings['enable_jetpackforms']) && class_exists('Jetpack')) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/jetpack-forms.php';
        }

        // Kadence Forms (auto-init). Kadence_Blocks_Form is not a real class in Kadence
        // Blocks; gate on the plugin's own version constant instead (the integration itself
        // additionally requires Kadence_Blocks_Advanced_Form_Block – the only Kadence form
        // type with a genuine pre-actions validation hook – before registering anything).
        $kadence_blocks_present = defined( 'KADENCE_BLOCKS_VERSION' ) || class_exists( 'Kadence_Blocks_Advanced_Form_Block' );
        if (!empty($settings['enable_kadenceforms']) && $kadence_blocks_present) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/kadence-forms.php';
        }

        // JetFormBuilder (auto-init)
        $jfb_present = class_exists( '\\Jet_Form_Builder\\Plugin' ) || defined( 'JET_FORM_BUILDER_VERSION' ) || defined( 'JET_FORM_BUILDER_PATH' );
        if ( ! empty( $settings['enable_jetformbuilder'] ) && $jfb_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/jetformbuilder.php';
        }

        // Ninja Forms (auto-init)
        $ninjaforms_present = class_exists( 'Ninja_Forms' ) || defined( 'NF_PLUGIN_DIR' );
        if ( ! empty( $settings['enable_ninjaforms'] ) && $ninjaforms_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forms/ninja-forms.php';
        }

        // MailPoet newsletter forms.
        $mailpoet_present = defined( 'MAILPOET_VERSION' ) || class_exists( '\\MailPoet\\Config\\Env' ) || class_exists( '\\MailPoet\\API\\API' );
        if ( ! empty( $settings['enable_mailpoet'] ) && $mailpoet_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/newsletters/class-mailpoet.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Newsletters\MailPoet::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Newsletters\MailPoet::init();
            }
        }

        // Paid Memberships Pro checkout / registration.
        $pmpro_present = defined( 'PMPRO_VERSION' ) || function_exists( 'pmpro_getOption' ) || class_exists( 'MemberOrder' );
        if ( ! empty( $settings['enable_paidmembershipspro'] ) && $pmpro_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/membership/class-paid-memberships-pro.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership\Paid_Memberships_Pro::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership\Paid_Memberships_Pro::init();
            }
        }

        // Ultimate Member auth flows.
        $um_present = defined( 'ultimatemember_version' ) || class_exists( 'UM' ) || function_exists( 'UM' );
        if ( ! empty( $settings['enable_ultimatemember'] ) && $um_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/membership/class-ultimate-member.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership\Ultimate_Member::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership\Ultimate_Member::init();
            }
        }

        // MemberPress checkout / signup forms.
        $memberpress_present = defined( 'MEPR_VERSION' ) || class_exists( 'MeprOptions' ) || class_exists( 'MeprAppCtrl' );
        if ( ! empty( $settings['enable_memberpress'] ) && $memberpress_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/membership/class-memberpress.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership\MemberPress::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Membership\MemberPress::init();
            }
        }

        // wpDiscuz comment forms.
        $wpdiscuz_present = defined( 'WPDISCUZ_VERSION' ) || class_exists( 'WpdiscuzCore' ) || class_exists( '\\WpdiscuzCore' );
        if ( ! empty( $settings['enable_wpdiscuz'] ) && $wpdiscuz_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/community/class-wpdiscuz.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Community\WpDiscuz::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Community\WpDiscuz::init();
            }
        }

        // BuddyPress (forums) integration – accept multiple presence signals
        $bp_present = defined( 'BP_VERSION' ) || class_exists( 'BuddyPress' ) || function_exists( 'bp_is_active' ) || function_exists( 'bp_register' );
        if ( ! empty( $settings['enable_buddypress'] ) && $bp_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forums/buddypress.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Forums\BuddyPress::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Forums\BuddyPress::init();
            }
        }

        // bbPress integration – accept multiple presence signals (constant, class, or common function)
        $bbp_present = defined( 'BBPRESS_VERSION' ) || defined( 'BBP_VERSION' ) || class_exists( 'bbPress' ) || function_exists( 'bbp_is_single_forum' );
        if ( ! empty( $settings['enable_bbpress'] ) && $bbp_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/forums/bbpress.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Forums\BbPress::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Forums\BbPress::init();
            }
        }

        // Kitgenix Plugin Score – custom auth forms (login, register, forgot-password).
        $kgps_present = defined( 'KGPS_VERSION' ) || class_exists( 'KGPS_Plugin' ) || in_array( 'kitgenix-plugin-score/kitgenix-plugin-score.php', (array) get_option( 'active_plugins', [] ), true );
        if ( ! empty( $settings['enable_kitgenix_plugin_score'] ) && $kgps_present ) {
            require_once KitgenixCaptchaForCloudflareTurnstile_Includes_Path . 'integrations/kitgenix/class-plugin-score.php';
            if ( class_exists( \KitgenixCaptchaForCloudflareTurnstile\Integrations\Kitgenix\Plugin_Score::class ) ) {
                \KitgenixCaptchaForCloudflareTurnstile\Integrations\Kitgenix\Plugin_Score::init();
            }
        }

        
    }
}
