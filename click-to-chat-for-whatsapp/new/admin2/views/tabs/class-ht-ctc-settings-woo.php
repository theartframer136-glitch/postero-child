<?php
/**
 * WooCommerce Settings
 *
 * @package Click_To_Chat
 * @subpackage admin
 * @since 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Settings_Woo' ) ) {

	/**
	 * WooCommerce settings class.
	 */
	class HT_CTC_Settings_Woo {

		/**
		 * Get fields for Overwrite Settings
		 *
		 * @return array
		 */
		public static function fields_overwrite() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return array();
			}
			return array( self::card_woo_settings() );
		}

		/**
		 * Get fields for Advanced/Add WhatsApp Settings
		 *
		 * @return array
		 */
		public static function fields_advanced() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return array();
			}
			return array( self::card_advanced_woo_settings() );
		}

		/**
		 * Get fields
		 *
		 * @return array
		 */
		public static function fields() {
			// Return empty array if WooCommerce is not active
			if ( ! class_exists( 'WooCommerce' ) ) {
				return array();
			}

			$fields = array();

			// WooCommerce Settings
			$fields[] = self::card_woo_settings();

			// Advanced WooCommerce Settings
			$fields[] = self::card_advanced_woo_settings();

			return $fields;
		}

		// -------------------------------------------------------------------------
		// Private helpers
		// -------------------------------------------------------------------------

		/**
		 * Style options for WooCommerce settings.
		 *
		 * @return array
		 */
		private static function style_options() {
			return array(
				'1'   => __( 'Style-1', 'click-to-chat-for-whatsapp' ),
				'2'   => __( 'Style-2', 'click-to-chat-for-whatsapp' ),
				'3'   => __( 'Style-3', 'click-to-chat-for-whatsapp' ),
				'3_1' => __( 'Style-3 Extend', 'click-to-chat-for-whatsapp' ), // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
				'4'   => __( 'Style-4', 'click-to-chat-for-whatsapp' ),
				'5'   => __( 'Style-5', 'click-to-chat-for-whatsapp' ),
				'7'   => __( 'Style-7', 'click-to-chat-for-whatsapp' ),
				'7_1' => __( 'Style-7 Extend', 'click-to-chat-for-whatsapp' ), // phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found
				'8'   => __( 'Style-8', 'click-to-chat-for-whatsapp' ),
				'99'  => __( 'Add your own image / GIF (Style-99)', 'click-to-chat-for-whatsapp' ),
			);
		}

		/**
		 * Helper to create margin and layout fields for WooCommerce.
		 *
		 * @param string $prefix Field ID prefix (e.g., 'woo_single' or 'woo_shop').
		 * @param bool   $include_block_type Whether to include the block type selector.
		 * @return array
		 */
		private static function margin_fields( $prefix, $include_block_type = false ) {
			$fields = array();

			$fields[] = array(
				'field_type'   => 'field_checkbox',
				'id'           => $prefix . '_position_center',
				'label'        => 'Display Center',
				'option_group' => 'ht_ctc_woo_options',
				'help'         => 'Display center within available space',
			);

			if ( $include_block_type ) {
				$fields[] = array(
					'field_type'   => 'field_select',
					'id'           => $prefix . '_block_type',
					'label'        => 'Display Block Type',
					'option_group' => 'ht_ctc_woo_options',
					'options'      => array(
						'block'        => 'block',
						'inline'       => 'inline',
						'inline-block' => 'inline-block',
					),
				);
				// On the based of display center if checked Recommended 'block' style
				$fields[] = array(
					'field_type'     => 'block_content',
					'content'        => "Recommended type: 'block'",
					'class_pr'       => 'description',
					'style'          => 'margin-top: -12px; margin-bottom: 12px;',
					'data_watch'     => '#' . $prefix . '_position_center',
					'data_show_when' => '1',
				);
			}

			$margins = array(
				'top'    => 'Margin Top',
				'bottom' => 'Margin Bottom',
				'left'   => 'Margin Left',
				'right'  => 'Margin Right',
			);

			foreach ( $margins as $side => $label ) {
				$fields[] = array(
					'field_type'   => 'field_text',
					'id'           => $prefix . '_margin_' . $side,
					'label'        => $label,
					'option_group' => 'ht_ctc_woo_options',
					'placeholder'  => '10px',
				);
			}

			return $fields;
		}

		/**
		 * Variables reference block for WooCommerce settings.
		 *
		 * @return array
		 */
		private static function variables_reference() {
			$variables = array(
				'{product}'       => 'Product name',
				'{price}'         => 'Price',
				'{{price}}'       => 'Price with currency sign (e.g. $10.00)',
				'{regular_price}' => 'Regular price',
				'{sku}'           => 'Product SKU',
				'{site}'          => 'Site name',
				'{title}'         => 'Page title',
				'{url}'           => 'Page URL',
			);

			return array(
				'field_type' => 'block_variables',
				'title'      => 'Variables',
				'variables'  => $variables,
			);
		}

		/**
		 * WooCommerce Settings Card
		 */
		private static function card_woo_settings() {
			// Pro plugin adds greetings fields here via filter (after woo_call_to_action).
			$pro_single_product_fields = apply_filters( 'ht_ctc_fh_woo_single_product_fields', array() );

			$shop_fields = array(
				array(
					'field_type' => 'block_content',
					'content'    => vsprintf(
						'At <a target="_blank" href="%1$s" class="external-link">%2$s %3$s <span class="dashicons dashicons-external"></span></a> can overwrite: %4$s, %5$s, %6$s, %7$s. <br>(<a target="_blank" href="%8$s" class="external-link">PRO <span class="dashicons dashicons-external"></span></a>: Greetings, Style, %9$s)',
						array(
							'https://holithemes.com/plugins/click-to-chat/change-values-at-page-level/',
							__( 'Page level', 'click-to-chat-for-whatsapp' ),
							__( 'Settings', 'click-to-chat-for-whatsapp' ),
							__( 'Number', 'click-to-chat-for-whatsapp' ),
							__( 'Call to Action', 'click-to-chat-for-whatsapp' ),
							__( 'Pre-filled Message', 'click-to-chat-for-whatsapp' ),
							__( 'Display Settings', 'click-to-chat-for-whatsapp' ),
							'https://holithemes.com/plugins/click-to-chat/pricing/',
							__( 'Time, Scroll Delay', 'click-to-chat-for-whatsapp' ),
						)
					),
				),
			);

			if ( function_exists( 'wc_get_page_id' ) ) {
				$woo_pages = array(
					'shop'      => 'Edit Shop Page',
					'cart'      => 'Edit Cart Page',
					'checkout'  => 'Edit Checkout Page',
					'myaccount' => 'Edit My Account Page',
				);

				foreach ( $woo_pages as $page => $label ) {
					$page_id = wc_get_page_id( $page );

					if ( $page_id > 0 ) {
						$admin_url     = admin_url( 'post.php?post=' . $page_id . '&action=edit' );
						$shop_fields[] = array(
							'field_type' => 'block_external_link',
							'url'        => $admin_url,
							'label'      => $label,
							'icon'       => 'dashicons dashicons-external',
							'class_pr'   => 'woo-edit-link',
						);
						$shop_fields[] = array(
							'field_type' => 'block_spacer',
							'height'     => '8px',
						);
					}
				}
			}

			$values = array(
				'field_type'  => 'card',
				'title'       => 'WooCommerce Settings',
				'description' => 'Overwrite Settings for WooCommerce Pages',
				// 'description' => 'Configure WhatsApp for WooCommerce pages',
				'fields'      => array(
					array(
						'field_type' => 'tabs',
						'tabs'       => array(
							'single-product' => array(
								'label'  => 'Single Product',
								'fields' => array_merge(
									array(
										array(
											'field_type' => 'block_external_link',
											'id'         => 'woo_single_product_pages_doc',
											'url'        => 'https://holithemes.com/plugins/click-to-chat/woocommerce-single-product-pages/',
											'label'      => 'WooCommerce Single Product pages',
											// 'label'      => 'View Documentation'
										),
										// filed_type: block_variables
										self::variables_reference(),
										array(
											'field_type' => 'block_content',
											'content'    => '<p class="description">' . __( 'Leave blank for default settings', 'click-to-chat-for-whatsapp' ) . '</p>',
										),
										array(
											'field_type' => 'block_spacer',
											'height'     => '24px',
										),
										array(
											'field_type'   => 'field_textarea',
											'id'           => 'woo_pre_filled',
											'label'        => __( 'Pre-filled Message', 'click-to-chat-for-whatsapp' ),
											'option_group' => 'ht_ctc_woo_options',
											'placeholder'  => "Hello {site} \nLike to buy {product}, {url}",
											'rows'         => 4,
										),
										array(
											'field_type'   => 'field_text',
											'id'           => 'woo_call_to_action',
											'label'        => __( 'Call to Action', 'click-to-chat-for-whatsapp' ),
											'option_group' => 'ht_ctc_woo_options',
											'placeholder'  => 'Buy {product}',
										),
										array(
											'field_type' => 'block_content',
											'content'    => '<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border);">'
															. '<strong>Greetings Overwrite</strong>'
															. '<p class="description">Overwrite Greetings Settings for WooCommerce single product pages</p>'
															. '</div>',
										),
									),
									$pro_single_product_fields
								),
							),
							'Shop'           => array(
								'label'  => 'SHOP, CART, CHECKOUT, ACCOUNT',
								'fields' => $shop_fields,
							),
						),
					),
				),
			);
			return $values;
		}

		/**
		 * Advanced WooCommerce Settings Card
		 */
		private static function card_advanced_woo_settings() {
			$style_options = self::style_options();

			// Advanced Fields: Business Hours
			$advanced_fields = array();
			if ( ! defined( 'HT_CTC_PRO_VERSION' ) ) {
				$advanced_fields[] = array(
					'field_type'  => 'block_pro_feature',
					'title'       => 'Apply Business Hours',
					'badge'       => __( 'PRO', 'click-to-chat-for-whatsapp' ),
					'description' => 'Apply business hours settings to WhatsApp added in WooCommerce Pages (single product, Shop)',
					'control'     => array(
						'type'     => 'switch',
						'disabled' => true,
					),
				);
			}

			$advanced_fields = apply_filters( 'ht_ctc_fh_woo_additional_settings_fields', $advanced_fields );

			$values = array(
				'field_type'  => 'card',
				'title'       => __( 'Add WhatsApp', 'click-to-chat-for-whatsapp' ),
				'description' => 'Advanced WooCommerce integration settings',
				'fields'      => array(
					array(
						'field_type' => 'tabs',
						'tabs'       => array(
							'single-product-pages' => array(
								'label'  => 'Single Product Pages',
								'fields' => array(
									array(
										'field_type' => 'block_external_link',
										'id'         => 'woo_single_product_pages',
										'url'        => 'https://holithemes.com/plugins/click-to-chat/add-whatsapp-in-woocommerce-single-product-pages/',
										'label'      => 'Add WhatsApp in WooCommerce Single Product pages',
									),
									array(
										'field_type' => 'block_spacer',
										'height'     => '12px',
									),
									array(
										'field_type'   => 'field_select',
										'id'           => 'woo_position',
										'label'        => 'Add Whatsapp',
										'option_group' => 'ht_ctc_woo_options',
										'options'      => array(
											'select' => '-- Select --',
											'woocommerce_before_main_content' => 'Before Main Content',
											'woocommerce_before_single_product' => 'Before Product',
											'woocommerce_before_single_product_summary' => 'Before Product Summary',
											'woocommerce_single_product_summary' => 'Product Summary',
											'woocommerce_before_add_to_cart_form' => 'Before Add to Cart Form',
											'woocommerce_before_add_to_cart_button' => 'Before Cart Button',
											'woocommerce_after_add_to_cart_button' => 'After Cart Button',
											'woocommerce_after_add_to_cart_form' => 'After Add to Cart Form',
											'woocommerce_after_single_product' => 'After Product',
											'woocommerce_after_single_product_summary' => 'After Product Summary',
										),
									),
									array(
										'field_type'     => 'field_select',
										'id'             => 'woo_style',
										'label'          => __( 'Select Style', 'click-to-chat-for-whatsapp' ),
										'option_group'   => 'ht_ctc_woo_options',
										'options'        => $style_options,
										'help'           => sprintf(
											'<a target="_blank" href="%1$s" class="external-link">%2$s <span class="dashicons dashicons-external"></span></a> &emsp; | &emsp; <a class="customize_styles_href ctc-shortcut-link" href="%3$s">%4$s <span class="dashicons dashicons-arrow-right-alt2"></span></a> <br> <strong>%5$s: 1, 4, 8</strong>',
											'https://holithemes.com/plugins/click-to-chat/list-of-styles/',
											__( 'List of Styles', 'click-to-chat-for-whatsapp' ),
											'#customize-settings',
											__( 'Customize the styles', 'click-to-chat-for-whatsapp' ),
											'Recommended Styles'
										),
										'data_watch'     => '#woo_position',
										'data_show_when' => 'woocommerce_before_main_content,woocommerce_before_single_product,woocommerce_before_single_product_summary,woocommerce_single_product_summary,woocommerce_before_add_to_cart_form,woocommerce_before_add_to_cart_button,woocommerce_after_add_to_cart_button,woocommerce_after_add_to_cart_form,woocommerce_after_single_product,woocommerce_after_single_product_summary',
									),
									array(
										'field_type'     => 'block_group',
										'data_watch'     => '#woo_position',
										'data_show_when' => 'woocommerce_before_main_content,woocommerce_before_single_product,woocommerce_before_single_product_summary,woocommerce_single_product_summary,woocommerce_before_add_to_cart_form,woocommerce_before_add_to_cart_button,woocommerce_after_add_to_cart_button,woocommerce_after_add_to_cart_form,woocommerce_after_single_product,woocommerce_after_single_product_summary',
										'fields'         => array(
											array(
												'field_type' => 'field_checkbox',
												'id'    => 'woo_single_layout_cart_btn',
												'label' => 'Button Layout - Like Add to Cart',
												// 'label' => 'Match Add to Cart Button Style',
												'option_group' => 'ht_ctc_woo_options',
												'help'  => 'WhatsApp button looks like Add to Cart button',
												// 'help' => 'Applies theme styles to make the WhatsApp button match your theme\'s Add to Cart button.',
												'data_watch' => '#woo_style',
												'data_show_when' => '1,8',
											),
											array(
												'field_type' => 'block_content',
												'content' => '<p class="description woo_single_position_settings" style="display: block;"><a class="ctc-shortcut-link" href="#woo-overwrite-settings" style="margin-bottom: 15px;">Override Prefilled Message and Call to action <span class="dashicons dashicons-arrow-right-alt2"></span></a></p>'
																	. '<p class="description woo_single_position_settings" style="display: block;">The appearance of these styles may vary depending on how your theme implements WooCommerce hooks.</p>',
												'style'   => 'margin-bottom: 5px;',
												'data_watch' => '#woo_position',
												'data_show_when' => 'woocommerce_before_main_content,woocommerce_before_single_product,woocommerce_before_single_product_summary,woocommerce_single_product_summary,woocommerce_before_add_to_cart_form,woocommerce_before_add_to_cart_button,woocommerce_after_add_to_cart_button,woocommerce_after_add_to_cart_form,woocommerce_after_single_product,woocommerce_after_single_product_summary',
											),
											array(
												'field_type' => 'block_accordion',
												'title'  => 'Adjust settings compatible with the theme design',
												// 'title' => 'Advanced Theme Compatibility Settings',
												'id'     => 'woo_single_layout_settings',
												'data_watch' => '#woo_position',
												'data_show_when' => 'woocommerce_before_main_content,woocommerce_before_single_product,woocommerce_before_single_product_summary,woocommerce_single_product_summary,woocommerce_before_add_to_cart_form,woocommerce_before_add_to_cart_button,woocommerce_after_add_to_cart_button,woocommerce_after_add_to_cart_form,woocommerce_after_single_product,woocommerce_after_single_product_summary',
												'fields' => self::margin_fields( 'woo_single', true ),
											),
										),
									),
								),
							),
							'shop-page-advanced'   => array(
								'label'  => 'Shop Page',
								'fields' => array(
									array(
										'field_type' => 'block_external_link',
										'id'         => 'woo_shop_page',
										'label'      => 'WooCommerce Shop page',
										'url'        => 'https://holithemes.com/plugins/click-to-chat/whatsapp-chat-in-woocommerce-shop-page/',
									),
									// filed_type: block_variables
									self::variables_reference(),
									array(
										'field_type' => 'block_spacer',
										'height'     => '12px',
									),
									array(
										'field_type'   => 'field_checkbox',
										'id'           => 'woo_shop_add_whatsapp',
										'option_group' => 'ht_ctc_woo_options',
										'label'        => __( 'Add WhatsApp', 'click-to-chat-for-whatsapp' ),
										'help'         => 'At Product Page, Shop Page',
									),
									array(
										'field_type'     => 'block_rows',
										'data_watch'     => '#woo_shop_add_whatsapp',
										'data_show_when' => '1',
										'fields'         => array(
											array(
												array(
													'field_type' => 'field_textarea',
													'id'   => 'woo_shop_pre_filled',
													'label' => __( 'Pre-filled Message', 'click-to-chat-for-whatsapp' ),
													'option_group' => 'ht_ctc_woo_options',
													'placeholder' => "Hello {site} \nLike to buy {product}, {url}",
													'rows' => 4,
													'help' => 'pre-filled, call-to-action: if blank, get values from page-level settings if not from the main settings',
													// 'help'           => 'If blank, values will be inherited from page-level or global settings.',
													'data_watch' => '#woo_shop_add_whatsapp',
													'data_show_when' => '1',
												),
											),
											array(
												array(
													'field_type' => 'field_text',
													'id' => 'woo_shop_call_to_action',
													'label' => __( 'Call to Action', 'click-to-chat-for-whatsapp' ),
													'option_group' => 'ht_ctc_woo_options',
													'placeholder' => 'Buy {product}',
													'data_watch' => '#woo_shop_add_whatsapp',
													'data_show_when' => '1',
												),
											),
											array(
												array(
													'field_type' => 'field_select',
													'id'   => 'woo_shop_style',
													'label' => __( 'Select Style', 'click-to-chat-for-whatsapp' ),
													'option_group' => 'ht_ctc_woo_options',
													'options' => $style_options,
													'help' => sprintf(
														'<a target="_blank" href="%1$s" class="external-link">%2$s <span class="dashicons dashicons-external"></span></a> &emsp; | &emsp; <a class="customize_styles_href ctc-shortcut-link" href="%3$s">%4$s <span class="dashicons dashicons-arrow-right-alt2"></span></a> <br> <strong>%5$s: 1, 8</strong>',
														'https://holithemes.com/plugins/click-to-chat/list-of-styles/',
														__( 'List of Styles', 'click-to-chat-for-whatsapp' ),
														'#customize-settings',
														__( 'Customize the styles', 'click-to-chat-for-whatsapp' ),
														'Recommended Styles'
													),
													'data_watch' => '#woo_shop_add_whatsapp',
													'data_show_when' => '1',
												),
											),
											array(
												array(
													'field_type' => 'field_checkbox',
													'id'   => 'woo_shop_layout_cart_btn',
													'label' => 'Button Layout - Like Add to Cart',
													// 'label'          => 'Match Add to Cart Button Style',
													'option_group' => 'ht_ctc_woo_options',
													'help' => 'WhatsApp button looks like Add to Cart button',
													// 'help'           => 'Applies theme styles to make the WhatsApp button match your theme\'s Add to Cart button.',
													'data_watch' => '#woo_shop_style',
													'data_show_when' => '1,8',
												),
											),
											array(
												array(
													'field_type' => 'block_accordion',
													'title' => 'Adjust settings compatible with the theme design',
													// 'title'          => 'Advanced Theme Compatibility Settings',
													'id' => 'woo_shop_layout_settings',
													'data_watch' => '#woo_shop_add_whatsapp',
													'data_show_when' => '1',
													'fields' => self::margin_fields( 'woo_shop' ),
												),
											),
										),
									),
								),
							),
							'Advanced'             => array(
								'label'  => 'Additional Settings',
								'fields' => $advanced_fields,
							),
						),
					),
				),
			);
			return $values;
		}
	}
}
