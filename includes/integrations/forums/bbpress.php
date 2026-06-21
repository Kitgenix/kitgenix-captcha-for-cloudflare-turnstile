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
	 * Prevent duplicate rendering when multiple forum-form hooks fire.
	 *
	 * Some bbPress themes fire both "before submit" and "after form" hooks.
	 */
	private static bool $forum_widget_rendered = false;

	/**
	 * Prevent duplicate rendering for topic/reply forms when multiple hooks fire.
	 */
	private static bool $topic_reply_widget_rendered = false;

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

		// Render widget in topic/reply forms.
		// Prefer above the submit button; keep after-form hooks as fallback.
		add_action( 'bbp_theme_before_topic_form_submit_wrapper', [ __CLASS__, 'render_topic_reply_widget' ], 5 );
		add_action( 'bbp_theme_before_reply_form_submit_wrapper', [ __CLASS__, 'render_topic_reply_widget' ], 5 );
		// Some themes use a submit button hook rather than the wrapper.
		add_action( 'bbp_theme_before_topic_form_submit_button', [ __CLASS__, 'render_topic_reply_widget' ], 5 );
		add_action( 'bbp_theme_before_reply_form_submit_button', [ __CLASS__, 'render_topic_reply_widget' ], 5 );

		add_action( 'bbp_theme_after_topic_form', [ __CLASS__, 'render_topic_reply_widget' ] );
		add_action( 'bbp_theme_after_reply_form', [ __CLASS__, 'render_topic_reply_widget' ] );

		// Render widget in forum form (shortcode: [bbp-forum-form]).
		// Prefer placing it near the submit button; keep a safe fallback after the form.
		add_action( 'bbp_theme_before_forum_form_submit_wrapper', [ __CLASS__, 'render_forum_widget' ], 5 );
		add_action( 'bbp_theme_after_forum_form', [ __CLASS__, 'render_forum_widget' ] );

		// Validate before topic/reply is created.
		add_action( 'bbp_new_topic_pre_extras', [ __CLASS__, 'validate_submission' ] );
		add_action( 'bbp_new_reply_pre_extras', [ __CLASS__, 'validate_submission' ] );

		// Validate before forum is created.
		add_action( 'bbp_new_forum_pre_extras', [ __CLASS__, 'validate_submission' ] );
		add_action( 'bbp_new_forum_pre_insert', [ __CLASS__, 'validate_submission' ] );
	}

	/**
	 * Render the Turnstile widget for the bbPress forum form.
	 *
	 * The forum form often lives in a fieldset with left-aligned fields, so we
	 * default to left alignment here.
	 */
	public static function render_forum_widget(): void {
		if ( self::$forum_widget_rendered ) {
			return;
		}

		self::$forum_widget_rendered = true;

		self::render_widget_with_context(
			'bbpress_forum',
			'display: flex; justify-content: flex-start;',
			'kitgenix-ts-bbpress-forum'
		);
	}

	/**
	 * Render the Turnstile widget for bbPress topic/reply forms.
	 *
	 * We want it above the submit button and left-aligned.
	 */
	public static function render_topic_reply_widget(): void {
		if ( self::$topic_reply_widget_rendered ) {
			return;
		}

		self::$topic_reply_widget_rendered = true;

		self::render_widget_with_context(
			'bbpress',
			'display: flex; justify-content: flex-start;',
			'kitgenix-ts-bbpress'
		);
	}

	/**
	 * Render the Turnstile widget inside bbPress forms.
	 */
	public static function render_widget(): void {
		// Back-compat alias (older installs/themes might call this directly).
		self::render_topic_reply_widget();
	}

	/**
	 * Shared renderer with contextual defaults.
	 */
	private static function render_widget_with_context( string $context, string $default_inline_style, string $extra_class = '' ): void {
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

		// Allow inline style to be customized per context.
		$inline_style = (string) apply_filters(
			'kitgenix_turnstile_inline_style',
			$default_inline_style,
			$context
		);

		$classes = trim( 'cf-turnstile ' . $extra_class );

		echo '<div class="' . esc_attr( $classes ) . '" style="' . esc_attr( $inline_style ) . '"'
			. ' data-sitekey="'    . esc_attr( $site_key ) . '"'
			. ' data-theme="'      . esc_attr( $settings['theme']       ?? 'auto' ) . '"'
			. ' data-size="'       . esc_attr( \KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler::normalize_widget_size( (string)( $settings['widget_size'] ?? 'normal' ) ) ) . '"'
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
		if ( Turnstile_Validator::is_valid_submission( true, 'bbpress' ) ) {
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
