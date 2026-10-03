<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's class-cr-referrals.php. Kept: the referral_session
 * query var, the cr_referral_session cookie it sets, and copying that cookie to
 * new orders. Left out: reporting paid orders to CusRev, which needs option
 * ivole_referrals_tracking (default 'no') and a CusRev licence key.
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Referrals' ) ) :

	class CR_Referrals {
		public function __construct() {
			// Track referrals
			add_filter( 'query_vars', array( $this, 'referral_session' ) );
			add_action( 'parse_query', array( $this, 'check_referral' ) );
			// Trigger for new order
			add_action( 'woocommerce_new_order', array( $this, 'update_order_meta' ), 10, 2 );
		}

		public function referral_session( $qvars ) {
			$qvars[] = 'referral_session';
			return $qvars;
		}

		public function check_referral( $wp_query ) {
			$referral_session = get_query_var( 'referral_session', '' );
			$expires = 30 * DAY_IN_SECONDS; // 30 days
			$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : parse_url( get_option( 'siteurl' ), PHP_URL_HOST );

			if( $referral_session ) {
				//error_log( print_r( $referral_session, true ) );
				setcookie( 'cr_referral_session', strval( $referral_session ), array(
					'expires' => time() + $expires,
					'path' => '/',
					'domain' => $domain,
					'samesite' => 'Lax' )
				);
			}
		}

		public function update_order_meta( $order_id, $order ) {
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order && isset( $_COOKIE['cr_referral_session'] ) ) {
					// If the referral cookie is present, save it as a meta field in the order
					$order->update_meta_data( '_cr_referral_session', sanitize_text_field( $_COOKIE['cr_referral_session'] ) );
					$order->save();
				}
			}
		}
	}

endif;
