<?php
/**
 * Fired during plugin activation
 *
 * @link       https://codingfix.com
 * @since      1.0.0
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/includes
 * @author     Marco Gasi <codingfix@codingfix.com>
 */
class Cfx_Language_Switcher_For_Transposh_Activator {

	/**
	 * Activating the plugin
	 *
	 * Sets options for the plugin
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		$defaults         = array(
			'flag_type'           => 'lsft',
			'usa_flag'            => 'off',
			'automode'            => 'on',
			'redirect_to_home'    => 'off',
			'original_lang_names' => 'on',
			'switcher_type'       => 'flags',
			'menu_classes'        => '',
			'select_as_list'      => 'yes',
			'custom_list_items'   => 'flag-only',
			'menu_locations'      => 'primary',
			'custom_style'        => '',
			'version'             => '2.0.0',
			'hide_refb_notice'    => 'no',
		);
		$options          = get_option( 'cfxlsft_options', array() );
		$options_to_store = array_merge( $defaults, $options );
		update_option( 'cfxlsft_options', $options_to_store );
	}
}
