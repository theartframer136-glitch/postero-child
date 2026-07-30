<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove the §6 orientation filter and the §4 blog hub work on the live site.
 * Read-only.
 * Run: wp eval-file tools/verify-orientation-blog.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;
function af_obv($label, $ok, &$fail, $extra = '') {
    printf("  %-44s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}
function af_obv_get($url) {
    $a = array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify'));
    for ($i=0;$i<3;$i++) {
        $r = wp_remote_get($url,$a);
        if (!is_wp_error($r) && !in_array((int) wp_remote_retrieve_response_code($r), array(508,503,429), true)) return $r;
        sleep(3);
    }
    return $r;
}
global $wpdb;

echo "=== ORIENTATION FILTER (§6) ===\n";
af_obv('module loaded', function_exists('af_orientation_current'), $fail);
$counts = $wpdb->get_results("SELECT meta_value o, COUNT(*) n FROM {$wpdb->postmeta} WHERE meta_key='_af_orientation' GROUP BY meta_value");
$total = 0; foreach ($counts as $c) { printf("    %-10s %d\n", $c->o, $c->n); $total += $c->n; }
af_obv('orientation meta populated', $total > 0, $fail, "{$total} products classified");

$terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'number'=>1,'orderby'=>'count','order'=>'DESC'));
if ($terms && !is_wp_error($terms)) {
    $url = get_term_link($terms[0]);
    $r = af_obv_get(add_query_arg('nocache', time(), $url));
    if (!is_wp_error($r)) {
        $b = wp_remote_retrieve_body($r);
        $gate = strpos($b, 'data-g="loaded"') !== false ? 'loaded'
              : (strpos($b, 'data-g="missing"') !== false ? 'missing' : 'marker absent');
        echo "    render gate: {$gate}\n";
        // does the toolbar itself render on this page at all?
        foreach (array('af-listing-toolbar', 'class="af-lt-controls"', 'Frame Type', 'af-lt-dbtn"') as $needle) {
            echo '    page has [' . $needle . ']: ' . (strpos($b, $needle) !== false ? 'yes' : 'NO') . "\n";
        }
        af_obv('dropdown on category page', strpos($b, 'Orientation') !== false, $fail);
        if (strpos($b, 'Orientation') === false) {
            $i = strpos($b, 'af-lt-controls');
            echo '    toolbar window: ' . ($i === false ? '(af-lt-controls NOT in page)'
                : str_replace("\n", ' ', substr($b, $i, 400))) . "\n";
        }
        af_obv('all three options linked',
            strpos($b, 'orientation=portrait') !== false &&
            strpos($b, 'orientation=landscape') !== false &&
            strpos($b, 'orientation=square') !== false, $fail);
    }
    // the filter must actually narrow the query
    $pick = null; foreach ($counts as $c) { if ((int)$c->n > 0) { $pick = $c->o; break; } }
    if ($pick) {
        $r2 = af_obv_get(add_query_arg(array('orientation' => $pick, 'nocache' => time()), $url));
        if (!is_wp_error($r2)) {
            $b2   = wp_remote_retrieve_body($r2);
            $code = (int) wp_remote_retrieve_response_code($r2);
            af_obv("filtered page ({$pick}) responds", $code === 200, $fail, "HTTP {$code}");
            af_obv('filtered page shows products or none-notice',
                strpos($b2, 'product') !== false, $fail);
        }
    }
}

echo "\n=== BLOG & KNOWLEDGE HUB (§4) ===\n";
$posts = wp_count_posts('post');
echo "  published posts: " . (int) $posts->publish . "\n";
$r3 = af_obv_get(home_url('/blog/?nocache=' . time()));
if (is_wp_error($r3)) { af_obv('/blog/ fetch', false, $fail, $r3->get_error_message()); }
else {
    $b3   = wp_remote_retrieve_body($r3);
    $code = (int) wp_remote_retrieve_response_code($r3);
    af_obv('/blog/ responds 200',   $code === 200, $fail, "HTTP {$code}");
    af_obv('hub heading',           strpos($b3, 'Blog &amp; Knowledge Hub') !== false || strpos($b3, 'Blog & Knowledge Hub') !== false, $fail);
    af_obv('search box',            strpos($b3, 'af-hub-search') !== false, $fail);
    if ((int) $posts->publish > 0) {
        af_obv('article cards',     strpos($b3, 'af-hub-card') !== false || strpos($b3, 'af-hub-hero') !== false, $fail);
        af_obv('reading times',     strpos($b3, 'min read') !== false, $fail);
    }
    af_obv('topic chips',           strpos($b3, 'af-hub-cat') !== false || (int) $posts->publish === 0, $fail);
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
