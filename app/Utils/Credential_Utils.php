<?php
/**
 * AI credential helpers.
 *
 * @package Nilambar\Wordish
 */

namespace Nilambar\Wordish\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Credential_Utils.
 *
 * @since 1.0.0
 */
class Credential_Utils {

	/**
	 * Option name for AI credentials.
	 *
	 * @since 1.0.0
	 */
	public const WP_AI_CLIENT_CREDENTIALS_OPTION = 'wp_ai_client_provider_credentials';

	/**
	 * Map of provider IDs (credentials option keys) to their main plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	private static $provider_plugin_map = [
		'anthropic' => 'ai-provider-for-anthropic/plugin.php',
		'google'    => 'ai-provider-for-google/plugin.php',
		'openai'    => 'ai-provider-for-openai/plugin.php',
	];

	/**
	 * Check if at least one AI provider has credentials and its plugin is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function has_ai_credentials(): bool {
		$credentials = get_option( self::WP_AI_CLIENT_CREDENTIALS_OPTION, [] );
		if ( ! is_array( $credentials ) ) {
			return false;
		}

		$provider_plugin_map = apply_filters( 'wordish_ai_provider_plugin_map', self::$provider_plugin_map );

		$active_plugins  = (array) get_option( 'active_plugins', [] );
		$network_plugins = is_multisite()
			? (array) get_site_option( 'active_sitewide_plugins', [] )
			: [];

		foreach ( $credentials as $provider_id => $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$plugin_file = isset( $provider_plugin_map[ $provider_id ] )
				? $provider_plugin_map[ $provider_id ]
				: null;

			if ( null === $plugin_file ) {
				continue;
			}

			$is_active = in_array( $plugin_file, $active_plugins, true )
				|| isset( $network_plugins[ $plugin_file ] );

			if ( $is_active ) {
				return true;
			}
		}

		return false;
	}
}
