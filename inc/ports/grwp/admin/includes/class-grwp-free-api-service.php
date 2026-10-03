<?php

class GRWP_Free_API_Service {
    public function __construct() {
        // Business search ajax handler
        add_action( 'wp_ajax_handle_serp_business_search', [$this, 'handle_serp_business_search'] );
        add_action( 'wp_ajax_handle_get_reviews_pro_api', [$this, 'get_reviews_free_api'] );
        // Save language ajax handler
        add_action( 'wp_ajax_handle_language_saving', [$this, 'handle_language_saving'] );
        // Save location ajax handler
        add_action( 'wp_ajax_handle_location_saving', [$this, 'handle_location_saving'] );
    }

    /**
     * Validate all requests to prevent external threats
     * @return true|WP_Error
     */
    public static function validate_request() {
        if ( wp_doing_cron() ) {
            return true;
            // Allow if running via WP-Cron
        }
        if ( !isset( $_REQUEST['_ajax_nonce'] ) || !wp_verify_nonce( $_REQUEST['_ajax_nonce'], 'grwp_nonce_action' ) ) {
            return new WP_Error('forbidden', 'Security check failed.', [
                'status' => 403,
            ]);
        }
        if ( !current_user_can( 'manage_options' ) ) {
            return new WP_Error('forbidden', 'You are not allowed to do this.', [
                'status' => 403,
            ]);
        }
        return true;
    }

    /**
     * Handle location saving via ajax
     */
    public static function handle_location_saving() {
        $validate = self::validate_request();
        if ( is_wp_error( $validate ) ) {
            wp_send_json_error( [
                'message' => $validate->get_error_message(),
            ], $validate->get_error_data()['status'] ?? 403 );
        }
        $response = new WP_REST_Response();
        $data_id = ( isset( $_GET['data_id'] ) ? sanitize_text_field( $_GET['data_id'] ) : '' );
        $location_name = ( isset( $_GET['location_name'] ) ? sanitize_text_field( $_GET['location_name'] ) : '' );
        $place_id = ( isset( $_GET['place_id'] ) ? sanitize_text_field( $_GET['place_id'] ) : '' );
        $cid = ( isset( $_GET['cid'] ) ? sanitize_text_field( $_GET['cid'] ) : '' );
        if ( $data_id == '' || $location_name == '' ) {
            $response->set_status( 404 );
        } else {
            $google_reviews_options = get_option( 'google_reviews_option_name' );
            $google_reviews_options['serp_data_id'] = $data_id;
            $google_reviews_options['serp_business_name'] = $location_name;
            $google_reviews_options['serp_place_id'] = $place_id;
            $google_reviews_options['serp_cid'] = $cid;
            update_option( 'google_reviews_option_name', $google_reviews_options );
            $response->set_status( 200 );
        }
        return $response;
    }

    /**
     * Handle language saving via ajax
     * @return WP_REST_Response
     */
    public static function handle_language_saving( $arg ) {
        $validate = self::validate_request();
        if ( is_wp_error( $validate ) ) {
            wp_send_json_error( [
                'message' => $validate->get_error_message(),
            ], $validate->get_error_data()['status'] ?? 403 );
        }
        $language = ( isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : 'en' );
        $google_reviews_options = get_option( 'google_reviews_option_name' );
        $google_reviews_options["reviews_language_3"] = $language;
        update_option( 'google_reviews_option_name', $google_reviews_options );
        $response = new WP_REST_Response();
        $response->set_status( 200 );
        return $response;
    }

    /**
     * Get reviews from Pro API
     * @return WP_REST_Response
     */
    public static function get_reviews_free_api() {
        $validate = self::validate_request();
        if ( is_wp_error( $validate ) ) {
            wp_send_json_error( [
                'message' => $validate->get_error_message(),
            ], $validate->get_error_data()['status'] ?? 403 );
        }
        $google_reviews_options = get_option( 'google_reviews_option_name' );
        $data_id = $google_reviews_options['serp_data_id'];
        $reviews_language = $google_reviews_options['reviews_language_3'];
        if ( empty( $data_id ) ) {
            return;
        }
        $site = urlencode( get_site_url() );
        $admin_email = urlencode( get_option( 'admin_email' ) );
        if ( grwp_fs()->get_site() ) {
            $install_id = grwp_fs()->get_site()->id;
        } else {
            $install_id = '';
        }
        $license_request_url = sprintf(
            'https://easyreviewsapi.com/get-reviews-data.php?install_id=%s&data_id=%s&language=%s&site=%s&mail=%s',
            $install_id,
            $data_id,
            $reviews_language,
            $site,
            $admin_email
        );
        $get_reviews = wp_remote_get( $license_request_url, [
            'timeout' => 30,
        ] );
        $response = new WP_REST_Response();
        // check for errors in response
        if ( is_wp_error( $get_reviews ) ) {
            wp_send_json_error( array(
                'html' => $get_reviews->get_error_message(),
            ) );
            die;
        } else {
            if ( !$get_reviews ) {
                $message = 'Response was invalid.';
                wp_send_json_error( array(
                    'html' => $message,
                ) );
                die;
            }
        }
        $body = json_decode( wp_remote_retrieve_body( $get_reviews ) );
        // check if response body has content
        if ( $body === '' || $body === null ) {
            $message = 'Empty response body.';
            wp_send_json_error( array(
                'html' => $message,
            ) );
            die;
        } else {
            $get_reviews = json_decode( wp_remote_retrieve_body( $get_reviews ) );
            $reviews_arr = json_decode( wp_json_encode( $get_reviews ), true );
            // make sure, the reviews are properly formatted and contain all necessary info
            if ( self::check_reviews( $reviews_arr['reviews'] ) ) {
                // Update reviews
                update_option( 'gr_latest_results', [
                    $data_id => wp_json_encode( $reviews_arr['reviews'] ),
                ] );
            }
            // make sure, the place_info is properly formatted and contains all necessary info
            if ( self::check_place_info( $reviews_arr['place_info'] ) ) {
                // Update place info data
                update_option( 'grwp_place_info', [
                    $data_id => wp_json_encode( $reviews_arr['place_info'] ),
                ] );
            }
            $response->set_status( 200 );
        }
        return $response;
    }

    public static function check_reviews( $reviews ) {
        $all_values_correct = true;
        foreach ( $reviews as $review ) {
            if ( !isset( $review['link'] ) || !is_string( $review['link'] ) || !isset( $review['rating'] ) || !is_int( $review['rating'] ) || !isset( $review['date'] ) || !is_string( $review['date'] ) || !isset( $review['user']['name'] ) || !is_string( $review['user']['name'] ) || !isset( $review['user']['link'] ) || !is_string( $review['user']['link'] ) || !isset( $review['user']['thumbnail'] ) || !is_string( $review['user']['thumbnail'] ) ) {
                $all_values_correct = false;
                break;
            }
        }
        return $all_values_correct;
    }

    public static function check_place_info( $place_info ) {
        $all_values_correct = true;
        if ( !isset( $place_info['title'] ) || !is_string( $place_info['title'] ) || !isset( $place_info['rating'] ) || !is_float( $place_info['rating'] ) && !is_int( $place_info['rating'] ) || !isset( $place_info['reviews'] ) || !is_int( $place_info['reviews'] ) ) {
            $all_values_correct = false;
        }
        return $all_values_correct;
    }

    /**
     * Handle Google business search
     * @return void
     */
    public static function handle_serp_business_search() {
        $validate = self::validate_request();
        if ( is_wp_error( $validate ) ) {
            wp_send_json_error( [
                'message' => $validate->get_error_message(),
            ], $validate->get_error_data()['status'] ?? 403 );
        }
        $search_value = ( isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '' );
        $language = ( isset( $_GET['language'] ) ? sanitize_text_field( $_GET['language'] ) : 'en' );
        if ( grwp_fs()->get_site() ) {
            $install_id = grwp_fs()->get_site()->id;
        } else {
            $install_id = '';
        }
        $site = urlencode( get_site_url() );
        $admin_email = urlencode( get_option( 'admin_email' ) );
        $is_premium = ( grwp_fs()->is__premium_only() ? 'true' : 'false' );
        $license_request_url = sprintf(
            'https://easyreviewsapi.com/get-results.php?install_id=%s&search_value=%s&language=%s&site=%s&mail=%s&is_premium=%s',
            $install_id,
            $search_value,
            $language,
            $site,
            $admin_email,
            $is_premium
        );
        $get_results = wp_remote_get( $license_request_url, [
            'timeout' => 30,
        ] );
        $get_results = json_decode( wp_remote_retrieve_body( $get_results ) );
        if ( isset( $get_results->error ) ) {
            wp_send_json_error( array(
                'html' => $get_results->reason,
            ) );
            die;
        } else {
            if ( isset( $get_results->html ) ) {
                wp_send_json_success( array(
                    'html' => $get_results->html,
                ) );
                die;
            }
        }
        wp_send_json_error( array(
            'html' => 'Not found. Please refer to this guide: https://reviewsembedder.com/docs/business-not-found/',
        ) );
        die;
    }

    /**
     * Parse json results of Pro API and check for errors
     * @return mixed|WP_Error
     */
    public static function parse_pro_review_json() {
        $business = get_option( 'google_reviews_option_name' );
        $data_id = ( isset( $business['serp_data_id'] ) && $business['serp_data_id'] ? $business['serp_data_id'] : null );
        $raw = get_option( 'gr_latest_results' );
        if ( isset( $raw[$data_id] ) && $raw[$data_id] ) {
            $reviewArr = json_decode( $raw[$data_id], true );
            $reviews = $reviewArr;
        } else {
            $reviews = [];
        }
        return $reviews;
    }

    /**
     * Parse json results of Free API and check for errors
     * @return mixed|WP_Error
     */
    public static function parse_free_review_json() {
        $raw = get_option( 'gr_latest_results_free' );
        $reviewArr = json_decode( $raw, true );
        $result = ( isset( $reviewArr['reviews'] ) ? $reviewArr['reviews'] : [] );
        return $result;
    }

}
