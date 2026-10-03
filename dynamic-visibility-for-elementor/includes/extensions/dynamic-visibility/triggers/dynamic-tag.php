<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;

class DynamicTag extends Base {

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		$element->add_control(
			'dce_visibility_dynamic_tag',
			[
				'label' => esc_html__( 'Dynamic Tag', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
				'ai' => [
					'active' => false,
				],
				'dynamic' => [
					'active' => true,
					'categories' => [
						// only categories that return strings or we'll
						// get Elementor warnings.
						\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
						\Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
						\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
						\Elementor\Modules\DynamicTags\Module::DATETIME_CATEGORY,
						\Elementor\Modules\DynamicTags\Module::COLOR_CATEGORY,
						\Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
					],
				],
				'placeholder' => esc_html__( 'Choose a Dynamic Tag', 'dynamic-visibility-for-elementor' ),
			]
		);
		$element->add_control(
			'dce_visibility_dynamic_tag_status',
			[
				'label' => esc_html__( 'Status', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => Helper::get_compare_options(),
				'default' => 'isset',
				// do not insert a condition: dce_visibility_dynamic_tag
				// not empty. Otherwise if the result of the dynamic tag
				// is empty status will be always null. As of 04/22 this
				// is the behaviour of Elementor conditions.
			]
		);
		$element->add_control(
			'dce_visibility_dynamic_tag_value',
			[
				'type' => Controls_Manager::TEXT,
				'label' => esc_html__( 'Value', 'dynamic-visibility-for-elementor' ),
				'ai' => [
					'active' => false,
				],
				'condition' => [
					'dce_visibility_dynamic_tag_status!' => [ 'not', 'isset' ],
				],
			]
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> &$triggers
	 * @param array<string,mixed> &$conditions
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function check_conditions( $settings, &$triggers, &$conditions, &$required, $element ) {
		if ( ! empty( $settings['__dynamic__'] ) && ! empty( $settings['__dynamic__']['dce_visibility_dynamic_tag'] ) ) {
			$triggers['dce_visibility_dynamic_tag'] = esc_html__( 'Dynamic Tag', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_dynamic_tag'] = true;

			$my_val = $settings['dce_visibility_dynamic_tag'];
			$condition_result = Helper::is_condition_satisfied(
				$my_val,
				$settings['dce_visibility_dynamic_tag_status'],
				$settings['dce_visibility_dynamic_tag_value']
			);

			if ( $condition_result ) {
				$conditions['dce_visibility_dynamic_tag'] = esc_html__( 'Dynamic Tag', 'dynamic-visibility-for-elementor' );
			}
		}
	}
}
