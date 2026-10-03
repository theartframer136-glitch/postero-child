<?php

namespace QuadLayers\IGG\Api\Rest\Endpoints\Frontend;

use QuadLayers\IGG\Api\Rest\Endpoints\Base as Endpoints;
use QuadLayers\IGG\Models\Feeds as Models_Feeds;

/**
 * Base Class for Frontend Endpoints
 *
 * Provides security validation to ensure account_ids are only accessible
 * if they are associated with at least one published feed.
 */
abstract class Base extends Endpoints {

	/**
	 * Validates that an account_id is associated with at least one feed
	 *
	 * @param int $account_id The account ID to validate
	 * @return bool True if account is in a feed, false otherwise
	 */
	protected function validate_account_in_feeds( $account_id ) {
		// Validate account_id is provided and numeric
		if ( empty( $account_id ) || ! is_numeric( $account_id ) ) {
			return false;
		}

		$account_id = intval( $account_id );

		// Get all feeds
		$feeds = Models_Feeds::instance()->get_all();

		// If no feeds exist, deny access
		if ( empty( $feeds ) ) {
			return false;
		}

		// Check if the account_id exists in any feed
		foreach ( $feeds as $feed ) {
			if ( isset( $feed['account_id'] ) && intval( $feed['account_id'] ) === $account_id ) {
				return true; // Account is associated with a published feed
			}
		}

		// Account not found in any feed
		return false;
	}

	/**
	 * Permission callback for REST API endpoints
	 *
	 * Validates that the account_id parameter corresponds to an account
	 * that is being used in at least one published feed.
	 *
	 * @return bool True if authorized, false otherwise
	 */
	public function get_rest_permission( \WP_REST_Request $request = null ) {
		$account_id = $request ? ( $request->get_param( 'account_id' ) ? intval( $request->get_param( 'account_id' ) ) : 0 ) : 0;

		if ( ! $this->validate_account_in_feeds( $account_id ) ) {
			return false;
		}

		return true;
	}
}
