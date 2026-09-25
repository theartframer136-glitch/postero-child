<?php
/**
 * Stretcher bar products priced by size. Owner's recording, 25 Sep: the six
 * products in Art Accessories → Canvas Stretcher Bars and DIY Canvas Stretcher
 * Bars showed $0.00 or "Price on request" (measured: five have no price at
 * all, one a flat $100), so none could be bought at a sensible figure.
 *
 * The owner's rule: $4 a square foot of the size, over the sizes the shop
 * sells (af_sizes_available(): 2×3, 3×2, 2.5×3, 3×4, 3×5 ft → $24, $24, $30,
 * $48, $60). The same af_bar_price() prices the bars inside "Painting +
 * structure bars" on the art pages, so the two can never disagree.
 *
 * The product keeps whatever is stored in wp-admin; on the storefront its
 * price is read from the size. A quick Add to Cart from a card takes the
 * smallest size.
 */
if (!defined('ABSPATH')) exit;

function af_bar_cat_slugs() { return array('canvas-stretcher-bars', 'diy-canvas-stretcher-bars'); }

function af_is_bar_product($product) {
    $pid = $product instanceof WC_Product ? $product->get_id() : (int) $product;
    if ($product instanceof WC_Product && $product->get_parent_id()) $pid = $product->get_parent_id();
    if (!$pid) return false;
    static $memo = array();
    if (isset($memo[$pid])) return $memo[$pid];
    $hit = false;
    $terms = get_the_terms($pid, 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $t) {
            if (in_array($t->slug, af_bar_cat_slugs(), true)) { $hit = true; break; }
            foreach (get_ancestors($t->term_id, 'product_cat') as $aid) {
                $at = get_term($aid, 'product_cat');
                if ($at && !is_wp_error($at) && in_array($at->slug, af_bar_cat_slugs(), true)) { $hit = true; break 2; }
            }
        }
    }
    return $memo[$pid] = $hit;
}

/** size label => price, for the sizes on sale */
function af_bar_size_prices() {
    $out = array();
    if (!function_exists('af_sizes_available') || !function_exists('af_bar_price')) return $out;
    foreach (af_sizes_available() as $s) {
        $p = af_bar_price($s);
        if ($p > 0) $out[$s] = $p;
    }
    return $out;
}

function af_bar_min_price() {
    $p = af_bar_size_prices();
    return $p ? min($p) : 0;
}

function af_bar_storefront() {
    return !is_admin() || wp_doing_ajax();
}

// The storefront price is the smallest size's, unless the cart has already
// set this line's own price (set_price leaves it in the object's changes).
foreach (array('woocommerce_product_get_price', 'woocommerce_product_get_regular_price') as $af_hook) {
    add_filter($af_hook, function ($price, $product) {
        if (!af_bar_storefront() || !af_is_bar_product($product)) return $price;
        $changes = $product->get_changes();
        if (isset($changes['price']) && $changes['price'] !== '') return $price;
        $min = af_bar_min_price();
        return $min > 0 ? (string) $min : $price;
    }, 10, 2);
}
add_filter('woocommerce_product_get_sale_price', function ($price, $product) {
    if (!af_bar_storefront() || !af_is_bar_product($product)) return $price;
    return '';
}, 10, 2);

// "From $24.00" wherever a card or the page quotes it
add_filter('woocommerce_get_price_html', function ($html, $product) {
    if (!af_bar_storefront() || !af_is_bar_product($product)) return $html;
    $min = af_bar_min_price();
    if ($min <= 0) return $html;
    return '<span class="af-bar-from">From </span>' . wc_price($min);
}, 30, 2);

// The size choice on the product page
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    if (!$product || !af_is_bar_product($product)) return;
    $sizes = af_bar_size_prices();
    if (!$sizes) return;
    $first = array_key_first($sizes);
    ?>
<div class="af-bar-size" id="af-bar-size">
  <label class="af-opt-label" for="af-bar-size-select">Size <span class="af-opt-sub">(height × width)</span></label>
  <select id="af-bar-size-select" name="af_bar_size">
    <?php foreach ($sizes as $s => $p) : ?>
      <option value="<?php echo esc_attr($s); ?>" data-price="<?php echo esc_attr($p); ?>"><?php echo esc_html($s . ' — ' . html_entity_decode(wp_strip_all_tags(wc_price($p)))); ?></option>
    <?php endforeach; ?>
  </select>
  <p class="af-bar-live">Your Price: <strong id="af-bar-live"><?php echo wc_price($sizes[$first]); ?></strong>
    <span class="af-bar-rate">($<?php echo esc_html(rtrim(rtrim(number_format(af_bar_rate_sqft(), 2), '0'), '.')); ?> per sq ft)</span></p>
</div>
<style>
.af-bar-size{margin:12px 0 14px}
.af-bar-size select{width:100%;max-width:360px;padding:10px 12px;border:1.5px solid #e7e7e7;border-radius:8px;font-size:14px;background:#fff}
.af-bar-live{margin:10px 0 0;font-size:14px;color:#1a1a1a}
.af-bar-live strong{font-size:18px;font-weight:700}
.af-bar-rate{font-size:12.5px;color:#6b6b6b;margin-left:4px}
</style>
<script>
(function(){
  var sel = document.getElementById('af-bar-size-select'), out = document.getElementById('af-bar-live');
  if (!sel || !out) return;
  function upd(){
    var o = sel.options[sel.selectedIndex], v = parseFloat(o.getAttribute('data-price')) || 0;
    var sym = (out.textContent || '').match(/^[^0-9]*/); sym = sym && sym[0] ? sym[0] : '$';
    out.textContent = sym + v.toFixed(2);
    var head = document.querySelector('.summary .price, .entry-summary .price, p.price');
    if (head) head.innerHTML = '<span class="woocommerce-Price-amount amount">' + sym + v.toFixed(2) + '</span>';
  }
  sel.addEventListener('change', upd);
  upd();
})();
</script>
    <?php
}, 9);

// Cart: the chosen size, priced by area
add_filter('woocommerce_add_cart_item_data', function ($data, $pid) {
    if (!af_is_bar_product($pid)) return $data;
    $sizes = af_bar_size_prices();
    if (!$sizes) return $data;
    $size = isset($_POST['af_bar_size']) ? sanitize_text_field(wp_unslash($_POST['af_bar_size'])) : '';
    if (!isset($sizes[$size])) $size = array_key_first($sizes);
    $data['af_bar_size'] = $size;
    $data['af_price']    = $sizes[$size];   // applied by the cart's af_price pass
    $data['af_unique']   = md5('bar|' . $pid . '|' . $size);
    return $data;
}, 15, 2);

add_filter('woocommerce_get_item_data', function ($data, $item) {
    if (!empty($item['af_bar_size'])) $data[] = array('name' => 'Size', 'value' => $item['af_bar_size']);
    return $data;
}, 20, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $key, $values) {
    if (!empty($values['af_bar_size'])) $item->add_meta_data('Size', $values['af_bar_size']);
}, 20, 3);
