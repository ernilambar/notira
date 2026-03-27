<?php
/**
 * Admin page template.
 *
 * @package Nilambar\Notira
 * @since 1.0.0
 */

declare(strict_types=1);

use Nilambar\Notira\API\REST_API;

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap notira-wrap">
	<h1><?php esc_html_e( 'Notira', 'notira' ); ?></h1>

	<div class="notira-content">
		<div
			id="notira-root"
			data-min-length="<?php echo (int) REST_API::INPUT_MIN_LENGTH; ?>"
			data-max-length="<?php echo (int) REST_API::INPUT_MAX_LENGTH; ?>"
		></div>
	</div>
</div>
