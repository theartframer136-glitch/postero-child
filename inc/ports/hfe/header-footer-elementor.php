<?php
/**
 * Plugin Name: Ultimate Addons for Elementor (UAE)
 * Plugin URI:  https://wordpress.org/plugins/header-footer-elementor/
 * Description: Ultimate Addons is a powerful plugin allows you to create custom headers and footers with Elementor and display them in selected locations. You can also create custom Elementor blocks and place them anywhere on your website using a shortcode.
 * Author:      Brainstorm Force
 * Author URI:  https://www.brainstormforce.com/
 * Text Domain: header-footer-elementor
 * Domain Path: /languages
 * Version: 2.9.5
 * Elementor tested up to: 4.3
 * Elementor Pro tested up to: 4.3
 *
 * @package         header-footer-elementor
 *
 * Theme port (postero-child): the plugin's main file, required by
 * inc/ports/header-footer-elementor.php only while the plugin is inactive.
 * Changes from the plugin's file:
 *  - the HFE_* / UAE_LITE constants are defined by the loader (same names and
 *    values, paths pointing at the theme copy);
 *  - the Abilities API loader (inc/abilities, WP 6.9+ MCP/AI tooling, admin
 *    only) is not ported;
 *  - hfe_plugin_activation() / hfe_plugin_deactivation() and their
 *    register_*_hook() calls are not ported (a theme has no activation);
 *  - add_action( 'plugins_loaded', 'hfe_init' ) is replaced by the loader
 *    calling hfe_init() directly (plugins_loaded has already run when the
 *    theme loads).
 * hfe_init() and hfe_enqueue_font_awesome() are unchanged.
 */

/**
 * Load the class loader.
 */
require_once HFE_DIR . '/inc/class-header-footer-elementor.php';

/**
 * Load the Plugin Class.
 *
 * @return void
 */
function hfe_init() {
	Header_Footer_Elementor::instance();
}

/** Function for FA5, Social Icons, Icon List */
function hfe_enqueue_font_awesome() {

	if ( class_exists( 'Elementor\Plugin' ) ) {

		// Ensure Elementor Icons CSS is loaded.
		wp_enqueue_style(
			'hfe-elementor-icons',
			plugins_url( '/elementor/assets/lib/eicons/css/elementor-icons.min.css', 'elementor' ),
			[],
			'5.34.0'
		);
		wp_enqueue_style(
			'hfe-icons-list',
			plugins_url( '/elementor/assets/css/widget-icon-list.min.css', 'elementor' ),
			[],
			'3.24.3'
		);
		wp_enqueue_style(
			'hfe-social-icons',
			plugins_url( '/elementor/assets/css/widget-social-icons.min.css', 'elementor' ),
			[],
			'3.24.0'
		);
		wp_enqueue_style(
			'hfe-social-share-icons-brands',
			plugins_url( '/elementor/assets/lib/font-awesome/css/brands.css', 'elementor' ),
			[],
			'5.15.3'
		);

		wp_enqueue_style(
			'hfe-social-share-icons-fontawesome',
			plugins_url( '/elementor/assets/lib/font-awesome/css/fontawesome.css', 'elementor' ),
			[],
			'5.15.3'
		);
		wp_enqueue_style(
			'hfe-nav-menu-icons',
			plugins_url( '/elementor/assets/lib/font-awesome/css/solid.css', 'elementor' ),
			[],
			'5.15.3'
		);
	}
	if ( class_exists( '\ElementorPro\Plugin' ) ) {
		wp_enqueue_style(
			'hfe-widget-blockquote',
			plugins_url( '/elementor-pro/assets/css/widget-blockquote.min.css', 'elementor' ),
			[],
			'3.25.0'
		);
		wp_enqueue_style(
			'hfe-mega-menu',
			plugins_url( '/elementor-pro/assets/css/widget-mega-menu.min.css', 'elementor' ),
			[],
			'3.26.2'
		);
		wp_enqueue_style(
			'hfe-nav-menu-widget',
			plugins_url( '/elementor-pro/assets/css/widget-nav-menu.min.css', 'elementor' ),
			[],
			'3.26.0'
		);
	}
}
add_action( 'wp_enqueue_scripts', 'hfe_enqueue_font_awesome', 20 );
