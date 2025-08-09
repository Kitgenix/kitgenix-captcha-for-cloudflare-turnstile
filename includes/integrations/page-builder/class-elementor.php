<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\PageBuilder;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

use function add_action;
use function get_option;
use function esc_attr;
use function esc_html;
use function esc_attr_e;
use function __;
use function wp_enqueue_script;
use function sanitize_text_field;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function is_admin;

class Elementor {

    public static function init() {
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }
        if (Whitelist::is_whitelisted()) {
            if (!is_admin() && isset($_SERVER['REQUEST_URI']) && strpos(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), 'elementor') !== false) {
            } else {
                return;
            }
        }
        add_action('elementor/frontend/init', [__CLASS__, 'register_widget_hooks']);
        add_action('elementor/widget/form/after_render', [__CLASS__, 'inject_widget_script'], 10, 2);
        add_action('elementor_pro/forms/validation', [__CLASS__, 'validate_turnstile'], 10, 2);
        add_action('elementor_pro/forms/pre_render', [__CLASS__, 'disable_submit_css']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        // Fallback: inject widget for all Elementor forms (free or pro)
        add_action('wp_footer', [__CLASS__, 'fallback_inject_widget'], 20);
    }

    public static function register_widget_hooks() {
        add_action('elementor_pro/forms/render_form_after_fields', [__CLASS__, 'render_widget'], 10, 1);
    }

    public static function render_widget($form) {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';

        if (!$site_key) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">' . esc_html__('Turnstile site key is missing.', 'kitgenix-captcha-for-cloudflare-turnstile') . '</p>';
            return;
        }

        // Add a nonce field for CSRF protection
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce');
        }

        echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '" data-theme="' . esc_attr($settings['theme'] ?? 'auto') . '" data-size="' . esc_attr($settings['widget_size'] ?? 'normal') . '" data-appearance="' . esc_attr($settings['appearance'] ?? 'always') . '"></div>';
    }

    public static function inject_widget_script($widget, $args) {
        // No longer needed; logic moved to elementor.js
    }

    /**
     * Fallback: Inject Turnstile widget before the submit button in Elementor forms
     */
    public static function fallback_inject_widget() {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';
        $theme = $settings['theme'] ?? 'auto';
        $size = $settings['widget_size'] ?? 'normal';
        $appearance = $settings['appearance'] ?? 'always';
        if (!$site_key) return;
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.elementor-form-fields-wrapper').forEach(function(wrapper) {
                var submitGroup = wrapper.querySelector('.elementor-field-type-submit');
                if (!submitGroup) return;
                if (!wrapper.querySelector('.cf-turnstile')) {
                    var container = document.createElement('div');
                    container.className = 'cf-turnstile';
                    container.setAttribute('data-sitekey', '<?php echo esc_attr($site_key); ?>');
                    container.setAttribute('data-theme', '<?php echo esc_attr($theme); ?>');
                    container.setAttribute('data-size', '<?php echo esc_attr($size); ?>');
                    container.setAttribute('data-appearance', '<?php echo esc_attr($appearance); ?>');
                    submitGroup.parentNode.insertBefore(container, submitGroup);
                }
            });
        });
        </script>
        <?php
    }

    public static function validate_turnstile($record, $handler) {
        if (!Turnstile_Validator::is_valid_submission()) {
            $handler->add_error_message(Turnstile_Validator::get_error_message('elementor'));
            $handler->add_error('__all__');
        }
    }

    public static function disable_submit_css() {
        // No longer needed; logic moved to elementor.js or CSS file
    }

    public static function enqueue_scripts() {
        wp_enqueue_script(
            'kitgenix-captcha-for-cloudflare-turnstile-elementor',
            KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'js/elementor.js',
            ['jquery', 'elementor-frontend'],
            KitgenixCaptchaForCloudflareTurnstileVERSION,
            true
        );
        // Optionally enqueue a CSS file if you want to move styles out of JS
        // wp_enqueue_style('kitgenix-captcha-for-cloudflare-turnstile-elementor', KitgenixCaptchaForCloudflareTurnstileASSETS_URL . 'css/elementor.css', [], KitgenixCaptchaForCloudflareTurnstileVERSION);
    }
}

Elementor::init();
