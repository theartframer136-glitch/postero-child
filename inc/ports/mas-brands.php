<?php
/**
 * MAS Brands for WooCommerce 1.1.0 -> theme code (shim).
 *
 * Live settings (read 3 Oct 2026): brand taxonomy option 'pa_artists' (that
 * attribute no longer exists, so no brand markup is ever printed), plugin
 * stylesheet on, no widgets placed, no shortcodes used. But the parent theme
 * calls Mas_WC_Brands()->get_brand_taxonomy() and mas_wcbr_* without a guard
 * (its artist widgets on the All Artists and About pages) and checks
 * class_exists('Mas_WC_Brands'). So the class, its accessor, the global and
 * the three helpers are kept verbatim, and the stylesheet still loads (copied
 * byte for byte to assets/ports/mas-brands/). The plugin's shortcodes and
 * widgets are not used anywhere on the site and are dropped.
 *
 * Only while the plugin is off (it defines MAS_WCBR_PLUGIN_FILE first).
 */
if (!defined('ABSPATH')) exit;
if (defined('MAS_WCBR_PLUGIN_FILE') || class_exists('Mas_WC_Brands', false)) return;

// Declared inside a block: a top-level class is declared when the file is
// compiled, before the early return above, and would clash with the plugin's.
if (!class_exists('Mas_WC_Brands', false)) {
final class Mas_WC_Brands {
    public $version = '1.1.0';
    protected static $_instance = null;
    public static function instance() {
        if (is_null(self::$_instance)) self::$_instance = new self();
        return self::$_instance;
    }
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    public function enqueue_scripts() {
        if ('yes' === get_option('mas_wc_brands_plugin_styles', true)) {
            wp_enqueue_style('mas-wc-brands-style', get_stylesheet_directory_uri() . '/assets/ports/mas-brands/style.css', '', $this->version);
            wp_style_add_data('mas-wc-brands-style', 'rtl', 'replace');
        }
    }
    public function get_brand_taxonomy() {
        return get_option('mas_wc_brands_brand_taxonomy');
    }
    public function get_brand_attribute() {
        return str_replace('pa_', '', $this->get_brand_taxonomy());
    }
}

}

if (!function_exists('Mas_WC_Brands')) {
    function Mas_WC_Brands() { //phpcs:ignore
        return Mas_WC_Brands::instance();
    }
}
$GLOBALS['mas_wc_brands'] = Mas_WC_Brands();

if ( ! function_exists( 'mas_wcbr_get_brand_thumbnail_url' ) ) {
	/**
	 * Helper function :: mas_wcbr_get_brand_thumbnail_url function.
	 *
	 * @param string $brand_id The ID of the brand.
	 * @param string $size     The brand thumbnail size.
	 * @return string
	 */
	function mas_wcbr_get_brand_thumbnail_url( $brand_id, $size = 'full' ) {
		$thumbnail_id = get_term_meta( $brand_id, 'thumbnail_id', true );

		if ( $thumbnail_id ) {
			$thumb_src = wp_get_attachment_image_src( $thumbnail_id, $size );
			if ( ! empty( $thumb_src ) ) {
				return current( $thumb_src );
			}
		}
	}
}

if ( ! function_exists( 'mas_wcbr_get_brand_thumbnail_image' ) ) {
	/**
	 * Helper function :: mas_wcbr_get_brand_thumbnail_image function.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_Term $brand The brand attribute.
	 * @param string  $size  The brand thumbnail size.
	 * @return string
	 */
	function mas_wcbr_get_brand_thumbnail_image( $brand, $size = '' ) {
		$thumbnail_id = get_term_meta( $brand->term_id, 'thumbnail_id', true );

		if ( '' === $size ) {
			$size = apply_filters( 'mas_wcbr_brand_thumbnail_size', 'brand-thumb' );
		}

		if ( $thumbnail_id ) {
			$image_src    = wp_get_attachment_image_src( $thumbnail_id, $size );
			$image_src    = $image_src[0];
			$dimensions   = wc_get_image_size( $size );
			$image_srcset = function_exists( 'wp_get_attachment_image_srcset' ) ? wp_get_attachment_image_srcset( $thumbnail_id, $size ) : false;
			$image_sizes  = function_exists( 'wp_get_attachment_image_sizes' ) ? wp_get_attachment_image_sizes( $thumbnail_id, $size ) : false;
		} else {
			$image_src    = wc_placeholder_img_src();
			$dimensions   = wc_get_image_size( $size );
			$image_srcset = false;
			$image_sizes  = false;
		}

		// Add responsive image markup if available.
		if ( $image_srcset && $image_sizes ) {
			$image = '<img src="' . esc_url( $image_src ) . '" alt="' . esc_attr( $brand->name ) . '" class="brand-thumbnail" width="' . esc_attr( $dimensions['width'] ) . '" height="' . esc_attr( $dimensions['height'] ) . '" srcset="' . esc_attr( $image_srcset ) . '" sizes="' . esc_attr( $image_sizes ) . '" />';
		} else {
			$image = '<img src="' . esc_url( $image_src ) . '" alt="' . esc_attr( $brand->name ) . '" class="brand-thumbnail" width="' . esc_attr( $dimensions['width'] ) . '" height="' . esc_attr( $dimensions['height'] ) . '" />';
		}

		return $image;
	}
}

if ( ! function_exists( 'mas_wcbr_get_brands' ) ) {
	/**
	 * Get all the brands. The mas_wcbr_get_brands function.
	 *
	 * @param int    $post_id (default: 0) The ID of the product.
	 * @param string $sep (default: ')     Separator for the brands.
	 * @param string $before (default: '') Prefix for the brands list.
	 * @param string $after (default: '')  Suffix for the brands list.
	 * @return void|string|false|WP_Error
	 */
	function mas_wcbr_get_brands( $post_id = 0, $sep = ', ', $before = '', $after = '' ) {
		global $post;

		$brand_taxonomy = Mas_WC_Brands()->get_brand_taxonomy();

		if ( empty( $brand_taxonomy ) ) {
			return $brand_taxonomy;
		}

		if ( ! $post_id ) {
			$post_id = $post->ID;
		}

		return get_the_term_list( $post_id, $brand_taxonomy, $before, $sep, $after );
	}
}
