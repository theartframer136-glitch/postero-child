<?php
/**
 * Gold Foiled & UV — a category of its own, priced off the same rate card.
 *
 * Owner request (2026-08-22): "create a another section on category that will
 * be gold foiled and uv and that products price will be 40% of normal products
 * price and the product image folder path i am giving after few minutes".
 *
 * HOW THE PRICE IS DECIDED
 * The store does not price a canvas from a per-product number — the SIZE sets
 * the price, straight from the printed rate card (af_pricing_config()['sizes']).
 * So this section is not a list of hand-typed prices that would drift the
 * moment the card changes: it is a RATIO applied to that card, in one place.
 *
 *     gold-foil price for a size = card price for that size x af_goldfoil_ratio
 *
 * Because af_pricing_config() is what the product page's selector, the cart's
 * server-side calculator and the deploy's repricing tool all read, scaling it
 * there means the grid price, the price the selector opens on, the price the
 * selector recomputes when a size is picked, and the price actually charged are
 * the same number — with no second price book to keep in sync.
 *
 * Frame and colour surcharges are NOT scaled. The foiling and UV coating are
 * done to the print; a moulding costs what a moulding costs.
 *
 * THE RATIO IS A SETTING, NOT A CONSTANT
 * Settled by the owner (2026-08-22): "just price is 40% more the normal product
 * price" — so 1.40 x, the premium reading, and that is the default. The first
 * wording ("40% of") read literally as 0.40 x and was flagged rather than
 * assumed; this replaces it. One command changes it again, and every price the
 * section shows or charges follows:
 *
 *     wp option update af_goldfoil_ratio 1.4    # 40% MORE than a normal print (default)
 *     wp option update af_goldfoil_ratio 0.4    # 40% OF a normal print
 *
 * A whole number is read as a percentage, so `40` means 0.40 and `140` means
 * 1.40 — whichever way it gets typed, it lands where it was meant to.
 */
if (!defined('ABSPATH')) exit;

/** The category slug and display name this whole section hangs off. */
function af_goldfoil_slug() { return 'gold-foiled-uv'; }
function af_goldfoil_name() { return 'Gold Foiled & UV'; }

/**
 * What a gold-foil size costs relative to the same size on a normal print.
 * Clamped so a stray option value can never zero out the catalogue or invent a
 * ten-fold price.
 */
function af_goldfoil_ratio() {
    $raw = get_option('af_goldfoil_ratio', '');
    $r = ($raw === '' || $raw === false) ? 1.40 : (float) $raw;
    // typed as a percentage ("40", "140") rather than a multiplier
    if ($r > 5) $r = $r / 100;
    if ($r < 0.05) $r = 0.05;
    if ($r > 5)    $r = 5;
    return apply_filters('af_goldfoil_ratio', $r);
}

/** The category term, or null when it has not been created yet. */
function af_goldfoil_term() {
    static $term = false;
    if ($term !== false) return $term;
    $t = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
    $term = ($t && !is_wp_error($t)) ? $t : null;
    return $term;
}

/**
 * Is this product part of the section? True for the category itself and for
 * anything filed under a child of it, so sub-sections ("Gold Foiled & UV →
 * Ganesha") price the same way without being listed here.
 */
function af_is_goldfoil($product) {
    $pid = ($product instanceof WC_Product) ? $product->get_id() : (int) $product;
    if ($pid <= 0) return false;

    // A shop page asks this twice per card — once for the badge, once for the
    // price — so the answer is remembered for the request.
    static $memo = array();
    if (isset($memo[$pid])) return $memo[$pid];

    $memo[$pid] = false;
    if (get_post_meta($pid, '_af_goldfoil', true) === 'yes') return $memo[$pid] = true;

    $term = af_goldfoil_term();
    if (!$term) return false;
    $terms = get_the_terms($pid, 'product_cat');
    if (!$terms || is_wp_error($terms)) return false;
    foreach ($terms as $t) {
        if ((int) $t->term_id === (int) $term->term_id) return $memo[$pid] = true;
        $anc = get_ancestors($t->term_id, 'product_cat');
        if (in_array((int) $term->term_id, array_map('intval', $anc), true)) return $memo[$pid] = true;
    }
    return false;
}

/**
 * The multiplier to apply to a rate-card size price for one product: the ratio
 * for a gold-foil piece, 1.0 for everything else. This is the ONE predicate the
 * pricing engine asks — see af_pricing_config().
 */
function af_goldfoil_factor($product_id) {
    $product_id = (int) $product_id;
    if ($product_id <= 0) return 1.0;
    return af_is_goldfoil($product_id) ? af_goldfoil_ratio() : 1.0;
}

/**
 * The least a piece in this section may cost, whatever its size.
 *
 * Owner, 2026-08-26: "all price will be more than 120". The ratio alone cannot
 * deliver that. 40% more than the card's smallest sizes is $84 to $112 — the
 * arithmetic is right, but a premium line that opens under the owner's floor is
 * not what was asked for. Raising the ratio instead would break the rule
 * settled the day before ("if price is 100 then 100+40 = 140"), so the two live
 * side by side: the ratio decides the price, and this decides how low it is
 * allowed to land.
 *
 * The floor only ever RAISES a price, so it can be read as: 40% more than a
 * normal print, and never less than this. Sizes above it are untouched and stay
 * exactly 40% more — a 3x5 is $100 on the card and $140 here, floor or no floor.
 *
 * One command moves it, and every surface follows:
 *
 *     wp option update af_goldfoil_min_price 150
 *     wp option update af_goldfoil_min_price 0     # no floor at all
 */
function af_goldfoil_min_price() {
    $raw = get_option('af_goldfoil_min_price', '');
    $v = ($raw === '' || $raw === false) ? 120.0 : (float) $raw;
    if ($v < 0) $v = 0.0;
    return (float) apply_filters('af_goldfoil_min_price', $v);
}

/**
 * Scale one card price by the section's ratio, then hold it to the floor.
 *
 * This used to round the result to the nearest $5 so the section would read
 * like a price list. That quietly broke the 40%: a 3x4 came out at $110 against
 * a $80 card price, which is 37.5% more, and a 3x2 at $85 against $60, which is
 * 41.7%. Nothing is lost by dropping it — every price on the card is a multiple
 * of 5 and 5 x 1.4 = 7, so the scaled figure is already a whole number for every
 * size the studio sells ($60 -> $84, $80 -> $112, $100 -> $140, $150 -> $210).
 *
 * The floor is applied HERE, on the per-size price, rather than to the finished
 * product price. That is deliberate: this function is what feeds the size
 * selector, so a shopper who switches a floored product to its smallest size
 * cannot drop below the floor either. Frame and colour surcharges are added
 * afterwards and only ever push the total further up.
 */
function af_goldfoil_scale($usd, $factor) {
    $usd = (float) $usd * (float) $factor;
    if ($factor == 1.0) return $usd;          // not a gold-foil piece: untouched
    $usd = round($usd, 2);
    $min = af_goldfoil_min_price();
    if ($min > 0 && $usd < $min) $usd = $min;
    return $usd < 5 ? 5.0 : (float) $usd;
}

/* ── The badge ─────────────────────────────────────────────────────────────
 * A foiled, UV-coated piece has to LOOK like the premium line in a grid of
 * ordinary prints, otherwise the section is just a menu entry. One small gold
 * ribbon on the card and one line above the title on the product page.
 */

function af_goldfoil_badge_html() {
    return '<span class="af-gf-badge">Gold Foiled &amp; UV</span>';
}

// after_shop_loop_item_title, not before_: measured on the live grid, this
// theme's card template never fires the before_ hook — the badge rendered
// nowhere. after_ is the hook the Art Code line (PHASE 25) already proves out;
// priority 8 puts the badge above that line.
add_action('woocommerce_after_shop_loop_item_title', function () {
    $product = function_exists('af_wc_product') ? af_wc_product() : ($GLOBALS['product'] ?? null);
    if (!($product instanceof WC_Product) || !af_is_goldfoil($product)) return;
    echo '<span class="af-gf-flag">' . af_goldfoil_badge_html() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
}, 8);

add_action('woocommerce_single_product_summary', function () {
    $product = function_exists('af_wc_product') ? af_wc_product() : ($GLOBALS['product'] ?? null);
    if (!($product instanceof WC_Product) || !af_is_goldfoil($product)) return;
    echo '<p class="af-gf-line">' . af_goldfoil_badge_html()
       . ' <span>Real gold foil detailing, sealed under a UV-cured coat.</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
}, 4);

add_action('wp_head', function () {
    ?>
<style id="af-goldfoil-css">
/* Deliberately NOT position:absolute. Pinning it to the card corner would mean
   giving every product card a positioning context, and the cards already hold
   absolutely-positioned children (the discount badge, the hover actions) that
   are placed against an ancestor further up — moving that context would shift
   them. An inline chip above the title cannot disturb anything. */
.af-gf-flag{display:block;margin:0 0 6px;line-height:1}
.af-gf-badge{display:inline-block;padding:4px 9px;border-radius:999px;
  font:600 10.5px/1.35 inherit;letter-spacing:.04em;text-transform:uppercase;
  color:#3d2f06;background:linear-gradient(135deg,#f7e7a8 0%,#d4af37 45%,#b8912a 100%);
  box-shadow:0 1px 3px rgba(0,0,0,.18);white-space:nowrap}
.af-gf-line{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:0 0 10px}
.af-gf-line span{color:#6b6b6b;font-size:13.5px}
.af-gf-intro,.tax-product_cat.term-gold-foiled-uv .term-description{color:#6b6b6b;font-size:14.5px;margin:4px 0 10px}
.af-gf-intro strong,.tax-product_cat.term-gold-foiled-uv .term-description strong{color:#8a6d1f}
</style>
    <?php
}, 9);

/* ── The shop sidebar's Categories list ───────────────────────────────────
 * Handled in af_sidebar_cat_menu(), not here.
 *
 * There was a filter at this spot that widened the widget's hide_empty
 * condition, on the belief — stated in a long-standing comment in
 * functions.php — that emptiness was what kept this section out of that list.
 * It is not, and the filter never even ran: that widget's query has
 * hide_empty OFF and an explicit `include` allow-list of the nine categories
 * the header menu shows. A tenth row in af_sidebar_cat_menu() is the whole
 * fix, and it is the right place for it, because that list exists to mirror
 * the header menu.
 */

/**
 * The line of explanation at the top of this archive is the CATEGORY'S OWN
 * description, set by tools/setup-gold-foil.php and editable in wp-admin under
 * Products -> Categories without touching this code or waiting for a deploy.
 *
 * There was a second paragraph printed from here, on this same
 * woocommerce_archive_description hook, saying the same thing in different
 * words — so the page opened with the premium finish explained twice, one
 * sentence under the other. Removed rather than reworded: two places writing
 * the same sentence would drift apart the moment either was edited, and the
 * one the owner can edit is the one worth keeping.
 *
 * The .af-gf-intro styling above still applies — WooCommerce prints the term
 * description inside .term-description, which the rule now covers.
 */

/* ── The badge on the theme's own grid cards ──────────────────────────────
 * Measured on a fresh (cache-miss) category render: the Postero main-grid
 * card template fires NEITHER woocommerce_before_ NOR after_shop_loop_item_title
 * — the PHP hook above only reaches the standard Woo templates (related rows,
 * up-sells). This is the same gap the Art Code line closes with PHASE 25b's
 * client-side pass, and the same fix applies. No per-product lookup is needed:
 * WordPress already stamps every card <li> with product_cat-gold-foiled-uv,
 * so the class IS the data.
 */
add_action('wp_footer', function () {
    if (is_admin()) return; ?>
<script>
(function(){
  var BADGE = <?php echo wp_json_encode('<span class="af-gf-flag">' . af_goldfoil_badge_html() . '</span>'); ?>;
  function run(){
    var cards = document.querySelectorAll(
      'li[class*="product_cat-gold-foiled-uv"], .product-card[class*="product_cat-gold-foiled-uv"]');
    Array.prototype.forEach.call(cards, function(card){
      if (card.getAttribute('data-af-gf')) return;
      card.setAttribute('data-af-gf', '1');
      if (card.querySelector('.af-gf-flag')) return;      // a hook already placed one
      var title = card.querySelector(
        '.woocommerce-loop-product__title, .product-title, h2 a, h3 a, h2, h3');
      if (!title) return;
      var host = (title.tagName === 'A' && title.parentNode) ? title.parentNode : title;
      var span = document.createElement('span');
      span.innerHTML = BADGE;
      host.parentNode.insertBefore(span.firstChild, host);
    });
  }
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  [400, 1200, 2500].forEach(function(d){ setTimeout(run, d); });
  try {
    var obs = new MutationObserver(function(m){
      for (var i = 0; i < m.length; i++){
        if (m[i].addedNodes && m[i].addedNodes.length){ run(); break; }
      }
    });
    obs.observe(document.body, {childList: true, subtree: true});
  } catch (e) {}
})();
</script>
<?php }, 61);
