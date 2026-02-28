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
}
