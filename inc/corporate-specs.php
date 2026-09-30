<?php
/**
 * Corporate Printing: the size and quantity the rate card sells, on the card
 * and on the product page.
 *
 * Owner, 30 Sep 2026: "5 sizes and 2 frames not applicable - copied
 * everywhere. Size not correct, quantity not mentioned." The 33 products sat
 * inside the canvas engine (af_pricing_applies), so every card offered the
 * five canvas sizes, two frames and four frame colours, and the product page
 * sold a 2×3 ft canvas. They are out of the engine now (functions.php) and
 * each one carries what the Corporate Rate Card 2026 prints under its tile:
 *
 *   _af_corp_size   "3.5 × 2 in", "8 × 8 ft", "A4 (8.3 × 11.7 in)" ...
 *   _af_corp_qty    "Pack of 500", "1 banner", "Pack of 25 bags" ...
 *
 * Written by tools/product-importer/corp_rate_card.py from the brochure. The
 * price stays the product's own: the owner's $5.70 a square foot for large
 * format, the brochure's pack price for packs.
 */
if (!defined('ABSPATH')) exit;

/** array(size, quantity) for a Corporate Printing product, or null. */
function af_corp_spec($product) {
    if (!($product instanceof WC_Product)) return null;
    $pid = $product->get_parent_id() ?: $product->get_id();
    if (!has_term('corporate-printing', 'product_cat', $pid)) return null;
    $size = trim((string) get_post_meta($pid, '_af_corp_size', true));
    $qty  = trim((string) get_post_meta($pid, '_af_corp_qty', true));
    if ($size === '' && $qty === '') return null;
    return array($size, $qty);
}

// The card: where the canvas cards say "5 sizes · 2 frames". Same slot
// (priority 13) and the same class, so it sits and looks where that line did,
// and the browser-side filler, which skips a card already carrying
// .af-card-vars, leaves it alone.
add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    $spec = af_corp_spec($product);
    if (!$spec) return;
    echo '<div class="af-card-vars af-corp-spec"><span>'
       . esc_html(implode(' · ', array_filter($spec)))
       . '</span></div>';
}, 13);

// The product page: under the price.
add_action('woocommerce_single_product_summary', function () {
    global $product;
    $spec = af_corp_spec($product);
    if (!$spec) return;
    list($size, $qty) = $spec;
    echo '<div class="af-corp-spec-line">';
    if ($size !== '') echo '<span><strong>Size:</strong> ' . esc_html($size) . '</span>';
    if ($qty !== '')  echo '<span><strong>Quantity:</strong> ' . esc_html($qty) . '</span>';
    echo '</div>';
    echo '<style>.af-corp-spec-line{display:flex;flex-wrap:wrap;gap:6px 18px;margin:6px 0 14px;font-size:15px;color:#1a1a1a}'
       . '.af-corp-spec-line strong{font-weight:700}</style>';
}, 11);
