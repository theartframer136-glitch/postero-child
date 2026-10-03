<?php

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

if ( ! class_exists( 'CR_Import_Admin_Menu' ) ):

class CR_Import_Admin_Menu {

	protected $page_url;
	protected $menu_slug;
	protected $current_tab = 'import';
	protected $tab;

	public function __construct() {
		$this->menu_slug = 'cr-import-export';
		$this->page_url = add_query_arg(
			array(
				'page' => $this->menu_slug
			),
			admin_url( 'admin.php' )
		);
		if ( isset( $_GET['tab'] ) ) {
			$this->current_tab = $_GET['tab'];
		}
		$this->tab = 'import';

		add_action( 'admin_menu', array( $this, 'register_import_menu' ), 11 );
		add_action( 'admin_init', array( $this, 'handle_template_download' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'include_scripts' ) );
	}

	public function register_import_menu() {
		add_submenu_page(
			'cr-reviews',
			__( 'Import / Export', 'customer-reviews-woocommerce' ),
			__( 'Import / Export', 'customer-reviews-woocommerce' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'display_import_admin_page' )
		);
	}

	public function display_import_admin_page() {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<hr class="wp-header-end">
		<?php

		$tabs = apply_filters( 'cr_import_export_tabs', array() );

		if ( is_array( $tabs ) && sizeof( $tabs ) > 1 ) {
			echo '<ul class="subsubsub">';

			$array_keys = array_keys( $tabs );
			$last = end( $array_keys );

			foreach ( $tabs as $tab => $label ) {
				echo '<li><a href="' . $this->page_url . '&tab=' . $tab . '" class="' . ( $this->current_tab === $tab ? 'current' : '' ) . '">' . $label . '</a> ' . ( $last === $tab ? '' : '|' ) . ' </li>';
			}

			echo '</ul><br class="clear" />';
		}

		WC_Admin_Settings::show_messages();

		do_action( 'cr_import_export_display_' . $this->current_tab );

		echo "<div>";

		return;
	}

	public function handle_template_download() {
		if (
			isset( $_GET['action'] ) &&
			in_array( $_GET['action'], array( 'cr-download-import-template', 'cr-download-import-qna-template' ) )
		) {
			// Ensure a valid nonce has been provided
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'download_csv_template' ) ) {
				wp_die( sprintf( __( 'Failed to download template: invalid nonce. <a href="%s">Return to settings</a>', 'customer-reviews-woocommerce' ), $this->page_url ) );
			}

			if ( 'cr-download-import-qna-template' === $_GET['action'] ) {
				$template_data = array(
					array(
						'qna_id',
						'qna_content',
						'qna_parent',
						'date',
						'product_id',
						'product_sku',
						'display_name',
						'email'
					),
					array(
						'1',
						__( 'Does this t-shirt shrink after washing?', 'customer-reviews-woocommerce' ),
						'',
						'2025-04-01 15:30:05',
						'22',
						'',
						__( 'Example Customer', 'customer-reviews-woocommerce' ),
						'example.customer@mail.com'
					),
					array(
						'2',
						__( 'The t-shirt is made from pre-shrunk cotton, so it holds its size well after washing.', 'customer-reviews-woocommerce' ),
						'1',
						'2025-04-02 10:22:07',
						'22',
						'',
						__( 'Sample Store Manager', 'customer-reviews-woocommerce' ),
						'sample.store.manager@mail.com'
					),
					array(
						'3',
						__( 'To keep the best fit, we recommend washing in cold water and air drying, as this helps minimize any natural fabric shrinkage over time.', 'customer-reviews-woocommerce' ),
						'1',
						'2025-05-18 17:24:43',
						'',
						'sku-24',
						__( 'Another Store Manager', 'customer-reviews-woocommerce' ),
						'another.store.manager@mail.com'
					)
				);
				$file_name = 'qna-import-template.csv';
			} else {
				$template_data = array(
					array(
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
					),
					array(
						'1',
						'',
						__( 'This product is great!', 'customer-reviews-woocommerce' ),
						'5',
						'',
						'2018-07-01 15:30:05',
						12,
						'sku-123',
						__( 'Example Customer', 'customer-reviews-woocommerce' ),
						'example.customer@mail.com',
						'',
						'https://www.example.com/image-1.jpeg,https://www.example.com/image-2.jpeg,https://www.example.com/video-1.mp4',
						''
					),
					array(
						'2',
						'An optional review title...',
						__( 'This product is not so great.', 'customer-reviews-woocommerce' ),
						'1',
						'',
						'2017-04-15 09:54:32',
						22,
						'sku-456',
						__( 'Sample Customer', 'customer-reviews-woocommerce' ),
						'sample.customer@mail.com',
						'',
						'',
						'GB | London'
					),
					array(
						'3',
						'An optional title of a shop review',
						__( 'This is a shop review. Note that the product_id is -1 and product_sku is blank. Customer service is good!', 'customer-reviews-woocommerce' ),
						'4',
						'',
						'2017-04-18 10:24:43',
						-1,
						'',
						__( 'Sample Customer', 'customer-reviews-woocommerce' ),
						'sample.customer@mail.com',
						'',
						'',
						'JP | Tokyo'
					),
					array(
						'4',
						'',
						__( 'Another shop review. Note that the product_id is still -1 and product_sku is blank. Customer service is good!', 'customer-reviews-woocommerce' ),
						'4',
						'',
						'2018-04-18 10:24:43',
						-1,
						'',
						__( 'Sample Customer', 'customer-reviews-woocommerce' ),
						'sample.customer@mail.com',
						'',
						'',
						'US | New York, NY'
					),
					array(
						'5',
						'',
						__( 'This is a reply to the review with the id = 2. Sorry it did not meet your expectations — thanks for the feedback!', 'customer-reviews-woocommerce' ),
						'',
						'2',
						'2017-04-20 14:12:03',
						22,
						'',
						__( 'Store Manager', 'customer-reviews-woocommerce' ),
						'sample.store.manager@mail.com',
						'',
						'',
						''
					)
				);
				$file_name = 'review-import-template.csv';
			}

			$stdout = fopen( 'php://output', 'w' );
			$length = 0;

			foreach ( $template_data as $row ) {
				$length += fputcsv( $stdout, $row );
			}

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Connection: Keep-Alive' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . $length );
			fclose( $stdout );
			exit;
		}
	}

	public function include_scripts() {
		if ( $this->is_this_page() ) {
			wp_register_script( 'cr-admin-import', plugins_url( 'js/admin-import.js', dirname( dirname( __FILE__ ) ) ), [ 'wp-plupload', 'media', 'jquery' ], Ivole::CR_VERSION );
			wp_localize_script( 'cr-admin-import', 'ivoleImporterStrings', array(
				'uploading'           => __( 'Upload progress: %s%', 'customer-reviews-woocommerce' ),
				'importing'           => __( 'Import is in progress (%s/%s completed)', 'customer-reviews-woocommerce' ),
				'filelist_empty'      => __( 'No file selected', 'customer-reviews-woocommerce' ),
				'cancelling'          => __( 'Cancelling', 'customer-reviews-woocommerce' ),
				'cancel'              => __( 'Cancel', 'customer-reviews-woocommerce' ),
				'upload_cancelled'    => __( 'Upload Cancelled', 'customer-reviews-woocommerce' ),
				'upload_failed'       => __( 'Upload Failed', 'customer-reviews-woocommerce' ),
				'result_started'      => __( 'Started: %s', 'customer-reviews-woocommerce' ),
				'result_finished'     => __( 'Finished: %s', 'customer-reviews-woocommerce' ),
				'result_cancelled'    => __( 'Cancelled: %s', 'customer-reviews-woocommerce' ),
				'result_imported'     => __( '%d review(s) successfully uploaded', 'customer-reviews-woocommerce' ),
				'result_rep_imported' => __( '%d reply(s) to review(s) successfully uploaded', 'customer-reviews-woocommerce' ),
				'result_skipped'      => __( '%d duplicate review(s) skipped', 'customer-reviews-woocommerce' ),
				'result_rep_skipped'  => __( '%d duplicate reply(s) to review(s) skipped', 'customer-reviews-woocommerce' ),
				'result_errors'       => __( '%d error(s)', 'customer-reviews-woocommerce' ),
				'result_q_imported'   => __( '%d question(s) successfully uploaded', 'customer-reviews-woocommerce' ),
				'result_a_imported'   => __( '%d answer(s) successfully uploaded', 'customer-reviews-woocommerce' ),
				'result_q_skipped'    => __( '%d duplicate question(s) skipped', 'customer-reviews-woocommerce' ),
				'result_a_skipped'    => __( '%d duplicate answer(s) skipped', 'customer-reviews-woocommerce' )
			) );
			wp_enqueue_media();
			wp_enqueue_script( 'cr-admin-import' );
			wp_enqueue_style( 'cr-import-export-css', plugins_url( 'css/import-export.css', dirname( dirname( __FILE__) ) ), array(), Ivole::CR_VERSION );
		}
	}

	public function is_this_page() {
		return ( isset( $_GET['page'] ) && $_GET['page'] === $this->menu_slug );
	}

	public function get_page_slug() {
		return $this->menu_slug;
	}
}

endif;
