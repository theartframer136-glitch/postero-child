<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace DynamicVisibilityForElementor\Extensions\DynamicVisibility\Triggers;

use Elementor\Controls_Manager;
use DynamicVisibilityForElementor\Helper;

class Post extends Base {
	/**
	 * @param \Elementor\Element_Base $element
	 * @return void
	 */
	public function register_controls( $element ) {
		$element->add_control(
			'dce_visibility_post_id',
			[
				'label' => esc_html__( 'Post ID', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'current' => [
						'title' => esc_html__( 'Current', 'dynamic-visibility-for-elementor' ),
						'icon' => 'fa fa-list',
					],
					'global' => [
						'title' => esc_html__( 'Global', 'dynamic-visibility-for-elementor' ),
						'icon' => 'fa fa-globe',
					],
					'static' => [
						'title' => esc_html__( 'Static', 'dynamic-visibility-for-elementor' ),
						'icon' => 'eicon-pencil',
					],
				],
				'default' => 'current',
				'toggle' => false,
			]
		);
		$element->add_control(
			'dce_visibility_post_id_static',
			[
				'label' => esc_html__( 'Set Post ID', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'condition' => [
					'dce_visibility_post_id' => 'static',
				],
			]
		);
		$element->add_control(
			'dce_visibility_post_id_description',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => '<small>' . esc_html__( 'In some cases, Current ID and Global ID may be different. For example, if you use a widget with a loop on a page, then Global ID will be Page ID, and Current ID will be Post ID in preview inside the loop.', 'dynamic-visibility-for-elementor' ) . '</small>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
			]
		);

		$element->add_control(
			'dce_visibility_cpt',
			[
				'label' => esc_html__( 'Post Type', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT2,
				'options' => Helper::get_public_post_types(),
				'multiple' => true,
				'label_block' => true,
				'query_type' => 'posts',
				'object_type' => 'type',
			]
		);

		$element->add_control(
			'dce_visibility_post',
			[
				'label' => esc_html__( 'Page/Post', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Post Title', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'query_type' => 'posts',
				'dynamic' => [
					'active' => false,
				],
				'multiple' => true,
				'separator' => 'before',
			]
		);

		$taxonomies = Helper::get_taxonomies();

		$element->add_control(
			'dce_visibility_tax',
			[
				'label' => esc_html__( 'Taxonomy', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT2,
				'options' => $taxonomies,
				'multiple' => false,
				'separator' => 'before',
			]
		);

		foreach ( $taxonomies as $tkey => $atax ) {
			if ( $tkey ) {
				$element->add_control(
					'dce_visibility_term_' . $tkey,
					[
						'label' => esc_html__( 'Terms', 'dynamic-visibility-for-elementor' ),
						'type' => 'ooo_query',
						'placeholder' => esc_html__( 'Term Name', 'dynamic-visibility-for-elementor' ),
						'label_block' => true,
						'query_type' => 'terms',
						'object_type' => $tkey,
						'multiple' => true,
						'condition' => [
							'dce_visibility_tax' => $tkey,
						],
					]
				);
			}
		}
		$element->add_control(
			'dce_visibility_field',
			[
				'label' => esc_html__( 'Post Field', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Meta key or Name', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'query_type' => 'fields',
				'object_type' => 'post',
				'separator' => 'before',
				'dynamic' => [
					'active' => false,
				],
			]
		);

		$element->add_control(
			'dce_visibility_field_status',
			[
				'label' => esc_html__( 'Post Field Status', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'options' => Helper::get_compare_options(),
				'default' => 'isset',
				'condition' => [
					'dce_visibility_field!' => '',
				],
			]
		);
		$element->add_control(
			'dce_visibility_field_value',
			[
				'label' => esc_html__( 'Post Field Value', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__( 'The specific value of the Post Field', 'dynamic-visibility-for-elementor' ),
				'ai' => [
					'active' => false,
				],
				'condition' => [
					'dce_visibility_field!' => '',
					'dce_visibility_field_status!' => [ 'not', 'isset' ],
				],
			]
		);

		$element->add_control(
			'dce_visibility_meta',
			[
				'label' => esc_html__( 'Multiple Metas', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Meta key or Name', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'query_type' => 'metas',
				'object_type' => 'post',
				'description' => esc_html__( 'Triggered by specifics metas fields if they are valorized', 'dynamic-visibility-for-elementor' ),
				'multiple' => true,
				'separator' => 'before',
			]
		);

		$element->add_control(
			'dce_visibility_meta_operator',
			[
				'label' => esc_html__( 'Meta conditions', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'label_on' => esc_html__( 'And', 'dynamic-visibility-for-elementor' ),
				'label_off' => esc_html__( 'Or', 'dynamic-visibility-for-elementor' ),
				'description' => esc_html__( 'How post meta have to satisfy this condition', 'dynamic-visibility-for-elementor' ),
				'condition' => [
					'dce_visibility_meta!' => '',
				],
			]
		);

		$element->add_control(
			'dce_visibility_format',
			[
				'label' => esc_html__( 'Format', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT2,
				'options' => Helper::get_post_formats(),
				'multiple' => true,
				'separator' => 'before',
			]
		);

		$element->add_control(
			'dce_visibility_parent',
			[
				'label' => esc_html__( 'Is Parent', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered for post with children', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_root',
			[
				'label' => esc_html__( 'Is Root', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered for first level posts (without parent)', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_leaf',
			[
				'label' => esc_html__( 'Is Leaf', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered for last level posts (without children)', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_node',
			[
				'label' => esc_html__( 'Is Node', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered for intermedial level posts (with parent and child)', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_node_level',
			[
				'label' => esc_html__( 'Node level', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'condition' => [
					'dce_visibility_node!' => '',
				],
			]
		);
		$element->add_control(
			'dce_visibility_level',
			[
				'label' => esc_html__( 'Has Level', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'description' => esc_html__( 'Triggered for specific level posts', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_child',
			[
				'label' => esc_html__( 'Has Parent', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered for children posts (with a parent)', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_child_parent',
			[
				'label' => esc_html__( 'Specific Parent Post IDs', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Post Title', 'dynamic-visibility-for-elementor' ),
				'label_block' => true,
				'multiple' => true,
				'separator' => 'before',
				'query_type' => 'posts',
				'condition' => [
					'dce_visibility_child!' => '',
				],
			]
		);

		$element->add_control(
			'dce_visibility_sibling',
			[
				'label' => esc_html__( 'Has Siblings', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered for post with siblings', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_friend',
			[
				'label' => esc_html__( 'Has Term Buddies', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Triggered for posts grouped in taxonomies with other posts', 'dynamic-visibility-for-elementor' ),
				'separator' => 'before',
			]
		);
		$element->add_control(
			'dce_visibility_friend_term',
			[
				'label' => esc_html__( 'Terms where find Buddies', 'dynamic-visibility-for-elementor' ),
				'type' => 'ooo_query',
				'placeholder' => esc_html__( 'Term Name', 'dynamic-visibility-for-elementor' ),
				'query_type' => 'terms',
				'description' => esc_html__( 'Specific a Term for current post has friends.', 'dynamic-visibility-for-elementor' ),
				'multiple' => true,
				'label_block' => true,
				'condition' => [
					'dce_visibility_friend!' => '',
				],
			]
		);

		$element->add_control(
			'dce_visibility_conditional_tags_post',
			[
				'label' => esc_html__( 'Conditional Tags - Post', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT2,
				'options' => static::get_whitelist_post_functions(),
				'multiple' => true,
				'separator' => 'before',
				'condition' => [
					'dce_visibility_post_id' => 'current',
				],
			]
		);
		$element->add_control(
			'dce_visibility_special',
			[
				'label' => esc_html__( 'Conditional Tags - Page', 'dynamic-visibility-for-elementor' ),
				'type' => Controls_Manager::SELECT2,
				'options' => static::get_whitelist_page_functions(),
				'multiple' => true,
				'separator' => 'before',
				'condition' => [
					'dce_visibility_post_id' => 'current',
				],
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

		if ( ! isset( $settings['dce_visibility_context'] ) || ! $settings['dce_visibility_context'] ) {
			// cpt
			if ( isset( $settings['dce_visibility_cpt'] ) && ! empty( $settings['dce_visibility_cpt'] ) && is_array( $settings['dce_visibility_cpt'] ) ) {
				$triggers['dce_visibility_cpt'] = esc_html__( 'Post Type', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_cpt'] = true;

				$cpt = get_post_type();

				if ( in_array( $cpt, $settings['dce_visibility_cpt'] ) ) {
					$conditions['dce_visibility_cpt'] = esc_html__( 'Post Type', 'dynamic-visibility-for-elementor' );
				}
			}

			// post
			if ( ! empty( $settings['dce_visibility_post'] ) && is_array( $settings['dce_visibility_post'] ) ) {
				$triggers['dce_visibility_post'] = esc_html__( 'Post', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_post'] = true;
				if ( Helper::is_plugin_active( 'wpml' ) ) {
					$visibility_post = Helper::wpml_translate_object_id( $settings['dce_visibility_post'] );
				} else {
					$visibility_post = $settings['dce_visibility_post'];
				}

				if ( in_array( $post_ID, $visibility_post ) ) {
					$conditions['dce_visibility_post'] = esc_html__( 'Post', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_tax'] ) && $settings['dce_visibility_tax'] ) {
				$triggers['dce_visibility_tax'] = esc_html__( 'Taxonomy', 'dynamic-visibility-for-elementor' );

				$tax = get_post_taxonomies();

				if ( in_array( $settings['dce_visibility_tax'], $tax ) ) {
					// term
					$terms = get_the_terms( $post_ID, $settings['dce_visibility_tax'] );
					$tmp = [];
					if ( ! empty( $terms ) ) {
						if ( ! is_object( $terms ) ) {
							foreach ( $terms as $aterm ) {
								$tmp[ $aterm->term_id ] = $aterm->term_id;
							}
						}
						$terms = $tmp;
					}

					$tkey = 'dce_visibility_term_' . $settings['dce_visibility_tax'];
					if ( ! empty( $settings[ $tkey ] ) && is_array( $settings[ $tkey ] ) ) {
						$required[ $tkey ] = true;
						if ( ! empty( $terms ) ) {
							// Retrieve terms searched on the current language
							$term_searched_current_language = Helper::wpml_translate_object_id_by_type( $settings[ $tkey ], $settings['dce_visibility_tax'] );
							if ( array_intersect( $terms, $term_searched_current_language ) ) {
								$conditions[ $tkey ] = esc_html__( 'Taxonomy', 'dynamic-visibility-for-elementor' );
							}
						}
					} else {
						$required['dce_visibility_tax'] = true;
						$conditions['dce_visibility_tax'] = esc_html__( 'Taxonomy', 'dynamic-visibility-for-elementor' );
					}
				}
			}
			// meta
			if ( isset( $settings['dce_visibility_meta'] ) && is_array( $settings['dce_visibility_meta'] ) && ! empty( $settings['dce_visibility_meta'] ) ) {
				$triggers['dce_visibility_meta'] = esc_html__( 'Post Metas', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_meta'] = true;

				$post_metas = $settings['dce_visibility_meta'];
				$metafirst = true;
				$metavalued = false;
				foreach ( $post_metas as $mkey => $ameta ) {
					if ( is_author() ) {
						$author_id = intval( get_the_author_meta( 'ID' ) ); // phpstan
						$mvalue = get_user_meta( $author_id, $ameta, true );
					} else {
						$mvalue = get_post_meta( $post_ID, $ameta, true );
						if ( is_array( $mvalue ) && empty( $mvalue ) ) {
							$mvalue = false;
						}
					}
					if ( $settings['dce_visibility_meta_operator'] ) { // AND
						if ( $metafirst && $mvalue ) {
							$metavalued = true;
						}
						if ( ! $metavalued || ! $mvalue ) {
							$metavalued = false;
						}
					} elseif ( $metavalued || $mvalue ) { // OR
							$metavalued = true;
					}
					$metafirst = false;
				}

				if ( $metavalued ) {
					$conditions['dce_visibility_meta'] = esc_html__( 'Post Metas', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_field'] ) && ! empty( $settings['dce_visibility_field'] ) ) {
				$triggers['dce_visibility_field'] = esc_html__( 'Post Field', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_field'] = true;
				$postmeta = Helper::get_post_value( $post_ID, $settings['dce_visibility_field'] );
				$condition_result = Helper::is_condition_satisfied( $postmeta, $settings['dce_visibility_field_status'], $settings['dce_visibility_field_value'] );

				if ( $condition_result ) {
					$conditions['dce_visibility_field'] = esc_html__( 'Post Field', 'dynamic-visibility-for-elementor' );
				}
			}
			if ( isset( $settings['dce_visibility_root'] ) && $settings['dce_visibility_root'] ) {
				$triggers['dce_visibility_root'] = esc_html__( 'Post is Root', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_root'] = true;

				if ( ! wp_get_post_parent_id( $post_ID ) ) {
					$conditions['dce_visibility_root'] = esc_html__( 'Post is Root', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_format'] ) && ! empty( $settings['dce_visibility_format'] ) ) {
				$triggers['dce_visibility_format'] = esc_html__( 'Post Format', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_format'] = true;

				$format = get_post_format( $post_ID ) ?: 'standard';

				if ( in_array( $format, $settings['dce_visibility_format'] ) ) {
					$conditions['dce_visibility_format'] = esc_html__( 'Post Format', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_parent'] ) && $settings['dce_visibility_parent'] ) {
				$triggers['dce_visibility_parent'] = esc_html__( 'Post is Parent', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_parent'] = true;

				$args = [
					'post_parent' => $post_ID,
					'post_type' => get_post_type(),
					'numberposts' => -1,
					'post_status' => 'publish',
				];
				$children = get_children( $args );

				if ( ! empty( $children ) ) {
					$conditions['dce_visibility_parent'] = esc_html__( 'Post is Parent', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_leaf'] ) && $settings['dce_visibility_leaf'] ) {
				$triggers['dce_visibility_leaf'] = esc_html__( 'Post is Leaf', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_leaf'] = true;

				$args = [
					'post_parent' => $post_ID,
					'post_type' => get_post_type(),
					'numberposts' => -1,
					'post_status' => 'publish',
				];
				$children = get_children( $args );

				if ( empty( $children ) ) {
					$conditions['dce_visibility_leaf'] = esc_html__( 'Post is Leaf', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_node'] ) && $settings['dce_visibility_node'] ) {
				$triggers['dce_visibility_node'] = esc_html__( 'Post is Node', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_node'] = true;

				if ( wp_get_post_parent_id( $post_ID ) ) {
					$args = [
						'post_parent' => $post_ID,
						'post_type' => get_post_type(),
						'numberposts' => -1,
						'post_status' => 'publish',
					];
					$children = get_children( $args );
					if ( ! empty( $children ) ) {
						$parents = get_post_ancestors( $post_ID );
						$node_level = count( $parents ) + 1;
						if ( empty( $settings['dce_visibility_node_level'] ) || $node_level == $settings['dce_visibility_node_level'] ) {
							$conditions['dce_visibility_node'] = esc_html__( 'Post is Node', 'dynamic-visibility-for-elementor' );
						}
					}
				}
			}

			if ( isset( $settings['dce_visibility_level'] ) && $settings['dce_visibility_level'] ) {
				$triggers['dce_visibility_level'] = esc_html__( 'Post is Node', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_level'] = true;

				$parents = get_post_ancestors( $post_ID );
				$node_level = count( $parents ) + 1;

				if ( $node_level == $settings['dce_visibility_level'] ) {
					$conditions['dce_visibility_level'] = esc_html__( 'Post has Level', 'dynamic-visibility-for-elementor' );
				}
			}

			if ( isset( $settings['dce_visibility_child'] ) && $settings['dce_visibility_child'] ) {
				$triggers['dce_visibility_child'] = esc_html__( 'Post has Parent', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_child'] = true;

				if ( $post_parent_ID = wp_get_post_parent_id( $post_ID ) ) {
					$parent_ids = Helper::str_to_array( ',', $settings['dce_visibility_child_parent'] );
					if ( empty( $settings['dce_visibility_child_parent'] ) || in_array( $post_parent_ID, $parent_ids ) ) {
						$conditions['dce_visibility_child'] = esc_html__( 'Post has Parent', 'dynamic-visibility-for-elementor' );
					}
				}
			}

			if ( isset( $settings['dce_visibility_sibling'] ) && $settings['dce_visibility_sibling'] ) {
				$triggers['dce_visibility_sibling'] = esc_html__( 'Post has Siblings', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_sibling'] = true;

				if ( $post_parent_ID = wp_get_post_parent_id( $post_ID ) ) {
					$args = [
						'post_parent' => $post_parent_ID,
						'post_type' => get_post_type(),
						'posts_per_page' => -1,
						'post_status' => 'publish',
					];
					$children = get_children( $args );
					if ( ! empty( $children ) && count( $children ) > 1 ) {
						$conditions['dce_visibility_sibling'] = esc_html__( 'Post has Siblings', 'dynamic-visibility-for-elementor' );
					}
				}
			}

			if ( isset( $settings['dce_visibility_friend'] ) && $settings['dce_visibility_friend'] ) {
				$triggers['dce_visibility_friend'] = esc_html__( 'Post has Friends', 'dynamic-visibility-for-elementor' );
				$required['dce_visibility_friend'] = true;

				$posts_ids = [];
				if ( $settings['dce_visibility_friend_term'] ) {
					$term = get_term( $settings['dce_visibility_friend_term'] );
					$terms = is_wp_error( $term ) ? [] : [ $term ];
				} else {
					$post_type = get_post_type( $post_ID );
					if ( $post_type ) {
						$terms = wp_get_post_terms( $post_ID, get_object_taxonomies( $post_type ) );
						$terms = is_wp_error( $terms ) ? [] : $terms;
					} else {
						$terms = [];
					}
				}
				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						if ( ! $term ) {
							continue;
						}
						$post_args = [
							'posts_per_page' => -1,
							'post_type' => get_post_type(),
							'tax_query' => [
								[
									'taxonomy' => $term->taxonomy,
									'field' => 'term_id',
									'terms' => (int) $term->term_id,
								],
							],
							'suppress_filters' => false,
						];
						/** @var array{posts_per_page:int,post_type:string,tax_query:array{array{taxonomy:string,field:string,terms:int}},suppress_filters:bool} $post_args */
						$term_posts = get_posts( $post_args );
						if ( ! empty( $term_posts ) && count( $term_posts ) > 1 ) {
							$posts_ids = wp_list_pluck( $term_posts, 'ID' );
							if ( in_array( $post_ID, $posts_ids ) ) {
								$conditions['dce_visibility_friend'] = esc_html__( 'Post has Friends', 'dynamic-visibility-for-elementor' );
								break;
							}
						}
					}
				}
			}
		}

		// Conditional Tags - Post
		if ( ! empty( $settings['dce_visibility_conditional_tags_post'] ) && is_array( $settings['dce_visibility_conditional_tags_post'] ) ) {

			$required['dce_visibility_conditional_tags_post'] = true;
			$callable_functions = array_filter( $settings['dce_visibility_conditional_tags_post'], function ( $function ) {
				return in_array( $function, array_keys( self::get_whitelist_post_functions() ), true ) && is_callable( $function );
			});

			$condition_satisfied = false;

			foreach ( $callable_functions as $function ) {
				switch ( $function ) {
					case 'is_post_type_hierarchical':
					case 'is_post_type_archive':
						if ( call_user_func( $function, get_post_type() ?: [] ) ) {
							$condition_satisfied = true;
						}
						break;
					case 'has_post_thumbnail':
						if ( call_user_func( $function, $post_ID ) ) {
							$condition_satisfied = true;
						}
						break;
					default:
						if ( call_user_func( $function ) ) {
							$condition_satisfied = true;
						}
				}

				if ( $condition_satisfied ) {
					$conditions['dce_visibility_conditional_tags_post'] = esc_html__( 'Conditional tags Post', 'dynamic-visibility-for-elementor' );
					break;
				}
			}
		}

		// Conditional Tags - Page
		if ( ! empty( $settings['dce_visibility_special'] ) && is_array( $settings['dce_visibility_special'] ) ) {
			$triggers['dce_visibility_special'] = esc_html__( 'Conditional tags Special', 'dynamic-visibility-for-elementor' );
			$required['dce_visibility_special'] = true;

			$callable_functions = array_filter( $settings['dce_visibility_special'], function ( $function ) {
				return in_array( $function, array_keys( self::get_whitelist_page_functions() ), true ) && is_callable( $function );
			});

			foreach ( $callable_functions as $function ) {
				if ( call_user_func( $function ) ) {
					$conditions['dce_visibility_special'] = esc_html__( 'Conditional tags Special', 'dynamic-visibility-for-elementor' );
					break;
				}
			}
		}
	}

	/**
	 * Post Functions
	 *
	 * @return array<string,string>
	 */
	public static function get_whitelist_post_functions() {
		return [
			'is_sticky' => esc_html__( 'Is Sticky', 'dynamic-visibility-for-elementor' ),
			'is_post_type_hierarchical' => esc_html__( 'Is Hierarchical Post Type', 'dynamic-visibility-for-elementor' ),
			'is_post_type_archive' => esc_html__( 'Is Post Type Archive', 'dynamic-visibility-for-elementor' ),
			'comments_open' => esc_html__( 'Comments are open', 'dynamic-visibility-for-elementor' ),
			'pings_open' => esc_html__( 'Pings are open', 'dynamic-visibility-for-elementor' ),
			'has_tag' => esc_html__( 'Has Tags', 'dynamic-visibility-for-elementor' ),
			'has_term' => esc_html__( 'Has Terms', 'dynamic-visibility-for-elementor' ),
			'has_excerpt' => esc_html__( 'Has Excerpt', 'dynamic-visibility-for-elementor' ),
			'has_post_thumbnail' => esc_html__( 'Has Post Thumbnail', 'dynamic-visibility-for-elementor' ),
			'has_nav_menu' => esc_html__( 'Has Nav menu', 'dynamic-visibility-for-elementor' ),
		];
	}

	/**
	 * Page Functions
	 *
	 * @return array<string,string>
	 */
	public static function get_whitelist_page_functions() {
		return [
			'is_front_page' => esc_html__( 'Front Page', 'dynamic-visibility-for-elementor' ),
			'is_home' => esc_html__( 'Home', 'dynamic-visibility-for-elementor' ),
			'is_404' => esc_html__( '404 Not Found', 'dynamic-visibility-for-elementor' ),
			'is_single' => esc_html__( 'Single', 'dynamic-visibility-for-elementor' ),
			'is_page' => esc_html__( 'Page', 'dynamic-visibility-for-elementor' ),
			'is_attachment' => esc_html__( 'Attachment', 'dynamic-visibility-for-elementor' ),
			'is_preview' => esc_html__( 'Preview', 'dynamic-visibility-for-elementor' ),
			'is_admin' => esc_html__( 'Admin', 'dynamic-visibility-for-elementor' ),
			'is_page_template' => esc_html__( 'Page Template', 'dynamic-visibility-for-elementor' ),
			'is_comments_popup' => esc_html__( 'Comments Popup', 'dynamic-visibility-for-elementor' ),
			'is_woocommerce' => esc_html__( 'WooCommerce Page', 'dynamic-visibility-for-elementor' ),
			'is_shop' => esc_html__( 'Shop', 'dynamic-visibility-for-elementor' ),
			'is_product' => esc_html__( 'Product', 'dynamic-visibility-for-elementor' ),
			'is_product_taxonomy' => esc_html__( 'Product Taxonomy', 'dynamic-visibility-for-elementor' ),
			'is_product_category' => esc_html__( 'Product Category', 'dynamic-visibility-for-elementor' ),
			'is_product_tag' => esc_html__( 'Product Tag', 'dynamic-visibility-for-elementor' ),
			'is_cart' => esc_html__( 'Cart', 'dynamic-visibility-for-elementor' ),
			'is_checkout' => esc_html__( 'Checkout', 'dynamic-visibility-for-elementor' ),
			'is_add_payment_method_page' => esc_html__( 'Add Payment method', 'dynamic-visibility-for-elementor' ),
			'is_checkout_pay_page' => esc_html__( 'Checkout Page', 'dynamic-visibility-for-elementor' ),
			'is_account_page' => esc_html__( 'Account Page', 'dynamic-visibility-for-elementor' ),
			'is_edit_account_page' => esc_html__( 'Edit Account', 'dynamic-visibility-for-elementor' ),
			'is_lost_password_page' => esc_html__( 'Lost Password', 'dynamic-visibility-for-elementor' ),
			'is_view_order_page' => esc_html__( 'Order Summary', 'dynamic-visibility-for-elementor' ),
			'is_order_received_page' => esc_html__( 'Order Received', 'dynamic-visibility-for-elementor' ),
		];
	}
}
