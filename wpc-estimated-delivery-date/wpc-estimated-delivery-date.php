<?php
/*
Plugin Name: WPC Estimated Delivery Date for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Estimated Delivery Date allows you to establish and personalize delivery times for each product available in your store on several levels.
Version: 2.6.2
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-estimated-delivery-date
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.9
WC requires at least: 3.0
WC tested up to: 10.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

! defined( 'WPCED_VERSION' ) && define( 'WPCED_VERSION', '2.6.2' );
! defined( 'WPCED_LITE' ) && define( 'WPCED_LITE', __FILE__ );
! defined( 'WPCED_FILE' ) && define( 'WPCED_FILE', __FILE__ );
! defined( 'WPCED_DIR' ) && define( 'WPCED_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCED_URI' ) && define( 'WPCED_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCED_SUPPORT' ) && define( 'WPCED_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpced&utm_campaign=wporg' );
! defined( 'WPCED_REVIEWS' ) && define( 'WPCED_REVIEWS', 'https://wordpress.org/support/plugin/wpc-estimated-delivery-date/reviews/' );
! defined( 'WPCED_CHANGELOG' ) && define( 'WPCED_CHANGELOG', 'https://wordpress.org/plugins/wpc-estimated-delivery-date/#developers' );
! defined( 'WPCED_DISCUSSION' ) && define( 'WPCED_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-estimated-delivery-date' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCED_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpced_init' ) ) {
    add_action( 'plugins_loaded', 'wpced_init', 11 );

    function wpced_init() {
        if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
            add_action( 'admin_notices', 'wpced_notice_wc' );

            return null;
        }

        if ( ! class_exists( 'WPCleverWpced' ) && class_exists( 'WC_Product' ) ) {
            class WPCleverWpced {
                public function __construct() {
                    require_once trailingslashit( WPCED_DIR ) . 'includes/class-helper.php';
                    require_once trailingslashit( WPCED_DIR ) . 'includes/class-backend.php';
                    require_once trailingslashit( WPCED_DIR ) . 'includes/class-frontend.php';
                }
            }

            new WPCleverWpced();
        }
    }
}

if ( ! function_exists( 'wpced_notice_wc' ) ) {
    function wpced_notice_wc() {
        ?>
        <div class="error">
            <p><strong>WPC Estimated Delivery Date</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
        <?php
    }
}
