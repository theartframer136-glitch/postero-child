<?php
namespace QuadLayers\IGG\Api\Rest\Endpoints\Frontend;

use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\Base;
use QuadLayers\IGG\Models\Accounts as Models_Accounts;
use QuadLayers\IGG\Api\Fetch\Business\Stories\Get as Api_Fetch_Business_Stories;
use QuadLayers\IGG\Services\Cache;

class User_Stories extends Base {

	protected static $route_path = 'frontend/user-stories';

	protected $media_cache_engine;
	protected $media_cache_key = 'stories';

	public function callback( \WP_REST_Request $request ) {

		$account_id = $request->get_param( 'account_id' );

		// Set prefix to cache with a shorter TTL for stories (1 hour instead of 6)
		$feed_md5 = md5(
			wp_json_encode(
				array(
					'account_id' => $account_id,
				)
			)
		);

		$media_complete_prefix = "{$this->media_cache_key}_{$feed_md5}";

		// Stories cache for only 1 hour due to their ephemeral nature
		$this->media_cache_engine = new Cache( 1, true, $media_complete_prefix );

		// Get cached user stories data.
		$response = $this->media_cache_engine->get( $media_complete_prefix );

		// Check if $response has data, if it has, return it.
		if ( ! QLIGG_DEVELOPER && ! empty( $response['response'] ) ) {
			return $response['response'];
		}

		$account = Models_Accounts::instance()->get( $account_id );

		// Check if exist an access_token and access_token_type related to id set by param, if not return error.
		if ( ! isset( $account['access_token'], $account['access_token_type'] ) ) {
			return $this->handle_response(
				array(
					'code'    => 412,
					'message' => sprintf( esc_html__( 'Account id %s not found to fetch user stories.', 'insta-gallery' ), $account_id ),
				)
			);
		}

		$access_token = $account['access_token'];

		// Stories are only available for Business accounts in the Graph API
		if ( $account['access_token_type'] != 'BUSINESS' ) {
			return $this->handle_response(
				array(
					'code'    => 400,
					'message' => esc_html__( 'Stories are only available for Professional accounts.', 'insta-gallery' ),
				)
			);
		}

		// Get user stories data
		$response = ( new Api_Fetch_Business_Stories() )->get_data( $access_token, $account_id );

		// Check if response is an error and return it.
		if ( isset( $response['message'], $response['code'] ) ) {
			return $this->handle_response( $response );
		}

		if ( empty( $response['data'] ) ) {
			return array(
				'code'    => 404,
				'message' => esc_html__( 'No active stories found for this account.', 'insta-gallery' ),
			);
		}

		// Update user stories data cache and return it.
		if ( ! QLIGG_DEVELOPER ) {
			$this->media_cache_engine->update( $media_complete_prefix, $response );
		}

		return $this->handle_response( $response );
	}

	public static function get_rest_args() {
		return array(
			'account_id' => array(
				'required'          => true,
				'sanitize_callback' => function ( $account_id ) {
					return sanitize_text_field( $account_id );
				},
				'validate_callback' => function ( $account_id ) {
					return is_numeric( $account_id );
				},
			),
		);
	}

	public static function get_rest_method() {
		return \WP_REST_Server::READABLE;
	}
}
