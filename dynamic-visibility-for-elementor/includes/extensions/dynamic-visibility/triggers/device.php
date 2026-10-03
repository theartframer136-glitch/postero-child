<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;

class Device extends Base {

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		$element->add_control(
			'dce_visibility_responsive',
			[
				'label' => esc_html__( 'Responsive', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [

					'desktop' => [
						'title' => esc_html__( 'Desktop and Tv', 'dynamic-visibility-for-elementor' ),
						'icon' => 'fa fa-desktop',
					],
					'mobile' => [
						'title' => esc_html__( 'Mobile and Tablet', 'dynamic-visibility-for-elementor' ),
						'icon' => 'fa fa-mobile',
					],
				],
				'description' => esc_html__( 'Not really responsive, remove the element from the code based on the user\'s device. This trigger uses native WP device detection. Note: the device is reported by the visitor and can be faked, so use this for layout only, never to protect sensitive content.', 'dynamic-visibility-for-elementor' ) . ' <a href="https://codex.wordpress.org/Function_Reference/wp_is_mobile" target="_blank">' . esc_html__( 'Read more.', 'dynamic-visibility-for-elementor' ) . '</a>',

			]
		);
		$element->add_control(
			'dce_visibility_browser',
			[
				'label' => esc_html__( 'Browser', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT2,
				'options' => [
					'is_chrome' => 'Google Chrome',
					'is_gecko' => 'FireFox',
					'is_safari' => 'Safari',
					'is_IE' => 'Internet Explorer',
					'is_edge' => 'Microsoft Edge',
					'is_NS4' => 'Netscape',
					'is_opera' => 'Opera',
					'is_lynx' => 'Lynx',
					'is_iphone' => 'iPhone',
				],
				'description' => esc_html__( 'Trigger visibility for a specific browser. Note: the browser is reported by the visitor and can be faked, so use this for layout only, never to protect sensitive content.', 'dynamic-visibility-for-elementor' ),
				'multiple' => true,
				'separator' => 'before',
			]
		);
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
		if ( ! isset( $settings['dce_visibility_device'] ) || ! $settings['dce_visibility_device'] ) {
			$ahidden = false;

			// responsive
			if ( isset( $settings['dce_visibility_responsive'] ) && $settings['dce_visibility_responsive'] ) {
				$triggers['dce_visibility_responsive'] = esc_html__( 'Responsive', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_responsive'] = true;

				if ( wp_is_mobile() ) {
					if ( $settings['dce_visibility_responsive'] == 'mobile' ) {
						$conditions['dce_visibility_responsive'] = esc_html__( 'Responsive: is Mobile', 'dynamic-visibility-for-elementor' );
						$ahidden = true;
					}
				} elseif ( $settings['dce_visibility_responsive'] == 'desktop' ) {
						$conditions['dce_visibility_responsive'] = esc_html__( 'Responsive: is Desktop', 'dynamic-visibility-for-elementor' );
						$ahidden = true;
				}
			}

			// browser
			if ( isset( $settings['dce_visibility_browser'] ) && is_array( $settings['dce_visibility_browser'] ) && ! empty( $settings['dce_visibility_browser'] ) ) {
				$triggers['dce_visibility_browser'] = esc_html__( 'Browser', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_browser'] = true;

				$is_browser = false;
				$allowed_browsers = [ 'is_chrome', 'is_gecko', 'is_safari', 'is_IE', 'is_edge', 'is_NS4', 'is_opera', 'is_lynx', 'is_iphone' ];
				foreach ( $settings['dce_visibility_browser'] as $browser ) {
					if ( in_array( $browser, $allowed_browsers, true ) ) {
						if ( ! empty( $GLOBALS[ $browser ] ) ) {
							$is_browser = true;
						}
					}
				}
				if ( $is_browser ) {
					$conditions['dce_visibility_browser'] = esc_html__( 'Browser', 'dynamic-visibility-for-elementor' );
					$ahidden = true;
				}
			}
		}
	}
}
