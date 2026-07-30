<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove the §17 cookie banner and the §6 masonry toggle actually ship in the
 * pages a visitor receives. Read-only.
 * Run: wp eval-file tools/verify-consent-masonry.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;
function af_cmv($label, $ok, &$fail, $extra = '') {
    printf("  %-44s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}
function af_cmv_get($url) {
    $a = array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify'));
    for ($i=0;$i<3;$i++) {
        $r = wp_remote_get($url,$a);
        if (!is_wp_error($r) && !in_array((int) wp_remote_retrieve_response_code($r), array(508,503,429), true)) return $r;
        sleep(3);
    }
    return $r;
}

echo "=== COOKIE CONSENT (§17) — homepage ===\n";
$r = af_cmv_get(home_url('/?nocache=' . time()));
if (is_wp_error($r)) { af_cmv('homepage fetch', false, $fail, $r->get_error_message()); }
else {
    $b = wp_remote_retrieve_body($r);
    af_cmv('responds 200',            (int) wp_remote_retrieve_response_code($r) === 200, $fail);
    af_cmv('banner markup',           strpos($b, 'id="af-consent"') !== false, $fail);
    af_cmv('accept all',              strpos($b, 'af-ck-accept') !== false, $fail);
    af_cmv('necessary only',          strpos($b, 'af-ck-necessary') !== false, $fail);
    af_cmv('per-category preferences',strpos($b, 'af-ck-analytics') !== false && strpos($b, 'af-ck-marketing') !== false, $fail);
    af_cmv('CCPA do-not-sell wording',stripos($b, 'Do&nbsp;Not&nbsp;Sell') !== false || stripos($b, 'Do Not Sell') !== false, $fail);
    af_cmv('privacy policy linked',   stripos($b, 'privacy') !== false, $fail);
    af_cmv('consent API exposed',     strpos($b, 'afConsent') !== false, $fail);
    af_cmv('gated-script activation', strpos($b, 'data-consent') !== false, $fail);
}

echo "\n=== MASONRY (§6) — category page ===\n";
$terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'number'=>1,'orderby'=>'count','order'=>'DESC'));
if (!$terms || is_wp_error($terms)) { echo "  (no product categories found)\n"; }
else {
    $url = get_term_link($terms[0]);
    echo "  testing: {$url}\n";
    $r2 = af_cmv_get(add_query_arg('nocache', time(), $url));
    if (is_wp_error($r2)) { af_cmv('category fetch', false, $fail, $r2->get_error_message()); }
    else {
        $b2 = wp_remote_retrieve_body($r2);
        af_cmv('responds 200',        (int) wp_remote_retrieve_response_code($r2) === 200, $fail);
        af_cmv('layout toggle',       strpos($b2, 'af-layout-toggle') !== false, $fail);
        af_cmv('grid + masonry modes',strpos($b2, 'data-mode="grid"') !== false && strpos($b2, 'data-mode="masonry"') !== false, $fail);
        af_cmv('masonry CSS',         strpos($b2, 'af-masonry') !== false, $fail);
        af_cmv('choice persistence',  strpos($b2, 'af_shop_layout') !== false, $fail);
        // the banner must be on shop pages too, not just the homepage
        af_cmv('consent banner here too', strpos($b2, 'id="af-consent"') !== false, $fail);
    }
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
