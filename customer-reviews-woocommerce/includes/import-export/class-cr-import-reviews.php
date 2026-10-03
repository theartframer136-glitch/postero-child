<?php

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

if ( ! class_exists( 'CR_Import_Reviews' ) ):

class CR_Import_Reviews {

	protected $page_url;
	protected $menu_slug;
	protected $admin_menu;
	protected $tab;
	protected $settings;
	public static $columns = array(
		'id',
		'review_title',
		'review_content',
		'review_score',
		'parent',
		'date',
		'product_id',
		'product_sku',
		'display_name',
		'email',
		'order_id',
		'media',
		'location'
	);
	public static $file_read_buffer = 3;

	public function __construct( $admin_menu ) {
		$this->menu_slug = 'cr-import-export';
		$this->admin_menu = $admin_menu;
		$this->tab = 'import';
		$this->page_url = add_query_arg(
			array(
				'page' => $this->admin_menu->get_page_slug()
			),
			admin_url( 'admin.php' )
		);

		add_filter( 'cr_import_export_tabs', array( $this, 'register_tab' ) );
		add_action( 'cr_import_export_display_' . $this->tab, array( $this, 'display' ) );
		add_action( 'wp_ajax_cr_import_upload_csv', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_cr_import_chunk', array( $this, 'import_chunk' ) );
	}

	public function register_tab( $tabs ) {
		$tabs[$this->tab] = __( 'Import Reviews', 'customer-reviews-woocommerce' );
		return $tabs;
	}

	public function display() {
		$download_template_url = add_query_arg(
			array(
				'action'   => 'cr-download-import-template',
				'_wpnonce' => wp_create_nonce( 'download_csv_template' )
			),
			$this->page_url
		);
		$max_upload_size = size_format( wp_max_upload_size() );
		?>
			<div class="cr-import-container" data-nonce="<?php echo wp_create_nonce( 'cr_import_page' ); ?>">
				<h2><?php echo _e( 'Import Reviews from CSV', 'customer-reviews-woocommerce' ); ?></h2>
					<p>
						<?php
							_e( 'You can use this tool to import reviews and replies to reviews in three steps:', 'customer-reviews-woocommerce' );
							echo '<ol><li>';
							echo '<strong>';
							_e( 'Download the template', 'customer-reviews-woocommerce' );
							echo '</strong><br>';
							_e( 'Get the CSV template for entering your reviews and replies to reviews', 'customer-reviews-woocommerce' );
							echo '</li><li>';
							echo '<strong>';
							_e( 'Fill in the template', 'customer-reviews-woocommerce' );
							echo '</strong><br>';
							_e( 'Add your reviews and replies to reviews to the template and save the file (if using MS Excel, choose CSV UTF-8 format)', 'customer-reviews-woocommerce' );
							echo '<ul class="cr-admin-import-steps-desc">';
							echo '<li>';
							_e( 'Make sure to enter valid product IDs that exist on your WooCommerce site', 'customer-reviews-woocommerce' );
							echo '</li>';
							echo '<li>';
							_e( 'Use -1 as a product ID to import general shop reviews that are not related to any particular product', 'customer-reviews-woocommerce' );
							echo '</li>';
							echo '<li>';
							_e( 'Keep the column \'order_id\' blank unless you are importing a file created with the export utility of this plugin', 'customer-reviews-woocommerce' );
							echo '</li>';
							echo '</ul>';
							echo '</li><li>';
							echo '<strong>';
							_e( 'Upload and import', 'customer-reviews-woocommerce' );
							echo '</strong><br>';
							_e( 'Upload the completed template and run the import process', 'customer-reviews-woocommerce' );
							echo '</li></ol>';
						?>
					</p>
					<div id="cr-import-upload-steps">
						<div class="ivole-import-step">
							<h3 class="ivole-step-title"><?php _e( 'Step 1: Download the template', 'customer-reviews-woocommerce' ); ?></h3>
								<a class="button button-secondary" href="<?php echo esc_url( $download_template_url ); ?>" target="_blank">
									<?php _e( 'Download', 'customer-reviews-woocommerce' ); ?>
								</a>
						</div>

						<div class="ivole-import-step">
							<h3 class="ivole-step-title"><?php _e( 'Step 2: Fill in the template', 'customer-reviews-woocommerce' ); ?></h3>
						</div>

						<div class="ivole-import-step">
							<h3 class="ivole-step-title"><?php _e( 'Step 3: Upload and import', 'customer-reviews-woocommerce' ); ?></h3>
								<p id="cr-import-status"></p>
								<div id="cr-import-filelist" class="cr-import-upload-filelist">
									<?php _e( 'No file selected', 'customer-reviews-woocommerce' ); ?>
								</div>
								<div id="cr-upload-container">
									<table border="0" cellpadding="0" cellspacing="0">
										<tbody>
											<tr class="cr-import-upload-tr">
												<td>
													<button type="button" id="cr-select-button"><?php _e( 'Choose File', 'customer-reviews-woocommerce' ); ?></button><br/>
													<small>
													<?php
													echo esc_html(
														sprintf(
															__( 'Maximum size: %s', 'customer-reviews-woocommerce' ),
															$max_upload_size
														)
													);
													?>
													</small>
												</td>
												<td>
													<button type="button" class="button button-primary cr-import-upload-btn" id="cr-upload-button" disabled><?php _e( 'Upload', 'customer-reviews-woocommerce' ); ?></button>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<div id="cr-import-progress" class="cr-import-progress">
								<h2 id="cr-import-text"><?php _e( 'Import is in progress', 'customer-reviews-woocommerce' ); ?></h2>
								<progress id="cr-progress-bar" max="100" value="0" data-numreviews="0"></progress>
								<div>
										<button id="cr-import-cancel" class="button button-secondary" data-cancelled="0"><?php _e( 'Cancel', 'customer-reviews-woocommerce' ); ?></button>
								</div>
						</div>
						<div id="cr-import-results" class="cr-import-results">
								<h3 id="cr-import-result-status"><?php _e( 'Upload Completed', 'customer-reviews-woocommerce' ); ?></h3>
								<p id="cr-import-result-started"></p>
								<p id="cr-import-result-finished"></p>
								<p id="cr-import-result-rev-imported" data-count="0"></p>
								<p id="cr-import-result-rep-imported" data-count="0"></p>
								<p id="cr-import-result-rev-skipped" data-count="0"></p>
								<p id="cr-import-result-rep-skipped" data-count="0"></p>
								<p id="cr-import-result-errors" data-count="0"></p>
								<div id="cr-import-result-details">
										<h4><?php _e( 'Error details:', 'customer-reviews-woocommerce' ); ?></h4>
								</div>
								<br>
								<a href="" class="button button-secondary"><?php _e( 'New Upload', 'customer-reviews-woocommerce' ); ?></a>
						</div>
				</div>
			</div>
		<?php
	}

	public function remove_file( $filename ) {
		if ( file_exists( $filename ) ) {
			unlink( $filename );
			clearstatcache();
		}
	}

	public function handle_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'data'    => array(
						'message' => __('Permission denied', 'customer-reviews-woocommerce')
					),
				)
			);
			wp_die();
		}

		if ( ! check_ajax_referer( 'media-form', '_wpnonce', false ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'data'    => array(
						'message'  => __( 'Error: nonce expired, please reload the page and try again', 'customer-reviews-woocommerce' )
					)
				)
			);
			wp_die();
		}

		if ( ! isset( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'data'    => array(
						'message' => __('No file was uploaded', 'customer-reviews-woocommerce')
					),
				)
			);
			wp_die();
		}

		if ( extension_loaded( 'fileinfo' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$real_mime = finfo_file( $finfo, $_FILES['file']['tmp_name'] );
			finfo_close( $finfo );
			if ( ! in_array( $real_mime, array( 'text/plain', 'text/csv', 'text/x-csv', 'application/vnd.ms-excel', 'application/csv', 'application/x-csv' ) ) ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'data'    => array(
							'message'  => __('The uploaded file is not a valid CSV file', 'customer-reviews-woocommerce'),
							'filename' => $_FILES['file']['name'],
						)
					)
				);
				wp_die();
			}
		}

		$file_data = wp_handle_upload(
			$_FILES['file'],
			array(
				'action' => 'cr_import_upload_csv',
				'test_type' => true,
			)
		);

		if ( isset( $file_data['error'] ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'data'    => array(
						'message'  => $file_data['error'],
						'filename' => $_FILES['file']['name'],
					)
				)
			);
			wp_die();
		}

		$file_stats = $this->validate_csv_file( $file_data['file'] );

		if ( is_wp_error( $file_stats ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'data'    => array(
						'message'  => $file_stats->get_error_message(),
						'filename' => $_FILES['file']['name'],
					)
				)
			);
			wp_die();
		}

		$progress_id = 'import_' . uniqid();
		set_transient( $progress_id, $file_data['file'], DAY_IN_SECONDS );

		$this->import_meta_cleanup();

		echo wp_json_encode(
			array(
				'success' => true,
				'data'    => array(
					'num_rows'    => $file_stats['num_reviews'],
					'offset'      => $file_stats['offset'],
					'progress_id' => $progress_id
				)
			)
		);
		wp_die();
	}

	private function validate_csv_file($file_path) {
		if ( ! is_readable( $file_path ) ) {
			return new WP_Error(
				'failed_read_file',
				__('Cannot read CSV file', 'customer-reviews-woocommerce')
			);
		}

		$file = fopen($file_path, 'r');
		// detect delimiter
		$delimiter = self::detect_delimiter( $file );
		set_transient( 'cr_csv_delimiter', $delimiter, DAY_IN_SECONDS );
		$columns = fgetcsv( $file, 0, $delimiter );
		// check for Byte Order Mark present in UTF8 files
		$bom = pack("CCC", 0xef, 0xbb, 0xbf);
		$columns_correct = true;
		if ( ! is_array($columns) || count( self::$columns ) !== count( $columns ) ) {
			$columns_correct = false;
		} else {
			for ($i = 0; $i< count( self::$columns ); $i++) {
				//if there is BOM, remove it before comparison of column names
				if (0 == strncmp($columns[$i], $bom, 3)) {
					$columns[$i] = substr($columns[$i], 3);
				}
				if( self::$columns[$i] !== $columns[$i] ) {
					$columns_correct = false;
					break;
				}
			}
		}

		if ( ! $columns_correct ) {
			fclose($file);
			return new WP_Error(
				'malformed_columns',
				__('The CSV file contains invalid or missing column headings, please refer to the template in step 1', 'customer-reviews-woocommerce')
			);
		}

		$offset = ftell($file);

		$num_reviews = 0;
		while ( ( $row = fgetcsv( $file, 0, $delimiter ) ) !== false ) {
			$num_reviews++;
		}

		fclose($file);

		if ( $num_reviews < 1 ) {
			return new WP_Error(
				'no_reviews',
				__('The CSV file contains no reviews', 'customer-reviews-woocommerce')
			);
		}

		return array(
			'offset'      => $offset,
			'num_reviews' => $num_reviews
		);
	}

	private function import_meta_cleanup() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM $wpdb->commentmeta WHERE meta_key = 'cr_import_id'"
		);
	}

	public static function detect_delimiter( $file_pointer ) {
		$delimiters = array(
			';' => 0,
			',' => 0,
			"\t" => 0,
			"|" => 0
		);

		$first_line = fgets( $file_pointer );
		// move back to the beginning of the file
		fseek( $file_pointer, 0 );
		foreach ( $delimiters as $delimiter => &$count ) {
			$count = count( str_getcsv( $first_line, $delimiter ) );
		}

		return array_search( max( $delimiters ), $delimiters );
	}

	public function import_chunk() {
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
		if ( ! check_ajax_referer( 'cr_import_page', 'cr_nonce', false ) ) {
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
		//
		$file_name = get_transient( $_POST['progressID'] );
		$file = false;
		if ( $file_name ) {
			$file = fopen(
				$file_name,
				'r'
			);
		}
		if ( $file ) {
			// set the read position
			$offset = 0;
			if ( isset( $_POST['offset'] ) ) {
				$offset = intval( $_POST['offset'] );
			}
			if ( 0 > $offset ) {
				$offset = 0;
			}
			fseek( $file, $offset );
			// set the last line for tracking and error reporting purposes
			$last_line = 0;
			if ( isset( $_POST['lastLine'] ) ) {
				$last_line = intval( $_POST['lastLine'] );
			}
			if ( 0 > $last_line ) {
				$last_line = 0;
			}
			//
			$delimiter = get_transient( 'cr_csv_delimiter' );
			if ( ! $delimiter ) {
				$delimiter = ',';
			}
			$lines = array();
			$last_chunk = false;
			for( $i = 0; $i < self::$file_read_buffer; $i++ ) {
				$line = fgetcsv( $file, null, $delimiter );
				if ( false === $line ) {
					$last_chunk = true;
					break;
				}
				$lines[] = $line;
				$last_line++;
			}
			// create reviews and replies to reviews
			$process_res = $this->process_lines( $lines, $last_line );
			// get the current file position
			$offset = ftell( $file );
			//
			fclose( $file );
			//
			if ( $last_chunk ) {
				$this->remove_file( $file_name );
			}
			//
			wp_send_json(
				array(
					'success' => true,
					'offset'  => $offset, // for reading the next chunk
					'lastLine' => $last_line, // for logging errors and updating the progress bar
					'lastChunk' => $last_chunk, // for knowing when there is no more data in the file
					'progressID' => $_POST['progressID'], // for retrieving the file name in the next chunk
					'data'    => $process_res // statistics
				)
			);
		} else {
			wp_send_json(
				array(
					'success' => false,
					'data'    => array(
						'message'  => __( 'Error: could not open the import file', 'customer-reviews-woocommerce' )
					)
				)
			);
			wp_die();
		}
	}

	private function process_lines( $lines, $last_line ) {
		global $wpdb;
		$results = array(
			'rev' => array(
				'imported'   => 0,
				'skipped'    => 0
			),
			'rep' => array(
				'imported'   => 0,
				'skipped'    => 0
			),
			'errors' => 0,
			'error_list' => array()
		);
		$id_index = array_search( 'id', self::$columns );
		$title_index = array_search( 'review_title', self::$columns );
		$content_index = array_search( 'review_content', self::$columns );
		$score_index = array_search( 'review_score', self::$columns );
		$parent_index = array_search( 'parent', self::$columns );
		$date_index = array_search( 'date', self::$columns );
		$product_id_index = array_search( 'product_id', self::$columns );
		$product_sku_index = array_search( 'product_sku', self::$columns );
		$display_name_index = array_search( 'display_name', self::$columns );
		$email_index = array_search( 'email', self::$columns );
		$order_id_index = array_search( 'order_id', self::$columns );
		$media_index = array_search( 'media', self::$columns );
		$location_index = array_search( 'location', self::$columns );
		$num_lines = count( $lines );
		$shop_page_id = wc_get_page_id( 'shop' );
		$product_ids = array();
		// ensure mandatory fields are provided
		foreach ( $lines as $index => $line ) {
			$line_number = $last_line - ( $num_lines - $index ) + 1;
			//
			$filtered = array_filter( $line );
			if ( empty( $filtered ) ) {
				unset( $lines[$index] );
				$results['errors']++;
				$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: no data for this review.', 'customer-reviews-woocommerce' ), $line_number );
				continue;
			}
			//
			$count_cols = count( $line );
			if ( $count_cols < count( self::$columns ) ) {
				unset( $lines[$index] );
				$results['errors']++;
				$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: incorrect file format. Only %2$d column(s) found. Please open the input file in a text editor (e.g., in Notepad on Windows) and verify that columns are correctly separated by commas.', 'customer-reviews-woocommerce' ), $line_number, $count_cols );
				continue;
			}
			// check that either id or sku was provided
			$product_id = intval( $line[$product_id_index] );
			$product_sku = trim( strval( $line[$product_sku_index] ) );
			if ( ! $product_id && ! $product_sku ) {
				unset( $lines[$index] );
				$results['errors']++;
				$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: either product_id or product_sku must be provided.', 'customer-reviews-woocommerce' ), $line_number );
				continue;
			}
			// if no valid product_id is available but there is product_sku, try to look up the id by the sku
			if ( $product_id < 1 && -1 !== $product_id ) {
				if ( $product_sku ) {
					$product_id = wc_get_product_id_by_sku( $product_sku );
					if ( ! $product_id ) {
						unset( $lines[$index] );
						$results['errors']++;
						$results['error_list'][] = sprintf(
							__( 'Line %1$d >> Error: could not find a product with SKU = %2$s.', 'customer-reviews-woocommerce' ),
							$line_number,
							$product_sku
						);
						continue;
					} else {
						$lines[$index][$product_id_index] = $product_id;
					}
				} else {
					unset( $lines[$index] );
					$results['errors']++;
					$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: product_id must be a positive number or \'-1\' for shop reviews.', 'customer-reviews-woocommerce' ), $line_number );
					continue;
				}
			}
			// product reviews
			if ( -1 !== $product_id ) {
				$ppp = wc_get_product( $product_id );
				if ( $ppp ) {
					// check that the provided product id is not for a variation because reviews are stored at the parent product level
					if (
						'variation' === $ppp->get_type()
					) {
						unset( $lines[$index] );
						$results['errors']++;
						$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: product ID %2$d refers to a product variation. Use the parent product ID instead.', 'customer-reviews-woocommerce' ), $line_number, $product_id );
						continue;
					}
				}
				if ( ! $ppp ) {
					// if no valid product_id is available but there is product_sku, try to look up the id by the sku
					if ( $product_sku ) {
						$product_found = false;
						$product_id_by_sku = wc_get_product_id_by_sku( $product_sku );
						if ( $product_id_by_sku ) {
							$ppp = wc_get_product( $product_id_by_sku );
							if ( $ppp ) {
								if ( 'variation' === $ppp->get_type() ) {
									unset( $lines[$index] );
									$results['errors']++;
									$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: product SKU %2$d refers to a product variation. Use the parent product SKU instead.', 'customer-reviews-woocommerce' ), $line_number, $product_sku );
									continue;
								}
								$product_found = true;
								$product_id = $product_id_by_sku;
							}
						}
						if ( $product_found ) {
							$lines[$index][$product_id_index] = $product_id;
						} else {
							unset( $lines[$index] );
							$results['errors']++;
							$results['error_list'][] = sprintf(
								__( 'Line %1$d >> Error: product with ID = %2$d or SKU = %3$s doesn\'t exist in this WooCommerce store.', 'customer-reviews-woocommerce' ),
								$line_number,
								$product_id,
								$product_sku
							);
							continue;
						}
					} else {
						unset( $lines[$index] );
						$results['errors']++;
						$results['error_list'][] = sprintf(
							__( 'Line %1$d >> Error: product with ID = %2$d doesn\'t exist in this WooCommerce store.', 'customer-reviews-woocommerce' ),
							$line_number,
							$product_id
						);
						continue;
					}
				}
			} else {
				$product_id = $shop_page_id;
			}
			//
			$display_name = $line[$display_name_index];
			if ( empty( $display_name ) ) {
				unset( $lines[$index] );
				$results['errors']++;
				$results['error_list'][] = sprintf(
					__( 'Line %1$d >> Error: display name cannot be empty.', 'customer-reviews-woocommerce' ),
					$line_number
				);
				continue;
			}
			//
			$email = $line[$email_index];
			$email = trim( $email );
			if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				unset( $lines[$index] );
				$results['errors']++;
				//make sure that the email is in UTF-8 encoding
				if ( ! mb_check_encoding( $email, 'UTF-8' ) ) {
					$results['error_list'][] = sprintf(
						__( 'Line %1$d >> Error: email address includes invalid characters.', 'customer-reviews-woocommerce' ),
						$line_number
					);
				} else {
					$results['error_list'][] = sprintf(
						__( 'Line %1$d >> Error: %2$s is not a valid email address.', 'customer-reviews-woocommerce' ),
						$line_number,
						$email
					);
				}
				continue;
			}
			//
			$order_id = intval( $line[$order_id_index] );
			if ( $order_id < 0 ) {
				unset( $lines[$index] );
				$results['errors']++;
				$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: order_id must be a positive number or empty.', 'customer-reviews-woocommerce' ), $line_number );
				continue;
			}
			//
			if ( ! in_array( $product_id, $product_ids ) ) {
				$product_ids[] = $product_id;
			}
		}
		// get existing reviews and replies to reviews to check for duplicates
		$existing_reviews = array();
		if ( 0 < count( $product_ids ) ) {
			$existing_reviews = $wpdb->get_results(
				"SELECT com.*
				FROM {$wpdb->comments} AS com
				WHERE com.comment_type <> 'cr_qna' AND com.comment_approved IN('0','1') AND com.comment_post_ID IN(" . implode( ',', $product_ids ) . ")",
				ARRAY_A
			);
			if ( ! is_array( $existing_reviews ) ) {
				$existing_reviews = array();
			}
		}
		$existing_reviews = array_reduce(
			$existing_reviews,
			function( $carry, $item ) {
				if ( ! isset( $carry[$item['comment_post_ID']] ) ) {
					$carry[$item['comment_post_ID']] = array();
				}
				$carry[$item['comment_post_ID']][] = $item;
				return $carry;
			},
			[]
		);
		//
		$timezone_string = get_option( 'timezone_string' );
		$timezone_string = empty( $timezone_string ) ? 'gmt': $timezone_string;
		$site_timezone = new DateTimeZone( $timezone_string );
		$gmt_timezone  = new DateTimeZone( 'gmt' );
		// check for duplicates and create reviews
		foreach ( $lines as $index => $line ) {
			$line_number = $last_line - ( $num_lines - $index ) + 1;
			$product_id = intval( $line[$product_id_index] );
			if ( -1 === $product_id ) {
				$product_id = $shop_page_id;
			}
			//
			if ( ! empty( $line[$date_index] ) ) {
				$date_string = str_ireplace( 'UTC', 'GMT', $line[$date_index] );
				try {
					if ( strpos( $date_string, 'GMT' ) !== false ) {
						$date = new DateTime( $date_string );
					} else {
						$date = new DateTime( $date_string, $site_timezone );
					}
				} catch ( Exception $exception ) {
					$date = new DateTime( 'now', $site_timezone );
				}
			} else {
				$date = new DateTime( 'now', $site_timezone );
			}
			$line_date = $date->format( 'Y-m-d H:i:s' );
			$date->setTimezone( $gmt_timezone );
			$line_date_gmt = $date->format( 'Y-m-d H:i:s' );
			// duplicate check
			if ( isset( $existing_reviews[$product_id] ) ) {
				$duplicate_found = false;
				foreach ( $existing_reviews[$product_id] as $existing_one ) {
					if (
						$line[$content_index] == $existing_one['comment_content'] &&
						$line[$email_index] == $existing_one['comment_author_email'] &&
						$line[$content_index]
					) {
						if ( $line[$parent_index] ) {
							$results['rep']['skipped']++;
						} else {
							$results['rev']['skipped']++;
						}
						$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: Duplicate review or reply to review.', 'customer-reviews-woocommerce' ), $line_number );
						$duplicate_found = true;
						break;
					}
				}
				if ( $duplicate_found ) {
					continue;
				}
			}
			// check that the parent review exists for replies
			$line_parent = 0;
			if ( 0 < $line[$parent_index] ) {
				$line_parent = intval( $line[$parent_index] );
				$parent_count = get_comments(
					array(
						'comment__in' => array(
							$line_parent
						),
						'count' => true,
						'type__not_in' => array( 'cr_qna' ),
						'parent' => 0
					)
				);
				// no reviews found with the provided id, try searching by meta data
				if ( 0 >= $parent_count ) {
					$args = array(
						'type__not_in' => array( 'cr_qna' ),
						'meta_query' => array(
							array(
								'key' => 'cr_import_id',
								'value' => sanitize_text_field( $line[$parent_index] ),
								'compare' => '='
							)
						),
						'parent' => 0
					);
					$parent = get_comments( $args );
					if ( $parent && is_array( $parent ) && 0 < count( $parent ) ) {
						$line_parent = $parent[0]->comment_ID;
					} else {
						$results['errors']++;
						$results['error_list'][] = sprintf(
							__( 'Line %1$d >> Error: A matching review with ID %2$d could not be found.', 'customer-reviews-woocommerce' ),
							$line_number,
							sanitize_text_field( $line[$parent_index] )
						);
						continue;
					}
				}
			}
			// ensure that the display name is in UTF-8 encoding
			if ( ! mb_check_encoding( $line[$display_name_index], 'UTF-8' ) ) {
				// if it is not, try to convert the encoding to UTF-8
				if ( mb_check_encoding( $line[$display_name_index], 'Windows-1252' ) ) {
					$line[$display_name_index] = mb_convert_encoding( $line[$display_name_index], 'UTF-8', 'Windows-1252' );
				} elseif ( mb_check_encoding( $line[$display_name_index], 'Windows-1251' ) ) {
					$line[$display_name_index] = mb_convert_encoding( $line[$display_name_index], 'UTF-8', 'Windows-1251' );
				}
			}
			// ensure that the review content is in UTF-8 encoding
			if ( ! mb_check_encoding( $line[$content_index], 'UTF-8' ) ) {
				// if it is not, try to convert the encoding to UTF-8
				if ( mb_check_encoding( $line[$content_index], 'Windows-1252' ) ) {
					$line[$content_index] = mb_convert_encoding( $line[$content_index], 'UTF-8', 'Windows-1252' );
				} elseif ( mb_check_encoding( $line[$content_index], 'Windows-1251' ) ) {
					$line[$content_index] = mb_convert_encoding( $line[$content_index], 'UTF-8', 'Windows-1251' );
				}
			}
			// sanitize_textarea_field function is defined only in WordPress 4.7+
			$tmp_comment_content = '';
			if ( function_exists( 'sanitize_textarea_field' ) ) {
				$tmp_comment_content = sanitize_textarea_field( $line[$content_index] );
			} else {
				$tmp_comment_content = sanitize_text_field( $line[$content_index] );
			}
			// create meta
			$meta = array();
			if ( $line[$id_index] ) {
				$meta['cr_import_id'] = sanitize_text_field( $line[$id_index] );
			}
			if ( $line[$title_index] ) {
				$meta['cr_rev_title'] = sanitize_text_field( $line[$title_index] );
			}
			if ( $line[$score_index] ) {
				$meta['rating'] = intval( $line[$score_index] );
			}
			$order_id = intval( $line[$order_id_index] );
			if ( $order_id ) {
				$meta['ivole_order'] = $order_id;
			}
			$country_code = '';
			$countr_desc = '';
			$country = trim( $line[$location_index] );
			if ( $country ) {
				$parts = explode( '|', $country, 2 );
				if ( $parts && is_array( $parts ) ) {
					if ( isset( $parts[0] ) ) {
						$country_code = strtolower( sanitize_text_field( trim( $parts[0] ) ) );
						if ( isset( $parts[1] ) ) {
							$countr_desc = sanitize_text_field( trim( $parts[1] ) );
						}
					}
				}
			}
			if ( $country_code ) {
				$meta['ivole_country'] = array( 'code' => $country_code, 'desc' => $countr_desc );
			}
			// WPML compatibility
			if ( has_filter( 'wpml_object_id' ) && class_exists( 'WCML_Comments' ) ) {
				global $woocommerce_wpml;
				if ( $woocommerce_wpml ) {
					remove_action( 'added_comment_meta', array( $woocommerce_wpml->comments, 'maybe_duplicate_comment_rating' ), 10, 4 );
				}
			}
			// create reviews and replies to reviews
			$line_id = wp_insert_comment(
				array(
					'comment_author'       => sanitize_text_field( $line[$display_name_index] ),
					'comment_author_email' => $line[$email_index],
					'comment_content'      => $tmp_comment_content,
					'comment_post_ID'      => $product_id,
					'comment_date'         => $line_date,
					'comment_date_gmt'     => $line_date_gmt,
					'comment_type'         => ( 0 < $line_parent ) ? 'comment' : 'review',
					'comment_parent'       => $line_parent,
					'comment_meta'         => $meta
				)
			);
			// WPML compatibility
			if ( has_filter( 'wpml_object_id' ) && class_exists( 'WCML_Comments' ) ) {
				global $woocommerce_wpml;
				if ( $woocommerce_wpml ) {
					add_action( 'added_comment_meta', array( $woocommerce_wpml->comments, 'maybe_duplicate_comment_rating' ), 10, 4 );
				}
			}
			//
			if ( $line_id ) {
				$line_obj = get_comment( $line_id );
				$media = trim( $line[$media_index] );
				if ( $line_obj && $media ) {
					$media_array = explode( ',', $media );
					foreach ( $media_array as $media_file ) {
						$media_file = trim( $media_file );
						if ( filter_var( $media_file, FILTER_VALIDATE_URL ) ) {
							$media_file = sanitize_url( $media_file );
							// download the media
							$tmpFile = download_url( $media_file );
							$file_array = array(
								'name' => basename( $media_file ),
								'tmp_name' => $tmpFile
							);
							if ( is_wp_error( $tmpFile ) ) {
								$results['errors']++;
								$results['error_list'][] = sprintf( __( 'Line %1$d >> An error occurred while downloading a media file. Error code: %2$s. File name: %3$s', 'customer-reviews-woocommerce' ), $line_number, $tmpFile->get_error_code() . ' - ' . $tmpFile->get_error_message(), esc_url( $media_file ) );
							} else {
								$customerName = get_comment_author( $line_id );
								$prdct = wc_get_product( $line_obj->comment_post_ID );
								if ( $prdct ) {
									$reviewedItem = $prdct->get_name();
								} else {
									$reviewedItem = get_the_title( $line_obj->comment_post_ID );
								}
								$fileDesc = sprintf( __( 'Review of %s by %s', 'customer-reviews-woocommerce' ), $reviewedItem, $customerName );
								$customerUserId = $line_obj->user_id ? $line_obj->user_id : 0;
								$reviewId = sprintf( __( 'Review ID: %s', 'customer-reviews-woocommerce' ), $line_id );
								$mediaId = media_handle_sideload( $file_array, $line_obj->comment_post_ID, $fileDesc, array( 'post_author' => $customerUserId, 'post_date' => $line_obj->comment_date, 'post_content' => $reviewId ) );
								if ( is_wp_error( $mediaId ) ) {
									$results['errors']++;
									$results['error_list'][] = __( 'Line %1$d >> An error occurred while downloading a media file.', 'customer-reviews-woocommerce' );
								} else {
									$successful_media_attachment = false;
									if ( wp_attachment_is( 'image', $mediaId ) ) {
										add_comment_meta( $line_id, CR_Reviews::REVIEWS_META_LCL_IMG, $mediaId, false );
										$successful_media_attachment = true;
									} else if( wp_attachment_is( 'video', $mediaId ) ) {
										add_comment_meta( $line_id, CR_Reviews::REVIEWS_META_LCL_VID, $mediaId, false );
										$successful_media_attachment = true;
									} else {
										$results['errors']++;
										$results['error_list'][] = __( 'Line %1$d >> A media file could not be imported due to its type.', 'customer-reviews-woocommerce' );
									}
									if ( $successful_media_attachment ) {
										// create a meta value that can later be used for sorting reviews by media attachments
										$media_count = CR_Ajax_Reviews::get_media_count( $line_id );
										add_comment_meta( $line_id, 'ivole_media_count', $media_count );
									}
								}
							}
						}
					}
				}
				//
				wp_update_comment_count_now( $product_id );
				if ( $line[$parent_index] ) {
					$results['rep']['imported']++;
				} else {
					$results['rev']['imported']++;
				}
			} else {
				// errors are not split between questions and answers because they can be generic
				$results['errors']++;
			}
		}
		sort( $results['error_list'], SORT_STRING );
		return $results;
	}

}

endif;
