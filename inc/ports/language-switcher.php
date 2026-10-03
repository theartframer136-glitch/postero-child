<?php
/**
 * Language switcher: the plugin "Language Switcher for Transposh" 2.0.6, moved
 * into the theme (the English / Hindi flags in the primary menu and the
 * [lsft_*] shortcodes). Transposh itself stays a plugin.
 *
 * inc/ports/lsft/ is the plugin's own code, copied unchanged except for the
 * four lines that built asset URLs from the plugin folder's location (they
 * now use LSFT_PLUGIN_URL / LSFT_PLUGIN_PATH, which point at the copy). Its
 * assets (flags, styles including the saved lsft.css, public and admin CSS/JS)
 * are copied byte for byte to assets/ports/lsft/ with the plugin's layout.
 * Settings stay in the option cfxlsft_options; the settings page still works.
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('CFX_LSFT_VERSION') || class_exists('Cfx_Language_Switcher_For_Transposh', false)) {
    return; // the plugin is active and does the work
}

define('CFX_LSFT_VERSION', '2.0.6');
define('LSFT_PLUGIN', __DIR__ . '/lsft/cfx-language-switcher-for-transposh.php');
define('LSFT_PLUGIN_URL', get_stylesheet_directory_uri() . '/assets/ports/lsft/');
define('LSFT_PLUGIN_PATH', get_stylesheet_directory() . '/assets/ports/lsft/');

require_once __DIR__ . '/lsft/includes/class-cfx-language-switcher-for-transposh.php';

// cfx-language-switcher-for-transposh.php: run_cfx_language_switcher_for_transposh()
$af_lsft = new Cfx_Language_Switcher_For_Transposh();
$af_lsft->run();
unset($af_lsft);
