<?php
/**
 * The storefront half of Essential Addons' Bootstrap class
 * (includes/Classes/Bootstrap.php, 6.8.5), for the theme port.
 *
 * Built from the plugin's own traits, unchanged (inc/ports/eael/includes/Traits/):
 * Library, Helper (with Template_Query), Enqueue, Login_Registration and
 * Ajax_Handler — the same traits Bootstrap uses, minus Core, Admin, Elements,
 * Controls, Woo_Product_Comparable and Facebook_Feed. The handful of methods
 * needed from those six are copied below word for word, each marked with its
 * source. register_hooks() keeps the plugin's own calls in the plugin's order,
 * hooks and priorities; what it leaves out is listed in
 * inc/ports/essential-addons.php.
 *
 * Required only after that file's "is the plugin active?" guard.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Plugin;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Essential_Addons_Elementor\Classes\Asset_Builder;

if ( ! class_exists( 'AF_Eael_Port', false ) ) {
class AF_Eael_Port {
	use \Essential_Addons_Elementor\Traits\Library;
	use \Essential_Addons_Elementor\Traits\Helper;
	use \Essential_Addons_Elementor\Traits\Enqueue;
	use \Essential_Addons_Elementor\Traits\Login_Registration;
	use \Essential_Addons_Elementor\Traits\Ajax_Handler;

	// instance container
	private static $instance = null;

	// registered elements container
	protected $registered_elements;

	// registered extensions container
	protected $registered_extensions;

	// identify whether pro is enabled
	protected $pro_enabled;

	// localize objects
	public $localize_objects = [];

	/**
	 * Singleton instance (Bootstrap::instance()).
	 */
	public static function instance() {
		if ( self::$instance == null ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Bootstrap::__construct(), without the admin-only modules (plugin
	 * installer, ThinkRank/xSpeed promotions, usage tracking, Theme Builder,
	 * Mega Menu, Angie, Mondial Relay compatibility).
	 */
	private function __construct() {
		// before init hook
		do_action( 'eael/before_init' );

		// search for pro version
		$this->pro_enabled = apply_filters( 'eael/pro_enabled', false );

		// elements classmap
		$this->registered_elements = apply_filters( 'eael/registered_elements', $GLOBALS['eael_config']['elements'] );

		// extensions classmap
		$this->registered_extensions = apply_filters( 'eael/registered_extensions', $GLOBALS['eael_config']['extensions'] );

		// register extensions
		$this->register_extensions();

		// register hooks
		$this->register_hooks();

		if ( $this->is_activate_elementor() ) {
			new Asset_Builder( $this->registered_elements, $this->registered_extensions );
		}
	}

	/**
	 * Bootstrap::register_hooks(), storefront and editor parts, in the plugin's order.
	 */
	protected function register_hooks() {
		// Core
		add_action( 'init', [ $this, 'i18n' ] );
		// TODO::RM
		add_filter( 'eael/active_plugins', [ $this, 'is_plugin_active' ], 10, 1 );

		add_filter( 'eael/is_plugin_active', [ $this, 'is_plugin_active' ], 10, 1 );

		// Enqueue
		add_action( 'eael/before_enqueue_styles', [ $this, 'before_enqueue_styles' ] );
		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'editor_enqueue_scripts' ] );
		add_action( 'elementor/frontend/before_register_scripts', [ $this, 'frontend_enqueue_scripts' ] );

		// Ajax (Ajax_Handler::init_ajax_hooks(): the endpoints these four widgets and general.js call)
		add_action( 'wp_ajax_eael_product_add_to_cart', array( $this, 'eael_product_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_eael_product_add_to_cart', array( $this, 'eael_product_add_to_cart' ) );

		add_action( 'wp_ajax_eael_ajax_add_to_cart',        [ $this, 'eael_ajax_add_to_cart' ] );
		add_action( 'wp_ajax_nopriv_eael_ajax_add_to_cart', [ $this, 'eael_ajax_add_to_cart' ] );

		add_action( 'wp_ajax_nopriv_eael_product_quickview_popup', [ $this, 'eael_product_quickview_popup' ] );
		add_action( 'wp_ajax_eael_product_quickview_popup', [ $this, 'eael_product_quickview_popup' ] );

		add_action( 'wp_ajax_eael_select2_search_post', [ $this, 'select2_ajax_posts_filter_autocomplete' ] );
		add_action( 'wp_ajax_eael_select2_get_title', [ $this, 'select2_ajax_get_posts_value_titles' ] );

		add_action( 'wp_ajax_eael_get_token', [ $this, 'eael_get_token' ] );
		add_action( 'wp_ajax_nopriv_eael_get_token', [ $this, 'eael_get_token' ] );

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			if ( version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				add_action( 'elementor/controls/register', array( $this, 'register_controls' ) );
				add_action( 'elementor/widgets/register', array( $this, 'register_elements' ) );
				add_action( 'elementor/widgets/register', array( $this, 'woo_checkout_type_instance' ) );
			} else {
				add_action( 'elementor/controls/controls_registered', array( $this, 'register_controls' ) );
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_elements' ) );
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'woo_checkout_type_instance' ) );
			}
		}

		// Elements
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_categories' ) );

		// Controls (the one shared control group these widgets ask for: the carousel's "Not Found Message")
		add_action( 'eael/controls/nothing_found_style', [ $this, 'nothing_found_style' ], 10, 1 );

		// Login | Register
		add_action( 'init', [ $this, 'login_or_register_user' ] );
		add_filter( 'wp_new_user_notification_email', array( $this, 'new_user_notification_email' ), 10, 3 );
		add_filter( 'wp_new_user_notification_email_admin', array( $this, 'new_user_notification_email_admin' ), 10, 3 );

		// Email OTP Verification (Login | Register)
		add_action( 'wp_ajax_eael_lr_send_otp',        [ $this, 'eael_ajax_send_otp' ] );
		add_action( 'wp_ajax_nopriv_eael_lr_send_otp', [ $this, 'eael_ajax_send_otp' ] );
		add_action( 'wp_ajax_eael_lr_verify_otp',        [ $this, 'eael_ajax_verify_otp' ] );
		add_action( 'wp_ajax_nopriv_eael_lr_verify_otp', [ $this, 'eael_ajax_verify_otp' ] );

		// Block any core authentication path (wp-login.php, XML-RPC, application
		// passwords, wp_signon() calls elsewhere) for accounts still pending OTP
		// verification. The EA login widget's own gate in log_user_in() only covers
		// logins submitted through that widget's form/AJAX endpoint.
		add_filter( 'wp_authenticate_user', [ $this, 'eael_block_otp_pending_authentication' ], 20, 2 );

		// Flag unverified OTP register users in wp-admin → Users.
		add_action( 'admin_footer-users.php', [ $this, 'eael_lr_otp_pending_user_flag' ] );
		add_action( 'init', [ $this, 'eael_redirect_to_reset_password' ] );

		if ( 'on' === get_option( 'eael_custom_profile_fields' ) ) {
			add_action( 'show_user_profile', [ $this, 'eael_extra_user_profile_fields' ] );
			add_action( 'edit_user_profile', [ $this, 'eael_extra_user_profile_fields' ] );

			add_action( 'personal_options_update', [ $this, 'eael_save_extra_user_profile_fields' ] );
			add_action( 'edit_user_profile_update', [ $this, 'eael_save_extra_user_profile_fields' ] );
		}

		// Admin Approval hooks registered unconditionally so option/widget checks
		// happen at call time (correct site context in Multisite) rather than at init.
		add_filter( 'wp_authenticate_user', [ $this, 'eael_block_pending_user_login' ], 10, 2 );
		add_filter( 'manage_users_columns', [ $this, 'eael_add_user_status_column' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'eael_render_user_status_column' ], 10, 3 );
		add_action( 'show_user_profile', [ $this, 'eael_show_approve_user_button' ] );
		add_action( 'edit_user_profile', [ $this, 'eael_show_approve_user_button' ] );
		add_action( 'personal_options_update', [ $this, 'eael_handle_approve_user' ] );
		add_action( 'edit_user_profile_update', [ $this, 'eael_handle_approve_user' ] );
		add_filter( 'bulk_actions-users', [ $this, 'eael_register_bulk_approve_action' ] );
		add_filter( 'views_users', [ $this, 'eael_register_status_views' ] );
		add_action( 'pre_get_users', [ $this, 'eael_filter_users_by_status' ] );
		add_filter( 'handle_bulk_actions-users', [ $this, 'eael_handle_bulk_approve_action' ], 10, 3 );
		add_filter( 'handle_bulk_actions-users', [ $this, 'eael_handle_bulk_reject_action' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'eael_bulk_approve_admin_notice' ] );

		//rank math support
		add_filter( 'rank_math/researches/toc_plugins', [ $this, 'toc_rank_math_support' ] );

		// Translate embedded Elementor templates/documents (saved templates used by
		// Advanced Tabs, Advanced Accordion, Info Box, etc.) to the current language.
		// eael_wpml_template_translation() handles both WPML and Polylang. Previously
		// this was disabled and gated to WPML only, so Polylang sites always rendered
		// the source-language template.
		if ( defined( 'WPML_TM_VERSION' ) || defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_get_post_language' ) ) {
			add_filter( 'elementor/documents/get/post_id', [ $this, 'eael_wpml_template_translation' ] );
		}

		// Polylang has no Elementor integration, so the template library is not
		// translatable by default. Opt it in so saved templates can be mapped to the
		// current language (and managed from Polylang's UI).
		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_get_post_language' ) ) {
			add_filter( 'pll_get_post_types', [ $this, 'eael_pll_translate_elementor_library' ], 10, 2 );
		}

		if ( class_exists( 'woocommerce' ) ) {
			// Login|Register custom fields on WooCommerce My Account edit-account page
			add_action( 'woocommerce_edit_account_form_fields', [ $this, 'eael_wc_account_form_fields' ] );
			add_action( 'woocommerce_save_account_details', [ $this, 'eael_wc_save_account_fields' ] );

			// quick view
			add_action( 'eael_woo_single_product_image', 'woocommerce_show_product_images', 20 );
			add_action( 'eael_woo_single_product_summary', 'woocommerce_template_single_title', 5 );
			add_action( 'eael_woo_single_product_summary', 'woocommerce_template_single_rating', 10 );
			add_action( 'eael_woo_single_product_summary', 'woocommerce_template_single_price', 15 );
			add_action( 'eael_woo_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
			add_action( 'eael_woo_single_product_summary', 'woocommerce_template_single_add_to_cart', 25 );
			add_action( 'eael_woo_single_product_summary', 'woocommerce_template_single_meta', 30 );

			add_filter( 'eael_product_wrapper_class', [ $this, 'eael_product_wrapper_class' ], 10, 3 );

			add_action( 'wp_loaded', [ $this, 'eael_woo_cart_empty_action' ], 20 );
			add_filter( 'woocommerce_checkout_fields', [ $this, 'eael_customize_woo_checkout_fields' ] );

			add_action( 'eael_woo_before_product_loop', function ( $layout ) {
				if ( $layout === 'eael-product-default' ) {
					return;
				}

				remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open' );
				remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close' );
				remove_action( 'woocommerce_after_shop_loop_item', 'astra_woo_woocommerce_shop_product_content' );
				remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
			} );

			add_action( 'eael_woo_after_product_loop', function ( $layout ) {
				if ( $layout === 'eael-product-default' ) {
					return;
				}

				add_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open' );
				add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close' );
				//Get current active theme
				$theme = wp_get_theme();
				$theme = $theme->parent() ? $theme->parent() : $theme;
				//Astra Theme
				if ( function_exists( 'astra_woo_woocommerce_shop_product_content' ) ) {
					add_action( 'woocommerce_after_shop_loop_item', 'astra_woo_woocommerce_shop_product_content' );
				} else {
					add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
				}
				//Theme Support
				$theme_to_check = [ 'OceanWP', 'Blocksy', 'Travel Ocean' ];
				if ( in_array( $theme->name, $theme_to_check, true ) ) {
					remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
				}
			} );
		}

		if ( is_admin() ) {
			// On Editor - Register WooCommerce frontend hooks before the Editor init.
			// Priority = 5, in order to allow plugins remove/add their wc hooks on init.
			if ( ! empty( $_REQUEST['action'] ) && 'elementor' === $_REQUEST['action'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				add_action( 'init', [ $this, 'register_wc_hooks' ], 5 );
			}
		} else {
			add_action( 'wp', [ $this, 'eael_post_view_count' ] );
		}

		// Registered on every request and checked at save time — see the method.
		add_filter( 'elementor/document/save/data', [ $this, 'eael_restrict_document_save_data' ], 10, 2 );
	}

	/**
	 * Core::i18n() (includes/Traits/Core.php).
	 */
	public function i18n() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		load_plugin_textdomain( 'essential-addons-for-elementor-lite' );
	}

	/**
	 * Core::is_plugin_active() (includes/Traits/Core.php).
	 */
	public function is_plugin_active( $plugin ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		return is_plugin_active( $plugin );
	}

	/**
	 * Elements::register_controls() (includes/Traits/Elements.php), with the one
	 * custom control these four widgets use (eael-select2: the tabs' "Choose
	 * Template" and the carousel's "Select Products"). The plugin also
	 * registered eael-background, eael-gradient-text and eael-choose, which only
	 * its other widgets use.
	 */
	public function register_controls( $controls_manager ) {
		if ( version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
			$controls_manager->register( new \Essential_Addons_Elementor\Controls\Select2() );
		} else {
			$controls_manager->register_control( 'eael-select2', new \Essential_Addons_Elementor\Controls\Select2() );
		}
	}

	/**
	 * Elements::register_widget_categories() (includes/Traits/Elements.php).
	 */
	public function register_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'essential-addons-elementor',
			[
				'title' => __( 'Essential Addons', 'essential-addons-for-elementor-lite' ),
				'icon'  => 'font',
			], 1 );
	}

	/**
	 * Elements::register_elements() (includes/Traits/Elements.php), unchanged.
	 */
	public function register_elements( $widgets_manager ) {
		$active_elements = (array) $this->get_settings();

		if ( empty( $active_elements ) ) {
			return;
		}

		asort( $active_elements );

		foreach ( $active_elements as $active_element ) {
			if ( ! isset( $this->registered_elements[ $active_element ] ) ) {
				continue;
			}

			if ( isset( $this->registered_elements[ $active_element ]['condition'] ) ) {
				$check = false;

				if ( isset( $this->registered_elements[ $active_element ]['condition'][2] ) ) {
					$check = $this->registered_elements[ $active_element ]['condition'][2];
				}

				if ( $this->registered_elements[ $active_element ]['condition'][0]( $this->registered_elements[ $active_element ]['condition'][1] ) == $check ) {
					continue;
				}
			}

			if ( $this->pro_enabled && defined( 'EAEL_PRO_PLUGIN_VERSION' ) && \version_compare( EAEL_PRO_PLUGIN_VERSION, '3.3.0', '<' ) ) {
				if ( in_array( $active_element, [
					'content-timeline',
					'dynamic-filter-gallery',
					'post-block',
					'post-carousel',
					'post-list'
				] ) ) {
					continue;
				}
			}

			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				$widgets_manager->register( new $this->registered_elements[ $active_element ]['class'] );
			} else {
				$widgets_manager->register_widget_type( new $this->registered_elements[ $active_element ]['class'] );
			}
		}
	}

	/**
	 * What the plugin's Woo Checkout widget did on every request without being
	 * placed anywhere. register_elements() builds a type instance of every
	 * switched-on widget whenever Elementor fills its widget registry, and
	 * Woo_Checkout::__construct() (includes/Elements/Woo_Checkout.php), for
	 * that type instance with WooCommerce active, loads the cart if nothing has
	 * yet and adds the eael-woo-checkout body class on the checkout page. That
	 * class can reach the live checkout page, so the port keeps both, at the
	 * same moment (widget registration, after Login | Register's own body_class
	 * filter, as in the plugin's alphabetical order). The constructor's third
	 * call, eael_woocheckout_recurring(), only hooks WooCommerce Subscriptions
	 * into actions the Woo Checkout widget's own render fires, so it is not kept.
	 */
	public function woo_checkout_type_instance( $widgets_manager ) {
		if ( ! in_array( 'woo-checkout', (array) $this->get_settings(), true ) ) {
			return;
		}

		if ( class_exists( 'woocommerce' ) ) {
			if ( is_null( WC()->cart ) ) {
				include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
				include_once WC_ABSPATH . 'includes/class-wc-cart.php';
				wc_load_cart();
			}
			add_filter( 'body_class', [ $this, 'add_checkout_body_class' ] );
		}
	}

	/**
	 * Woo_Checkout::add_checkout_body_class() (includes/Elements/Woo_Checkout.php), unchanged.
	 */
	public function add_checkout_body_class( $classes ) {
		if ( is_checkout() ) {
			$classes[] = 'eael-woo-checkout';
		}
		return $classes;
	}

	/**
	 * Elements::register_extensions() (includes/Traits/Elements.php), unchanged;
	 * the config lists only Custom JS.
	 */
	public function register_extensions() {
		$active_elements = (array) $this->get_settings();

		// set promotion extension enabled
		array_push( $active_elements, 'promotion' );

		foreach ( $this->registered_extensions as $key => $extension ) {
			if ( ! in_array( $key, $active_elements ) ) {
				continue;
			}

			if ( class_exists( $extension['class'] ) ) {
				new $extension['class']; // Safely instantiate
			}
		}
	}

	/**
	 * Elements::register_wc_hooks() (includes/Traits/Elements.php).
	 */
	public function register_wc_hooks() {
		if ( class_exists( 'WooCommerce' ) ) {
			wc()->frontend_includes();
		}
	}

	/**
	 * Controls::nothing_found_style() (includes/Traits/Controls.php), unchanged.
	 */
	public static function nothing_found_style( $wb ) {
		$wb->start_controls_section(
			'eael_section_nothing_found_style',
			[
				'label' => __( 'Not Found Message', 'essential-addons-for-elementor-lite' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$wb->add_control( 'eael_section_nothing_found_note', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'Style the message when no posts are found.', 'essential-addons-for-elementor-lite' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		] );

		$wb->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'eael_post_nothing_found_typography',
				'selector' => '{{WRAPPER}} .eael-no-posts-found',
			]
		);
		$wb->add_control(
			'eael_post_nothing_found_color',
			[
				'label' => esc_html__( 'Text Color', 'essential-addons-for-elementor-lite' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .eael-no-posts-found' => 'color: {{VALUE}};',
				],
			]
		);
		$wb->add_control(
			'eael_post_nothing_found_bg_color',
			[
				'label' => esc_html__( 'Background Color', 'essential-addons-for-elementor-lite' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .eael-no-posts-found' => 'background-color: {{VALUE}};',
				],
			]
		);
		$wb->add_responsive_control(
			'eael_post_nothing_found_padding',
			[
				'label' => esc_html__( 'Padding', 'essential-addons-for-elementor-lite' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default'    => [
					'top'      => "25",
					'right'    => "25",
					'bottom'   => "25",
					'left'     => "25",
					'isLinked' => true,
				],
				'selectors' => [
					'{{WRAPPER}} .eael-no-posts-found' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$wb->add_control(
			'eael_post_nothing_found_alignment',
			[
				'label'     => __( 'Alignment', 'essential-addons-for-elementor-lite' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'  => [
						'title' => __( 'Left', 'essential-addons-for-elementor-lite' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center'  => [
						'title' => __( 'Center', 'essential-addons-for-elementor-lite' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'essential-addons-for-elementor-lite' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default' => 'center',
				'selectors' => [
					'{{WRAPPER}} .eael-no-posts-found' => 'text-align: {{VALUE}};',
				],
			]
		);

		$wb->end_controls_section();
	}

	/**
	 * Bootstrap::eael_restrict_document_save_data(), unchanged.
	 *
	 * Drops document settings a non-administrator must not be able to save.
	 *
	 * Resets the Custom JS extension's code to what is already stored, the
	 * Login | Register widget's new-user role, the Product Grid's post status
	 * and, without `install_plugins`, the Advanced Data Table's source.
	 *
	 * @param array                          $data     Document data about to be saved.
	 * @param \Elementor\Core\Base\Document|null $document Document being saved.
	 *
	 * @return array
	 */
	public function eael_restrict_document_save_data( $data, $document = null ) {
		if ( current_user_can( 'administrator' ) ) {
			return $data;
		}

		if ( isset( $data['settings']['eael_custom_js'] ) ) {
			// The document's own ID: outside the editor there is no global post,
			// and get_the_ID() would read the stored code from post 0.
			$post_id = ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) ? $document->get_main_id() : get_the_ID();

			$data['settings']['eael_custom_js'] = get_post_meta( $post_id, '_eael_custom_js', true );
		}

		if ( empty( $data['elements'] ) ) {
			return $data;
		}

		$data['elements'] = Plugin::$instance->db->iterate_data( $data['elements'], function ( $element ) {
			if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'eael-login-register' ) {
				if ( ! empty( $element['settings']['register_user_role'] ) ) {
					$element['settings']['register_user_role'] = '';
				}
			}

			if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'eicon-woocommerce' ) {
				if ( ! empty( $element['settings']['eael_product_grid_products_status'] ) ) {
					$element['settings']['eael_product_grid_products_status'] = [ 'publish' ];
				}
			}

			if ( ! current_user_can( 'install_plugins' ) && isset( $element['widgetType'] ) && $element['widgetType'] === 'eael-advanced-data-table' ) {
				if ( ! empty( $element['settings']['ea_adv_data_table_source'] ) ) {
					$element['settings']['ea_adv_data_table_source'] = 'static';
				}
			}

			return $element;
		} );

		return $data;
	}
}
}
