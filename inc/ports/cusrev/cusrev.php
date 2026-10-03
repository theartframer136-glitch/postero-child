<?php
/**
 * Customer Reviews for WooCommerce 5.123.0: what the plugin's main files
 * (ivole.php and class-ivole.php) set up on this site, for
 * inc/ports/customer-reviews.php. Loaded only while the plugin is inactive.
 *
 * Names: the classes keep the plugin's names (CR_Reviews, CR_Tags, ...). The
 * plugin declares each of them inside if ( ! class_exists() ), so when the
 * plugin is switched back on, the one request that activates it (which loads
 * the plugin while this file is already loaded) skips them instead of failing.
 * The plugin's own functions (cusrev_init(), ivole_reviews_shortcode(), ...)
 * and its class Ivole are declared WITHOUT such a check, so here they get an
 * af_cusrev_ name and Ivole is not declared at all: reusing those names would
 * make activating the plugin die with "Cannot redeclare".
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/emails/class-cr-sender.php';
require_once __DIR__ . '/includes/reviews/class-cr-reviews.php';
require_once __DIR__ . '/includes/reviews/class-cr-custom-questions.php';
require_once __DIR__ . '/includes/reviews/class-cr-reviews-media-download.php';
require_once __DIR__ . '/includes/blocks/class-cr-all-reviews.php';
require_once __DIR__ . '/includes/blocks/class-cr-reviews-grid.php';
require_once __DIR__ . '/includes/google/class-cr-structured-data.php';
require_once __DIR__ . '/includes/tags/class-cr-tags.php';            // the plugin's file, unchanged
require_once __DIR__ . '/includes/qna/class-cr-qna.php';
require_once __DIR__ . '/includes/misc/class-cr-checkout.php';
require_once __DIR__ . '/includes/settings/class-cr-settings-review-reminder.php';
require_once __DIR__ . '/class-cr-referrals.php';

/* ivole.php: the plugin hooks in only when WooCommerce is active (same test). */
$af_cusrev_activated_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
if (
	in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||
	( is_multisite() && isset( $af_cusrev_activated_plugins['woocommerce/woocommerce.php'] ) )
) {
	add_action( 'init', 'af_cusrev_init', 9 );                          // ivole.php: cusrev_init
	add_action( 'woocommerce_init', 'af_cusrev_woocommerce_init' );    // ivole.php: cr_woocommerce_init
}
unset( $af_cusrev_activated_plugins );

/* ivole.php: [cusrev_reviews], registered whether or not WooCommerce is active. */
add_shortcode( 'cusrev_reviews', 'af_cusrev_reviews_shortcode' );

/**
 * ivole.php cusrev_init() at init:9, then new Ivole(): the objects Ivole's
 * constructor creates, in the same order, limited to the ones that do
 * something with the default settings. Left out of cusrev_init():
 * load_plugin_textdomain() (the plugin's languages folder is not copied; the
 * site runs in English) and the staging-site lock (ivole_siteurl is already
 * set, so it did nothing).
 */
function af_cusrev_init() {
	if ( function_exists( 'wc' ) ) {
		new CR_Sender();
		new CR_Reviews();
		new CR_Referrals();
		new CR_StructuredData();
		new CR_Tags();
		new CR_Qna();
		// CR_Trust_Badge::__construct() (CR_Reviews_Grid's constructor adds the
		// same two callbacks again, which WordPress ignores as duplicates).
		add_action( 'init', array( 'CR_Reviews_Grid', 'cr_register_blocks_script' ) );
		add_action( 'enqueue_block_assets', 'af_cusrev_trust_badge_block_style' );
		add_action( 'enqueue_block_assets', array( 'CR_Reviews_Grid', 'cr_enqueue_block_scripts' ) );
		new CR_Reviews_Media_Download();
		new CR_All_Reviews();
	}
}

/** ivole.php cr_woocommerce_init() at woocommerce_init. */
function af_cusrev_woocommerce_init() {
	$cr_checkout = new CR_Checkout();
}

/**
 * Stand-in for the plugin's "cusrev/trust-badge" block. Its block.json names
 * the style "cr-badges-css", and WordPress (wp_enqueue_registered_block_scripts_and_styles(),
 * which runs at enqueue_block_assets:10 before the plugin's own callback)
 * enqueues the style of every registered block on every page unless block
 * assets load on demand. That is how cr-badges-css reached every page of this
 * classic theme. Enqueued here, before CR_Reviews_Grid::cr_enqueue_block_scripts()
 * registers the handle, it lands in the same place in the queue. The block
 * itself (editor-only here: it is not used in any content) is not ported.
 */
function af_cusrev_trust_badge_block_style() {
	if ( function_exists( 'wp_should_load_block_assets_on_demand' ) && wp_should_load_block_assets_on_demand() ) {
		return;
	}
	wp_enqueue_style( 'cr-badges-css' );
}

/** ivole.php ivole_reviews_shortcode(), unchanged but for the name of the helper. */
function af_cusrev_reviews_shortcode( $atts, $content ) {
	extract( shortcode_atts( array( 'comment_file' => '/comments.php' ), $atts ) );
	$content = af_cusrev_return_comment_form( $comment_file );
	return $content;
}

/** ivole.php ivole_return_comment_form(), unchanged. */
function af_cusrev_return_comment_form( $comment_file )
{
	if ( 0 !== validate_file( wp_normalize_path( $comment_file ) ) ) {
		return '';
	}
	ob_start();
	comments_template( $comment_file );
	$form = ob_get_contents();
	ob_end_clean();
	return $form;
}

/**
 * includes/reviews/class-cr-reviews-list-table.php CR_Reviews_List_Table::get_shop_page(),
 * unchanged but for the indentation, for CR_Reviews::cr_review_is_from_verified_owner()
 * (reached only for reviews of the shop page, a CusRev feature). That class
 * (an admin list table, declared by the plugin without a class_exists() check)
 * is not ported.
 */
function af_cusrev_get_shop_page() {
	// normally WooCommerce has only one shop page
	// however, translation plugins can create additional translated version of the main shop page
	$shop_pages = array();
	$shop_page_id = intval( wc_get_page_id( 'shop' ) );
	if( $shop_page_id > 0 ) {
		$shop_pages = array( $shop_page_id );
		// Polylang integration
		if( function_exists( 'pll_get_post_translations' ) ) {
			$translated_shop_page_ids = pll_get_post_translations( $shop_page_id );
			if( $translated_shop_page_ids && is_array( $translated_shop_page_ids ) && count( $translated_shop_page_ids ) > 0 ) {
				$shop_pages = array_map( 'intval', $translated_shop_page_ids );
			}
		} else {
			// WPML integration
			if( has_filter( 'wpml_object_id' ) ) {
				$trid = apply_filters( 'wpml_element_trid', NULL, $shop_page_id, 'post_page' );
				if( $trid ) {
					$translations = apply_filters( 'wpml_get_element_translations', NULL, $trid, 'post_page' );
					if( $translations && is_array( $translations ) && count( $translations ) > 0 ) {
						$translated_shop_page_ids = array();
						foreach ($translations as $translation) {
							if( isset( $translation->element_id ) ) {
								$translated_shop_page_ids[] = intval( $translation->element_id );
							}
						}
						if( count( $translated_shop_page_ids ) > 0 ) {
							$shop_pages = $translated_shop_page_ids;
						}
					}
				}
			}
		}
	}
	return $shop_pages;
}
