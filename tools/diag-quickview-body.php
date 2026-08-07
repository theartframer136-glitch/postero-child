<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Dump what the quick-view modal actually returns, so we stop guessing why the
 * product page's sections are or are not in it.
 *
 * Prints every af-* class the response contains, which of our known section
 * markers are present, and which WooCommerce summary hooks our callbacks are
 * even attached to. Read-only.
 * Run: wp eval-file tools/diag-quickview-body.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

// pick a product that definitely has an image
$pid = 0;
foreach (wc_get_products(array('status'=>'publish','limit'=>8,'return'=>'ids','orderby'=>'date','order'=>'DESC')) as $c) {
    $p = wc_get_product($c);
    if ($p && $p->get_image_id()) { $pid = $c; break; }
}
echo "product: #{$pid} " . ($pid ? get_the_title($pid) : '') . "\n";

global $wp_filter;
$action = '';
foreach (array_keys($wp_filter) as $hook) {
    if (strpos($hook, 'wp_ajax_nopriv_') !== 0) continue;
    $name = substr($hook, strlen('wp_ajax_nopriv_'));
    if (preg_match('/(woosq|quick_?view|_qv$|^qv_)/i', $name)) { $action = $name; break; }
}
echo "ajax action: " . ($action ?: '(none)') . "\n\n";

echo "=== WHO IS ON THE SUMMARY HOOKS ===\n";
foreach (array('woocommerce_single_product_summary', 'woocommerce_after_single_product_summary') as $hook) {
    $n = isset($wp_filter[$hook]) ? count($wp_filter[$hook]->callbacks, COUNT_RECURSIVE) : 0;
    echo "  {$hook}: {$n} callback slot(s)\n";
}

if (!$action || !$pid) { echo "\n(cannot probe)\n"; return; }

$nonce = wp_create_nonce('essential-addons-elementor');
$body  = '';
foreach (array('product_id','id','pid') as $key) {
    $r = wp_remote_post(admin_url('admin-ajax.php'), array(
        'timeout'=>60,'sslverify'=>false,
        'headers'=>array('User-Agent'=>'AF-Diag','X-Requested-With'=>'XMLHttpRequest'),
        'body'=>array('action'=>$action, $key=>$pid, 'security'=>$nonce, 'nonce'=>$nonce),
    ));
    if (is_wp_error($r)) continue;
    $b = wp_remote_retrieve_body($r);
    if (strlen($b) > 2000) { $body = $b; echo "\nkey that worked: {$key}\n"; break; }
}
if ($body === '') { echo "\n(no usable response)\n"; return; }

echo "response: " . number_format(strlen($body)/1024, 1) . " KB\n";

echo "\n=== OUR MARKERS ===\n";
$markers = array(
    'af-pp-sec (any post-summary section)', 'af-recent', 'af-faq', 'af-relsearch',
    'af-pp-trust (in-summary badges)', 'af-buynow', 'af-opts', 'af-mini-card',
);
foreach ($markers as $m) {
    $needle = trim(explode('(', $m)[0]);
    printf("  %-38s %s\n", $m, strpos($body, $needle) !== false ? 'present' : 'ABSENT');
}

echo "\n=== EVERY af-* CLASS IN THE RESPONSE ===\n";
if (preg_match_all('/class="([^"]*af-[^"]*)"/i', $body, $m)) {
    $seen = array();
    foreach ($m[1] as $cls) {
        foreach (preg_split('/\s+/', $cls) as $one) {
            if (strpos($one, 'af-') !== 0) continue;
            if (isset($seen[$one])) { $seen[$one]++; continue; }
            $seen[$one] = 1;
        }
    }
    ksort($seen);
    if ($seen) { foreach ($seen as $cls => $n) echo "  {$cls} x{$n}\n"; }
    else echo "  (none)\n";
} else {
    echo "  (none)\n";
}

// If the sections are missing, the likeliest reason is that the plugin never
// fires the hook they hang off. Show what the response ends with.
echo "\n=== LAST 400 BYTES OF THE RESPONSE ===\n";
echo "  " . str_replace("\n", ' ', substr($body, -400)) . "\n";

echo "\n=== DONE ===\n";
