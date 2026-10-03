<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;

class MyFastApp extends Base {

	/**
	 * @return boolean
	 */
	public function is_available() {
		return Helper::is_plugin_active( 'dynamic-content-for-elementor' ) && Helper::is_plugin_active( 'myfastapp' );
	}

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		$element->add_control(
			'dce_visibility_myfastapp',
			[
				'label' => esc_html__( 'The visitor is', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'all' => esc_html__( 'on the site or in the app', 'dynamic-visibility-for-elementor' ),
					'site' => esc_html__( 'on the site', 'dynamic-visibility-for-elementor' ),
					'app' => esc_html__( 'in the app', 'dynamic-visibility-for-elementor' ),
				],
				'default' => 'all',
				'description' => esc_html__( 'Note: the app context is reported by the visitor and can be faked, so use it as a hint only, never to protect sensitive content.', 'dynamic-visibility-for-elementor' ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> &$triggers
	 * @param array<string,mixed> &$conditions
	 * @param array<string,mixed> &$required
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function check_conditions( $settings, &$triggers, &$conditions, &$required, $element ) {
		if ( isset( $settings['dce_visibility_myfastapp'] ) &&
			'all' !== $settings['dce_visibility_myfastapp'] ) {

			$triggers['dce_visibility_myfastapp'] = 'My FastAPP';
			$required['dce_visibility_myfastapp'] = true;

			$headers = getallheaders();
			$is_on_myfastapp = isset( $headers['X-Appid'] ) || isset( $_COOKIE['myfastapp-cli'] );

			if ( 'app' === $settings['dce_visibility_myfastapp'] && $is_on_myfastapp
				|| 'site' === $settings['dce_visibility_myfastapp'] && ! $is_on_myfastapp ) {
				$conditions['dce_visibility_myfastapp'] = 'My FastAPP';
			}
		}
	}
}
