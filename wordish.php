<?php
/**
 * Plugin Name: Wordish
 * Plugin URI: https://github.com/ernilambar/wordish
 * Description: Improve draft notes into professional email-ready HTML using AI.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Nilambar
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wordish
 *
 * @package Wordish
 */

use Nilambar\Wordish\Core\Bootstrap;

defined( 'ABSPATH' ) || exit;

// Define.
define( 'WORDISH_VERSION', '1.0.0' );
define( 'WORDISH_BASE_NAME', basename( __DIR__ ) );
define( 'WORDISH_BASE_FILEPATH', __FILE__ );
define( 'WORDISH_BASE_FILENAME', plugin_basename( __FILE__ ) );
define( 'WORDISH_DIR', rtrim( plugin_dir_path( __FILE__ ), '/' ) );
define( 'WORDISH_URL', rtrim( plugin_dir_url( __FILE__ ), '/' ) );

if ( file_exists( WORDISH_DIR . '/vendor/autoload.php' ) ) {
	require_once WORDISH_DIR . '/vendor/autoload.php';
}

require_once WORDISH_DIR . '/app/Core/Bootstrap.php';

Bootstrap::init();
