<?php

Class GRWP_Pro_Settings {

    private $google_reviews_options;

    public function __construct() {

        $this->google_reviews_options = get_option( 'google_reviews_option_name' );
        $this->add_settings();

    }

    private function add_settings() {

        add_settings_field(
            'serp_business_name', // id
            __( 'Search for your business:', 'embedder-for-google-reviews' ), // title
            array( $this, 'serp_business_name_callback' ), // callback
            'google-reviews-admin', // page
            'google_reviews_setting_section' // section
        );

        add_settings_field(
            'serp_data_id', // id
            false, // title
            array( $this, 'serp_data_id_callback' ), // callback
            'google-reviews-admin', // page
            'google_reviews_setting_section', // section
            array( 'class' => 'hidden' )
        );

    }




    /**
     * Echo Business Search Field
     */
    public function serp_business_name_callback() {
        global $allowed_html;
        ob_start();

        $search_disabled = '';
        $pull_button_disabled = '';

        // If business is already saved, disable 'Search business' button
        if ( isset($this->google_reviews_options['serp_business_name'])
            && $this->google_reviews_options['serp_business_name'] !== '' ) {
            $search_disabled = 'disabled';
        }

        // If business name has not yet been saved, disable both buttons
        if ( ! isset($this->google_reviews_options['serp_business_name'])
        || $this->google_reviews_options['serp_business_name'] == '' ) {
            $pull_button_disabled = 'disabled';
            $search_disabled = 'disabled';
        }

        // If reviews have already been pulled, disable 'Pull reviews' button
        $reviews = GRWP_Pro_API_Service::parse_pro_review_json();
        if ($reviews !== null) {
            $pull_button_disabled = 'disabled';
        }

        ?>

        <div class="serp-container">
            <div class="serp-search">
                <div class="serp-input-wrapper">
                <input type="search"
                       class="regular-text js-serp-business-search"
                       name="google_reviews_option_name[serp_business_name]"
                       id="serp_business_name"
                       value="<?php echo esc_attr( isset( $this->google_reviews_options['serp_business_name'] ) ? $this->google_reviews_options['serp_business_name'] : '' ); ?>"
                       autocomplete="off"
                       placeholder="<?php esc_html_e('Search for your business', 'embedder-for-google-reviews');?>"
                />
                <fieldset class="serp-results"></fieldset><!-- /.serp-results -->
                </div><!-- /.serp-input-wrapper -->
                <div class="button-row">
                    <a class="button search-business pro" <?php echo esc_attr($search_disabled); ?>>
                        <?php esc_html_e('Search business', 'embedder-for-google-reviews');?>
                    </a>
                    <a class="button pull-reviews pro" <?php echo esc_attr($pull_button_disabled); ?>>
                        <?php esc_html_e('Pull reviews', 'embedder-for-google-reviews');?>
                    </a>
                </div>
            </div><!-- /.serp-search -->
        </div><!-- /.serp-container -->

        <p id="errors"></p>
        <p>
            <?php esc_html_e( 'Details like country, state, city and/or phone number may help achieving more accurate results.', 'embedder-for-google-reviews' ); ?>
        </p>

        <?php
        $html = ob_get_clean();

        echo wp_kses($html, $allowed_html );
    }

    /**
     * Echo Hidden SERP Data ID Field
     */
    public function serp_data_id_callback() {
        global $allowed_html;
        ob_start();
        ?>

        <input type="hidden" class="hidden js-serp-data-id" name="google_reviews_option_name[serp_data_id]" id="serp_data_id" value="<?php echo esc_attr( isset( $this->google_reviews_options['serp_data_id'] ) ? $this->google_reviews_options['serp_data_id'] : '' ); ?>">

        <?php
        $html = ob_get_clean();

        echo wp_kses($html, $allowed_html);
    }

}
