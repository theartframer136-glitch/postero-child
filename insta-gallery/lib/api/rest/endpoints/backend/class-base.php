<?php

namespace QuadLayers\IGG\Api\Rest\Endpoints\Backend;

use QuadLayers\IGG\Api\Rest\Endpoints\Base as Endpoints;

/**
 * Base Class
 */
abstract class Base extends Endpoints {

	public function get_rest_permission( \WP_REST_Request $request = null ) {
		// Fall back to 'manage_options' in case 'qligg_manage_feeds' has not been
		// assigned yet (add_capability runs on admin_init which doesn't fire on
		// REST API requests if no admin page has been visited since activation).
		if ( current_user_can( 'qligg_manage_feeds' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		return false;
	}
}
