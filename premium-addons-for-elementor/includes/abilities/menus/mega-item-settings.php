<?php
/**
 * Mega Item Settings.
 *
 * Reads and writes the Premium Mega Menu options stored on a menu item.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Menus;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Maps the "Premium Menu" item meta to the menu abilities' input vocabulary.
 *
 * The stored format is the one admin/assets/js/menu-editor.js reads: every
 * value HTML-encoded, switches as "true"/"false" strings and the width as
 * "{n}px". The modal breaks on a partial key set, so save() writes all keys.
 *
 * @since 4.11.110
 */
class Mega_Item_Settings {

	const SETTINGS_META_KEY = 'pa_megamenu_item_meta';

	const TEMPLATE_META_KEY = 'pa_mega_content_temp';

	const DEFAULT_COLOR = '#bada55';

	const DEFAULT_WIDTH = 1170;

	const COLOR_FIELDS = array( 'icon_color', 'badge_color', 'badge_bg' );

	const BOOLEAN_FIELDS = array( 'mega_content', 'mega_full_width' );

	/**
	 * The icon classes the "Premium Menu" icon picker offers.
	 *
	 * @var string
	 */
	const ICON_PATTERN = '/^(dashicons dashicons-[a-z0-9-]+|fa fa-[a-z0-9-]+)$/';

	/**
	 * Get the settings a menu item has before anything is saved.
	 *
	 * @return array
	 */
	private static function get_defaults() {
		return array(
			'icon_type'       => 'icon',
			'icon'            => '',
			'lottie_url'      => '',
			'icon_color'      => self::DEFAULT_COLOR,
			'badge'           => '',
			'badge_color'     => self::DEFAULT_COLOR,
			'badge_bg'        => self::DEFAULT_COLOR,
			'mega_content'    => false,
			'mega_position'   => 'centered',
			'mega_full_width' => false,
			'mega_width'      => self::DEFAULT_WIDTH,
		);
	}

	/**
	 * Get a menu item's stored settings.
	 *
	 * @param int $item_id Menu item ID.
	 * @return array|null Settings, or null when none are stored.
	 */
	public static function get( $item_id ) {

		$raw    = get_post_meta( $item_id, self::SETTINGS_META_KEY, true );
		$stored = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;

		if ( ! is_array( $stored ) ) {
			return null;
		}

		$stored = array_merge(
			self::to_stored( self::get_defaults() ),
			array_map( array( __CLASS__, 'decode_value' ), $stored )
		);

		return self::from_stored( $stored );
	}

	/**
	 * Validate and sanitize the settings an ability received.
	 *
	 * @param array $input Ability input. Keys outside the settings are ignored.
	 * @return array|\WP_Error The sanitized settings present in the input.
	 */
	public static function sanitize_input( $input ) {

		$settings = array_intersect_key( $input, self::get_defaults() );

		foreach ( self::COLOR_FIELDS as $field ) {
			if ( ! isset( $settings[ $field ] ) || '' === $settings[ $field ] ) {
				continue;
			}

			$color = sanitize_hex_color( $settings[ $field ] );

			if ( ! $color ) {
				return self::invalid_field_error(
					$field,
					/* translators: 1: setting name, 2: submitted value. */
					sprintf( __( '%1$s must be a hex color such as #1a73e8. Got "%2$s".', 'premium-addons-for-elementor' ), $field, $settings[ $field ] )
				);
			}

			$settings[ $field ] = $color;
		}

		if ( isset( $settings['icon'] ) && '' !== $settings['icon'] && ! preg_match( self::ICON_PATTERN, $settings['icon'] ) ) {
			return self::invalid_field_error(
				'icon',
				/* translators: %s: submitted icon class. */
				sprintf( __( 'icon must be a Dashicons class such as "dashicons dashicons-admin-home" or a Font Awesome 4 class such as "fa fa-star". Got "%s".', 'premium-addons-for-elementor' ), $settings['icon'] )
			);
		}

		if ( isset( $settings['lottie_url'] ) && '' !== $settings['lottie_url'] ) {
			$url = esc_url_raw( $settings['lottie_url'] );

			if ( '' === $url ) {
				return self::invalid_field_error(
					'lottie_url',
					/* translators: %s: submitted URL. */
					sprintf( __( 'lottie_url must be a valid URL to a Lottie JSON file. Got "%s".', 'premium-addons-for-elementor' ), $settings['lottie_url'] )
				);
			}

			$settings['lottie_url'] = $url;
		}

		if ( isset( $settings['badge'] ) ) {
			$settings['badge'] = sanitize_text_field( $settings['badge'] );
		}

		foreach ( self::BOOLEAN_FIELDS as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$settings[ $field ] = rest_sanitize_boolean( $settings[ $field ] );
			}
		}

		if ( isset( $settings['mega_width'] ) ) {
			$settings['mega_width'] = absint( $settings['mega_width'] );
		}

		return $settings;
	}

	/**
	 * Merge sanitized settings into a menu item's stored settings and save the full set.
	 *
	 * @param int   $item_id  Menu item ID.
	 * @param int   $depth    Menu item depth, 0 for top level.
	 * @param array $settings Sanitized settings from sanitize_input().
	 * @return array The saved settings.
	 */
	public static function save( $item_id, $depth, $settings ) {

		$current  = self::get( $item_id );
		$settings = array_merge( $current ? $current : self::get_defaults(), $settings );

		$stored = array_merge(
			array(
				'item_id'    => (string) $item_id,
				'item_depth' => (string) $depth,
			),
			self::to_stored( $settings )
		);

		// Same encoding as Admin_Helper::pa_save_menu_item_settings(), which the walker and the modal expect.
		$stored = array_map(
			function ( $value ) {
				return htmlspecialchars( $value, ENT_QUOTES );
			},
			$stored
		);

		// update_post_meta() unslashes, which would corrupt backslashes inside the JSON.
		update_post_meta( $item_id, self::SETTINGS_META_KEY, wp_slash( wp_json_encode( $stored, JSON_UNESCAPED_UNICODE ) ) );

		return $settings;
	}

	/**
	 * Get a menu item's depth, 0 for top level.
	 *
	 * Stops at a parent that is no longer in the item's menu: the walker
	 * renders such items at the top level.
	 *
	 * @param int $item_id Menu item ID.
	 * @return int
	 */
	public static function get_depth( $item_id ) {

		$menu_ids = wp_get_object_terms( $item_id, 'nav_menu', array( 'fields' => 'ids' ) );
		$menu_id  = is_array( $menu_ids ) && ! empty( $menu_ids ) ? (int) $menu_ids[0] : 0;

		$depth   = 0;
		$visited = array( $item_id => true );
		$parent  = (int) get_post_meta( $item_id, '_menu_item_menu_item_parent', true );

		while ( $parent && ! isset( $visited[ $parent ] ) && is_nav_menu_item( $parent ) && has_term( $menu_id, 'nav_menu', $parent ) ) {
			++$depth;
			$visited[ $parent ] = true;
			$parent             = (int) get_post_meta( $parent, '_menu_item_menu_item_parent', true );
		}

		return $depth;
	}

	/**
	 * Get the mega content template linked to a menu item.
	 *
	 * @param int $item_id Menu item ID.
	 * @return array|null { template_id, has_content }, or null when no template exists.
	 */
	public static function get_template( $item_id ) {

		$template_id = (int) get_post_meta( $item_id, self::TEMPLATE_META_KEY, true );

		if ( ! $template_id || ! get_post( $template_id ) ) {
			return null;
		}

		return array(
			'template_id' => $template_id,
			'has_content' => self::has_content( $template_id ),
		);
	}

	/**
	 * Whether an Elementor template holds any elements.
	 *
	 * Reads the stored data instead of rendering it: rendering writes the
	 * template's CSS file and can print styles into the response.
	 *
	 * @param int $template_id Template post ID.
	 * @return bool
	 */
	private static function has_content( $template_id ) {

		$data = get_post_meta( $template_id, '_elementor_data', true );

		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		return is_array( $data ) && ! empty( $data );
	}

	/**
	 * Map settings to the stored key set.
	 *
	 * @param array $settings Full settings.
	 * @return array
	 */
	private static function to_stored( $settings ) {
		return array(
			'item_icon_type'          => $settings['icon_type'],
			'item_icon'               => $settings['icon'],
			'item_lottie_url'         => $settings['lottie_url'],
			'item_icon_color'         => $settings['icon_color'],
			'item_badge'              => $settings['badge'],
			'item_badge_color'        => $settings['badge_color'],
			'item_badge_bg'           => $settings['badge_bg'],
			'mega_content_enabled'    => $settings['mega_content'] ? 'true' : 'false',
			'mega_content_pos'        => 'relative' === $settings['mega_position'] ? 'relative' : 'default',
			'full_width_mega_content' => $settings['mega_full_width'] ? 'true' : 'false',
			'mega_content_width'      => $settings['mega_width'] . 'px',
		);
	}

	/**
	 * Map the stored key set to settings, coercing values the modal never writes.
	 *
	 * @param array $stored Full, decoded stored key set.
	 * @return array
	 */
	private static function from_stored( $stored ) {

		$width = absint( $stored['mega_content_width'] );

		return array(
			'icon_type'       => 'lottie' === $stored['item_icon_type'] ? 'lottie' : 'icon',
			'icon'            => (string) $stored['item_icon'],
			'lottie_url'      => (string) $stored['item_lottie_url'],
			'icon_color'      => (string) $stored['item_icon_color'],
			'badge'           => (string) $stored['item_badge'],
			'badge_color'     => (string) $stored['item_badge_color'],
			'badge_bg'        => (string) $stored['item_badge_bg'],
			'mega_content'    => 'true' === $stored['mega_content_enabled'],
			'mega_position'   => 'relative' === $stored['mega_content_pos'] ? 'relative' : 'centered',
			'mega_full_width' => 'true' === $stored['full_width_mega_content'],
			'mega_width'      => $width ? $width : self::DEFAULT_WIDTH,
		);
	}

	/**
	 * Undo the storage HTML encoding of one value.
	 *
	 * @param mixed $value Stored value.
	 * @return mixed
	 */
	private static function decode_value( $value ) {
		return is_string( $value ) ? htmlspecialchars_decode( $value, ENT_QUOTES ) : $value;
	}

	/**
	 * Build the error for a setting with a wrong value.
	 *
	 * @param string $field   Setting name.
	 * @param string $message Translated message.
	 * @return \WP_Error
	 */
	private static function invalid_field_error( $field, $message ) {
		return new \WP_Error( 'premium_addons_invalid_' . $field, $message, array( 'status' => 400 ) );
	}
}
