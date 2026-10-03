<?php

namespace QuadLayers\IGG\Controllers;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use QuadLayers\IGG\Models\Feeds as Models_Feeds;
use QuadLayers\IGG\Models\Accounts as Models_Accounts;
use QuadLayers\IGG\Frontend\Load as Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instagram Feed Widget
 */
class Elementor_Widget extends Widget_Base {

	/**
	 * Constructor
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );
	}

	/**
	 * Get widget name
	 */
	public function get_name() {
		return 'instagram_feed';
	}

	/**
	 * Get widget title
	 */
	public function get_title() {
		return esc_html__( 'Instagram Feed', 'insta-gallery' );
	}

	/**
	 * Get widget icon
	 */
	public function get_icon() {
		return 'eicon-instagram-gallery';
	}

	/**
	 * Get widget categories
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Get widget keywords
	 */
	public function get_keywords() {
		return array( 'qligg', 'instagram', 'gallery', 'social', 'feed', 'quadlayers', 'Social Feed Gallery' );
	}

	/**
	 * Register scripts dependencies
	 */
	public function get_script_depends() {
		return array( 'qligg-frontend' );
	}

	/**
	 * Register style dependencies
	 */
	public function get_style_depends() {
		return array( 'qligg-frontend' );
	}

	/**
	 * Register widget controls
	 */
	protected function register_controls() {
		$feed_defaults = Models_Feeds::instance()->get_args();
		$accounts      = Models_Accounts::instance()->get_all();

		// CACHE SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_cache',
			array(
				'label' => esc_html__( 'Cache', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'cache_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'The cache will be cleared automatically when you save the widget settings. To manually clear cache, click the button below.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'clear_cache_button',
			array(
				'label'       => esc_html__( 'Clear Cache', 'insta-gallery' ),
				'type'        => Controls_Manager::BUTTON,
				'text'        => esc_html__( 'Clear Cache', 'insta-gallery' ),
				'button_type' => 'default',
				'event'       => 'qligg:clear_cache',
			)
		);

		$this->end_controls_section();

		// GENERAL SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_general',
			array(
				'label' => esc_html__( 'General', 'insta-gallery' ),
			)
		);

		// Get accounts for dropdown
		$account_options = array();

		if ( ! empty( $accounts ) ) {
			foreach ( $accounts as $index => $account ) {
				$label = $account['username'] ?? $account['nickname'] ?? '';
				if ( trim( $label ) === '' ) {
					$label = sprintf( 'ID #%s', $account['id'] );
				}

				// Add token type to the label
				$token_type = $account['access_token_type'] ?? '';
				if ( $token_type ) {
					$type_label = $token_type === 'BUSINESS' ? 'Professional' : 'Personal';
					$label     .= sprintf( ' (%s)', $type_label );
				}

				if ( ! defined( 'QLIGG_PREMIUM' ) && $index > 0 ) {
					continue;
				}
				$account_options[ $account['id'] ] = $label;
			}
		}

		// Add option to create account if none exists
		if ( empty( $account_options ) ) {
			$this->add_control(
				'no_accounts_notice',
				array(
					'type' => Controls_Manager::RAW_HTML,
					'raw'  => sprintf(
						'<div class="elementor-panel-alert elementor-panel-alert-warning">%s</div>',
						esc_html__( 'You need to create an Instagram account first. Go to Instagram Feed Gallery → Accounts and add a new account.', 'insta-gallery' )
					),
				)
			);
		} else {
			$this->add_control(
				'account_id',
				array(
					'label'       => esc_html__( 'Account', 'insta-gallery' ),
					'type'        => Controls_Manager::SELECT,
					'default'     => array_key_first( $account_options ),
					'options'     => $account_options,
					'description' => ! defined( 'QLIGG_PREMIUM' ) ? esc_html__( 'Additional Instagram accounts (Premium)', 'insta-gallery' ) : '',
				)
			);
		}

		$this->add_control(
			'source',
			array(
				'label'   => esc_html__( 'Source', 'insta-gallery' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $feed_defaults['source'],
				'options' => array(
					'username' => esc_html__( 'Username', 'insta-gallery' ),
					'tag'      => esc_html__( 'Tag', 'insta-gallery' ),
					'tagged'   => esc_html__( 'Tagged (Premium)', 'insta-gallery' ),
					'stories'  => esc_html__( 'Stories (Premium)', 'insta-gallery' ),
				),
			)
		);

		if ( ! empty( $accounts ) ) {
			$default_account_id = array_key_first( $account_options );

			$default_account = Models_Accounts::instance()->get( $default_account_id );

			$is_business_account = isset( $default_account['access_token_type'] ) && $default_account['access_token_type'] === 'BUSINESS';

			if ( ! $is_business_account ) {
				$this->add_control(
					'business_notice',
					array(
						'type'            => Controls_Manager::RAW_HTML,
						'raw'             => esc_html__( 'Hashtag, Tagged, and Stories options are only available for professional accounts.', 'insta-gallery' ),
						'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
					)
				);
			}
		}

		$this->add_control(
			'tag',
			array(
				'label'     => esc_html__( 'Tag', 'insta-gallery' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $feed_defaults['tag'],
				'condition' => array(
					'source' => 'tag',
				),
			)
		);

		$this->add_control(
			'api_limit_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Instagram API limitation: You are limited to 30 unique hashtags in a 7-day period.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'source' => 'tag',
				),
			)
		);

		$this->add_control(
			'tagged_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'This feed will display photos where your Instagram account has been tagged by other users.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'source' => 'tagged',
				),
			)
		);

		$this->add_control(
			'stories_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'This feed will display your active Instagram stories. Stories expire after 24 hours.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'source' => 'stories',
				),
			)
		);

		$this->add_control(
			'order_by',
			array(
				'label'     => esc_html__( 'Order by', 'insta-gallery' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => $feed_defaults['order_by'],
				'options'   => array(
					'recent_media' => esc_html__( 'Recent (Within 24 hours)', 'insta-gallery' ),
					'top_media'    => esc_html__( 'Top (Most popular first)', 'insta-gallery' ),
				),
				'condition' => array(
					'source' => 'tag',
				),
			)
		);

		$layout_options = array(
			'gallery'   => array(
				'title' => esc_html__( 'Gallery', 'insta-gallery' ),
				'icon'  => 'eicon-gallery-grid',
			),
			'carousel'  => array(
				'title' => esc_html__( 'Carousel', 'insta-gallery' ),
				'icon'  => 'eicon-slider-album',
			),
			'masonry'   => array(
				'title' => esc_html__( 'Masonry', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'icon'  => 'eicon-inner-section',
			),
			'highlight' => array(
				'title' => esc_html__( 'Highlight', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'icon'  => 'eicon-posts-masonry',
			),
		);

		$this->add_control(
			'layout_type',
			array(
				'label'   => esc_html__( 'Layout', 'insta-gallery' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => $layout_options,
				'default' => $feed_defaults['layout'],
				'toggle'  => false,
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => esc_html__( 'Limit', 'insta-gallery' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['limit'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 1,
						'max'  => 33,
						'step' => 1,
					),
				),
			)
		);

		$this->add_control(
			'reel_hide',
			array(
				'label'     => esc_html__( 'Hide feed reels', 'insta-gallery' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => $feed_defaults['reel']['hide'] ? 'yes' : '',
				'condition' => array(
					'source!' => array( 'tag', 'stories' ),
				),
			)
		);

		$this->add_control(
			'copyright_hide',
			array(
				'label'   => esc_html__( 'Hide feed copyright', 'insta-gallery' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => $feed_defaults['copyright']['hide'] ? 'yes' : '',
			)
		);

		$this->add_control(
			'aspect_ratio_width',
			array(
				'label'   => esc_html__( 'Aspect ratio - Width', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => 1,
				),
				'range'   => array(
					'px' => array(
						'min'  => 1,
						'max'  => 100,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'aspect_ratio_height',
			array(
				'label'   => esc_html__( 'Aspect ratio - Height', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => 1,
				),
				'range'   => array(
					'px' => array(
						'min'  => 1,
						'max'  => 100,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'copyright_placeholder',
			array(
				'label'       => esc_html__( 'Copyright placeholder', 'insta-gallery' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $feed_defaults['copyright']['placeholder'],
				'placeholder' => esc_html__( 'Enter copyright placeholder text', 'insta-gallery' ),
				'condition'   => array(
					'copyright_hide' => '',
				),
			)
		);

		$this->end_controls_section();

		// RESPONSIVE SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_responsive',
			array(
				'label' => esc_html__( 'Responsive', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'responsive_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => '<small>' . esc_html__( 'These responsive settings will override the main feed settings for different screen sizes.', 'insta-gallery' ) . '</small>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		// Desktop settings
		$this->add_control(
			'desktop_heading',
			array(
				'label'     => esc_html__( 'Desktop', 'insta-gallery' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'desktop_columns',
			array(
				'label'   => esc_html__( 'Columns', 'insta-gallery' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['desktop']['columns'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 1,
						'max'  => 10,
						'step' => 1,
					),
				),
			)
		);

		$this->add_control(
			'desktop_spacing',
			array(
				'label'   => esc_html__( 'Spacing', 'insta-gallery' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['desktop']['spacing'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 0,
						'max'  => 50,
						'step' => 1,
					),
				),
			)
		);

		// Tablet settings
		$this->add_control(
			'tablet_heading',
			array(
				'label'     => esc_html__( 'Tablet', 'insta-gallery' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'tablet_breakpoint',
			array(
				'label'   => esc_html__( 'Breakpoint (px)', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['breakpoints']['tablet'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 320,
						'max'  => 1200,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			),
		);

		$this->add_control(
			'tablet_columns',
			array(
				'label'   => esc_html__( 'Columns', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['tablet']['columns'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 1,
						'max'  => 8,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'tablet_spacing',
			array(
				'label'   => esc_html__( 'Spacing', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['tablet']['spacing'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 0,
						'max'  => 50,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			)
		);

		// Mobile settings
		$this->add_control(
			'mobile_heading',
			array(
				'label'     => esc_html__( 'Mobile', 'insta-gallery' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'mobile_breakpoint',
			array(
				'label'   => esc_html__( 'Breakpoint (px)', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['breakpoints']['mobile'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 320,
						'max'  => 767,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'mobile_columns',
			array(
				'label'   => esc_html__( 'Columns', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['mobile']['columns'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 1,
						'max'  => 6,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'mobile_spacing',
			array(
				'label'   => esc_html__( 'Spacing', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'    => Controls_Manager::SLIDER,
				'default' => array(
					'size' => $feed_defaults['responsive']['mobile']['spacing'],
				),
				'range'   => array(
					'px' => array(
						'min'  => 0,
						'max'  => 50,
						'step' => 1,
					),
				),
				'classes' => 'qligg-premium-field',
			)
		);

		$this->end_controls_section();

		// CAROUSEL SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_carousel',
			array(
				'label'     => esc_html__( 'Carousel', 'insta-gallery' ),
				'condition' => array(
					'layout_type' => 'carousel',
				),
			)
		);

		$this->add_control(
			'carousel_centered_slides',
			array(
				'label'       => esc_html__( 'Centered slides', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['carousel']['centered_slides'] ? 'yes' : '',
				'description' => esc_html__( 'Enable centered slides position', 'insta-gallery' ),
				'classes'     => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'carousel_autoplay',
			array(
				'label'   => esc_html__( 'Autoplay', 'insta-gallery' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => $feed_defaults['carousel']['autoplay'] ? 'yes' : '',
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'carousel_autoplay_interval',
			array(
				'label'     => esc_html__( 'Autoplay Interval', 'insta-gallery' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => $feed_defaults['carousel']['autoplay_interval'],
				),
				'range'     => array(
					'px' => array(
						'min'  => 1000,
						'max'  => 300000,
						'step' => 100,
					),
				),
				'condition' => array(
					'autoplay' => 'yes',
				),
				'classes'   => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'carousel_navarrows',
			array(
				'label'   => esc_html__( 'Navigation', 'insta-gallery' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => $feed_defaults['carousel']['navarrows'] ? 'yes' : '',
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'carousel_navarrows_color',
			array(
				'label'              => esc_html__( 'Navigation color', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['carousel']['navarrows_color'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-carousel-arrow-color: {{VALUE}};',
				),
				'condition'          => array(
					'carousel_navarrows' => 'yes',
				),
				'classes'            => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'carousel_pagination',
			array(
				'label'   => esc_html__( 'Pagination', 'insta-gallery' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => $feed_defaults['carousel']['pagination'] ? 'yes' : '',
				'classes' => 'qligg-premium-field',
			)
		);

		$this->add_control(
			'carousel_pagination_color',
			array(
				'label'              => esc_html__( 'Pagination color', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['carousel']['pagination_color'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-carousel-pagination-color: {{VALUE}};',
				),
				'condition'          => array(
					'carousel_pagination' => 'yes',
				),
				'classes'            => 'qligg-premium-field',
			)
		);

		$this->end_controls_section();

		// HIGHLIGHT SECTION (Premium)
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_highlight',
			array(
				'label'     => esc_html__( 'Highlight', 'insta-gallery' ),
				'condition' => array(
					'layout_type' => 'highlight',
				),
			)
		);

		$this->add_control(
			'highlight_tag',
			array(
				'label'       => esc_html__( 'Highlight by tag', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $feed_defaults['highlight']['tag'],
				'description' => esc_html__( 'Highlight feeds items with this tags', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'highlight_id',
			array(
				'label'       => esc_html__( 'Highlight by id', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $feed_defaults['highlight']['id'],
				'description' => esc_html__( 'Highlight feeds items with this ID', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'highlight_position',
			array(
				'label'       => esc_html__( 'Highlight by position', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $feed_defaults['highlight']['position'],
				'description' => esc_html__( 'Highlight feeds items in these positions (comma-separated)', 'insta-gallery' ),
			)
		);

		$this->end_controls_section();

		// IMAGE SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_image',
			array(
				'label' => esc_html__( 'Image', 'insta-gallery' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'lazy',
			array(
				'label'       => esc_html__( 'Lazy load', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['lazy'] ? 'yes' : '',
				'description' => esc_html__( 'Defers image load', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'mask_display',
			array(
				'label'       => esc_html__( 'Mask', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['mask']['display'] ? 'yes' : '',
				'description' => esc_html__( 'Display mask on hover', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'mask_background',
			array(
				'label'              => esc_html__( 'Background', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['mask']['background'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-mask-bg: {{VALUE}};',
				),
				'condition'          => array(
					'mask_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'mask_business_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Likes and comments options are only available for professional accounts.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'mask_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'mask_likes_count',
			array(
				'label'       => esc_html__( 'Likes count', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['mask']['likes_count'] ? 'yes' : '',
				'description' => esc_html__( 'Display likes count in mask', 'insta-gallery' ),
				'condition'   => array(
					'mask_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'mask_comments_count',
			array(
				'label'       => esc_html__( 'Comments count', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['mask']['comments_count'] ? 'yes' : '',
				'description' => esc_html__( 'Display comments count in mask', 'insta-gallery' ),
				'condition'   => array(
					'mask_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'mask_icon_color',
			array(
				'label'              => esc_html__( 'Icons color', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['mask']['icon_color'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-mask-icon-color: {{VALUE}};',
				),
				'condition'          => array(
					'mask_display'     => 'yes',
					'mask_likes_count' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// CARD SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_card',
			array(
				'label' => esc_html__( 'Card', 'insta-gallery' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_display',
			array(
				'label'       => esc_html__( 'Display', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['card']['display'] ? 'yes' : '',
				'description' => esc_html__( 'Display the Instagram item card', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'card_business_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Likes and comments options are only available for professional accounts.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'card_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'card_radius',
			array(
				'label'              => esc_html__( 'Radius', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'               => Controls_Manager::SLIDER,
				'default'            => array(
					'size' => $feed_defaults['card']['radius'],
					'unit' => 'px',
				),
				'size_units'         => array( 'px', '%' ),
				'range'              => array(
					'px' => array(
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					),
				),
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' =>
						'--qligg-card-radius: {{SIZE}}{{UNIT}};',
				),

				'condition'          => array(
					'card_display' => 'yes',
				),
			)
);

	$this->add_control(
		'card_font_size',
		array(
			'label'              => esc_html__( 'Font size', 'insta-gallery' ),
			'type'               => Controls_Manager::SLIDER,
			'default'            => array(
				'size' => $feed_defaults['card']['font_size'],
				'unit' => 'px',
			),
			'size_units'         => array( 'px' ),
			'range'              => array(
				'px' => array(
					'min'  => 8,
					'max'  => 36,
					'step' => 1,
				),
			),
			'frontend_available' => true,
			'selectors'          => array(
				'{{WRAPPER}} .instagram-gallery-feed' =>
					'--qligg-card-font-size: {{SIZE}}{{UNIT}};',
			),
			'condition'          => array(
				'card_display' => 'yes',
			),
		)
);

		$this->add_control(
			'card_background',
			array(
				'label'              => esc_html__( 'Background', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['card']['background'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-card-bg: {{VALUE}};',
				),
				'condition'          => array(
					'card_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'card_background_hover',
			array(
				'label'              => esc_html__( 'Background hover', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['card']['background_hover'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-card-bg-hover: {{VALUE}};',
				),
				'condition'          => array(
					'card_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'card_text_color',
			array(
				'label'              => esc_html__( 'Text color', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['card']['text_color'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-card-color: {{VALUE}};',
				),
				'condition'          => array(
					'card_display' => 'yes',
				),
			)
		);

	$this->add_control(
		'card_padding',
		array(
			'label'              => esc_html__( 'Padding', 'insta-gallery' ),
			'type'               => Controls_Manager::SLIDER,
			'default'            => array(
				'size' => $feed_defaults['card']['padding'],  // ej. 5
				'unit' => 'px',
			),
			'size_units'         => array( 'px' ),
			'range'              => array(
				'px' => array(
					'min'  => 0,
					'max'  => 50,
					'step' => 1,
				),
			),

			'frontend_available' => true,
			'selectors'          => array(
				'{{WRAPPER}} .instagram-gallery-feed' =>
					'--qligg-card-padding: {{SIZE}}{{UNIT}};',
			),
			'condition'          => array(
				'card_display' => 'yes',
			),
		)
);

		$this->add_control(
			'card_likes_count',
			array(
				'label'       => esc_html__( 'Likes count', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['card']['likes_count'] ? 'yes' : '',
				'description' => esc_html__( 'Display likes count in card', 'insta-gallery' ),
				'condition'   => array(
					'card_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'card_text_length',
			array(
				'label'     => esc_html__( 'Text length', 'insta-gallery' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => $feed_defaults['card']['text_length'],
				),
				'range'     => array(
					'px' => array(
						'min'  => 5,
						'max'  => 1000,
						'step' => 5,
					),
				),
				'condition' => array(
					'card_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'card_comments_count',
			array(
				'label'       => esc_html__( 'Comments count', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['card']['comments_count'] ? 'yes' : '',
				'description' => esc_html__( 'Display comments count in card', 'insta-gallery' ),
				'condition'   => array(
					'card_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'card_text_align',
			array(
				'label'              => esc_html__( 'Text align', 'insta-gallery' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => $feed_defaults['card']['text_align'],
				'options'            => array(
					'left'   => esc_html__( 'Left', 'insta-gallery' ),
					'center' => esc_html__( 'Center', 'insta-gallery' ),
					'right'  => esc_html__( 'Right', 'insta-gallery' ),
				),
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-card-text-align: {{VALUE}};',
				),
				'condition'          => array(
					'card_display' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// PROFILE SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_profile',
			array(
				'label' => esc_html__( 'Profile', 'insta-gallery' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'profile_display',
			array(
				'label'       => esc_html__( 'Display', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['profile']['display'] ? 'yes' : '',
				'description' => esc_html__( 'Display user profile or tag info', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'profile_username',
			array(
				'label'     => esc_html__( 'Username', 'insta-gallery' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $feed_defaults['profile']['username'],
				'condition' => array(
					'profile_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'profile_avatar',
			array(
				'label'     => esc_html__( 'Avatar', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'      => Controls_Manager::MEDIA,
				'default'   => array(
					'url' => $feed_defaults['profile']['avatar'],
				),
				'condition' => array(
					'profile_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'profile_nickname',
			array(
				'label'     => esc_html__( 'Full name', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $feed_defaults['profile']['nickname'],
				'condition' => array(
					'profile_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'profile_link_text',
			array(
				'label'     => esc_html__( 'Follow text', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $feed_defaults['profile']['link_text'],
				'condition' => array(
					'profile_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'profile_website',
			array(
				'label'       => esc_html__( 'Website', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => esc_html__( 'https://your-link.com', 'insta-gallery' ),
				'default'     => array(
					'url' => $feed_defaults['profile']['website'],
				),
				'condition'   => array(
					'profile_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'profile_website_text',
			array(
				'label'     => esc_html__( 'Website link text', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $feed_defaults['profile']['website_text'],
				'condition' => array(
					'profile_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'profile_biography',
			array(
				'label'     => esc_html__( 'Biography', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'      => Controls_Manager::TEXTAREA,
				'default'   => $feed_defaults['profile']['biography'],
				'condition' => array(
					'profile_display' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// MODAL SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_modal',
			array(
				'label' => esc_html__( 'Modal', 'insta-gallery' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'modal_display',
			array(
				'label'       => esc_html__( 'Display', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['modal']['display'] ? 'yes' : '',
				'description' => esc_html__( 'Display modal by clicking on image', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'modal_business_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Likes and comments options are only available for professional accounts.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_stories_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Comments option is not available for Instagram Stories.', 'insta-gallery' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'modal_display' => 'yes',
					'source'        => 'stories',
				),
			)
		);

		$this->add_control(
			'modal_align',
			array(
				'label'     => esc_html__( 'Sidebar align', 'insta-gallery' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => $feed_defaults['modal']['modal_align'],
				'options'   => array(
					'left'   => esc_html__( 'Left', 'insta-gallery' ),
					'right'  => esc_html__( 'Right', 'insta-gallery' ),
					'bottom' => esc_html__( 'Bottom', 'insta-gallery' ),
					'top'    => esc_html__( 'Top', 'insta-gallery' ),
				),
				'condition' => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_profile',
			array(
				'label'       => esc_html__( 'Profile', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['modal']['profile'] ? 'yes' : '',
				'description' => esc_html__( 'Display user profile or tag info', 'insta-gallery' ),
				'condition'   => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_likes_count',
			array(
				'label'       => esc_html__( 'Show likes count', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['modal']['likes_count'] ? 'yes' : '',
				'description' => esc_html__( 'Display likes count in sidebar', 'insta-gallery' ),
				'condition'   => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_comments_count',
			array(
				'label'       => esc_html__( 'Show comments count', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['modal']['comments_count'] ? 'yes' : '',
				'description' => esc_html__( 'Display comments count in sidebar', 'insta-gallery' ),
				'condition'   => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_media_description',
			array(
				'label'       => esc_html__( 'Show description', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['modal']['media_description'] ? 'yes' : '',
				'description' => esc_html__( 'Display description in the sidebar', 'insta-gallery' ),
				'condition'   => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_comments_list',
			array(
				'label'       => esc_html__( 'Show comments list', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['modal']['comments_list'] ? 'yes' : '',
				'description' => esc_html__( 'Display comments list in sidebar', 'insta-gallery' ),
				'condition'   => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_text_align',
			array(
				'label'              => esc_html__( 'Text align', 'insta-gallery' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => $feed_defaults['modal']['text_align'],
				'options'            => array(
					'left'   => esc_html__( 'Left', 'insta-gallery' ),
					'center' => esc_html__( 'Center', 'insta-gallery' ),
					'right'  => esc_html__( 'Right', 'insta-gallery' ),
				),
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-modal-text-align: {{VALUE}};',
				),
				'condition'          => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_font_size',
			array(
				'label'              => esc_html__( 'Font size', 'insta-gallery' ),
				'type'               => Controls_Manager::SLIDER,
				'default'            => array(
					'size' => $feed_defaults['modal']['font_size'],
					'unit' => 'px',
				),

				'size_units'         => array( 'px' ),
				'range'              => array(
					'px' => array(
						'min'  => 8,
						'max'  => 36,
						'step' => 1,
					),
				),
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' =>
						'--qligg-modal-font-size: {{SIZE}}{{UNIT}};',
				),

				'condition'          => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'modal_text_length',
			array(
				'label'     => esc_html__( 'Text length', 'insta-gallery' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => $feed_defaults['modal']['text_length'],
				),
				'range'     => array(
					'px' => array(
						'min'  => 10,
						'max'  => 50000,
						'step' => 10,
					),
				),
				'condition' => array(
					'modal_display' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// BOX SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_box',
			array(
				'label' => esc_html__( 'Box', 'insta-gallery' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'box_display',
			array(
				'label'       => esc_html__( 'Display', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['box']['display'] ? 'yes' : '',
				'description' => esc_html__( 'Display the Instagram Feed inside a customizable box', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'box_padding',
			array(
				'label'              => esc_html__( 'Padding', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'               => Controls_Manager::SLIDER,
				'default'            => array(
					'size' => $feed_defaults['box']['padding'],
					'unit' => 'px',
				),
				'size_units'         => array( 'px' ),
				'range'              => array(
					'px' => array(
						'min'  => 0,
						'max'  => 300,
						'step' => 1,
					),
				),
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' =>
						'--qligg-box-padding: {{SIZE}}{{UNIT}};',
				),
				'condition'          => array(
					'box_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'box_radius',
			array(
				'label'              => esc_html__( 'Radius', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'               => Controls_Manager::SLIDER,
				'default'            => array(
					'size' => $feed_defaults['box']['radius'],
					'unit' => 'px',
				),
				'size_units'         => array( 'px' ),
				'range'              => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1000,
						'step' => 1,
					),
				),
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' =>
						'--qligg-box-radius: {{SIZE}}{{UNIT}};',
				),
				'condition'          => array(
					'box_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'box_background',
			array(
				'label'              => esc_html__( 'Background', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['box']['background'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-box-bg: {{VALUE}};',
				),
				'condition'          => array(
					'box_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'box_text_color',
			array(
				'label'              => esc_html__( 'Text color', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['box']['text_color'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-box-color: {{VALUE}};',
				),
				'condition'          => array(
					'box_display' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// BUTTON SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_button',
			array(
				'label' => esc_html__( 'Button', 'insta-gallery' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'button_display',
			array(
				'label'       => esc_html__( 'Display', 'insta-gallery' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['button']['display'] ? 'yes' : '',
				'description' => esc_html__( 'Display the button to open Instagram site link', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'     => esc_html__( 'Text', 'insta-gallery' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $feed_defaults['button']['text'],
				'condition' => array(
					'button_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'              => esc_html__( 'Text color', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['button']['text_color'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-button-color: {{VALUE}};',
				),
				'condition'          => array(
					'button_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_background',
			array(
				'label'              => esc_html__( 'Background', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['button']['background'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-button-bg: {{VALUE}};',
				),
				'condition'          => array(
					'button_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_background_hover',
			array(
				'label'              => esc_html__( 'Background hover', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['button']['background_hover'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-button-bg-hover: {{VALUE}};',
				),
				'condition'          => array(
					'button_display' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// BUTTON LOAD MORE SECTION
		// -------------------------------------------------------------------------
		$this->start_controls_section(
			'section_button_load',
			array(
				'label' => esc_html__( 'Button Load More', 'insta-gallery' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'button_load_display',
			array(
				'label'       => esc_html__( 'Display', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => $feed_defaults['button_load']['display'] ? 'yes' : '',
				'description' => esc_html__( 'Display the button to load more videos', 'insta-gallery' ),
			)
		);

		$this->add_control(
			'button_load_text',
			array(
				'label'     => esc_html__( 'Text', 'insta-gallery' ) . ( ! defined( 'QLIGG_PREMIUM' ) ? ' (' . esc_html__( 'Premium', 'insta-gallery' ) . ')' : '' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $feed_defaults['button_load']['text'],
				'condition' => array(
					'button_load_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_load_text_color',
			array(
				'label'              => esc_html__( 'Text color', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['button_load']['text_color'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-load-button-color: {{VALUE}};',
				),
				'condition'          => array(
					'button_load_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_load_background',
			array(
				'label'              => esc_html__( 'Background', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['button_load']['background'],

				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-load-button-bg: {{VALUE}};',
				),
				'condition'          => array(
					'button_load_display' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_load_background_hover',
			array(
				'label'              => esc_html__( 'Background hover', 'insta-gallery' ),
				'type'               => Controls_Manager::COLOR,
				'default'            => $feed_defaults['button_load']['background_hover'],
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}} .instagram-gallery-feed' => '--qligg-load-button-bg-hover: {{VALUE}};',
				),
				'condition'          => array(
					'button_load_display' => 'yes',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Convert elementor settings to feed settings
	 */
	protected function get_feed_settings( $settings ) {
		$feed = array();

		$feed['account_id'] = $settings['account_id'] ?? '';
		$feed['source']     = $settings['source'] ?? 'username';
		$feed['tag']        = $settings['tag'] ?? 'WordPress';
		$feed['order_by']   = $settings['order_by'] ?? 'top_media';
		$feed['layout']     = $settings['layout_type'] ?? 'gallery';
		$feed['limit']      = $settings['limit']['size'] ?? 12;
		$feed['lazy']       = ! empty( $settings['lazy'] );

		$feed['columns'] = $settings['desktop_columns']['size'] ?? 3;
		$feed['spacing'] = $settings['desktop_spacing']['size'] ?? 10;

		$feed['reel'] = array(
			'hide' => ! empty( $settings['reel_hide'] ),
		);

		$feed['copyright'] = array(
			'hide'        => ! empty( $settings['copyright_hide'] ),
			'placeholder' => $settings['copyright_placeholder'] ?? '',
		);

		$feed['aspect_ratio'] = array(
			'width'  => isset( $settings['aspect_ratio_width']['size'] ) ? max( 1, min( 100, (int) $settings['aspect_ratio_width']['size'] ) ) : 1,
			'height' => isset( $settings['aspect_ratio_height']['size'] ) ? max( 1, min( 100, (int) $settings['aspect_ratio_height']['size'] ) ) : 1,
		);

		$feed['responsive'] = array(
			'desktop'     => array(
				'columns' => $settings['desktop_columns']['size'] ?? 3,
				'spacing' => $settings['desktop_spacing']['size'] ?? 10,
			),
			'tablet'      => array(
				'columns' => $settings['tablet_columns']['size'] ?? 2,
				'spacing' => $settings['tablet_spacing']['size'] ?? 8,
			),
			'mobile'      => array(
				'columns' => $settings['mobile_columns']['size'] ?? 1,
				'spacing' => $settings['mobile_spacing']['size'] ?? 6,
			),
			'breakpoints' => array(
				'tablet' => $settings['tablet_breakpoint']['size'] ?? 768,
				'mobile' => $settings['mobile_breakpoint']['size'] ?? 480,
			),
		);

		$feed['highlight'] = array(
			'tag'      => $settings['highlight_tag'] ?? '',
			'id'       => $settings['highlight_id'] ?? '',
			'position' => $settings['highlight_position'] ?? '1,3,5',
		);

		$feed['mask'] = array(
			'display'        => ! empty( $settings['mask_display'] ),
			'background'     => $settings['mask_background'] ?? '#000000',
			'likes_count'    => ! empty( $settings['mask_likes_count'] ),
			'comments_count' => ! empty( $settings['mask_comments_count'] ),
			'icon_color'     => $settings['mask_icon_color'] ?? '#ffffff',
		);

		$feed['profile'] = array(
			'display'      => ! empty( $settings['profile_display'] ),
			'username'     => $settings['profile_username'] ?? '',
			'nickname'     => $settings['profile_nickname'] ?? '',
			'link_text'    => $settings['profile_link_text'] ?? 'Follow',
			'website'      => $settings['profile_website']['url'] ?? '',
			'website_text' => $settings['profile_website_text'] ?? 'Website',
			'biography'    => $settings['profile_biography'] ?? '',
			'avatar'       => $settings['profile_avatar']['url'] ?? '',
		);

		$feed['card'] = array(
			'display'          => ! empty( $settings['card_display'] ),
			'radius'           => $settings['card_radius']['size'] ?? 1,
			'font_size'        => $settings['card_font_size']['size'] ?? 12,
			'background'       => $settings['card_background'] ?? '#ffffff',
			'background_hover' => $settings['card_background_hover'] ?? '',
			'text_color'       => $settings['card_text_color'] ?? '#000000',
			'padding'          => $settings['card_padding']['size'] ?? 5,
			'likes_count'      => ! empty( $settings['card_likes_count'] ),
			'text_length'      => $settings['card_text_length']['size'] ?? 10,
			'comments_count'   => ! empty( $settings['card_comments_count'] ),
			'text_align'       => $settings['card_text_align'] ?? 'left',
		);

		$feed['modal'] = array(
			'display'           => ! empty( $settings['modal_display'] ),
			'profile'           => ! empty( $settings['modal_profile'] ),
			'likes_count'       => ! empty( $settings['modal_likes_count'] ),
			'comments_count'    => ! empty( $settings['modal_comments_count'] ),
			'media_description' => ! empty( $settings['modal_media_description'] ),
			'comments_list'     => ! empty( $settings['modal_comments_list'] ),
			'text_align'        => $settings['modal_text_align'] ?? 'left',
			'modal_align'       => $settings['modal_align'] ?? 'right',
			'text_length'       => $settings['modal_text_length']['size'] ?? 10000,
			'font_size'         => $settings['modal_font_size']['size'] ?? 12,
		);

		$feed['box'] = array(
			'display'    => ! empty( $settings['box_display'] ),
			'padding'    => $settings['box_padding']['size'] ?? 1,
			'radius'     => $settings['box_radius']['size'] ?? 0,
			'background' => $settings['box_background'] ?? '#fefefe',
			'text_color' => $settings['box_text_color'] ?? '#000000',
			'profile'    => ! empty( $settings['box_profile'] ),
			'desc'       => $settings['box_desc'] ?? '',
		);

		$feed['carousel'] = array(
			'centered_slides'   => ! empty( $settings['carousel_centered_slides'] ),
			'autoplay'          => ! empty( $settings['carousel_autoplay'] ),
			'autoplay_interval' => $settings['carousel_autoplay_interval']['size'] ?? 3000,
			'navarrows'         => ! empty( $settings['carousel_navarrows'] ),
			'navarrows_color'   => $settings['carousel_navarrows_color'] ?? '',
			'pagination'        => ! empty( $settings['carousel_pagination'] ),
			'pagination_color'  => $settings['carousel_pagination_color'] ?? '',
		);

		$feed['button'] = array(
			'display'          => ! empty( $settings['button_display'] ),
			'text'             => $settings['button_text'] ?? 'View on Instagram',
			'text_color'       => $settings['button_text_color'] ?? '#ffff',
			'background'       => $settings['button_background'] ?? '',
			'background_hover' => $settings['button_background_hover'] ?? '',
		);

		$feed['button_load'] = array(
			'display'          => ! empty( $settings['button_load_display'] ),
			'text'             => $settings['button_load_text'] ?? 'Load more...',
			'text_color'       => $settings['button_load_text_color'] ?? '#ffff',
			'background'       => $settings['button_load_background'] ?? '',
			'background_hover' => $settings['button_load_background_hover'] ?? '',
		);

		return $feed;
	}

	/**
	 * Render widget output on the frontend
	 */
	protected function render() {
		$settings            = $this->get_settings_for_display();
		$feed_settings       = $this->get_feed_settings( $settings );
		$id                  = 'elementor-instagram-' . $this->get_id();
		$feed_settings['id'] = $id;
		echo Frontend::instance()->create_shortcode( $feed_settings, $id );

		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
			echo '<script>if (typeof qliggFrontend !== "undefined") qliggFrontend.init();</script>';
		}
	}
}
