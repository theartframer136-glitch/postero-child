<?php
/**
 * Slider Revolution 6.7.40: what revslider/revslider.php does on the
 * storefront, as the theme runs it. Loaded by inc/ports/revslider.php only
 * while the plugin is off.
 *
 * Every line copied from revslider.php is unchanged; the lines marked "port:"
 * are the theme's. The plugin's classes are its own files, unchanged, in this
 * folder with the plugin's layout (includes/, public/, admin/includes/…).
 *
 * The plugin's folder constant RS_PLUGIN_PATH points at the theme copy of its
 * public files (public/, sr6/assets/), assets/ports/revslider/ — the folder
 * the plugin reads through that constant at run time: public/js/page.js is
 * printed into every page's head, public/js/libs, public/css and
 * sr6/assets/js are checked for what exists, and includes/googlefonts.php,
 * navigations.php, basic-css.php and coloreasing.class.php are read as data.
 * RS_PLUGIN_URL is that folder's URL, so SR7.E.plugin_url, the tp-tools/sr7/
 * sr7css files and everything sr7.js loads later come from the theme.
 */
defined('ABSPATH') || exit;

// port: the plugin defines these from its own file (plugin_dir_path(__FILE__),
// plugin_basename(__FILE__), __FILE__); the slug path stays the plugin's, as
// the head script prints it (SR7.E.slug_path).
define('RS_REVISION',			'6.7.40');
define('RS_PLUGIN_PATH',		get_stylesheet_directory() . '/assets/ports/revslider/');
define('RS_PLUGIN_SLUG_PATH',	'revslider/revslider.php');
define('RS_PLUGIN_FILE_PATH',	WP_PLUGIN_DIR . '/revslider/revslider.php');
define('RS_PLUGIN_SLUG',		apply_filters('set_revslider_slug', 'revslider'));
define('RS_PLUGIN_URL',			get_sr_plugin_url());
define('RS_PLUGIN_URL_CLEAN',	str_replace(array('http://', 'https://'), '//', RS_PLUGIN_URL));
define('RS_DEMO',				false);
define('RS_TP_TOOLS',			'6.7.40'); //holds the version of the tp-tools script, load only the latest!

global $SR_GLOBALS;

$SR_GLOBALS = array(
	'addon_notice_merged'	=> 0,
	'animations'			=> array(),
	'collections'			=> array(
		'css'	=> array(),
		'ids'	=> array(),
		'js'	=> array('revapi' => array(), 'js' => array(), 'minimal' => '', 'stream' => array()),
		'trans'	=> array(),
		'nav'	=> array('arrows' => array(), 'thumbs' => array(), 'bullets' => array(), 'tabs' => array(), 'scrubber' => array()),
		'v6tov7'=> array('n' => array(), 's' => array()),
	),
	'deprecated'			=> array(),
	'fonts'					=> array('queue' => array(), 'loaded' => array(), 'custom' => array()),
	'front_version'			=> get_sr_current_engine(),
	'header_js'				=> false,
	'icon_sets'				=> array(
		'Materialicons' => array('css' => false, 'parsed' => false),
		'FontAwesome'	=> array('css' => false, 'parsed' => false),
		'PeIcon'		=> array('css' => false, 'parsed' => false),
		'RevIcon'		=> array('css' => false, 'parsed' => false)
	),
	'data_init'				=> true,
	'js_init'				=> false,
	'loaded_by_editor'		=> false,
	'preview_mode'			=> false,
	'markup_export'			=> false,
	'save_post'				=> false,
	'use_table_version'		=> 6,
	'serial'				=> 0,
	'sliders'				=> array(),
	'yt_api_loaded'			=> false,
	'bad_extensions'		=> array(
		'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'phps', 'phps', 'pht', 'phtm', 'phtml', 'pgif', 'shtml', 'htaccess', 'phar', 'inc', 'hphp', 'ctp', 'module',
		'asp', 'aspx', 'config', 'ashx', 'asmx', 'aspq', 'axd', 'cshtm', 'cshtml', 'rem', 'soap', 'vbhtm', 'vbhtml', 'asa', 'cer', 'shtml',
		'jsp', 'jspx', 'jsw', 'jsv', 'jspf', 'wss', 'do', 'action',
		'cfm, .cfml, .cfc, .dbm',
		'swf',
		'pl', 'cgi',
		'yaws',
		'zip', 'rar', '7z',
		'html', 'htm', 'js', 'exe', 'bat', 'cmd', 'vbs', 'msi', 'reg', 'scr', 'com', 'pif', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'swf', 'htaccess', 'sh', 'py', 'rb', 'ps1', 'psm1', 'jar', 'jspx', 'xhtml', 'jspx', 'shtml', 'ini', 'dll', 'sys', 'jspx'
	),
	'mime_types'			=> array(
		'image'	=> array('jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'),
		'video'	=> array('mpeg|mpg|mpe' => 'video/mpeg', 'mp4|m4v' => 'video/mp4', 'ogv' => 'video/ogg', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg')
	)
);

// port: the plugin requires all of its class files here. The theme requires
// the ones the plugin uses on every request, in the plugin's order, and loads
// the rest (the slider, slide and output classes, the stream sources, the
// CSS/navigation/colour helpers, the database upgrader, the old class names)
// only when first used, from the same unchanged files. Not carried: the admin
// and editor files, the plugin-update/licence checks and the Divi module.
require_once(__DIR__ . '/includes/data.class.php');
require_once(__DIR__ . '/includes/functions.class.php');
require_once(__DIR__ . '/includes/em-integration.class.php');
require_once(__DIR__ . '/includes/woocommerce.class.php');
require_once(__DIR__ . '/admin/includes/widget.class.php');
require_once(__DIR__ . '/includes/extension.class.php');
require_once(__DIR__ . '/includes/aq-resizer.class.php');
require_once(__DIR__ . '/includes/page-template.class.php');
require_once(__DIR__ . '/public/revslider-front-global.class.php');
if($SR_GLOBALS['front_version'] === 6){
	require_once(__DIR__ . '/sr6/revslider-front.class.php');
}else{
	require_once(__DIR__ . '/public/revslider-front.class.php');
}
require_once(__DIR__ . '/includes/globals.class.php');
require_once(__DIR__ . '/includes/api.class.php');
require_once(__DIR__ . '/includes/wpml.class.php');
require_once(__DIR__ . '/includes/jetpack.class.php');

spl_autoload_register(function($class){
	static $files = array(
		'RevSliderCache'			=> 'includes/cache.class.php',
		'RevSliderCssParser'		=> 'includes/cssparser.class.php',
		'RSColorpicker'				=> 'includes/colorpicker.class.php',
		'RevSliderNavigation'		=> 'includes/navigation.class.php',
		'RevSliderObjectLibrary'	=> 'includes/object-library.class.php',
		'RevSliderLoadBalancer'		=> 'admin/includes/loadbalancer.class.php',
		'RevSliderPluginUpdate'		=> 'admin/includes/plugin-update.class.php',
		'RevSliderFavorite'			=> 'includes/favorite.class.php',
		'RevSliderFacebook'			=> 'includes/external/facebook.class.php',
		'RevSliderFlickr'			=> 'includes/external/flickr.class.php',
		'RevSliderInstagram'		=> 'includes/external/instagram.class.php',
		'RevSliderVimeo'			=> 'includes/external/vimeo.class.php',
		'RevSliderYoutube'			=> 'includes/external/youtube.class.php',
		'RevSliderSlider'			=> 'includes/slider.class.php',
		'RevSliderSlide'			=> 'includes/slide.class.php',
		'RevSliderOutput'			=> 'includes/output.sr6.class.php',
		'RevSlider7Output'			=> 'includes/output.sr7.class.php',
		'RevSliderBase'				=> 'includes/backwards.php',
		'RevSliderFunctionsWP'		=> 'includes/backwards.php',
		'RevSliderOperations'		=> 'includes/backwards.php',
		'RevSlider'					=> 'includes/backwards.php',
		'UniteFunctionsRev'			=> 'includes/backwards.php',
	);
	if(isset($files[$class])) require_once(__DIR__ . '/' . $files[$class]);
});

// port: includes/backwards.php declares this at load; the theme loads that
// file only when one of its old class names is used, so the function is here.
if(!function_exists('set_revslider_as_theme')){
	function set_revslider_as_theme(){
	}
}

try{
	RevSliderFunctions::set_memory_limit();

	function rev_slider_shortcode($args, $mid_content = null){

		//do not render in elementor preview iframe
		if(isset($_GET['elementor-preview'])) return false;

		//do not render on saving a post/page
		global $SR_GLOBALS;
		if($SR_GLOBALS['save_post']) return false;
		
		//skip shortcode generation if any of these functions found in backtrace 
		//function can be provided as array item without key
		//or as 'class' => 'function'
		$skip_functions = apply_filters(
			'rs_shortcode_skip_functions',
			array(
				'WC_Structured_Data' => 'generate_product_data', // woocommerce
				'AIOSEO\Plugin\Common\Meta\Description' => 'getDescription', // all-in-one-seo
				//'Elementor\Core\Editor\Editor' => 'print_editor_template', // elementor
			)
		);

		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		foreach($backtrace as $trace){
			foreach($skip_functions as $class => $func){
				if($trace['function'] == $func){
					//no class was provided, func matched, return
					if(!is_string($class)) return false;
					//class provided in key, compare with trace class
					if(isset($trace['class']) && $trace['class'] == $class) return false;
				}
			}
		}
		
		$sc		= shortcode_atts(array('alias' => '', 'layout' => '', 'modal' => '', 'offset' => '', 'order' => '', 'settings' => '', 'skin' => '', 'usage' => '', 'zindex' => ''), $args, 'rev_slider');
		$sc		= array_map('wp_kses_post', $sc);
		$output = ($SR_GLOBALS['front_version'] === 6) ? new RevSliderOutput() : new RevSlider7Output();

		if(is_admin() && $output->_is_gutenberg_page()) return false;

		$slider_alias = ($sc['alias'] != '') ? $sc['alias'] : $output->get_val($args, 0); //backwards compatibility

		//this fixes an issue with the Visual Composer extension
		if(empty($slider_alias)){
			return (function_exists('is_user_logged_in') && is_user_logged_in()) ? '<div><img src="' . RS_PLUGIN_URL_CLEAN . 'admin/assets/images/rs6_logo_2x.png"></div>' : '';
		}

		$output->set_custom_order($sc['order']);
		$output->set_custom_settings($sc['settings']);
		$output->set_custom_skin($sc['skin']);

		$gallery_ids = $output->check_for_shortcodes($mid_content); //check for example on gallery shortcode and do stuff
		if($gallery_ids !== false) $output->set_gallery_ids($gallery_ids);

		ob_start();
		
		//reset after each Slider back to origin
		$table_version = $SR_GLOBALS['use_table_version'];

		if($SR_GLOBALS['front_version'] === 6){
			$slider = $output->add_slider_to_stage($slider_alias, $sc['usage'], $sc['layout'], $sc['offset'], $sc['modal']);
		}else{
			$output->set_usage($sc['usage']);
			$output->set_layout($sc['layout']);
			$output->set_offset($sc['offset']);
			$sc['modal'] = (empty($sc['modal'])) ? 'true' : $sc['modal'];
			if($sc['usage'] === 'modal') $output->set_modal($sc['modal']);
			$slider = $output->add_slider_to_stage($slider_alias);
		}
		$content = ob_get_contents();

		$SR_GLOBALS['use_table_version'] = $table_version;

		ob_clean();
		ob_end_clean();

		if(!empty($sc_attr['zindex'])){
			$content = '<div class="wp-block-themepunch-revslider" style="z-index:'.esc_attr($sc_attr['zindex']).';">'. $content .'</div>';
		}

		if(empty($slider)) return $content;
		$filter = ($slider->v7) ? $slider->get_param(array('general', 'outPutFilter'), '') : $slider->get_param(array('troubleshooting', 'outPutFilter'), '');
		switch($filter){
			case 'compress':
				$content = str_replace(array("\n", "\r"), '', $content);
				return $content;
			case 'echo':
				global $SR_GLOBALS;
				if($SR_GLOBALS['save_post']) return $content;
				echo $content; //bypass the filters
			break;
		}

		return $content;
	}
	
	$SR_wpml	= RevSliderGlobals::instance()->get('RevSliderWpml');
	$SR_jetpack	= RevSliderGlobals::instance()->get('RevSliderJetPack');
	$SR_api		= RevSliderGlobals::instance()->get('RevSliderApi');
	// port: no $rslb->refresh_server_list() — the plugin's monthly call to
	// updates.themepunch.tools for its update/library servers (used by the
	// admin only). The editor's own AJAX actions are not carried either; the
	// storefront's (revslider_ajax_call_front) and the REST routes are.
	remove_action('wp_ajax_revslider_ajax_action', array($SR_api, 'do_ajax_action'));
	remove_action('wp_ajax_rs_ajax_action', array($SR_api, 'do_ajax_action'));
	add_shortcode('rev_slider', 'rev_slider_shortcode');
	add_shortcode('sr7', 'rev_slider_shortcode');
	add_action('save_post', array('RevSliderFront', 'set_post_saving'));
	add_action('widgets_init', array('RevSliderWidget', 'register_widget'));

	// port: the plugin loads its admin and editor classes here when is_admin().
	if(!is_admin()){
		$rev_slider_front = new RevSliderFront();
	}

	// port: the plugin hooks these to plugins_loaded, which has run by the time
	// the theme loads, so they run now, in the same order. Left out:
	// RevSliderPluginUpdate::do_update_checks (nothing left to do: the
	// database is at revslider_update_version 6.7.24, its last step; running
	// it would only load that 380 KB class on every request) and the
	// editor half of add_post_editor (the shortcode wizard, the Gutenberg
	// block, WPBakery). Its Elementor half is below: the slider_revolution
	// widget, without the editor panel's scripts and styles, which belong to
	// the wizard.
	RevSliderFront::create_tables();
	RevSliderPageTemplate::get_instance();
	require_once(__DIR__ . '/admin/includes/shortcode_generator/elementor/elementor.class.php');
	add_action('init', array('RevSliderElementor', 'init'));
	add_action('init', function(){
		remove_action('elementor/editor/after_enqueue_styles', array('RevSliderShortcodeWizard', 'add_styles'));
		remove_action('elementor/editor/after_enqueue_scripts', array('RevSliderElementor', 'add_scripts'));
	}, 11);
	add_filter('wpseo_sitemap_entry', array('RevSliderFront', 'get_images_for_seo'), 10, 3);
	add_filter('rocket_rucss_inline_atts_exclusions', array('RevSliderFront', 'wp_rocket_inline_atts_exclusions'));
}catch(Exception $e){
	$message = $e->getMessage();
	//$trace = $e->getTraceAsString();
	echo _e('Revolution Slider Error:', 'revslider').' <b>'. esc_html($message) .'</b>';
}

/**
 * add RevSlider to the page/post
 */
function putRevSlider($data, $put_in = ''){
	add_revslider($data, $put_in);
}

function add_revslider($data, $put_in = ''){
	global $SR_GLOBALS;

	//reset after each Slider back to origin
	$table_version = $SR_GLOBALS['use_table_version'];
	$output		= ($SR_GLOBALS['front_version'] === 6) ? new RevSliderOutput() : new RevSlider7Output();
	$g_values	= $output->get_global_settings();
	$add_to		= $output->get_val($g_values, 'includeids', '');
	$output->set_add_to($add_to);
	if($output->check_add_to(true) == false && $output->_truefalse($output->get_val($g_values, 'allinclude', true)) == false){
		$output->print_error_message(__('If you want to use the PHP function "add_revslider" in your code please make sure to activate ', 'revslider').__('"Include RevSlider libraries globally" ', 'revslider').__('and/or add the current page to the ', 'revslider').__('"Pages to include RevSlider libraries" option ', 'revslider').__('in the "Global Settings" of Slider Revolution.', 'revslider'));
		return false;
	}

	ob_start();
	$output->set_add_to($put_in);
	$slider = $output->add_slider_to_stage($data);
	$content = ob_get_contents();
	ob_clean();
	ob_end_clean();

	echo $content;

	$SR_GLOBALS['use_table_version'] = $table_version;
}

// port: the theme copy of the plugin folder (the plugin: plugins_url('index.php', __FILE__) without 'index.php')
function get_sr_plugin_url(){
	$url = get_stylesheet_directory_uri() . '/assets/ports/revslider/';
	if(strpos($url, 'http') === false){
		$site_url	= get_site_url();
		$url		= (substr($site_url, -1) === '/') ? substr($site_url, 0, -1). $url : $site_url. $url;
	}

	return str_replace(array(chr(10), chr(13)), '', $url);
}

function get_sr_current_engine(){
	$global	= get_option('revslider-global-settings', '');
	$global	= (!is_array($global)) ? json_decode($global, true) : $global;
	$engine	= (isset($global['getTec']) && isset($global['getTec']['engine']) && $global['getTec']['engine'] === 'SR7') ? 7 : 6;
	$engine	= (isset($_GET['srengine']) && (intval($_GET['srengine']) === 6 || intval($_GET['srengine']) === 7)) ? intval($_GET['srengine']) : $engine;
	/*if(isset($_REQUEST['action']) && isset($_REQUEST['client_action']) && isset($_REQUEST['nonce'])){ // && wp_verify_nonce($_REQUEST['nonce'], 'revslider_actions') !== false
		if($_REQUEST['action'] === 'rs_ajax_action' && $_REQUEST['client_action'] === 'preview_slider') $engine = 6;
	}*/

	return $engine;
}
