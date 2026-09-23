<?php
/**
 * One H1 on the pages that had none.
 *
 * DEF-09. Measured before writing a line of this, because the report was
 * wrong about one page and adding an H1 blind would have made it worse:
 *
 *     page          H1   what it had instead
 *     category       0   nothing — the category name is not a heading at all
 *     search         0   nothing (the product-scoped search, see below)
 *     cart           0   no heading of any level
 *     login          0   an H2, "Welcome Back", doing the title's job
 *     sign-up        0   an H2, "Sign-Up", doing the title's job
 *     wishlist       1   "Your Wishlist" — ALREADY CORRECT. Left alone.
 *     shop (control) 1   "All Our Wall Art", an Elementor heading
 *
 * The report lists the wishlist among the six. It has exactly one H1 that is
 * read, and an H1 added here would give it two — the report's own SEO-06
 * case. So it is not touched.
 *
 * WHY THE CATEGORY AND THE SEARCH ARE ONE BUG.
 * search.php already prints "Results for …" as an H1. But the report's URL is
 * /?s=…&post_type=product, and a search scoped to products is taken over by
 * WooCommerce's template loader and rendered with the product ARCHIVE, the
 * same Postero layout the category uses. That layout drops WooCommerce's own
 * <h1 class="page-title">; the shop page only has one because its Elementor
 * template adds "All Our Wall Art". Category and product search get neither.
 *
 * WHERE THE ARCHIVE HEADING GOES. Postero's archive does not fire
 * woocommerce_before_shop_loop (functions.php:19656 works around exactly that
 * client-side). It does fire woocommerce_archive_description: inc/gold-foil.php
 * records a paragraph printed on it appearing on the category page. So the
 * H1 is printed there, at priority 5 — ahead of WooCommerce's own category
 * description at 10, where a page's title belongs. Server-rendered, which
 * matters: a heading injected by JavaScript is weaker for search and arrives
 * late for a screen reader.
 *
 * VISIBLE OR NOT, per page, and why:
 *
 *   category, search, cart — VISIBLE. None of them shows any title, so a
 *     visible one duplicates nothing, and on a category page it is the
 *     ranking signal the report is about. It borrows search.php's own
 *     .af-sr-title treatment so the three read as one family.
 *
 *   login, sign-up — VISUALLY HIDDEN. Both already show their title, as an
 *     H2 inside Elementor content this repository cannot edit. A second
 *     visible title stacked on "Welcome Back" would be worse than the defect.
 *     A visually-hidden H1 is the standard accessibility pattern: screen
 *     readers announce it, search engines read it, and the page looks the
 *     same. Promoting the existing H2 would be better still, and is an
 *     Elementor edit for the owner — noted rather than attempted.
 */
if (!defined('ABSPATH')) exit;

/** Printed once per request, whichever path reaches it first. */
function af_heading_printed($set = false) {
    static $done = false;
    if ($set) $done = true;
    return $done;
}

/** The small amount of CSS both kinds of heading need, printed once. */
function af_heading_css() {
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    // .af-archive-title mirrors search.php's .af-sr-title, so the category,
    // the search and the cart read as the same kind of page heading.
    // .af-sr-only is the standard visually-hidden pattern; its own name so it
    // cannot depend on whether the parent theme defines .screen-reader-text.
    return '<style id="af-page-headings">'
        . '.af-archive-title{font-size:clamp(24px,3vw,34px);font-weight:800;color:#1a1a1a;'
        . 'line-height:1.2;margin:0 0 18px;padding-bottom:12px;position:relative;}'
        . '.af-archive-title::after{content:"";position:absolute;left:0;bottom:0;width:64px;'
        . 'height:4px;border-radius:2px;background:#a8872e;}'
        . '.af-archive-title em{font-style:normal;color:#a8872e;}'
        . '.af-sr-only{position:absolute!important;width:1px!important;height:1px!important;'
        . 'padding:0!important;margin:-1px!important;overflow:hidden!important;'
        . 'clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important;}'
        . '</style>';
}

/**
 * The archive heading: product categories, tags and attributes, and the
 * product-scoped search. Not the shop page itself, which already has one.
 */
add_action('woocommerce_archive_description', function () {
    try {
        if (af_heading_printed()) return;
        if (!function_exists('is_product_taxonomy')) return;

        $html = '';
        if (is_product_taxonomy()) {
            $term = get_queried_object();
            $name = ($term && isset($term->name)) ? trim(wp_strip_all_tags($term->name)) : '';
            if ($name === '') return;
            $html = '<h1 class="af-archive-title">' . esc_html($name) . '</h1>';
        } elseif (is_search()) {
            // Not get_search_query(): search.php records that 's' comes back
            // EMPTY on this stack, and uses this resolver for that reason. A
            // heading reading 'Results for ""' would be a new defect.
            $term = function_exists('af_search_query_string')
                ? af_search_query_string($GLOBALS['wp_query'])
                : get_search_query(false);
            $term = trim((string) $term);
            $html = $term !== ''
                ? '<h1 class="af-archive-title">Results for <em>&ldquo;' . esc_html($term) . '&rdquo;</em></h1>'
                : '<h1 class="af-archive-title">Search results</h1>';
        } else {
            // The shop page, and anything else on this template: the shop
            // already carries "All Our Wall Art" from Elementor. Adding one
            // here would give it two.
            return;
        }

        af_heading_printed(true);
        echo af_heading_css() . $html;
    } catch (\Throwable $e) {
        // A missing heading is today's behaviour; an error page is not.
    }
}, 5);

/**
 * The page headings: cart, login and sign-up, prepended to the page's own
 * content.
 *
 * the_content runs many times on a page — widgets, related posts, excerpts —
 * so this only acts on the content of the page being viewed, and only once.
 * Elementor replaces content on this same filter at priority 9; running at 20
 * means the heading goes in front of whatever Elementor rendered.
 */
add_filter('the_content', function ($content) {
    try {
        if (af_heading_printed()) return $content;
        if (!is_singular('page')) return $content;
        if ((int) get_the_ID() !== (int) get_queried_object_id()) return $content;

        $html = '';
        if (function_exists('is_cart') && is_cart()) {
            $html = '<h1 class="af-archive-title">Your Cart</h1>';
        } elseif (is_page('login')) {
            $html = '<h1 class="af-sr-only">Log in to The Art Framer</h1>';
        } elseif (is_page('sign-up')) {
            $html = '<h1 class="af-sr-only">Create your Art Framer account</h1>';
        } else {
            return $content;
        }

        af_heading_printed(true);
        return af_heading_css() . $html . $content;
    } catch (\Throwable $e) {
        return $content;
    }
}, 20);
