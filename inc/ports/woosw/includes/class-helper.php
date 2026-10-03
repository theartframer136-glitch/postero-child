<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woosw_Helper' ) ) {
	class Woosw_Helper {
		protected static $settings = [];
		protected static $localization = [];
		protected static $products = [];
		protected static $key = null;
		protected static $ids = [];
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			self::$settings     = (array) get_option( 'woosw_settings', [] );
			self::$localization = (array) get_option( 'woosw_localization', [] );
		}

		public static function get_settings() {
			return apply_filters( 'woosw_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( 'woosw_' . $name, $default );
			}

			return apply_filters( 'woosw_get_setting', $setting, $name, $default );
		}

		public static function localization( $key = '', $default = '' ) {
			$str = '';

			if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
				$str = self::$localization[ $key ];
			} elseif ( ! empty( $default ) ) {
				$str = $default;
			}

			return esc_html( apply_filters( 'woosw_localization_' . $key, $str ) );
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'woosw_key_characters', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'woosw_key_length', 6 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			return apply_filters( 'woosw_generate_key', $key );
		}

		public static function can_edit( $key ) {
			if ( is_user_logged_in() ) {
				if ( get_user_meta( get_current_user_id(), 'woosw_key', true ) === $key ) {
					return true;
				}

				if ( ( $keys = get_user_meta( get_current_user_id(), 'woosw_keys', true ) ) && isset( $keys[ $key ] ) ) {
					return true;
				}
			} else {
				if ( isset( $_COOKIE['woosw_key'] ) && ( sanitize_text_field( $_COOKIE['woosw_key'] ) === $key ) ) {
					return true;
				}
			}

			return false;
		}

		public static function get_page_id() {
			if ( self::get_setting( 'page_id' ) ) {
				return absint( self::get_setting( 'page_id' ) );
			}

			return false;
		}

		public static function get_key( $new = false ) {
			if ( $new ) {
				// get a new key for multiple wishlist
				$key = self::generate_key();

				while ( self::exists_key( $key ) ) {
					$key = self::generate_key();
				}

				return $key;
			} else {
				if ( ! is_null( self::$key ) ) {
					return self::$key;
				}

				if ( ! is_user_logged_in() && ( self::get_setting( 'disable_unauthenticated', 'no' ) === 'yes' ) ) {
					return self::$key = '#';
				}

				if ( is_user_logged_in() && ( ( $user_id = get_current_user_id() ) > 0 ) ) {
					$key = get_user_meta( $user_id, 'woosw_key', true );

					if ( empty( $key ) ) {
						$key = self::generate_key();

						while ( self::exists_key( $key ) ) {
							$key = self::generate_key();
						}

						// set a new key
						update_user_meta( $user_id, 'woosw_key', $key );

						// multiple wishlist
						update_user_meta( $user_id, 'woosw_keys', [
							$key => [
								'type' => 'primary',
								'name' => '',
								'time' => '',
							],
						] );
					}

					return self::$key = $key;
				}

				if ( isset( $_COOKIE['woosw_key'] ) ) {
					return self::$key = sanitize_text_field( $_COOKIE['woosw_key'] );
				}

				return self::$key = 'WOOSW';
			}
		}

		public static function exists_key( $key ) {
			if ( get_option( 'woosw_list_' . $key ) ) {
				return true;
			}

			return false;
		}

		public static function get_ids( $key = null ) {
			if ( ! $key ) {
				$key = self::get_key();
			}

			if ( isset( self::$ids[ $key ] ) ) {
				return self::$ids[ $key ];
			}

			return self::$ids[ $key ] = (array) apply_filters( 'woosw_get_ids', get_option( 'woosw_list_' . $key, [] ), $key );
		}

		public static function clear_internal_cache( $key = null ) {
			if ( $key ) {
				unset( self::$ids[ $key ] );
			} else {
				self::$key = null;
				self::$ids = [];
			}
		}

		public static function get_products() {
			return self::$products;
		}

		public static function set_products( $products ) {
			self::$products = $products;
		}

		public static function get_url( $key = null, $full = false ) {
			$url = home_url( '/' );

			if ( $page_id = self::get_page_id() ) {
				if ( $full ) {
					if ( ! $key ) {
						$key = self::get_key();
					}

					if ( get_option( 'permalink_structure' ) !== '' ) {
						$url = trailingslashit( get_permalink( $page_id ) ) . $key;
					} else {
						$url = get_permalink( $page_id ) . '&woosw_id=' . $key;
					}
				} else {
					$url = get_permalink( $page_id );
				}
			}

			return esc_url( apply_filters( 'woosw_wishlist_url', $url, $key, $full ) );
		}

		public static function get_count( $key = null ) {
			if ( ! $key ) {
				$key = self::get_key();
			}

			$products = self::get_ids( $key );
			$count    = count( $products );

			return esc_html( apply_filters( 'woosw_wishlist_count', $count, $key ) );
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
	}

	return Woosw_Helper::instance();
}
