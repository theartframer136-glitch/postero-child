<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_XML_Feeds' ) ) :

	/**
	* Class to generate XML feeds for Google Shopping
	*
	* @since 4.33
	*/
	class CR_XML_Feeds {

		public function __construct() {
			//daily hook
			add_action( 'cr_generate_prod_feed', array( $this, 'generate_google_shopping_prod_feed' ) );
			//chunk hook
			add_action( 'cr_generate_prod_feed_chunk', array( $this, 'generate_google_shopping_prod_feed_chunk' ) );
			//reviews daily hook
			add_action( 'cr_generate_feed', array( $this, 'generate_google_shopping_reviews_feed' ) );
			//reviews chunk hook
			add_action( 'cr_generate_product_reviews_feed_chunk', array( $this, 'generate_google_shopping_reviews_feed_chunk' ) );
			// refresh frequency
			add_filter( 'cron_schedules', array( $this, 'cron_refresh_frequency' ) );
		}

		public function generate_google_shopping_prod_feed() {
			// WPML compatibility to create feeds in different languages
			$languages = array();
			if ( has_filter( 'wpml_active_languages' ) ) {
				$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 1 ) );
			}
			if (
				! empty( $languages ) &&
				is_array( $languages ) &&
				0 < count( $languages )
			) {
				foreach ( $languages as $lang ) {
					if ( isset( $lang['language_code'] ) ) {
						$feed = new CR_Google_Shopping_Prod_Feed( $lang['language_code'] );
						$feed->start_cron();
						$feed->generate();
					}
				}
			} else {
				$feed = new CR_Google_Shopping_Prod_Feed();
				$feed->start_cron();
				$feed->generate();
			}
		}

		public function generate_google_shopping_prod_feed_chunk( $lang = '' ) {
			if ( $lang ) {
				$feed = new CR_Google_Shopping_Prod_Feed( $lang );
			} else {
				$feed = new CR_Google_Shopping_Prod_Feed();
			}
			$feed->generate();
		}

		public function generate_google_shopping_reviews_feed() {
			// WPML compatibility to create feeds in different languages
			$languages = array();
			if ( has_filter( 'wpml_active_languages' ) ) {
				$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 1 ) );
			}
			if (
				! empty( $languages ) &&
				is_array( $languages ) &&
				0 < count( $languages )
			) {
				foreach ( $languages as $lang ) {
					if ( isset( $lang['language_code'] ) ) {
						$feed = new CR_Google_Shopping_Feed( $lang['language_code'] );
						$feed->start_cron();
						$feed->generate();
					}
				}
			} else {
				$feed = new CR_Google_Shopping_Feed();
				$feed->start_cron();
				$feed->generate();
			}
		}

		public function generate_google_shopping_reviews_feed_chunk( $lang = '' ) {
			if ( $lang ) {
				$feed = new CR_Google_Shopping_Feed( $lang );
			} else {
				$feed = new CR_Google_Shopping_Feed();
			}
			$feed->generate();
		}

		public function cron_refresh_frequency( $schedules ) {
			$days = intval( get_option( 'ivole_feed_refresh', 1 ) );
			if ( 1 > $days ) {
				$days = 1;
			}
			$schedules['cr_xml_refresh'] = array(
				'interval' => $days * DAY_IN_SECONDS,
				'display' => sprintf( _n( 'Once in %d day', 'Once in %d days', $days, 'customer-reviews-woocommerce' ), $days )
			);
			return $schedules;
		}

	}

endif;
