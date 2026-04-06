<?php
/**
 * PHPUnit bootstrap for Notira.
 *
 * @package Notira
 */

$notira_dir = dirname( __DIR__ );

require_once $notira_dir . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( false === $_tests_dir || '' === $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! is_readable( $_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find ' . $_tests_dir . "/includes/functions.php — run bin/install-wp-tests.sh or set WP_TESTS_DIR.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin under test.
 */
function notira_tests_load_plugin() {
	require dirname( __DIR__ ) . '/notira.php';
}

tests_add_filter( 'muplugins_loaded', 'notira_tests_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
