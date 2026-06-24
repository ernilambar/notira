<?php
/**
 * Plugin bootstrap and hook registration.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Core;

use Nilambar\Notira\API\REST_API;
use Nilambar\Notira\Options\Options;
use Nilambar\Notira\Utils\Credential_Utils;
use Nilambar\Notira\Utils\Mode_Utils;
use Nilambar\Notira\Utils\Tone_Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Bootstrap.
 *
 * @since 1.0.0
 */
class Bootstrap {

	/**
	 * Admin page menu slug (generator).
	 *
	 * @since 1.0.0
	 */
	public const ADMIN_PAGE_SLUG = 'notira';

	/**
	 * Settings submenu slug.
	 *
	 * @since 1.0.0
	 */
	public const SETTINGS_PAGE_SLUG = 'notira-settings';

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		( new Options() )->register();

		add_action( 'init', [ __CLASS__, 'load_textdomain' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_admin_menu' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_credentials_notice' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ], 10, 1 );
		add_action( 'admin_footer', [ __CLASS__, 'print_admin_settings' ], 0 );
		add_action( 'admin_head', [ __CLASS__, 'set_favicon' ], 999 );
		add_filter( 'site_icon_meta_tags', [ __CLASS__, 'disable_core_favicon' ], 10, 1 );
		add_filter( 'plugin_action_links_' . NOTIRA_BASE_FILENAME, [ __CLASS__, 'add_plugin_action_links' ] );
		add_action( 'wp_ajax_notira_get_models', [ __CLASS__, 'handle_get_models_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_settings_assets' ] );

		REST_API::init();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'notira', false, NOTIRA_BASE_NAME . '/languages' );
	}

	/**
	 * Show credentials notice when no API key is set.
	 *
	 * @since 1.0.0
	 */
	public static function render_credentials_notice(): void {
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! in_array( $current_page, [ self::ADMIN_PAGE_SLUG, self::SETTINGS_PAGE_SLUG ], true ) ) {
			return;
		}

		if ( Credential_Utils::supports_ai() && Credential_Utils::has_ai_credentials() ) {
			return;
		}

		if ( ! Credential_Utils::supports_ai() ) {
			wp_admin_notice(
				esc_html__( 'WordPress AI is disabled.', 'notira' ),
				[
					'type'        => 'warning',
					'dismissible' => false,
				]
			);

			return;
		}

		$connectors_url = admin_url( 'options-connectors.php' );

		$message = sprintf(
			/* translators: 1: opening link tag, 2: closing link tag */
			esc_html__( 'Set your API key in %1$sConnectors%2$s to use this feature.', 'notira' ),
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
	 * Set favicon for Notira admin page.
	 *
	 * @since 1.0.0
	 */
	public static function set_favicon(): void {
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::ADMIN_PAGE_SLUG === $current_page || self::SETTINGS_PAGE_SLUG === $current_page ) {
			$icon_url = NOTIRA_URL . '/build/favicon.png';

			echo '<link rel="shortcut icon" type="image/png" href="' . esc_url( $icon_url ) . '">' . "\n";
			echo '<link rel="icon" type="image/png" href="' . esc_url( $icon_url ) . '">' . "\n";
		}
	}

	/**
	 * Disable core favicon meta tags on Notira admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meta_tags Array of favicon meta tags.
	 * @return array Modified array of meta tags.
	 */
	public static function disable_core_favicon( array $meta_tags ): array {
		global $pagenow;

		if ( ! isset( $pagenow ) || 'admin.php' !== $pagenow || ! isset( $_GET['page'] ) ) {
			return $meta_tags;
		}

		$current_page = sanitize_key( wp_unslash( $_GET['page'] ) );

		if ( self::ADMIN_PAGE_SLUG === $current_page || self::SETTINGS_PAGE_SLUG === $current_page ) {
			return [];
		}

		return $meta_tags;
	}

	/**
	 * Register admin menu.
	 *
	 * @since 1.0.0
	 */
	public static function register_admin_menu(): void {
		add_menu_page(
			_x( 'Notira', 'page title', 'notira' ),
			_x( 'Notira', 'menu title', 'notira' ),
			'manage_options',
			self::ADMIN_PAGE_SLUG,
			[ __CLASS__, 'render_admin_page' ],
			'dashicons-email-alt',
			58
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
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::ADMIN_PAGE_SLUG !== $current_page ) {
			return;
		}

		wp_enqueue_style(
			'notira-admin',
			NOTIRA_URL . '/build/main.css',
			[],
			NOTIRA_VERSION
		);

		wp_enqueue_script_module(
			'notira-admin',
			NOTIRA_URL . '/build/main.js',
			[],
			NOTIRA_VERSION
		);
	}

	/**
	 * Print admin settings for script modules (no wp_localize_script for modules).
	 *
	 * Outputs before script modules so notiraAdmin is available to the bundle.
	 *
	 * @since 1.0.0
	 */
	public static function print_admin_settings(): void {
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::ADMIN_PAGE_SLUG !== $current_page ) {
			return;
		}

		$ai_ui_enabled = Credential_Utils::supports_ai() && Credential_Utils::has_ai_credentials();
		$tones_options = Tone_Utils::get_tone_options();
		$tones_list    = [];
		foreach ( $tones_options as $value => $label ) {
			$tones_list[] = [
				'value' => (string) $value,
				'label' => $label,
			];
		}

		$modes_list = [
			[
				'value' => Mode_Utils::MODE_PROOFREAD,
				'label' => __( 'Proofread', 'notira' ),
				'help'  => __( 'Grammar, spelling, and punctuation corrections, with clarity improvements only where needed; preserves structure and meaning.', 'notira' ),
			],
			[
				'value' => Mode_Utils::MODE_EMAIL,
				'label' => __( 'Email', 'notira' ),
				'help'  => __( 'Polish your notes into a clear email body. Opening and closing lines from settings are added around the result.', 'notira' ),
			],
		];

		$default_mode = Option::get( 'default_mode' );
		if ( ! is_string( $default_mode ) || ! in_array( $default_mode, Mode_Utils::get_valid_slugs(), true ) ) {
			$default_mode = Mode_Utils::DEFAULT_MODE;
		}

		$default_tone = Option::get( 'default_tone' );
		if ( ! is_string( $default_tone ) || ! in_array( $default_tone, Tone_Utils::get_valid_slugs(), true ) ) {
			$default_tone = Tone_Utils::DEFAULT_TONE;
		}

		$settings = [
			'apiUrl'         => rest_url( 'notira/v1/generate' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'aiUiEnabled'    => $ai_ui_enabled,
			'defaultMode'    => $default_mode,
			'modes'          => $modes_list,
			'defaultTone'    => $default_tone,
			'tones'          => $tones_list,
			'defaultContent' => '',
			'i18n'           => [
				'inputLabel'         => __( 'Enter draft notes or bullet points', 'notira' ),
				'inputPlaceholder'   => __( 'Paste or type your text here…', 'notira' ),
				'modeLabel'          => __( 'Mode', 'notira' ),
				'toneLabel'          => __( 'Tone', 'notira' ),
				'generateLabel'      => __( 'Generate', 'notira' ),
				'copyLabel'          => __( 'Copy', 'notira' ),
				'copiedLabel'        => __( 'Copied', 'notira' ),
				'outputLabel'        => __( 'Output', 'notira' ),
				'outputPlaceholder'  => __( 'Output will appear here.', 'notira' ),
				/* translators: Character counter format. %1$d: current length, %2$d: max length, e.g. "12 / 500 characters". */
				'charCountFormat'    => __( '%1$d / %2$d characters', 'notira' ),
				'minCharsHint'       => sprintf(
					/* translators: %d: minimum character count */
					__( 'Min: %d chars', 'notira' ),
					REST_API::INPUT_MIN_LENGTH
				),
				'generatingLabel'    => __( 'Generating…', 'notira' ),
				'inputTooShort'      => sprintf(
					/* translators: %d: min character count */
					__( 'Input is too short. Enter at least %d characters.', 'notira' ),
					REST_API::INPUT_MIN_LENGTH
				),
				'textTooLong'        => __( 'Text is too long.', 'notira' ),
				'pleaseEnterText'    => __( 'Enter text.', 'notira' ),
				'nothingToCopy'      => __( 'Nothing to copy.', 'notira' ),
				'copyFailedManual'   => __( 'Could not copy. Select and copy manually.', 'notira' ),
				'generatedSuccess'   => __( 'Generated.', 'notira' ),
				'requestFailed'      => __( 'Request failed.', 'notira' ),
				'somethingWentWrong' => __( 'Something went wrong.', 'notira' ),
				'networkError'       => __( 'Network or server error. Try again.', 'notira' ),
				'metaProvider'       => __( 'Provider', 'notira' ),
				'metaModel'          => __( 'Model', 'notira' ),
				'metaTokens'         => __( 'Tokens', 'notira' ),
				'metaPrompt'         => __( 'Prompt', 'notira' ),
				'metaCompletion'     => __( 'Completion', 'notira' ),
				'metaTotal'          => __( 'Total', 'notira' ),
				'metaThought'        => __( 'Thought', 'notira' ),
				'metaFromCache'      => __( 'Served from cache.', 'notira' ),
			],
		];

		wp_print_inline_script_tag(
			'window.notiraAdmin = ' . wp_json_encode( $settings ) . ';',
			[ 'id' => 'notira-admin-settings' ]
		);
	}

	/**
	 * Handle AJAX request to return model options for a given provider.
	 *
	 * @since 1.0.0
	 */
	public static function handle_get_models_ajax(): void {
		check_ajax_referer( 'notira_get_models', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		wp_send_json_success( Credential_Utils::get_models_for_provider( $provider_id ) );
	}

	/**
	 * Enqueue settings page scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_settings_assets( $hook_suffix ): void {
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::SETTINGS_PAGE_SLUG !== $current_page ) {
			return;
		}

		wp_enqueue_script(
			'notira-settings',
			NOTIRA_URL . '/build/settings.js',
			[],
			NOTIRA_VERSION,
			[ 'in_footer' => true ]
		);

		wp_add_inline_script(
			'notira-settings',
			'window.notiraSettings = ' . wp_json_encode(
				[
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'notira_get_models' ),
					'default_label' => __( '- Default -', 'notira' ),
					'saved_model'   => sanitize_text_field( (string) Option::get( 'preferred_model' ) ),
				]
			) . ';',
			'before'
		);
	}

	/**
	 * Add Settings link to plugin action links.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public static function add_plugin_action_links( array $links ): array {
		$settings_url  = admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'notira' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.0.0
	 */
	public static function render_admin_page(): void {
		include_once NOTIRA_DIR . '/templates/admin-page.php';
	}
}
