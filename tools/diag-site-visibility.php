<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Live-site triage: is the shop visible, and WHY are product pages erroring?
 *
 * The first version answered the visibility question but cried wolf on the
 * word "coming soon" appearing anywhere in a 140 KB storefront. It now judges
 * a placeholder by the page TITLE, and spends its effort on the real incident:
 * which products fail, whether it is only recently-modified ones, and what the
 * fatal actually says. Read-only.
 * Run: wp eval-file tools/diag-site-visibility.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

function af_dv_get($url) {
    return wp_remote_get(add_query_arg('nocache', microtime(true), $url),
        array('timeout' => 45, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Diag')));
}
function af_dv_title($body) {
    return preg_match('#<title[^>]*>(.*?)</title>#is', $body, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '(no title)';
}

echo "=== SITE VISIBILITY ===\n";
$cs = get_option('woocommerce_coming_soon');
printf("  %-40s %s\n", 'WooCommerce coming-soon mode', var_export($cs, true));
printf("  %-40s %s\n", '…store pages only', var_export(get_option('woocommerce_store_pages_only'), true));
printf("  %-40s %s\n", '.maintenance flag', file_exists(ABSPATH . '.maintenance') ? 'PRESENT <<<' : 'absent');
echo "  (store-pages-only only bites when coming-soon mode is 'yes')\n";

echo "\n=== WHAT AN ANONYMOUS VISITOR RECEIVES ===\n";
$checks = array('home' => home_url('/'), 'shop' => home_url('/shop/'));
foreach ($checks as $label => $url) {
    $r = af_dv_get($url);
    if (is_wp_error($r)) { printf("  %-8s FETCH FAILED %s\n", $label, $r->get_error_message()); continue; }
    $b = wp_remote_retrieve_body($r);
    printf("  %-8s HTTP %-4d %5s KB  title=\"%s\"  %s\n", $label,
        wp_remote_retrieve_response_code($r), number_format(strlen($b)/1024),
        mb_strimwidth(af_dv_title($b), 0, 46, '…'),
        stripos(af_dv_title($b), 'coming soon') !== false ? 'PLACEHOLDER <<<' : 'real page');
}

echo "\n=== PRODUCT PAGES: recent vs random ===\n";
$recent = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>6,'fields'=>'ids','orderby'=>'modified','order'=>'DESC'));
$random = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>6,'fields'=>'ids','orderby'=>'rand'));
$fail_ids = array(); $ok_ids = array();
foreach (array('recently modified' => $recent, 'random' => $random) as $group => $ids) {
    echo "  -- {$group} --\n";
    foreach ($ids as $pid) {
        $r = af_dv_get(get_permalink($pid));
        if (is_wp_error($r)) { printf("     #%-7d FETCH FAILED\n", $pid); continue; }
        $code = (int) wp_remote_retrieve_response_code($r);
        $b    = wp_remote_retrieve_body($r);
        $bad  = ($code >= 500) || stripos($b, 'critical error') !== false;
        if ($bad) $fail_ids[] = $pid; else $ok_ids[] = $pid;
        printf("     #%-7d HTTP %-4d %5s KB  %s\n", $pid, $code, number_format(strlen($b)/1024),
            $bad ? 'BROKEN <<<' : 'ok');
    }
}
printf("  totals: %d broken, %d ok\n", count($fail_ids), count($ok_ids));

echo "\n=== WHO IS SERVING 'COMING SOON' ===\n";
$active = (array) get_option('active_plugins', array());
$suspects = array('coming-soon','maintenance','seedprod','under-construction','minimal-coming','cmp-','launch');
echo "  active plugins: " . count($active) . "\n";
foreach ($active as $plug) {
    foreach ($suspects as $needle) {
        if (stripos($plug, $needle) !== false) { echo "  SUSPECT <<< {$plug}\n"; break; }
    }
}
foreach (array('seed_csp_enable_css','seed_csp4_settings_content','minimal_coming_soon_settings',
               'cmp_options','wpmm_settings','under_construction_activation_status') as $o) {
    $v = get_option($o);
    if ($v !== false) echo "  option set: {$o}\n";
}
// which theme actually rendered it?
$r = af_dv_get(home_url('/'));
if (!is_wp_error($r)) {
    $b = wp_remote_retrieve_body($r);
    if (preg_match_all('#/wp-content/(plugins|themes)/([^/\'"]+)#', $b, $m)) {
        $srcs = array_slice(array_unique($m[0]), 0, 12);
        echo "  assets the placeholder loads:\n";
        foreach ($srcs as $src) echo "    {$src}\n";
    }
}

echo "\n=== WHAT THE FATAL SAYS ===\n";
// Trigger one, then read what the recorder caught. Fetching a broken product
// makes the fatal happen now, with the recorder already in place — rather than
// hoping an old log holds it.
if (!empty($fail_ids)) {
    af_dv_get(get_permalink($fail_ids[0]));
    sleep(1);
}
// our own recorder — written by inc/fatal-recorder.php on any real fatal
if (function_exists('af_fatal_log_path') && file_exists(af_fatal_log_path())) {
    $ours = @file(af_fatal_log_path());
    if ($ours) {
        echo "  af-fatals.log — last 8:\n";
        foreach (array_slice($ours, -8) as $l) echo '    ' . rtrim(mb_strimwidth($l, 0, 320, '…')) . "\n";
    }
} else {
    echo "  af-fatals.log not written yet (it records from the next fatal onward)\n";
}
$found = false;
foreach (array(WP_CONTENT_DIR . '/debug.log', ABSPATH . 'error_log', WP_CONTENT_DIR . '/error_log',
               ini_get('error_log')) as $log) {
    if (!$log || !file_exists($log) || !is_readable($log)) continue;
    $lines = @file($log);
    if (!$lines) continue;
    // the fatals, not the endless textdomain notices
    $hits = array_filter($lines, function($l) {
        return preg_match('/(Fatal error|Uncaught|Allowed memory size|Maximum execution)/i', $l);
    });
    if (!$hits) continue;
    $found = true;
    echo "  {$log} — last 6 fatals:\n";
    foreach (array_slice($hits, -6) as $l) echo '    ' . rtrim(mb_strimwidth($l, 0, 300, '…')) . "\n";
}
if (!$found) echo "  No Fatal/Uncaught lines in any readable log — the 500 may come from the host layer, not PHP.\n";

// Is a broken product different from a working one? Compare their meta keys.
if ($fail_ids && $ok_ids) {
    echo "\n=== BROKEN vs WORKING PRODUCT ===\n";
    $bad = $fail_ids[0]; $good = $ok_ids[0];
    $bk = array_keys(get_post_meta($bad));  sort($bk);
    $gk = array_keys(get_post_meta($good)); sort($gk);
    printf("  broken #%d has %d meta keys; working #%d has %d\n", $bad, count($bk), $good, count($gk));
    $only_bad  = array_diff($bk, $gk);
    $only_good = array_diff($gk, $bk);
    if ($only_bad)  echo "  only on the BROKEN one: " . implode(', ', array_slice($only_bad, 0, 15)) . "\n";
    if ($only_good) echo "  only on the WORKING one: " . implode(', ', array_slice($only_good, 0, 15)) . "\n";
    if (!$only_bad && !$only_good) echo "  identical meta keys — the difference is in the values, not the shape.\n";
    $p = wc_get_product($bad);
    if ($p) printf("  broken product: type=%s price=%s stock=%s image=%d gallery=%d\n",
        $p->get_type(), $p->get_price(), $p->get_stock_status(), (int) $p->get_image_id(), count($p->get_gallery_image_ids()));
}
echo "\n=== DONE ===\n";
