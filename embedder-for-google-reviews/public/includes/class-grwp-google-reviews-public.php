<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       test
 * @since      1.0.0
 *
 * @package    Google_Reviews
 * @subpackage Google_Reviews/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Google_Reviews
 * @subpackage Google_Reviews/public
 * @author     David Maucher <hallo@maucher-online.com>
 */
class GRWP_Google_Reviews_Public {

	private $plugin_name;

	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		if ( is_admin() && ! $this->is_plugin_admin_page() ) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in GRWP_Google_Reviews_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The GRWP_Google_Reviews_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

        wp_enqueue_style( $this->plugin_name, GR_PLUGIN_DIR_URL . 'dist/css/google-reviews-public.css', array(), $this->asset_version( 'dist/css/google-reviews-public.css' ), 'all' );

        $options = get_option( 'google_reviews_option_name' );
        if ( ! empty( $options['hide_profile_picture'] ) ) {
            wp_add_inline_style( $this->plugin_name, '.gr-profile { display: none !important; }' );
        }
        if ( ! empty( $options['disable_box_shadow'] ) ) {
            wp_add_inline_style( $this->plugin_name, '#g-review[class*="layout_style"] .g-review, #g-review[class*="layout_style"] .grwp_header--compact .grwp_compact { box-shadow: none !important; }' );
        }
        if ( ! empty( $options['hide_rating_text'] ) ) {
            wp_add_inline_style( $this->plugin_name, '.gr-inner-body { display: none !important; }' );
        }
        if ( ! empty( $options['use_safe_fallback_font'] ) ) {
            wp_add_inline_style( $this->plugin_name, '#g-review { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important; }' );
        }

        // Custom review-text height. The .grwp-expanded exception must be emitted
        // alongside it (higher specificity) so the "Read more" expansion still wins.
        if ( ! empty( $options['content_max_height'] ) && intval( $options['content_max_height'] ) > 0 ) {
            $max_height = intval( $options['content_max_height'] );
            wp_add_inline_style(
                $this->plugin_name,
                '#g-review[class*="layout_style"] .g-review .gr-inner-body { max-height: ' . $max_height . 'px !important; }'
                . ' #g-review[class*="layout_style"] .g-review .gr-inner-body.grwp-expanded { max-height: none !important; }'
            );
        }

        // "Read more" mode: hide the scrollbar — overflowing text is revealed
        // by the button injected in read-more.js instead.
        if ( isset( $options['content_overflow'] ) && $options['content_overflow'] === 'read_more' ) {
            wp_add_inline_style(
                $this->plugin_name,
                '#g-review[class*="layout_style"] .g-review .gr-inner-body { overflow: hidden !important; }'
                . ' #g-review[class*="layout_style"] .g-review .gr-inner-body.grwp-expanded { overflow: visible !important; }'
            );
        }
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		if ( is_admin() && ! $this->is_plugin_admin_page() ) {
			return;
		}

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in GRWP_Google_Reviews_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The GRWP_Google_Reviews_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

        wp_enqueue_script( $this->plugin_name, GR_PLUGIN_DIR_URL . 'dist/js/public-bundle.js', array( 'jquery' ), $this->asset_version( 'dist/js/public-bundle.js' ), true );

        $options = get_option( 'google_reviews_option_name' );
        $slider_delay = isset($options['slide_duration']) ? intval($options['slide_duration']) : 0;
        $disable_slider_loop = isset($options['disable_loop_slider']) && $options['disable_loop_slider'] == '1' ? $options['disable_loop_slider'] : false;
        $marquee_slider = isset($options['marquee_slider']) && $options['marquee_slider'] == '1';
        $pause_on_hover = isset($options['pause_on_hover']) && $options['pause_on_hover'] == '1';
        // Map the 1-10 "speed" setting (higher = faster) to a Swiper transition
        // duration in ms (lower = faster): 1 => 10000ms, 10 => 1000ms.
        $marquee_level = isset($options['marquee_speed']) ? intval($options['marquee_speed']) : 5;
        $marquee_level = min( 10, max( 1, $marquee_level ) );
        $marquee_speed = ( 11 - $marquee_level ) * 1000;
        $show_more_default = isset($options['show_more_grid_text']) && $options['show_more_grid_text'] !== ''
            ? $options['show_more_grid_text']
            : __( 'Show more', 'embedder-for-google-reviews' );
        // A Translation-page override beats the Grid Settings text.
        $show_more_text = grwp_text( 'show_more', $show_more_default );

        $content_overflow = isset($options['content_overflow']) && $options['content_overflow'] === 'read_more'
            ? 'read_more'
            : 'scrollbar';

        $swiper_data = array(
            'disableLoop'     => $disable_slider_loop,
            'autoplayDelay'   => $slider_delay,
            'marquee'         => $marquee_slider,
            'marqueeSpeed'    => $marquee_speed,
            'pauseOnHover'    => $pause_on_hover,
            'showMoreText'    => $show_more_text,
            'contentOverflow' => $content_overflow,
            'readMoreText'    => grwp_text( 'read_more', __( 'Read more', 'embedder-for-google-reviews' ) ),
            'readLessText'    => grwp_text( 'read_less', __( 'Read less', 'embedder-for-google-reviews' ) ),
        );
        wp_localize_script( $this->plugin_name, 'swiperSettings', $swiper_data );
	}

	/**
	 * Cache-busting version for a built asset.
	 *
	 * Uses the file's modification time so a rebuilt bundle/stylesheet always
	 * gets a fresh URL (browsers won't serve a stale cached copy). Falls back
	 * to the plugin version if the file can't be found.
	 *
	 * @param string $relative_path Path under the plugin root, e.g. 'dist/js/public-bundle.js'.
	 * @return string|int
	 */
	private function asset_version( $relative_path ) {
		$file = GR_BASE_PATH . $relative_path;
		return file_exists( $file ) ? filemtime( $file ) : $this->version;
	}

	/**
	 * Check whether the current admin request belongs to this plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_admin_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return (
			'google-reviews' === $page ||
			'how-to-free-version' === $page ||
			'how-to-premium-version' === $page
		);
	}

}
