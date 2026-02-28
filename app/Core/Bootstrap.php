<?php
/**
 * Plugin bootstrap and hook registration.
 *
 * @package Nilambar\Wordish
 */

namespace Nilambar\Wordish\Core;

use Nilambar\Wordish\Rest\Controller as RestController;
use Nilambar\Wordish\Utils\Credential_Utils;
use Nilambar\Wordish\Utils\Tone_Utils;

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
		add_action( 'init', array( __CLASS__, 'on_init' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ), 10, 1 );
		add_filter( 'linkit_admin_links_status', array( __CLASS__, 'disable_linkit_on_wordish_page' ), 10, 2 );

		RestController::init();
	}

	/**
	 * Initialize text domain.
	 *
	 * @since 1.0.0
	 */
	public static function on_init(): void {
		self::load_textdomain();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 1.0.0
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'wordish',
			false,
			dirname( WORDISH_BASE_FILENAME ) . '/languages'
		);
	}

	/**
	 * Disable Linkit admin links (Open Links button, quick menu) on the Wordish admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $show Whether to show Linkit admin UI. Passed by linkit_admin_links_status filter.
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
	 * Register admin menu under Dashboard.
	 *
	 * @since 1.0.0
	 */
	public static function register_admin_menu(): void {
		add_dashboard_page(
			__( 'Wordish', 'wordish' ),
			__( 'Wordish', 'wordish' ),
			'manage_options',
			self::ADMIN_PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles only on Wordish page.
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
			array(),
			WORDISH_VERSION
		);

		wp_enqueue_script(
			'wordish-admin',
			WORDISH_URL . '/assets/js/admin.js',
			array(),
			WORDISH_VERSION,
			true
		);

		wp_localize_script(
			'wordish-admin',
			'wordishAdmin',
			array(
				'apiUrl'    => rest_url( 'wordish/v1/generate' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'minLength' => RestController::INPUT_MIN_LENGTH,
				'maxLength' => RestController::INPUT_MAX_LENGTH,
				'i18n'      => array(
					'copyLabel'        => __( 'Copy!', 'wordish' ),
					'copiedLabel'      => __( 'Copied', 'wordish' ),
					'inputTooShort'    => sprintf(
						/* translators: %d: min character count */
						__( 'Input is too short. Please enter at least %d characters.', 'wordish' ),
						RestController::INPUT_MIN_LENGTH
					),
				),
			)
		);
	}

	/**
	 * Render the Wordish admin page.
	 *
	 * @since 1.0.0
	 */
	public static function render_admin_page(): void {
		$has_credentials = Credential_Utils::has_ai_credentials();
		$tones           = Tone_Utils::get_tone_options();
		?>
		<div class="wrap wordish-wrap">
			<h1><?php esc_html_e( 'Wordish', 'wordish' ); ?></h1>

			<?php if ( ! $has_credentials ) : ?>
				<div class="notice notice-warning wordish-notice-not-dismissible">
					<p><?php esc_html_e( 'Please set your API key in Settings → AI Credentials to use this feature.', 'wordish' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wordish-columns">
				<div class="wordish-column-left">
					<div class="wordish-panel">
						<div class="wordish-input-section">
							<label for="wordish-input">
								<?php esc_html_e( 'Draft notes or bullet points', 'wordish' ); ?>
							</label>
							<textarea id="wordish-input"
								class="wordish-textarea"
								rows="10"
								placeholder="<?php esc_attr_e( 'Paste or type your draft notes, bullets, or paragraphs here…', 'wordish' ); ?>"
								maxlength="<?php echo (int) RestController::INPUT_MAX_LENGTH; ?>"
								<?php echo $has_credentials ? '' : ' disabled'; ?>
							></textarea>
							<p class="description wordish-char-count">
								<span class="wordish-char-current">0</span> / <?php echo (int) RestController::INPUT_MAX_LENGTH; ?>
								<?php esc_html_e( 'characters', 'wordish' ); ?>
							</p>
						</div>

						<div class="wordish-tone-section">
							<fieldset>
								<legend><?php esc_html_e( 'Tone', 'wordish' ); ?></legend>
								<?php foreach ( $tones as $value => $label ) : ?>
									<label class="wordish-tone-option">
										<input type="radio"
											name="wordish_tone"
											value="<?php echo esc_attr( $value ); ?>"
											<?php checked( $value, Tone_Utils::DEFAULT_TONE ); ?>
											<?php echo $has_credentials ? '' : ' disabled'; ?>
										/>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</div>

						<div class="wordish-actions">
							<button type="button" id="wordish-generate" class="button button-primary" <?php echo $has_credentials ? '' : ' disabled'; ?>>
								<?php esc_html_e( 'Generate', 'wordish' ); ?>
							</button>
							<span class="spinner" id="wordish-generate-spinner" aria-hidden="true"></span>
						</div>
					</div>
					<div id="wordish-message" class="wordish-message" role="status" aria-live="polite"></div>
				</div>

				<div class="wordish-column-right">
					<div class="wordish-output-section" id="wordish-output-section">
						<div class="wordish-output-header">
							<label><?php esc_html_e( 'Output (HTML)', 'wordish' ); ?></label>
							<button type="button" id="wordish-copy" class="button">
								<?php esc_html_e( 'Copy!', 'wordish' ); ?>
							</button>
						</div>
						<div id="wordish-output" class="wordish-output" role="region" aria-live="polite" data-empty="true">
							<span class="wordish-output-placeholder" aria-hidden="true"><?php esc_html_e( 'Output will appear here.', 'wordish' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
