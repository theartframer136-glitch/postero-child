<?php

class GRWP_Reviews_Widget_Slider
    extends
    GRWP_Google_Reviews_Output {

    /**
     * Slider HTML
     * @return string
     */
    public function render( $style_type, $link_user_profiles, $max_reviews = null, $show_place_info = false, $txt = '', $marquee_override = null ) {

        // error handling
        if ( $this->reviews_have_error ) {
            /* translators: no reviews available */
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

        $hide_slider_arrows = false;
        if ( isset($this->options['hide_slider_arrows']) ) {
            if ( $this->options['hide_slider_arrows'] === '1' ) {
                $hide_slider_arrows = true;
            }
        }

        // Resolve the effective marquee state for this instance. A shortcode
        // `marquee` attribute (true/false) overrides the dashboard setting; when
        // absent ($marquee_override === null) the dashboard setting applies.
        // Marquee is a premium feature, so it's forced off on free installs
        // regardless of the shortcode/dashboard value.
        $marquee_active = ( $marquee_override !== null )
            ? (bool) $marquee_override
            : ( isset($this->options['marquee_slider']) && $this->options['marquee_slider'] === '1' );

        if ( ! grwp_fs()->can_use_premium_code() ) {
            $marquee_active = false;
        }

        // Marquee mode scrolls continuously and ignores the navigation arrows
        // (the Swiper Navigation module isn't loaded for it), so hide them.
        if ( $marquee_active ) {
            $hide_slider_arrows = true;
        }

	    $google_svg = GR_PLUGIN_DIR_URL . 'dist/images/google-logo-svg.svg';

	    // Prev/next arrow placement mode (CSS in swiper.scss). The default
	    // depends on the install's first-activation version (see
	    // grwp_default_arrows_position()).
	    $arrows_position = isset( $this->options['slider_arrows_position'] )
		    ? $this->options['slider_arrows_position']
		    : grwp_default_arrows_position();
	    if ( ! in_array( $arrows_position, array( 'below', 'middle' ), true ) ) {
		    $arrows_position = grwp_default_arrows_position();
	    }
	    $arrows_class = 'grwp-arrows-' . $arrows_position;

		$output = '';

		// When the compact header is shown it carries the top spacing, so the
		// slider body/track top offsets are removed (see header-compact.scss).
		$compact_header_class = $this->compact_header_active( $show_place_info ) ? ' grwp-has-compact-header' : '';

	    $output = sprintf( '<div id="g-review" class="%s grwp_grid %s%s">', $style_type, $hide_date, $compact_header_class );

		$output .= $this->render_company_header( $show_place_info, $show_verified, $txt, 'Lorem Ipsum Business Title', true );

	    $output .= '<div class="grwp_body">';

        // loop through reviews
        $output .= sprintf('<div id="g-review" class="%s">', $style_type);
        $slider_output = '';

        $count = 0;
        foreach ( $this->reviews as $review ) {

            if ( $max_reviews && is_numeric( $max_reviews ) && intval($max_reviews) <= $count ) {
                break;
            }

            $star_output = $this->get_star_output($review);

            $slide_duration = isset($this->options['slide_duration']) ? intval($this->options['slide_duration']) * 1000 : '';

            ob_start();
            $markup_file = 'partials/slider/markup.php';
            if ( $style_type === 'layout_style-10' ) {
                $markup_file = 'partials/slider/markup-style10.php';
            } elseif ( $style_type === 'layout_style-11' ) {
                $markup_file = 'partials/slider/markup-style11.php';
            }
            require $markup_file;
            $slider_output .= ob_get_clean();

            $count++;

        }

        ob_start();
        require 'partials/slider/slider-header.php';
        echo wp_kses( $slider_output, $this->allowed_html );
        require 'partials/slider/slider-footer.php';

        $output .= ob_get_clean();

        $output .= '</div></div>';

        // The compact header carries its own "See all reviews" button, so skip
        // the standalone one below the widget to avoid a duplicate.
        if ( ! $this->compact_header_active( $show_place_info ) ) {
            $output .= $this->get_button_output();
        }

        $output .= '</div>';

        return wp_kses( $output, $this->allowed_html );

    }

}
