<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

use function wp_verify_nonce;
use function get_option;
use function sanitize_text_field;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function wp_unslash;
use function __;
use function apply_filters;
use function esc_html;

class Turnstile_Validator {
    /**
     * Validate Turnstile challenge (nonce + token).
     * @param bool $require_nonce
     * @return bool
     */
    public static function is_valid_submission($require_nonce = true): bool {
        if ($require_nonce) {
            // Always unslash and sanitize input
            $nonce = isset($_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce']) ? sanitize_text_field(wp_unslash($_POST['kitgenix_captcha_for_cloudflare_turnstile_nonce'])) : '';
            if (!$nonce || !wp_verify_nonce($nonce, 'kitgenix_captcha_for_cloudflare_turnstile_action')) {
                return false;
            }
        }
        if (!isset($_POST['cf-turnstile-response'])) {
            return false;
        }
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $secret = $settings['secret_key'] ?? '';
        $response = sanitize_text_field(wp_unslash($_POST['cf-turnstile-response']));
        $remoteip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!$secret || !$response) {
            return false;
        }
        $verify = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret'   => $secret,
                'response' => $response,
                'remoteip' => $remoteip,
            ],
        ]);
        if (!$verify || is_wp_error($verify)) {
            // Debug only: error_log() should not be used in production.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG && $verify && is_wp_error($verify)) error_log('Turnstile verification error: ' . $verify->get_error_message());
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($verify), true);
        return !empty($data['success']);
    }

    /**
     * Validate a token directly (for integrations that only have the token, e.g. Fluent Forms filter).
     * @param string $token
     * @return bool
     */
    public static function validate_token($token): bool {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $secret = $settings['secret_key'] ?? '';
        $remoteip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!$secret || !$token) return false;
        $verify = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $remoteip,
            ],
        ]);
        if (!$verify || is_wp_error($verify)) {
            // Debug only: error_log() should not be used in production.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            if (defined('WP_DEBUG') && WP_DEBUG && $verify && is_wp_error($verify)) error_log('Turnstile verification error: ' . $verify->get_error_message());
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($verify), true);
        return !empty($data['success']);
    }

    /**
     * Get the error message for Turnstile validation (shared utility).
     * @param string $context Optional context for filter hook.
     * @return string
     */
    public static function get_error_message($context = ''): string {
        $settings = get_option('kitgenix_captcha_for_cloudflare_turnstile_settings', []);
        $message = !empty($settings['error_message']) ? $settings['error_message'] : __('Please complete the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile');
        if ($context) {
            $filter = 'kitgenix_captcha_for_cloudflare_turnstile_' . $context . '_turnstile_error_message';
            $message = apply_filters($filter, $message);
        }
        return esc_html($message);
    }
}
