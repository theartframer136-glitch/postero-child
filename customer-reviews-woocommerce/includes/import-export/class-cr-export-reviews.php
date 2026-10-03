<?php

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

if ( ! class_exists( 'CR_Export_Reviews' ) ):

class CR_Export_Reviews {

	const FILE_PATH = 'export_reviews.csv';
	const TEMP_FILE_PATH = 'export_reviews_temp.csv';
	protected $page_url;
	protected $menu_slug;
	protected $admin_menu;
	protected $tab;
	protected $settings;
	public static $file_write_buffer = 25;

	public function __construct( $admin_menu ) {
		$this->menu_slug = 'cr-import-export';
		$this->admin_menu = $admin_menu;
		$this->tab = 'export';
		$this->page_url = add_query_arg(
			array(
				'page' => $this->admin_menu->get_page_slug()
			),
			admin_url( 'admin.php' )
		);

		add_action( 'admin_init', array( $this, 'handle_download' ) );
		add_filter( 'cr_import_export_tabs', array( $this, 'register_tab' ) );
		add_action( 'cr_import_export_display_' . $this->tab, array( $this, 'display' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'include_scripts' ) );
		add_action( 'wp_ajax_cr_export_chunk', array( $this, 'export_chunk' ) );
	}

	public function register_tab( $tabs ) {
		$tabs[$this->tab] = __( 'Export Reviews', 'customer-reviews-woocommerce' );
		return $tabs;
	}

	public function display() {
		$this->init_settings();
		WC_Admin_Settings::output_fields( $this->settings );

		$download_url = add_query_arg( array(
				'action'   => 'cr-download-export-reviews',
				'_wpnonce' => wp_create_nonce( 'download_csv_export_reviews' )
		), $this->page_url );

		?>
		<div id="cr-export-reviews">
				<button type="button" class="button button-primary" id="cr-export-button" data-nonce="<?php echo wp_create_nonce( 'cr-export-reviews' ); ?>">
					<?php _e( 'Export', 'customer-reviews-woocommerce' ); ?>
				</button>
				<?php
				if( file_exists( get_temp_dir() . self::FILE_PATH ) ):
				?>
				<a href="<?php echo esc_url( $download_url ); ?>" class="cr-export-reviews-download button button-primary" target="_blank"><?php _e( 'Download', 'customer-reviews-woocommerce' ); ?></a>
				<?php
				endif;
				?>
		</div>
		<div id="cr-export-progress">
				<h2 id="cr-export-text"><?php _e( 'Export is in progress', 'customer-reviews-woocommerce' ); ?></h2>
				<progress id="cr-export-progress-bar" max="100" value="0" data-nonce="<?php echo wp_create_nonce( 'cr-export-progress' ); ?>"></progress>
				<div>
						<button id="cr-export-cancel" class="button button-secondary" data-nonce="<?php echo wp_create_nonce( 'cr-export-cancel' ); ?>" data-cancelled="0">
							<?php _e( 'Cancel', 'customer-reviews-woocommerce' ); ?>
						</button>
				</div>
		</div>
		<div id="cr-export-results">
				<h3 id="cr-export-result-status"><?php _e( 'Export Completed', 'customer-reviews-woocommerce' ); ?></h3>
				<p id="cr-export-result-started"></p>
				<p id="cr-export-result-finished"></p>
				<p id="cr-export-result-exported" data-count="0"></p>
				<br>
				<a id="cr-export-download" href="<?php echo esc_url( $download_url ); ?>" class="button button-primary" style="display: none"><?php _e( 'Download', 'customer-reviews-woocommerce' ); ?></a>
		</div>

		<?php
	}

	public function handle_download() {
		if( isset( $_GET['action'] ) && $_GET['action'] === 'cr-download-export-reviews' ){
			// Ensure a valid nonce has been provided
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'download_csv_export_reviews' ) ) {
				wp_die( sprintf( __( 'Failed to download: invalid nonce. <a href="%s">Return to settings</a>', 'customer-reviews-woocommerce' ), $this->page_url ) );
			}

			$filename = get_temp_dir() . self::FILE_PATH;

			ignore_user_abort( true );

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="export-reviews.csv"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Connection: Keep-Alive' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize($filename) );

			readfile($filename);
			register_shutdown_function( array( $this, 'remove_file' ), $filename );

			exit;
		}
	}

	protected function init_settings() {
		$desc = '';
		if( file_exists( get_temp_dir() . self::FILE_PATH ) ) {
			$desc = __( 'A utility to export reviews and replies to a CSV file. Use the Export button to start export of reviews. Use the Download button to download the last export.', 'customer-reviews-woocommerce' );
		} else {
			$desc = __( 'A utility to export reviews and replies to a CSV file.', 'customer-reviews-woocommerce' );
		}
		$this->settings = array(
			array(
				'title' => __( 'Export Reviews to CSV File', 'customer-reviews-woocommerce' ),
				'type'  => 'title',
				'desc'  => $desc,
				'id'    => 'cr_export'
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cr_export'
			)
		);
	}

	public function include_scripts() {
		if( $this->is_this_page() ) {
				wp_register_script( 'cr-export-reviews', plugins_url('js/admin-export.js', dirname( dirname( __FILE__ ) ) ), ['jquery'], Ivole::CR_VERSION );

				wp_localize_script(
					'cr-export-reviews',
					'CrExportStrings',
					array(
						'exporting' => __( 'Export is in progress (%s/%s completed)', 'customer-reviews-woocommerce' ),
						'cancelling' => __( 'Cancelling', 'customer-reviews-woocommerce' ),
						'cancel' => __( 'Cancel', 'customer-reviews-woocommerce' ),
						'export_cancelled' => __( 'Export Cancelled', 'customer-reviews-woocommerce' ),
						'export_failed' => __( 'Export Failed', 'customer-reviews-woocommerce' ),
						'result_started' => __( 'Started: %s', 'customer-reviews-woocommerce' ),
						'result_finished' => __( 'Finished: %s', 'customer-reviews-woocommerce' ),
						'result_cancelled' => __( 'Cancelled: %s', 'customer-reviews-woocommerce' ),
						'result_exported' => __( '%d review(s) and/or reply(s) successfully exported', 'customer-reviews-woocommerce' ),
						'result_qna_exported' => __( '%d question(s) and/or answer(s) successfully exported', 'customer-reviews-woocommerce' )
					)
				);

				wp_enqueue_script( 'cr-export-reviews' );
		}
	}

	public function is_this_page() {
		return ( isset( $_GET['page'] ) && $_GET['page'] === $this->menu_slug );
	}

	public function remove_file( $filename ) {
		if( file_exists( $filename ) ) {
			unlink( $filename );
		}
	}

	public function export_chunk() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json(
				array(
					'success' => false,
					'data'    => array(
						'message' => __( 'Permission denied', 'customer-reviews-woocommerce' )
					),
				)
			);
			wp_die();
		}
		if ( ! check_ajax_referer( 'cr-export-reviews', 'nonce', false ) ) {
			wp_send_json(
				array(
					'success' => false,
					'data'    => array(
						'message'  => __( 'Error: nonce expired, please reload the page and try again', 'customer-reviews-woocommerce' )
					)
				)
			);
			wp_die();
		}
		$file_path = get_temp_dir() . self::FILE_PATH;
		$temp_file_path = get_temp_dir() . self::TEMP_FILE_PATH;
		// ensure that the folder exists
		$dirname = dirname( $temp_file_path );
		if ( ! is_dir( $dirname ) ) {
			$res = mkdir( $dirname, 0755 );
			if ($res === false) {
				wp_send_json(
					array(
						'success'  => false,
						'data'     => array(
							'message'  => sprintf( __( 'Export failed: Could not create a folder in %s. Please check folder permissions.', 'customer-reviews-woocommerce' ), '<code>' . dirname( $dirname ) . '</code>' ),
						)
					)
				);
				wp_die();
			}
		}
		// get the total count of reviews to export unless it is provided as an input parameter
		$total_reviews = intval( $_POST['totalReviews'] );
		$total_replies = intval( $_POST['totalReplies'] );
		$total = $total_reviews + $total_replies;
		$shop_page_ids_a = CR_Reviews_List_Table::get_shop_page();
		$shop_page_ids_a = array_map( 'intval', (array) $shop_page_ids_a );
		if ( $shop_page_ids_a ) {
			$placeholders = implode( ',', array_fill( 0, count( $shop_page_ids_a ), '%d' ) );
			$in_clause = " OR p.ID IN ($placeholders)";
			$in_args = $shop_page_ids_a;
		} else {
			$in_clause = '';
			$in_args = array();
		}
		if ( 0 >= $total ) {
			// count reviews
			$query_count_reviews = $wpdb->prepare(
				"SELECT COUNT(*)
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
					INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID
					WHERE c.comment_approved = %s
						AND (p.post_type = %s{$in_clause})
						AND m.meta_key = %s",
				array_merge(
					array( '1', 'product' ),
					$in_args,
					array( 'rating' )
				)
			);
			$total_reviews = $wpdb->get_var( $query_count_reviews );
			// count replies to reviews
			$query_count_replies = $wpdb->prepare(
				"SELECT COUNT(*)
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
					INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_parent
					WHERE c.comment_approved = %s
						AND (p.post_type = %s{$in_clause})
						AND m.meta_key = %s",
				array_merge(
					array( '1', 'product' ),
					$in_args,
					array( 'rating' )
				)
			);
			$total_replies = $wpdb->get_var( $query_count_replies );
		}
		//
		$offset_reviews = intval( $_POST['offsetReviews'] );
		$offset_replies = intval( $_POST['offsetReplies'] );
		// read next chunk of reviews from the database
		$query_reviews_chunk = $wpdb->prepare(
			"SELECT *
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
				INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID
				WHERE c.comment_approved = %s
					AND (p.post_type = %s{$in_clause})
					AND m.meta_key = %s
				LIMIT %d, %d",
			array_merge(
				array( '1', 'product' ),
				$in_args,
				array( 'rating', $offset_reviews, self::$file_write_buffer )
			)
		);
		$result_reviews_chunk = $wpdb->get_results( $query_reviews_chunk );
		if ( ! $result_reviews_chunk && ! is_array( $result_reviews_chunk ) ) {
			wp_send_json(
				array(
					'success'  => false,
					'data'     => array(
						'message'  => __( 'Export failed: Could not read reviews from the database.', 'customer-reviews-woocommerce' )
					)
				)
			);
			wp_die();
		}
		// open the temporary file
		if ( 0 < count( $result_reviews_chunk ) ) {
			$file_open_mode = ( $offset_reviews + $offset_replies ) ? 'a' : 'w';
			$file = fopen( $temp_file_path, $file_open_mode );
			if ( $file ) {
				if ( 0 === ( $offset_reviews + $offset_replies ) ) {
					fputcsv( $file, CR_Import_Reviews::$columns );
				}
				$this->process_chunk( $file, $result_reviews_chunk, $shop_page_ids_a );
				fclose( $file );
			} else {
				wp_send_json(
					array(
						'success'  => false,
						'data'     => array(
							'message'  => sprintf( __( 'Export failed: Could not open the file \'%s\' for writing.', 'customer-reviews-woocommerce' ), $temp_file_path )
						)
					)
				);
				wp_die();
			}
		}
		//
		$offset_reviews += count( $result_reviews_chunk );

		$last_chunk = false;
		if ( self::$file_write_buffer > count( $result_reviews_chunk ) ) {
			// all reviews have been fetched, now select replies to reviews
			$query_replies_chunk = $wpdb->prepare(
				"SELECT *
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
					INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_parent
					WHERE c.comment_approved = %s
						AND (p.post_type = %s{$in_clause})
						AND m.meta_key = %s
					LIMIT %d, %d",
				array_merge(
					array( '1', 'product' ),
					$in_args,
					array( 'rating', $offset_replies, self::$file_write_buffer )
				)
			);
			$result_replies_chunk = $wpdb->get_results( $query_replies_chunk );
			if ( ! $result_replies_chunk && ! is_array( $result_replies_chunk ) ) {
				wp_send_json(
					array(
						'success'  => false,
						'data'     => array(
							'message'  => __( 'Export failed: Could not read replies to reviews from the database.', 'customer-reviews-woocommerce' )
						)
					)
				);
				wp_die();
			}
			// open the temporary file
			if ( 0 < count( $result_replies_chunk ) ) {
				$file_open_mode = ( $offset_reviews + $offset_replies ) ? 'a' : 'w';
				$file = fopen( $temp_file_path, $file_open_mode );
				if ( $file ) {
					if ( 0 === ( $offset_reviews + $offset_replies ) ) {
						fputcsv( $file, CR_Import_Reviews::$columns );
					}
					$this->process_chunk( $file, $result_replies_chunk, $shop_page_ids_a );
					fclose( $file );
				} else {
					wp_send_json(
						array(
							'success'  => false,
							'data'     => array(
								'message'  => sprintf( __( 'Export failed: Could not open the file \'%s\' for writing.', 'customer-reviews-woocommerce' ), $temp_file_path )
							)
						)
					);
					wp_die();
				}
			}
			//
			$offset_replies += count( $result_replies_chunk );
			//
			if ( self::$file_write_buffer > count( $result_replies_chunk ) ) {
				$last_chunk = true;
				rename( $temp_file_path, $file_path );
			}
		}
		//
		wp_send_json(
			array(
				'success' => true,
				'offsetReviews'  => $offset_reviews, // for reading the next chunk of reviews
				'offsetReplies'  => $offset_replies, // for reading the next chunk of replies to reviews
				'totalReviews' => $total_reviews, // for avoiding calculating totals every time
				'totalReplies' => $total_replies, // for avoiding calculating totals every time
				'lastChunk' => $last_chunk // for knowing when there is no more data in the database
			)
		);
	}

	private function process_chunk( $file, $data, $shop_page_ids ) {
		$shop_page_id = wc_get_page_id( 'shop' );
		// extract relevant fields from each review or reply for writing them into the file
		foreach ( $data as $review_or_reply ) {
			$product = wc_get_product( $review_or_reply->comment_post_ID );
			$row = array();
			$row[] = $review_or_reply->comment_ID;

			// extract title of a review if any
			$title = get_comment_meta( $review_or_reply->comment_ID, 'cr_rev_title', true );
			if ( $title ) {
				$row[] = $title;
			} else {
				$row[] = '';
			}

			$row[] = $review_or_reply->comment_content;
			$row[] = get_comment_meta ( $review_or_reply->comment_ID, 'rating', true );
			$row[] = $review_or_reply->comment_parent > 0 ? $review_or_reply->comment_parent : '';
			$row[] = $review_or_reply->comment_date;
			$row[] = in_array( $review_or_reply->comment_post_ID, $shop_page_ids ) ? -1 : $review_or_reply->comment_post_ID;
			$row[] = $product ? $product->get_sku() : '';
			$row[] = $review_or_reply->comment_author;
			$row[] = $review_or_reply->comment_author_email;
			$row[] = get_comment_meta ( $review_or_reply->comment_ID, 'ivole_order', true );

			$media = array();
			// export images attached to reviews
			$images = get_comment_meta ( $review_or_reply->comment_ID, CR_Reviews::REVIEWS_META_LCL_IMG, false );
			if ( is_array( $images ) && 0 < count( $images ) ) {
				foreach( $images as $image ) {
					$image_url = wp_get_attachment_url( $image );
					if ( $image_url ) {
						$media[] = $image_url;
					}
				}
			}
			// export videos attached to reviews
			$videos = get_comment_meta ( $review_or_reply->comment_ID, CR_Reviews::REVIEWS_META_LCL_VID, false );
			if ( is_array( $videos ) && 0 < count( $videos ) ) {
				foreach( $videos as $video ) {
					$video_url = wp_get_attachment_url( $video );
					if ( $video_url ) {
						$media[] = $video_url;
					}
				}
			}
			// save URLs of images and videos into the 'media' column of a CSV file
			if ( 0 < count( $media ) ) {
				$row[] = implode( ',', $media );
			} else {
				$row[] = '';
			}

			// extract location of a review if any
			$country_col = '';
			$country = get_comment_meta( $review_or_reply->comment_ID, 'ivole_country', true );
			if ( is_array( $country ) && 2 === count( $country ) ) {
				if ( isset( $country['code'] ) ) {
					$country_col = $country['code'];
					if ( isset( $country['desc'] ) ) {
						$country_col .= ' | ' . $country['desc'];
					}
				}
			}
			$row[] = $country_col;

			fputcsv( $file, $row );
		}
	}

}

endif;
