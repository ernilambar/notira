<?php
/**
 * AI credential helpers.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Utils;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Class Credential_Utils.
 *
 * @since 1.0.0
 */
class Credential_Utils {

	/**
	 * Whether the environment allows WordPress AI features.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function supports_ai(): bool {
		if ( ! function_exists( 'wp_supports_ai' ) ) {
			return false;
		}

		return wp_supports_ai();
	}

	/**
	 * Check if at least one Connectors AI provider has configured credentials.
	 *
	 * Uses `wp_get_connectors()` and per-provider options (e.g. `connectors_ai_openai_api_key`).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function has_ai_credentials(): bool {
		if ( ! self::supports_ai() ) {
			return false;
		}

		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return false;
		}

		return self::has_connectors_api_credentials();
	}

	/**
	 * Whether stored credentials appear usable for text generation.
	 *
	 * Performs a lightweight client check after {@see Credential_Utils::has_ai_credentials()}.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function has_valid_ai_credentials(): bool {
		if ( ! self::has_ai_credentials() ) {
			return false;
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		try {
			$builder = wp_ai_client_prompt( 'Test' );
		} catch ( Throwable $e ) {
			return false;
		}

		if ( ! is_object( $builder ) || ! method_exists( $builder, 'is_supported_for_text_generation' ) ) {
			return false;
		}

		try {
			return (bool) $builder->is_supported_for_text_generation();
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Uses Connectors-registered AI providers and their Settings API option names.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private static function has_connectors_api_credentials(): bool {
		$connectors = wp_get_connectors();
		if ( ! is_array( $connectors ) || [] === $connectors ) {
			return false;
		}

		foreach ( $connectors as $connector_data ) {
			if ( ! is_array( $connector_data ) ) {
				continue;
			}

			if ( ! isset( $connector_data['type'], $connector_data['authentication'] ) || ! is_array( $connector_data['authentication'] ) ) {
				continue;
			}

			if ( 'ai_provider' !== $connector_data['type'] ) {
				continue;
			}

			$auth = $connector_data['authentication'];
			if ( ! isset( $auth['method'] ) || 'api_key' !== $auth['method'] ) {
				continue;
			}

			$setting_name = isset( $auth['setting_name'] ) && is_string( $auth['setting_name'] ) ? $auth['setting_name'] : '';
			if ( '' === $setting_name ) {
				continue;
			}

			$value = get_option( $setting_name, '' );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return true;
			}
		}

		return false;
	}
}
