<?php

Class GRWP_Free_Settings {

    private $google_reviews_options;

    public function __construct() {
        $this->google_reviews_options = get_option( 'google_reviews_option_name' );
        $this->add_settings();
    }

    private function add_settings() {
        /*
        add_settings_field(
            'gmb_id_1', // id
            /* translators: Place ID */
        /*           __( 'Place ID', 'embedder-for-google-reviews' ),
                   array( $this, 'gmb_id_1_callback' ), // callback
                   'google-reviews-admin', // page
                   'google_reviews_setting_section' // section
               );
               */

	    add_settings_field(
		    'serp_business_name', // id
            /* translators: search for your business */
		    __( 'Search for your business:', 'embedder-for-google-reviews' ),
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

        add_settings_field(
            'video_intro', // id
            '', // title
            array( $this, 'video_intro_callback' ), // callback
            'google-reviews-admin', // page
            'google_reviews_setting_section' // section
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

		echo wp_kses($html, $allowed_html);
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

    /**
     * Show modal with video introduction
     */
    public function video_intro_callback() { ?>
        <div id="how_to_modal" class="modal hide">

            <!-- Modal content -->
            <div class="modal-inner">
                <div class="modal-content">
                    <span id="modal_close" class="close">&times;</span>
                    <h3><?php esc_html_e('How to use this plugin', 'embedder-for-google-reviews'); ?></h3>
                    <p><?php esc_html_e('Explained in less than 1 minute...', 'embedder-for-google-reviews'); ?></p>
                    <div class="responsive_iframe">
                        <iframe style="display:block;" width="560" height="315" src="https://www.youtube-nocookie.com/embed/y2pWCW_cuNk" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                    </div>
                </div>
            </div>

            <div class="modal-overlay"></div>

        </div>

        <?php
    }

    /**
     * Echo place ID field
     */
    public function gmb_id_1_callback() {
        global $allowed_html; ?>

        <div class="serp-container">
            <div class="serp-search">

        <?php
        printf(
            '<input class="regular-text free" 
        type="text" 
        name="google_reviews_option_name[gmb_id_1]" 
        id="gmb_id_1" 
        value="%s" 
        placeholder="%s">',
            isset($this->google_reviews_options['gmb_id_1']) ? esc_attr($this->google_reviews_options['gmb_id_1']) : '',
            /* translators: placeholder text */
            esc_attr( __( 'Paste the Place ID of your business here.', 'embedder-for-google-reviews' ) )
        ); ?>

                <a class="button pull-reviews free"><?php esc_html_e('Pull reviews', 'embedder-for-google-reviews');?></a>
            </div>
        </div>


        <?php
        $video_link = get_site_url() .'/wp-admin/admin.php?page=how-to-free-version';

        ?>
        <p id="errors"></p>
        <p>
            <?php

            echo
            wp_kses(
                sprintf(
                        /* translators: %s: video link */
                        __('Search for your business in the map below and copy/paste the Place ID into the field above (<a href="%s" target="_blank">short explainer video</a>).', 'embedder-for-google-reviews'),
                        $video_link
                ),
                $allowed_html
            );
            ?>
        </p>
        <br>
        <h4>
            <?php
            esc_html_e('Look up your Place ID and paste it in the field above.', 'embedder-for-google-reviews');
            ?>
        </h4>
        <iframe id="mapFrame" height="200" style="height: 200px; width: 100%; max-width: 700px;display:block;" src="https://geo-devrel-javascript-samples.web.app/samples/places-placeid-finder/app/dist/" allow="fullscreen;"></iframe>
        <?php

    }

}
