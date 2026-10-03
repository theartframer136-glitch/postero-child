<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Copy_Link' ) ) :

class CR_Copy_Link {

	public $to;
	public $language;
	public $form_header;
	public $form_body;
	public $find = array();
	public $replace = array();
	/**
	 * Constructor.
	 */
	public function __construct( $order_id = 0 ) {
		$this->form_header = strval( get_option( 'ivole_form_header', __( 'How did we do?', 'customer-reviews-woocommerce' ) ) );
		$this->form_body = strval( get_option( 'ivole_form_body', __( 'Please review your experience with products and services that you purchased at {site_title}.', 'customer-reviews-woocommerce' ) ) );
		$this->find['site-title'] = '{site_title}';
		$this->replace['site-title'] = Ivole_Email::get_blogname();
		$this->find['customer-first-name']  = '{customer_first_name}';
		$this->find['customer-last-name'] = '{customer_last_name}';
		$this->find['customer-name'] = '{customer_name}';
		$this->find['order-id'] = '{order_id}';
		$this->find['order-date'] = '{order_date}';
		$this->find['list-products'] = '{list_products}';
		$this->find['review-form'] = '{review_form}';
		$this->find['coupon-code'] = '{coupon_code}';
		$this->find['discount-amount'] = '{discount_amount}';

		$this->language = Ivole_Email::fetch_language();

		$order = false;
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
		}

		//WPML integration
		if ( has_filter( 'wpml_translate_single_string' ) && defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
			$wpml_current_language = apply_filters( 'wpml_current_language', NULL );
			if ( $order ) {
				$wpml_current_language = $order->get_meta( 'wpml_language', true );
			}
			$this->form_header = apply_filters( 'wpml_translate_single_string', $this->form_header, 'ivole', 'ivole_form_header', $wpml_current_language );
			$this->form_body = apply_filters( 'wpml_translate_single_string', $this->form_body, 'ivole', 'ivole_form_body', $wpml_current_language );
			if ( $wpml_current_language ) {
				$this->language = strtoupper( $wpml_current_language );
			}
		}

		//Polylang integration
		if( function_exists( 'pll_current_language' ) && function_exists( 'pll_get_post_language' ) && function_exists( 'pll_translate_string' ) ) {
			$polylang_current_language = pll_current_language();
			if( $order_id ) {
				$polylang_current_language = pll_get_post_language( $order_id );
			}
			$this->form_header = pll_translate_string( $this->form_header, $polylang_current_language );
			$this->form_body = pll_translate_string( $this->form_body, $polylang_current_language );
			$this->language = strtoupper( $polylang_current_language );
		}

		// TranslatePress integration
		if( function_exists( 'trp_translate' ) ) {
			$trp_order_language = '';
			if ( $order ) {
				$trp_order_language = $order->get_meta( 'trp_language', true );
			}
			if( $trp_order_language ) {
				$this->form_header = trp_translate( $this->form_header, $trp_order_language, false );
				$this->form_body = trp_translate( $this->form_body, $trp_order_language, false );
				$this->language = strtoupper( substr( $trp_order_language, 0, 2 ) );
			}
		}

		//a safety check if some translation plugin removed language
		if ( empty( $this->language ) || 'WPML' === $this->language ) {
			$this->language = 'EN';
		}

		// map language codes returned by translation plugins that include '-' like PT-PT
		$this->language = CR_Email_Func::cr_map_language( $this->language );
	}

	public function get_review_form( $order_id ) {
		$data_for_sending = $this->get_data_for_sending( $order_id );

		if ( 0 === $data_for_sending[0] ) {
			$data = $data_for_sending[1];
			$form_result = CR_Local_Forms::save_form(
				$data['order']['id'],
				array(
					'firstname' => $data['customer']['firstname'],
					'lastname' => $data['customer']['lastname'],
					'email' => $data['email']['to'],
				),
				$data['form']['header'],
				$data['form']['description'],
				$data['order']['items'],
				( 0 === $order_id ? true : false ),
				$data['language'],
				null
			);

			if ( 0 !== $form_result['code'] ) {
				return array( 9, 'Error: ' . $form_result['text'] );
			}

			return array(
				0,
				esc_url( $form_result['text'] )
			);
		} else {
			return $data_for_sending;
		}
	}

	public function get_data_for_sending( $order_id ) {
		$comment_required = get_option( 'ivole_form_comment_required', 'no' );
		if( 'no' === $comment_required ) {
			$comment_required = 0;
		} else {
			$comment_required = 1;
		}

		$shop_rating = 'yes' === get_option( 'ivole_form_shop_rating', 'no' ) ? true : false;
		$allowMedia = 'yes' === get_option( 'ivole_form_attach_media', 'no' ) ? true : false;
		$ratingBar = 'star' === get_option( 'ivole_form_rating_bar', 'smiley' ) ? 'star' : 'smiley';
		$geolocation = 'yes' === get_option( 'ivole_form_geolocation', 'no' ) ? true : false;

		if ( $order_id ) {

			$order = wc_get_order( $order_id );
			if ( ! $order  ) {
				// if no order exists with the provided $order_id, then we cannot create a review form
				return array( 1, sprintf( __( 'Error: order %s does not exist', 'customer-reviews-woocommerce' ), $order_id ) );
			}

			//check if Limit Number of Reviews option is used
			if( 'yes' === get_option( 'ivole_limit_reminders', 'yes' ) ) {
				//check how many reminders have already been sent for this order (if any)
				$reviews = $order->get_meta( '_ivole_review_reminder', true );
				if( $reviews >= 1 ) {
					//if more than one, then we should not create a review form
					return array( 2, __( 'Error: an option to limit review reminders is enabled in the settings', 'customer-reviews-woocommerce' ) );
				}
			}

			//check if registered customers option is used
			$registered_customers = false;
			if( 'yes' === get_option( 'ivole_registered_customers', 'no' ) ) {
				$registered_customers = true;
			}

			//check customer roles
			$for_role = get_option( 'ivole_enable_for_role', 'all' );
			$enabled_roles = get_option( 'ivole_enabled_roles', array() );
			$for_guests = 'no' === get_option( 'ivole_enable_for_guests', 'yes' ) ? false : true;

			// check if taxes should be included in list_products variable
			$tax_displ = get_option( 'woocommerce_tax_display_cart' );
			$incl_tax = false;
			if ( 'excl' === $tax_displ ) {
				$incl_tax = false;
			} else {
				$incl_tax = true;
			}

			//check if free products should be excluded from list_products variable
			$excl_free = false;
			if( 'yes' == get_option( 'ivole_exclude_free_products', 'no' ) ) {
				$excl_free = true;
			}

			// check if we are dealing with an old WooCommerce version
			$customer_first_name = '';
			$customer_last_name = '';
			$order_date = '';
			$order_currency = '';
			$order_items = array();
			$user = NULL;
			$billing_country = apply_filters( 'woocommerce_get_base_location', get_option( 'woocommerce_default_country' ) );
			$shipping_country = apply_filters( 'woocommerce_get_base_location', get_option( 'woocommerce_default_country' ) );
			$temp_country = '';
			if( method_exists( $order, 'get_billing_email' ) ) {
				// Woocommerce version 3.0 or later
				$user = $order->get_user();
				if ( $registered_customers ) {
					if ( $user ) {
						$this->to = $user->user_email;
					} else {
						$this->to = $order->get_billing_email();
					}
				} else {
					$this->to = $order->get_billing_email();
				}
				$customer_first_name = $order->get_billing_first_name();
				$customer_last_name = $order->get_billing_last_name();
				$this->replace['customer-first-name'] = $customer_first_name;
				$this->replace['customer-last-name'] = $customer_last_name;
				$this->replace['customer-name'] = $customer_first_name . ' ' . $customer_last_name;
				$this->replace['order-id'] = $order->get_order_number();
				$this->replace['order-date']   = date_i18n( wc_date_format(), strtotime( $order->get_date_created() ) );
				$order_date = date_i18n( 'd.m.Y', strtotime( $order->get_date_created() ) );
				$order_currency = $order->get_currency();

				$price_args = array( 'currency' => $order_currency );
				$list_products = '';
				$first_product = true;
				foreach ( $order->get_items() as $order_item ) {
					if( $excl_free && 0 >= $order->get_line_total( $order_item, $incl_tax ) ) {
						continue;
					}
					if ( $first_product ) {
						$first_product = false;
					} else {
						$list_products .= ', ';
					}
					$list_products .= $order_item->get_name();
				}
				$this->replace['list-products'] = $list_products;
			} else {
				return array( 3, 'Error: old WooCommerce version, please update WooCommerce to the latest version' );
			}
			if( isset( $user ) && !empty( $user ) ) {
				// check customer roles if there is a restriction to which roles reminders should be sent
				if( 'roles' === $for_role ) {
					$roles = $user->roles;
					$intersection = array_intersect( $enabled_roles, $roles );
					if( count( $intersection ) < 1 ){
						//customer has no allowed roles
						return array( 4, 'Error: customer does not have roles for which review reminders are enabled' );
					}
				}
			} else {
				// check if sending of review reminders is enabled for guests
				if( ! $for_guests ) {
					return array( 5, 'Error: review reminders are disabled for guests' );
				}
			}

			// check if customer email is valid
			if ( ! filter_var( $this->to, FILTER_VALIDATE_EMAIL ) ) {
				$this->to = '';
			}

			$data = array(
				'shop' => array(
					'name' => Ivole_Email::get_blogname(),
			 		'domain' => Ivole_Email::get_blogurl(),
				 	'country' => apply_filters( 'woocommerce_get_base_location', get_option( 'woocommerce_default_country' ) ) ),
				'email' => array(
					'to' => $this->to
				),
				'customer' => array(
					'firstname' => $customer_first_name,
					'lastname' => $customer_last_name
				),
				'order' => array(
					'id' => strval( $order_id ),
			 		'date' => $order_date,
					'currency' => $order_currency,
					'country' => $shipping_country,
				 	'items' => CR_Email_Func::get_order_items2( $order, $order_currency )
				),
				'form' => array(
					'header' => $this->replace_variables( $this->form_header ),
					'description' => $this->replace_variables( $this->form_body ),
				 	'commentRequired' => $comment_required,
				 	'allowMedia' => $allowMedia,
				 	'shopRating' => $shop_rating,
				 	'ratingBar' => $ratingBar,
				 	'geoLocation' => $geolocation
				),
				'colors' => array(
					'form' => array(
						'bg' => get_option( 'ivole_form_color_bg', '#0f9d58' ),
						'text' => get_option( 'ivole_form_color_text', '#ffffff' ),
						'el' => get_option( 'ivole_form_color_el', '#1AB394' )
					)
				),
				'language' => $this->language
			);
			//check that array of items is not empty
			if( 1 > count( $data['order']['items'] ) ) {
				$order->add_order_note(
					__( 'CR: A review invitation cannot be sent because the order does not contain any products for which review reminders are enabled in the settings.', 'customer-reviews-woocommerce' )
				);
				return array( 7, __( 'Error: the order does not contain any products for which review reminders are enabled in the settings.', 'customer-reviews-woocommerce' ) );
			}
		} elseif ( 0 === $order_id ) {
			// a test order
			$data = array(
				'shop' => array(
					'name' => Ivole_Email::get_blogname(),
			 		'domain' => Ivole_Email::get_blogurl(),
				 	'country' => apply_filters( 'woocommerce_get_base_location', get_option( 'woocommerce_default_country' ) ) ),
				'email' => array(
					'to' => ''
				),
				'customer' => array(
					'firstname' => __( 'Jane', 'customer-reviews-woocommerce' ),
					'lastname' => __( 'Doe', 'customer-reviews-woocommerce' )
				),
				'order' => array(
					'id' => '12345',
			 		'date' => date_i18n( 'd.m.Y', time() ),
					'currency' => get_woocommerce_currency(),
				 	'items' => CR_Email_Func::get_test_items()
				),
				'form' => array(
					'header' => $this->replace_variables( $this->form_header ),
					'description' => $this->replace_variables( $this->form_body ),
				 	'commentRequired' => $comment_required,
				 	'allowMedia' => $allowMedia,
				 	'shopRating' => $shop_rating,
				 	'ratingBar' => $ratingBar,
				 	'geoLocation' => $geolocation
				),
				'colors' => array(
					'form' => array(
						'bg' => get_option( 'ivole_form_color_bg', '#0f9d58' ),
						'text' => get_option( 'ivole_form_color_text', '#ffffff' ),
						'el' => get_option( 'ivole_form_color_el', '#1AB394' )
					)
				),
				'language' => $this->language
			);
		} else {
			return array( 8, __( 'Error: invalid order ID', 'customer-reviews-woocommerce' ) );
		}

		return array( 0, $data );
	}

	public function replace_variables( $input ) {
		return str_replace( $this->find, $this->replace, $input );
	}

}

endif;
