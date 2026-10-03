<?php
/**
 * HFE Module Manager.
 *
 * @package header-footer-elementor
 */

namespace HFE\WidgetsManager;

use HFE\WidgetsManager\Base\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Modules_Manager.
 */

#[\AllowDynamicProperties]
class Modules_Manager {

	/**
	 * Member Variable
	 *
	 * @var modules.
	 */
	private $_modules = []; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->require_files();
		$this->register_modules();
	}

	/**
	 * Returns Script array.
	 *
	 * @return array()
	 * @since 1.3.0
	 */
	public function register_modules() {
		// Port: the plugin listed 15 modules here (retina, copyright,
		// navigation-menu, site-title, page-title, site-tagline, site-logo,
		// cart, search-button, breadcrumbs-widget, post-info, infocard,
		// woo-product-grid, basic-posts, counter). Only three of its widgets
		// are placed anywhere on this site (site-logo and navigation-menu in
		// the header/footer templates, hfe-basic-posts on the home page), so
		// only those are kept, in the plugin's order. 'copyright' stays in
		// the list for its two shortcodes only ([hfe_current_year],
		// [hfe_site_title]); its widget is not placed and not ported.
		$all_modules = [
			'copyright',
			'navigation-menu',
			'site-logo',
			'basic-posts',
		];

		foreach ( $all_modules as $module_name ) {
			// Port: the shortcodes the Copyright module registered, at the same
			// point (elementor/init), without its widget.
			if ( 'copyright' === $module_name ) {
				require HFE_DIR . '/inc/widgets-manager/widgets/copyright/copyright-shortcode.php';
				$copyright_shortcode = new \HFE\WidgetsManager\Widgets\Copyright\Copyright_Shortcode();
				continue;
			}

			$class_name = str_replace( '-', ' ', $module_name );

			$class_name = str_replace( ' ', '', ucwords( $class_name ) );

			$class_name = __NAMESPACE__ . '\\Widgets\\' . $class_name . '\Module';

			if ( class_exists( $class_name ) && $class_name::is_enable() ) {
				$this->_modules[ $module_name ] = $class_name::instance();

				if ( 'copyright' === $module_name ) {
					require HFE_DIR . '/inc/widgets-manager/widgets/copyright/copyright-shortcode.php';
					$copyright_shortcode = new \HFE\WidgetsManager\Widgets\Copyright\Copyright_Shortcode();
				}           
			}
		}
	}

	/**
	 * Get Modules.
	 *
	 * @param string $module_name Module Name.
	 *
	 * @since 0.0.1
	 *
	 * @return Module_Base|Module_Base[]
	 */
	public function get_modules( $module_name = null ) {
		if ( $module_name ) {
			if ( isset( $this->_modules[ $module_name ] ) ) {
				return $this->_modules[ $module_name ];
			}
			return null;
		}

		return $this->_modules;
	}

	/**
	 * Required Files.
	 *
	 * @since 0.0.1
	 */
	private function require_files() {
		require HFE_DIR . 'inc/widgets-manager/base/module-base.php';
	}

}
