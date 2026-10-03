<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;
use DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;
use DynamicVisibilityForElementor\Extensions\ExtensionPrototype;

class Sections {

	/**
	 * @var string
	 */
	const TAB_NAME = 'dce_visibility';

	/**
	 * @var Elements
	 */
	protected $elements;

	/**
	 * @var Triggers\Manager
	 */
	protected $triggers_manager;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	protected $triggers;

	/**
	 * @param Elements $elements
	 * @param Triggers\Manager $triggers_manager
	 */
	public function __construct( $elements, $triggers_manager ) {
		$this->elements = $elements;
		$this->triggers_manager = $triggers_manager;
		$this->triggers = $this->triggers_manager->get_triggers();

		$this->register_tab();
		$this->register_controls();
	}

	/**
	 * @return void
	 */
	protected function register_tab() {
		Controls_Manager::add_tab(
			static::TAB_NAME,
			esc_html__( 'Visibility', 'dynamic-visibility-for-elementor' )
		);
	}

	/**
	 * @return array<string,string>
	 */
	public function get_tabs() {
		$tabs = [];
		foreach ( $this->triggers as $key => $data ) {
			$tabs[ $key ] = $data['label'];
		}
		return $tabs;
	}

	/**
	 * @return void
	 */
	protected function register_controls() {
		foreach ( $this->elements->get() as $element => $data ) {
			if ( ! isset( $data['hook'] ) || $data['hook'] === false ) {
				continue;
			}
			$this->register_element_controls( $element, $data['hook'] );
		}
	}

	/**
	 * Register controls for a single element type
	 *
	 * @param string $element
	 * @param string $section
	 * @return void
	 */
	protected function register_element_controls( $element, $section ) {
		add_action(
			"elementor/element/{$element}/{$section}/after_section_end",
			function ( $element_instance ) {
				$this->add_visibility_sections( $element_instance );
			}
		);
	}

	/**
	 * Add all visibility sections to an element
	 *
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	protected function add_visibility_sections( $element ) {
		$this->add_base_section( $element );
		$this->add_trigger_sections( $element );
		$this->add_fallback_section( $element );
	}

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	protected function add_base_section( $element ) {
		$element->start_controls_section(
			'dce_section_visibility_base',
			[
				'tab' => static::TAB_NAME,
				'label' => '<span class="color-dce icon-dce-logo-dce pull-right ml-1"></span> ' . esc_html__( 'Visibility', 'dynamic-visibility-for-elementor' ),
			]
		);

		$this->triggers_manager->register_base_controls( $element );

		$element->end_controls_section();
	}

	/**
	 * Add sections for each trigger
	 *
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	protected function add_trigger_sections( $element ) {
		$element_type = $element->get_type();

		foreach ( $this->triggers_manager->get_triggers() as $trigger_id => $trigger_data ) {
			if ( empty( $this->triggers_manager->triggers[ $trigger_id ] ) ) {
				continue;
			}

			$condition = $this->triggers_manager->triggers[ $trigger_id ];

			// Skip events trigger for page elements
			if ( 'events' === $trigger_id &&
				$this->elements::TRIGGER_TYPE_PAGE === $this->elements->get()[ $element_type ]['type'] ) {
				continue;
			}

			// Skip unavailable triggers without requirements message
			if ( ! $condition->is_available() ) {
				$message = $condition->get_availability_requirements_message();
				if ( ! $message ) {
					continue;
				}
			}

			$this->add_trigger_section( $element, $trigger_id, $trigger_data, $condition );
		}
	}

	/**
	 * Add a single trigger section
	 *
	 * @param \Elementor\Element_Base $element
	 * @param string $trigger_id
	 * @param array<string,mixed> $trigger_data
	 * @param Triggers\Base $condition
	 * @return void
	 */
	protected function add_trigger_section( $element, $trigger_id, $trigger_data, $condition ) {
		$element->start_controls_section(
			'dce_section_visibility_' . $trigger_id,
			[
				'tab' => static::TAB_NAME,
				'label' => $trigger_data['label'],
				'condition' => $this->get_trigger_condition( $trigger_id ),
			]
		);

		if ( ! $condition->is_available() ) {
			$element->add_control(
				'dce_visibility_' . $trigger_id . '_notice',
				[
					'type' => Controls_Manager::RAW_HTML,
					'raw' => $condition->get_availability_requirements_message(),
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				]
			);
		} else {
			$condition->register_controls( $element );
		}

		$element->end_controls_section();
	}

	/**
	 * Add fallback section
	 *
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	protected function add_fallback_section( $element ) {
		$element->start_controls_section(
			'dce_section_visibility_fallback',
			[
				'tab' => static::TAB_NAME,
				'label' => esc_html__( 'Fallback', 'dynamic-visibility-for-elementor' ),
				'condition' => [
					'enabled_visibility' => 'yes',
				],
			]
		);

		$this->triggers_manager->register_fallback_controls( $element );

		$element->end_controls_section();
	}

	/**
	 * Get conditions for a trigger
	 * All Triggers have the same condition
	 *
	 * @param string $key
	 * @return array<string,mixed>
	 */
	protected function get_trigger_condition( $key ) {
		return [
			'enabled_visibility' => 'yes',
			'dce_visibility_hidden' => '',
			'dce_visibility_triggers' => $key,
		];
	}
}
