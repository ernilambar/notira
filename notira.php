<?php
/**
 * Plugin Name: Notira
 * Plugin URI: https://github.com/ernilambar/notira
 * Description: Improve draft text into email-ready or proofread HTML using AI.
 * Version: 1.1.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Nilambar Sharma
 * Author URI: https://nilambar.net/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: notira
 * Domain Path: /languages
 *
 * @package Notira
 */

declare(strict_types=1);

use Nilambar\Notira\Core\Bootstrap;

defined( 'ABSPATH' ) || exit;

// Define.
define( 'NOTIRA_VERSION', '1.1.0' );
define( 'NOTIRA_BASE_NAME', basename( __DIR__ ) );
define( 'NOTIRA_BASE_FILEPATH', __FILE__ );
define( 'NOTIRA_BASE_FILENAME', plugin_basename( __FILE__ ) );
define( 'NOTIRA_DIR', rtrim( plugin_dir_path( __FILE__ ), '/' ) );
define( 'NOTIRA_URL', rtrim( plugin_dir_url( __FILE__ ), '/' ) );

if ( file_exists( NOTIRA_DIR . '/vendor/autoload.php' ) ) {
	require_once NOTIRA_DIR . '/vendor/autoload.php';
	require_once NOTIRA_DIR . '/vendor/ernilambar/optiz/init.php';
}

require_once NOTIRA_DIR . '/app/Core/Bootstrap.php';

Bootstrap::init();
