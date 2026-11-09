<?php
/**
 * An "add to cart" link opened directly adds nothing: it sends the visitor to
 * the product's page, where they choose the size, frame and colour and press
 * Add to cart themselves (owner, 9 Nov 2026).
 *
 * WooCommerce adds a product to the cart for any request carrying
 * ?add-to-cart=<id>, with every option at its default. Google had indexed
 * eight such addresses from before robots.txt blocked them (5 Oct), e.g.
 * /product-tag/nursery-wall-art-canvas/?layout=list&add-to-cart=8866, so a
 * searcher clicking one would land with a piece already in their cart. The
 * card's gold "Add to Cart" button was the same kind of link
 * (assets/af/page-class-tweaks.js); it now opens the product page.
 *
 * Only a plain GET with add-to-cart in the address is turned away. The
 * product page's own form posts add-to-cart (method="post"), the AJAX and
 * Store API paths use their own routes, so the real Add to cart and Buy Now
 * buttons work as before. Crawlers without browser headers are already sent
 * home before WordPress loads (ops/mu/af-crawl-guard.php, 4c); this covers
 * everyone else. The redirect says noindex, so an address Google does fetch
 * leaves its index.
 *
 * Runs on wp_loaded at priority 1, before WC_Form_Handler::add_to_cart_action
 * (wp_loaded, 20) reads the request.
 */
if (!defined('ABSPATH')) exit;

/** Where an ?add-to-cart=<id> link goes instead: that product's page (a variation's parent), else home. */
function af_atc_link_target($id) {
    try {
        $id = absint($id);
        $p  = ($id && function_exists('wc_get_product')) ? wc_get_product($id) : null;
        if ($p && $p->is_type('variation')) $p = wc_get_product($p->get_parent_id());
        if ($p && $p->get_status() === 'publish') {
            $u = get_permalink($p->get_id());
            if ($u) return $u;
        }
    } catch (\Throwable $e) {
    }
    return home_url('/');
}

/** Whether this request is an add-to-cart link opened directly (a GET with add-to-cart in the address). */
function af_atc_is_direct_link() {
    if (!isset($_GET['add-to-cart']) || $_GET['add-to-cart'] === '') return false;
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($method !== 'GET' && $method !== 'HEAD') return false;
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || isset($_GET['wc-ajax'])) return false;
    return true;
}

add_action('wp_loaded', function () {
    if (!af_atc_is_direct_link()) return;
    $to = af_atc_link_target(wp_unslash($_GET['add-to-cart']));
    unset($_GET['add-to-cart'], $_REQUEST['add-to-cart']);   // WooCommerce never sees it
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow');
    wp_safe_redirect($to, 301, 'The Art Framer');
    exit;
}, 1);
