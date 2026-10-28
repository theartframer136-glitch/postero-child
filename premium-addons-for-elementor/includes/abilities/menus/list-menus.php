<?php
/**
 * List Menus.
 *
 * Lists the site's menus, menu locations and menu items, including the
 * Premium Mega Menu options of each item.
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
 * @since 4.11.110
 */
class List_Menus implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list-menus';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Menus', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists the site\'s menus, menu locations and menu items, including Premium Mega Menu item options.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-menus',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'additionalProperties' => false,
				'properties'           => array(
					'menu_id' => array(
						'type'        => 'integer',
						'description' => __( 'The term ID of one menu to return. Omit to return every menu.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Menus and theme menu locations.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'menus'     => array(
						'type'        => 'array',
						'description' => __( 'The menus, sorted by name.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'term_id'   => array(
									'type'        => 'integer',
									'description' => __( 'The menu ID. Pass it as menu_id to premium-addons/save-menu, or as pa_nav_menus to the Premium Mega Menu widget.', 'premium-addons-for-elementor' ),
								),
								'name'      => array(
									'type'        => 'string',
									'description' => __( 'The menu name.', 'premium-addons-for-elementor' ),
								),
								'slug'      => array(
									'type'        => 'string',
									'description' => __( 'The menu slug.', 'premium-addons-for-elementor' ),
								),
								'locations' => array(
									'type'        => 'array',
									'description' => __( 'Slugs of the theme menu locations showing this menu.', 'premium-addons-for-elementor' ),
									'items'       => array( 'type' => 'string' ),
								),
								'items'     => array(
									'type'        => 'array',
									'description' => __( 'The top-level menu items, in menu order. Each item nests its sub-items in children.', 'premium-addons-for-elementor' ),
									'items'       => $this->get_item_output_schema(),
								),
							),
						),
					),
					'locations' => array(
						'type'        => 'array',
						'description' => __( 'Every menu location the active theme registers. Empty for block themes.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'slug'    => array(
									'type'        => 'string',
									'description' => __( 'The location slug. Pass it in locations to premium-addons/save-menu.', 'premium-addons-for-elementor' ),
								),
								'label'   => array(
									'type'        => 'string',
									'description' => __( 'The location name the theme shows.', 'premium-addons-for-elementor' ),
								),
								'menu_id' => array(
									'type'        => array( 'integer', 'null' ),
									'description' => __( 'The menu assigned to this location, or null.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
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
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
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

		// The schema top-level default arrives as an empty stdClass, not an array.
		$input = is_array( $input ) ? $input : array();

		$menus = wp_get_nav_menus();

		if ( isset( $input['menu_id'] ) ) {
			$menu = get_term( absint( $input['menu_id'] ), 'nav_menu' );

			if ( ! $menu instanceof \WP_Term ) {
				return new \WP_Error(
					'premium_addons_invalid_menu_id',
					/* translators: %d: menu ID. */
					sprintf( __( 'No menu found with ID %d. Call premium-addons/list-menus without menu_id to see every menu.', 'premium-addons-for-elementor' ), absint( $input['menu_id'] ) ),
					array( 'status' => 404 )
				);
			}

			$menus = array( $menu );
		}

		$assigned = get_nav_menu_locations();

		return array(
			'menus'     => array_map(
				function ( $menu ) use ( $assigned ) {
					return $this->format_menu( $menu, $assigned );
				},
				$menus
			),
			'locations' => $this->format_locations( $assigned ),
		);
	}

	/**
	 * Format one menu with its item tree.
	 *
	 * @param \WP_Term $menu     Menu term.
	 * @param array    $assigned Location slug => menu ID.
	 * @return array
	 */
	private function format_menu( $menu, $assigned ) {

		$registered = get_registered_nav_menus();
		$locations  = array_keys(
			array_filter(
				$assigned,
				function ( $menu_id, $slug ) use ( $menu, $registered ) {
					return (int) $menu_id === $menu->term_id && isset( $registered[ $slug ] );
				},
				ARRAY_FILTER_USE_BOTH
			)
		);

		$item_ids = Menu_Item_Fields::get_menu_item_ids( $menu->term_id, true );
		$groups   = $this->group_by_parent( $item_ids );

		$this->prime_templates( $item_ids );
		Menu_Item_Fields::prime_linked_objects( array_merge( array(), ...array_values( $groups ) ) );

		return array(
			'term_id'   => $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'locations' => $locations,
			'items'     => $this->build_branch( $groups, 0, 0 ),
		);
	}

	/**
	 * Group item IDs by parent, moving items whose parent left the menu to the top level.
	 *
	 * @param int[] $item_ids Item IDs in menu order.
	 * @return array Parent ID => item fields keyed by item ID.
	 */
	private function group_by_parent( $item_ids ) {

		$in_menu = array_flip( $item_ids );
		$groups  = array();

		foreach ( $item_ids as $item_id ) {
			$fields = Menu_Item_Fields::get_stored_fields( $item_id );
			$parent = isset( $in_menu[ $fields['menu-item-parent-id'] ] ) ? $fields['menu-item-parent-id'] : 0;

			$groups[ $parent ][ $item_id ] = $fields;
		}

		return $groups;
	}

	/**
	 * Build the items under one parent, recursively.
	 *
	 * @param array $groups    Output of group_by_parent().
	 * @param int   $parent_id Parent item ID, 0 for top level.
	 * @param int   $depth     Depth of this branch.
	 * @return array
	 */
	private function build_branch( $groups, $parent_id, $depth ) {

		if ( empty( $groups[ $parent_id ] ) ) {
			return array();
		}

		$branch = array();

		foreach ( $groups[ $parent_id ] as $item_id => $fields ) {
			$item             = $this->format_item( $item_id, $fields, $parent_id, $depth );
			$item['children'] = $this->build_branch( $groups, $item_id, $depth + 1 );

			$branch[] = $item;
		}

		return $branch;
	}

	/**
	 * Format one menu item.
	 *
	 * @param int   $item_id   Menu item ID.
	 * @param array $fields    Stored fields.
	 * @param int   $parent_id Parent item ID, 0 for top level.
	 * @param int   $depth     Item depth.
	 * @return array
	 */
	private function format_item( $item_id, $fields, $parent_id, $depth ) {

		$template = Mega_Item_Settings::get_template( $item_id );

		return array(
			'item_id'                   => $item_id,
			'title'                     => Menu_Item_Fields::get_title( $fields ),
			'type'                      => $fields['menu-item-type'],
			'object'                    => $fields['menu-item-object'],
			'object_id'                 => 'custom' === $fields['menu-item-type'] ? 0 : $fields['menu-item-object-id'],
			'url'                       => Menu_Item_Fields::get_url( $fields ),
			'parent_id'                 => $parent_id,
			'depth'                     => $depth,
			'pa_settings'               => Mega_Item_Settings::get( $item_id ),
			'mega_template_id'          => $template ? $template['template_id'] : null,
			'mega_template_has_content' => $template ? $template['has_content'] : false,
		);
	}

	/**
	 * Load the menu's mega templates and their meta in one query each.
	 *
	 * @param int[] $item_ids Menu item IDs.
	 * @return void
	 */
	private function prime_templates( $item_ids ) {

		$template_ids = array_filter(
			array_map(
				function ( $item_id ) {
					return (int) get_post_meta( $item_id, Mega_Item_Settings::TEMPLATE_META_KEY, true );
				},
				$item_ids
			)
		);

		if ( ! empty( $template_ids ) ) {
			_prime_post_caches( $template_ids, false, true );
		}
	}

	/**
	 * Format the theme's registered menu locations.
	 *
	 * @param array $assigned Location slug => menu ID.
	 * @return array
	 */
	private function format_locations( $assigned ) {

		$locations = array();

		foreach ( get_registered_nav_menus() as $slug => $label ) {
			$menu_id = isset( $assigned[ $slug ] ) ? (int) $assigned[ $slug ] : 0;

			$locations[] = array(
				'slug'    => $slug,
				'label'   => $label,
				'menu_id' => $menu_id && is_nav_menu( $menu_id ) ? $menu_id : null,
			);
		}

		return $locations;
	}

	/**
	 * Get the output schema of one menu item.
	 *
	 * @return array
	 */
	private function get_item_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'item_id'                   => array(
					'type'        => 'integer',
					'description' => __( 'The menu item ID. Pass it as item_id to premium-addons/configure-menu-item, or to premium-addons/save-menu to keep the item.', 'premium-addons-for-elementor' ),
				),
				'title'                     => array(
					'type'        => 'string',
					'description' => __( 'The label the item shows.', 'premium-addons-for-elementor' ),
				),
				'type'                      => array(
					'type'        => 'string',
					'description' => __( 'post_type, taxonomy or custom (other core types may appear).', 'premium-addons-for-elementor' ),
				),
				'object'                    => array(
					'type'        => 'string',
					'description' => __( 'The post type or taxonomy the item links to (e.g. page, category), or custom.', 'premium-addons-for-elementor' ),
				),
				'object_id'                 => array(
					'type'        => 'integer',
					'description' => __( 'The linked post or term ID. 0 for custom links.', 'premium-addons-for-elementor' ),
				),
				'url'                       => array(
					'type'        => 'string',
					'description' => __( 'The URL the item links to.', 'premium-addons-for-elementor' ),
				),
				'parent_id'                 => array(
					'type'        => 'integer',
					'description' => __( 'The parent item ID, 0 for top level.', 'premium-addons-for-elementor' ),
				),
				'depth'                     => array(
					'type'        => 'integer',
					'description' => __( 'The nesting level, 0 for top level.', 'premium-addons-for-elementor' ),
				),
				'pa_settings'               => array(
					'type'        => array( 'object', 'null' ),
					'description' => __( 'The Premium Mega Menu options, in the shape premium-addons/configure-menu-item accepts. null when never set.', 'premium-addons-for-elementor' ),
				),
				'mega_template_id'          => array(
					'type'        => array( 'integer', 'null' ),
					'description' => __( 'The Elementor template holding the item\'s mega content, or null.', 'premium-addons-for-elementor' ),
				),
				'mega_template_has_content' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the mega content template holds any elements.', 'premium-addons-for-elementor' ),
				),
				'children'                  => array(
					'type'        => 'array',
					'description' => __( 'The sub-items, same shape.', 'premium-addons-for-elementor' ),
					'items'       => array( 'type' => 'object' ),
				),
			),
		);
	}
}
