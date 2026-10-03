<?php

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

if ( ! class_exists( 'CR_Import_Qna' ) ):

class CR_Import_Qna {

	public static $columns = array(
		'qna_id',
		'qna_content',
		'qna_parent',
		'date',
		'product_id',
		'product_sku',
		'display_name',
		'email'
	);
	public static $file_read_buffer = 3;

	protected $page_url;
	protected $menu_slug;
	protected $admin_menu;
	protected $tab;
	protected $settings;

	public function __construct( $admin_menu ) {
		$this->menu_slug = 'cr-import-export';
		$this->admin_menu = $admin_menu;
		$this->tab = 'import-qna';
		$this->page_url = add_query_arg(
			array(
				'page' => $this->admin_menu->get_page_slug()
			),
			admin_url( 'admin.php' )
		);

		add_filter( 'cr_import_export_tabs', array( $this, 'register_tab' ) );
		add_action( 'cr_import_export_display_' . $this->tab, array( $this, 'display' ) );
		add_action( 'wp_ajax_cr_import_qna_upload_csv', array( $this, 'handle_qna_upload' ) );
		add_action( 'wp_ajax_cr_qna_import_chunk', array( $this, 'import_qna_chunk' ) );
	}

		public function register_tab( $tabs ) {
			$tabs[$this->tab] = __( 'Import Q & A', 'customer-reviews-woocommerce' );
			return $tabs;
		}

		public function display() {
			$download_template_url = add_query_arg(
				array(
					'action'   => 'cr-download-import-qna-template',
					'_wpnonce' => wp_create_nonce( 'download_csv_template' )
				),
				$this->page_url
			);
			$max_upload_size = size_format( wp_max_upload_size() );
			?>
				<div class="cr-import-container" data-nonce="<?php echo wp_create_nonce( 'cr_qna_import_page' ); ?>">
					<h2><?php echo _e( 'Import Questions & Answers from CSV', 'customer-reviews-woocommerce' ); ?></h2>
						<p>
							<?php
								_e( 'You can use this tool to import questions and answers in three steps:', 'customer-reviews-woocommerce' );
								echo '<ol><li>';
								echo '<strong>';
								_e( 'Download the template', 'customer-reviews-woocommerce' );
								echo '</strong><br>';
								_e( 'Get the CSV template for entering your questions and answers', 'customer-reviews-woocommerce' );
								echo '</li><li>';
								echo '<strong>';
								_e( 'Fill in the template', 'customer-reviews-woocommerce' );
								echo '</strong><br>';
								_e( 'Add your questions and answers to the template and save the file (if using MS Excel, choose CSV UTF-8 format)', 'customer-reviews-woocommerce' );
								echo '<ul class="cr-admin-import-steps-desc">';
								echo '<li>';
								_e( 'Make sure to enter valid product IDs that exist on your WooCommerce site', 'customer-reviews-woocommerce' );
								echo '</li>';
								echo '<li>';
								_e( 'Alternatively, you can use page or post IDs if you want to import questions and answers for non-product pages', 'customer-reviews-woocommerce' );
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
						<div id="cr-qna-import-upload-steps">
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
									<p id="cr-qna-import-status"></p>
									<div id="cr-qna-import-filelist" class="cr-import-upload-filelist">
										<?php _e( 'No file selected', 'customer-reviews-woocommerce' ); ?>
									</div>
									<div id="cr-qna-upload-container">
										<table border="0" cellpadding="0" cellspacing="0">
											<tbody>
												<tr class="cr-import-upload-tr">
													<td>
														<button type="button" id="cr-qna-select-button"><?php _e( 'Choose File', 'customer-reviews-woocommerce' ); ?></button><br/>
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
														<button type="button" class="button button-primary cr-import-upload-btn" id="cr-qna-upload-button" disabled><?php _e( 'Upload', 'customer-reviews-woocommerce' ); ?></button>
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							</div>
							<div id="cr-qna-import-progress" class="cr-import-progress">
									<h2 id="cr-qna-import-text"><?php _e( 'Import is in progress', 'customer-reviews-woocommerce' ); ?></h2>
									<progress id="cr-qna-progress-bar" max="100" value="0" data-numreviews="0"></progress>
									<div>
											<button id="cr-qna-import-cancel" class="button button-secondary" data-cancelled="0"><?php _e( 'Cancel', 'customer-reviews-woocommerce' ); ?></button>
									</div>
							</div>
							<div id="cr-qna-import-results" class="cr-import-results">
									<h3 id="cr-qna-import-result-status"><?php _e( 'Upload Completed', 'customer-reviews-woocommerce' ); ?></h3>
									<p id="cr-qna-import-result-started"></p>
									<p id="cr-qna-import-result-finished"></p>
									<p id="cr-qna-import-result-que-imported" data-qnacount="0"></p>
									<p id="cr-qna-import-result-ans-imported" data-qnacount="0"></p>
									<p id="cr-qna-import-result-que-skipped" data-qnacount="0"></p>
									<p id="cr-qna-import-result-ans-skipped" data-qnacount="0"></p>
									<p id="cr-qna-import-result-errors" data-qnacount="0"></p>
									<div id="cr-qna-import-result-details">
											<h4><?php _e( 'Error details:', 'customer-reviews-woocommerce' ); ?></h4>
									</div>
									<br>
									<a href="" class="button button-secondary"><?php _e( 'New Upload', 'customer-reviews-woocommerce' ); ?></a>
							</div>
					</div>
				</div>
			<?php
		}

		public function is_this_page() {
			return ( isset( $_GET['page'] ) && $_GET['page'] === $this->menu_slug );
		}

		public function remove_file( $filename ) {
			if ( file_exists( $filename ) ) {
				unlink( $filename );
				clearstatcache();
			}
		}

		public function handle_qna_upload() {
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
					'action' => 'cr_import_qna_upload_csv',
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

			$file_stats = $this->validate_qna_csv_file( $file_data['file'] );

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

			$progress_id = 'import_qna_' . uniqid();
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

		private function validate_qna_csv_file( $file_path ) {
			if ( ! is_readable( $file_path ) ) {
				return new WP_Error(
					'failed_read_file',
					__('Cannot read CSV file', 'customer-reviews-woocommerce')
				);
			}

			$file = fopen( $file_path, 'r' );
			// detect delimiter
			$delimiter = CR_Import_Reviews::detect_delimiter( $file );
			set_transient( 'cr_csv_delimiter', $delimiter, DAY_IN_SECONDS );
			$columns = fgetcsv( $file, 0, $delimiter );
			// check for Byte Order Mark present in UTF8 files
			$bom = pack("CCC", 0xef, 0xbb, 0xbf);
			$columns_correct = true;
			if ( ! is_array( $columns ) || count( self::$columns ) !== count( $columns ) ) {
				$columns_correct = false;
			} else {
				for ( $i = 0; $i < count( self::$columns ); $i++ ) {
					//if there is BOM, remove it before comparison of column names
					if ( 0 == strncmp($columns[$i], $bom, 3) ) {
						$columns[$i] = substr( $columns[$i], 3 );
					}
					if ( self::$columns[$i] !== $columns[$i] ) {
						$columns_correct = false;
						break;
					}
				}
			}

			if ( ! $columns_correct ) {
				fclose( $file );
				return new WP_Error(
					'malformed_columns',
					__(
						'Invalid or missing column headers detected in the CSV file. Refer to the Step 1 template for the correct format.',
						'customer-reviews-woocommerce'
					)
				);
			}

			$offset = ftell( $file );

			$num_reviews = 0;
			while ( ( $row = fgetcsv( $file, 0, $delimiter ) ) !== false ) {
				$num_reviews++;
			}

			fclose( $file );

			if ( $num_reviews < 1 ) {
				return new WP_Error(
					'no_reviews',
					__( 'The CSV file contains no reviews', 'customer-reviews-woocommerce' )
				);
			}

			return array(
				'offset'      => $offset,
				'num_reviews' => $num_reviews
			);
		}

		public function import_qna_chunk() {
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
			if ( ! check_ajax_referer( 'cr_qna_import_page', 'cr_nonce', false ) ) {
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
				// create Q & A
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

		private function process_lines( $qnas, $last_line ) {
			global $wpdb;
			$results = array(
				'que' => array(
					'imported'   => 0,
					'skipped'    => 0
				),
				'ans' => array(
					'imported'   => 0,
					'skipped'    => 0
				),
				'errors' => 0,
				'error_list' => array()
			);
			$qna_id_index = array_search( 'qna_id', self::$columns );
			$qna_content_index = array_search( 'qna_content', self::$columns );
			$qna_parent_index = array_search( 'qna_parent', self::$columns );
			$product_id_index = array_search( 'product_id', self::$columns );
			$product_sku_index = array_search( 'product_sku', self::$columns );
			$date_index = array_search( 'date', self::$columns );
			$display_name_index = array_search( 'display_name', self::$columns );
			$email_index = array_search( 'email', self::$columns );
			$num_qna = count( $qnas );
			$shop_page_id = wc_get_page_id( 'shop' );
			$product_ids = array();
			// ensure mandatory fields are provided
			foreach ( $qnas as $index => $qna ) {
				$line_number = $last_line - ( $num_qna - $index ) + 1;
				//
				$filtered = array_filter( $qna );
				if ( empty( $filtered ) ) {
					unset( $qnas[$index] );
					$results['errors']++;
					$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: no data for this Q & A.', 'customer-reviews-woocommerce' ), $line_number );
					continue;
				}
				//
				$count_cols = count( $qna );
				if( $count_cols < count( self::$columns ) ) {
					unset( $qnas[$index] );
					$results['errors']++;
					$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: incorrect file format. Only %2$d column(s) found. Please open the input file in a text editor (e.g., in Notepad on Windows) and verify that columns are correctly separated by commas.', 'customer-reviews-woocommerce' ), $line_number, $count_cols );
					continue;
				}
				// check that either id or sku was provided
				$product_id = intval( $qna[$product_id_index] );
				$product_sku = trim( strval( $qna[$product_sku_index] ) );
				if ( ! $product_id && ! $product_sku ) {
					unset( $qnas[$index] );
					$results['errors']++;
					$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: either product_id or product_sku must be provided.', 'customer-reviews-woocommerce' ), $line_number );
					continue;
				}
				// if no valid product_id is available but there is product_sku, try to look up the id by the sku
				if ( $product_id < 1 && -1 !== $product_id ) {
					if ( $product_sku ) {
						$product_id = wc_get_product_id_by_sku( $product_sku );
						if ( ! $product_id ) {
							unset( $qnas[$index] );
	 						$results['errors']++;
							$results['error_list'][] = sprintf(
								__( 'Line %1$d >> Error: could not find a product with SKU = %2$s.', 'customer-reviews-woocommerce' ),
								$line_number,
								$product_sku
							);
	 						continue;
						} else {
							$qnas[$index][$product_id_index] = $product_id;
						}
					} else {
						unset( $qnas[$index] );
						$results['errors']++;
						$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: product_id must be a positive number or \'-1\' for general Q & A about a shop.', 'customer-reviews-woocommerce' ), $line_number );
						continue;
					}
				}
				// product or post/page Q & A
				if ( -1 !== $product_id ) {
					$ppp = wc_get_product( $product_id );
					$post = get_post( $product_id );
					if ( ( ! $ppp && ! $post ) || ( $ppp && 0 < wp_get_post_parent_id( $product_id ) ) ) {
						// if no valid product_id is available but there is product_sku, try to look up the id by the sku
						if ( $product_sku ) {
							$product_found = false;
							$product_id = wc_get_product_id_by_sku( $product_sku );
							if ( $product_id ) {
								$ppp = wc_get_product( $product_id );
								if( $ppp && 0 === wp_get_post_parent_id( $product_id ) ) {
									$product_found = true;
								}
							}
							if ( $product_found ) {
								$qnas[$index][$product_id_index] = $product_id;
							} else {
								unset( $qnas[$index] );
								$results['errors']++;
								$results['error_list'][] = sprintf(
									__( 'Line %1$d >> Error: products, posts or pages with ID = %2$d and products with SKU = %3$s don\'t exist on this WordPress site.', 'customer-reviews-woocommerce' ),
									$line_number,
									$product_id,
									$product_sku
								);
								continue;
							}
						} else {
							unset( $qnas[$index] );
							$results['errors']++;
							$results['error_list'][] = sprintf(
								__( 'Line %1$d >> Error: products, posts or pages with ID = %2$d don\'t exist on this WordPress site.', 'customer-reviews-woocommerce' ),
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
				$display_name = $qna[$display_name_index];
				if ( empty( $display_name ) ) {
					unset( $qnas[$index] );
					$results['errors']++;
					$results['error_list'][] = sprintf(
						__( 'Line %1$d >> Error: display name cannot be empty.', 'customer-reviews-woocommerce' ),
						$line_number
					);
					continue;
				}
				//
				$email = $qna[$email_index];
				$email = trim( $email );
				if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
					unset( $qnas[$index] );
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
				if ( ! in_array( $product_id, $product_ids ) ) {
					$product_ids[] = $product_id;
				}
			}
			// get existing Q & A to check for duplicates
			$existing_qna = array();
			if ( 0 < count( $product_ids ) ) {
				$existing_qna = $wpdb->get_results(
					"SELECT com.*
					FROM {$wpdb->comments} AS com
					WHERE com.comment_type = 'cr_qna' AND com.comment_approved IN('0','1') AND com.comment_post_ID IN(" . implode( ',', $product_ids ) . ")",
					ARRAY_A
				);
				if ( ! is_array( $existing_qna ) ) {
					$existing_qna = array();
				}
			}
			$existing_qna = array_reduce(
				$existing_qna,
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
			// check for duplicates and create Q & A
			foreach ( $qnas as $index => $qna ) {
				$line_number = $last_line - ( $num_qna - $index ) + 1;
				$product_id = intval( $qna[$product_id_index] );
				if( -1 === $product_id ) {
					$product_id = $shop_page_id;
				}
				//
				if ( ! empty( $qna[$date_index] ) ) {
					$date_string = str_ireplace( 'UTC', 'GMT', $qna[$date_index] );
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
				$qna_date = $date->format( 'Y-m-d H:i:s' );
				$date->setTimezone( $gmt_timezone );
				$qna_date_gmt = $date->format( 'Y-m-d H:i:s' );
				// duplicate check
				if ( isset( $existing_qna[$product_id] ) ) {
					$duplicate_found = false;
					foreach ( $existing_qna[$product_id] as $existing_one ) {
						if (
							$qna[$qna_content_index] == $existing_one['comment_content'] &&
							$qna[$email_index] == $existing_one['comment_author_email']
						) {
							if ( $qna[$qna_parent_index] ) {
								$results['ans']['skipped']++;
							} else {
								$results['que']['skipped']++;
							}
							$results['error_list'][] = sprintf( __( 'Line %1$d >> Error: Duplicate question or answer.', 'customer-reviews-woocommerce' ), $line_number );
							$duplicate_found = true;
							break;
						}
					}
					if ( $duplicate_found ) {
						continue;
					}
				}
				// check that the parent question exists for answers
				$qna_parent = 0;
				if ( 0 < $qna[$qna_parent_index] ) {
					$qna_parent = intval( $qna[$qna_parent_index] );
					$parent_count = get_comments(
						array(
							'comment__in' => array(
								$qna_parent
							),
							'count' => true,
							'type' => 'cr_qna',
							'parent' => 0
						)
					);
					// no questions found with the provided id, try searching by meta data
					if ( 0 >= $parent_count ) {
						$args = array(
							'type' => 'cr_qna',
							'meta_query' => array(
								array(
									'key' => 'cr_import_id',
									'value' => sanitize_text_field( $qna[$qna_parent_index] ),
									'compare' => '='
								)
							),
							'parent' => 0
						);
						$parent = get_comments( $args );
						if ( $parent && is_array( $parent ) && 0 < count( $parent ) ) {
							$qna_parent = $parent[0]->comment_ID;
						} else {
							$results['errors']++;
							$results['error_list'][] = sprintf(
								__( 'Line %1$d >> Error: A matching question with ID %2$d could not be found.', 'customer-reviews-woocommerce' ),
								$line_number,
								sanitize_text_field( $qna[$qna_parent_index] )
							);
							continue;
						}
					}
				}
				// ensure that the display name is in UTF-8 encoding
				if ( ! mb_check_encoding( $qna[$display_name_index], 'UTF-8' ) ) {
					// if it is not, try to convert the encoding to UTF-8
					if ( mb_check_encoding( $qna[$display_name_index], 'Windows-1252' ) ) {
						$qna[$display_name_index] = mb_convert_encoding( $qna[$display_name_index], 'UTF-8', 'Windows-1252' );
					} elseif ( mb_check_encoding( $qna[$display_name_index], 'Windows-1251' ) ) {
						$qna[$display_name_index] = mb_convert_encoding( $qna[$display_name_index], 'UTF-8', 'Windows-1251' );
					}
				}
				// ensure that the review content is in UTF-8 encoding
				if ( ! mb_check_encoding( $qna[$qna_content_index], 'UTF-8' ) ) {
					// if it is not, try to convert the encoding to UTF-8
					if ( mb_check_encoding( $qna[$qna_content_index], 'Windows-1252' ) ) {
						$qna[$qna_content_index] = mb_convert_encoding( $qna[$qna_content_index], 'UTF-8', 'Windows-1252' );
					} elseif ( mb_check_encoding( $qna[$qna_content_index], 'Windows-1251' ) ) {
						$qna[$qna_content_index] = mb_convert_encoding( $qna[$qna_content_index], 'UTF-8', 'Windows-1251' );
					}
				}
				// sanitize_textarea_field function is defined only in WordPress 4.7+
				$tmp_comment_content = '';
				if ( function_exists( 'sanitize_textarea_field' ) ) {
					$tmp_comment_content = sanitize_textarea_field( $qna[$qna_content_index] );
				} else {
					$tmp_comment_content = sanitize_text_field( $qna[$qna_content_index] );
				}
				// create meta
				$meta = array();
				if ( $qna[$qna_id_index] ) {
					$meta = array(
						'cr_import_id' => sanitize_text_field( $qna[$qna_id_index] )
					);
				}
				// create Q&A
				$qna_id = wp_insert_comment(
					array(
						'comment_author'       => sanitize_text_field( $qna[$display_name_index] ),
						'comment_author_email' => $qna[$email_index],
						'comment_content'      => $tmp_comment_content,
						'comment_post_ID'      => $product_id,
						'comment_date'         => $qna_date,
						'comment_date_gmt'     => $qna_date_gmt,
						'comment_type'         => 'cr_qna',
						'comment_parent'       => $qna_parent,
						'comment_meta'         => $meta
					)
				);
				if ( $qna_id ) {
					wp_update_comment_count_now( $product_id );
					if ( $qna[$qna_parent_index] ) {
						$results['ans']['imported']++;
					} else {
						$results['que']['imported']++;
					}
				} else {
					// errors are not split between questions and answers because they can be generic
					$results['errors']++;
				}
			}
			sort( $results['error_list'], SORT_STRING );
			return $results;
		}

		private function import_meta_cleanup() {
			global $wpdb;
			$wpdb->query(
				"DELETE FROM $wpdb->commentmeta WHERE meta_key = 'cr_import_id'"
			);
		}

}

endif;
