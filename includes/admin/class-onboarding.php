<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

defined('ABSPATH') || exit;

class Onboarding {

    /**
     * Initialize onboarding logic.
     */
    public static function init() {
        \add_action('admin_init', [__CLASS__, 'maybe_redirect_to_setup']);
        \add_action('admin_menu', [__CLASS__, 'register_welcome_screen']);
    }

    /**
     * Trigger onboarding redirect on plugin activation.
     */
    public static function maybe_redirect_to_setup() {
        if (\get_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect')) {
            \delete_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect');

            // Nonce verification for security
            $nonce = '';
            if (isset($_GET['_wpnonce'])) {
                $nonce = \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) );
            }
            if (empty($nonce) || !wp_verify_nonce($nonce, 'kitgenix_captcha_for_cloudflare_turnstile_onboarding_redirect')) {
                return;
            }

            if (!isset($_GET['activate-multi'])) {
                \wp_safe_redirect(\admin_url('options-general.php?page=kitgenix-captcha-for-cloudflare-turnstile-onboarding&_wpnonce=' . wp_create_nonce('kitgenix_captcha_for_cloudflare_turnstile_onboarding_redirect')));
                exit;
            }
        }
    }

    /**
     * Register onboarding page (not shown in menu).
     */
    public static function register_welcome_screen() {
        \add_submenu_page(
            null,
            \__('Welcome to Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile'),
            '',
            'manage_options',
            'kitgenix-captcha-for-cloudflare-turnstile-onboarding',
            [__CLASS__, 'render_onboarding_page']
        );
    }

    /**
     * Render onboarding screen HTML.
     */
    public static function render_onboarding_page() {
        ?>
        <div class="wrap" id="kitgenix-captcha-for-cloudflare-turnstile-admin-app">
            <h1><?php echo \esc_html(\__('Welcome to Kitgenix CAPTCHA for Cloudflare Turnstile 🎉', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></h1>
            <p><?php echo \esc_html(\__('Thank you for installing Kitgenix CAPTCHA for Cloudflare Turnstile! You’re now protected from spam bots with a modern, privacy-friendly solution powered by Cloudflare.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></p>

            <h2><?php echo \esc_html(\__('Getting Started', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></h2>
            <ol>
                <li><?php echo \wp_kses_post(\__('Go to <strong>Settings → Cloudflare Turnstile</strong> and enter your Site Key & Secret Key.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
                <li><?php echo \esc_html(\__('Enable Turnstile protection for WordPress, WooCommerce, or Elementor forms.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
                <li><?php echo \esc_html(\__('Customize the appearance and behavior to match your site.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
            </ol>

            <h2><?php echo \esc_html(\__('Useful Links', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></h2>
            <ul>
                <li><a href="<?php echo \esc_url(\admin_url('options-general.php?page=kitgenix-captcha-for-cloudflare-turnstile')); ?>" class="button button-primary"><?php echo \esc_html(\__('Go to Settings', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></a></li>
                <li><a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer"><?php echo \esc_html(\__('Cloudflare Dashboard', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></a></li>
            </ul>
        </div>
        <?php
    }
}
