<?php
/**
 * Plugin bootstrap and hook registration.
 *
 * @package Nilambar\Wordish
 */

namespace Nilambar\Wordish\Core;

use Nilambar\Wordish\API\REST_API;
use Nilambar\Wordish\Utils\Credential_Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Bootstrap.
 *
 * @since 1.0.0
 */
class Bootstrap {

	/**
	 * Admin page menu slug.
	 *
	 * @since 1.0.0
	 */
	public const ADMIN_PAGE_SLUG = 'wordish';

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_admin_menu' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_credentials_notice' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ], 10, 1 );
		add_action( 'admin_footer', [ __CLASS__, 'print_admin_settings' ], 0 );
		add_action( 'admin_head', [ __CLASS__, 'set_favicon' ], 999 );
		add_filter( 'site_icon_meta_tags', [ __CLASS__, 'disable_core_favicon' ], 10, 1 );
		add_filter( 'linkit_admin_links_status', [ __CLASS__, 'disable_linkit_on_wordish_page' ], 10, 2 );

		REST_API::init();
	}

	/**
	 * Show credentials notice when no API key is set.
	 *
	 * @since 1.0.0
	 */
	public static function render_credentials_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard_page_' . self::ADMIN_PAGE_SLUG !== $screen->id ) {
			return;
		}

		if ( Credential_Utils::has_ai_credentials() ) {
			return;
		}

		$connectors_url = admin_url( 'options-general.php?page=connectors-wp-admin' );

		$message = sprintf(
			/* translators: 1: opening link tag, 2: closing link tag */
			esc_html__( 'Please set your API key in %1$sConnectors%2$s to use this feature.', 'wordish' ),
			'<a href="' . esc_url( $connectors_url ) . '">',
			'</a>'
		);

		wp_admin_notice(
			$message,
			[
				'type'        => 'error',
				'dismissible' => false,
			]
		);
	}

	/**
	 * Set favicon for Wordish admin page.
	 *
	 * @since 1.0.0
	 */
	public static function set_favicon(): void {
		if ( isset( $_GET['page'] ) && self::ADMIN_PAGE_SLUG === $_GET['page'] ) {
			$icon_url = WORDISH_URL . '/static/favicon.png';

			echo '<link rel="shortcut icon" type="image/png" href="' . esc_url( $icon_url ) . '">' . "\n";
			echo '<link rel="icon" type="image/png" href="' . esc_url( $icon_url ) . '">' . "\n";
		}
	}

	/**
	 * Disable core favicon meta tags on Wordish admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meta_tags Array of favicon meta tags.
	 * @return array Modified array of meta tags.
	 */
	public static function disable_core_favicon( array $meta_tags ): array {
		global $pagenow;

		if ( isset( $pagenow ) && 'index.php' === $pagenow && isset( $_GET['page'] ) && self::ADMIN_PAGE_SLUG === $_GET['page'] ) {
			return [];
		}

		return $meta_tags;
	}

	/**
	 * Disable Linkit admin links.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $show Whether to show Linkit admin UI.
	 * @param string $hook Current admin page hook suffix.
	 * @return bool False on Wordish page, otherwise the original $show value.
	 */
	public static function disable_linkit_on_wordish_page( bool $show, string $hook ): bool {
		if ( 'dashboard_page_' . self::ADMIN_PAGE_SLUG === $hook ) {
			return false;
		}
		return $show;
	}

	/**
	 * Register admin menu.
	 *
	 * @since 1.0.0
	 */
	public static function register_admin_menu(): void {
		add_dashboard_page(
			__( 'Wordish', 'wordish' ),
			__( 'Wordish', 'wordish' ),
			'manage_options',
			self::ADMIN_PAGE_SLUG,
			[ __CLASS__, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook_suffix ): void {
		if ( 'dashboard_page_' . self::ADMIN_PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wordish-admin',
			WORDISH_URL . '/build/main.css',
			[],
			WORDISH_VERSION
		);

		wp_enqueue_script_module(
			'wordish-admin',
			WORDISH_URL . '/build/main.js',
			[],
			WORDISH_VERSION
		);
	}

	/**
	 * Print admin settings for script modules (no wp_localize_script for modules).
	 *
	 * Outputs before script modules so wordishAdmin is available to the bundle.
	 *
	 * @since 1.0.0
	 */
	public static function print_admin_settings(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard_page_' . self::ADMIN_PAGE_SLUG !== $screen->id ) {
			return;
		}

		$settings = [
			'apiUrl'    => rest_url( 'wordish/v1/generate' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'minLength' => REST_API::INPUT_MIN_LENGTH,
			'maxLength' => REST_API::INPUT_MAX_LENGTH,
			'i18n'      => [
				'copyLabel'     => __( 'Copy', 'wordish' ),
				'copiedLabel'   => __( 'Copied', 'wordish' ),
				'inputTooShort' => sprintf(
					/* translators: %d: min character count */
					__( 'Input is too short. Please enter at least %d characters.', 'wordish' ),
					REST_API::INPUT_MIN_LENGTH
				),
			],
		];

		wp_print_inline_script_tag(
			'window.wordishAdmin = ' . wp_json_encode( $settings ) . ';',
			[ 'id' => 'wordish-admin-settings' ]
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.0.0
	 */
	public static function render_admin_page(): void {
		include_once WORDISH_DIR . '/templates/admin-page.php';
	}
}
