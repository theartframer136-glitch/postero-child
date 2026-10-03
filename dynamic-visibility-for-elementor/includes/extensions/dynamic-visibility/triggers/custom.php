<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;

class Custom extends Base {
	

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		if ( ! Helper::is_plugin_active( 'dynamic-content-for-elementor' ) ) { //  Feature not available in FREE version
			$content = sprintf(
				__(
					'%1$sUnlock 150+ powerful features%2$s including Custom PHP conditions, Dynamic Tags, Widgets, Extensions and more.',
					'dynamic-visibility-for-elementor'
				) . '<br />',
				'<strong>',
				'</strong>'
			);
			$content .= sprintf(
				__(
					'Save 10&#37; with promo code %1$sVISIBILITY%2$s',
					'dynamic-visibility-for-elementor'
				),
				'<strong>',
				'</strong>'
			);

			$upgrade_url = 'https://www.dynamic.ooo/upgrade/visibility-to-premium?utm_source=wp-plugins&utm_campaign=custom-php&utm_medium=editor-notice';

			$content .= sprintf(
				'<br /><br /><a href="%s" target="_blank" style="font-weight: 500;">%s &rarr;</a>',
				esc_url( $upgrade_url ),
				esc_html__( 'Upgrade Now', 'dynamic-visibility-for-elementor' )
			);

			$element->add_control(
				'dce_visibility_custom_hide',
				[
					'type' => Controls_Manager::NOTICE,
					'notice_type' => 'warning',
					'heading' => esc_html__( 'This is a Premium Feature', 'dynamic-visibility-for-elementor' ),
					'content' => $content,
				]
			);
		}
		
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
		
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return boolean
	 */
	protected function check_custom_condition( $settings ) {
		
		return false;
	}
}
