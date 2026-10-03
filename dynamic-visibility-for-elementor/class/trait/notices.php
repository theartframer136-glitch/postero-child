<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor;

trait Notices {

	/**
	 * Notice
	 *
	 * @param string|false $title
	 * @param string $content
	 * @param string $type
	 * @return void
	 */
	public static function notice( $title, $content, $type = 'info' ) {
		?>
		<div role="alert" style="background-color: <?php
		switch ( $type ) {
			case 'success':
				echo '#F2FDF5';
				$border_color = '#E5F7EA';
				break;
			case 'warning':
				echo '#FFFBEB';
				$border_color = '#FFF4D1';
				break;
			case 'danger':
				echo '#FEF1F4';
				$border_color = '#FCE4EA';
				break;
			default:
				echo '#F0F7FF';
				$border_color = '#E1EFFE';
				break;
		}
		?>; border-inline-start: 5px solid <?php echo $border_color; ?>; padding: 15px; position: relative;">
		<?php if ( $title ) { ?>
			<h5><?php echo wp_kses_post( $title ); ?></h5>
		<?php }
		if ( $content ) { ?>
			<p><?php echo wp_kses_post( $content ); ?></p>
		<?php } ?>
	</div>
	<?php }
} ?>
