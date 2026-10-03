<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcsm_Backend' ) ) {
    class Wpcsm_Backend {
        protected static $instance = null;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        function __construct() {
            add_action( 'init', [ $this, 'init' ] );
        }

        function get_locations() {
            return apply_filters( 'wpcsm_locations', [
                    'Custom'          => [
                            'custom' => [
                                    'hook'     => 'custom',
                                    'priority' => 10,
                                    'name'     => 'Custom',
                            ],
                            'none'   => [
                                    'hook'     => 'none',
                                    'priority' => 10,
                                    'name'     => 'None',
                            ],
                    ],
                    'General'         => [
                            'store_notice_info'    => [
                                    'hook'     => 'wp',
                                    'priority' => 20,
                                    'name'     => 'Info notice',
                            ],
                            'store_notice_error'   => [
                                    'hook'     => 'wp',
                                    'priority' => 20,
                                    'name'     => 'Error notice',
                            ],
                            'store_notice_success' => [
                                    'hook'     => 'wp',
                                    'priority' => 20,
                                    'name'     => 'Success notice',
                            ],
                            'main_content_before'  => [
                                    'hook'     => 'woocommerce_before_main_content',
                                    'priority' => 10,
                                    'name'     => 'Before main content',
                            ],
                            'main_content_after'   => [
                                    'hook'     => 'woocommerce_after_main_content',
                                    'priority' => 10,
                                    'name'     => 'After main content',
                            ],
                    ],
                    'Product Archive' => [
                            'shop_loop_item_before'       => [
                                    'hook'     => 'woocommerce_before_shop_loop_item',
                                    'priority' => 10,
                                    'name'     => 'Before product',
                            ],
                            'shop_loop_item_after'        => [
                                    'hook'     => 'woocommerce_after_shop_loop_item',
                                    'priority' => 25,
                                    'name'     => 'After product',
                            ],
                            'shop_loop_item_title_before' => [
                                    'hook'     => 'woocommerce_shop_loop_item_title',
                                    'priority' => 9,
                                    'name'     => 'Before product title',
                            ],
                            'shop_loop_item_title_after'  => [
                                    'hook'     => 'woocommerce_shop_loop_item_title',
                                    'priority' => 11,
                                    'name'     => 'After product title',
                            ],
                            'shop_loop_item_price_before' => [
                                    'hook'     => 'woocommerce_after_shop_loop_item_title',
                                    'priority' => 9,
                                    'name'     => 'Before product price',
                            ],
                            'shop_loop_item_price_after'  => [
                                    'hook'     => 'woocommerce_after_shop_loop_item_title',
                                    'priority' => 11,
                                    'name'     => 'After product price',
                            ],
                    ],
                    'Product Single'  => [
                            'single_product_before'             => [
                                    'hook'     => 'woocommerce_before_single_product',
                                    'priority' => 11,
                                    'name'     => 'Before product',
                            ],
                            'single_product_after'              => [
                                    'hook'     => 'woocommerce_after_single_product',
                                    'priority' => 11,
                                    'name'     => 'After product',
                            ],
                            'single_product_summary_before'     => [
                                    'hook'     => 'woocommerce_before_single_product_summary',
                                    'priority' => 9,
                                    'name'     => 'Before product summary',
                            ],
                            'single_product_summary_after'      => [
                                    'hook'     => 'woocommerce_after_single_product_summary',
                                    'priority' => 21,
                                    'name'     => 'After product summary',
                            ],
                            'single_product_title_before'       => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 4,
                                    'name'     => 'Before product title',
                            ],
                            'single_product_title_after'        => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 6,
                                    'name'     => 'After product title',
                            ],
                            'single_product_price_before'       => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 9,
                                    'name'     => 'Before product price',
                            ],
                            'single_product_price_after'        => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 11,
                                    'name'     => 'After product price',
                            ],
                            'single_product_excerpt_before'     => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 19,
                                    'name'     => 'Before product excerpt',
                            ],
                            'single_product_excerpt_after'      => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 21,
                                    'name'     => 'After product excerpt',
                            ],
                            'single_product_add_to_cart_before' => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 29,
                                    'name'     => 'Before product add to cart',
                            ],
                            'single_product_add_to_cart_after'  => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 31,
                                    'name'     => 'After product add to cart',
                            ],
                            'single_product_meta_before'        => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 39,
                                    'name'     => 'Before product meta',
                            ],
                            'single_product_meta_after'         => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 41,
                                    'name'     => 'After product meta',
                            ],
                            'single_product_sharing_before'     => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 49,
                                    'name'     => 'Before product sharing',
                            ],
                            'single_product_sharing_after'      => [
                                    'hook'     => 'woocommerce_single_product_summary',
                                    'priority' => 51,
                                    'name'     => 'After product sharing',
                            ],
                            'single_product_tabs_before'        => [
                                    'hook'     => 'woocommerce_after_single_product_summary',
                                    'priority' => 9,
                                    'name'     => 'Before product tabs',
                            ],
                            'single_product_tabs_after'         => [
                                    'hook'     => 'woocommerce_after_single_product_summary',
                                    'priority' => 11,
                                    'name'     => 'After product tabs',
                            ],
                            'single_product_related_before'     => [
                                    'hook'     => 'woocommerce_after_single_product_summary',
                                    'priority' => 19,
                                    'name'     => 'Before related products',
                            ],
                            'single_product_related_after'      => [
                                    'hook'     => 'woocommerce_after_single_product_summary',
                                    'priority' => 21,
                                    'name'     => 'After related products',
                            ],
                    ],
                    'Cart'            => [
                            'cart_before'             => [
                                    'hook'     => 'woocommerce_before_cart',
                                    'priority' => 10,
                                    'name'     => 'Before cart',
                            ],
                            'cart_after'              => [
                                    'hook'     => 'woocommerce_after_cart',
                                    'priority' => 10,
                                    'name'     => 'After cart',
                            ],
                            'cart_table_before'       => [
                                    'hook'     => 'woocommerce_before_cart_table',
                                    'priority' => 10,
                                    'name'     => 'Before cart table',
                            ],
                            'cart_table_after'        => [
                                    'hook'     => 'woocommerce_after_cart_table',
                                    'priority' => 10,
                                    'name'     => 'After cart table',
                            ],
                            'cart_totals_before'      => [
                                    'hook'     => 'woocommerce_before_cart_totals',
                                    'priority' => 10,
                                    'name'     => 'Before cart totals',
                            ],
                            'cart_totals_after'       => [
                                    'hook'     => 'woocommerce_after_cart_totals',
                                    'priority' => 10,
                                    'name'     => 'After cart totals',
                            ],
                            'cart_before_collaterals' => [
                                    'hook'     => 'woocommerce_before_cart_collaterals',
                                    'priority' => 10,
                                    'name'     => 'Before cart collaterals',
                            ],
                            'cart_after_item_name'    => [
                                    'hook'     => 'woocommerce_after_cart_item_name',
                                    'priority' => 10,
                                    'name'     => 'After cart item name',
                            ],
                    ],
                    'Checkout'        => [
                            'checkout_form_before'             => [
                                    'hook'     => 'woocommerce_before_checkout_form',
                                    'priority' => 10,
                                    'name'     => 'Before checkout',
                            ],
                            'checkout_form_after'              => [
                                    'hook'     => 'woocommerce_after_checkout_form',
                                    'priority' => 10,
                                    'name'     => 'After checkout',
                            ],
                            'checkout_customer_details_before' => [
                                    'hook'     => 'woocommerce_checkout_before_customer_details',
                                    'priority' => 10,
                                    'name'     => 'Before customer details',
                            ],
                            'checkout_customer_details_after'  => [
                                    'hook'     => 'woocommerce_checkout_after_customer_details',
                                    'priority' => 10,
                                    'name'     => 'After customer details',
                            ],
                            'checkout_billing_before'          => [
                                    'hook'     => 'woocommerce_checkout_billing',
                                    'priority' => 10,
                                    'name'     => 'Before billing address',
                            ],
                            'checkout_shipping_before'         => [
                                    'hook'     => 'woocommerce_checkout_shipping',
                                    'priority' => 10,
                                    'name'     => 'Before shipping address',
                            ],
                    ],
            ] );
        }

        function init() {
            $labels = [
                    'name'          => _x( 'Smart Messages', 'Post Type General Name', 'wpc-smart-messages' ),
                    'singular_name' => _x( 'Smart Message', 'Post Type Singular Name', 'wpc-smart-messages' ),
                    'add_new_item'  => esc_html__( 'Add New Smart Message', 'wpc-smart-messages' ),
                    'add_new'       => esc_html__( 'Add New', 'wpc-smart-messages' ),
                    'edit_item'     => esc_html__( 'Edit Smart Message', 'wpc-smart-messages' ),
                    'update_item'   => esc_html__( 'Update Smart Message', 'wpc-smart-messages' ),
                    'search_items'  => esc_html__( 'Search Smart Message', 'wpc-smart-messages' ),
            ];

            $args = [
                    'label'               => esc_html__( 'Smart Messages', 'wpc-smart-messages' ),
                    'labels'              => $labels,
                    'supports'            => [ 'title', 'editor' ],
                    'hierarchical'        => false,
                    'public'              => false,
                    'show_ui'             => true,
                    'show_in_menu'        => true,
                    'show_in_nav_menus'   => true,
                    'show_in_admin_bar'   => true,
                    'menu_position'       => 28,
                    'menu_icon'           => 'dashicons-megaphone',
                    'can_export'          => true,
                    'has_archive'         => false,
                    'exclude_from_search' => true,
                    'publicly_queryable'  => false,
                    'capability_type'     => 'post',
                    'show_in_rest'        => false,
            ];

            register_post_type( 'wpc_smart_message', $args );
        }

    }

    Wpcsm_Backend::instance();
}
