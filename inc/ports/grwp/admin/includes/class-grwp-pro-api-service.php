<?php

Class GRWP_Pro_API_Service
	extends
	GRWP_Free_API_Service {

	public function __construct() {

		parent::__construct();

		// Pull reviews ajax handler
		add_action('wp_ajax_handle_get_reviews_pro_api', [$this, 'get_reviews_pro_api']);
	}

	/**
     * Get reviews from Pro API
     * @return WP_REST_Response
     */
    public static function get_reviews_pro_api() {

        $validate = parent::validate_request();
        if (is_wp_error($validate)) {
            wp_send_json_error( [ 'message' => $validate->get_error_message() ], $validate->get_error_data()['status'] ?? 403 );
        }

        $google_reviews_options = get_option( 'google_reviews_option_name' );

        $data_id          = $google_reviews_options['serp_data_id'];
        $reviews_language = $google_reviews_options['reviews_language_3'];

        if ( empty( $data_id ) ) {
            return;
        }

        $site = urlencode(get_site_url());
        $admin_email = urlencode(get_option('admin_email'));
		$install_id = grwp_fs()->get_site()->id;
        $secret_key = base64_encode( grwp_fs()->get_site()->secret_key );

        $new_hash_request_url = 'https://easyreviewsapi.com/generate-hash.php';

        $new_hash = wp_remote_get( $new_hash_request_url, array(
            'headers' => array(
                'Authorization' => $secret_key
            )
        ) );

		// assign endpoint, depending on plan
		if (grwp_fs()->is_plan('pro_unlimited')) {
			$api_endpoint = 'get-reviews-data-pro-unlimited.php';
		} else {
			$api_endpoint = 'get-reviews-data-pro.php';
		}

		// construct request URL
        $license_request_url = sprintf(
			'https://easyreviewsapi.com/%s?install_id=%s&data_id=%s&language=%s&site=%s&mail=%s',
	        $api_endpoint,
            $install_id,
            $data_id,
            $reviews_language,
            $site,
            $admin_email
        );

        $get_reviews = wp_remote_get( $license_request_url, array(
            'headers' => array(
                'Authorization' => wp_remote_retrieve_body( $new_hash )
            ),
	        'timeout' => 30
        ) );

        $response = new WP_REST_Response();

        // check for errors in response
        if ( is_wp_error( $get_reviews ) ) {

            wp_send_json_error( array(
                'html' => $get_reviews->get_error_message()
            ) );

            die();

        }

        // check for empty response
        else if ( ! $get_reviews ) {

			$message = 'Response was invalid.';
            wp_send_json_error( array(
                'html' => $message
            ) );

            die();

        }

		$body = json_decode( wp_remote_retrieve_body( $get_reviews ) );

		// check if response body has content
		if ( $body === '' || $body === null ) {

			$message = 'Empty response body.';
            wp_send_json_error( array(
                'html' => $message
            ) );

            die();

        }

        // if response body available, proceed
        else {

			$get_reviews = json_decode( wp_remote_retrieve_body( $get_reviews ) );
			$reviews_arr = json_decode( wp_json_encode($get_reviews), true );

			// make sure, the reviews are properly formatted and contain all necessary info
			if ( parent::check_reviews($reviews_arr['reviews']) ) {

				// Update reviews
				update_option( 'gr_latest_results', [
					$data_id => wp_json_encode( $reviews_arr['reviews'] )
				] );

			}

			// make sure, the place_info is properly formatted and contains all necessary info
	        if ( parent::check_place_info($reviews_arr['place_info']) ) {

		        // Update place info data
		        update_option( 'grwp_place_info', [
			        $data_id => wp_json_encode( $reviews_arr['place_info'] )
		        ] );

	        }

			$response->set_status(200);
        }

        return $response;

    }

}
