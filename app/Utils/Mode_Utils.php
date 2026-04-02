<?php
/**
 * Generation mode helpers.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Mode_Utils.
 *
 * @since 1.1.0
 */
class Mode_Utils {

	/**
	 * Email polish mode (REST / internal slug).
	 *
	 * @since 1.1.0
	 */
	public const MODE_EMAIL = 'email';

	/**
	 * Minimal proofread mode (REST / internal slug).
	 *
	 * @since 1.1.0
	 */
	public const MODE_PROOFREAD = 'proofread';

	/**
	 * Default mode when the UI loads.
	 *
	 * @since 1.1.0
	 */
	public const DEFAULT_MODE = self::MODE_EMAIL;

	/**
	 * Valid mode slugs for REST and validation.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function get_valid_slugs(): array {
		return [
			self::MODE_EMAIL,
			self::MODE_PROOFREAD,
		];
	}
}
