<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;

class WooCommerce extends Base {

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		$element->add_control(
			'dce_visibility_woo_cart',
			[
				'label' => esc_html__( 'Cart is', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'select' => esc_html__( 'Select...', 'dynamic-visibility-for-elementor' ),
					'empty' => esc_html__( 'Empty', 'dynamic-visibility-for-elementor' ),
					'not_empty' => esc_html__( 'Not empty', 'dynamic-visibility-for-elementor' ),
				],
				'default' => 'select',
			]
		);

		$element->add_control(
			'dce_visibility_woo_product_type',
			[
				'label' => esc_html__( 'Product Type is', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => array_merge( [ 'select' => esc_html__( 'Select...', 'dynamic-visibility-for-elementor' ) ], wc_get_product_types() ),
				'default' => 'select',
				'placeholder' => esc_html__( 'Product Type', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
			]
		);

		$element->add_control(
			'dce_visibility_woo_product_id_static',
			[
				'label' => esc_html__( 'Product in the cart', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Product Name', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'query_type' => 'posts',
				'object_type' => 'product',
			]
		);

		$element->add_control(
			'dce_visibility_woo_product_category',
			[
				'label' => esc_html__( 'Product Category in the cart', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Product Category', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'query_type' => 'terms',
			]
		);

		if ( Helper::is_plugin_active( 'woocommerce-memberships' ) ) {
			$plans = get_posts([
				'post_type' => 'wc_membership_plan',
				'post_status' => 'publish',
				'numberposts' => -1,
			]);
			if ( ! empty( $plans ) ) {
				$element->add_control(
					'dce_visibility_woo_membership_post',
					[
						'label' => esc_html__( 'Use Post Membership settings', 'dynamic-visibility-for-elementor' ),
						'type' => Controls_Manager::SWITCHER,
					]
				);

				$plan_options = [ 0 => esc_html__( 'NOT Member', 'dynamic-visibility-for-elementor' ) ];
				foreach ( $plans as $aplan ) {
					$plan_options[ $aplan->ID ] = esc_html( $aplan->post_title );
				}
				$element->add_control(
					'dce_visibility_woo_membership',
					[
						'label' => esc_html__( 'Membership', 'dynamic-visibility-for-elementor' ),
						'type' => Controls_Manager::SELECT2,
						'options' => $plan_options,
						'multiple' => true,
						'label_block' => true,
						'condition' => [
							'dce_visibility_woo_membership_post' => '',
						],
					]
				);
			}
		}
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> &$triggers
	 * @param array<string,mixed> &$conditions
	 * @param array<string,mixed> &$required
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function check_conditions( $settings, &$triggers, &$conditions, &$required, $element ) {
		if ( 'select' !== $settings['dce_visibility_woo_cart'] ) {
			$triggers['dce_visibility_woo_cart'] = esc_html__( 'Cart is', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_woo_cart'] = true;
			$cart_is_empty = WC()->cart->get_cart_contents_count() === 0;
			if ( 'empty' === $settings['dce_visibility_woo_cart'] && $cart_is_empty
				|| 'not_empty' === $settings['dce_visibility_woo_cart'] && ! $cart_is_empty ) {
				$conditions['dce_visibility_woo_cart'] = esc_html__( 'Cart is', 'dynamic-visibility-for-elementor' );
			}
		}

		if ( ! empty( $settings['dce_visibility_woo_product_type'] ) && 'select' !== $settings['dce_visibility_woo_product_type'] ) {
			$triggers['dce_visibility_woo_product_type'] = esc_html__( 'Product Type is', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_woo_product_type'] = true;
			$product = wc_get_product( get_the_ID() );
			if ( $product && $product->is_type( $settings['dce_visibility_woo_product_type'] ) ) {
				$conditions['dce_visibility_woo_product_type'] = esc_html__( 'Product Type is', 'dynamic-visibility-for-elementor' );
			}
		}

		if ( ! empty( $settings['dce_visibility_woo_product_id_static'] ) ) {
			$triggers['dce_visibility_woo_product_id_static'] = esc_html__( 'Product in the cart', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_woo_product_id_static'] = true;
			$product_id = $settings['dce_visibility_woo_product_id_static'];
			$product_cart_id = WC()->cart->generate_cart_id( $product_id );
			$in_cart = WC()->cart->find_product_in_cart( $product_cart_id );
			if ( $in_cart ) {
				$conditions['dce_visibility_woo_product_id_static'] = esc_html__( 'Product in the cart', 'dynamic-visibility-for-elementor' );
			}
		}

		if ( ! empty( $settings['dce_visibility_woo_product_category'] ) ) {
			$triggers['dce_visibility_woo_product_category'] = esc_html__( 'Product Category in the cart', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_woo_product_id_static'] = true;

			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( has_term( $settings['dce_visibility_woo_product_category'], 'product_cat', $cart_item['product_id'] ) ) {
					$conditions['dce_visibility_woo_product_id_static'] = esc_html__( 'Product Category in the cart', 'dynamic-visibility-for-elementor' );
					break;
				}
			}
		}

		if ( Helper::is_plugin_active( 'woocommerce-memberships' ) ) {
			if ( $settings['dce_visibility_woo_membership_post'] ) {
				$triggers['dce_visibility_woo_membership_post'] = esc_html__( 'Woo Membership Post', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_woo_membership_post'] = true;

				if ( function_exists( 'wc_memberships_is_user_active_or_delayed_member' ) ) {

					$post_ID = get_the_ID(); // Current post
					if ( ! empty( $settings['dce_visibility_post_id'] ) ) {
						switch ( $settings['dce_visibility_post_id'] ) {
							case 'global':
								$post_ID = Helper::get_post_id_from_url();
								if ( ! $post_ID ) {
									if ( get_queried_object() instanceof \WP_Post ) {
										$post_ID = get_queried_object_id();
									}
								}
								break;
							case 'static':
								$post_tmp = get_post( absint( $settings['dce_visibility_post_id_static'] ) );
								if ( is_object( $post_tmp ) ) {
									$post_ID = $post_tmp->ID;
								}
								break;
						}
					}

					$user_id = get_current_user_id();
					$has_access = true;
					$rules = wc_memberships()->get_rules_instance()->get_post_content_restriction_rules( $post_ID );
					if ( ! empty( $rules ) ) {
						$has_access = false;
						if ( $user_id ) {
							foreach ( $rules as $rule ) {
								if ( wc_memberships_is_user_active_or_delayed_member( $user_id, $rule->get_membership_plan_id() ) ) {
									$has_access = true;
									break;
								}
							}
						}
					}
					if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
						$has_access = true;
					}
					if ( $has_access ) {
						$conditions['dce_visibility_woo_membership_post'] = esc_html__( 'Woo Membership Post', 'dynamic-visibility-for-elementor' );
					}
				}
			} else {
				//roles
				if ( isset( $settings['dce_visibility_woo_membership'] ) && ! empty( $settings['dce_visibility_woo_membership'] ) ) {
					$triggers['dce_visibility_woo_membership'] = esc_html__( 'Woo Membership', 'dynamic-visibility-for-elementor' );
					$required['dce_visibility_woo_membership'] = true;

					$current_user_id = get_current_user_id();
					if ( $current_user_id ) {
						$member_plans = get_posts([
							'author' => $current_user_id,
							'post_type' => 'wc_user_membership',
							'post_status' => [
								'wcm-active',
								'wcm-free_trial',
								'wcm-pending',
							],
							'posts_per_page' => -1,
						]);
						$user_members = [];
						if ( empty( $member_plans ) ) {
							// not member
							if ( in_array( 0, $settings['dce_visibility_woo_membership'] ) ) {
								$conditions['dce_visibility_woo_membership'] = esc_html__( 'Woo Membership', 'dynamic-visibility-for-elementor' );
							}
						} else {
							// find all user membership plan
							foreach ( $member_plans as $member ) {
								$user_members[] = $member->post_parent;
							}
							$tmp_members = array_intersect( $user_members, $settings['dce_visibility_woo_membership'] );
							if ( ! empty( $tmp_members ) ) {
								$conditions['dce_visibility_woo_membership'] = esc_html__( 'Woo Membership', 'dynamic-visibility-for-elementor' );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @return boolean
	 */
	public function is_available() {
		return Helper::is_plugin_active( 'woocommerce' );
	}
}
