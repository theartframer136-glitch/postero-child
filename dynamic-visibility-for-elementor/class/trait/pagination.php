<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor;

use DynamicVisibilityForElementor\Plugin;

trait Pagination {

	

	/**
	 * @param \DynamicVisibilityForElementor\Widgets\WidgetPrototype $element
	 * @param array<string> $widgets_with_pagination
	 * @return boolean
	 *
	 * SPDX-FileCopyrightText: Elementor
	 * SPDX-License-Identifier: GPL-3.0-or-later
	 */
	protected static function is_valid_widget_for_pagination( $element, $widgets_with_pagination ) {
		return isset( $element['widgetType'] ) && in_array( $element['widgetType'], $widgets_with_pagination, true );
	}
}
