<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

abstract class Base {

	/**
	 * @var array<string,string>
	 */
	protected $triggers = [];

	/**
	 * @var array<string,string>
	 */
	protected $conditions = [];

	/**
	 * Check if the trigger should be available
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	abstract public function register_controls( $element );

	/**
	 * Get message explaining what is required to make this trigger available
	 *
	 * @return string|null
	 */
	public function get_availability_requirements_message() {
		return null;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> &$triggers
	 * @param array<string,mixed> &$conditions
	 * @param array<string,mixed> &$required
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	abstract public function check_conditions( $settings, &$triggers, &$conditions, &$required, $element );
}
