<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/reviews/class-cr-reviews-media-download.php. Kept: the
 * front-end AJAX action cr_auto_download_media_frontend that the plugin's
 * frontend.js calls on pages showing a review whose photo/video is still
 * hosted by CusRev, to copy it into the Media Library. Left out: the admin
 * "download all media" screen actions.
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Reviews_Media_Download' ) ) :

	class CR_Reviews_Media_Download {

		public function __construct() {
			add_action( 'wp_ajax_cr_auto_download_media_frontend', array( $this, 'download_media_frontend' ) );
			add_action( 'wp_ajax_nopriv_cr_auto_download_media_frontend', array( $this, 'download_media_frontend' ) );
		}

		public function download_media_frontend() {
			$transientFrontendExists = get_transient( 'cr_download_media_frontend' );
			if ( ! $transientFrontendExists && isset( $_POST['reviewID'] ) && 0 < intval( $_POST['reviewID'] ) ) {
				global $wpdb;
				$reviewID = intval( $_POST['reviewID'] );

				$mediaFiles = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT comment_id, meta_key, meta_value
						FROM {$wpdb->commentmeta}
						WHERE comment_id = %d
						AND ( meta_key = %s OR meta_key = %s )",
						$reviewID,
						CR_Reviews::REVIEWS_META_IMG,
						CR_Reviews::REVIEWS_META_VID
					),
					OBJECT
				);

				$mediaFileToDownload = null;
				$downloadURL = '';
				$commentMeta = array( 'newCache' => 'cr_media_cache' );
				if( $mediaFiles ) {
					foreach( $mediaFiles as $mediaFile ) {
						$downloadURL = maybe_unserialize( $mediaFile->meta_value );
						$transientExists = get_transient( $downloadURL['url'] );
						if( !$transientExists  ) {
							$mediaFileToDownload = $mediaFile;
							if( 'ivole_review_video' === $mediaFile->meta_key ) {
								$commentMeta['newLocal'] = 'ivole_review_video2';
								$commentMeta['oldCloud'] = 'ivole_review_video';
							} else {
								$commentMeta['newLocal'] = 'ivole_review_image2';
								$commentMeta['oldCloud'] = 'ivole_review_image';
							}
							break;
						}
					}

					if( $mediaFileToDownload ) {
						$this->download_media_file( $mediaFileToDownload, $downloadURL, $commentMeta );
					}
				}
				// do not download next file for at least 5 mins
				set_transient( 'cr_download_media_frontend', 1, 300 );
			}
			wp_send_json( 0 );
		}

		private function download_media_file( $mediaFileToDownload, $downloadURL, $commentMeta ) {
			$return = array(
				'code' => 100,
				'msg' => ''
			);
			// set a transient to prevent parallel downloads of this file for 10 mins
			set_transient( $downloadURL['url'], 1, 600 );
			//
			$tmpFile = download_url( $downloadURL['url'] . '?crsrc=do' );
			$file_array = array(
				'name' => basename( $downloadURL['url'] ),
				'tmp_name' => $tmpFile
			);
			if ( is_wp_error( $tmpFile ) ) {
				$return['code'] = 400;
				$return['msg'] = sprintf( __( 'An error occurred while downloading a media file. Error code: %1$s (%2$s). File name: %3$s', 'customer-reviews-woocommerce' ), $return['code'], $tmpFile->get_error_code() . ' - ' . $tmpFile->get_error_message(), esc_url( $downloadURL['url'] ) );
				// check if the file was deleted by customer and the plugin should stop trying to download it
				if( false !== strpos( $tmpFile->get_error_code(), '404' ) && false !== strpos( $tmpFile->get_error_message(), 'Not Found' ) ) {
					delete_comment_meta( $mediaFileToDownload->comment_id, $commentMeta['oldCloud'], array( 'url' => $downloadURL['url'] ) );
					add_comment_meta( $mediaFileToDownload->comment_id, $commentMeta['newCache'], array( 'external' => $downloadURL['url'], 'local' => -1 ) );
				}
			} else {
				$review = get_comment( $mediaFileToDownload->comment_id );
				if( $review && $review->comment_post_ID ) {
					$customerName = get_comment_author( $mediaFileToDownload->comment_id );
					$product = wc_get_product( $review->comment_post_ID );
					if( $product ) {
						$reviewedItem = $product->get_name();
					} else {
						$reviewedItem = get_the_title( $review->comment_post_ID );
					}
					$fileDesc = sprintf( __( 'Media from review %s', 'customer-reviews-woocommerce' ), $review->comment_ID );
					$customerUserId = $review->user_id ? $review->user_id : 0;
					$reviewId = sprintf( __( 'Review ID: %s', 'customer-reviews-woocommerce' ), $review->comment_ID );
					$post_data = array( 'post_author' => $customerUserId, 'post_date' => $review->comment_date, 'post_content' => $reviewId );
					$mediaId = media_handle_sideload( $file_array, $review->comment_post_ID, $fileDesc, $post_data );
					if ( is_wp_error( $mediaId ) ) {
						$return['code'] = 401;
						$return['msg'] = sprintf(
							__( 'An error occurred while downloading a media file. Error code: %1$s. Error description: %2$s. Error data: %3$s. Product ID: %4$s. Parameters: %5$s.', 'customer-reviews-woocommerce' ),
							print_r( $mediaId->get_error_codes(), true ),
							$mediaId->get_error_message(),
							$mediaId->get_error_data(),
							$review->comment_post_ID . ' (' . $reviewedItem . ')',
							print_r( $post_data, true )
						);
					} else {
						$metaId = add_comment_meta( $review->comment_ID, $commentMeta['newLocal'], $mediaId );
						if( $metaId ) {
							if( add_comment_meta( $review->comment_ID, $commentMeta['newCache'], array( 'external' => $downloadURL['url'], 'local' => $metaId ) ) ) {
								if( delete_comment_meta( $review->comment_ID, $commentMeta['oldCloud'], array( 'url' => $downloadURL['url'] ) ) ) {
									$return['code'] = 200;
									$return['msg'] = sprintf( __( 'Downloaded the file <b>%1$s</b> attached by <b>%2$s</b> to their review of <b>%3$s</b>. Downloading the next file...', 'customer-reviews-woocommerce' ), $file_array[ 'name' ], $customerName, $reviewedItem );
								} else {
									delete_comment_meta( $review->comment_ID, $commentMeta['newLocal'], $mediaId );
									delete_comment_meta( $review->comment_ID, $commentMeta['newCache'], array( 'url' => $downloadURL['url'] ) );
									wp_delete_attachment( $mediaId );
									$return['code'] = 402;
									$return['msg'] = sprintf( __( 'An error occurred while downloading a media file. Error code: %s.', 'customer-reviews-woocommerce' ), $return['code'] );
								}
							} else {
								delete_comment_meta( $review->comment_ID, $commentMeta['newLocal'], $mediaId );
								wp_delete_attachment( $mediaId );
								$return['code'] = 403;
								$return['msg'] = sprintf( __( 'An error occurred while downloading a media file. Error code: %s.', 'customer-reviews-woocommerce' ), $return['code'] );
							}
						} else {
							wp_delete_attachment( $mediaId );
							$return['code'] = 404;
							$return['msg'] = sprintf( __( 'An error occurred while downloading a media file. Error code: %s.', 'customer-reviews-woocommerce' ), $return['code'] );
						}
					}
				} else {
					delete_comment_meta(
						$mediaFileToDownload->comment_id,
						$mediaFileToDownload->meta_key,
						$downloadURL
					);
					$return['code'] = 405;
					$return['msg'] = sprintf( __( 'An error occurred while downloading a media file. Error code: %s.', 'customer-reviews-woocommerce' ), $return['code'] );
				}
				@unlink( $file_array[ 'tmp_name' ] );
			}
			return $return;
		}

	}

endif;
