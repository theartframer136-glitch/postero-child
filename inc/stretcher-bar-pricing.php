<?php
/**
 * Stretcher bar products priced by size, shown as a discount.
 *
 * Owner, 25 Sep: $4 a square foot of the size (inc/stretcher-bars.php, now
 * retired). Owner, 26 Sep: "the same type of discount" as the aluminium
 * frames — the customer still pays $4 a square foot, shown as 50% off a list
 * price of $8 a square foot. The discount is on top of the $4, not off it.
 * Over the sizes the shop sells (af_sizes_available()):
 *
 *     2×3 / 3×2 ft    6 sq ft     $48 → $24
 *     2.5×3 ft        7.5 sq ft   $60 → $30
 *     3×4 ft         12 sq ft     $96 → $48
 *     3×5 ft         15 sq ft    $120 → $60
 *
 * The same af_bar_price() (inc/kit-options.php) still prices the bars inside
 * "Painting + structure bars" on the art pages, so the two never disagree on
 * what is paid.
 *
 * The product keeps whatever is stored in wp-admin (nothing, today); on the
 * storefront its price is read from the size. A quick Add to Cart from a
 * card takes the smallest size. Both numbers are options:
 *     wp option update af_bar_rate_sqft 4
 *     wp option update af_bar_discount_pct 50
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

/** The discount shown against the list price, in percent. */
function af_bar_discount_pct() {
    $d = get_option('af_bar_discount_pct');
    $d = ($d !== false && is_numeric($d)) ? (float) $d : 50.0;
    return max(0.0, min(95.0, $d));
}

/** array(sale, regular) for one size label, or null if it has no dimensions. */
function af_bar_price_pair($size_label) {
    if (!function_exists('af_bar_price')) return null;
    $sale = af_bar_price($size_label);
    if ($sale <= 0) return null;
    $pct = af_bar_discount_pct();
    $reg = $pct > 0 ? round($sale / (1 - $pct / 100), 2) : $sale;
    return array($sale, $reg);
}

/** size label => array(sale, regular), for the sizes on sale */
function af_bar_size_prices() {
    static $out = null;
    if ($out !== null) return $out;
    $out = array();
    if (!function_exists('af_sizes_available')) return $out;
    foreach (af_sizes_available() as $s) {
        $pair = af_bar_price_pair($s);
        if ($pair) $out[$s] = $pair;
    }
    return $out;
}

/** The smallest size's label, so a quick Add to Cart and the card agree. */
function af_bar_default_size() {
    $sizes = af_bar_size_prices();
    if (!$sizes) return '';
    $best = '';
    foreach ($sizes as $s => $p) {
        if ($best === '' || $p[0] < $sizes[$best][0]) $best = $s;
    }
    return $best;
}

function af_bar_storefront() {
    return !is_admin() || wp_doing_ajax();
}

// Storefront prices: the smallest size, unless the cart has set this line's
// own numbers (set_price and friends leave them in the object's changes).
$af_bar_filters = array(
    'woocommerce_product_get_price'         => array('price', 0),
    'woocommerce_product_get_regular_price' => array('regular_price', 1),
);
foreach ($af_bar_filters as $af_hook => $af_spec) {
    add_filter($af_hook, function ($price, $product) use ($af_spec) {
        if (!af_bar_storefront() || !($product instanceof WC_Product) || !af_is_bar_product($product)) return $price;
        $changes = $product->get_changes();
        if (isset($changes[$af_spec[0]]) && $changes[$af_spec[0]] !== '') return $price;
        $def = af_bar_default_size();
        if ($def === '') return $price;
        return (string) af_bar_size_prices()[$def][$af_spec[1]];
    }, 99, 2);
}
unset($af_bar_filters, $af_hook, $af_spec);

// The currency switcher rebuilds the sale price from the STORED value late in
// the chain, and these products store none — measured on the aluminium
// frames, 26 Sep: sale '' and on_sale false however early the filter ran. For
// these bars the sale price IS the price paid, which arrives already
// converted for a CAD shopper. So, last in the chain, the sale price is the
// price. get_price() re-enters is_on_sale, hence the guards.
add_filter('woocommerce_product_get_sale_price', function ($sale, $product) {
    if (!af_bar_storefront() || !($product instanceof WC_Product) || !af_is_bar_product($product)) return $sale;
    $changes = $product->get_changes();
    if (isset($changes['sale_price']) && $changes['sale_price'] !== '') return $sale;
    static $busy = false;
    if ($busy) return $sale;
    $busy = true;
    $p = $product->get_price();
    $busy = false;
    return ($p === '' || $p === null) ? $sale : (string) $p;
}, PHP_INT_MAX, 2);

add_filter('woocommerce_product_is_on_sale', function ($on_sale, $product) {
    if (!af_bar_storefront() || !($product instanceof WC_Product) || !af_is_bar_product($product)) return $on_sale;
    static $busy = false;
    if ($busy) return $on_sale;
    $busy = true;
    $reg  = (float) $product->get_regular_price();
    $sale = (float) $product->get_sale_price();
    $busy = false;
    return $sale > 0 && $reg > $sale;
}, PHP_INT_MAX, 2);

// "From $48.00 $24.00" wherever a card or the page quotes it; the theme's own
// script reads the struck price and adds "(50% off)".
add_filter('woocommerce_get_price_html', function ($html, $product) {
    if (!af_bar_storefront() || !($product instanceof WC_Product) || !af_is_bar_product($product)) return $html;
    $def = af_bar_default_size();
    if ($def === '') return $html;
    list($sale, $reg) = af_bar_size_prices()[$def];
    return '<span class="af-bar-from">From </span>' . ($reg > $sale ? wc_format_sale_price($reg, $sale) : wc_price($sale));
}, 99, 2);

// The size choice on the product page
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    if (!($product instanceof WC_Product) || !af_is_bar_product($product)) return;
    $sizes = af_bar_size_prices();
    if (!$sizes) return;
    $def   = af_bar_default_size();
    $pct   = af_bar_discount_pct();
    $plain = function ($n) { return html_entity_decode(wp_strip_all_tags(wc_price($n))); };
    ?>
<div class="af-bar-size" id="af-bar-size">
  <label class="af-opt-label" for="af-bar-size-select">Size <span class="af-opt-sub">(height × width)</span></label>
  <select id="af-bar-size-select" name="af_bar_size">
    <?php foreach ($sizes as $s => $p) : ?>
      <option value="<?php echo esc_attr($s); ?>" data-price="<?php echo esc_attr($p[0]); ?>" data-regular="<?php echo esc_attr($p[1]); ?>"<?php selected($s, $def); ?>><?php
        echo esc_html($s . ' — ' . $plain($p[0]) . ($p[1] > $p[0] ? ' (was ' . $plain($p[1]) . ')' : ''));
      ?></option>
    <?php endforeach; ?>
  </select>
  <p class="af-bar-live">Your Price: <strong id="af-bar-live"><?php echo wc_price($sizes[$def][0]); ?></strong>
    <del id="af-bar-was"><?php echo wc_price($sizes[$def][1]); ?></del>
    <span class="af-bar-save"><?php echo esc_html(rtrim(rtrim(number_format($pct, 1), '0'), '.')); ?>% off</span>
    <span class="af-bar-rate">($<?php echo esc_html(rtrim(rtrim(number_format(af_bar_rate_sqft(), 2), '0'), '.')); ?> per sq ft)</span></p>
</div>
<style>
.af-bar-size{margin:12px 0 14px}
.af-bar-size select{width:100%;max-width:360px;padding:10px 12px;border:1.5px solid #e7e7e7;border-radius:8px;font-size:14px;background:#fff}
.af-bar-live{margin:10px 0 0;font-size:14px;color:#1a1a1a}
.af-bar-live strong{font-size:18px;font-weight:700}
.af-bar-live del{color:#8a8a8a;margin-left:6px}
.af-bar-save{color:var(--af-save,#2e7d32);font-weight:700;margin-left:6px}
.af-bar-rate{font-size:12.5px;color:#6b6b6b;margin-left:4px}
</style>
<script>
(function(){
  var sel = document.getElementById('af-bar-size-select'),
      out = document.getElementById('af-bar-live'), was = document.getElementById('af-bar-was');
  if (!sel || !out) return;
  var sym = (out.textContent || '').match(/^[^0-9]*/); sym = sym && sym[0] ? sym[0] : '$';
  function fmt(v){ return sym + v.toFixed(2); }
  function upd(){
    var o = sel.options[sel.selectedIndex],
        p = parseFloat(o.getAttribute('data-price')) || 0,
        r = parseFloat(o.getAttribute('data-regular')) || 0;
    out.textContent = fmt(p);
    if (was) was.textContent = r > p ? fmt(r) : '';
    var head = document.querySelector('.summary .price, .entry-summary .price, p.price');
    if (head) {
      head.innerHTML = r > p
        ? '<del><span class="woocommerce-Price-amount amount">' + fmt(r) + '</span></del> '
          + '<ins><span class="woocommerce-Price-amount amount">' + fmt(p) + '</span></ins> '
          + '<span class="af-pct-off">(' + Math.round((r - p) / r * 100) + '% off)</span>'
        : '<span class="woocommerce-Price-amount amount">' + fmt(p) + '</span>';
    }
  }
  sel.addEventListener('change', upd);
  upd();
})();
</script>
    <?php
}, 9);

// Cart: the chosen size, priced by area, with its list price alongside
add_filter('woocommerce_add_cart_item_data', function ($data, $pid) {
    if (!af_is_bar_product($pid)) return $data;
    $sizes = af_bar_size_prices();
    if (!$sizes) return $data;
    $size = isset($_POST['af_bar_size']) ? sanitize_text_field(wp_unslash($_POST['af_bar_size'])) : '';
    if (!isset($sizes[$size])) $size = af_bar_default_size();   // a forged size falls back to the smallest
    $data['af_bar_size']    = $size;
    $data['af_price']       = $sizes[$size][0];   // applied by the cart's af_price pass
    $data['af_bar_regular'] = $sizes[$size][1];
    $data['af_unique']      = md5('bar|' . $pid . '|' . $size);
    return $data;
}, 15, 2);

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart) || !is_a($cart, 'WC_Cart')) return;
    foreach ($cart->get_cart() as $item) {
        if (empty($item['af_bar_size']) || empty($item['af_price'])) continue;
        $item['data']->set_price($item['af_price']);
        $item['data']->set_sale_price($item['af_price']);
        $item['data']->set_regular_price(isset($item['af_bar_regular']) ? $item['af_bar_regular'] : $item['af_price']);
    }
}, 25);

add_filter('woocommerce_get_item_data', function ($data, $item) {
    if (!empty($item['af_bar_size'])) $data[] = array('name' => 'Size', 'value' => $item['af_bar_size']);
    return $data;
}, 20, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $key, $values) {
    if (!empty($values['af_bar_size'])) $item->add_meta_data('Size', $values['af_bar_size']);
}, 20, 3);
