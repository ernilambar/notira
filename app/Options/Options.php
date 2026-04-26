<?php
/**
 * Optioner settings registration.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Options;

use Nilambar\Notira\Core\Bootstrap;
use Nilambar\Notira\Core\Option;
use Nilambar\Notira\Utils\Mode_Utils;
use Nilambar\Notira\Utils\Tone_Utils;
use Nilambar\Optioner\Optioner;

/**
 * Options class.
 *
 * @since 1.0.0
 */
class Options {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		add_action( 'optioner_admin_init', [ $this, 'register_plugin_options' ] );
	}

	/**
	 * Register plugin options page and fields.
	 *
	 * @since 1.0.0
	 */
	public function register_plugin_options() {
		$obj = new Optioner();

		$obj->set_page(
			[
				'page_title'     => esc_html__( 'Notira Settings', 'notira' ),
				'menu_title'     => esc_html__( 'Settings', 'notira' ),
				'capability'     => 'manage_options',
				'menu_slug'      => 'notira-settings',
				'option_slug'    => 'notira_options',
				'parent_page'    => Bootstrap::ADMIN_PAGE_SLUG,
				'top_level_menu' => false,
			]
		);

		$obj->add_tab(
			[
				'id'    => 'notira_settings',
				'title' => esc_html__( 'Output', 'notira' ),
			]
		);

		$obj->add_field(
			'notira_settings',
			[
				'id'          => 'default_mode',
				'type'        => 'select',
				'title'       => esc_html__( 'Default mode', 'notira' ),
				'description' => esc_html__( 'Initial mode selected when the generator screen loads.', 'notira' ),
				'choices'     => [
					Mode_Utils::MODE_EMAIL     => __( 'Email', 'notira' ),
					Mode_Utils::MODE_PROOFREAD => __( 'Proofread', 'notira' ),
				],
				'default'     => (string) Option::defaults( 'default_mode' ),
			]
		);

		$obj->add_field(
			'notira_settings',
			[
				'id'          => 'default_tone',
				'type'        => 'select',
				'title'       => esc_html__( 'Default tone', 'notira' ),
				'description' => esc_html__( 'Initial tone selected when the generator screen loads.', 'notira' ),
				'choices'     => Tone_Utils::get_tone_options(),
				'default'     => (string) Option::defaults( 'default_tone' ),
			]
		);

		$obj->add_field(
			'notira_settings',
			[
				'id'          => 'email_greeting',
				'type'        => 'text',
				'title'       => esc_html__( 'Opening line', 'notira' ),
				'description' => esc_html__( 'Used only in Email mode: shown before the generated body (for example: Hi,).', 'notira' ),
				'placeholder' => (string) Option::defaults( 'email_greeting' ),
				'default'     => (string) Option::defaults( 'email_greeting' ),
			]
		);

		$obj->add_field(
			'notira_settings',
			[
				'id'          => 'email_signoff',
				'type'        => 'text',
				'title'       => esc_html__( 'Closing line', 'notira' ),
				'description' => esc_html__( 'Used only in Email mode: shown after the generated body (for example: Regards,).', 'notira' ),
				'placeholder' => (string) Option::defaults( 'email_signoff' ),
				'default'     => (string) Option::defaults( 'email_signoff' ),
			]
		);

		$obj->run();
	}
}
