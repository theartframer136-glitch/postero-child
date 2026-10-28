<?php
/**
 * Configure Menu Item.
 *
 * Sets the Premium Mega Menu options of a menu item and creates its mega
 * content template.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Menus;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;
use PremiumAddons\Includes\Extras\Live_Editor;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 *
 * Writes the same meta the "Premium Menu" button on Appearance → Menus
 * writes, and resolves the mega template through the same helper as its
 * "Edit Mega Content" button, so both always point to one template.
 *
 * @since 4.11.110
 */
class Configure_Menu_Item implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'configure-menu-item';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Configure Mega Menu Item', 'premium-addons-for-elementor' ),
			'description'         => __( 'Sets Premium Mega Menu options on a menu item, and creates its mega content template.', 'premium-addons-for-elementor' )
				. "\n\n"
				. __( 'The options show only in the Premium Mega Menu widget with Menu Type "WordPress Menu". Mega content works on top-level items only. When template_has_content is false, build the content with premium-addons/add-container, premium-addons/insert-widget or premium-addons/insert-premium-template using post_id = template_id.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-menus',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'item_id' ),
				'properties'           => array(
					'item_id'         => array(
						'type'        => 'integer',
						'description' => __( 'The menu item ID, from premium-addons/save-menu or premium-addons/list-menus. Options not sent keep their current values.', 'premium-addons-for-elementor' ),
					),
					'icon_type'       => array(
						'type'        => 'string',
						'enum'        => array( 'icon', 'lottie' ),
						'description' => __( 'Show the icon class (icon) or a Lottie animation (lottie) before the label.', 'premium-addons-for-elementor' ),
					),
					'icon'            => array(
						'type'        => 'string',
						'description' => __( 'A Dashicons class such as "dashicons dashicons-admin-home" or a Font Awesome 4 class such as "fa fa-star". Empty string removes it.', 'premium-addons-for-elementor' ),
					),
					'lottie_url'      => array(
						'type'        => 'string',
						'description' => __( 'URL of a Lottie JSON file, used when icon_type is lottie.', 'premium-addons-for-elementor' ),
					),
					'icon_color'      => array(
						'type'        => 'string',
						'description' => __( 'Icon hex color, e.g. #1a73e8.', 'premium-addons-for-elementor' ),
					),
					'badge'           => array(
						'type'        => 'string',
						'description' => __( 'Short badge text shown next to the label, e.g. New. Empty string removes it.', 'premium-addons-for-elementor' ),
					),
					'badge_color'     => array(
						'type'        => 'string',
						'description' => __( 'Badge text hex color.', 'premium-addons-for-elementor' ),
					),
					'badge_bg'        => array(
						'type'        => 'string',
						'description' => __( 'Badge background hex color.', 'premium-addons-for-elementor' ),
					),
					'mega_content'    => array(
						'type'        => 'boolean',
						'description' => __( 'Show an Elementor template as a mega panel under this top-level item. true creates the template if needed; false hides it and keeps the template.', 'premium-addons-for-elementor' ),
					),
					'mega_position'   => array(
						'type'        => 'string',
						'enum'        => array( 'centered', 'relative' ),
						'description' => __( 'centered aligns the panel to the menu; relative opens it under the item.', 'premium-addons-for-elementor' ),
					),
					'mega_full_width' => array(
						'type'        => 'boolean',
						'description' => __( 'Stretch the panel to the full width of the section holding the menu. Top-level items only.', 'premium-addons-for-elementor' ),
					),
					'mega_width'      => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 2000,
						'description' => __( 'Panel width in pixels when not full width. Defaults to 1170.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The menu item\'s saved options.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'item_id'              => array(
						'type'        => 'integer',
						'description' => __( 'The menu item ID.', 'premium-addons-for-elementor' ),
					),
					'depth'                => array(
						'type'        => 'integer',
						'description' => __( 'The item nesting level, 0 for top level.', 'premium-addons-for-elementor' ),
					),
					'settings'             => array(
						'type'        => 'object',
						'description' => __( 'Every option as now saved, in the shape this ability accepts.', 'premium-addons-for-elementor' ),
					),
					'template_id'          => array(
						'type'        => 'integer',
						'description' => __( 'The Elementor template holding the mega content. Pass it as post_id to the build abilities.', 'premium-addons-for-elementor' ),
					),
					'template_edit_url'    => array(
						'type'        => 'string',
						'description' => __( 'The Elementor editor URL of the template.', 'premium-addons-for-elementor' ),
					),
					'template_has_content' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the template holds any elements yet.', 'premium-addons-for-elementor' ),
					),
					'next_step'            => array(
						'type'        => 'string',
						'description' => __( 'What to do next, when mega content is on but has nothing to show.', 'premium-addons-for-elementor' ),
					),
					'warnings'             => array(
						'type'        => 'array',
						'description' => __( 'Problems that did not stop the save.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'type'   => array(
									'type'        => 'string',
									'description' => __( 'widget_disabled: the Premium Mega Menu widget is turned off, so the options show nowhere yet.', 'premium-addons-for-elementor' ),
								),
								'detail' => array(
									'type'        => 'string',
									'description' => __( 'What to tell the user or fix.', 'premium-addons-for-elementor' ),
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
					'readonly'    => false,
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$input   = is_array( $input ) ? $input : array();
		$item_id = isset( $input['item_id'] ) ? absint( $input['item_id'] ) : 0;

		if ( ! is_nav_menu_item( $item_id ) ) {
			return new \WP_Error(
				'premium_addons_invalid_item_id',
				/* translators: %d: menu item ID. */
				sprintf( __( 'No menu item found with ID %d. Call premium-addons/list-menus to get item IDs.', 'premium-addons-for-elementor' ), $item_id ),
				array( 'status' => 404 )
			);
		}

		$settings = Mega_Item_Settings::sanitize_input( $input );

		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		$depth = Mega_Item_Settings::get_depth( $item_id );
		$error = $this->guard_top_level_options( $settings, $depth );

		if ( $error ) {
			return $error;
		}

		// Link the template before saving the switch, so mega content is never on without one.
		if ( ! empty( $settings['mega_content'] ) ) {
			$error = $this->link_mega_template( $item_id );

			if ( $error ) {
				return $error;
			}
		}

		$saved = Mega_Item_Settings::save( $item_id, $depth, $settings );

		return $this->format_result( $item_id, $depth, $saved );
	}

	/**
	 * Reject the options that only work on top-level items.
	 *
	 * The walker renders mega content at depth 0 only.
	 *
	 * @param array $settings Sanitized settings.
	 * @param int   $depth    Item depth.
	 * @return \WP_Error|null
	 */
	private function guard_top_level_options( $settings, $depth ) {

		if ( ! $depth ) {
			return null;
		}

		foreach ( array( 'mega_content', 'mega_full_width' ) as $field ) {
			if ( ! empty( $settings[ $field ] ) ) {
				return new \WP_Error(
					'premium_addons_invalid_' . $field,
					/* translators: 1: option name, 2: item depth. */
					sprintf( __( '%1$s works on top-level menu items only. This item is a sub-item (depth %2$d). Nothing was saved.', 'premium-addons-for-elementor' ), $field, $depth ),
					array( 'status' => 400 )
				);
			}
		}

		return null;
	}

	/**
	 * Get or create the item's mega template and link it to the item.
	 *
	 * @param int $item_id Menu item ID.
	 * @return \WP_Error|null
	 */
	private function link_mega_template( $item_id ) {

		$template = Live_Editor::get_or_create_dynamic_template( (string) $item_id );

		if ( ! $template['id'] ) {
			return new \WP_Error(
				'premium_addons_template_data_unavailable',
				__( 'The mega content template could not be created. Nothing was saved; try again.', 'premium-addons-for-elementor' ),
				array( 'status' => 500 )
			);
		}

		// A string, as Admin_Helper::pa_save_mega_item_content() stores it, so an unchanged ID skips the write.
		update_post_meta( $item_id, Mega_Item_Settings::TEMPLATE_META_KEY, (string) $template['id'] );

		return null;
	}

	/**
	 * Build the ability result.
	 *
	 * @param int   $item_id  Menu item ID.
	 * @param int   $depth    Item depth.
	 * @param array $settings Saved settings.
	 * @return array
	 */
	private function format_result( $item_id, $depth, $settings ) {

		$result = array(
			'item_id'  => $item_id,
			'depth'    => $depth,
			'settings' => $settings,
		);

		$template = Mega_Item_Settings::get_template( $item_id );

		if ( $template ) {
			$result['template_id']          = $template['template_id'];
			$result['template_edit_url']    = $this->get_template_edit_url( $template['template_id'] );
			$result['template_has_content'] = $template['has_content'];
		}

		$next_step = $settings['mega_content'] ? $this->get_next_step( $template ) : '';

		if ( $next_step ) {
			$result['next_step'] = $next_step;
		}

		$result['warnings'] = $this->get_warnings();

		return $result;
	}

	/**
	 * Get the Elementor editor URL of a template.
	 *
	 * @param int $template_id Template post ID.
	 * @return string
	 */
	private function get_template_edit_url( $template_id ) {

		$document = \Elementor\Plugin::$instance->documents->get( $template_id );

		return $document ? $document->get_edit_url() : '';
	}

	/**
	 * Tell the AI how to give an enabled mega panel something to show.
	 *
	 * @param array|null $template Output of Mega_Item_Settings::get_template().
	 * @return string Empty when the template already has content.
	 */
	private function get_next_step( $template ) {

		if ( ! $template ) {
			return __( 'The mega content template is missing. Call this ability again with mega_content: true to recreate it.', 'premium-addons-for-elementor' );
		}

		if ( $template['has_content'] ) {
			return '';
		}

		return sprintf(
			/* translators: %d: template ID. */
			__( 'The mega panel is empty. Build its content with premium-addons/add-container, premium-addons/insert-widget or premium-addons/insert-premium-template using post_id %d.', 'premium-addons-for-elementor' ),
			$template['template_id']
		);
	}

	/**
	 * Get the problems that do not stop the save.
	 *
	 * @return array
	 */
	private function get_warnings() {

		if ( ! empty( Admin_Helper::get_enabled_elements()['premium-nav-menu'] ) ) {
			return array();
		}

		return array(
			array(
				'type'   => 'widget_disabled',
				'detail' => __( 'The Premium Mega Menu widget is disabled, so these options show nowhere yet. Enable premium-nav-menu with premium-addons/update-setting.', 'premium-addons-for-elementor' ),
			),
		);
	}
}
