<?php
/*
 * Customer Reviews for WooCommerce 5.123.0 (customer-reviews-woocommerce), moved
 * into the theme: see inc/ports/customer-reviews.php.
 *
 * From the plugin's includes/reviews/class-cr-custom-questions.php. Kept: reading and
 * printing the answers to custom questions stored with a review
 * (comment meta ivole_c_questions, CR_Custom_Question objects), as shown
 * under the review text. Left out: the review-form and admin-editing parts.
 * Every line below is the plugin's own, in the plugin's order, except the
 * lines marked "port:".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Custom_Questions' ) ) :

	class CR_Custom_Questions {
		private $questions = array();
		public static $meta_id = 'ivole_c_questions';
		public static $onsite_prefix = 'cr_onsite_';
		public static $type_label_prefix = 'cr_typ_lab_';

		public function __construct() {
		}

		public function read_questions( $review_id ) {
			$meta = get_comment_meta( $review_id, self::$meta_id, true );
			if( $meta && is_array( $meta ) ) {
				$count_meta = count( $meta );
				for( $i = 0; $i < $count_meta; $i++ ) {
					if( $meta[$i] instanceof CR_Custom_Question ) {
						$this->questions[] = $meta[$i];
					}
				}
			}
		}

		public function get_questions( $f, $hr ) {
			$fr = '';
			if( $f ) {
				$fr = 'f';
			}
			$count_questions = count( $this->questions );
			$output = '';
			for( $i = 0; $i < $count_questions; $i++ ) {
				if (
					isset( $this->questions[$i]->type ) &&
					isset( $this->questions[$i]->title )
				) {
					$title = ( isset( $this->questions[$i]->label ) && $this->questions[$i]->label ) ? $this->questions[$i]->label : $this->questions[$i]->title;
					switch( $this->questions[$i]->type ) {
						case 'checkbox':
							if (
								isset( $this->questions[$i]->values ) &&
								is_array( $this->questions[$i]->values )
							) {
								$count_values = count( $this->questions[$i]->values );
								$output_temp = '';
								for( $j = 0; $j < $count_values; $j++ ) {
									$output_temp .= '<li>' . $this->questions[$i]->values[$j] . '</li>';
								}
								if( $count_values > 0 ) {
									if ( 2 === $f ) {
										// slider layout
										$output .= '<div class="cr-sldr-custom-question cr-sldr-checkbox">';
										$output .= '<p class="cr-sldr-p"><span class="cr-sldr-label">' . $title . '</span> :</p>';
										$output .= '<ul class="iv' . $fr . '-custom-question-ul">' . $output_temp . '</ul>';
										$output .= '</div>';
									} else {
										$output .= '<p class="iv' . $fr . '-custom-question-checkbox">' . $this->questions[$i]->title . ' : </p>';
										$output .= '<ul class="iv' . $fr . '-custom-question-ul">' . $output_temp . '</ul>';
									}
								}
							}
							break;
						case 'rating':
							if( isset( $this->questions[$i]->value ) ) {
								if( $this->questions[$i]->value > 0 ) {
									if( $f ) {
										if ( 2 === $f ) {
											// slider layout
											$output .= '<div class="cr-sldr-custom-question">';
											$output .= '<div class="crstar-rating-svg" role="img" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'woocommerce' ), $this->questions[$i]->value ) ) . '">' . CR_Reviews::get_star_rating_svg( $this->questions[$i]->value, 0, '' ) . '</div>';
											$output .= '<div class="cr' . $fr . '-custom-question-rating">' . $title . '</div></div>';
										} else {
											// list layout
											$output .= '<div class="cr' . $fr . '-custom-question-rating-cont"><div class="cr' . $fr . '-custom-question-rating">' . $title . ' :</div>';
											$output .= '<div class="crstar-rating-svg" role="img" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'woocommerce' ), $this->questions[$i]->value ) ) . '">' . CR_Reviews::get_star_rating_svg( $this->questions[$i]->value, 0, '' ) . '</div></div>';
										}
									} else {
										$output .= '<div class="cr' . $fr . '-custom-question-rating-cont"><span class="cr' . $fr . '-custom-question-rating">' . $title . ' :</span>';
										$output .= '<span class="iv' . $fr . '-star-rating">';
										for ( $j = 1; $j < 6; $j++ ) {
											$class = ( $j <= $this->questions[$i]->value ) ? 'filled' : 'empty';
											$output .= '<span class="dashicons dashicons-star-' . $class . '"></span>';
										}
										$output .= '</span></div>';
									}
								}
							}
							break;
						case 'radio':
						case 'comment':
						case 'number':
						case 'text':
							if( isset( $this->questions[$i]->value ) && $this->questions[$i]->value ) {
								if ( 2 === $f ) {
									// slider layout
									$output .= '<div class="cr-sldr-custom-question">';
									$output .= '<p class="cr-sldr-p"><span class="cr-sldr-label">' . $title . '</span> ' . $this->questions[$i]->value . '</p>';
									$output .= '</div>';
								} else {
									$output .= '<div class="iv' . $fr . '-custom-question-p"><span class="iv' . $fr . '-custom-question-radio">' . $title .
										' :</span> ' . $this->questions[$i]->value . '</div>';
								}
							}
							break;
						default:
							break;
					}
				}
			}
			if( strlen( $output ) > 0 ) {
				if( $f ) {
					if ( 2 === $f ) {
						// do not add <hr>
					} else {
						$output = '<hr class="iv' . $fr . '-custom-question-hr">' . $output . '<hr class="iv' . $fr . '-custom-question-hr">';
					}
				} else {
					if( $hr ) {
						$output = '<hr class="iv' . $fr . '-custom-question-hr">' . $output;
					}
				}
			}
			return $output;
		}

		public function output_questions( $f = false, $hr = true ) {
			$qs = $this->get_questions( $f, $hr );
			if ( $qs ) {
				echo apply_filters( 'cr_custom_questions', $qs );
			}
		}

	}

endif;

if ( ! class_exists( 'CR_Custom_Question' ) ) :
	class CR_Custom_Question {
		public $type;
		public $title;
		public $label;
		public $value;
		public $values = array();
	}
endif;
