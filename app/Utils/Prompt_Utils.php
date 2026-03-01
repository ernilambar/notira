<?php
/**
 * Prompt template helpers.
 *
 * @package Nilambar\Wordish
 */

namespace Nilambar\Wordish\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Prompt_Utils.
 *
 * @since 1.0.0
 */
class Prompt_Utils {

	/**
	 * Load a prompt template from prompts directory and replace placeholders.
	 *
	 * Placeholders in the template file use the format {{key}}. Pass replacements
	 * as [ 'key' => 'value' ].
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename     Template filename (e.g. system.md).
	 * @param array  $replacements Map of placeholder name => value (e.g. [ 'tone' => 'Professional' ]).
	 * @return string Template content with placeholders replaced, or empty string if file unreadable.
	 */
	public static function get_template( string $filename, array $replacements = [] ): string {
		$path = WORDISH_DIR . '/prompts/' . $filename;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return '';
		}

		$content = trim( $content );

		foreach ( $replacements as $key => $value ) {
			$content = str_replace( '{{' . $key . '}}', $value, $content );
		}

		return $content;
	}

	/**
	 * Get the system prompt for email body generation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tone_label Tone label for the prompt.
	 * @return string Template content or empty string if file unreadable.
	 */
	public static function get_email_system_prompt( string $tone_label ): string {
		return self::get_template( 'system.md', [ 'tone' => $tone_label ] );
	}

	/**
	 * Get the user prompt for email body generation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $input User draft input.
	 * @return string Template content or empty string if file unreadable.
	 */
	public static function get_email_user_prompt( string $input ): string {
		return self::get_template( 'user.md', [ 'input' => $input ] );
	}
}
