<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/qna/class-cr-qna.php. Kept: the two comment
 * hooks the class adds whatever the settings: replies to a question are typed
 * cr_qna, and questions/answers (comment type cr_qna) are kept out of comment
 * queries on product pages, i.e. out of the review list. Left out: the Q&A tab
 * and everything else (option ivole_questions_answers, default 'no').
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Qna' ) ) :

	class CR_Qna {

		public function __construct() {
			add_filter( 'preprocess_comment', array( $this, 'update_answer_type' ) );
			add_action( 'pre_get_comments', array( $this, 'filter_out_qna' ) );
		}

		public function update_answer_type( $commentdata ) {
			// if a new comment is a reply to a question, then set its type to 'cr_qna'
			if( isset( $commentdata['comment_parent'] ) && 0 < $commentdata['comment_parent'] ) {
				if( 'cr_qna' === get_comment_type( $commentdata['comment_parent'] ) ) {
					$commentdata['comment_type'] = 'cr_qna';
				}
			}
			return $commentdata;
		}

		public function filter_out_qna( &$query ) {
			if( is_product() ) {
				if( isset( $query->query_vars ) && isset( $query->query_vars['type'] ) && 'cr_qna' !== $query->query_vars['type'] ) {
					if( isset( $query->query_vars['type__not_in'] ) && is_array( $query->query_vars['type__not_in'] ) ) {
						$query->query_vars['type__not_in'][] = 'cr_qna';
					} else {
						$query->query_vars['type__not_in'] = array( 'cr_qna' );
					}
				}
			}
		}

	}

endif;
