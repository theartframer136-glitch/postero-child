<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpced_Helper' ) ) {
	class Wpced_Helper {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public static function get_shipping_zone( $default_zone_id = 0 ) {
			$shipping_zone = null;

			if ( $shipping_zone == null ) {
				$shipping_zone = self::get_matched_zone();
			}

			if ( $shipping_zone == null ) {
				$shipping_zone = self::get_geo_zone();
			}

			if ( $shipping_zone == null ) {
				$shipping_zone = self::get_default_zone( $default_zone_id );
			}

			return $shipping_zone;
		}

		public static function get_shipping_methods( $default_zone_id = 0 ) {
			$shipping_zone = self::get_shipping_zone( $default_zone_id );

			if ( is_object( $shipping_zone ) ) {
				$methods = $shipping_zone->get_shipping_methods( true );

				return $methods;
			}

			return null;
		}

		public static function get_zone_methods( $zone_id = 0 ) {
			$shipping_zone = WC_Shipping_Zones::get_zone( $zone_id );

			if ( is_object( $shipping_zone ) ) {
				$methods = $shipping_zone->get_shipping_methods( true );

				return $methods;
			}

			return null;
		}

		public static function get_matched_zone() {
			if ( isset( WC()->cart ) ) {
				$shipping_packages = WC()->cart->get_shipping_packages();
				$shipping_zone     = wc_get_shipping_zone( reset( $shipping_packages ) );

				if ( is_object( $shipping_zone ) ) {
					$methods = self::get_zone_methods( $shipping_zone->get_id() );

					if ( ! empty( $methods ) ) {
						return $shipping_zone;
					}

					return null;
				}
			}

			return null;
		}

		public static function get_geo_zone() {
			$destination   = self::ip_based_destination();
			$shipping_zone = WC_Shipping_Zones::get_zone_matching_package( $destination );

			if ( is_object( $shipping_zone ) ) {
				$methods = self::get_zone_methods( $shipping_zone->get_id() );

				if ( ! empty( $methods ) ) {
					return $shipping_zone;
				}

				return null;
			}

			return null;
		}

		public static function get_default_zone( $zone_id = 0 ) {
			$shipping_zone = WC_Shipping_Zones::get_zone( $zone_id );

			if ( is_object( $shipping_zone ) ) {
				$methods = self::get_zone_methods( $shipping_zone->get_id() );

				if ( ! empty( $methods ) ) {
					return $shipping_zone;
				}

				return null;
			}

			return null;
		}

		public static function get_selected_method( $get_name = false ) {
			$selected_shipping_method = [];

			if ( isset( WC()->session ) ) {
				$selected_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
			}

			if ( isset( $selected_shipping_method[0] ) && $selected_shipping_method[0] !== false ) { // flat_rate:19
				$method = explode( ":", $selected_shipping_method[0] );

				if ( $get_name ) {
					return $method[0];
				} else {
					return $method[1] ?? null;
				}
			}

			return null;
		}

		public static function ip_based_destination() {
			$geo_obj  = new WC_Geolocation();
			$user_ip  = $geo_obj->get_ip_address();
			$user_geo = $geo_obj->geolocate_ip( $user_ip );

			$destination['destination']['country']  = $user_geo['country'];
			$destination['destination']['state']    = $user_geo['state'];
			$destination['destination']['postcode'] = "";

			return $destination;
		}

		public static function sanitize_array( $arr ) {
			foreach ( (array) $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
				}
			}

			return $arr;
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'wpced_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'wpced_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'wpced_generate_key', $key );
		}
	}

	function Wpced_Helper() {
		return Wpced_Helper::instance();
	}
}
