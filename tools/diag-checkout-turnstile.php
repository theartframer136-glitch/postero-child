<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The live facts the 22 Oct "Turnstile everywhere" work depends on:
 *  - is Turnstile on, are its keys stored (lengths only), the last problem;
 *  - checkout settings: guest checkout, account creation at checkout;
 *  - every payment gateway: enabled, title, and the settings that decide
 *    where it is offered (COD's virtual-order switch, Zelle's);
 *  - Square: digital wallets / Cash App Pay switches, and the wc-ajax and
 *    admin-ajax actions Square registers (the wallet route to exempt);
 *  - gift cards: the product, and which gateways a gift-card-only cart is
 *    offered (COD for a virtual cart would mean unpaid codes);
 *  - orders by the route that created them (classic checkout vs the REST
 *    "store-api" route), and failed orders with a recorded visitor address;
 *  - the order-tracking page.
 * Prints no key, no customer email and no address.
 *
 *   wp eval-file tools/diag-checkout-turnstile.php
 */
global $wpdb, $wp_filter;
$len = function ($v) { $v = (string) $v; return $v === '' ? 'absent' : 'present (' . strlen($v) . ' chars)'; };
$yn = function ($b) { return $b ? 'yes' : 'no'; };

echo "=== Turnstile\n";
echo '  active: ' . $yn(function_exists('af_turnstile_active') && af_turnstile_active()) . ' | mode ' . get_option('af_turnstile_mode', '(unset)') . ' | site key ' . $len(get_option('af_turnstile_site_key')) . ' | secret ' . $len(get_option('af_turnstile_secret_key')) . "\n";
$le = get_transient('af_turnstile_last_error');
echo '  last problem recorded: ' . ($le ? wp_json_encode($le) : 'none') . "\n";

echo "\n=== checkout settings\n";
foreach (array('woocommerce_enable_guest_checkout', 'woocommerce_enable_signup_and_login_from_checkout', 'woocommerce_enable_checkout_login_reminder', 'woocommerce_registration_generate_password', 'woocommerce_enable_myaccount_registration') as $o) echo "  {$o}: " . get_option($o, '(unset)') . "\n";
$co = (int) get_option('woocommerce_checkout_page_id');
echo '  checkout page #' . $co . ': ' . (has_block('woocommerce/checkout', (string) get_post_field('post_content', $co)) ? 'BLOCK checkout' : 'classic') . "\n";

echo "\n=== payment gateways\n";
if (function_exists('WC')) {
    foreach (WC()->payment_gateways()->payment_gateways() as $id => $g) {
        $s = is_array($g->settings) ? $g->settings : array();
        $extra = array();
        foreach (array('enable_for_virtual', 'enable_for_methods', 'enable_digital_wallets', 'digital_wallets_button_type', 'enabled_payment_methods', 'charge_virtual_orders', 'transaction_type') as $k) if (array_key_exists($k, $s)) $extra[] = $k . '=' . (is_array($s[$k]) ? implode('|', $s[$k]) : $s[$k]);
        echo "  {$id}: enabled=" . ($g->enabled ?? '?') . ' | "' . wp_strip_all_tags((string) $g->get_title()) . '" | class ' . get_class($g) . ($extra ? ' | ' . implode(', ', $extra) : '') . "\n";
    }
}
foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_square%settings' OR option_name LIKE 'wc_square%' ORDER BY option_name LIMIT 20") as $o) {
    $v = get_option($o);
    if (!is_array($v)) { echo "  option {$o}: (scalar)\n"; continue; }
    $keep = array();
    foreach ($v as $k => $x) if (preg_match('/wallet|cash_app|apple|google|enabled|mode|environment|gift/i', $k) && !preg_match('/token|secret|key|id$/i', $k)) $keep[] = $k . '=' . (is_array($x) ? implode('|', $x) : $x);
    echo "  option {$o}: " . implode(', ', $keep) . "\n";
}

echo "\n=== AJAX actions Square (or wallets) registered for visitors\n";
foreach (array_keys((array) $wp_filter) as $h) {
    if (preg_match('/^(wc_ajax_|wp_ajax_nopriv_)/', $h) && preg_match('/square|wallet|cash_app|apple_pay|google_pay|payment_request/i', $h)) echo "  {$h}\n";
}
$rest = rest_get_server()->get_routes();
$store = array_values(array_filter(array_keys($rest), function ($r) { return strpos($r, '/wc/store') === 0 && strpos($r, 'checkout') !== false; }));
echo '  REST checkout routes: ' . implode(', ', $store) . "\n";

echo "\n=== gift cards\n";
$gc = function_exists('af_gc_product_id') ? af_gc_product_id() : 0;
if (!$gc) { $ids = get_posts(array('post_type' => 'product', 'post_status' => 'any', 'meta_key' => '_af_is_gift_card', 'meta_value' => 'yes', 'fields' => 'ids', 'numberposts' => 3)); $gc = $ids ? (int) $ids[0] : 0; }
$p = $gc ? wc_get_product($gc) : null;
echo '  gift card product: ' . ($p ? "#{$gc} {$p->get_status()} \"{$p->get_name()}\" virtual=" . $yn($p->is_virtual()) . ' downloadable=' . $yn($p->is_downloadable()) . ' purchasable=' . $yn($p->is_purchasable()) : 'not found') . "\n";
if ($p && function_exists('WC')) {
    // which gateways a gift-card-only cart is offered: build a throwaway cart in this CLI process
    try {
        wc_load_cart();
        WC()->cart->empty_cart(false);
        WC()->cart->add_to_cart($gc, 1, 0, array(), array('_af_gc' => array('amount' => 50)));
        $_POST['af_gc_amount'] = 50; // what the product page sends; the theme's add-to-cart check reads it
        if (!WC()->cart->get_cart_contents_count()) WC()->cart->add_to_cart($gc, 1);
        echo '  throwaway cart lines: ' . WC()->cart->get_cart_contents_count() . (WC()->cart->get_cart_contents_count() ? '' : ' (could not add the gift card here; the gateway list below is for an empty cart)') . "\n";
        $needs = WC()->cart->needs_shipping() ? 'yes' : 'no';
        $avail = array_keys(WC()->payment_gateways()->get_available_payment_gateways());
        echo '  a cart holding only the gift card: needs shipping ' . $needs . ' | gateways offered: ' . ($avail ? implode(', ', $avail) : 'none') . "\n";
        WC()->cart->empty_cart(false);
    } catch (Throwable $e) { echo '  cart test failed: ' . $e->getMessage() . "\n"; }
}
$issued = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}af_gift_cards");
echo '  gift card codes ever issued: ' . $issued . "\n";

echo "\n=== orders by route and status (last 180 days)\n";
if (function_exists('wc_get_orders')) {
    $since = gmdate('Y-m-d', time() - 180 * DAY_IN_SECONDS);
    foreach (array('checkout', 'store-api', 'admin', 'rest-api') as $via) {
        $n = count(wc_get_orders(array('created_via' => $via, 'date_created' => '>=' . $since, 'limit' => -1, 'return' => 'ids', 'type' => 'shop_order')));
        echo "  created via {$via}: {$n}\n";
    }
    foreach (array('failed', 'pending', 'processing', 'completed', 'cancelled', 'on-hold') as $st) {
        $ids = wc_get_orders(array('status' => $st, 'date_created' => '>=' . $since, 'limit' => -1, 'return' => 'ids', 'type' => 'shop_order'));
        echo "  status {$st}: " . count($ids) . "\n";
    }
    $failed = wc_get_orders(array('status' => 'failed', 'limit' => 50, 'return' => 'objects', 'type' => 'shop_order'));
    $withIp = 0; foreach ($failed as $o) if ($o->get_meta('_af_visitor_ip')) $withIp++;
    echo '  failed orders with a recorded visitor address: ' . $withIp . ' of ' . count($failed) . "\n";
    $byMethod = array();
    foreach (wc_get_orders(array('date_created' => '>=' . $since, 'limit' => 300, 'return' => 'objects', 'type' => 'shop_order')) as $o) { $m = $o->get_payment_method() ?: '(none)'; $byMethod[$m] = ($byMethod[$m] ?? 0) + 1; }
    arsort($byMethod);
    echo '  orders by payment method: ' . wp_json_encode($byMethod) . "\n";
}

echo "\n=== order tracking\n";
$tp = get_page_by_path('track-your-order');
echo '  /track-your-order/: ' . ($tp ? "#{$tp->ID} {$tp->post_status}, shortcode " . $yn(has_shortcode((string) $tp->post_content, 'woocommerce_order_tracking')) : 'not found') . "\n";
echo "=== END\n";
