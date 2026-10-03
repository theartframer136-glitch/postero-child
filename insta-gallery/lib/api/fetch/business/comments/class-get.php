<?php
namespace QuadLayers\IGG\Api\Fetch\Business\Comments;

use QuadLayers\IGG\Api\Fetch\Business\Base;
use QuadLayers\IGG\Helpers;

/**
 * Api_Fetch_Business_Comments
 */
class Get extends Base {

	/**
	 * Function to get comments data from media.
	 *
	 * @param string $access_token Account access_token.
	 * @param string $media_id Media ID to fetch comments from.
	 * @param int    $limit Comments limit.
	 * @param string $after After param to query pagination.
	 * @return array $data
	 */
	public function get_data( $access_token = null, $media_id = null, $limit = null, $after = null ) {
		$response = $this->get_response( $access_token, $media_id, $limit, $after );
		$data     = $this->response_to_data( $response );
		return $data;
	}

	/**
	 * Function to set comments into required structure
	 *
	 * @param array $items Array of raw comment items.
	 * @return array
	 */
	protected function get_comments_data( $items ) {
		$filter_items = Helpers::array_reduce(
			$items,
			function ( $carry, $key, $item ) {
				$comment = array(
					'id'           => isset( $item['id'] ) ? $item['id'] : '',
					'text'         => isset( $item['text'] ) ? $item['text'] : '',
					'username'     => isset( $item['username'] ) ? $item['username'] : '',
					'timestamp'    => isset( $item['timestamp'] ) ? $item['timestamp'] : '',
					'like_count'   => isset( $item['like_count'] ) ? $item['like_count'] : 0,
					'hidden'       => isset( $item['hidden'] ) ? $item['hidden'] : false,
					'user_profile' => isset( $item['from']['profile_picture'] ) ? $item['from']['profile_picture'] : '',
					'replies'      => isset( $item['replies']['data'] ) ? $this->get_comments_data( $item['replies']['data'] ) : array(),
				);

				array_push( $carry, $comment );
				return $carry;
			},
			array()
		);

		return $filter_items;
	}

	/**
	 * Function to parse response to usable data.
	 *
	 * @param array $response Raw response from Instagram.
	 * @return array
	 */
	public function response_to_data( $response = null ) {
		if ( isset( $response['data'] ) ) {
			$comments_data = $this->get_comments_data( $response['data'] );

			$paging = array(
				'prev' => '',
				'next' => '',
			);

			if ( isset( $response['paging'] ) ) {
				if ( isset( $response['paging']['previous'] ) ) {
					$paging['prev'] = isset( $response['paging']['cursors']['before'] ) ? $response['paging']['cursors']['before'] : '';
				}

				if ( isset( $response['paging']['next'] ) ) {
					$paging['next'] = isset( $response['paging']['cursors']['after'] ) ? $response['paging']['cursors']['after'] : '';
				}
			}

			$response = array(
				'data'   => $comments_data,
				'paging' => $paging,
			);
		}

		return $response;
	}

	/**
	 * Function to query Instagram data.
	 *
	 * @param string $access_token Account access_token.
	 * @param string $media_id Media ID to fetch comments from.
	 * @param int    $limit Comments limit.
	 * @param string $after After param to query pagination.
	 * @return array
	 */
	public function get_response( $access_token = null, $media_id = null, $limit = null, $after = null ) {
		$url      = $this->get_url( $access_token, $media_id, $limit, $after );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);
		$response = $this->handle_response( $response );

		return $response;
	}

	/**
	 * Function to build query url.
	 *
	 * @param string $access_token Account access_token.
	 * @param string $media_id Media ID to fetch comments from.
	 * @param int    $limit Comments limit.
	 * @param string $after After param to query pagination.
	 * @return string
	 */
	public function get_url( $access_token = null, $media_id = null, $limit = null, $after = null ) {
		// Make sure media_id is in the correct format for Instagram API
		if ( ! preg_match( '/^[0-9]+$/', $media_id ) ) {
			return array(
				'code'    => 400,
				'message' => esc_html__( 'Invalid media ID format.', 'insta-gallery' ),
			);
		}

		$url = add_query_arg(
			array(
				'after'        => $after,
				'limit'        => $limit,
				'fields'       => 'id,text,username,timestamp,like_count,hidden,replies{id,text,username,timestamp,like_count,hidden}',
				'access_token' => $access_token,
			),
			"{$this->api_url}/{$media_id}/comments"
		);

		return $url;
	}

	/**
	 * Function to reply to a comment.
	 *
	 * @param string $access_token Account access_token.
	 * @param string $comment_id Comment ID to reply to.
	 * @param string $message Reply message.
	 * @return array
	 */
	public function reply_to_comment( $access_token = null, $comment_id = null, $message = null ) {
		$url = add_query_arg(
			array(
				'access_token' => $access_token,
			),
			"{$this->api_url}/{$comment_id}/replies"
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'body'    => array(
					'message' => $message,
				),
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Function to hide or unhide a comment.
	 *
	 * @param string  $access_token Account access_token.
	 * @param string  $comment_id Comment ID to hide/unhide.
	 * @param boolean $hide Whether to hide (true) or unhide (false) the comment.
	 * @return array
	 */
	public function hide_comment( $access_token = null, $comment_id = null, $hide = true ) {
		$url = add_query_arg(
			array(
				'access_token' => $access_token,
				'hide'         => $hide ? 'true' : 'false',
			),
			"{$this->api_url}/{$comment_id}"
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Function to delete a comment.
	 *
	 * @param string $access_token Account access_token.
	 * @param string $comment_id Comment ID to delete.
	 * @return array
	 */
	public function delete_comment( $access_token = null, $comment_id = null ) {
		$url = add_query_arg(
			array(
				'access_token' => $access_token,
			),
			"{$this->api_url}/{$comment_id}"
		);

		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'DELETE',
				'timeout' => 30,
			)
		);

		return $this->handle_response( $response );
	}
}
