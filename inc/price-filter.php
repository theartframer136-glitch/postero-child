<?php
/**
 * Price filter bounds that make sense, and an empty result with a way back.
 *
 * DEF-17. /shop/?min_price=500&max_price=10 showed no products.
 *
 * Measured live before this (tools/probe-price-filter.mjs):
 *   500 to 10, shop or category     0 products
 *   min 100, max present but blank  0 products
 *   both present but blank          35 products: only the "Price on request"
 *                                   pieces, priced 0
 *   $90,000 to $99,000              0 products (a valid range, genuinely empty)
 *   10 to 500                       532 of 570
 * Every empty page did carry WooCommerce's "No products were found matching
 * your selection." It said nothing about why, and offered no way back.
 *
 * WHY. WooCommerce reads both bounds straight from the query string
 * (WC_Query::price_filter_post_clauses) and keeps a product only when
 *     NOT (max < product.min_price OR min > product.max_price)
 * so a minimum above the maximum matches nothing, and a bound that is present
 * but blank is read as 0. The price slider always sends two good numbers, so
 * these come from a typed, edited or shared URL. They are still answered with
 * the shop, not an empty page.
 *
 * 1. The bounds are put right before WooCommerce reads them. A bound that is
 *    blank or not a number is dropped, and a minimum above the maximum is
 *    swapped. The visitor is sent (302) to the corrected URL, so the address
 *    bar, the slider and the products agree, and a shared link carries the
 *    range that was actually applied.
 *
 * 2. A range that is valid but matches nothing cannot be swapped away. There,
 *    WooCommerce's notice is replaced by one that names the range and links
 *    back: to every artwork, or to the same filters without the price. The
 *    listing is Postero's WooCommerce archive, which does fire
 *    woocommerce_no_products_found (unlike woocommerce_before_shop_loop), and
 *    the "You may also like" row printed on that hook at 30 still follows.
 *
 * Shop, product categories, tags and attribute archives only. Search has its
 * own template.
 */
if (!defined('ABSPATH')) exit;

/** True on the pages WooCommerce applies ?min_price / ?max_price to. */
function af_price_filter_on_listing() {
    return function_exists('is_shop') && (is_shop() || is_product_taxonomy());
}

/**
 * One bound as typed, cleaned: "1,000" and " $80 " become "1000" and "80".
 * Returns null for a bound that WooCommerce would misread: blank, not a
 * number, or not a single value.
 */
function af_price_filter_clean($v) {
    if (!is_scalar($v)) return null;
    $v = preg_replace('/[\s,$]/', '', (string) $v);
    if ($v === '' || !is_numeric($v)) return null;
    return $v;
}

/**
 * The query string with its price bounds put right, or null when they already
 * are. Pure, so it can be tested without WordPress.
 */
function af_price_filter_normalise(array $get) {
    $out = $get;
    $changed = false;
    foreach (array('min_price', 'max_price') as $k) {
        if (!array_key_exists($k, $out)) continue;
        $c = af_price_filter_clean($out[$k]);
        if ($c === null) {
            unset($out[$k]);
            $changed = true;
        } elseif ($c !== $out[$k]) {
            $out[$k] = $c;
            $changed = true;
        }
    }
    if (isset($out['min_price'], $out['max_price']) && (float) $out['min_price'] > (float) $out['max_price']) {
        $low = $out['max_price'];
        $out['max_price'] = $out['min_price'];
        $out['min_price'] = $low;
        $changed = true;
    }
    if (!$changed) return null;
    // A different range is a different listing: start it at page 1.
    unset($out['paged']);
    return $out;
}

/**
 * The query string as the visitor sent it. Not $_GET: a currency plugin may
 * rewrite the price bounds there, and a redirect built from a rewritten value
 * would change the number on every hop.
 */
function af_price_filter_query() {
    $args = array();
    $qs = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
    if ($qs !== '') parse_str($qs, $args);
    return is_array($args) ? $args : array();
}

/** The listing's own path, without /page/N/ and without a query string. */
function af_price_filter_base_path() {
    $path = strtok(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/', '?');
    return preg_replace('#/page/\d+/?$#', '/', $path ?: '/');
}

/** An absolute URL on this site for a path and a set of query args. */
function af_price_filter_url($path, array $args) {
    $origin = preg_replace('#^(https?://[^/]+).*$#i', '$1', home_url('/'));
    return $origin . '/' . ltrim($path, '/') . ($args ? '?' . http_build_query($args) : '');
}

add_action('template_redirect', function () {
    try {
        if (is_admin() || wp_doing_ajax()) return;
        $args = af_price_filter_query();
        if (!array_key_exists('min_price', $args) && !array_key_exists('max_price', $args)) return;
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') return;
        if (!af_price_filter_on_listing()) return;
        $fixed = af_price_filter_normalise($args);
        if ($fixed === null) return;
        // Never cached: the redirect belongs to this URL's typo, not to the page.
        nocache_headers();
        do_action('litespeed_control_set_nocache', 'AF price filter redirect');
        wp_safe_redirect(af_price_filter_url(af_price_filter_base_path(), $fixed), 302, 'AF price filter');
        exit;
    } catch (\Throwable $e) {
        // Leave the request exactly as it came.
    }
}, 1);

/** True on a shop or category listing that has no products to show. */
function af_listing_is_empty() {
    $q = isset($GLOBALS['wp_the_query']) ? $GLOBALS['wp_the_query'] : null;
    if (!af_price_filter_on_listing() || is_404() || !is_object($q)) return false;
    return (int) $q->found_posts === 0;
}

/** The filters in the query string, by name. */
function af_listing_active_filters() {
    $on = array();
    foreach (array_keys(af_price_filter_query()) as $k) {
        if (preg_match('/^(min_price|max_price|filter_[a-z0-9_-]+|orientation|product_tag|rating_filter)$/i', (string) $k)) $on[] = (string) $k;
    }
    return $on;
}

/** "$1,000" or "$12.50", in the currency the shop is showing. */
function af_price_filter_money($v) {
    $sym = function_exists('get_woocommerce_currency_symbol')
        ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '$';
    $f = (float) $v;
    return $sym . number_format_i18n($f, floor($f) == $f ? 0 : 2);
}

/** The message and the way out. */
function af_listing_empty_message() {
    $get      = af_price_filter_query();
    $filters  = af_listing_active_filters();
    $path     = af_price_filter_base_path();
    $min      = isset($get['min_price']) ? af_price_filter_clean($get['min_price']) : null;
    $max      = isset($get['max_price']) ? af_price_filter_clean($get['max_price']) : null;
    $by_price = ($min !== null || $max !== null);
    $others   = array_diff($filters, array('min_price', 'max_price'));

    if ($filters) {
        $title = 'Nothing here matches those filters.';
        if ($by_price) {
            if ($min !== null && $max !== null) $range = 'between ' . af_price_filter_money($min) . ' and ' . af_price_filter_money($max);
            elseif ($min !== null)              $range = 'at ' . af_price_filter_money($min) . ' or more';
            else                                $range = 'at ' . af_price_filter_money($max) . ' or less';
            $detail = 'No artwork here is priced ' . $range . ($others ? ' with the other filters you chose' : '')
                    . '. Try a wider range, or clear the filters to see everything.';
        } else {
            $detail = 'Try fewer filters, or clear them to see everything.';
        }
    } else {
        $title  = 'This collection is empty right now.';
        $detail = 'New pieces are added all the time. In the meantime, the rest of the studio is open.';
    }

    $all_url = $filters ? af_price_filter_url($path, array()) : home_url('/shop/');
    $html  = '<div class="af-empty-filter">';
    $html .= '<h2 class="af-ef-title">' . esc_html($title) . '</h2>';
    $html .= '<p class="af-ef-detail">' . esc_html($detail) . '</p>';
    $html .= '<div class="af-ef-actions">';
    $html .= '<a class="af-ef-btn solid" href="' . esc_url($all_url) . '">' . ($filters ? 'Clear the filters' : 'Browse the shop') . '</a>';
    if ($by_price && $others) {
        $keep = array_diff_key($get, array('min_price' => 1, 'max_price' => 1, 'paged' => 1));
        $html .= '<a class="af-ef-btn" href="' . esc_url(af_price_filter_url($path, $keep)) . '">Remove only the price</a>';
    }
    $html .= '</div></div>';
    $html .= '<style id="af-empty-filter-style">'
           . '.af-empty-filter{max-width:640px;margin:28px auto 56px;padding:0 16px;text-align:center}'
           . '.af-empty-filter .af-ef-title{font-size:26px;margin:0 0 10px;letter-spacing:-.3px}'
           . '.af-empty-filter .af-ef-detail{color:#6b6250;font-size:15px;line-height:1.7;margin:0 auto 22px;max-width:520px}'
           . '.af-ef-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}'
           . '.af-ef-btn{padding:12px 20px;border:1.5px solid #e2d9c4;border-radius:11px;background:#fffdf8;color:#5a5140;font-weight:700;font-size:13.5px;text-decoration:none}'
           . '.af-ef-btn:hover{border-color:#c9a84c;color:#5a5140}'
           . '.af-ef-btn.solid{background:#1a1a1a;border-color:#1a1a1a;color:#fff}'
           . '.af-ef-btn.solid:hover{background:#000;color:#fff}'
           . '</style>';
    return $html;
}

// In place of WooCommerce's "No products were found matching your selection."
// Once per page, like the "You may also like" row, in case a template fires
// the hook twice.
add_action('woocommerce_no_products_found', function () {
    static $done = false;
    try {
        if ($done || !af_listing_is_empty()) return;
        $done = true;
        echo af_listing_empty_message();
        remove_action('woocommerce_no_products_found', 'wc_no_products_found');
    } catch (\Throwable $e) {
        // WooCommerce's own notice still prints.
    }
}, 5);
