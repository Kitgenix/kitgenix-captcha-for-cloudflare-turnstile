<?php
/**
 * bbPress Integration
 *
 * @package KitgenixCaptchaForCloudflareTurnstile
 */

declare(strict_types=1);

namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Forums;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;

defined( 'ABSPATH' ) || exit;

final class BbPress {

	/**
	 * Bootstraps the bbPress integration.
	 */
	public static function init(): void {
		// Skip completely for whitelisted requests (admins, IPs, etc).
		if ( Whitelist::is_whitelisted() ) {
			return;
		}

		$settings = self::get_settings();

		// bbPress not enabled in plugin settings.
		if ( empty( $settings['enable_bbpress'] ) ) {
			return;
		}

		// bbPress not actually active.
		if ( ! self::is_bbpress_active() ) {
			return;
		}

		// Render widget in topic and reply forms.
		add_action( 'bbp_theme_after_topic_form', [ __CLASS__, 'render_widget' ] );
		add_action( 'bbp_theme_after_reply_form', [ __CLASS__, 'render_widget' ] );

		// Validate before topic/reply is created.
		add_action( 'bbp_new_topic_pre_extras', [ __CLASS__, 'validate_submission' ] );
		add_action( 'bbp_new_reply_pre_extras', [ __CLASS__, 'validate_submission' ] );
	}

	/**
	 * Render the Turnstile widget inside bbPress forms.
	 */
	public static function render_widget(): void {
		$settings = self::get_settings();
		$site_key = $settings['site_key'] ?? '';

		// If no site key, show a clear warning (admins will see it; users just see text).
		if ( '' === $site_key ) {
			echo '<p class="kitgenix-captcha-for-cloudflare-turnstile-warning">';
			echo esc_html__(
				'Cloudflare Turnstile site key is missing. Please configure it in plugin settings.',
				'kitgenix-captcha-for-cloudflare-turnstile'
			);
			echo '</p>';

			return;
		}

		// Nonce to protect against CSRF around the Turnstile submission.
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field(
				'kitgenix_captcha_for_cloudflare_turnstile_action',
				'kitgenix_captcha_for_cloudflare_turnstile_nonce'
			);
		}

		// Hidden input that your JS can populate with the Turnstile token if needed.
		echo '<input type="hidden" name="cf-turnstile-response" value="" />';

		// Allow inline style to be customized for bbPress context.
		$inline_style = (string) apply_filters(
			'kitgenix_turnstile_inline_style',
			'display: flex; justify-content: center;',
			'bbpress'
		);

		echo '<div class="cf-turnstile" style="' . esc_attr( $inline_style ) . '"'
			. ' data-sitekey="'    . esc_attr( $site_key ) . '"'
			. ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
			. ' data-size="'       . esc_attr( $settings['widget_size'] ?? 'normal' ) . '"'
			. ' data-appearance="' . esc_attr( $settings['appearance']  ?? 'always' ) . '"'
			. '></div>';
	}

	/**
	 * Validate the Turnstile submission for bbPress forms.
	 */
	public static function validate_submission(): void {
		// Only care about POST requests.
		if ( 'POST' !== self::request_method() ) {
			return;
		}

		// Bail early if validation passes.
		if ( Turnstile_Validator::is_valid_submission() ) {
			return;
		}

		// Block and show error.
		wp_die(
			esc_html( Turnstile_Validator::get_error_message( 'bbpress' ) ),
			esc_html__( 'Submission blocked', 'kitgenix-captcha-for-cloudflare-turnstile' ),
			[
				'response'  => 403,
				'back_link' => true,
			]
		);
	}

	/**
	 * Safely fetch plugin settings as an array.
	 */
	private static function get_settings(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return [];
		}

		$settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );

		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Detect whether bbPress is active.
	 *
	 * We avoid the broken BBPRESS_VERSION constant and instead rely on
	 * known bbPress constants/functions.
	 */
	private static function is_bbpress_active(): bool {
		// bbPress defines BBP_VERSION and its core template functions.
		if ( defined( 'BBP_VERSION' ) || function_exists( 'bbp_is_single_forum' ) ) {
			return true;
		}

		// Fallback: try plugin API if available.
		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( 'bbpress/bbpress.php' );
		}

		return false;
	}

	/**
	 * Normalise request method to uppercase.
	 */
	private static function request_method(): string {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';

		return strtoupper( $method ?: 'GET' );
	}
}
