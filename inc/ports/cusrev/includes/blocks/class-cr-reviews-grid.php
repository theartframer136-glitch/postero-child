<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/blocks/class-cr-reviews-grid.php. Kept: the two static
 * methods that register the plugin's front-end script handles at init and load
 * frontend.css, frontend.js and colcade.js on EVERY front-end page through
 * enqueue_block_assets. Left out: the [cusrev_reviews_grid] shortcode and block
 * (not used anywhere on the site) and their AJAX actions.
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Reviews_Grid' ) ) {

	/**
	* Class for reviews grid shortcode and block.
	*/
	final class CR_Reviews_Grid {

		public static function cr_register_blocks_script() {
			wp_register_script(
				'cr-frontend-js',
				AF_CUSREV_URL . 'js/frontend.js' /* port: URL */,
				array('jquery'),
				AF_CUSREV_VERSION, // port: was Ivole::CR_VERSION ('5.123.0')
				true
			);
			wp_register_script(
				'cr-colcade',
				AF_CUSREV_URL . 'js/colcade.js' /* port: URL */,
				array(),
				AF_CUSREV_VERSION, // port: was Ivole::CR_VERSION ('5.123.0')
				true
			);
		}

		public static function cr_enqueue_block_scripts() {
			global $current_screen;
			$assets_version = AF_CUSREV_VERSION; // port: was Ivole::CR_VERSION ('5.123.0')

			wp_register_style( 'cr-frontend-css', AF_CUSREV_URL . 'css/frontend.css' /* port: URL */, array(), $assets_version, 'all' );
			wp_enqueue_style( 'cr-frontend-css' );

			wp_register_style( 'cr-badges-css', AF_CUSREV_URL . 'css/badges.css' /* port: URL */, array(), $assets_version, 'all' );

			wp_localize_script(
				'cr-frontend-js',
				'cr_ajax_object',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
			wp_enqueue_script( 'cr-frontend-js' );
			wp_enqueue_script( 'cr-colcade' );
		}

	}

}
