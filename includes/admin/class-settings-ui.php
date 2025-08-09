<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

use function add_action;
use function add_options_page;
use function current_user_can;
use function apply_filters;
use function get_option;
use function settings_fields;
use function wp_nonce_field;
use function checked;
use function selected;
use function esc_attr;
use function esc_attr__;
use function esc_html__;
use function esc_textarea;
use function submit_button;
use function in_array;
use function defined;
use function __;
use function settings_errors;

defined('ABSPATH') || exit;

class Settings_UI {
    /**
     * Initialize admin menu and page rendering.
     */
    public static function init() {
        \add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    /**
     * Register the plugin settings page.
     */
    public static function register_menu() {
        \add_options_page(
            \__('Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile'),
            \__('Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile'),
            'manage_options',
            'kitgenix-captcha-for-cloudflare-turnstile',
            [__CLASS__, 'render_page']
        );
        // Enqueue Turnstile API script only on this admin page
        \add_action('admin_enqueue_scripts', function($hook) {
            if (strpos($hook, 'kitgenix-captcha-for-cloudflare-turnstile') === false) return;
            $settings = Admin_Options::get_settings();
            $site_key = esc_attr($settings['site_key'] ?? '');
            if (!$site_key) return;
            $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=KitgenixCaptchaForCloudflareTurnstileAdminTurnstileReady&render=explicit';
            if (!empty($settings['language']) && $settings['language'] !== 'auto') {
                $url .= '&hl=' . esc_attr($settings['language']);
            }
            wp_enqueue_script('kitgenix-captcha-for-cloudflare-turnstile-admin-turnstile', $url, [], defined('KitgenixCaptchaForCloudflareTurnstileVERSION') ? KitgenixCaptchaForCloudflareTurnstileVERSION : null, true);
        });
    }

    /**
     * Render the settings page.
     */
    public static function render_page() {
        if (!\current_user_can('manage_options')) {
            return;
        }

        // Remove default success notice WordPress adds automatically
        add_filter('get_settings_errors', function ($errors) {
            return array_filter($errors, function ($error) {
                return $error['code'] !== 'settings_updated';
            });
        });

        $settings = Admin_Options::get_settings();
        $active_plugins = \apply_filters('active_plugins', \get_option('active_plugins', []));

        // Inline script for Turnstile widget rendering (admin only)
        $site_key = $settings['site_key'] ?? '';
        $theme = $settings['theme'] ?? 'auto';
        $size = $settings['widget_size'] ?? 'normal';
        $appearance = $settings['appearance'] ?? 'always';
        if ( function_exists('wp_add_inline_script') ) {
            \wp_add_inline_script(
                'kitgenix-captcha-for-cloudflare-turnstile-admin-turnstile',
                'window.KitgenixCaptchaForCloudflareTurnstileAdminTurnstileReady = function(){' .
                  'var el=document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-widget");' .
                  'if(el && typeof turnstile!=="undefined" && !el.dataset.rendered){' .
                    'turnstile.render(el,{' .
                      'sitekey:' . \wp_json_encode( $site_key ) . ',' .
                      'theme:'   . \wp_json_encode( $theme ) . ',' .
                      'size:'    . \wp_json_encode( $size ) . ',' .
                      'appearance:' . \wp_json_encode( $appearance ) . ',' .
                      'callback:function(token){var ok=document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-success"); if(ok){ok.style.display="block";}}' .
                    '});' .
                    'el.dataset.rendered="true";' .
                  '}' .
                '};'
            );
        }
        ?>
        <div class="wrap" id="kitgenix-captcha-for-cloudflare-turnstile-admin-app">
            <div class="kitgenix-captcha-for-cloudflare-turnstile-settings-intro">
                <h1 class="kitgenix-captcha-for-cloudflare-turnstile-admin-title"><?php echo \esc_html( \__( 'Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h1>
                <p>Seamlessly integrate Cloudflare’s free Turnstile CAPTCHA into your WordPress forms to enhance security and reduce spam – without compromising user experience.</p>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-intro-links">
                    <a href="https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation/" target="_blank" rel="noopener"><?php echo \esc_html( \__( 'View Plugin Documentation', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                    <a href="https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/reviews/#new-post" target="_blank" rel="noopener"><?php echo \esc_html( \__( 'Consider Leaving Us a Review', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                    <a href="https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/" target="_blank" rel="noopener"><?php echo \esc_html( \__( 'Get Support', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                    <a href="https://kitgenix.com/plugins/support-us/" target="_blank" rel="noopener"><?php echo \esc_html( \__( 'Buy us a coffee', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                </div>
            </div>
            <form method="post" action="options.php" autocomplete="off" novalidate>
                <?php \settings_fields('kitgenix_captcha_for_cloudflare_turnstile_settings_group'); ?>
                <?php \wp_nonce_field('kitgenix_captcha_for_cloudflare_turnstile_settings_save', 'kitgenix_captcha_for_cloudflare_turnstile_settings_nonce'); ?>

                <!-- Site Keys (no accordion, now at top) -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                  <h2>Cloudflare Turnstile Site Key & Secret Key</h2>
                  <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                    <p style="margin-bottom:18px;font-size:1.08em;max-width:100%;">
                      You can obtain your Site Key and Secret Key by visiting the following Cloudflare Turnstile dashboard link:<br>
                      <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener" style="color:var(--jt-accent);font-weight:600;">https://dash.cloudflare.com/?to=/:account/turnstile</a>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th><label for="site_key">Site Key</label></th>
                            <td><input type="text" id="site_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[site_key]" value="<?php echo \esc_attr($settings['site_key'] ?? ''); ?>" class="regular-text" required autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="secret_key">Secret Key</label></th>
                            <td><input type="text" id="secret_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key]" value="<?php echo \esc_attr($settings['secret_key'] ?? ''); ?>" class="regular-text" required autocomplete="off" />
                            </td>
                        </tr>
                    </table>

                    <!-- Test Cloudflare Turnstile Response Section (moved here) -->
                    <table class="form-table" style="margin-top:28px;">
                      <tr>
                        <th scope="row" style="vertical-align:top;"><label style="margin-bottom:10px; margin-top:0;">Test Cloudflare Turnstile Response</label></th>
                        <td>
                          <div id="kitgenix-captcha-for-cloudflare-turnstile-test-widget" class="cf-turnstile" data-sitekey="<?php echo esc_attr($settings['site_key'] ?? ''); ?>" data-theme="<?php echo esc_attr($settings['theme'] ?? 'auto'); ?>" data-size="<?php echo esc_attr($settings['widget_size'] ?? 'normal'); ?>" data-appearance="<?php echo esc_attr($settings['appearance'] ?? 'always'); ?>"></div>
                          <div id="kitgenix-captcha-for-cloudflare-turnstile-test-success" style="display:none;">
                              <?php echo esc_html__('Success! Your API keys are valid and Turnstile is functioning correctly.', 'kitgenix-captcha-for-cloudflare-turnstile'); ?>
                          </div>
                          <?php if (empty($settings['site_key'])): ?>
                              <div class="kitgenix-captcha-for-cloudflare-turnstile-warning"><?php echo esc_html__('Enter your Site Key above to test Turnstile.', 'kitgenix-captcha-for-cloudflare-turnstile'); ?></div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    </table>
                  </div>
                </div>

                <!-- Display Settings -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                  <h2>Display Settings</h2>
                  <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                    <table class="form-table">
                        <tr>
                            <th><label for="theme"><?php echo \esc_html( \__( 'Theme', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td>
                                <select id="theme" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]">
                                    <option value="auto" <?php selected($settings['theme'] ?? '', 'auto'); ?>><?php echo \esc_html( \__( 'Auto', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="light" <?php selected($settings['theme'] ?? '', 'light'); ?>><?php echo \esc_html( \__( 'Light', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="dark" <?php selected($settings['theme'] ?? '', 'dark'); ?>><?php echo \esc_html( \__( 'Dark', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                </select>
                                <div class="description" style="margin-top:6px;color:#555;font-size:0.97em;">
                                    <?php echo \esc_html( \__( 'Select the visual style for the Turnstile widget to match your site\'s design.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="widget_size"><?php echo \esc_html( \__( 'Widget Size', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td>
                                <select id="widget_size" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]">
                                    <option value="normal" <?php selected($settings['widget_size'] ?? '', 'normal'); ?>><?php echo \esc_html( \__( 'Normal', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="small" <?php selected($settings['widget_size'] ?? '', 'small'); ?>><?php echo \esc_html( \__( 'Small', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="medium" <?php selected($settings['widget_size'] ?? '', 'medium'); ?>><?php echo \esc_html( \__( 'Medium', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="large" <?php selected($settings['widget_size'] ?? '', 'large'); ?>><?php echo \esc_html( \__( 'Large', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                </select>
                                <div class="description" style="margin-top:6px;color:#555;font-size:0.97em;">
                                    <?php echo \esc_html( \__( 'Define the display size of the Turnstile widget (e.g., normal, small, medium or large) to best fit your form layout.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="appearance"><?php echo \esc_html( \__( 'Appearance Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td>
                                <select id="appearance" name="kitgenix_captcha_for_cloudflare_turnstile_settings[appearance]">
                                    <option value="always" <?php selected($settings['appearance'] ?? '', 'always'); ?>><?php echo \esc_html( \__( 'Always', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="interaction-only" <?php selected($settings['appearance'] ?? '', 'interaction-only'); ?>><?php echo \esc_html( \__( 'Interaction Only', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                </select>
                                <div class="description" style="margin-top:6px;color:#555;font-size:0.97em;">
                                    <?php echo \esc_html( \__( 'Control how the Turnstile widget is rendered visually (e.g., always visible or context-aware).', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="language"><?php echo \esc_html( \__( 'Language', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td>
                                <select id="language" name="kitgenix_captcha_for_cloudflare_turnstile_settings[language]">
                                    <option value="auto" <?php selected($settings['language'] ?? '', 'auto'); ?>><?php echo \esc_html( \__( 'Auto (Detect)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="en" <?php selected($settings['language'] ?? '', 'en'); ?>><?php echo \esc_html( \__( 'English', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="es" <?php selected($settings['language'] ?? '', 'es'); ?>><?php echo \esc_html( \__( 'Español (Spanish)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="fr" <?php selected($settings['language'] ?? '', 'fr'); ?>><?php echo \esc_html( \__( 'Français (French)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="de" <?php selected($settings['language'] ?? '', 'de'); ?>><?php echo \esc_html( \__( 'Deutsch (German)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="it" <?php selected($settings['language'] ?? '', 'it'); ?>><?php echo \esc_html( \__( 'Italiano (Italian)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="pt" <?php selected($settings['language'] ?? '', 'pt'); ?>><?php echo \esc_html( \__( 'Português (Portuguese)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="ru" <?php selected($settings['language'] ?? '', 'ru'); ?>><?php echo \esc_html( \__( 'Русский (Russian)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="zh-CN" <?php selected($settings['language'] ?? '', 'zh-CN'); ?>><?php echo \esc_html( \__( '简体中文 (Chinese Simplified)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="zh-TW" <?php selected($settings['language'] ?? '', 'zh-TW'); ?>><?php echo \esc_html( \__( '繁體中文 (Chinese Traditional)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="ja" <?php selected($settings['language'] ?? '', 'ja'); ?>><?php echo \esc_html( \__( '日本語 (Japanese)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="ko" <?php selected($settings['language'] ?? '', 'ko'); ?>><?php echo \esc_html( \__( '한국어 (Korean)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="ar" <?php selected($settings['language'] ?? '', 'ar'); ?>><?php echo \esc_html( \__( 'العربية (Arabic)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="tr" <?php selected($settings['language'] ?? '', 'tr'); ?>><?php echo \esc_html( \__( 'Türkçe (Turkish)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="pl" <?php selected($settings['language'] ?? '', 'pl'); ?>><?php echo \esc_html( \__( 'Polski (Polish)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="nl" <?php selected($settings['language'] ?? '', 'nl'); ?>><?php echo \esc_html( \__( 'Nederlands (Dutch)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="sv" <?php selected($settings['language'] ?? '', 'sv'); ?>><?php echo \esc_html( \__( 'Svenska (Swedish)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="fi" <?php selected($settings['language'] ?? '', 'fi'); ?>><?php echo \esc_html( \__( 'Suomi (Finnish)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="da" <?php selected($settings['language'] ?? '', 'da'); ?>><?php echo \esc_html( \__( 'Dansk (Danish)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="no" <?php selected($settings['language'] ?? '', 'no'); ?>><?php echo \esc_html( \__( 'Norsk (Norwegian)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="cs" <?php selected($settings['language'] ?? '', 'cs'); ?>><?php echo \esc_html( \__( 'Čeština (Czech)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="hu" <?php selected($settings['language'] ?? '', 'hu'); ?>><?php echo \esc_html( \__( 'Magyar (Hungarian)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="el" <?php selected($settings['language'] ?? '', 'el'); ?>><?php echo \esc_html( \__( 'Ελληνικά (Greek)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="he" <?php selected($settings['language'] ?? '', 'he'); ?>><?php echo \esc_html( \__( 'עברית (Hebrew)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="uk" <?php selected($settings['language'] ?? '', 'uk'); ?>><?php echo \esc_html( \__( 'Українська (Ukrainian)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="ro" <?php selected($settings['language'] ?? '', 'ro'); ?>><?php echo \esc_html( \__( 'Română (Romanian)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="bg" <?php selected($settings['language'] ?? '', 'bg'); ?>><?php echo \esc_html( \__( 'Български (Bulgarian)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="id" <?php selected($settings['language'] ?? '', 'id'); ?>><?php echo \esc_html( \__( 'Bahasa Indonesia', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="th" <?php selected($settings['language'] ?? '', 'th'); ?>><?php echo \esc_html( \__( 'ไทย (Thai)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                    <option value="vi" <?php selected($settings['language'] ?? '', 'vi'); ?>><?php echo \esc_html( \__( 'Tiếng Việt (Vietnamese)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
                                </select>
                                <div class="description" style="margin-top:6px;color:#555;font-size:0.97em;">
                                    <?php echo \esc_html( \__( 'Choose the language for the Turnstile interface.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="defer_scripts"><?php echo \esc_html( \__( 'Defer Turnstile Script', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td><input type="checkbox" id="defer_scripts" name="kitgenix_captcha_for_cloudflare_turnstile_settings[defer_scripts]" value="1" <?php checked(!empty($settings['defer_scripts'])); ?> /> <span class="description"><?php echo \esc_html( \__( 'When enabled, the plugin\'s JavaScript files will be deferred to improve page load performance. Disable this option if it conflicts with other performance or optimization plugins.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="disable_submit"><?php echo \esc_html( \__( 'Disable Submit Button', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td><input type="checkbox" id="disable_submit" name="kitgenix_captcha_for_cloudflare_turnstile_settings[disable_submit]" value="1" <?php checked(!empty($settings['disable_submit'])); ?> />
                                <span class="description">When enabled, the form’s submit button will remain inactive until the Turnstile challenge is successfully completed, ensuring proper verification before submission.</span>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="error_message"><?php echo \esc_html( \__( 'Custom Error Message', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td>
                                <input type="text" id="error_message" name="kitgenix_captcha_for_cloudflare_turnstile_settings[error_message]" value="<?php echo esc_attr($settings['error_message'] ?? ''); ?>" class="regular-text" />
                                <div class="description" style="margin-top:6px;color:#555;font-size:0.97em;">
                                    <?php echo \esc_html( \__( 'This message is displayed if the form is submitted without completing the Turnstile challenge. Leave blank to use the default localized message: “Please verify that you are human.”', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="extra_message"><?php echo \esc_html( \__( 'Extra Failure Message', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                            <td>
                                <input type="text" id="extra_message" name="kitgenix_captcha_for_cloudflare_turnstile_settings[extra_message]" value="<?php echo esc_attr($settings['extra_message'] ?? ''); ?>" class="regular-text" />
                                <div class="description" style="margin-top:6px;color:#555;font-size:0.97em;">
                                    <?php echo \esc_html( \__( 'This message appears below the Turnstile widget when a user receives a “Failure!” response. It’s helpful for providing additional guidance in the rare event that a valid user is mistakenly flagged as spam.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                  </div>
                </div>

                <!-- Whitelist -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Whitelist Settings</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="whitelist_loggedin"><?php echo esc_html( __( 'Skip for Logged-in Users', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="whitelist_loggedin" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_loggedin]" value="1" <?php checked(!empty($settings['whitelist_loggedin'])); ?> />
                                    <span class="description" style="margin-left:8px;"><?php echo esc_html( __( 'When enabled, users who are logged in to your site will be exempt from the Turnstile challenge.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whitelist_ips"><?php echo esc_html( __( 'IP Address Whitelist', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <textarea id="whitelist_ips" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_ips]" rows="2" class="large-text code"><?php echo esc_textarea($settings['whitelist_ips'] ?? ''); ?></textarea><br />
                                    <span class="description">Enter one IP address per line. Visitors from these IP addresses will bypass the Turnstile verification.<br />
                                    <span style='color:#b91c1c;font-weight:600;'>⚠️ Caution: IP spoofing is possible. If an attacker obtains a whitelisted IP address, they may be able to bypass the challenge.</span></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whitelist_user_agents"><?php echo esc_html( __( 'User Agent Whitelist', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <textarea id="whitelist_user_agents" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_user_agents]" rows="2" class="large-text code"><?php echo esc_textarea($settings['whitelist_user_agents'] ?? ''); ?></textarea><br />
                                    <span class="description">Enter one User Agent per line. Visitors using a listed User Agent will bypass the Turnstile challenge.<br />
                                    <span style='color:#b91c1c;font-weight:600;'>⚠️ Caution: User Agents can be spoofed. Only use this option if you understand the risks.</span></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- WordPress Integration -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>WordPress Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_wordpress"><?php echo esc_html( __( 'Enable for WordPress Core Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_wordpress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wordpress]" value="1" <?php checked(!empty($settings['enable_wordpress'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WordPress login, registration, password reset, and comment forms. (Select exact forms separately below.)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                        <table class="form-table">
                            <tr>
                                <th><label for="wp_login_form"><?php echo esc_html( __( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wp_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_login_form]" value="1" <?php checked(!empty($settings['wp_login_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WordPress login form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wp_register_form"><?php echo esc_html( __( 'Registration Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wp_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_register_form]" value="1" <?php checked(!empty($settings['wp_register_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WordPress registration form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wp_lostpassword_form"><?php echo esc_html( __( 'Password Reset Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wp_lostpassword_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_lostpassword_form]" value="1" <?php checked(!empty($settings['wp_lostpassword_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WordPress password reset form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wp_comments_form"><?php echo esc_html( __( 'Comments Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wp_comments_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_comments_form]" value="1" <?php checked(!empty($settings['wp_comments_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WordPress comments form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <!-- WooCommerce Integration -->
                <?php $is_wc_active = in_array('woocommerce/woocommerce.php', $active_plugins, true); ?>
                <?php if ($is_wc_active): ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>WooCommerce Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_woocommerce"><?php echo esc_html( __( 'Enable for WooCommerce Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_woocommerce" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_woocommerce]" value="1" <?php checked(!empty($settings['enable_woocommerce'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WooCommerce checkout, login, registration, and password reset forms. (Select exact forms separately below.)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                        <table class="form-table">
                            <tr>
                                <th><label for="wc_checkout_form"><?php echo esc_html( __( 'Checkout Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wc_checkout_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_checkout_form]" value="1" <?php checked(!empty($settings['wc_checkout_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WooCommerce checkout form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wc_login_form"><?php echo esc_html( __( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wc_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_login_form]" value="1" <?php checked(!empty($settings['wc_login_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WooCommerce login form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wc_register_form"><?php echo esc_html( __( 'Registration Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wc_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_register_form]" value="1" <?php checked(!empty($settings['wc_register_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WooCommerce registration form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wc_lostpassword_form"><?php echo esc_html( __( 'Password Reset Form', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="wc_lostpassword_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_lostpassword_form]" value="1" <?php checked(!empty($settings['wc_lostpassword_form'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on WooCommerce password reset form.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Elementor Integration -->
                <?php if (defined('ELEMENTOR_VERSION')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Elementor Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_elementor"><?php echo esc_html( __( 'Enable for Elementor Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_elementor" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_elementor]" value="1" <?php checked(!empty($settings['enable_elementor'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Elementor forms and popups.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- WPForms Integration -->
                <?php if (class_exists('WPForms')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>WPForms Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_wpforms"><?php echo esc_html( __( 'Enable for WPForms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_wpforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wpforms]" value="1" <?php checked(!empty($settings['enable_wpforms'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all WPForms forms.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Fluent Forms Integration -->
                <?php if (defined('FLUENTFORM') || class_exists('FluentForm')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Fluent Forms Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_fluentforms"><?php echo esc_html( __( 'Enable for Fluent Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_fluentforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_fluentforms]" value="1" <?php checked(!empty($settings['enable_fluentforms'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Fluent Forms forms.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Gravity Forms Integration -->
                <?php if (class_exists('GFForms')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Gravity Forms Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_gravityforms"><?php echo esc_html( __( 'Enable for Gravity Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_gravityforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_gravityforms]" value="1" <?php checked(!empty($settings['enable_gravityforms'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Gravity Forms forms.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Formidable Forms Integration -->
                <?php if (class_exists('FrmForm')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Formidable Forms Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_formidableforms"><?php echo esc_html( __( 'Enable for Formidable Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_formidableforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_formidableforms]" value="1" <?php checked(!empty($settings['enable_formidableforms'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Formidable Forms forms.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Contact Form 7 Integration Toggle -->
                <?php if (in_array('contact-form-7/wp-contact-form-7.php', $active_plugins, true) || defined('WPCF7_VERSION')): ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Contact Form 7 Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_cf7"><?php echo esc_html( __( 'Enable for Contact Form 7', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_cf7" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_cf7]" value="1" <?php checked(!empty($settings['enable_cf7'])); ?> />
                                <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Contact Form 7 forms..', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Forminator Integration -->
                <?php if (class_exists('Forminator')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Forminator Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_forminator"><?php echo esc_html( __( 'Enable for Forminator Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_forminator" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_forminator]" value="1" <?php checked(!empty($settings['enable_forminator'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Forminator forms.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Jetpack Forms Integration -->
                <?php if (class_exists('Jetpack')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Jetpack Forms Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_jetpackforms"><?php echo esc_html( __( 'Enable for Jetpack Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_jetpackforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_jetpackforms]" value="1" <?php checked(!empty($settings['enable_jetpackforms'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Jetpack Forms.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Kadence Forms Integration -->
                <?php if (class_exists('Kadence_Blocks_Form')) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card">
                    <h2>Kadence Forms Integration</h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_kadenceforms"><?php echo esc_html( __( 'Enable for Kadence Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td><input type="checkbox" id="enable_kadenceforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_kadenceforms]" value="1" <?php checked(!empty($settings['enable_kadenceforms'])); ?> />
                                    <span class="description"><?php echo esc_html( __( 'Enable Turnstile on all Kadence Forms (Kadence Blocks).', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Support Us Section -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-settings-intro" style="margin-top: 0; margin-bottom: 24px;">
                    <h2 style="font-size:1.3em; font-weight:700; margin-bottom:6px;">Support Active Development</h2>
                    <p style="margin-bottom:8px; font-size:1.05em; max-width:100%;">
                        If you find Kitgenix CAPTCHA for Cloudflare Turnstile useful, please consider buying us a coffee! Your support helps us maintain and actively develop this plugin for the WordPress community.
                    </p>
                    <a href="https://kitgenix.com/plugins/support-us/" target="_blank" rel="noopener" style="color:var(--jt-accent);font-weight:600;">☕ Buy us a coffee</a>
                </div>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-save-row">
                    <?php submit_button(__('Save Settings', 'kitgenix-captcha-for-cloudflare-turnstile'), 'primary', 'submit', false, ['style' => 'min-width:160px;font-size:17px;']); ?>
                </div>
            </form>
        </div>
    <?php
    }
}