<?php
namespace KitgenixCaptchaForCloudflareTurnstile\Integrations\Kitgenix;

use KitgenixCaptchaForCloudflareTurnstile\Core\Turnstile_Validator;
use KitgenixCaptchaForCloudflareTurnstile\Core\Whitelist;
use KitgenixCaptchaForCloudflareTurnstile\Core\Script_Handler;
use function add_action;
use function apply_filters;
use function esc_attr;
use function esc_html;
use function get_option;
use function is_page;
use function wp_create_nonce;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_verify_nonce;
use function sanitize_text_field;
use function get_post_field;
use function get_the_ID;
use function set_transient;
use function get_transient;
use function delete_transient;
use function add_query_arg;
use function remove_query_arg;
use function home_url;

defined( 'ABSPATH' ) || exit;

/**
 * Turnstile integration for Kitgenix Plugin Score.
 *
 * Works entirely via output buffering and template_redirect interception –
 * no modifications to the Plugin Score plugin or theme are required.
 *
 * Protects the custom login, register, and forgot-password forms rendered
 * by the Kitgenix Plugin Score theme in its page.php template.
 *
 * Flow:
 *  1. On GET: ob_start() callback injects the Turnstile widget + CSRF nonce into
 *     the form HTML before each submit button, and injects any pending error message.
 *  2. On POST: template_redirect (priority 5) intercepts the form submission,
 *     validates the Turnstile token, and – on failure – redirects back to the page
 *     with an error stored in a short-lived transient before the page template runs.
 */
class Plugin_Score {

	/**
	 * Map of page slugs to their form configuration.
	 *
	 * @var array<string,array{nonce_name:string,nonce_action:string,submit:string,setting:string,integration:string,widget_id:string}>
	 */
	private static $form_map = [
		'login' => [
			'nonce_name'   => 'kgps_login_nonce',
			'nonce_action' => 'kgps_theme_login',
			'submit'       => 'kgps_login_submit',
			'setting'      => 'kgps_login_form',
			'integration'  => 'kgps-login',
			'widget_id'    => 'kgps-login',
		],
		'register' => [
			'nonce_name'   => 'kgps_register_nonce',
			'nonce_action' => 'kgps_theme_register',
			'submit'       => 'kgps_register_submit',
			'setting'      => 'kgps_register_form',
			'integration'  => 'kgps-register',
			'widget_id'    => 'kgps-register',
		],
		'forgot-password' => [
			'nonce_name'   => 'kgps_lostpassword_nonce',
			'nonce_action' => 'kgps_theme_lostpassword',
			'submit'       => 'kgps_lostpassword_submit',
			'setting'      => 'kgps_lostpassword_form',
			'integration'  => 'kgps-lostpassword',
			'widget_id'    => 'kgps-lostpassword',
		],
	];

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		if ( Whitelist::is_whitelisted() ) {
			return;
		}

		// Intercept POST submissions before the template runs.
		add_action( 'template_redirect', [ __CLASS__, 'maybe_validate_and_redirect' ], 5 );

		// Start output buffering to inject widgets into the rendered HTML.
		add_action( 'template_redirect', [ __CLASS__, 'maybe_start_output_buffer' ], 10 );
	}

	// ─── POST interception ────────────────────────────────────────────────────

	/**
	 * Runs early on template_redirect.
	 * If this is a POST to a Plugin Score auth form, validate the Turnstile token.
	 * On failure: stash the error in a transient and redirect back to the page.
	 * On success: allow the request through so the template processes it normally.
	 */
	public static function maybe_validate_and_redirect(): void {
		if ( 'POST' !== strtoupper( sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return;
		}

		$form = self::get_active_form_config();
		if ( null === $form ) {
			return;
		}

		$settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
		if ( ! is_array( $settings ) || empty( $settings[ $form['setting'] ] ) ) {
			return;
		}

		// Confirm this is the Plugin Score form (nonce + submit present and valid).
		$raw_nonce = isset( $_POST[ $form['nonce_name'] ] )
			? sanitize_text_field( (string) wp_unslash( $_POST[ $form['nonce_name'] ] ) )
			: '';

		if ( '' === $raw_nonce ) {
			return;
		}
		if ( ! wp_verify_nonce( $raw_nonce, $form['nonce_action'] ) ) {
			return;
		}
		if ( ! isset( $_POST[ $form['submit'] ] ) ) {
			return;
		}

		// Validate Turnstile.
		if ( Turnstile_Validator::is_valid_submission( true, $form['integration'] ) ) {
			// Token is valid – let the template process the form normally.
			return;
		}

		// Token invalid: stash error and redirect back before the template runs.
		$error_msg = Turnstile_Validator::get_error_message( $form['integration'] );
		$ip_hash   = md5( sanitize_text_field( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		set_transient( 'kgps_turnstile_error_' . $ip_hash, $error_msg, 60 );

		$redirect_url = add_query_arg(
			'kgps_captcha_error',
			'1',
			remove_query_arg( 'kgps_captcha_error' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	// ─── Output buffer ────────────────────────────────────────────────────────

	/**
	 * Starts the output buffer on Plugin Score auth pages so we can inject
	 * the Turnstile widget into the rendered HTML.
	 */
	public static function maybe_start_output_buffer(): void {
		$form = self::get_active_form_config();
		if ( null === $form ) {
			return;
		}

		$settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
		if ( ! is_array( $settings ) || empty( $settings[ $form['setting'] ] ) ) {
			return;
		}

		ob_start( [ __CLASS__, 'inject_widget_into_output' ] );
	}

	/**
	 * Output buffer callback: injects the Turnstile widget and any pending error
	 * message into the Plugin Score auth form HTML.
	 *
	 * @param string $html The fully rendered page HTML.
	 * @return string Modified HTML.
	 */
	public static function inject_widget_into_output( string $html ): string {
		$form = self::get_active_form_config();
		if ( null === $form ) {
			return $html;
		}

		$settings = get_option( 'kitgenix_captcha_for_cloudflare_turnstile_settings', [] );
		if ( ! is_array( $settings ) || empty( $settings[ $form['setting'] ] ) ) {
			return $html;
		}

		$site_key = (string) ( $settings['site_key'] ?? '' );
		if ( '' === $site_key ) {
			return $html;
		}

		// Build the nonce field + widget markup to inject before the submit button.
		$nonce  = wp_create_nonce( 'kitgenix_captcha_for_cloudflare_turnstile_action' );
		$style  = (string) apply_filters(
			'kitgenix_turnstile_inline_style',
			'display: flex; justify-content: flex-start; margin-bottom: 1rem;',
			$form['widget_id']
		);

		$widget  = '<input type="hidden"'
			. ' name="kitgenix_captcha_for_cloudflare_turnstile_nonce"'
			. ' value="' . esc_attr( $nonce ) . '" />' . "\n";
		$widget .= Script_Handler::render_honeypot_field() . "\n";
		$widget .= '<div'
			. ' id="cf-turnstile-' . esc_attr( $form['widget_id'] ) . '"'
			. ' class="cf-turnstile"'
			. ' style="' . esc_attr( $style ) . '"'
			. Script_Handler::get_widget_data_attributes( 'kitgenix_plugin_score', $site_key )
			. '></div>' . "\n";

		// Inject before the submit button.
		$submit_marker = '<button type="submit" name="' . esc_attr( $form['submit'] ) . '"';
		$html = str_replace( $submit_marker, $widget . $submit_marker, $html );

		// Inject any pending error message (from a prior failed-Turnstile redirect).
		$ip_hash   = md5( sanitize_text_field( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		$error_msg = get_transient( 'kgps_turnstile_error_' . $ip_hash );

		if ( false !== $error_msg && '' !== (string) $error_msg ) {
			delete_transient( 'kgps_turnstile_error_' . $ip_hash );

			$error_html = '<div class="notice-card notice-warning" role="alert">'
				. '<p>' . esc_html( (string) $error_msg ) . '</p>'
				. '</div>' . "\n";

			// Insert the error div just before the Turnstile widget (which is already injected).
			$html = str_replace( $widget . $submit_marker, $error_html . $widget . $submit_marker, $html );
		}

		return $html;
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Determine which Plugin Score auth form is active on the current page,
	 * or return null if this is not a Plugin Score auth page.
	 *
	 * @return array{nonce_name:string,nonce_action:string,submit:string,setting:string,integration:string,widget_id:string}|null
	 */
	private static function get_active_form_config(): ?array {
		if ( ! function_exists( 'is_page' ) || ! is_page() ) {
			return null;
		}

		$slug = (string) get_post_field( 'post_name', get_the_ID() );

		return self::$form_map[ $slug ] ?? null;
	}
}
