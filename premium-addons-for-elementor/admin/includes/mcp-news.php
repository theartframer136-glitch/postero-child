<?php
/**
 * PA MCP News.
 */

namespace PremiumAddons\Admin\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCP_News
 *
 * Remote "What's New" video feed shown in the MCP Config & AI Abilities tab:
 * the YouTube video IDs of the MCP & AI Abilities playlist.
 *
 * @since 4.11.102
 */
class MCP_News {

	// Kill switch for the sidebar.
	const ENABLED = true;

	const ENDPOINT = 'https://premiumaddons.com/wp-json/mcp-videos/v2/get';

	// New keys, not the pa_mcp_news_* ones: those still hold the retired articles feed.
	const VIDEOS_OPTION = 'pa_mcp_videos';

	const FRESH_TRANSIENT = 'pa_mcp_videos_fresh';

	const CACHE_TTL = 2 * DAY_IN_SECONDS;

	const RETRY_TTL = 6 * HOUR_IN_SECONDS;

	const RENDER_LIMIT = 10;

	const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

	/**
	 * Get the playlist video IDs, refreshing the cache when it expired.
	 *
	 * @since 4.11.111
	 * @access public
	 *
	 * @return array
	 */
	public static function get_videos() {

		if ( false === get_transient( self::FRESH_TRANSIENT ) ) {
			self::refresh();
		}

		return self::get_cached_videos();
	}

	/**
	 * Get the cached video IDs without triggering a remote fetch.
	 *
	 * @since 4.11.111
	 * @access private
	 *
	 * @return array
	 */
	private static function get_cached_videos() {

		$videos = get_option( self::VIDEOS_OPTION, array() );

		return is_array( $videos ) ? $videos : array();
	}

	/**
	 * Fetch the remote feed. On failure the last cached copy stays in place and
	 * the retry is postponed, so the dashboard never waits on the feed twice.
	 *
	 * @since 4.11.102
	 * @access private
	 */
	private static function refresh() {

		$response = wp_remote_get( self::ENDPOINT, array( 'timeout' => 3 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::FRESH_TRANSIENT, 1, self::RETRY_TTL );
			return;
		}

		$videos = self::sanitize_videos( json_decode( wp_remote_retrieve_body( $response ), true ) );

		update_option( self::VIDEOS_OPTION, $videos, false );
		set_transient( self::FRESH_TRANSIENT, 1, self::CACHE_TTL );
	}

	/**
	 * Keep only well-formed YouTube video IDs, in playlist order.
	 *
	 * @since 4.11.111
	 * @access private
	 *
	 * @param mixed $data decoded response body.
	 *
	 * @return array
	 */
	private static function sanitize_videos( $data ) {

		if ( ! is_array( $data ) ) {
			return array();
		}

		$videos = array_filter(
			$data,
			function ( $video_id ) {
				return is_string( $video_id ) && preg_match( self::VIDEO_ID_PATTERN, $video_id );
			}
		);

		return array_slice( array_values( $videos ), 0, self::RENDER_LIMIT );
	}
}
