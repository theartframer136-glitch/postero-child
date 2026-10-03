<?php

class GRWP_Shortcode {

    /**
     * Plugin Options/settings.
     * @var null
     */
    private $options = null;

    public function __construct() {

        $this->options = get_option( 'google_reviews_option_name' );

        add_shortcode('google-reviews', [ $this, 'reviews_shortcode' ] );

    }

    /**
     * Get style override value from shortcode attributes
     * @param array $atts
     * @return string
     */
    private function get_review_style_override( array $atts ) {

        $review_style_override = '';

        if ( isset($atts['style']) ) {
            $override = $atts['style'];

            switch ( $override ) {
                case '1':
                    $review_style_override = 'layout_style-1';
                    break;
                case '2':
                    $review_style_override = 'layout_style-2';
                    break;
                case '3':
                    $review_style_override = 'layout_style-3';
                    break;
                case '4':
                    $review_style_override = 'layout_style-4';
                    break;
                case '5':
                    $review_style_override = 'layout_style-5';
                    break;
                case '6':
                    $review_style_override = 'layout_style-6';
                    break;
                case '7':
                    $review_style_override = 'layout_style-7';
                    break;
                case '8':
                    $review_style_override = 'layout_style-8';
                    break;
                case '9':
                    $review_style_override = 'layout_style-9';
                    break;
                case '10':
                    $review_style_override = 'layout_style-10';
                    break;
                case '11':
                    $review_style_override = 'layout_style-11';
                    break;
            }
        }

        return $review_style_override;

    }


    /**
     * Get type override value from shortcode attributes
     * @param array $atts
     * @return string
     */
    private function get_review_type_override( array $atts ) {

        $result = '';

        if ( isset( $atts['type'] ) ) {

	        switch ( $atts['type'] ) {
		        case 'grid':
					$result = 'grid';
					break;
		        case 'slider':
					$result = 'slider';
					break;
		        case 'badge':
					$result = 'badge';
					break;
	        }

            // $result = $atts['type'] === 'grid' ? 'grid' : 'slider';
        }

        return $result;
    }

    /**
     * Get marquee override value from shortcode attributes.
     *
     * Returns true/false to force marquee mode on/off for this specific
     * shortcode (overriding the dashboard setting), or null when the attribute
     * is absent/unrecognised (fall back to the dashboard setting).
     *
     * @param array $atts
     * @return bool|null
     */
    private function get_marquee_override( array $atts ) {

        if ( ! isset( $atts['marquee'] ) ) {
            return null;
        }

        $value = strtolower( trim( (string) $atts['marquee'] ) );

        if ( in_array( $value, array( 'true', '1', 'yes', 'on' ), true ) ) {
            return true;
        }

        if ( in_array( $value, array( 'false', '0', 'no', 'off' ), true ) ) {
            return false;
        }

        return null;
    }

    /**
     * Parse shortcode data, return html
     * @param array|null $atts
     * @return string
     */
    public function reviews_shortcode( $atts = null ) : string {

        // get style/type override values
        $review_type_override = '';
        $review_style_override = '';
        $max_reviews = null;
	    $show_place_info = false;
        $marquee_override = null;

        if ( $atts ) {

            $review_type_override = $this->get_review_type_override( $atts );
            $review_style_override = $this->get_review_style_override( $atts );
			$place_info = isset($atts['place_info']) ? $atts['place_info'] : null;
	        $show_place_info = $place_info === 'true';
            $max_reviews = isset($atts['max_reviews']) ? $atts['max_reviews'] : null;
            $marquee_override = $this->get_marquee_override( $atts );

        }

        $link_user_profiles = false;
        if ( isset($this->options['link_users_profiles']) ) {
            if ( $this->options['link_users_profiles'] === '1' ) {
                $link_user_profiles = true;
            }
        }

        // check if style type is overwritten by shortcode attributes
        $style_type = $this->options['layout_style'];

        if ( $review_style_override !== '' ) {

            $style_type = $review_style_override;

        }

        // check if widget type is overwritten by shortcode attributes
        $widget_type = strtolower($this->options['style_2']);
        if ( $review_type_override !== '' ) {

            $widget_type = $review_type_override;

        }

        // set txt for badge
        $txt = 'https://reviewsembedder.com';
        $activation_version = get_option('grwp_activation_version');
        if ( $activation_version ) {
            if ( version_compare( $activation_version, '1.6.6', '<=') ) {
                $txt = 'https://reviewsembedder.com';
            }
            else if ( version_compare( $activation_version, '1.7.6', '<=') ) {
                $txt = 'Google Reviews WordPress Plugin';
            }
            else if ( version_compare( $activation_version, '2.1.2', '>=') ) {
                $txt = 'Google Reviews WordPress Plugin';
            }
        }

        if ( $widget_type === 'slider' ) {
            $slider = new GRWP_Reviews_Widget_Slider();
            return $slider->render( $style_type, $link_user_profiles, $max_reviews, $show_place_info, $txt, $marquee_override );
        }

		elseif ( $widget_type === 'badge' ) {     
            $badge = new GRWP_Reviews_Widget_Badge();
            return $badge->render( $link_user_profiles, $max_reviews, $txt );
        
		}

        $grid = new GRWP_Reviews_Widget_Grid();
        return $grid->render( $style_type, $link_user_profiles, $max_reviews, $show_place_info, $txt );

    }


}
