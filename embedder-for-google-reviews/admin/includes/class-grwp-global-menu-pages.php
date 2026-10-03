<?php

Class GRWP_Global_Menu_Pages {

    protected $google_reviews_options;

    public function __construct() {
        $this->add_menu_pages();
    }

    private function add_menu_pages() {

        add_menu_page(
                /* translators: page title */
            __( 'Google Reviews', 'embedder-for-google-reviews' ),
            /* translators: menu title */
            __( 'Google Reviews', 'embedder-for-google-reviews' ),
            'manage_options', // capability
            'google-reviews', // menu_slug
            array( $this, 'google_reviews_create_admin_page' ), // function
            'dashicons-star-filled', // icon_url
            75 // position
        );

        add_submenu_page(
            'google-reviews', // parent_slug
            /* translators: page title */
            __( 'Translation', 'embedder-for-google-reviews' ),
            /* translators: menu title */
            __( 'Translation', 'embedder-for-google-reviews' ),
            'manage_options', // capability
            'google-reviews-translation', // menu_slug
            array( $this, 'google_reviews_create_translation_page' ) // function
        );

    }

    /**
     * Translation subpage: lets the user override the plugin's front-end
     * strings, regardless of whether a translation exists for the site locale.
     */
    public function google_reviews_create_translation_page() {

        $overrides = get_option( 'grwp_string_overrides' );
        $overrides = is_array( $overrides ) ? $overrides : array();
        $strings   = grwp_translatable_strings();
        ?>

        <div class="wrap grwp-outer-wrap">
            <h2 style="display:none;"></h2><!-- WP-Notices-Anker -->

            <div id="grwp-notices">
                <?php settings_errors(); ?>
            </div>

            <div id="grwp-dashboard">

                <div id="grwp-header">
                    <h1><?php esc_html_e( 'Translation', 'embedder-for-google-reviews' ); ?></h1>
                    <div class="grwp-header-right">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=google-reviews' ) ); ?>" class="grwp-status-badge">
                            ← <?php esc_html_e( 'Back to settings', 'embedder-for-google-reviews' ); ?>
                        </a>
                    </div>
                </div>

                <form method="post" action="options.php">
                    <?php settings_fields( 'grwp_string_overrides_group' ); ?>

                    <div id="grwp-main-card">

                        <div class="grwp-tab-panel active" role="tabpanel" style="display:block;">
                            <h2><?php esc_html_e( 'Text overrides', 'embedder-for-google-reviews' ); ?></h2>
                            <p style="color:#64748b;font-size:.85rem;margin:0 0 8px;">
                                <?php esc_html_e( 'Override the texts shown in your widgets. Leave a field empty to use the default (translated) text.', 'embedder-for-google-reviews' ); ?>
                            </p>

                            <table class="form-table grwp-form-table" role="presentation"><tbody>
                                <?php foreach ( $strings as $key => $string ) : ?>
                                    <tr>
                                        <th scope="row">
                                            <label for="grwp_string_<?php echo esc_attr( $key ); ?>">
                                                <?php echo esc_html( $string['label'] ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text"
                                                   class="regular-text"
                                                   name="grwp_string_overrides[<?php echo esc_attr( $key ); ?>]"
                                                   id="grwp_string_<?php echo esc_attr( $key ); ?>"
                                                   placeholder="<?php echo esc_attr( $string['label'] ); ?>"
                                                   value="<?php echo esc_attr( isset( $overrides[ $key ] ) ? $overrides[ $key ] : '' ); ?>"
                                            >
                                            <?php if ( ! empty( $string['description'] ) ) : ?>
                                                <p class="description"><?php echo esc_html( $string['description'] ); ?></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody></table>
                        </div>

                        <div id="grwp-save-row">
                            <?php submit_button( null, 'primary', 'submit', false ); ?>
                            <span style="color:#64748b;font-size:.82rem;">
                                <?php esc_html_e( 'Changes apply to all widgets on your site.', 'embedder-for-google-reviews' ); ?>
                            </span>
                        </div>

                    </div><!-- /#grwp-main-card -->
                </form>

            </div><!-- /#grwp-dashboard -->
        </div><!-- /.grwp-outer-wrap -->

        <?php
    }

    /**
     * Create admin page on backend
     */
    public function google_reviews_create_admin_page() {
        global $allowed_html;


        $settings             = $this->google_reviews_options = get_option( 'google_reviews_option_name' );
        $widget_type          = isset( $settings['style_2'] ) ? $settings['style_2'] : 'Slider';
        $is_connected         = ! empty( $settings['serp_business_name'] ) || ! empty( $settings['gmb_id_1'] );
        $reviews_loaded       = GRWP_Pro_API_Service::parse_pro_review_json() !== null;
        $is_premium           = grwp_fs()->is__premium_only();
        $docs                 = 'https://reviewsembedder.com/docs/how-to-overwrite-styles/?utm_source=wp_backend&utm_medium=preview&utm_campaign=docs';
        $upgrade_url          = 'https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=upgrade_tab&utm_campaign=upgrade';

        // Preview-only type switcher — does NOT modify the saved option.
        $allowed_preview = array( 'Slider', 'Grid', 'Badge' );
        $preview_type    = isset( $_GET['preview_type'] )
            ? sanitize_text_field( wp_unslash( $_GET['preview_type'] ) )
            : $widget_type;
        if ( ! in_array( $preview_type, $allowed_preview, true ) ) {
            $preview_type = $widget_type;
        }

        // Simple URLs — no nonce needed since nothing is saved.
        $base_url     = admin_url( 'admin.php?page=google-reviews' );
        $url_slider   = $base_url . '&preview_type=Slider';
        $url_grid     = $base_url . '&preview_type=Grid';
        $url_badge    = $base_url . '&preview_type=Badge';
        ?>

        <!-- WP/Freemius braucht .wrap als Ziel fuer admin_notices und JS-Notices -->
        <div class="wrap grwp-outer-wrap">
            <h2 style="display:none;"></h2><!-- WP-Notices-Anker -->

        <div id="grwp-notices">
            <?php settings_errors(); ?>
        </div>

        <div id="grwp-dashboard">

            <!-- ── Header ── -->
            <div id="grwp-header">
                <h1>
                    <?php esc_html_e( 'Google Reviews', 'embedder-for-google-reviews' ); ?>
                    <?php if ( $is_premium ) : ?>
                        <span style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:4px;font-size:.7rem;font-weight:700;padding:2px 8px;">PRO</span>
                    <?php endif; ?>
                </h1>
                <div class="grwp-header-right">
                    <?php if ( $is_connected && $reviews_loaded ) : ?>
                        <span class="grwp-status-badge">
                            <?php esc_html_e( 'Connected & reviews loaded', 'embedder-for-google-reviews' ); ?>
                        </span>
                    <?php elseif ( $is_connected ) : ?>
                        <span class="grwp-status-badge disconnected">
                            ⚡ <?php esc_html_e( 'Business set – pull reviews first', 'embedder-for-google-reviews' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="grwp-status-badge disconnected">

                        </span>
                    <?php endif; ?>
                    <?php if ( ! $is_premium ) : ?>
                        <a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="grwp-upgrade-btn">
                            🗲 <?php esc_html_e( ' Upgrade to PRO', 'embedder-for-google-reviews' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Main settings card ── -->
            <form method="post" action="options.php">
                <?php
                // Alle Felder schreiben in google_reviews_option_name –
                // daher genuegt ein einziger settings_fields()-Aufruf
                settings_fields( 'google_reviews_option_group' );
                ?>
                <div id="grwp-main-card">

                    <!-- Tabs -->
                    <div id="grwp-tabs" role="tablist">
                        <button type="button" class="grwp-tab-btn active" data-tab="connect" role="tab">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                            <?php esc_html_e( 'Connect Google', 'embedder-for-google-reviews' ); ?>
                        </button>
                        <button type="button" class="grwp-tab-btn" data-tab="display" role="tab">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php esc_html_e( 'Display Settings', 'embedder-for-google-reviews' ); ?>
                        </button>
                        <button type="button" class="grwp-tab-btn" data-tab="header" role="tab">
                            <span class="dashicons dashicons-cover-image"></span>
                            <?php esc_html_e( 'Header Settings', 'embedder-for-google-reviews' ); ?>
                        </button>
                        <button type="button" class="grwp-tab-btn" data-tab="slider" role="tab">
                            <span class="dashicons dashicons-slides"></span>
                            <?php esc_html_e( 'Slider Settings', 'embedder-for-google-reviews' ); ?>
                        </button>
                        <button type="button" class="grwp-tab-btn" data-tab="grid" role="tab">
                            <span class="dashicons dashicons-grid-view"></span>
                            <?php esc_html_e( 'Grid Settings', 'embedder-for-google-reviews' ); ?>
                        </button>
                        <button type="button" class="grwp-tab-btn" data-tab="legacy" role="tab">
                            <span class="dashicons dashicons-backup"></span>
                            <?php esc_html_e( 'Legacy Options', 'embedder-for-google-reviews' ); ?>
                        </button>
                        <!-- Link tab (no data-tab): navigates to the Translation subpage -->
                        <a class="grwp-tab-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=google-reviews-translation' ) ); ?>">
                            <span class="dashicons dashicons-translation"></span>
                            <?php esc_html_e( 'Translation', 'embedder-for-google-reviews' ); ?>
                        </a>
                    </div>

                    <!-- Panel: Connect Google -->
                    <div id="grwp-panel-connect" class="grwp-tab-panel active" role="tabpanel">
                        <table class="form-table grwp-form-table" role="presentation"><tbody>
                            <?php do_settings_fields( 'google-reviews-admin', 'google_reviews_setting_section' ); ?>
                        </tbody></table>
                    </div>

                    <!-- Panel: Display Settings -->
                    <div id="grwp-panel-display" class="grwp-tab-panel" role="tabpanel">
                        <div class="grwp-two-col">
                            <table class="form-table grwp-form-table" role="presentation"><tbody>
                                <?php do_settings_fields( 'google-reviews-admin', 'google_reviews_style_layout_setting_section_inputs' ); ?>
                            </tbody></table>
                            <table class="form-table grwp-form-table" role="presentation"><tbody>
                                <?php do_settings_fields( 'google-reviews-admin', 'google_reviews_style_layout_setting_section' ); ?>
                            </tbody></table>
                        </div>
                    </div>

                    <!-- Panel: Header Settings -->
                    <div id="grwp-panel-header" class="grwp-tab-panel" role="tabpanel">
                        <table class="form-table grwp-form-table" role="presentation"><tbody>
                            <?php do_settings_fields( 'google-reviews-admin', 'google_reviews_header_setting_section' ); ?>
                        </tbody></table>
                    </div>

                    <!-- Panel: Slider Settings -->
                    <div id="grwp-panel-slider" class="grwp-tab-panel" role="tabpanel">
                        <table class="form-table grwp-form-table" role="presentation"><tbody>
                            <?php do_settings_fields( 'google-reviews-admin', 'google_reviews_slider_setting_section' ); ?>
                        </tbody></table>
                    </div>

                    <!-- Panel: Grid Settings -->
                    <div id="grwp-panel-grid" class="grwp-tab-panel" role="tabpanel">
                        <table class="form-table grwp-form-table" role="presentation"><tbody>
                            <?php do_settings_fields( 'google-reviews-admin', 'google_reviews_grid_setting_section' ); ?>
                        </tbody></table>
                    </div>

                    <!-- Panel: Legacy Options -->
                    <div id="grwp-panel-legacy" class="grwp-tab-panel" role="tabpanel">
                        <table class="form-table grwp-form-table" role="presentation"><tbody>
                            <?php do_settings_fields( 'google-reviews-admin', 'google_reviews_legacy_setting_section' ); ?>
                        </tbody></table>
                    </div>

                    <!-- Save row -->
                    <div id="grwp-save-row">
                        <?php submit_button( null, 'primary', 'submit', false ); ?>
                        <span style="color:#64748b;font-size:.82rem;">
                            <?php esc_html_e( 'Changes apply to all widgets on your site.', 'embedder-for-google-reviews' ); ?>
                        </span>
                    </div>

                </div><!-- /#grwp-main-card -->
            </form>

            <!-- ── Layout type switcher ── -->
            <div id="grwp-layout-switcher-section">
                <div class="grwp-layout-toggle">
                    <a href="<?php echo esc_url( $url_slider ); ?>"
                       class="grwp-layout-btn<?php echo $preview_type === 'Slider' ? ' active' : ''; ?>">
                        <?php esc_html_e( 'Slider', 'embedder-for-google-reviews' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $url_grid ); ?>"
                       class="grwp-layout-btn<?php echo $preview_type === 'Grid' ? ' active' : ''; ?>">
                        <?php esc_html_e( 'Grid', 'embedder-for-google-reviews' ); ?>
                    </a>
                    <?php if ( $is_premium ) : ?>
                    <a href="<?php echo esc_url( $url_badge ); ?>"
                       class="grwp-layout-btn<?php echo $preview_type === 'Badge' ? ' active' : ''; ?>">
                        <?php esc_html_e( 'Badge', 'embedder-for-google-reviews' ); ?>
                    </a>
                    <?php else : ?>
                    <!-- Free version: Badge preview is a PRO feature — shown but deactivated -->
                    <div class="tooltip grwp-layout-tooltip">
                        <span class="grwp-layout-btn grwp-layout-btn--disabled">
                            <?php esc_html_e( 'Badge', 'embedder-for-google-reviews' ); ?>
                        </span>
                        <span class="tooltiptext">PRO Feature <br> <a href="https://reviewsembedder.com/?utm_source=wp_backend&utm_medium=badge_preview&utm_campaign=upgrade" target="_blank">⚡ Upgrade now</a></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Preview (collapsible) ── -->
            <div id="grwp-preview-toggle">
                <button type="button" id="grwp-preview-toggle-btn">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e( 'Widget preview', 'embedder-for-google-reviews' ); ?>
                    <span id="grwp-preview-arrow" style="margin-left:auto;">▲</span>
                </button>
                <div id="grwp-preview-body">
                    <?php
                    // Respect the Header type "None" setting (which absorbs the
                    // legacy "Hide company header" flag) in preview shortcodes.
                    $place_info = grwp_resolve_header_type( $settings ) === 'none' ? 'false' : 'true';

                    if ( $preview_type === 'Slider' || $preview_type === 'Grid' ) :

                        // Order the styles appear in the preview (top to bottom).
                        // Edit this array to re-order; any installed style not
                        // listed here is appended afterwards in numeric order.
                        $preview_style_order = array( 10, 9, 7, 11, 6, 5, 8, 4, 3, 2, 1 );

                        $all_styles  = range( 1, 11 );
                        $preview_styles = array_merge(
                            array_intersect( $preview_style_order, $all_styles ),
                            array_diff( $all_styles, $preview_style_order )
                        );

                        foreach ( $preview_styles as $x ) :
                            ?>

                            <div class="preview_section">
                                <?php if ( $preview_type === 'Slider' ) : ?>
                                    <div class="preview_section-header">
                                        <label>
                                            <?php printf(
                                                /* translators: %1$s style number, %2$s docs link */
                                                esc_html__( 'Style #%1$s – shortcode (%2$s)', 'embedder-for-google-reviews' ),
                                                esc_html( $x ),
                                                '<a href="' . esc_url( $docs ) . '" target="_blank">' . esc_html__( 'Docs', 'embedder-for-google-reviews' ) . '</a>'
                                            ); ?>
                                        </label>
                                    </div>
                                    <div class="preview_section-shortcode">
                                        <input type="text" readonly value="[google-reviews type='slider' place_info='<?php echo esc_attr( $place_info ); ?>' style='<?php echo esc_attr( $x ); ?>']">
                                        <button class="grwp-copy-btn" type="button" data-clipboard="[google-reviews type='slider' place_info='<?php echo esc_attr( $place_info ); ?>' style='<?php echo esc_attr( $x ); ?>']">
                                            📋 <span class="copy-btn"><?php esc_html_e( 'Copy', 'embedder-for-google-reviews' ); ?></span>
                                        </button>
                                    </div>
                                    <p class="grwp-preview-notice">
                                        <?php esc_html_e( 'This preview displays up to 20 reviews. More reviews will be visible on the website.', 'embedder-for-google-reviews' ); ?>
                                    </p>
                                    <?php
                                    // Output is already wp_kses'd inside the render method.
                                    // max_reviews=20 keeps the preview light and prevents timeout.
                                    echo do_shortcode( '[google-reviews type="slider" max_reviews="20" place_info="' . esc_attr( $place_info ) . '" style="' . esc_attr( $x ) . '"]' );
                                    ?>
                                <?php else : ?>
                                    <div class="preview_section-header">
                                        <label>
                                            <?php printf(
                                                /* translators: %1$s style number, %2$s docs link */
                                                esc_html__( 'Style #%1$s – shortcode (%2$s)', 'embedder-for-google-reviews' ),
                                                esc_html( $x ),
                                                '<a href="' . esc_url( $docs ) . '" target="_blank">' . esc_html__( 'Docs', 'embedder-for-google-reviews' ) . '</a>'
                                            ); ?>
                                        </label>
                                    </div>
                                    <div class="preview_section-shortcode">
                                        <input type="text" readonly value="[google-reviews type='grid' place_info='<?php echo esc_attr( $place_info ); ?>' style='<?php echo esc_attr( $x ); ?>']">
                                        <button class="grwp-copy-btn" type="button" data-clipboard="[google-reviews type='grid' place_info='<?php echo esc_attr( $place_info ); ?>' style='<?php echo esc_attr( $x ); ?>']">
                                            📋 <span class="copy-btn"><?php esc_html_e( 'Copy', 'embedder-for-google-reviews' ); ?></span>
                                        </button>
                                    </div>
                                    <p class="grwp-preview-notice">
                                        <?php esc_html_e( 'This preview displays up to 20 reviews. More reviews will be visible on the website.', 'embedder-for-google-reviews' ); ?>
                                    </p>
                                    <?php
                                    // max_reviews=20 keeps the preview light and prevents timeout.
                                    echo do_shortcode( '[google-reviews type="grid" max_reviews="20" place_info="' . esc_attr( $place_info ) . '" style="' . esc_attr( $x ) . '"]' );
                                    ?>
                                <?php endif; ?>
                            </div>

                        <?php endforeach;

                    else :                        if ( grwp_fs()->can_use_premium_code() ) : ?>
                            <div class="preview_section">
                                <div class="preview_section-header">
                                    <label>
                                        <?php printf(
                                            /* translators: %1$s docs link */
                                            esc_html__( 'Floating Badge – shortcode (%1$s)', 'embedder-for-google-reviews' ),
                                            '<a href="' . esc_url( $docs ) . '" target="_blank">' . esc_html__( 'Docs', 'embedder-for-google-reviews' ) . '</a>'
                                        ); ?>
                                    </label>
                                </div>
                                <div class="preview_section-shortcode">
                                    <input type="text" readonly value="[google-reviews type='badge']">
                                    <button class="grwp-copy-btn" type="button" data-clipboard="[google-reviews type='badge']">
                                        📋 <span class="copy-btn"><?php esc_html_e( 'Copy', 'embedder-for-google-reviews' ); ?></span>
                                    </button>
                                </div>
                                <?php echo do_shortcode( '[google-reviews type="badge"]' ); ?>
                            </div>
                        <?php else : ?>
                            <div class="preview_section">
                                <p>
                                    <?php printf(
                                        /* translators: %s upgrade link */
                                        esc_html__( 'Floating Badge preview requires PRO. %s', 'embedder-for-google-reviews' ),
                                        '<a href="' . esc_url( $upgrade_url ) . '" target="_blank">' . esc_html__( 'Upgrade now', 'embedder-for-google-reviews' ) . '</a>'
                                    ); ?>
                                </p>
                            </div>
                        <?php endif;

                    endif;
                    ?>
                </div><!-- /#grwp-preview-body -->
            </div><!-- /#grwp-preview-toggle -->

        </div><!-- /#grwp-dashboard -->
        </div><!-- /.grwp-outer-wrap -->

        <script>
        (function($) {
            // JS ist in src/js/admin/google-reviews-admin.js und wird per Build kompiliert
        }(jQuery));
        </script>

    <?php }
}
