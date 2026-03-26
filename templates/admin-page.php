<?php
/**
 * Admin page template.
 *
 * @package Nilambar\Notira
 * @since 1.0.0
 */

use Nilambar\Notira\API\REST_API;
use Nilambar\Notira\Utils\Credential_Utils;
use Nilambar\Notira\Utils\Tone_Utils;

defined( 'ABSPATH' ) || exit;

$ai_ui_enabled    = Credential_Utils::supports_ai() && Credential_Utils::has_ai_credentials();
$tones            = Tone_Utils::get_tone_options();
$default_tone     = Tone_Utils::DEFAULT_TONE;
$input_min_length = REST_API::INPUT_MIN_LENGTH;
$input_max_length = REST_API::INPUT_MAX_LENGTH;
?>

<div class="wrap notira-wrap">
	<h1><?php esc_html_e( 'Notira', 'notira' ); ?></h1>

	<div class="notira-content">
	<div class="notira-columns">
		<div class="notira-column-left">
			<div class="notira-panel">
				<div class="notira-input-section">
					<label for="notira-input">
						<?php esc_html_e( 'Enter draft notes or bullet points', 'notira' ); ?>
					</label>
					<textarea id="notira-input"
						class="notira-textarea"
						rows="10"
						placeholder="<?php esc_attr_e( 'Paste or type your draft notes, bullets, or paragraphs here…', 'notira' ); ?>"
						maxlength="<?php echo (int) $input_max_length; ?>"
						<?php echo $ai_ui_enabled ? '' : ' disabled'; ?>
					></textarea>
					<p class="description notira-char-count">
						<span class="notira-char-current">0</span> / <?php echo (int) $input_max_length; ?>
						<?php esc_html_e( 'characters', 'notira' ); ?>
						<span class="notira-char-limits">(
						<?php
							/* translators: %d: minimum character count */
							printf( esc_html__( 'Min: %d chars', 'notira' ), (int) $input_min_length );
						?>
						)</span>
					</p>
				</div>

				<div class="notira-tone-section">
					<label for="notira-tone"><?php esc_html_e( 'Tone', 'notira' ); ?></label>
					<select id="notira-tone"
						name="notira_tone"
						<?php echo $ai_ui_enabled ? '' : ' disabled'; ?>
					>
						<?php foreach ( $tones as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $default_tone ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="notira-actions">
					<button type="button" id="notira-generate" class="button button-primary" <?php echo $ai_ui_enabled ? '' : ' disabled'; ?>>
						<?php esc_html_e( 'Generate', 'notira' ); ?>
					</button>
					<span class="spinner" id="notira-generate-spinner" aria-hidden="true"></span>
				</div>
				<div id="notira-generation-meta" class="notira-generation-meta is-empty" role="status" aria-live="polite"></div>
			</div>
			<div
				id="notira-notice"
				class="notira-notice notira-notice--hidden"
				role="status"
				aria-live="polite"
				aria-hidden="true"
			></div>
		</div>

		<div class="notira-column-right">
			<div class="notira-output-section" id="notira-output-section">
				<div class="notira-output-header">
					<label><?php esc_html_e( 'Output', 'notira' ); ?></label>
					<button type="button" id="notira-copy" class="button">
						<?php esc_html_e( 'Copy', 'notira' ); ?>
					</button>
				</div>
				<div id="notira-output" class="notira-output" role="region" aria-live="polite" data-empty="true">
					<span class="notira-output-placeholder" aria-hidden="true"><?php esc_html_e( 'Output will appear here.', 'notira' ); ?></span>
				</div>
			</div>
		</div>
	</div>
	</div>
</div>
