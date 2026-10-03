<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Reviews_Top_Charts' ) ) :

class CR_Reviews_Top_Charts {

	public static function count_ratings( $rating ) {
		$count_ratings = 0;

		// WPML compatibility
		if ( has_filter( 'wpml_current_language' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				remove_filter( 'comments_clauses', array( $sitepress, 'comments_clauses' ), 10, 2 );
			}
		}

		// count product reviews
		$args = array(
			'number'       => '',
			'parent'       => 0,
			'count'        => true,
			'type__not_in' => array( 'cr_qna' ),
			'meta_key'     => 'rating',
			'lang'         => '' // Polylang compatibility
		);
		if ( $rating > 0 ) {
			$args['meta_value'] = intval( $rating );
		}
		// add_filter( 'comments_clauses', array( 'CR_Reviews_List_Table', 'filter_include_shop_reviews' ), 10, 1 );
		$count_ratings = get_comments( $args );

		// WPML compatibility
		if ( has_filter( 'wpml_current_language' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				add_filter( 'comments_clauses', array( $sitepress, 'comments_clauses' ), 10, 2 );
			}
		}

		return $count_ratings;
	}

	public static function count_ratings_by_source( $source ) {
		$count_ratings = 0;

		// WPML compatibility
		if ( has_filter( 'wpml_current_language' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				remove_filter( 'comments_clauses', array( $sitepress, 'comments_clauses' ), 10, 2 );
			}
		}

		// count product reviews
		$args = array(
			'number'       => '',
			'parent'       => 0,
			'count'        => true,
			'type__not_in' => array( 'cr_qna' ),
			'meta_key'     => ['rating'],
			'lang'         => '' // Polylang compatibility
		);
		if ( $source ) {
			$args['meta_query'][] = array(
				'key' => $source,
				'compare' => 'EXISTS'
			);
		}
		add_filter( 'comments_clauses', array( 'CR_Reviews_List_Table', 'filter_include_shop_reviews' ), 10, 1 );
		$count_ratings = get_comments( $args );

		// WPML compatibility
		if ( has_filter( 'wpml_current_language' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				add_filter( 'comments_clauses', array( $sitepress, 'comments_clauses' ), 10, 2 );
			}
		}

		return $count_ratings;
	}

	public static function get_reviews_top_row_stats() {
		check_ajax_referer( 'cr-reviews-top-row', 'cr_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			die( '-2' );
		}

		$five  = self::count_ratings(5);
		$four  = self::count_ratings(4);
		$three = self::count_ratings(3);
		$two   = self::count_ratings(2);
		$one   = self::count_ratings(1);

		$all = $five + $four + $three + $two + $one;

		if ( 0 < $all ) {
			$five  = (float) $five;
			$four  = (float) $four;
			$three = (float) $three;
			$two   = (float) $two;
			$one   = (float) $one;

			$five_prcnt   = floor( $five / $all * 100 );
			$five_rnd     = $five / $all * 100 - $five_prcnt;
			$four_prcnt   = floor( $four / $all * 100 );
			$four_rnd     = $four / $all * 100 - $four_prcnt;
			$three_prcnt  = floor( $three / $all * 100 );
			$three_rnd    = $three / $all * 100 - $three_prcnt;
			$two_prcnt    = floor( $two / $all * 100 );
			$two_rnd      = $two / $all * 100 - $two_prcnt;
			$one_prcnt    = floor( $one / $all * 100 );
			$one_rnd      = $one / $all * 100 - $one_prcnt;

			$hundred = $five_prcnt + $four_prcnt + $three_prcnt + $two_prcnt + $one_prcnt;
			if ( $hundred < 100 ) {
				$to_distribute = 100 - $hundred;
				$roundings = array( '5' => $five_rnd, '4' => $four_rnd, '3' => $three_rnd, '2' => $two_rnd, '1' => $one_rnd );
				arsort($roundings);
				$roundings = array_filter( $roundings, function( $value ) {
					return $value > 0;
				} );
				while( $to_distribute > 0 && count( $roundings ) > 0 ) {
					foreach( $roundings as $key => $value ) {
						if ( $to_distribute > 0 ) {
							switch( $key ) {
								case 5:
									$five_prcnt++;
									break;
								case 4:
									$four_prcnt++;
									break;
								case 3:
									$three_prcnt++;
									break;
								case 2:
									$two_prcnt++;
									break;
								case 1:
									$one_prcnt++;
									break;
								default:
									break;
							}
							$to_distribute--;
						} else {
							break;
						}
					}
				}
			}
			$average = ( 5 * $five + 4 * $four + 3 * $three + 2 * $two + 1 * $one ) / $all;
		} else {
			$five_prcnt = $five = 0;
			$four_prcnt = $four = 0;
			$three_prcnt = $three = 0;
			$two_prcnt = $two = 0;
			$one_prcnt = $one = 0;
			$average = 0;
		}

		// count reviews by source
		$via_cusrev      = self::count_ratings_by_source( 'ivole_order' );
		$via_cusrev_priv = self::count_ratings_by_source( 'ivole_order_priv' );
		$via_cusrev_unve = self::count_ratings_by_source( 'ivole_order_unve' );
		$via_local       = self::count_ratings_by_source( 'ivole_order_locl' );
		$via_any         = self::count_ratings_by_source( '' );

		$total = $via_any;

		$sources = array(
			'via_reminders' => (object) array(
				'label' => __( 'In response to reminders', 'customer-reviews-woocommerce' ),
				'count' => $via_cusrev + $via_cusrev_priv + $via_cusrev_unve + $via_local,
				'part' => 0,
				'class' => 'chartColor1'
			),
			'via_onsite' => (object) array(
				'label' => __( 'On-site reviews and other', 'customer-reviews-woocommerce' ),
				'count' => $via_any - $via_cusrev - $via_cusrev_priv - $via_cusrev_unve - $via_local,
				'part' => 0,
				'class' => 'chartColor2'
			)
		);

		// calculate percentage of different statuses
		if ( 0 < $total ) {
			foreach ( $sources as $source ) {
				$source->part = round( $source->count / $via_any * 100 );
				$source->count = number_format_i18n( $source->count );
			}
		}

		// collect information for the status card
		$verified_reviews = get_option( 'ivole_verified_reviews', 'no' );
		$live_mode = get_option( 'ivole_verified_live_mode', 'no' );
		$auto_reminders = get_option( 'ivole_enable', 'no' );
		$manual_reminders = get_option( 'ivole_enable_manual', 'no' );
		//
		$reviewReminderIcon = 'IconServer';
		$reviewReminderLabel = 'Local';
		$reviewReminderHelp = __(
			'Review reminders are configured to invite customers to review their orders using aggregated review forms hosted locally on your website. This setup is ideal for meeting personal data protection requirements, as customer data does not leave your website. You can adjust this configuration in the plugin settings.',
			'customer-reviews-woocommerce'
		);
		$reviewReminderHelpLinks = array();
		$reviewReminderHelpLinks[] = (object) array(
			'icon' => 'IconAdjustments',
			'link' => admin_url( 'admin.php?page=cr-reviews-settings&tab=review_reminder' ),
			'label' => __( 'Review reminders', 'customer-reviews-woocommerce' )
		);
		if ( 'yes' === $verified_reviews ) {
			$reviewReminderLabel = 'CusRev';
			if ( 'yes' === $live_mode ) {
				$reviewReminderIcon = 'IconBroadcast';
				$reviewReminderHelp = __(
					'Review reminders are configured to invite customers to review their orders using aggregated review forms hosted on CusRev.com. These reviews are collected in "Live" mode, meaning they will be publicly published both on CusRev.com and your website. You can adjust this configuration in the plugin settings.',
					'customer-reviews-woocommerce'
				);
			} else {
				$reviewReminderIcon = 'IconLockFilled';
				$reviewReminderHelp = __(
					'Review reminders are configured to invite customers to review their orders using aggregated review forms hosted on CusRev.com. These reviews are collected in "Test" mode, which means they will only be displayed on your website but not on CusRev.com. The "Test" mode is intended for setting up and testing the integration of the plugin with CusRev.com. You can adjust this configuration in the plugin settings.',
					'customer-reviews-woocommerce'
				);
			}
			$reviewReminderHelpLinks[] = (object) array(
				'icon' => 'IconAdjustments',
				'link' => admin_url( 'admin.php?page=cr-reviews-settings&tab=cusrev' ),
				'label' => 'CusRev.com'
			);
		}
		//
		$reminderSendingIcon = 'IconSendOff';
		$reminderSendingLabel = __( 'Off', 'customer-reviews-woocommerce' );
		$reminderSendingHelp = __(
			'Review reminders are switched off. Enabling them can help you gather more authentic feedback from your customers about the products, services, and your store.',
			'customer-reviews-woocommerce'
		);
		$reminderSendingHelpLinks = array();
		$reminderSendingHelpLinks[] = (object) array(
			'icon' => 'IconAdjustments',
			'link' => admin_url( 'admin.php?page=cr-reviews-settings&tab=review_reminder' ),
			'label' => __( 'Review reminders', 'customer-reviews-woocommerce' )
		);
		if ( 'yes' === $auto_reminders ) {
			$reminderSendingIcon = 'IconSend';
			$reminderSendingLabel = __( 'Automatic', 'customer-reviews-woocommerce' );
			$reminderSendingHelp = __(
				'Review reminders are sent automatically with a delay and parameters defined in the settings.',
				'customer-reviews-woocommerce'
			);
		} elseif ( 'yes' === $manual_reminders ) {
			$reminderSendingIcon = 'IconHandClick';
			$reminderSendingLabel = __( 'Manual', 'customer-reviews-woocommerce' );
			$reminderSendingHelp = __(
				'Review reminders can be manually triggered from the WooCommerce Orders page by clicking the envelope button next to each order.',
				'customer-reviews-woocommerce'
			);
			$reminderSendingHelpLinks[] = (object) array(
				'icon' => 'IconShoppingCart',
				'link' => admin_url( 'admin.php?page=wc-orders' ),
				'label' => __( 'Orders', 'customer-reviews-woocommerce' )
			);
		}


		wp_send_json(
			array(
				'average' => number_format_i18n( $average, 1 ),
				'ratings' => array_values( array(
					5 => (object) array(
						'label' => '5',
						'count' => html_entity_decode( number_format_i18n( $five, 0 ) ),
						'part'  => $five_prcnt,
						'class' => 'chartColor1'
					),
					4 => (object) array(
						'label' => '4',
						'count' => html_entity_decode( number_format_i18n( $four, 0 ) ),
						'part' => $four_prcnt,
						'class' => 'chartColor2'
					),
					3 => (object) array(
						'label' => '3',
						'count' => html_entity_decode( number_format_i18n( $three, 0 ) ),
						'part' => $three_prcnt,
						'class' => 'chartColor3'
					),
					2 => (object) array(
						'label' => '2',
						'count' => html_entity_decode( number_format_i18n( $two, 0 ) ),
						'part' => $two_prcnt,
						'class' => 'chartColor4'
					),
					1 => (object) array(
						'label' => '1',
						'count' => html_entity_decode( number_format_i18n( $one, 0 ) ),
						'part' => $one_prcnt,
						'class' => 'chartColor5'
					)
				) ),
				'total' => html_entity_decode( number_format_i18n( $via_any, 0 ) ),
				'sources' => array_values( $sources ),
				'status' => array(
					'reviewReminder' => (object) array(
						'icon' => $reviewReminderIcon,
						'label' => $reviewReminderLabel,
						'help' => $reviewReminderHelp,
						'helpLinks' => $reviewReminderHelpLinks
					),
					'reminderSending' => (object) array(
						'icon' => $reminderSendingIcon,
						'label' => $reminderSendingLabel,
						'help' => $reminderSendingHelp,
						'helpLinks' => $reminderSendingHelpLinks
					)
				)
			)
		);
	}

	public static function get_reviews_top_row_refs() {
		check_ajax_referer( 'cr-reviews-top-row', 'cr_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			die( '-2' );
		}

		$countReferralViews = 0;
		$countReferralClicks = 0;
		$referralSalesAmount = 0;

		// check if there are cached stats
		$cachedFormReferralStats = get_transient( 'cr_form_referrals_stats' );
		if (
			// cache is available
			false !== $cachedFormReferralStats &&
			is_object( $cachedFormReferralStats ) &&
			property_exists( $cachedFormReferralStats, 'views' ) &&
			property_exists( $cachedFormReferralStats, 'clicks' ) &&
			property_exists( $cachedFormReferralStats, 'sales' )
		) {
			$countReferralViews = $cachedFormReferralStats->views + 0;
			$countReferralClicks = $cachedFormReferralStats->clicks + 0;
			$referralSalesAmount = $cachedFormReferralStats->sales + 0;
		} else {
			// there are no cached stats
			$licenseKey = get_option( 'ivole_license_key', '' );
			$cusrev_hosted = 'yes' === get_option( 'ivole_verified_reviews', 'no' ) ? true : false;
			$cusrev_stats = null;
			// if there is a license key, try to retrieve stats for CusRev-hosted review forms
			if ( $licenseKey ) {
				$cusrev_stats = self::get_cusrev_referral_stats( $licenseKey );
				if ( $cusrev_hosted && ! $cusrev_stats ) {
					die( '-4' );
				}
			} else {
				// if the pluging is using CusRev-hosted review forms, return an error
				if ( $cusrev_hosted ) {
					die( '-3' );
				}
			}
			// irrespective of license key, try to retrieve stats for self-hosted review forms
			$local_stats = self::get_local_referral_stats();
			// sum up cusrev and local stats
			if ( $cusrev_stats ) {
				$local_stats['views'] += $cusrev_stats['views'];
				$local_stats['clicks'] += $cusrev_stats['clicks'];
			}
			$countReferralViews = $local_stats['views'];
			$countReferralClicks = $local_stats['clicks'];
			// calculate referral sales
			$referralSalesAmount = self::get_orders_referrals();
			// save cache
			set_transient(
				'cr_form_referrals_stats',
				(object) array(
					'views' => $countReferralViews,
					'clicks' => $countReferralClicks,
					'sales' => $referralSalesAmount
				),
				DAY_IN_SECONDS
			);
		}
		// send results to the frontend
		wp_send_json(
			array(
				'referralViews' => (object) array(
					'label' => __( 'Views', 'customer-reviews-woocommerce' ),
					'help' => __( 'The number of times your store\'s products were displayed as recommendations on aggregated review forms.', 'customer-reviews-woocommerce' ),
					'helpLinks' => (object) array(
						'link' => 'https://help.cusrev.com/support/solutions/articles/43000592925-product-recommendations-on-review-forms',
						'label' => __( 'Product Recommendations on Review Forms', 'customer-reviews-woocommerce' )
					),
					'count' => html_entity_decode( number_format_i18n( $countReferralViews, 0 ) )
				),
				'referralClicks' => (object) array(
					'label' => __( 'Clicks', 'customer-reviews-woocommerce' ),
					'help' => __( 'The number of times customers clicked on your store\'s product recommendations shown on aggregated review forms.', 'customer-reviews-woocommerce' ),
					'helpLinks' => (object) array(
						'link' => 'https://help.cusrev.com/support/solutions/articles/43000592925-product-recommendations-on-review-forms',
						'label' => __( 'Product Recommendations on Review Forms', 'customer-reviews-woocommerce' )
					),
					'count' => html_entity_decode( number_format_i18n( $countReferralClicks, 0 ) )
				),
				'referralSales' => (object) array(
					'label' => __( 'Sales', 'customer-reviews-woocommerce' ),
					'help' => __( 'Revenue attributed to purchases influenced by product recommendations shown on aggregated review forms.', 'customer-reviews-woocommerce' ),
					'helpLinks' => (object) array(
						'link' => 'https://help.cusrev.com/support/solutions/articles/43000592925-product-recommendations-on-review-forms',
						'label' => __( 'Product Recommendations on Review Forms', 'customer-reviews-woocommerce' )
					),
					'amount' => html_entity_decode( wc_price( $referralSalesAmount, array(
						'in_span' => false
					) ) )
				)
			)
		);
	}

	public static function get_orders_referrals() {
		$orders = wc_get_orders( array(
			'status'   => wc_get_is_paid_statuses(), // completed, processing, etc.
			'limit'    => -1,
			'return'   => 'ids',
			'meta_key' => '_cr_referral_session',
		) );

		$total = 0.0;

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$total += (float) $order->get_total();
		}

		return $total;
	}

	public static function get_cusrev_referral_stats( $license_key ) {
		$data = array(
			'shopDomain' => Ivole_Email::get_blogurl(),
			'licenseKey' => $license_key
		);
		$api_url = CR_Utils::cr_api_url() . '/v2/track-referrals';
		$data_string = json_encode($data);
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $api_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen( $data_string ) )
		);
		$result = curl_exec( $ch );
		$httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( 200 === $httpcode ) {
			$result = json_decode( $result );
			if (
				is_object( $result ) &&
				property_exists( $result, 'formsReferrals' )
			) {
				if (
					property_exists( $result->formsReferrals, 'countViews' ) &&
					property_exists( $result->formsReferrals, 'countClicks' )
				) {
					return array(
						'views'  => $result->formsReferrals->countViews + 0,
						'clicks' => $result->formsReferrals->countClicks + 0
					);
				}
			}
		}
		return false;
	}

	public static function get_local_referral_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . CR_Local_Forms_Ajax::RECOMMENDATION_EVENTS_TABLE;

		$stats = array(
			'views'  => 0,
			'clicks' => 0,
		);

		// handle missing table
		if ( $wpdb->get_var(
			$wpdb->prepare( "SHOW TABLES LIKE %s", $table_name )
		) !== $table_name ) {
			return $stats;
		}

		$results = $wpdb->get_results(
			"
			SELECT
				event_type,
				COUNT(*) AS cnt
			FROM {$table_name}
			WHERE event_type IN ('view', 'click')
			GROUP BY event_type
			",
			ARRAY_A
		);

		foreach ( $results as $row ) {
			if ( $row['event_type'] === 'view' ) {
				$stats['views'] = (int) $row['cnt'];
			} elseif ( $row['event_type'] === 'click' ) {
				$stats['clicks'] = (int) $row['cnt'];
			}
		}

		return $stats;
	}

}

endif;
