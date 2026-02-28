<?php
/**
 * Plugin settings helpers.
 *
 * @package Nilambar\Wordish
 * @since 1.0.0
 */

namespace Nilambar\Wordish\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Option name for wp-ai-client provider credentials (Settings → AI Credentials).
	 *
	 * @since 1.0.0
	 */
	public const WP_AI_CLIENT_CREDENTIALS_OPTION = 'wp_ai_client_provider_credentials';

	/**
	 * Transient key for invalid API key flag.
	 *
	 * @since 1.0.0
	 */
	public const TRANSIENT_INVALID_KEY = 'wordish_api_key_invalid';

	/**
	 * Initialize settings.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		// Clear invalid-key flag when AI Credentials are updated.
		add_action( 'update_option_' . self::WP_AI_CLIENT_CREDENTIALS_OPTION, array( __CLASS__, 'clear_invalid_key_flag' ), 10, 0 );
	}

	/**
	 * Check if at least one AI provider credential is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function has_ai_credentials(): bool {
		$credentials = get_option( self::WP_AI_CLIENT_CREDENTIALS_OPTION, array() );
		if ( ! is_array( $credentials ) ) {
			return false;
		}
		foreach ( $credentials as $key ) {
			if ( is_string( $key ) && '' !== $key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clear invalid API key transient when key is updated.
	 *
	 * @since 1.0.0
	 */
	public static function clear_invalid_key_flag(): void {
		delete_transient( self::TRANSIENT_INVALID_KEY );
	}
}
