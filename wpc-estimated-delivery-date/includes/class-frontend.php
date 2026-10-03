<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpced_Frontend' ) ) {
	class Wpced_Frontend {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Shortcode
			add_shortcode( 'wpced', [ $this, 'shortcode' ] );

			// Archive
			$pos_archive = Wpced_Backend()->get_setting( 'position_archive', 'above_add_to_cart' );

			switch ( $pos_archive ) {
				case 'under_title':
					add_action( 'woocommerce_shop_loop_item_title', [ $this, 'show_date' ], 11 );
					break;
				case 'under_rating':
					add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'show_date' ], 6 );
					break;
				case 'under_price':
					add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'show_date' ], 11 );
					break;
				case 'above_add_to_cart':
					add_action( 'woocommerce_after_shop_loop_item', [ $this, 'show_date' ], 9 );
					break;
				case 'under_add_to_cart':
					add_action( 'woocommerce_after_shop_loop_item', [ $this, 'show_date' ], 11 );
					break;
				case '0':
				case 'no':
				case 'none':
					break;
				default:
					add_action( 'wpced_custom_archive_position', [ $this, 'show_date_archive_position' ] );
			}

			// Single
			$pos_single = Wpced_Backend()->get_setting( 'position_single', '31' );

			if ( ! empty( $pos_single ) ) {
				if ( is_numeric( $pos_single ) ) {
					add_action( 'woocommerce_single_product_summary', [ $this, 'show_date' ], absint( $pos_single ) );
				} else {
					// custom position
					add_action( 'wpced_custom_single_position', [ $this, 'show_date_single_position' ] );
				}
			}

			// Variation
			add_filter( 'woocommerce_available_variation', [ $this, 'available_variation' ], 99, 3 );
			add_action( 'woocommerce_before_variations_form', [ $this, 'before_variations_form' ] );

			// AJAX
			add_action( 'wc_ajax_wpced_reload_dates', [ $this, 'ajax_reload_dates' ] );

			// Cart
			if ( Wpced_Backend()->get_setting( 'cart_item', 'no' ) === 'yes' ) {
				add_filter( 'woocommerce_cart_item_name', [ $this, 'cart_item_name' ], 10, 2 );
			}

			if ( Wpced_Backend()->get_setting( 'cart_item', 'no' ) === 'yes_data' ) {
				add_filter( 'woocommerce_get_item_data', [ $this, 'cart_item_meta' ], 10, 2 );
			}

			if ( Wpced_Backend()->get_setting( 'cart_overall', 'yes' ) !== 'no' ) {
				add_action( 'woocommerce_cart_contents', [ $this, 'show_cart_overall_date' ] );
			}

			// Order details (order confirmation or emails)
			add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'create_order_line_item' ], 10, 3 );
			add_action( 'woocommerce_order_item_meta_start', [ $this, 'order_item_meta_start' ], 10, 2 );
			add_filter( 'woocommerce_email_styles', [ $this, 'email_styles' ], 10, 2 );

			// Admin order
			add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_itemmeta' ] );
			add_action( 'woocommerce_before_order_itemmeta', [ $this, 'before_order_itemmeta' ], 10, 2 );
		}

		public function enqueue_scripts() {
			wp_enqueue_style( 'wpced-frontend', WPCED_URI . 'assets/css/frontend.css', [], WPCED_VERSION );
			wp_enqueue_script( 'wpced-frontend', WPCED_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCED_VERSION, true );
			wp_localize_script( 'wpced-frontend', 'wpced_vars', [
				'wc_ajax_url'  => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'nonce'        => wp_create_nonce( 'wpced-security' ),
				'reload_dates' => apply_filters( 'wpced_reload_dates', wc_string_to_bool( Wpced_Backend()->get_setting( 'reload_dates', 'no' ) ) )
			] );
		}

		function ajax_reload_dates() {
			$dates = [];
			$ids   = isset( $_POST['ids'] ) ? Wpced_Helper()->sanitize_array( $_POST['ids'] ) : [];

			if ( ! empty( $ids ) ) {
				$ids = array_unique( $ids );

				foreach ( $ids as $id ) {
					$dates[ 'wpced-' . $id ] = self::get_product_date( $id );
				}
			}

			wp_send_json( $dates );
		}

		public static function get_rule( $product, $shipping_method = null ) {
			$ignore = ! is_a( $product, 'WC_Product' ) || ! $product->exists() || ! $product->is_purchasable() || ! $product->is_in_stock() || $product->is_type( 'external' ) || $product->is_virtual();

			if ( apply_filters( 'wpced_ignore', $ignore, $product ) ) {
				return [];
			}

			$get_rule      = [];
			$user_zone     = Wpced_Helper()->get_shipping_zone();
			$user_method   = $shipping_method ?: Wpced_Helper()->get_selected_method();
			$variation_id  = 0;
			$parent_enable = 0;
			$product_id    = $product->get_id();
			$enable        = get_post_meta( $product_id, 'wpced_enable', true ) ?: 'global';
			$rules         = $default_rule = [];

			if ( $product->is_type( 'variation' ) ) {
				$variation_id = $product_id;
				$product_id   = $product->get_parent_id();
				$enable       = apply_filters( 'wpced_enable_variation', get_post_meta( $product->get_id(), 'wpced_enable', true ) ?: 'parent', $product );

				if ( $enable === 'parent' ) {
					$parent_enable = 1;
					$enable        = get_post_meta( $product_id, 'wpced_enable', true ) ?: 'global';
				}
			}

			if ( $enable === 'disable' ) {
				return [];
			}

			if ( $enable === 'global' ) {
				$rules = Wpced_Backend()->get_rules();
			}

			if ( $enable === 'override' ) {
				if ( $variation_id && ! $parent_enable ) {
					$rules = get_post_meta( $variation_id, 'wpced_rules', true ) ?: [];
				} else {
					$rules = get_post_meta( $product_id, 'wpced_rules', true ) ?: [];
				}
			}

			if ( isset( $rules['default'] ) ) {
				$default_rule = $rules['default'];
				unset( $rules['default'] );
			}

			if ( ! empty( $rules ) ) {
				foreach ( $rules as $rule_key => $rule ) {
					$rule = array_merge( Wpced_Backend()->get_base_rule(), $rule );

					// check apply
					$apply         = ! empty( $rule['apply'] ) ? $rule['apply'] : 'all';
					$apply_val     = ! empty( $rule['apply_val'] ) ? (array) $rule['apply_val'] : [];
					$apply_compare = ! empty( $rule['apply_compare'] ) ? $rule['apply_compare'] : 'equal';
					$apply_number  = ! empty( $rule['apply_number'] ) ? (float) $rule['apply_number'] : 0;
					$zone          = ! empty( $rule['zone'] ) ? $rule['zone'] : 'all';
					$method        = ! empty( $rule['method'] ) ? $rule['method'] : 'all';

					if ( ! in_array( $apply, [
						'all',
						'stock',
						'instock',
						'outofstock',
						'backorder'
					] ) ) {
						if ( ( substr( $apply, 0, 3 ) === 'pa_' ) && $variation_id ) {
							// check variation attribute
							$attrs = $product->get_attributes();

							if ( empty( $attrs[ $apply ] ) || ! in_array( $attrs[ $apply ], $apply_val ) ) {
								continue;
							}
						}

						if ( ! has_term( $apply_val, $apply, $product_id ) ) {
							// doesn't apply for current product
							continue;
						}
					}

					if ( $apply === 'stock' ) {
						// check stock quantity
						if ( ! $product->managing_stock() ) {
							continue;
						}

						$stock_qty = $product->get_stock_quantity();

						if ( ( $apply_compare === 'equal' ) && ( $stock_qty !== $apply_number ) ) {
							continue;
						}

						if ( ( $apply_compare === 'not_equal' ) && ( $stock_qty === $apply_number ) ) {
							continue;
						}

						if ( ( $apply_compare === 'greater' ) && ( $stock_qty <= $apply_number ) ) {
							continue;
						}

						if ( ( $apply_compare === 'greater_equal' ) && ( $stock_qty < $apply_number ) ) {
							continue;
						}

						if ( ( $apply_compare === 'less' ) && ( $stock_qty >= $apply_number ) ) {
							continue;
						}

						if ( ( $apply_compare === 'less_equal' ) && ( $stock_qty > $apply_number ) ) {
							continue;
						}
					}

					if ( ( $apply === 'instock' ) && ! $product->is_in_stock() ) {
						continue;
					}

					if ( ( $apply === 'outofstock' ) && $product->is_in_stock() ) {
						continue;
					}

					if ( ( $apply === 'backorder' ) && ! $product->is_on_backorder() ) {
						continue;
					}

					if ( $zone !== 'all' && ( $user_zone && ( $user_zone->get_id() != $zone ) ) ) {
						// doesn't match zone
						continue;
					}

					if ( $method !== 'all' && ( $user_method && ( $user_method != $method ) ) ) {
						// doesn't match method
						continue;
					}

					$get_rule        = $rule;
					$get_rule['key'] = $rule_key;
					break;
				}
			}

			if ( empty( $get_rule ) ) {
				$get_rule        = array_merge( Wpced_Backend()->get_base_rule(), $default_rule );
				$get_rule['key'] = 'default';
			}

			return apply_filters( 'wpced_get_rule', $get_rule, $product );
		}

		function show_date( $product = null ) {
			if ( ! $product ) {
				global $product;
			}

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			echo self::get_product_date( $product );
		}

		function get_product_date( $product, $type = 'full', $context = 'product' ) {
			if ( is_numeric( $product ) ) {
				$product_id = $product;
				$product    = wc_get_product( $product_id );
			} elseif ( is_a( $product, 'WC_Product' ) ) {
				$product_id = $product->get_id();
			} else {
				$product_id = 0;
			}

			if ( ! $product_id ) {
				return '';
			}

			$delivery_date   = '';
			$delivery_date_u = '';
			$is_min          = $is_max = false;
			$rule            = self::get_rule( $product );

			if ( isset( $rule['min'] ) && $rule['min'] !== '' ) {
				$min_time        = self::get_date( $rule['min'], $rule['scheduled'] );
				$delivery_date   .= self::format_date( $min_time );
				$delivery_date_u = $min_time;

				if ( empty( $rule['max'] ) ) {
					$is_min = true;
				}
			}

			if ( isset( $rule['max'] ) && $rule['max'] !== '' ) {
				$max_time = self::get_date( $rule['max'], $rule['scheduled'] );

				if ( $rule['min'] !== '' ) {
					$delivery_date   .= apply_filters( 'wpced_dates_separator', ' - ' );
					$delivery_date_u .= apply_filters( 'wpced_dates_separator', ' - ' );
				} else {
					$is_max = true;
				}

				$delivery_date   .= self::format_date( $max_time );
				$delivery_date_u .= $max_time;
			}

			if ( $type === 'u' || $type === 'U' ) {
				// get the unix time only
				$product_date = $delivery_date_u;
			} elseif ( $type === 'plain' || $type === 'text' ) {
				// get the plain text only
				$product_date = $delivery_date;
			} else {
				if ( $is_max ) {
					$delivery_text = Wpced_Backend()->get_setting( 'text_max', /* translators: date */ esc_html__( 'Latest estimated delivery date: %s', 'wpc-estimated-delivery-date' ) );

					if ( empty( $delivery_text ) ) {
						$delivery_text = /* translators: date */
							esc_html__( 'Latest estimated delivery date: %s', 'wpc-estimated-delivery-date' );
					}
				} elseif ( $is_min ) {
					$delivery_text = Wpced_Backend()->get_setting( 'text_min', /* translators: date */ esc_html__( 'Earliest estimated delivery date: %s', 'wpc-estimated-delivery-date' ) );

					if ( empty( $delivery_text ) ) {
						$delivery_text = /* translators: date */
							esc_html__( 'Earliest estimated delivery date: %s', 'wpc-estimated-delivery-date' );
					}
				} else {
					$delivery_text = Wpced_Backend()->get_setting( 'text', /* translators: date */ esc_html__( 'Estimated delivery dates: %s', 'wpc-estimated-delivery-date' ) );

					if ( empty( $delivery_text ) ) {
						$delivery_text = /* translators: date */
							esc_html__( 'Estimated delivery dates: %s', 'wpc-estimated-delivery-date' );
					}
				}

				$wrapper_id    = is_a( $product, 'WC_Product_Variation' ) ? $product->get_parent_id() : $product_id;
				$wrapper_class = apply_filters( 'wpced_wrapper_class', 'wpced wpced-' . $wrapper_id . ' wpced-' . $context . ' wpced-' . ( $rule['key'] ?? 'default' ), $product, $type, $context );

				if ( ! empty( $delivery_date ) ) {
					$product_date = '<div class="' . esc_attr( $wrapper_class ) . '" data-id="' . esc_attr( $wrapper_id ) . '"><div class="wpced-inner">' . sprintf( $delivery_text, $delivery_date ) . '</div></div>';
				} else {
					// keep wrapper for variable product
					$product_date = '<div class="' . esc_attr( $wrapper_class ) . '" data-id="' . esc_attr( $wrapper_id ) . '"></div>';
				}
			}

			return apply_filters( 'wpced_get_product_date', $product_date, $product, $type, $context );
		}

		function show_date_archive_position( $pos = 'none' ) {
			global $product;

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			$position = apply_filters( 'wpced_archive_position', Wpced_Backend()->get_setting( 'position_archive', apply_filters( 'wpced_default_archive_position', 'above_add_to_cart' ) ) );

			if ( $position === $pos ) {
				self::show_date( $product );
			}
		}

		function show_date_single_position( $pos = '0' ) {
			global $product;

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			$position = apply_filters( 'wpced_single_position', Wpced_Backend()->get_setting( 'position_single', apply_filters( 'wpced_default_single_position', '31' ) ) );

			if ( $position === $pos ) {
				self::show_date( $product );
			}
		}

		function available_variation( $available, $variable, $variation ) {
			if ( apply_filters( 'wpced_available_variation', true, $available, $variable, $variation ) ) {
				$available['wpced_enable'] = apply_filters( 'wpced_enable_variation', get_post_meta( $variation->get_id(), 'wpced_enable', true ) ?: 'parent', $variation );
				$available['wpced_date']   = htmlentities( self::get_product_date( $variation ) );
			}

			return $available;
		}

		function before_variations_form() {
			global $product;

			echo '<span class="wpced-variable wpced-variable-' . esc_attr( $product->get_id() ) . '" data-wpced="' . esc_attr( htmlentities( self::get_product_date( $product ) ) ) . '" style="display: none"></span>';
		}

		function cart_item_name( $name, $cart_item ) {
			return $name . self::get_product_date( $cart_item['data'] );
		}

		function cart_item_meta( $data, $cart_item ) {
			$date = self::get_product_date( $cart_item['data'], 'plain' );

			if ( ! empty( $date ) ) {
				$data['wpced_date'] = apply_filters( 'wpced_cart_item_meta', [
					'key'     => apply_filters( 'wpced_cart_item_meta_key', Wpced_Backend()->get_setting( 'text_cart_item', esc_html__( 'Estimated delivery date', 'wpc-estimated-delivery-date' ) ), $cart_item ),
					'value'   => apply_filters( 'wpced_cart_item_meta_value', esc_html( $date ), $cart_item ),
					'display' => apply_filters( 'wpced_cart_item_meta_display', $date, $cart_item ),
				], $cart_item );
			}

			return $data;
		}

		function show_cart_overall_date() {
			$overall_date = self::get_overall_date();

			if ( ! empty( $overall_date ) ) {
				if ( Wpced_Backend()->get_setting( 'cart_overall', 'yes' ) === 'yes_text' ) {
					echo '<tr><td colspan="100" class="wpced-cart">' . esc_html( $overall_date ) . '</td></tr>';
				} else {
					echo '<tr><td colspan="100" class="wpced-cart"><span class="wpced"><span class="wpced-inner">' . esc_html( $overall_date ) . '</span></span></td></tr>';
				}
			}

			return null;
		}

		function get_overall_date( $shipping_method = null ) {
			if ( ! isset( WC()->cart ) ) {
				return null;
			}

			$items = WC()->cart->get_cart();

			if ( is_array( $items ) && ( count( $items ) > 0 ) ) {
				$format      = Wpced_Backend()->get_setting( 'cart_overall_format', 'latest' );
				$overall_min = [];
				$overall_max = [];

				foreach ( $items as $item ) {
					$rule     = self::get_rule( $item['data'], $shipping_method );
					$item_min = $item_max = '';

					if ( $rule['min'] !== '' ) {
						$item_min = $item_max = self::get_date( $rule['min'], $rule['scheduled'] );
					}

					if ( $rule['max'] !== '' ) {
						$item_max = self::get_date( $rule['max'], $rule['scheduled'] );

						if ( empty( $item_min ) ) {
							$item_min = $item_max;
						}
					}

					if ( ! empty( $item_min ) ) {
						$overall_min[] = $item_min;
					}

					if ( ! empty( $item_max ) ) {
						$overall_max[] = $item_max;
					}
				}

				if ( ! empty( $overall_min ) && ! empty( $overall_max ) ) {
					sort( $overall_min );
					sort( $overall_max );

					switch ( $format ) {
						case 'earliest':
							$delivery_date = self::format_date( reset( $overall_min ) );
							break;

						case 'earliest_latest':
							$delivery_date = self::format_date( reset( $overall_min ) ) . apply_filters( 'wpced_dates_separator', ' - ' ) . self::format_date( end( $overall_max ) );
							break;

						case 'latest':
						default:
							$delivery_date = self::format_date( end( $overall_max ) );
							break;
					}

					$delivery_text = Wpced_Backend()->get_setting( 'text_cart_overall', /* translators: date */ esc_html__( 'Overall estimated dispatch date: %s', 'wpc-estimated-delivery-date' ) );

					if ( empty( $delivery_text ) ) {
						$delivery_text = /* translators: date */
							esc_html__( 'Overall estimated dispatch date: %s', 'wpc-estimated-delivery-date' );
					}

					return apply_filters( 'wpced_get_overall_date', sprintf( $delivery_text, $delivery_date ), $shipping_method );
				}
			}

			return null;
		}

		function get_date( $days, $scheduled = '' ) {
			// get date after skipped
			$i                    = 1;
			$j                    = 1;
			$available            = [];
			$days                 = absint( $days );
			$current_unix         = current_time( 'U' );
			$current_time         = current_time( 'h:i a' );
			$current_date         = current_time( 'm/d/Y' );
			$current_weekday      = current_time( 'w' );
			$extra_time_line      = Wpced_Backend()->get_setting( 'extra_time_line' );
			$date_format          = self::get_date_format();
			$current_date_skipped = false;

			if ( ! empty( $scheduled ) && ( strtotime( $scheduled ) > strtotime( $current_date ) ) ) {
				$current_unix         = strtotime( $scheduled );
				$current_date         = date_i18n( 'm/d/Y', $current_unix );
				$current_weekday      = date_i18n( 'w', $current_unix );
				$current_date_skipped = true;
			}

			while ( self::check_skipped( $current_date, $current_weekday ) && ( $j <= 100 ) ) {
				// skipped start date
				$current_unix         += 86400;
				$current_date         = date_i18n( 'm/d/Y', $current_unix );
				$current_weekday      = date_i18n( 'w', $current_unix );
				$current_date_skipped = true;
				$j ++;
			}

			if ( $current_date_skipped && apply_filters( 'wpced_apply_extra_date_for_skipped_date', false ) ) {
				$days += 1;
			}

			if ( ! empty( $extra_time_line ) && apply_filters( 'wpced_apply_extra_time_for_skipped_date', ! $current_date_skipped ) ) {
				// don't calculate extra time if current date is skipped
				if ( strtotime( $current_date . ' ' . $current_time ) > strtotime( $current_date . ' ' . $extra_time_line ) ) {
					$days += 1;
				}
			}

			if ( $days === 0 ) {
				$get_date = $current_unix;
			} else {
				while ( ( count( $available ) < $days ) && ( $i <= 100 ) ) {
					if ( ! $current_date_skipped ) {
						$current_unix    += 86400;
						$current_date    = date_i18n( 'm/d/Y', $current_unix );
						$current_weekday = date_i18n( 'w', $current_unix );
					}

					if ( ! self::check_skipped( $current_date, $current_weekday ) ) {
						$available[] = $current_unix;
					}

					if ( $current_date_skipped ) {
						$current_unix    += 86400;
						$current_date    = date_i18n( 'm/d/Y', $current_unix );
						$current_weekday = date_i18n( 'w', $current_unix );
					}

					$i ++;
				}

				$get_date = end( $available );
			}

			if ( $date_format === 'days' ) {
				$get_date = absint( round( ( $get_date - current_time( 'U' ) ) / 86400 ) );
			}

			return apply_filters( 'wpced_get_date', $get_date, $days, $scheduled );
		}

		function format_date( $date ) {
			if ( ! is_numeric( $date ) ) {
				return $date;
			}

			$date_format = self::get_date_format();

			if ( empty( $date_format ) ) {
				$date_format = 'M j, Y';
			}

			if ( $date_format === 'days' ) {
				return absint( $date );
			}

			return date_i18n( $date_format, $date );
		}

		function get_date_format() {
			$date_format        = Wpced_Backend()->get_setting( 'date_format', 'M j, Y' );
			$date_format_custom = Wpced_Backend()->get_setting( 'date_format_custom', 'M j, Y' );

			if ( ( $date_format === 'custom' ) && ! empty( $date_format_custom ) ) {
				$date_format = $date_format_custom;
			}

			$date_format = apply_filters( 'wpced_date_format', $date_format );

			return apply_filters( 'wpced_get_date_format', $date_format );
		}

		function check_skipped( $date, $weekday = null ) {
			$skipped_dates = Wpced_Backend()->get_setting( 'skipped_dates', [] );

			if ( empty( $skipped_dates ) || ! is_array( $skipped_dates ) ) {
				return false;
			}

			if ( is_numeric( $date ) ) {
				// backward compatibility
				$weekday = date_i18n( 'w', $date );
				$date    = date_i18n( 'm/d/Y', $date );
			}

			foreach ( $skipped_dates as $skipped_date ) {
				if ( $skipped_date['type'] !== 'cus' ) {
					// weekly on every
					if ( $skipped_date['type'] == $weekday ) {
						return true;
					}
				}
			}

			return false;
		}

		function shortcode( $attrs ) {
			$attrs = shortcode_atts( [
				'product_id' => null,
			], $attrs );

			if ( empty( $attrs['product_id'] ) ) {
				global $product;
			} else {
				$product = wc_get_product( $attrs['product_id'] );
			}

			return apply_filters( 'wpced_shortcode', is_a( $product, 'WC_Product' ) ? self::get_product_date( $product ) : '', $attrs );
		}

		function create_order_line_item( $order_item, $cart_item_key, $values ) {
			$order_item->update_meta_data( '_wpced_date', self::get_product_date( $values['data'] ) );
		}

		function hidden_order_itemmeta( $hidden ) {
			return array_merge( $hidden, [
				'_wpced_date',
			] );
		}

		function before_order_itemmeta( $order_item_id, $order_item ) {
			if ( ( $date = $order_item->get_meta( '_wpced_date' ) ) && ! empty( $date ) ) {
				echo $date;
			}
		}

		function order_item_meta_start( $order_item_id, $order_item ) {
			if ( ( Wpced_Backend()->get_setting( 'order_item', 'no' ) === 'yes' ) && ( $date = $order_item->get_meta( '_wpced_date' ) ) && ! empty( $date ) ) {
				echo apply_filters( 'wpced_order_item_date', $date, $order_item_id, $order_item );
			}
		}

		function email_styles( $css, $email ) {
			// hide delivery date for these emails
			if ( in_array( $email->id, apply_filters( 'wpced_hide_order_item_date_emails', [
				'failed_order',
				'cancelled_order',
				'customer_failed_order',
				'customer_refunded_order'
			] ) ) ) {
				$css .= ' .wpced{display: none !important;} ';
			}

			return $css;
		}
	}

	function Wpced_Frontend() {
		return Wpced_Frontend::instance();
	}

	Wpced_Frontend();
}
