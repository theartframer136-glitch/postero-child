<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility;

use DynamicVisibilityForElementor\Helper;

class Elements {

	/**
	 * @var string
	 */
	const TRIGGER_TYPE_PAGE = 'page';

	/**
	 * @var string
	 */
	const TRIGGER_TYPE_STRUCTURE = 'structure';

	/**
	 * @var array<string,array<string,mixed>>
	 */
	protected $elements = [];

	public function __construct() {
		$this->elements = $this->init_elements();
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function get() {
		return $this->elements;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	protected function init_elements() {
		$section_for_pages = Helper::is_plugin_active( 'elementor-pro' ) ? 'section_custom_css' : 'section_custom_css_pro';

		$elements = [
			'widget' => [ // we use "common" hook for widget,  but we need the widget "type"
				'type' => self::TRIGGER_TYPE_STRUCTURE,
				'hook' => false,
			],
			'common' => [ // Used only for hooks
				'type' => self::TRIGGER_TYPE_STRUCTURE,
				'hook' => '_section_style',
			],
			'section' => [
				'type' => self::TRIGGER_TYPE_STRUCTURE,
				'hook' => 'section_advanced',
			],
			'column' => [
				'type' => self::TRIGGER_TYPE_STRUCTURE,
				'hook' => 'section_advanced',
			],
			'container' => [
				'type' => self::TRIGGER_TYPE_STRUCTURE,
				'hook' => '_section_responsive',
			],
			'wp-post' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'wp-page' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'post' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'page' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'header' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'footer' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'single' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'single-post' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'single-page' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'archive' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			'search-results' => [
				'type' => self::TRIGGER_TYPE_PAGE,
				'hook' => $section_for_pages,
			],
			
		];

		return $elements;
	}
}
