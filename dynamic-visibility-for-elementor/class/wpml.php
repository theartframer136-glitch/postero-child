<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace DynamicVisibilityForElementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wpml {
	/**
	 * Extensions Fields
	 *
	 * @var array<string,mixed>
	 */
	protected $extensions_fields = [];

	public function __construct() {
		// Translate Extensions
		add_filter( 'wpml_elementor_widgets_to_translate', [ $this, 'translate_extensions' ], 10, 1 );
	}

	/**
	 * Add Fields for Extensions
	 *
	 * @param array<string,mixed> $fields
	 * @return void
	 */
	public function add_extensions_fields( array $fields ) {
		if ( empty( $fields ) ) {
			return;
		}

		$this->extensions_fields += $fields;
	}

	/**
	 * Get Extensions Fields
	 *
	 * @return array<string,mixed>
	 */
	protected function get_extensions_fields() {
		return $this->extensions_fields;
	}

	/**
	 * Translate Extensions
	 *
	 * @param array<string,mixed> $widgets
	 * @return array<string,mixed>
	 */
	public function translate_extensions( array $widgets ) {
		foreach ( $widgets as &$widget ) {
			if ( ! array_key_exists( 'fields', $widget ) ) {
				$widget['fields'] = [];
			}
			$widget['fields'] += $this->get_extensions_fields();
		}

		return $widgets;
	}
}
