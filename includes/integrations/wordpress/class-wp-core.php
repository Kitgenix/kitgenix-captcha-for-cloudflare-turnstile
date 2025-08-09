<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\WordPress;

use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use function esc_html;
use function esc_html__;
use function esc_attr;
use function wp_nonce_field;
use function wp_die;
use function add_action;
use function add_filter;
use function get_option;
use \WP_Error;

defined('ABSPATH') || exit;

class WP_Core {

    /**
     * Initialize hooks.
     */
    public static function init() {
        if (Whitelist::is_whitelisted()) {
            return;
        }

        // Add Turnstile widget
        add_action('login_form', [__CLASS__, 'render_widget']);
        add_action('register_form', [__CLASS__, 'render_widget']);
        add_action('lostpassword_form', [__CLASS__, 'render_widget']);
        add_action('comment_form_after_fields', [__CLASS__, 'render_widget']);
        add_action('comment_form_logged_in_after', [__CLASS__, 'render_widget']);

        // Validate submission
        add_filter('authenticate', [__CLASS__, 'validate_login'], 30, 3);
        add_filter('registration_errors', [__CLASS__, 'validate_generic'], 30, 3);
        add_action('reset_password_post', [__CLASS__, 'validate_reset'], 5);
        add_filter('preprocess_comment', [__CLASS__, 'validate_comment']);
    }

    /**
     * Render the Turnstile widget HTML.
     */
    public static function render_widget() {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $site_key = $settings['site_key'] ?? '';

        if (!$site_key) {
            echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">' . esc_html__( 'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</p>';
            return;
        }

        // Add a nonce field for CSRF protection
        wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_action', 'kitgenix_captcha_for_cloudflare_turnstile_nonce');

        // Use current filter/action to determine context for unique ID
        global $wp_current_filter;
        $context = 'login';
        if (is_array($wp_current_filter)) {
            foreach ($wp_current_filter as $filter) {
                if (strpos($filter, 'register') !== false) {
                    $context = 'register';
                    break;
                } elseif (strpos($filter, 'lostpassword') !== false) {
                    $context = 'lostpassword';
                    break;
                } elseif (strpos($filter, 'comment') !== false) {
                    $context = 'comment';
                    break;
                }
            }
        }
        $unique_id = 'cf-turnstile-' . esc_attr($context);

        // Always output the id attribute for the Turnstile div
        echo '<div id="' . esc_attr($unique_id) . '" class="cf-turnstile" style="display: flex; justify-content: center;" data-sitekey="' . esc_attr($site_key) . '" data-theme="' . esc_attr($settings['theme'] ?? 'auto') . '" data-size="' . esc_attr($settings['widget_size'] ?? 'normal') . '" data-appearance="' . esc_attr($settings['appearance'] ?? 'always') . '"></div>';
    }

    /**
     * Validate Turnstile on login.
     */
    public static function validate_login($user, $username, $password) {
        if (!Turnstile_Validator::is_valid_submission()) {
            return new WP_Error('turnstile_failed', esc_html(Turnstile_Validator::get_error_message('wp_core')));
        }
        return $user;
    }

    /**
     * Validate on registration and lost password.
     */
    public static function validate_generic($errors) {
        if (!Turnstile_Validator::is_valid_submission()) {
            $errors->add('turnstile_failed', esc_html(Turnstile_Validator::get_error_message('wp_core')));
        }
        return $errors;
    }

    /**
     * Validate on password reset.
     */
    public static function validate_reset($user) {
        if (!Turnstile_Validator::is_valid_submission()) {
            wp_die(esc_html(Turnstile_Validator::get_error_message('wp_core')), 403);
        }
    }

    /**
     * Validate comment form.
     */
    public static function validate_comment($commentdata) {
        if (!Turnstile_Validator::is_valid_submission()) {
            wp_die(esc_html(Turnstile_Validator::get_error_message('wp_core')), 403);
        }
        return $commentdata;
    }
}
