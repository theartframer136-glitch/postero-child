<?php

class GRWP_Global_Settings {
    private $google_reviews_options;

    private $settings_slug;

    public function __construct() {
        $this->google_reviews_options = get_option( 'google_reviews_option_name' );
        $this->settings_slug = 'google-reviews-admin';
        $this->add_api_settings();
        $this->add_display_settings();
        $this->add_header_settings();
        $this->add_slider_settings();
        $this->add_grid_settings();
        $this->add_legacy_settings();
        $this->add_translation_settings();
    }

    /**
     * API settings
     */
    private function add_api_settings() {
        register_setting( 
            'google_reviews_option_group',
            // option_group
            'google_reviews_option_name',
            // option_name
            array($this, 'google_reviews_sanitize')
         );
        add_settings_section(
            'google_reviews_setting_section',
            // id
            '',
            // title
            array($this, 'google_reviews_section_info'),
            // callback
            $this->settings_slug
        );
        add_settings_field(
            'show_upgrade_message',
            // id
            '',
            // title
            array($this, 'show_upgrade_message_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_setting_section'
        );
        add_settings_field(
            'show_verified',
            // id
            /* translators: Show dummy content */
            __( 'Show \'verified\' badge', 'embedder-for-google-reviews' ),
            array($this, 'show_verified_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_setting_section'
        );
        add_settings_field(
            'reviews_language_3',
            // id
            /* translators: Reviews language */
            __( 'Reviews language', 'embedder-for-google-reviews' ),
            array($this, 'reviews_language_3_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_setting_section'
        );
        add_settings_field(
            'style_2_in_connect',
            __( 'Layout type', 'embedder-for-google-reviews' ),
            array($this, 'style_2_callback'),
            $this->settings_slug,
            'google_reviews_legacy_setting_section'
        );
    }

    public function show_upgrade_message_callback() {
        $upgrade_url = 'https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=upgrade_tab&utm_campaign=upgrade_banner';
        ?>
        <div id="grwp-upgrade-banner" style="background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:6px;padding:12px 16px;display:flex;align-items:flex-start;gap:12px;margin:8px 0 4px;">
            <span style="font-size:1.1rem;line-height:1.5;">⚡</span>
            <div style="flex:1;font-size:.85rem;color:#78350f;line-height:1.6;">
                <?php 
        printf( wp_kses( 
            /* translators: %s is replaced with "Attention" in bold. */
            sprintf( __( '%s: the free version only allows for pulling 20 reviews.', 'embedder-for-google-reviews' ), '<strong>' . __( 'Attention', 'embedder-for-google-reviews' ) . '</strong>' ),
            array(
                'strong' => array(),
            )
         ) );
        ?>
                <a href="<?php 
        echo esc_url( $upgrade_url );
        ?>" target="_blank" style="color:#92400e;font-weight:600;">
                    <?php 
        esc_html_e( 'Upgrade to PRO ', 'embedder-for-google-reviews' );
        ?>
                </a>
                <?php 
        esc_html_e( '– to display ALL reviews and hide bad ones.', 'embedder-for-google-reviews' );
        ?>
            </div>
            <button type="button" id="grwp-upgrade-banner-close" style="background:none;border:none;cursor:pointer;color:#92400e;font-size:1rem;line-height:1;padding:2px 0 0;flex-shrink:0;" aria-label="<?php 
        esc_attr_e( 'Close', 'embedder-for-google-reviews' );
        ?>">✕</button>
        </div>
        <script>
        (function($){
            if ( localStorage.getItem('grwp_hide_upgrade_banner') ) {
                //$('#grwp-upgrade-banner').hide();
            }
            $('#grwp-upgrade-banner-close').on('click', function(){
                $('#grwp-upgrade-banner').slideUp(200);
                //localStorage.setItem('grwp_hide_upgrade_banner', '1');
            });
        }(jQuery));
        </script>

        <?php 
    }

    /**
     * Display settings
     */
    private function add_display_settings() {
        // settings for styles and layout
        register_setting( 
            'google_reviews_style_group',
            // option_group
            'google_reviews_style',
            // option_name
            array($this, 'google_reviews_sanitize')
         );
        // add style and layout settings section
        add_settings_section(
            'google_reviews_style_layout_setting_section',
            // id
            '',
            // title
            array($this, 'google_reviews_display_section_info'),
            // callback
            $this->settings_slug
        );
        // second section, rendered as a side-by-side column on the Display
        // Settings tab – groups text/number/url inputs separately from the
        // plain Yes/No toggles in the section above, so each column's rows
        // stay close in height (see google-reviews-admin.scss .grwp-two-col)
        add_settings_section(
            'google_reviews_style_layout_setting_section_inputs',
            // id
            '',
            // title
            '__return_false',
            // callback
            $this->settings_slug
        );
        add_settings_field(
            'filter_below_5_stars',
            // id
            /* translators: Minimum rating (stars) */
            __( 'Minimum rating (stars)', 'embedder-for-google-reviews' ),
            array($this, 'filter_below_5_stars_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section_inputs'
        );
        add_settings_field(
            'exclude_reviews_without_text',
            // id
            /* translators: Exclude reviews without text */
            __( 'Exclude reviews without text', 'embedder-for-google-reviews' ),
            array($this, 'exclude_reviews_without_text_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'hide_date_string',
            // id
            /* translators: Hide review date */
            __( 'Hide review date', 'embedder-for-google-reviews' ),
            array($this, 'hide_date_string_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'link_users_profiles',
            // id
            /* translators: Link to users Google prilfe */
            __( 'Link to users Profile <br> (uncheck for better SEO)', 'embedder-for-google-reviews' ),
            array($this, 'link_users_profiles_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'filter_words',
            // id
            /* translators: Filter by words (comma separated) */
            __( 'Filter by words (comma separated)', 'embedder-for-google-reviews' ),
            array($this, 'filter_words_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section_inputs'
        );
        // "Hide company header section" has been folded into the Header Settings
        // tab as the "None" header type, so it no longer has its own field here.
        // The stored value is still honoured (and kept in sync) via
        // grwp_resolve_header_type() and the sanitizer.
        add_settings_field(
            'show_dummy_content',
            // id
            /* translators: Show dummy content */
            __( 'Show dummy content', 'embedder-for-google-reviews' ),
            array($this, 'show_dummy_content_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'hide_profile_picture',
            // id
            /* translators: Hide reviewer profile picture */
            __( 'Hide reviewer profile picture', 'embedder-for-google-reviews' ),
            array($this, 'hide_profile_picture_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'hide_rating_text',
            // id
            /* translators: Hide rating text */
            __( 'Hide rating text', 'embedder-for-google-reviews' ),
            array($this, 'hide_rating_text_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'use_safe_fallback_font',
            // id
            /* translators: Use safe fallback font */
            __( 'Use safe fallback font', 'embedder-for-google-reviews' ),
            array($this, 'use_safe_fallback_font_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'disable_box_shadow',
            // id
            /* translators: Remove card box shadows */
            __( 'Remove card box shadows', 'embedder-for-google-reviews' ),
            array($this, 'disable_box_shadow_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section'
        );
        add_settings_field(
            'content_max_height',
            // id
            /* translators: Maximum height of the review text (px) */
            __( 'Content max height (px)', 'embedder-for-google-reviews' ),
            array($this, 'content_max_height_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section_inputs'
        );
        add_settings_field(
            'content_overflow',
            // id
            /* translators: How overflowing review text is handled */
            __( 'Content overflow', 'embedder-for-google-reviews' ),
            array($this, 'content_overflow_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section_inputs'
        );
        add_settings_field(
            'button_type',
            // id
            /* translators: Call to action button link target */
            __( 'Call to action button', 'embedder-for-google-reviews' ),
            array($this, 'button_type_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section_inputs'
        );
        add_settings_field(
            'button_url',
            // id
            /* translators: Button URL */
            __( 'Custom button URL', 'embedder-for-google-reviews' ),
            array($this, 'button_url_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section_inputs',
            // section
            array(
                'class' => 'grwp-button-custom-row',
            )
        );
        add_settings_field(
            'button_text',
            // id
            /* translators: Button text */
            __( 'Custom button text', 'embedder-for-google-reviews' ),
            array($this, 'button_text_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_style_layout_setting_section_inputs',
            // section
            array(
                'class' => 'grwp-button-custom-row',
            )
        );
    }

    /**
     * Translation overrides (Translation subpage). Only registers the option;
     * the fields are rendered directly by the subpage template.
     * @return void
     */
    private function add_translation_settings() {
        register_setting( 
            'grwp_string_overrides_group',
            // option_group
            'grwp_string_overrides',
            // option_name
            array($this, 'grwp_sanitize_string_overrides')
         );
    }

    /**
     * Sanitize the Translation subpage overrides: only known keys, plain text.
     * @param mixed $input
     * @return array
     */
    public function grwp_sanitize_string_overrides( $input ) {
        $sanitary_values = array();
        if ( !is_array( $input ) ) {
            return $sanitary_values;
        }
        foreach ( array_keys( grwp_translatable_strings() ) as $key ) {
            if ( isset( $input[$key] ) ) {
                $sanitary_values[$key] = sanitize_text_field( $input[$key] );
            }
        }
        return $sanitary_values;
    }

    /**
     * Maximum height of the review text of each review card. Empty keeps the
     * per-style default (76px).
     * @return void
     */
    public function content_max_height_callback() {
        global $allowed_html;
        $value = ( isset( $this->google_reviews_options['content_max_height'] ) && $this->google_reviews_options['content_max_height'] !== '' ? intval( $this->google_reviews_options['content_max_height'] ) : '' );
        ob_start();
        ?>

        <input type="number"
               name="google_reviews_option_name[content_max_height]"
               id="content_max_height"
               min="0"
               step="1"
               placeholder="76"
               value="<?php 
        echo esc_attr( $value );
        ?>"
        >

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * How review text taller than the max height is handled: a vertical
     * scrollbar (default) or a "Read more" button that expands the card.
     * @return void
     */
    public function content_overflow_callback() {
        $current = ( isset( $this->google_reviews_options['content_overflow'] ) ? $this->google_reviews_options['content_overflow'] : 'scrollbar' );
        ?>

        <select name="google_reviews_option_name[content_overflow]" id="content_overflow">
            <option value="scrollbar" <?php 
        selected( $current, 'scrollbar' );
        ?>>
                <?php 
        esc_html_e( 'Vertical scrollbar', 'embedder-for-google-reviews' );
        ?>
            </option>
            <option value="read_more" <?php 
        selected( $current, 'read_more' );
        ?>>
                <?php 
        esc_html_e( '"Read more" button', 'embedder-for-google-reviews' );
        ?>
            </option>
        </select>

        <?php 
    }

    /**
     * Grid settings
     * @return void
     */
    private function add_grid_settings() {
        add_settings_section(
            'google_reviews_grid_setting_section',
            // id
            '',
            // title
            array($this, 'google_reviews_display_grid_info'),
            // callback
            $this->settings_slug
        );
        add_settings_field(
            'show_more_grid',
            // id
            /* translators: Show "Load more" button on grid */
            __( 'Show "Load more" button', 'embedder-for-google-reviews' ),
            array($this, 'show_more_grid_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_grid_setting_section'
        );
        add_settings_field(
            'show_more_grid_initial',
            // id
            /* translators: Initially visible review rows (grid load-more) */
            __( 'Initially visible review rows', 'embedder-for-google-reviews' ),
            array($this, 'show_more_grid_initial_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_grid_setting_section'
        );
        add_settings_field(
            'show_more_grid_load_more_rows',
            // id
            /* translators: Number of rows revealed per "Load more" click (grid load-more) */
            __( 'Load more rows', 'embedder-for-google-reviews' ),
            array($this, 'show_more_grid_load_more_rows_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_grid_setting_section'
        );
        // The "Load more" button text is edited on the Translation subpage
        // (the "Show more" string), not here.
    }

    public function google_reviews_display_grid_info() {
        ?>
        <h2 id="grid_settings"><?php 
        esc_html_e( 'Grid settings', 'embedder-for-google-reviews' );
        ?></h2>
        <?php 
    }

    /**
     * Header settings
     *
     * Lets users pick which header is shown above the slider/grid widgets,
     * optionally replacing the standard "Overall rating out of X reviews".
     * @return void
     */
    private function add_header_settings() {
        add_settings_section(
            'google_reviews_header_setting_section',
            // id
            '',
            // title
            array($this, 'google_reviews_display_header_info'),
            // callback
            $this->settings_slug
        );
        add_settings_field(
            'header_type',
            // id
            /* translators: Header type */
            __( 'Header type', 'embedder-for-google-reviews' ),
            array($this, 'header_type_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_header_setting_section'
        );
    }

    public function google_reviews_display_header_info() {
        ?>
        <h2 id="header_settings"><?php 
        esc_html_e( 'Header settings', 'embedder-for-google-reviews' );
        ?></h2>
        <p style="color:#64748b;font-size:.85rem;margin:0 0 8px;">
            <?php 
        esc_html_e( 'Choose which header is displayed above your slider and grid widgets. The selected header replaces the standard one.', 'embedder-for-google-reviews' );
        ?>
        </p>
        <?php 
    }

    /**
     * Echo header type field (PRO feature).
     * @return void
     */
    public function header_type_callback() {
        $current = grwp_resolve_header_type( $this->google_reviews_options );
        ?>

            <select name="google_reviews_option_name[header_type]" id="header_type">
                <option value="standard" <?php 
        selected( $current, 'standard' );
        ?>>
                    <?php 
        esc_html_e( 'Standard', 'embedder-for-google-reviews' );
        ?>
                </option>
                <option value="compact" <?php 
        selected( $current, 'compact' );
        ?>>
                    <?php 
        esc_html_e( 'Compact bar', 'embedder-for-google-reviews' );
        ?>
                </option>
                <option value="compact_plain" <?php 
        selected( $current, 'compact_plain' );
        ?>>
                    <?php 
        esc_html_e( 'Compact – plain', 'embedder-for-google-reviews' );
        ?>
                </option>
                <option value="none" <?php 
        selected( $current, 'none' );
        ?>>
                    <?php 
        esc_html_e( 'None', 'embedder-for-google-reviews' );
        ?>
                </option>
            </select>

        <?php 
    }

    /**
     * Slider settings
     * @return void
     */
    private function add_slider_settings() {
        // settings for styles and layout
        register_setting( 
            'google_reviews_slider_settings_group',
            // option_group
            'google_reviews_slider_settings',
            // option_name
            array($this, 'google_reviews_sanitize')
         );
        add_settings_section(
            'google_reviews_slider_setting_section',
            // id
            '',
            // title
            array($this, 'google_reviews_display_slider_info'),
            // callback
            $this->settings_slug
        );
        add_settings_field(
            'slide_duration',
            // id
            /* translators: Layout type */
            __( 'Slide Duration (seconds). <br> Use \'0\' to disable autoplay', 'embedder-for-google-reviews' ),
            array($this, 'slide_duration_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_slider_setting_section'
        );
        add_settings_field(
            'hide_slider_arrows',
            // id
            /* translators: Layout type */
            __( 'Hide slider arrows', 'embedder-for-google-reviews' ),
            array($this, 'hide_slider_arrows_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_slider_setting_section'
        );
        add_settings_field(
            'slider_arrows_position',
            // id
            /* translators: Prev/next arrow placement */
            __( 'Arrow position', 'embedder-for-google-reviews' ),
            array($this, 'slider_arrows_position_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_slider_setting_section'
        );
        add_settings_field(
            'disable_loop_slider',
            // id
            /* translators: Layout type */
            __( 'Disable slider endless loop', 'embedder-for-google-reviews' ),
            array($this, 'disable_loop_slider_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_slider_setting_section'
        );
        add_settings_field(
            'pause_on_hover',
            // id
            /* translators: Pause slider autoplay on mouseover */
            __( 'Pause on mouseover', 'embedder-for-google-reviews' ),
            array($this, 'pause_on_hover_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_slider_setting_section'
        );
        add_settings_field(
            'marquee_slider',
            // id
            /* translators: Marquee slider */
            __( 'Marquee slider <br> (continuous scrolling)', 'embedder-for-google-reviews' ),
            array($this, 'marquee_slider_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_slider_setting_section'
        );
        add_settings_field(
            'marquee_speed',
            // id
            /* translators: Marquee scrolling speed */
            __( 'Marquee speed <br> (1 = slow, 10 = fast)', 'embedder-for-google-reviews' ),
            array($this, 'marquee_speed_callback'),
            // callback
            $this->settings_slug,
            // page
            'google_reviews_slider_setting_section'
        );
    }

    /**
     * Slide duration
     * @return void
     */
    public function slide_duration_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <?php 
        ?>
            <div class="tooltip">
                <input type="hidden"
                       name="google_reviews_option_name[slide_duration]"
                       value="5"
                />
                <input type="number"
                       name="google_reviews_option_name[slide_duration]"
                       id="slide_duration"
                       value="5"
                       disabled
                />
                <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=slide_duration&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    public function hide_slider_arrows_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <?php 
        ?>
            <div class="tooltip">
                <input type="hidden"
                       name="google_reviews_option_name[hide_slider_arrows]"
                       id="hide_slider_arrows"
                       value="0"
                />

                <input type="checkbox"
                       name="google_reviews_option_name[hide_slider_arrows]"
                       disabled
                />
                <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=slide_duration&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Prev/next arrow placement: Below or Middle. The default for installs that
     * have not chosen one depends on the first-activation version (see
     * grwp_default_arrows_position()).
     * @return void
     */
    public function slider_arrows_position_callback() {
        $current = ( isset( $this->google_reviews_options['slider_arrows_position'] ) ? $this->google_reviews_options['slider_arrows_position'] : grwp_default_arrows_position() );
        if ( !in_array( $current, array('below', 'middle'), true ) ) {
            $current = grwp_default_arrows_position();
        }
        ?>
            <div class="tooltip">
                <input type="hidden" name="google_reviews_option_name[slider_arrows_position]" value="<?php 
        echo esc_attr( $current );
        ?>" />
                <select disabled>
                    <option>
                        <?php 
        echo ( 'middle' === $current ? esc_html__( 'Middle', 'embedder-for-google-reviews' ) : esc_html__( 'Below', 'embedder-for-google-reviews' ) );
        ?>
                    </option>
                </select>
                <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=arrow_position&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <?php 
    }

    public function disable_loop_slider_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <?php 
        ?>
            <div class="tooltip">
                <input type="hidden"
                       name="google_reviews_option_name[disable_loop_slider]"
                       id="disable_loop_slider"
                       value="0"
                />

                <input type="checkbox"
                       name="google_reviews_option_name[disable_loop_slider]"
                       disabled
                />
                <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=slide_duration&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    public function pause_on_hover_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <?php 
        ?>
            <div class="tooltip">
                <input type="hidden"
                       name="google_reviews_option_name[pause_on_hover]"
                       id="pause_on_hover"
                       value="0"
                />

                <input type="checkbox"
                       name="google_reviews_option_name[pause_on_hover]"
                       disabled
                />
                <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=pause_on_hover&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    public function marquee_slider_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <?php 
        ?>
            <div class="tooltip">
                <input type="hidden"
                       name="google_reviews_option_name[marquee_slider]"
                       id="marquee_slider"
                       value="0"
                />

                <input type="checkbox"
                       name="google_reviews_option_name[marquee_slider]"
                       disabled
                />
                <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=marquee_slider&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    public function marquee_speed_callback() {
        global $allowed_html;
        $value = ( isset( $this->google_reviews_options['marquee_speed'] ) ? intval( $this->google_reviews_options['marquee_speed'] ) : 5 );
        if ( $value < 1 ) {
            $value = 1;
        }
        if ( $value > 10 ) {
            $value = 10;
        }
        ob_start();
        ?>

        <?php 
        ?>
            <div class="tooltip">
                <input type="hidden"
                       name="google_reviews_option_name[marquee_speed]"
                       value="5"
                />
                <input type="number"
                       name="google_reviews_option_name[marquee_speed]"
                       id="marquee_speed"
                       value="5"
                       disabled
                />
                <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=marquee_speed&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Sanitize user input
     * @param $input
     * @return array
     */
    public function google_reviews_sanitize( $input ) {
        $sanitary_values = array();
        if ( isset( $input['show_dummy_content'] ) ) {
            $sanitary_values['show_dummy_content'] = sanitize_text_field( $input['show_dummy_content'] );
        }
        if ( isset( $input['show_verified'] ) ) {
            $sanitary_values['show_verified'] = sanitize_text_field( $input['show_verified'] );
        }
        if ( isset( $input['serp_business_name'] ) ) {
            $sanitary_values['serp_business_name'] = sanitize_text_field( $input['serp_business_name'] );
        }
        if ( isset( $input['serp_data_id'] ) ) {
            $sanitary_values['serp_data_id'] = sanitize_text_field( $input['serp_data_id'] );
        }
        // place_id and cid are saved via AJAX on business selection and have no
        // form fields, so carry the stored values forward to avoid wiping them.
        $existing_options = get_option( 'google_reviews_option_name' );
        if ( isset( $input['serp_place_id'] ) ) {
            $sanitary_values['serp_place_id'] = sanitize_text_field( $input['serp_place_id'] );
        } elseif ( isset( $existing_options['serp_place_id'] ) ) {
            $sanitary_values['serp_place_id'] = $existing_options['serp_place_id'];
        }
        if ( isset( $input['serp_cid'] ) ) {
            $sanitary_values['serp_cid'] = sanitize_text_field( $input['serp_cid'] );
        } elseif ( isset( $existing_options['serp_cid'] ) ) {
            $sanitary_values['serp_cid'] = $existing_options['serp_cid'];
        }
        if ( isset( $input['api_key_0'] ) ) {
            $sanitary_values['api_key_0'] = sanitize_text_field( $input['api_key_0'] );
        }
        if ( isset( $input['gmb_id_1'] ) ) {
            $sanitary_values['gmb_id_1'] = sanitize_text_field( $input['gmb_id_1'] );
        }
        if ( isset( $input['style_2'] ) ) {
            $sanitary_values['style_2'] = $input['style_2'];
        }
        if ( isset( $input['grid_columns'] ) ) {
            $sanitary_values['grid_columns'] = $input['grid_columns'];
        }
        if ( isset( $input['layout_style'] ) ) {
            $sanitary_values['layout_style'] = $input['layout_style'];
        }
        if ( isset( $input['show_dummy_content'] ) ) {
            $sanitary_values['show_dummy_content'] = sanitize_text_field( $input['show_dummy_content'] );
        }
        if ( isset( $input['filter_below_5_stars'] ) ) {
            $sanitary_values['filter_below_5_stars'] = sanitize_text_field( $input['filter_below_5_stars'] );
        }
        if ( isset( $input['slide_duration'] ) ) {
            $sanitary_values['slide_duration'] = sanitize_text_field( $input['slide_duration'] );
        }
        if ( isset( $input['exclude_reviews_without_text'] ) ) {
            $sanitary_values['exclude_reviews_without_text'] = $input['exclude_reviews_without_text'];
        }
        if ( isset( $input['hide_date_string'] ) ) {
            $sanitary_values['hide_date_string'] = $input['hide_date_string'];
        }
        if ( isset( $input['filter_words'] ) ) {
            $sanitary_values['filter_words'] = $input['filter_words'];
        }
        // Header type. The legacy "Hide company header section" checkbox is now
        // the "None" option: selecting None keeps the flag set (so pre-existing
        // shortcodes that hide the header via place_info stay hidden), while any
        // other type clears it. When the header settings aren't submitted the
        // stored flag is preserved.
        if ( isset( $input['header_type'] ) ) {
            $header_type = sanitize_text_field( $input['header_type'] );
            $header_type = ( in_array( $header_type, array(
                'standard',
                'compact',
                'compact_plain',
                'none'
            ), true ) ? $header_type : 'standard' );
            $sanitary_values['header_type'] = $header_type;
            if ( $header_type === 'none' ) {
                $sanitary_values['hide_company_header'] = 'on';
            }
            // Any non-none type intentionally omits hide_company_header, clearing it.
        } elseif ( !empty( $existing_options['hide_company_header'] ) ) {
            $sanitary_values['hide_company_header'] = $existing_options['hide_company_header'];
        }
        if ( isset( $input['hide_profile_picture'] ) ) {
            $sanitary_values['hide_profile_picture'] = sanitize_text_field( $input['hide_profile_picture'] );
        }
        if ( isset( $input['hide_rating_text'] ) ) {
            $sanitary_values['hide_rating_text'] = sanitize_text_field( $input['hide_rating_text'] );
        }
        if ( isset( $input['use_safe_fallback_font'] ) ) {
            $sanitary_values['use_safe_fallback_font'] = sanitize_text_field( $input['use_safe_fallback_font'] );
        }
        if ( isset( $input['button_type'] ) ) {
            $allowed_button_types = array(
                'reviews_google',
                'write_review',
                'custom',
                'none'
            );
            $button_type = sanitize_text_field( $input['button_type'] );
            $sanitary_values['button_type'] = ( in_array( $button_type, $allowed_button_types, true ) ? $button_type : 'none' );
        }
        if ( isset( $input['button_url'] ) ) {
            $sanitary_values['button_url'] = esc_url_raw( $input['button_url'] );
        }
        if ( isset( $input['button_text'] ) ) {
            $sanitary_values['button_text'] = sanitize_text_field( $input['button_text'] );
        }
        if ( isset( $input['disable_box_shadow'] ) ) {
            $sanitary_values['disable_box_shadow'] = sanitize_text_field( $input['disable_box_shadow'] );
        }
        if ( isset( $input['content_max_height'] ) ) {
            // empty keeps the per-style default height
            $sanitary_values['content_max_height'] = ( $input['content_max_height'] === '' ? '' : absint( $input['content_max_height'] ) );
        }
        if ( isset( $input['content_overflow'] ) ) {
            $overflow = sanitize_text_field( $input['content_overflow'] );
            $sanitary_values['content_overflow'] = ( in_array( $overflow, array('scrollbar', 'read_more'), true ) ? $overflow : 'scrollbar' );
        }
        if ( isset( $input['show_more_grid'] ) ) {
            $sanitary_values['show_more_grid'] = sanitize_text_field( $input['show_more_grid'] );
        }
        if ( isset( $input['show_more_grid_initial'] ) ) {
            $sanitary_values['show_more_grid_initial'] = absint( $input['show_more_grid_initial'] );
        }
        if ( isset( $input['show_more_grid_load_more_rows'] ) ) {
            $sanitary_values['show_more_grid_load_more_rows'] = absint( $input['show_more_grid_load_more_rows'] );
        }
        // The "Load more" text moved to the Translation subpage. No form field
        // writes it here anymore, so carry any legacy saved value forward (used
        // as the front-end fallback) instead of wiping it on save.
        if ( isset( $input['show_more_grid_text'] ) ) {
            $sanitary_values['show_more_grid_text'] = sanitize_text_field( $input['show_more_grid_text'] );
        } elseif ( isset( $existing_options['show_more_grid_text'] ) ) {
            $sanitary_values['show_more_grid_text'] = $existing_options['show_more_grid_text'];
        }
        if ( isset( $input['link_users_profiles'] ) ) {
            $sanitary_values['link_users_profiles'] = $input['link_users_profiles'];
        }
        if ( isset( $input['hide_slider_arrows'] ) ) {
            $sanitary_values['hide_slider_arrows'] = $input['hide_slider_arrows'];
        }
        if ( isset( $input['disable_loop_slider'] ) ) {
            $sanitary_values['disable_loop_slider'] = $input['disable_loop_slider'];
        }
        if ( isset( $input['pause_on_hover'] ) ) {
            $sanitary_values['pause_on_hover'] = $input['pause_on_hover'];
        }
        if ( isset( $input['slider_arrows_position'] ) ) {
            $position = sanitize_text_field( $input['slider_arrows_position'] );
            $sanitary_values['slider_arrows_position'] = ( in_array( $position, array('below', 'middle'), true ) ? $position : grwp_default_arrows_position() );
        }
        if ( isset( $input['marquee_slider'] ) ) {
            $sanitary_values['marquee_slider'] = $input['marquee_slider'];
        }
        if ( isset( $input['marquee_speed'] ) ) {
            $speed = absint( $input['marquee_speed'] );
            $sanitary_values['marquee_speed'] = min( 10, max( 1, $speed ) );
        }
        if ( isset( $input['reviews_language_3'] ) ) {
            $sanitary_values['reviews_language_3'] = $input['reviews_language_3'];
        }
        return $sanitary_values;
    }

    public function google_reviews_section_info() {
        ?>
        <h2 id="connect_settings"><?php 
        esc_html_e( 'Global settings for showing reviews', 'embedder-for-google-reviews' );
        ?></h2>

        <?php 
    }

    public function google_reviews_display_section_info() {
        ?>
        <h2 id="display_settings"><?php 
        esc_html_e( 'Display settings', 'embedder-for-google-reviews' );
        ?></h2>

        <?php 
    }

    public function google_reviews_display_slider_info() {
        ?>
        <h2 id="slider_settings"><?php 
        esc_html_e( 'Slider settings', 'embedder-for-google-reviews' );
        ?></h2>

        <?php 
    }

    /**
     * Show dummy content
     * @return void
     */
    public function show_dummy_content_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[show_dummy_content]"
               value="1"
               id="show_dummy_content"
            <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['show_dummy_content'] ) ? 'checked' : '' ) );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Hide reviewer profile picture
     * @return void
     */
    public function hide_profile_picture_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[hide_profile_picture]"
               value="1"
               id="hide_profile_picture"
            <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['hide_profile_picture'] ) ? 'checked' : '' ) );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Hide rating text
     * @return void
     */
    public function hide_rating_text_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[hide_rating_text]"
               value="1"
               id="hide_rating_text"
            <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['hide_rating_text'] ) ? 'checked' : '' ) );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Use safe fallback font
     * @return void
     */
    public function use_safe_fallback_font_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[use_safe_fallback_font]"
               value="1"
               id="use_safe_fallback_font"
            <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['use_safe_fallback_font'] ) ? 'checked' : '' ) );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Button link target. Drives whether the custom URL/text rows are shown
     * (via JS) and how the front-end button URL is built.
     * @return void
     */
    public function button_type_callback() {
        $opt = $this->google_reviews_options;
        // Migrate installs saved before this setting existed: a configured
        // custom URL maps to 'custom', otherwise default to "Write a review".
        $current = ( !empty( $opt['button_type'] ) ? $opt['button_type'] : (( !empty( $opt['button_url'] ) ? 'custom' : 'write_review' )) );
        $has_place_id = !empty( $opt['serp_place_id'] );
        $needs_place = in_array( $current, array('reviews_google', 'write_review'), true );
        ?>

        <select name="google_reviews_option_name[button_type]" id="button_type" class="js-button-type">
            <option value="reviews_google" <?php 
        selected( $current, 'reviews_google' );
        ?>>
                <?php 
        esc_html_e( 'Link to Google Profile', 'embedder-for-google-reviews' );
        ?>
            </option>
            <option value="write_review" <?php 
        selected( $current, 'write_review' );
        ?>>
                <?php 
        esc_html_e( 'Link to "Write a review"', 'embedder-for-google-reviews' );
        ?>
            </option>
            <option value="custom" <?php 
        selected( $current, 'custom' );
        ?>>
                <?php 
        esc_html_e( 'Custom URL & text', 'embedder-for-google-reviews' );
        ?>
            </option>
            <option value="none" <?php 
        selected( $current, 'none' );
        ?>>
                <?php 
        esc_html_e( 'No button', 'embedder-for-google-reviews' );
        ?>
            </option>
        </select>

        <?php 
        if ( !$has_place_id ) {
            ?>
            <p class="description js-button-place-hint"
               style="margin-top:6px;<?php 
            echo ( $needs_place ? '' : 'display:none;' );
            ?>">
                <?php 
            esc_html_e( 'Re-select your business above to enable this link.', 'embedder-for-google-reviews' );
            ?>
            </p>
        <?php 
        }
        ?>

        <?php 
    }

    /**
     * Button URL, used only when the Button option is set to "Custom URL & text"
     * @return void
     */
    public function button_url_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="url"
               name="google_reviews_option_name[button_url]"
               id="button_url"
               placeholder="https://"
               value="<?php 
        echo esc_url( ( !empty( $this->google_reviews_options['button_url'] ) ? $this->google_reviews_options['button_url'] : '' ) );
        ?>"
        >

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Button text, shown on the button rendered below each widget
     * @return void
     */
    public function button_text_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="text"
               name="google_reviews_option_name[button_text]"
               id="button_text"
               placeholder="<?php 
        esc_attr_e( 'See all Reviews', 'embedder-for-google-reviews' );
        ?>"
               value="<?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['button_text'] ) ? $this->google_reviews_options['button_text'] : '' ) );
        ?>"
        >

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Remove card box shadows
     * @return void
     */
    public function disable_box_shadow_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[disable_box_shadow]"
               value="1"
               id="disable_box_shadow"
            <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['disable_box_shadow'] ) ? 'checked' : '' ) );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Show dummy content
     * @return void
     */
    public function show_verified_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[show_verified]"
               value="1"
               id="show_verified"
            <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['show_verified'] ) ? 'checked' : '' ) );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Filter below 5 stars
     * @return void
     */
    public function filter_below_5_stars_callback() {
        global $allowed_html;
        ob_start();
        ?>
        <?php 
        ?>
        <div class="tooltip">
        <?php 
        ?>

            <input type="number"
                   name="google_reviews_option_name[filter_below_5_stars]"
                   id="filter_below_5_stars"
                   min="1"
                   max="5"
                   step="1"
                   value="<?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['filter_below_5_stars'] ) ? $this->google_reviews_options['filter_below_5_stars'] : '1' ) );
        ?>"
                   <?php 
        echo ( !grwp_fs()->is__premium_only() ? 'disabled' : '' );
        ?>
            />

        <?php 
        ?>
            <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=minimum_rating&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
        </div>
	    <?php 
        ?>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Link to User's Google Profiles
     * @return void
     */
    public function link_users_profiles_callback() {
        global $allowed_html;
        ob_start();
        ?>
        <?php 
        ?>
            <div class="tooltip">
        <?php 
        ?>

        <?php 
        ?>
            <input type="hidden"
                   name="google_reviews_option_name[link_users_profiles]"
                   id="link_users_profiles"
                   value="1"
            />

            <input type="checkbox"
                   name="google_reviews_option_name[link_users_profiles]"
                   id="link_users_profiles"
                   checked
                   disabled
            />
        <?php 
        ?>

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        ?>
            <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=link_profile&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
        <?php 
        ?>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Exclude reviews without text
     * @return void
     */
    public function exclude_reviews_without_text_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <?php 
        ?>
        <div class="tooltip">
        <?php 
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[exclude_reviews_without_text]"
               value="1"
               id="exclude_reviews_without_text"
               <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['exclude_reviews_without_text'] ) ? 'checked' : '' ) );
        ?>
               <?php 
        echo ( grwp_fs()->is__premium_only() ? '' : 'disabled' );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

	    <?php 
        ?>
            <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=textless_reviews&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
	    <?php 
        ?>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Hide date string
     * @return void
     */
    public function hide_date_string_callback() {
        global $allowed_html;
        ob_start();
        ?>

		<?php 
        ?>
            <div class="tooltip">
		<?php 
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[hide_date_string]"
               value="1"
               id="hide_date_string"
			<?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['hide_date_string'] ) ? 'checked' : '' ) );
        ?>
			<?php 
        echo ( grwp_fs()->is__premium_only() ? '' : 'disabled' );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

		<?php 
        ?>
            <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=textless_reviews&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
		<?php 
        ?>

		<?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Filter specific words
     * @return void
     */
    public function filter_words_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <?php 
        ?>
        <div class="tooltip">
        <?php 
        ?>

        <textarea
           name="google_reviews_option_name[filter_words]"
           id="filter_words"
           rows="2"
           <?php 
        echo ( grwp_fs()->is__premium_only() ? '' : 'disabled' );
        ?>
        ><?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['filter_words'] ) ? $this->google_reviews_options['filter_words'] : '' ) );
        ?></textarea>

	    <?php 
        ?>
            <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=filter_words&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
            </div>
	    <?php 
        ?>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Show "Load more" button on grid widget
     * @return void
     */
    public function show_more_grid_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="checkbox"
               name="google_reviews_option_name[show_more_grid]"
               value="1"
               id="show_more_grid"
            <?php 
        echo esc_attr( ( !empty( $this->google_reviews_options['show_more_grid'] ) ? 'checked' : '' ) );
        ?>
        >

        <span>
            <?php 
        esc_html_e( 'Yes', 'embedder-for-google-reviews' );
        ?>
        </span>

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Number of review rows visible before "Load more" is clicked
     * @return void
     */
    public function show_more_grid_initial_callback() {
        global $allowed_html;
        $value = ( isset( $this->google_reviews_options['show_more_grid_initial'] ) ? intval( $this->google_reviews_options['show_more_grid_initial'] ) : 2 );
        if ( $value < 1 ) {
            $value = 2;
        }
        ob_start();
        ?>

        <input type="number"
               name="google_reviews_option_name[show_more_grid_initial]"
               id="show_more_grid_initial"
               min="1"
               step="1"
               value="<?php 
        echo esc_attr( $value );
        ?>"
        >

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Number of additional review rows revealed each time "Load more" is clicked
     * @return void
     */
    public function show_more_grid_load_more_rows_callback() {
        global $allowed_html;
        $value = ( isset( $this->google_reviews_options['show_more_grid_load_more_rows'] ) ? intval( $this->google_reviews_options['show_more_grid_load_more_rows'] ) : 2 );
        if ( $value < 1 ) {
            $value = 2;
        }
        ob_start();
        ?>

        <input type="number"
               name="google_reviews_option_name[show_more_grid_load_more_rows]"
               id="show_more_grid_load_more_rows"
               min="1"
               step="1"
               value="<?php 
        echo esc_attr( $value );
        ?>"
        >

        <?php 
        $html = ob_get_clean();
        echo wp_kses( $html, $allowed_html );
    }

    /**
     * Echo layout option field
     */
    public function style_2_callback() {
        ?> <select name="google_reviews_option_name[style_2]" id="style_2">
            <?php 
        $selected = ( isset( $this->google_reviews_options['style_2'] ) && $this->google_reviews_options['style_2'] === 'Slider' ? 'selected' : '' );
        ?>
            <option <?php 
        echo esc_attr( $selected );
        ?> value="Slider">
                <?php 
        esc_html_e( 'Slider', 'embedder-for-google-reviews' );
        ?>
            </option>
            <?php 
        $selected = ( isset( $this->google_reviews_options['style_2'] ) && $this->google_reviews_options['style_2'] === 'Grid' ? 'selected' : '' );
        ?>
            <option <?php 
        echo esc_attr( $selected );
        ?> value="Grid">
                <?php 
        esc_html_e( 'Grid', 'embedder-for-google-reviews' );
        ?>
            </option>

            <?php 
        ?>

            <option disabled value="Badge">
			    <?php 
        esc_html_e( 'Floating Badge (PRO)', 'embedder-for-google-reviews' );
        ?>
            </option>

            <?php 
        ?>

        </select> <?php 
    }

    public function grid_columns_callback() {
        $columns = $this->google_reviews_options['grid_columns'] ?? '';
        if ( empty( $columns ) ) {
            $columns = 3;
        }
        ?>

        <select name="google_reviews_option_name[grid_columns]" id="grid_columns">
            <option <?php 
        selected( $columns, '1' );
        ?> value="1"><?php 
        esc_attr_e( '1', 'embedder-for-google-reviews' );
        ?></option>
            <option <?php 
        selected( $columns, '2' );
        ?> value="2"><?php 
        esc_attr_e( '2', 'embedder-for-google-reviews' );
        ?></option>
            <option <?php 
        selected( $columns, '3' );
        ?> value="3"><?php 
        esc_attr_e( '3', 'embedder-for-google-reviews' );
        ?></option>
        </select>

        <?php 
    }

    /**
     * Legacy settings tab (Layout type + Design type)
     * Kept for users who rely on the saved values without shortcode attributes.
     */
    private function add_legacy_settings() {
        add_settings_section(
            'google_reviews_legacy_setting_section',
            '',
            array($this, 'google_reviews_display_legacy_info'),
            $this->settings_slug
        );
        add_settings_field(
            'layout_style',
            __( 'Design type', 'embedder-for-google-reviews' ),
            array($this, 'layout_style_callback'),
            $this->settings_slug,
            'google_reviews_legacy_setting_section'
        );
    }

    public function google_reviews_display_legacy_info() {
        ?>
        <h2 id="legacy_settings"><?php 
        esc_html_e( 'Legacy options', 'embedder-for-google-reviews' );
        ?></h2>
        <p style="color:#64748b;font-size:.85rem;margin:0 0 8px;">
            <?php 
        esc_html_e( 'These settings apply globally for users who embed the widget without shortcode attributes. New installations should use the shortcode attributes instead.', 'embedder-for-google-reviews' );
        ?>
        </p>
        <?php 
    }

    public function layout_style_callback() {
        $layout_style = ( isset( $this->google_reviews_options['layout_style'] ) ? $this->google_reviews_options['layout_style'] : '' );
        if ( empty( $layout_style ) ) {
            $layout_style = 'layout_style-7';
        }
        ?>
        <select name="google_reviews_option_name[layout_style]" id="layout_style">
            <?php 
        for ($i = 1; $i <= 11; $i++) {
            ?>
                <option
                    <?php 
            selected( $layout_style, 'layout_style-' . $i );
            ?>
                    value="<?php 
            echo esc_attr( sprintf( 'layout_style-%s', $i ) );
            ?>"
                >
                    <?php 
            printf( 
                /* translators: Design */
                esc_html__( 'Design #%s', 'embedder-for-google-reviews' ),
                esc_html( $i )
             );
            ?>
                </option>
            <?php 
        }
        ?>
        </select>
        <?php 
    }

    /**
     * Echo language field
     */
    public function reviews_language_3_callback() {
        $languages = [
            'en'      => 'English',
            'ar'      => 'Arabic',
            'bg'      => 'Bulgarian',
            'bn'      => 'Bengali',
            'ca'      => 'Catalan',
            'cs'      => 'Czech',
            'da'      => 'Danish',
            'de'      => 'German',
            'el'      => 'Greek',
            'es'      => 'Spanish',
            'es-419'  => 'Spanish (Latin America)',
            'eu'      => 'Basque',
            'fa'      => 'Farsi',
            'fi'      => 'Finnish',
            'fil'     => 'Filipino',
            'fr'      => 'French',
            'gl'      => 'Galician',
            'gu'      => 'Gujarati',
            'hi'      => 'Hindi',
            'hr'      => 'Croatian',
            'hu'      => 'Hungarian',
            'id'      => 'Indonesian',
            'it'      => 'Italian',
            'iw'      => 'Hebrew',
            'ja'      => 'Japanese',
            'kn'      => 'Kannada',
            'ko'      => 'Korean',
            'lt'      => 'Lithuanian',
            'lv'      => 'Latvian',
            'ml'      => 'Malayalam',
            'mr'      => 'Marathi',
            'nl'      => 'Dutch',
            'no'      => 'Norwegian',
            'pl'      => 'Polish',
            'pt'      => 'Portuguese',
            'pt-BR'   => 'Portuguese (Brazil)',
            'pt-PT'   => 'Portuguese (Portugal)',
            'ro'      => 'Romanian',
            'ru'      => 'Russian',
            'sk'      => 'Slovak',
            'sl'      => 'Slovenian',
            'sr'      => 'Serbian',
            'sv'      => 'Swedish',
            'ta'      => 'Tamil',
            'te'      => 'Telugu',
            'th'      => 'Thai',
            'tl'      => 'Tagalog',
            'tr'      => 'Turkish',
            'uk'      => 'Ukrainian',
            'vi'      => 'Vietnamese',
            'zh'      => 'Chinese (Simplified)',
            'zh-HK'   => 'Chinese (Hongkong)',
            'zh-Hant' => 'Chinese (Traditional)',
        ];
        $current = ( isset( $this->google_reviews_options['reviews_language_3'] ) ? $this->google_reviews_options['reviews_language_3'] : 'en' );
        ?>
        <select name="google_reviews_option_name[reviews_language_3]" id="reviews_language_3">
            <option value="">Choose language</option>
            <?php 
        foreach ( $languages as $key => $language ) {
            if ( $key === $current ) {
                echo '<option value="' . esc_attr( $key ) . '" selected>' . esc_attr( $language ) . '</option>';
            } else {
                echo '<option value="' . esc_attr( $key ) . '">' . esc_attr( $language ) . '</option>';
            }
        }
        ?>
        </select> <?php 
    }

    /**
     * Echo shortcode instructions
     */
    public function reviews_instructions_callback() {
        ?>
        <div id="instructions">
            <p>
                <?php 
        esc_html_e( 'Use this shortcode to show your reviews on pages and posts:', 'embedder-for-google-reviews' );
        ?>
            </p>
            <input class="shortcode-container" type="text" disabled="" value="[google-reviews]">
            <p>
                <?php 
        echo sprintf( 
            /* translators: %s: URL */
            esc_html__( '<a href="%s" target="_blank">See</a>, how to overwrite styles, widget types and other settings.', 'embedder-for-google-reviews' ),
            esc_url( "https://reviewsembedder.com/docs/how-to-overwrite-styles/?utm_source=wp_backend&utm_medium=instructions&utm_campaign=overwrite_styles_types" )
         );
        ?>
            </p>
        </div>

        <?php 
    }

}
