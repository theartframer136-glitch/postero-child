<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://codingfix.com
 * @since      1.0.0
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/includes
 * @author     Marco Gasi <codingfix@codingfix.com>
 */
class Cfx_Language_Switcher_For_Transposh {


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Cfx_Language_Switcher_For_Transposh_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $cfx_language_switcher_for_transposh;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The options of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $options    The options of this plugin.
	 */
	private $options;

	/**
	 * The options of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The name of this plugin.
	 */
	private $plugin_name;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'CFX_LSFT_VERSION' ) ) {
			$this->version = CFX_LSFT_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'cfx-language-switcher-for-transposh';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->options = get_option( 'cfxlsft_options' );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Cfx_Language_Switcher_For_Transposh_Loader. Orchestrates the hooks of the plugin.
	 * - Cfx_Language_Switcher_For_Transposh_i18n. Defines internationalization functionality.
	 * - Cfx_Language_Switcher_For_Transposh_Admin. Defines all hooks for the admin area.
	 * - Cfx_Language_Switcher_For_Transposh_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cfx-language-switcher-for-transposh-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cfx-language-switcher-for-transposh-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-cfx-language-switcher-for-transposh-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-cfx-language-switcher-for-transposh-public.php';

		/**
		 * The widget
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'widgets/language-switcher-for-transposh-widget.php';

		$this->loader = new Cfx_Language_Switcher_For_Transposh_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Cfx_Language_Switcher_For_Transposh_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new Cfx_Language_Switcher_For_Transposh_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Cfx_Language_Switcher_For_Transposh_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		// creating admin settings page menu subitem in Settings menu.
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'cfxlsft_admin_menu' );

		$this->loader->add_action( 'admin_notices', $plugin_admin, 'no_transposh_found' );
		/**
		 * Processing users choices
		 * First we initialize the plugin adding two actions to save and process plugin options
		 */
		$this->loader->add_action( 'admin_init', $plugin_admin, 'cfxlsft_admin_init' );
		/**
		 * Adding Ajax action
		 */
		$this->loader->add_action( 'wp_ajax_load_style', $plugin_admin, 'load_style' );

		$this->loader->add_action( 'admin_post_save_cfxlsft_options', $plugin_admin, 'process_cfxlsft_options' );

		/**
		 * Registers the widget
		 */
		$this->loader->add_action( 'widgets_init', $plugin_admin, 'register_widgets' );

		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );

		$this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new Cfx_Language_Switcher_For_Transposh_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		/**
		 * Set the behavior of the edit button
		 */
		$this->loader->add_action( 'wp_head', $plugin_public, 'cfxlsft_edit_button_action' );
		$this->loader->add_filter( 'wp_nav_menu_items', $plugin_public, 'cfxlsft_add_menu_item', 10, 2 );
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Cfx_Language_Switcher_For_Transposh_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
