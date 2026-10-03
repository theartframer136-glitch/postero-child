<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpced_Backend' ) ) {
    class Wpced_Backend {
        protected static $instance = null;
        protected static $settings = [];
        protected static $rules = [];
        protected static $zones = [];
        protected static $methods = [];
        protected static $archive_pos = [];
        protected static $single_pos = [];
        protected static $base_rule = [
                'name'          => '',
                'apply'         => 'all',
                'apply_compare' => 'equal',
                'apply_number'  => '0',
                'apply_val'     => [],
                'zone'          => 'all',
                'method'        => 'all',
                'min'           => '5',
                'max'           => '10',
                'scheduled'     => ''
        ];

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function __construct() {
            self::$settings = (array) get_option( 'wpced_settings', [] );
            self::$rules    = (array) get_option( 'wpced_rules', [] );

            // Init
            add_action( 'init', [ $this, 'init' ] );

            // Enqueue
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

            // Settings
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );

            // Links
            add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
            add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

            // Single Product
            add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
            add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
            add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

            // Variation
            add_action( 'woocommerce_product_after_variable_attributes', [
                    $this,
                    'variation_settings'
            ], 99, 3 );
            add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings' ], 99, 2 );

            // WPC Variation Duplicator
            add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

            // WPC Variation Bulk Editor
            add_action( 'wpcvb_bulk_update_variation', [ $this, 'bulk_update_variation' ], 99, 2 );

            // Export
            add_filter( 'woocommerce_product_export_meta_value', [ $this, 'export_process' ], 10, 3 );

            // Import
            add_filter( 'woocommerce_product_import_pre_insert_product_object', [
                    $this,
                    'import_process'
            ], 10, 2 );

            // AJAX
            add_action( 'wp_ajax_wpced_add_rule', [ $this, 'ajax_add_rule' ] );
            add_action( 'wp_ajax_wpced_add_date', [ $this, 'ajax_add_date' ] );
            add_action( 'wp_ajax_wpced_search_term', [ $this, 'ajax_search_term' ] );
            add_action( 'wp_ajax_wpced_date_format_preview', [ $this, 'ajax_date_format_preview' ] );

            // Backend Order
            add_action( 'woocommerce_order_item_add_action_buttons', [ $this, 'add_action_buttons' ] );
            add_action( 'wp_ajax_wpced_get_order_dates', [ $this, 'ajax_get_order_dates' ] );
            add_action( 'wp_ajax_wpced_save_order_dates', [ $this, 'ajax_save_order_dates' ] );
        }

        function init() {
            // load text-domain
            load_plugin_textdomain( 'wpc-estimated-delivery-date', false, basename( WPCED_DIR ) . '/languages/' );

            self::$archive_pos = apply_filters( 'wpced_archive_positions', [
                    'under_title'       => esc_html__( 'Under title', 'wpc-estimated-delivery-date' ),
                    'under_rating'      => esc_html__( 'Under rating', 'wpc-estimated-delivery-date' ),
                    'under_price'       => esc_html__( 'Under price', 'wpc-estimated-delivery-date' ),
                    'above_add_to_cart' => esc_html__( 'Above add to cart', 'wpc-estimated-delivery-date' ),
                    'under_add_to_cart' => esc_html__( 'Under add to cart', 'wpc-estimated-delivery-date' ),
                    'none'              => esc_html__( 'None (hide it)', 'wpc-estimated-delivery-date' ),
            ] );
            self::$single_pos  = apply_filters( 'wpced_single_positions', [
                    '6'  => esc_html__( 'Under title', 'wpc-estimated-delivery-date' ),
                    '11' => esc_html__( 'Under price & rating', 'wpc-estimated-delivery-date' ),
                    '21' => esc_html__( 'Under excerpt', 'wpc-estimated-delivery-date' ),
                    '31' => esc_html__( 'Under add to cart', 'wpc-estimated-delivery-date' ),
                    '41' => esc_html__( 'Under meta', 'wpc-estimated-delivery-date' ),
                    '51' => esc_html__( 'Under sharing', 'wpc-estimated-delivery-date' ),
                    '0'  => esc_html__( 'None (hide it)', 'wpc-estimated-delivery-date' ),
            ] );
        }

        public static function get_settings() {
            return apply_filters( 'wpced_get_settings', self::$settings );
        }

        public static function get_setting( $name, $default = false ) {
            if ( isset( self::$settings[ $name ] ) && ( self::$settings[ $name ] !== '' ) ) {
                $setting = self::$settings[ $name ];
            } else {
                $setting = get_option( 'wpced_' . $name, $default );
            }

            return apply_filters( 'wpced_get_setting', $setting, $name, $default );
        }

        public static function get_rules() {
            return apply_filters( 'wpced_get_rules', self::$rules );
        }

        public static function get_archive_positions() {
            return self::$archive_pos;
        }

        public static function get_single_positions() {
            return self::$single_pos;
        }

        public function product_data_tabs( $tabs ) {
            $tabs['wpced'] = [
                    'label'  => esc_html__( 'Estimated Delivery Date', 'wpc-estimated-delivery-date' ),
                    'target' => 'wpced_settings'
            ];

            return $tabs;
        }

        public function product_data_panels() {
            global $post, $thepostid, $product_object;

            if ( $product_object instanceof WC_Product ) {
                $product_id = $product_object->get_id();
            } elseif ( is_numeric( $thepostid ) ) {
                $product_id = $thepostid;
            } elseif ( $post instanceof WP_Post ) {
                $product_id = $post->ID;
            } else {
                $product_id = 0;
            }

            if ( ! $product_id ) {
                ?>
                <div id="wpced_settings" class="wpced-product-settings panel woocommerce_options_panel">
                    <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-estimated-delivery-date' ); ?></p>
                </div>
                <?php
                return;
            }

            echo '<div id="wpced_settings" class="wpced-product-settings panel woocommerce_options_panel">';
            self::settings_form( $product_id );
            echo '</div>';
        }

        function variation_settings( $loop, $variation_data, $variation ) {
            $variation_id = absint( $variation->ID );
            ?>
            <div class="form-row form-row-full wpced-variation-settings">
                <label><?php esc_html_e( 'WPC Estimated Delivery Date', 'wpc-estimated-delivery-date' ); ?></label>
                <div class="wpced-variation-wrap wpced-variation-wrap-<?php echo esc_attr( $variation_id ); ?>">
                    <?php self::settings_form( $variation_id, true ); ?>
                </div>
            </div>
            <?php
        }

        function settings_form( $product_id, $is_variation = false ) {
            $enable = get_post_meta( $product_id, 'wpced_enable', true ) ?: 'global';
            $rules  = get_post_meta( $product_id, 'wpced_rules', true ) ?: [];
            ?>
            <div class='wpced-settings'>
                <div class="wpced-select-wrapper">
                    <label>
                        <span><?php esc_html_e( 'Estimated Delivery Date', 'wpc-estimated-delivery-date' ); ?></span>
                        <select name="<?php echo esc_attr( $is_variation ? 'wpced_enable_v[' . $product_id . ']' : 'wpced_enable' ); ?>"
                                class="wpced-select-enable">
                            <option value="global" <?php selected( $enable, 'global' ); ?>><?php esc_html_e( 'Global', 'wpc-estimated-delivery-date' ); ?></option>
                            <?php if ( $is_variation ) { ?>
                                <option value="parent" <?php selected( $enable, 'parent' ); ?>><?php esc_html_e( 'Parent', 'wpc-estimated-delivery-date' ); ?></option>
                            <?php } ?>
                            <option value="disable" <?php selected( $enable, 'disable' ); ?>><?php esc_html_e( 'Disable', 'wpc-estimated-delivery-date' ); ?></option>
                            <option value="override" <?php selected( $enable, 'override' ); ?>><?php esc_html_e( 'Override', 'wpc-estimated-delivery-date' ); ?></option>
                        </select> </label>
                </div>
                <div class="wpced-single-product"
                     style="display: <?php echo esc_attr( $enable === 'override' ? 'block' : 'none' ); ?>;">
                    <div class="wpced-items-wrapper">
                        <div class="wpced-items">
                            <?php
                            if ( ! isset( $rules['default'] ) ) {
                                $key  = 'default';
                                $rule = [];
                            } else {
                                $key  = 'default';
                                $rule = $rules['default'];
                            }

                            include WPCED_DIR . 'includes/templates/rule.php';
                            ?>
                        </div>
                        <div class="wpced-items wpced-rules">
                            <?php
                            if ( isset( $rules['default'] ) ) {
                                unset( $rules['default'] );
                            }

                            foreach ( $rules as $key => $rule ) {
                                include WPCED_DIR . 'includes/templates/rule.php';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="wpced-items-new">
                        <input type="button" class="button wpced-item-new"
                               data-product_id="<?php echo esc_attr( $product_id ); ?>"
                               data-is_variation="<?php echo esc_attr( $is_variation ? 'true' : 'false' ); ?>"
                               value="<?php esc_attr_e( '+ Add rule', 'wpc-estimated-delivery-date' ); ?>">
                    </div>
                </div>
            </div>
            <?php
        }

        public function process_product_meta( $post_id ) {
            if ( isset( $_POST['wpced_enable'] ) ) {
                update_post_meta( $post_id, 'wpced_enable', sanitize_text_field( $_POST['wpced_enable'] ) );
            }

            if ( isset( $_POST['wpced_rules'] ) ) {
                update_post_meta( $post_id, 'wpced_rules', Wpced_Helper()->sanitize_array( $_POST['wpced_rules'] ) );
            } else {
                delete_post_meta( $post_id, 'wpced_rules' );
            }
        }

        function save_variation_settings( $post_id ) {
            if ( isset( $_POST['wpced_enable_v'][ $post_id ] ) ) {
                update_post_meta( $post_id, 'wpced_enable', sanitize_text_field( $_POST['wpced_enable_v'][ $post_id ] ) );
            } else {
                delete_post_meta( $post_id, 'wpced_enable' );
            }

            if ( isset( $_POST['wpced_rules_v'][ $post_id ] ) ) {
                update_post_meta( $post_id, 'wpced_rules', Wpced_Helper()->sanitize_array( $_POST['wpced_rules_v'][ $post_id ] ) );
            } else {
                delete_post_meta( $post_id, 'wpced_rules' );
            }
        }

        function duplicate_variation( $old_variation_id, $new_variation_id ) {
            if ( $enable = get_post_meta( $old_variation_id, 'wpced_enable', true ) ) {
                update_post_meta( $new_variation_id, 'wpced_enable', $enable );
            }

            if ( $rules = get_post_meta( $old_variation_id, 'wpced_rules', true ) ) {
                update_post_meta( $new_variation_id, 'wpced_rules', $rules );
            }
        }

        function bulk_update_variation( $variation_id, $fields ) {
            if ( ! empty( $fields['wpced_enable_v'] ) && ( $fields['wpced_enable_v'] !== 'wpcvb_no_change' ) ) {
                update_post_meta( $variation_id, 'wpced_enable', sanitize_text_field( $fields['wpced_enable_v'] ) );
            }

            if ( ! empty( $fields['wpced_enable_v'] ) && ( $fields['wpced_enable_v'] === 'override' ) && ! empty( $fields['wpced_rules_v'] ) ) {
                update_post_meta( $variation_id, 'wpced_rules', Wpced_Helper()->sanitize_array( $fields['wpced_rules_v'] ) );
            }
        }

        function export_process( $value, $meta, $product ) {
            if ( $meta->key === 'wpced_rules' ) {
                $ids = get_post_meta( $product->get_id(), 'wpced_rules', true );

                if ( ! empty( $ids ) && is_array( $ids ) ) {
                    return json_encode( $ids );
                }
            }

            return $value;
        }

        function import_process( $object, $data ) {
            if ( isset( $data['meta_data'] ) ) {
                foreach ( $data['meta_data'] as $meta ) {
                    if ( $meta['key'] === 'wpced_rules' ) {
                        $object->update_meta_data( 'wpced_rules', json_decode( $meta['value'], true ) );
                        break;
                    }
                }
            }

            return $object;
        }

        function register_settings() {
            // settings
            register_setting( 'wpced_settings', 'wpced_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'Wpced_Helper', 'sanitize_array' ],
            ] );
            register_setting( 'wpced_settings', 'wpced_rules', [
                    'type'              => 'array',
                    'sanitize_callback' => [ 'Wpced_Helper', 'sanitize_array' ],
            ] );
        }

        public function admin_menu() {
            add_submenu_page( 'wpclever', 'WPC Estimated Delivery Date', 'Estimated Delivery Date', 'manage_options', 'wpclever-wpced', [
                    $this,
                    'admin_menu_content'
            ] );
        }

        public function admin_menu_content() {
            include WPCED_DIR . 'includes/templates/settings.php';
        }

        function action_links( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WPCED_FILE );
            }

            if ( $plugin === $file ) {
                $settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpced&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-estimated-delivery-date' ) . '</a>';
                $links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpced&tab=premium' ) ) . '" style="color: #c9356e">' . esc_html__( 'Premium Version', 'wpc-estimated-delivery-date' ) . '</a>';
                array_unshift( $links, $settings );
            }

            return (array) $links;
        }

        function row_meta( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WPCED_FILE );
            }

            if ( $plugin === $file ) {
                $row_meta = [
                        'support' => '<a href="' . esc_url( WPCED_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-estimated-delivery-date' ) . '</a>',
                ];

                return array_merge( $links, $row_meta );
            }

            return (array) $links;
        }

        public function ajax_add_rule() {
            $key          = Wpced_Helper()->generate_key();
            $product_id   = absint( sanitize_text_field( $_POST['product_id'] ?? 0 ) );
            $is_variation = wc_string_to_bool( sanitize_text_field( $_POST['is_variation'] ?? 'no' ) );
            $rule_name    = $is_variation ? 'wpced_rules_v' : 'wpced_rules';
            $rule_data    = $_POST['rule_data'] ?? '';
            $rule_arr     = [];

            if ( ! empty( $rule_data ) ) {
                $form_rule = [];
                parse_str( $rule_data, $form_rule );

                if ( isset( $form_rule[ $rule_name ] ) && is_array( $form_rule[ $rule_name ] ) ) {
                    $rule_arr = reset( $form_rule[ $rule_name ] );

                    if ( $is_variation && is_array( $rule_arr ) ) {
                        $rule_arr = reset( $rule_arr );
                    }
                }
            }

            if ( ! empty( $key ) ) {
                $active = true;
                $rule   = array_merge( self::$base_rule, $rule_arr );
                include WPCED_DIR . 'includes/templates/rule.php';
            }

            wp_die();
        }

        public function ajax_add_date() {
            $date = [];
            include WPCED_DIR . 'includes/templates/date.php';

            wp_die();
        }

        public function enqueue_scripts() {
            // hint
            wp_enqueue_style( 'hint', WPCED_URI . 'assets/css/hint.css' );

            // wpcdpk
            wp_enqueue_style( 'wpcdpk', WPCED_URI . 'assets/libs/wpcdpk/css/datepicker.css' );
            wp_enqueue_script( 'wpcdpk', WPCED_URI . 'assets/libs/wpcdpk/js/datepicker.js', [ 'jquery' ], WPCED_VERSION, true );

            wp_enqueue_style( 'wpced-backend', WPCED_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCED_VERSION );
            wp_enqueue_script( 'wpced-backend', WPCED_URI . 'assets/js/backend.js', [
                    'jquery',
                    'jquery-ui-sortable',
                    'wc-enhanced-select',
                    'jquery-ui-dialog',
                    'selectWoo'
            ], WPCED_VERSION, true );
            wp_localize_script( 'wpced-backend', 'wpced_vars', [
                    'nonce' => wp_create_nonce( 'wpced-security' )
            ] );
        }

        function ajax_search_term() {
            $return = [];

            $args = [
                    'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
                    'orderby'    => 'id',
                    'order'      => 'ASC',
                    'hide_empty' => false,
                    'fields'     => 'all',
                    'name__like' => sanitize_text_field( $_REQUEST['q'] ),
            ];

            $terms = get_terms( $args );

            if ( count( $terms ) ) {
                foreach ( $terms as $term ) {
                    $return[] = [ $term->slug, $term->name ];
                }
            }

            wp_send_json( $return );
        }

        function ajax_date_format_preview() {
            echo sprintf( esc_html__( 'Preview: %s', 'wpc-estimated-delivery-date' ), current_time( sanitize_text_field( $_POST['date_format'] ?? '' ) ) );
            wp_die();
        }

        function ajax_get_order_dates() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpced-security' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                die( esc_html__( 'Permissions check failed!', 'wpc-estimated-delivery-date' ) );
            }

            $order_id = absint( $_POST['order_id'] ?? 0 );

            if ( $order_id && ( $order = wc_get_order( $order_id ) ) ) {
                echo '<ul class="wpced-order-items" data-id="' . esc_attr( $order_id ) . '">';

                $order_items = $order->get_items( 'line_item' );

                foreach ( $order_items as $order_item ) {
                    echo '<li class="wpced-order-item" data-id="' . esc_attr( $order_item->get_id() ) . '">';
                    echo '<div class="wpced-order-item-name">' . $order_item->get_name() . '</div>';
                    echo '<div class="wpced-order-item-date"><input type="text" data-id="' . esc_attr( $order_item->get_id() ) . '" class="text large-text wpced-order-item-date-val" value="' . esc_attr( wp_strip_all_tags( $order_item->get_meta( '_wpced_date' ) ) ) . '"/></div>';
                    echo '</li>';
                }

                echo '</ul>';
                echo '<div class="wpced-order-items-save"><button class="button button-primary wpced-order-items-save-btn">' . esc_html__( 'Save', 'wpc-estimated-delivery-date' ) . '</button></div>';
            }

            wp_die();
        }

        function ajax_save_order_dates() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpced-security' ) || ! current_user_can( 'manage_woocommerce' ) ) {
                die( esc_html__( 'Permissions check failed!', 'wpc-estimated-delivery-date' ) );
            }

            $order_id    = absint( $_POST['order_id'] ?? 0 );
            $order_dates = Wpced_Helper()->sanitize_array( $_POST['order_dates'] ?? [] );

            if ( ! empty( $order_dates ) ) {
                foreach ( $order_dates as $order_date ) {
                    $order_date      = array_merge( [ 'id' => 0, 'date' => '' ], $order_date );
                    $order_item_date = apply_filters( 'wpced_update_order_item_date', '<div class="wpced"><div class="wpced-inner">' . wp_strip_all_tags( $order_date['date'] ) . '</div></div>' );
                    wc_update_order_item_meta( $order_date['id'], '_wpced_date', $order_item_date );
                }
            }

            echo $order_id;

            wp_die();
        }

        function add_action_buttons() {
            echo '<button type="button" class="button wpced-update-dates">' . esc_html__( 'Update delivery dates', 'wpc-estimated-delivery-date' ) . '</button>';
            echo '<div class="wpced-update-dates-dialog" id="wpced_update_dates_dialog" style="display: none" title="' . esc_attr__( 'Update delivery dates', 'wpc-estimated-delivery-date' ) . '"></div>';
        }

        public static function get_base_rule() {
            return self::$base_rule;
        }

        public static function get_zones() {
            $zones            = WC_Shipping_Zones::get_zones();
            self::$zones      = self::get_zones_array( $zones );
            $non_covered_zone = WC_Shipping_Zones::get_zone_by( "zone_id", 0 );

            if ( is_object( $non_covered_zone ) ) {
                $non_covered_zone_name = $non_covered_zone->get_zone_name();
                $non_covered_zone_id   = $non_covered_zone->get_id();

                if ( ! empty( $non_covered_zone_name ) ) {
                    self::$zones[ $non_covered_zone_id ] = $non_covered_zone_name;
                }
            }

            return self::$zones;
        }

        public static function get_zones_array( $zones ) {
            $zs = [];

            foreach ( $zones as $zone ) {
                $zone_obj = new WC_Shipping_Zone( $zone['zone_id'] );
                $methods  = $zone_obj->get_shipping_methods( true );

                if ( count( $methods ) > 0 ) {
                    $zs[ $zone['zone_id'] ] = $zone['zone_name'];
                }
            }

            return $zs;
        }

        public static function get_methods() {
            self::$methods = [];
            $zones         = self::get_zones();

            foreach ( $zones as $zone_id => $zone ) {
                $methods = self::get_zone_methods( $zone_id );

                foreach ( $methods as $method ) {
                    self::$methods[ $method->instance_id ] = [
                            'zone'  => $zone_id,
                            'name'  => $method->id,
                            'title' => $method->title
                    ];
                }
            }

            return self::$methods;
        }

        public static function get_zone_methods( $zone_id ) {
            $zone_obj = new WC_Shipping_Zone( $zone_id );
            $methods  = $zone_obj->get_shipping_methods( true );

            return $methods;
        }
    }

    function Wpced_Backend() {
        return Wpced_Backend::instance();
    }

    Wpced_Backend();
}
