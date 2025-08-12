<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

defined('ABSPATH') || exit;

class Settings_Transfer {

    const ACTION_EXPORT = 'kitgenix_turnstile_export';
    const ACTION_IMPORT = 'kitgenix_turnstile_import';

    public static function init() {
        \add_action('admin_post_' . self::ACTION_EXPORT, [__CLASS__, 'handle_export']);
        \add_action('admin_post_' . self::ACTION_IMPORT, [__CLASS__, 'handle_import']);
        \add_action('admin_notices', [__CLASS__, 'admin_notices']);
    }

    /**
     * Export current settings as a JSON download.
     */
    public static function handle_export() {
        if ( ! \current_user_can('manage_options') ) {
            \wp_die(
                \esc_html__('You do not have permission to export settings.', 'kitgenix-captcha-for-cloudflare-turnstile'),
                '',
                ['response' => 403]
            );
        }

        $nonce = isset($_POST['_wpnonce']) ? (string) \wp_unslash($_POST['_wpnonce']) : '';
        if ( ! $nonce || ! \wp_verify_nonce($nonce, self::ACTION_EXPORT) ) {
            \wp_die(
                \esc_html__('Invalid request (nonce).', 'kitgenix-captcha-for-cloudflare-turnstile'),
                '',
                ['response' => 400]
            );
        }

        $include_secret = ! empty($_POST['include_secret']);
        $settings       = Admin_Options::get_settings();

        // Build export payload (meta + settings)
        $payload = [
            '_meta' => [
                'plugin'     => 'kitgenix-captcha-for-cloudflare-turnstile',
                'version'    => \defined('KitgenixCaptchaForCloudflareTurnstileVERSION') ? \constant('KitgenixCaptchaForCloudflareTurnstileVERSION') : 'unknown',
                'generated'  => time(),
                'site_url'   => \home_url(),
            ],
            'settings' => $settings,
        ];

        if ( ! $include_secret ) {
            // Redact sensitive values by default
            unset($payload['settings']['secret_key']);
        }

        $json     = \wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        // Use gmdate() to avoid timezone-dependent output.
        $filename = 'kitgenix-turnstile-settings-' . \gmdate('Ymd-His') . '.json';

        \nocache_headers();
        header('Content-Type: application/json; charset=' . \get_option('blog_charset'));
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Length: ' . strlen($json));
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Import settings from uploaded JSON (or pasted JSON).
     * Supports "merge" (default) or "replace".
     */
    public static function handle_import() {
        if ( ! \current_user_can('manage_options') ) {
            \wp_die(
                \esc_html__('You do not have permission to import settings.', 'kitgenix-captcha-for-cloudflare-turnstile'),
                '',
                ['response' => 403]
            );
        }

        $nonce = isset($_POST['_wpnonce']) ? (string) \wp_unslash($_POST['_wpnonce']) : '';
        if ( ! $nonce || ! \wp_verify_nonce($nonce, self::ACTION_IMPORT) ) {
            \wp_die(
                \esc_html__('Invalid request (nonce).', 'kitgenix-captcha-for-cloudflare-turnstile'),
                '',
                ['response' => 400]
            );
        }

        $redirect = \admin_url('options-general.php?page=kitgenix-captcha-for-cloudflare-turnstile');

        // Accept either file upload or pasted JSON
        $raw = '';
        $file_index = 'kitgenix_captcha_for_cloudflare_turnstile_ts_import_file';
        $tmp_name   = isset($_FILES[$file_index]['tmp_name']) ? (string) $_FILES[$file_index]['tmp_name'] : '';

        if ( $tmp_name !== '' && @is_uploaded_file($tmp_name) ) {
            // Read from the uploaded temp file
            $raw = (string) \file_get_contents($tmp_name);
        } elseif ( ! empty($_POST['kitgenix_captcha_for_cloudflare_turnstile_ts_import_text']) ) {
            $raw = (string) \wp_unslash($_POST['kitgenix_captcha_for_cloudflare_turnstile_ts_import_text']);
        }

        if ( trim($raw) === '' ) {
            \wp_safe_redirect(\add_query_arg('kitgenix_captcha_for_cloudflare_turnstile_ts_import', 'empty', $redirect));
            exit;
        }

        // Rudimentary size guard
        if ( strlen($raw) > 512 * 1024 ) { // 512KB
            \wp_safe_redirect(\add_query_arg('kitgenix_captcha_for_cloudflare_turnstile_ts_import', 'toolarge', $redirect));
            exit;
        }

        $data = json_decode($raw, true);
        if ( ! is_array($data) || empty($data['settings']) || ! is_array($data['settings']) ) {
            \wp_safe_redirect(\add_query_arg('kitgenix_captcha_for_cloudflare_turnstile_ts_import', 'invalid', $redirect));
            exit;
        }

        $mode_raw = isset($_POST['kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode'])
            ? (string) \wp_unslash($_POST['kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode'])
            : '';
        $mode = ($mode_raw === 'replace') ? 'replace' : 'merge';

        // Whitelist and sanitize imported settings using our existing sanitizer,
        // but bypass the settings form nonce by calling this dedicated helper.
        $incoming = self::sanitize_import_payload($data['settings']);

        // Merge or replace
        $current = Admin_Options::get_settings();
        $final   = ($mode === 'replace') ? $incoming : array_merge($current, $incoming);

        // Never blindly overwrite secret_key unless present in import & user allowed it explicitly
        $allow_secret = ! empty($_POST['kitgenix_captcha_for_cloudflare_turnstile_ts_allow_secret']);
        if ( ! $allow_secret ) {
            if ( array_key_exists('secret_key', $incoming) && (! isset($current['secret_key']) || $current['secret_key'] !== '') ) {
                // Keep existing secret
                $final['secret_key'] = $current['secret_key'];
            }
        }

        \update_option(Admin_Options::OPTION_NAME, $final, false);

        \wp_safe_redirect(\add_query_arg('kitgenix_captcha_for_cloudflare_turnstile_ts_import', 'success', $redirect));
        exit;
    }

    /**
     * Sanitize import payload using similar rules to settings save,
     * but without the settings form nonce requirement.
     */
    private static function sanitize_import_payload(array $settings): array {
        $clean = [];

        $clean['site_key']   = \function_exists('sanitize_text_field') ? \sanitize_text_field($settings['site_key'] ?? '') : ($settings['site_key'] ?? '');
        $clean['secret_key'] = \function_exists('sanitize_text_field') ? \sanitize_text_field($settings['secret_key'] ?? '') : ($settings['secret_key'] ?? '');

        // Integrations toggles
        foreach ([
            'enable_wordpress','enable_woocommerce','enable_elementor','enable_wpforms',
            'enable_fluentforms','enable_gravityforms','enable_cf7','enable_formidableforms',
            'enable_forminator','enable_jetpackforms',
            // WordPress sub-toggles
            'wp_login_form','wp_register_form','wp_lostpassword_form','wp_comments_form',
            // Woo sub-toggles
            'wc_checkout_form','wc_login_form','wc_register_form','wc_lostpassword_form',
            // Extra flags
            'disable_submit','whitelist_loggedin','replay_protection',
            'trust_proxy','dev_mode_warn_only'
        ] as $flag) {
            if ( array_key_exists($flag, $settings) ) {
                $clean[$flag] = (int) ! empty($settings[$flag]);
            }
        }

        // Display
        $clean['theme']        = \in_array($settings['theme'] ?? '', ['auto','light','dark'], true) ? $settings['theme'] : 'auto';
        $clean['language']     = \function_exists('sanitize_text_field') ? \sanitize_text_field($settings['language'] ?? 'auto') : ($settings['language'] ?? 'auto');
        $clean['widget_size']  = \in_array($settings['widget_size'] ?? '', ['small','medium','large','normal'], true) ? $settings['widget_size'] : 'normal';
        $clean['appearance']   = \in_array($settings['appearance'] ?? '', ['always','interaction-only'], true) ? $settings['appearance'] : 'always';

        // Messages
        foreach (['error_message','extra_message'] as $msg) {
            if ( array_key_exists($msg, $settings) ) {
                $clean[$msg] = \function_exists('sanitize_text_field') ? \sanitize_text_field($settings[$msg]) : $settings[$msg];
            }
        }

        // Back-compat: accept old key 'trusted_proxy_ips' by mapping it to 'trusted_proxies'
        if ( isset($settings['trusted_proxy_ips']) && ! isset($settings['trusted_proxies']) ) {
            $settings['trusted_proxies'] = $settings['trusted_proxy_ips'];
        }

        // Whitelist IPs / UAs / Trusted proxies (allow wildcards/CIDRs; sanitize lines)
        foreach (['whitelist_ips','whitelist_user_agents','trusted_proxies'] as $bulk) {
            if ( isset($settings[$bulk]) ) {
                $val   = (string) $settings[$bulk];
                $val   = str_replace(["\r\n", "\r"], "\n", $val);
                $lines = array_map('trim', explode("\n", $val));
                $lines = array_filter($lines, static function($v){ return $v !== ''; });

                // sanitize_text_field per line except IP-ish: keep * and / characters
                $lines = array_map(static function($line) use ($bulk) {
                    if ( $bulk === 'whitelist_user_agents' ) {
                        return \sanitize_text_field($line);
                    }
                    // keep wildcards/CIDR chars; strip tags
                    $line = \wp_kses_post($line);
                    $line = preg_replace('~[^\w\.\:\*\/\-]+~u', '', $line);
                    return trim($line);
                }, $lines);

                $clean[$bulk] = implode("\n", $lines);
            }
        }

        return $clean;
    }

    /**
     * Small admin notices helper.
     */
    public static function admin_notices() {
        if ( empty($_GET['kitgenix_captcha_for_cloudflare_turnstile_ts_import']) || ! \current_user_can('manage_options') ) {
            return;
        }
        $code_raw = isset($_GET['kitgenix_captcha_for_cloudflare_turnstile_ts_import'])
            ? (string) \wp_unslash($_GET['kitgenix_captcha_for_cloudflare_turnstile_ts_import'])
            : '';
        $code = \sanitize_text_field($code_raw);

        $map = [
            'success'  => [ 'updated',   \__('Settings imported successfully.', 'kitgenix-captcha-for-cloudflare-turnstile') ],
            'empty'    => [ 'error',     \__('No JSON provided for import.', 'kitgenix-captcha-for-cloudflare-turnstile') ],
            'toolarge' => [ 'error',     \__('Import file is too large.', 'kitgenix-captcha-for-cloudflare-turnstile') ],
            'invalid'  => [ 'error',     \__('Invalid JSON or missing "settings" object.', 'kitgenix-captcha-for-cloudflare-turnstile') ],
        ];
        if ( ! isset($map[$code]) ) {
            return;
        }

        [$class, $msg] = $map[$code];
        echo '<div class="notice notice-' . \esc_attr($class) . ' is-dismissible"><p>' . \esc_html($msg) . '</p></div>';
    }
}
