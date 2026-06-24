<?php
/**
 * REST API for text generation
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\API;

use Nilambar\Notira\Services\Generator;
use Nilambar\Notira\Utils\Credential_Utils;
use Nilambar\Notira\Utils\Mode_Utils;
use Nilambar\Notira\Utils\Tone_Utils;
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
				__( 'WordPress AI is unavailable.', 'notira' ),
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

		$input = is_string( $input ) ? trim( $input ) : '';
		if ( '' === $input ) {
			return new WP_Error(
				'notira_empty_input',
				__( 'Enter text to improve.', 'notira' ),
				[ 'status' => 400 ]
			);
		}

		$mode   = is_string( $mode ) ? $mode : '';
		$tone   = ( is_string( $tone ) && in_array( $tone, Tone_Utils::get_valid_slugs(), true ) ) ? $tone : Tone_Utils::DEFAULT_TONE;
		$result = Generator::generate( $input, $tone, $mode );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

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
}
