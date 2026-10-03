<?php
namespace DynamicVisibilityForElementor;

use DynamicVisibilityForElementor\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

/**
 * Main Plugin Class
 *
 * @since 0.0.1
 */
class Plugin {

	/**
	 * @var \DynamicVisibilityForElementor\Controls
	 */
	public $controls;
	/**
	 * @var \DynamicVisibilityForElementor\Wpml
	 */
	public $wpml;
	/**
	 * @var \DynamicVisibilityForElementor\AdminPages\Notices
	 */
	public $notices;
	/**
	 * @var \DynamicVisibilityForElementor\Modules\QueryControl\Module
	 */
	public $query_control;

	protected static $instance;

	public function __construct() {
		self::$instance = $this;

		$this->wpml = new Wpml();
		$this->controls = new Controls();
		$this->notices = new AdminPages\Notices();
		$this->query_control = new \DynamicVisibilityForElementor\Modules\QueryControl\Module();

		add_action( 'init', function () {
			load_plugin_textdomain( 'dynamic-visibility-for-elementor' );

			// Promo
			if ( current_user_can( 'install_plugins' ) ) {
				$this->get_promo_notice_dashboard();
			}

			// Plugin page links (must be after load_plugin_textdomain)
			add_filter('plugin_action_links_' . DVE_PLUGIN_BASE, [
				$this,
				'add_action_links',
			]);
			add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		} );

		add_action( 'elementor/init', [ $this, 'add_dve_to_elementor' ] );

		// Admin Style
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
	}

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			new self();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function add_dve_to_elementor() {
		add_action('elementor/frontend/after_register_styles', function () {
			wp_register_style(
				'dce-dynamic-visibility-style',
				DVE_URL . 'assets/css/dynamic-visibility.css',
				[],
				DVE_VERSION
			);
			// Enqueue Visibility Style
			wp_enqueue_style( 'dce-dynamic-visibility-style' );
		});

		add_action('wp_enqueue_scripts', function () {
			wp_register_script(
				'dce-visibility',
				DVE_URL . 'assets/js/visibility.js',
				[ 'jquery' ],
				DVE_VERSION,
				true
			);
		});

		// DCE Custom Icons - in Elementor Editor
		add_action('elementor/preview/enqueue_styles', function () {
			wp_register_style(
				'dce-preview',
				DVE_URL . 'assets/css/preview.css',
				[],
				DVE_VERSION
			);
			// Enqueue DCE Elementor Style
			wp_enqueue_style( 'dce-preview' );
		});

		add_action('elementor/editor/after_enqueue_scripts', [
			$this,
			'dce_editor',
		]);

		// Controls
		add_action('elementor/controls/controls_registered', [
			$this->controls,
			'on_controls_registered',
		]);

		$visibility_extension = new Extensions\DynamicVisibility\Manager();
		if ( method_exists( $visibility_extension, 'run_once' ) ) {
			$visibility_extension->run_once();
		}
	}

	/**
	 * @return void
	 */
	public function enqueue_admin() {
		// Style
		wp_register_style(
			'dve-admin-css',
			DVE_URL . 'assets/css/admin.css',
			[],
			DVE_VERSION
		);
		wp_enqueue_style( 'dve-admin-css' );

		// Scripts
		wp_enqueue_script(
			'dve-admin-js',
			DVE_URL . 'assets/js/admin.js',
			[],
			DVE_VERSION,
			true
		);
	}

	/**
	 * @return bool
	 */
	private function is_early_access_period() {
		$current_date = current_time( 'Y-m-d' );
		$early_end = '2025-11-16';
		
		return ( $current_date <= $early_end );
	}

	/**
	 * @return bool
	 */
	private function is_black_friday_period() {
		$current_date = current_time( 'Y-m-d' );
		$bf_start = '2025-11-17';
		$bf_end = '2025-12-01';
		
		return ( $current_date >= $bf_start && $current_date <= $bf_end );
	}

	/**
	 * @return void
	 */
	public function get_promo_notice_dashboard() {
		if ( $this->is_early_access_period() ) {
			$this->get_early_access_notice();
			return;
		}

		if ( $this->is_black_friday_period() ) {
			$this->get_black_friday_notice();
			return;
		}

		$msg = sprintf(
			__(
				'%1$sBuy now Dynamic.ooo - Dynamic Content for Elementor%2$s and save 10&#37; using promo code %3$sVISIBILITY%4$s.',
				'dynamic-visibility-for-elementor'
			) . '<br />',
			'<a target="_blank" href="https://www.dynamic.ooo/upgrade/visibility-to-premium?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash-promo">',
			'</a>',
			'<strong>',
			'</strong>'
		);
		$msg .= sprintf(
			__(
				'We give you %1$sover 150 features for Elementor%2$s that will save you time and money on achieving complex results. We support ACF Free and ACF Pro, JetEngine, Meta Box, WooCommerce, WPML, Search and Filter Pro, Pods and Toolset.',
				'dynamic-visibility-for-elementor'
			),
			'<strong>',
			'</strong>'
		);
		$this->notices->info( $msg, 'upgrade_10' );
	}

	/**
	 * @return void
	 */
	private function get_early_access_notice() {
		$msg = sprintf(
			__(
				'%1$sGet Early Access to our Black Friday Sale!%2$s',
				'dynamic-visibility-for-elementor'
			) . '<br />',
			'<strong>',
			'</strong>'
		);
		$msg .= sprintf(
			__(
				'Be the first to grab our limited-time offers and enjoy our biggest discounts of the year on Dynamic Content for Elementor. %1$sRegister now &rarr;%2$s',
				'dynamic-visibility-for-elementor'
			),
			'<a target="_blank" href="https://www.dynamic.ooo/promo/black-friday-2025/?utm_source=wp-plugins&utm_campaign=black-friday-early&utm_medium=wp-dash-promo"><strong>',
			'</strong></a>'
		);
		$this->notices->info( $msg, 'black_friday_early_2025' );
	}

	/**
	 * @return void
	 */
	private function get_black_friday_notice() {
		$msg = sprintf(
			__(
				'%1$sBlack Friday Sale!%2$s Get our biggest discounts of the year on Dynamic Content for Elementor.',
				'dynamic-visibility-for-elementor'
			) . '<br />',
			'<strong>',
			'</strong>'
		);
		$msg .= sprintf(
			__(
				'We give you %1$sover 150 features for Elementor%2$s that will save you time and money on achieving complex results. %3$sGet it now &rarr;%4$s',
				'dynamic-visibility-for-elementor'
			),
			'<strong>',
			'</strong>',
			'<a target="_blank" href="https://www.dynamic.ooo/upgrade/visibility-to-premium?utm_source=wp-plugins&utm_campaign=black-friday&utm_medium=wp-dash-promo"><strong>',
			'</strong></a>'
		);
		$this->notices->info( $msg, 'black_friday_2025' );
	}

	/**
	 * @return array<mixed>
	 */
	public function add_action_links( $links ) {
		$my_links[] = sprintf(
			'<a href="https://www.dynamic.ooo/upgrade/visibility-to-premium?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash" target="_blank"">' .
				__( 'Go Premium', 'dynamic-visibility-for-elementor' ) .
				'</a>'
		);
		return array_merge( $links, $my_links );
	}

	/**
	 * @return array<mixed>
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if (
			'dynamic-visibility-for-elementor/dynamic-visibility-for-elementor.php' ===
			$plugin_file
		) {
			$row_meta = [
				'docs' =>
					'<a href="https://dnmc.ooo/visibilitydoc" aria-label="' .
					esc_attr(
						__(
							'View Dynamic Visibility Documentation',
							'dynamic-visibility-for-elementor'
						)
					) .
					'" target="_blank">' .
					__( 'Docs', 'dynamic-visibility-for-elementor' ) .
					'</a>',
				'community' =>
					'<a href="http://facebook.com/groups/dynamic.ooo" aria-label="' .
					esc_attr(
						__(
							'Facebook Community',
							'dynamic-visibility-for-elementor'
						)
					) .
					'" target="_blank">' .
					__( 'FB Community', 'dynamic-visibility-for-elementor' ) .
					'</a>',
			];

			$plugin_meta = array_merge( $plugin_meta, $row_meta );
		}

		return $plugin_meta;
	}

	/**
	 * @return void
	 */
	public function dce_editor() {
		// Register styles
		wp_register_style(
			'dce-icons',
			DVE_URL . 'assets/css/dce-icon.css',
			[],
			DVE_VERSION
		);
		// Enqueue styles Icons
		wp_enqueue_style( 'dce-icons' );

		// Register styles
		wp_register_style(
			'dce-editor',
			DVE_URL . 'assets/css/editor.css',
			[],
			DVE_VERSION
		);
		wp_enqueue_style( 'dce-editor' );

		wp_register_script(
			'dce-script-editor',
			DVE_URL . 'assets/js/editor.js',
			[],
			DVE_VERSION
		);
		wp_enqueue_script( 'dce-script-editor' );

		wp_register_script(
			'dce-editor-visibility',
			DVE_URL . 'assets/js/editor-dynamic-visibility.js',
			[],
			DVE_VERSION
		);
		wp_enqueue_script( 'dce-editor-visibility' );

		// select2
		wp_enqueue_style(
			'dce-select2',
			DVE_URL . 'assets/lib/select2/select2.min.css',
			[],
			DVE_VERSION
		);
		wp_enqueue_script(
			'dce-select2',
			DVE_URL . 'assets/lib/select2/select2.full.min.js',
			[ 'jquery' ],
			DVE_VERSION,
			true
		);
	}
}

Plugin::instance();
