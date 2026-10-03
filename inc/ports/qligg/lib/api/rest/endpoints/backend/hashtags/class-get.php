<?php
namespace QuadLayers\IGG\Api\Rest\Endpoints\Backend\Hashtags;

use QuadLayers\IGG\Api\Rest\Endpoints\Backend\Base as Backend_Base;
use QuadLayers\IGG\Services\Hashtag_Tracker;

class Get extends Backend_Base {

	protected static $route_path = 'backend/hashtags';

	public function callback( \WP_REST_Request $request ) {
		// Get all tracked hashtags
		$hashtags = Hashtag_Tracker::get_tracked_hashtags();

		// Format the data for better display
		$formatted_hashtags = array();
		foreach ( $hashtags as $tag => $timestamp ) {
			$formatted_hashtags[] = array(
				'hashtag'   => $tag,
				'timestamp' => $timestamp,
				'date'      => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ),
				'expires'   => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp + ( 7 * DAY_IN_SECONDS ) ),
			);
		}

		return $this->handle_response(
			array(
				'code'           => 200,
				'hashtags'       => $formatted_hashtags,
				'count'          => count( $hashtags ),
				'limit'          => 30,
				'remaining'      => Hashtag_Tracker::get_remaining_hashtags(),
				'next_available' => count( $hashtags ) >= 30 ? date_i18n( get_option( 'date_format' ), strtotime( '+7 days', min( $hashtags ) ) ) : null,
			)
		);
	}

	public static function get_rest_args() {
		return array();
	}

	public static function get_rest_method() {
		return \WP_REST_Server::READABLE;
	}
}
