<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Core;

defined('ABSPATH') || exit;

class Script_Handler {

    /**
     * Normalize widget size to Cloudflare-supported values.
     *
     * @param string $size Raw saved widget size.
     */
    public static function normalize_widget_size( string $size ): string {
        $size = strtolower( trim( $size ) );

        switch ( $size ) {
            case 'small':
            case 'compact':
                return 'compact';

            case 'flexible':
                return 'flexible';

            case 'medium':
            case 'large':
            case 'normal':
            default:
                return 'normal';
        }
    }

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
            if ( $src && stripos( $src, 'challenges.cloudflare.com/turnstile/v0/api.js' ) !== false ) { // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Cloudflare Turnstile's own official widget domain, the plugin's core third-party CAPTCHA service; there is no self-hosted alternative.
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
        if ( '' === $nonce ) {
            return;
        }
        if ( ! \wp_verify_nonce( $nonce, 'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss' ) ) {
            return;
        }

        \delete_transient( 'kitgenix_turnstile_duplicate_scripts' );
        \wp_safe_redirect( \remove_query_arg( [ 'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe', '_wpnonce' ] ) );
        exit;
    }

    /**
     * Return normalized duplicate-loader detection details.
     *
     * @return array<string,mixed>
     */
    public static function get_duplicate_loader_detection(): array {
        $data = \get_transient( 'kitgenix_turnstile_duplicate_scripts' );
        if ( empty( $data ) || empty( $data['matches'] ) || ! is_array( $data['matches'] ) ) {
            return [];
        }

        $matches = [];
        foreach ( $data['matches'] as $handle => $src ) {
            $handle = \sanitize_key( (string) $handle );
            $src    = \esc_url_raw( (string) $src );

            if ( '' === $handle || '' === $src ) {
                continue;
            }

            $matches[ $handle ] = $src;
        }

        if ( empty( $matches ) ) {
            return [];
        }

        return [
            'when'        => isset( $data['when'] ) ? (int) $data['when'] : 0,
            'matches'     => $matches,
            'match_count' => count( $matches ),
        ];
    }

    /**
     * Return the nonce-protected dismissal URL for duplicate-loader notices.
     */
    public static function get_duplicate_loader_dismiss_url(): string {
        return (string) \wp_nonce_url(
            \add_query_arg( 'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss_dupe', '1' ),
            'kitgenix_captcha_for_cloudflare_turnstile_ts_dismiss'
        );
    }

    /**
     * Show admin notice if duplicate Turnstile API loaders were detected.
     */
    public static function admin_notice_duplicate_loader() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        $data = self::get_duplicate_loader_detection();
        if ( empty( $data['matches'] ) || ! is_array( $data['matches'] ) ) {
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

        $dismiss_url = self::get_duplicate_loader_dismiss_url();

        // Build list HTML with escaped pieces.
        $list = '';
        foreach ( $data['matches'] as $handle => $src ) {
            $list .= '<li><code>' . \esc_html( $handle ) . '</code> &mdash; <span style="word-break:break-all;">' . \esc_html( $src ) . '</span></li>';
        }
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong><?php echo \esc_html__( 'Cloudflare Turnstile is being loaded more than once.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong></p>
            <p><?php echo \esc_html__( 'Another plugin or theme also enqueues the Turnstile API. Double-loading can break rendering or callbacks. Consider disabling the other loader and let this plugin load the API once.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
            <ul style="margin-left:18px;list-style:disc;"><?php
                $allowed = [
                    'li'   => [],
                    'code' => [],
                    'span' => [ 'style' => true ],
                ];
                echo wp_kses( $list, $allowed );
            ?></ul>
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
        // Only enqueue api.js if we have a site key, the request isn't whitelisted, and the
        // outage failsafe isn't currently bypassing verification. When the failsafe is active,
        // loading a script from a service we've just independently confirmed is unreachable
        // would only leave every widget on the page stuck in a broken loading state; skipping
        // it instead leaves the (inert) container harmlessly empty while Turnstile_Validator
        // lets submissions through without a token. See Cloudflare_Health.
        if ( $site_key && ! $whitelisted && ! Cloudflare_Health::failsafe_active() ) {
            $url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=kitgenix_captcha_for_cloudflare_turnstile_TurnstileOnLoad'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Cloudflare Turnstile's own official widget script, the plugin's core third-party CAPTCHA service; there is no self-hosted alternative.
            if ( ! empty( $settings['language'] ) && 'auto' !== $settings['language'] ) {
                $url .= '&hl=' . rawurlencode( (string) $settings['language'] );
            }
            $url = \apply_filters( 'kitgenix_captcha_for_cloudflare_turnstile_script_url', $url, $settings );

            $args = \version_compare( \get_bloginfo( 'version' ), '6.3', '>=' )
                ? [ 'in_footer' => true, 'strategy' => 'async' ]
                : true;

            // No $ver: this is Cloudflare's own CDN-hosted script, not a local asset —
            // appending our plugin version as a `&ver=` query param pollutes their URL
            // (Turnstile's own api.js logs "Unknown parameter ... ignoring" for it) and
            // defeats Cloudflare's own cache headers for no benefit.
            \wp_register_script(
                'kitgenix-captcha-for-cloudflare-turnstile',
                $url,
                [],
                null,
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
        $base_path = \trailingslashit( constant( 'KitgenixCaptchaForCloudflareTurnstile_Path' ) );
        $base_url  = constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' );

        $public_css_path = $base_path . 'assets/css/public.css';
        $public_js_path  = $base_path . 'assets/js/public.js';

        $css_ver = \file_exists( $public_css_path ) ? \filemtime( $public_css_path ) : constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' );
        $js_ver  = \file_exists( $public_js_path )  ? \filemtime( $public_js_path )  : constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' );

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

        // Register script translations for JS strings (if available)
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                'kitgenix-captcha-for-cloudflare-turnstile-public',
                'kitgenix-captcha-for-cloudflare-turnstile',
                constant( 'KitgenixCaptchaForCloudflareTurnstile_Path' ) . 'languages'
            );
        }

        // Config BEFORE public.js
        $fresh_ms = (int) \apply_filters('kitgenix_turnstile_freshness_ms', 150000); // 2.5 minutes default
        $config = [
            'site_key'       => $site_key,
            'disable_submit' => ! empty( $settings['disable_submit'] ),
            'appearance'     => $settings['appearance'] ?? 'always',
            'size'           => self::normalize_widget_size( (string) ( $settings['widget_size'] ?? 'normal' ) ),
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
            // Modes exposed to JS for defensive behavior (e.g., suppress Blocks auto-render in shortcode-only)
            'modes'          => [
                'woocommerce_blocks' => $settings['mode_woocommerce_blocks'] ?? 'auto',
            ],
            // Effective per-integration widget attributes for WooCommerce Blocks Checkout.
            // The Blocks checkout is a client-rendered React tree: on some page loads it
            // re-renders the "Checkout Actions" area after our PHP-injected container has
            // already been added, wiping it out before Turnstile ever mounts into it. The
            // public JS uses this to rebuild the container on the client as a fallback so
            // the widget shows up without requiring a page reload.
            'wc_blocks'      => [
                'theme'       => self::get_effective_theme( 'woocommerce_blocks' ),
                'size'        => self::get_effective_size( 'woocommerce_blocks' ),
                'appearance'  => self::get_effective_appearance(),
                'language'    => self::get_effective_language_override( 'woocommerce_blocks' ),
            ],
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

        $base_path = \trailingslashit( constant( 'KitgenixCaptchaForCloudflareTurnstile_Path' ) );
        $base_url  = constant( 'KitgenixCaptchaForCloudflareTurnstile_Assets_URL' );

        \wp_enqueue_style( 'kitgenix-captcha-for-cloudflare-turnstile-admin-ui' );

        $admin_css_path = $base_path . 'assets/css/admin.css';
        $admin_js_path  = $base_path . 'assets/js/admin.js';
        $ui_js_path     = $base_path . 'assets/js/kitgenix-admin-tabs.js';

        $css_ver   = \file_exists( $admin_css_path ) ? \filemtime( $admin_css_path ) : constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' );
        $js_ver    = \file_exists( $admin_js_path )  ? \filemtime( $admin_js_path )  : constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' );
        $ui_js_ver = \file_exists( $ui_js_path )     ? \filemtime( $ui_js_path )     : constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' );

        \wp_enqueue_style(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            $base_url . 'css/admin.css',
            [ 'kitgenix-captcha-for-cloudflare-turnstile-admin-ui' ],
            $css_ver
        );
        \wp_enqueue_script(
            'kitgenix-admin-tabs',
            $base_url . 'js/kitgenix-admin-tabs.js',
            [],
            $ui_js_ver,
            true
        );
        \wp_enqueue_script(
            'kitgenix-captcha-for-cloudflare-turnstile-admin',
            $base_url . 'js/admin.js',
            [ 'jquery' ],
            $js_ver,
            true
        );
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                'kitgenix-captcha-for-cloudflare-turnstile-admin',
                'kitgenix-captcha-for-cloudflare-turnstile',
                constant( 'KitgenixCaptchaForCloudflareTurnstile_Path' ) . 'languages'
            );
        }
        // Localize admin-only config: AJAX URL + reveal-secret action/nonce.
        if ( function_exists( '\wp_create_nonce' ) ) {
            \wp_localize_script(
                'kitgenix-captcha-for-cloudflare-turnstile-admin',
                'KitgenixTurnstileAdmin',
                [
                    'ajax_url' => \admin_url( 'admin-ajax.php' ),
                    // Action name handled by Settings_UI::ajax_get_secret
                    'reveal_secret_action' => 'kitgenix_turnstile_get_secret',
                    // Nonce to protect the reveal-secret AJAX endpoint
                    'reveal_secret_nonce'  => \wp_create_nonce( 'kitgenix_turnstile_reveal_secret' ),
                    'verify_setup_action'  => Setup_Verification::get_ajax_action(),
                    'verify_setup_nonce'   => \wp_create_nonce( Setup_Verification::get_ajax_nonce() ),
                ]
            );
        }
    }

    /**
     * Late CSS for ALL wp-login.php screens (login, lost password, reset, register).
     * Runs with priority 99 so it overrides earlier rules.
     */
    public static function align_login_turnstile() {
        \wp_register_style( 'kitgenix-captcha-for-cloudflare-turnstile-login-align', false, [], constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) );
        \wp_enqueue_style( 'kitgenix-captcha-for-cloudflare-turnstile-login-align' );

        \wp_add_inline_style(
            'kitgenix-captcha-for-cloudflare-turnstile-login-align',
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
        \wp_register_style( 'kitgenix-captcha-for-cloudflare-turnstile-admin-align', false, [], constant( 'KitgenixCaptchaForCloudflareTurnstile_Version' ) );
        \wp_enqueue_style( 'kitgenix-captcha-for-cloudflare-turnstile-admin-align' );

        \wp_add_inline_style(
            'kitgenix-captcha-for-cloudflare-turnstile-admin-align',
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
            $origin = 'https://challenges.cloudflare.com'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Cloudflare Turnstile's own official widget domain, the plugin's core third-party CAPTCHA service; there is no self-hosted alternative.
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

    /**
     * Canonical Turnstile-supported language codes. Kept as the single source of truth;
     * Admin_Options::sanitize_settings_payload() validates against this same list.
     *
     * @return string[]
     */
    public static function get_allowed_languages(): array {
        return [ 'auto', 'en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh-CN', 'zh-TW', 'ja', 'ko', 'ar', 'tr', 'pl', 'nl', 'sv', 'fi', 'da', 'no', 'cs', 'hu', 'el', 'he', 'uk', 'ro', 'bg', 'id', 'th', 'vi' ];
    }

    /**
     * Canonical integration keys eligible for per-integration widget overrides
     * (theme/size/language) and for Display → "Per-integration widget overrides".
     * Keys mirror the existing `enable_{key}` settings suffixes.
     *
     * @return array<string,string> key => human-readable label
     */
    public static function get_override_integration_keys(): array {
        return [
            'wordpress'            => \__( 'WordPress Core (login, register, lost password, comments)', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'woocommerce'          => \__( 'WooCommerce (checkout, reviews, account)', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'woocommerce_blocks'   => \__( 'WooCommerce Blocks (Checkout block)', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'edd'                  => \__( 'Easy Digital Downloads', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'elementor'            => \__( 'Elementor Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'wpforms'              => \__( 'WPForms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'fluentforms'          => \__( 'Fluent Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'gravityforms'         => \__( 'Gravity Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'cf7'                  => \__( 'Contact Form 7', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'formidableforms'      => \__( 'Formidable Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'forminator'           => \__( 'Forminator', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'jetpackforms'         => \__( 'Jetpack Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'kadenceforms'         => \__( 'Kadence Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'jetformbuilder'       => \__( 'JetFormBuilder', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'ninjaforms'           => \__( 'Ninja Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'buddypress'           => \__( 'BuddyPress', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'bbpress'              => \__( 'bbPress', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'mailpoet'             => \__( 'MailPoet', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'memberpress'          => \__( 'MemberPress', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'paidmembershipspro'   => \__( 'Paid Memberships Pro', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'ultimatemember'       => \__( 'Ultimate Member', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'wpdiscuz'             => \__( 'wpDiscuz', 'kitgenix-captcha-for-cloudflare-turnstile' ),
            'kitgenix_plugin_score' => \__( 'Kitgenix Plugin Score', 'kitgenix-captcha-for-cloudflare-turnstile' ),
        ];
    }

    /**
     * Map each fine-grained `Turnstile_Validator` analytics/diagnostics integration
     * key (e.g. 'woocommerce-checkout', 'kgps-login') to the coarser top-level
     * integration key used by settings (`enable_{key}` / `mode_{key}`) and by
     * get_override_integration_keys() above. Used by the admin Integration Health
     * Matrix to show each analytics row's actual Enabled/Mode state without
     * duplicating a second, drift-prone copy of this list.
     *
     * @return array<string,string> analytics integration key => top-level settings key
     */
    public static function get_integration_analytics_key_map(): array {
        return [
            'bbpress'                       => 'bbpress',
            'buddypress'                    => 'buddypress',
            'cf7'                            => 'cf7',
            'edd'                            => 'edd',
            'edd-account'                    => 'edd',
            'edd-checkout'                   => 'edd',
            'elementor'                      => 'elementor',
            'fluentforms'                    => 'fluentforms',
            'formidableforms'                => 'formidableforms',
            'forminator'                     => 'forminator',
            'gravityforms'                   => 'gravityforms',
            'jetformbuilder'                 => 'jetformbuilder',
            'jetpackforms'                   => 'jetpackforms',
            'kadenceforms'                   => 'kadenceforms',
            'kgps-login'                     => 'kitgenix_plugin_score',
            'kgps-register'                  => 'kitgenix_plugin_score',
            'kgps-lostpassword'              => 'kitgenix_plugin_score',
            'mailpoet'                       => 'mailpoet',
            'ninjaforms'                     => 'ninjaforms',
            'memberpress'                    => 'memberpress',
            'memberpress-signup'             => 'memberpress',
            'paid-memberships-pro'           => 'paidmembershipspro',
            'paid-memberships-pro-checkout'  => 'paidmembershipspro',
            'ultimate-member'                => 'ultimatemember',
            'woocommerce'                    => 'woocommerce',
            'woocommerce-account'            => 'woocommerce',
            'woocommerce-checkout'           => 'woocommerce',
            'woocommerce-login'              => 'woocommerce',
            'woocommerce-review'             => 'woocommerce',
            'woocommerce-blocks-checkout'    => 'woocommerce_blocks',
            'wordpress-comment'              => 'wordpress',
            'wordpress-login'                => 'wordpress',
            'wordpress-lostpassword'         => 'wordpress',
            'wordpress-register'             => 'wordpress',
            'wordpress-resetpassword'        => 'wordpress',
            'wp-core'                        => 'wordpress',
            'wpdiscuz'                       => 'wpdiscuz',
            'wpforms'                        => 'wpforms',
        ];
    }

    /**
     * Resolve an analytics integration key's Enabled + Mode state from settings,
     * via get_integration_analytics_key_map() above.
     *
     * @return array{group:string,enabled:bool,mode:string} mode is '' when this
     *         integration has no auto/shortcode mode setting (e.g. WordPress core).
     */
    public static function get_integration_protection_state( string $analytics_key ): array {
        $map   = self::get_integration_analytics_key_map();
        $group = $map[ $analytics_key ] ?? '';
        if ( '' === $group ) {
            return [ 'group' => '', 'enabled' => false, 'mode' => '' ];
        }

        $settings = self::get_settings();
        $enabled  = ! empty( $settings[ 'enable_' . $group ] );
        $mode     = isset( $settings[ 'mode_' . $group ] ) ? (string) $settings[ 'mode_' . $group ] : '';

        return [ 'group' => $group, 'enabled' => $enabled, 'mode' => $mode ];
    }

    /**
     * Resolve the effective theme for one integration: its own override when set,
     * otherwise the global Display → Theme setting.
     */
    public static function get_effective_theme( string $key ): string {
        $settings = self::get_settings();
        $override = isset( $settings[ 'theme_override_' . $key ] ) ? (string) $settings[ 'theme_override_' . $key ] : '';
        if ( \in_array( $override, [ 'auto', 'light', 'dark' ], true ) ) {
            return $override;
        }

        $theme = (string) ( $settings['theme'] ?? 'auto' );
        return \in_array( $theme, [ 'auto', 'light', 'dark' ], true ) ? $theme : 'auto';
    }

    /**
     * Resolve the effective widget size for one integration: its own override when set,
     * otherwise the global Display → Widget Size setting.
     */
    public static function get_effective_size( string $key ): string {
        $settings = self::get_settings();
        $override = isset( $settings[ 'size_override_' . $key ] ) ? (string) $settings[ 'size_override_' . $key ] : '';
        if ( '' !== $override ) {
            return self::normalize_widget_size( $override );
        }

        return self::normalize_widget_size( (string) ( $settings['widget_size'] ?? 'normal' ) );
    }

    /**
     * Resolve the effective widget appearance (not per-integration overridable today,
     * kept here so callers have one place to read every widget attribute from).
     */
    public static function get_effective_appearance(): string {
        $settings   = self::get_settings();
        $appearance = (string) ( $settings['appearance'] ?? 'always' );
        return \in_array( $appearance, [ 'always', 'interaction-only' ], true ) ? $appearance : 'always';
    }

    /**
     * Resolve an active per-integration language override, or '' when the integration
     * should keep relying on the global `hl` script parameter (no `data-language` attribute).
     */
    public static function get_effective_language_override( string $key ): string {
        $settings = self::get_settings();
        $override = isset( $settings[ 'language_override_' . $key ] ) ? (string) $settings[ 'language_override_' . $key ] : '';
        return \in_array( $override, self::get_allowed_languages(), true ) ? $override : '';
    }

    /**
     * Render the zero-JS honeypot trap field (empty string when the setting is off).
     * A real visitor never sees or fills this field (see public.css); a filled value
     * means whatever submitted the form skipped rendering/JS entirely, so
     * Turnstile_Validator::is_valid_submission()/validate_token() reject it before
     * even contacting Cloudflare. Integrations echo this next to their widget markup.
     */
    public static function render_honeypot_field(): string {
        $settings = self::get_settings();
        if ( empty( $settings['honeypot_enabled'] ) ) {
            return '';
        }

        $field_name = Turnstile_Validator::honeypot_field_name();

        return '<div class="kitgenix-captcha-for-cloudflare-turnstile-hp-wrap" aria-hidden="true">'
            . '<label for="' . \esc_attr( $field_name ) . '">' . \esc_html__( 'Leave this field empty', 'kitgenix-captcha-for-cloudflare-turnstile' ) . '</label>'
            . '<input type="text" name="' . \esc_attr( $field_name ) . '" id="' . \esc_attr( $field_name ) . '" value="" tabindex="-1" autocomplete="off" />'
            . '</div>';
    }

    /**
     * Build the shared data-sitekey/data-theme/data-size/data-appearance (+ optional
     * data-language) attribute string used by every integration's Turnstile widget markup.
     * Centralizing this means a per-integration override (or any future shared attribute)
     * only needs to change here instead of in every integration file.
     */
    public static function get_widget_data_attributes( string $key, string $site_key ): string {
        $attrs = ' data-sitekey="' . \esc_attr( $site_key ) . '"'
            . ' data-theme="' . \esc_attr( self::get_effective_theme( $key ) ) . '"'
            . ' data-size="' . \esc_attr( self::get_effective_size( $key ) ) . '"'
            . ' data-appearance="' . \esc_attr( self::get_effective_appearance() ) . '"';

        $language_override = self::get_effective_language_override( $key );
        if ( '' !== $language_override ) {
            $attrs .= ' data-language="' . \esc_attr( $language_override ) . '"';
        }

        return $attrs;
    }
}
