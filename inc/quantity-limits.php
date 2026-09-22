<?php
/**
 * A ceiling on how many of one piece can be ordered at once.
 *
 * DEF-05. Measured: an add-to-cart request carrying quantity=99999 was
 * accepted with a success message and a $7,999,920.00 subtotal, and 500 units
 * likewise at $40,000.00. The quantity input carries min="1" and no max.
 *
 * Those are two findings, not one, and only the second matters. A max
 * attribute is advice to a browser. The request that produced $7,999,920 did
 * not come from a browser obeying attributes and neither will the next one,
 * so adding max="25" and stopping there would leave the hole exactly as open
 * while making the test go green. The attribute is here because an honest
 * shopper should see the limit before they hit it; the validation below is
 * what actually holds.
 *
 * Why it matters here more than on a normal shop: Cash on Delivery is
 * enabled, so there is no payment capture standing between an absurd order
 * and a made-to-order studio. Nothing declines. The order simply arrives.
 *
 * Four ways into the cart, all of them covered:
 *
 *   product page form   -> woocommerce_add_to_cart_validation
 *   /?add-to-cart= URL  -> woocommerce_add_to_cart_validation
 *   wc-ajax add_to_cart -> woocommerce_add_to_cart_validation
 *   /cart/ quantity box -> woocommerce_update_cart_validation
 *
 * All four funnel through WC_Cart::add_to_cart() or the cart form handler, so
 * two filters cover every path rather than one per template.
 *
 * DELIBERATELY NOT a per-order total. A gallery buying thirty different
 * pieces is a good order, not an attack, and capping the basket would refuse
 * it. The limit is per line, which is what the reported request abused.
 *
 * The number is an option, so the studio can change it without a deploy:
 *   wp option update af_max_quantity 25
 */
if (!defined('ABSPATH')) exit;

/**
 * The most of one piece that may be ordered at once.
 *
 * Filterable as well as option-backed, so a product that genuinely sells in
 * volume can differ without moving the limit for everything else.
 */
function af_max_quantity($product = null) {
    $max = get_option('af_max_quantity', 25);
    $max = is_numeric($max) ? (int) $max : 25;
    if ($max < 1) $max = 25;
    $max = (int) apply_filters('af_max_quantity', $max, $product);
    return max(1, $max);
}

/**
 * What the shopper reads. One place, so all four paths say the same thing,
 * and it points somewhere rather than just refusing: bulk is a real part of
 * this business and /wholesale-corporate/ exists for it.
 */
function af_max_quantity_message($max, $already = 0) {
    $max     = (int) $max;
    $already = (int) $already;
    $lead = ($already > 0)
        ? sprintf(
            'Your basket already holds %d of this piece, and %d is the most you can order at once.',
            $already, $max)
        : sprintf('You can order up to %d of one piece at a time.', $max);

    return $lead . ' Every piece is made to order, so for a larger run please '
        . '<a href="' . esc_url(home_url('/contact/')) . '">talk to the studio</a> '
        . 'and we will quote it properly.';
}

/**
 * How many of this exact line the cart already holds.
 *
 * Without this the limit is trivially defeated by asking twice: WooCommerce
 * adds a repeat of the same line to the existing quantity rather than
 * creating a second line, so 25 then 25 becomes 50 and no single request ever
 * exceeded the cap.
 *
 * The cart id is rebuilt the way WC_Cart does it — product, variation and the
 * item data — because this shop's pieces differ by size, frame and colour and
 * those are separate lines. A 25 limit on "this piece, 3x4 ft, oak" is the
 * intent; it is not a limit on the piece across every size.
 */
function af_cart_line_quantity($product_id, $variation_id = 0, $variations = array(), $cart_item_data = array()) {
    try {
        if (!function_exists('WC') || !WC() || !WC()->cart) return 0;
        $cart = WC()->cart;
        if (!method_exists($cart, 'generate_cart_id') || !method_exists($cart, 'find_product_in_cart')) return 0;
        $id  = $cart->generate_cart_id((int) $product_id, (int) $variation_id,
                                       (array) $variations, (array) $cart_item_data);
        $key = $cart->find_product_in_cart($id);
        if (!$key) return 0;
        // cart_contents directly, not get_cart(): get_cart() re-loads the
        // session and calls wc_doing_it_wrong() if anything reaches it early,
        // and this runs from inside add_to_cart(). find_product_in_cart()
        // reads the same property, so there is nothing to gain by the longer
        // route and a _doing_it_wrong notice to lose.
        $contents = (array) $cart->cart_contents;
        return isset($contents[$key]['quantity']) ? (int) $contents[$key]['quantity'] : 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * The control. Everything that adds to the cart passes through here.
 *
 * Returns the incoming verdict untouched on any failure. An optional guard
 * that throws takes the shop down with it — af_asset_src() did exactly that
 * for 34 minutes this morning — so the worst case here is today's behaviour,
 * never an error page.
 */
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity,
                                                           $variation_id = 0, $variations = array(),
                                                           $cart_item_data = array()) {
    if (!$passed) return $passed;
    try {
        $qty = (int) $quantity;
        // Zero, negative and non-numeric quantities are DEF-11's subject, not
        // this one. Touching them here would hide that finding rather than
        // fix it.
        if ($qty < 1) return $passed;

        // This filter fires twice on a simple add: once from
        // WC_Form_Handler with three arguments, and once from inside
        // WC_Cart::add_to_cart() with all six. The three-argument call cannot
        // identify the existing line, so it only sees the incoming quantity —
        // which is enough to stop 99999. The six-argument call is the one
        // that catches 25 asked for twice, and it has the item data it needs.
        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        $cap     = af_max_quantity($product);
        $already = af_cart_line_quantity($product_id, $variation_id, $variations, $cart_item_data);

        if ($already + $qty <= $cap) return $passed;

        if (function_exists('wc_add_notice')) {
            wc_add_notice(af_max_quantity_message($cap, $already), 'error');
        }
        return false;
    } catch (\Throwable $e) {
        return $passed;
    }
}, 10, 6);

/**
 * The cart's own quantity box is a second way in, and a fix on the product
 * page would miss it entirely.
 *
 * Refusing rather than clamping is deliberate: WooCommerce keeps the previous
 * quantity when this returns false, so the box visibly springs back to what
 * it was. That feedback does not depend on notices rendering, which on the
 * cart template they currently do not (DEF-11 / run 01 H-01).
 */
add_filter('woocommerce_update_cart_validation', function ($passed, $cart_item_key, $values, $quantity) {
    if (!$passed) return $passed;
    try {
        $qty = (int) $quantity;
        if ($qty < 1) return $passed;
        $product = (is_array($values) && isset($values['data'])) ? $values['data'] : null;
        $cap     = af_max_quantity($product);
        if ($qty <= $cap) return $passed;
        if (function_exists('wc_add_notice')) {
            wc_add_notice(af_max_quantity_message($cap), 'error');
        }
        return false;
    } catch (\Throwable $e) {
        return $passed;
    }
}, 10, 4);

/**
 * What the input advertises.
 *
 * Only ever lowers a ceiling, never raises one: WooCommerce passes a
 * stock-derived max for products it manages stock on, and -1 or an empty
 * value for "no limit". Taking the smaller of the two means a piece with 3 in
 * stock still says 3.
 */
function af_quantity_cap_apply($current, $product = null) {
    $cap = af_max_quantity($product);
    if ($current === '' || $current === null) return $cap;
    $current = (int) $current;
    if ($current < 1) return $cap;   // -1 == unlimited
    return min($current, $cap);
}

add_filter('woocommerce_quantity_input_max', function ($max, $product = null) {
    try {
        return af_quantity_cap_apply($max, $product);
    } catch (\Throwable $e) {
        return $max;
    }
}, 10, 2);

/**
 * Applied last and to the merged arguments, which is what the cart template
 * uses — it passes max_value explicitly, so the filter above never sees it.
 */
add_filter('woocommerce_quantity_input_args', function ($args, $product = null) {
    try {
        if (!is_array($args)) return $args;
        $args['max_value'] = af_quantity_cap_apply(
            isset($args['max_value']) ? $args['max_value'] : -1, $product);
        return $args;
    } catch (\Throwable $e) {
        return $args;
    }
}, 10, 2);
