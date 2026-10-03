<?php

namespace QuadLayers\IGG\Controllers;

use QuadLayers\IGG\Controllers\Elementor_Widget;

/**
 * Elementor_Integration Class
 */
class Elementor {

	protected static $instance;

	private function __construct() {

		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'fix_loop_widget' ), 999 );

		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/editor/after_enqueue_scripts', array( 'QuadLayers\IGG\Controllers\Backend', 'add_premium_styles' ), 10 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( 'QuadLayers\IGG\Controllers\Backend', 'register_scripts' ), 10 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'register_scripts' ), 10 );
		add_action( 'elementor/editor/footer', array( __CLASS__, 'add_premium_js' ), 10 );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	public function fix_loop_widget() {

		wp_add_inline_style(
			'widget-loop-carousel',
			'
			.elementor-widget-loop-carousel.elementor-widget-loop-carousel .swiper-pagination-bullets {
				height: max-content;
				left: calc(var(--dots-horizontal-position) + var(--dots-horizontal-offset));
				top: calc(var(--dots-vertical-position) + var(--dots-vertical-offset));
				transform: translate(
					calc(var(--dots-horizontal-transform) * var(--horizontal-transform-modifier)),
					var(--dots-vertical-transform)
				);
				width: max-content;
			}
		'
		);
	}

	/**
	 * Register script dependencies for Elementor
	 */
	public function register_scripts() {

		$elementor = include QLIGG_PLUGIN_DIR . 'build/elementor/js/index.asset.php';

		wp_enqueue_script(
			'qligg-elementor-widget',
			plugins_url( '/build/elementor/js/index.js', QLIGG_PLUGIN_FILE ),
			$elementor['dependencies'],
			$elementor['version'],
			true
		);

		wp_localize_script(
			'qligg-elementor-widget',
			'qligg_elementor_widget',
			array(
				'i18n' => array(
					'headerMessage' => __( 'Premium Feature', 'insta-gallery' ),
					'message'       => __( 'This option is available only in the Premium version. Unlock it now at QuadLayers.', 'insta-gallery' ),
					'confirm'       => __( 'Confirm', 'insta-gallery' ),
					'cancel'        => __( 'Cancel', 'insta-gallery' ),
				),
				'url'  => 'https://quadlayers.com/products/instagram-feed-gallery/?utm_source=qligg_plugin&utm_medium=admin_elementor&utm_campaign=premium_upgrade&utm_content=premium_link',
			)
		);
	}

	/**
	 * Register Elementor widgets
	 */
	public function register_widgets( $widgets_manager ) {
		$widgets_manager->register( new Elementor_Widget() );
	}

	public static function add_premium_js() {
		?>
			<script>
				var QLIGG_IS_PREMIUM = false;
			</script>
		<?php
	}

	/**
	 * Return class instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
