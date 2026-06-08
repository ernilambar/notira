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
	 * Return registered AI provider slugs mapped to display names.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function get_ai_provider_options(): array {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return [];
		}

		$connectors = wp_get_connectors();
		if ( ! is_array( $connectors ) || [] === $connectors ) {
			return [];
		}

		$options = [];
		foreach ( $connectors as $id => $connector_data ) {
			if ( ! is_string( $id ) || '' === $id || ! is_array( $connector_data ) ) {
				continue;
			}

			if ( ! isset( $connector_data['type'] ) || 'ai_provider' !== $connector_data['type'] ) {
				continue;
			}

			if ( ! self::connector_has_credentials( $connector_data ) ) {
				continue;
			}

			$name = ( isset( $connector_data['name'] ) && is_string( $connector_data['name'] ) && '' !== $connector_data['name'] )
				? $connector_data['name']
				: $id;

			$options[ $id ] = $name;
		}

		return $options;
	}

	/**
	 * Whether a single connector data array has a non-empty credential stored.
	 *
	 * Checks env var, PHP constant, and database in order.
	 *
	 * @since 1.0.0
	 *
	 * @param array $connector_data Connector data from wp_get_connectors().
	 * @return bool
	 */
	private static function connector_has_credentials( array $connector_data ): bool {
		if ( ! isset( $connector_data['authentication'] ) || ! is_array( $connector_data['authentication'] ) ) {
			return false;
		}

		$auth = $connector_data['authentication'];

		if ( ! isset( $auth['method'] ) || 'api_key' !== $auth['method'] ) {
			return false;
		}

		$has_env   = ! empty( $auth['env_var_name'] ) && false !== getenv( $auth['env_var_name'] ) && '' !== getenv( $auth['env_var_name'] );
		$has_const = ! empty( $auth['constant_name'] ) && defined( $auth['constant_name'] ) && '' !== constant( $auth['constant_name'] );
		$has_db    = ! empty( $auth['setting_name'] ) && '' !== (string) get_option( $auth['setting_name'], '' );

		return $has_env || $has_const || $has_db;
	}

	/**
	 * Whether at least one Connectors AI provider has configured credentials.
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
			if ( is_array( $connector_data )
				&& isset( $connector_data['type'] )
				&& 'ai_provider' === $connector_data['type']
				&& self::connector_has_credentials( $connector_data )
			) {
				return true;
			}
		}

		return false;
	}
}
