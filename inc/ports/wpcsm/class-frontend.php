<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcsm_Frontend' ) ) {
	class Wpcsm_Frontend {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
				return;
			}

			add_action( 'init', [ $this, 'init' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );

			self::process_messages();
		}

		function init() {
			add_shortcode( 'wpcsm', [ $this, 'shortcode' ] );
			add_shortcode( 'wpc_smart_message', [ $this, 'shortcode' ] );
		}

		function shortcode( $attrs ) {
			$attrs = shortcode_atts( [ 'id' => 0, 'name' => '' ], $attrs, 'wpc_smart_message' );

			ob_start();
			self::display_message( $attrs['id'], 'shortcode' );

			return apply_filters( 'wpc_smart_message_shortcode', ob_get_clean(), $attrs );
		}

		function process_messages() {
			$locations = Wpcsm_Backend::instance()->get_locations();

			foreach ( self::get_messages() as $message ) {
				$message_id = $message->ID;
				$location   = get_post_meta( $message_id, 'wpcsm_location', true );

				if ( $location == 'custom' ) {
					$custom_location = get_post_meta( $message_id, 'wpcsm_custom_location', true );

					if ( ! empty( $custom_location ) ) {
						$exploded = explode( ':', $custom_location );
						$hook     = reset( $exploded );
						$priority = isset( $exploded[1] ) ? absint( $exploded[1] ) : 10;
						$location = $hook;
					}
				} else {
					$has_location = false;

					foreach ( $locations as $location_keys ) {
						if ( array_key_exists( $location, $location_keys ) ) {
							$has_location = true;

							if ( is_array( $location_keys[ $location ] ) ) {
								$hook     = $location_keys[ $location ]['hook'];
								$priority = $location_keys[ $location ]['priority'];
							} else {
								$exploded = explode( ':', $location );
								$hook     = reset( $exploded );
								$priority = isset( $exploded[1] ) ? absint( $exploded[1] ) : 10;
								$location = $hook;
							}

							break;
						}
					}

					if ( ! $has_location ) {
						$exploded = explode( ':', $location );
						$hook     = reset( $exploded );
						$priority = isset( $exploded[1] ) ? absint( $exploded[1] ) : 10;
						$location = $hook;
					}
				}

				if ( defined( 'DOING_AJAX' ) && DOING_AJAX && in_array( $location, [
						'store_notice_info',
						'store_notice_success',
						'store_notice_error'
					] ) ) {
					return;
				}

				if ( ! isset( $hook, $priority ) ) {
					return;
				}

				add_action( $hook, function () use ( $message_id, $location ) {
					self::display_message( $message_id, $location );
				}, $priority );
			}
		}

		function display_message( $message_id, $location ) {
			$message       = apply_filters( 'wpcsm_message_original', get_post_field( 'post_content', $message_id ), $message_id, $location );
			$container     = get_post_meta( $message_id, 'wpcsm_container', true ) ?: 'div';
			$extra_classes = get_post_meta( $message_id, 'wpcsm_extra_classes', true ) ?: '';
			$conditions    = get_post_meta( $message_id, 'wpcsm_conditions', true );

			if ( empty( $message ) || empty( $location ) || ! self::match_conditions( $conditions ) ) {
				return;
			}

			$message       = apply_filters( 'wpcsm_message', do_shortcode( $message ), $message, $message_id, $location );
			$message_class = 'wpcsm-message wpcsm-message-' . $message_id . ' wpcsm-location-' . $location;

			if ( ! empty( $extra_classes ) ) {
				$message_class .= ' ' . $extra_classes;
			}

			if ( 'store_notice_success' == $location ) {
				if ( ! wc_has_notice( $message ) ) {
					wc_add_notice( $message );
				}
			} elseif ( 'store_notice_info' == $location ) {
				if ( ! wc_has_notice( $message, 'notice' ) ) {
					wc_add_notice( $message, 'notice' );
				}
			} elseif ( 'store_notice_error' == $location ) {
				if ( ! wc_has_notice( $message, 'error' ) ) {
					wc_add_notice( $message, 'error' );
				}
			} else {
				$class = apply_filters( 'wpcsm_message_class', $message_class );

				if ( $container === 'div' ) {
					echo '<div class="' . esc_attr( $class ) . '">' . wp_kses_post( $message ) . '</div>';
				} else {
					echo '<span class="' . esc_attr( $class ) . '">' . wp_kses_post( $message ) . '</span>';
				}
			}
		}

		function match_conditions( $conditions ) {
			$match_all = true;

			foreach ( $conditions as $condition ) {
				$match     = false;
				$condition = array_merge( [
					'group'   => '',
					'type'    => '',
					'compare' => 'including',
					'value'   => ''
				], $condition );

				switch ( $condition['group'] ) {
					case 'term':
						if ( is_cart() || is_checkout() ) {
							foreach ( WC()->cart->get_cart() as $cart_item ) {
								if ( ( $condition['compare'] === 'including' ) && has_term( $condition['value'], $condition['type'], $cart_item['product_id'] ) ) {
									$match = true;
									break;
								}

								if ( ( $condition['compare'] === 'excluding' ) && ! has_term( $condition['value'], $condition['type'], $cart_item['product_id'] ) ) {
									$match = true;
									break;
								}
							}
						} elseif ( is_product_taxonomy() && ( $term = get_queried_object() ) ) {
							if ( ( $condition['compare'] === 'including' ) && ( ( is_array( $condition['value'] ) && in_array( $term->slug, $condition['value'] ) ) || ( ! is_array( $condition['value'] ) && ( $condition['value'] === $term->slug ) ) ) ) {
								$match = true;
							}

							if ( ( $condition['compare'] === 'excluding' ) && ( ( is_array( $condition['value'] ) && ! in_array( $term->slug, $condition['value'] ) ) || ( ! is_array( $condition['value'] ) && ( $condition['value'] !== $term->slug ) ) ) ) {
								$match = true;
							}
						} elseif ( is_product() ) {
							global $post;

							if ( ( $condition['compare'] === 'including' ) && $post && has_term( $condition['value'], $condition['type'], $post->ID ) ) {
								$match = true;
							}

							if ( ( $condition['compare'] === 'excluding' ) && $post && ! has_term( $condition['value'], $condition['type'], $post->ID ) ) {
								$match = true;
							}
						} else {
							global $product;

							if ( ( $condition['compare'] === 'including' ) && $product && is_a( $product, 'WC_Product' ) && has_term( $condition['value'], $condition['type'], $product->get_id() ) ) {
								$match = true;
							}

							if ( ( $condition['compare'] === 'excluding' ) && $product && is_a( $product, 'WC_Product' ) && ! has_term( $condition['value'], $condition['type'], $product->get_id() ) ) {
								$match = true;
							}
						}

						break;
					case 'user_role':
						$current_roles = self::get_current_roles();

						if ( ! is_array( $condition['value'] ) ) {
							$condition['value'] = (array) $condition['value'];
						}

						foreach ( $condition['value'] as $ur ) {
							if ( in_array( $ur, $current_roles ) ) {
								$match = true;
								break;
							}
						}

						break;
					case 'compare_number':
						if ( ! empty( $condition['value'] ) && ! empty( $condition['value']['compare'] ) && isset( $condition['value']['number'] ) && ( $condition['value']['number'] !== '' ) ) {
							$number = null;

							switch ( $condition['type'] ) {
								case 'cart_total':
									$number = WC()->cart->get_total( 'amount' );

									break;
								case 'cart_count':
									$number = WC()->cart->get_cart_contents_count();

									break;
								case 'product_stock':
									global $product;

									if ( $product && is_a( $product, 'WC_Product' ) && $product->managing_stock() ) {
										$number = $product->get_stock_quantity();
									}

									break;
								case 'product_weight':
									global $product;

									if ( $product && is_a( $product, 'WC_Product' ) ) {
										$number = $product->get_weight();
									}

									break;
								case 'product_width':
									global $product;

									if ( $product && is_a( $product, 'WC_Product' ) ) {
										$number = $product->get_width();
									}

									break;
								case 'product_height':
									global $product;

									if ( $product && is_a( $product, 'WC_Product' ) ) {
										$number = $product->get_height();
									}

									break;
							}

							if ( ! is_null( $number ) ) {
								// has a $number to compare with $compare_number
								$number         = (float) $number;
								$compare_number = (float) $condition['value']['number'];

								switch ( $condition['value']['compare'] ) {
									case 'equal':
										if ( $number == $compare_number ) {
											$match = true;
										}

										break;
									case 'not_equal':
										if ( $number != $compare_number ) {
											$match = true;
										}

										break;
									case 'greater':
										if ( $number > $compare_number ) {
											$match = true;
										}

										break;
									case 'greater_equal':
										if ( $number >= $compare_number ) {
											$match = true;
										}

										break;
									case 'less':
										if ( $number < $compare_number ) {
											$match = true;
										}

										break;
									case 'less_equal':
										if ( $number <= $compare_number ) {
											$match = true;
										}

										break;
								}
							}
						}

						break;
					case 'product_status':
						global $product;

						if ( $product && is_a( $product, 'WC_Product' ) ) {
							switch ( $condition['value'] ) {
								case 'onsale':
									if ( $product->is_on_sale() ) {
										$match = true;
									}

									break;
								case 'featured':
									if ( $product->is_featured() ) {
										$match = true;
									}

									break;
								case 'instock':
									if ( $product->is_in_stock() ) {
										$match = true;
									}

									break;
								case 'outofstock':
									if ( ! $product->is_in_stock() ) {
										$match = true;
									}

									break;
								case 'backorder':
									if ( $product->is_on_backorder() ) {
										$match = true;
									}

									break;
								case 'managing_stock':
									if ( $product->managing_stock() ) {
										$match = true;
									}

									break;
								case 'sold_individually':
									if ( $product->is_sold_individually() ) {
										$match = true;
									}

									break;
							}
						}

						break;
					default:
						// date & time
						switch ( $condition['type'] ) {
							case 'date_range':
								$date_range_arr = explode( '-', $condition['value'] );

								if ( count( $date_range_arr ) === 2 ) {
									$date_range_start = trim( $date_range_arr[0] );
									$date_range_end   = trim( $date_range_arr[1] );
									$current_date     = strtotime( current_time( 'm/d/Y' ) );

									if ( $current_date >= strtotime( $date_range_start ) && $current_date <= strtotime( $date_range_end ) ) {
										$match = true;
									}
								} elseif ( count( $date_range_arr ) === 1 ) {
									$date_range_start = trim( $date_range_arr[0] );

									if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $date_range_start ) ) {
										$match = true;
									}
								}

								break;
							case 'date_multi':
								$multiple_dates_arr = explode( ', ', $condition['value'] );

								if ( in_array( current_time( 'm/d/Y' ), $multiple_dates_arr ) ) {
									$match = true;
								}

								break;
							case 'date_even':
								if ( (int) current_time( 'd' ) % 2 === 0 ) {
									$match = true;
								}

								break;
							case 'date_odd':
								if ( (int) current_time( 'd' ) % 2 !== 0 ) {
									$match = true;
								}

								break;
							case 'date_on':
								if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'date_before':
								if ( strtotime( current_time( 'm/d/Y' ) ) < strtotime( $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'date_after':
								if ( strtotime( current_time( 'm/d/Y' ) ) > strtotime( $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'date_time_before':
								$current_time = current_time( 'm/d/Y h:i a' );

								if ( strtotime( $current_time ) < strtotime( $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'date_time_after':
								$current_time = current_time( 'm/d/Y h:i a' );

								if ( strtotime( $current_time ) > strtotime( $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'time_range':
								if ( is_array( $condition['value'] ) && ! empty( $condition['value']['start'] ) && ! empty( $condition['value']['end'] ) ) {
									$current_time     = strtotime( current_time( 'm/d/Y h:i a' ) );
									$current_date     = current_time( 'm/d/Y' );
									$time_range_start = $current_date . ' ' . trim( $condition['value']['start'] );
									$time_range_end   = $current_date . ' ' . trim( $condition['value']['end'] );

									if ( $current_time >= strtotime( $time_range_start ) && $current_time <= strtotime( $time_range_end ) ) {
										$match = true;
									}
								}

								break;
							case 'time_before':
								$current_time = current_time( 'm/d/Y h:i a' );
								$current_date = current_time( 'm/d/Y' );

								if ( strtotime( $current_time ) < strtotime( $current_date . ' ' . $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'time_after':
								$current_time = current_time( 'm/d/Y h:i a' );
								$current_date = current_time( 'm/d/Y' );

								if ( strtotime( $current_time ) > strtotime( $current_date . ' ' . $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'weekly_every':
								if ( ! is_array( $condition['value'] ) ) {
									$condition['value'] = (array) $condition['value'];
								}

								$current_d = strtolower( current_time( 'D' ) );

								if ( in_array( $current_d, $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'week_even':
								if ( (int) current_time( 'W' ) % 2 === 0 ) {
									$match = true;
								}

								break;
							case 'week_odd':
								if ( (int) current_time( 'W' ) % 2 !== 0 ) {
									$match = true;
								}

								break;
							case 'week_no':
								if ( ! is_array( $condition['value'] ) ) {
									$condition['value'] = (array) $condition['value'];
								}

								$current_w          = (int) current_time( 'W' );
								$condition['value'] = array_map( 'absint', $condition['value'] );

								if ( in_array( $current_w, $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'monthly_every':
								if ( ! is_array( $condition['value'] ) ) {
									$condition['value'] = (array) $condition['value'];
								}

								$current_j = strtolower( current_time( 'j' ) );

								if ( in_array( $current_j, $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'month_no':
								if ( ! is_array( $condition['value'] ) ) {
									$condition['value'] = (array) $condition['value'];
								}

								$current_m          = (int) current_time( 'm' );
								$condition['value'] = array_map( 'absint', $condition['value'] );

								if ( in_array( $current_m, $condition['value'] ) ) {
									$match = true;
								}

								break;
							case 'every_day':
								$match = true;

								break;
						}
				}

				$match_all &= $match;
			}

			return $match_all;
		}

		function get_messages( $args = [] ) {
			$query_args    = wp_parse_args( $args, [
				'post_type'              => 'wpc_smart_message',
				'post_status'            => 'publish',
				'posts_per_page'         => - 1,
				'update_post_term_cache' => false,
				'no_found_rows'          => true,
				'meta_query'             => [
					'relation' => 'OR',
					[
						'key'     => 'wpcsm_activate',
						'compare' => 'NOT EXISTS'
					],
					[
						'key'     => 'wpcsm_activate',
						'value'   => 'off',
						'compare' => '!='
					]
				]
			] );
			$message_query = new WP_Query( $query_args );

			return $message_query->posts;
		}

		function get_current_roles() {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();

				return array_merge( $user->roles, [ 'wpcsm_user' ] );
			}

			return [ 'wpcsm_guest' ];
		}

		function scripts() {
			// simple-text-rotator
			wp_enqueue_style( 'simple-text-rotator', WPCSM_URI . 'assets/libs/simple-text-rotator/simpletextrotator.css' );
			wp_enqueue_script( 'simple-text-rotator', WPCSM_URI . 'assets/libs/simple-text-rotator/jquery.simple-text-rotator.js', [ 'jquery' ], WPCSM_VERSION, true );

			// wpcsm
			wp_enqueue_style( 'wpcsm-frontend', WPCSM_URI . 'assets/css/frontend.css' );
			wp_enqueue_script( 'wpcsm-frontend', WPCSM_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCSM_VERSION, true );
			wp_add_inline_style( 'wpcsm-frontend', self::inline_css() );
		}

		function inline_css() {
			$inline_css = '';

			foreach ( self::get_messages() as $message ) {
				$message_id = $message->ID;
				$design     = get_post_meta( $message_id, 'wpcsm_design', true ) ?: 'no';
				$custom_css = get_post_meta( $message_id, 'wpcsm_custom_css', true ) ?: '';
				$css        = (array) get_post_meta( $message_id, 'wpcsm_css', true ) ?: [];
				$css        = array_merge( [
					'background' => [ 'color' => '' ],
					'border'     => [ 'style' => '', 'width' => '0', 'color' => '', 'radius' => '0' ],
					'padding'    => [ 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0' ],
					'margin'     => [ 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0' ],
				], $css );

				if ( ( $design === 'custom_css' ) && ! empty( $custom_css ) ) {
					$inline_css .= '.wpcsm-message-' . $message_id . '{' . $custom_css . '}';
				} elseif ( $design === 'css' ) {
					$inline_css .= '.wpcsm-message-' . $message_id . '{
					background-color: ' . $css['background']['color'] . ';
					border-style: ' . $css['border']['style'] . ';
					border-width: ' . absint( $css['border']['width'] ) . 'px;
					border-color: ' . $css['border']['color'] . ';
					border-radius: ' . absint( $css['border']['radius'] ) . 'px;
					padding: ' . absint( $css['padding']['top'] ) . 'px ' . absint( $css['padding']['right'] ) . 'px ' . absint( $css['padding']['bottom'] ) . 'px ' . absint( $css['padding']['left'] ) . 'px;
					margin: ' . absint( $css['margin']['top'] ) . 'px ' . absint( $css['margin']['right'] ) . 'px ' . absint( $css['margin']['bottom'] ) . 'px ' . absint( $css['margin']['left'] ) . 'px;
					}';
				}
			}

			return $inline_css;
		}
	}

	Wpcsm_Frontend::instance();
}