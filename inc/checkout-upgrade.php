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
 *
 * The cart page carries the same editor under each line (owner, 1 Oct: "same
 * also this page"), from the same markup, styles, request and script; the
 * cart redraws its own form and totals after a change, as it does after a
 * quantity change.
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

/**
 * The editor's stylesheet (inc/checkout-upgrade.css), printed with the list
 * itself, once per response. Checkout redraws the list over AJAX; when the
 * page's own head was older than the list (a deploy between the two, as in
 * the owner's recording of 1 Oct) the buttons met the theme's global gold
 * capsule style. Shipping the rules with the markup closes that gap.
 */
function af_co_up_style_tag($where = 'checkout') {
    static $done = false, $css = null;
    if ($done) return '';
    $done = true;
    if ($css === null) {
        $f = __DIR__ . '/checkout-upgrade.css';
        $css = is_readable($f) ? (string) file_get_contents($f) : '';
    }
    if ($css === '') return '';
    // The stylesheet names both pages as :is(#order_review,.woocommerce-cart-form).
    // A browser without :is() (iOS 12 Safari, Chrome before 88) drops every rule
    // written so, so each page gets its own plain selector, both with an id's
    // weight: checkout's rules read exactly as they did before the cart had one.
    $scope = $where === 'cart' ? '.woocommerce-cart-form:not(#af-co-up-none)' : '#order_review';
    return '<style id="af-co-up-css">' . str_replace(':is(#order_review,.woocommerce-cart-form)', $scope, $css) . '</style>';
}

/**
 * The editor for one cart line: its sizes, the three "You receive" choices and,
 * with a frame, the frame colours, each priced for one piece. $where is
 * 'checkout' or 'cart' (the delivery note differs: the cart shows no delivery
 * cost yet). Empty for a line it does not apply to.
 */
function af_co_up_markup($cart_item, $cart_item_key, $where = 'checkout') {
    $level = ob_get_level();
    try {
        if (!$cart_item_key || !af_co_up_eligible($cart_item)) return '';
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
        $sizes = function_exists('af_sizes_offered') ? af_sizes_offered() : array();
        $kit_for = $cur !== '' ? $cur : $cart_item['af_kit'];
        $short = function ($sz) { return trim(preg_replace('/\s*\(.*\)\s*$/', '', (string) $sz)); };
        $inches = preg_match('/\(([^)]+)\)/', (string) $cart_item['af_size'], $m) ? $m[1] : '';
        $material = trim(preg_replace('/\s*Frame$/i', '', (string) af_co_up_frame_for($cart_item, 'painting_bar_frame')));
        $hex = array('Black' => '#1d1d1d', 'Silver' => '#c7c9cc', 'Gold' => '#c9a84c', 'Rose Gold' => '#d4a39a');
        $uid = 'af-co-up-' . substr(md5($cart_item_key), 0, 10);
        $note = $where === 'cart'
            ? 'Delivery is worked out at checkout: a framed piece ships flat in a crate, which costs more to deliver (large sizes add oversize handling).'
            : 'Delivery updates with your choice: a framed piece ships flat in a crate, which costs more to deliver (large sizes add oversize handling).';
        ob_start();
        echo af_co_up_style_tag($where);   // travels with the list, so markup and style always match ?>
<div class="af-co-up af-co-up--<?php echo esc_attr($where); ?><?php echo $cur !== '' ? ' af-co-up--set' : ''; ?>" data-key="<?php echo esc_attr($cart_item_key); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('woocommerce-af-co-upgrade')); ?>" data-endpoint="<?php echo esc_url(WC_AJAX::get_endpoint('af_co_upgrade')); ?>"><div class="af-co-up-in">
  <?php if (count($sizes) > 1) : ?>
  <div class="af-co-up-g af-co-up-g--size" role="group" aria-labelledby="<?php echo esc_attr($uid . '-size'); ?>">
    <div class="af-co-up-lab" id="<?php echo esc_attr($uid . '-size'); ?>">Size<?php if ($inches !== '') : ?><span class="af-co-up-hint"><?php echo esc_html($inches); ?></span><?php endif; ?><?php if ($qty > 1) : ?><span class="af-co-up-each">price per piece</span><?php endif; ?></div>
    <div class="af-co-up-ctl af-co-up-sizes">
      <?php foreach ($sizes as $sz) :
          $alt = $cart_item; $alt['af_size'] = $sz;
          $on = ($sz === $cart_item['af_size']); ?>
      <button type="button" class="af-co-up-size<?php echo $on ? ' is-on' : ''; ?>" data-kit="<?php echo esc_attr($kit_for); ?>" data-size="<?php echo esc_attr($sz); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>" title="<?php echo esc_attr($sz); ?>"><span class="af-co-up-v"><?php echo esc_html($short($sz)); ?></span><span class="af-co-up-p"><?php echo wp_kses_post(af_co_up_money(af_co_up_unit_price($alt, $kit_for, $color))); ?></span></button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="af-co-up-g af-co-up-g--kit" role="group" aria-labelledby="<?php echo esc_attr($uid . '-kit'); ?>">
    <div class="af-co-up-lab" id="<?php echo esc_attr($uid . '-kit'); ?>">You receive<?php if ($qty > 1 && count($sizes) <= 1) : ?><span class="af-co-up-each">price per piece</span><?php endif; ?></div>
    <div class="af-co-up-ctl af-co-up-opts">
      <?php foreach ($labels as $kit => $label) :
          $on = ($kit === $cur); ?>
      <button type="button" class="af-co-up-opt<?php echo $on ? ' is-on' : ''; ?>" data-kit="<?php echo esc_attr($kit); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><span class="af-co-up-radio" aria-hidden="true"></span><span class="af-co-up-v"><?php echo esc_html($label); ?></span><span class="af-co-up-p"><?php echo wp_kses_post(af_co_up_money(af_co_up_unit_price($cart_item, $kit, $color))); ?></span></button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($cur === 'painting_bar_frame') : ?>
  <div class="af-co-up-g af-co-up-g--color" role="group" aria-labelledby="<?php echo esc_attr($uid . '-color'); ?>">
    <div class="af-co-up-lab" id="<?php echo esc_attr($uid . '-color'); ?>">Frame color<?php if ($material !== '') : ?><span class="af-co-up-hint"><?php echo esc_html($material); ?></span><?php endif; ?></div>
    <div class="af-co-up-ctl af-co-up-colors">
      <?php foreach (af_co_up_colors($pid) as $name => $fee) :
          $on = ($name === $color); ?>
      <button type="button" class="af-co-up-color<?php echo $on ? ' is-on' : ''; ?>" data-kit="painting_bar_frame" data-color="<?php echo esc_attr($name); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><span class="af-co-up-v"><span class="af-co-up-sw" style="background:<?php echo esc_attr(isset($hex[$name]) ? $hex[$name] : '#cccccc'); ?>" aria-hidden="true"></span><?php echo esc_html($name); ?></span><span class="af-co-up-p"><?php echo (float) $fee > 0 ? wp_kses_post('+' . af_co_up_money((float) $fee)) : 'Included'; ?></span></button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="af-co-up-foot">
    <p class="af-co-up-note"><?php echo esc_html($note); ?></p>
    <p class="af-co-up-msg" role="status" aria-live="polite"></p>
  </div>
</div></div>
        <?php
        return ob_get_clean();
    } catch (\Throwable $e) {
        // never leave a buffer open: this runs inside checkout's JSON refresh
        while (ob_get_level() > $level) ob_end_clean();
        return '';
    }
}

// ── The choices, under each eligible line of "Your order" ──────────────────
// WooCommerce prints the quantity through this filter without passing it
// through wp_kses_post (unlike the name), so buttons survive. The block is
// moved below the line's details by the script further down.
add_filter('woocommerce_checkout_cart_item_quantity', function ($html, $cart_item = array(), $cart_item_key = '') {
    if (function_exists('af_review_rows') && !af_review_rows()) return $html;
    return $html . af_co_up_markup($cart_item, $cart_item_key, 'checkout');
}, 20, 3);

// ── The same choices on the cart page ──────────────────────────────────────
// Printed only while WooCommerce draws the cart table's rows: the same hook
// also fires in mini-cart templates (Elementor Pro, Essential Addons), which
// must not grow an editor. WooCommerce prints this hook right after the
// line's name, outside wp_kses_post; the script moves the block into a row of
// its own under the line, as on checkout.
function af_co_up_cart_rows($set = null) {
    static $on = false;
    if ($set !== null) $on = (bool) $set;
    return $on;
}
add_action('woocommerce_before_cart_contents', function () { af_co_up_cart_rows(true); }, 1);
add_action('woocommerce_after_cart_contents', function () { af_co_up_cart_rows(false); }, 999);
add_action('woocommerce_after_cart_item_name', function ($cart_item = array(), $cart_item_key = '') {
    if (!af_co_up_cart_rows()) return;
    echo af_co_up_markup($cart_item, $cart_item_key, 'cart');
}, 20, 2);
// placed as soon as the table is parsed, so the block never flashes inside the
// narrow name column (the cart's own redraws are placed by the script's watcher)
add_action('woocommerce_after_cart_table', function () {
    echo '<script>window.__afCoUpPlace&&window.__afCoUpPlace();</script>';
}, 1);

// The basket-reminder snapshot (inc/abandoned-cart.php) follows a change made
// here, so its "return to your basket" link restores this choice, not the old
// one beside it.
function af_co_up_follow_capture() {
    try {
        if (is_user_logged_in() && function_exists('af_ac_capture_logged_in')) {
            af_ac_capture_logged_in();
        } elseif (function_exists('af_ac_capture') && WC()->session) {
            // checkout's own refresh never stores the email, so the reminder
            // capture keeps the one the guest typed in their session
            $em = (string) WC()->session->get('af_ac_email');
            // Only an address the capture itself took (after Cloudflare's
            // check, inc/abandoned-cart.php) once the checkout check is on:
            // the billing email in the session can be set by anyone without
            // one, and reminders would then go to any address (review, 22 Oct).
            $checked = function_exists('af_turnstile_checkout_on') && af_turnstile_checkout_on();
            if ($em === '' && !$checked && WC()->customer) $em = (string) WC()->customer->get_billing_email();
            if (is_email($em)) af_ac_capture($em, (string) WC()->session->get('af_ac_first'));
        }
    } catch (\Throwable $e) {}
}
// ── The request that changes the line ───────────────────────────────────────
add_action('wc_ajax_af_co_upgrade', 'af_co_upgrade_handler');
function af_co_upgrade_handler() {
    if (!check_ajax_referer('woocommerce-af-co-upgrade', 'nonce', false)) {
        wp_send_json_error(array('message' => 'This page has expired. Please refresh the page and try again.'), 403);
    }
    $key   = isset($_POST['key'])   ? sanitize_text_field(wp_unslash($_POST['key']))   : '';
    $kit   = isset($_POST['kit'])   ? sanitize_key(wp_unslash($_POST['kit']))          : '';
    $size  = isset($_POST['size'])  ? sanitize_text_field(wp_unslash($_POST['size']))  : '';
    $color = isset($_POST['color']) ? sanitize_text_field(wp_unslash($_POST['color'])) : '';
    if (!function_exists('WC') || !WC()->cart) wp_send_json_error(array('message' => 'Your basket could not be read. Please refresh the page.'), 400);
    $cart = WC()->cart;
    $contents = $cart->get_cart();
    if ($key === '' || !isset($contents[$key])) {
        wp_send_json_error(array('message' => 'That piece is no longer in your basket. Please refresh the page.'), 404);
    }
    $item = $contents[$key];
    if (!in_array($kit, af_co_up_kits(), true) || !af_co_up_eligible($item)) {
        wp_send_json_error(array('message' => 'This piece can no longer be changed here. Please refresh the page.'), 400);
    }
    $colors = af_co_up_colors((int) $item['product_id']);
    if ($color !== '' && !isset($colors[$color])) $color = '';
    if ($size !== '') {
        if (!function_exists('af_size_is_offered') || !af_size_is_offered($size)) {
            wp_send_json_error(array('message' => 'That size is not on sale. Please refresh the page.'), 400);
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
    af_co_up_follow_capture();
    wp_send_json_success(array('kit' => $kit, 'key' => $new_key));
}

// ── Style and behaviour ─────────────────────────────────────────────────────
add_action('wp_head', function () {
    if (!function_exists('is_checkout')) return;
    $checkout = is_checkout() && !(function_exists('is_order_received_page') && is_order_received_page());
    $cart = function_exists('is_cart') && is_cart();
    if (!$checkout && !$cart) return;
    ?>
<?php /* styles: inc/checkout-upgrade.css, printed with the list (af_co_up_style_tag) */ ?>
<script id="af-checkout-upgrade" src="<?php echo esc_url(af_static_url('checkout-upgrade.js')); ?>" data-no-optimize="1"></script>
    <?php
}, 99);
