<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The exact sentences that still say "free shipping" or "free
 * delivery" anywhere in post content or Elementor data, each printed once,
 * in full, so tools/rewrite-free-shipping.php can be given the precise
 * phrase to replace. diag-free-shipping.php lists where they are; this
 * prints what they say.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-free-shipping-phrases.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$seen = array();
$show = function ($text, $where) use (&$seen) {
    // Elementor data is JSON: unescape so the sentence reads as the visitor sees it.
    $t = str_replace(array('\\/', '\\"', '\\r\\n', '\\n', '\\u2013', '\\u2014'), array('/', '"', ' ', ' ', '–', '—'), (string) $text);
    $t = html_entity_decode(wp_strip_all_tags(preg_replace('/<\/(p|li|h\d|summary|div)>/i', "$0\n", $t)), ENT_QUOTES | ENT_HTML5);
    $t = preg_replace('/[ \t]+/', ' ', $t);
    foreach (preg_split('/\n+/', $t) as $line) {
        $line = trim($line);
        if ($line === '' || !preg_match('/free\s+(usa?\s+)?(shipping|deliver)/i', $line)) continue;
        $key = md5(mb_strtolower($line));
        if (isset($seen[$key])) { $seen[$key]['n']++; continue; }
        $seen[$key] = array('line' => mb_substr($line, 0, 400), 'where' => $where, 'n' => 1);
    }
};

$rows = $wpdb->get_results("SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts}
    WHERE post_status IN ('publish','draft','private') AND post_type IN ('page','post','product')
      AND (LOWER(post_content) LIKE '%free shipping%' OR LOWER(post_content) LIKE '%free delivery%' OR LOWER(post_content) LIKE '%free usa shipping%')");
foreach ($rows as $r) $show($r->post_content, "content #{$r->ID} {$r->post_type} " . mb_substr($r->post_title, 0, 40));

$metas = $wpdb->get_results("SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm
    JOIN {$wpdb->posts} p ON p.ID = pm.post_id
    WHERE pm.meta_key IN ('_elementor_data') AND p.post_status IN ('publish','draft','private')
      AND (LOWER(pm.meta_value) LIKE '%free shipping%' OR LOWER(pm.meta_value) LIKE '%free delivery%')");
foreach ($metas as $m) $show($m->meta_value, "elementor #{$m->post_id}");

echo "=== DISTINCT SENTENCES SAYING FREE SHIPPING / DELIVERY (" . count($seen) . ")\n\n";
uasort($seen, function ($a, $b) { return $b['n'] - $a['n']; });
foreach ($seen as $s) {
    echo "[x{$s['n']}] first seen: {$s['where']}\n    " . $s['line'] . "\n\n";
}
echo "=== DONE ===\n";
