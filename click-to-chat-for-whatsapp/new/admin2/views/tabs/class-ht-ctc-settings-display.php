<?php
/**
 * Display Settings
 *
 * @package Click_To_Chat
 * @subpackage admin
 * @since 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Settings_Display' ) ) {

	/**
	 * Display settings class.
	 */
	class HT_CTC_Settings_Display {

		/**
		 * Get fields
		 *
		 * @return array
		 */
		public static function fields() {
			$fields = array();

			// Devices
			$fields[] = self::card_devices();

			// Pages
			$fields[] = self::card_pages();

			// Targeting
			$fields[] = self::card_targeting();

			// Business Hours
			$fields[] = self::card_business_hours();

			return $fields;
		}

		/**
		 * Helper to create device display option fields
		 *
		 * @param string $label Field label.
		 * @param string $id Field ID.
		 * @return array
		 */
		private static function device_display_field( $label, $id ) {
			return array(
				'field_type'   => 'field_radio',
				'type'         => 'segment',
				'label'        => $label,
				'id'           => $id,
				'option_group' => 'ht_ctc_chat_options',
				'options'      => array(
					'show' => __( 'Show', 'click-to-chat-for-whatsapp' ),
					'hide' => __( 'Hide', 'click-to-chat-for-whatsapp' ),
				),
				'default'      => 'show',
			);
		}

		/**
		 * Helper to create page display option fields
		 *
		 * @param string $label Field label.
		 * @param string $id Field ID.
		 * @param string $help_click Optional help text shown via a "?" toggle next to the label.
		 * @return array
		 */
		private static function page_display_field( $label, $id, $help_click = '' ) {
			$field = array(
				'field_type'   => 'field_radio',
				'type'         => 'segment',
				'label'        => $label,
				'id'           => $id,
				'option_group' => 'ht_ctc_chat_options[display]',
				'options'      => array(
					'g'    => __( 'Global', 'click-to-chat-for-whatsapp' ),
					'show' => __( 'Show', 'click-to-chat-for-whatsapp' ),
					'hide' => __( 'Hide', 'click-to-chat-for-whatsapp' ),
				),
				'default'      => 'g',
			);

			// Optional "?" help toggle next to the label (clarifies ambiguous page types).
			if ( '' !== $help_click ) {
				$field['help_click'] = $help_click;
			}

			return $field;
		}

		/**
		 * Helper to create list text fields
		 *
		 * @param string $label Field label.
		 * @param string $id Field ID.
		 * @param string $help Field help text.
		 * @param string $watch Data watch string.
		 * @param string $show_when Data show when string.
		 * @return array
		 */
		private static function list_field( $label, $id, $help, $watch, $show_when ) {
			$type        = ( strpos( $id, 'pages' ) !== false ) ? 'page' : 'category';
			$placeholder = ( 'page' === $type ) ? 'Enter page IDs separated by commas (e.g., 12, 34, 56)' : 'Enter category names separated by commas (e.g., News, Offers)';
			return array(
				'field_type'     => 'field_text',
				'label'          => $label,
				'id'             => $id,
				'option_group'   => 'ht_ctc_chat_options[display]',
				'placeholder'    => $placeholder,
				'help'           => $help,
				'data_watch'     => $watch,
				'data_show_when' => $show_when,
			);
		}

		/**
		 * Get public custom post types that use display controls.
		 *
		 * @return array
		 */
		private static function get_display_custom_post_type_keys() {
			if ( ! function_exists( 'get_post_types' ) ) {
				return array();
			}

			$custom_post_types = get_post_types(
				array(
					'public'   => true,
					'_builtin' => false,
				)
			);

			if ( is_array( $custom_post_types ) ) {
				unset( $custom_post_types['product'] );
				return array_values( $custom_post_types );
			}

			return array();
		}

		/**
		 * Devices Card
		 */
		private static function card_devices() {
			$values = array(
				'field_type'     => 'card',
				'title'          => 'Devices',
				'description'    => 'Control display on different devices',
				'data_watch'     => '#connection_type',
				'data_show_when' => 'single',
				'fields'         => array(
					self::device_display_field( 'Desktop Display', 'display_desktop' ),
					self::device_display_field( 'Mobile Display', 'display_mobile' ),
				),
			);
			// $values = apply_filters( 'ht_ctc_fh_settings_fields_display_devices', $values );
			return $values;
		}

		/**
		 * Pages Card
		 */
		private static function card_pages() {
			$pages_fields = array(
				array(
					'field_type'   => 'field_radio',
					'type'         => 'segment',
					'label'        => sprintf( '%1$s %2$s', __( 'Global', 'click-to-chat-for-whatsapp' ), __( 'Display', 'click-to-chat-for-whatsapp' ) ),
					'id'           => 'global_display',
					'option_group' => 'ht_ctc_chat_options[display]',
					'options'      => array(
						'show' => __( 'Show', 'click-to-chat-for-whatsapp' ),
						'hide' => __( 'Hide', 'click-to-chat-for-whatsapp' ),
					),
					'default'      => 'show',
					'help'         => 'Global setting for all pages',
				),
				array(
					'field_type'  => 'block_sub_heading',
					'title'       => __( 'Overwrite the Global settings', 'click-to-chat-for-whatsapp' ),
					'description' => 'If global display is enabled, you can hide on specific pages and vice-versa',
					'class_pr'    => '',
				),
				self::page_display_field( 'Home Page', 'home', 'The front page of your site.' ),
				self::page_display_field( 'Posts', 'posts', 'Single blog post pages.' ),
				self::page_display_field( 'Pages', 'pages', 'Static pages such as About or Contact.' ),
				self::page_display_field( 'Archive pages', 'archive', 'Listing pages that group posts together — date, author, tag and custom taxonomy archives.' ),
				self::page_display_field( 'Category pages', 'category', 'Pages that list all posts filed under a category.' ),
				self::page_display_field( '404 Page', 'page_404', 'The error page shown when a URL is not found (“page not found”).' ),
			);

			// Add WooCommerce settings only if WooCommerce is active
			if ( class_exists( 'WooCommerce' ) ) {
				$woo_fields   = array(
					array(
						'field_type'  => 'block_sub_heading',
						'title'       => 'WooCommerce Pages',
						'description' => 'Control display on WooCommerce specific pages',
					),
					self::page_display_field( 'Single Product Pages', 'woo_product', 'Individual product detail pages.' ),
					self::page_display_field( 'Shop Page', 'woo_shop', 'The main store page that lists all products.' ),
					self::page_display_field( 'Cart Page', 'woo_cart', 'The shopping cart page.' ),
					self::page_display_field( 'Checkout Page', 'woo_checkout', 'The checkout / payment page.' ),
					self::page_display_field( 'Thank You / Order Received Page', 'woo_order_received', 'The order confirmation (thank-you) page shown after checkout.' ),
					self::page_display_field( 'Account Page', 'woo_account', 'The customer’s My Account dashboard.' ),
				);
				$pages_fields = array_merge( $pages_fields, $woo_fields );
			}

			$custom_post_types = self::get_display_custom_post_type_keys();
			if ( ! empty( $custom_post_types ) ) {
				$custom_post_types_fields = array(
					array(
						'field_type'  => 'block_sub_heading',
						'title'       => 'Custom Post Types',
						'description' => 'Control display on custom post types',
					),
				);
				foreach ( $custom_post_types as $cpt ) {
					// // Use get_post_type_object to fetch the properly registered human-readable name,
					// // falling back to a capitalized slug if the object isn't found.
					// $post_type_obj = get_post_type_object( $cpt );
					// $cpt_label     = $post_type_obj ? $post_type_obj->labels->name : ucfirst( $cpt );
					// $custom_post_types_fields[]  = self::page_display_field( $cpt_label, $cpt );
					$custom_post_types_fields[] = self::page_display_field( $cpt, $cpt );
				}
				$pages_fields = array_merge( $pages_fields, $custom_post_types_fields );
			}

			// Add Page/Category Lists section
			$list_fields = array(
				array(
					'field_type'  => 'block_sub_heading',
					'title'       => 'Page/Category Lists',
					'description' => 'Specify individual pages (by ID) or categories (by name)',
				),
				self::list_field( sprintf( '%1$s (by ID)', __( 'Hide on this pages', 'click-to-chat-for-whatsapp' ) ), 'list_hideon_pages', 'Enter page IDs where you want to hide the chat button', '#global_display_show', 'show' ),
				self::list_field( sprintf( '%1$s (by name)', __( 'Hide on this Category posts', 'click-to-chat-for-whatsapp' ) ), 'list_hideon_cat', 'Enter category names where you want to hide the chat button', '#global_display_show', 'show' ),
				self::list_field( sprintf( '%1$s (by ID)', __( 'Show on this pages', 'click-to-chat-for-whatsapp' ) ), 'list_showon_pages', 'Enter page IDs where you want to show the chat button', '#global_display_hide', 'hide' ),
				self::list_field( sprintf( '%1$s (by name)', __( 'Show on this Category posts', 'click-to-chat-for-whatsapp' ) ), 'list_showon_cat', 'Enter category names where you want to show the chat button', '#global_display_hide', 'hide' ),
				array(
					'field_type' => 'block_external_link',
					'title'      => '',
					'url'        => 'https://holithemes.com/plugins/click-to-chat/docs/show-hide-styles/',
					'label'      => __( 'Display Settings', 'click-to-chat-for-whatsapp' ),
				),
			);

			$pages_fields = array_merge( $pages_fields, $list_fields );

			$values = array(
				'field_type'     => 'card',
				'title'          => 'Pages',
				'description'    => 'Control display on specific pages',
				'data_watch'     => '#connection_type',
				'data_show_when' => 'single',
				'fields'         => $pages_fields,
			);
			// $values = apply_filters( 'ht_ctc_fh_settings_fields_display_pages', $values );
			return $values;
		}

		/**
		 * Business Hours Card
		 */
		private static function card_business_hours() {
			$values = array(
				'field_type'     => 'card',
				'title'          => __( 'Business Hours', 'click-to-chat-for-whatsapp' ),
				'description'    => 'Control display based on time and day',
				'data_watch'     => '#connection_type',
				'data_show_when' => 'single',
				'fields'         => array(),
			);

			if ( ! defined( 'HT_CTC_PRO_VERSION' ) ) {
				$values['fields'] = array(
					// array(
					// 'field_type' => 'block_infobox_alert',
					// 'type'       => 'warning',
					// 'icon'       => 'dashicons dashicons-warning',
					// 'content'    => 'Business Hours are available in the Pro version',
					// ),
					array(
						'field_type'  => 'block_pro_feature',
						'title'       => __( 'Business Hours', 'click-to-chat-for-whatsapp' ),
						'badge'       => __( 'PRO', 'click-to-chat-for-whatsapp' ),
						'description' => 'Show chat button only during specific hours',
						'control'     => array(
							'type'     => 'switch',
							'disabled' => true,
						),
					),
				);
			}

			$values = apply_filters( 'ht_ctc_fh_settings_fields_display_business_hours', $values );
			return $values;
		}

		/**
		 * Targeting Card
		 */
		private static function card_targeting() {
			$values = array(
				'field_type'     => 'card',
				'title'          => 'Targeting',
				'description'    => 'Advanced targeting options',
				'data_watch'     => '#connection_type',
				'data_show_when' => 'single',
				'fields'         => array(),
			);

			if ( ! defined( 'HT_CTC_PRO_VERSION' ) ) {
				$values['fields'] = array(
					// array(
					// 'field_type' => 'block_infobox_alert',
					// 'type'       => 'warning',
					// 'icon'       => 'dashicons dashicons-warning',
					// 'content'    => 'Advanced targeting options are available in the Pro version',
					// ),
					array(
						'field_type'  => 'block_pro_feature',
						'title'       => 'Country-Based Display',
						'badge'       => __( 'PRO', 'click-to-chat-for-whatsapp' ),
						'description' => 'Show chat button only to visitors from specific countries',
						'control'     => array(
							'type'     => 'switch',
							'disabled' => true,
						),
					),
					array(
						'field_type'  => 'block_pro_feature',
						'title'       => 'Time-Based Display',
						'badge'       => __( 'PRO', 'click-to-chat-for-whatsapp' ),
						'description' => 'Show chat button only during specific hours',
						'control'     => array(
							'type'     => 'switch',
							'disabled' => true,
						),
					),
				);
			}

			$values = apply_filters( 'ht_ctc_fh_settings_fields_display_targeting', $values );
			return $values;
		}
	}
}
