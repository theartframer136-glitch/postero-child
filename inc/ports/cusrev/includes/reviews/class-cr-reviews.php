<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/reviews/class-cr-reviews.php. Kept: what the class
 * does with the plugin's default settings on the standard WooCommerce review
 * template (review meta line, title/featured badge, country flag, custom
 * questions, review photos/videos), its CSS/JS and lightbox on product pages,
 * the ivrating/crsearch query vars, and removing a deleted review's media.
 * Left out: everything that only runs when a setting is switched on (voting,
 * media upload, captcha, histogram, AJAX review list, Q&A, floating badge,
 * incentivised badge, terms checkbox) or only for the plugin's own templates.
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( ABSPATH . 'wp-admin/includes/media.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/image.php' );

if ( ! class_exists( 'CR_Reviews' ) ) :

	class CR_Reviews {

		private $limit_file_size = 5000000;
		private $limit_file_count = 3;
		public static $rating_get_filter = 'ivrating';
		private $disable_lightbox = false;
		public static $reviews_tab;
		public static $custom_ratings_enabled = true;

		const REVIEWS_META_IMG = 'ivole_review_image';
		const REVIEWS_META_LCL_IMG = 'ivole_review_image2';
		const REVIEWS_META_VID = 'ivole_review_video';
		const REVIEWS_META_LCL_VID = 'ivole_review_video2';

		public function __construct() {
			$this->limit_file_count = get_option( 'ivole_attach_image_quantity', 5 );
			$this->limit_file_size = 1024 * 1024 * get_option( 'ivole_attach_image_size', 25 );
			$this->disable_lightbox = 'yes' === get_option( 'ivole_disable_lightbox', 'no' ) ? true : false;
			self::$reviews_tab = apply_filters( 'cr_productpage_reviews_tab', '#tab-reviews' );

			add_action( 'wp_enqueue_scripts', array( $this, 'cr_style_1' ) );
			// standard WooCommerce review template
			add_action( 'woocommerce_review_after_comment_text', array( $this, 'display_review_image' ), 10 );
			add_action( 'init', array( $this, 'add_query_var' ), 20 );
			add_action( 'woocommerce_review_before_comment_text', array( $this, 'display_verified_badge' ), 10 );
			add_action( 'woocommerce_review_before_comment_text', array( $this, 'display_custom_questions' ), 11 );
			add_action( 'woocommerce_review_meta', array( $this, 'cusrev_review_meta' ), 9, 1 );
			add_action( 'wp_footer', array( $this, 'cr_photoswipe' ) );
			add_action( 'woocommerce_review_before_comment_text', array( $this, 'display_featured' ), 9 );
			add_action( 'delete_comment', array( $this, 'delete_review_media_attachments' ), 10, 2 );
		}
		public function display_review_image( $comment ) {
			$output = '';
			$pics = get_comment_meta( $comment->comment_ID, self::REVIEWS_META_IMG );
			$pics_local = get_comment_meta( $comment->comment_ID, self::REVIEWS_META_LCL_IMG );
			$pics_v = get_comment_meta( $comment->comment_ID, self::REVIEWS_META_VID );
			$pics_v_local = get_comment_meta( $comment->comment_ID, self::REVIEWS_META_LCL_VID );
			$pics_n = ( is_array( $pics ) ? count( $pics ) : 0 );
			$pics_local_n = ( is_array( $pics_local ) ? count( $pics_local ) : 0 );
			$pics_v_n = ( is_array( $pics_v ) ? count( $pics_v ) : 0 );
			$pics_v_local_n = ( is_array( $pics_v_local ) ? count( $pics_v_local ) : 0 );
			$cr_query = '?crsrc=wp';
			if( 0 < $pics_n || 0 < $pics_local_n || 0 < $pics_v_n || 0 < $pics_v_local_n ) {
				$output .= '<div class="cr-comment-images cr-comment-videos">';
				$k = 1;
				if( 0 < $pics_n ) {
					for( $i = 0; $i < $pics_n; $i++ ) {
						if ( isset( $pics[$i]['url'] ) ) {
							$output .= '<div class="iv-comment-image cr-comment-image-ext" data-reviewid="' . $comment->comment_ID . '">';
							$output .= '<a href="' . esc_url( $pics[$i]['url'] . $cr_query ) . '" class="cr-comment-a" rel="nofollow"><img src="' .
							esc_url( $pics[$i]['url'] . $cr_query ) . '" alt="' .
							esc_attr(
								sprintf( __( 'Image #%1$d from %2$s', 'customer-reviews-woocommerce' ), $k, $comment->comment_author )
							) . '" loading="lazy"></a>';
							$output .= '</div>';
							$k++;
						}
					}
				}
				if( 0 < $pics_local_n ) {
					$temp_comment_content_flag = false;
					$temp_comment_content = '';
					for( $i = 0; $i < $pics_local_n; $i++ ) {
						$attachmentSrc = wp_get_attachment_image_src( $pics_local[$i], apply_filters( 'cr_reviews_image_size', 'large' ) );
						if( $attachmentSrc ) {
							$temp_comment_content_flag = true;
							$temp_comment_content .= '<div class="iv-comment-image">';
							$temp_comment_content .= '<a href="' . esc_url( $attachmentSrc[0] ) . '" class="cr-comment-a"><img src="' .
							esc_url( $attachmentSrc[0] ) . '" width="' . $attachmentSrc[1] . '" height="' . $attachmentSrc[2] .
							'" alt="' .
							esc_attr(
								sprintf( __( 'Image #%1$d from %2$s', 'customer-reviews-woocommerce' ), $k, $comment->comment_author )
							) . '" loading="lazy"></a>';
							$temp_comment_content .= '</div>';
							$k++;
						}
					}
					if( $temp_comment_content_flag ) {
						$output .= $temp_comment_content;
					}
				}
				$k = 1;
				if( 0 < $pics_v_n ) {
					for( $i = 0; $i < $pics_v_n; $i ++) {
						$output .= '<div class="cr-comment-video cr-comment-video-ext cr-comment-video-' . $k . '" data-reviewid="' . $comment->comment_ID . '">';
						$output .= '<div class="cr-video-cont">';
						$output .= '<video preload="metadata" class="cr-video-a" ';
						$output .= 'src="' . esc_url( $pics_v[$i]['url'] . $cr_query . '#t=0.1' );
						$output .= '"></video>';
						$output .= '<img class="cr-comment-videoicon" src="' . AF_CUSREV_URL . 'img/video.svg" '; // port: URL
						$output .= 'alt="' .
						esc_attr(
							sprintf( __( 'Video #%1$d from %2$s', 'customer-reviews-woocommerce' ), $k, $comment->comment_author )
						) . '">';
						$output .= '<button class="cr-comment-video-close" aria-label="' . esc_attr__( 'Close', 'customer-reviews-woocommerce' ) . '">' . self::get_close_button_svg() . '</button>';
						$output .= '</div></div>';
						$k++;
					}
				}
				if( 0 < $pics_v_local_n ) {
					$temp_comment_content_flag = false;
					$temp_comment_content = '';
					for( $i = 0; $i < $pics_v_local_n; $i++ ) {
						$attachmentUrl = wp_get_attachment_url( $pics_v_local[$i] );
						if( $attachmentUrl ) {
							$temp_comment_content_flag = true;
							$temp_comment_content .= '<div class="cr-comment-video cr-comment-video-' . $k . '">';
							$temp_comment_content .= '<div class="cr-video-cont">';
							$temp_comment_content .= '<video preload="metadata" class="cr-video-a" ';
							$temp_comment_content .= 'src="' . esc_url( $attachmentUrl . '#t=0.1' );
							$temp_comment_content .= '"></video>';
							$temp_comment_content .= '<img class="cr-comment-videoicon" src="' . AF_CUSREV_URL . 'img/video.svg" '; // port: URL
							$temp_comment_content .= 'alt="' .
							esc_attr(
								sprintf( __( 'Video #%1$d from %2$s', 'customer-reviews-woocommerce' ), $k, $comment->comment_author )
							) . '">';
							$temp_comment_content .= '<button class="cr-comment-video-close" aria-label="' . esc_attr__( 'Close', 'customer-reviews-woocommerce' ) . '">' . self::get_close_button_svg() . '</button>';
							$temp_comment_content .= '</div></div>';
							$k++;
						}
					}
					if( $temp_comment_content_flag ) {
						$output .= $temp_comment_content;
					}
				}
				$output .= '<div style="clear:both;"></div></div>';
			}
			echo $output;
		}

		public function cr_style_1() {
			if ( is_product() ) {
				$assets_version = AF_CUSREV_VERSION; // port: was Ivole::CR_VERSION ('5.123.0')
				if( ! $this->disable_lightbox ) {
					wp_enqueue_script( 'wc-photoswipe-ui-default' );
					wp_enqueue_style( 'photoswipe-default-skin' );
				}
				wp_register_style( 'cr-frontend-css', AF_CUSREV_URL . 'css/frontend.css' /* port: URL */, array(), $assets_version, 'all' );
				wp_register_script( 'cr-frontend-js', AF_CUSREV_URL . 'js/frontend.js' /* port: URL */, array( 'jquery' ), $assets_version, true );
				wp_enqueue_style( 'cr-frontend-css' );
				wp_localize_script(
					'cr-frontend-js',
					'cr_ajax_object',
					array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'ivole_recaptcha' => 0, // port: CR_Captcha::is_enabled(), false with the default settings (no captcha configured)
						'disable_lightbox' => ( $this->disable_lightbox ? 1 : 0 ),
						'cr_upload_initial' => sprintf( __( 'Upload up to %d images or videos', 'customer-reviews-woocommerce' ), $this->limit_file_count ),
						'cr_upload_error_file_type' => __( 'Error: accepted file types are PNG, JPG, JPEG, GIF, MP4, MPEG, OGG, WEBM, MOV, AVI', 'customer-reviews-woocommerce' ),
						'cr_upload_error_too_many' => sprintf( __( 'Error: You tried to upload too many files. The maximum number of files that can be uploaded is %d.', 'customer-reviews-woocommerce' ), $this->limit_file_count ),
						'cr_upload_error_file_size' => sprintf( __( 'The file cannot be uploaded because its size exceeds the limit of %d MB', 'customer-reviews-woocommerce' ), intval( $this->limit_file_size / 1024 / 1024 ) ),
						'cr_images_upload_limit' => $this->limit_file_count,
						'cr_images_upload_max_size' => $this->limit_file_size,
						'rating_filter' => self::$rating_get_filter,
						'reviews_tab' => self::$reviews_tab,
						'flags_url' => AF_CUSREV_URL . 'img/flags/' // port: URL
					)
			);
			wp_enqueue_script( 'cr-frontend-js' );
			do_action( 'cr_after_enqueue_scripts_scripts' );
		}
	}

	public function add_query_var() {
		global $wp;
		$wp->add_query_var( self::$rating_get_filter );
		$wp->add_query_var( 'crsearch' );
	}

	public function display_verified_badge( $comment ) {
		if( 0 === intval( $comment->comment_parent ) ) {
			$output = '';
			// check if a badge should be shown for the review
			$product_id = $comment->comment_post_ID;
			$order_id = get_comment_meta( $comment->comment_ID, 'ivole_order', true );
			// WPML integration
			if ( has_filter( 'wpml_object_id' ) ) {
				$wpml_def_language = apply_filters( 'wpml_default_language', null );
				$original_product_id = apply_filters( 'wpml_object_id', $product_id, 'product', true, $wpml_def_language );
				$product_id = $original_product_id;
			}
			// port: the plugin's "Verified review - view original" link to cusrev.com
			// (option ivole_verified_links, default 'no', a CusRev-hosted feature) is
			// not ported. It was never switched on here.

			// check if country/region should be shown for the review
			$country = get_comment_meta( $comment->comment_ID, 'ivole_country', true );
			if( is_array( $country ) && 2 === count( $country ) ) {
				$country_string = '';
				if( isset( $country['code'] ) ) {
					if( strlen( $output ) > 0 ) {
						$output .= '<span class="ivole-review-country-space">&emsp;|&emsp;</span>';
					}
					$output .= '<img src="' . AF_CUSREV_URL . // port: was plugin_dir_url( dirname( dirname( __FILE__ ) ) ) .
						'img/flags/' .
						rawurlencode( strtolower( $country['code'] ) ) .
						'.svg" class="ivole-review-country-icon" alt="' .
						esc_attr( strtoupper( $country['code'] ) ) .
						'">';
					if ( isset( $country['desc'] ) ) {
						$output .= '<span class="ivole-review-country-text">' . esc_html( $country['desc'] ) . '</span>';
					}
				}
			}
			// if there is something to print, print it
			if( strlen( $output ) > 0 ) {
				echo '<p class="ivole-verified-badge">' . $output . '</p>';
			}
		}
	}

	public function display_featured( $comment ) {
		if ( 0 === intval( $comment->comment_parent ) ) {
			$title = get_comment_meta( $comment->comment_ID, 'cr_rev_title', true );
			if ( $title ) {
				echo '<div class="cr-comment-head-text">' . esc_html( $title ) . '</div>';
			}
			if( 0 < $comment->comment_karma ) {
				// display 'featured' badge
				$output = __( 'Featured Review', 'customer-reviews-woocommerce' );
				echo '<p class="cr-featured-badge"><span>' . $output . '</span></p>';
			}
		}
	}

	public function display_custom_questions( $comment ) {
		if ( self::$custom_ratings_enabled && 0 === intval( $comment->comment_parent ) ) {
			$custom_questions = new CR_Custom_Questions();
			$custom_questions->read_questions( $comment->comment_ID );
			$custom_questions->output_questions( true );
		}
	}

	public function cusrev_review_meta( $comment ) {
		$template = wc_locate_template(
			'review-meta.php',
			'customer-reviews-woocommerce',
			__DIR__ . '/../../templates/'
		);
		include( $template );
		remove_action( 'woocommerce_review_meta', 'woocommerce_review_display_meta', 10 );
	}

	public function cr_photoswipe() {
		if( is_product() ) {
			if ( ! $this->disable_lightbox && ! current_theme_supports( 'wc-product-gallery-lightbox' ) ) {
				wc_get_template(
					'cr-photoswipe.php',
					array(),
					'customer-reviews-woocommerce',
					dirname( dirname( dirname( __FILE__ ) ) ) . '/templates/'
				);
			}
		}
	}

	public static function get_close_button_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="cr-close-button-svg"><rect x="0" fill="none" width="18" height="18"/><g><path class="cr-close-button-svg-p" d="M12.12 10l3.53 3.53-2.12 2.12L10 12.12l-3.54 3.54-2.12-2.12L7.88 10 4.34 6.46l2.12-2.12L10 7.88l3.54-3.53 2.12 2.12z"/></g></svg>';
	}

	public static function get_star_rating_svg( $rating, $count, $color ) {
		$templateFile = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . '/templates/cr-rating-icon.php';
		$templateFileBg = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . '/templates/cr-rating-icon-bg.php';
		$rating = is_numeric( $rating ) ? (float)$rating : 0;

		$inline_icon_style = '';
		if ( $color ) {
			$inline_icon_style = 'stroke: ' . $color . ';';
		}
		$html = '<div class="cr-rating-icon-base">';
		for ($i = 0; $i < 5; $i++) {
			ob_start();
			include( $templateFileBg );
			$html .= ob_get_clean();
		}
		$html .= '</div>';

		$inline_icon_style = '';
		if ( $color ) {
			$inline_icon_style = 'fill: ' . $color . ';';
		}
		$html .= '<div class="cr-rating-icon-frnt" style="width:' . ( ( $rating / 5 ) * 100 ) . '%;">';
		for ($i = 0; $i < 5; $i++) {
			ob_start();
			include( $templateFile );
			$html .= ob_get_clean();
		}
		$html .= '</div>';

		return apply_filters( 'cr_get_star_rating_svg', $html, $rating, $count, $color );
	}

	public static function cr_review_is_from_verified_owner( $review ) {
		static $verification_cache = array();

		if ( ! is_object( $review ) || ! $review instanceof WP_Comment ) {
			return false;
		}

		$comment_id = absint( $review->comment_ID );
		if ( array_key_exists( $comment_id, $verification_cache ) ) {
			return $verification_cache[ $comment_id ];
		}

		$verified = get_comment_meta( $comment_id, 'verified', true );
		if ( '' !== $verified ) {
			$verification_cache[ $comment_id ] = (bool) $verified;
			return $verification_cache[ $comment_id ];
		}

		global $wpdb;
		$lock_name = 'cr_review_verify_' . md5( (string) $comment_id );
		$lock_acquired = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK( %s, 0 )',
				$lock_name
			)
		);

		if ( 1 !== $lock_acquired ) {
			$verification_cache[ $comment_id ] = false;
			return false;
		}

		try {
			$verified = get_comment_meta( $comment_id, 'verified', true );
			if ( '' !== $verified ) {
				$verification_cache[ $comment_id ] = (bool) $verified;
				return $verification_cache[ $comment_id ];
			}

			$verified = false;
			$email = $review->user_id ? '' : $review->comment_author_email;
			if ( 'product' === get_post_type( $review->comment_post_ID ) ) {
				$verified = wc_customer_bought_product( $email, $review->user_id, $review->comment_post_ID );
			} elseif ( $review->comment_post_ID ) {
				$shop_pages = af_cusrev_get_shop_page(); // port: CR_Reviews_List_Table::get_shop_page(), copied to inc/ports/cusrev/cusrev.php
				if ( in_array( $review->comment_post_ID, $shop_pages ) ) {
					$customer_orders = wc_get_orders( array(
						'limit'         => 1,
						'customer_id'   => $review->user_id ? $review->user_id : '',
						'billing_email' => $email,
						'status'        => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
						'return'        => 'ids',
					) );
					$verified = ! empty( $customer_orders );
				}
			}

			update_comment_meta( $comment_id, 'verified', (int) $verified );
			$verification_cache[ $comment_id ] = (bool) $verified;
			return $verification_cache[ $comment_id ];
		} finally {
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT RELEASE_LOCK( %s )',
					$lock_name
				)
			);
		}
	}

	public function delete_review_media_attachments( $comment_id, $comment ) {
		$meta_keys = array(
			self::REVIEWS_META_LCL_IMG,
			self::REVIEWS_META_LCL_VID,
		);
		foreach ( $meta_keys as $meta_key ) {
			$meta_values = get_comment_meta( $comment_id, $meta_key, false );

			if ( empty( $meta_values ) ) {
				continue;
			}

			foreach ( $meta_values as $attachment_id ) {

				$attachment_id = absint( $attachment_id );
				if ( ! $attachment_id ) {
					continue;
				}

				// Make sure the attachment exists and is a media item
				if ( 'attachment' === get_post_type( $attachment_id ) ) {
					wp_delete_attachment( $attachment_id, true );
				}
			}
		}
	}

}

endif;
