<?php
/**
 * REST API for Wordish generate endpoint.
 *
 * @package Nilambar\Wordish
 */

namespace Nilambar\Wordish\API;

use Nilambar\Wordish\Utils\Credential_Utils;
use Nilambar\Wordish\Utils\Tone_Utils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class REST_API.
 *
 * @since 1.0.0
 */
class REST_API {

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
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
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
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'generate' ],
				'permission_callback' => [ __CLASS__, 'permission_callback' ],
				'args'                => [
					'input' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => [ __CLASS__, 'validate_input' ],
					],
					'tone'  => [
						'required'          => false,
						'type'              => 'string',
						'default'           => Tone_Utils::DEFAULT_TONE,
						'enum'              => Tone_Utils::get_valid_slugs(),
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
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
				[ 'status' => 400 ]
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
				[ 'status' => 400 ]
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
				__( 'API key is not set.', 'wordish' ),
				[ 'status' => 503 ]
			);
		}

		$input = $request->get_param( 'input' );
		$tone  = $request->get_param( 'tone' );
		$input = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $input ) {
			return new WP_Error(
				'wordish_empty_input',
				__( 'Please enter some text to improve.', 'wordish' ),
				[ 'status' => 400 ]
			);
		}

		$cache_key = 'wordish_' . md5( $input . '|' . $tone );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_string( $cached ) ) {
			return new WP_REST_Response(
				[
					'success' => true,
					'data'    => [ 'output' => $cached ],
				],
				200
			);
		}

		$result = self::call_ai( $input, $tone );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		set_transient( $cache_key, $result, self::CACHE_DURATION );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [ 'output' => $result ],
			],
			200
		);
	}

	/**
	 * Call AI to generate improved HTML content (uses credentials from Connectors).
	 *
	 * @since 1.0.0
	 *
	 * @param string $input Raw user input.
	 * @param string $tone  Tone slug.
	 * @return string|WP_Error HTML output or error.
	 */
	private static function call_ai( string $input, string $tone ) {
		$tone_label = Tone_Utils::get_tone_label( $tone );

		$system = sprintf(
			'You are an expert at turning draft notes into polished email body text. Your tasks: (1) Check and fix all grammar, spelling, punctuation, and sentence structure in the draft. (2) Output ONLY the middle part of the email as clean HTML (use <p>, <ul>, <li>, <strong>, <br> as needed). CRITICAL: Do NOT include any opening greeting (no "Dear", "Hello", "Hi", "Dear Sir/Madam", or similar) and do NOT include any closing (no "Regards", "Sincerely", "Best", or similar). The output will be wrapped with "Hi," and "Regards," separately. Start your output directly with the first paragraph of content. Never add information not present in the original input. Tone: %s.',
			$tone_label
		);

		$prompt = sprintf(
			'Convert the following draft notes into a single email body in HTML. First check and fix any grammar, spelling, punctuation, or sentence-structure errors in the draft. Output ONLY the middle content (corrected and polished). Do NOT start with any greeting (no Dear/Hello/Hi). Do NOT end with any sign-off. Do not add any information that is not in the draft notes. Start directly with the first paragraph:' . "\n\n%s",
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
				__( 'No AI provider is configured.', 'wordish' ),
				[ 'status' => 503 ]
			);
		}

		$response = $builder->generate_text();

		if ( is_wp_error( $response ) ) {
			$msg  = $response->get_error_message();
			$code = $response->get_error_code();
			if ( 'wordish_no_models' === $code || strpos( $msg, 'No models found' ) !== false ) {
				return new WP_Error(
					'wordish_no_models',
					__( 'No AI provider is configured.', 'wordish' ),
					[ 'status' => 503 ]
				);
			}
			if ( strpos( $msg, '401' ) !== false || strpos( $msg, 'Incorrect API key' ) !== false ) {
				return new WP_Error( 'wordish_ai_unauthorized', __( 'Invalid API key.', 'wordish' ), [ 'status' => 503 ] );
			}
			if ( strpos( $msg, '403' ) !== false ) {
				return new WP_Error( 'wordish_ai_forbidden', __( 'API access denied. Check your API key and account.', 'wordish' ), [ 'status' => 503 ] );
			}
			return new WP_Error( 'wordish_ai_error', $msg ? $msg : __( 'AI request failed.', 'wordish' ), [ 'status' => 502 ] );
		}

		$allowed = [
			'p'      => [],
			'br'     => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'strong' => [],
			'em'     => [],
			'a'      => [ 'href' => [] ],
		];
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
