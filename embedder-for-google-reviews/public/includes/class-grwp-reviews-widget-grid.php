<?php

class GRWP_Reviews_Widget_Grid
    extends
    GRWP_Google_Reviews_Output {

    public function render( $style_type, $link_user_profiles, $max_reviews = null, $show_place_info = false, $txt = '' ) {

        // error handling
        if ( $this->reviews_have_error ) {
            /* translators: No reviews available */
            return __( 'No reviews available', 'embedder-for-google-reviews' );
        }

	    $hide_date = '';
	    if ( isset($this->options['hide_date_string']) ) {
		    if ( $this->options['hide_date_string'] !== '' ) {
			    $hide_date = 'hide_date';
		    }
	    }

        $show_verified = false;
        if ( isset($this->options['show_verified']) ) {
            if ( $this->options['show_verified'] === '1' ) {
                $show_verified = true;
            }
        }

        $google_svg = GR_PLUGIN_DIR_URL . 'dist/images/google-logo-svg.svg';

        // Show-more button: number of review ROWS visible before "Load more".
        // The cards-per-row count is responsive, so JS derives the visible card
        // count from rows x columns and keeps it in sync on resize.
        $show_more_attr = '';
        $show_more_rows = 0;
        if ( ! empty( $this->options['show_more_grid'] ) ) {
            $show_more_rows = isset( $this->options['show_more_grid_initial'] ) && intval( $this->options['show_more_grid_initial'] ) > 0
                ? intval( $this->options['show_more_grid_initial'] )
                : 2;
            $load_more_rows = isset( $this->options['show_more_grid_load_more_rows'] ) && intval( $this->options['show_more_grid_load_more_rows'] ) > 0
                ? intval( $this->options['show_more_grid_load_more_rows'] )
                : 2;
            $show_more_attr = ' data-grwp-show-more-rows="' . esc_attr( $show_more_rows ) . '"'
                . ' data-grwp-load-more-rows="' . esc_attr( $load_more_rows ) . '"';
        }

        // Number of cards that will actually be rendered (respecting max_reviews)
        $total_cards = count( $this->reviews );
        if ( $max_reviews && is_numeric( $max_reviews ) ) {
            $total_cards = min( $total_cards, intval( $max_reviews ) );
        }

        // Truncated up front when "Show more" is active and there are extra cards.
        // While truncated the "See all reviews" button stays hidden (see CSS);
        // JS removes this class once every review has been revealed.
        $truncated_class = ( $show_more_rows > 0 && $total_cards > $show_more_rows )
            ? ' grwp-truncated'
            : '';

	    $output = sprintf( '<div id="g-review" class="%s grwp_grid %s%s"%s>', $style_type, $hide_date, $truncated_class, $show_more_attr );

		$output .= $this->render_company_header( $show_place_info, $show_verified, $txt, 'Lorem Ipsum Business' );

		$output .= '<div class="grwp_body">';

		// loop through reviews
        $count = 0;
        foreach ( $this->reviews as $review ) {

            if ( $max_reviews && is_numeric( $max_reviews ) && intval($max_reviews) <= $count ) {
                break;
            }

            $star_output = $this->get_star_output($review);

            // Hide cards beyond the initial rows up front so they don't flash on load.
            // Columns are unknown server-side, so assume one column (the safe minimum):
            // never show more than intended; JS then reveals enough to fill each row.
            $card_hidden_class = ( $show_more_rows > 0 && $count >= $show_more_rows )
                ? ' grwp-card-hidden'
                : '';

            ob_start();
            $markup_file = 'partials/grid/markup.php';
            if ( $style_type === 'layout_style-10' ) {
                $markup_file = 'partials/grid/markup-style10.php';
            } elseif ( $style_type === 'layout_style-11' ) {
                $markup_file = 'partials/grid/markup-style11.php';
            }
            require $markup_file;
            $output .= ob_get_clean();

            $count++;

        }

        $output .= '</div>';

        // The compact header carries its own "See all reviews" button, so skip
        // the standalone one below the widget to avoid a duplicate.
        if ( ! $this->compact_header_active( $show_place_info ) ) {
            $output .= $this->get_button_output();
        }

        $output .= '</div>';

        return wp_kses( $output, $this->allowed_html );

    }
}
