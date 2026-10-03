<?php
namespace QuadLayers\IGG\Api\Fetch\Business\Stories;

use QuadLayers\IGG\Api\Fetch\Business\Base;
use QuadLayers\IGG\Helpers;

/**
 * Api_Fetch_Business_Stories
 */
class Get extends Base {

	/**
	 * Function to get stories data from user.
	 *
	 * @param string $access_token Account access_token.
	 * @param int    $id Account id.
	 * @return array $data
	 */
	public function get_data( $access_token = null, $id = null ) {
		$response = $this->get_response( $access_token, $id );
		$data     = $this->response_to_data( $response );
		return $data;
	}

	/**
	 * Function to get item media file data
	 *
	 * @param array $item Item to get media url.
	 * @return array|null
	 */
	protected function get_item_media_file_data( array $item = array() ) {
		if ( isset( $item['media_type'] ) ) {
			switch ( $item['media_type'] ) {
				case 'IMAGE':
					if ( isset( $item['media_url'] ) ) {
							return array( $item['media_url'], 'IMAGE' );
					}
					break;
				case 'VIDEO':
					if ( isset( $item['media_url'] ) ) {
							return array( $item['media_url'], 'VIDEO' );
					}
					break;
			}
		}

		return false;
	}

	/**
	 * Function to get item media url and type
	 *
	 * @param array $item Story element.
	 * @return array
	 */
	public function get_item_media( $item = null ) {
		$media_file_url = $this->get_item_media_file_data( $item );

		if ( ! $media_file_url ) {
			return array(
				null,
				null,
			);
		}
		return $media_file_url;
	}

	/**
	 * Function to set items into required structure
	 *
	 * @param array $items Array of raw items.
	 * @return array
	 */
	protected function get_items_data( $items ) {
		$filter_items = Helpers::array_reduce(
			$items,
			function ( $carry, $key, $item ) {
				list( $media_file_url, $media_file_type ) = $this->get_item_media( $item );

				// If no valid media URL, skip this item
				if ( ! $media_file_url ) {
					return $carry;
				}

				$item = array_filter(
					array(
						'media'      => array(
							'url'       => $media_file_url,
							'thumbnail' => isset( $item['media_type'] ) && 'VIDEO' === $item['media_type'] ? $item['thumbnail_url'] : '',
							'type'      => $media_file_type,
						),
						'user_type'  => 'BUSINESS',
						'media_type' => isset( $item['media_type'] ) ? $item['media_type'] : '',
						'id'         => isset( $item['id'] ) ? $item['id'] : '',
						'share_url'  => isset( $item['permalink'] ) ? $item['permalink'] : '',
						'expires_at' => isset( $item['timestamp'] ) ? strtotime( $item['timestamp'] ) + ( 24 * HOUR_IN_SECONDS ) : '', // Stories expire after 24 hours
						'timestamp'  => isset( $item['timestamp'] ) ? $item['timestamp'] : '',
						'date'       => isset( $item['timestamp'] ) ? date_i18n( 'j F, Y', strtotime( trim( str_replace( array( 'T', '+', ' 0000' ), ' ', $item['timestamp'] ) ) ) ) : '',
					)
				);

				array_push( $carry, $item );
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
			$items_data = $this->get_items_data( $response['data'] );

			$response = array(
				'data'   => $items_data,
				'paging' => isset( $response['paging'] ) ? array(
					'prev' => isset( $response['paging']['previous'] ) ? $response['paging']['cursors']['before'] : '',
					'next' => isset( $response['paging']['next'] ) ? $response['paging']['cursors']['after'] : '',
				) : array(
					'prev' => '',
					'next' => '',
				),
			);
		}

		return $response;
	}

	/**
	 * Function to query Instagram data.
	 *
	 * @param string $access_token Account access_token.
	 * @param int    $id Account id.
	 * @return array
	 */
	public function get_response( $access_token = null, $id = null ) {
		$url      = $this->get_url( $access_token, $id );
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
	 * @param int    $id Account id.
	 * @return string
	 */
	public function get_url( $access_token = null, $id = null ) {
		$url = add_query_arg(
			array(
				'fields'       => 'id,media_type,media_url,thumbnail_url,permalink,timestamp',
				'access_token' => $access_token,
			),
			"{$this->api_url}/{$id}/stories"
		);

		return $url;
	}
}
