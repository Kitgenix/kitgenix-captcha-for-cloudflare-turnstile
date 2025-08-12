<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

defined('ABSPATH') || exit;

/**
 * Onboarding / Welcome flow shown right after activation.
 * - Redirects once to a hidden page.
 * - Explains where the widget renders, based on toggles.
 * - Links to Settings, Site Health, and Cloudflare.
 */
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
     * Pattern: main plugin sets a transient in register_activation_hook(),
     * then we redirect here once.
     */
    public static function maybe_redirect_to_setup() {
        if ( ! \get_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect') ) {
            return;
        }
        \delete_transient('kitgenix_captcha_for_cloudflare_turnstile_do_activation_redirect');

        // Do NOT redirect in these contexts
        if ( \wp_doing_ajax() ) { return; }
        if ( \defined('REST_REQUEST') && REST_REQUEST ) { return; }
        if ( \defined('WP_CLI') && WP_CLI ) { return; }
        if ( \is_network_admin() ) { return; }
        if ( isset($_GET['activate-multi']) ) { return; } // bulk or multisite activations
        if ( ! \current_user_can('manage_options') ) { return; }

        // Redirect once to our hidden onboarding page, with a one-time nonce
        $url = \add_query_arg(
            [
                '_kitgenix_captcha_for_cloudflare_turnstile_onb' => \wp_create_nonce('kitgenix_captcha_for_cloudflare_turnstile_onboarding_redirect'),
            ],
            \admin_url('options-general.php?page=kitgenix-captcha-for-cloudflare-turnstile-onboarding')
        );
        \wp_safe_redirect($url);
        exit;
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
     * We verify the nonce if present (from the post-activation redirect),
     * but do not require it to view the page directly.
     */
    public static function render_onboarding_page() {
        if ( ! \current_user_can('manage_options') ) {
            \wp_die(
                \esc_html__('Sorry, you are not allowed to access this page.', 'kitgenix-captcha-for-cloudflare-turnstile'),
                '',
                ['response' => 403]
            );
        }

        $nonce_ok = false;
        if ( isset($_GET['_kitgenix_captcha_for_cloudflare_turnstile_onb']) ) {
            $nonce = \sanitize_text_field( \wp_unslash( $_GET['_kitgenix_captcha_for_cloudflare_turnstile_onb'] ) );
            $nonce_ok = \wp_verify_nonce($nonce, 'kitgenix_captcha_for_cloudflare_turnstile_onboarding_redirect');
        }

        $settings = class_exists(\KitgenixCaptchaForCloudflareTurnstile\Admin\Admin_Options::class)
            ? \KitgenixCaptchaForCloudflareTurnstile\Admin\Admin_Options::get_settings()
            : [];

        $has_keys = !empty($settings['site_key']) && !empty($settings['secret_key']);

        // Build a human guide of where the widget will render based on toggles.
        $where = self::build_where_list($settings);
        ?>
        <div class="wrap" id="kitgenix-captcha-for-cloudflare-turnstile-admin-app">
            <h1><?php echo \esc_html(\__('Welcome to Kitgenix CAPTCHA for Cloudflare Turnstile 🎉', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></h1>

            <?php if (!$nonce_ok): ?>
                <div class="notice notice-info" style="margin:12px 0;">
                    <p><?php echo \esc_html(\__('You can revisit this onboarding page anytime via the Settings screen.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$has_keys): ?>
                <div class="notice notice-warning" style="margin:12px 0;">
                    <p><?php echo \esc_html(\__('Almost there! Add your Site Key and Secret Key to start protecting your forms.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-success" style="margin:12px 0;">
                    <p><?php echo \esc_html(\__('Great! Your keys are saved. Next, confirm where you’d like Turnstile to appear.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></p>
                </div>
            <?php endif; ?>

            <p><?php echo \esc_html(\__('Thanks for installing Kitgenix CAPTCHA for Cloudflare Turnstile! Enjoy modern, privacy-friendly bot protection with minimal friction for real users.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></p>

            <h2 style="margin-top:18px;"><?php echo \esc_html(\__('Where Turnstile will render', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></h2>
            <p style="max-width:720px;"><?php echo \esc_html(\__('Based on your current settings, the widget will automatically be added to the following places:', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></p>
            <?php if (!empty($where)) : ?>
                <ul style="margin-left:18px;list-style:disc;">
                    <?php foreach ($where as $line) : ?>
                        <li><?php echo \esc_html($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php echo \esc_html(\__('No integrations are enabled yet. Open Settings to pick where Turnstile should appear.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></p>
            <?php endif; ?>

            <h2 style="margin-top:20px;"><?php echo \esc_html(\__('Getting started', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></h2>
            <ol>
                <li><?php echo \esc_html(\__('Open Settings and enter your Site Key & Secret Key.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
                <li><?php echo \esc_html(\__('Enable Turnstile for WordPress Core forms, WooCommerce, and/or your preferred form plugins.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
                <li><?php echo \esc_html(\__('Save, then visit a protected form (e.g., Login or Checkout) in a private window to verify the widget appears.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
            </ol>

            <h2 style="margin-top:20px;"><?php echo \esc_html(\__('Helpful tools & checks', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></h2>
            <ul>
                <li><?php echo \esc_html(\__('Site Health → Status includes a “Cloudflare Turnstile readiness” test (keys present, script loaded once, last verification status, and caching plugin hints).', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
                <li><?php echo \esc_html(\__('We detect duplicate Turnstile API loaders and show an admin notice to help avoid double-loading conflicts.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
                <li><?php echo \esc_html(\__('Tokens auto-expire after a short period of inactivity; users will see a clear “Expired—please verify again” message if needed.', 'kitgenix-captcha-for-cloudflare-turnstile')); ?></li>
            </ul>

            <p style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
                <a href="<?php echo \esc_url(\admin_url('options-general.php?page=kitgenix-captcha-for-cloudflare-turnstile')); ?>" class="button button-primary">
                    <?php echo \esc_html(\__('Go to Settings', 'kitgenix-captcha-for-cloudflare-turnstile')); ?>
                </a>
                <a href="<?php echo \esc_url(\admin_url('site-health.php')); ?>" class="button button-secondary">
                    <?php echo \esc_html(\__('Open Site Health', 'kitgenix-captcha-for-cloudflare-turnstile')); ?>
                </a>
                <a href="<?php echo \esc_url('https://dash.cloudflare.com/?to=/:account/turnstile'); ?>" target="_blank" rel="noopener noreferrer" class="button button-link">
                    <?php echo \esc_html(\__('Cloudflare Turnstile Dashboard', 'kitgenix-captcha-for-cloudflare-turnstile')); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Build a simple list of sentences describing where Turnstile will render,
     * based on current settings toggles. Purely informational for onboarding.
     *
     * @param array $settings
     * @return array<string>
     */
    private static function build_where_list(array $settings): array {
        $out = [];

        // WordPress core forms
        if ( ! empty($settings['enable_wordpress']) ) {
            $wp_targets = [];
            if ( ! empty($settings['wp_login_form']) ) {
                // translators: %s is a location name, e.g. "Login"
                $wp_targets[] = \sprintf( \__('Login', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }
            if ( ! empty($settings['wp_register_form']) ) {
                $wp_targets[] = \sprintf( \__('Register', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }
            if ( ! empty($settings['wp_lostpassword_form']) ) {
                $wp_targets[] = \sprintf( \__('Lost password / Reset password', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }
            if ( ! empty($settings['wp_comments_form']) ) {
                $wp_targets[] = \sprintf( \__('Comments', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }

            if (!empty($wp_targets)) {
                /* translators: %s: comma-separated list of WordPress form locations */
                $out[] = \sprintf( \__('WordPress Core: %s', 'kitgenix-captcha-for-cloudflare-turnstile'), implode(', ', $wp_targets) );
            }
        }

        // WooCommerce
        if ( ! empty($settings['enable_woocommerce']) ) {
            $wc_targets = [];
            if ( ! empty($settings['wc_checkout_form']) ) {
                $wc_targets[] = \sprintf( \__('Checkout (classic & Blocks)', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }
            if ( ! empty($settings['wc_login_form']) ) {
                $wc_targets[] = \sprintf( \__('My Account: Login', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }
            if ( ! empty($settings['wc_register_form']) ) {
                $wc_targets[] = \sprintf( \__('My Account: Register', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }
            if ( ! empty($settings['wc_lostpassword_form']) ) {
                $wc_targets[] = \sprintf( \__('My Account: Lost/Reset password', 'kitgenix-captcha-for-cloudflare-turnstile') );
            }
            if (!empty($wc_targets)) {
                /* translators: %s: comma-separated list of WooCommerce locations */
                $out[] = \sprintf( \__('WooCommerce: %s', 'kitgenix-captcha-for-cloudflare-turnstile'), implode(', ', $wc_targets) );
            }
        }

        // Elementor
        if ( ! empty($settings['enable_elementor']) ) {
            $out[] = \__('Elementor Forms: widget container injected near the submit button.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // WPForms
        if ( ! empty($settings['enable_wpforms']) ) {
            $out[] = \__('WPForms: widget container added before submit; server-side validation active.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // Fluent Forms
        if ( ! empty($settings['enable_fluentforms']) ) {
            $out[] = \__('Fluent Forms: token & container inserted before submit; AJAX-friendly validation.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // Gravity Forms
        if ( ! empty($settings['enable_gravityforms']) ) {
            $out[] = \__('Gravity Forms: widget injected before submit; top-level validation message on fail.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // CF7
        if ( ! empty($settings['enable_cf7']) ) {
            $out[] = \__('Contact Form 7: hidden token + container placed before the first submit control.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // Formidable Forms
        if ( ! empty($settings['enable_formidableforms']) ) {
            $out[] = \__('Formidable Forms: widget prepended before the submit button.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // Forminator
        if ( ! empty($settings['enable_forminator']) ) {
            $out[] = \__('Forminator: widget wrapped with submit area; AJAX steps re-signaled for rendering.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // Jetpack Forms
        if ( ! empty($settings['enable_jetpackforms']) ) {
            $out[] = \__('Jetpack Forms: container injected into the form HTML; WP_Error returned on failure.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        // Kadence Forms
        if ( ! empty($settings['enable_kadenceforms']) ) {
            $out[] = \__('Kadence Forms: widget prepended before the submit button; error surfaced inline.', 'kitgenix-captcha-for-cloudflare-turnstile');
        }

        return $out;
    }
}
