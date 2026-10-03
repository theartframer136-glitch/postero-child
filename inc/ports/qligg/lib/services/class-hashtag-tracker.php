<?php
namespace QuadLayers\IGG\Services;

/**
 * Hashtag_Tracker Class
 *
 * Handles tracking of hashtag searches to respect Instagram's limit of 30 unique hashtags in a 7-day rolling window
 */
class Hashtag_Tracker {

	/**
	 * Option name where hashtag tracking data is stored
	 *
	 * @var string
	 */
	private static $option_name = 'qligg_hashtag_tracker';

	/**
	 * Track a hashtag search
	 *
	 * @param string $hashtag The hashtag being searched (without #)
	 * @return int Number of unique hashtags tracked in the last 7 days
	 */
	public static function track_hashtag( $hashtag ) {
		$hashtag      = strtolower( trim( $hashtag ) );
		$tracker      = get_option( self::$option_name, array() );
		$current_time = time();
		$week_ago     = $current_time - ( 7 * DAY_IN_SECONDS );

		// Remove expired entries
		foreach ( $tracker as $tag => $timestamp ) {
			if ( $timestamp < $week_ago ) {
				unset( $tracker[ $tag ] );
			}
		}

		// Add new hashtag with current timestamp
		$tracker[ $hashtag ] = $current_time;
		update_option( self::$option_name, $tracker );

		return count( $tracker ); // Return number of unique hashtags in last 7 days
	}

	/**
	 * Check if a hashtag can be queried (within the 30 hashtag limit)
	 *
	 * @param string $hashtag The hashtag to check (without #)
	 * @return boolean True if hashtag can be queried, false otherwise
	 */
	public static function can_query_hashtag( $hashtag ) {
		$hashtag = strtolower( trim( $hashtag ) );
		$tracker = get_option( self::$option_name, array() );

		// Clean up expired entries first
		$current_time = time();
		$week_ago     = $current_time - ( 7 * DAY_IN_SECONDS );

		foreach ( $tracker as $tag => $timestamp ) {
			if ( $timestamp < $week_ago ) {
				unset( $tracker[ $tag ] );
			}
		}

		// If we've already queried this hashtag in the last 7 days, it doesn't count against our limit
		if ( isset( $tracker[ $hashtag ] ) ) {
			return true;
		}

		// Check if we're at the 30 hashtag limit
		return count( $tracker ) < 30;
	}

	/**
	 * Get all tracked hashtags and their timestamps
	 *
	 * @return array Hashtags and their timestamps
	 */
	public static function get_tracked_hashtags() {
		$tracker      = get_option( self::$option_name, array() );
		$current_time = time();
		$week_ago     = $current_time - ( 7 * DAY_IN_SECONDS );

		// Remove expired entries
		foreach ( $tracker as $tag => $timestamp ) {
			if ( $timestamp < $week_ago ) {
				unset( $tracker[ $tag ] );
			}
		}

		// Save cleaned tracker
		update_option( self::$option_name, $tracker );

		return $tracker;
	}

	/**
	 * Get count of unique hashtags searched in last 7 days
	 *
	 * @return int Number of unique hashtags
	 */
	public static function get_hashtag_count() {
		$tracker = self::get_tracked_hashtags();
		return count( $tracker );
	}

	/**
	 * Get remaining available hashtag searches
	 *
	 * @return int Number of remaining hashtags that can be searched
	 */
	public static function get_remaining_hashtags() {
		$count = self::get_hashtag_count();
		return max( 0, 30 - $count );
	}
}
