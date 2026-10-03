<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Reviews_Rating' ) ) {

	/**
	* Class for reviews rating shortcode
	*/
 class CR_Reviews_Rating {

		public function __construct() {
			$this->register_shortcode();
		}

		public function register_shortcode() {
			add_shortcode( 'cusrev_reviews_rating', array( $this, 'render_reviews_rating_shortcode' ) );
		}

		public function render_reviews_rating( $attributes ) {
			$cr_products = array();
			// check if a product was provided as a shortcode parameter
			if (
				isset( $attributes['product'] ) &&
				is_numeric( $attributes['product'] ) &&
				0 < $attributes['product']
			) {
				$cr_products[] = wc_get_product( $attributes['product'] );
			}
			// otherwise check if the shortcode is on a single product page
			if ( ! $cr_products ) {
				global $product;
				if (
					isset( $product ) &&
					$product instanceof WC_Product
				) {
					$cr_products[] = $product;
				}
			}
			// check if the group product flag is set
			if (
				isset( $attributes['group'] ) &&
				$attributes['group']
			) {
				if ( $cr_products && 0 < count( $cr_products ) ) {
					if ( $cr_products[0] && $cr_products[0]->is_type( 'grouped' ) ) {
						$child_ids = $cr_products[0]->get_children();
						$child_prods = array_map( 'wc_get_product', $child_ids );
						$cr_products = array_merge( $cr_products, $child_prods );
					}
				}
			}
			// include the template
			if( $cr_products ) {
				$cr_stars_style = $attributes['color_stars'];
				$template = wc_locate_template(
					'cr-shortcode-rating.php',
					'customer-reviews-woocommerce',
					__DIR__ . '/../../templates/'
				);
				ob_start();
				include( $template );
				return ob_get_clean();
			} else {
				return self::not_a_product_page();
			}
		}

		public function render_reviews_rating_shortcode( $attributes ) {
			// Convert shortcode attributes to block attributes
			$attributes = shortcode_atts(
				array(
				'color_stars' => '#FFBC00',
				'product' => '',
				'group' => false
				),
				$attributes, 'cusrev_reviews_rating'
			);
			$attributes['group'] = $attributes['group'] === 'true' ? true : false;
			return $this->render_reviews_rating( $attributes );
		}

		public static function not_a_product_page() {
			$output = '<div class="cr-reviews-rating-not-product">' .
				esc_html__( 'Error: if no valid product ID was provided as a parameter, [cusrev_reviews_rating] shortcode works only on WooCommerce single product pages', 'customer-reviews-woocommerce' ) .
				'</div>';
			return $output;
		}

	}

}
