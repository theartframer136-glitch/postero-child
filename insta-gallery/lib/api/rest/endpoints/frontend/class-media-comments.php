<?php
namespace QuadLayers\IGG\Api\Rest\Endpoints\Frontend;

use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\Base;
use QuadLayers\IGG\Models\Accounts as Models_Accounts;
use QuadLayers\IGG\Api\Fetch\Business\Comments\Get as Api_Fetch_Business_Comments;
use QuadLayers\IGG\Services\Cache;

class Media_Comments extends Base {

	protected static $route_path = 'frontend/media-comments';

	protected $comments_cache_engine;
	protected $comments_cache_key = 'comments';

	public function callback( \WP_REST_Request $request ) {

		$after      = $request->get_param( 'after' );
		$account_id = $request->get_param( 'account_id' );
		$media_id   = $request->get_param( 'media_id' );
		$limit      = $request->get_param( 'limit' );

		// Set prefix to cache.
		$comments_md5 = md5(
			wp_json_encode(
				array(
					'account_id' => $account_id,
					'media_id'   => $media_id,
					'limit'      => $limit,
				)
			)
		);

		$comments_complete_prefix = "{$this->comments_cache_key}_{$comments_md5}_{$after}";

		$this->comments_cache_engine = new Cache( 1, true, $comments_complete_prefix ); // 1 hour cache for comments

		// Get cached comments data.
		$response = $this->comments_cache_engine->get( $comments_complete_prefix );

		// Check if $response has data, if it has, return it.
		if ( ! QLIGG_DEVELOPER && ! empty( $response['response'] ) ) {
			return $response['response'];
		}

		$account = Models_Accounts::instance()->get( $account_id );

		// Check if exist an access_token and access_token_type related to id set by param
		if ( ! isset( $account['access_token'], $account['access_token_type'] ) ) {
			return $this->handle_response(
				array(
					'code'    => 412,
					'message' => sprintf( esc_html__( 'Account id %s not found to fetch comments.', 'insta-gallery' ), $account_id ),
				)
			);
		}

		$access_token = $account['access_token'];

		// Comments API is only available for Business accounts
		if ( $account['access_token_type'] != 'BUSINESS' ) {
			return $this->handle_response(
				array(
					'code'    => 400,
					'message' => esc_html__( 'Comments API is only available for Business accounts.', 'insta-gallery' ),
				)
			);
		}

		// Get comments data
		$response = ( new Api_Fetch_Business_Comments() )->get_data( $access_token, $media_id, $limit, $after );

		// Check if response is an error and return it.
		if ( isset( $response['message'], $response['code'] ) ) {
			return $this->handle_response( $response );
		}

		// Ensure we always return a consistent structure
		if ( empty( $response['data'] ) ) {
			// Return empty array of data instead of error code
			$response = array(
				'data'   => array(),
				'paging' => array(
					'prev' => '',
					'next' => '',
				),
			);
		}

		// Update comments data cache and return it.
		if ( ! QLIGG_DEVELOPER ) {
			$this->comments_cache_engine->update( $comments_complete_prefix, $response );
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
			'media_id'   => array(
				'required'          => true,
				'sanitize_callback' => function ( $media_id ) {
					return sanitize_text_field( $media_id );
				},
			),
			'limit'      => array(
				'default'           => 25,
				'sanitize_callback' => function ( $limit ) {
					return (int) $limit;
				},
				'required'          => false,
			),
			'after'      => array(
				'default'           => '',
				'sanitize_callback' => function ( $after ) {
					return sanitize_text_field( $after );
				},
				'required'          => false,
			),
		);
	}

	public static function get_rest_method() {
		return \WP_REST_Server::READABLE;
	}
}
