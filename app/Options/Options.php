<?php
/**
 * Optiz settings registration.
 *
 * @package Nilambar\Notira
 */

declare(strict_types=1);

namespace Nilambar\Notira\Options;

use Nilambar\Notira\Core\Bootstrap;
use Nilambar\Notira\Utils\Credential_Utils;
use Nilambar\Notira\Utils\Mode_Utils;
use Nilambar\Notira\Utils\Tone_Utils;
use Nilambar\Optiz\Manager;

defined( 'ABSPATH' ) || exit;

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
		add_action( 'init', [ $this, 'register_plugin_options' ], 20 );
	}

	/**
	 * Register plugin options page and fields.
	 *
	 * @since 1.0.0
	 */
	public function register_plugin_options() {
		Manager::register(
			'notira_options',
			[
				'option_key' => 'notira_options',
				'page'       => [
					'title'       => esc_html__( 'Notira Settings', 'notira' ),
					'menu_title'  => esc_html__( 'Settings', 'notira' ),
					'menu_slug'   => 'notira-settings',
					'capability'  => 'manage_options',
					'parent_slug' => Bootstrap::ADMIN_PAGE_SLUG,
				],
				'tabs'       => [
					[
						'id'     => 'notira_settings',
						'label'  => esc_html__( 'Output', 'notira' ),
						'fields' => [
							[
								'id'      => 'default_mode',
								'type'    => 'radio',
								'label'   => esc_html__( 'Default mode', 'notira' ),
								'choices' => [
									Mode_Utils::MODE_EMAIL => __( 'Email', 'notira' ),
									Mode_Utils::MODE_PROOFREAD => __( 'Proofread', 'notira' ),
								],
								'default' => Mode_Utils::DEFAULT_MODE,
								'layout'  => 'horizontal',
							],
							[
								'id'      => 'default_tone',
								'type'    => 'select',
								'label'   => esc_html__( 'Default tone', 'notira' ),
								'choices' => Tone_Utils::get_tone_options(),
								'default' => Tone_Utils::DEFAULT_TONE,
							],
							[
								'id'      => 'preferred_provider',
								'type'    => 'select',
								'label'   => esc_html__( 'AI provider', 'notira' ),
								'choices' => array_merge(
									[ '' => __( '- Select -', 'notira' ) ],
									Credential_Utils::get_ai_provider_options()
								),
								'default' => '',
							],
							[
								'id'          => 'email_greeting',
								'type'        => 'text',
								'label'       => esc_html__( 'Opening line', 'notira' ),
								'placeholder' => __( 'Hi,', 'notira' ),
								'default'     => __( 'Hi,', 'notira' ),
							],
							[
								'id'          => 'email_signoff',
								'type'        => 'text',
								'label'       => esc_html__( 'Closing line', 'notira' ),
								'placeholder' => __( 'Regards,', 'notira' ),
								'default'     => __( 'Regards,', 'notira' ),
							],
						],
					],
				],
			]
		);
	}
}
