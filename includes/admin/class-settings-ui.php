<?php
/**
 * Admin Settings UI
 *
 * @package KitgenixCaptchaForCloudflareTurnstile
 */

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
use function esc_html__;
use function esc_textarea;
use function submit_button;
use function in_array;
use function defined;
use function __;
use function esc_url;

defined( 'ABSPATH' ) || exit;

class Settings_UI {

	/**
	 * The page hook suffix returned by add_options_page().
	 *
	 * @var string|null
	 */
	private static $page_hook = null;

	/**
	 * Initialize admin menu and page rendering.
	 */
	public static function init(): void {
		\add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		\add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Register the plugin settings page.
	 */
	public static function register_menu(): void {
		self::$page_hook = \add_options_page(
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

		// Admin CSS for the UI (enqueue if your plugin registers it elsewhere).
		// Example:
		// \wp_enqueue_style( 'kitgenix-captcha-for-cloudflare-turnstile-admin' );

		// Bail if no site key yet — the test widget can't render.
		if ( ! $site_key ) {
			return;
		}

		$ver = defined( 'KitgenixCaptchaForCloudflareTurnstileVERSION' )
			? \constant( 'KitgenixCaptchaForCloudflareTurnstileVERSION' )
			: null;

		// Tiny shim handle just to attach our onload callback before the API.
		\wp_register_script(
			'kitgenix-captcha-for-cloudflare-turnstile-admin',
			false,
			[],
			$ver,
			true
		);

		$theme      = $settings['theme']        ?? 'auto';
		$size       = $settings['widget_size']  ?? 'normal';
		$appearance = $settings['appearance']   ?? 'always';

		\wp_add_inline_script(
			'kitgenix-captcha-for-cloudflare-turnstile-admin',
			'window.KitgenixCaptchaForCloudflareTurnstileAdminTurnstileReady = function () {' .
				'try {' .
					'var el = document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-widget");' .
					'if (!el || typeof turnstile === "undefined" || el.dataset.rendered) { return; }' .
					'turnstile.render(el, {' .
						'sitekey: ' . \wp_json_encode( $site_key ) . ',' .
						'theme: ' . \wp_json_encode( $theme ) . ',' .
						'size: ' . \wp_json_encode( $size ) . ',' .
						'appearance: ' . \wp_json_encode( $appearance ) . ',' .
						'callback: function(){' .
							'var ok = document.getElementById("kitgenix-captcha-for-cloudflare-turnstile-test-success");' .
							'if (ok) { ok.style.display = "block"; ok.setAttribute("aria-hidden","false"); }' .
						'}' .
					'});' .
					'el.dataset.rendered = "true";' .
				'} catch (e) { if (window.console) console.error(e); }' .
			'};',
			'before'
		);
		\wp_enqueue_script( 'kitgenix-captcha-for-cloudflare-turnstile-admin' );

		// Load Turnstile API with onload pointing at our callback.
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

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Admin_Options::get_settings();

		// Active plugins (single site) — include plugin.php for is_plugin_active support if needed.
		if ( ! function_exists( '\is_plugin_active' ) ) {
			@include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active_plugins = (array) \apply_filters( 'active_plugins', \get_option( 'active_plugins', [] ) );

		// Admin notices area.
		do_action( 'admin_notices' );

		// Developer mode warning (global top).
		if ( ! empty( $settings['dev_mode_warn_only'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
				\esc_html__( 'Developer Mode (warn-only) is enabled.', 'kitgenix-captcha-for-cloudflare-turnstile' ) .
				'</strong> ' .
				\esc_html__( 'Turnstile failures will be logged but will not block form submissions.', 'kitgenix-captcha-for-cloudflare-turnstile' ) .
				'</p></div>';
		}
		?>
		<div class="wrap" id="kitgenix-captcha-for-cloudflare-turnstile-admin-app">
			<div class="kitgenix-captcha-for-cloudflare-turnstile-settings-intro">
				<h1 class="kitgenix-captcha-for-cloudflare-turnstile-admin-title"><?php echo \esc_html( \__( 'Kitgenix CAPTCHA for Cloudflare Turnstile', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h1>
				<p><?php echo \esc_html__( 'Seamlessly integrate Cloudflare’s free Turnstile CAPTCHA into your WordPress forms to enhance security and reduce spam – without compromising user experience.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-intro-links">
					<a href="<?php echo \esc_url( 'https://kitgenix.com/plugins/kitgenix-captcha-for-cloudflare-turnstile/documentation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'View Plugin Documentation', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
					<a href="<?php echo \esc_url( 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/reviews/#new-post' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Consider Leaving Us a Review', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
					<a href="<?php echo \esc_url( 'https://wordpress.org/support/plugin/kitgenix-captcha-for-cloudflare-turnstile/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( \__( 'Get Support', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
					<a href="<?php echo \esc_url( 'https://buymeacoffee.com/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer">☕ <?php echo \esc_html( \__( 'Buy us a coffee', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></a>
				</div>
			</div>

			<form method="post" action="options.php" autocomplete="off" novalidate>
				<?php \settings_fields( 'kitgenix_captcha_for_cloudflare_turnstile_settings_group' ); ?>
				<?php \wp_nonce_field( 'kitgenix_captcha_for_cloudflare_turnstile_settings_save', 'kitgenix_captcha_for_cloudflare_turnstile_settings_nonce' ); ?>

				<!-- Site Keys -->
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Cloudflare Turnstile Site Key & Secret Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description">
							<?php echo \esc_html__( 'You can obtain your Site Key and Secret Key by visiting:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?><br>
							<a href="<?php echo \esc_url( 'https://dash.cloudflare.com/?to=/:account/turnstile' ); ?>" target="_blank" rel="noopener noreferrer">https://dash.cloudflare.com/?to=/:account/turnstile</a>
						</p>

						<table class="form-table">
							<tr>
								<th><label for="site_key"><?php echo \esc_html__( 'Site Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="text" id="site_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[site_key]" value="<?php echo \esc_attr( $settings['site_key'] ?? '' ); ?>" class="regular-text" required autocomplete="off" /></td>
							</tr>
							<tr>
								<th><label for="secret_key"><?php echo \esc_html__( 'Secret Key', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="text" id="secret_key" name="kitgenix_captcha_for_cloudflare_turnstile_settings[secret_key]" value="<?php echo \esc_attr( $settings['secret_key'] ?? '' ); ?>" class="regular-text" required autocomplete="off" /></td>
							</tr>
						</table>

						<!-- Test widget -->
						<table class="form-table" >
							<tr class="jt-has-turnstile-test">
								<th scope="row"><label><?php echo \esc_html__( 'Test Cloudflare Turnstile Response', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td>
									<div
										id="kitgenix-captcha-for-cloudflare-turnstile-test-widget"
										class="cf-turnstile"
										data-sitekey="<?php echo \esc_attr( $settings['site_key'] ?? '' ); ?>"
										data-theme="<?php echo \esc_attr( $settings['theme'] ?? 'auto' ); ?>"
										data-size="<?php echo \esc_attr( $settings['widget_size'] ?? 'normal' ); ?>"
										data-appearance="<?php echo \esc_attr( $settings['appearance'] ?? 'always' ); ?>"
									></div>

									<div
										id="kitgenix-captcha-for-cloudflare-turnstile-test-success"
										role="status"
										aria-live="polite"
										aria-hidden="true"
										class="screen-reader-text"
									>
										<?php echo \esc_html__( 'Success! Your API keys are valid and Turnstile is functioning correctly.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
									</div>

									<?php if ( empty( $settings['site_key'] ) ) : ?>
										<div class="kitgenix-captcha-for-cloudflare-turnstile-warning description"><?php echo \esc_html__( 'Enter your Site Key above to test Turnstile.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></div>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Display Settings -->
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Display Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<table class="form-table">
							<tr>
								<th><label for="theme"><?php echo \esc_html__( 'Theme', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td>
									<select id="theme" name="kitgenix_captcha_for_cloudflare_turnstile_settings[theme]">
										<option value="auto"  <?php selected( $settings['theme'] ?? '', 'auto' );  ?>><?php echo \esc_html__( 'Auto', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<option value="light" <?php selected( $settings['theme'] ?? '', 'light' ); ?>><?php echo \esc_html__( 'Light', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<option value="dark"  <?php selected( $settings['theme'] ?? '', 'dark' );  ?>><?php echo \esc_html__( 'Dark',  'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
									</select>
									<p class="description"><?php echo \esc_html__( 'Select the visual style for the widget.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="widget_size"><?php echo \esc_html__( 'Widget Size', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td>
									<select id="widget_size" name="kitgenix_captcha_for_cloudflare_turnstile_settings[widget_size]">
										<option value="normal" <?php selected( $settings['widget_size'] ?? '', 'normal' ); ?>><?php echo \esc_html__( 'Normal', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<option value="small"  <?php selected( $settings['widget_size'] ?? '', 'small' );  ?>><?php echo \esc_html__( 'Small',  'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<option value="medium" <?php selected( $settings['widget_size'] ?? '', 'medium' ); ?>><?php echo \esc_html__( 'Medium', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<option value="large"  <?php selected( $settings['widget_size'] ?? '', 'large' );  ?>><?php echo \esc_html__( 'Large',  'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<option value="flexible"  <?php selected( $settings['widget_size'] ?? '', 'flexible' );  ?>><?php echo \esc_html__( 'Flexible (100% width)',  'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
									</select>
									<p class="description"><?php echo \esc_html__( 'Pick a size that fits your layout. "Flexible" makes the iframe scale to 100% of its container (Cloudflare Turnstile data-size=flexible).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="appearance"><?php echo \esc_html__( 'Appearance Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td>
									<select id="appearance" name="kitgenix_captcha_for_cloudflare_turnstile_settings[appearance]">
										<option value="always"            <?php selected( $settings['appearance'] ?? '', 'always' ); ?>><?php echo \esc_html__( 'Always', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<option value="interaction-only" <?php selected( $settings['appearance'] ?? '', 'interaction-only' ); ?>><?php echo \esc_html__( 'Interaction Only', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
									</select>
									<p class="description"><?php echo \esc_html__( 'Control how the widget is displayed.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="language"><?php echo \esc_html__( 'Language', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td>
									<select id="language" name="kitgenix_captcha_for_cloudflare_turnstile_settings[language]">
										<option value="auto" <?php selected( $settings['language'] ?? '', 'auto' ); ?>><?php echo \esc_html__( 'Auto (Detect)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></option>
										<?php
										$langs = apply_filters(
											'kitgenix_turnstile_languages',
											[ 'en','es','fr','de','it','pt','ru','zh-CN','zh-TW','ja','ko','ar','tr','pl','nl','sv','fi','da','no','cs','hu','el','he','uk','ro','bg','id','th','vi' ]
										);
										foreach ( $langs as $code ) {
											printf(
												'<option value="%1$s" %2$s>%3$s</option>',
												\esc_attr( $code ),
												selected( $settings['language'] ?? '', $code, false ),
												\esc_html( strtoupper( (string) $code ) )
											);
										}
										?>
									</select>
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
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html( \__( 'Developer Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<table class="form-table">
							<tr>
								<th><label for="dev_mode_warn_only"><?php echo \esc_html( \__( 'Development Mode (Warn-only)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
								<td>
									<label>
										<input type="checkbox" id="dev_mode_warn_only" name="kitgenix_captcha_for_cloudflare_turnstile_settings[dev_mode_warn_only]" value="1" <?php checked( ! empty( $settings['dev_mode_warn_only'] ) ); ?> />
										<span class="description">
											<?php echo \esc_html( \__( 'Do not block submissions if Turnstile fails. Instead, log the failure and show an inline warning (admins only). Ideal for staging.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?>
										</span>
									</label>
									<?php if ( ! empty( $settings['dev_mode_warn_only'] ) ) : ?>
										<div class="notice notice-warning" style="margin-top:10px;">
											<p><strong><?php echo \esc_html__( 'Developer Mode is active', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong> — <?php echo \esc_html__( 'Turnstile failures will not block submissions until you disable this option.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
										</div>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Security -->
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html( \__( 'Security', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<table class="form-table">
							<tr>
								<th><label for="replay_protection"><?php echo \esc_html( \__( 'Enable Replay Protection', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
								<td>
									<label>
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
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
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
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
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
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
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
						<table class="form-table">
							<tr>
								<th><label for="wp_login_form"><?php echo \esc_html__( 'Login Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="wp_login_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_login_form]" value="1" <?php checked( ! empty( $settings['wp_login_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'wp-login.php – below the password field.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
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
								<td><input type="checkbox" id="wp_comments_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wp_comments_form]" value="1" <?php checked( ! empty( $settings['wp_comments_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Below comment fields (for guests and logged-in users).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
							</tr>
						</table>
					</div>
				</div>



				<!-- WooCommerce Integration -->
				<?php $is_wc_active = ( function_exists( '\is_plugin_active' ) && \is_plugin_active( 'woocommerce/woocommerce.php' ) ) || in_array( 'woocommerce/woocommerce.php', $active_plugins, true ); ?>
				<?php if ( $is_wc_active ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'WooCommerce Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description">
							<?php echo \esc_html__( 'Classic Checkout + My Account screens:', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
							<strong><?php echo \esc_html__( 'Checkout (Place order area), My Account Login/Registration, Lost/Reset Password.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></strong>
							<?php echo ' '; ?>
							<?php echo \esc_html__( 'Blocks Checkout is also supported via a JS bridge that attaches the token to Store API requests.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?>
						</p>
						<table class="form-table">
							<tr>
								<th><label for="enable_woocommerce"><?php echo \esc_html__( 'Enable for WooCommerce Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_woocommerce" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_woocommerce]" value="1" <?php checked( ! empty( $settings['enable_woocommerce'] ) ); ?> /></td>
							</tr>
						</table>
						<table class="form-table">
							<tr>
								<th><label for="wc_checkout_form"><?php echo \esc_html__( 'Checkout Form', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="wc_checkout_form" name="kitgenix_captcha_for_cloudflare_turnstile_settings[wc_checkout_form]" value="1" <?php checked( ! empty( $settings['wc_checkout_form'] ) ); ?> /><p class="description"><?php echo \esc_html__( 'Classic checkout: widget renders before “Place order”. Blocks checkout: container is injected; token is sent via header and extensions.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p></td>
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
					</div>
				</div>
				<?php endif; ?>

				<!-- Elementor Integration -->
				<?php if ( defined( 'ELEMENTOR_VERSION' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Elementor Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Elementor Pro Forms: container renders after fields; server-side validation via Elementor hooks. Elementor (free): auto-injection above the submit button.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_elementor"><?php echo \esc_html__( 'Enable for Elementor Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_elementor" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_elementor]" value="1" <?php checked( ! empty( $settings['enable_elementor'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- WPForms -->
				<?php if ( class_exists( 'WPForms' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'WPForms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget renders near the submit area; server-side validation uses WPForms process hook (works with AJAX).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_wpforms"><?php echo \esc_html__( 'Enable for WPForms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_wpforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_wpforms]" value="1" <?php checked( ! empty( $settings['enable_wpforms'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Fluent Forms -->
				<?php if ( defined( 'FLUENTFORM' ) || class_exists( 'FluentForm' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Fluent Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget is inserted before the submit button; AJAX-friendly validation via Fluent’s submit filter.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_fluentforms"><?php echo \esc_html__( 'Enable for Fluent Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_fluentforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_fluentforms]" value="1" <?php checked( ! empty( $settings['enable_fluentforms'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Gravity Forms -->
				<?php if ( class_exists( 'GFForms' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Gravity Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget renders immediately before the submit button; server-side validation sets the top-level error container.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_gravityforms"><?php echo \esc_html__( 'Enable for Gravity Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_gravityforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_gravityforms]" value="1" <?php checked( ! empty( $settings['enable_gravityforms'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Formidable -->
				<?php if ( class_exists( 'FrmForm' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Formidable Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget renders before the submit button; validation runs during entry validation.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_formidableforms"><?php echo \esc_html__( 'Enable for Formidable Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_formidableforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_formidableforms]" value="1" <?php checked( ! empty( $settings['enable_formidableforms'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Contact Form 7 -->
				<?php if ( in_array( 'contact-form-7/wp-contact-form-7.php', $active_plugins, true ) || defined( 'WPCF7_VERSION' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Contact Form 7 Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget is injected before the first submit control; validation uses the CF7 validation filter (AJAX and non-AJAX).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_cf7"><?php echo \esc_html__( 'Enable for Contact Form 7', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_cf7" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_cf7]" value="1" <?php checked( ! empty( $settings['enable_cf7'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Forminator -->
				<?php if ( function_exists( 'forminator' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Forminator Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget is added alongside the submit markup; validation uses Forminator’s submit errors filter (AJAX-safe).', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_forminator"><?php echo \esc_html__( 'Enable for Forminator Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_forminator" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_forminator]" value="1" <?php checked( ! empty( $settings['enable_forminator'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Jetpack -->
				<?php if ( class_exists( 'Jetpack' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Jetpack Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget is injected into Jetpack contact forms; validation occurs via the spam check hook and blocks submission with a surfaced error.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_jetpackforms"><?php echo \esc_html__( 'Enable for Jetpack Forms', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_jetpackforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_jetpackforms]" value="1" <?php checked( ! empty( $settings['enable_jetpackforms'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Kadence -->
				<?php if ( class_exists( 'Kadence_Blocks_Form' ) ) : ?>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
					<h2><?php echo \esc_html__( 'Kadence Forms Integration', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
					<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">
						<p class="description"><?php echo \esc_html__( 'Widget is prepended before the submit button in Kadence Blocks Form; validation returns a form-level error without killing AJAX.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="enable_kadenceforms"><?php echo \esc_html__( 'Enable for Kadence Forms (Kadence Blocks)', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></label></th>
								<td><input type="checkbox" id="enable_kadenceforms" name="kitgenix_captcha_for_cloudflare_turnstile_settings[enable_kadenceforms]" value="1" <?php checked( ! empty( $settings['enable_kadenceforms'] ) ); ?> /></td>
							</tr>
						</table>
					</div>
				</div>
				<?php endif; ?>

				<!-- Save (end of main settings form) -->
				<div class="kitgenix-captcha-for-cloudflare-turnstile-save-row">
					<?php submit_button( \__( 'Save Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ), 'primary', 'submit', false, [ 'style' => 'min-width:160px;font-size:17px;' ] ); ?>
				</div>
			</form>

			<!-- Export / Import -->
			<div class="kitgenix-captcha-for-cloudflare-turnstile-card">
				<h2><?php echo \esc_html( \__( 'Export / Import Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h2>
				<div class="kitgenix-captcha-for-cloudflare-turnstile-section-content">

					<!-- Export -->
					<h3><?php echo \esc_html( \__( 'Export', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h3>
					<p class="description"><?php echo \esc_html( \__( 'Download your current settings as JSON for backup or migration.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></p>
					<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="kitgenix_turnstile_export" />
						<?php \wp_nonce_field( 'kitgenix_turnstile_export' ); ?>
						<label style="display:inline-flex;gap:8px;align-items:center;margin:8px 0;">
							<input type="checkbox" name="include_secret" value="1" />
							<span><?php echo \esc_html( \__( 'Include Secret Key (sensitive)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
						</label>
						<p><button type="submit" class="button button-secondary"><?php echo \esc_html( \__( 'Download JSON', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></button></p>
					</form>

					<hr style="margin:18px 0;border:none;border-top:1px solid #e5e7eb;" />

					<!-- Import -->
					<h3><?php echo \esc_html( \__( 'Import', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></h3>
					<p class="description"><?php echo \esc_html( \__( 'Upload a previously exported JSON file or paste JSON below.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></p>
					<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="kitgenix_turnstile_import" />
						<?php \wp_nonce_field( 'kitgenix_turnstile_import' ); ?>

						<table class="form-table">
							<tr>
								<th><label for="kitgenix_captcha_for_cloudflare_turnstile_ts_import_file"><?php echo \esc_html( \__( 'JSON File', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
								<td><input type="file" id="kitgenix_captcha_for_cloudflare_turnstile_ts_import_file" name="kitgenix_captcha_for_cloudflare_turnstile_ts_import_file" accept="application/json,.json" /></td>
							</tr>
							<tr>
								<th><label for="kitgenix_captcha_for_cloudflare_turnstile_ts_import_text"><?php echo \esc_html( \__( 'Or paste JSON', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
								<td><textarea id="kitgenix_captcha_for_cloudflare_turnstile_ts_import_text" name="kitgenix_captcha_for_cloudflare_turnstile_ts_import_text" rows="6" class="large-text code" placeholder="{ ... }"></textarea></td>
							</tr>
							<tr>
								<th><label for="kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode"><?php echo \esc_html( \__( 'Import Mode', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
								<td>
									<select id="kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode" name="kitgenix_captcha_for_cloudflare_turnstile_ts_import_mode">
										<option value="merge" selected><?php echo \esc_html( \__( 'Merge with existing (recommended)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
										<option value="replace"><?php echo \esc_html( \__( 'Replace existing (overwrite)', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></option>
									</select>
									<p class="description"><?php echo \esc_html( \__( '“Merge” only updates provided keys. “Replace” overwrites the full settings object.', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="kitgenix_captcha_for_cloudflare_turnstile_ts_allow_secret"><?php echo \esc_html( \__( 'Secret Key Handling', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></label></th>
								<td>
									<label style="display:inline-flex;gap:8px;align-items:center;">
										<input type="checkbox" id="kitgenix_captcha_for_cloudflare_turnstile_ts_allow_secret" name="kitgenix_captcha_for_cloudflare_turnstile_ts_allow_secret" value="1" />
										<span><?php echo \esc_html( \__( 'Allow import to overwrite my Secret Key (sensitive).', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></span>
									</label>
								</td>
							</tr>
						</table>

						<p><button type="submit" class="button button-primary"><?php echo \esc_html( \__( 'Import Settings', 'kitgenix-captcha-for-cloudflare-turnstile' ) ); ?></button></p>
					</form>
				</div>
			</div>

			<div class="kitgenix-captcha-for-cloudflare-turnstile-settings-intro" style="margin-top:0;margin-bottom:24px;">
				<h2 style="font-size:1.3em;font-weight:700;margin-bottom:6px;"><?php echo \esc_html__( 'Support Active Development', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></h2>
				<p class="description"><?php echo \esc_html__( 'If you find Kitgenix CAPTCHA for Cloudflare Turnstile useful, please consider buying us a coffee! Your support helps us maintain and actively develop this plugin for the WordPress community.', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></p>
				<a href="<?php echo \esc_url( 'https://buymeacoffee.com/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer" class="kitgenix-captcha-for-cloudflare-turnstile-review-link">☕ <?php echo \esc_html__( 'Buy us a coffee', 'kitgenix-captcha-for-cloudflare-turnstile' ); ?></a>
			</div>
		</div>
		<?php
	}
}
