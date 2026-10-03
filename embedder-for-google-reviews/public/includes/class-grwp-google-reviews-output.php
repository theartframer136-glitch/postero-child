<?php

/**
 * Define methods for public html output via shortcodes etc.
 */
class GRWP_Google_Reviews_Output {
    /**
     * Whether to show dummy content
     * @var bool
     */
    protected $showdummy = false;

    /**
     * Allowed HTML and HTML attributes
     * @var array
     */
    protected $allowed_html;

    /**
     * Gobal reviews data
     * @var array|null
     */
    protected $reviews;

    /**
     * Flag if reviews are erroneous
     * @var bool
     */
    protected $reviews_have_error = false;

    protected $rating_rounded = 0;

    protected $rating_formatted = 5.0;

    protected $rating_value = 5.0;

    protected $total_reviews = 0;

    protected $place_title = '';

    protected $options = [];

    /**
     * Constructor method
     */
    public function __construct() {
        require_once __DIR__ . '/allowed-html.php';
        global $allowed_html;
        $this->allowed_html = $allowed_html;
        $this->options = get_option( 'google_reviews_option_name' );
        $this->showdummy = isset( $this->options['show_dummy_content'] );
        $this->reviews = $this->get_review_data();
        $this->set_place_info();
        // check for errors and set flag
        if ( is_wp_error( $this->reviews ) || $this->reviews == '' || $this->reviews == null || !is_array( $this->reviews ) ) {
            $this->reviews_have_error = true;
        }
    }

    /**
     * Get place info
     */
    protected function set_place_info() {
        $all_options = get_option( 'google_reviews_option_name' );
        $data_id = $all_options['serp_data_id'];
        $place_raw = get_option( 'grwp_place_info' );
        $place_info = ( isset( $place_raw[$data_id] ) ? $place_raw[$data_id] : null );
        if ( $place_info ) {
            $place_info_arr = json_decode( $place_info, true );
            $this->rating_rounded = ( isset( $place_info_arr['rating'] ) ? intval( round( $place_info_arr['rating'] ) ) : 0 );
            $this->rating_value = ( isset( $place_info_arr['rating'] ) ? floatval( $place_info_arr['rating'] ) : 5.0 );
            $this->rating_formatted = ( isset( $place_info_arr['rating'] ) ? number_format_i18n( $place_info_arr['rating'], 1 ) : 'N/A' );
            $this->total_reviews = ( isset( $place_info_arr['reviews'] ) ? number_format_i18n( $place_info_arr['reviews'] ) : 'N/A' );
            $this->place_title = ( isset( $place_info_arr['title'] ) ? $place_info_arr['title'] : 'Unknown Title' );
        }
    }

    /**
     * Get total star rating html
     */
    protected function get_total_stars() {
        $path = esc_attr( GR_PLUGIN_DIR_URL );
        $star = sprintf( '<img src="%sdist/images/svg-star.svg" alt="" />', $path );
        $star_output = '<span class="grwp_stars-wrapper">';
        if ( $this->rating_rounded === 0 ) {
            $this->rating_rounded = 5;
        }
        for ($i = 1; $i <= $this->rating_rounded; $i++) {
            $star_output .= $star;
        }
        $star_output .= '</span>';
        return $star_output;
    }

    /**
     * Map the numeric rating to a human readable label (Trustpilot style),
     * used by the compact company header.
     * @return string
     */
    protected function get_rating_label() {
        $rating = $this->rating_value;
        if ( $rating >= 4.5 ) {
            return grwp_text( 'excellent', __( 'Excellent', 'embedder-for-google-reviews' ) );
        }
        if ( $rating >= 3.5 ) {
            return grwp_text( 'very_good', __( 'Very good', 'embedder-for-google-reviews' ) );
        }
        if ( $rating >= 2.5 ) {
            return grwp_text( 'average', __( 'Average', 'embedder-for-google-reviews' ) );
        }
        if ( $rating >= 1.5 ) {
            return grwp_text( 'poor', __( 'Poor', 'embedder-for-google-reviews' ) );
        }
        return grwp_text( 'bad', __( 'Bad', 'embedder-for-google-reviews' ) );
    }

    /**
     * Render the company header for the slider/grid widgets, honouring the
     * selected header type (Header Settings tab). Returns an empty string when
     * the header should not be shown.
     *
     * @param bool   $show_place_info Whether the widget requested the header.
     * @param bool   $show_verified   Whether to render the 'Verified by' badge.
     * @param string $txt             Alt text for the verified badge image.
     * @param string $default_title   Placeholder business title fallback.
     * @return string
     */
    protected function render_company_header(
        $show_place_info,
        $show_verified,
        $txt = '',
        $default_title = 'Lorem Ipsum Business',
        $is_slider = false
    ) {
        $header_type = $this->effective_header_type( $show_place_info );
        if ( $header_type === '' ) {
            return '';
        }
        $this->place_title = ( $this->place_title === '' ? $default_title : $this->place_title );
        if ( $header_type === 'compact' || $header_type === 'compact_plain' ) {
            return $this->get_compact_header(
                $is_slider,
                $header_type === 'compact_plain',
                $show_verified,
                $txt
            );
        }
        return $this->get_standard_header( $show_verified, $txt );
    }

    /**
     * The header type that will actually render for a widget, accounting for the
     * place_info gate and the "none → default" override when a shortcode
     * explicitly requests the header. Returns '' when no header is shown.
     *
     * @param bool $show_place_info
     * @return string '', 'standard', 'compact' or 'compact_plain'.
     */
    protected function effective_header_type( $show_place_info ) {
        if ( !$show_place_info ) {
            return '';
        }
        $header_type = $this->resolve_header_type();
        // 'none' hides the header entirely, even for shortcodes that still carry
        // an explicit place_info="true" attribute.
        if ( $header_type === 'none' ) {
            return '';
        }
        return $header_type;
    }

    /**
     * Resolve the effective header type. Available to free and premium users
     * alike; the front-end simply renders the saved value, falling back to the
     * version-aware default when none is set.
     *
     * @return string One of 'standard', 'compact', 'compact_plain', 'none'.
     */
    protected function resolve_header_type() {
        return grwp_resolve_header_type( $this->options );
    }

    /**
     * Whether the compact header is active and shown for this widget. When true
     * the standalone "See all reviews" button is moved into the header, so the
     * widget should not also render it at the bottom.
     *
     * @param bool $show_place_info
     * @return bool
     */
    protected function compact_header_active( $show_place_info ) {
        return in_array( $this->effective_header_type( $show_place_info ), array('compact', 'compact_plain'), true );
    }

    /**
     * Standard company header ("Overall rating out of X Google reviews").
     * @return string
     */
    protected function get_standard_header( $show_verified, $txt ) {
        $stars = $this->get_total_stars();
        // "Out of 5 stars": the Translation override uses a {{n}} placeholder for
        // the maximum rating, the built-in default is a translatable string.
        $out_of_override = grwp_text( 'out_of_stars', '' );
        if ( $out_of_override !== '' ) {
            $out_of_text = str_replace( '{{n}}', 5, $out_of_override );
        } else {
            /* translators: out of 5 stars */
            $out_of_text = __( 'Out of 5 stars', 'embedder-for-google-reviews' );
        }
        $output = '<div class="grwp_header">';
        $output .= '<div class="grwp_header-inner">';
        $output .= sprintf( '<h3 class="grwp_business-title">%s</h3>', grwp_text( 'company_name', $this->place_title ) );
        $output .= sprintf( '<span class="grwp_total-rating">%s</span><span class="grwp_5_stars">%s</span>', $this->rating_formatted, $out_of_text );
        $output .= $stars;
        // Overall rating line: the Translation override uses a {{n}} placeholder
        // for the review count, the built-in default is a translatable pattern.
        $overall_override = grwp_text( 'overall_rating', '' );
        if ( $overall_override !== '' ) {
            $overall = str_replace( '{{n}}', $this->total_reviews, $overall_override );
        } else {
            $overall = sprintf( 
                /* translators: %s: total reviews */
                __( 'Overall rating out of %s Google reviews', 'embedder-for-google-reviews' ),
                $this->total_reviews
             );
        }
        // Keep the badge inside the heading so it sits inline after the text and
        // only wraps to the next line when there isn't enough width.
        if ( $show_verified ) {
            $overall .= ' ' . $this->get_verified_badge( $txt );
        }
        $output .= '<h3 class="grwp_overall">' . $overall . '</h3>';
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Shared "Verified by" badge: a blue check (as in design 10) that reveals a
     * tooltip with the label and plugin icon on hover/focus, linking to
     * reviewsembedder.com. Used by both the standard and compact headers.
     * @return string
     */
    protected function get_verified_badge( $txt = '' ) {
        $verified_svg = GR_PLUGIN_DIR_URL . 'dist/images/verified-badge.svg';
        /* translators: 'Verified by' badge */
        $verified_label = grwp_text( 'verified_by', __( 'Verified by', 'embedder-for-google-reviews' ) );
        return sprintf(
            '<span class="grwp_verified-badge"><a href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s"><span class="grwp_verified-badge-check"></span><span class="grwp_verified-badge-tip">%3$s <img class="grwp_verified-badge-tip-icon" src="%4$s" alt="%5$s" /></span></a></span>',
            'https://reviewsembedder.com',
            esc_attr( $verified_label ),
            esc_html( $verified_label ),
            esc_url( $verified_svg ),
            esc_attr( $txt )
        );
    }

    /**
     * Compact company header (Design 1): Google logo, rating label, stars,
     * rating, review count and an optional "Write a review" button.
     * @return string
     */
    protected function get_compact_header(
        $is_slider = false,
        $plain = false,
        $show_verified = false,
        $txt = ''
    ) {
        $logo = GR_PLUGIN_DIR_URL . 'dist/images/google-logo-svg.svg';
        $stars = $this->get_total_stars();
        $label = $this->get_rating_label();
        // Slider cards are inset from the container edge by their per-slide
        // margins, so the slider header gets matching side margins to align.
        $slider_class = ( $is_slider ? ' grwp_header--slider' : '' );
        // Plain variant: drop the bar background, border and shadow.
        $plain_class = ( $plain ? ' grwp_compact--plain' : '' );
        $output = '<div class="grwp_header grwp_header--compact' . $slider_class . '">';
        $output .= '<div class="grwp_compact' . $plain_class . '">';
        $output .= '<div class="grwp_compact-info">';
        $output .= sprintf( '<img class="grwp_compact-logo" src="%s" alt="Google" width="22" height="22" />', esc_url( $logo ) );
        $output .= sprintf( '<span class="grwp_compact-label">%s</span>', esc_html( $label ) );
        $output .= sprintf( '<span class="grwp_compact-stars">%s</span>', $stars );
        $output .= sprintf( '<span class="grwp_compact-rating">%s</span>', esc_html( $this->rating_formatted ) );
        // Review count: the Translation override uses a {{n}} placeholder for
        // the number, the built-in default is a translatable sprintf pattern.
        $count_override = grwp_text( 'n_reviews', '' );
        if ( $count_override !== '' ) {
            $count_text = str_replace( '{{n}}', $this->total_reviews, $count_override );
        } else {
            $count_text = sprintf( 
                /* translators: %s: total number of reviews */
                __( '%s reviews', 'embedder-for-google-reviews' ),
                $this->total_reviews
             );
        }
        $output .= sprintf( '<span class="grwp_compact-count">%s</span>', esc_html( $count_text ) );
        if ( $show_verified ) {
            $output .= $this->get_verified_badge( $txt );
        }
        $output .= '</div>';
        $button_url = $this->get_button_url();
        if ( $button_url !== '' ) {
            $output .= sprintf( '<a class="grwp_compact-write" href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $button_url ), esc_html( $this->get_button_text() ) );
        }
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Resolve the effective button link target (Display Settings → "Button").
     * Installs saved before this setting existed are migrated on read: a
     * configured custom URL maps to 'custom', otherwise no button is shown.
     *
     * @return string One of 'reviews_google', 'write_review', 'custom', 'none'.
     */
    protected function get_button_type() {
        if ( !empty( $this->options['button_type'] ) ) {
            return $this->options['button_type'];
        }
        return ( !empty( $this->options['button_url'] ) ? 'custom' : 'write_review' );
    }

    /**
     * Resolve the button URL for the selected link target. The Google modes
     * build the URL from the saved place_id and return '' when it is missing
     * (hide-if-empty), so a broken link is never rendered.
     *
     * @return string
     */
    protected function get_button_url() {
        $place_id = ( !empty( $this->options['serp_place_id'] ) ? $this->options['serp_place_id'] : '' );
        switch ( $this->get_button_type() ) {
            case 'reviews_google':
                return ( $place_id !== '' ? 'https://search.google.com/local/reviews?placeid=' . rawurlencode( $place_id ) : '' );
            case 'write_review':
                return ( $place_id !== '' ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $place_id ) : '' );
            case 'custom':
                return ( !empty( $this->options['button_url'] ) ? $this->options['button_url'] : '' );
            case 'none':
            default:
                return '';
        }
    }

    /**
     * Text for the button. The custom text only applies in "Custom URL & text"
     * mode; the Google link modes use their own (translation-overridable)
     * default so a stale custom text can't leak onto them.
     * @return string
     */
    protected function get_button_text() {
        switch ( $this->get_button_type() ) {
            case 'write_review':
                return grwp_text( 'write_a_review', __( 'Write a review', 'embedder-for-google-reviews' ) );
            case 'reviews_google':
                return grwp_text( 'view_on_google', __( 'View on Google', 'embedder-for-google-reviews' ) );
            case 'custom':
            default:
                // Custom mode: the user's custom text wins; otherwise fall back
                // to the standard (overridable) default.
                if ( !empty( $this->options['button_text'] ) ) {
                    return $this->options['button_text'];
                }
                return __( 'See all Reviews', 'embedder-for-google-reviews' );
        }
    }

    /**
     * Get button output, linking to the resolved button URL, if any
     * @return string
     */
    protected function get_button_output() {
        $button_url = $this->get_button_url();
        if ( $button_url === '' ) {
            return '';
        }
        return sprintf( '<div class="grwp_button-wrapper"><a class="grwp_button" href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>', esc_url( $button_url ), esc_html( $this->get_button_text() ) );
    }

    /**
     * Prepare time string for reviews
     * @param $datetime
     * @param $full
     * @return string|null
     * @throws Exception
     */
    protected function time_elapsed_string( $datetime, $full = false ) {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff( $ago );
        $weeks = floor( $diff->d / 7 );
        $diff->d -= $weeks * 7;
        $string = array(
            'y' => array(__( 'year', 'embedder-for-google-reviews' ), __( 'years', 'embedder-for-google-reviews' )),
            'm' => array(__( 'month', 'embedder-for-google-reviews' ), __( 'months', 'embedder-for-google-reviews' )),
            'w' => array(__( 'week', 'embedder-for-google-reviews' ), __( 'weeks', 'embedder-for-google-reviews' )),
            'd' => array(__( 'day', 'embedder-for-google-reviews' ), __( 'days', 'embedder-for-google-reviews' )),
            'h' => array(__( 'hour', 'embedder-for-google-reviews' ), __( 'hours', 'embedder-for-google-reviews' )),
            'i' => array(__( 'minute', 'embedder-for-google-reviews' ), __( 'minutes', 'embedder-for-google-reviews' )),
            's' => array(__( 'second', 'embedder-for-google-reviews' ), __( 'seconds', 'embedder-for-google-reviews' )),
        );
        if ( $weeks ) {
            $string['w'] = $weeks . ' ' . (( $weeks > 1 ? __( 'weeks', 'embedder-for-google-reviews' ) : __( 'week', 'embedder-for-google-reviews' ) ));
        }
        foreach ( $string as $k => &$v ) {
            if ( $k != 'w' && $diff->{$k} ) {
                $v = $diff->{$k} . ' ' . (( $diff->{$k} > 1 ? $v[1] : $v[0] ));
            } elseif ( $k != 'w' ) {
                unset($string[$k]);
            }
        }
        if ( !$full ) {
            $string = array_slice( $string, 0, 1 );
        }
        // standard time string for English
        $time_string = ( $string ? implode( ', ', $string ) . __( ' ago', 'embedder-for-google-reviews' ) : __( 'just now', 'embedder-for-google-reviews' ) );
        // reverse string arrangement for non English sites
        $language_code = get_locale();
        if ( substr( $language_code, 0, 3 ) !== 'en_' ) {
            $time_string = ( $string ? __( ' ago', 'embedder-for-google-reviews' ) . implode( ', ', $string ) : __( 'just now', 'embedder-for-google-reviews' ) );
        }
        // allow filtering for edge cases
        $time_string = apply_filters(
            'grwp_filter_time_string',
            $time_string,
            $string,
            $diff
        );
        return $time_string;
    }

    /**
     * Count and prepare stars
     * @param $review
     * @return string
     */
    protected function get_star_output( $review ) {
        $path = esc_attr( GR_PLUGIN_DIR_URL );
        $star = sprintf( '<img src="%sdist/images/svg-star.svg" alt="" />', $path );
        $star_output = '<span class="stars-wrapper">';
        for ($i = 1; $i <= $review['rating']; $i++) {
            $star_output .= $star;
        }
        $star_output .= '</span>';
        $star_output .= sprintf( '<span class="time">%s</span>', $review['time'] );
        return $star_output;
    }

    /**
     * Get dummy review content
     * @return array[]
     */
    protected function get_dummy_content() {
        $reviews = array(array(
            'author_name'               => __( 'Lorem Ipsum', 'embedder-for-google-reviews' ),
            'author_url'                => '#',
            'language'                  => 'en',
            'profile_photo_url'         => GR_PLUGIN_DIR_URL . 'dist/images/sample-photo.png',
            'rating'                    => 5,
            'relative_time_description' => __( 'three months ago', 'embedder-for-google-reviews' ),
            'text'                      => __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.', 'embedder-for-google-reviews' ),
            'time'                      => '1643630205',
        ));
        for ($i = 0; $i <= 4; $i++) {
            $reviews[] = $reviews[0];
        }
        return $reviews;
    }

    /**
     * Check if review should be left out, due to global settings
     * @param $rating
     * @param $text
     * @return bool
     */
    protected function step_over_review( $rating, $text ) {
        if ( !grwp_fs()->can_use_premium_code() ) {
            return false;
        }
        // step over rating if minimum stars are not met
        if ( isset( $this->options['filter_below_5_stars'] ) && $this->options['filter_below_5_stars'] ) {
            $min_rating = intval( $this->options['filter_below_5_stars'] );
            if ( $rating < $min_rating ) {
                return true;
            }
        }
        // step over rating if review has no text
        if ( isset( $this->options['exclude_reviews_without_text'] ) && $this->options['exclude_reviews_without_text'] ) {
            if ( $text == '' ) {
                return true;
            }
        }
        // step over rating if review contains certain words
        if ( isset( $this->options['filter_words'] ) && $this->options['filter_words'] !== '' ) {
            $words_str = rtrim( $this->options['filter_words'], ',' );
            $words_arr = explode( ',', $words_str );
            foreach ( $words_arr as $word ) {
                $word = trim( $word );
                if ( str_contains( $text, $word ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Prepare and map review data
     * @param $reviews_raw
     * @return array
     */
    protected function map_review_data( $reviews_raw, $use_new_api_results ) {
        // map reviews from different raw data to universal format
        $reviews = [];
        foreach ( $reviews_raw as $review ) {
            // assign dummy content
            if ( $this->showdummy ) {
                $name = $review['author_name'];
                $author_url = $review['author_url'];
                $profile_photo_url = $review['profile_photo_url'];
                $rating = $review['rating'];
                $text = $review['text'];
                $time = $this->time_elapsed_string( gmdate( 'Y-m-d h:i:s', $review['time'] ) );
            } else {
                // use different array keys for pro version results
                if ( grwp_fs()->is__premium_only() || $use_new_api_results ) {
                    $name = ( isset( $review['user']['name'] ) ? $review['user']['name'] : '' );
                    $author_url = ( isset( $review['user']['link'] ) ? $review['user']['link'] : '' );
                    $profile_photo_url = ( isset( $review['user']['thumbnail'] ) ? $review['user']['thumbnail'] : '' );
                    $rating = ( isset( $review['rating'] ) ? $review['rating'] : 5 );
                    $text = ( isset( $review['snippet'] ) ? $review['snippet'] : '' );
                    $time = ( isset( $review['date'] ) ? $review['date'] : '' );
                } else {
                    $name = $review['author_name'];
                    $author_url = $review['author_url'];
                    $profile_photo_url = $review['profile_photo_url'];
                    $rating = $review['rating'];
                    $text = $review['text'];
                    $time = $this->time_elapsed_string( gmdate( 'Y-m-d h:i:s', $review['time'] ) );
                }
            }
            // check if rating should be left out, due to global settings
            if ( $this->step_over_review( $rating, $text ) ) {
                continue;
            }
            $reviews[] = [
                'name'              => $name,
                'author_url'        => $author_url,
                'profile_photo_url' => $profile_photo_url,
                'rating'            => $rating,
                'text'              => $text,
                'time'              => $time,
            ];
        }
        return $reviews;
    }

    /**
     * Avoid duplication of reviews by filtering for duplicate name entries
     * @return void
     */
    protected function filter_unique_reviews( $reviews ) {
        $unique_names = [];
        $filtered_reviews = [];
        foreach ( $reviews as $review ) {
            $name = $review['user']['name'];
            if ( !isset( $unique_names[$name] ) ) {
                $unique_names[$name] = true;
                $filtered_reviews[] = $review;
            }
        }
        return $filtered_reviews;
    }

    /**
     * Get raw review data from database
     * @return void|array
     */
    protected function get_review_data() {
        $use_new_api_results = false;
        // if dummy setting is active, get dummy content
        if ( $this->showdummy ) {
            $reviews_raw = $this->get_dummy_content();
        } else {
            // get reviews data from prior versions, if possible
            $reviews_raw = GRWP_Free_API_Service::parse_free_review_json();
            // if no old reviews data, get new reviews data
            if ( count( $reviews_raw ) === 0 ) {
                $reviews_raw = GRWP_Pro_API_Service::parse_pro_review_json();
                $use_new_api_results = true;
            }
        }
        if ( !empty( $reviews_raw ) && !$this->showdummy ) {
            $reviews_raw = $this->filter_unique_reviews( $reviews_raw );
        }
        return $this->map_review_data( $reviews_raw, $use_new_api_results );
    }

}
