<?php
/**
 * REST API controller for Wordish generate endpoint.
 *
 * @package Nilambar\Wordish
 * @since 1.0.0
 */

namespace Nilambar\Wordish\Rest;

use Nilambar\Wordish\Core\Settings;
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
	 * Maximum input length (characters) to prevent abuse.
	 *
	 * @since 1.0.0
	 */
	public const INPUT_MAX_LENGTH = 4000;

	/**
	 * Cache duration for identical requests (seconds).
	 *
	 * @since 1.0.0
	 */
	private const CACHE_DURATION = 300;

	/**
	 * Preferred OpenAI model (cheapest option for development).
	 * gpt-4o-mini is currently OpenAI's lowest-cost chat model.
	 *
	 * @since 1.0.0
	 */
	private const OPENAI_MODEL = 'gpt-4o-mini';

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
		if ( strlen( $value ) > self::INPUT_MAX_LENGTH ) {
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
		if ( ! Settings::has_ai_credentials() ) {
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
			$code = $result->get_error_code();
			if ( in_array( $code, array( 'wordish_ai_unauthorized', 'wordish_ai_forbidden' ), true ) ) {
				set_transient( Settings::TRANSIENT_INVALID_KEY, 1, 300 );
			}
			return $result;
		}

		delete_transient( Settings::TRANSIENT_INVALID_KEY );
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
			__( 'You are an expert at turning rough notes into polished email body text. Output ONLY the middle part of the email as clean HTML (use <p>, <ul>, <li>, <strong>, <br> as needed). CRITICAL: Do NOT include any opening greeting (no "Dear", "Hello", "Hi", "Dear Sir/Madam", or similar) and do NOT include any closing (no "Regards", "Sincerely", "Best", or similar). The output will be wrapped with "Hi," and "Regards," separately. Start your output directly with the first paragraph of content. Tone: %s.', 'wordish' ),
			$tone_label
		);

		$prompt = sprintf(
			__( 'Convert the following rough notes into a single email body in HTML. Output ONLY the middle content. Do NOT start with any greeting (no Dear/Hello/Hi). Do NOT end with any sign-off. Start directly with the first paragraph:', 'wordish' ) . "\n\n%s",
			$input
		);

		$response = null;
		if ( class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			try {
				$builder = \WordPress\AI_Client\AI_Client::prompt_with_wp_error( $prompt )
					->using_system_instruction( $system )
					->using_temperature( 0.5 )
					->using_max_tokens( 1024 )
					->using_model_preference( array( 'openai', self::OPENAI_MODEL ) );
				$response = $builder->generate_text();
			} catch ( \Exception $e ) {
				$response = new WP_Error( 'wordish_ai_error', $e->getMessage() );
			}
		}
		if ( null === $response ) {
			$credentials = get_option( Settings::WP_AI_CLIENT_CREDENTIALS_OPTION, array() );
			$api_key     = is_array( $credentials ) && isset( $credentials['openai'] ) ? $credentials['openai'] : '';
			$response    = self::request_openai_direct( $api_key, $system, $prompt );
		}

		if ( is_wp_error( $response ) ) {
			$msg  = $response->get_error_message();
			$code = $response->get_error_code();
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
		return 'Hi,<br><br>' . $body . '<br><br>Regards,';
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

	/**
	 * Fallback: call OpenAI API directly via HTTP when wp_ai_client_prompt is not available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key  OpenAI API key.
	 * @param string $system   System message.
	 * @param string $prompt   User message.
	 * @return string|WP_Error
	 */
	private static function request_openai_direct( string $api_key, string $system, string $prompt ) {
		$body = wp_json_encode(
			array(
				'model'    => self::OPENAI_MODEL,
				'messages' => array(
					array( 'role' => 'system', 'content' => $system ),
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'max_tokens' => 1024,
				'temperature' => 0.5,
			)
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			return new WP_Error( 'wordish_ai_unauthorized', __( 'Invalid API key.', 'wordish' ) );
		}
		if ( 403 === $code ) {
			return new WP_Error( 'wordish_ai_forbidden', __( 'API access denied.', 'wordish' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			$body_res = wp_remote_retrieve_body( $response );
			return new WP_Error( 'wordish_ai_error', $body_res ?: 'Request failed' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'wordish_ai_error', __( 'Invalid response from API.', 'wordish' ) );
		}

		return trim( $data['choices'][0]['message']['content'] );
	}
}
