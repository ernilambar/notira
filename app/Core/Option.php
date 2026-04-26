<?php
/**
 * Plugin option accessor.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Core;

use Nilambar\Notira\Utils\Mode_Utils;
use Nilambar\Notira\Utils\Tone_Utils;

/**
 * Option class.
 *
 * @since 1.0.0
 */
class Option {

	/**
	 * Return plugin option.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Option key.
	 * @return mixed Option value.
	 */
	public static function get( string $key ) {
		if ( '' === $key ) {
			return null;
		}

		$default_options = self::get_defaults();
		$current_options = (array) get_option( 'notira_options' );
		$current_options = wp_parse_args( $current_options, $default_options );

		if ( array_key_exists( $key, $current_options ) ) {
			return $current_options[ $key ];
		}

		return null;
	}

	/**
	 * Return default options.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Default options.
	 */
	public static function get_defaults() {
		return [
			'default_mode'   => Mode_Utils::DEFAULT_MODE,
			'default_tone'   => Tone_Utils::DEFAULT_TONE,
			'email_greeting' => __( 'Hi,', 'notira' ),
			'email_signoff'  => __( 'Regards,', 'notira' ),
		];
	}

	/**
	 * Return default value of given key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Option key.
	 * @return mixed Default option value.
	 */
	public static function defaults( string $key ) {
		$value = null;

		$defaults = self::get_defaults();

		if ( '' !== $key && array_key_exists( $key, $defaults ) ) {
			$value = $defaults[ $key ];
		}

		return $value;
	}
}
