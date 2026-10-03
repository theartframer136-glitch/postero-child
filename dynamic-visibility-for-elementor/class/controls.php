<?php

namespace DynamicVisibilityForElementor;

class Controls {

	/**
	 * @var array<string,string>
	 */
	public $controls = [];

	/**
	 * @var array<string,string>
	 */
	public $group_controls = [];

	/**
	 * @var string
	 */
	public static $namespace = '\\DynamicVisibilityForElementor\\Controls\\';

	public function __construct() {
		$this->controls = $this->get_controls();
	}


	public function get_controls() {
		$controls['ooo_query'] = 'Control_OOO_Query';

		return $controls;
	}

	/**
	 * @return void
	 */
	public function on_controls_registered() {
		$this->register_controls();
	}

	/**
	 * @return void
	 */
	public function register_controls() {
		$controls_manager = \Elementor\Plugin::$instance->controls_manager;

		foreach ( $this->controls as $key => $name ) {
			$class = self::$namespace . $name;

			/**
			 * @var \Elementor\Base_Control $new_class
			 */
			$new_class = new $class();
			$controls_manager->register( $new_class );
		}
	}
}
