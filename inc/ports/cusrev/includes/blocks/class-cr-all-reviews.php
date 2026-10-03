<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/blocks/class-cr-all-reviews.php. Kept: cr_style_1(),
 * which on every singular page that is not a product (pages, posts, cart,
 * checkout, the home page) loads the lightbox and the plugin's CSS/JS and
 * prints the lightbox markup in the footer, and the two helpers it uses.
 * Left out: the [cusrev_all_reviews] shortcode (not used anywhere on the site)
 * and its AJAX actions.
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('CR_All_Reviews')) :

	class CR_All_Reviews
	{

		public function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'cr_style_1' ) );
		}

		private function enqueue_wc_script( $handle, $path = '', $deps = array( 'jquery' ), $version = WC_VERSION, $in_footer = true ) {
			if ( ! wp_script_is( $handle, 'registered' ) ) {
				wp_register_script( $handle, $path, $deps, $version, $in_footer );
			}
			if ( ! wp_script_is( $handle ) ) {
				wp_enqueue_script( $handle );
			}
		}

		private function enqueue_wc_style( $handle, $path = '', $deps = array(), $version = WC_VERSION, $media = 'all', $has_rtl = false ) {
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, $path, $deps, $version, $media );
			}
			if ( ! wp_style_is( $handle ) ) {
				wp_enqueue_style( $handle );
			}
		}

		public function cr_style_1()
		{
			if ( is_singular() && ! is_product() ) {
				$assets_version = AF_CUSREV_VERSION; // port: was Ivole::CR_VERSION ('5.123.0')
				$disable_lightbox = 'yes' === get_option( 'ivole_disable_lightbox', 'no' ) ? true : false;
				// Load gallery scripts on product pages only if supported.
				if ( ! $disable_lightbox ) {
					$this->enqueue_wc_script( 'wc-photoswipe-ui-default' );
					$this->enqueue_wc_style( 'photoswipe-default-skin' );
					add_action( 'wp_footer', array( $this, 'cr_photoswipe' ) );
				}

				wp_register_style( 'cr-frontend-css', AF_CUSREV_URL . 'css/frontend.css' /* port: URL */, array(), $assets_version, 'all' );
				wp_register_script( 'cr-frontend-js', AF_CUSREV_URL . 'js/frontend.js' /* port: URL */, array( 'jquery' ), $assets_version, true );
				wp_register_script( 'cr-colcade', AF_CUSREV_URL . 'js/colcade.js' /* port: URL */, array(), $assets_version, true );
				wp_enqueue_style( 'cr-frontend-css' );
				wp_localize_script(
					'cr-frontend-js',
					'cr_ajax_object',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'disable_lightbox' => ( $disable_lightbox ? 1 : 0 ),
						'flags_url' => AF_CUSREV_URL . 'img/flags/' // port: URL
					)
				);
				wp_enqueue_script( 'cr-frontend-js' );
				do_action( 'cr_after_enqueue_allreviews_scripts' );
			}
		}

		public function cr_photoswipe() {
			wc_get_template(
				'cr-photoswipe.php',
				array(),
				'customer-reviews-woocommerce',
				dirname( dirname( dirname( __FILE__ ) ) ) . '/templates/'
			);
		}

	}

endif;
