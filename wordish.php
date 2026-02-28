<?php
/**
 * Plugin Name: Wordish
 * Plugin URI: https://github.com/nilambar/wordish
 * Description: Improve rough notes into professional email-ready HTML using AI.
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: Nilambar
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wordish
 *
 * @package Wordish
 */

defined( 'ABSPATH' ) || exit;

const WORDISH_VERSION = '1.0.0';
const WORDISH_PLUGIN_FILE = __FILE__;
const WORDISH_PLUGIN_DIR = __DIR__;

if ( file_exists( WORDISH_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
	require_once WORDISH_PLUGIN_DIR . '/vendor/autoload.php';
}

require_once WORDISH_PLUGIN_DIR . '/app/Core/Bootstrap.php';

Nilambar\Wordish\Core\Bootstrap::init();
