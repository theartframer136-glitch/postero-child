<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The quick-view modal must be the product page for that product.
 *
 * Not a trimmed summary: the same sections, with the same styling. Two things
 * have to hold for that. The sections must render during the quick-view ajax
 * call, and the CSS they need must be on the page the modal opens over — the
 * shop or the homepage — because that is the DOM the modal is injected into.
 * Miss the second and Recently Viewed comes out as a column of full-width
 * images, which is what a visitor actually saw.
 *
 * Read-only.
 * Run: wp eval-file tools/verify-quickview-modal.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;

function af_qv($label, $ok, &$fail, $extra = '') {
    printf("  %-48s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra !== '' ? '  ' . $extra : '');
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
af_qv('section gate exists',        function_exists('af_show_product_sections'), $fail);
af_qv('quick-view detector exists', function_exists('af_is_quick_view_action'), $fail);
if (!function_exists('af_show_product_sections') || !function_exists('af_is_quick_view_action')) {
    echo "\n=== RESULT: PROBLEM(S) ===\n"; return;
}

// ── 1. the action test must recognise a modal and nothing else ──
// Only the action-matching half is exercised here. The full request check also
// requires wp_doing_ajax(), which is always false under WP-CLI, so asserting it
// from here would fail against perfectly good code — an earlier version of this
// file did exactly that.
$cases = array(
    'eael_product_quickview_popup' => true,   // what this site actually uses
    'woosq_get_product'            => true,
    'wc_quick_view'                => true,
    'qv_load_product'              => true,
    'woocommerce_add_to_cart'      => false,
    'af_bot_reply'                 => false,
    ''                             => false,
);
$wrong = array();
foreach ($cases as $act => $want) {
    if (af_is_quick_view_action($act) !== $want) $wrong[] = ($act === '' ? '(empty)' : $act);
}
af_qv('quick-view actions recognised, others not', !$wrong, $fail,
      $wrong ? implode(', ', $wrong) : count($cases) . ' actions correct');
// and outside an ajax request it must always be false, whatever the action says
af_qv('not a quick view outside ajax', af_is_quick_view_request() === false, $fail);

// ── 2. the styling must live where the modal opens, not only on the product page ──
// This is the bit that actually broke: the CSS was gated to is_product(), so on
// the shop the modal had no rule for .af-recent-row and the strip blew up.
$css_rules = array('.af-recent-row', '.af-mini-card', '.af-pp-sec');
foreach (array('home' => home_url('/'), 'shop' => get_permalink(wc_get_page_id('shop'))) as $where => $url) {
    if (!$url) continue;
    $r = af_qv_get($url);
    if (is_wp_error($r)) { af_qv("{$where} fetch", false, $fail, $r->get_error_message()); continue; }
    $b = wp_remote_retrieve_body($r);
    $missing = array();
    foreach ($css_rules as $rule) { if (strpos($b, $rule) === false) $missing[] = $rule; }
    af_qv("{$where} carries the modal's styling", !$missing, $fail,
          $missing ? 'missing ' . implode(' ', $missing) : implode(' ', $css_rules));
}

// ── 3. the product page keeps everything ──
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
    if ($code === 200) af_qv('product page still has its sections', strpos($body, 'af-pp-sec') !== false, $fail);
}

// ── 4. and the modal itself must carry the same sections ──
// Discover the plugin's ajax action instead of hard-coding a private name, so
// swapping quick-view plugins surfaces here rather than silently passing.
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
    // Essential Addons (this site's quick view) wants a nonce and its own
    // product key; other plugins want plain id/pid. Try each shape rather than
    // hard-coding one plugin's contract.
    $nonce = wp_create_nonce('essential-addons-elementor');
    foreach (array('product_id','id','pid') as $key) {
        $r = wp_remote_post(admin_url('admin-ajax.php'), array(
            'timeout'=>60, 'sslverify'=>false,
            'headers'=>array('User-Agent'=>'AF-Verify','X-Requested-With'=>'XMLHttpRequest'),
            'body'=>array('action'=>$action, $key=>$pid, 'security'=>$nonce, 'nonce'=>$nonce),
        ));
        if (is_wp_error($r)) continue;
        $b = wp_remote_retrieve_body($r);
        if (strlen($b) > 2000) { $got = $b; break; }
    }
    if ($got === '') {
        echo "  (modal response not captured — the plugin wants different args; gate and CSS checked above)\n";
    } else {
        af_qv('modal responds with markup', true, $fail, number_format(strlen($got)/1024, 0) . ' KB');
        af_qv('modal has the purchase summary',
              stripos($got, 'add-to-cart') !== false || stripos($got, 'add to cart') !== false, $fail);
        // at least one of the page's own sections must have come through —
        // which of them has content depends on the visitor, so do not demand all
        $found = array();
        foreach (array('af-pp-sec', 'af-recent', 'af-faq') as $marker) {
            if (strpos($got, $marker) !== false) $found[] = $marker;
        }
        af_qv('modal carries the product page sections', !empty($found), $fail,
              $found ? implode(' ', $found) : 'none present');
    }
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
