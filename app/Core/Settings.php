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
	 * Option name for OpenAI API key.
	 *
	 * @since 1.0.0
	 */
	public const OPTION_API_KEY = 'wordish_openai_api_key';

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
		// Clear invalid-key flag when API key is updated.
		add_action( 'update_option_' . self::OPTION_API_KEY, array( __CLASS__, 'clear_invalid_key_flag' ), 10, 0 );
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
