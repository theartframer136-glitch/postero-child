<?php
/**
 * WHAT THE CUSTOMER RECEIVES — the four ways to buy a piece.
 *
 * Owner's requirement (2026-08-17, handwritten note + explanation):
 *
 *   1. Digital download   — the file only. Nothing ships, so no delivery is
 *                           charged. The artwork still costs its full price.
 *   2. Painting only      — the printed canvas, rolled in a tube.
 *   3. Painting + bar + DIY kit — plus the wooden structure (stretcher) bars
 *                           and the fittings needed to hang it.
 *   4. Painting + bar + frame + DIY kit — as above with the frame the customer
 *                           picked.
 *
 * Updated 2026-08-22 on the owner's instruction: the separate "Full DIY kit"
 * is gone, and what used to make it a kit — hooks, screws, screwdriver and
 * hanging strip — now comes with both of the structure-bar options instead. So
 * anything that arrives on bars arrives ready to put on the wall, and the
 * customer no longer has to work out which of two similar rows to pick.
 *
 * Both of those rows say "DIY kit" in their own label, because dropping the
 * words altogether hid the thing from the people looking for it: a customer who
 * came for a DIY kit could no longer see one anywhere on the page, even though
 * it is now in both options. The label is the only change — the parts, the keys
 * and the prices are exactly as they were.
 *
 * The retired option is still understood (see af_kit_retired_options) so an
 * order already placed against it still prints what was actually sent.
 *
 * PRICES ARE DELIBERATELY ZERO. The owner does not have supplier costs yet and
 * asked for the functionality first, so every add-on is £0/$0 until real
 * numbers arrive. Nothing here invents a charge. When the prices come, they go
 * in ONE option — no deploy, no code change:
 *
 *   wp option update af_kit_prices --format=json \
 *     '{"bar":25,"hooks":4,"screws":3,"driver":6,"hanging":5}'
 *   wp option update af_tube_prices --format=json \
 *     '{"30":4,"40":5,"50":6,"60":8}'
 *
 * Until then the selector works, the choice is recorded on the cart, the order
 * and the packing slip, and delivery is calculated correctly for it — the only
 * thing missing is money changing hands for the parts.
 */
if (!defined('ABSPATH')) exit;

/** The four options, in the order the customer sees them. */
function af_kit_options() {
    return apply_filters('af_kit_options', array(
        'digital' => array(
            'label' => 'Digital download',
            'desc'  => 'The high-resolution file, sent to you. Nothing is posted.',
            'parts' => array(),
            'ships' => false,
        ),
        'painting' => array(
            'label' => 'Painting only',
            'desc'  => 'The printed canvas, rolled in a protective tube.',
            'parts' => array(),
            'ships' => true,
        ),
        'painting_bar' => array(
            'label' => 'Painting + structure bars + DIY kit',
            'desc'  => 'The canvas with wooden stretcher bars, plus hooks, screws, '
                     . 'screwdriver and hanging strip — everything rolled in one tube.',
            'parts' => array('bar', 'hooks', 'screws', 'driver', 'hanging'),
            'ships' => true,
        ),
        'painting_bar_frame' => array(
            'label' => 'Painting + structure bars + frame + DIY kit',
            'desc'  => 'Stretched and finished in the frame you choose above, with '
                     . 'hooks, screws, screwdriver and hanging strip included.',
            'parts' => array('bar', 'hooks', 'screws', 'driver', 'hanging'),
            'ships' => true,
        ),
    ));
}

/**
 * No longer offered, but still understood.
 *
 * An order placed before the DIY kit was retired carries af_kit=full_kit. Its
 * label was written onto the order line at checkout, so the customer's copy is
 * safe either way — but the packing slip reads the key back, and without this
 * the studio would be handed a line with no parts on it. Nothing here is
 * selectable: the selector only ever renders af_kit_options().
 */
function af_kit_retired_options() {
    return array(
        'full_kit' => array(
            'label' => 'Full DIY kit',
            'desc'  => 'Painting, structure bars, hooks, screws, screwdriver and '
                     . 'hanging strip — everything rolled in one tube.',
            'parts' => array('bar', 'hooks', 'screws', 'driver', 'hanging'),
            'ships' => true,
        ),
    );
}

/** One option by key, retired ones included, so an old order still reads right. */
function af_kit_option($key) {
    $live = af_kit_options();
    if (isset($live[$key])) return $live[$key];
    $gone = af_kit_retired_options();
    return isset($gone[$key]) ? $gone[$key] : null;
}

/**
 * What each part adds. ZERO until the owner supplies prices — a part with no
 * price simply adds nothing, so no customer is ever charged a made-up figure.
 */
function af_kit_prices() {
    $set = get_option('af_kit_prices');
    if (is_string($set)) $set = json_decode($set, true);
    $base = array('bar' => 0, 'hooks' => 0, 'screws' => 0, 'driver' => 0, 'hanging' => 0);
    return is_array($set) ? array_merge($base, $set) : $base;
}

/** Are any kit prices actually set yet? Drives the "pricing pending" notice. */
function af_kit_priced() {
    foreach (af_kit_prices() as $v) { if ((float) $v > 0) return true; }
    return false;
}

/**
 * Tube prices by length in inches. The tube is chosen from the artwork's SHORT
 * side, because that is what the rolled canvas measures along the tube. Zero
 * until the owner gives the sizes he stocks and their cost.
 */
function af_tube_prices() {
    $set = get_option('af_tube_prices');
    if (is_string($set)) $set = json_decode($set, true);
    return is_array($set) ? $set : array();
}

/** The tube that fits this size label, and what it costs (0 when unpriced). */
function af_tube_for_size($size_label) {
    $in = function_exists('af_ship_inches') ? af_ship_inches($size_label) : null;
    if (!$in) return array(null, 0.0);
    sort($in);
    $short = (float) $in[0];
    $tubes = af_tube_prices();
    if (!$tubes) return array(null, 0.0);
    ksort($tubes, SORT_NUMERIC);
    foreach ($tubes as $len => $cost) {
        if ($short <= (float) $len) return array((int) $len, (float) $cost);
    }
    end($tubes);
    return array((int) key($tubes), (float) current($tubes));
}

/**
 * Stretcher bars are priced by area: $4 a square foot of the chosen size
 * (owner's instruction, 25 Sep). 3×4 ft = 12 sq ft = $48. Change the rate in
 * one place: wp option update af_bar_rate_sqft 4
 */
function af_bar_rate_sqft() {
    $r = get_option('af_bar_rate_sqft');
    return ($r !== false && is_numeric($r) && (float) $r >= 0) ? (float) $r : 4.0;
}
function af_bar_price($size_label) {
    $in = function_exists('af_ship_inches') ? af_ship_inches($size_label) : null;
    if (!$in) return 0.0;
    return round(($in[0] * $in[1] / 144) * af_bar_rate_sqft(), 2);
}

/** What the chosen option adds to the price of one piece. */
function af_kit_addon_price($key, $size_label = '') {
    $opt = af_kit_option($key);
    if (!$opt) return 0.0;
    $prices = af_kit_prices();
    $sum = 0.0;
    foreach ($opt['parts'] as $part) {
        if ($part === 'bar' && $size_label !== '') { $sum += af_bar_price($size_label); continue; }
        $sum += isset($prices[$part]) ? (float) $prices[$part] : 0.0;
    }
    // the tube only applies to something that actually posts
    if (!empty($opt['ships']) && $size_label !== '') {
        list($len, $cost) = af_tube_for_size($size_label);
        $sum += (float) $cost;
    }
    return round($sum, 2);
}

/** Does this choice put a parcel in the post? */
function af_kit_ships($key) {
    $opt = af_kit_option($key);
    return $opt ? !empty($opt['ships']) : true;
}

/**
 * Corporate Printing (and its sub-collections) is printed work only: the
 * owner's instruction, 25 Sep, is that these products offer "Painting only"
 * and nothing else. Every other category keeps all four options.
 */
function af_kit_is_corporate($pid) {
    $pid = (int) $pid;
    if (!$pid) return false;
    $root = get_term_by('slug', 'corporate-printing', 'product_cat');
    if (!$root || is_wp_error($root)) return false;
    foreach (wc_get_product_term_ids($pid, 'product_cat') as $tid) {
        if ((int) $tid === (int) $root->term_id) return true;
        if (in_array((int) $root->term_id, array_map('intval', get_ancestors($tid, 'product_cat')), true)) return true;
    }
    return false;
}

/** The options this product offers. */
function af_kit_options_for($pid) {
    $opts = af_kit_options();
    if (af_kit_is_corporate($pid) && isset($opts['painting'])) {
        return array('painting' => $opts['painting']);
    }
    return $opts;
}

function af_kit_default() {
    return apply_filters('af_kit_default', 'painting');
}

// ─────────────────────────────────────────────────────────────
// The selector on the product page
// ─────────────────────────────────────────────────────────────
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    if (!$product || !function_exists('af_pricing_applies') || !af_pricing_applies($product)) return;
    $opts = af_kit_options_for($product->get_id());
    $sel  = af_kit_default();
    if (!isset($opts[$sel])) $sel = array_key_first($opts);
    ?>
<div class="af-kit-group" id="af-kit-group"
     data-bar-rate="<?php echo esc_attr(af_bar_rate_sqft()); ?>"
     data-frame-real="<?php echo esc_attr(function_exists('af_frame_first_real') ? af_frame_first_real() : ''); ?>"
     data-dd-now="<?php echo esc_attr(function_exists('af_digital_price') ? af_digital_price($product->get_id()) : ''); ?>"
     data-dd-was="<?php echo esc_attr(function_exists('af_digital_was') ? af_digital_was($product->get_id()) : ''); ?>">
  <label class="af-opt-label">What you receive</label>
  <div class="af-kit-list">
    <?php foreach ($opts as $key => $o) : ?>
      <label class="af-kit-item<?php echo $key === $sel ? ' is-on' : ''; ?>">
        <input type="radio" name="af_kit" value="<?php echo esc_attr($key); ?>"
               <?php checked($key, $sel); ?>>
        <span class="af-kit-text">
          <strong><?php echo esc_html($o['label']); ?></strong>
          <em><?php echo esc_html($o['desc']); ?></em>
        </span>
        <span class="af-kit-add" data-kit="<?php echo esc_attr($key); ?>"></span>
      </label>
    <?php endforeach; ?>
  </div>
  <?php if (!af_kit_priced() && isset($opts['painting_bar'])) : ?>
    <?php /* This note appears only while af_kit_prices() is all zeroes, which is
             the truth right now: the parts cost the customer nothing. It used to
             say so by explaining our side of it — "while we finalise pricing" —
             and that sentence sat directly above Add to Cart on every product
             page, where it reads as "this shop is not open yet" at the exact
             moment someone is deciding to commit. The fact is the same either
             way; only one version of it is the customer's business. The parts
             named are the ones af_kit_options() actually ships: bar, hooks,
             screws, driver, hanging. */ ?>
    <p class="af-kit-note">Stretcher bars are priced by size ($<?php echo esc_html(rtrim(rtrim(number_format(af_bar_rate_sqft(), 2), '0'), '.')); ?> a square foot). Hooks, screws, screwdriver and hanging strip are included at no extra charge.</p>
  <?php endif; ?>
</div>
<style>
.af-kit-group{margin:14px 0 6px;padding:14px 0 4px;border-top:1px solid #eee}
.af-kit-list{display:flex;flex-direction:column;gap:8px;margin-top:8px}
.af-kit-item{display:flex;align-items:flex-start;gap:10px;padding:11px 13px;border:1.5px solid #e7e7e7;
  border-radius:10px;cursor:pointer;transition:border-color .18s,background .18s}
.af-kit-item:hover{border-color:#c9a84c}
.af-kit-item.is-on{border-color:#c9a84c;background:#fdfaf2}
.af-kit-item input{margin-top:3px;accent-color:#c9a84c;flex:0 0 auto}
.af-kit-text{display:flex;flex-direction:column;gap:2px;flex:1 1 auto}
.af-kit-text strong{font-size:14px;color:#1a1a1a;font-weight:600}
.af-kit-text em{font-size:12.5px;color:#6b6b6b;font-style:normal;line-height:1.45}
.af-kit-add{font-size:13px;font-weight:600;color:#8a6d1f;white-space:nowrap}
.af-kit-note{margin:9px 0 0;font-size:12.5px;color:#6b6b6b}
/* A file has no size, frame or frame colour to choose. */
body.af-kit-digital .af-opts .af-opt-group,
body.af-kit-digital .af-opts .af-wall-hint,
body.af-kit-digital .af-opts .af-color-tip{display:none !important}
/* Painting only / on bars: rolled, no frame to choose. */
body.af-kit-noframe .af-opts .af-grp-frame,
body.af-kit-noframe .af-opts .af-grp-color,
body.af-kit-noframe .af-opts .af-color-tip{display:none !important}
</style>
<script>
(function(){
  var g = document.getElementById('af-kit-group');
  if (!g) return;
  g.addEventListener('change', function(e){
    if (!e.target.matches('input[name="af_kit"]')) return;
    g.querySelectorAll('.af-kit-item').forEach(function(l){ l.classList.remove('is-on'); });
    var item = e.target.closest('.af-kit-item');
    if (item) item.classList.add('is-on');
    // the frame choice is only meaningful when a frame is actually included
    var frameGroup = document.querySelector('.af-opt-group-frame, [data-af-opt="frame"]');
    if (frameGroup) {
      var needsFrame = e.target.value === 'painting_bar_frame';
      frameGroup.style.opacity = needsFrame ? '1' : '';
    }
    document.dispatchEvent(new CustomEvent('af:kit-changed', {detail:{kit: e.target.value}}));
    setTimeout(function(){ frameFor(e.target.value); digital(e.target.value === 'digital'); }, 60);
  });

  // Digital download: the price is the file's (the same figure the Digital
  // Download modal and the cart charge), and size and frame are hidden.
  // Any other choice puts them back and re-prices from the chosen size.
  var wasDigital = false;
  function digital(on){
    document.body.classList.toggle('af-kit-digital', on);
    if (!on) {
      // re-price from the chosen size: leaving the file, or moving between
      // options that do and don't include stretcher bars
      var sel = document.getElementById('af-size-select');
      if (sel) sel.dispatchEvent(new Event('change', {bubbles:true}));
      wasDigital = false;
      return;
    }
    wasDigital = true;
    var now = parseFloat(g.getAttribute('data-dd-now')), was = parseFloat(g.getAttribute('data-dd-was'));
    if (!(now > 0)) return;
    var live = document.getElementById('af-live-price');
    var sym  = live ? ((live.textContent || '').match(/^[^0-9]*/) || ['$'])[0] || '$' : '$';
    var fmt  = function(v){ return sym + v.toFixed(2); };
    var pct  = was > now ? Math.round((was - now) / was * 100) : 0;
    if (live) live.innerHTML = '<span class="amount">' + fmt(now) + '</span>';
    var mrp = document.getElementById('af-live-mrp'), disc = document.getElementById('af-live-disc');
    if (mrp)  mrp.textContent  = was > now ? fmt(was) : '';
    if (disc) disc.textContent = pct > 0 ? '(' + pct + '% OFF)' : '';
    var head = document.querySelector('.summary .price, .entry-summary .price, p.price');
    if (head) {
      var ins = head.querySelector('ins'), del = head.querySelector('del');
      var cur = (ins || head).querySelector('.woocommerce-Price-amount, .amount');
      if (cur) cur.innerHTML = fmt(now);
      var old = del ? del.querySelector('.woocommerce-Price-amount, .amount') : null;
      if (old) old.innerHTML = fmt(was);
      var p = head.querySelector('.af-pct-off');
      if (p) p.textContent = pct > 0 ? '(' + pct + '% off)' : '';
      head.querySelectorAll('.screen-reader-text').forEach(function(sr){
        if (/original price/i.test(sr.textContent)) sr.textContent = 'Original price was: ' + fmt(was) + '.';
        else if (/current price/i.test(sr.textContent)) sr.textContent = 'Current price is: ' + fmt(now) + '.';
      });
    }
  }
  // Only "Painting + structure bars + frame + DIY kit" has a frame in it.
  // Painting only, and painting on bars, arrive rolled: no Frame Type, no
  // Frame Color, and the price is the unframed one (the cart enforces the
  // same). The frame the visitor had picked comes back if they return to the
  // framed option.
  var FRAMED = 'painting_bar_frame', keptFrame = null;
  function frameGroups(){
    var opts = document.querySelector('.af-opts'); if (!opts) return;
    opts.querySelectorAll('.af-opt-group').forEach(function(gr){
      if (gr.querySelector('.af-frame-chips')) gr.classList.add('af-grp-frame');
      if (gr.querySelector('.af-color-chips')) gr.classList.add('af-grp-color');
    });
    return opts;
  }
  function frameFor(kit){
    var opts = frameGroups(); if (!opts) return;
    var noFrame = kit === 'painting' || kit === 'painting_bar';
    document.body.classList.toggle('af-kit-noframe', noFrame);
    var active = opts.querySelector('.af-frame-chips .af-chip-opt.active');
    var cur = active ? active.getAttribute('data-val') : '';
    if (noFrame) {
      if (cur && cur !== 'Without Frame') {
        keptFrame = cur;
        var none = opts.querySelector('.af-frame-chips .af-chip-opt[data-val="Without Frame"]');
        if (none) none.click();
      }
    } else if (kit === FRAMED && cur === 'Without Frame') {
      // A framed kit has a frame in it. Bring back the one the visitor had
      // picked, else the first frame in stock — "+ frame" is never frameless.
      var want = keptFrame || g.getAttribute('data-frame-real') || '';
      var back = want ? opts.querySelector('.af-frame-chips .af-chip-opt[data-val="' + want + '"]:not([disabled])') : null;
      if (!back) back = opts.querySelector('.af-frame-chips .af-chip-opt:not([disabled]):not([data-val="Without Frame"])');
      if (back) back.click();
      keptFrame = null;
    }
  }
  // Choosing Without Frame while on the framed kit is choosing the kit
  // without its frame: move to that kit, so the price and the parcel agree.
  document.addEventListener('click', function(e){
    var chip = e.target.closest ? e.target.closest('.af-frame-chips .af-chip-opt[data-val="Without Frame"]') : null;
    if (!chip || chip.disabled) return;
    var on = g.querySelector('input[name="af_kit"]:checked');
    if (!on || on.value !== FRAMED) return;
    var bars = g.querySelector('input[name="af_kit"][value="painting_bar"]');
    if (!bars) return;
    bars.checked = true;
    bars.dispatchEvent(new Event('change', {bubbles:true}));
  });
  // On arrival: a frame already chosen elsewhere (Try On Wall passes one in
  // the address) means the visitor wants it framed, so open on the framed
  // option instead of silently dropping their frame.
  function startUp(){
    var start = g.querySelector('input[name="af_kit"]:checked');
    var opts = frameGroups();
    var active = opts ? opts.querySelector('.af-frame-chips .af-chip-opt.active') : null;
    if (start && start.value !== FRAMED && start.value !== 'digital' && active
        && active.getAttribute('data-val') !== 'Without Frame') {
      var framed = g.querySelector('input[name="af_kit"][value="' + FRAMED + '"]');
      if (framed) { framed.checked = true; framed.dispatchEvent(new Event('change', {bubbles:true})); return; }
    }
    if (start) { frameFor(start.value); if (start.value === 'digital') digital(true); }
  }
  if (document.readyState === 'complete') setTimeout(startUp, 300);
  else window.addEventListener('load', function(){ setTimeout(startUp, 300); });
})();
</script>
    <?php
}, 25);

// ─────────────────────────────────────────────────────────────
// Cart, price and shipping
// ─────────────────────────────────────────────────────────────

// Corporate Printing offers Painting only. A different af_kit posted for one
// (an old page, a hand-made request) is rewritten before any pricing reads
// it, so it can never be bought as a digital download or a kit this way.
add_filter('woocommerce_add_cart_item_data', function ($data, $pid) {
    if (isset($_POST['af_kit']) && af_kit_is_corporate($pid)) {
        $_POST['af_kit'] = $_REQUEST['af_kit'] = 'painting';
    }
    return $data;
}, 1, 2);
add_filter('woocommerce_add_cart_item_data', function ($data, $pid) {
    $product = wc_get_product($pid);
    if (!$product || !function_exists('af_pricing_applies') || !af_pricing_applies($product)) return $data;
    $kit = isset($_POST['af_kit']) ? sanitize_text_field(wp_unslash($_POST['af_kit'])) : af_kit_default();
    $opts = af_kit_options_for($pid);
    if (!isset($opts[$kit])) $kit = isset($opts[af_kit_default()]) ? af_kit_default() : array_key_first($opts);
    $data['af_kit'] = $kit;
    // keep lines with different choices separate in the cart
    if (isset($data['af_unique'])) $data['af_unique'] = md5($data['af_unique'] . '|' . $kit);
    return $data;
}, 20, 2);

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (empty($cart) || !is_a($cart, 'WC_Cart')) return;
    foreach ($cart->get_cart() as $item) {
        if (empty($item['af_kit']) || !isset($item['data'])) continue;
        $kit  = $item['af_kit'];
        $size = isset($item['af_size']) ? $item['af_size'] : '';

        // A digital download posts nothing. Marking THIS cart line virtual is
        // what stops WooCommerce building a shipping package for it — the
        // products themselves are downloadable but still flagged as physical,
        // which is why a download was being charged delivery.
        if (!af_kit_ships($kit) && method_exists($item['data'], 'set_virtual')) {
            $item['data']->set_virtual(true);
        }

        $add = af_kit_addon_price($kit, $size);
        if ($add > 0) {
            $item['data']->set_price((float) $item['data']->get_price() + $add);
        }
    }
}, 25);

/** A basket of downloads alone must not ask for a shipping address. */
add_filter('woocommerce_cart_needs_shipping', function ($needs) {
    if (!function_exists('WC') || !WC()->cart) return $needs;
    $items = WC()->cart->get_cart();
    if (!$items) return $needs;
    foreach ($items as $item) {
        if (empty($item['af_kit'])) return $needs;      // an ordinary line: unchanged
        if (af_kit_ships($item['af_kit'])) return $needs;
    }
    return false;
}, 20);

// show the choice in the cart, checkout and the order
add_filter('woocommerce_get_item_data', function ($data, $item) {
    if (empty($item['af_kit'])) return $data;
    $opt = af_kit_option($item['af_kit']);
    if (!$opt) return $data;
    $data[] = array('name' => 'You receive', 'value' => $opt['label']);
    if (af_kit_ships($item['af_kit']) && !empty($item['af_size'])) {
        list($len, $cost) = af_tube_for_size($item['af_size']);
        if ($len) $data[] = array('name' => 'Ships in', 'value' => $len . '" tube');
    }
    return $data;
}, 20, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $key, $values) {
    if (empty($values['af_kit'])) return;
    $opt = af_kit_option($values['af_kit']);
    if ($opt) {
        $item->add_meta_data('You receive', $opt['label']);
    }
    $item->add_meta_data('_af_kit', $values['af_kit'], true);
    if (af_kit_ships($values['af_kit']) && !empty($values['af_size'])) {
        list($len, $cost) = af_tube_for_size($values['af_size']);
        if ($len) $item->add_meta_data('_af_tube_in', $len, true);
    }
}, 20, 3);

/** The packing slip needs the parts list, so the studio knows what to pack. */
add_filter('af_packing_extra_lines', function ($lines, $order) {
    if (!$order) return $lines;
    foreach ($order->get_items() as $item) {
        $kit = $item->get_meta('_af_kit');
        if (!$kit) continue;
        $opt = af_kit_option($kit);
        if (!$opt) continue;
        $parts = $opt['parts'];
        $lines[] = sprintf('%s — %s%s',
            $item->get_name(),
            $opt['label'],
            $parts ? (' (' . implode(', ', $parts) . ')') : '');
    }
    return $lines;
}, 10, 2);
