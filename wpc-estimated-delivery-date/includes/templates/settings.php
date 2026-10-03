<?php
defined( 'ABSPATH' ) || exit;

$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
$rules      = Wpced_Backend()->get_rules();
?>
<div class="wpclever_settings_page wrap">
    <div class="wpclever_settings_page_header">
        <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/"
           target="_blank" title="Visit wpclever.net"></a>
        <div class="wpclever_settings_page_header_text">
            <div class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Estimated Delivery Date', 'wpc-estimated-delivery-date' ) . ' ' . esc_html( WPCED_VERSION ) . ' ' . ( defined( 'WPCED_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-estimated-delivery-date' ) . '</span>' : '' ); ?></div>
            <div class="wpclever_settings_page_desc about-text">
                <p>
                    <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-estimated-delivery-date' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                    <br/>
                    <a href="<?php echo esc_url( WPCED_REVIEWS ); ?>"
                       target="_blank"><?php esc_html_e( 'Reviews', 'wpc-estimated-delivery-date' ); ?></a> |
                    <a href="<?php echo esc_url( WPCED_CHANGELOG ); ?>"
                       target="_blank"><?php esc_html_e( 'Changelog', 'wpc-estimated-delivery-date' ); ?></a> |
                    <a href="<?php echo esc_url( WPCED_DISCUSSION ); ?>"
                       target="_blank"><?php esc_html_e( 'Discussion', 'wpc-estimated-delivery-date' ); ?></a>
                </p>
            </div>
        </div>
    </div>
    <h2></h2>
    <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Settings updated.', 'wpc-estimated-delivery-date' ); ?></p>
        </div>
    <?php } ?>
    <div class="wpclever_settings_page_nav">
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpced&tab=settings' ) ); ?>"
               class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                <?php esc_html_e( 'Settings', 'wpc-estimated-delivery-date' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpced&tab=premium' ) ); ?>"
               class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
               style="color: #c9356e">
                <?php esc_html_e( 'Premium Version', 'wpc-estimated-delivery-date' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
                <?php esc_html_e( 'Essential Kit', 'wpc-estimated-delivery-date' ); ?>
            </a>
        </h2>
    </div>
    <div class="wpclever_settings_page_content">
        <?php if ( $active_tab === 'settings' ) {
            $date_format         = Wpced_Backend()->get_setting( 'date_format', 'M j, Y' );
            $date_format_custom  = Wpced_Backend()->get_setting( 'date_format_custom', 'M j, Y' );
            $pos_archive         = Wpced_Backend()->get_setting( 'position_archive', apply_filters( 'wpced_default_archive_position', 'above_add_to_cart' ) );
            $pos_single          = Wpced_Backend()->get_setting( 'position_single', apply_filters( 'wpced_default_single_position', '31' ) );
            $skipped_dates       = Wpced_Backend()->get_setting( 'skipped_dates', [] );
            $cart_item           = Wpced_Backend()->get_setting( 'cart_item', 'no' );
            $cart_overall        = Wpced_Backend()->get_setting( 'cart_overall', 'yes' );
            $cart_overall_format = Wpced_Backend()->get_setting( 'cart_overall_format', 'latest' );
            $order_item          = Wpced_Backend()->get_setting( 'order_item', 'no' );
            $reload_dates        = Wpced_Backend()->get_setting( 'reload_dates', 'no' );
            ?>
            <form method="post" action="options.php">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Position on archive', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label> <select name="wpced_settings[position_archive]">
                                    <?php foreach ( Wpced_Backend()->get_archive_positions() as $key => $pos ) {
                                        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $pos_archive, $key, false ) . '>' . esc_html( $pos ) . '</option>';
                                    } ?>
                                </select> </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Position on single', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label> <select name="wpced_settings[position_single]">
                                    <?php foreach ( Wpced_Backend()->get_single_positions() as $key => $pos ) {
                                        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $pos_single, $key, false ) . '>' . esc_html( $pos ) . '</option>';
                                    } ?>
                                </select> </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Shortcode', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <?php echo sprintf( /* translators: shortcode */ esc_html__( 'You can use shortcode %s to show the estimated delivery date for current product.', 'wpc-estimated-delivery-date' ), '<code>[wpced]</code>' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show on cart items', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label> <select name="wpced_settings[cart_item]">
                                    <option value="yes" <?php selected( $cart_item, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="yes_data" <?php selected( $cart_item, 'yes_data' ); ?>><?php esc_html_e( 'Yes, as an item\'s data', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="no" <?php selected( $cart_item, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-estimated-delivery-date' ); ?></option>
                                </select> </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show cart overall', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label> <select name="wpced_settings[cart_overall]">
                                    <option value="yes" <?php selected( $cart_overall, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="yes_text" <?php selected( $cart_overall, 'yes_text' ); ?>><?php esc_html_e( 'Yes, as a plain text', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="no" <?php selected( $cart_overall, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-estimated-delivery-date' ); ?></option>
                                </select> </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Overall date format', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label> <select name="wpced_settings[cart_overall_format]">
                                    <option value="latest" <?php selected( $cart_overall_format, 'latest' ); ?>><?php esc_html_e( 'Latest date (default)', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="earliest" <?php selected( $cart_overall_format, 'earliest' ); ?>><?php esc_html_e( 'Earliest date', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="earliest_latest" <?php selected( $cart_overall_format, 'earliest_latest' ); ?>><?php esc_html_e( 'Earliest - Latest date', 'wpc-estimated-delivery-date' ); ?></option>
                                </select> </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show on order items', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label> <select name="wpced_settings[order_item]">
                                    <option value="yes" <?php selected( $order_item, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="no" <?php selected( $order_item, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-estimated-delivery-date' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( 'Show the date on order items (order confirmation or emails).', 'wpc-estimated-delivery-date' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Reload dates', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label> <select name="wpced_settings[reload_dates]">
                                    <option value="yes" <?php selected( $reload_dates, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-estimated-delivery-date' ); ?></option>
                                    <option value="no" <?php selected( $reload_dates, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-estimated-delivery-date' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( 'Dates will be reloaded when opening the page? If you use the cache for your site, please turn on this option.', 'wpc-estimated-delivery-date' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Date format', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <?php
                            $date_formats = [
                                    'Y/m/d',
                                    'd/m/Y',
                                    'm/d/y',
                                    'm/d/Y',
                                    'Y-m-d',
                                    'd-m-Y',
                                    'm-d-y',
                                    'Y.m.d',
                                    'd.m.Y',
                                    'm.d.y',
                                    'F j, Y',
                                    'M j, Y',
                                    'jS \of F',
                                    'jS F',
                                    'j. F',
                                    'l j. F',
                                    'F jS',
                                    'jS M',
                                    'M jS'
                            ];
                            echo '<select name="wpced_settings[date_format]" class="wpced-date-format">';

                            foreach ( $date_formats as $df ) {
                                echo '<option value="' . esc_attr( $df ) . '" ' . selected( $date_format, $df, false ) . '>' . current_time( $df ) . '</option>';
                            }

                            echo '<option value="days" ' . selected( $date_format, 'days', false ) . '>' . esc_html__( 'Days count', 'wpc-estimated-delivery-date' ) . '</option>';
                            echo '<option value="custom" ' . selected( $date_format, 'custom', false ) . '>' . esc_html__( 'Custom', 'wpc-estimated-delivery-date' ) . '</option>';

                            echo '</select>';
                            ?>
                            <label>
                                <input type="text" class="text wpced-date-format-custom"
                                       name="wpced_settings[date_format_custom]"
                                       value="<?php echo esc_attr( $date_format_custom ); ?>"/>
                            </label>
                            <span class="wpced-date-format-preview"><?php echo sprintf( /* translators: preview date */ esc_html__( 'Preview: %s', 'wpc-estimated-delivery-date' ), current_time( $date_format_custom ) ); ?></span>
                            <p class="description">
                                <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/"
                                   target="_blank"><?php esc_html_e( 'Documentation on date and time formatting.', 'wpc-estimated-delivery-date' ); ?></a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Message', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label><?php esc_html_e( 'Have both minimum and maximum days', 'wpc-estimated-delivery-date' ); ?></label>
                            <label>
                                <input type="text" name="wpced_settings[text]" class="large-text"
                                       style="width: 100%; margin: 0"
                                       value="<?php echo esc_attr( Wpced_Backend()->get_setting( 'text' ) ); ?>"
                                       placeholder="<?php /* translators: date */
                                       esc_attr_e( 'Estimated delivery dates: %s', 'wpc-estimated-delivery-date' ); ?>"/>
                            </label> <br/><br/>
                            <label><?php esc_html_e( 'Have minimum days only', 'wpc-estimated-delivery-date' ); ?></label>
                            <label>
                                <input type="text" name="wpced_settings[text_min]" class="large-text"
                                       style="width: 100%; margin: 0"
                                       value="<?php echo esc_attr( Wpced_Backend()->get_setting( 'text_min' ) ); ?>"
                                       placeholder="<?php /* translators: date */
                                       esc_attr_e( 'Earliest estimated delivery date: %s', 'wpc-estimated-delivery-date' ); ?>"/>
                            </label> <br/><br/>
                            <label><?php esc_html_e( 'Have maximum days only', 'wpc-estimated-delivery-date' ); ?></label>
                            <label>
                                <input type="text" name="wpced_settings[text_max]" class="large-text"
                                       style="width: 100%; margin: 0"
                                       value="<?php echo esc_attr( Wpced_Backend()->get_setting( 'text_max' ) ); ?>"
                                       placeholder="<?php /* translators: date */
                                       esc_attr_e( 'Latest estimated delivery date: %s', 'wpc-estimated-delivery-date' ); ?>"/>
                            </label> <br/><br/>
                            <label><?php esc_html_e( 'Cart item\'s data label', 'wpc-estimated-delivery-date' ); ?></label>
                            <label>
                                <input type="text" name="wpced_settings[text_cart_item]" class="large-text"
                                       style="width: 100%; margin: 0"
                                       value="<?php echo esc_attr( Wpced_Backend()->get_setting( 'text_cart_item' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Estimated delivery date', 'wpc-estimated-delivery-date' ); ?>"/>
                            </label> <br/><br/>
                            <label><?php esc_html_e( 'Cart overall', 'wpc-estimated-delivery-date' ); ?></label> <label>
                                <input type="text" name="wpced_settings[text_cart_overall]" class="large-text"
                                       style="width: 100%; margin: 0"
                                       value="<?php echo esc_attr( Wpced_Backend()->get_setting( 'text_cart_overall' ) ); ?>"
                                       placeholder="<?php /* translators: date */
                                       esc_attr_e( 'Overall estimated dispatch date: %s', 'wpc-estimated-delivery-date' ); ?>"/>
                            </label> <br/><br/> <span class="description"><?php /* translators: date */
                                esc_html_e( 'Use %s to show the date or date-range. Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-estimated-delivery-date' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Current time', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <code><?php echo esc_html( current_time( 'l' ) ); ?></code>
                            <code><?php echo esc_html( current_time( 'm/d/Y' ) ); ?></code>
                            <code><?php echo esc_html( current_time( 'h:i a' ) ); ?></code>
                            <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"
                               target="_blank"><?php esc_html_e( 'Date/time settings', 'wpc-estimated-delivery-date' ); ?></a>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Extra time line', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <label>
                                <input type="text" name="wpced_settings[extra_time_line]" class="wpced-time-val"
                                       value="<?php echo esc_attr( Wpced_Backend()->get_setting( 'extra_time_line' ) ); ?>"
                                       readonly/>
                            </label>
                            <span class="description"><?php esc_html_e( 'Maximum time to consider an extra day of shipping.', 'wpc-estimated-delivery-date' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Skipped dates', 'wpc-estimated-delivery-date' ); ?></th>
                        <td>
                            <span class="description"><?php esc_html_e( 'Select dates to skip in estimated (most shipping don\'t work on weekends, so you can select Saturday, Sunday and all the saturday and sundays will not be counted in calculating estimated shipping date).', 'wpc-estimated-delivery-date' ); ?></span>
                            <div class="wpced-skipped-dates">
                                <?php
                                if ( ! empty( $skipped_dates ) && is_array( $skipped_dates ) ) {
                                    foreach ( $skipped_dates as $date_key => $date ) {
                                        include WPCED_DIR . 'includes/templates/date.php';
                                    }
                                }
                                ?>
                            </div>
                            <div class="wpced-add-date">
                                <input type="button" class="button wpced-add-date-btn"
                                       value="<?php esc_attr_e( '+ Add date', 'wpc-estimated-delivery-date' ); ?>">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <?php esc_html_e( 'Rules', 'wpc-estimated-delivery-date' ); ?>
                            <a style="display: none;" class="wpclever_export" data-key="wpced_rules" data-name="rules"
                               href="#"><?php esc_html_e( 'import / export', 'wpc-estimated-delivery-date' ); ?></a>
                        </th>
                        <td>
                            <div class='wpced-settings'>
                                <div class="wpced-items-wrapper">
                                    <div class="wpced-items">
                                        <?php
                                        // variables for rule.php
                                        $product_id   = 0;
                                        $is_variation = false;

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
                                        unset( $rules['default'] );

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
                        </td>
                    </tr>
                    <tr class="submit">
                        <th colspan="2">
                            <?php settings_fields( 'wpced_settings' ); ?><?php submit_button(); ?>
                            <a style="display: none;" class="wpclever_export" data-key="wpced_settings"
                               data-name="settings"
                               href="#"><?php esc_html_e( 'import / export', 'wpc-estimated-delivery-date' ); ?></a>
                        </th>
                    </tr>
                </table>
            </form>
        <?php } elseif ( $active_tab == 'premium' ) { ?>
            <div class="wpclever_settings_page_content_text">
                <p>Get the Premium Version just $29!
                    <a href="https://wpclever.net/downloads/wpc-estimated-delivery-date?utm_source=pro&utm_medium=wpced&utm_campaign=wporg"
                       target="_blank">https://wpclever.net/downloads/wpc-estimated-delivery-date</a>
                </p>
                <p><strong>Extra features for Premium Version:</strong></p>
                <ul style="margin-bottom: 0">
                    <li>- Add custom skipped dates.</li>
                    <li>- Add scheduled delivery date for each rule.</li>
                    <li>- Get the lifetime update & premium support.</li>
                </ul>
            </div>
        <?php } ?>
    </div><!-- /.wpclever_settings_page_content -->
    <div class="wpclever_settings_page_suggestion">
        <div class="wpclever_settings_page_suggestion_label">
            <span class="dashicons dashicons-yes-alt"></span> Suggestion
        </div>
        <div class="wpclever_settings_page_suggestion_content">
            <div>
                To display custom engaging real-time messages on any wished positions, please install
                <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a>
                plugin. It's free!
            </div>
            <div>
                Wanna save your precious time working on variations? Try our brand-new free plugin
                <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk
                    Editor</a> and
                <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation
                    Duplicator</a>.
            </div>
        </div>
    </div>
</div>
