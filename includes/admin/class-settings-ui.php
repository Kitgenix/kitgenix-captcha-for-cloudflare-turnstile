<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Admin;

use KitgenixCaptchaForCloudflareTurnstile\Core\Setup_Verification;
use KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler;
use KitgenixCaptchaForCloudflareTurnstile\Core\Settings_Overrides;
use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;

defined('ABSPATH') || exit;

/**
 * Admin Settings UI
 *
 * @package KitgenixCaptchaForCloudflareTurnstile
 */

class Settings_UI {

    /**
        * The page hook suffix returned by the admin menu registration.
     *
     * @var string|null
     */
    private static $page_hook = null;

    /**
     * Initialize admin menu and page rendering.
     */
    public static function init(): void {
        \add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        // Run after core Script_Handler so we can safely add inline scripts to the real admin handle.
        \add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ], 20 );
        \add_action( 'admin_notices', [ __CLASS__, 'render_admin_notices' ] );
        // AJAX: reveal stored secret on demand (never printed in HTML by default).
        \add_action( 'wp_ajax_kitgenix_turnstile_get_secret', [ __CLASS__, 'ajax_get_secret' ] );
        \add_action( 'admin_post_kitgenix_turnstile_export_analytics', [ __CLASS__, 'handle_export_analytics' ] );
    }

    private static function is_settings_screen_now(): bool {
        if ( function_exists( '\\get_current_screen' ) ) {
            $screen = \get_current_screen();
            if ( $screen && ! empty( self::$page_hook ) && $screen->id === self::$page_hook ) {
                return true;
            }
        }

        // Fallback via `page` query arg.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : '';
        return $page === 'kitgenix-captcha-for-cloudflare-turnstile';
    }

    private static function is_operational_notice_screen(): bool {
        if ( function_exists( '\\get_current_screen' ) ) {
            $screen = \get_current_screen();
            if ( $screen ) {
                $screen_id = (string) $screen->id;
                if (
                    strpos( $screen_id, 'kitgenix-captcha-for-cloudflare-turnstile' ) !== false
                    || in_array( $screen_id, [ 'dashboard', 'plugins', 'site-health' ], true )
                ) {
                    return true;
                }
            }
        }

        return self::is_settings_screen_now();
    }

    private static function describe_key_override( array $override ): string {
        if ( empty( $override['is_overridden'] ) || empty( $override['source_name'] ) ) {
            return '';
        }

        if ( ( $override['source_type'] ?? '' ) === 'constant' ) {
            return \sprintf(
                /* translators: %s: wp-config.php constant name */
                \__( 'Managed by wp-config.php constant %s.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                $override['source_name']
            );
        }

        return \sprintf(
            /* translators: %s: environment variable name */
            \__( 'Managed by environment variable %s.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            $override['source_name']
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_operational_alerts( bool $include_duplicate_loader = true ): array {
        $alerts          = Turnstile_Validator::get_active_alerts();
        $diagnostics_url = self::get_support_diagnostics_url();

        foreach ( $alerts as &$alert ) {
            if ( empty( $alert['action_url'] ) ) {
                $alert['action_url'] = $diagnostics_url;
            }

            if ( empty( $alert['action_label'] ) ) {
                $alert['action_label'] = \__( 'Open Support diagnostics', 'kitgenix-captcha-for-cloudflare-turnstile' );
            }
        }
        unset( $alert );

        if ( $include_duplicate_loader ) {
            $duplicate_loader = Script_Handler::get_duplicate_loader_detection();
            if ( ! empty( $duplicate_loader['matches'] ) && is_array( $duplicate_loader['matches'] ) ) {
                $alerts[] = [
                    'key'         => 'duplicate-loader-conflict',
                    'severity'    => 'warning',
                    'title'       => \__( 'Duplicate Turnstile loader detected', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    'message'     => \sprintf(
                        /* translators: %s: number of duplicate loader matches */
                        _n(
                            'Turnstile API was also enqueued by %s other script in a recent frontend request. Double-loading can break widget rendering or callbacks.',
                            'Turnstile API was also enqueued by %s other scripts in a recent frontend request. Double-loading can break widget rendering or callbacks.',
                            (int) $duplicate_loader['match_count'],
                            'kitgenix-captcha-for-cloudflare-turnstile'
                        ),
                        \number_format_i18n( (int) $duplicate_loader['match_count'] )
                    ),
                    'matches'     => $duplicate_loader['matches'],
                    'detected_at' => (int) ( $duplicate_loader['when'] ?? 0 ),
                    'action_url'  => Script_Handler::get_duplicate_loader_dismiss_url(),
                    'action_label'=> \__( 'Dismiss loader notice', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                ];
            }
        }

        usort(
            $alerts,
            static function ( array $left, array $right ): int {
                $priority = [
                    'error'   => 0,
                    'warning' => 1,
                ];

                $left_priority  = $priority[ (string) ( $left['severity'] ?? 'warning' ) ] ?? 9;
                $right_priority = $priority[ (string) ( $right['severity'] ?? 'warning' ) ] ?? 9;

                if ( $left_priority !== $right_priority ) {
                    return $left_priority <=> $right_priority;
                }

                return strcmp( (string) ( $left['title'] ?? '' ), (string) ( $right['title'] ?? '' ) );
            }
        );

        return $alerts;
    }

    private static function get_support_diagnostics_url(): string {
        return (string) \admin_url( 'admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=support#section-support' );
    }

    private static function get_alert_notice_class( string $severity ): string {
        return 'error' === $severity ? 'notice-error' : 'notice-warning';
    }

    public static function render_admin_notices(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( self::is_operational_notice_screen() ) {
            foreach ( self::get_operational_alerts( false ) as $alert ) {
                $notice_class = self::get_alert_notice_class( (string) ( $alert['severity'] ?? 'warning' ) );
                echo '<div class="notice ' . \esc_attr( $notice_class ) . '"><p><strong>'
                    . \esc_html( (string) ( $alert['title'] ?? '' ) )
                    . '</strong> '
                    . \esc_html( (string) ( $alert['message'] ?? '' ) );

                if ( ! empty( $alert['action_url'] ) && ! empty( $alert['action_label'] ) ) {
                    echo ' <a href="' . \esc_url( (string) $alert['action_url'] ) . '">' . \esc_html( (string) $alert['action_label'] ) . '</a>';
                }

                echo '</p></div>';
            }
        }

        if ( ! self::is_settings_screen_now() ) {
            return;
        }

        if ( function_exists( '\\settings_errors' ) ) {
            \settings_errors();
        }

        $settings = Admin_Options::get_settings();
        if ( ! empty( $settings['dev_mode_warn_only'] ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>'
                . \esc_html__( 'Developer Mode (warn-only) is enabled.', 'kitgenix-captcha-for-cloudflare-turnstile' )
                . '</strong> '
                . \esc_html__( 'Turnstile failures will be logged but will not block form submissions.', 'kitgenix-captcha-for-cloudflare-turnstile' )
                . '</p></div>';
        }
    }

    /**
     * AJAX handler: return stored secret key (admins only).
     */
    public static function ajax_get_secret(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Forbidden', 'kitgenix-captcha-for-cloudflare-turnstile' ) ], 403 );
        }

        $override_details = Settings_Overrides::get_override_details();
        if ( ! empty( $override_details['secret_key']['is_overridden'] ) ) {
            \wp_send_json_error( [ 'message' => \__( 'The active secret key is managed outside WordPress and cannot be revealed here.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ], 403 );
        }

        $nonce = '';
        if ( isset( $_POST['nonce'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below
            $nonce = \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) );
        }
        if ( '' === $nonce ) {
            \wp_send_json_error( [ 'message' => \__( 'Invalid nonce', 'kitgenix-captcha-for-cloudflare-turnstile' ) ], 403 );
        }
        if ( ! \wp_verify_nonce( $nonce, 'kitgenix_turnstile_reveal_secret' ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Invalid nonce', 'kitgenix-captcha-for-cloudflare-turnstile' ) ], 403 );
        }

        $settings = Admin_Options::get_settings();
        $secret   = (string) ( $settings['secret_key'] ?? '' );
        if ( $secret === '' ) {
            \wp_send_json_error( [ 'message' => \__( 'No saved secret key', 'kitgenix-captcha-for-cloudflare-turnstile' ) ], 404 );
        }

        \wp_send_json_success( [ 'secret_key' => $secret ] );
    }

    public static function handle_export_analytics(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \esc_html__( 'Forbidden', 'kitgenix-captcha-for-cloudflare-turnstile' ) );
        }

        \check_admin_referer( 'kitgenix_turnstile_export_analytics' );

        $export = isset( $_GET['export'] )
            ? \sanitize_key( \wp_unslash( $_GET['export'] ) )
            : 'integrations';

        if ( 'recent-log' === $export ) {
            self::stream_csv_download(
                'kitgenix-turnstile-recent-log-' . gmdate( 'Ymd-His' ) . '.csv',
                [
                    'time',
                    'integration_key',
                    'integration_label',
                    'outcome',
                    'category',
                    'error_codes',
                    'note',
                ],
                self::build_recent_log_export_rows()
            );
        }

        self::stream_csv_download(
            'kitgenix-turnstile-integration-analytics-' . gmdate( 'Ymd-His' ) . '.csv',
            [
                'integration_key',
                'integration_label',
                'checks_total',
                'checks_passed',
                'checks_failed',
                'checks_retries',
                'friction_rate',
                'last_outcome',
                'last_checked',
                'last_error_codes',
            ],
            self::build_integration_export_rows()
        );
    }

    /**
     * Register the plugin settings page.
     */
    public static function register_menu(): void {
        if ( function_exists( '\\kitgenix_ensure_admin_menu' ) ) {
            \kitgenix_ensure_admin_menu();
        }

        self::$page_hook = \add_submenu_page(
            'kitgenix',
            \__( 'Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            \__( 'Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'manage_options',
            'kitgenix-captcha-for-cloudflare-turnstile',
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Enqueue scripts/styles only on our settings page.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public static function enqueue_assets( $hook ): void {
        if ( empty( self::$page_hook ) || $hook !== self::$page_hook ) {
            return;
        }

        $settings = Admin_Options::get_settings();
        $site_key = $settings['site_key'] ?? '';

        $ver = defined( 'KitgenixCaptchaForCloudflareTurnstile_Version' )
            ? \constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' )
            : null;
        $social_base = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            ? constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' ) . 'images/social-media/'
            : '';
        $admin_post_url = \admin_url( 'admin-post.php' );

        // Ensure our admin UI assets are present on the settings screen.
        // Normally these are enqueued by Core\Script_Handler, but we defensively register/enqueue
        // here as well (without clobbering the real handles).
        $admin_handle = 'kitgenix-captcha-for-cloudflare-turnstile-admin';
        if ( function_exists( '\wp_script_is' ) && ! \wp_script_is( $admin_handle, 'registered' ) ) {
            $base_path = defined( 'KitgenixCaptchaForCloudflareTurnstile_Path' )
                ? \trailingslashit( (string) \constant( 'KitgenixCaptchaForCloudflareTurnstile_Path' ) )
                : '';
            $base_url  = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
                ? (string) \constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
                : '';

            if ( $base_url ) {
                $admin_js_path = $base_path ? $base_path . 'assets/js/admin.js' : '';
                $js_ver = ( $admin_js_path && \file_exists( $admin_js_path ) ) ? \filemtime( $admin_js_path ) : $ver;
                \wp_register_script(
                    $admin_handle,
                    $base_url . 'js/admin.js',
                    [ 'jquery' ],
                    $js_ver,
                    true
                );
            }
        }
        if ( function_exists( '\wp_style_is' ) && ! \wp_style_is( $admin_handle, 'registered' ) ) {
            $base_path = defined( 'KitgenixCaptchaForCloudflareTurnstile_Path' )
                ? \trailingslashit( (string) \constant( 'KitgenixCaptchaForCloudflareTurnstile_Path' ) )
                : '';
            $base_url  = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
                ? (string) \constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
                : '';
            if ( $base_url ) {
                $admin_css_path = $base_path ? $base_path . 'assets/css/admin.css' : '';
                $css_ver = ( $admin_css_path && \file_exists( $admin_css_path ) ) ? \filemtime( $admin_css_path ) : $ver;
                \wp_register_style(
                    $admin_handle,
                    $base_url . 'css/admin.css',
                    [ 'kitgenix-admin-ui' ],
                    $css_ver
                );
            }
        }

        // Shared Kitgenix admin UI (tabs + baseline styles).
        $base_path = defined( 'KitgenixCaptchaForCloudflareTurnstile_Path' )
            ? \trailingslashit( (string) \constant( 'KitgenixCaptchaForCloudflareTurnstile_Path' ) )
            : '';
        $base_url  = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            ? (string) \constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            : '';

        if ( $base_url ) {
            $ui_js_path  = $base_path ? $base_path . 'assets/js/kitgenix-admin-tabs.js' : '';
            $ui_js_ver   = ( $ui_js_path && \file_exists( $ui_js_path ) ) ? \filemtime( $ui_js_path ) : $ver;

            if ( function_exists( '\wp_enqueue_style' ) ) {
                \wp_enqueue_style( 'kitgenix-admin-ui' );
            }
            if ( function_exists( '\wp_enqueue_script' ) ) {
                \wp_enqueue_script(
                    'kitgenix-admin-tabs',
                    $base_url . 'js/kitgenix-admin-tabs.js',
                    [],
                    $ui_js_ver,
                    true
                );
            }
        }

        if ( function_exists( '\wp_enqueue_style' ) ) {
            \wp_enqueue_style( $admin_handle );
        }

        // Enqueue plugin admin JS AFTER the shared UI/tab script.
        if ( function_exists( '\wp_enqueue_script' ) ) {
            \wp_enqueue_script( $admin_handle );
        }

        $theme      = $settings['theme']        ?? 'auto';
        $size       = \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string) ( $settings['widget_size'] ?? 'normal' ) );
        $appearance = $settings['appearance']   ?? 'always';

        // Attach our Turnstile test-widget callback BEFORE the API loads.
        // IMPORTANT: do not re-register the admin handle here (it would clobber the real admin.js).
        if ( function_exists( '\wp_add_inline_script' ) ) {
            \wp_add_inline_script(
                $admin_handle,
                'window.KitgenixCaptchaForCloudflareTurnstileAdminTurnstileReady = function () {' .
                    'try {' .
                        'var el = document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-widget");' .
                        'if (!el || typeof turnstile === "undefined" || el.dataset.rendered) { return; }' .
                        'var siteKey = ' . \wp_json_encode( (string) $site_key ) . ';' .
                        'if (!siteKey) { return; }' .
                        'turnstile.render(el, {' .
                            'sitekey: siteKey,' .
                            'theme: ' . \wp_json_encode( (string) $theme ) . ',' .
                            'size: ' . \wp_json_encode( (string) $size ) . ',' .
                            'appearance: ' . \wp_json_encode( (string) $appearance ) . ',' .
                            'callback: function(token){' .
                                'var ok = document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-success");' .
                                'if (ok) { ok.style.display = "block"; ok.setAttribute("aria-hidden","false"); }' .
                                'if (window.KitgenixCaptchaForCloudflareTurnstileAdminHandleToken && token) { window.KitgenixCaptchaForCloudflareTurnstileAdminHandleToken(token); }' .
                            '}' .
                        '});' .
                        'el.dataset.rendered = "true";' .
                    '} catch (e) { if (window.console) console.error(e); }' .
                '};',
                'before'
            );


            // Progressive enhancement / fallbacks for the settings UI.
            // Use wp_add_inline_script (instead of printing <script> tags in markup)
            // so Plugin Check / WP.org review tooling sees correct asset loading.
            static $kitgenix_settings_inline_added = false;
            if ( ! $kitgenix_settings_inline_added ) {
                $kitgenix_settings_inline_added = true;

                $extra_js = <<<'JS'
// Fallback logic: ensure reveal/copy buttons function even if admin.js failed.
(function(){
    function onReady(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
    onReady(function(){
        // If the full admin UI JS loaded, do not attach fallback handlers.
        if (window.KitgenixTurnstileAdminJsReady) { return; }
        var revealBtn = document.querySelector('.kitgenix-reveal-secret');
        var copyBtn   = document.querySelector('.kitgenix-copy-secret');
        var input     = document.getElementById('secret_key');
        if(revealBtn && input){
            revealBtn.addEventListener('click', function(){
                if (window.KitgenixTurnstileAdminJsReady) { return; }
                var isPw = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPw ? 'text' : 'password');
                this.setAttribute('aria-pressed', isPw ? 'true' : 'false');
                var showLabel = this.getAttribute('data-label-show') || 'Reveal secret key';
                var hideLabel = this.getAttribute('data-label-hide') || 'Hide secret key';
                this.setAttribute('aria-label', isPw ? hideLabel : showLabel);
                var showText = this.getAttribute('data-text-show') || 'Show';
                var hideText = this.getAttribute('data-text-hide') || 'Hide';
                var span = this.querySelector('.kitgenix-reveal-secret-text');
                if(span){ span.textContent = isPw ? hideText : showText; } else { this.textContent = isPw ? hideText : showText; }
            });
        }
        if(copyBtn && input){
            copyBtn.addEventListener('click', function(){
                if (window.KitgenixTurnstileAdminJsReady) { return; }
                var val = input.value || '';
                if(!val){ return; }
                function feedback(){
                    var original = copyBtn.innerHTML;
                    copyBtn.innerHTML = '✓';
                    copyBtn.setAttribute('aria-label','Copied');
                    setTimeout(function(){ copyBtn.innerHTML = original; copyBtn.setAttribute('aria-label','Copy secret key'); },1200);
                }
                if(navigator.clipboard && navigator.clipboard.writeText){
                    navigator.clipboard.writeText(val).then(feedback).catch(fallback);
                } else { fallback(); }
                function fallback(){
                    try {
                        var origType = input.getAttribute('type');
                        input.setAttribute('type','text');
                        input.select();
                        document.execCommand('copy');
                        input.setAttribute('type', origType);
                        feedback();
                    } catch(e){ /* ignore */ }
                }
            });
        }
    });
})();
JS;

                \wp_add_inline_script( $admin_handle, $extra_js, 'after' );
            }
        }

        // Load Turnstile API with onload pointing at our callback (only when we have a site key).
        if ( $site_key ) {
            $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=KitgenixCaptchaForCloudflareTurnstileAdminTurnstileReady';
            if ( ! empty( $settings['language'] ) && 'auto' !== $settings['language'] ) {
                $url .= '&hl=' . rawurlencode( (string) $settings['language'] );
            }

            \wp_enqueue_script(
                'kitgenix-captcha-for-cloudflare-turnstile-admin-api',
                $url,
                [],
                $ver,
                true
            );

            // Hint to load non-blocking on newer WP (falls back gracefully).
            if ( function_exists( '\wp_script_add_data' ) ) {
                \wp_script_add_data( 'kitgenix-captcha-for-cloudflare-turnstile-admin-api', 'strategy', 'defer' );
            }
        }
    }

    /**
     * Render a consistent shortcode row with a copy button.
     */
    private static function render_shortcode_row(): void {
        echo '<div class="kitgenix-captcha-for-cloudflare-turnstile-shortcode-row">';
        echo '<span>' . \esc_html__( 'Shortcode: ', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</span>';
        echo '<code>[kitgenix_turnstile]</code>';
        echo '<button type="button" class="button button-secondary kitgenix-captcha-for-cloudflare-turnstile-copy-shortcode" aria-label="' . \esc_attr__( 'Copy shortcode', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '">' . \esc_html__( 'Copy shortcode', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</button>';
        echo '</div>';
    }

    /**
     * Render shortcode guidance text with the shared shortcode row.
     *
     * @param string $message Guidance text shown below the shortcode row.
     */
    private static function render_shortcode_help( string $message ): void {
        self::render_shortcode_row();
        echo '<p class="description kitgenix-captcha-for-cloudflare-turnstile-mt-6">' . \esc_html( $message ) . '</p>';
    }

    /**
     * Render the settings page.
     */
    public static function render_page(): void {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = Admin_Options::get_settings();
        $admin_post_url = \admin_url( 'admin-post.php' );
        $setup_status = Setup_Verification::get_status();
        $ver = defined( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) ? \constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) : '';
        $social_base = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            ? constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' ) . 'images/social-media/'
            : '';

        // Active plugins (single site) — include plugin.php for is_plugin_active support if needed.
        if ( ! function_exists( '\is_plugin_active' ) ) {
            @include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $active_plugins = (array) \get_option( 'active_plugins', [] );

        ?>
        <div class="wrap kitgenix-admin-app kitgenix-captcha-for-cloudflare-turnstile-use-top-tabs" id="kitgenix-captcha-for-cloudflare-turnstile-admin-app" data-kitgenix-tabs data-kitgenix-default-tab="site-keys">
            <?php
            ?>
            <div class="kitgenix-captcha-for-cloudflare-turnstile-settings-intro kitgenix-settings-header">
                <div class="kitgenix-settings-header-row">
                    <div class="kitgenix-settings-header-main">
                        <div class="kitgenix-settings-brand">
                            <img class="kitgenix-settings-logo" src="<?php echo \esc_url( constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' ) . 'images/logos/kitgenix-favicon-purple.svg' ); ?>" alt="<?php echo \esc_attr__( 'Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>" />
                            <h1 class="kitgenix-captcha-for-cloudflare-turnstile-admin-title"><?php echo \esc_html( \__( 'Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h1>
                        </div>
                        <p><?php echo \esc_html__( 'Seamlessly integrate Cloudflare’s free Turnstile CAPTCHA into your WordPress forms to enhance security and reduce spam – without compromising user experience.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <div class="kitgenix-settings-meta">
                            <span class="kitgenix-settings-version" aria-label="Plugin version">v<?php echo esc_html( $ver ); ?></span>
                        </div>
                    </div>

                    <div class="kitgenix-settings-header-actions">
                        <div class="kitgenix-intro-links kitgenix-captcha-for-cloudflare-turnstile-intro-links">
                            <a href="<?php echo \esc_url( 'https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Documentation', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                            <a href="<?php echo \esc_url( 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/reviews/#new-post' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Review Plugin', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                            <a href="<?php echo \esc_url( 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Support Request', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                            <a href="<?php echo \esc_url( 'https://buymeacoffee.com/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Support Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
                        </div>
                        <?php if ( $social_base ) : ?>
                            <div class="kitgenix-social-links kitgenix-social-links--icons">
                                <a href="https://kitgenix.com" target="_blank" rel="noopener noreferrer" aria-label="Website" title="Website"><img src="<?php echo esc_url( $social_base . 'globe-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Website</span></a>
                                <a href="https://www.facebook.com/groups/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook Community" title="Facebook Community"><img src="<?php echo esc_url( $social_base . 'facebook-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Facebook Community</span></a>
                                <a href="https://www.facebook.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><img src="<?php echo esc_url( $social_base . 'facebook-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Facebook</span></a>
                                <a href="https://www.instagram.com/kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><img src="<?php echo esc_url( $social_base . 'instagram-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Instagram</span></a>
                                <a href="https://www.youtube.com/@Kitgenix" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><img src="<?php echo esc_url( $social_base . 'youtube-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">YouTube</span></a>
                                <a href="https://www.reddit.com/r/Kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Reddit" title="Reddit"><img src="<?php echo esc_url( $social_base . 'reddit-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Reddit</span></a>
                                <a href="https://www.linkedin.com/company/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><img src="<?php echo esc_url( $social_base . 'linkedin-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">LinkedIn</span></a>
                                <a href="https://x.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="X" title="X"><img src="<?php echo esc_url( $social_base . 'x-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">X</span></a>
                                <a href="https://www.tiktok.com/@kitgenix" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok"><img src="<?php echo esc_url( $social_base . 'tiktok-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">TikTok</span></a>
                                <a href="https://github.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub"><img src="<?php echo esc_url( $social_base . 'github-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">GitHub</span></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <h2 class="nav-tab-wrapper kitgenix-nav-tabs" aria-label="Settings navigation">
                <a class="nav-tab nav-tab-active kitgenix-tab-trigger" href="admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=site-keys#kitgenix-tab-site-keys" data-kitgenix-tab="site-keys"><?php echo \esc_html__( 'Site Keys', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                <a class="nav-tab kitgenix-tab-trigger" href="admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=display#kitgenix-tab-display" data-kitgenix-tab="display"><?php echo \esc_html__( 'Display', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                <a class="nav-tab kitgenix-tab-trigger" href="admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=integrations#kitgenix-tab-integrations" data-kitgenix-tab="integrations"><?php echo \esc_html__( 'Integrations', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                <a class="nav-tab kitgenix-tab-trigger" href="admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=security#kitgenix-tab-security" data-kitgenix-tab="security"><?php echo \esc_html__( 'Security', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                <a class="nav-tab kitgenix-tab-trigger" href="admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=advanced#kitgenix-tab-advanced" data-kitgenix-tab="advanced"><?php echo \esc_html__( 'Advanced', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                <a class="nav-tab kitgenix-tab-trigger" href="admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=portability#kitgenix-tab-portability" data-kitgenix-tab="portability"><?php echo \esc_html__( 'Portability', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                <a class="nav-tab kitgenix-tab-trigger" href="admin.php?page=kitgenix-captcha-for-cloudflare-turnstile&tab=support#kitgenix-tab-support" data-kitgenix-tab="support"><?php echo \esc_html__( 'Support', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
            </h2>
            <div class="kitgenix-settings-layout">
                <div class="kitgenix-settings-content" id="kitgenix-settings-content" tabindex="-1">
            <?php
            $override_details      = Settings_Overrides::get_override_details();
            $supported_sources     = Settings_Overrides::get_supported_key_sources();
            $site_key_override     = $override_details['site_key'] ?? [];
            $secret_key_override   = $override_details['secret_key'] ?? [];
            $site_key_locked       = ! empty( $site_key_override['is_overridden'] );
            $secret_key_locked     = ! empty( $secret_key_override['is_overridden'] );
            $keys_locked           = $site_key_locked || $secret_key_locked;
            $site_key_override_msg = self::describe_key_override( $site_key_override );
            $secret_key_override_msg = self::describe_key_override( $secret_key_override );
            $secret_present        = $secret_key_locked || ! empty( $settings['secret_key'] );
            $setup_checked_at      = ! empty( $setup_status['checked_at'] ) ? (int) $setup_status['checked_at'] : 0;
            $setup_checked_label   = $setup_checked_at ? ( \function_exists( 'wp_date' ) ? \wp_date( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ), $setup_checked_at ) : \date_i18n( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ), $setup_checked_at ) ) : '';
            ?>
            <form method="post" action="options.php" autocomplete="off" novalidate>
                <?php \settings_fields( 'kitgenix_captcha_for_cloudflare_turnstile_settings_group' ); ?>
                <?php \wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_settings_save', 'kitgenix_captcha_for_cloudflare_turnstile_settings_nonce' ); ?>

                <!-- Site Keys -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-site-keys" data-section data-kitgenix-tab-panel="site-keys">
                    <h2><?php echo \esc_html__( 'Cloudflare Turnstile Site Key & Secret Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description">
                            <?php echo \esc_html__( 'You can obtain your Site Key and Secret Key by visiting:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?><br>
                            <a href="<?php echo \esc_url( 'https://dash.cloudflare.com/?to=/:account/turnstile' ); ?>" target="_blank" rel="noopener noreferrer">https://dash.cloudflare.com/?to=/:account/turnstile</a>
                        </p>

                        <?php if ( $keys_locked ) : ?>
                            <div class="notice notice-info inline">
                                <p><?php echo \esc_html__( 'One or more Turnstile keys are managed outside WordPress. Database values stay in place but are ignored while these overrides are active.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </div>
                        <?php endif; ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="site_key"><?php echo \esc_html__( 'Site Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="text" id="site_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[site_key]" value="<?php echo \esc_attr( $settings['site_key'] ?? '' ); ?>" class="regular-text" required autocomplete="off" <?php echo $site_key_locked ? 'readonly aria-readonly="true"' : ''; ?> />
                                    <?php if ( $site_key_locked && $site_key_override_msg ) : ?>
                                        <p class="description"><?php echo \esc_html( $site_key_override_msg ); ?> <?php echo \esc_html__( 'Update the host-level value to change the active site key.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="secret_key"><?php echo \esc_html__( 'Secret Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <div class="kitgenix-secret-key-wrapper">
                                        <input type="password" id="secret_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key]" value="" class="regular-text" autocomplete="off" aria-describedby="secret-key-help" <?php echo $secret_key_locked ? 'placeholder="' . \esc_attr__( 'Managed outside WordPress', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '" readonly aria-readonly="true"' : ( $secret_present ? 'placeholder="' . \esc_attr__( 'Saved (hidden)', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '" data-kitgenix-captcha-for-cloudflare-turnstile-saved-secret="1"' : '' ); ?> />
                                        <div class="kitgenix-secret-key-actions">
                                            <?php if ( $secret_present && ! $secret_key_locked ) : ?>
                                                <input type="hidden" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key_present]" value="1" />
                                                <label class="kitgenix-captcha-for-cloudflare-turnstile-inline-flex">
                                                    <input type="checkbox" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key_clear]" value="1" />
                                                    <span><?php echo \esc_html__( 'Clear saved secret', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                                </label>
                                            <?php endif; ?>
                                            <?php if ( ! $secret_key_locked ) : ?>
                                                <button type="button"
                                                    class="button button-secondary kitgenix-reveal-secret"
                                                    data-target="secret_key"
                                                    data-label-show="<?php echo \esc_attr__( 'Reveal secret key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                                    data-label-hide="<?php echo \esc_attr__( 'Hide secret key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                                    data-text-show="<?php echo \esc_attr__( 'Show', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                                    data-text-hide="<?php echo \esc_attr__( 'Hide', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"
                                                    aria-pressed="false"
                                                    aria-label="<?php echo \esc_attr__( 'Reveal secret key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>">
                                                    <span class="kitgenix-reveal-secret-text" aria-hidden="true"><?php echo \esc_html__( 'Show', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                                </button>
                                                <button type="button"
                                                    class="button button-secondary kitgenix-copy-secret"
                                                    data-target="secret_key"
                                                    aria-label="<?php echo \esc_attr__( 'Copy secret key to clipboard', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>">
                                                    <span aria-hidden="true">Copy</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p id="secret-key-help" class="description">
                                        <?php if ( $secret_key_locked && $secret_key_override_msg ) : ?>
                                            <?php echo \esc_html( $secret_key_override_msg ); ?> <?php echo \esc_html__( 'The active secret key cannot be revealed, copied, cleared, or edited from this screen. Update the host-level value to change it.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                        <?php else : ?>
                                            <?php echo \esc_html__( 'Your Secret Key is sensitive. For safety this screen does not expose the stored secret. Enter a new secret to replace the stored value, or check "Clear saved secret" to remove it.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <!-- Test widget -->
                        <table class="form-table">
                            <tr class="kitgenix-has-turnstile-test">
                                <th scope="row"><label><?php echo \esc_html__( 'Test Cloudflare Turnstile Response', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <div id="kitgenix-captcha-for-cloudflare-turnstile-test-widget" aria-label="<?php echo \esc_attr__( 'Turnstile test widget', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>"></div>
                                    <p id="kitgenix-captcha-for-cloudflare-turnstile-test-success" aria-hidden="true"><?php echo \esc_html__( 'Widget challenge completed. Verifying setup with Cloudflare now...', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                    <div id="kitgenix-captcha-for-cloudflare-turnstile-setup-status" class="notice inline <?php echo ! empty( $setup_status['verified'] ) ? 'notice-success' : ( ! empty( $setup_status['gate_active'] ) ? 'notice-warning' : 'notice-info' ); ?>" aria-live="polite">
                                        <p><strong><?php echo \esc_html__( 'Setup verification', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>:</strong> <?php echo \esc_html( $setup_status['message'] ); ?></p>
                                        <?php if ( $setup_checked_label ) : ?>
                                            <?php /* translators: %s: localized date and time of the last successful or failed setup verification check. */ ?>
                                            <p class="description"><?php echo \esc_html( \sprintf( __( 'Last checked: %s', 'kitgenix-captcha-for-cloudflare-turnstile' ), $setup_checked_label ) ); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description"><?php echo \esc_html__( 'This test must pass before login-sensitive flows such as WordPress auth, WooCommerce account auth, EDD account forms, and membership integrations become active on the frontend.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                    <?php if ( empty( $settings['site_key'] ) ) : ?>
                                        <div class="kitgenix-captcha-for-cloudflare-turnstile-warning description"><?php echo \esc_html__( 'Enter your Site Key above to test Turnstile.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="site-keys">
                    <h2><?php echo \esc_html__( 'Managed Key Overrides', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description">
                            <?php
                            echo \esc_html__( 'You can also manage keys outside WordPress using wp-config.php constants or environment variables.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                            echo ' ';
                            echo \esc_html( \implode( ', ', $supported_sources['site_key_constants'] ) );
                            echo ' / ';
                            echo \esc_html( \implode( ', ', $supported_sources['secret_key_constants'] ) );
                            echo '. ';
                            echo \esc_html__( 'The same names are supported as environment variables, and any active override locks the matching admin field.', 'kitgenix-captcha-for-cloudflare-turnstile' );
                            ?>
                        </p>
                    </div>
                </div>

                    <!-- Display Settings -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-display" data-section data-kitgenix-tab-panel="display">
                    <h2><?php echo \esc_html__( 'Display Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label><?php echo \esc_html__( 'Theme', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]" value="auto" <?php checked( $settings['theme'] ?? 'auto', 'auto' ); ?> /> <?php echo \esc_html__( 'Auto', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]" value="light" <?php checked( $settings['theme'] ?? '', 'light' ); ?> /> <?php echo \esc_html__( 'Light', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]" value="dark" <?php checked( $settings['theme'] ?? '', 'dark' ); ?> /> <?php echo \esc_html__( 'Dark', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </fieldset>
                                    <p class="description"><?php echo \esc_html__( 'Select the visual style for the widget.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo \esc_html__( 'Widget Size', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline-sm"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="normal" <?php checked( $settings['widget_size'] ?? 'normal', 'normal' ); ?> /> <?php echo \esc_html__( 'Normal', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline-sm"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="compact" <?php checked( ( $settings['widget_size'] ?? '' ) === 'compact' || ( $settings['widget_size'] ?? '' ) === 'small' ); ?> /> <?php echo \esc_html__( 'Compact', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]" value="flexible" <?php checked( $settings['widget_size'] ?? '', 'flexible' ); ?> /> <?php echo \esc_html__( 'Flexible (100% width)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </fieldset>
                                    <p class="description"><?php echo \esc_html__( 'Select the size used when rendering the widget.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php echo \esc_html__( 'Appearance', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[appearance]" value="always" <?php checked( $settings['appearance'] ?? 'always', 'always' ); ?> /> <?php echo \esc_html__( 'Always', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[appearance]" value="interaction-only" <?php checked( $settings['appearance'] ?? '', 'interaction-only' ); ?> /> <?php echo \esc_html__( 'Interaction Only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </fieldset>
                                    <p class="description"><?php echo \esc_html__( 'Control how the widget is displayed.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="language"><?php echo \esc_html__( 'Language', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="text" id="language" name="kitgenix_captcha_for_cloudflare_turnstile_settings[language]" value="<?php echo \esc_attr( $settings['language'] ?? 'auto' ); ?>" class="regular-text" />
                                    <p class="description"><?php echo \esc_html__( 'Enter a language code (e.g. "en", "fr", "zh-CN") or use "auto" to detect. Common codes: en, es, fr, de, it, pt, ru, ja, ko, zh-CN, zh-TW.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="disable_submit"><?php echo \esc_html__( 'Disable Submit Button', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="disable_submit" name="kitgenix_captcha_for_cloudflare_turnstile_settings[disable_submit]" value="1" <?php checked( ! empty( $settings['disable_submit'] ) ); ?> />
                                        <span class="description"><?php echo \esc_html__( 'Keep the submit button inactive until Turnstile is solved.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="error_message"><?php echo \esc_html__( 'Custom Error Message', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="text" id="error_message" name="kitgenix_captcha_for_cloudflare_turnstile_settings[error_message]" value="<?php echo \esc_attr( $settings['error_message'] ?? '' ); ?>" class="regular-text" />
                                    <p class="description"><?php echo \esc_html__( 'Override the default inline error shown to users when verification fails.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="extra_message"><?php echo \esc_html__( 'Extra Failure Message', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="text" id="extra_message" name="kitgenix_captcha_for_cloudflare_turnstile_settings[extra_message]" value="<?php echo \esc_attr( $settings['extra_message'] ?? '' ); ?>" class="regular-text" />
                                    <p class="description"><?php echo \esc_html__( 'Optional extra text appended to error messages (e.g., support instructions).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Developer Mode -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-developer" data-section data-kitgenix-tab-panel="advanced">
                    <h2><?php echo \esc_html( \__( 'Developer Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="dev_mode_warn_only"><?php echo \esc_html( \__( 'Development Mode (Warn-only)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label class="kitgenix-captcha-for-cloudflare-turnstile-inline-flex">
                                        <input type="checkbox" id="dev_mode_warn_only" name="kitgenix_captcha_for_cloudflare_turnstile_settings[dev_mode_warn_only]" value="1" <?php checked( ! empty( $settings['dev_mode_warn_only'] ) ); ?> />
                                        <span><?php echo \esc_html__( 'Enable warn-only mode (do not block submissions).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                    </label>
                                    <p class="description kitgenix-captcha-for-cloudflare-turnstile-mt-6">
                                        <?php echo \esc_html__( 'Do not block submissions if Turnstile fails. Instead, log the failure and show an inline warning (admins only). Ideal for staging.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Security -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-security" data-section data-kitgenix-tab-panel="security">
                    <h2><?php echo \esc_html( \__( 'Security', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="replay_protection"><?php echo \esc_html( \__( 'Enable Replay Protection', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="hidden" name="kitgenix_captcha_for_cloudflare_turnstile_settings[replay_protection]" value="0" />
                                        <input type="checkbox" id="replay_protection" name="kitgenix_captcha_for_cloudflare_turnstile_settings[replay_protection]" value="1" <?php checked( ! empty( $settings['replay_protection'] ) ); ?> />
                                        <span class="description">
                                            <?php echo \esc_html( \__( 'Rejects reused Turnstile tokens for a short period (default 10 minutes). Prevents replays and accidental double-submits.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Whitelist -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-whitelist" data-section data-kitgenix-tab-panel="security">
                    <h2><?php echo \esc_html__( 'Whitelist Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="whitelist_loggedin"><?php echo \esc_html__( 'Skip for Logged-in Users', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="whitelist_loggedin" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_loggedin]" value="1" <?php checked( ! empty( $settings['whitelist_loggedin'] ) ); ?> />
                                        <span class="description"><?php echo \esc_html__( 'Useful for membership sites or intranets. Applies to all integrations.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whitelist_ips"><?php echo \esc_html__( 'IP Address Whitelist', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <textarea id="whitelist_ips" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_ips]" rows="2" class="large-text code"><?php echo \esc_textarea( $settings['whitelist_ips'] ?? '' ); ?></textarea><br />
                                        <span class="description"><?php echo \esc_html__( 'One per line. Supports exact IPs, wildcards (e.g. 203.0.113.*) and CIDR (e.g. 203.0.113.0/24, 2001:db8::/32).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="whitelist_user_agents"><?php echo \esc_html__( 'User Agent Whitelist', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                        <textarea id="whitelist_user_agents" name="kitgenix_captcha_for_cloudflare_turnstile_settings[whitelist_user_agents]" rows="2" class="large-text code"><?php echo \esc_textarea( $settings['whitelist_user_agents'] ?? '' ); ?></textarea><br />
                                        <span class="description"><?php echo \esc_html__( 'One per line. Supports * wildcards. Use cautiously—UAs can be spoofed.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Reverse Proxy / Cloudflare -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-proxy" data-section data-kitgenix-tab-panel="security">
                    <h2><?php echo \esc_html( \__( 'Reverse Proxy / Cloudflare', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <table class="form-table">
                            <tr>
                                <th><label for="trust_proxy"><?php echo \esc_html( \__( 'Trust Cloudflare/Proxy Headers', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="trust_proxy" name="kitgenix_captcha_for_cloudflare_turnstile_settings[trust_proxy]" value="1" <?php checked( ! empty( $settings['trust_proxy'] ) ); ?> />
                                        <span class="description">
                                            <?php echo \esc_html( \__( 'When enabled, the plugin will trust CF-Connecting-IP / X-Forwarded-For (etc.) only if the request comes from a trusted proxy below. Otherwise, REMOTE_ADDR is used.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="trusted_proxies"><?php echo \esc_html__( 'Trusted Proxy IPs (one per line)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <textarea id="trusted_proxies" name="kitgenix_captcha_for_cloudflare_turnstile_settings[trusted_proxies]" rows="4" class="large-text code"><?php echo \esc_textarea( $settings['trusted_proxies'] ?? '' ); ?></textarea>
                                    <p class="description">
                                        <?php echo \esc_html__( 'Accepts IPv4/IPv6 or CIDR ranges, e.g. 203.0.113.10 or 2001:db8::/32. Only when REMOTE_ADDR matches one of these will proxy headers be used.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- WordPress Integration -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-wp" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'WordPress Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Renders Turnstile on core WordPress forms:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                            <strong><?php echo \esc_html__( 'Login, Register, Lost Password, Reset Password, and Comments.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_wordpress"><?php echo \esc_html__( 'Enable for WordPress Core Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="enable_wordpress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wordpress]" value="1" <?php checked( ! empty( $settings['enable_wordpress'] ) ); ?> />
                                        <span class="description"><?php echo \esc_html__( 'Adds a Turnstile widget to the forms listed below and validates on POST only.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="description kitgenix-captcha-for-cloudflare-turnstile-mt-6">
                            <strong><?php echo esc_html__( 'Injection Mode — WordPress Core', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                            <br />
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_wp_core]" value="auto" <?php checked( $settings['mode_wp_core'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_wp_core]" value="shortcode" <?php checked( $settings['mode_wp_core'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <small class="description"><?php echo esc_html__( 'When set to “Shortcode only”, automatic injection is disabled for WordPress core forms. Use the [kitgenix_turnstile] shortcode in any custom templates/forms that support shortcodes.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></small>
                        </p>
                        <?php self::render_shortcode_row(); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wp_login_form"><?php echo \esc_html__( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_login_form]" value="1" <?php checked( ! empty( $settings['wp_login_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'wp-login.php and wp_login_form()-powered custom login screens – below the password field.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wp_register_form"><?php echo \esc_html__( 'Registration Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_register_form]" value="1" <?php checked( ! empty( $settings['wp_register_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'wp-login.php?action=register – above the submit button.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wp_lostpassword_form"><?php echo \esc_html__( 'Password Reset Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_lostpassword_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_lostpassword_form]" value="1" <?php checked( ! empty( $settings['wp_lostpassword_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Lost/Reset password screens – beneath email/new password fields.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wp_comments_form"><?php echo \esc_html__( 'Comments Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wp_comments_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_comments_form]" value="1" <?php checked( ! empty( $settings['wp_comments_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Below standard WordPress comment fields (for guests and logged-in users). WooCommerce product reviews have a separate setting below.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php
                // Match Turnstile_Loader's BuddyPress presence signals.
                $is_buddypress = defined( 'BP_VERSION' ) || class_exists( 'BuddyPress' ) || function_exists( 'bp_is_active' ) || function_exists( 'bp_register' );
                ?>
                <?php if ( $is_buddypress ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-buddypress" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'BuddyPress Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Adds Turnstile to BuddyPress registration and validates submissions server-side.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_buddypress"><?php echo \esc_html__( 'Enable for BuddyPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_buddypress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_buddypress]" value="1" <?php checked( ! empty( $settings['enable_buddypress'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Match Turnstile_Loader's bbPress presence signals.
                $is_bbpress = in_array( 'bbpress/bbpress.php', $active_plugins, true )
                    || defined( 'BBPRESS_VERSION' )
                    || defined( 'BBP_VERSION' )
                    || class_exists( 'bbPress' )
                    || function_exists( 'bbp_is_single_forum' );
                ?>
                <?php if ( $is_bbpress ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-bbpress" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'bbPress Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Adds Turnstile to bbPress topic and reply forms and validates submissions server-side.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_bbpress"><?php echo \esc_html__( 'Enable for bbPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_bbpress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_bbpress]" value="1" <?php checked( ! empty( $settings['enable_bbpress'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Kitgenix Plugin Score — match the same presence signals as Turnstile_Loader.
                $is_kgps = defined( 'KGPS_VERSION' )
                    || class_exists( 'KGPS_Plugin' )
                    || in_array( 'kitgenix-plugin-score/kitgenix-plugin-score.php', $active_plugins, true );
                ?>
                <?php if ( $is_kgps ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-kgps" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Kitgenix Plugin Score Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Protects the Plugin Score Login, Register, and Forgot Password forms with Cloudflare Turnstile. Widgets are injected automatically — no changes to Plugin Score files are required.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_kitgenix_plugin_score"><?php echo \esc_html__( 'Enable for Plugin Score', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_kitgenix_plugin_score" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_kitgenix_plugin_score]" value="1" <?php checked( ! empty( $settings['enable_kitgenix_plugin_score'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <table class="form-table">
                            <tr>
                                <th><label for="kgps_login_form"><?php echo \esc_html__( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="kgps_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[kgps_login_form]" value="1" <?php checked( ! empty( $settings['kgps_login_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Plugin Score custom login page (/login/).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="kgps_register_form"><?php echo \esc_html__( 'Register Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="kgps_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[kgps_register_form]" value="1" <?php checked( ! empty( $settings['kgps_register_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Plugin Score custom register page (/register/).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="kgps_lostpassword_form"><?php echo \esc_html__( 'Forgot Password Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="kgps_lostpassword_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[kgps_lostpassword_form]" value="1" <?php checked( ! empty( $settings['kgps_lostpassword_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Plugin Score custom forgot-password page (/forgot-password/).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>



                <!-- Easy Digital Downloads Integration -->
                <?php $is_edd_active = ( function_exists( '\is_plugin_active' ) && \is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) || in_array( 'easy-digital-downloads/easy-digital-downloads.php', $active_plugins, true ) || defined( 'EDD_VERSION' ); ?>
                <?php if ( $is_edd_active ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-edd" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Easy Digital Downloads Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description">
                            <?php echo \esc_html__( 'Protects Easy Digital Downloads checkout and account-related forms (Login, Registration, Profile Editor) with Cloudflare Turnstile.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_edd"><?php echo \esc_html__( 'Enable for Easy Digital Downloads', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_edd" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_edd]" value="1" <?php checked( ! empty( $settings['enable_edd'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <hr class="kitgenix-captcha-for-cloudflare-turnstile-divider" />
                        <h3 class="kitgenix-captcha-for-cloudflare-turnstile-h3-tight">&nbsp;<?php echo \esc_html__( 'Checkout & Account Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                        <p class="description">&nbsp;<?php echo \esc_html__( 'EDD Purchase form (checkout), Login / Register areas, and Profile Editor.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="edd_checkout_form"><?php echo \esc_html__( 'Checkout Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="checkbox" id="edd_checkout_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[edd_checkout_form]" value="1" <?php checked( ! empty( $settings['edd_checkout_form'] ) ); ?> />
                                    <p class="description"><?php echo \esc_html__( 'Purchase form (Complete Purchase button) on EDD checkout.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="edd_login_form"><?php echo \esc_html__( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="edd_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[edd_login_form]" value="1" <?php checked( ! empty( $settings['edd_login_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'EDD login form (including the checkout login area).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="edd_register_form"><?php echo \esc_html__( 'Registration Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="edd_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[edd_register_form]" value="1" <?php checked( ! empty( $settings['edd_register_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'EDD registration form (standalone and at checkout).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="edd_profile_form"><?php echo \esc_html__( 'Profile / Account Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="edd_profile_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[edd_profile_form]" value="1" <?php checked( ! empty( $settings['edd_profile_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'EDD Profile Editor (account details form).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                        </table>

                        <p class="description kitgenix-captcha-for-cloudflare-turnstile-mt-6">
                            <strong><?php echo esc_html__( 'Injection Mode — Easy Digital Downloads', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                            <br />
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_edd]" value="auto" <?php checked( $settings['mode_edd'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_edd]" value="shortcode" <?php checked( $settings['mode_edd'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <small class="description"><?php echo esc_html__( 'When set to "Shortcode only", automatic widget injection is skipped on EDD forms. Use the [kitgenix_turnstile] shortcode where your form allows custom HTML or shortcodes.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></small>
                        </p>
                        <?php self::render_shortcode_row(); ?>
                    </div>
                </div>
                <?php endif; ?>
                


                <!-- WooCommerce Integration -->
                <?php $is_wc_active = ( function_exists( '\is_plugin_active' ) && \is_plugin_active( 'woocommerce/woocommerce.php' ) ) || in_array( 'woocommerce/woocommerce.php', $active_plugins, true ); ?>
                <?php if ( $is_wc_active ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-wc" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'WooCommerce Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description">
                            <?php echo \esc_html__( 'Manage protection for WooCommerce checkout, product reviews, and account flows. Use separate controls for Classic vs Blocks checkout.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_woocommerce"><?php echo \esc_html__( 'Enable for WooCommerce Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_woocommerce" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_woocommerce]" value="1" <?php checked( ! empty( $settings['enable_woocommerce'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <hr class="kitgenix-captcha-for-cloudflare-turnstile-divider" />
                        <h3 class="kitgenix-captcha-for-cloudflare-turnstile-h3-tight">&nbsp;<?php echo \esc_html__( 'WooCommerce Classic', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                        <p class="description">&nbsp;<?php echo \esc_html__( 'Classic Checkout, product review forms, and My Account screens (Login, Registration, Lost/Reset Password).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="wc_checkout_form"><?php echo \esc_html__( 'Checkout Form (Classic)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td>
                                    <input type="checkbox" id="wc_checkout_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_checkout_form]" value="1" <?php checked( ! empty( $settings['wc_checkout_form'] ) ); ?> />
                                    <p class="description">
                                        <?php echo \esc_html__( 'Classic checkout only: places the widget before the “Place order” button. This toggle does not control Blocks checkout.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                        <br />
                                        <?php echo \esc_html__( 'For Blocks checkout, use the “Blocks Checkout” injection options below.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="wc_reviews_form"><?php echo \esc_html__( 'Product Reviews', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_reviews_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_reviews_form]" value="1" <?php checked( ! empty( $settings['wc_reviews_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Single product review form, including themes or extensions that submit through the standard WooCommerce review/comment flow.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wc_login_form"><?php echo \esc_html__( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_login_form]" value="1" <?php checked( ! empty( $settings['wc_login_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'My Account → Login.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wc_register_form"><?php echo \esc_html__( 'Registration Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_register_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_register_form]" value="1" <?php checked( ! empty( $settings['wc_register_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'My Account → Register.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="wc_lostpassword_form"><?php echo \esc_html__( 'Password Reset Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="wc_lostpassword_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_lostpassword_form]" value="1" <?php checked( ! empty( $settings['wc_lostpassword_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'My Account → Lost/Reset password.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
                            </tr>
                        </table>

                        <p class="description kitgenix-captcha-for-cloudflare-turnstile-mt-6">
                            <strong><?php echo esc_html__( 'Injection Mode — Classic', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                            <br />
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce]" value="auto" <?php checked( $settings['mode_woocommerce'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce]" value="shortcode" <?php checked( $settings['mode_woocommerce'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <hr class="kitgenix-captcha-for-cloudflare-turnstile-divider kitgenix-captcha-for-cloudflare-turnstile-divider--lg" />
                        <h3 class="kitgenix-captcha-for-cloudflare-turnstile-h3-tight">&nbsp;<?php echo \esc_html__( 'WooCommerce Blocks (Store API)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                        <p class="description">&nbsp;<?php echo \esc_html__( 'Blocks Checkout is supported via a JS bridge that attaches the token to Store API requests, with validation via REST pre-dispatch.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <p class="description kitgenix-captcha-for-cloudflare-turnstile-mt-6">
                            <strong><?php echo esc_html__( 'Injection Mode — Blocks Checkout', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                            <br />
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce_blocks]" value="auto" <?php checked( $settings['mode_woocommerce_blocks'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_woocommerce_blocks]" value="shortcode" <?php checked( $settings['mode_woocommerce_blocks'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <br />
                            <small class="description"><?php echo esc_html__( 'Unchecking “Checkout Form (Classic)” does not affect Blocks Checkout. Switch Blocks to “Shortcode only” to disable auto-injection.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></small>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Elementor Integration -->
                <?php if ( defined( 'ELEMENTOR_VERSION' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-elementor" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Elementor Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Elementor Pro Forms: container renders after fields; server-side validation via Elementor hooks. Elementor (free): auto-injection above the submit button.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_elementor"><?php echo \esc_html__( 'Enable for Elementor Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_elementor" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_elementor]" value="1" <?php checked( ! empty( $settings['enable_elementor'] ) ); ?> /></td>
                            </tr>
                        </table>

                                    <p class="description">
                                        <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_elementor]" value="auto" <?php checked( $settings['mode_elementor'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_elementor]" value="shortcode" <?php checked( $settings['mode_elementor'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </p>
                                    <?php self::render_shortcode_help( __( 'Use the shortcode in custom HTML to manually place the widget when Shortcode only is selected.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- WPForms -->
                <?php if ( class_exists( 'WPForms' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-integrations-forms" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'WPForms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget renders near the submit area; server-side validation uses WPForms process hook (works with AJAX).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_wpforms"><?php echo \esc_html__( 'Enable for WPForms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_wpforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wpforms]" value="1" <?php checked( ! empty( $settings['enable_wpforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_wpforms]" value="auto" <?php checked( $settings['mode_wpforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_wpforms]" value="shortcode" <?php checked( $settings['mode_wpforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Place the shortcode in a custom HTML field or form content. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Fluent Forms -->
                <?php if ( defined( 'FLUENTFORM' ) || class_exists( 'FluentForm' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Fluent Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is inserted before the submit button; AJAX-friendly validation via Fluent’s submit filter.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_fluentforms"><?php echo \esc_html__( 'Enable for Fluent Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_fluentforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_fluentforms]" value="1" <?php checked( ! empty( $settings['enable_fluentforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_fluentforms]" value="auto" <?php checked( $settings['mode_fluentforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_fluentforms]" value="shortcode" <?php checked( $settings['mode_fluentforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Add the shortcode to a custom HTML field or HTML block in your form. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Gravity Forms -->
                <?php if ( class_exists( 'GFForms' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Gravity Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget renders immediately before the submit button; server-side validation sets the top-level error container.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_gravityforms"><?php echo \esc_html__( 'Enable for Gravity Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_gravityforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_gravityforms]" value="1" <?php checked( ! empty( $settings['enable_gravityforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_gravityforms]" value="auto" <?php checked( $settings['mode_gravityforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_gravityforms]" value="shortcode" <?php checked( $settings['mode_gravityforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Place the shortcode inside an HTML block or custom HTML field in Gravity Forms when supported. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formidable -->
                <?php if ( class_exists( 'FrmForm' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Formidable Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget renders before the submit button; validation runs during entry validation.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_formidableforms"><?php echo \esc_html__( 'Enable for Formidable Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_formidableforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_formidableforms]" value="1" <?php checked( ! empty( $settings['enable_formidableforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_formidableforms]" value="auto" <?php checked( $settings['mode_formidableforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_formidableforms]" value="shortcode" <?php checked( $settings['mode_formidableforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Insert the shortcode into a HTML field or custom content area in Formidable Forms. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact Form 7 -->
                <?php if ( in_array( 'contact-form-7/wp-contact-form-7.php', $active_plugins, true ) || defined( 'WPCF7_VERSION' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Contact Form 7 Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is injected before the first submit control; validation uses the CF7 validation filter (AJAX and non-AJAX).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_cf7"><?php echo \esc_html__( 'Enable for Contact Form 7', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_cf7" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_cf7]" value="1" <?php checked( ! empty( $settings['enable_cf7'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_cf7]" value="auto" <?php checked( $settings['mode_cf7'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_cf7]" value="shortcode" <?php checked( $settings['mode_cf7'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'While CF7 auto-inject remains the default, you can place the shortcode in a HTML field or form content to control widget placement. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Forminator -->
                <?php if ( function_exists( 'forminator' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Forminator Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is added alongside the submit markup; validation uses Forminator’s submit errors filter (AJAX-safe).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_forminator"><?php echo \esc_html__( 'Enable for Forminator Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_forminator" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_forminator]" value="1" <?php checked( ! empty( $settings['enable_forminator'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_forminator]" value="auto" <?php checked( $settings['mode_forminator'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_forminator]" value="shortcode" <?php checked( $settings['mode_forminator'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Paste the shortcode into a custom HTML block or field in Forminator; when present the plugin will not auto-inject a second widget. When Shortcode only is selected, auto-inject is disabled for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Jetpack -->
                <?php if ( class_exists( 'Jetpack' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Jetpack Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is injected into Jetpack contact forms; validation occurs via the spam check hook and blocks submission with a surfaced error.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_jetpackforms"><?php echo \esc_html__( 'Enable for Jetpack Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_jetpackforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_jetpackforms]" value="1" <?php checked( ! empty( $settings['enable_jetpackforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_jetpackforms]" value="auto" <?php checked( $settings['mode_jetpackforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_jetpackforms]" value="shortcode" <?php checked( $settings['mode_jetpackforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Add the shortcode to a custom HTML area if Jetpack supports it. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Kadence -->
                <?php if ( class_exists( 'Kadence_Blocks_Form' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Kadence Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget is prepended before the submit button in Kadence Blocks Form; validation returns a form-level error without killing AJAX.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_kadenceforms"><?php echo \esc_html__( 'Enable for Kadence Forms (Kadence Blocks)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_kadenceforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_kadenceforms]" value="1" <?php checked( ! empty( $settings['enable_kadenceforms'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_kadenceforms]" value="auto" <?php checked( $settings['mode_kadenceforms'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_kadenceforms]" value="shortcode" <?php checked( $settings['mode_kadenceforms'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Use the shortcode inside a custom HTML field or block in Kadence Forms. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- JetFormBuilder -->
                <?php if ( class_exists( '\\Jet_Form_Builder\\Plugin' ) || defined( 'JET_FORM_BUILDER_VERSION' ) || defined( 'JET_FORM_BUILDER_PATH' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'JetFormBuilder Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Widget renders near the submit button; server-side validation runs during JetFormBuilder submit handling (AJAX compatible).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <p class="description"><?php echo \esc_html__( 'Note: JetFormBuilder can also enable its own CAPTCHA/Turnstile. If you enable both, you may see a “Turnstile is being loaded more than once” notice after saving. That notice is just a compatibility warning (two different components are enqueueing Cloudflare’s api.js). It’s safe to dismiss, but for best reliability you should use one Turnstile provider per form and disable the other loader to avoid occasional rendering/callback issues.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_jetformbuilder"><?php echo \esc_html__( 'Enable for JetFormBuilder', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_jetformbuilder" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_jetformbuilder]" value="1" <?php checked( ! empty( $settings['enable_jetformbuilder'] ) ); ?> /></td>
                            </tr>
                        </table>
                        <p class="description">
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_jetformbuilder]" value="auto" <?php checked( $settings['mode_jetformbuilder'] ?? 'auto', 'auto' ); ?> /> <?php echo esc_html__( 'Auto-inject (default)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                            <label><input type="radio" name="kitgenix_captcha_for_cloudflare_turnstile_settings[mode_jetformbuilder]" value="shortcode" <?php checked( $settings['mode_jetformbuilder'] ?? 'auto', 'shortcode' ); ?> /> <?php echo esc_html__( 'Shortcode only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                        </p>
                        <?php self::render_shortcode_help( __( 'Place the shortcode in a custom HTML field or block in your form. When Shortcode only is selected, the plugin will not auto-inject for this integration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- MailPoet -->
                <?php if ( defined( 'MAILPOET_VERSION' ) || class_exists( '\\MailPoet\\Config\\Env' ) || class_exists( '\\MailPoet\\API\\API' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'MailPoet Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Injects Turnstile above the MailPoet submit button and validates newsletter subscriptions server-side before the subscriber is created.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_mailpoet"><?php echo \esc_html__( 'Enable for MailPoet Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_mailpoet" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_mailpoet]" value="1" <?php checked( ! empty( $settings['enable_mailpoet'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Ultimate Member -->
                <?php if ( defined( 'ultimatemember_version' ) || class_exists( 'UM' ) || function_exists( 'UM' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Ultimate Member Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Adds Turnstile to Ultimate Member login, registration, and password reset flows using Ultimate Member’s own validation hooks.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_ultimatemember"><?php echo \esc_html__( 'Enable for Ultimate Member', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_ultimatemember" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_ultimatemember]" value="1" <?php checked( ! empty( $settings['enable_ultimatemember'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- MemberPress -->
                <?php if ( defined( 'MEPR_VERSION' ) || class_exists( 'MeprOptions' ) || class_exists( 'MeprAppCtrl' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'MemberPress Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Protects MemberPress signup and checkout forms by rendering Turnstile before submission and validating the signup request server-side.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <p class="description"><?php echo \esc_html__( 'If you also use MemberPress login forms that rely on WordPress authentication, keep the WordPress Login integration enabled as well.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_memberpress"><?php echo \esc_html__( 'Enable for MemberPress', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_memberpress" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_memberpress]" value="1" <?php checked( ! empty( $settings['enable_memberpress'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Paid Memberships Pro -->
                <?php if ( defined( 'PMPRO_VERSION' ) || function_exists( 'pmpro_getOption' ) || class_exists( 'MemberOrder' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'Paid Memberships Pro Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Adds Turnstile to Paid Memberships Pro checkout and registration screens and blocks checkout when verification fails.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_paidmembershipspro"><?php echo \esc_html__( 'Enable for Paid Memberships Pro', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_paidmembershipspro" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_paidmembershipspro]" value="1" <?php checked( ! empty( $settings['enable_paidmembershipspro'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- wpDiscuz -->
                <?php if ( defined( 'WPDISCUZ_VERSION' ) || class_exists( 'WpdiscuzCore' ) || class_exists( '\\WpdiscuzCore' ) ) : ?>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card" data-section data-kitgenix-tab-panel="integrations">
                    <h2><?php echo \esc_html__( 'wpDiscuz Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                    <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                        <p class="description"><?php echo \esc_html__( 'Protects wpDiscuz comment and reply forms with Turnstile using wpDiscuz-specific hooks, without relying on the standard WordPress comment form placement.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="enable_wpdiscuz"><?php echo \esc_html__( 'Enable for wpDiscuz', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="checkbox" id="enable_wpdiscuz" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wpdiscuz]" value="1" <?php checked( ! empty( $settings['enable_wpdiscuz'] ) ); ?> /></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Support (tab page) -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-card kitgenix-captcha-for-cloudflare-turnstile-support-page kitgenix-support-page" id="section-support" data-section data-kitgenix-tab-panel="support">
                    <?php
                    $kitgenix_captcha_for_cloudflare_turnstile_donate_once_url     = 'https://buymeacoffee.com/kitgenix';
                    $kitgenix_captcha_for_cloudflare_turnstile_monthly_support_url = 'https://buymeacoffee.com/kitgenix/membership';
                    $kitgenix_captcha_for_cloudflare_turnstile_plugin_page_url     = 'https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/';
                    $kitgenix_captcha_for_cloudflare_turnstile_review_url          = 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/reviews/#new-post';
                    $kitgenix_captcha_for_cloudflare_turnstile_support_url         = 'https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/support';
                    $kitgenix_captcha_for_cloudflare_turnstile_copy_onclick        = "if(window.navigator&&navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(" . \wp_json_encode( $kitgenix_captcha_for_cloudflare_turnstile_plugin_page_url ) . ");}else{window.prompt(" . \wp_json_encode( \__( 'Copy plugin link:', 'kitgenix-captcha-for-cloudflare-turnstile' ) ) . ", " . \wp_json_encode( $kitgenix_captcha_for_cloudflare_turnstile_plugin_page_url ) . ");}return false;";
                    $kitgenix_captcha_for_cloudflare_turnstile_monthly_options     = [
                        [ 'label' => \__( 'Kitgenix Supporter (£4/month)', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                        [ 'label' => \__( 'Kitgenix Plus (£8/month)', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                        [ 'label' => \__( 'Kitgenix Pro Supporter (£19/month)', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                        [ 'label' => \__( 'Kitgenix Agency (£37/month)', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                        [ 'label' => \__( 'Kitgenix Partner (£75/month)', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                        [ 'label' => \__( 'Kitgenix YouTube Sponsor (£730/month)', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                    ];
                    $kitgenix_captcha_for_cloudflare_turnstile_metrics = Turnstile_Validator::get_metrics_snapshot();
                    $kitgenix_captcha_for_cloudflare_turnstile_total   = isset( $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_total'] ) ? (int) $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_total'] : 0;
                    $kitgenix_captcha_for_cloudflare_turnstile_passed  = isset( $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_passed'] ) ? (int) $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_passed'] : 0;
                    $kitgenix_captcha_for_cloudflare_turnstile_failed  = isset( $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_failed'] ) ? (int) $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_failed'] : 0;
                    $kitgenix_captcha_for_cloudflare_turnstile_retries = isset( $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_retries'] ) ? (int) $kitgenix_captcha_for_cloudflare_turnstile_metrics['checks_retries'] : 0;
                    $kitgenix_captcha_for_cloudflare_turnstile_friction_rate = $kitgenix_captcha_for_cloudflare_turnstile_total > 0
                        ? round( ( $kitgenix_captcha_for_cloudflare_turnstile_retries / $kitgenix_captcha_for_cloudflare_turnstile_total ) * 100, 1 )
                        : 0.0;
                    $kitgenix_captcha_for_cloudflare_turnstile_active_alerts = self::get_operational_alerts();
                    $kitgenix_captcha_for_cloudflare_turnstile_integration_analytics = Turnstile_Validator::get_integration_analytics();
                    $kitgenix_captcha_for_cloudflare_turnstile_recent_event_log      = Turnstile_Validator::get_recent_event_log();
                    $kitgenix_captcha_for_cloudflare_turnstile_recent_event_log_text = Turnstile_Validator::get_recent_event_log_text();
                    $kitgenix_captcha_for_cloudflare_turnstile_integration_export_url = \wp_nonce_url(
                        \admin_url( 'admin-post.php?action=kitgenix_turnstile_export_analytics&export=integrations' ),
                        'kitgenix_turnstile_export_analytics'
                    );
                    $kitgenix_captcha_for_cloudflare_turnstile_recent_log_export_url = \wp_nonce_url(
                        \admin_url( 'admin-post.php?action=kitgenix_turnstile_export_analytics&export=recent-log' ),
                        'kitgenix_turnstile_export_analytics'
                    );
                    $kitgenix_captcha_for_cloudflare_turnstile_impact_cards = [
                        [
                            'label' => \__( 'Turnstile checks', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                            'value' => \number_format_i18n( $kitgenix_captcha_for_cloudflare_turnstile_total ),
                            'meta'  => \__( 'Verification attempts already processed on your site.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        ],
                        [
                            'label' => \__( 'Verified users', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                            'value' => \number_format_i18n( $kitgenix_captcha_for_cloudflare_turnstile_passed ),
                            'meta'  => \__( 'Legitimate visitors who cleared protection successfully.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        ],
                        [
                            'label' => \__( 'Blocked attempts', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                            'value' => \number_format_i18n( $kitgenix_captcha_for_cloudflare_turnstile_failed ),
                            'meta'  => \__( 'Suspicious or failed submissions stopped before they landed.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        ],
                        [
                            'label' => \__( 'Retry events', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                            'value' => \number_format_i18n( $kitgenix_captcha_for_cloudflare_turnstile_retries ),
                            'meta'  => \__( 'Recoverable challenge failures that forced the visitor to try again.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        ],
                        [
                            'label' => \__( 'Challenge friction', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                            'value' => \number_format_i18n( $kitgenix_captcha_for_cloudflare_turnstile_friction_rate, 1 ) . '%',
                            'meta'  => \__( 'Retry events as a share of total checks, useful for spotting conversion drag.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        ],
                    ];
                    $kitgenix_captcha_for_cloudflare_turnstile_meaning_points = [
                        \__( 'Your site is already using Turnstile to protect real forms and submissions.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'Successful verifications show legitimate users are still flowing through without extra friction.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'Blocked checks show the plugin is actively filtering suspicious or failed attempts.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'Per-integration retry and friction data helps isolate the exact forms that may be harming conversions.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    ];
                    $kitgenix_captcha_for_cloudflare_turnstile_support_points = [
                        \__( 'Compatibility updates for new WordPress / WooCommerce releases', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'Bug fixes, edge-case testing, and better integration coverage', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'Security hardening and ongoing performance improvements', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'Documentation upgrades and faster, clearer support responses', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    ];
                    $kitgenix_captcha_for_cloudflare_turnstile_trust_points = [
                        \__( 'No paid features locked behind donations', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'No tracking or invasive upsells', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                        \__( 'Support is always optional, and genuinely appreciated.', 'kitgenix-captcha-for-cloudflare-turnstile' ),
                    ];
                    ?>
                    <div class="kitgenix-support-shell">
                        <section class="kitgenix-support-hero">
                            <div class="kitgenix-support-hero__copy">
                                <span class="kitgenix-support-eyebrow"><?php echo \esc_html__( 'Help keep Kitgenix independent', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                                <h2 class="kitgenix-support-heading"><?php echo \esc_html__( 'Support Kitgenix', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                                <p class="description kitgenix-support-intro"><?php echo \esc_html__( 'We try to keep Kitgenix plugins lightweight, privacy-friendly, and free for everyone. If CAPTCHA for Cloudflare Turnstile saves you admin time or helps prevent spam, please consider supporting Kitgenix. Your support directly funds ongoing development, testing, and maintenance so we can keep features open and updates frequent.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </div>
                            <div class="kitgenix-support-hero__aside">
                                <p class="kitgenix-support-kicker"><?php echo \esc_html__( 'Support this plugin', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                <div class="kitgenix-support-actions">
                                    <a href="<?php echo \esc_url( $kitgenix_captcha_for_cloudflare_turnstile_donate_once_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary"><?php echo \esc_html__( 'Buy Me a Coffee', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                                    <a href="<?php echo \esc_url( $kitgenix_captcha_for_cloudflare_turnstile_monthly_support_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php echo \esc_html__( 'Become a member', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                                </div>
                                <p class="kitgenix-support-note"><?php echo \esc_html__( 'Via Buy Me a Coffee. Cancel anytime.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </div>
                        </section>

                        <section class="kitgenix-support-section kitgenix-support-section--full">
                            <div class="kitgenix-support-section__header">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'Active protection alerts', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <p class="description"><?php echo \esc_html__( 'Automatic alerts flag sudden verification failures, blocked siteverify calls, and duplicate Turnstile loaders before they turn into silent form outages.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </div>
                            <?php if ( empty( $kitgenix_captcha_for_cloudflare_turnstile_active_alerts ) ) : ?>
                                <div class="notice notice-success inline"><p><?php echo \esc_html__( 'No active Turnstile alert thresholds are currently breached. Recent verification activity looks healthy based on the stored diagnostic window.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></div>
                            <?php else : ?>
                                <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_active_alerts as $kitgenix_captcha_for_cloudflare_turnstile_alert ) : ?>
                                    <div class="notice inline <?php echo \esc_attr( self::get_alert_notice_class( (string) ( $kitgenix_captcha_for_cloudflare_turnstile_alert['severity'] ?? 'warning' ) ) ); ?>">
                                        <p><strong><?php echo \esc_html( (string) ( $kitgenix_captcha_for_cloudflare_turnstile_alert['title'] ?? '' ) ); ?></strong></p>
                                        <p><?php echo \esc_html( (string) ( $kitgenix_captcha_for_cloudflare_turnstile_alert['message'] ?? '' ) ); ?></p>
                                        <?php if ( ! empty( $kitgenix_captcha_for_cloudflare_turnstile_alert['detected_at'] ) ) : ?>
                                            <?php /* translators: %s: local date and time when the alert was detected. */ ?>
                                            <p class="description"><?php echo \esc_html( sprintf( __( 'Detected: %s', 'kitgenix-captcha-for-cloudflare-turnstile' ), self::format_timestamp_for_display( (int) $kitgenix_captcha_for_cloudflare_turnstile_alert['detected_at'] ) ) ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $kitgenix_captcha_for_cloudflare_turnstile_alert['matches'] ) && is_array( $kitgenix_captcha_for_cloudflare_turnstile_alert['matches'] ) ) : ?>
                                            <ul style="margin-left:18px;list-style:disc;">
                                                <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_alert['matches'] as $kitgenix_captcha_for_cloudflare_turnstile_match_handle => $kitgenix_captcha_for_cloudflare_turnstile_match_src ) : ?>
                                                    <li><code><?php echo \esc_html( (string) $kitgenix_captcha_for_cloudflare_turnstile_match_handle ); ?></code> - <span style="word-break:break-all;"><?php echo \esc_html( (string) $kitgenix_captcha_for_cloudflare_turnstile_match_src ); ?></span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $kitgenix_captcha_for_cloudflare_turnstile_alert['action_url'] ) && ! empty( $kitgenix_captcha_for_cloudflare_turnstile_alert['action_label'] ) ) : ?>
                                            <p><a href="<?php echo \esc_url( (string) $kitgenix_captcha_for_cloudflare_turnstile_alert['action_url'] ); ?>" class="button button-secondary"><?php echo \esc_html( (string) $kitgenix_captcha_for_cloudflare_turnstile_alert['action_label'] ); ?></a></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </section>

                        <section class="kitgenix-support-section kitgenix-support-section--feature">
                            <div class="kitgenix-support-section__header">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'Your site impact', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <p class="description"><?php echo \esc_html__( 'These stats show how CAPTCHA for Cloudflare Turnstile is currently working on your site:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </div>
                            <div class="kitgenix-support-metric-grid">
                                <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_impact_cards as $kitgenix_captcha_for_cloudflare_turnstile_impact_card ) : ?>
                                    <div class="kitgenix-support-stat">
                                        <span class="kitgenix-support-stat__label"><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_impact_card['label'] ); ?></span>
                                        <strong class="kitgenix-support-stat__value"><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_impact_card['value'] ); ?></strong>
                                        <span class="kitgenix-support-stat__meta"><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_impact_card['meta'] ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="kitgenix-support-section kitgenix-support-section--full">
                            <div class="kitgenix-support-section__header">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'Per-integration analytics', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <p class="description"><?php echo \esc_html__( 'Compare passes, failures, retry-driven friction, and the latest outcome for each protected flow so you can spot broken rollouts quickly.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </div>
                            <div class="kitgenix-support-actions">
                                <a href="<?php echo \esc_url( $kitgenix_captcha_for_cloudflare_turnstile_integration_export_url ); ?>" class="button button-secondary"><?php echo \esc_html__( 'Export analytics CSV', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                                <span class="description"><?php echo \esc_html__( 'Retries count recoverable Turnstile failures such as expired, missing, duplicate, or replayed challenges. Friction is retries divided by total checks.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                            </div>
                            <?php if ( empty( $kitgenix_captcha_for_cloudflare_turnstile_integration_analytics ) ) : ?>
                                <p class="description"><?php echo \esc_html__( 'No integration analytics recorded yet. Submit a protected form to start building comparison data.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            <?php else : ?>
                                <div class="kitgenix-table-wrap">
                                    <table class="kitgenix-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo \esc_html__( 'Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                                <th><?php echo \esc_html__( 'Checks', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                                <th><?php echo \esc_html__( 'Passes', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                                <th><?php echo \esc_html__( 'Failures', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                                <th><?php echo \esc_html__( 'Retries', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                                <th><?php echo \esc_html__( 'Friction', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                                <th><?php echo \esc_html__( 'Latest outcome', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_integration_analytics as $kitgenix_captcha_for_cloudflare_turnstile_integration_row ) : ?>
                                                <?php
                                                $kitgenix_captcha_for_cloudflare_turnstile_last_codes = ! empty( $kitgenix_captcha_for_cloudflare_turnstile_integration_row['last_codes'] )
                                                    ? implode( ', ', array_map( 'strval', (array) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['last_codes'] ) )
                                                    : '';
                                                $kitgenix_captcha_for_cloudflare_turnstile_last_checked = self::format_timestamp_for_display( (int) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['last_checked'] );
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo \esc_html( (string) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['label'] ); ?></strong>
                                                        <div class="description"><code><?php echo \esc_html( (string) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['integration'] ); ?></code></div>
                                                    </td>
                                                    <td><?php echo \esc_html( \number_format_i18n( (int) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['checks_total'] ) ); ?></td>
                                                    <td><?php echo \esc_html( \number_format_i18n( (int) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['checks_passed'] ) ); ?></td>
                                                    <td><?php echo \esc_html( \number_format_i18n( (int) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['checks_failed'] ) ); ?></td>
                                                    <td><?php echo \esc_html( \number_format_i18n( (int) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['checks_retries'] ) ); ?></td>
                                                    <td><?php echo \esc_html( \number_format_i18n( (float) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['friction_rate'], 1 ) ); ?>%</td>
                                                    <td>
                                                        <strong><?php echo \esc_html( (string) $kitgenix_captcha_for_cloudflare_turnstile_integration_row['last_outcome'] === 'success' ? \__( 'Success', 'kitgenix-captcha-for-cloudflare-turnstile' ) : \__( 'Failure', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></strong>
                                                        <?php if ( $kitgenix_captcha_for_cloudflare_turnstile_last_checked !== '' ) : ?>
                                                            <div class="description"><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_last_checked ); ?></div>
                                                        <?php endif; ?>
                                                        <?php if ( $kitgenix_captcha_for_cloudflare_turnstile_last_codes !== '' ) : ?>
                                                            <div class="description"><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_last_codes ); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="kitgenix-support-section kitgenix-support-section--full">
                            <div class="kitgenix-support-section__header">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'Recent diagnostic log', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <p class="description"><?php echo \esc_html__( 'A privacy-safe record of recent Turnstile checks. Each line shows: timestamp | integration | outcome | category | error codes | plain-English note. IP addresses, request URIs, and submitted values are never stored.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </div>
                            <textarea id="kitgenix-turnstile-diagnostic-log" class="large-text code" rows="10" readonly><?php echo \esc_textarea( $kitgenix_captcha_for_cloudflare_turnstile_recent_event_log_text ); ?></textarea>
                            <table class="widefat striped" style="margin-top:12px;font-size:12px;">
                                <thead>
                                    <tr>
                                        <th><?php echo \esc_html__( 'Category', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                        <th><?php echo \esc_html__( 'What it means', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                        <th><?php echo \esc_html__( 'Action needed?', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>passed</code></td>
                                        <td><?php echo \esc_html__( 'Cloudflare verified the challenge — legitimate submission.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'None.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>cached-or-expired-page</code></td>
                                        <td><?php echo \esc_html__( 'The WP security (nonce) token was stale. Most common on cached pages or after a login/session change.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'Usually a false positive — NOT necessarily a bot. If frequent, exclude My Account and login pages from your page cache.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>widget-not-loaded</code></td>
                                        <td><?php echo \esc_html__( 'No Turnstile token reached the server. The widget may not have finished loading before the form was submitted, or JS was blocked.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'If happening for real users, check for JS errors or a slow CDN. Could also be a bot posting the form directly.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>token-rejected</code></td>
                                        <td><?php echo \esc_html__( 'A token was submitted but Cloudflare rejected it as invalid or malformed.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'Likely a bot-crafted submission. No action needed — the block is working correctly.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>token-expired</code></td>
                                        <td><?php echo \esc_html__( 'Token expired before server verification, or was already used in a previous request.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'Normal for users who leave the page open for a long time. The user can refresh and try again.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>replay-blocked</code></td>
                                        <td><?php echo \esc_html__( 'The same Turnstile token was submitted more than once.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'Likely a replay attack. The block is working correctly.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>config-error</code></td>
                                        <td><?php echo \esc_html__( 'Plugin secret key or site key is missing or incorrect.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'Go to Settings → Cloudflare Turnstile and verify both keys match your Cloudflare dashboard.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>network-error</code></td>
                                        <td><?php echo \esc_html__( 'Plugin could not reach Cloudflare\'s verify endpoint (timeout or DNS issue).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'Check your server\'s outbound connectivity to challenges.cloudflare.com. Usually transient.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                    <tr>
                                        <td><code>cloudflare-error</code></td>
                                        <td><?php echo \esc_html__( 'Cloudflare\'s verification service returned a temporary error.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                        <td><?php echo \esc_html__( 'Usually resolves on its own. Monitor if it persists.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="kitgenix-support-actions" style="margin-top:12px;">
                                <button type="button" id="kitgenix-turnstile-copy-log" class="button button-secondary" data-target="kitgenix-turnstile-diagnostic-log"><?php echo \esc_html__( 'Copy recent log', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></button>
                                <a href="<?php echo \esc_url( $kitgenix_captcha_for_cloudflare_turnstile_recent_log_export_url ); ?>" class="button button-secondary"><?php echo \esc_html__( 'Export recent log CSV', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                                <span class="description"><?php echo \esc_html__( 'Stored recent events:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?> <?php echo \esc_html( \number_format_i18n( count( $kitgenix_captcha_for_cloudflare_turnstile_recent_event_log ) ) ); ?></span>
                            </div>
                        </section>

                        <div class="kitgenix-support-grid">
                            <section class="kitgenix-support-section">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'Support options', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <p class="description"><?php echo \esc_html__( 'Buy Me a Coffee: A quick way to say thanks and help fund the next round of improvements.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                <p class="description"><?php echo \esc_html__( 'A membership helps keep development consistent if CAPTCHA for Cloudflare Turnstile is part of your day-to-day anti-spam setup.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                <div class="kitgenix-support-chip-list">
                                    <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_monthly_options as $kitgenix_captcha_for_cloudflare_turnstile_monthly_option ) : ?>
                                        <?php
                                        $monthly_label = (string) $kitgenix_captcha_for_cloudflare_turnstile_monthly_option['label'];
                                        $monthly_name  = $monthly_label;
                                        $monthly_price = '';
                                        if ( preg_match( '/^(.*)\(([^)]+)\)$/u', $monthly_label, $monthly_parts ) ) {
                                            $monthly_name  = trim( $monthly_parts[1] );
                                            $monthly_price = trim( $monthly_parts[2] );
                                        }
                                        ?>
                                        <a class="kitgenix-support-chip" href="<?php echo \esc_url( $kitgenix_captcha_for_cloudflare_turnstile_monthly_option['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                            <span class="kitgenix-support-chip__name"><?php echo \esc_html( $monthly_name ); ?></span>
                                            <?php if ( '' !== $monthly_price ) : ?>
                                                <span class="kitgenix-support-chip__price"><?php echo \esc_html( $monthly_price ); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <section class="kitgenix-support-section">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'What this means', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <ul class="kitgenix-support-list">
                                    <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_meaning_points as $kitgenix_captcha_for_cloudflare_turnstile_meaning_point ) : ?>
                                        <li><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_meaning_point ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>

                            <section class="kitgenix-support-section kitgenix-support-section--soft">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'What your support helps with', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <ul class="kitgenix-support-list">
                                    <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_support_points as $kitgenix_captcha_for_cloudflare_turnstile_support_point ) : ?>
                                        <li><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_support_point ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>

                            <section class="kitgenix-support-section">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'Not in a position to donate?', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <p class="description"><?php echo \esc_html__( 'No worries - you can still massively help:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                <p class="description"><?php echo \esc_html__( 'Reviews help others discover the plugin and keep the project sustainable. Sharing the plugin with site owners who want a lighter anti-spam stack, and sending clear bug reports, both help improve coverage faster.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                <div class="kitgenix-support-actions">
                                    <a href="<?php echo \esc_url( $kitgenix_captcha_for_cloudflare_turnstile_review_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php echo \esc_html__( 'Leave a WordPress.org review', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                                    <button type="button" class="button button-secondary" onclick="<?php echo \esc_attr( $kitgenix_captcha_for_cloudflare_turnstile_copy_onclick ); ?>"><?php echo \esc_html__( 'Copy plugin link', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></button>
                                    <a href="<?php echo \esc_url( $kitgenix_captcha_for_cloudflare_turnstile_support_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php echo \esc_html__( 'Open support / feature request', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                                </div>
                            </section>

                            <section class="kitgenix-support-section kitgenix-support-section--full">
                                <h3 class="kitgenix-support-subheading"><?php echo \esc_html__( 'A small note on trust & privacy', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h3>
                                <ul class="kitgenix-support-list">
                                    <?php foreach ( $kitgenix_captcha_for_cloudflare_turnstile_trust_points as $kitgenix_captcha_for_cloudflare_turnstile_trust_point ) : ?>
                                        <li><?php echo \esc_html( $kitgenix_captcha_for_cloudflare_turnstile_trust_point ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="kitgenix-support-footer-note"><?php echo \esc_html__( 'Thank you for supporting Kitgenix.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                            </section>
                        </div>
                    </div>
                </div>

                <!-- Save (end of main settings form) -->
                <div class="kitgenix-captcha-for-cloudflare-turnstile-save-row" id="section-save" data-section data-kitgenix-tab-hide-on="portability,support" aria-hidden="false">
                    <?php submit_button( \__( 'Save Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'primary', 'submit', false ); ?>
                </div>
            </form>

            <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-portability-export" data-section data-kitgenix-tab-panel="portability">
                <h2><?php echo \esc_html__( 'Export Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                    <p class="description"><?php echo \esc_html__( 'Download your current database-backed plugin settings as JSON so you can reuse the same configuration on staging, client sites, or multisite rollouts.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                    <form method="post" action="<?php echo \esc_url( $admin_post_url ); ?>">
                        <input type="hidden" name="action" value="<?php echo \esc_attr( Settings_Transfer::get_export_action() ); ?>" />
                        <?php \wp_nonce_field( Settings_Transfer::get_export_nonce() ); ?>
                        <fieldset>
                            <label class="kitgenix-captcha-for-cloudflare-turnstile-inline-flex">
                                <input type="checkbox" name="include_keys" value="1" />
                                <span><?php echo \esc_html__( 'Include Site Key and Secret Key in the export file', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></span>
                            </label>
                        </fieldset>
                        <p class="description"><?php echo \esc_html__( 'Keep this unchecked unless you explicitly need to move keys too. Host-managed key overrides are not exported from the environment.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                        <?php submit_button( \__( 'Download JSON Export', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'secondary', 'submit', false ); ?>
                    </form>
                </div>
            </div>

            <div class="kitgenix-captcha-for-cloudflare-turnstile-card" id="section-portability-import" data-section data-kitgenix-tab-panel="portability">
                <h2><?php echo \esc_html__( 'Import Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                <div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
                    <p class="description"><?php echo \esc_html__( 'Import a JSON export from this plugin to copy the same setup to another site. Unknown keys are ignored and active host-managed key overrides still take precedence.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                    <form method="post" action="<?php echo \esc_url( $admin_post_url ); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo \esc_attr( Settings_Transfer::get_import_action() ); ?>" />
                        <?php \wp_nonce_field( Settings_Transfer::get_import_nonce() ); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="kitgenix_turnstile_import_file"><?php echo \esc_html__( 'Settings JSON File', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
                                <td><input type="file" id="kitgenix_turnstile_import_file" name="kitgenix_turnstile_import_file" accept="application/json,.json" required /></td>
                            </tr>
                            <tr>
                                <th><?php echo \esc_html__( 'Import Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label class="kitgenix-captcha-for-cloudflare-turnstile-radio-inline"><input type="radio" name="kitgenix_turnstile_import_mode" value="replace" checked /> <?php echo \esc_html__( 'Replace current settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                        <label><input type="radio" name="kitgenix_turnstile_import_mode" value="merge" /> <?php echo \esc_html__( 'Merge into current settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label>
                                    </fieldset>
                                    <p class="description"><?php echo \esc_html__( 'Replace is best when you want a site to match a known-good template exactly. Merge keeps existing settings for anything not present in the import file.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( \__( 'Import JSON Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'primary', 'submit', false ); ?>
                    </form>
                </div>
            </div>

            <!-- Unsaved changes floating bar (progressive enhancement via JS) -->
            <div id="kitgenix-captcha-for-cloudflare-turnstile-unsaved-bar" class="kitgenix-captcha-for-cloudflare-turnstile-unsaved-bar" aria-hidden="true">
                <strong><?php echo \esc_html__( 'Unsaved changes', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
                <button type="button" id="kitgenix-captcha-for-cloudflare-turnstile-unsaved-save" class="button button-secondary"><?php echo \esc_html__( 'Save now', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></button>
            </div>
            </div><!-- /.kitgenix-settings-content -->
            <?php self::render_sidebar(); ?>
        </div><!-- /.kitgenix-settings-layout -->
        </div>
        <?php
    }

    /**
     * @return array<int,array<int|string>>
     */
    private static function build_integration_export_rows(): array {
        $rows = [];

        foreach ( Turnstile_Validator::get_integration_analytics() as $analytics_row ) {
            $rows[] = [
                (string) ( $analytics_row['integration'] ?? '' ),
                (string) ( $analytics_row['label'] ?? '' ),
                (int) ( $analytics_row['checks_total'] ?? 0 ),
                (int) ( $analytics_row['checks_passed'] ?? 0 ),
                (int) ( $analytics_row['checks_failed'] ?? 0 ),
                (int) ( $analytics_row['checks_retries'] ?? 0 ),
                self::format_percentage_for_csv( (float) ( $analytics_row['friction_rate'] ?? 0 ) ),
                (string) ( $analytics_row['last_outcome'] ?? '' ),
                self::format_timestamp_for_export( (int) ( $analytics_row['last_checked'] ?? 0 ) ),
                implode( ', ', array_map( 'strval', (array) ( $analytics_row['last_codes'] ?? [] ) ) ),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int,array<int|string>>
     */
    private static function build_recent_log_export_rows(): array {
        $rows = [];

        foreach ( Turnstile_Validator::get_recent_event_log() as $event ) {
            $codes_arr = array_map( 'strval', (array) ( $event['codes'] ?? [] ) );
            $outcome   = (string) ( $event['outcome'] ?? 'failure' );
            $context   = Turnstile_Validator::get_event_category_and_note( $codes_arr, $outcome );

            $rows[] = [
                self::format_timestamp_for_export( (int) ( $event['time'] ?? 0 ) ),
                (string) ( $event['integration'] ?? '' ),
                (string) ( $event['label'] ?? '' ),
                $outcome,
                $context['category'],
                implode( ', ', $codes_arr ),
                $context['note'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,string>          $headers
     * @param array<int,array<int|string>> $rows
     */
    private static function stream_csv_download( string $filename, array $headers, array $rows ): void {
        \nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . \sanitize_file_name( $filename ) );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) {
            \wp_die( \esc_html__( 'Could not open export stream.', 'kitgenix-captcha-for-cloudflare-turnstile' ) );
        }

        fputcsv( $output, $headers );
        foreach ( $rows as $row ) {
            fputcsv( $output, $row );
        }

        fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output is a stream; WP_Filesystem does not support stream wrappers.
        exit;
    }

    private static function format_timestamp_for_display( int $timestamp ): string {
        if ( $timestamp <= 0 ) {
            return '';
        }

        $format = (string) \get_option( 'date_format' ) . ' ' . (string) \get_option( 'time_format' );
        if ( function_exists( '\wp_date' ) ) {
            return (string) \wp_date( $format, $timestamp );
        }

        return (string) \date_i18n( $format, $timestamp );
    }

    private static function format_timestamp_for_export( int $timestamp ): string {
        if ( $timestamp <= 0 ) {
            return '';
        }

        if ( function_exists( '\wp_date' ) ) {
            return (string) \wp_date( 'c', $timestamp );
        }

        return gmdate( 'c', $timestamp );
    }

    private static function format_percentage_for_csv( float $value ): string {
        return number_format( $value, 1, '.', '' ) . '%';
    }

    private static function render_sidebar(): void {
        $social_base = defined( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' )
            ? constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' ) . 'images/social-media/'
            : '';
        ?>
        <aside class="kitgenix-settings-sidebar" aria-label="<?php echo esc_attr__( 'Help and links', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>">
            <div class="kitgenix-sidebar-card">
                <h2><?php echo esc_html__( 'Need Help?', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                <p><?php echo esc_html__( 'Open the documentation for setup guidance or send us a support request if you need help configuring the plugin.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                <div class="kitgenix-sidebar-actions">
                    <a class="button button-secondary" href="<?php echo esc_url( 'https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Documentation', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                    <a class="button button-primary" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Request Support', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                </div>
            </div>

            <div class="kitgenix-sidebar-card">
                <h2><?php echo esc_html__( 'Visit Our Official Facebook Group', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                <p><?php echo esc_html__( 'Join the Kitgenix community to ask questions, share feedback, and keep up with product updates.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                <div class="kitgenix-sidebar-actions">
                    <a class="button button-secondary" href="<?php echo esc_url( 'https://www.facebook.com/groups/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Join Group', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
                </div>
            </div>

            <div class="kitgenix-sidebar-card">
                <h2><?php echo esc_html__( 'Follow Us', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
                <p><?php echo esc_html__( 'Keep up with new releases, tutorials, and product news across our channels.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
                <div class="kitgenix-sidebar-social-grid">
                    <a class="kitgenix-sidebar-social-link" href="https://kitgenix.com" target="_blank" rel="noopener noreferrer" aria-label="Website" title="Website"><img src="<?php echo esc_url( $social_base . 'globe-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.facebook.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><img src="<?php echo esc_url( $social_base . 'facebook-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.instagram.com/kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><img src="<?php echo esc_url( $social_base . 'instagram-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.youtube.com/@Kitgenix" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><img src="<?php echo esc_url( $social_base . 'youtube-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.reddit.com/r/Kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Reddit" title="Reddit"><img src="<?php echo esc_url( $social_base . 'reddit-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.linkedin.com/company/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><img src="<?php echo esc_url( $social_base . 'linkedin-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://x.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="X" title="X"><img src="<?php echo esc_url( $social_base . 'x-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.tiktok.com/@kitgenix" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok"><img src="<?php echo esc_url( $social_base . 'tiktok-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://github.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub"><img src="<?php echo esc_url( $social_base . 'github-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                </div>
            </div>
        </aside>
        <?php
    }
}

