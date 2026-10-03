<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;
class Context extends Base {
	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		$element->add_control(
			'dce_visibility_parameter',
			[
				'label' => esc_html__( 'Parameter', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__( 'Type here the name of the parameter passed in GET, COOKIE or POST method', 'dynamic-visibility-for-elementor' ),
				'ai' => [
					'active' => false,
				],
			]
		);
		$element->add_control(
			'dce_visibility_parameter_method',
			[
				'label' => esc_html__( 'Parameter Method', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'GET' => 'GET',
					'POST' => 'POST',
					'REQUEST' => 'REQUEST',
					'COOKIE' => 'COOKIE',
					'SERVER' => 'SERVER',
				],
				'default' => 'REQUEST',
				'description' => esc_html__( 'Warning: with the SERVER method, HTTP_* keys come from request headers sent by the visitor and can be faked. Use this as a non-security hint only, never to protect sensitive content.', 'dynamic-visibility-for-elementor' ),
				'condition' => [
					'dce_visibility_parameter!' => '',
				],
			]
		);
		$element->add_control(
		'dce_visibility_parameter_status',
		[
			'label' => esc_html__( 'Parameter Status', 'dynamic-visibility-for-elementor' ),
			'type' => Controls_Manager::SELECT,
			'options' => Helper::get_compare_options(),
			'default' => 'isset',
			'condition' => [
				'dce_visibility_parameter!' => '',
			],
		]
		);
		$element->add_control(
			'dce_visibility_parameter_value',
			[
				'label' => esc_html__( 'Parameter Value', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__( 'The specific value of the parameter', 'dynamic-visibility-for-elementor' ),
				'ai' => [
					'active' => false,
				],
				'condition' => [
					'dce_visibility_parameter!' => '',
					'dce_visibility_parameter_status!' => [ 'not', 'isset' ],
				],
			]
		);

		$element->add_control(
		'dce_visibility_conditional_tags_site',
		[
			'label' => esc_html__( 'Site', 'dynamic-visibility-for-elementor' ),
			'type' => Controls_Manager::SELECT2,
			'options' => static::get_whitelist_site_functions(),
			'multiple' => true,
			'separator' => 'before',
		]
		);

		$element->add_control(
			'dce_visibility_max_day',
			[
				'label' => esc_html__( 'Max per Day', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_max_total',
			[
				'label' => esc_html__( 'Max Total', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'separator' => 'before',
			]
		);

		$select_lang = [];
		// WPML
		global $sitepress;
		if ( ! empty( $sitepress ) ) {
			$langs = $sitepress->get_active_languages();
			if ( ! empty( $langs ) ) {
				foreach ( $langs as $lkey => $lvalue ) {
					$select_lang[ $lkey ] = $lvalue['native_name'];
				}
			}
		}
		// POLYLANG
		if ( Helper::is_plugin_active( 'polylang' ) && function_exists( 'pll_languages_list' ) ) {
			$translations = pll_languages_list();
			$translations_name = pll_languages_list( [ 'fields' => 'name' ] );
			if ( ! empty( $translations ) ) {
				foreach ( $translations as $tkey => $tvalue ) {
					$select_lang[ $tvalue ] = $translations_name[ $tkey ];
				}
			}
		}
		// TRANSLATEPRESS
		if ( Helper::is_plugin_active( 'translatepress-multilingual' ) ) {
			$settings = get_option( 'trp_settings' );
			if ( $settings && is_array( $settings ) && isset( $settings['publish-languages'] ) ) {
				$languages = $settings['publish-languages'];
				if ( class_exists( '\TRP_Translate_Press' ) ) {
					$trp = \TRP_Translate_Press::get_trp_instance();
					$trp_languages = $trp->get_component( 'languages' );
					$published_languages = $trp_languages->get_language_names( $languages, 'english_name' );
					$select_lang = $published_languages;
				}
			}
		}
		// WEGLOT
		if ( Helper::is_plugin_active( 'weglot' ) && function_exists( 'weglot_get_destination_languages' ) ) {
			$select_lang_array = array_column( weglot_get_destination_languages(), 'language_to' );
			// Add current language
			$select_lang_array[] = weglot_get_current_language();
			if ( ! empty( $select_lang_array ) ) {
				foreach ( $select_lang_array as $key => $value ) {
					$select_lang[ $value ] = $value;
				}
			}
		}
		if ( ! empty( $select_lang ) ) {
			$element->add_control(
				'dce_visibility_lang',
				[
					'label' => esc_html__( 'Language', 'dynamic-visibility-for-elementor' ),
					'type' => Controls_Manager::SELECT2,
					'options' => $select_lang,
					'multiple' => true,
					'separator' => 'before',
				]
			);
		}
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
		if ( isset( $settings['dce_visibility_parameter'] ) && $settings['dce_visibility_parameter'] ) {
			$triggers['dce_visibility_parameter'] = esc_html__( 'Parameter', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_parameter'] = true;

			$my_val = null;
			switch ( $settings['dce_visibility_parameter_method'] ) {
				case 'COOKIE':
					if ( isset( $_COOKIE[ $settings['dce_visibility_parameter'] ] ) ) {
						$my_val = sanitize_text_field( $_COOKIE[ $settings['dce_visibility_parameter'] ] );
					}
					break;
				case 'SERVER':
					if ( isset( $_SERVER[ $settings['dce_visibility_parameter'] ] ) ) {
						$my_val = sanitize_text_field( $_SERVER[ $settings['dce_visibility_parameter'] ] );
					}
					break;
				case 'GET':
				case 'POST':
				case 'REQUEST':
				default:
					if ( isset( $_REQUEST[ $settings['dce_visibility_parameter'] ] ) ) {
						$my_val = sanitize_text_field( $_REQUEST[ $settings['dce_visibility_parameter'] ] );
					}
			}
			$condition_result = Helper::is_condition_satisfied( $my_val, $settings['dce_visibility_parameter_status'], $settings['dce_visibility_parameter_value'] );
			if ( $condition_result ) {
				$conditions['dce_visibility_parameter'] = esc_html__( 'Parameter', 'dynamic-visibility-for-elementor' );
			}
		}

		// LANGUAGES
		if ( ! empty( $settings['dce_visibility_lang'] ) ) {
			$triggers['dce_visibility_lang'] = esc_html__( 'Language', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_lang'] = true;

			$current_language = get_locale();
			// WPML
			global $sitepress;
			if ( ! empty( $sitepress ) ) {
				$current_language = $sitepress->get_current_language(); // return lang code
			}
			// POLYLANG
			if ( Helper::is_plugin_active( 'polylang' ) && function_exists( 'pll_languages_list' ) ) {
				$current_language = pll_current_language();
			}
			// TRANSLATEPRESS
			global $TRP_LANGUAGE;
			if ( ! empty( $TRP_LANGUAGE ) ) {
				$current_language = $TRP_LANGUAGE; // return lang code
			}
			// WEGLOT
			if ( Helper::is_plugin_active( 'weglot' ) ) {
				$current_language = weglot_get_current_language();
			}
			if ( in_array( $current_language, $settings['dce_visibility_lang'] ) ) {
				$conditions['dce_visibility_lang'] = esc_html__( 'Language', 'dynamic-visibility-for-elementor' );
			}
		}

		if ( ! empty( $settings['dce_visibility_max_day'] ) ) {
			$triggers['dce_visibility_max_day'] = esc_html__( 'Max Day', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_max_day'] = true;
			$dce_visibility_max = get_option( 'dce_visibility_max', [] );
			$today = date( 'Ymd' );
			if ( isset( $dce_visibility_max[ $element->get_id() ] ) && isset( $dce_visibility_max[ $element->get_id() ]['day'] ) && isset( $dce_visibility_max[ $element->get_id() ]['day'][ $today ] ) ) {
				if ( $settings['dce_visibility_max_day'] >= $dce_visibility_max[ $element->get_id() ]['day'][ $today ] ) {
					$conditions['dce_visibility_max_day'] = esc_html__( 'Max per Day', 'dynamic-visibility-for-elementor' );
				}
			} else {
				$conditions['dce_visibility_max_day'] = esc_html__( 'Max per Day', 'dynamic-visibility-for-elementor' );
			}
		}
		if ( ! empty( $settings['dce_visibility_max_total'] ) ) {
			$triggers['dce_visibility_max_total'] = esc_html__( 'Max Total', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_max_total'] = true;
			$dce_visibility_max = get_option( 'dce_visibility_max', [] );
			if ( isset( $dce_visibility_max[ $element->get_id() ] ) && isset( $dce_visibility_max[ $element->get_id() ]['total'] ) ) {
				if ( $settings['dce_visibility_max_total'] >= $dce_visibility_max[ $element->get_id() ]['total'] ) {
					$conditions['dce_visibility_max_total'] = esc_html__( 'Max Total', 'dynamic-visibility-for-elementor' );
				}
			} else {
				$conditions['dce_visibility_max_total'] = esc_html__( 'Max Total', 'dynamic-visibility-for-elementor' );
			}
		}

		if ( ! empty( $settings['dce_visibility_conditional_tags_site'] ) && is_array( $settings['dce_visibility_conditional_tags_site'] ) ) {
			$required['dce_visibility_conditional_tags_site'] = true;
			$callable_functions = array_filter( $settings['dce_visibility_conditional_tags_site'], function ( $function ) {
				return in_array( $function, array_keys( self::get_whitelist_site_functions() ), true ) && is_callable( $function );
			});

			foreach ( $callable_functions as $function ) {
				if ( call_user_func( $function ) ) {
					$conditions['dce_visibility_conditional_tags_site'] = esc_html__( 'Conditional tags Site', 'dynamic-visibility-for-elementor' );
					break;
				}
			}
		}
	}

	/**
	 * @return array<string,string>
	 */
	public static function get_whitelist_site_functions() {
		return [
			'is_dynamic_sidebar' => esc_html__( 'Dynamic sidebar', 'dynamic-visibility-for-elementor' ),
			'is_active_sidebar' => esc_html__( 'Active sidebar', 'dynamic-visibility-for-elementor' ),
			'is_rtl' => esc_html__( 'RTL', 'dynamic-visibility-for-elementor' ),
			'is_multisite' => esc_html__( 'Multisite', 'dynamic-visibility-for-elementor' ),
			'is_main_site' => esc_html__( 'Main site', 'dynamic-visibility-for-elementor' ),
			'is_child_theme' => esc_html__( 'Child theme', 'dynamic-visibility-for-elementor' ),
			'is_customize_preview' => esc_html__( 'Customize preview', 'dynamic-visibility-for-elementor' ),
			'is_multi_author' => esc_html__( 'Multi author', 'dynamic-visibility-for-elementor' ),
			'is feed' => esc_html__( 'Feed', 'dynamic-visibility-for-elementor' ),
			'is_trackback' => esc_html__( 'Trackback', 'dynamic-visibility-for-elementor' ),
		];
	}

	/**
	 * @param \Elementor\Element_Base $element
	 * @param boolean $hidden
	 * @return void
	 */
	public function set_element_view_counters( $element, $hidden = false ) {
		if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$user_id = get_current_user_id();
			$settings = $element->get_settings_for_display();
			if ( ( ! $hidden && ( $settings['dce_visibility_selected'] ?? '' ) == 'yes' ) || ( $hidden && ( $settings['dce_visibility_selected'] ?? '' ) == 'hide' ) ) {
				if ( ! empty( $settings['dce_visibility_max_user'] ) || ! empty( $settings['dce_visibility_max_day'] ) || ! empty( $settings['dce_visibility_max_total'] ) ) {
					$dce_visibility_max = get_option( 'dce_visibility_max', [] );
					// remove elements with no limits
					foreach ( $dce_visibility_max as $ekey => $value ) {
						if ( $ekey != $element->get_id() ) {
							$esettings = Helper::get_elementor_element_settings_by_id( $ekey );
							if ( empty( $esettings['dce_visibility_max_day'] ) && empty( $esettings['dce_visibility_max_total'] ) && empty( $esettings['dce_visibility_max_user'] ) ) {
								unset( $dce_visibility_max[ $ekey ] );
							} else {
								if ( empty( $esettings['dce_visibility_max_day'] ) ) {
									unset( $dce_visibility_max[ $ekey ]['day'] );
								}
								if ( empty( $esettings['dce_visibility_max_total'] ) ) {
									unset( $dce_visibility_max[ $ekey ]['total'] );
								}
								if ( empty( $esettings['dce_visibility_max_user'] ) ) {
									unset( $dce_visibility_max[ $ekey ]['user'] );
								}
							}
						}
					}

					if ( isset( $dce_visibility_max[ $element->get_id() ] ) ) {
						$today = date( 'Ymd' );

						if ( ! empty( $settings['dce_visibility_max_day'] ) ) {
							if ( ! empty( $dce_visibility_max[ $element->get_id() ]['day'][ $today ] ) ) {
								$dce_visibility_max_day = $dce_visibility_max[ $element->get_id() ]['day'];
								$dce_visibility_max_day[ $today ] = intval( $dce_visibility_max_day[ $today ] ) + 1;
							} else {
								$dce_visibility_max_day = [];
								$dce_visibility_max_day[ $today ] = 1;
							}
						} else {
							$dce_visibility_max_day = [];
						}
						if ( ! empty( $settings['dce_visibility_max_total'] ) ) {
							if ( isset( $dce_visibility_max[ $element->get_id() ]['total'] ) ) {
								$dce_visibility_max_total = intval( $dce_visibility_max[ $element->get_id() ]['total'] ) + 1;
							} else {
								$dce_visibility_max_total = 1;
							}
						} else {
							$dce_visibility_max_total = 0;
						}
						if ( $user_id && ! empty( $settings['dce_visibility_max_user'] ) ) {
							if ( ! empty( $dce_visibility_max[ $element->get_id() ]['user'] ) ) {
								$dce_visibility_max_user = $dce_visibility_max[ $element->get_id() ]['user'];
							} else {
								$dce_visibility_max_user = [];
							}
							$dce_visibility_max_user[ $user_id ] = $user_id;
						} else {
							$dce_visibility_max_user = [ $user_id => $user_id ];
						}
					} else {
						$dce_visibility_max_user = [ $user_id => $user_id ];
						$dce_visibility_max_day = [];
						$dce_visibility_max_total = 1;
					}
					$dce_visibility_max[ $element->get_id() ] = [
						'day' => $dce_visibility_max_day,
						'total' => $dce_visibility_max_total,
						'user' => $dce_visibility_max_user,
					];
					update_option( 'dce_visibility_max', $dce_visibility_max );
				}
			}
			if ( ! empty( $settings['dce_visibility_selected'] ) ) {
				if ( $user_id && ! empty( $settings['dce_visibility_max_user'] ) ) {
					$dce_visibility_max_user = get_user_meta( $user_id, 'dce_visibility_max_user', true );
					if ( empty( $dce_visibility_max_user[ $element->get_id() ] ) ) {
						if ( empty( $dce_visibility_max_user ) ) {
							$dce_visibility_max_user = [];
						}
						$dce_visibility_max_user[ $element->get_id() ] = 2;
					} else {
						++$dce_visibility_max_user[ $element->get_id() ];
					}
					update_user_meta( $user_id, 'dce_visibility_max_user', $dce_visibility_max_user );
				}
			}
		}
	}
}
