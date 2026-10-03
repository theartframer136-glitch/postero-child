<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/google/class-cr-structured-data.php. Kept: the filter that
 * adds a review's CusRev-hosted photos to WooCommerce's review structured data.
 * Left out, because with the default settings they return their input
 * unchanged or print nothing: the GTIN/MPN/brand product fields (option
 * ivole_product_feed_enable_id_str_dat) and the plugin's own product JSON-LD
 * (option ivole_review_extensions['schema_markup'], default false).
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_StructuredData' ) ) :

	class CR_StructuredData {

		public function __construct() {
			add_filter( 'woocommerce_structured_data_review', array( $this, 'filter_woocommerce_structured_data_review' ), 10, 2 );
		}

		public function filter_woocommerce_structured_data_review( $markup, $comment ) {
			$pics = get_comment_meta( $comment->comment_ID, 'ivole_review_image' );
			$pics_n = ( is_array( $pics ) ? count( $pics ) : 0 );
			if( $pics_n > 0 ) {
				$markup['associatedMedia']  = array();
				for( $i = 0; $i < $pics_n; $i ++) {
					$markup['associatedMedia'][]  = array(
						'@type' => 'ImageObject',
						'name' => sprintf( __( 'Image #%1$d from ', 'customer-reviews-woocommerce' ), $i + 1 ) . $comment->comment_author,
						'contentUrl' => $pics[$i]['url']
					);
				}
			}
			return $markup;
		}

	}

endif;
