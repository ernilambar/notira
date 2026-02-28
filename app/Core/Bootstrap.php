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
		$message        = sprintf(
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
			WORDISH_URL . '/assets/css/admin.css',
			[],
			WORDISH_VERSION
		);

		wp_enqueue_script(
			'wordish-admin',
			WORDISH_URL . '/assets/js/admin.js',
			[],
			WORDISH_VERSION,
			true
		);

		wp_localize_script(
			'wordish-admin',
			'wordishAdmin',
			[
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
			]
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
