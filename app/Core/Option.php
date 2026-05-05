<?php
/**
 * Plugin option accessor.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Core;

use Nilambar\Optiz\Manager;

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
		return Manager::instance( 'notira_options' )->get( $key );
	}
}
