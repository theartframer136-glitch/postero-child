<?php
/**
 * "Shop by Deity & Vastu": a plain, server-rendered row of links to the
 * devotional and Vastu categories, on every page that sells.
 *
 * Measured in Search Console on 15 Oct 2026, the day after the category
 * titles went live:
 *
 *     seven-horses      URL is on Google
 *     lord-shiva        Crawled, currently not indexed — no referring sitemap;
 *                       referring pages: a faceted /shop/ URL and pichwai-art
 *     lakshmi-ganesha   URL is unknown to Google — referring page: none
 *
 * So the deity categories were orphans. The header's CATEGORIES menu lists
 * the top level only, the sidebar widget nests the children behind a "+"
 * that opens client-side, and nothing else on the site links to them. A
 * title cannot rank a page Google never reaches.
 *
 * WHERE THE ROW GOES, all server-rendered so a crawler sees it:
 *   - shop page and every product-category archive: under the heading and
 *     the intro (woocommerce_archive_description at 15, after the H1 at 5
 *     and WooCommerce's own description at 10). The shop page is an
 *     Elementor page whose template may not fire that hook, so it also
 *     gets the row appended to its content;
 *   - every product page: after the summary, through af_section() so the
 *     quick-view modal gets it too;
 *   - the front page and every post: appended to the content.
 * Cart, checkout, account and the other noindexed pages get nothing.
 *
 * ONE ROW PER REQUEST, whichever hook reaches it first. The current
 * category is marked aria-current and styled, never linked to itself.
 *
 * Terms that do not exist on the site are skipped, so the list can name a
 * category before it is created.
 */
if (!defined('ABSPATH')) exit;

/** slug => label, in display order. Labels are what a US shopper types. */
function af_deity_link_terms() {
    $terms = array(
        'hindu-deities'   => 'Hindu Gods',
        'radha-krishna'   => 'Radha Krishna',
        'lakshmi-ganesha' => 'Lakshmi Ganesh',
        'lord-shiva'      => 'Lord Shiva',
        'seven-horses'    => '7 Horses (Vastu)',
        'vaastu-art'      => 'Vastu Art',
        'tirupati-balaji' => 'Tirupati Balaji',
        'lord-rama'       => 'Lord Ram',
        'buddha'          => 'Buddha',
        'sikh-art'        => 'Sikh Art',
        'swaminarayan'    => 'Swaminarayan',
        'pichwai-art'     => 'Pichwai',
        'indian-culture'  => 'Indian Culture',
    );
    return (array) apply_filters('af_deity_link_terms', $terms);
}

/**
 * A second row, "Shop Canvas & Frames": the canvas and frame categories,
 * labelled with the words US shoppers search (Search Console, US, 14-27 Oct
 * 2026: "floating frame" #51, "floating framed canvas" #48, "canvas frames"
 * #50, "rolled canvas art" #43, "abstract canvas art" #54). Like the deity
 * categories before 15 Oct, these were linked from almost nowhere but the
 * menu: Google last visited Abstract Art and Rolled Canvas on 7-8 Oct, so it
 * had not yet seen the 17 Oct Abstract Art guide at all.
 *
 * Rolled Canvas is left out: on 29 Oct it held four blank canvas rolls at
 * $0, not the rolled art prints that search means. A category with no
 * products (Framed Canvas Prints, Extra Large Canvas on 29 Oct) is skipped
 * by af_term_has_products(), and shows up by itself once it has some.
 */
function af_canvas_link_terms() {
    $terms = array(
        'abstract-art'                 => 'Abstract Canvas Art',
        'extra-large-canvas-paintings' => 'Extra Large Canvas',
        'standard-framed-canvas'       => 'Framed Canvas Prints',
        'diy-floating-frames'          => 'Floating Frames for Canvas',
        'premium-aluminium-frames'     => 'Aluminium Canvas Frames',
        'canvas-stretcher-bars'        => 'Canvas Stretcher Bars',
    );
    return (array) apply_filters('af_canvas_link_terms', $terms);
}

/** Whether a product category, or any category under it, has products to show. */
function af_term_has_products($term) {
    if (!$term || is_wp_error($term)) return false;
    if ((int) $term->count > 0) return true;
    $kids = get_terms(array('taxonomy' => 'product_cat', 'child_of' => (int) $term->term_id, 'hide_empty' => true, 'fields' => 'ids', 'number' => 1));
    return !is_wp_error($kids) && !empty($kids);
}

/** Printed once per request, whichever hook reaches it first. */
function af_deity_links_printed($set = false) {
    static $done = false;
    if ($set) $done = true;
    return $done;
}

function af_deity_links_css() {
    static $printed = false;
    if ($printed) return '';
    $printed = true;
    return '<style id="af-deity-links">'
        . '.af-deity-nav{margin:18px 0 24px;clear:both}'
        . '.af-deity-nav h2{font-size:14px;letter-spacing:.08em;text-transform:uppercase;color:#1a1a1a;margin:0 0 10px;font-weight:700}'
        . '.af-deity-nav ul{list-style:none;display:flex;flex-wrap:wrap;gap:8px;padding:0;margin:0}'
        . '.af-deity-nav li{margin:0;padding:0}'
        . '.af-deity-nav a,.af-deity-nav span{display:inline-block;padding:7px 14px;border:1px solid #d9c89a;border-radius:999px;'
        . 'color:#1a1a1a;text-decoration:none;font-size:14px;line-height:1.3;background:#fff}'
        . '.af-deity-nav a:hover{background:#a8872e;border-color:#a8872e;color:#fff}'
        . '.af-deity-nav span[aria-current]{background:#1a1a1a;border-color:#1a1a1a;color:#fff}'
        . '</style>';
}

/**
 * Both rows' HTML (deity & Vastu, then canvas & frames), or '' when neither
 * has two categories that resolve.
 *
 * @param int $current_term_id  the category being viewed, marked not linked
 */
function af_deity_links_html($current_term_id = 0) {
    $rows = af_link_row_html(af_deity_link_terms(), $current_term_id, 'Shop by deity and Vastu', 'Shop by Deity &amp; Vastu')
          . af_link_row_html(af_canvas_link_terms(), $current_term_id, 'Shop canvas and frames', 'Shop Canvas &amp; Frames');
    return $rows === '' ? '' : af_deity_links_css() . $rows;
}

/** One row of category links, or '' when fewer than two categories resolve. */
function af_link_row_html(array $terms, $current_term_id, $aria, $heading) {
    try {
        $items = array();
        foreach ($terms as $slug => $label) {
            $term = get_term_by('slug', $slug, 'product_cat');
            if (!$term || is_wp_error($term) || !af_term_has_products($term)) continue;
            $link = get_term_link($term, 'product_cat');
            if (is_wp_error($link)) continue;
            if ((int) $term->term_id === (int) $current_term_id) {
                $items[] = '<li><span aria-current="page">' . esc_html($label) . '</span></li>';
            } else {
                $items[] = '<li><a href="' . esc_url($link) . '">' . esc_html($label) . '</a></li>';
            }
        }
        if (count($items) < 2) return '';
        return '<nav class="af-deity-nav" aria-label="' . esc_attr($aria) . '">'
            . '<h2>' . $heading . '</h2><ul>' . implode('', $items) . '</ul></nav>';
    } catch (\Throwable $e) {
        return '';
    }
}

/** Print once, if not printed yet. */
function af_deity_links_print($current_term_id = 0) {
    if (af_deity_links_printed()) return;
    $html = af_deity_links_html($current_term_id);
    if ($html === '') return;
    af_deity_links_printed(true);
    echo $html;
}

/** Pages that must never carry the row. */
function af_deity_links_excluded() {
    try {
        if (is_admin() || is_feed() || is_404()) return true;
        if (function_exists('is_cart') && is_cart()) return true;
        if (function_exists('is_checkout') && is_checkout()) return true;
        if (function_exists('is_account_page') && is_account_page()) return true;
        if (function_exists('af_noindex_current') && af_noindex_current()) return true;
    } catch (\Throwable $e) {
    }
    return false;
}

// Shop page and product-category archives: after the heading (5) and the
// category description (10).
add_action('woocommerce_archive_description', function () {
    try {
        if (af_deity_links_excluded()) return;
        $is_shop = function_exists('is_shop') && is_shop();
        $is_cat  = function_exists('is_product_category') && is_product_category();
        if (!$is_shop && !$is_cat) return;
        $term = $is_cat ? get_queried_object() : null;
        af_deity_links_print(($term && isset($term->term_id)) ? (int) $term->term_id : 0);
    } catch (\Throwable $e) {
    }
}, 15);

// Product pages, and the quick-view modal through af_section().
$af_deity_product_section = function () {
    try {
        if (af_deity_links_excluded()) return;
        if (!function_exists('is_product') || !is_product()) return;
        af_deity_links_print(0);
    } catch (\Throwable $e) {
    }
};
if (function_exists('af_section')) {
    af_section($af_deity_product_section, 12);
} else {
    add_action('woocommerce_after_single_product_summary', $af_deity_product_section, 12);
}
unset($af_deity_product_section);

// Front page, shop page (Elementor-built, in case the archive hook never
// fires there) and single posts: appended to the content. Same guards as
// inc/page-headings.php: only the main content of the page being viewed,
// only after <head> is done, never inside an excerpt. Elementor replaces the
// content at priority 9; 20 runs after it.
add_filter('the_content', function ($content) {
    try {
        if (af_deity_links_printed()) return $content;
        if (!did_action('wp_head') || doing_action('wp_head')) return $content;
        if (doing_filter('get_the_excerpt')) return $content;
        if (!in_the_loop() || !is_main_query()) return $content;
        if ((int) get_the_ID() !== (int) get_queried_object_id()) return $content;
        if (af_deity_links_excluded()) return $content;
        $wanted = is_front_page()
            || is_singular('post')
            || (function_exists('is_shop') && is_shop());
        if (!$wanted) return $content;
        $html = af_deity_links_html(0);
        if ($html === '') return $content;
        af_deity_links_printed(true);
        return $content . $html;
    } catch (\Throwable $e) {
        return $content;
    }
}, 20);
