<?php
/**
 * Document Helper Class
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Helpers;

use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Document_Helper.
 *
 * Loads widgets from Elementor documents for AJAX requests, which carry
 * a document ID and an element ID chosen by the visitor.
 */
class Document_Helper {

	/**
	 * Whether the current visitor can read the document.
	 *
	 * @since 4.11.112
	 * @access public
	 *
	 * @param int $doc_id document ID.
	 *
	 * @return bool
	 */
	public static function can_read_document( $doc_id ) {

		$post = get_post( $doc_id );

		if ( ! $post ) {
			return false;
		}

		if ( 'publish' === $post->post_status && ! post_password_required( $post ) ) {
			return true;
		}

		// read_post maps to plain `read` on published posts, so it would let any logged-in user past a password.
		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Get a widget instance from a document the current visitor can read.
	 *
	 * @since 4.11.112
	 * @access public
	 *
	 * @param int    $doc_id       document ID.
	 * @param string $elem_id      element ID.
	 * @param array  $widget_names allowed widget names, empty to allow any widget.
	 *
	 * @return \Elementor\Widget_Base|null
	 */
	public static function get_widget( $doc_id, $elem_id, $widget_names = array() ) {

		if ( ! self::can_read_document( $doc_id ) ) {
			return null;
		}

		$document = Plugin::$instance->documents->get( $doc_id );

		if ( ! $document ) {
			return null;
		}

		$widget_data = self::find_element( $document->get_elements_data(), $elem_id );

		if ( empty( $widget_data ) || 'widget' !== $widget_data['elType'] ) {
			return null;
		}

		$widget = Plugin::$instance->elements_manager->create_element_instance( $widget_data );

		if ( ! $widget || ( ! empty( $widget_names ) && ! in_array( $widget->get_name(), $widget_names, true ) ) ) {
			return null;
		}

		return $widget;
	}

	/**
	 * Find an element by ID in a document's elements tree.
	 *
	 * @since 4.11.112
	 * @access private
	 *
	 * @param array  $elements elements data.
	 * @param string $elem_id  element ID.
	 *
	 * @return array|null
	 */
	private static function find_element( $elements, $elem_id ) {

		foreach ( $elements as $element ) {

			if ( $elem_id === $element['id'] ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) ) {
				$found = self::find_element( $element['elements'], $elem_id );

				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}
}
