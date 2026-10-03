<?php
/*
Plugin Name: WPC Smart Messages for WooCommerce
Plugin URI: https://wpclever.net/
Description: Display messages throughout your store through smart conditional logic settings.
Version: 4.2.7
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-smart-messages
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.9
WC requires at least: 3.0
WC tested up to: 10.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

! defined( 'WPCSM_VERSION' ) && define( 'WPCSM_VERSION', '4.2.7' );
! defined( 'WPCSM_LITE' ) && define( 'WPCSM_LITE', __FILE__ );
! defined( 'WPCSM_FILE' ) && define( 'WPCSM_FILE', __FILE__ );
! defined( 'WPCSM_URI' ) && define( 'WPCSM_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCSM_DIR' ) && define( 'WPCSM_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCSM_REVIEWS' ) && define( 'WPCSM_REVIEWS', 'https://wordpress.org/support/plugin/wpc-smart-messages/reviews/' );
! defined( 'WPCSM_CHANGELOG' ) && define( 'WPCSM_CHANGELOG', 'https://wordpress.org/plugins/wpc-smart-messages/#developers' );
! defined( 'WPCSM_DISCUSSION' ) && define( 'WPCSM_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-smart-messages' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCSM_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

// plugin activate
include 'includes/class-activate.php';
register_activation_hook( WPCSM_FILE, [ 'Wpcsm_Activate', 'generate_examples' ] );

if ( ! function_exists( 'wpcsm_init' ) ) {
    add_action( 'plugins_loaded', 'wpcsm_init', 11 );

    function wpcsm_init() {
        if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
            add_action( 'admin_notices', 'wpcsm_notice_wc' );

            return null;
        }

        if ( ! class_exists( 'WPCleverWpcsm' ) && class_exists( 'WC_Product' ) ) {
            class WPCleverWpcsm {
                public function __construct() {
                    require_once 'includes/class-shortcode.php';
                    require_once 'includes/class-backend.php';
                    require_once 'includes/class-frontend.php';
                }
            }

            new WPCleverWpcsm();
        }
    }
}

if ( ! function_exists( 'wpcsm_notice_wc' ) ) {
    function wpcsm_notice_wc() {
        ?>
        <div class="error">
            <p><strong>WPC Smart Messages</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
        <?php
    }
}
