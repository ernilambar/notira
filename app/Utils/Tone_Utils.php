<?php
/**
 * Tone helpers.
 *
 * @package Nilambar\Wordish
 */

namespace Nilambar\Wordish\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tone_Utils.
 *
 * @since 1.0.0
 */
class Tone_Utils {

	/**
	 * Default tone slug when none or invalid is given.
	 *
	 * @since 1.0.0
	 */
	public const DEFAULT_TONE = 'professional';

	/**
	 * Get all tone options as slug => translated label.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function get_tone_options(): array {
		return array(
			'professional'  => __( 'Professional and courteous', 'wordish' ),
			'friendly'      => __( 'Friendly and warm', 'wordish' ),
			'formal'        => __( 'Formal', 'wordish' ),
			'concise'       => __( 'Concise and direct', 'wordish' ),
			'empathetic'    => __( 'Empathetic and supportive', 'wordish' ),
			'authoritative' => __( 'Authoritative', 'wordish' ),
			'commanding'    => __( 'Commanding', 'wordish' ),
			'assertive'     => __( 'Assertive', 'wordish' ),
			'neutral'       => __( 'Neutral (objective and factual)', 'wordish' ),
		);
	}

	/**
	 * Get list of valid tone slugs (for validation / REST enum).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public static function get_valid_slugs(): array {
		return array_keys( self::get_tone_options() );
	}

	/**
	 * Get translated label for a tone slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Tone slug.
	 * @return string Label for the tone, or default tone label if slug invalid.
	 */
	public static function get_tone_label( string $slug ): string {
		$options = self::get_tone_options();
		return isset( $options[ $slug ] ) ? $options[ $slug ] : $options[ self::DEFAULT_TONE ];
	}
}
