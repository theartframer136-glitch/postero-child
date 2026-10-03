<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://codingfix.com
 * @since             1.0.0
 * @package           Cfx_Language_Switcher_For_Transposh
 *
 * @wordpress-plugin
 * Plugin Name:       Language Switcher for Transposh
 * Plugin URI:        https://codingfix.com/language-switcher-for-transposh
 * Description:       A small plugin to use a customized language switcher with Transposh plugin.
 * Version:           2.0.6
 * Author:            Marco Gasi
 * Author URI:        https://codingfix.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       language-switcher-for-transposh
 * Domain Path:       /languages
 */

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CFX_LSFT_VERSION', '2.0.6' );

/**
 * Define some constant to link some resource.
 */
define( 'LSFT_PLUGIN', __FILE__ );
define( 'LSFT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LSFT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cfx-language-switcher-for-transposh-activator.php
 */
function activate_cfx_language_switcher_for_transposh() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cfx-language-switcher-for-transposh-activator.php';
	Cfx_Language_Switcher_For_Transposh_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cfx-language-switcher-for-transposh-deactivator.php
 */
function deactivate_cfx_language_switcher_for_transposh() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cfx-language-switcher-for-transposh-deactivator.php';
	Cfx_Language_Switcher_For_Transposh_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_cfx_language_switcher_for_transposh' );
register_deactivation_hook( __FILE__, 'deactivate_cfx_language_switcher_for_transposh' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cfx-language-switcher-for-transposh.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cfx_language_switcher_for_transposh() {

	$plugin = new Cfx_Language_Switcher_For_Transposh();
	$plugin->run();
}

run_cfx_language_switcher_for_transposh();
