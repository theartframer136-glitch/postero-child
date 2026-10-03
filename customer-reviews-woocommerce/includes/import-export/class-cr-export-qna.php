<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Export_Qna' ) ):

class CR_Export_Qna {

	const FILE_PATH = 'export_qna.csv';
	const TEMP_FILE_PATH = 'export_qna_temp.csv';
	protected $page_url;
	protected $menu_slug;
	protected $admin_menu;
	protected $tab;
	protected $settings;
	public static $file_write_buffer = 10;

	public function __construct( $admin_menu ) {
		$this->menu_slug = 'cr-import-export';
		$this->admin_menu = $admin_menu;
		$this->tab = 'export-qna';
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
		add_action( 'wp_ajax_cr_qna_export_chunk', array( $this, 'export_qna_chunk' ) );
	}

	public function register_tab( $tabs ) {
		$tabs[$this->tab] = __( 'Export Q & A', 'customer-reviews-woocommerce' );
		return $tabs;
	}

	public function display() {
		$this->init_settings();
		WC_Admin_Settings::output_fields( $this->settings );

		$download_url = add_query_arg(
			array(
				'action'   => 'cr-download-export-qna',
				'_wpnonce' => wp_create_nonce( 'download_csv_export_qna' )
			),
			$this->page_url
		);

		?>
		<div id="cr-export-qna">
			<button type="button" class="button button-primary" id="cr-export-qna-button" data-nonce="<?php echo wp_create_nonce( 'cr-export-qna' ); ?>">
				<?php _e( 'Export', 'customer-reviews-woocommerce' ); ?>
			</button>
			<?php
			if( file_exists( get_temp_dir() . self::FILE_PATH ) ):
			?>
			<a href="<?php echo esc_url( $download_url ); ?>" class="cr-export-qna-download button button-primary" target="_blank"><?php _e( 'Download', 'customer-reviews-woocommerce' ); ?></a>
			<?php
			endif;
			?>
		</div>
		<div id="cr-export-qna-progress" class="cr-export-progress">
			<h2 id="cr-export-qna-text"><?php _e( 'Export is in progress', 'customer-reviews-woocommerce' ); ?></h2>
			<progress id="cr-export-qna-progress-bar" max="100" value="0" data-nonce="<?php echo wp_create_nonce( 'cr-export-progress' ); ?>"></progress>
			<div>
				<button id="cr-export-qna-cancel" class="button button-secondary" data-cancelled="0">
					<?php _e( 'Cancel', 'customer-reviews-woocommerce' ); ?>
				</button>
			</div>
		</div>
		<div id="cr-export-qna-results" class="cr-export-results">
			<h3 id="cr-export-qna-result-status"><?php _e( 'Export Completed', 'customer-reviews-woocommerce' ); ?></h3>
			<p id="cr-export-qna-result-started"></p>
			<p id="cr-export-qna-result-finished"></p>
			<p id="cr-export-qna-result-exported" data-qnacount="0"></p>
			<br>
			<a id="cr-export-qna-download" href="<?php echo esc_url( $download_url ); ?>" class="button button-primary" style="display: none"><?php _e( 'Download', 'customer-reviews-woocommerce' ); ?></a>
		</div>

		<?php
	}

	public function handle_download() {
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'cr-download-export-qna' ) {
			// ensure a valid nonce has been provided
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'download_csv_export_qna' ) ) {
				wp_die(
					sprintf(
						__( 'Failed to download: invalid nonce. <a href="%s">Return to settings</a>', 'customer-reviews-woocommerce' ),
						$this->page_url
					)
				);
			}

			$filename = get_temp_dir() . self::FILE_PATH;

			ignore_user_abort( true );

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="export-qna.csv"' );
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
			$desc = __( 'A utility to export questions and answers to a CSV file. Use the Export button to start export of Questions and Answers. Use the Download button to download the last export.', 'customer-reviews-woocommerce' );
		} else {
			$desc = __( 'A utility to export questions and answers to a CSV file.', 'customer-reviews-woocommerce' );
		}
		$this->settings = array(
			array(
				'title' => __( 'Export Questions and Answers to CSV', 'customer-reviews-woocommerce' ),
				'type'  => 'title',
				'desc'  => $desc,
				'id'    => 'cr_qna_export'
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cr_qna_export'
			)
		);
	}

	public function include_scripts() {
		if( $this->is_this_page() ) {
			wp_register_script( 'cr-export-reviews', plugins_url('js/admin-export.js', dirname( dirname( __FILE__ ) ) ), ['jquery'], Ivole::CR_VERSION );

			wp_localize_script( 'cr-export-reviews', 'CrExportStrings', array(
				'exporting_init' => __( 'Export is in progress', 'customer-reviews-woocommerce' ),
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
			));

			wp_enqueue_script( 'cr-export-reviews' );
		}
	}

	public function is_this_page() {
		return ( isset( $_GET['page'] ) && $_GET['page'] === $this->menu_slug );
	}

	public function remove_file( $filename ) {
		if( file_exists( $filename ) ) {
			unlink( $filename );
			clearstatcache();
		}
	}

	public function export_qna_chunk() {
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
		if ( ! check_ajax_referer( 'cr-export-qna', 'nonce', false ) ) {
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
		// get the total count of Q&A to export unless it is provided as an input parameter
		$total = intval( $_POST['total'] );
		if ( 0 >= $total ) {
			$query_count = "SELECT COUNT(*) FROM $wpdb->comments c " .
				"INNER JOIN $wpdb->posts p ON p.ID = c.comment_post_ID " .
				"WHERE c.comment_approved = '1' AND c.comment_type = 'cr_qna'";
			$total = $wpdb->get_var($query_count);
		}
		//
		$offset = intval( $_POST['offset'] );
		// read next chunk of Q&A from the database
		$query_chunk = "SELECT * FROM $wpdb->comments c " .
			"INNER JOIN $wpdb->posts p ON p.ID = c.comment_post_ID " .
			"WHERE c.comment_approved = '1' AND c.comment_type = 'cr_qna'" .
			"LIMIT " . $offset . "," . self::$file_write_buffer;
		$result_chunk = $wpdb->get_results( $query_chunk );
		if ( ! $result_chunk || ! is_array( $result_chunk ) ) {
			wp_send_json(
				array(
					'success'  => false,
					'data'     => array(
						'message'  => __( 'Export failed: Could not read Q & A from the database.', 'customer-reviews-woocommerce' )
					)
				)
			);
			wp_die();
		}
		// open the temporary file
		$file_open_mode = $offset ? 'a' : 'w';
		$file = fopen( $temp_file_path, $file_open_mode );
		if ( $file ) {
			if ( 0 === $offset ) {
				fputcsv( $file, CR_Import_Qna::$columns );
			}
			$this->process_chunk( $file, $result_chunk );
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
		//
		$offset += count( $result_chunk );
		$last_chunk = false;
		if ( self::$file_write_buffer > count( $result_chunk ) ) {
			$last_chunk = true;
			rename( $temp_file_path, $file_path );
		}
		//
		wp_send_json(
			array(
				'success' => true,
				'offset'  => $offset, // for reading the next chunk
				'total' => $total, // for avoiding calculating totals every time
				'lastChunk' => $last_chunk // for knowing when there is no more data in the database
			)
		);
	}

	private function process_chunk( $file, $data ) {
		$shop_page_id = wc_get_page_id( 'shop' );
		// extract relevant fields from each Q&A record for writing them into the file
		foreach ( $data as $qna ) {
			$product = wc_get_product( $qna->comment_post_ID );
			$row = array();
			$row[] = $qna->comment_ID;
			$row[] = $qna->comment_content;
			$row[] = ( $qna->comment_parent ? $qna->comment_parent : '' );
			$row[] = $qna->comment_date;
			$row[] = $qna->comment_post_ID === $shop_page_id ? -1 : $qna->comment_post_ID;
			$row[] = ( $product ? $product->get_sku() : '' );
			$row[] = $qna->comment_author;
			$row[] = $qna->comment_author_email;
			fputcsv( $file, $row );
		}
	}

}

endif;
