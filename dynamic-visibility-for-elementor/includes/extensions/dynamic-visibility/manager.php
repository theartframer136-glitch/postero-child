<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility;

use DynamicVisibilityForElementor\Helper;
use DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;
use DynamicVisibilityForElementor\Extensions\ExtensionPrototype;

class Manager extends ExtensionPrototype {
	/**
	 * @var string
	 */
	public $name = 'Dynamic Visibility';

	/**
	 * @var boolean
	 */
	public $has_controls = true;

	/**
	 * @var string
	 */
	const CUSTOM_PHP_CONTROL_NAME = 'dce_visibility_custom_condition_php';

	/**
	 * @var array<string,array<string,mixed>>
	 */
	protected $triggers = [];

	/**
	 * @var Triggers\Manager
	 */
	protected $triggers_manager;

	/**
	 * @var Elements
	 */
	protected $elements;

	/**
	 * @var Sections
	 */
	protected $sections;

	/**
	 * Hidden elements data for Frontend Inspector API
	 *
	 * @var array<string,array{mode:string,operator:string,conditions:array<string,string>,events?:array{click:string,load:bool}}>
	 */
	private static $hidden_elements = [];

	public function __construct() {
		$this->elements = new Elements();
		$this->triggers_manager = new Triggers\Manager( $this->elements );
		$this->triggers = $this->triggers_manager->get_triggers();
		$this->sections = new Sections( $this->elements, $this->triggers_manager );

		add_filter( 'elementor/widget/render_content', [ $this, 'render_template' ], 9, 2 );
		add_filter( 'elementor/section/render_content', [ $this, 'render_template' ], 9, 2 );
		add_filter( 'elementor/column/render_content', [ $this, 'render_template' ], 9, 2 );
		add_filter( 'elementor/container/render_content', [ $this, 'render_template' ], 9, 2 );

		// Element Caching Compatibility
		add_filter( 'elementor/element/is_dynamic_content', [ $this, 'ensure_element_caching_compatibility' ], 10, 2 );

		// Frontend Inspector API
		add_filter( 'dife/frontend-inspector/hidden-elements', [ __CLASS__, 'get_hidden_elements' ] );

		parent::__construct();
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'dynamic-visibility';
	}

	/**
	 * @return string
	 */
	public function get_id() {
		return 'visibility';
	}

	/**
	 * @return boolean
	 */
	public function is_common() {
		return false;
	}

	/**
	 * @return array<string>
	 */
	public function get_style_depends() {
		return [ 'dce-dynamic-visibility' ];
	}

	/**
	 * @return void
	 */
	public function run_once() {
		

		\DynamicVisibilityForElementor\Plugin::instance()->wpml->add_extensions_fields(
			[
				'dce_visibility_fallback_text' =>
				[
					'field' => 'dce_visibility_fallback_text',
					'type' => 'Fallback Text',
					'editor_type' => 'AREA',
				],
			]
		);
	}

	/**
	 * @return void
	 */
	protected function add_actions() {
		// this is for end users, so they can prevent visibility from running on certain pages:
		$should_run = apply_filters( 'dynamicooo/visibility/should-run', true );
		if ( ! $should_run ) {
			return;
		}

		add_filter( 'elementor/widget/render_content', [ $this, 'render_template' ], 9, 2 );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );

		// Document
		add_filter( 'elementor/frontend/the_content', [ $this, 'document_filter' ] );

		// Widget
		add_action( 'elementor/frontend/widget/before_render', [ $this, 'start_element' ], 10, 1 );
		add_action( 'elementor/frontend/widget/after_render', [ $this, 'end_element' ], 10, 1 );

		// Container
		add_action( 'elementor/frontend/container/before_render', [ $this, 'start_element' ], 10, 1 );
		add_action( 'elementor/frontend/container/after_render', [ $this, 'end_element' ], 10, 1 );

		// Section
		add_action( 'elementor/frontend/section/before_render', [ $this, 'start_element' ], 10, 1 );
		add_action( 'elementor/frontend/section/after_render', [ $this, 'end_element' ], 10, 1 );

		// Column
		add_action( 'elementor/frontend/column/before_render', [ $this, 'start_element' ], 10, 1 );
		add_action( 'elementor/frontend/column/after_render', [ $this, 'end_element' ], 10, 1 );

		// Section
		add_action( 'elementor/frontend/section/before_render', function ( $element ) {
			$columns = $element->get_children();
			if ( ! empty( $columns ) ) {
				$cols_visible = count( $columns );
				$cols_hidden = 0;
				foreach ( $columns as $acol ) {
					if ( $this->is_hidden( $acol ) ) {
						$fallback = $acol->get_settings_for_display( 'dce_visibility_fallback' );
						if ( empty( $fallback ) ) {
							$cols_visible--;
							$cols_hidden++;
						}
					}
				}
				if ( $cols_hidden ) {
					if ( $cols_visible ) {
						$_column_size = round( 100 / $cols_visible );
						foreach ( $columns as $acol ) {
							$acol->set_settings( '_column_size', $_column_size );
						}
					} else {
						$element->add_render_attribute( '_wrapper', 'class', 'dce-visibility-element-hidden' );
						$element->add_render_attribute( '_wrapper', 'class', 'dce-visibility-original-content' );
					}
				}
			}
		}, 10, 1);
	}

	/**
	 * @param string $content
	 * @return string
	 */
	public function document_filter( $content ) {
		$document = \Elementor\Plugin::instance()->documents->get_current();
		$settings = $document->get_settings_for_display();
		if ( ( $settings['enabled_visibility'] ?? '' ) === 'yes' ) {
			$hidden = $this->is_hidden( $document );
			if ( $hidden ) {
				if ( $this->should_remove_from_dom( $settings ) ) {
					$content = '<!-- dce invisible -->';
				} else {
					$content = preg_replace( '/class=(["\'])/', 'class=\1dce-visibility-element-hidden ', $content, 1 ) ?? '';
				}
				$fallback = self::get_fallback_content( $settings );
				if ( $fallback !== false ) {
					ob_start();
					\Elementor\Utils::print_html_attributes( $document->get_container_attributes() );
					$content .= '<div ' . ob_get_clean() . '>';
					$content .= $fallback;
					$content .= '</div>';
				}
				return $content;
			}
		}
		return $content;
	}

	/**
	 * Should Remove from DOM
	 *
	 * @param array<mixed> $settings
	 * @return boolean
	 */
	public function should_remove_from_dom( $settings ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple feature flag check
		if ( ( isset( $_GET['dce-nav'] ) || isset( $_GET['frontend-inspector'] ) ) && current_user_can( 'manage_options' ) ) {
			return false;
		}
		return empty( $settings['dce_visibility_dom'] );
	}

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function start_element( $element ) {
		$settings = $element->get_settings_for_display();
		if ( ! empty( $settings['enabled_visibility'] ) ) {
			$hidden = $this->is_hidden( $element );
			if ( $hidden ) {
				if ( $this->should_remove_from_dom( $settings ) ) {
					ob_start();
				} else {
					$element->add_render_attribute( '_wrapper', 'class', 'dce-visibility-element-hidden' );
					$element->add_render_attribute( '_wrapper', 'class', 'dce-visibility-original-content' );
				}
			}
			if ( in_array( 'events', $settings['dce_visibility_triggers'] ?? [], true ) ) {
				$element->add_render_attribute( '_wrapper', 'class', 'dce-visibility-event' );
				$element->add_script_depends( 'dce-visibility' );
			}
			$this->triggers_manager->triggers['context']->set_element_view_counters( $element, $hidden );
		}
	}

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function end_element( $element ) {
		$settings = $element->get_settings_for_display();
		if ( ! empty( $settings['enabled_visibility'] ) ) {
			if ( $this->is_hidden( $element ) ) {
				if ( $this->should_remove_from_dom( $settings ) ) {
					$content = ob_get_clean();
					// Visibility can cause in some cases the content of the
					// entire page to be empty, resulting in an editor error,
					// avoid this by printing the following:
					echo "<!-- dce invisible element {$element->get_id()} -->";
					$content = $content ? $content : '';
					// Elementor Improved CSS Loading will put CSS inline on
					// the first widget of a certain type. If it's invisibile
					// because of visibility, the style will be lost for any
					// other widget of the same type on the page.
					//
					// NOTE: There are clients who wants to use custom style
					// tags that follow the visibility rules. The have been
					// told to add the data-visibility-ok attribute to the
					// style tag. So future changes should take that into
					// account.
					preg_match_all( '$<style( id="[^"]*"|)>.*?</style>$s', $content, $matches );
					foreach ( $matches[0] as $m ) {
						echo $m;
					}
					preg_match_all( '$<link\s+rel=.?stylesheet.*?>$s', $content, $slinks );
					foreach ( $slinks[0] as $l ) {
						echo $l;
					}
				}
				$fallback = self::get_fallback( $settings, $element );
				if ( ! empty( $fallback ) ) {
					$fallback = str_replace( 'dce-visibility-element-hidden', '', $fallback );
					$fallback = str_replace( 'dce-visibility-original-content', 'dce-visibility-fallback-content', $fallback );
					echo $fallback;
				}
			}
		}
	}

	/**
	 * @return void
	 */
	public function enqueue_editor_scripts() {
		// JS for Dynamic Visibility on editor mode
		wp_register_script(
			'dce-script-editor-dynamic-visibility',
			plugins_url( '/assets/js/editor-dynamic-visibility.js', DVE__FILE__ ),
			[],
			DVE_VERSION,
			true
		);
		wp_enqueue_script( 'dce-script-editor-dynamic-visibility' );
	}

	/**
	 * Ensure that elements with dynamic visibility are not cached by Elementor
	 *
	 * @param bool $is_dynamic_content
	 * @param array<string,mixed> $raw_data
	 * @return bool
	 */
	public function ensure_element_caching_compatibility( $is_dynamic_content, $raw_data ) {
		if ( ! empty( $raw_data['settings']['enabled_visibility'] ) ) {
			return true;
		}
		return $is_dynamic_content;
	}

	/**
	 * Check if an element should be hidden
	 *
	 * @param \Elementor\Element_Base $element
	 * @return boolean
	 */
	public function is_hidden( $element ) {
		$settings = $element->get_settings_for_display();
		if ( empty( $settings['enabled_visibility'] ) ) {
			return false;
		}

		if ( ! empty( $settings['dce_visibility_hidden'] ) ) {
			return true;
		}

		$display_mode_is_show = ( $settings['dce_visibility_selected'] ?? '' ) == 'yes';

		if ( ! empty( $settings['dce_visibility_triggers'] ) ) {
			// If "events" is the only trigger specified, we determine the element's initial hidden state
			// solely based on whether a click selector (dce_visibility_click) is provided.
			// This means that if the only trigger is "events" and the click selector is not empty,
			// the element will be initially hidden (waiting for JavaScript to reveal it upon the event).
			if ( count( $settings['dce_visibility_triggers'] ) === 1 && in_array( 'events', $settings['dce_visibility_triggers'], true ) ) {
				$hidden = $display_mode_is_show && ! empty( $settings['dce_visibility_click'] );
				// Frontend Inspector API for events-only trigger
				if ( $hidden ) {
					self::$hidden_elements[ $element->get_id() ] = [
						'mode'       => 'show',
						'operator'   => 'or',
						'conditions' => [ 'events' => __( 'Events (client-side)', 'dynamic-visibility-for-elementor' ) ],
						'events'     => [
							'click'    => $settings['dce_visibility_click'] ?? '',
							'load'     => ! empty( $settings['dce_visibility_load'] ),
						],
					];
				}
				return $hidden;
			}
			// If there are other triggers in addition to "events", we remove "events" from the array.
			// This is because the "events" trigger is managed entirely by client-side JavaScript,
			// and we do not want it to affect the server-side condition evaluation.
			$settings['dce_visibility_triggers'] = array_filter(
				$settings['dce_visibility_triggers'],
				function ( $trigger ) {
					return $trigger !== 'events';
				}
			);
		}

		$check_result = $this->triggers_manager->check_conditions( $settings, $element );

		$conditions = $check_result['conditions'];
		$triggers = $check_result['triggers'];
		$required = $check_result['required'];

		if ( isset( $settings['dce_visibility_logical_connective'] ) && $settings['dce_visibility_logical_connective'] === 'and' ) {
			$triggered = ! empty( $required ) && count( $conditions ) === count( $required );
		} else {
			$triggered = ! empty( $conditions );
		}

		$hidden = $display_mode_is_show ? ! $triggered : $triggered;

		// Frontend Inspector API
		if ( $hidden ) {
			$logical_connective = $settings['dce_visibility_logical_connective'] ?? 'or';
			self::$hidden_elements[ $element->get_id() ] = [
				'mode'       => $display_mode_is_show ? 'show' : 'hide',
				'operator'   => $logical_connective,
				'conditions' => $triggers,
			];
		}

		return $hidden;
	}

	/**
	 * @param string $content
	 * @param \Elementor\Widget_Base $widget
	 * @return string
	 */
	public function render_template( $content, $widget ) {
		$this->enqueue_all();

		return $content;
	}

	/**
	 * @param array<mixed> $settings
	 * @return string|false
	 */
	public static function get_fallback_content( $settings ) {
		if ( ! empty( $settings['dce_visibility_fallback'] ) ) {
			if ( isset( $settings['dce_visibility_fallback_type'] ) && $settings['dce_visibility_fallback_type'] == 'template' ) {
				
			} else {
				return $settings['dce_visibility_fallback_text'];
			}
		} else {
			return false;
		}
	}

	/**
	 * Get Fallback
	 *
	 * @param array<string,mixed> $settings
	 * @param \Elementor\Element_Base $element
	 * @return string|false
	 */
	public static function get_fallback( $settings, $element ) {
		$fallback_content = self::get_fallback_content( $settings );
		if ( ! $fallback_content ) {
			return false;
		}

		if ( $element->get_type() == 'section' &&
				( ! isset( $settings['dce_visibility_fallback_section'] ) || $settings['dce_visibility_fallback_section'] == 'yes' ) ) {
			$fallback_content = '
						<div class="elementor-element elementor-column elementor-col-100 elementor-top-column" data-element_type="column">
							<div class="elementor-column-wrap elementor-element-populated">
								<div class="elementor-widget-wrap">
									<div class="elementor-element elementor-widget">
										<div class="elementor-widget-container dce-visibility-fallback">'
								. $fallback_content .
								'</div>
									</div>
								</div>
							</div>
						</div>';
		}

		ob_start();
		$element->before_render();
		echo $fallback_content;
		$element->after_render();
		$fallback_content = ob_get_clean();

		return $fallback_content;
	}

	/**
	 * Get hidden elements for Frontend Inspector API
	 *
	 * @param array<string,array<string,mixed>> $hidden
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_hidden_elements( $hidden ) {
		return array_merge( $hidden, self::$hidden_elements );
	}
}
