<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/emails/class-cr-sender.php. Kept: the two order
 * hooks that run whatever the settings: when an order is refunded or cancelled
 * the plugin clears any scheduled review reminder for it (there are none here)
 * and adds a private order note saying so. Left out: scheduling and sending
 * review reminders (option ivole_enable, default 'no').
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Sender' ) ) :

	class CR_Sender {
		public function __construct() {
			// Trigger for refunded orders
			add_action( 'woocommerce_order_status_refunded', array( $this, 'refund_trigger' ), 20, 1 );
			// Trigger for cancelled orders
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancellation_trigger' ), 20, 1 );
		}

		public function refund_trigger( $order_id ) {
			if( $order_id ) {
				$order = new WC_Order( $order_id );
				wp_clear_scheduled_hook( 'ivole_send_reminder', array( $order_id ) );
				$order->add_order_note( __( 'CR: a review reminder was cancelled because the order was refunded.', 'customer-reviews-woocommerce' ) );
				do_action( 'cr_wp_reminder_refund', $order_id );
			}
		}

		public function cancellation_trigger( $order_id ) {
			if( $order_id ) {
				$order = new WC_Order( $order_id );
				wp_clear_scheduled_hook( 'ivole_send_reminder', array( $order_id ) );
				$order->add_order_note( __( 'CR: a review reminder was cancelled because the order was cancelled.', 'customer-reviews-woocommerce' ) );
				do_action( 'cr_wp_reminder_cancellation', $order_id );
			}
		}

	}

endif;
