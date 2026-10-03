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
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

            // meta boxes
            add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
            add_action( 'save_post_wpc_smart_message', [ $this, 'save_post' ] );

            // ajax
            add_action( 'wp_ajax_wpcsm_get_condition_value', [ $this, 'ajax_get_condition_value' ] );
            add_action( 'wp_ajax_wpcsm_add_condition', [ $this, 'ajax_add_condition' ] );
            add_action( 'wp_ajax_wpcsm_activate', [ $this, 'ajax_enable' ] );
            add_action( 'wp_ajax_wpcsm_search_term', [ $this, 'ajax_search_term' ] );

            // columns
            add_filter( 'manage_edit-wpc_smart_message_columns', [ $this, 'message_columns' ] );
            add_action( 'manage_wpc_smart_message_posts_custom_column', [ $this, 'message_columns_content' ], 10, 2 );
        }

        function add_meta_box() {
            add_meta_box( 'wpcsm_metabox_location', esc_html__( 'Location', 'wpc-smart-messages' ), [
                    $this,
                    'metabox_location'
            ], 'wpc_smart_message', 'advanced', 'low' );

            add_meta_box( 'wpcsm_metabox_conditions', esc_html__( 'Conditions', 'wpc-smart-messages' ), [
                    $this,
                    'metabox_conditions'
            ], 'wpc_smart_message', 'advanced', 'low' );

            add_meta_box( 'wpcsm_metabox_design', esc_html__( 'Design', 'wpc-smart-messages' ), [
                    $this,
                    'metabox_design'
            ], 'wpc_smart_message', 'advanced', 'low' );

            add_meta_box( 'wpcsm_metabox_message', esc_html__( 'Message', 'wpc-smart-messages' ), [
                    $this,
                    'metabox_message'
            ], 'wpc_smart_message', 'advanced', 'low' );
        }

        function metabox_location( $post ) {
            $locations        = self::get_locations();
            $location         = get_post_meta( $post->ID, 'wpcsm_location', true ) ?: 'store_notice_info';
            $custom_location  = get_post_meta( $post->ID, 'wpcsm_custom_location', true ) ?: '';
            $matched_location = false;
            ?>
            <div class="wpcsm-settings-option">
                <select name="wpcsm_location" id="wpcsm_location">
                    <?php
                    foreach ( $locations as $group => $hooks ) {
                        echo '<optgroup label="' . esc_attr( $group ) . '">';

                        foreach ( $hooks as $hook => $label ) {
                            echo '<option value="' . esc_attr( $hook ) . '" ' . selected( $hook, $location, false ) . '>' . esc_html( is_array( $label ) && isset( $label['name'] ) ? $label['name'] : $label ) . '</option>';

                            if ( $hook == $location ) {
                                $matched_location = true;
                            }
                        }

                        echo '</optgroup>';
                    }

                    if ( ! $matched_location ) {
                        echo '<optgroup label="' . esc_html__( 'Undefined', 'wpc-smart-messages' ) . '"></optgroup>';
                        echo '<option value="' . esc_attr( $location ) . '" selected>' . esc_html( $location ) . '</option>';
                    }
                    ?>
                </select>
                <span class="wpcsm-custom-location <?php echo esc_attr( $location !== 'custom' ? 'hidden' : '' ) ?>">
                <span class="hint--top"
                      aria-label="You can set any action hook where you want to display the message. (Optional) Set a custom priority by adding a colon followed by a integer, e.g. &#039;:10&#039;"><input
                            type="text" class="regular-text" name="wpcsm_custom_location"
                            value="<?php echo esc_attr( $custom_location ); ?>"/></span> <span><a
                                href="https://wpclever.net/faqs/using-the-custom-location/" target="_blank">How to use the ‘Custom’ location?</a></span>
            </span>
            </div>
            <?php
            echo '<div class="wpcsm-shortcode">';
            echo '<div class="wpcsm-shortcode-des">' . esc_html__( 'You also can place below shortcode where you want to show this message. It still needs to meet the conditions to be displayed.', 'wpc-smart-messages' ) . '</div>';
            echo '<div class="wpcsm-shortcode-txt"><input type="text" class="wpcsm-shortcode-input" data-id="' . esc_attr( $post->ID ) . '" readonly value="[wpc_smart_message id=&quot;' . esc_attr( $post->ID ) . '&quot; name=&quot;' . esc_attr( $post->post_title ) . '&quot;]"/></div>';
            echo '</div>';
        }

        function metabox_conditions( $post ) {
            $conditions = get_post_meta( $post->ID, 'wpcsm_conditions', true );
            ?>
            <div class="wpcsm-conditions-note">
                <?php esc_html_e( 'Current time', 'wpc-smart-messages' ); ?>
                <code><?php echo esc_html( current_time( 'l' ) ); ?></code>
                <code><?php echo esc_html( current_time( 'm/d/Y' ) ); ?></code>
                <code><?php echo esc_html( current_time( 'h:i a' ) ); ?></code>
                <code><?php echo esc_html__( 'Week No.', 'wpc-smart-messages' ) . ' ' . esc_html( current_time( 'W' ) ); ?></code>
                <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"
                   target="_blank"><?php esc_html_e( 'Date/time settings', 'wpc-smart-messages' ); ?></a>
            </div>
            <div class="wpcsm-conditions-note" style="padding: 12px; margin: 12px 0; background-color: rgba(0,0,0,.03)">
                <?php esc_html_e( 'The logic when configuring conditions should be precise, meaningful & relevant for the message to be displayed on the chosen locations. Some conditions are satisfied in certain locations only. Irrelevant conditions will be skipped. For example, if you choose the condition “Product categories” & “Single product page” for the location, the message will be visible for all products under the chosen categories.', 'wpc-smart-messages' ); ?>
            </div>
            <div class="wpcsm-conditions">
                <?php if ( is_array( $conditions ) && count( $conditions ) > 0 ) {
                    $index = 0;

                    foreach ( $conditions as $condition ) { ?>
                        <div class="input-panel" data-key="<?php echo esc_attr( $index ) ?>">
                            <div class="input-type">
                                <?php self::get_condition_type( $index, $condition ); ?>
                            </div>
                            <div class="input-value">
                                <?php self::get_condition_value( $index, $condition ); ?>
                            </div>
                            <span class="wpcsm-remove-condition hint--left"
                                  aria-label="<?php esc_attr_e( 'Remove', 'wpc-smart-messages' ); ?>">×</span>
                        </div>
                        <?php
                        $index ++;
                    }
                } else { ?>
                    <div class="input-panel" data-key="0">
                        <div class="input-type">
                            <?php self::get_condition_type(); ?>
                        </div>
                        <div class="input-value">
                            <?php self::get_condition_value(); ?>
                        </div>
                        <span class="wpcsm-remove-condition hint--left"
                              aria-label="<?php esc_attr_e( 'Remove', 'wpc-smart-messages' ); ?>">×</span>
                    </div>
                <?php } ?>
            </div>
            <button class="button wpcsm-add-condition" type="button">
                <?php esc_html_e( '+ Add Condition', 'wpc-smart-messages' ); ?>
            </button>
            <?php
        }

        function metabox_message( $post ) {
            ?>
            <div class="wpcsm-settings-note">
                <?php esc_html_e( 'You can use the shortcode within the HTML text. Build-in shortcodes:', 'wpc-smart-messages' ); ?>
                <ul>
                    <?php
                    foreach ( Wpcsm_Shortcode::instance()->get_shortcodes() as $k => $d ) {
                        echo '<li><code>[' . esc_attr( $k ) . ']</code> - <i>' . esc_html( $d ) . '</i></li>';
                    }
                    ?>
                </ul>
            </div>
            <?php
        }

        function metabox_design( $post ) {
            $container     = get_post_meta( $post->ID, 'wpcsm_container', true ) ?: 'div';
            $extra_classes = get_post_meta( $post->ID, 'wpcsm_extra_classes', true ) ?: '';
            $design        = get_post_meta( $post->ID, 'wpcsm_design', true ) ?: 'no';
            $custom_css    = get_post_meta( $post->ID, 'wpcsm_custom_css', true ) ?: '';
            $css           = (array) get_post_meta( $post->ID, 'wpcsm_css', true ) ?: [];
            $css           = array_merge( [
                    'background' => [ 'color' => '' ],
                    'border'     => [ 'style' => '', 'width' => '', 'color' => '', 'radius' => '' ],
                    'padding'    => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                    'margin'     => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
            ], $css );
            ?>
            <div class="wpcsm-settings-note wpcsm-design-box">
                <div class="wpcsm-design-box-row wpcsm-design-box-row-design">
                    <div class="wpcsm-design-box-label"><?php esc_html_e( 'Container', 'wpc-smart-messages' ); ?></div>
                    <div class="wpcsm-design-box-value">
                        <select name="wpcsm_container" class="wpcsm_container">
                            <option value="div" <?php selected( $container, 'div' ); ?>>div</option>
                            <option value="span" <?php selected( $container, 'span' ); ?>>span</option>
                        </select>
                        <span class="description"><?php esc_html_e( 'Wrap the message by a div or span tag.', 'wpc-smart-messages' ); ?></span>
                    </div>
                </div>
                <div class="wpcsm-design-box-row wpcsm-design-box-row-design">
                    <div class="wpcsm-design-box-label"><?php esc_html_e( 'Extra CSS classes', 'wpc-smart-messages' ); ?></div>
                    <div class="wpcsm-design-box-value">
                        <input type="text" name="wpcsm_extra_classes" class="text regular-text"
                               value="<?php echo esc_attr( $extra_classes ); ?>"/>
                        <span class="description"><?php esc_html_e( 'Add extra CSS classes for the message container, split by one space.', 'wpc-smart-messages' ); ?></span>
                    </div>
                </div>
                <div class="wpcsm-design-box-row wpcsm-design-box-row-design">
                    <div class="wpcsm-design-box-label"><?php esc_html_e( 'Design', 'wpc-smart-messages' ); ?></div>
                    <div class="wpcsm-design-box-value">
                        <select name="wpcsm_design" class="wpcsm_design">
                            <option value="no" <?php selected( $design, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-smart-messages' ); ?></option>
                            <option value="css" <?php selected( $design, 'css' ); ?>><?php esc_html_e( 'Visual', 'wpc-smart-messages' ); ?></option>
                            <option value="custom_css" <?php selected( $design, 'custom_css' ); ?>><?php esc_html_e( 'Custom CSS', 'wpc-smart-messages' ); ?></option>
                        </select>
                        <span class="description"><?php esc_html_e( 'Configure the appearance of the container for this message.', 'wpc-smart-messages' ); ?></span>
                    </div>
                </div>
                <div class="wpcsm-design-box-row wpcsm-design-box-row-custom-css">
                    <div class="wpcsm-design-box-label">CSS</div>
                    <div class="wpcsm-design-box-value">
                        <div>
                            <div style="margin-bottom: 5px"><?php printf( /* translators: CSS */ esc_html__( 'Just fill CSS code without brackets, it will be used in %s', 'wpc-smart-messages' ), '<code>.wpcsm-message-' . esc_attr( $post->ID ) . ' { ... }</code>' ); ?></div>
                            <div>
                                <textarea rows="10" cols="50" class="large-text" name="wpcsm_custom_css"
                                          style="width: 100%"><?php echo esc_textarea( $custom_css ); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wpcsm-design-box-row">
                    <div class="wpcsm-design-box-label"><?php esc_html_e( 'Background', 'wpc-smart-messages' ); ?></div>
                    <div class="wpcsm-design-box-value">
                        <div>
                            <div><?php esc_html_e( 'Color', 'wpc-smart-messages' ); ?></div>
                            <div>
                                <input type="text" name="wpcsm_css[background][color]" class="wpcsm_color_input"
                                       data-alpha-enabled="true" data-alpha-color-type="rgba"
                                       value="<?php echo esc_attr( $css['background']['color'] ); ?>"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wpcsm-design-box-row">
                    <div class="wpcsm-design-box-label"><?php esc_html_e( 'Border', 'wpc-smart-messages' ); ?></div>
                    <div class="wpcsm-design-box-value">
                        <div>
                            <div><?php esc_html_e( 'Style', 'wpc-smart-messages' ); ?></div>
                            <div>
                                <select name="wpcsm_css[border][style]">
                                    <option value="none" <?php selected( $css['border']['style'], 'none' ); ?>><?php esc_html_e( 'None', 'wpc-smart-messages' ); ?></option>
                                    <option value="solid" <?php selected( $css['border']['style'], 'solid' ); ?>><?php esc_html_e( 'Solid', 'wpc-smart-messages' ); ?></option>
                                    <option value="dotted" <?php selected( $css['border']['style'], 'dotted' ); ?>><?php esc_html_e( 'Dotted', 'wpc-smart-messages' ); ?></option>
                                    <option value="dashed" <?php selected( $css['border']['style'], 'dashed' ); ?>><?php esc_html_e( 'Dashed', 'wpc-smart-messages' ); ?></option>
                                    <option value="double" <?php selected( $css['border']['style'], 'double' ); ?>><?php esc_html_e( 'Double', 'wpc-smart-messages' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <div><?php esc_html_e( 'With (px)', 'wpc-smart-messages' ); ?></div>
                            <div>
                                <input type="number" name="wpcsm_css[border][width]" placeholder="0"
                                       value="<?php echo esc_attr( $css['border']['width'] ); ?>"/>
                            </div>
                        </div>
                        <div>
                            <div><?php esc_html_e( 'Color', 'wpc-smart-messages' ); ?></div>
                            <div>
                                <input type="text" name="wpcsm_css[border][color]" class="wpcsm_color_input"
                                       data-alpha-enabled="true" data-alpha-color-type="rgba"
                                       value="<?php echo esc_attr( $css['border']['color'] ); ?>"/>
                            </div>
                        </div>
                        <div>
                            <div><?php esc_html_e( 'Radius (px)', 'wpc-smart-messages' ); ?></div>
                            <div>
                                <input type="number" name="wpcsm_css[border][radius]" placeholder="0"
                                       value="<?php echo esc_attr( $css['border']['radius'] ); ?>"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wpcsm-design-box-row">
                    <div class="wpcsm-design-box-label"><?php esc_html_e( 'Padding', 'wpc-smart-messages' ); ?></div>
                    <div class="wpcsm-design-box-value">
                        <div class="wpcsm-design-box-value">
                            <div>
                                <div><?php esc_html_e( 'Top (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[padding][top]" placeholder="0"
                                           value="<?php echo esc_attr( $css['padding']['top'] ); ?>"/>
                                </div>
                            </div>
                            <div>
                                <div><?php esc_html_e( 'Right (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[padding][right]" placeholder="0"
                                           value="<?php echo esc_attr( $css['padding']['right'] ); ?>"/>
                                </div>
                            </div>
                            <div>
                                <div><?php esc_html_e( 'Bottom (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[padding][bottom]" placeholder="0"
                                           value="<?php echo esc_attr( $css['padding']['bottom'] ); ?>"/>
                                </div>
                            </div>
                            <div>
                                <div><?php esc_html_e( 'Left (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[padding][left]" placeholder="0"
                                           value="<?php echo esc_attr( $css['padding']['left'] ); ?>"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wpcsm-design-box-row">
                    <div class="wpcsm-design-box-label"><?php esc_html_e( 'Margin', 'wpc-smart-messages' ); ?></div>
                    <div class="wpcsm-design-box-value">
                        <div class="wpcsm-design-box-value">
                            <div>
                                <div><?php esc_html_e( 'Top (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[margin][top]" placeholder="0"
                                           value="<?php echo esc_attr( $css['margin']['top'] ); ?>"/>
                                </div>
                            </div>
                            <div>
                                <div><?php esc_html_e( 'Right (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[margin][right]" placeholder="0"
                                           value="<?php echo esc_attr( $css['margin']['right'] ); ?>"/>
                                </div>
                            </div>
                            <div>
                                <div><?php esc_html_e( 'Bottom (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[margin][bottom]" placeholder="0"
                                           value="<?php echo esc_attr( $css['margin']['bottom'] ); ?>"/>
                                </div>
                            </div>
                            <div>
                                <div><?php esc_html_e( 'Left (px)', 'wpc-smart-messages' ); ?></div>
                                <div>
                                    <input type="number" name="wpcsm_css[margin][left]" placeholder="0"
                                           value="<?php echo esc_attr( $css['margin']['left'] ); ?>"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        function ajax_add_condition() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcsm-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $index = sanitize_text_field( $_POST['index'] );
            ?>
            <div class="input-panel" data-key="<?php echo esc_attr( $index ) ?>">
                <div class="input-type">
                    <?php self::get_condition_type( $index ); ?>
                </div>
                <div class="input-value">
                    <?php self::get_condition_value( $index ); ?>
                </div>
                <span class="wpcsm-remove-condition hint--left"
                      aria-label="<?php esc_attr_e( 'Remove', 'wpc-smart-messages' ); ?>">×</span>
            </div>
            <?php
            wp_die();
        }

        function ajax_get_condition_value() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcsm-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            $group = sanitize_text_field( $_POST['group'] );
            $type  = sanitize_text_field( $_POST['type'] );
            $index = sanitize_text_field( $_POST['index'] );
            self::get_condition_value( $index, [ 'group' => $group, 'type' => $type ] );
            wp_die();
        }

        function get_condition_type( $index = 0, $condition = [] ) {
            $condition = array_merge( [ 'type' => 'every_day' ], $condition );
            ?>
            <select class="wpcsm-condition-type-select" name="wpcsm_conditions[<?php echo esc_attr( $index ) ?>][type]">
                <optgroup label="<?php esc_html_e( 'Date & Time', 'wpc-smart-messages' ); ?>">
                    <option value="every_day"
                            data-group="none" <?php selected( $condition['type'], 'every_day' ); ?>><?php esc_html_e( 'Everyday', 'wpc-smart-messages' ); ?></option>
                    <option value="date_on"
                            data-group="date" <?php selected( $condition['type'], 'date_on' ); ?>><?php esc_html_e( 'On the date', 'wpc-smart-messages' ); ?></option>
                    <option value="date_time_before"
                            data-group="date_time" <?php selected( $condition['type'], 'date_time_before' ); ?>><?php esc_html_e( 'Before date & time', 'wpc-smart-messages' ); ?></option>
                    <option value="date_time_after"
                            data-group="date_time" <?php selected( $condition['type'], 'date_time_after' ); ?>><?php esc_html_e( 'After date & time', 'wpc-smart-messages' ); ?></option>
                    <option value="date_before"
                            data-group="date" <?php selected( $condition['type'], 'date_before' ); ?>><?php esc_html_e( 'Before date', 'wpc-smart-messages' ); ?></option>
                    <option value="date_after"
                            data-group="date" <?php selected( $condition['type'], 'date_after' ); ?>><?php esc_html_e( 'After date', 'wpc-smart-messages' ); ?></option>
                    <option value="date_multi"
                            data-group="date_multi" <?php selected( $condition['type'], 'date_multi' ); ?>><?php esc_html_e( 'Multiple dates', 'wpc-smart-messages' ); ?></option>
                    <option value="date_range"
                            data-group="date_range" <?php selected( $condition['type'], 'date_range' ); ?>><?php esc_html_e( 'Date range', 'wpc-smart-messages' ); ?></option>
                    <option value="date_even"
                            data-group="none" <?php selected( $condition['type'], 'date_even' ); ?>><?php esc_html_e( 'All even dates', 'wpc-smart-messages' ); ?></option>
                    <option value="date_odd"
                            data-group="none" <?php selected( $condition['type'], 'date_odd' ); ?>><?php esc_html_e( 'All odd dates', 'wpc-smart-messages' ); ?></option>
                    <option value="time_range"
                            data-group="time_range" <?php selected( $condition['type'], 'time_range' ); ?>><?php esc_html_e( 'Daily time range', 'wpc-smart-messages' ); ?></option>
                    <option value="time_before"
                            data-group="time" <?php selected( $condition['type'], 'time_before' ); ?>><?php esc_html_e( 'Daily before time', 'wpc-smart-messages' ); ?></option>
                    <option value="time_after"
                            data-group="time" <?php selected( $condition['type'], 'time_after' ); ?>><?php esc_html_e( 'Daily after time', 'wpc-smart-messages' ); ?></option>
                    <option value="weekly_every"
                            data-group="weekday" <?php selected( $condition['type'], 'weekly_every' ); ?>><?php esc_html_e( 'Weekly on every', 'wpc-smart-messages' ); ?></option>
                    <option value="week_even"
                            data-group="none" <?php selected( $condition['type'], 'week_even' ); ?>><?php esc_html_e( 'All even weeks', 'wpc-smart-messages' ); ?></option>
                    <option value="week_odd"
                            data-group="none" <?php selected( $condition['type'], 'week_odd' ); ?>><?php esc_html_e( 'All odd weeks', 'wpc-smart-messages' ); ?></option>
                    <option value="week_no"
                            data-group="weekno" <?php selected( $condition['type'], 'week_no' ); ?>><?php esc_html_e( 'On week No.', 'wpc-smart-messages' ); ?></option>
                    <option value="monthly_every"
                            data-group="monthday" <?php selected( $condition['type'], 'monthly_every' ); ?>><?php esc_html_e( 'Monthly on the', 'wpc-smart-messages' ); ?></option>
                    <option value="month_no"
                            data-group="monthno" <?php selected( $condition['type'], 'month_no' ); ?>><?php esc_html_e( 'On month No.', 'wpc-smart-messages' ); ?></option>
                </optgroup>
                <optgroup label="<?php esc_html_e( 'User', 'wpc-smart-messages' ); ?>">
                    <option value="user_role"
                            data-group="user_role" <?php selected( $condition['type'], 'user_role' ); ?>><?php esc_html_e( 'User role', 'wpc-smart-messages' ); ?></option>
                </optgroup>
                <optgroup label="<?php esc_html_e( 'Cart', 'wpc-smart-messages' ); ?>">
                    <option value="cart_total"
                            data-group="compare_number" <?php selected( $condition['type'], 'cart_total' ); ?>><?php esc_html_e( 'Cart total', 'wpc-smart-messages' ); ?></option>
                    <option value="cart_count"
                            data-group="compare_number" <?php selected( $condition['type'], 'cart_count' ); ?>><?php esc_html_e( 'Cart count', 'wpc-smart-messages' ); ?></option>
                </optgroup>
                <optgroup label="<?php esc_html_e( 'Product', 'wpc-smart-messages' ); ?>">
                    <option value="product_status"
                            data-group="product_status" <?php selected( $condition['type'], 'product_status' ); ?>><?php esc_html_e( 'Product status', 'wpc-smart-messages' ); ?></option>
                    <option value="product_stock"
                            data-group="compare_number" <?php selected( $condition['type'], 'product_stock' ); ?>><?php esc_html_e( 'Product stock', 'wpc-smart-messages' ); ?></option>
                    <option value="product_weight"
                            data-group="compare_number" <?php selected( $condition['type'], 'product_weight' ); ?>><?php esc_html_e( 'Product weight', 'wpc-smart-messages' ); ?></option>
                    <option value="product_width"
                            data-group="compare_number" <?php selected( $condition['type'], 'product_width' ); ?>><?php esc_html_e( 'Product width', 'wpc-smart-messages' ); ?></option>
                    <option value="product_height"
                            data-group="compare_number" <?php selected( $condition['type'], 'product_height' ); ?>><?php esc_html_e( 'Product height', 'wpc-smart-messages' ); ?></option>
                    <?php
                    $taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

                    foreach ( $taxonomies as $taxonomy ) {
                        echo '<option value="' . esc_attr( $taxonomy->name ) . '" data-group="term" ' . selected( $condition['type'], $taxonomy->name, false ) . '>' . esc_html( $taxonomy->label ) . '</option>';
                    }
                    ?>
                </optgroup>
            </select>
            <?php
        }

        function get_condition_value( $index = 0, $condition = [] ) {
            $condition = array_merge( [
                    'group'   => 'none',
                    'type'    => 'every_day',
                    'compare' => '',
                    'value'   => ''
            ], $condition );

            if ( ! empty( $condition['group'] ) ) {
                include 'templates/conditions/' . sanitize_file_name( $condition['group'] ) . '.php';
            }
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

        function sanitize_array( $arr ) {
            foreach ( (array) $arr as $k => $v ) {
                if ( is_array( $v ) ) {
                    $arr[ $k ] = self::sanitize_array( $v );
                } else {
                    $arr[ $k ] = sanitize_text_field( $v );
                }
            }

            return $arr;
        }

        function save_post( $post_id ) {
            if ( isset( $_POST['wpcsm_location'] ) ) {
                update_post_meta( $post_id, 'wpcsm_location', sanitize_text_field( $_POST['wpcsm_location'] ) );
            }

            if ( isset( $_POST['wpcsm_custom_location'] ) ) {
                update_post_meta( $post_id, 'wpcsm_custom_location', sanitize_text_field( $_POST['wpcsm_custom_location'] ) );
            }

            if ( isset( $_POST['wpcsm_conditions'] ) ) {
                update_post_meta( $post_id, 'wpcsm_conditions', self::sanitize_array( $_POST['wpcsm_conditions'] ) );
            }

            if ( isset( $_POST['wpcsm_container'] ) ) {
                update_post_meta( $post_id, 'wpcsm_container', sanitize_text_field( $_POST['wpcsm_container'] ) );
            }

            if ( isset( $_POST['wpcsm_extra_classes'] ) ) {
                update_post_meta( $post_id, 'wpcsm_extra_classes', sanitize_text_field( $_POST['wpcsm_extra_classes'] ) );
            }

            if ( isset( $_POST['wpcsm_design'] ) ) {
                update_post_meta( $post_id, 'wpcsm_design', sanitize_text_field( $_POST['wpcsm_design'] ) );
            }

            if ( isset( $_POST['wpcsm_css'] ) ) {
                update_post_meta( $post_id, 'wpcsm_css', self::sanitize_array( $_POST['wpcsm_css'] ) );
            }

            if ( isset( $_POST['wpcsm_custom_css'] ) ) {
                update_post_meta( $post_id, 'wpcsm_custom_css', sanitize_textarea_field( $_POST['wpcsm_custom_css'] ) );
            }
        }

        function init() {
            // load text-domain
            load_plugin_textdomain( 'wpc-smart-messages', false, basename( WPCSM_DIR ) . '/languages/' );

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

        function enqueue_scripts() {
            wp_enqueue_style( 'hint', WPCSM_URI . 'assets/css/hint.css' );

            // color picker
            wp_enqueue_style( 'wp-color-picker' );
            wp_register_script( 'wp-color-picker-alpha', WPCSM_URI . 'assets/js/wp-color-picker-alpha.min.js', [ 'wp-color-picker' ], WPCSM_VERSION );

            // wpcdpk
            wp_enqueue_style( 'wpcdpk', WPCSM_URI . 'assets/libs/wpcdpk/css/datepicker.css' );
            wp_enqueue_script( 'wpcdpk', WPCSM_URI . 'assets/libs/wpcdpk/js/datepicker.js', [ 'jquery' ], WPCSM_VERSION, true );

            wp_enqueue_style( 'wpcsm-backend', WPCSM_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCSM_VERSION );
            wp_enqueue_script( 'wpcsm-backend', WPCSM_URI . 'assets/js/backend.js', [
                    'jquery',
                    'wp-color-picker',
                    'wp-color-picker-alpha',
                    'wc-enhanced-select',
                    'selectWoo'
            ], WPCSM_VERSION, true );
            wp_localize_script( 'wpcsm-backend', 'wpcsm_vars', [ 'nonce' => wp_create_nonce( 'wpcsm-security' ), ] );
        }

        function ajax_enable() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcsm-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

            if ( isset( $_POST['id'], $_POST['act'] ) ) {
                $id  = sanitize_text_field( $_POST['id'] );
                $act = sanitize_text_field( $_POST['act'] );

                update_post_meta( $id, 'wpcsm_activate', ( $act === 'activate' ? 'on' : 'off' ) );
                echo esc_html( $act );
            }

            wp_die();
        }

        function ajax_search_term() {
            if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wpcsm-security' ) || ! current_user_can( 'manage_options' ) ) {
                die( 'Permissions check failed!' );
            }

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

        function message_columns( $columns ) {
            return [
                    'cb'       => $columns['cb'],
                    'activate' => esc_html__( 'Activate', 'wpc-smart-messages' ),
                    'title'    => esc_html__( 'Title', 'wpc-smart-messages' ),
                    'location' => esc_html__( 'Location', 'wpc-smart-messages' ),
                    'message'  => esc_html__( 'Message', 'wpc-smart-messages' ),
                    'date'     => esc_html__( 'Date', 'wpc-smart-messages' ),
            ];
        }

        function message_columns_content( $column, $post_id ) {
            if ( $column === 'activate' ) {
                if ( get_post_meta( $post_id, 'wpcsm_activate', true ) === 'off' ) {
                    echo '<a href="#" class="wpcsm-activate-btn activate button" data-id="' . esc_attr( $post_id ) . '"></a>';
                } else {
                    echo '<a href="#" class="wpcsm-activate-btn deactivate button button-primary" data-id="' . esc_attr( $post_id ) . '"></a>';
                }
            }

            if ( $column === 'location' ) {
                $location = get_post_meta( $post_id, 'wpcsm_location', true ) ?: 'store_notice_info';

                if ( $location === 'custom' ) {
                    echo esc_html( get_post_meta( $post_id, 'wpcsm_custom_location', true ) );
                } else {
                    $locations    = self::get_locations();
                    $has_location = false;

                    foreach ( $locations as $group => $location_keys ) {
                        if ( array_key_exists( $location, $location_keys ) ) {
                            $has_location = true;
                            echo esc_html( is_array( $location_keys[ $location ] ) && isset( $location_keys[ $location ]['name'] ) ? $group . ' > ' . $location_keys[ $location ]['name'] : $group . ' > ' . $location_keys[ $location ] );

                            break;
                        }
                    }

                    if ( ! $has_location ) {
                        echo esc_html( $location );
                    }
                }
            }

            if ( $column === 'message' ) {
                echo wp_kses_post( wp_strip_all_tags( get_the_content( null, false, $post_id ) ) );;
            }
        }
    }

    Wpcsm_Backend::instance();
}