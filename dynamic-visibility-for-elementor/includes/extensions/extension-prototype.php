<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace DynamicVisibilityForElementor\Extensions;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class ExtensionPrototype {
	/**
	 * @var string
	 */
	public $name = 'Extension';

	/**
	 * @var string
	 */
	public static $docs = 'https://www.dynamic.ooo';

	/**
	 * @var bool
	 */
	public $has_controls = false;

	/**
	 * @var array<string>
	 */
	private $depended_scripts = [];

	/**
	 * @var array<string>
	 */
	private $depended_styles = [];

	/**
	 * @var array<string>
	 */
	public static $depended_plugins = [];

	/**
	 * @var bool
	 */
	private $actions_added = false;

	/**
	 * @var array<array{element:string,action:string}>
	 */
	public $common_sections_actions = array(
		array(
			'element' => 'common',
			'action' => '_section_style',
		),
	);

	public function __construct() {

		// Enqueue scripts
		add_action( 'elementor/frontend/after_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Enqueue styles
		add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_styles' ] );

		if ( $this->is_common() ) {
			// Add the advanced section required to display controls
			$this->add_common_sections_actions();
		}
		if ( ! $this->actions_added ) {
			$this->actions_added = true;
			$this->add_actions();
		}
	}

	/**
	 * @return string
	 */
	public function get_docs() {
		return self::$docs;
	}

	/**
	 * @param bool $ret
	 * @return bool|array<int,int|string>
	 */
	public static function get_satisfy_dependencies( $ret = false ) {
		$widgetClass = get_called_class();
		return $widgetClass::satisfy_dependencies( $ret );
	}

	/**
	 * @return array<string>
	 */
	public static function get_plugin_depends() {
		return self::$depended_plugins;
	}

	/**
	 * @param bool $ret
	 * @param array<int|string,string> $deps
	 * @return bool|array<int,int|string>
	 */
	public static function satisfy_dependencies( $ret = false, $deps = array() ) {
		if ( empty( $deps ) ) {
			$deps = self::get_plugin_depends();
		}
		$deps_disabled = array();
		if ( ! empty( $deps ) ) {
			$isActive = true;
			foreach ( $deps as $pkey => $plugin ) {
				if ( ! is_numeric( $pkey ) ) {
					if ( ! Helper::is_plugin_active( $pkey ) ) {
						$isActive = false;
					}
				} elseif ( ! Helper::is_plugin_active( $plugin ) ) {
						$isActive = false;
				}
				if ( ! $isActive ) {
					if ( ! $ret ) {
						return false;
					}
					$deps_disabled[] = $pkey;
				}
			}
		}
		if ( $ret ) {
			return $deps_disabled;
		}
		return true;
	}

	/**
	 * @param string $handler
	 * @return void
	 */
	public function add_script_depends( $handler ) {
		$this->depended_scripts[] = $handler;
	}

	/**
	 * @param string $handler
	 * @return void
	 */
	public function add_style_depends( $handler ) {
		$this->depended_styles[] = $handler;
	}

	/**
	 * @return array<string>
	 */
	public function get_script_depends() {
		return $this->depended_scripts;
	}

	/**
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$this->_enqueue_scripts();
		}
	}

	/**
	 * @return void
	 */
	public function _enqueue_scripts() {
		$scripts = $this->get_script_depends();
		if ( ! empty( $scripts ) ) {
			foreach ( $scripts as $script ) {
				wp_enqueue_script( $script );
			}
		}
	}

	/**
	 * @return array<string>
	 */
	public function get_style_depends() {
		return $this->depended_styles;
	}

	/**
	 * @return string
	 */
	public static function get_description() {
		return '';
	}

	/**
	 * @return void
	 */
	final public function enqueue_styles() {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$this->_enqueue_styles();
		}
	}

	/**
	 * @return void
	 */
	public function _enqueue_styles() {
		$styles = $this->get_style_depends();
		if ( ! empty( $styles ) ) {
			foreach ( $styles as $style ) {
				wp_enqueue_style( $style );
			}
		}
	}
	/**
	 * @return void
	 */
	public function enqueue_all() {
		$this->_enqueue_styles();
		$this->_enqueue_scripts();
	}

	/**
	 * @return string
	 */
	public function get_id() {
		$low_name = strtolower( $this->name );
		$low_name = str_replace( ' ', '_', $low_name );
		return $low_name;
	}

	/**
	 * @param \Elementor\Element_Base $element
	 * @param array<string,mixed> $args
	 * @return void
	 */
	final public function add_common_sections( $element, $args ) {
		$low_name = $this->get_id();
		$section_name = 'dce_section_' . $low_name . '_advanced';

		if ( ! $this->has_controls ) {
			// no need settings
			return;
		}

		// Check if this section exists
		$section_exists = \Elementor\Plugin::instance()->controls_manager->get_control_from_stack( $element->get_unique_name(), $section_name );

		if ( ! is_wp_error( $section_exists ) ) {
			// We can't and should try to add this section to the stack
			return;
		}

		$this->get_control_section( $section_name, $element );
	}

	/**
	 * @param string $section_name
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function get_control_section( $section_name, $element ) {
		$element->start_controls_section(
			$section_name, [
				'tab' => Controls_Manager::TAB_ADVANCED,
				'label' => '<span class="color-dce icon-dce-logo-dce pull-right ml-1"></span> ' . $this->name,
			]
		);
		$element->end_controls_section();
	}

	/**
	 * @return void
	 */
	public function add_common_sections_actions() {
		foreach ( $this->common_sections_actions as $action ) {
			// Activate action for elements
			add_action('elementor/element/' . $action['element'] . '/' . $action['action'] . '/after_section_end', function ( $element, $args ) {
				$this->add_common_sections( $element, $args );
			}, 10, 2);
		}
	}

	/**
	 * @return void
	 */
	protected function add_actions() {}

	/**
	 * @param \Elementor\Element_Base $element
	 * @param mixed $controls
	 * @return void
	 */
	protected function remove_controls( $element, $controls = null ) {
		if ( empty( $controls ) ) {
			return;
		}

		if ( is_array( $controls ) ) {
			$control_id = $controls;

			foreach ( $controls as $control_id ) {
				$element->remove_control( $control_id );
			}
		} else {
			$element->remove_control( $controls );
		}
	}

	/**
	 * @return bool
	 */
	public function is_common() {
		return true;
	}

	
}
