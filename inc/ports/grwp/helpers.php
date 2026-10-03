<?php
/* Embedder for Google Reviews 2.1.1: the helper functions from google-reviews.php
   (lines 78-231), unchanged. Loaded by inc/ports/google-reviews.php. */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( !function_exists( 'grwp_text' ) ) {
    /**
     * Default header type for installs that have not explicitly chosen one.
     *
     * New installs (first activated on v2.0 or later) default to the
     * "Compact – plain" header. Installs that predate v2.0 keep the legacy
     * "Standard" header, since they never consented to the new look. The
     * first-activation version is stored once and preserved across updates.
     *
     * @return string 'compact_plain' or 'standard'.
     */
    function grwp_default_header_type() {
        $activation_version = get_option( 'grwp_activation_version' );
        if ( $activation_version && version_compare( $activation_version, '2.0', '>=' ) ) {
            return 'compact_plain';
        }
        return 'standard';
    }

    /**
     * Resolve the effective header type for a saved options array.
     *
     * The legacy "Hide company header section" checkbox has been folded into the
     * "None" header type: whenever that flag is set the header type reads as
     * 'none', so old installs that hid the header keep hiding it and the
     * Header type dropdown reflects their previous choice.
     *
     * @param array $options google_reviews_option_name values.
     * @return string One of 'standard', 'compact', 'compact_plain', 'none'.
     */
    function grwp_resolve_header_type(  $options  ) {
        if ( !empty( $options['hide_company_header'] ) ) {
            return 'none';
        }
        return ( isset( $options['header_type'] ) && $options['header_type'] !== '' ? $options['header_type'] : grwp_default_header_type() );
    }

    /**
     * Default slider arrow position for installs that have not explicitly
     * chosen one.
     *
     * New installs (first activated on v2.1 or later) default to "Middle"
     * (arrows over the slides). Installs that predate v2.1 keep the legacy
     * "Below" placement so their sliders don't shift on update. The
     * first-activation version is stored once and preserved across updates.
     *
     * @return string 'middle' or 'below'.
     */
    function grwp_default_arrows_position() {
        $activation_version = get_option( 'grwp_activation_version' );
        if ( $activation_version && version_compare( $activation_version, '2.1', '>=' ) ) {
            return 'middle';
        }
        return 'below';
    }

    /**
     * Front-end strings the user can override on the Translation subpage,
     * regardless of the active locale. Keyed by the option key used in the
     * 'grwp_string_overrides' option.
     *
     * @return array key => array( 'label' => admin label, 'default' => runtime default, 'description' => optional hint )
     */
    function grwp_translatable_strings() {
        return array(
            'write_a_review'     => array(
                'label'   => __( 'Write a review', 'embedder-for-google-reviews' ),
                'default' => __( 'Write a review', 'embedder-for-google-reviews' ),
            ),
            'excellent'          => array(
                'label'   => __( 'Excellent', 'embedder-for-google-reviews' ),
                'default' => __( 'Excellent', 'embedder-for-google-reviews' ),
            ),
            'very_good'          => array(
                'label'   => __( 'Very good', 'embedder-for-google-reviews' ),
                'default' => __( 'Very good', 'embedder-for-google-reviews' ),
            ),
            'average'            => array(
                'label'   => __( 'Average', 'embedder-for-google-reviews' ),
                'default' => __( 'Average', 'embedder-for-google-reviews' ),
            ),
            'poor'               => array(
                'label'   => __( 'Poor', 'embedder-for-google-reviews' ),
                'default' => __( 'Poor', 'embedder-for-google-reviews' ),
            ),
            'bad'                => array(
                'label'   => __( 'Bad', 'embedder-for-google-reviews' ),
                'default' => __( 'Bad', 'embedder-for-google-reviews' ),
            ),
            'n_reviews'          => array(
                'label'       => '{{n}} reviews',
                'default'     => __( '%s reviews', 'embedder-for-google-reviews' ),
                'description' => __( '{{n}} is replaced with the number of reviews.', 'embedder-for-google-reviews' ),
            ),
            'verified_by'        => array(
                'label'   => __( 'Verified by', 'embedder-for-google-reviews' ),
                'default' => __( 'Verified by', 'embedder-for-google-reviews' ),
            ),
            'show_more'          => array(
                'label'       => __( 'Show more', 'embedder-for-google-reviews' ),
                'default'     => __( 'Show more', 'embedder-for-google-reviews' ),
                'description' => __( 'Shown on the grid "Load more" button.', 'embedder-for-google-reviews' ),
            ),
            'read_more'          => array(
                'label'   => __( 'Read more', 'embedder-for-google-reviews' ),
                'default' => __( 'Read more', 'embedder-for-google-reviews' ),
            ),
            'read_less'          => array(
                'label'   => __( 'Read less', 'embedder-for-google-reviews' ),
                'default' => __( 'Read less', 'embedder-for-google-reviews' ),
            ),
            'view_on_google'     => array(
                'label'       => __( 'View on Google', 'embedder-for-google-reviews' ),
                'default'     => __( 'View on Google', 'embedder-for-google-reviews' ),
                'description' => __( 'Shown on the button that links to the Google reviews page.', 'embedder-for-google-reviews' ),
            ),
            'our_google_reviews' => array(
                'label'       => __( 'Our Google Reviews', 'embedder-for-google-reviews' ),
                'default'     => __( 'Our Google Reviews', 'embedder-for-google-reviews' ),
                'description' => __( 'Shown on the floating badge label.', 'embedder-for-google-reviews' ),
            ),
            'overall_rating'     => array(
                'label'       => 'Overall rating out of {{n}} Google reviews',
                'default'     => __( 'Overall rating out of %s Google reviews', 'embedder-for-google-reviews' ),
                'description' => __( '{{n}} is replaced with the number of reviews. Shown in the standard header.', 'embedder-for-google-reviews' ),
            ),
            'out_of_stars'       => array(
                'label'       => 'Out of {{n}} stars',
                'default'     => __( 'Out of 5 stars', 'embedder-for-google-reviews' ),
                'description' => __( '{{n}} is replaced with the maximum rating (5). Shown in the standard header.', 'embedder-for-google-reviews' ),
            ),
            'company_name'       => array(
                'label'       => __( 'Company name', 'embedder-for-google-reviews' ),
                'default'     => '',
                'description' => __( 'Overrides the business name shown in the header and the badge flyout bar. Leave empty to use the name from Google.', 'embedder-for-google-reviews' ),
            ),
        );
    }

    /**
     * Resolve a user-facing string, preferring the user's override from the
     * Translation subpage over the (possibly translated) default.
     *
     * @param string $key     Key from grwp_translatable_strings().
     * @param string $default Fallback when no override is saved.
     * @return string
     */
    function grwp_text(  $key, $default  ) {
        $overrides = get_option( 'grwp_string_overrides' );
        if ( is_array( $overrides ) && isset( $overrides[$key] ) && trim( $overrides[$key] ) !== '' ) {
            return $overrides[$key];
        }
        return $default;
    }

}
