<?php
/**
 * Save Menu.
 *
 * Creates a WordPress menu, or updates an existing menu's items and locations.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Menus;

use PremiumAddons\Admin\Includes\Admin_Helper;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 *
 * Validates the whole request before writing anything. Writes run in an
 * order that keeps the old items until the new ones are saved, and items
 * created by a failed call are removed again.
 *
 * @since 4.11.110
 */
class Save_Menu implements Ability_Handler {

	/**
	 * Input item fields that set what the item links to => wp_update_nav_menu_item() argument.
	 *
	 * @var array
	 */
	const LINK_FIELD_MAP = array(
		'type'      => 'menu-item-type',
		'object'    => 'menu-item-object',
		'object_id' => 'menu-item-object-id',
		'url'       => 'menu-item-url',
	);

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'save-menu';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Create or Edit Menu', 'premium-addons-for-elementor' ),
			'description'         => __( 'Creates a menu or updates its items and locations.', 'premium-addons-for-elementor' )
				. "\n\n"
				. __( 'To edit an existing menu, call premium-addons/list-menus first and pass item_id on every item to keep. Then set Premium Mega Menu options with premium-addons/configure-menu-item.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-menus',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'menu_id'   => array(
						'type'        => 'integer',
						'description' => __( 'The menu to update. Omit to create a new menu.', 'premium-addons-for-elementor' ),
					),
					'name'      => array(
						'type'        => 'string',
						'description' => __( 'The menu name. Required when creating; renames the menu when updating.', 'premium-addons-for-elementor' ),
					),
					'locations' => array(
						'type'        => 'array',
						'description' => __( 'Theme menu location slugs (from premium-addons/list-menus) to show this menu in. Other locations are left as they are. Not needed for the Premium Mega Menu widget.', 'premium-addons-for-elementor' ),
						'items'       => array( 'type' => 'string' ),
					),
					'mode'      => array(
						'type'        => 'string',
						'enum'        => array( 'append', 'replace' ),
						'default'     => 'append',
						'description' => __( 'append adds new items after the existing ones. replace makes the menu exactly the items sent, in that order, and deletes every existing item not listed by item_id. Use replace to reorder. Defaults to append.', 'premium-addons-for-elementor' ),
					),
					'items'     => array(
						'type'        => 'array',
						'description' => __( 'The menu items, top level first. Each item nests its sub-items in children.', 'premium-addons-for-elementor' ),
						'items'       => $this->get_item_input_schema(),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The saved menu.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'menu_id'                 => array(
						'type'        => 'integer',
						'description' => __( 'The menu ID. Pass it as pa_nav_menus to the Premium Mega Menu widget.', 'premium-addons-for-elementor' ),
					),
					'name'                    => array(
						'type'        => 'string',
						'description' => __( 'The menu name.', 'premium-addons-for-elementor' ),
					),
					'locations'               => array(
						'type'        => 'array',
						'description' => __( 'Every location now showing this menu.', 'premium-addons-for-elementor' ),
						'items'       => array( 'type' => 'string' ),
					),
					'items'                   => array(
						'type'        => 'array',
						'description' => __( 'The items sent, flattened in menu order.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'item_id'   => array(
									'type'        => 'integer',
									'description' => __( 'The menu item ID. Pass it to premium-addons/configure-menu-item.', 'premium-addons-for-elementor' ),
								),
								'title'     => array(
									'type'        => 'string',
									'description' => __( 'The label the item shows.', 'premium-addons-for-elementor' ),
								),
								'depth'     => array(
									'type'        => 'integer',
									'description' => __( 'The nesting level, 0 for top level.', 'premium-addons-for-elementor' ),
								),
								'parent_id' => array(
									'type'        => 'integer',
									'description' => __( 'The parent item ID, 0 for top level.', 'premium-addons-for-elementor' ),
								),
								'created'   => array(
									'type'        => 'boolean',
									'description' => __( 'Whether this call created the item.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
					'replaced_location_menus' => array(
						'type'        => 'array',
						'description' => __( 'Locations that showed another menu before this call.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'location' => array( 'type' => 'string' ),
								'menu_id'  => array( 'type' => 'integer' ),
							),
						),
					),
					'deleted_item_ids'        => array(
						'type'        => 'array',
						'description' => __( 'Items replace mode deleted.', 'premium-addons-for-elementor' ),
						'items'       => array( 'type' => 'integer' ),
					),
					'orphaned_template_ids'   => array(
						'type'        => 'array',
						'description' => __( 'Mega content templates of deleted items. They stay in Templates → Saved Templates and are no longer used by the menu.', 'premium-addons-for-elementor' ),
						'items'       => array( 'type' => 'integer' ),
					),
					'warnings'                => $this->get_warnings_output_schema(),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'edit_theme_options' );
			},
			'meta'                => array(
				// Don't want Angie to see this.
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		$input = is_array( $input ) ? $input : array();
		$mode  = isset( $input['mode'] ) && 'replace' === $input['mode'] ? 'replace' : 'append';

		$menu = $this->resolve_menu( $input );

		if ( is_wp_error( $menu ) ) {
			return $menu;
		}

		$name = $this->resolve_name( $input, $menu );

		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$locations = isset( $input['locations'] ) ? array_values( array_unique( $input['locations'] ) ) : array();
		$error     = $this->validate_locations( $locations );

		if ( $error ) {
			return $error;
		}

		// Without this guard, a call meant only to set a location would empty the menu.
		if ( 'replace' === $mode && ! array_key_exists( 'items', $input ) ) {
			return new \WP_Error(
				'premium_addons_missing_items',
				__( 'mode replace needs an items list: it deletes every existing item not listed. Send items, or use mode append to leave the items alone.', 'premium-addons-for-elementor' ),
				array( 'status' => 400 )
			);
		}

		$member_ids = $menu ? Menu_Item_Fields::get_menu_item_ids( $menu->term_id ) : array();
		$tree       = $this->prepare_tree( isset( $input['items'] ) ? $input['items'] : array(), $member_ids );

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		return $this->save( $menu, $name, $mode, $tree, $member_ids, $locations );
	}

	/**
	 * Get the menu to update.
	 *
	 * @param array $input Ability input.
	 * @return \WP_Term|null|\WP_Error Null when creating a menu.
	 */
	private function resolve_menu( $input ) {

		if ( ! isset( $input['menu_id'] ) ) {
			return null;
		}

		$menu_id = absint( $input['menu_id'] );
		$menu    = get_term( $menu_id, 'nav_menu' );

		if ( $menu instanceof \WP_Term ) {
			return $menu;
		}

		return new \WP_Error(
			'premium_addons_invalid_menu_id',
			/* translators: %d: menu ID. */
			sprintf( __( 'No menu found with ID %d. Call premium-addons/list-menus to see every menu, or omit menu_id to create one.', 'premium-addons-for-elementor' ), $menu_id ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Get the name the menu should have after this call.
	 *
	 * @param array         $input Ability input.
	 * @param \WP_Term|null $menu  Menu to update, null when creating.
	 * @return string|\WP_Error
	 */
	private function resolve_name( $input, $menu ) {

		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

		if ( '' === $name ) {
			return $menu ? $menu->name : new \WP_Error(
				'premium_addons_missing_name',
				__( 'A name is required to create a menu.', 'premium-addons-for-elementor' ),
				array( 'status' => 400 )
			);
		}

		// Same lookup core uses, so the match is case-insensitive like core's own check.
		$existing = get_term_by( 'name', $name, 'nav_menu' );

		if ( $existing && ( ! $menu || $existing->term_id !== $menu->term_id ) ) {
			return new \WP_Error(
				'premium_addons_invalid_name',
				/* translators: 1: menu name, 2: existing menu ID. */
				sprintf( __( 'A menu named "%1$s" already exists (menu_id %2$d). Pass menu_id %2$d to edit it, or choose another name.', 'premium-addons-for-elementor' ), $name, $existing->term_id ),
				array(
					'status'           => 409,
					'existing_menu_id' => $existing->term_id,
				)
			);
		}

		return $name;
	}

	/**
	 * Check that every location is registered by the active theme.
	 *
	 * @param string[] $locations Location slugs.
	 * @return \WP_Error|null
	 */
	private function validate_locations( $locations ) {

		$registered = array_keys( get_registered_nav_menus() );
		$unknown    = array_diff( $locations, $registered );

		if ( empty( $unknown ) ) {
			return null;
		}

		return new \WP_Error(
			'premium_addons_invalid_locations',
			sprintf(
				/* translators: 1: unknown location slugs, 2: registered location slugs. */
				__( 'Unknown menu location(s): %1$s. This theme registers: %2$s.', 'premium-addons-for-elementor' ),
				implode( ', ', $unknown ),
				empty( $registered ) ? __( 'none (block themes have no menu locations)', 'premium-addons-for-elementor' ) : implode( ', ', $registered )
			),
			array(
				'status'  => 400,
				'invalid' => array_values( $unknown ),
			)
		);
	}

	/**
	 * Validate the item tree and flatten it into write-ready nodes.
	 *
	 * @param array $items      Input items.
	 * @param int[] $member_ids IDs of the items the menu holds now.
	 * @return array|\WP_Error { nodes, warnings }
	 */
	private function prepare_tree( $items, $member_ids ) {

		Menu_Item_Fields::prime_linked_objects( $this->collect_input_links( $items ) );

		$tree = array(
			'members'  => array_flip( $member_ids ),
			'seen'     => array(),
			'bad_ids'  => array(),
			'nodes'    => array(),
			'warnings' => array(),
		);

		$error = $this->collect_nodes( $items, -1, 0, $tree );

		if ( $error ) {
			return $error;
		}

		if ( ! empty( $tree['bad_ids'] ) ) {
			$bad_ids = array_values( array_unique( $tree['bad_ids'] ) );

			return new \WP_Error(
				'premium_addons_invalid_object_id',
				/* translators: %s: comma-separated IDs. */
				sprintf( __( 'These object_id values match no post or term of the given object, or are in the trash: %s. Nothing was saved.', 'premium-addons-for-elementor' ), implode( ', ', $bad_ids ) ),
				array(
					'status'  => 404,
					'bad_ids' => $bad_ids,
				)
			);
		}

		return $tree;
	}

	/**
	 * Add one branch of input items to the flat node list, depth first.
	 *
	 * @param array $items        Input items of this branch.
	 * @param int   $parent_index Index of the parent node, -1 for top level.
	 * @param int   $depth        Depth of this branch.
	 * @param array $tree         Tree state from prepare_tree(), by reference.
	 * @return \WP_Error|null
	 */
	private function collect_nodes( $items, $parent_index, $depth, &$tree ) {

		foreach ( $items as $item ) {

			// WordPress validates the top level only; children reuse the same schema here.
			$valid = rest_validate_value_from_schema( $item, $this->get_item_input_schema(), 'items' );

			if ( is_wp_error( $valid ) ) {
				return new \WP_Error( 'premium_addons_invalid_items', $valid->get_error_message(), array( 'status' => 400 ) );
			}

			$node = $this->prepare_node( $item, $depth, $tree );

			if ( is_wp_error( $node ) ) {
				return $node;
			}

			$node['parent']  = $parent_index;
			$tree['nodes'][] = $node;

			if ( ! empty( $item['children'] ) ) {
				$error = $this->collect_nodes( $item['children'], count( $tree['nodes'] ) - 1, $depth + 1, $tree );

				if ( $error ) {
					return $error;
				}
			}
		}

		return null;
	}

	/**
	 * Build the write-ready node for one input item.
	 *
	 * @param array $item  Input item.
	 * @param int   $depth Item depth.
	 * @param array $tree  Tree state, by reference.
	 * @return array|\WP_Error { item_id, depth, fields }
	 */
	private function prepare_node( $item, $depth, &$tree ) {

		$item_id = isset( $item['item_id'] ) ? absint( $item['item_id'] ) : 0;

		if ( $item_id ) {
			$error = $this->claim_existing_item( $item_id, $tree );

			if ( $error ) {
				return $error;
			}

			$this->warn_mega_on_child( $item_id, $depth, $tree );
		} elseif ( empty( $item['type'] ) ) {
			return new \WP_Error(
				'premium_addons_missing_type',
				__( 'Each new menu item needs a type: post_type, taxonomy or custom.', 'premium-addons-for-elementor' ),
				array( 'status' => 400 )
			);
		}

		$fields = $item_id ? Menu_Item_Fields::get_stored_fields( $item_id ) : $this->get_new_item_fields();
		$fields = $this->apply_item_input( $fields, $item );

		if ( $this->needs_link_validation( $item, $item_id ) ) {
			$fields = $this->resolve_link( $fields, ! isset( $item['object'] ), $tree );

			if ( is_wp_error( $fields ) ) {
				return $fields;
			}
		}

		return array(
			'item_id' => $item_id,
			'depth'   => $depth,
			'fields'  => $fields,
		);
	}

	/**
	 * Overlay the input item on its fields.
	 *
	 * @param array $fields Stored or new item fields.
	 * @param array $item   Input item.
	 * @return array
	 */
	private function apply_item_input( $fields, $item ) {

		foreach ( self::LINK_FIELD_MAP as $key => $field ) {
			if ( isset( $item[ $key ] ) ) {
				$fields[ $field ] = $item[ $key ];
			}
		}

		if ( isset( $item['title'] ) ) {
			$fields['menu-item-title'] = wp_kses_post( $item['title'] );
		}

		return $fields;
	}

	/**
	 * Whether an item's link must be validated.
	 *
	 * Kept items whose link the call does not change are saved back exactly as stored.
	 *
	 * @param array $item    Input item.
	 * @param int   $item_id Kept item ID, 0 for a new item.
	 * @return bool
	 */
	private function needs_link_validation( $item, $item_id ) {
		return ! $item_id || ! empty( array_intersect_key( $item, self::LINK_FIELD_MAP ) );
	}

	/**
	 * List the posts and terms the input items link to, children included.
	 *
	 * @param array $items Input items. Children are not schema-validated yet.
	 * @return array Links in item-fields shape: menu-item-type, menu-item-object-id.
	 */
	private function collect_input_links( $items ) {

		$links = array();

		foreach ( $items as $item ) {
			if ( isset( $item['type'], $item['object_id'] ) && is_string( $item['type'] ) ) {
				$links[] = array(
					'menu-item-type'      => $item['type'],
					'menu-item-object-id' => $item['object_id'],
				);
			}

			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				$links = array_merge( $links, $this->collect_input_links( $item['children'] ) );
			}
		}

		return $links;
	}

	/**
	 * Check that an item_id belongs to the menu and is listed once.
	 *
	 * @param int   $item_id Menu item ID.
	 * @param array $tree    Tree state, by reference.
	 * @return \WP_Error|null
	 */
	private function claim_existing_item( $item_id, &$tree ) {

		if ( ! isset( $tree['members'][ $item_id ] ) ) {
			return new \WP_Error(
				'premium_addons_invalid_item_id',
				/* translators: %d: menu item ID. */
				sprintf( __( 'Menu item %d is not in this menu. Call premium-addons/list-menus to get the menu\'s item IDs.', 'premium-addons-for-elementor' ), $item_id ),
				array( 'status' => 404 )
			);
		}

		if ( isset( $tree['seen'][ $item_id ] ) ) {
			return new \WP_Error(
				'premium_addons_invalid_item_id',
				/* translators: %d: menu item ID. */
				sprintf( __( 'Menu item %d is listed more than once.', 'premium-addons-for-elementor' ), $item_id ),
				array( 'status' => 400 )
			);
		}

		$tree['seen'][ $item_id ] = true;

		return null;
	}

	/**
	 * Warn when a kept item with mega content moves below the top level.
	 *
	 * @param int   $item_id Menu item ID.
	 * @param int   $depth   New depth.
	 * @param array $tree    Tree state, by reference.
	 * @return void
	 */
	private function warn_mega_on_child( $item_id, $depth, &$tree ) {

		$settings = $depth ? Mega_Item_Settings::get( $item_id ) : null;

		if ( $settings && $settings['mega_content'] ) {
			$tree['warnings'][] = array(
				'type'   => 'mega_on_child',
				/* translators: %d: menu item ID. */
				'detail' => sprintf( __( 'Menu item %d has mega content but is now a sub-item. Mega content shows on top-level items only.', 'premium-addons-for-elementor' ), $item_id ),
			);
		}
	}

	/**
	 * Get the starting fields of a new item.
	 *
	 * @return array
	 */
	private function get_new_item_fields() {
		return array(
			'menu-item-object-id' => 0,
			'menu-item-object'    => '',
			'menu-item-position'  => 0,
			'menu-item-type'      => '',
			'menu-item-title'     => '',
			'menu-item-url'       => '',
			'menu-item-status'    => 'publish',
		);
	}

	/**
	 * Validate what an item links to and complete its link fields.
	 *
	 * @param array $fields       Item fields with the input applied.
	 * @param bool  $infer_object Work out the post type or taxonomy from object_id.
	 * @param array $tree         Tree state, by reference.
	 * @return array|\WP_Error
	 */
	private function resolve_link( $fields, $infer_object, &$tree ) {

		if ( 'custom' === $fields['menu-item-type'] ) {
			return $this->resolve_custom_link( $fields );
		}

		// Other core types (e.g. post type archives) can only come from a kept item; keep them as stored.
		if ( ! in_array( $fields['menu-item-type'], array( 'post_type', 'taxonomy' ), true ) ) {
			return $fields;
		}

		$object_id = absint( $fields['menu-item-object-id'] );

		if ( ! $object_id ) {
			return new \WP_Error(
				'premium_addons_missing_object_id',
				/* translators: %s: item type. */
				sprintf( __( '%s items need an object_id: the ID of the post, page, product or term to link.', 'premium-addons-for-elementor' ), $fields['menu-item-type'] ),
				array( 'status' => 400 )
			);
		}

		$fields['menu-item-object-id'] = $object_id;

		if ( 'taxonomy' === $fields['menu-item-type'] ) {
			return $this->resolve_term_link( $fields, $infer_object, $tree );
		}

		return $this->resolve_post_link( $fields, $infer_object, $tree );
	}

	/**
	 * Validate a custom link item.
	 *
	 * @param array $fields Item fields.
	 * @return array|\WP_Error
	 */
	private function resolve_custom_link( $fields ) {

		if ( '' === trim( $fields['menu-item-url'] ) ) {
			return new \WP_Error(
				'premium_addons_missing_url',
				__( 'A custom item needs a url.', 'premium-addons-for-elementor' ),
				array( 'status' => 400 )
			);
		}

		$url = esc_url_raw( $fields['menu-item-url'] );

		if ( '' === $url ) {
			return new \WP_Error(
				'premium_addons_invalid_url',
				/* translators: %s: submitted URL. */
				sprintf( __( '"%s" is not a valid menu item URL.', 'premium-addons-for-elementor' ), $fields['menu-item-url'] ),
				array( 'status' => 400 )
			);
		}

		if ( '' === trim( $fields['menu-item-title'] ) ) {
			return new \WP_Error(
				'premium_addons_missing_title',
				/* translators: %s: item URL. */
				sprintf( __( 'The custom item linking to %s needs a title.', 'premium-addons-for-elementor' ), $url ),
				array( 'status' => 400 )
			);
		}

		$fields['menu-item-url']       = $url;
		$fields['menu-item-object']    = 'custom';
		$fields['menu-item-object-id'] = 0;

		return $fields;
	}

	/**
	 * Validate a post_type item.
	 *
	 * @param array $fields       Item fields.
	 * @param bool  $infer_object Take the post type from the post.
	 * @param array $tree         Tree state, by reference.
	 * @return array|\WP_Error
	 */
	private function resolve_post_link( $fields, $infer_object, &$tree ) {

		$post = get_post( $fields['menu-item-object-id'] );

		if ( $post && ( $infer_object || '' === $fields['menu-item-object'] ) ) {
			$fields['menu-item-object'] = $post->post_type;
		}

		if ( ! $post || $post->post_type !== $fields['menu-item-object'] || 'trash' === $post->post_status ) {
			$tree['bad_ids'][] = $fields['menu-item-object-id'];

			return $fields;
		}

		if ( ! in_array( $post->post_type, get_post_types( array( 'show_in_nav_menus' => true ) ), true ) ) {
			return $this->not_menu_object_error( $post->post_type );
		}

		if ( 'publish' !== $post->post_status ) {
			$tree['warnings'][ 'unpublished_' . $post->ID ] = array(
				'type'   => 'unpublished_object',
				'detail' => sprintf(
					/* translators: 1: post ID, 2: post title, 3: post status. */
					__( 'Post %1$d ("%2$s") is %3$s, so visitors get a broken link. Publish it with premium-addons/change-post-status.', 'premium-addons-for-elementor' ),
					$post->ID,
					$post->post_title,
					$post->post_status
				),
			);
		}

		return $fields;
	}

	/**
	 * Validate a taxonomy item.
	 *
	 * @param array $fields       Item fields.
	 * @param bool  $infer_object Take the taxonomy from the term.
	 * @param array $tree         Tree state, by reference.
	 * @return array|\WP_Error
	 */
	private function resolve_term_link( $fields, $infer_object, &$tree ) {

		$taxonomy = $infer_object ? '' : $fields['menu-item-object'];
		$term     = get_term( $fields['menu-item-object-id'], $taxonomy );

		if ( ! $term instanceof \WP_Term ) {
			$tree['bad_ids'][] = $fields['menu-item-object-id'];

			return $fields;
		}

		if ( ! in_array( $term->taxonomy, get_taxonomies( array( 'show_in_nav_menus' => true ) ), true ) ) {
			return $this->not_menu_object_error( $term->taxonomy );
		}

		$fields['menu-item-object'] = $term->taxonomy;

		return $fields;
	}

	/**
	 * Build the error for a post type or taxonomy menus cannot link to.
	 *
	 * @param string $object_slug Post type or taxonomy slug.
	 * @return \WP_Error
	 */
	private function not_menu_object_error( $object_slug ) {
		return new \WP_Error(
			'premium_addons_invalid_object',
			/* translators: %s: post type or taxonomy slug. */
			sprintf( __( '%s items cannot be added to menus. Use a custom link instead.', 'premium-addons-for-elementor' ), $object_slug ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Write the validated request, removing what this call created when a write fails.
	 *
	 * @param \WP_Term|null $menu       Menu to update, null when creating.
	 * @param string        $name       Menu name after this call.
	 * @param string        $mode       append or replace.
	 * @param array         $tree       Output of prepare_tree().
	 * @param int[]         $member_ids IDs of the items the menu held before this call.
	 * @param string[]      $locations  Location slugs to assign.
	 * @return array|\WP_Error
	 */
	private function save( $menu, $name, $mode, $tree, $member_ids, $locations ) {

		$journal = array(
			'created_menu_id'  => 0,
			'created_item_ids' => array(),
			'updated_item_ids' => array(),
		);

		wp_defer_term_counting( true );

		try {
			$result = $this->write( $menu, $name, $mode, $tree['nodes'], $member_ids, $locations, $journal );
		} catch ( \Throwable $e ) {
			// Exception messages can hold server paths, so the client only gets a generic reason.
			$result = new \WP_Error( 'premium_addons_menu_data_unavailable', __( 'An unexpected error occurred.', 'premium-addons-for-elementor' ) );
		}

		if ( is_wp_error( $result ) ) {
			$this->roll_back( $journal );
		}

		wp_defer_term_counting( false );

		if ( is_wp_error( $result ) ) {
			return $this->write_failed_error( $result, $journal );
		}

		$result['warnings'] = array_values( $tree['warnings'] );

		return $result;
	}

	/**
	 * Write the menu: items first, then deletions, then locations.
	 *
	 * Old items are only deleted after every new one is saved, so a failed
	 * replace leaves the menu as it was.
	 *
	 * @param \WP_Term|null $menu       Menu to update, null when creating.
	 * @param string        $name       Menu name after this call.
	 * @param string        $mode       append or replace.
	 * @param array         $nodes      Write-ready nodes.
	 * @param int[]         $member_ids IDs of the items the menu held before this call.
	 * @param string[]      $locations  Location slugs to assign.
	 * @param array         $journal    What this call created and updated, by reference.
	 * @return array|\WP_Error
	 */
	private function write( $menu, $name, $mode, $nodes, $member_ids, $locations, &$journal ) {

		$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( wp_slash( $name ) );

		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}

		if ( ! $menu ) {
			$journal['created_menu_id'] = $menu_id;
		}

		$item_ids = $this->write_items( $menu_id, $mode, $nodes, $member_ids, $journal );

		if ( is_wp_error( $item_ids ) ) {
			return $item_ids;
		}

		// Renames the menu if needed, and fires wp_update_nav_menu like Appearance → Menus does, so page caches purge.
		$saved = wp_update_nav_menu_object(
			$menu_id,
			wp_slash(
				array(
					'menu-name'   => $name,
					'description' => $menu ? $menu->description : '',
				)
			)
		);

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$deleted = 'replace' === $mode
			? $this->delete_unlisted_items( $member_ids, $item_ids )
			: array(
				'item_ids'     => array(),
				'template_ids' => array(),
			);

		$replaced = $this->assign_locations( $menu_id, $locations );

		return array(
			'menu_id'                 => $menu_id,
			'name'                    => get_term( $menu_id, 'nav_menu' )->name,
			'locations'               => $this->get_menu_locations( $menu_id ),
			'items'                   => $this->format_saved_items( $nodes, $item_ids ),
			'replaced_location_menus' => $replaced,
			'deleted_item_ids'        => $deleted['item_ids'],
			'orphaned_template_ids'   => $deleted['template_ids'],
		);
	}

	/**
	 * Create and update the items, depth first.
	 *
	 * @param int    $menu_id    Menu ID.
	 * @param string $mode       append or replace.
	 * @param array  $nodes      Write-ready nodes.
	 * @param int[]  $member_ids IDs of the items the menu held before this call.
	 * @param array  $journal    What this call created and updated, by reference.
	 * @return int[]|\WP_Error Saved item ID per node index.
	 */
	private function write_items( $menu_id, $mode, $nodes, $member_ids, &$journal ) {

		$next_position = 'replace' === $mode ? 1 : $this->get_next_position( $member_ids );
		$item_ids      = array();

		foreach ( $nodes as $index => $node ) {

			$args = $node['fields'];

			$args['menu-item-parent-id'] = $node['parent'] >= 0 ? $item_ids[ $node['parent'] ] : 0;

			// Position 0 means "move to the end" to core, so a kept item in append mode keeps its own.
			$keeps_position = 'append' === $mode && $node['item_id'] && $args['menu-item-position'];

			if ( ! $keeps_position ) {
				$args['menu-item-position'] = $next_position++;
			}

			// Core unslashes everything it saves.
			$item_id = wp_update_nav_menu_item( $menu_id, $node['item_id'], wp_slash( $args ) );

			if ( is_wp_error( $item_id ) ) {
				return $item_id;
			}

			if ( $node['item_id'] ) {
				$journal['updated_item_ids'][] = $item_id;
			} else {
				$journal['created_item_ids'][] = $item_id;
			}

			$item_ids[ $index ] = $item_id;
		}

		return $item_ids;
	}

	/**
	 * Get the position after the menu's last item.
	 *
	 * @param int[] $member_ids IDs of the items the menu holds.
	 * @return int
	 */
	private function get_next_position( $member_ids ) {

		$positions = array_map(
			function ( $item_id ) {
				return (int) get_post_field( 'menu_order', $item_id );
			},
			$member_ids
		);

		return empty( $positions ) ? 1 : max( $positions ) + 1;
	}

	/**
	 * Delete the items replace mode did not keep.
	 *
	 * Their mega content templates stay in the library and are reported.
	 *
	 * @param int[] $member_ids IDs of the items the menu held before this call.
	 * @param int[] $kept_ids   IDs of the items this call saved.
	 * @return array { item_ids, template_ids }
	 */
	private function delete_unlisted_items( $member_ids, $kept_ids ) {

		$deleted = array(
			'item_ids'     => array(),
			'template_ids' => array(),
		);

		foreach ( array_diff( $member_ids, $kept_ids ) as $item_id ) {

			$template = Mega_Item_Settings::get_template( $item_id );

			if ( ! wp_delete_post( $item_id, true ) ) {
				continue;
			}

			$deleted['item_ids'][] = $item_id;

			if ( $template ) {
				$deleted['template_ids'][] = $template['template_id'];
			}
		}

		return $deleted;
	}

	/**
	 * Show the menu in the given locations.
	 *
	 * @param int      $menu_id   Menu ID.
	 * @param string[] $locations Location slugs.
	 * @return array Locations that showed another menu before: { location, menu_id }.
	 */
	private function assign_locations( $menu_id, $locations ) {

		if ( empty( $locations ) ) {
			return array();
		}

		$assigned = get_nav_menu_locations();
		$replaced = array();

		foreach ( $locations as $slug ) {
			$previous = isset( $assigned[ $slug ] ) ? (int) $assigned[ $slug ] : 0;

			if ( $previous && $previous !== $menu_id && is_nav_menu( $previous ) ) {
				$replaced[] = array(
					'location' => $slug,
					'menu_id'  => $previous,
				);
			}

			// wp_delete_nav_menu() clears locations with a strict comparison, so store integers.
			$assigned[ $slug ] = (int) $menu_id;
		}

		set_theme_mod( 'nav_menu_locations', $assigned );

		return $replaced;
	}

	/**
	 * Get the locations showing a menu.
	 *
	 * @param int $menu_id Menu ID.
	 * @return string[]
	 */
	private function get_menu_locations( $menu_id ) {
		return array_keys(
			array_filter(
				get_nav_menu_locations(),
				function ( $assigned_id ) use ( $menu_id ) {
					return (int) $assigned_id === $menu_id;
				}
			)
		);
	}

	/**
	 * Format the saved items in menu order.
	 *
	 * @param array $nodes    Write-ready nodes.
	 * @param int[] $item_ids Saved item ID per node index.
	 * @return array
	 */
	private function format_saved_items( $nodes, $item_ids ) {

		Menu_Item_Fields::prime_linked_objects( wp_list_pluck( $nodes, 'fields' ) );

		$items = array();

		foreach ( $nodes as $index => $node ) {
			$items[] = array(
				'item_id'   => $item_ids[ $index ],
				'title'     => Menu_Item_Fields::get_title( $node['fields'] ),
				'depth'     => $node['depth'],
				'parent_id' => $node['parent'] >= 0 ? $item_ids[ $node['parent'] ] : 0,
				'created'   => ! $node['item_id'],
			);
		}

		return $items;
	}

	/**
	 * Remove what a failed call created.
	 *
	 * @param array $journal What this call created and updated.
	 * @return void
	 */
	private function roll_back( $journal ) {

		foreach ( $journal['created_item_ids'] as $item_id ) {
			wp_delete_post( $item_id, true );
		}

		if ( $journal['created_menu_id'] ) {
			wp_delete_nav_menu( $journal['created_menu_id'] );
		}
	}

	/**
	 * Build the error for a write that failed after validation passed.
	 *
	 * @param \WP_Error $error   The write error.
	 * @param array     $journal What this call created and updated.
	 * @return \WP_Error
	 */
	private function write_failed_error( $error, $journal ) {

		$updated = empty( $journal['updated_item_ids'] )
			? __( 'none', 'premium-addons-for-elementor' )
			: implode( ', ', $journal['updated_item_ids'] );

		return new \WP_Error(
			'premium_addons_menu_data_unavailable',
			sprintf(
				/* translators: 1: underlying error message, 2: comma-separated item IDs. */
				__( 'The menu could not be saved: %1$s Items this call created were removed. Items already updated: %2$s.', 'premium-addons-for-elementor' ),
				wp_strip_all_tags( $error->get_error_message() ),
				$updated
			),
			array(
				'status'           => 500,
				'updated_item_ids' => $journal['updated_item_ids'],
			)
		);
	}

	/**
	 * Get the input schema of one menu item.
	 *
	 * @return array
	 */
	private function get_item_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'item_id'   => array(
					'type'        => 'integer',
					'description' => __( 'An item this menu already has (from premium-addons/list-menus). It is kept with its Premium Mega Menu options and mega content; other fields sent here update it. Omit to create a new item.', 'premium-addons-for-elementor' ),
				),
				'type'      => array(
					'type'        => 'string',
					'enum'        => array( 'post_type', 'taxonomy', 'custom' ),
					'description' => __( 'post_type links a post, page, product or other post; taxonomy links a category, tag or other term; custom links any URL. Required for new items.', 'premium-addons-for-elementor' ),
				),
				'object'    => array(
					'type'        => 'string',
					'description' => __( 'The post type or taxonomy slug, e.g. page, post, product, category or product_cat. Optional: worked out from object_id when omitted.', 'premium-addons-for-elementor' ),
				),
				'object_id' => array(
					'type'        => 'integer',
					'description' => __( 'The post or term ID. Required for post_type and taxonomy items.', 'premium-addons-for-elementor' ),
				),
				'url'       => array(
					'type'        => 'string',
					'description' => __( 'The link URL. Required for custom items; "#" makes a label-only parent.', 'premium-addons-for-elementor' ),
				),
				'title'     => array(
					'type'        => 'string',
					'description' => __( 'The label. Required for new custom items. post_type and taxonomy items follow the linked title when omitted.', 'premium-addons-for-elementor' ),
				),
				'children'  => array(
					'type'        => 'array',
					'description' => __( 'Sub-items, same shape.', 'premium-addons-for-elementor' ),
					'items'       => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Get the output schema of the warnings list.
	 *
	 * @return array
	 */
	private function get_warnings_output_schema() {
		return array(
			'type'        => 'array',
			'description' => __( 'Problems that did not stop the save.', 'premium-addons-for-elementor' ),
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'type'   => array(
						'type'        => 'string',
						'description' => __( 'unpublished_object: the item links to a post visitors cannot see yet. mega_on_child: a kept item with mega content is now a sub-item, where mega content does not show.', 'premium-addons-for-elementor' ),
					),
					'detail' => array(
						'type'        => 'string',
						'description' => __( 'What to tell the user or fix.', 'premium-addons-for-elementor' ),
					),
				),
			),
		);
	}
}
