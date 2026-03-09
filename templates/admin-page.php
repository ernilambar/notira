<?php
/**
 * Admin page template.
 *
 * @package Nilambar\Wordish
 * @since 1.0.0
 */

use Nilambar\Wordish\API\REST_API;
use Nilambar\Wordish\Utils\Credential_Utils;
use Nilambar\Wordish\Utils\Tone_Utils;

defined( 'ABSPATH' ) || exit;

$has_credentials  = Credential_Utils::has_ai_credentials();
$tones            = Tone_Utils::get_tone_options();
$default_tone     = Tone_Utils::DEFAULT_TONE;
$input_min_length = REST_API::INPUT_MIN_LENGTH;
$input_max_length = REST_API::INPUT_MAX_LENGTH;
?>

<div class="wrap wordish-wrap">
	<h1><?php esc_html_e( 'Wordish', 'wordish' ); ?></h1>

	<div class="wordish-columns">
		<div class="wordish-column-left">
			<div class="wordish-panel">
				<div class="wordish-input-section">
					<label for="wordish-input">
						<?php esc_html_e( 'Enter draft notes or bullet points', 'wordish' ); ?>
					</label>
					<textarea id="wordish-input"
						class="wordish-textarea"
						rows="10"
						placeholder="<?php esc_attr_e( 'Paste or type your draft notes, bullets, or paragraphs here…', 'wordish' ); ?>"
						maxlength="<?php echo (int) $input_max_length; ?>"
						<?php echo $has_credentials ? '' : ' disabled'; ?>
					></textarea>
					<p class="description wordish-char-count">
						<span class="wordish-char-current">0</span> / <?php echo (int) $input_max_length; ?>
						<?php esc_html_e( 'characters', 'wordish' ); ?>
						<span class="wordish-char-limits">(
						<?php
							/* translators: %d: minimum character count */
							printf( esc_html__( 'Min: %d chars', 'wordish' ), (int) $input_min_length );
						?>
						)</span>
					</p>
				</div>

				<div class="wordish-tone-section">
					<label for="wordish-tone"><?php esc_html_e( 'Tone', 'wordish' ); ?></label>
					<select id="wordish-tone"
						name="wordish_tone"
						<?php echo $has_credentials ? '' : ' disabled'; ?>
					>
						<?php foreach ( $tones as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $default_tone ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
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
						<?php esc_html_e( 'Copy', 'wordish' ); ?>
					</button>
				</div>
				<div id="wordish-output" class="wordish-output" role="region" aria-live="polite" data-empty="true">
					<span class="wordish-output-placeholder" aria-hidden="true"><?php esc_html_e( 'Output will appear here.', 'wordish' ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>
