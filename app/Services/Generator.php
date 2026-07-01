<?php
/**
 * Text generation service
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Services;

use Nilambar\Notira\Core\Option;
use Nilambar\Notira\Utils\Mode_Utils;
use Nilambar\Notira\Utils\Prompt_Utils;
use Throwable;
use WordPress\AiClient\AiClient;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Generator.
 *
 * @since 1.1.1
 */
class Generator {

	/**
	 * Generate improved HTML content via the AI client.
	 *
	 * @since 1.1.1
	 *
	 * @param string $input Raw user input.
	 * @param string $tone  Tone slug.
	 * @param string $mode  Mode slug (email or proofread).
	 * @return array|WP_Error HTML output and metadata, or error.
	 */
	public static function generate( string $input, string $tone, string $mode ) {
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
		$preferred_model    = sanitize_text_field( (string) Option::get( 'preferred_model' ) );

		$builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system )
			->using_max_tokens( 1024 );

		if ( '' !== $preferred_provider && '' !== $preferred_model ) {
			try {
				$registry  = AiClient::defaultRegistry();
				$model_obj = $registry->getProviderModel( $preferred_provider, $preferred_model );
				$builder   = $builder->using_model( $model_obj );
			} catch ( Throwable $e ) {
				$builder = $builder->using_provider( $preferred_provider );
			}
		} elseif ( '' !== $preferred_provider ) {
			$builder = $builder->using_provider( $preferred_provider );
		} elseif ( '' !== $preferred_model ) {
			$builder = $builder->using_model_preference( $preferred_model );
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
				return new WP_Error( 'notira_ai_forbidden', __( 'API access denied.', 'notira' ), [ 'status' => 503 ] );
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
			$output = esc_html( $wrappers['greeting'] ) . '<br><br>' . $body . '<br>' . esc_html( $wrappers['signoff'] );
		}

		$meta = self::extract_generation_meta_from_result( $result_obj );

		return [
			'output' => $output,
			'meta'   => $meta,
		];
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
	 * Build a small metadata array from a generative AI result object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $result Result object from the AI client.
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
