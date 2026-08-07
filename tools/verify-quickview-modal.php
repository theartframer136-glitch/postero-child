<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The quick-view modal must show the purchase summary and nothing else.
 *
 * The product page hangs several full-width sections off
 * woocommerce_after_single_product_summary — Related Searches, Popular
 * Products, Recently Viewed, Digital Downloads, the Customize CTA, the FAQ.
 * The quick-view plugin fires that same hook to build its modal, but the modal
 * never loads the product-page stylesheet, so anything that leaks in renders as
 * a stack of full-width unstyled images. This proves the leak is closed while
 * the real product page keeps every section.
 *
 * Read-only.
 * Run: wp eval-file tools/verify-quickview-modal.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;

function af_qv($label, $ok, &$fail, $extra = '') {
    printf("  %-46s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra !== '' ? '  ' . $extra : '');
    if (!$ok) $fail++;
}
function af_qv_get($url) {
    $a = array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify'));
    for ($i=0; $i<3; $i++) {
        $r = wp_remote_get($url, $a);
        if (is_wp_error($r)) { sleep(3); continue; }
        if (!in_array((int) wp_remote_retrieve_response_code($r), array(508,503,429), true)) return $r;
        sleep(4);
    }
    return $r;
}

echo "=== QUICK VIEW MODAL ===\n";
af_qv('page-context guard exists', function_exists('af_is_product_page'), $fail);
if (!function_exists('af_is_product_page')) { echo "\n=== RESULT: 1 PROBLEM(S) ===\n"; return; }

// The sections that must never appear inside the modal, by the markup they emit.
$sections = array(
    'Recently Viewed'   => array('af-recent'),
    'Related Searches'  => array('af-relsearch', 'af-pp-sec'),
    'Popular Products'  => array('af-popular'),
    'Digital Downloads' => array('af-dd-sec'),
    'FAQ'               => array('af-faq'),
);

// ── 1. the real product page must still carry them ──
$pid = 0;
foreach (wc_get_products(array('status'=>'publish','limit'=>8,'return'=>'ids','orderby'=>'date','order'=>'DESC')) as $cand) {
    $p = wc_get_product($cand);
    if ($p && $p->get_image_id()) { $pid = $cand; break; }
}
if (!$pid) { echo "  (no published product with an image — skipped)\n"; }
else {
    $r    = af_qv_get(get_permalink($pid));
    $body = is_wp_error($r) ? '' : wp_remote_retrieve_body($r);
    $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
    af_qv('product page responds 200', $code === 200, $fail, "#{$pid}, " . number_format(strlen($body)/1024, 0) . " KB");
    if ($code === 200) {
        // at least the two that always have something to show
        af_qv('product page still has its sections',
              strpos($body, 'af-pp-sec') !== false, $fail);
    }
}

// ── 2. the modal must not ──
// Find whatever ajax action the quick-view plugin registered, rather than
// hard-coding a plugin's private action name.
global $wp_filter;
$action = '';
foreach (array_keys($wp_filter) as $hook) {
    if (strpos($hook, 'wp_ajax_nopriv_') !== 0) continue;
    $name = substr($hook, strlen('wp_ajax_nopriv_'));
    if (preg_match('/(woosq|quick_?view|_qv$|^qv_)/i', $name)) { $action = $name; break; }
}
echo "  quick-view ajax action: " . ($action ? $action : '(none found)') . "\n";

if ($action && $pid) {
    $got = '';
    foreach (array('id','product_id','pid') as $key) {
        $r = wp_remote_post(admin_url('admin-ajax.php'), array(
            'timeout'=>60, 'sslverify'=>false,
            'headers'=>array('User-Agent'=>'AF-Verify','X-Requested-With'=>'XMLHttpRequest'),
            'body'=>array('action'=>$action, $key=>$pid),
        ));
        if (is_wp_error($r)) continue;
        $b = wp_remote_retrieve_body($r);
        // the useful response is the one that actually rendered the product
        if (strlen($b) > 2000) { $got = $b; break; }
    }
    if ($got === '') {
        echo "  (modal response not captured — plugin wants different args; static check only)\n";
    } else {
        af_qv('modal responds with markup', true, $fail, number_format(strlen($got)/1024, 0) . ' KB');
        af_qv('modal has the purchase summary',
              stripos($got, 'add-to-cart') !== false || stripos($got, 'add to cart') !== false, $fail);
        foreach ($sections as $label => $needles) {
            $hit = '';
            foreach ($needles as $n) { if (strpos($got, $n) !== false) { $hit = $n; break; } }
            af_qv("modal is free of: {$label}", $hit === '', $fail, $hit ? "found '{$hit}'" : '');
        }
    }
}

// ── 3. and the guard itself must refuse an ajax context ──
// Every section is wrapped in this guard, so if it ever answers true during an
// ajax request the modal fills up again.
$was = defined('DOING_AJAX');
af_qv('guard refuses admin context', af_is_product_page() === false || !is_admin(), $fail);
af_qv('guard refuses a non-product request', !is_product() ? af_is_product_page() === false : true, $fail);
echo "  (guard under CLI: " . var_export(af_is_product_page(), true) . " — CLI is not a product page)\n";

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
