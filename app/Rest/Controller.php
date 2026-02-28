<?php
/**
 * REST API controller for Wordish generate endpoint.
 *
 * @package Nilambar\Wordish
 * @since 1.0.0
 */

namespace Nilambar\Wordish\Rest;

use Nilambar\Wordish\Utils\Credential_Utils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Controller.
 *
 * @since 1.0.0
 */
class Controller {

	/**
	 * Minimum input length (characters).
	 *
	 * @since 1.0.0
	 */
	public const INPUT_MIN_LENGTH = 20;

	/**
	 * Maximum input length (characters) to prevent abuse.
	 *
	 * @since 1.0.0
	 */
	public const INPUT_MAX_LENGTH = 2000;

	/**
	 * Cache duration for identical requests (seconds).
	 *
	 * @since 1.0.0
	 */
	private const CACHE_DURATION = 300;

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public static function register_routes(): void {
		register_rest_route(
			'wordish/v1',
			'/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'generate' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
				'args'                => array(
					'input' => array(
						'required'          => true,
						'type'               => 'string',
						'sanitize_callback'  => 'sanitize_textarea_field',
						'validate_callback'  => array( __CLASS__, 'validate_input' ),
					),
					'tone'  => array(
						'required'          => false,
						'type'               => 'string',
						'default'            => 'professional',
						'enum'               => array( 'professional', 'friendly', 'formal', 'concise', 'empathetic', 'authoritative', 'commanding', 'assertive' ),
						'sanitize_callback'  => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Check if current user can use the API.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function permission_callback(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate input length.
	 *
	 * @since 1.0.0
	 *
	 * @param string          $value Input value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param Parameter name.
	 * @return true|WP_Error
	 */
	public static function validate_input( $value, WP_REST_Request $request, string $param ) {
		$len = strlen( $value );
		if ( $len < self::INPUT_MIN_LENGTH ) {
			return new WP_Error(
				'wordish_input_too_short',
				sprintf(
					/* translators: %d: min character count */
					__( 'Input must be at least %d characters.', 'wordish' ),
					self::INPUT_MIN_LENGTH
				),
				array( 'status' => 400 )
			);
		}
		if ( $len > self::INPUT_MAX_LENGTH ) {
			return new WP_Error(
				'wordish_input_too_long',
				sprintf(
					/* translators: %d: max character count */
					__( 'Input must not exceed %d characters.', 'wordish' ),
					self::INPUT_MAX_LENGTH
				),
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Handle generate request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function generate( WP_REST_Request $request ) {
		if ( ! Credential_Utils::has_ai_credentials() ) {
			return new WP_Error(
				'wordish_no_api_key',
				__( 'API key is not set. Add it in Settings → AI Credentials.', 'wordish' ),
				array( 'status' => 503 )
			);
		}

		$input = $request->get_param( 'input' );
		$tone  = $request->get_param( 'tone' );
		$input = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $input ) {
			return new WP_Error(
				'wordish_empty_input',
				__( 'Please enter some text to improve.', 'wordish' ),
				array( 'status' => 400 )
			);
		}

		$cache_key = 'wordish_' . md5( $input . '|' . $tone );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_string( $cached ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array( 'output' => $cached ),
				),
				200
			);
		}

		$result = self::call_ai( $input, $tone );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		set_transient( $cache_key, $result, self::CACHE_DURATION );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array( 'output' => $result ),
			),
			200
		);
	}

	/**
	 * Call AI to generate improved HTML content (uses credentials from Settings → AI Credentials).
	 *
	 * @since 1.0.0
	 *
	 * @param string $input Raw user input.
	 * @param string $tone  Tone slug.
	 * @return string|WP_Error HTML output or error.
	 */
	private static function call_ai( string $input, string $tone ) {
		$tone_descriptions = array(
			'professional'  => __( 'professional and courteous', 'wordish' ),
			'friendly'      => __( 'friendly and warm', 'wordish' ),
			'formal'        => __( 'formal', 'wordish' ),
			'concise'       => __( 'concise and direct', 'wordish' ),
			'empathetic'    => __( 'empathetic and supportive', 'wordish' ),
			'authoritative' => __( 'authoritative', 'wordish' ),
			'commanding'    => __( 'commanding', 'wordish' ),
			'assertive'     => __( 'assertive', 'wordish' ),
		);
		$tone_label = isset( $tone_descriptions[ $tone ] ) ? $tone_descriptions[ $tone ] : $tone_descriptions['professional'];

		$system = sprintf(
			/* translators: %s: tone description */
			__( 'You are an expert at turning draft notes into polished email body text. Output ONLY the middle part of the email as clean HTML (use <p>, <ul>, <li>, <strong>, <br> as needed). CRITICAL: Do NOT include any opening greeting (no "Dear", "Hello", "Hi", "Dear Sir/Madam", or similar) and do NOT include any closing (no "Regards", "Sincerely", "Best", or similar). The output will be wrapped with "Hi," and "Regards," separately. Start your output directly with the first paragraph of content. Tone: %s.', 'wordish' ),
			$tone_label
		);

		$prompt = sprintf(
			__( 'Convert the following draft notes into a single email body in HTML. Output ONLY the middle content. Do NOT start with any greeting (no Dear/Hello/Hi). Do NOT end with any sign-off. Start directly with the first paragraph:', 'wordish' ) . "\n\n%s",
			$input
		);

		$builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system )
			->using_temperature( 0.5 )
			->using_max_tokens( 1024 )
			->using_model_preference(
				'gpt-4o-mini',
				'gpt-4o',
				'gpt-3.5-turbo',
				'claude-3-5-sonnet-20241022',
				'claude-sonnet-4-5',
				'gemini-2.0-flash',
				'gemini-1.5-pro'
			);

		if ( ! $builder->is_supported_for_text_generation() ) {
			return new WP_Error(
				'wordish_no_models',
				__( 'No AI provider is configured for text generation. Add an API key in Settings → Connectors.', 'wordish' ),
				array( 'status' => 503 )
			);
		}

		$response = $builder->generate_text();

		if ( is_wp_error( $response ) ) {
			$msg  = $response->get_error_message();
			$code = $response->get_error_code();
			if ( 'wordish_no_models' === $code || strpos( $msg, 'No models found' ) !== false ) {
				return new WP_Error(
					'wordish_no_models',
					__( 'No AI provider is configured for text generation. Add an API key in Settings → AI Credentials.', 'wordish' ),
					array( 'status' => 503 )
				);
			}
			if ( strpos( $msg, '401' ) !== false || strpos( $msg, 'Incorrect API key' ) !== false ) {
				return new WP_Error( 'wordish_ai_unauthorized', __( 'Invalid API key. Please check Settings → AI Credentials.', 'wordish' ), array( 'status' => 503 ) );
			}
			if ( strpos( $msg, '403' ) !== false ) {
				return new WP_Error( 'wordish_ai_forbidden', __( 'API access denied. Check your API key and account.', 'wordish' ), array( 'status' => 503 ) );
			}
			return new WP_Error( 'wordish_ai_error', $msg ?: __( 'AI request failed.', 'wordish' ), array( 'status' => 502 ) );
		}

		$allowed = array( 'p' => array(), 'br' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(), 'strong' => array(), 'em' => array(), 'a' => array( 'href' => array() ) );
		$body    = is_string( $response ) ? trim( $response ) : '';
		$body    = wp_kses( $body, $allowed );
		$body    = self::strip_leading_greeting( $body );
		if ( '' === $body ) {
			$body = '<p></p>';
		}
		return 'Hi,<br>' . $body . '<br>Regards,';
	}

	/**
	 * Remove leading greeting paragraph (e.g. "Dear Sir/Madam,") that the model may still output.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Body HTML.
	 * @return string
	 */
	private static function strip_leading_greeting( string $html ): string {
		$pattern = '/^\s*<p>\s*(Dear\s+(?:Sir\/Madam|[^<]+),\s*|Hello,?\s*|Hi,?\s*)\s*<\/p>\s*/iu';
		return preg_replace( $pattern, '', $html );
	}

}
