<?php
/**
 * Premium aluminium frame products priced by size. Owner's recording, 26 Sep:
 * the three products in Art Accessories → Premium Aluminium Frames
 * (TMP-1025, TMP-1026, TMP-1027) showed "Price on request" with an Enquire
 * button, so none could be bought.
 *
 * The owner's rule, "the same as the stretcher bars": count the square feet of
 * the size. The customer pays $4 a square foot, shown as 50% off — the list
 * price is $8 a square foot and the discount brings it to $4, not 50% off $4.
 * Over the sizes the shop sells (af_sizes_available()):
 *
 *     2×3 / 3×2 ft    6 sq ft     $48 → $24
 *     2.5×3 ft        7.5 sq ft   $60 → $30
 *     3×4 ft         12 sq ft     $96 → $48
 *     3×5 ft         15 sq ft    $120 → $60
 *
 * Scope is these products only. The "Aluminium Frame" option on the art pages
 * keeps its own fee in af_pricing_config().
 *
 * The product keeps whatever is stored in wp-admin (nothing, today); on the
 * storefront its price is read from the size. A quick Add to Cart from a card
 * takes the smallest size. Once a real price is saved in wp-admin this still
 * wins, so the rate lives in one place:
 *     wp option update af_alu_rate_sqft 4
 *     wp option update af_alu_discount_pct 50
 */
if (!defined('ABSPATH')) exit;

function af_alu_cat_slugs() { return array('premium-aluminium-frames'); }

function af_is_alu_product($product) {
    $pid = $product instanceof WC_Product ? $product->get_id() : (int) $product;
    if ($product instanceof WC_Product && $product->get_parent_id()) $pid = $product->get_parent_id();
    if (!$pid) return false;
    static $memo = array();
    if (isset($memo[$pid])) return $memo[$pid];
    $hit = false;
    $terms = get_the_terms($pid, 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $t) {
            if (in_array($t->slug, af_alu_cat_slugs(), true)) { $hit = true; break; }
            foreach (get_ancestors($t->term_id, 'product_cat') as $aid) {
                $at = get_term($aid, 'product_cat');
                if ($at && !is_wp_error($at) && in_array($at->slug, af_alu_cat_slugs(), true)) { $hit = true; break 2; }
            }
        }
    }
    return $memo[$pid] = $hit;
}

/** What the customer pays per square foot, after the discount. */
function af_alu_rate_sqft() {
    $r = get_option('af_alu_rate_sqft');
    return ($r !== false && is_numeric($r) && (float) $r > 0) ? (float) $r : 4.0;
}

/** The discount shown against the list price, in percent. */
function af_alu_discount_pct() {
    $d = get_option('af_alu_discount_pct');
    $d = ($d !== false && is_numeric($d)) ? (float) $d : 50.0;
    return max(0.0, min(95.0, $d));
}

/** array(sale, regular) for one size label, or null if it has no dimensions. */
function af_alu_price_pair($size_label) {
    $in = function_exists('af_ship_inches') ? af_ship_inches($size_label) : null;
    if (!$in) return null;
    $sale = round(($in[0] * $in[1] / 144) * af_alu_rate_sqft(), 2);
    $pct  = af_alu_discount_pct();
    $reg  = $pct > 0 ? round($sale / (1 - $pct / 100), 2) : $sale;
    return array($sale, $reg);
}

/** size label => array(sale, regular), for the sizes on sale */
function af_alu_size_prices() {
    static $out = null;
    if ($out !== null) return $out;
    $out = array();
    if (!function_exists('af_sizes_available')) return $out;
    foreach (af_sizes_available() as $s) {
        $pair = af_alu_price_pair($s);
        if ($pair && $pair[0] > 0) $out[$s] = $pair;
    }
    return $out;
}

/** The smallest size's label, so a quick Add to Cart and the card agree. */
function af_alu_default_size() {
    $sizes = af_alu_size_prices();
    if (!$sizes) return '';
    $best = '';
    foreach ($sizes as $s => $p) {
        if ($best === '' || $p[0] < $sizes[$best][0]) $best = $s;
    }
    return $best;
}

function af_alu_colors($product) {
    $raw = '';
    if ($product instanceof WC_Product) {
        foreach (array('pa_colors', 'Colors', 'colors') as $a) {
            $v = $product->get_attribute($a);
            if ($v) { $raw = $v; break; }
        }
    }
    $list = array_values(array_filter(array_map('trim', preg_split('/\s*[,|]\s*/', (string) $raw))));
    return $list ? $list : array('Black', 'Silver', 'Gold', 'Rose Gold');
}

function af_alu_storefront() {
    return !is_admin() || wp_doing_ajax();
}

// Storefront prices: the smallest size, unless the cart has set this line's
// own numbers (set_price and friends leave them in the object's changes).
$af_alu_filters = array(
    'woocommerce_product_get_price'         => array('price', 0),
    'woocommerce_product_get_sale_price'    => array('sale_price', 0),
    'woocommerce_product_get_regular_price' => array('regular_price', 1),
);
foreach ($af_alu_filters as $af_hook => $af_spec) {
    add_filter($af_hook, function ($price, $product) use ($af_spec) {
        if (!af_alu_storefront() || !($product instanceof WC_Product) || !af_is_alu_product($product)) return $price;
        $changes = $product->get_changes();
        if (isset($changes[$af_spec[0]]) && $changes[$af_spec[0]] !== '') return $price;
        $def = af_alu_default_size();
        if ($def === '') return $price;
        $pair = af_alu_size_prices()[$def];
        return (string) $pair[$af_spec[1]];
    }, 99, 2);
}
unset($af_alu_filters, $af_hook, $af_spec);

// Measured live, 26 Sep, twice: price $24 and list $48 came through, but the
// sale price read '' and on_sale false even with the filter above at 99. A
// later filter — the currency switcher's, which converts prices for CAD
// shoppers — rebuilds the sale price from the stored value, and these
// products store none. The theme's "X% off" badge divides by the sale price,
// so forcing on_sale alone would print "100% off".
//
// For these frames the sale price IS the price the customer pays, and the
// price survives the currency switcher already converted. So, last in the
// chain, the sale price is the price. That stays right in any currency.
add_filter('woocommerce_product_get_sale_price', function ($sale, $product) {
    if (!af_alu_storefront() || !($product instanceof WC_Product) || !af_is_alu_product($product)) return $sale;
    $changes = $product->get_changes();
    if (isset($changes['sale_price']) && $changes['sale_price'] !== '') return $sale;
    // get_price() runs the currency switcher's filter, which may ask whether
    // the product is on sale, which asks for the sale price: guard the loop.
    static $busy = false;
    if ($busy) return $sale;
    $busy = true;
    $p = $product->get_price();
    $busy = false;
    return ($p === '' || $p === null) ? $sale : (string) $p;
}, PHP_INT_MAX, 2);

add_filter('woocommerce_product_is_on_sale', function ($on_sale, $product) {
    if (!af_alu_storefront() || !($product instanceof WC_Product) || !af_is_alu_product($product)) return $on_sale;
    static $busy = false;
    if ($busy) return $on_sale;
    $busy = true;
    $reg  = (float) $product->get_regular_price();
    $sale = (float) $product->get_sale_price();
    $busy = false;
    return $sale > 0 && $reg > $sale;
}, PHP_INT_MAX, 2);

// "From $48.00 $24.00" wherever a card or the page quotes it. The struck
// list price is what the shop's own script reads to add "(50% off)".
add_filter('woocommerce_get_price_html', function ($html, $product) {
    if (!af_alu_storefront() || !($product instanceof WC_Product) || !af_is_alu_product($product)) return $html;
    $def = af_alu_default_size();
    if ($def === '') return $html;
    list($sale, $reg) = af_alu_size_prices()[$def];
    $out = '<span class="af-alu-from">From </span>';
    return $out . ($reg > $sale ? wc_format_sale_price($reg, $sale) : wc_price($sale));
}, 99, 2);

// The size and colour choice on the product page
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    if (!($product instanceof WC_Product) || !af_is_alu_product($product)) return;
    $sizes = af_alu_size_prices();
    if (!$sizes) return;
    $def    = af_alu_default_size();
    $colors = af_alu_colors($product);
    $pct    = af_alu_discount_pct();
    $plain  = function ($n) { return html_entity_decode(wp_strip_all_tags(wc_price($n))); };
    ?>
<div class="af-alu-opts" id="af-alu-opts">
  <label class="af-opt-label" for="af-alu-size-select">Size <span class="af-opt-sub">(height × width)</span></label>
  <select id="af-alu-size-select" name="af_alu_size">
    <?php foreach ($sizes as $s => $p) : ?>
      <option value="<?php echo esc_attr($s); ?>" data-price="<?php echo esc_attr($p[0]); ?>" data-regular="<?php echo esc_attr($p[1]); ?>"<?php selected($s, $def); ?>><?php
        echo esc_html($s . ' — ' . $plain($p[0]) . ($p[1] > $p[0] ? ' (was ' . $plain($p[1]) . ')' : ''));
      ?></option>
    <?php endforeach; ?>
  </select>
  <label class="af-opt-label" for="af-alu-color-select">Frame colour</label>
  <select id="af-alu-color-select" name="af_alu_color">
    <?php foreach ($colors as $c) : ?>
      <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
    <?php endforeach; ?>
  </select>
  <p class="af-alu-live">Your Price: <strong id="af-alu-live"><?php echo wc_price($sizes[$def][0]); ?></strong>
    <del id="af-alu-was"><?php echo wc_price($sizes[$def][1]); ?></del>
    <span class="af-alu-save"><?php echo esc_html(rtrim(rtrim(number_format($pct, 1), '0'), '.')); ?>% off</span>
    <span class="af-alu-rate">($<?php echo esc_html(rtrim(rtrim(number_format(af_alu_rate_sqft(), 2), '0'), '.')); ?> per sq ft)</span></p>
</div>
<style>
.af-alu-opts{margin:12px 0 14px}
.af-alu-opts select{width:100%;max-width:360px;padding:10px 12px;border:1.5px solid #e7e7e7;border-radius:8px;font-size:14px;background:#fff;margin-bottom:10px}
.af-alu-live{margin:4px 0 0;font-size:14px;color:#1a1a1a}
.af-alu-live strong{font-size:18px;font-weight:700}
.af-alu-live del{color:#8a8a8a;margin-left:6px}
.af-alu-save{color:var(--af-save,#2e7d32);font-weight:700;margin-left:6px}
.af-alu-rate{font-size:12.5px;color:#6b6b6b;margin-left:4px}
</style>
<script>
(function(){
  var sel = document.getElementById('af-alu-size-select'),
      out = document.getElementById('af-alu-live'), was = document.getElementById('af-alu-was');
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

// Cart: the chosen size and colour, priced by area
add_filter('woocommerce_add_cart_item_data', function ($data, $pid) {
    if (!af_is_alu_product($pid)) return $data;
    $sizes = af_alu_size_prices();
    if (!$sizes) return $data;
    $size = isset($_POST['af_alu_size']) ? sanitize_text_field(wp_unslash($_POST['af_alu_size'])) : '';
    if (!isset($sizes[$size])) $size = af_alu_default_size();   // a forged size falls back to the smallest
    $colors = af_alu_colors(wc_get_product($pid));
    $color  = isset($_POST['af_alu_color']) ? sanitize_text_field(wp_unslash($_POST['af_alu_color'])) : '';
    if (!in_array($color, $colors, true)) $color = $colors[0];
    $data['af_alu_size']    = $size;
    $data['af_alu_color']   = $color;
    $data['af_price']       = $sizes[$size][0];   // applied by the cart's af_price pass
    $data['af_alu_regular'] = $sizes[$size][1];
    $data['af_unique']      = md5('alu|' . $pid . '|' . $size . '|' . $color);
    return $data;
}, 15, 2);

// The line's list and sale numbers follow its size too, so anything reading
// the line's "was" price agrees with what it charges.
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart) || !is_a($cart, 'WC_Cart')) return;
    foreach ($cart->get_cart() as $item) {
        if (empty($item['af_alu_size']) || empty($item['af_price'])) continue;
        $item['data']->set_price($item['af_price']);
        $item['data']->set_sale_price($item['af_price']);
        $item['data']->set_regular_price(isset($item['af_alu_regular']) ? $item['af_alu_regular'] : $item['af_price']);
    }
}, 25);

add_filter('woocommerce_get_item_data', function ($data, $item) {
    if (!empty($item['af_alu_size']))  $data[] = array('name' => 'Size', 'value' => $item['af_alu_size']);
    if (!empty($item['af_alu_color'])) $data[] = array('name' => 'Frame Color', 'value' => $item['af_alu_color']);
    return $data;
}, 20, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $key, $values) {
    if (!empty($values['af_alu_size']))  $item->add_meta_data('Size', $values['af_alu_size']);
    if (!empty($values['af_alu_color'])) $item->add_meta_data('Frame Color', $values['af_alu_color']);
}, 20, 3);
