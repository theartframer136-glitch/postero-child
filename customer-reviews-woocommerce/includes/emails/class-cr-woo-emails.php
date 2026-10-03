<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Woo_Emails' ) ) :

class CR_Woo_Emails {

	// href of the button rendered by the block; it is replaced with a real link when an email is sent
	const REVIEW_LINK_PLACEHOLDER = '#cusrev-review-link';

	public function __construct() {
		add_filter(
			'woocommerce_email_additional_content_customer_processing_order',
			array( $this, 'shortcodes_in_emails' ),
			10,
			3
		);
		add_filter(
			'woocommerce_email_additional_content_customer_completed_order',
			array( $this, 'shortcodes_in_emails' ),
			10,
			3
		);
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_assets', array( $this, 'restrict_block_to_email_editor' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'restrict_block_to_email_editor' ), 20 );
		add_filter( 'woocommerce_mail_callback_params', array( $this, 'resolve_review_link' ), 10, 2 );
	}

	/**
	 * The block has to be registered on the server side so that it can be rendered in emails,
	 * but its editor script should run in the block-based email editor only. Otherwise, the block
	 * would be offered in the inserter of regular posts and pages too.
	 */
	public function restrict_block_to_email_editor() {
		if ( apply_filters( 'woocommerce_is_email_editor_page', false ) ) {
			return;
		}

		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'cusrev/email-review-button' );
		if ( ! $block ) {
			return;
		}

		foreach ( (array) $block->editor_script_handles as $handle ) {
			wp_dequeue_script( $handle );
		}
	}

	/**
	 * Registers the Review Button block for the block-based WooCommerce email editor.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		if ( ! post_type_exists( 'woo_email' ) ) {
			// the block-based email editor is not available
			return;
		}
		if ( 'no' !== get_option( 'ivole_verified_reviews', 'no' ) ) {
			// the block is available with the self-hosted setting only initially
			return;
		}

		register_block_type(
			dirname( dirname( dirname( __FILE__ ) ) ) . '/blocks/build/email-review-button',
			array(
				'render_callback' => array( $this, 'render_review_button_block' )
			)
		);
	}

	public function render_review_button_block( $attributes ) {
		$atts = array(
			'label'		=> ! empty( $attributes['label'] ) ? $attributes['label'] : __( 'Review', 'customer-reviews-woocommerce' ),
			'bg'		=> isset( $attributes['bg'] ) ? $attributes['bg'] : '#0073aa',
			'color'		=> isset( $attributes['color'] ) ? $attributes['color'] : '#ffffff',
			'radius'	=> isset( $attributes['radius'] ) ? $attributes['radius'] : '4px'
		);

		// the order is not known at this point, so a placeholder is used instead of a link
		return $this->render_review_button( $atts, self::REVIEW_LINK_PLACEHOLDER, $atts['label'] );
	}

	/**
	 * Replaces the placeholder of the Review Button block with a link to a review form.
	 */
	public function resolve_review_link( $params, $email ) {
		if ( ! isset( $params[2] ) || ! is_string( $params[2] ) ) {
			return $params;
		}
		if ( false === strpos( $params[2], self::REVIEW_LINK_PLACEHOLDER ) ) {
			return $params;
		}

		$url = '';
		if ( $email && isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) {
			$order_id = $email->object->get_id();
			$link = new CR_Copy_Link( $order_id );
			$review_form = $link->get_review_form( $order_id );
			if ( is_array( $review_form ) && count( $review_form ) > 1 && 0 === $review_form[0] ) {
				$url = $review_form[1];
			}
		}

		$params[2] = str_replace( self::REVIEW_LINK_PLACEHOLDER, esc_url( $url ? $url : home_url( '/' ) ), $params[2] );

		return $params;
	}

	public function shortcodes_in_emails( $additional_content, $object, $email ) {
		if ( empty( $additional_content ) ) {
			return $additional_content;
		}
		if ( empty( $object ) || ! is_a( $object, 'WC_Order' ) ) {
			return $additional_content;
		}
		if ( 'no' !== get_option( 'ivole_verified_reviews', 'no' )  ) {
			// shortcodes are available with the self-hosted setting only initially
			return $additional_content;
		}

		$order_id = $object->get_id();

		$pattern = '/\[cusrev_review_button([^\]]*)\]/';
		return preg_replace_callback(
			$pattern,
			function( $matches ) use ( $order_id ) {

				$atts_string = trim( $matches[1] );
				$atts = shortcode_parse_atts( $atts_string );

				if ( ! is_array( $atts ) ) {
					$atts = array();
				}

				// check if the order is a real one
				if ( ! wc_get_order( $order_id ) ) {
					$order_id = 0;
				}

				return $this->render_review_button_shortcode( $atts, $order_id );
			},
			$additional_content
		);
	}

	public function render_review_button_shortcode( $atts, $order_id = 0 ) {
		$atts = shortcode_atts(
			array(
				'label'	=> __( 'Review', 'customer-reviews-woocommerce' ),
				'bg'		=> '#0073aa',
				'color'		=> '#ffffff',
				'radius'	=> '4px',
			),
			$atts,
			'cusrev_review_button'
		);

		$label = $atts['label'];
		$url = '';

		$link = new CR_Copy_Link( $order_id );
		$review_form = $link->get_review_form( $order_id );
		if ( is_array( $review_form ) && count( $review_form )  > 1 ) {
			if ( 0 !== $review_form[0] ) {
				$label = $review_form[1];
			} else {
				// success
				$url = $review_form[1];
			}
		} else {
			$label = __( 'Error: could not copy a link to an aggregated review form', 'customer-reviews-woocommerce' );
		}

		return $this->render_review_button( $atts, $url, $label );
	}

	private function render_review_button( $atts, $url, $label ) {
		$bg		= sanitize_hex_color( $atts['bg'] ) ?: '#0073aa';
		$color	= sanitize_hex_color( $atts['color'] ) ?: '#ffffff';
		$radius	= preg_replace( '/[^0-9.%px]/', '', $atts['radius'] );
		$href	= self::REVIEW_LINK_PLACEHOLDER === $url ? $url : esc_url( $url );

		$output  = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">';
		$output .= '<tr>';
		$output .= '<td align="center">';

		$output .= '<table role="presentation" border="0" cellpadding="0" cellspacing="0">';
		$output .= '<tr>';
		$output .= '<td align="center" bgcolor="' . esc_attr( $bg ) . '" style="border-radius:' . esc_attr( $radius ) . ';">';
		$output .= '<a href="' . $href . '" target="_blank" ';
		$output .= 'style="display:inline-block;padding:12px 24px;';
		$output .= 'color:' . esc_attr( $color ) . ';';
		$output .= 'text-decoration:none;border-radius:' . esc_attr( $radius ) . ';">';
		$output .= esc_html( $label );
		$output .= '</a>';
		$output .= '</td>';
		$output .= '</tr>';
		$output .= '</table>';

		$output .= '</td>';
		$output .= '</tr>';
		$output .= '</table>';

		return $output;
	}

}

endif;
