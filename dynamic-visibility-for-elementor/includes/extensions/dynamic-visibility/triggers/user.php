<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;

class User extends Base {

	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		$element->add_control(
			'dce_visibility_role',
			[
				'label' => esc_html__( 'Roles', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT2,
				'placeholder' => esc_html__( 'Roles', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'multiple' => true,
				'options' => wp_roles()->get_names() + [ 'visitor' => 'Visitor (User not logged in)' ],
				'description' => esc_html__( 'Limit visualization to specific user roles', 'dynamic-visibility-for-elementor' ),
			]
		);
		$element->add_control(
			'dce_visibility_role_all',
			[
				'label' => esc_html__( 'Match All Roles', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'All roles should match not just one', 'dynamic-visibility-for-elementor' ),
			]
		);
		$element->add_control(
			'dce_visibility_users',
			[
				'label' => esc_html__( 'Selected Users', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__( 'Type here the list of users who will be able to view (or not) this element. You can use their ID, email or username. Simply separate them by a comma. (e.g. "23, email@yoursite.com, username")', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
				'ai' => [
					'active' => false,
				],
			]
		);

		$element->add_control(
			'dce_visibility_can',
			[
				'label' => esc_html__( 'User can', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Select a capability', 'dynamic-visibility-for-elementor' ),
				'description' => esc_html__( 'Select a capability from the list, or type a custom one and press Enter to add it. Per-user capabilities (added via add_cap) are not listed but can be entered here.', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'query_type' => 'capabilities',
				'separator' => 'before',
				'select2options' => [
					'tags' => true,
				],
				'dynamic' => [
					'active' => false,
				],
			]
		);

		$element->add_control(
			'dce_visibility_usermeta',
			[
				'label' => esc_html__( 'User Field', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Meta key or Name', 'dynamic-visibility-for-elementor' ),
				'dynamic' => [
					'active' => false,
				],
				'label_block' => true,
				'query_type' => 'fields',
				'object_type' => 'user',
				'separator' => 'before',
			]
		);

		$element->add_control(
			'dce_visibility_usermeta_status',
			[
				'label' => esc_html__( 'User Field Status', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => Helper::get_compare_options(),
				'default' => 'isset',
				'condition' => [
					'dce_visibility_usermeta!' => '',
				],
			]
		);
		$element->add_control(
				'dce_visibility_usermeta_value',
				[
					'label' => esc_html__( 'User Field Value', 'dynamic-visibility-for-elementor' ),
					'type' => Controls_Manager::TEXT,
					'description' => esc_html__( 'The specific value of the User Field', 'dynamic-visibility-for-elementor' ),
					'ai' => [
						'active' => false,
					],
					'condition' => [
						'dce_visibility_usermeta!' => '',
						'dce_visibility_usermeta_status!' => [ 'not', 'isset' ],
					],
				]
		);

		$element->add_control(
			'dce_visibility_ip',
			[
				'label' => esc_html__( 'Remote IP', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__( 'Type here the list of IP who will be able to view this element. Separate IPs by comma. (ex. "123.123.123.123, 8.8.8.8, 4.4.4.4")', 'dynamic-visibility-for-elementor' )
				. '<br><b>' . esc_html__( 'Your current IP is: ', 'dynamic-visibility-for-elementor' ) . Helper::get_client_ip() . '</b>',
				'separator' => 'before',
				'ai' => [
					'active' => false,
				],
			]
		);
		$element->add_control(
			'dce_visibility_referrer',
			[
				'label' => esc_html__( 'Referrer', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered when previous page is a specific page. Note: the referrer is reported by the visitor and can be faked, so use it as a hint only, never to protect sensitive content.', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_referrer_host_only',
			[
				'label' => esc_html__( 'Check host only', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'description' => esc_html__( 'check only the host part of the URL', 'dynamic-visibility-for-elementor' ),
				'condition' => [
					'dce_visibility_referrer' => 'yes',
				],
			]
		);
		$element->add_control(
			'dce_visibility_referrer_list',
			[
				'label' => esc_html__( 'Specific referral site authorized', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::TEXTAREA,
				'placeholder' => 'facebook.com' . PHP_EOL . 'google.com',
				'description' => esc_html__( 'Only selected referral, once per line. If empty it is triggered for all external site.', 'dynamic-visibility-for-elementor' ),
				'ai' => [
					'active' => false,
				],
				'condition' => [
					'dce_visibility_referrer' => 'yes',
				],
			]
		);

		$element->add_control(
				'dce_visibility_max_user',
				[
					'label' => esc_html__( 'Max per User', 'dynamic-visibility-for-elementor' ),
					'type' => Controls_Manager::NUMBER,
					'min' => 1,
					'separator' => 'before',
				]
		);
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
		if ( ! isset( $settings['dce_visibility_everyone'] ) || ! $settings['dce_visibility_everyone'] ) {

			//roles
			if ( isset( $settings['dce_visibility_role'] ) && ! empty( $settings['dce_visibility_role'] ) ) {
				$triggers['dce_visibility_role'] = esc_html__( 'User Role', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_role'] = true;
				$current_user = wp_get_current_user();
				if ( $current_user->ID ) {
					$user_roles = $current_user->roles; // An user could have multiple roles
					if ( is_array( $settings['dce_visibility_role'] ) ) {
						if ( ( $settings['dce_visibility_role_all'] ?? 'no' ) === 'yes' ) {
							sort( $user_roles );
							sort( $settings['dce_visibility_role'] );
							if ( $user_roles === $settings['dce_visibility_role'] ) {
								$conditions['dce_visibility_role'] = esc_html__( 'User Role', 'dynamic-visibility-for-elementor' );
							}
						} else {
							$tmp_role = array_intersect( $user_roles, $settings['dce_visibility_role'] );
							if ( ! empty( $tmp_role ) ) {
								$conditions['dce_visibility_role'] = esc_html__( 'User Role', 'dynamic-visibility-for-elementor' );
							}
						}
					}
				} elseif ( in_array( 'visitor', $settings['dce_visibility_role'] ) ) {
						$conditions['dce_visibility_role'] = esc_html__( 'User not logged', 'dynamic-visibility-for-elementor' );
				}
			}

			// user
			if ( isset( $settings['dce_visibility_users'] ) && $settings['dce_visibility_users'] ) {
				$triggers['dce_visibility_users'] = esc_html__( 'Specific User', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_users'] = true;

				$users = Helper::str_to_array( ',', $settings['dce_visibility_users'] );
				$is_user = false;
				if ( ! empty( $users ) ) {
					$current_user = wp_get_current_user();
					foreach ( $users as $key => $value ) {
						if ( is_numeric( $value ) ) {
							if ( $value == $current_user->ID ) {
								$is_user = true;
							}
						}
						if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
							if ( $value == $current_user->user_email ) {
								$is_user = true;
							}
						}
						if ( $value == $current_user->user_login ) {
							$is_user = true;
						}
					}
				}
				if ( $is_user ) {
					$conditions['dce_visibility_users'] = esc_html__( 'Specific User', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_can'] ) && $settings['dce_visibility_can'] ) {
				$triggers['dce_visibility_can'] = esc_html__( 'User can', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_can'] = true;

				$user_can = false;
				$user_id = get_current_user_id();
				if ( user_can( $user_id, $settings['dce_visibility_can'] ) ) {
					$user_can = true;
				}
				if ( $user_can ) {
					$conditions['dce_visibility_can'] = esc_html__( 'User can', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_usermeta'] ) && ! empty( $settings['dce_visibility_usermeta'] ) ) {
				$sensitive_fields = [ 'user_pass', 'pass', 'user_activation_key', 'activation_key' ];
				if ( ! in_array( $settings['dce_visibility_usermeta'], $sensitive_fields, true ) ) {
					$triggers['dce_visibility_usermeta'] = esc_html__( 'User Field', 'dynamic-visibility-for-elementor' );
					$required['dce_visibility_usermeta'] = true;

					$current_user = wp_get_current_user();
					if ( Helper::is_validated_user_meta( $settings['dce_visibility_usermeta'] ) ) {
						$usermeta = get_user_meta( $current_user->ID, $settings['dce_visibility_usermeta'], true ); // false for visitor
					} else {
						$usermeta = $current_user->{$settings['dce_visibility_usermeta']};
					}
					$condition_result = Helper::is_condition_satisfied( $usermeta, $settings['dce_visibility_usermeta_status'], $settings['dce_visibility_usermeta_value'] );
					if ( $condition_result ) {
						$conditions['dce_visibility_usermeta'] = esc_html__( 'User Field', 'dynamic-visibility-for-elementor' );
					}
				}
			}

			// referrer
			if ( isset( $settings['dce_visibility_referrer'] ) && $settings['dce_visibility_referrer'] && $settings['dce_visibility_referrer_list'] ) {
				$triggers['dce_visibility_referrer_list'] = esc_html__( 'Referer', 'dynamic-visibility-for-elementor' );

				if ( $_SERVER['HTTP_REFERER'] ) {
					$required['dce_visibility_referrer_list'] = true;
					$raw_referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
					$referrer = parse_url( $raw_referer, PHP_URL_HOST );
					$referrers = explode( PHP_EOL, $settings['dce_visibility_referrer_list'] );
					$referrers = array_map( 'trim', $referrers );
					$ref_found = false;
					foreach ( $referrers as $aref ) {
						if ( $settings['dce_visibility_referrer_host_only'] === 'yes' ) {
							if ( $aref == $referrer ||
								( is_string( $referrer ) && $aref == str_replace( 'www.', '', $referrer ) ) ||
								$aref == $raw_referer ) {
								$ref_found = true;
							}
						} else {
							$arefnh = preg_replace( '$^https?://$', '', $aref );
							$refnh = preg_replace( '$^https?://$', '', $raw_referer );
							if ( $arefnh === $refnh ) {
								$ref_found = true;
							}
						}
					}
					if ( $ref_found ) {
						$conditions['dce_visibility_referrer_list'] = esc_html__( 'Referer', 'dynamic-visibility-for-elementor' );
					}
				}
			}

			if ( isset( $settings['dce_visibility_ip'] ) && $settings['dce_visibility_ip'] ) {
				$triggers['dce_visibility_ip'] = esc_html__( 'Remote IP', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_ip'] = true;

				$ips = explode( ',', $settings['dce_visibility_ip'] );
				$ips = array_map( 'trim', $ips );
				$client_ip = Helper::get_client_ip();
				if ( $client_ip && in_array( $client_ip, $ips ) ) {
					$conditions['dce_visibility_ip'] = esc_html__( 'Remote IP', 'dynamic-visibility-for-elementor' );
				}
			}
		}

		if ( ! empty( $settings['dce_visibility_max_user'] ) ) {
			$triggers['dce_visibility_max_user'] = esc_html__( 'Max per User', 'dynamic-visibility-for-elementor' );
			$user_id = get_current_user_id();
			if ( $user_id ) {
				$required['dce_visibility_max_user'] = true;
				$dce_visibility_max_user = get_user_meta( $user_id, 'dce_visibility_max_user', true );
				$dce_visibility_max_user_count = 0;
				if ( ! empty( $dce_visibility_max_user[ $element->get_id() ] ) ) {
					$dce_visibility_max_user_count = $dce_visibility_max_user[ $element->get_id() ];
				}
				if ( $settings['dce_visibility_max_user'] >= $dce_visibility_max_user_count ) {
					$conditions['dce_visibility_max_user'] = esc_html__( 'Max per User', 'dynamic-visibility-for-elementor' );
				}
			}
		}
	}
}
