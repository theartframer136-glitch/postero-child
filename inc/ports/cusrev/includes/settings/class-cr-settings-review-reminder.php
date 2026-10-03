<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/settings/class-cr-settings-review-reminder.php. Kept: only
 * get_auto_show_consent(), which CR_Checkout's constructor can call. Left out:
 * the settings screen.
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Review_Reminder_Settings' ) ):

	class CR_Review_Reminder_Settings {

		public static function get_auto_show_consent() {
			$auto_consent = true;
			if ( class_exists( 'WC_Countries' ) ) {
				$countries = new WC_Countries();
				$eu_countries = $countries->get_european_union_countries();
				$shop_country = wc_get_base_location();
				if (
					$shop_country &&
					is_array( $shop_country ) &&
					$shop_country['country']
				) {
					if ( ! in_array( $shop_country['country'], $eu_countries ) ) {
						$auto_consent = false;
					}
				}
			}
			return $auto_consent;
		}
	}

endif;
