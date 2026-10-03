<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_StructuredData' ) ) :

	class CR_StructuredData {

		public function __construct() {
			add_filter( 'woocommerce_structured_data_product', array( $this, 'filter_woocommerce_structured_data_product' ), 10, 2 );
			add_action( 'woocommerce_product_meta_end', array( $this, 'action_woocommerce_structured_data_review' ) );
			add_filter( 'woocommerce_available_variation', array( $this, 'filter_woocommerce_available_variation'), 10, 3 );
			add_filter( 'woocommerce_structured_data_review', array( $this, 'filter_woocommerce_structured_data_review' ), 10, 2 );
			add_action( 'wp_footer', array( $this, 'output_schema_markup' ) );
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

		public function filter_woocommerce_structured_data_product( $markup, $product ) {
			if ( 'yes' === get_option( 'ivole_product_feed_enable_id_str_dat', 'no' ) ) {
				$identifiers = self::get_product_feed_identifiers();
				if( isset( $identifiers['gtin'] ) ) {
					$gtin = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['gtin'], $product );
					$gtin_lenth = mb_strlen( $gtin );
					switch( $gtin_lenth ) {
						case 8:
							$markup['gtin8'] = $gtin;
							break;
						case 12:
							$markup['gtin12'] = $gtin;
							break;
						case 13:
							$markup['gtin13'] = $gtin;
							break;
						case 14:
							$markup['gtin14'] = $gtin;
							break;
						default:
							$markup['gtin'] = $gtin;
							break;
					}
				}
				if( isset( $identifiers['mpn'] ) ) {
					$mpn = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['mpn'], $product );
					if( $mpn ) {
						$markup['mpn'] = $mpn;
					}
				}
				if( isset( $identifiers['brand'] ) ) {
					$brand = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['brand'], $product );
					if( !$brand ) {
						$brand = trim( get_option( 'ivole_google_brand_static', '' ) );
					}
					if( $brand ) {
						$markup['brand'] = array(
							'@type' => 'Brand',
							'name' => $brand
						);
					}
				}
			}
			return $markup;
		}

		public function action_woocommerce_structured_data_review() {
			global $product;
			if ( 'yes' === get_option( 'ivole_product_feed_enable_id_str_dat', 'no' ) ) {
				$identifiers = self::get_product_feed_identifiers();
				$space = apply_filters( 'cr_productids_separator', '<br>' );
				if( isset( $identifiers['gtin'] ) ) {
					$gtin = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['gtin'], $product );
					if( !$gtin ) {
						// if variable product, check if any variation has gtin
						if( $product->is_type( 'variable' ) ) {
							$available_variations = wc_get_products( array(
								'parent' => $product->get_id(),
								'status' => 'publish',
								'type' => 'variation',
								'limit' => -1
							) );
							foreach ( $available_variations as $variation )
							{
								if( CR_Google_Shopping_Prod_Feed::get_field( $identifiers['gtin'], $variation ) ) {
									$gtin = __( 'N/A', 'woocommerce' );
									break;
								}
							}
						}
					}
					if( $gtin ) {
						echo $space . '<span class="cr_gtin" data-o_content="' . $gtin . '">' . __( 'GTIN: ', 'customer-reviews-woocommerce' ) .
							'<span class="cr_gtin_val">' . $gtin . '</span></span>';
					}
				}
				if( isset( $identifiers['mpn'] ) ) {
					$mpn = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['mpn'], $product );
					if( !$mpn ) {
						// if variable product, check if any variation has mpn
						if( $product->is_type( 'variable' ) ) {
							$available_variations = wc_get_products( array(
								'parent' => $product->get_id(),
								'status' => 'publish',
								'type' => 'variation',
								'limit' => -1
							) );
							foreach ( $available_variations as $variation )
							{
								if( CR_Google_Shopping_Prod_Feed::get_field( $identifiers['mpn'], $variation ) ) {
									$mpn = __( 'N/A', 'woocommerce' );
									break;
								}
							}
						}
					}
					if( $mpn ) {
						echo $space . '<span class="cr_mpn" data-o_content="' . $mpn . '">' . __( 'MPN: ', 'customer-reviews-woocommerce' ) .
							'<span class="cr_mpn_val">' . $mpn . '</span></span>';
					}
				}
				if( isset( $identifiers['brand'] ) ) {
					$brand = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['brand'], $product );
					if( !$brand ) {
						$brand = trim( get_option( 'ivole_google_brand_static', '' ) );
					}
					if( $brand ) {
						echo $space . '<span class="cr_brand" data-o_content="' . $brand . '">' . __( 'Brand: ', 'customer-reviews-woocommerce' ) .
							'<span class="cr_brand_val">' . $brand . '</span></span>';
					}
				}
			}
		}

		/**
		* @var $variations array
		* @var $product WC_Product_Variable
		* @var $variation WC_Product_Variation
		*
		* @return array
		*/
		public function filter_woocommerce_available_variation( $variations, $product, $variation ) {
			if ( 'yes' === get_option( 'ivole_product_feed_enable_id_str_dat', 'no' ) ) {
				$identifiers = self::get_product_feed_identifiers();
				if( isset( $identifiers['gtin'] ) ) {
					$gtin = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['gtin'], $variation );
					if( $gtin ) {
						$variations['_cr_gtin'] = $gtin;
					} else {
						$gtin = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['gtin'], $product );
						if( $gtin ) {
							$variations['_cr_gtin'] = $gtin;
						}
					}
				}
				if( isset( $identifiers['mpn'] ) ) {
					$mpn = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['mpn'], $variation );
					if( $mpn ) {
						$variations['_cr_mpn'] = $mpn;
					} else {
						$mpn = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['mpn'], $product );
						if( $mpn ) {
							$variations['_cr_mpn'] = $mpn;
						}
					}
				}
				if( isset( $identifiers['brand'] ) ) {
					$brand = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['brand'], $variation );
					if( $brand ) {
						$variations['_cr_brand'] = $brand;
					} else {
						$brand = CR_Google_Shopping_Prod_Feed::get_field( $identifiers['brand'], $product );
						if( $brand ) {
							$variations['_cr_brand'] = $brand;
						} else {
							$brand = trim( get_option( 'ivole_google_brand_static', '' ) );
							if( $brand ) {
								$variations['_cr_brand'] = $brand;
							}
						}
					}
				}
			}
			return $variations;
		}

		public function output_schema_markup() {
			// schema markup should be displayed only on WooCommerce product pages
			if ( ! is_product() ) {
				return;
			}
			$post_id = get_the_ID();
			if ( ! $post_id ) {
				return;
			}
			$product = wc_get_product( $post_id );
			if ( ! $product instanceof WC_Product ) {
				return;
			}
			// check if the option to output schema markup is enabled
			$review_extensions_option = CR_Review_Extensions_Settings::get_review_extension_options();
			if ( ! $review_extensions_option['schema_markup'] ) {
				return;
			}
			// product schema markup
			$shop_name = get_bloginfo( 'name' );
			$shop_url  = home_url();
			$currency  = get_woocommerce_currency();
			$permalink = get_permalink( $product->get_id() );
			$image     = wp_get_attachment_url( $product->get_image_id() );
			$markup = array(
				'@context'    => 'https://schema.org/',
				'@type'       => 'Product',
				'@id'         => $permalink . '#product', // Append '#product' to differentiate between this @id and the @id generated for the Breadcrumblist.
				'name'        => wp_kses_post( $product->get_name() ),
				'url'         => $permalink,
				'description' => wp_strip_all_tags( do_shortcode( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ) ),
			);
			if ( $image ) {
				$markup['image'] = $image;
			}
			if ( $product->get_sku() ) {
				$markup['sku'] = $product->get_sku();
			} else {
				$markup['sku'] = $product->get_id();
			}
			// reviews schema markup
			if ( $product->get_rating_count() ) {
				$markup['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => $product->get_average_rating(),
					'reviewCount' => $product->get_review_count(),
				);

				// Markup 5 most recent rating/review.
				$comments = get_comments(
					array(
						'number'      => 5,
						'post_id'     => $product->get_id(),
						'status'      => 'approve',
						'post_status' => 'publish',
						'post_type'   => 'product',
						'parent'      => 0,
						'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => 'rating',
								'type'    => 'NUMERIC',
								'compare' => '>',
								'value'   => 0,
							),
						),
					)
				);

				if ( $comments ) {
					$markup['review'] = array();
					foreach ( $comments as $comment ) {
						$markup['review'][] = array(
							'@type'         => 'Review',
							'reviewRating'  => array(
								'@type'       => 'Rating',
								'bestRating'  => '5',
								'ratingValue' => get_comment_meta( $comment->comment_ID, 'rating', true ),
								'worstRating' => '1',
							),
							'author'        => array(
								'@type' => 'Person',
								'name'  => get_comment_author( $comment ),
							),
							'reviewBody'    => get_comment_text( $comment ),
							'datePublished' => get_comment_date( 'c', $comment ),
						);
					}
				}
			}
			if ( ! empty( $markup['aggregateRating'] ) ) {
				$markup_to_output = '<script type="application/ld+json">' . wp_json_encode( $markup ) . '</script>';
				echo apply_filters( 'cr_schema_markup', $markup_to_output, $post_id );
			}
		}

		private function get_product_feed_identifiers() {
			return get_option( 'ivole_product_feed_identifiers', array(
				'pid'   => '',
				'gtin'  => '',
				'mpn'   => '',
				'brand' => ''
			) );
		}

	}

endif;
