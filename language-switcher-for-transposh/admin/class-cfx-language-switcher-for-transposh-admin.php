<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://codingfix.com
 * @since      1.0.0
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/admin
 * @author     Marco Gasi <codingfix@codingfix.com>
 */
class Cfx_Language_Switcher_For_Transposh_Admin {


	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	public $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The options for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The options for this plugin.
	 */
	private $options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->options     = get_option( 'cfxlsft_options' );
	}

	/**
	 * Initialization of some properties if required.
	 *
	 * @since    1.0.0
	 */
	public function cfxlsft_admin_init() {
		// 1. Load existing options.
		$options = get_option( 'cfxlsft_options', array() );

		$current_version = isset( $options['version'] ) ? $options['version'] : '1.0.0';

		// 2. Check if new key exists in the array.
		if ( version_compare( $current_version, CFX_LSFT_VERSION, '<' ) ) {
			$options['custom_style'] = ''; // Initialize the new key with a default value.

			// Remove the old one if present in the array.
			if ( isset( $options['customCSS'] ) ) {
				unset( $options['customCSS'] );
			}

			$options['version'] = CFX_LSFT_VERSION;

			// 3. Save the entire updated array.
			update_option( 'cfxlsft_options', $options );
		}
	}

	/**
	 * Transposh is required.
	 *
	 * @since    1.0.0
	 */
	public function no_transposh_found() {
		if ( ! is_plugin_active( 'transposh-translation-filter-for-wordpress/transposh.php' ) ) {
			?>
			<div class="error notice">
				<p>Transposh plugin has not been found! Transposh plugin is required to use Language Switcher for Transposh: please, download and install it from the <a href="https://transposh.org/download" target="_blank">official website</a>.</p>
			</div>
			<?php
		}
	}

	/**
	 * Check if Transposh is installed.
	 *
	 * @since    1.0.0
	 */
	public function check_transposh() {

		if ( ! file_exists( WP_PLUGIN_DIR . '/transposh-translation-filter-for-wordpress/core/utils.php' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Register widgets
	 *
	 * @since 1.2.8
	 */
	public function register_widgets() {
		if ( true === $this->check_transposh() ) {
			register_widget( 'Language_Switcher_Widget' );
		}
	}

	/**
	 * Compares current version of the plugin with the one of the installed plugin: if needed,
	 * it calls activation hook
	 *
	 * @since    1.2.6
	 */
	public function check_lsft_version() {
		$options           = get_option( 'cfxlsft_options' );
		$installed_version = str_replace( '.', '', $options['version'] );
		$current_version   = str_replace( '.', '', CFX_LSFT_VERSION );
		if ( $installed_version <= '132' ) {
			$dir1 = new RecursiveDirectoryIterator( plugin_dir_path( __DIR__ ) . 'assets/styles', RecursiveDirectoryIterator::SKIP_DOTS );
			$dir  = new RecursiveIteratorIterator( $dir1 );
			foreach ( $dir as $fileinfo ) {
				$file_parts = explode( '.', $fileinfo->getFilename() );
				if ( $fileinfo->getFilename() === 'basic-flags.css' || $fileinfo->getFilename() === 'basic-list.css' || $fileinfo->getFilename() === 'basic-select.css' || $fileinfo->getFilename() === 'default.css' || $fileinfo->getFilename() === 'transparent.css' ) {
					wp_delete_file( $fileinfo->getPath() . '/' . $fileinfo->getFilename() );
				}
			}
			$dir = new DirectoryIterator( plugin_dir_path( __DIR__ ) . 'assets/styles' );
			foreach ( $dir as $fileinfo ) {
				if ( $fileinfo->getFilename() === 'flags' || $fileinfo->getFilename() === 'list' || $fileinfo->getFilename() === 'select' ) {
					// Initialize WP Filesystem and try to remove the directory recursively.
					WP_Filesystem();
					global $wp_filesystem;
					$target = $fileinfo->getPath() . '/' . $fileinfo->getFilename();
					if ( $wp_filesystem && method_exists( $wp_filesystem, 'rmdir' ) ) {
						$wp_filesystem->rmdir( $target, true );
					} else {
						// Fallback to PHP-based recursive removal.
						$this->cfxlsft_rrmdir( $target );
					}
				}
			}
		}
		if ( $installed_version !== $current_version ) {
			require_once plugin_dir_path( __FILE__ ) . '../includes/class-cfx-language-switcher-for-transposh-activator.php';
			Cfx_Language_Switcher_For_Transposh_Activator::activate();
		}
	}
	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cfx-language-switcher-for-transposh-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		$page = filter_input( INPUT_GET, 'page', FILTER_CALLBACK, array( 'options' => 'esc_html' ) );
		if ( isset( $page ) && '' !== $page ) {
			if ( false !== strpos( $page, 'language-switcher-settings' ) ) {
				// jquery-form is needed to make it easy to save form via Ajax.
				wp_enqueue_script( 'cfx_language_switcher_for_transposh_admin_js', plugin_dir_url( __FILE__ ) . 'js/cfx-language-switcher-for-transposh-admin.js', array( 'jquery' ), $this->version, false );
				wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
				wp_localize_script(
					'cfx_language_switcher_for_transposh_admin_js',
					'ajax_object',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'we_value' => 1234,
					)
				);
			}
		}
	}

	/**
	 * Register the Settings page.
	 *
	 * @since    1.0.0
	 */
	public function cfxlsft_admin_menu() {
		add_options_page( __( 'Language Switcher for Transposh', 'Language Switcher for Transposh' ), __( 'Language Switcher for Transposh', 'Language Switcher for Transposh' ), 'manage_options', 'language-switcher-settings', array( $this, 'display_plugin_admin_page' ) );
	}

	/**
	 * Load correct style
	 *
	 * @since     1.0.0
	 */
	public function load_style() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		$css           = '';
		$full_css_path = LSFT_PLUGIN_PATH . 'assets/styles/lsft.css';
		WP_Filesystem();
		global $wp_filesystem;
		$css = $wp_filesystem->get_contents( $full_css_path );
		echo esc_html( $css );
		exit();
	}

	/**
	 * Processes and save options.
	 *
	 * @since     1.0.0
	 */
	public function process_cfxlsft_options() {
		$options = get_option( 'cfxlsft_options' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'cfxlsft' );

		if ( isset( $_POST['automode'] ) ) {
			$options['automode'] = sanitize_text_field( wp_unslash( $_POST['automode'] ) );
		} else {
			$options['automode'] = 'off';
		}

		if ( isset( $_POST['redirect_to_home'] ) ) {
			$options['redirect_to_home'] = sanitize_text_field( wp_unslash( $_POST['redirect_to_home'] ) );
		} else {
			$options['redirect_to_home'] = 'off';
		}

		if ( isset( $_POST['original_lang_names'] ) ) {
			$options['original_lang_names'] = sanitize_text_field( wp_unslash( $_POST['original_lang_names'] ) );
		} else {
			$options['original_lang_names'] = 'off';
		}

		if ( isset( $_POST['flag_size'] ) ) {
			$options['flag_size'] = sanitize_text_field( wp_unslash( $_POST['flag_size'] ) );
			$flag_size            = $options['flag_size'];
		}

		$options['flag_type'] = 'lsft';
		if ( isset( $_POST['flag_type'] ) ) {
			$options['flag_type'] = 'tp';
		}

		if ( isset( $_POST['usa_flag'] ) ) {
			$options['usa_flag'] = sanitize_text_field( wp_unslash( $_POST['usa_flag'] ) );
		} else {
			$options['usa_flag'] = 'off';
		}

		if ( isset( $_POST['menu_classes'] ) ) {
			$options['menu_classes'] = sanitize_text_field( wp_unslash( $_POST['menu_classes'] ) );
		}

		if ( isset( $_POST['switcher_type'] ) ) {
			$options['switcher_type'] = sanitize_text_field( wp_unslash( $_POST['switcher_type'] ) );
		}

		if ( isset( $_POST['custom_style'] ) ) {
			$options['custom_style'] = sanitize_text_field( wp_unslash( $_POST['custom_style'] ) );
		}

		if ( isset( $_POST['list_style'] ) ) {
			$options['list_style'] = sanitize_text_field( wp_unslash( $_POST['list_style'] ) );
		}

		if ( isset( $_POST['flag_style'] ) ) {
			$options['flag_style'] = sanitize_text_field( wp_unslash( $_POST['flag_style'] ) );
		}

		if ( isset( $_POST['menu_locations'] ) ) {
			$options['menu_locations'] = implode( ',', array_map( 'sanitize_text_field', wp_unslash( $_POST['menu_locations'] ) ) );
		}

		if ( isset( $_POST['custom_list_items'] ) ) {
			$options['custom_list_items'] = sanitize_text_field( wp_unslash( $_POST['custom_list_items'] ) );
		}

		if ( isset( $_POST['activeTab'] ) && ! empty( $_POST['activeTab'] ) ) {
			$tab = sanitize_text_field( wp_unslash( $_POST['activeTab'] ) );
		}

		update_option( 'cfxlsft_options', $options );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'language-switcher-settings',
					'tab'     => $tab,
					'message' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Add a links near Deactivate link in the plugin list
	 *
	 * @since    1.0.0
	 * @param    array $links Array of plugin links.
	 */
	public function add_action_links( $links ) {
		/*
		*  Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
		*/
		$settings_link = array(
			'<a href="' . admin_url( 'admin.php?page=language-switcher-settings' ) . '">' . __( 'Settings', 'cfx-language-switcher-for-transposh' ) . '</a>',
		);
		return array_merge( $settings_link, $links );
	}

	/**
	 * Callback function for the admin settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		require_once plugin_dir_path( __DIR__ ) . 'admin/partials/cfx-language-switcher-for-transposh-admin-display.php';
	}

	/**
	 * Recursively remove a directory using PHP functions as a fallback when WP_Filesystem is unavailable.
	 *
	 * @param string $dir Directory path to remove.
	 * @return void
	 */
	private function cfxlsft_rrmdir( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}
		if ( is_file( $dir ) || is_link( $dir ) ) {
			wp_delete_file( $dir );
			return;
		}
		$items = scandir( $dir );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				$this->cfxlsft_rrmdir( $dir . DIRECTORY_SEPARATOR . $item );
			}
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && method_exists( $wp_filesystem, 'rmdir' ) ) {
			$wp_filesystem->rmdir( $dir, true );
		} else {
			// Fallback to PHP-based recursive removal.
			$this->cfxlsft_rrmdir( $dir );
		}
	}
}
