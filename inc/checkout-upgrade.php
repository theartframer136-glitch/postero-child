<?php
/**
 * Add stretcher bars, or bars and a frame, from the checkout "Your order" list.
 *
 * Owner, 30 Sep: "on checkout continue to give option to add stretcher bar and
 * frame". A shopper who chose "Painting only" on the product page could only
 * change it by going back to the product. Each eligible line in the checkout
 * list now carries the same three choices the product page offers for a
 * printed piece - Painting only / + stretcher bars / + bars and frame - each
 * with the price of one piece, and a frame colour when the frame is chosen.
 * Choosing one updates the line and checkout redraws the list: price, "You
 * receive", "Frame Type", delivery (a framed piece ships flat in a crate) and
 * any oversize fee all follow, because every one of them is worked out from
 * the line's stored choices each time the totals run.
 *
 * The line is changed in place with the product page's own pricing
 * (af_calc_price for size, frame and colour; the kit add-on in
 * inc/kit-choices.php for the bars), so the checkout can never quote a price
 * the product page would not. It keeps its place in the list and its key.
 *
 * Offered only where the product page offers it: a canvas the size and frame
 * engine prices (not a download, gift card, Corporate Printing, an accessory),
 * at a size still on sale, with a real frame in stock.
 */
if (!defined('ABSPATH')) exit;

/** The kits the checkout can switch between. Digital is not one of them. */
function af_co_up_kits() {
    return array('painting', 'painting_bar', 'painting_bar_frame');
}

/** Can this cart line be upgraded (or stepped back) at checkout? */
function af_co_up_eligible($item) {
    if (!is_array($item) || empty($item['af_size']) || empty($item['af_kit'])) return false;
    if (!empty($item['af_digital']) || !empty($item['af_gc'])) return false;
    if (!in_array($item['af_kit'], af_co_up_kits(), true)) return false;
    $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    $product = $pid ? wc_get_product($pid) : null;
    if (!$product || !function_exists('af_pricing_applies') || !af_pricing_applies($product)) return false;
    if (function_exists('af_size_is_offered') && !af_size_is_offered($item['af_size'])) return false;
    if (!function_exists('af_frame_first_real') || af_frame_first_real() === '') return false;
    $opts = function_exists('af_kit_options_for') ? af_kit_options_for($pid) : array();
    foreach (af_co_up_kits() as $k) if (!isset($opts[$k])) return false;
    return true;
}

/** The frame a kit gets on this line: the line's own real frame if it has one. */
function af_co_up_frame_for($item, $kit) {
    if ($kit !== 'painting_bar_frame') return 'Without Frame';
    $f = isset($item['af_frame']) ? (string) $item['af_frame'] : '';
    if ($f !== '' && $f !== 'Without Frame' && function_exists('af_frame_is_in_stock') && af_frame_is_in_stock($f)) return $f;
    return af_frame_first_real();
}

/** The frame colours on sale, name => surcharge (USD). */
function af_co_up_colors($pid) {
    $cfg = af_pricing_config($pid);
    return isset($cfg['colors']) && is_array($cfg['colors']) ? $cfg['colors'] : array();
}

/** The line's colour if it is one we sell, else the first. */
function af_co_up_color_for($item, $pid, $want = '') {
    $colors = af_co_up_colors($pid);
    if ($want !== '' && isset($colors[$want])) return $want;
    $cur = isset($item['af_color']) ? (string) $item['af_color'] : '';
    if ($cur !== '' && isset($colors[$cur])) return $cur;
    return (string) array_key_first($colors);
}

/**
 * One piece of this line with the given kit and colour, in US dollars, worked
 * out exactly as add-to-cart and the cart do it: af_calc_price() for size,
 * frame and colour, plus the kit add-on (the bars) on top.
 */
function af_co_up_unit_price($item, $kit, $color) {
    $pid = (int) $item['product_id'];
    $product = wc_get_product($pid);
    $base = 0.0;
    if ($product) {
        $base = $product->is_type('variable') ? (float) $product->get_variation_price('min') : (float) wc_get_price_to_display($product);
        if ($base <= 0) $base = (float) wc_get_price_to_display($product);
    }
    $frame = af_co_up_frame_for($item, $kit);
    $price = af_calc_price($base, $item['af_size'], $frame, $color, $pid);
    return round($price + (function_exists('af_kit_addon_price') ? af_kit_addon_price($kit, $item['af_size']) : 0.0), 2);
}

/** Rewrite the line's choices the way the add-to-cart filters would have set them. */
function af_co_up_apply($item, $kit, $color) {
    $pid   = (int) $item['product_id'];
    $size  = $item['af_size'];
    $frame = af_co_up_frame_for($item, $kit);
    $color = af_co_up_color_for($item, $pid, $color);
    $product = wc_get_product($pid);
    $base = 0.0;
    if ($product) {
        $base = $product->is_type('variable') ? (float) $product->get_variation_price('min') : (float) wc_get_price_to_display($product);
        if ($base <= 0) $base = (float) wc_get_price_to_display($product);
    }
    $item['af_kit']    = $kit;
    $item['af_frame']  = $frame;
    $item['af_color']  = $color;
    $item['af_price']  = af_calc_price($base, $size, $frame, $color, $pid);
    $item['af_unique'] = md5(md5($size . '|' . $frame . '|' . $color . '|' . $pid) . '|' . $kit);
    return $item;
}

/** A US-dollar amount in the shopper's currency, formatted like the rest of checkout. */
function af_co_up_money($usd) {
    $rate = function_exists('af_fx_rate') ? af_fx_rate() : 1.0;
    return wc_price(round($usd * $rate, 2));
}

// ── The choices, under each eligible line of "Your order" ──────────────────
// WooCommerce prints the quantity through this filter without passing it
// through wp_kses_post (unlike the name), so buttons survive. The block is
// moved below the line's details by the script further down.
add_filter('woocommerce_checkout_cart_item_quantity', function ($html, $cart_item = array(), $cart_item_key = '') {
    try {
        if (function_exists('af_review_rows') && !af_review_rows()) return $html;
        if (!$cart_item_key || !af_co_up_eligible($cart_item)) return $html;
        $pid    = (int) $cart_item['product_id'];
        $cur    = $cart_item['af_kit'];
        $color  = af_co_up_color_for($cart_item, $pid);
        // the product page's own words, the same as the "You receive" line above
        $kopts  = af_kit_options_for($pid);
        $labels = array();
        foreach (af_co_up_kits() as $k) $labels[$k] = $kopts[$k]['label'];
        $qty = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;
        // Mark the current choice only when the line really is priced as it: an
        // old line whose frame no longer matches its kit shows none marked, so
        // every choice (its own included) can be picked to put it right.
        $now = round((float) (isset($cart_item['af_price']) ? $cart_item['af_price'] : 0) + af_kit_addon_price($cur, $cart_item['af_size']), 2);
        if (abs($now - af_co_up_unit_price($cart_item, $cur, $color)) > 0.005) $cur = '';
        $pname = isset($cart_item['data']) && is_object($cart_item['data']) ? $cart_item['data']->get_name() : '';
        $level = ob_get_level();
        ob_start(); ?>
<div class="af-co-up" data-key="<?php echo esc_attr($cart_item_key); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('woocommerce-af-co-upgrade')); ?>" data-endpoint="<?php echo esc_url(WC_AJAX::get_endpoint('af_co_upgrade')); ?>">
  <div class="af-co-up-title">What you receive<?php echo $qty > 1 ? ' <span>(price of one piece)</span>' : ''; ?></div>
  <?php // every size on sale, priced with this line's current choices
  $sizes = function_exists('af_sizes_offered') ? af_sizes_offered() : array();
  if (count($sizes) > 1) : ?>
  <div class="af-co-up-sub">Size</div>
  <div class="af-co-up-sizes" role="group" aria-label="<?php echo esc_attr('Size: ' . $pname); ?>">
    <?php foreach ($sizes as $sz) :
        $alt = $cart_item; $alt['af_size'] = $sz;
        $kit_for = $cur !== '' ? $cur : $cart_item['af_kit'];
        $on = ($sz === $cart_item['af_size']); ?>
    <button type="button" class="af-co-up-size<?php echo $on ? ' is-on' : ''; ?>" data-kit="<?php echo esc_attr($kit_for); ?>" data-size="<?php echo esc_attr($sz); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><span><?php echo esc_html(trim(preg_replace('/\s*\(.*\)\s*$/', '', $sz))); ?></span> <b><?php echo wp_kses_post(af_co_up_money(af_co_up_unit_price($alt, $kit_for, $color))); ?></b></button>
    <?php endforeach; ?>
  </div>
  <div class="af-co-up-sub">What you receive</div>
  <?php endif; ?>
  <div class="af-co-up-opts" role="group" aria-label="<?php echo esc_attr('What you receive: ' . $pname); ?>">
    <?php foreach ($labels as $kit => $label) :
        $on = ($kit === $cur); ?>
    <button type="button" class="af-co-up-opt<?php echo $on ? ' is-on' : ''; ?>" data-kit="<?php echo esc_attr($kit); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
      <span class="af-co-up-name"><?php echo esc_html($label); ?></span>
      <span class="af-co-up-price"><?php echo wp_kses_post(af_co_up_money(af_co_up_unit_price($cart_item, $kit, $color))); ?></span>
    </button>
    <?php endforeach; ?>
  </div>
  <?php if ($cur === 'painting_bar_frame') : ?>
  <div class="af-co-up-colors" role="group" aria-label="<?php echo esc_attr('Frame Color: ' . $pname); ?>">
    <span class="af-co-up-colors-label">Frame Color</span>
    <?php foreach (af_co_up_colors($pid) as $name => $fee) :
        $on = ($name === $color); ?>
    <button type="button" class="af-co-up-color<?php echo $on ? ' is-on' : ''; ?>" data-kit="painting_bar_frame" data-color="<?php echo esc_attr($name); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><?php echo esc_html($name); ?><?php if ((float) $fee > 0) : ?> <small>+<?php echo wp_kses_post(af_co_up_money((float) $fee)); ?></small><?php endif; ?></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <p class="af-co-up-note">Delivery updates with your choice: a framed piece ships flat in a crate, which costs more to deliver (large sizes add oversize handling).</p>
  <p class="af-co-up-msg" role="status" aria-live="polite"></p>
</div>
        <?php
        return $html . ob_get_clean();
    } catch (\Throwable $e) {
        // never leave a buffer open: this runs inside checkout's JSON refresh
        if (isset($level)) { while (ob_get_level() > $level) ob_end_clean(); }
        return $html;
    }
}, 20, 3);

// ── The request that changes the line ───────────────────────────────────────
add_action('wc_ajax_af_co_upgrade', 'af_co_upgrade_handler');
function af_co_upgrade_handler() {
    if (!check_ajax_referer('woocommerce-af-co-upgrade', 'nonce', false)) {
        wp_send_json_error(array('message' => 'This page has expired. Please refresh checkout and try again.'), 403);
    }
    $key   = isset($_POST['key'])   ? sanitize_text_field(wp_unslash($_POST['key']))   : '';
    $kit   = isset($_POST['kit'])   ? sanitize_key(wp_unslash($_POST['kit']))          : '';
    $size  = isset($_POST['size'])  ? sanitize_text_field(wp_unslash($_POST['size']))  : '';
    $color = isset($_POST['color']) ? sanitize_text_field(wp_unslash($_POST['color'])) : '';
    if (!function_exists('WC') || !WC()->cart) wp_send_json_error(array('message' => 'Your basket could not be read. Please refresh checkout.'), 400);
    $cart = WC()->cart;
    $contents = $cart->get_cart();
    if ($key === '' || !isset($contents[$key])) {
        wp_send_json_error(array('message' => 'That piece is no longer in your basket. Please refresh checkout.'), 404);
    }
    $item = $contents[$key];
    if (!in_array($kit, af_co_up_kits(), true) || !af_co_up_eligible($item)) {
        wp_send_json_error(array('message' => 'This piece can no longer be changed here. Please refresh checkout.'), 400);
    }
    $colors = af_co_up_colors((int) $item['product_id']);
    if ($color !== '' && !isset($colors[$color])) $color = '';
    if ($size !== '') {
        if (!function_exists('af_size_is_offered') || !af_size_is_offered($size)) {
            wp_send_json_error(array('message' => 'That size is not on sale. Please refresh checkout.'), 400);
        }
        $item['af_size'] = $size;
    }
    $new = af_co_up_apply($item, $kit, $color);
    // A new identity for the changed line, the one add-to-cart would give these
    // choices. Keeping the old key meant adding the original choice again from
    // the product page landed on this line, at its new price.
    $data = array_diff_key($new, array_flip(array('key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash',
        'line_tax_data', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax')));
    $new_key = $cart->generate_cart_id($new['product_id'], $new['variation_id'], $new['variation'], $data);
    $new['key'] = $new_key;
    $rebuilt = array();
    foreach ($contents as $k => $line) {
        if ($k === $key) {
            if ($new_key !== $key && isset($contents[$new_key])) continue;   // joins the identical line below
            $rebuilt[$new_key] = $new;
        } elseif ($k === $new_key) {
            // the basket already holds this piece with these choices: one line, quantities added
            $qty = (int) $line['quantity'] + (int) $new['quantity'];
            $cap = function_exists('af_max_quantity') ? af_max_quantity($line['data']) : 0;
            if ($cap && $qty > $cap) {
                wp_send_json_error(array('message' => sprintf('Your basket already holds this piece with that choice, and %d is the most of one piece per order. Change the quantity instead.', $cap)), 400);
            }
            $line['quantity'] = $qty;
            $rebuilt[$k] = $line;
        } else {
            $rebuilt[$k] = $line;
        }
    }
    $cart->set_cart_contents($rebuilt);
    $cart->calculate_totals();   // also saves the basket to the session
    // a signed-in shopper's saved basket (their account) follows too; WooCommerce
    // only saves it on its own add, remove and quantity changes
    if (is_user_logged_in() && method_exists($cart, 'persistent_cart_update')) $cart->persistent_cart_update();
    // The basket-reminder snapshot (inc/abandoned-cart.php) follows the change,
    // so its "return to your basket" link restores this choice, not the old one
    // beside it.
    try {
        if (is_user_logged_in() && function_exists('af_ac_capture_logged_in')) {
            af_ac_capture_logged_in();
        } elseif (function_exists('af_ac_capture') && WC()->session) {
            // checkout's own refresh never stores the email, so the reminder
            // capture keeps the one the guest typed in their session
            $em = (string) WC()->session->get('af_ac_email');
            if ($em === '' && WC()->customer) $em = (string) WC()->customer->get_billing_email();
            if (is_email($em)) af_ac_capture($em, (string) WC()->session->get('af_ac_first'));
        }
    } catch (\Throwable $e) {}
    wp_send_json_success(array('kit' => $kit, 'key' => $new_key));
}

// ── Style and behaviour ─────────────────────────────────────────────────────
add_action('wp_head', function () {
    if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) return;
    ?>
<style id="af-co-up">
/* below the details the photo column is empty, so the block runs the full
   width of the line (the name cell's left padding, checkout-fixes.php) */
.woocommerce-checkout-review-order-table .af-co-up{margin:14px 0 2px;padding:10px 12px 11px;border:1px solid #ece5d4;border-radius:10px;background:#fcfaf6}
.woocommerce-checkout-review-order-table .af-co-up.is-placed{margin-left:-112px}
@media (min-width:1150px) and (max-width:1299px){.woocommerce-checkout-review-order-table .af-co-up.is-placed{margin-left:-94px}}
@media (max-width:499px){.woocommerce-checkout-review-order-table .af-co-up.is-placed{margin-left:-88px}}
.woocommerce-checkout-review-order-table .af-co-up-title{font-size:12.5px;font-weight:600;color:#2b2824;margin:0 0 8px}
.woocommerce-checkout-review-order-table .af-co-up-title span{font-weight:400;color:#6b655c}
.woocommerce-checkout-review-order-table .af-co-up-opts{display:flex;flex-direction:column;gap:6px}
.woocommerce-checkout-review-order-table .af-co-up-opt{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;height:auto;min-height:0;margin:0;padding:8px 11px;border:1.5px solid #e2dccf;border-radius:8px;background:#fff;color:#2b2824;font:inherit;font-size:13px;line-height:1.3;text-align:left;cursor:pointer;text-transform:none;letter-spacing:0;box-shadow:none;transition:border-color .15s,background .15s}
.woocommerce-checkout-review-order-table .af-co-up-opt:hover{border-color:#c9a84c}
.woocommerce-checkout-review-order-table .af-co-up-opt.is-on{border-color:#c9a84c;background:#fdf7e8;font-weight:600}
.woocommerce-checkout-review-order-table .af-co-up-opt:focus-visible,.woocommerce-checkout-review-order-table .af-co-up-color:focus-visible{outline:2px solid #8a6d1f;outline-offset:2px}
.woocommerce-checkout-review-order-table .af-co-up-price,.woocommerce-checkout-review-order-table .af-co-up-price *,.woocommerce-checkout-review-order-table .af-co-up-color,.woocommerce-checkout-review-order-table .af-co-up-color *{white-space:nowrap!important;overflow-wrap:normal!important;word-break:normal!important}
.woocommerce-checkout-review-order-table .af-co-up-price{font-weight:600;color:#1c1a17;flex:0 0 auto}
.woocommerce-checkout-review-order-table .af-co-up-name{flex:1 1 auto;min-width:0}
.woocommerce-checkout-review-order-table .af-co-up-color small{margin-left:3px}
.woocommerce-checkout-review-order-table .af-co-up-colors{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:9px}
.woocommerce-checkout-review-order-table .af-co-up-colors-label{font-size:12px;color:#6b665e;margin-right:2px}
.woocommerce-checkout-review-order-table .af-co-up-color{height:auto;min-height:0;width:auto;margin:0;padding:5px 10px;border:1.5px solid #e2dccf;border-radius:999px;background:#fff;color:#2b2824;font:inherit;font-size:12px;line-height:1.2;cursor:pointer;text-transform:none;letter-spacing:0;box-shadow:none}
.woocommerce-checkout-review-order-table .af-co-up-color small{font-size:11px;color:#8a6d1f}
.woocommerce-checkout-review-order-table .af-co-up-color.is-on{border-color:#c9a84c;background:#fdf7e8;font-weight:600}
.woocommerce-checkout-review-order-table .af-co-up-note{margin:8px 0 0;font-size:11.5px;line-height:1.45;color:#6b655c}
.woocommerce-checkout-review-order-table .af-co-up-msg{margin:6px 0 0;font-size:12px;color:#b3261e}
.woocommerce-checkout-review-order-table .af-co-up-sub{font-size:12px;font-weight:600;color:#6b655c;margin:8px 0 6px}
.woocommerce-checkout-review-order-table .af-co-up-sizes{display:flex;flex-wrap:wrap;gap:6px}
.woocommerce-checkout-review-order-table .af-co-up-size{height:auto;min-height:0;width:auto;margin:0;padding:6px 10px;border:1.5px solid #e2dccf;border-radius:8px;background:#fff;color:#2b2824;font:inherit;font-size:12.5px;line-height:1.25;cursor:pointer;text-transform:none;letter-spacing:0;box-shadow:none;white-space:nowrap!important;overflow-wrap:normal!important}
.woocommerce-checkout-review-order-table .af-co-up-size *{white-space:nowrap!important;overflow-wrap:normal!important}
.woocommerce-checkout-review-order-table .af-co-up-size b{font-weight:600;margin-left:4px}
.woocommerce-checkout-review-order-table .af-co-up-size:hover{border-color:#c9a84c}
.woocommerce-checkout-review-order-table .af-co-up-size.is-on{border-color:#c9a84c;background:#fdf7e8;font-weight:600}
.woocommerce-checkout-review-order-table .af-co-up-msg.is-ok{color:#2e6b3a}
.woocommerce-checkout-review-order-table .af-co-up-msg:empty{display:none}
.woocommerce-checkout-review-order-table .af-co-up.is-busy{opacity:.55;pointer-events:none}
</style>
<script>
(function(){
  if (window.__afCoUp) return; window.__afCoUp = true;
  var pending = null;   // the change just saved, waiting for checkout's redraw
  // WooCommerce prints the block beside the quantity, above the details; put it below them
  function place(){
    document.querySelectorAll('.woocommerce-checkout-review-order-table td.product-name .af-co-up').forEach(function(up){
      var dl = up.parentElement.querySelector('dl.variation');
      if (dl && dl.nextElementSibling !== up) dl.after(up);
      // only once it sits under the details may it reach under the photo
      if (dl && !up.classList.contains('is-placed')) up.classList.add('is-placed');
    });
  }
  function releaseOrder(){ var po = document.getElementById('place_order'); if (po) po.disabled = false; }
  // after checkout redraws the list: focus the chosen button again and say it worked
  function afterRedraw(){
    place();
    if (!pending) return;
    var box = document.querySelector('.af-co-up[data-key="' + pending.key + '"]');
    if (!box || box === pending.old) return;          // not redrawn yet
    var a = pending; pending = null; releaseOrder();
    var btn = box.querySelector(a.size ? '.af-co-up-size[data-size="' + a.size + '"]' : a.color ? '.af-co-up-color[data-color="' + a.color + '"]' : '.af-co-up-opt[data-kit="' + a.kit + '"]');
    if (btn) btn.focus({ preventScroll: true });
    var m = box.querySelector('.af-co-up-msg'); if (m) { m.classList.add('is-ok'); m.textContent = 'Updated: the price and delivery now include your choice.'; }
  }
  // Watch the order box itself: this script runs in the page head, before
  // jQuery and before WooCommerce's checkout events can be listened to.
  function watch(){
    var root = document.getElementById('order_review') || document.querySelector('form.checkout') || document.body;
    if (!root) return;
    var t; new MutationObserver(function(){ clearTimeout(t); t = setTimeout(afterRedraw, 30); }).observe(root, { childList: true, subtree: true });
    afterRedraw();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', watch); else watch();
  document.addEventListener('click', function(e){
    var b = e.target.closest('.af-co-up-opt, .af-co-up-color, .af-co-up-size'); if (!b) return;
    var box = b.closest('.af-co-up'); if (!box) return;
    e.preventDefault();
    if (b.classList.contains('is-on')) return;
    // one change at a time: two at once would each save a basket missing the other
    if (document.querySelector('.af-co-up.is-busy') || pending) return;
    var fd = new FormData();
    fd.append('key', box.dataset.key); fd.append('nonce', box.dataset.nonce);
    fd.append('kit', b.dataset.kit); if (b.dataset.color) fd.append('color', b.dataset.color); if (b.dataset.size) fd.append('size', b.dataset.size);
    var msg = box.querySelector('.af-co-up-msg'); if (msg) { msg.classList.remove('is-ok'); msg.textContent = ''; }
    box.classList.add('is-busy');
    // no order while the line is changing; released after the redraw
    var po = document.getElementById('place_order'); if (po) po.disabled = true;
    function free(){ box.classList.remove('is-busy'); releaseOrder(); }
    var ctl = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function(){ if (ctl) ctl.abort(); }, 20000);
    fetch(box.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: fd, signal: ctl ? ctl.signal : undefined })
      .then(function(r){ return r.json().catch(function(){ return { success: false }; }); })
      .then(function(res){
        clearTimeout(timer);
        if (res && res.success) {
          pending = { key: (res.data && res.data.key) || box.dataset.key, kit: b.dataset.kit, color: b.dataset.color || '', size: b.dataset.size || '', old: box };
          // if the redraw never comes, do not leave Place order switched off
          setTimeout(function(){ if (pending) { pending = null; free(); } }, 25000);
          if (window.jQuery) jQuery(document.body).trigger('update_checkout'); else location.reload();
        } else {
          free();
          if (msg) msg.textContent = (res && res.data && res.data.message) || 'That did not work. Please refresh checkout and try again.';
        }
      })
      .catch(function(){ clearTimeout(timer); free(); if (msg) msg.textContent = 'That took too long or the connection dropped. Please try again.'; });
  });
})();
</script>
    <?php
}, 99);
