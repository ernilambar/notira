<?php
/**
 * Prompt template helpers.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Utils;

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
	 * @param string $filename     Template filename (e.g. email-system.md).
	 * @param array  $replacements Map of placeholder name => value (e.g. [ 'tone' => 'Professional' ]).
	 * @return string Template content with placeholders replaced, or empty string if file unreadable.
	 */
	public static function get_template( string $filename, array $replacements = [] ): string {
		$path = NOTIRA_DIR . '/prompts/' . $filename;

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
	 * Resolve a mode prompt filename (`{slug}-user.md` or `{slug}-system.md`).
	 *
	 * New modes: register the slug in Mode_Utils and add matching files under `prompts/`.
	 *
	 * @since 1.1.0
	 *
	 * @param string $mode Generation mode slug.
	 * @param string $kind `system` or `user`.
	 * @return string Basename or empty string if the slug or kind is invalid.
	 */
	private static function get_mode_prompt_filename( string $mode, string $kind ): string {
		$slug = sanitize_key( $mode );
		if ( '' === $slug || ! in_array( $slug, Mode_Utils::get_valid_slugs(), true ) ) {
			return '';
		}
		if ( 'system' !== $kind && 'user' !== $kind ) {
			return '';
		}

		return $slug . '-' . $kind . '.md';
	}

	/**
	 * Build the tone instruction block for a mode and tone slug.
	 *
	 * @since 1.1.0
	 *
	 * @param string $mode      Generation mode slug.
	 * @param string $tone_slug Tone slug.
	 * @return string Instructions for the system prompt.
	 */
	public static function get_tone_instruction_block( string $mode, string $tone_slug ): string {
		if ( 'match_original' === $tone_slug ) {
			return 'Match the source tone and register of the text; do not impose a separate named style (such as Professional or Friendly) unless a correction strictly requires it.';
		}

		$label = Tone_Utils::get_tone_label( $tone_slug );

		if ( Mode_Utils::MODE_PROOFREAD === $mode ) {
			return sprintf(
				'Apply the %s tone lightly: use it only where it helps clarity; do not rewrite for style when the sentence is already correct.',
				$label
			);
		}

		return sprintf( 'Tone: %s.', $label );
	}

	/**
	 * Assemble full system prompt from the mode-specific system template.
	 *
	 * @since 1.1.0
	 *
	 * @param string $mode      Generation mode slug.
	 * @param string $tone_slug Tone slug.
	 * @return string System prompt or empty string if templates are missing.
	 */
	public static function get_assembled_system_prompt( string $mode, string $tone_slug ): string {
		$system_file = self::get_mode_prompt_filename( $mode, 'system' );
		$tone_block  = self::get_tone_instruction_block( $mode, $tone_slug );

		if ( '' === $system_file ) {
			return '';
		}

		$mode_system = self::get_template(
			$system_file,
			[
				'tone_block' => $tone_block,
			]
		);

		if ( '' === $mode_system ) {
			return '';
		}

		return $mode_system;
	}

	/**
	 * Get the user prompt for the given mode.
	 *
	 * @since 1.1.0
	 *
	 * @param string $mode  Generation mode slug.
	 * @param string $input User input.
	 * @return string User prompt or empty string if file unreadable.
	 */
	public static function get_mode_user_prompt( string $mode, string $input ): string {
		$user_file = self::get_mode_prompt_filename( $mode, 'user' );
		if ( '' === $user_file ) {
			return '';
		}

		return self::get_template(
			$user_file,
			[
				'input' => $input,
			]
		);
	}
}
