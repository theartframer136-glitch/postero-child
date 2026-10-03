<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcsm_Shortcode' ) ) {
	class Wpcsm_Shortcode {
		protected static $shortcodes = [];
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			add_action( 'init', [ $this, 'init' ] );
		}

		function init() {
			add_shortcode( 'wpcsm_product_data', [ $this, 'product_data' ] );
			add_shortcode( 'wpcsm_product_field', [ $this, 'product_field' ] );
			add_shortcode( 'wpcsm_product_terms', [ $this, 'product_terms' ] );
			add_shortcode( 'wpcsm_product_random_number', [ $this, 'product_random_number' ] );
			add_shortcode( 'wpcsm_best_seller', [ $this, 'best_seller' ] );
			add_shortcode( 'wpcsm_recent_order', [ $this, 'recent_order' ] );
			add_shortcode( 'wpcsm_price', [ $this, 'price' ] );
			add_shortcode( 'wpcsm_saved_percentage', [ $this, 'saved_percentage' ] );
			add_shortcode( 'wpcsm_saved_amount', [ $this, 'saved_amount' ] );
			add_shortcode( 'wpcsm_live_number', [ $this, 'live_number' ] );
			add_shortcode( 'wpcsm_random_number', [ $this, 'random_number' ] );
			add_shortcode( 'wpcsm_human_time_diff', [ $this, 'human_time_diff' ] );
			add_shortcode( 'wpcsm_cart_total', [ $this, 'cart_total' ] );
			add_shortcode( 'wpcsm_cart_total_diff', [ $this, 'cart_total_diff' ] );
			add_shortcode( 'wpcsm_cart_count', [ $this, 'cart_count' ] );
			add_shortcode( 'wpcsm_cart_count_diff', [ $this, 'cart_count_diff' ] );
			add_shortcode( 'wpcsm_text_rotator', [ $this, 'text_rotator' ] );

			self::$shortcodes = apply_filters( 'wpcsm_shortcodes', [
				'wpcsm_product_data'          => 'Display the product data. For example, [wpcsm_product_data get="stock"]. Available data: sku, price, stock, category, tag, average_rating, rating_counts, review_count, weight, width, height, etc.',
				'wpcsm_product_terms'         => 'Display the product terms. For example, [wpcsm_product_terms taxonomy="product_cat"]. Available taxonomy: product_cat, product_tag, wpc-brand, wpc-collection, etc.',
				'wpcsm_product_random_number' => 'Display a random number that associated to a product, it only change after selected period time in seconds. For example, [wpcsm_product_random_number min="10" max="15" refresh="600"]',
				'wpcsm_best_seller'           => 'Display the best-seller position in a category, tag, brand, or collection. For example, [wpcsm_best_seller top="10" in="product_cat" text="#%s in %s"]. Allow "in" param: product_cat, product_tag, wpc-brand, wpc-collection.',
				'wpcsm_recent_order'          => 'Display the most recent order that contains the current product. For example, [wpcsm_recent_order text="{name} from {from} bought this item!" name="billing_first_name" within="168" cache="24"]. Use "within" to get orders within (x) hours from the present time.',
				'wpcsm_human_time_diff'       => 'Display time difference human readable. For example, [wpcsm_human_time_diff to="12/24/2024 15:35"]',
				'wpcsm_price'                 => 'Display product price. For example, [wpcsm_price type="regular"]',
				'wpcsm_saved_percentage'      => 'Display saved percentage for on-sale product.',
				'wpcsm_saved_amount'          => 'Display saved amount for on-sale product.',
				'wpcsm_cart_total'            => 'Display cart total.',
				'wpcsm_cart_total_diff'       => 'Display cart total different from a target amount. For example, [wpcsm_cart_total_diff to="99"]',
				'wpcsm_cart_count'            => 'Display cart count.',
				'wpcsm_cart_count_diff'       => 'Display cart count different from a target number. For example, [wpcsm_cart_count_diff to="10"]',
				'wpcsm_live_number'           => 'Display a live number, it was refreshed automatically after the selected duration time in seconds. For example, [wpcsm_live_number min="10" max="15" step="5" duration="10" text="%s"]',
				'wpcsm_random_number'         => 'Display a random number. For example, [wpcsm_random_number min="10" max="15"]',
				'wpcsm_text_rotator'          => 'Display a super simple rotating text. For example, [wpcsm_text_rotator animation="flipUp" speed="2" text="text 1, text 2, text 3"]. Speed in seconds, text split by a comma and available animation are: dissolve (default), fade, flip, flipUp, flipCube, flipCubeUp and spin.',
			] );
		}

		function get_shortcodes() {
			return self::$shortcodes;
		}

		function product_data( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'get'  => 'price',
				'type' => 'html',
				'id'   => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['id'] );
			}

			if ( $product && is_a( $product, 'WC_Product' ) ) {
				switch ( $attrs['get'] ) {
					case 'price':
						switch ( $attrs['type'] ) {
							case 'html':
								$output = $product->get_price_html();

								break;
							case 'regular':
								$output = wc_price( $product->get_regular_price() );

								break;
							case 'sale':
								$output = wc_price( $product->get_sale_price() );

								break;
						}

						break;
					case 'stock':
						if ( $product->managing_stock() ) {
							$output = $product->get_stock_quantity();
						}

						break;
					case 'category':
						$output = wc_get_product_category_list( $product->get_id() );

						break;
					case 'tag':
						$output = wc_get_product_tag_list( $product->get_id() );

						break;
					default:
						$func = 'get_' . $attrs['get'];

						if ( method_exists( $product, $func ) ) {
							$output = $product->$func();
						}
				}
			}

			return apply_filters( 'wpcsm_shortcode_product_data', $output, $attrs );
		}

		function product_field( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'key' => '',
				'id'  => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
				$product_id = $product->get_id();
			} else {
				$product_id = absint( $attrs['id'] );
			}

			if ( $product_id && ! empty( $attrs['key'] ) ) {
				$output = get_post_meta( $product_id, $attrs['key'], true );
			}

			return apply_filters( 'wpcsm_shortcode_product_field', $output, $attrs );
		}

		function product_terms( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id'       => null,
				'taxonomy' => 'product_cat',
				'before'   => '',
				'sep'      => '',
				'after'    => '',
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['id'] );
			}

			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$output = get_the_term_list( $product->get_id(), $attrs['taxonomy'], $attrs['before'], $attrs['sep'], $attrs['after'] );
			}

			return apply_filters( 'wpcsm_shortcode_product_terms', $output, $attrs );
		}

		function product_random_number( $attrs ) {
			$attrs = shortcode_atts( [
				'min'     => null,
				'max'     => null,
				'refresh' => 600
			], $attrs );

			global $product;
			$refresh = absint( $attrs['refresh'] );

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return '';
			}

			$product_id = $product->get_id();
			$rand       = get_transient( 'wpcsm_product_random_number_' . $product_id );

			if ( ! $rand ) {
				$rand = wp_rand( $attrs['min'], $attrs['max'] );
				set_transient( 'wpcsm_product_random_number_' . $product_id, $rand, $refresh );
			}

			return apply_filters( 'wpcsm_shortcode_product_random_number', $rand, $attrs );
		}

		function best_seller( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id'   => null,
				'top'  => 10,
				'in'   => 'product_cat',
				'text' => /* translators: top of category */ esc_html__( '#%1$d in %2$s', 'wpc-smart-messages' )
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['id'] );
			}

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return '';
			}

			$product_id = $product->get_id();
			$terms      = get_the_terms( $product_id, $attrs['in'] );
			$text       = apply_filters( 'wpcsm_shortcode_best_seller_text', $attrs['text'] );
			$taxonomies = apply_filters( 'wpcsm_shortcode_best_seller_taxonomies', [
				'product_cat',
				'product_tag',
				'wpc-brand',
				'wpc-collection'
			] );

			if ( ! in_array( $attrs['in'], $taxonomies ) ) {
				$attrs['in'] = 'product_cat';
			}

			if ( is_array( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$args  = [
						'post_type'      => 'product',
						'meta_key'       => 'total_sales',
						'orderby'        => 'meta_value_num',
						'order'          => 'DESC',
						'post_status'    => 'publish',
						'posts_per_page' => (int) $attrs['top'],
						'tax_query'      => [
							[
								'taxonomy' => $attrs['in'],
								'field'    => 'term_id',
								'terms'    => [ $term->term_id ],
								'operator' => 'IN'
							]
						],
					];
					$query = new WP_Query( $args );

					if ( $query->have_posts() ) {
						$top = 1;

						while ( $query->have_posts() ) {
							$query->the_post();

							if ( get_the_ID() === $product_id ) {
								$output = sprintf( $text, $top, '<a href="' . get_term_link( $term->term_id, $attrs['in'] ) . '">' . $term->name . '</a>' );
								break;
							}

							$top ++;
						}

						wp_reset_postdata();
					}
				}
			}

			return apply_filters( 'wpcsm_shortcode_best_seller', $output, $attrs );
		}

		function recent_order( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id'     => null,
				'within' => 24 * 7,
				'cache'  => 24,
				'name'   => 'billing_first_name',
				'text'   => esc_html__( '{name} from {from} bought this item!', 'wpc-smart-messages' )
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['id'] );
			}

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return '';
			}

			$recent_order = [];
			$product_id   = $product->get_id();
			$transient    = 'wpcsm_recent_order_' . $product_id;
			$cache        = ! empty( $attrs['cache'] ) ? (float) $attrs['cache'] : 24;

			if ( ! get_transient( $transient ) ) {
				$within = ! empty( $attrs['within'] ) ? (float) $attrs['within'] : 24 * 7;
				$time   = strtotime( 'today' ) - absint( $within * HOUR_IN_SECONDS );

				$args = [
					'post_type'      => wc_get_order_types(),
					'post_status'    => apply_filters( 'wpcsm_recent_order_statuses', array_keys( wc_get_order_statuses() ) ),
					'posts_per_page' => - 1,
					'date_query'     => [
						'after' => gmdate( 'Y-m-d H:i:s', $time ),
					],
				];

				$the_query = new WP_Query( apply_filters( 'wpcsm_recent_order_args', $args ) );

				if ( $the_query->have_posts() ) {
					while ( $the_query->have_posts() ) {
						$the_query->the_post();
						$order = wc_get_order( get_the_ID() );

						foreach ( $order->get_items() as $item ) {
							if ( $item->get_product_id() === $product_id ) {
								$name = $order->get_billing_first_name();
								$from = $order->get_billing_city();

								if ( empty( $from ) ) {
									$from = $order->get_billing_country();
								}

								switch ( $attrs['name'] ) {
									case 'billing_full_name';
										$name = $order->get_formatted_billing_full_name();
										break;
									case 'billing_last_name';
										$name = $order->get_billing_last_name();
										break;
									case 'shipping_full_name';
										$name = $order->get_formatted_shipping_full_name();
										break;
									case 'shipping_first_name';
										$name = $order->get_shipping_first_name();
										break;
									case 'shipping_last_name';
										$name = $order->get_shipping_last_name();
										break;
								}

								$recent_order = apply_filters( 'wpcsm_recent_order_details', [
									'name' => $name,
									'from' => $from,
									'time' => $order->get_date_created()->format( 'U' )
								], $order );

								break;
							}
						}
					}

					wp_reset_postdata();
				}

				set_transient( $transient, $recent_order, absint( $cache * HOUR_IN_SECONDS ) );
			}

			$order_detail = get_transient( $transient );

			if ( ! empty( $order_detail ) ) {
				$output = $attrs['text'];
				$output = str_replace( '{name}', $order_detail['name'], $output );
				$output = str_replace( '{from}', $order_detail['from'], $output );
			}

			return apply_filters( 'wpcsm_shortcode_recent_order', $output, $attrs );
		}

		function price( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'type' => 'html',
				'id'   => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['id'] );
			}

			if ( $product && is_a( $product, 'WC_Product' ) ) {
				switch ( $attrs['type'] ) {
					case 'html':
						$output = $product->get_price_html();

						break;
					case 'regular':
						$output = wc_price( $product->get_regular_price() );

						break;
					case 'sale':
						$output = wc_price( $product->get_sale_price() );

						break;
				}
			}

			return apply_filters( 'wpcsm_shortcode_price', $output, $attrs );
		}

		function saved_amount( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id' => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;

				if ( $product && $product->is_on_sale() ) {
					$output = self::get_saved_amount( $product );
				}
			} else {
				if ( ( $_product = wc_get_product( $attrs['id'] ) ) && $_product->is_on_sale() ) {
					$output = self::get_saved_amount( $_product );
				}
			}

			return apply_filters( 'wpcsm_shortcode_saved_amount', $output, $attrs );
		}

		function saved_percentage( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'id' => null
			], $attrs );

			if ( ! $attrs['id'] ) {
				global $product;

				if ( $product && $product->is_on_sale() ) {
					$output = self::get_saved_percentage( $product );
				}
			} else {
				if ( ( $_product = wc_get_product( $attrs['id'] ) ) && $_product->is_on_sale() ) {
					$output = self::get_saved_percentage( $_product );
				}
			}

			return apply_filters( 'wpcsm_shortcode_saved_percentage', $output, $attrs );
		}

		function text_rotator( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'text'      => '',
				'speed'     => 2,
				'animation' => 'dissolve' //dissolve (default), fade, flip, flipUp, flipCube, flipCubeUp and spin
			], $attrs );

			$output .= '<span class="wpcsm-text-rotator" data-animation="' . esc_attr( $attrs['animation'] ) . '" data-speed="' . esc_attr( $attrs['speed'] ) . '">' . $attrs['text'] . '</span>';

			return apply_filters( 'wpcsm_shortcode_live_number', $output, $attrs );
		}

		function live_number( $attrs ) {
			$output = '';

			$attrs = shortcode_atts( [
				'min'       => 0,
				'max'       => null,
				'step'      => 5,
				'duration'  => 10,
				'text'      => '%s',
				'type'      => '',
				'animation' => 'flipUp' //dissolve (default), fade, flip, flipUp, flipCube, flipCubeUp and spin
			], $attrs );

			$rand = wp_rand( $attrs['min'], $attrs['max'] );

			if ( $attrs['type'] === 'rotator' ) {
				$rand_values = [];

				for ( $i = 0; $i <= 20; $i ++ ) {
					$rand_values[] = $rand + wp_rand( 0, $attrs['step'] );
				}

				$output .= '<span class="wpcsm-number-rotator">';
				$output .= sprintf( $attrs['text'], '<span class="wpcsm-number-rotator-value wpcsm-text-rotator" data-animation="' . esc_attr( $attrs['animation'] ) . '" data-speed="' . esc_attr( $attrs['duration'] ) . '">' . implode( ', ', $rand_values ) . '</span>' );
				$output .= '</span>';
			} else {
				$output .= '<span class="wpcsm-live-number" data-val="' . esc_attr( $rand ) . '" data-min="' . esc_attr( $attrs['min'] ) . '" data-max="' . esc_attr( $attrs['max'] ) . '" data-step="' . esc_attr( $attrs['step'] ) . '" data-duration="' . esc_attr( $attrs['duration'] ) . '" data-text="' . esc_attr( $attrs['text'] ) . '">';
				$output .= sprintf( $attrs['text'], '<span class="wpcsm-live-number-value">' . $rand . '</span>' );
				$output .= '</span>';
			}

			return apply_filters( 'wpcsm_shortcode_live_number', $output, $attrs );
		}

		function random_number( $attrs ) {
			$attrs = shortcode_atts( [
				'min' => null,
				'max' => null,
			], $attrs );

			return apply_filters( 'wpcsm_shortcode_random_number', wp_rand( $attrs['min'], $attrs['max'] ), $attrs );
		}

		function human_time_diff( $attrs ) {
			$attrs = shortcode_atts( [
				'from' => '',
				'to'   => 'tomorrow',
			], $attrs );

			if ( empty( $attrs['from'] ) ) {
				$attrs['from'] = current_time( 'timestamp' );
			} else {
				$attrs['from'] = strtotime( $attrs['from'] );
			}

			if ( empty( $attrs['to'] ) ) {
				$attrs['to'] = current_time( 'timestamp' );
			} else {
				$attrs['to'] = strtotime( $attrs['to'] );
			}

			$diff = absint( (int) $attrs['from'] - (int) $attrs['to'] );

			// year
			$years_value = floor( $diff / YEAR_IN_SECONDS );
			$years       = sprintf( /* translators: year */ _n( '%s year', '%s years', $years_value, 'wpc-smart-messages' ), $years_value );

			// month
			$months_value = floor( $diff / ( YEAR_IN_SECONDS / 12 ) ) % 12;
			$months       = sprintf( /* translators: month */ _n( '%s month', '%s months', $months_value, 'wpc-smart-messages' ), $months_value );

			// days
			$days_value = floor( $diff / DAY_IN_SECONDS ) % 365 % 30;
			$days       = sprintf( /* translators: day */ _n( '%s day', '%s days', $days_value, 'wpc-smart-messages' ), $days_value );

			// hours
			$hours_value = floor( $diff / HOUR_IN_SECONDS ) % 24;
			$hours       = sprintf( /* translators: hour */ _n( '%s hour', '%s hours', $hours_value, 'wpc-smart-messages' ), $hours_value );

			// minutes
			$mins_value = round( $diff / MINUTE_IN_SECONDS ) % 60;
			$mins       = sprintf( /* translators: min */ _n( '%s min', '%s mins', $mins_value, 'wpc-smart-messages' ), $mins_value );

			$years  = ( 0 == $years_value ) ? null : $years;
			$months = ( 0 == $months_value ) ? null : $months;
			$days   = ( 0 == $days_value ) ? null : $days;
			$hours  = ( 0 == $hours_value ) ? null : $hours;
			$mins   = ( 0 == $mins_value ) ? null : $mins;

			// human time diff
			$output = ltrim( sprintf( '%s %s %s %s %s', $years, $months, $days, $hours, $mins ) );

			return apply_filters( 'wpcsm_shortcode_human_time_diff', $output, $attrs );
		}

		function cart_total() {
			return apply_filters( 'wpcsm_shortcode_cart_total', WC()->cart->get_total() );
		}

		function cart_total_diff( $attrs ) {
			$attrs = shortcode_atts( [
				'to' => 100,
			], $attrs );

			return apply_filters( 'wpcsm_shortcode_cart_total_diff', wc_price( (float) $attrs['to'] - WC()->cart->get_total( 'amount' ) ), $attrs );
		}

		function cart_count( $attrs ) {
			$cart_count = WC()->cart->get_cart_contents_count();

			$attrs = shortcode_atts( [
				'context'    => 'cart',
				'product_id' => null,
			], $attrs );

			if ( $attrs['context'] === 'product' ) {
				$cart_count = 0;

				if ( ! $attrs['product_id'] ) {
					global $product;

					if ( $product && is_a( $product, 'WC_Product' ) ) {
						$attrs['product_id'] = $product->get_id();
					}
				}

				if ( $product_id = absint( $attrs['product_id'] ) ) {
					foreach ( WC()->cart->get_cart() as $cart_item ) {
						if ( $cart_item['product_id'] == $product_id ) {
							$cart_count += (float) $cart_item['quantity'];
						}
					}
				}
			}

			return apply_filters( 'wpcsm_shortcode_cart_count', $cart_count );
		}

		function cart_count_diff( $attrs ) {
			$attrs = shortcode_atts( [
				'to' => 100,
			], $attrs );

			return apply_filters( 'wpcsm_shortcode_cart_count_diff', (float) $attrs['to'] - WC()->cart->get_cart_contents_count(), $attrs );
		}

		function get_saved_percentage( $product ) {
			$output = '';

			if ( $product->get_type() == 'variable' ) {
				$available_variations = $product->get_variation_prices();
				$max_percentage       = 0;

				foreach ( $available_variations['regular_price'] as $key => $regular_price ) {
					$sale_price = $available_variations['sale_price'][ $key ];

					if ( $regular_price && $sale_price < $regular_price ) {
						$percentage = round( ( $regular_price - $sale_price ) * 100 / $regular_price );

						if ( $percentage > $max_percentage ) {
							$max_percentage = $percentage;
						}
					}
				}

				$output = $max_percentage . '%';
			} else {
				$regular_price = $product->get_regular_price();
				$sale_price    = $product->get_sale_price();

				if ( $regular_price && $sale_price ) {
					$output = round( ( $regular_price - $sale_price ) * 100 / $regular_price ) . '%';
				}
			}

			return $output;
		}

		function get_saved_amount( $product ) {
			$output = '';

			if ( $product->get_type() == 'variable' ) {
				$available_variations = $product->get_variation_prices();
				$max_amount           = 0;

				foreach ( $available_variations['regular_price'] as $key => $regular_price ) {
					$sale_price = $available_variations['sale_price'][ $key ];

					if ( $regular_price && $sale_price < $regular_price ) {
						$amount = $regular_price - $sale_price;

						if ( $amount > $max_amount ) {
							$max_amount = $amount;
						}
					}
				}

				$output = wc_price( $max_amount );
			} else {
				$regular_price = $product->get_regular_price();
				$sale_price    = $product->get_sale_price();

				if ( $regular_price && $sale_price < $regular_price ) {
					$output = wc_price( $regular_price - $sale_price );
				}
			}

			return $output;
		}
	}

	Wpcsm_Shortcode::instance();
}