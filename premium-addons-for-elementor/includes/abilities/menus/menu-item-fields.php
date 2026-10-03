<?php
/**
 * Menu Item Fields.
 *
 * Reads menu items from raw storage for the menu abilities.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Menus;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Raw menu item reads.
 *
 * wp_setup_nav_menu_item() returns display values (texturized titles,
 * classes as an array) that wp_update_nav_menu_item() cannot take back, and
 * wp_get_nav_menu_items() drops invalid items outside wp-admin. The menu
 * abilities read storage directly so what they list is what they can save.
 *
 * @since 4.11.110
 */
class Menu_Item_Fields {

	/**
	 * Get the IDs of every item in a menu, in menu order.
	 *
	 * @param int  $menu_id        Menu term ID.
	 * @param bool $published_only Leave out items added on Appearance → Menus but never saved.
	 * @return int[]
	 */
	public static function get_menu_item_ids( $menu_id, $published_only = false ) {

		$ids = get_objects_in_term( $menu_id, 'nav_menu' );

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array();
		}

		$ids = array_map( 'intval', $ids );

		_prime_post_caches( $ids, false, true );

		$posts = array_filter( array_map( 'get_post', $ids ) );

		if ( $published_only ) {
			$posts = array_filter(
				$posts,
				function ( $post ) {
					return 'publish' === $post->post_status;
				}
			);
		}

		usort(
			$posts,
			function ( $a, $b ) {
				return array( $a->menu_order, $a->ID ) <=> array( $b->menu_order, $b->ID );
			}
		);

		return wp_list_pluck( $posts, 'ID' );
	}

	/**
	 * Get a menu item's stored fields in wp_update_nav_menu_item() argument form.
	 *
	 * @param int $item_id Menu item ID.
	 * @return array
	 */
	public static function get_stored_fields( $item_id ) {

		$post    = get_post( $item_id );
		$classes = array_filter( (array) get_post_meta( $item_id, '_menu_item_classes', true ) );

		return array(
			'menu-item-object-id'     => (int) get_post_meta( $item_id, '_menu_item_object_id', true ),
			'menu-item-object'        => get_post_meta( $item_id, '_menu_item_object', true ),
			'menu-item-parent-id'     => (int) get_post_meta( $item_id, '_menu_item_menu_item_parent', true ),
			'menu-item-position'      => (int) $post->menu_order,
			'menu-item-type'          => get_post_meta( $item_id, '_menu_item_type', true ),
			'menu-item-title'         => $post->post_title,
			'menu-item-url'           => get_post_meta( $item_id, '_menu_item_url', true ),
			'menu-item-description'   => $post->post_content,
			'menu-item-attr-title'    => $post->post_excerpt,
			'menu-item-target'        => get_post_meta( $item_id, '_menu_item_target', true ),
			'menu-item-classes'       => implode( ' ', $classes ),
			'menu-item-xfn'           => get_post_meta( $item_id, '_menu_item_xfn', true ),
			'menu-item-status'        => $post->post_status,
			'menu-item-post-date'     => $post->post_date,
			'menu-item-post-date-gmt' => $post->post_date_gmt,
		);
	}

	/**
	 * Load the posts and terms a set of items links to, one query per kind.
	 *
	 * @param array $fields_list Item fields, each with menu-item-type and menu-item-object-id.
	 * @return void
	 */
	public static function prime_linked_objects( $fields_list ) {

		$ids = array(
			'post_type' => array(),
			'taxonomy'  => array(),
		);

		foreach ( $fields_list as $fields ) {
			if ( isset( $ids[ $fields['menu-item-type'] ] ) ) {
				$ids[ $fields['menu-item-type'] ][] = absint( $fields['menu-item-object-id'] );
			}
		}

		if ( ! empty( $ids['post_type'] ) ) {
			_prime_post_caches( array_unique( $ids['post_type'] ), false, false );
		}

		if ( ! empty( $ids['taxonomy'] ) ) {
			_prime_term_caches( array_unique( $ids['taxonomy'] ) );
		}
	}

	/**
	 * Get the title a menu item shows, unfiltered.
	 *
	 * An empty stored title means the item follows its object's title.
	 *
	 * @param array $fields Fields from get_stored_fields().
	 * @return string
	 */
	public static function get_title( $fields ) {

		if ( '' !== $fields['menu-item-title'] ) {
			return $fields['menu-item-title'];
		}

		$object = self::get_linked_object( $fields );

		if ( $object instanceof \WP_Post ) {
			return $object->post_title;
		}

		if ( $object instanceof \WP_Term ) {
			return $object->name;
		}

		return '';
	}

	/**
	 * Get the URL a menu item links to.
	 *
	 * @param array $fields Fields from get_stored_fields().
	 * @return string
	 */
	public static function get_url( $fields ) {

		$object = self::get_linked_object( $fields );

		if ( $object instanceof \WP_Post ) {
			return (string) get_permalink( $object );
		}

		if ( $object instanceof \WP_Term ) {
			$link = get_term_link( $object );

			return is_wp_error( $link ) ? '' : $link;
		}

		return (string) $fields['menu-item-url'];
	}

	/**
	 * Get the post or term a menu item points to.
	 *
	 * @param array $fields Fields from get_stored_fields().
	 * @return \WP_Post|\WP_Term|null
	 */
	private static function get_linked_object( $fields ) {

		$object_id = (int) $fields['menu-item-object-id'];

		if ( 'post_type' === $fields['menu-item-type'] ) {
			return get_post( $object_id );
		}

		if ( 'taxonomy' === $fields['menu-item-type'] ) {
			$term = get_term( $object_id, $fields['menu-item-object'] );

			return $term instanceof \WP_Term ? $term : null;
		}

		return null;
	}
}
