<?php
/**
 * REST API for text generation
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\API;

use Nilambar\Notira\Core\Option;
use Nilambar\Notira\Utils\Credential_Utils;
use Nilambar\Notira\Utils\Mode_Utils;
use Nilambar\Notira\Utils\Prompt_Utils;
use Nilambar\Notira\Utils\Tone_Utils;
use Throwable;
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
			'notira/v1',
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
					'mode'  => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => [ __CLASS__, 'validate_mode' ],
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
				'notira_input_too_short',
				sprintf(
					/* translators: %d: min character count */
					__( 'Input must be at least %d characters.', 'notira' ),
					self::INPUT_MIN_LENGTH
				),
				[ 'status' => 400 ]
			);
		}
		if ( $len > self::INPUT_MAX_LENGTH ) {
			return new WP_Error(
				'notira_input_too_long',
				sprintf(
					/* translators: %d: max character count */
					__( 'Input must not exceed %d characters.', 'notira' ),
					self::INPUT_MAX_LENGTH
				),
				[ 'status' => 400 ]
			);
		}
		return true;
	}

	/**
	 * Validate generation mode slug.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed           $value   Parameter value.
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public static function validate_mode( $value, WP_REST_Request $request ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return new WP_Error(
				'notira_invalid_mode',
				sprintf(
					/* translators: 1: email mode slug, 2: proofread mode slug. */
					__( 'A valid mode is required. Use %1$s or %2$s.', 'notira' ),
					'email',
					'proofread'
				),
				[ 'status' => 400 ]
			);
		}
		$slug = sanitize_key( $value );
		if ( ! in_array( $slug, Mode_Utils::get_valid_slugs(), true ) ) {
			return new WP_Error(
				'notira_invalid_mode',
				sprintf(
					/* translators: 1: email mode slug, 2: proofread mode slug. */
					__( 'A valid mode is required. Use %1$s or %2$s.', 'notira' ),
					'email',
					'proofread'
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
		if ( ! Credential_Utils::supports_ai() ) {
			return new WP_Error(
				'notira_ai_unsupported',
				__( 'WordPress AI is not available or is disabled on this site.', 'notira' ),
				[ 'status' => 503 ]
			);
		}

		if ( ! Credential_Utils::has_ai_credentials() ) {
			return new WP_Error(
				'notira_no_api_key',
				__( 'API key is not set.', 'notira' ),
				[ 'status' => 503 ]
			);
		}

		$input = $request->get_param( 'input' );
		$mode  = $request->get_param( 'mode' );
		$tone  = $request->get_param( 'tone' );
		$mode  = is_string( $mode ) ? sanitize_key( $mode ) : '';
		$input = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $input ) {
			return new WP_Error(
				'notira_empty_input',
				__( 'Please enter some text to improve.', 'notira' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! in_array( $mode, Mode_Utils::get_valid_slugs(), true ) ) {
			return new WP_Error(
				'notira_invalid_mode',
				sprintf(
					/* translators: 1: email mode slug, 2: proofread mode slug. */
					__( 'A valid mode is required. Use %1$s or %2$s.', 'notira' ),
					'email',
					'proofread'
				),
				[ 'status' => 400 ]
			);
		}

		$len = strlen( $input );
		if ( $len < self::INPUT_MIN_LENGTH ) {
			return new WP_Error(
				'notira_input_too_short',
				sprintf(
					/* translators: %d: min character count */
					__( 'Input must be at least %d characters.', 'notira' ),
					self::INPUT_MIN_LENGTH
				),
				[ 'status' => 400 ]
			);
		}
		if ( $len > self::INPUT_MAX_LENGTH ) {
			return new WP_Error(
				'notira_input_too_long',
				sprintf(
					/* translators: %d: max character count */
					__( 'Input must not exceed %d characters.', 'notira' ),
					self::INPUT_MAX_LENGTH
				),
				[ 'status' => 400 ]
			);
		}

		$valid_slugs = Tone_Utils::get_valid_slugs();
		$tone        = ( is_string( $tone ) && in_array( $tone, $valid_slugs, true ) ) ? $tone : Tone_Utils::DEFAULT_TONE;
		$provider    = sanitize_key( (string) Option::get( 'preferred_provider' ) );
		$cache_key   = 'notira_' . $mode . '_' . $tone . '_' . ( '' !== $provider ? $provider . '_' : '' ) . md5( $input );
		$cached      = get_transient( $cache_key );
		if ( false !== $cached ) {
			$cached_output = '';
			$cached_meta   = null;
			if ( is_array( $cached ) && isset( $cached['output'] ) && is_string( $cached['output'] ) ) {
				$cached_output = $cached['output'];
				$cached_meta   = isset( $cached['meta'] ) && is_array( $cached['meta'] ) ? $cached['meta'] : null;
			} elseif ( is_string( $cached ) ) {
				$cached_output = $cached;
			}
			if ( '' !== $cached_output ) {
				$meta = is_array( $cached_meta )
					? array_merge( $cached_meta, [ 'from_cache' => true ] )
					: [ 'from_cache' => true ];
				return new WP_REST_Response(
					[
						'success' => true,
						'data'    => [
							'output' => $cached_output,
							'meta'   => $meta,
						],
					],
					200
				);
			}
		}

		$result = self::call_ai( $input, $tone, $mode );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		set_transient(
			$cache_key,
			[
				'output' => $result['output'],
				'meta'   => $result['meta'],
			],
			self::CACHE_DURATION
		);

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'output' => $result['output'],
					'meta'   => $result['meta'],
				],
			],
			200
		);
	}

	/**
	 * Return sanitized opening and closing lines from settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array{greeting: string, signoff: string}
	 */
	private static function get_email_wrapper_lines(): array {
		$greeting = sanitize_text_field( (string) Option::get( 'email_greeting' ) );
		$signoff  = sanitize_text_field( (string) Option::get( 'email_signoff' ) );

		return [
			'greeting' => $greeting,
			'signoff'  => $signoff,
		];
	}

	/**
	 * Call AI to generate improved HTML content (uses credentials from Connectors).
	 *
	 * @since 1.0.0
	 *
	 * @param string $input Raw user input.
	 * @param string $tone  Tone slug.
	 * @param string $mode  Mode slug (email or proofread).
	 * @return array|WP_Error HTML output and metadata, or error.
	 */
	private static function call_ai( string $input, string $tone, string $mode ) {
		$wrappers = self::get_email_wrapper_lines();
		$system   = Prompt_Utils::get_assembled_system_prompt( $mode, $tone );
		$prompt   = Prompt_Utils::get_mode_user_prompt( $mode, $input );
		if ( '' === $system || '' === $prompt ) {
			return new WP_Error(
				'notira_missing_prompts',
				__( 'Prompt templates are missing.', 'notira' ),
				[ 'status' => 503 ]
			);
		}

		$preferred_provider = sanitize_key( (string) Option::get( 'preferred_provider' ) );

		$builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system )
			->using_max_tokens( 1024 );

		if ( '' !== $preferred_provider ) {
			$builder = $builder->using_provider( $preferred_provider );
		} else {
			$builder = $builder->using_model_preference(
				'openai/gpt-4o-mini',
				'openai/gpt-4o',
				'openai/gpt-4',
				'anthropic/claude-3.5-sonnet',
				'google/gemini-flash-1.5',
				'gpt-4o-mini',
				'gpt-4o',
				'gpt-4',
				'claude-3-5-sonnet-20241022',
				'gemini-2.0-flash'
			);
		}

		if ( ! $builder->is_supported_for_text_generation() ) {
			return new WP_Error(
				'notira_no_models',
				__( 'No AI provider is configured.', 'notira' ),
				[ 'status' => 503 ]
			);
		}

		$result_obj = $builder->generate_text_result();

		if ( is_wp_error( $result_obj ) ) {
			$msg  = $result_obj->get_error_message();
			$code = $result_obj->get_error_code();
			if ( 'notira_no_models' === $code || strpos( $msg, 'No models found' ) !== false ) {
				return new WP_Error(
					'notira_no_models',
					__( 'No AI provider is configured.', 'notira' ),
					[ 'status' => 503 ]
				);
			}
			if ( strpos( $msg, '401' ) !== false || strpos( $msg, 'Incorrect API key' ) !== false ) {
				return new WP_Error( 'notira_ai_unauthorized', __( 'Invalid API key.', 'notira' ), [ 'status' => 503 ] );
			}
			if ( strpos( $msg, '403' ) !== false ) {
				return new WP_Error( 'notira_ai_forbidden', __( 'API access denied. Check your API key and account.', 'notira' ), [ 'status' => 503 ] );
			}
			return new WP_Error( 'notira_ai_error', $msg ? $msg : __( 'AI request failed.', 'notira' ), [ 'status' => 502 ] );
		}

		if ( ! is_object( $result_obj ) || ! method_exists( $result_obj, 'toText' ) ) {
			return new WP_Error(
				'notira_ai_error',
				__( 'AI request failed.', 'notira' ),
				[ 'status' => 502 ]
			);
		}

		try {
			$raw_text = trim( $result_obj->toText() );
		} catch ( Throwable $e ) {
			return new WP_Error(
				'notira_ai_error',
				$e->getMessage() ? $e->getMessage() : __( 'AI request failed.', 'notira' ),
				[ 'status' => 502 ]
			);
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
		$body    = wp_kses( $raw_text, $allowed );
		if ( '' === $body ) {
			$body = '<p></p>';
		}
		if ( Mode_Utils::MODE_PROOFREAD === $mode ) {
			$output = $body;
		} else {
			$output = esc_html( $wrappers['greeting'] ) . '<br>' . $body . '<br>' . esc_html( $wrappers['signoff'] );
		}

		$meta = self::extract_generation_meta_from_result( $result_obj );

		return [
			'output' => $output,
			'meta'   => $meta,
		];
	}

	/**
	 * Build a small metadata array for the REST response from a generative AI result object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $result Result object from the AI client (e.g. GenerativeAiResult).
	 * @return array<string, mixed>
	 */
	private static function extract_generation_meta_from_result( object $result ): array {
		$meta = [];

		if ( method_exists( $result, 'getId' ) ) {
			$meta['response_id'] = $result->getId();
		}

		if ( method_exists( $result, 'getTokenUsage' ) ) {
			$usage = $result->getTokenUsage();
			if ( is_object( $usage ) && method_exists( $usage, 'toArray' ) ) {
				$meta['token_usage'] = $usage->toArray();
			}
		}

		if ( method_exists( $result, 'getModelMetadata' ) ) {
			$model = $result->getModelMetadata();
			if ( is_object( $model ) && method_exists( $model, 'toArray' ) ) {
				$arr           = $model->toArray();
				$meta['model'] = [
					'id'   => isset( $arr['id'] ) && is_string( $arr['id'] ) ? $arr['id'] : '',
					'name' => isset( $arr['name'] ) && is_string( $arr['name'] ) ? $arr['name'] : '',
				];
				$meta['model'] = array_filter( $meta['model'] );
			}
		}

		if ( method_exists( $result, 'getProviderMetadata' ) ) {
			$provider = $result->getProviderMetadata();
			if ( is_object( $provider ) && method_exists( $provider, 'toArray' ) ) {
				$arr              = $provider->toArray();
				$meta['provider'] = [
					'id'   => isset( $arr['id'] ) && is_string( $arr['id'] ) ? $arr['id'] : '',
					'name' => isset( $arr['name'] ) && is_string( $arr['name'] ) ? $arr['name'] : '',
					'type' => isset( $arr['type'] ) && is_string( $arr['type'] ) ? $arr['type'] : '',
				];
				$meta['provider'] = array_filter( $meta['provider'] );
			}
		}

		return $meta;
	}
}
