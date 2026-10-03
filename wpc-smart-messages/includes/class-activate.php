<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcsm_Activate' ) ) {
	class Wpcsm_Activate {
		protected static $examples = [
			'01' => [
				'title'      => 'Items left in stock',
				'content'    => 'Only [wpcsm_product_data get="stock"] left in stock. Hurry!',
				'location'   => 'single_product_price_after',
				'conditions' => [
					[
						'type'  => 'product_stock',
						'group' => 'compare_number',
						'value' => [ 'compare' => 'less', 'number' => '10' ]
					]
				]
			],
			'02' => [
				'title'      => 'Potential savings',
				'content'    => 'You save: [wpcsm_saved_amount] ([wpcsm_saved_percentage])',
				'location'   => 'single_product_add_to_cart_before',
				'conditions' => [
					[
						'type'  => 'product_status',
						'group' => 'product_status',
						'value' => 'onsale'
					]
				]
			],
			'03' => [
				'title'    => 'The store\'s best sellers',
				'content'  => '[wpcsm_best_seller top="10" in="product_cat" text="#%s in %s"]',
				'location' => 'single_product_meta_before'
			],
			'04' => [
				'title'    => 'Recent viewing',
				'content'  => '[wpcsm_random_number min="10" max="15"] people are viewing this product right now.',
				'location' => 'single_product_title_after'
			],
			'05' => [
				'title'    => 'Recent viewing with live number',
				'content'  => '[wpcsm_live_number min="10" max="15" step="5" duration="10" text="%s"] people are viewing this product right now.',
				'location' => 'single_product_title_after'
			],
			'06' => [
				'title'    => 'Recent sales activity',
				'content'  => '[wpcsm_recent_order text="{name} from {from} bought this item!" name="billing_first_name" within="168" cache="24"]',
				'location' => 'single_product_title_after'
			],
		];

		static function generate_examples() {
			foreach ( self::$examples as $key => $example ) {
				$args  = [
					'post_type'      => 'wpc_smart_message',
					'post_status'    => [ 'publish', 'draft' ],
					'posts_per_page' => 1,
					'meta_query'     => [
						[
							'key'     => 'wpcsm_example',
							'value'   => $key,
							'compare' => '=='
						]
					]
				];
				$query = new WP_Query( $args );

				if ( ! $query->have_posts() ) {
					$data = [
						'post_status'  => 'publish',
						'post_type'    => 'wpc_smart_message',
						'post_title'   => sanitize_text_field( $example['title'] ),
						'post_content' => sanitize_text_field( $example['content'] ),
					];

					if ( $id = wp_insert_post( $data ) ) {
						update_post_meta( $id, 'wpcsm_activate', 'off' );
						update_post_meta( $id, 'wpcsm_example', $key );

						if ( isset( $example['location'] ) ) {
							update_post_meta( $id, 'wpcsm_location', sanitize_text_field( $example['location'] ) );
						}

						if ( isset( $example['conditions'] ) ) {
							update_post_meta( $id, 'wpcsm_conditions', self::sanitize_array( $example['conditions'] ) );
						}
					}
				}
			}
		}

		static function sanitize_array( $arr ) {
			foreach ( (array) $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_text_field( $v );
				}
			}

			return $arr;
		}
	}
}