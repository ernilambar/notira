<?php
/**
 * AI credential helpers.
 *
 * @package Nilambar\Notira
 */

namespace Nilambar\Notira\Utils;

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
	 * Uses `wp_get_connectors()` and per-provider options (e.g. `connectors_ai_openai_api_key`),
	 * environment variables, or PHP constants (same rules as core Connectors).
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
	 * Resolves API key source for a connector (env, constant, database, none).
	 *
	 * Mirrors WordPress core _wp_connectors_get_api_key_source() behavior.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id  Connector or provider ID.
	 * @param string $setting_name Settings option name (e.g. connectors_ai_openai_api_key).
	 * @return string One of env, constant, database, none.
	 */
	private static function get_api_key_source( string $provider_id, string $setting_name ): string {
		$constant_case_id = strtoupper(
			preg_replace( '/([a-z])([A-Z])/', '$1_$2', str_replace( '-', '_', $provider_id ) )
		);
		$env_var_name     = $constant_case_id . '_API_KEY';

		$env_value = getenv( $env_var_name );
		if ( false !== $env_value && '' !== $env_value ) {
			return 'env';
		}

		if ( defined( $env_var_name ) ) {
			$const_value = constant( $env_var_name );
			if ( is_string( $const_value ) && '' !== $const_value ) {
				return 'constant';
			}
		}

		$db_value = get_option( $setting_name, '' );
		if ( '' !== $db_value && is_string( $db_value ) ) {
			return 'database';
		}

		return 'none';
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

		foreach ( $connectors as $connector_id => $connector_data ) {
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

			$source = self::get_api_key_source( (string) $connector_id, $setting_name );
			if ( 'none' !== $source ) {
				return true;
			}
		}

		return false;
	}
}
