<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Script_Handler {

    public static function init() {
        \add_action('wp_print_scripts', [__CLASS__, 'detect_duplicate_loader'], 99);
        \add_action('admin_init', [__CLASS__, 'handle_dup_notice_dismiss']);
        \add_action('admin_notices', [__CLASS__, 'admin_notice_duplicate_loader']);

        // Frontend + login
        \add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);
        \add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_public_assets']);

        // Admin assets and alignment
        \add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // Late alignment fixes so they win the cascade
        \add_action('login_enqueue_scripts', [__CLASS__, 'align_login_turnstile'], 99);
        \add_action('admin_enqueue_scripts', [__CLASS__, 'align_admin_turnstile'], 99);

        // Speed up first paint of the widget
        \add_filter('wp_resource_hints', [__CLASS__, 'resource_hints'], 10, 2);
    }

    /**
     * Detect if any other handle is loading Cloudflare Turnstile API.
     * Runs late on frontend when scripts are about to print.
     */
    public static function detect_duplicate_loader() {
        if ( \is_admin() ) {
            return; // only care about frontend double-loads
        }

        $wp_scripts = \wp_scripts();
        if ( ! $wp_scripts || empty( $wp_scripts->registered ) ) {
            return;
        }

        $matches = [];
        foreach ( $wp_scripts->registered as $handle => $obj ) {
            if ( $handle === 'kitgenix-captcha-for-cloudflare-turnstile' ) {
                continue;
            }
            $src = isset( $obj->src ) ? (string) $obj->src : '';
            if ( $src && stripos( $src, 'challenges.cloudflare.com/turnstile/v0/api.js' ) !== false ) {
                $matches[ $handle ] = $src;
            }
        }

        if ( ! empty( $matches ) ) {
            \set_transient(
                'kitgenix_turnstile_duplicate_scripts',
                [
                    'when'    => time(),
                    'matches' => $matches,
                ],
                12 * HOUR_IN_SECONDS
            );
        }
    }

    /**
     * Dismiss the duplicate loader notice via nonce’d link.
     */
    public static function handle_dup_notice_dismiss() {
        if ( ! isset( $_GET['kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        $nonce = isset( $_GET['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $nonce || ! \wp_verify_nonce( $nonce, 'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss' ) ) {
            return;
        }

        \delete_transient( 'kitgenix_turnstile_duplicate_scripts' );
        \wp_safe_redirect( \remove_query_arg( [ 'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe', '_wpnonce' ] ) );
        exit;
    }

    /**
     * Show admin notice if duplicate Turnstile API loaders were detected.
     */
    public static function admin_notice_duplicate_loader() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        $data = \get_transient( 'kitgenix_turnstile_duplicate_scripts' );
        if ( empty( $data ) || empty( $data['matches'] ) || ! is_array( $data['matches'] ) ) {
            return;
        }

        // Limit where we show this (our settings page + Plugins screen).
        $screen    = \function_exists( 'get_current_screen' ) ? \get_current_screen() : null;
        $screen_id = $screen ? (string) $screen->id : '';
        $allowed   = (
            strpos( $screen_id, 'kitgenix-captcha-for-cloudflare-turnstile' ) !== false
            || $screen_id === 'plugins'
        );
        if ( ! $allowed ) {
            return;
        }

        $dismiss_url = \wp_nonce_url(
            \add_query_arg( 'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe', '1' ),
            'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss'
        );

        // Build list HTML with escaped pieces.
        $list = '';
        foreach ( $data['matches'] as $handle => $src ) {
            $list .= '<li><code>' . \esc_html( $handle ) . '</code> &mdash; <span style="word-break:break-all;">' . \esc_html( $src ) . '</span></li>';
        }
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong><?php echo \esc_html__( 'Cloudflare Turnstile is being loaded more than once.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong></p>
            <p><?php echo \esc_html__( 'Another plugin or theme also enqueues the Turnstile API. Double-loading can break rendering or callbacks. Consider disabling the other loader and let this plugin load the API once.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
            <ul style="margin-left:18px;list-style:disc;"><?php echo $list; ?></ul>
            <p><a class="button button-secondary" href="<?php echo \esc_url( $dismiss_url ); ?>">
                <?php echo \esc_html__( 'Dismiss notice', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
            </a></p>
        </div>
        <?php
    }

    /**
     * Public-facing assets (frontend + login).
     */
    public static function enqueue_public_assets() {
        $settings = self::get_settings();

        $site_key   = $settings['site_key'] ?? '';
        $whitelisted = class_exists(\KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist::class)
            ? \KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist::is_whitelisted()
            : false;

        // --- Cloudflare Turnstile -----------------------------------------------------------
        // Only enqueue api.js if we have a site key and the request isn't whitelisted.
        if ( $site_key && ! $whitelisted ) {
            $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=kitgenix_captcha_for_cloudflare_turnstile_TurnstileOnLoad';
            if ( ! empty( $settings['language'] ) && 'auto' !== $settings['language'] ) {
                $url .= '&hl=' . rawurlencode( (string) $settings['language'] );
            }
            $url = \apply_filters( 'kitgenix_captcha_for_cloudflare_turnstile_script_url', $url, $settings );

            $args = \version_compare( \get_bloginfo( 'version' ), '6.3', '>=' )
                ? [ 'in_footer' => true, 'strategy' => 'async' ]
                : true;

            // Register external API (set a version to satisfy linters/caching heuristics).
            \wp_register_script(
                'kitgenix-captcha-for-cloudflare-turnstile',
                $url,
                [],
                constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' ),
                $args
            );

            // On WP < 6.3 but >= 5.7, add async via the official helper.
            if ( \version_compare( \get_bloginfo( 'version' ), '6.3', '<' ) && \version_compare( \get_bloginfo( 'version' ), '5.7', '>=' ) ) {
                \wp_script_add_data( 'kitgenix-captcha-for-cloudflare-turnstile', 'async', true );
            }

            // Define onload callback BEFORE the external script tag (handle must be registered already)
            \wp_add_inline_script(
                'kitgenix-captcha-for-cloudflare-turnstile',
                'window.kitgenix_captcha_for_cloudflare_turnstile_TurnstileOnLoad=function(){try{var m=window.KitgenixCaptchaForCloudflareTurnstile;if(m&&typeof m.renderWidgets==="function"){m.renderWidgets();}}catch(e){if(window.console)console.error(e);}};',
                'before'
            );

            \wp_enqueue_script( 'kitgenix-captcha-for-cloudflare-turnstile' );
        }

        // --- Public JS/CSS with cache-busting ----------------------------------------------
        $base_path = \trailingslashit( constant( 'KitgenixCaptchaForCloudflareTurnstilePATH' ) );
        $base_url  = constant( 'KitgenixCaptchaForCloudflareTurnstileASSETS_URL' );

        $public_css_path = $base_path . 'assets/css/public.css';
        $public_js_path  = $base_path . 'assets/js/public.js';

        $css_ver = \file_exists( $public_css_path ) ? \filemtime( $public_css_path ) : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );
        $js_ver  = \file_exists( $public_js_path )  ? \filemtime( $public_js_path )  : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );

        \wp_register_style(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            $base_url . 'css/public.css',
            [],
            $css_ver
        );
        \wp_register_script(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            $base_url . 'js/public.js',
            [ 'jquery' ],
            $js_ver,
            true
        );

        // Config BEFORE public.js
        $fresh_ms = (int) \apply_filters('kitgenix_turnstile_freshness_ms', 150000); // 2.5 minutes default
        $config = [
            'site_key'       => $site_key,
            'disable_submit' => ! empty( $settings['disable_submit'] ),
            'appearance'     => $settings['appearance'] ?? 'always',
            'size'           => $settings['widget_size'] ?? 'normal',
            'theme'          => $settings['theme'] ?? 'auto',
            'extra_message'  => $settings['extra_message'] ?? '',
            'debug'          => defined('WP_DEBUG') && WP_DEBUG,
            // NEW:
            'dev_mode'       => ! empty( $settings['dev_mode_warn_only'] ),
            'annotate_dev'   => \current_user_can( 'manage_options' ), // only show inline warnings to admins

            // NEW: replay inline message
            'replay_message' => \__( 'Your verification expired. Please complete the Turnstile challenge again.', 'kitgenix-captcha-for-cloudflare-turnstile' ),

            // NEW: freshness in ms
            'freshness_ms'   => $fresh_ms,
        ];
        \wp_add_inline_script(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            'window.KitgenixCaptchaForCloudflareTurnstileConfig=' . \wp_json_encode( $config ) . ';',
            'before'
        );

        // Woo Blocks bridge: attach token to Store API requests (header + JSON extensions)
        \wp_add_inline_script(
            'kitgenix-captcha-for-cloudflare-turnstile-public',
            "(function(){try{
                if(!window.fetch) return;
                function kitgenixcaptchaforcloudflareturnstileGetToken(){
                    try{
                        if(window.KitgenixCaptchaForCloudflareTurnstile && typeof window.KitgenixCaptchaForCloudflareTurnstile.getLastToken==='function'){
                            return window.KitgenixCaptchaForCloudflareTurnstile.getLastToken()||'';
                        }
                        var i=document.querySelector('input[name=\"cf-turnstile-response\"]');
                        return i&&i.value?i.value:'';
                    }catch(e){return '';}
                }
                var _fetch=window.fetch;
                window.fetch=function(input, init){
                    try{
                        var url = (typeof input==='string') ? input : (input && input.url) || '';
                        if(url && url.indexOf('/wc/store/')!==-1 && /(checkout|cart)/.test(url)){
                            init = init || {};
                            var h = new (window.Headers||Object)( (init && init.headers) || (input && input.headers) || {} );
                            var t = kitgenixcaptchaforcloudflareturnstileGetToken();
                            if(t && h.set){ h.set('X-Turnstile-Token', t); }
                            if(t && init && typeof init.body==='string' && init.body.trim().charAt(0)==='{'){
                                try{
                                    var b = JSON.parse(init.body); b.extensions = b.extensions || {};
                                    b.extensions.kitgenix_captcha_for_cloudflare_turnstile_turnstile = { token: t }; init.body = JSON.stringify(b);
                                }catch(e){}
                            }
                            if(h && init){ init.headers = h; }
                        }
                    }catch(e){}
                    return _fetch(input, init);
                };
            }catch(e){}})();",
            'after'
        );

        \wp_enqueue_style(  'kitgenix-captcha-for-cloudflare-turnstile-public' );
        \wp_enqueue_script( 'kitgenix-captcha-for-cloudflare-turnstile-public' );
    }

    /**
     * Admin assets (heavy assets only on our pages; alignment fix everywhere via align_admin_turnstile()).
     */
    public static function enqueue_admin_assets( $hook ) {
        // Heavy assets only on our plugin screens
        if ( \strpos( (string) $hook, 'kitgenix-captcha-for-cloudflare-turnstile' ) === false ) {
            return;
        }

        $base_path = \trailingslashit( constant( 'KitgenixCaptchaForCloudflareTurnstilePATH' ) );
        $base_url  = constant( 'KitgenixCaptchaForCloudflareTurnstileASSETS_URL' );

        $admin_css_path = $base_path . 'assets/css/admin.css';
        $admin_js_path  = $base_path . 'assets/js/admin.js';

        $css_ver = \file_exists( $admin_css_path ) ? \filemtime( $admin_css_path ) : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );
        $js_ver  = \file_exists( $admin_js_path )  ? \filemtime( $admin_js_path )  : constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' );

        \wp_enqueue_style(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            $base_url . 'css/admin.css',
            [],
            $css_ver
        );
        \wp_enqueue_script(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            $base_url . 'js/admin.js',
            [ 'jquery' ],
            $js_ver,
            true
        );
    }

    /**
     * Late CSS for ALL wp-login.php screens (login, lost password, reset, register).
     * Runs with priority 99 so it overrides earlier rules.
     */
    public static function align_login_turnstile() {
        \wp_register_style( 'kitgenix-turnstile-login-align', false, [], constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' ) );
        \wp_enqueue_style( 'kitgenix-turnstile-login-align' );

        \wp_add_inline_style(
            'kitgenix-turnstile-login-align',
            // General catch-all on wp-login pages:
            'body.login .cf-turnstile{width:100% !important;display:grid !important;place-items:center !important;text-align:center !important;margin:8px 0 16px}
             body.login .cf-turnstile > div, body.login .cf-turnstile iframe{margin:0 auto !important;float:none !important}
             /* Explicitly cover each core form ID */
             body.login #loginform .cf-turnstile,
             body.login #lostpasswordform .cf-turnstile,
             body.login #resetpassform .cf-turnstile,
             body.login #registerform .cf-turnstile{width:100% !important;}'
        );
    }

    /**
     * Late CSS for ALL wp-admin screens to center Turnstile.
     */
    public static function align_admin_turnstile() {
        \wp_register_style( 'kitgenix-turnstile-admin-align', false, [], constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' ) );
        \wp_enqueue_style( 'kitgenix-turnstile-admin-align' );

        \wp_add_inline_style(
            'kitgenix-turnstile-admin-align',
            'body.wp-admin .cf-turnstile{width:100% !important;display:grid !important;place-items:center !important}
             body.wp-admin .cf-turnstile > div, body.wp-admin .cf-turnstile iframe{margin:0 auto !important;float:none !important}'
        );
    }

    /**
     * Add resource hints for the CF Turnstile origin.
     * Helps browsers warm up the connection before api.js and verification calls.
     */
    public static function resource_hints( $hints, $rel ) {
        if ( $rel === 'preconnect' || $rel === 'dns-prefetch' ) {
            $origin = 'https://challenges.cloudflare.com';
            if ( ! in_array( $origin, $hints, true ) ) {
                $hints[] = $origin;
            }
        }
        return $hints;
    }

    /**
     * Retrieve plugin settings.
     */
    private static function get_settings() {
        $opts = \get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
        return \is_array( $opts ) ? $opts : [];
    }
}
