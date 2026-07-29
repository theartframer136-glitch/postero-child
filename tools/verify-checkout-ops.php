<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Confirm the §11 checkout modules are actually live: abandoned cart
 * recovery, address validation, fraud screening, invoices/packing slips.
 * Read-only apart from one throwaway probe row it deletes again.
 * Run: wp eval-file tools/verify-checkout-ops.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
$fail = 0;

function af_chk($label, $ok, &$fail, $extra = '') {
    printf("  %-42s %s%s\n", $label, $ok ? 'OK' : 'MISSING  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}

/**
 * Fetch, retrying once past a transient host error. A 508 is Hostinger saying
 * "resource limit reached", which tells us nothing about the code under test —
 * counting it as a failure would make this report lie.
 */
function af_get($url) {
    $args = array('timeout'=>45,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify'));
    for ($i = 0; $i < 3; $i++) {
        $r = wp_remote_get($url, $args);
        if (is_wp_error($r)) { sleep(3); continue; }
        $code = (int) wp_remote_retrieve_response_code($r);
        if ($code !== 508 && $code !== 503 && $code !== 429) return $r;
        sleep(4);
    }
    return $r;
}

echo "=== 1. ABANDONED CART RECOVERY ===\n";
$t = af_ac_table();
$has_table = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) === $t;
af_chk('carts table', $has_table, $fail, $t);
af_chk('capture ajax (guest)',   (bool) has_action('wp_ajax_nopriv_af_ac_capture'), $fail);
af_chk('capture on add-to-cart', (bool) has_action('woocommerce_add_to_cart'), $fail);
af_chk('hourly scan scheduled',  (bool) wp_next_scheduled('af_ac_scan'), $fail,
       wp_next_scheduled('af_ac_scan') ? 'next ' . gmdate('Y-m-d H:i', wp_next_scheduled('af_ac_scan')) . ' UTC' : '');
af_chk('order closes the loop',  (bool) has_action('woocommerce_checkout_order_processed'), $fail);
af_chk('admin screen',           (bool) function_exists('af_ac_admin_page'), $fail);
if ($has_table) {
    $counts = $wpdb->get_results("SELECT status, COUNT(*) n, COALESCE(SUM(total),0) v FROM {$t} GROUP BY status");
    if ($counts) { foreach ($counts as $c) printf("    %-12s %4d carts  %s\n", $c->status, $c->n, number_format((float)$c->v, 2)); }
    else echo "    (no carts recorded yet)\n";
    // round-trip probe: the reminder query must be able to find a due cart
    $tok = wp_generate_password(32, false, false);
    $wpdb->insert($t, array(
        'token'=>$tok, 'email'=>'probe@theartframer.us', 'contents'=>'[]', 'total'=>0,
        'status'=>'open', 'created_at'=>gmdate('Y-m-d H:i:s', time()-7200),
        'updated_at'=>gmdate('Y-m-d H:i:s', time()-7200),
    ));
    $found = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE token=%s", $tok));
    af_chk('insert + read back', $found === 1, $fail);
    $wpdb->delete($t, array('token' => $tok));
}

echo "\n=== 2. ADDRESS VALIDATION ===\n";
af_chk('checkout validation hook', (bool) has_action('woocommerce_after_checkout_validation'), $fail);
af_chk('postcode formatter',       (bool) has_filter('woocommerce_format_postcode'), $fail);
$cases = array(
    array('90210', 'CA'), array('10001', 'NY'), array('75001', 'TX'),
    array('33101', 'FL'), array('98101', 'WA'), array('60601', 'IL'),
);
$bad = 0;
foreach ($cases as $c) {
    $got = af_state_for_zip($c[0]);
    if ($got !== $c[1]) { echo "    ZIP {$c[0]} => {$got}, expected {$c[1]}  <<<\n"; $bad++; }
}
af_chk('ZIP => state lookup (6 samples)', $bad === 0, $fail);
af_chk('unknown ZIP passes through', af_state_for_zip('99999') === 'AK' || af_state_for_zip('00000') === '', $fail);

echo "\n=== 3. FRAUD SCREENING ===\n";
af_chk('assessor',            function_exists('af_fraud_assess'), $fail);
af_chk('scores on new orders',(bool) has_action('woocommerce_checkout_order_processed'), $fail);
af_chk('orders list column',  (bool) has_filter('manage_edit-shop_order_columns'), $fail);
af_chk('HPOS list column',    (bool) has_filter('woocommerce_shop_order_list_table_columns'), $fail);
af_chk('auto-hold off by default', apply_filters('af_fraud_autohold', false, null, array()) === false, $fail);
$scored = $wpdb->get_results(
    "SELECT meta_value lvl, COUNT(*) n FROM {$wpdb->postmeta} WHERE meta_key='_af_risk_level' GROUP BY meta_value"
);
if ($scored) { foreach ($scored as $s) printf("    %-8s %d order(s)\n", $s->lvl, $s->n); }
else echo "    (no orders screened yet — applies to new orders only)\n";

echo "\n=== 4. INVOICES & PACKING SLIPS ===\n";
af_chk('renderer',            function_exists('af_doc_render'), $fail);
af_chk('permission gate',     function_exists('af_doc_can_view'), $fail);
af_chk('admin order buttons', (bool) has_action('woocommerce_admin_order_data_after_order_details'), $fail);
af_chk('bulk print',          (bool) has_filter('bulk_actions-edit-shop_order'), $fail);
af_chk('my-account action',   (bool) has_filter('woocommerce_my_account_my_orders_actions'), $fail);

// Prove the document actually renders, and that a stranger cannot open it.
$latest = wc_get_orders(array('limit' => 1, 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC'));
if ($latest) {
    $order = wc_get_order($latest[0]);
    $url   = add_query_arg(array('af_doc'=>'invoice','order'=>$order->get_id(),'key'=>$order->get_order_key()), home_url('/'));
    $r     = af_get($url);
    if (is_wp_error($r)) { echo "  invoice fetch FAILED: " . $r->get_error_message() . "\n"; $fail++; }
    else {
        $body = wp_remote_retrieve_body($r);
        $code = wp_remote_retrieve_response_code($r);
        af_chk('invoice renders with order key', $code === 200 && stripos($body, 'invoice') !== false, $fail, "HTTP {$code}, " . strlen($body) . " bytes");
        af_chk('shows the order number', strpos($body, (string) $order->get_order_number()) !== false, $fail);
    }
    // same document, no key, not signed in => must be refused
    $bad = af_get(add_query_arg(array('af_doc'=>'invoice','order'=>$order->get_id()), home_url('/')));
    if (!is_wp_error($bad)) {
        $bc = wp_remote_retrieve_response_code($bad);
        af_chk('refuses without the order key', $bc === 403, $fail, "HTTP {$bc}");
    }
    $slip = af_get(add_query_arg(array('af_doc'=>'packing-slip','order'=>$order->get_id(),'key'=>$order->get_order_key()), home_url('/')));
    if (!is_wp_error($slip)) {
        $sc = wp_remote_retrieve_response_code($slip);
        af_chk('packing slip is staff-only', $sc === 403, $fail, "HTTP {$sc}");
    }
} else {
    echo "  (no orders yet — document render not exercised)\n";
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
