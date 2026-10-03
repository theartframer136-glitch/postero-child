<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       test
 * @since      1.0.0
 *
 * @package    Google_Reviews
 * @subpackage Google_Reviews/admin
 */
class GRWP_Google_Reviews_Admin {
    private $google_reviews_options;

    private $plugin_name;

    private $version;

    private $dir;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->google_reviews_options = get_option( 'google_reviews_option_name' );
        $this->dir = plugin_dir_path( __FILE__ );
        add_action( 'admin_menu', array($this, 'gr_add_plugin_pages') );
        add_action( 'admin_init', array($this, 'google_reviews_page_init') );
        // newest version
        new GRWP_Free_API_Service();
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        require_once $this->dir . '../public/includes/allowed-html.php';
    }

    /**
     * Function to activate wp cron job to pull reviews automatically, if not already existing
     */
    public function wp_cron_activate() {
        if ( !wp_next_scheduled( 'get_google_reviews' ) ) {
            wp_schedule_event( time(), 'weekly', 'get_google_reviews' );
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        if ( !$this->is_plugin_admin_page() ) {
            return;
        }
        wp_enqueue_style(
            'admin-' . $this->plugin_name,
            GR_PLUGIN_DIR_URL . 'dist/css/google-reviews-admin.css',
            array(),
            $this->asset_version( 'dist/css/google-reviews-admin.css' ),
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        if ( !$this->is_plugin_admin_page() ) {
            return;
        }
        wp_enqueue_script(
            'admin-' . $this->plugin_name,
            GR_PLUGIN_DIR_URL . 'dist/js/admin-bundle.js',
            array('jquery'),
            $this->asset_version( 'dist/js/admin-bundle.js' ),
            false
        );
        if ( isset( $this->google_reviews_options['reviews_language_3'] ) ) {
            wp_localize_script( 'admin-' . $this->plugin_name, 'js_global', array(
                'wp_ajax_url' => admin_url( 'admin-ajax.php' ),
                'language'    => $this->google_reviews_options['reviews_language_3'],
                'nonce'       => wp_create_nonce( 'grwp_nonce_action' ),
            ) );
        } else {
            wp_localize_script( 'admin-' . $this->plugin_name, 'js_global', array(
                'wp_ajax_url' => admin_url( 'admin-ajax.php' ),
                'language'    => 'en',
                'nonce'       => wp_create_nonce( 'grwp_nonce_action' ),
            ) );
        }
    }

    /**
     * Cache-busting version for a built asset.
     *
     * Uses the file's modification time so a rebuilt bundle/stylesheet always
     * gets a fresh URL (browsers won't serve a stale cached copy). Falls back
     * to the plugin version if the file can't be found.
     *
     * @param string $relative_path Path under the plugin root, e.g. 'dist/js/admin-bundle.js'.
     * @return string|int
     */
    private function asset_version( $relative_path ) {
        $file = GR_BASE_PATH . $relative_path;
        return ( file_exists( $file ) ? filemtime( $file ) : $this->version );
    }

    /**
     * Check whether the current admin request belongs to this plugin.
     *
     * @return bool
     */
    private function is_plugin_admin_page() {
        $page = ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' );
        return 'google-reviews' === $page || 'google-reviews-translation' === $page || 'how-to-free-version' === $page || 'how-to-premium-version' === $page;
    }

    /**
     * Add menu pages to backend
     */
    public function gr_add_plugin_pages() {
        // Parent for free and pro version
        new GRWP_Global_Menu_Pages();
        new GRWP_Free_Menu_Pages();
    }

    /**
     * Register settings, sections and option fields
     */
    public function google_reviews_page_init() {
        // Global settings
        new GRWP_Global_Settings();
        new GRWP_Free_Settings();
    }

}
