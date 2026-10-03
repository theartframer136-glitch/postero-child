<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Local_Forms_Ajax' ) ) :

	class CR_Local_Forms_Ajax {
		private $form_id;
		private $items;
		private $customer_email;
		private $customer_name;
		private $form_header;
		private $form_body;

		const RECOMMENDATION_EVENTS_TABLE = 'cr_reco_events';

		public function __construct() {
			add_action( 'wp_ajax_cr_local_forms_submit', array( $this, 'submit_form' ) );
			add_action( 'wp_ajax_nopriv_cr_local_forms_submit', array( $this, 'submit_form' ) );
			add_action( 'wp_ajax_cr_local_forms_upload_media', array( $this, 'upload_media' ) );
			add_action( 'wp_ajax_nopriv_cr_local_forms_upload_media', array( $this, 'upload_media' ) );
			add_action( 'wp_ajax_cr_local_forms_delete_media', array( $this, 'delete_media' ) );
			add_action( 'wp_ajax_nopriv_cr_local_forms_delete_media', array( $this, 'delete_media' ) );
			add_action( 'wp_ajax_cr_local_forms_event_click', array( $this, 'event_click' ) );
			add_action( 'wp_ajax_nopriv_cr_local_forms_event_click', array( $this, 'event_click' ) );
		}

		public function submit_form() {
			if ( ! isset( $_POST['formId'] ) || ! is_string( $_POST['formId'] ) ) {
				wp_die();
			}
			$form_id = sanitize_text_field( wp_unslash( $_POST['formId'] ) );
			check_ajax_referer( 'cr_local_forms_submit_' . $form_id, 'nonce' );

			// fetch product recommendations
			$recom_prods = $this->get_recommended_products();
			$recommendations = $this->get_recommended_products_html( $recom_prods, $form_id );
			//
			if( CR_Local_Forms::TEST_FORM === $form_id ) {
				// submission of a test form
				wp_send_json_success( $recommendations );
			} else {
				global $wpdb;
				$table_name = $wpdb->prefix . CR_Local_Forms::FORMS_TABLE;
				$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `formId` = %s", $form_id ) );
				if( null !== $record ) {
					$db_items = json_decode( $record->items, true );

					foreach( $_POST['items'] as $review_item ) {
						foreach( $db_items as $key => $item ) {
							if ( intval( $review_item['id'] ) === intval( $item['id'] ) ) {
								$db_items[$key]['rating'] = intval( $review_item['rating'] );
								$db_items[$key]['comment'] = wp_kses_post( $review_item['comment'] );
								if ( isset( $review_item['media'] ) && is_array( $review_item['media'] ) ) {
									$validated_media = array();
									foreach ( $review_item['media'] as $media_item ) {
										if ( isset( $media_item['id'] ) && $media_item['id'] ) {
											if ( isset( $media_item['key'] ) && $media_item['key'] ) {
												$attachmentId = intval( $media_item['id'] );
												if ( 'attachment' === get_post_type( $attachmentId ) ) {
													if ( $media_item['key'] === get_post_meta( $attachmentId, 'cr-upload-temp-key', true ) ) {
														$validated_media[] = $attachmentId;
													}
												}
											}
										}
									}
									$db_items[$key]['media'] = array_values( $validated_media );
								} else {
									$db_items[$key]['media'] = array();
								}
								break;
							}
						}
					}

					$req = new stdClass();
					$req->order = new stdClass();
					$req->order->id = $record->orderId;
					$req->order->display_name = sanitize_text_field( $_POST['displayName'] );
					$req->order->items = array();
					foreach( $db_items as $item ) {
						if( -1 === intval( $item['id'] ) ) {
							$req->order->shop_rating = $item['rating'];
							$req->order->shop_comment = $item['comment'];
						} else {
							$product = new stdClass();
							$product->id = $item['id'];
							$product->name = $item['name'];
							$product->price = $item['price'];
							$product->rating = $item['rating'];
							$product->comment = $item['comment'];
							$product->media = $item['media'];
							$req->order->items[] = $product;
						}
					}

					$db_items = json_encode( $db_items );
					$update_result = $wpdb->update( $table_name, array(
						'displayName' => $req->order->display_name,
						'items' => $db_items
					), array( 'formId' => $form_id ) );
					if( false !== $update_result ) {
						CR_Endpoint::create_review( $req, true );
					};
				}
				$this->record_recommendations_views( $recom_prods, $form_id, 'view' );
				wp_send_json_success( $recommendations );
			}
		}

		public function upload_media() {
			$return = array(
				'code' => 100,
				'message' => ''
			);
			if ( 'yes' === get_option( 'ivole_form_attach_media', 'no' ) ) {
				if ( isset( $_POST['cr_form'] ) && isset( $_POST['cr_item'] ) ) {
					if ( self::form_exists( $_POST['cr_form'] ) ) {
						if ( isset( $_FILES ) && is_array( $_FILES ) && 0 < count( $_FILES ) ) {
							// check the file size
							$attach_image_size = get_option( 'ivole_attach_image_size', 25 );
							$max_size = 1024 * 1024 * $attach_image_size;
							if ( $max_size < $_FILES['cr_file']['size'] ) {
								$return['code'] = 501;
								$return['message'] = sprintf( __( 'The file cannot be uploaded because its size exceeds the limit of %d MB', 'customer-reviews-woocommerce' ), $attach_image_size );
								wp_send_json( $return );
								return;
							}
							// check the file type
							$file_name_parts = explode( '.', $_FILES['cr_file']['name'] );
							$file_ext = $file_name_parts[ count( $file_name_parts ) - 1 ];
							if( ! CR_Reviews::is_valid_file_type( $file_ext ) ) {
								$return['code'] = 502;
								$return['message'] = __( 'Error: accepted file types are PNG, JPG, JPEG, GIF, MP4, MPEG, OGG, WEBM, MOV, AVI', 'customer-reviews-woocommerce' );
								wp_send_json( $return );
								return;
							}
							// upload the file
							$attachmentId = media_handle_upload( 'cr_file', 0 );
							if( !is_wp_error( $attachmentId ) ) {
								$upload_key = bin2hex( openssl_random_pseudo_bytes( 10 ) );
								if( false !== update_post_meta( $attachmentId, 'cr-upload-temp-key', $upload_key ) ) {
									// save the attachment id in the database
									if( false !== self::update_db_item( $_POST['cr_form'], $_POST['cr_item'], $attachmentId, false ) ) {
										// return to js
										$return['attachment'] = array(
											'id' => $attachmentId,
											'key' => $upload_key
										);
									} else {
										$return['code'] = 504;
										$return['message'] = 'Error: could not update media in the database.';
									}
								} else {
									$return['code'] = 503;
									$return['message'] = $_FILES['cr_file']['name'] . ': could not update the upload key.';
								}
							} else {
								$return['code'] = $attachmentId->get_error_code();
								$return['message'] = $attachmentId->get_error_message();
							}
							$return['code'] = 200;
							$return['message'] = 'OK';
						}
					}
				}
			}
			wp_send_json( $return );
		}

		public function delete_media() {
			$return = array(
				'code' => 100,
				'message' => ''
			);
			if( isset( $_POST['image'] ) && $_POST['image'] ) {
				$image_decoded = json_decode( stripslashes( $_POST['image'] ), true );
				if( $image_decoded && is_array( $image_decoded ) ) {
					if( isset( $image_decoded["id"] ) && $image_decoded["id"] ) {
						if( isset( $image_decoded["key"] ) && $image_decoded["key"] ) {
							$attachmentId = intval( $image_decoded["id"] );
							if( 'attachment' === get_post_type( $attachmentId ) ) {
								if( $image_decoded["key"] === get_post_meta( $attachmentId, 'cr-upload-temp-key', true ) ) {
									if( wp_delete_attachment( $attachmentId, true ) ) {
										if( false !== self::update_db_item( $_POST['cr_form'], $_POST['cr_item'], $attachmentId, true ) ) {
											$return['code'] = 200;
											$return['message'] = 'OK';
										} else {
											$return['code'] = 508;
											$return['message'] = 'Error: could not delete a media ID in the database.';
										}
									} else {
										$return['code'] = 507;
										$return['message'] = 'Error: could not delete the image.';
									}
								} else {
									$return['code'] = 506;
									$return['message'] = 'Error: meta key does not match.';
								}
							} else {
								$return['code'] = 505;
								$return['message'] = 'Error: id does not belong to an attachment.';
							}
						} else {
							$return['code'] = 504;
							$return['message'] = 'Error: image key is not set.';
						}
					} else {
						$return['code'] = 503;
						$return['message'] = 'Error: image id is not set.';
					}
				} else {
					$return['code'] = 502;
					$return['message'] = 'Error: JSON decoding problem.';
				}
			} else {
				$return['code'] = 501;
				$return['message'] = 'Error: no image to delete.';
			}
			wp_send_json( $return );
		}

		public static function update_db_item( $form_id, $item_id, $attachmentId, $delete ) {
			global $wpdb;
			$table_name = $wpdb->prefix . CR_Local_Forms::FORMS_TABLE;
			$update_result = false;
			$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table_name` WHERE `formId` = %s", $form_id ) );
			if( null !== $record ) {
				$db_items = json_decode( $record->items, true );
				foreach( $db_items as $key => $value ) {
					if( isset( $value['id'] ) && $item_id == $value['id'] ) {
						if( $delete ) {
							if( isset( $value['media'] ) && is_array( $value['media'] ) ) {
								$k = array_search( $attachmentId, $value['media'] );
								if( false !== $k ) {
									unset( $db_items[$key]['media'][$k] );
									$db_items[$key]['media'] = array_values( $db_items[$key]['media'] );
								}
							}
						} else {
							$db_items[$key]['media'][] = $attachmentId;
						}
						$db_items = json_encode( $db_items );
						$update_result = $wpdb->update( $wpdb->prefix . CR_Local_Forms::FORMS_TABLE, array(
							'items' => $db_items
						), array( 'formId' => $form_id ) );
						break;
					}
				}
			}
			return $update_result;
		}

		public static function form_exists( $form_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . CR_Local_Forms::FORMS_TABLE;
			$form_exists = false;
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM `$table_name` WHERE `formId` = %s",
				$form_id
			);
			$row_exists = $wpdb->get_var( $query );
			if ( $row_exists > 0 ) {
				$form_exists = true;
			}
			return $form_exists;
		}

		private function get_recommended_products() {
			if ( ! function_exists( 'wc_get_products' ) ) {
				return [];
			}

			$products = wc_get_products( [
				'limit'        => 3,
				'status'       => 'publish',
				'stock_status' => 'instock',
				'visibility'   => 'catalog',
				'orderby'      => 'rand',
			] );

			$result = [];

			foreach ( $products as $product ) {
				$image_id  = $product->get_image_id();
				$image_url = $image_id
					? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
					: wc_placeholder_img_src();

				$result[] = [
					'id'           => $product->get_id(),
					'name'         => $product->get_name(),
					'price'        => wc_get_price_to_display( $product ),
					'rating'       => (float) $product->get_average_rating(),
					'review_count' => (int) $product->get_review_count(),
					'image'        => $image_url,
					'permalink'    => get_permalink( $product->get_id() ),
				];
			}

			return $result;
		}

		private function get_recommended_products_html( $products, $form_id ) {
			if ( empty( $products ) ) {
				return '';
			}

			// create HTML to return
			ob_start();
			foreach ( $products as $product ) :
				?>
				<div class="cr-form-recommend-prod-container">
					<div class="cr-form-recommend-prod-price">
						<?php echo wc_price( $product['price'] ); ?>
					</div>

					<img
						src="<?php echo esc_url( $product['image'] ); ?>"
						alt="<?php echo esc_attr( $product['name'] ); ?>"
					/>

					<div class="cr-form-recommend-prod-content">
						<h3 class="cr-form-recommend-prod-title">
							<?php echo esc_html( $product['name'] ); ?>
						</h3>

						<div class="cr-form-recommend-prod-rating">
							<div class="cr-form-recommend-prod-rating-top">
								<div class="cr-form-recommend-prod-rating-rng">
									<?php echo esc_html( number_format( $product['rating'], 1 ) ); ?>
								</div>
								<div class="cr-form-recommend-prod-rating-str">
									<?php
										$label = sprintf( __( 'Rated %s out of 5', 'customer-reviews-woocommerce' ), number_format( $product['rating'], 1 ) );
										$html_star_rating = '<div class="crstar-rating-svg" role="img" aria-label="' . esc_attr( $label ) . '">' . CR_Reviews::get_star_rating_svg( $product['rating'], 0, '' ) . '</div>';
										echo $html_star_rating;
									?>
								</div>
							</div>
							<div class="cr-form-recommend-prod-rating-btm">
								<?php
									$count = (int) $product['review_count'];
									echo esc_html(
										sprintf(
											/* translators: %s: number of reviews */
											_n( '%s review', '%s reviews', $count, 'customer-reviews-woocommerce' ),
											$count
										)
									);
								?>
							</div>
						</div>

						<a
							href="<?php echo esc_url(
								add_query_arg(
									array(
										'referral_session' => $form_id,
										'utm_source' => wp_parse_url( home_url(), PHP_URL_HOST ),
										'utm_medium' => 'cusrev_recommendation',
										'utm_content' => 'local_aggregated_review_form'
									),
									$product['permalink']
									)
								); ?>"
							class="cr-form-recommend-prod-buy"
							data-productid="<?php echo esc_attr( $product['id'] ); ?>"
							data-formid="<?php echo esc_attr( $form_id ); ?>"
						>
							<?php esc_html_e( 'View', 'customer-reviews-woocommerce' ); ?>
						</a>
					</div>
				</div>
				<?php
			endforeach;

			$html = ob_get_clean();

			return $html;
		}

		private function record_recommendations_views( $products, $form_id, $event_type ) {
			global $wpdb;

			$event_type = sanitize_key( $event_type );

			// Allowed event types (must match ENUM definition)
			$allowed_event_types = array(
				'view',
				'click',
				'sale',
			);
			if ( ! in_array( $event_type, $allowed_event_types, true ) ) {
				return;
			}

			// Extract and normalize product IDs
			$product_ids = array();

			foreach ( $products as $product ) {
				if ( is_array( $product ) && isset( $product['id'] ) ) {
					$product_ids[] = (int) $product['id'];
				}
			}

			$product_ids = array_unique( array_filter( $product_ids ) );

			if ( empty( $product_ids ) || ! $form_id ) {
				return;
			}

			$table_name = $wpdb->prefix . self::RECOMMENDATION_EVENTS_TABLE;

			// ensure table exists
			if ( $wpdb->get_var(
				$wpdb->prepare( "SHOW TABLES LIKE %s", $table_name )
			) !== $table_name ) {

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';

				$charset_collate = $wpdb->get_charset_collate();

				$sql = "
					CREATE TABLE {$table_name} (
						id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
						form_id VARCHAR(190) NOT NULL,
						product_id BIGINT(20) UNSIGNED NOT NULL,
						event_type ENUM('view','click','sale') NOT NULL,
						created_at DATETIME NOT NULL,
						PRIMARY KEY (id),
						KEY product (product_id),
						KEY form (form_id),
						KEY event (event_type),
						KEY created_at (created_at)
					) {$charset_collate};
				";

				dbDelta( $sql );
			}

			// insert impression events
			$now = current_time( 'mysql' );

			$placeholders = array();
			$values       = array();

			foreach ( $product_ids as $product_id ) {
				$placeholders[] = "(%s, %d, %s, %s)";
				$values[]       = $form_id;
				$values[]       = $product_id;
				$values[]       = $event_type;
				$values[]       = $now;
			}

			$sql = "
				INSERT INTO {$table_name}
					(form_id, product_id, event_type, created_at)
				VALUES " . implode( ', ', $placeholders );

			$wpdb->query( $wpdb->prepare( $sql, $values ) );
		}

		public function event_click() {
			check_ajax_referer( 'cr_click_event', 'nonce' );

			$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
			$form_id    = isset( $_POST['form_id'] ) ? $_POST['form_id'] : '';

			if ( ! $product_id || ! $form_id || 'test' === $form_id ) {
				wp_die();
			}

			$this->record_recommendations_views(
				array(
					array( 'id' => $product_id ),
				),
				$form_id,
				'click'
			);

			wp_die();
		}

	}

endif;
