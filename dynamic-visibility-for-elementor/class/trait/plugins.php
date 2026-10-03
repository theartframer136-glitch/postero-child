<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor;

trait Plugins {

	protected static $plugin_dependency_names =
		[
			'acf' => 'Advanced Custom Fields',
			'acf-pro' => 'Advanced Custom Fields Pro',
			'dynamic-shortcodes' => 'Dynamic Shortcodes',
			'elementor-pro' => 'Elementor Pro',
			'geoip-detect' => 'Geolocation IP Detection',
			'jet-engine' => 'JetEngine',
			'metabox' => 'Meta Box',
			'meta-box' => 'Meta Box',
			'memberpress' => 'MemberPress',
			'myfastapp' => 'MyFastAPP',
			'pods' => 'Pods',
			'polylang' => 'Polylang',
			'search-filter-pro' => 'Search & Filter Pro',
			'timber' => 'Timber',
			'types' => 'Toolset',
			'toolset' => 'Toolset',
			'wpcf' => 'Toolset',
			'wpml' => 'WPML',
			'sitepress-multilingual-cms' => 'WPML',
			'woocommerce' => 'WooCommerce',
		];

	/**
	 * @var array<string,mixed>
	 */
	private static $plugin_depends = [];

	/**
	 * @param string $plugin
	 * @return void
	 */
	private static function set_plugin_dependency_status( $plugin ) {
		switch ( $plugin ) {
			case 'acf':
				self::$plugin_depends['acf'] = class_exists( 'ACF' );
				break;
			case 'acf-pro':
				self::$plugin_depends['acf-pro'] = class_exists( 'ACF' ) && defined( 'ACF_PRO' );
				break;
			//+protect_start
			case 'dynamic-content-for-elementor':
				self::$plugin_depends['dynamic-content-for-elementor'] = defined( 'DCE_PATH' );
				break;
			//+protect_end
			case 'dynamic-shortcodes':
				self::$plugin_depends['dynamic-shortcodes'] = class_exists( 'DynamicShortcodes\Plugin' );
				break;
			case 'elementor-pro':
				self::$plugin_depends['elementor-pro'] = class_exists( 'ElementorPro\Plugin' );
				break;
			case 'geoip-detect':
				self::$plugin_depends['geoip-detect'] = function_exists( 'geoip_detect2_get_info_from_current_ip' );
				break;
			case 'jet-engine':
				self::$plugin_depends['jet-engine'] = class_exists( 'Jet_Engine' );
				break;
			case 'memberpress':
				self::$plugin_depends['memberpress'] = defined( 'MEPR_PLUGIN_NAME' );
				break;
			case 'metabox':
				self::$plugin_depends['metabox'] = class_exists( 'RWMB_Core' );
				break;
			case 'myfastapp':
				self::$plugin_depends['myfastapp'] = defined( 'TOA_MYFASTAPP_VERSION' );
				break;
			case 'pods':
				self::$plugin_depends['pods'] = class_exists( 'Pods' );
				break;
			case 'polylang':
				self::$plugin_depends['polylang'] = class_exists( 'Polylang' ) && function_exists( 'pll_languages_list' );
				break;
			case 'search-filter-pro':
				self::$plugin_depends['search-filter-pro'] = defined( 'SEARCH_FILTER_PRO_BASE_PATH' ) || defined( 'SEARCH_FILTER_PRO_BASE_FILE' );
				break;
			case 'timber':
				self::$plugin_depends['timber'] = class_exists( '\Timber\Timber' );
				break;
			case 'types':
				self::$plugin_depends['types'] = defined( 'TYPES_VERSION' );
				break;
			case 'toolset':
				self::$plugin_depends['toolset'] = defined( 'TYPES_VERSION' );
				break;
			case 'translatepress-multilingual':
				self::$plugin_depends['translatepress-multilingual'] = defined( 'TRP_PLUGIN_VERSION' );
				break;
			case 'woocommerce':
				self::$plugin_depends['woocommerce'] = class_exists( 'woocommerce' );
				break;
			case 'woocommerce-memberships':
				self::$plugin_depends['woocommerce-memberships'] = class_exists( 'WC_Memberships' );
				break;
			case 'wpcf': // Toolset - Old name
				self::$plugin_depends['wpcf'] = defined( 'TYPES_VERSION' );
				break;
			case 'sitepress-multilingual-cms': // WPML
				self::$plugin_depends['sitepress-multilingual-cms'] = class_exists( 'SitePress' );
				break;
			case 'weglot':
				self::$plugin_depends['weglot'] = function_exists( 'weglot_get_current_language' );
				break;
			case 'wpml':
				self::$plugin_depends['wpml'] = class_exists( 'SitePress' );
				break;
			default:
				self::$plugin_depends[ $plugin ] = false;
				_doing_it_wrong( __METHOD__, "Unknown plugin slug: {$plugin}", '3.3.14' );

		}
	}

	/**
	 * @param string $plugin
	 * @return bool
	 */
	public static function is_plugin_active( $plugin ) {
		if ( ! isset( self::$plugin_depends[ $plugin ] ) ) {
			self::set_plugin_dependency_status( $plugin );
		}
		return self::$plugin_depends[ $plugin ];
	}

	/**
	 * @param string $plugin
	 * @return string
	 */
	public static function get_plugin_name( $plugin ) {
		return self::$plugin_dependency_names[ $plugin ] ?? $plugin;
	}

	/**
	 * Check if all the given plugin dependencies are active.
	 *
	 * Accepts either:
	 * - a string slug: 'acf'
	 * - a numeric array of slugs: [ 'acf', 'elementor' ]
	 * - or an associative array slug => label: [ 'acf' => 'Advanced Custom Fields' ].
	 *
	 * @param string|array<int|string,string> $dependencies Dependencies to check. If the key is numeric, the value is the slug; otherwise the key is the slug.
	 * @return bool True if all dependencies are active, false otherwise.
	 */
	public static function is_dependencies_satisfied( $dependencies ) {
		if ( empty( $dependencies ) ) {
			return true;
		}
		if ( is_string( $dependencies ) ) {
			$dependencies = [ $dependencies ];
		}
		foreach ( $dependencies as $key => $plugin ) {
			$slug = is_numeric( $key ) ? $plugin : $key;
			if ( ! Helper::is_plugin_active( $slug ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Return the list of missing plugin dependencies as human‑readable names.
	 *
	 * Accepts either:
	 * - a string slug: 'acf'
	 * - a numeric array of slugs: [ 'acf', 'elementor' ]
	 * - or an associative array slug => label: [ 'acf' => 'Advanced Custom Fields' ].
	 *
	 * @param string|array<int|string,string> $dependencies Dependencies to check. If the key is numeric, the value is the slug; otherwise the key is the slug.
	 * @return array<int,string> Human‑readable names of missing dependencies (empty if all are active).
	 */
	public static function get_missing_dependencies( $dependencies ) {
		if ( empty( $dependencies ) ) {
			return [];
		}
		if ( is_string( $dependencies ) ) {
			$dependencies = [ $dependencies ];
		}
		$missing = [];
		foreach ( $dependencies as $key => $plugin ) {
			$slug = is_numeric( $key ) ? $plugin : $key;
			if ( ! Helper::is_plugin_active( $slug ) ) {
				$missing[] = is_numeric( $key ) ? self::get_plugin_name( $plugin ) : $key;
			}
		}
		return $missing;
	}
}
