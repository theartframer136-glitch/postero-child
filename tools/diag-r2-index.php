<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The whole Cloudflare index (inc/digital-masters.php): every art
 * code that has a master in the bucket, its file, and the products holding that
 * code, with their SKU. Before an art code moves, this shows whether a product
 * would lose its download or gain one. File names and art codes only; the
 * bucket's keys and links are not printed.
 *
 * Output, one line each:
 *   @@M|<art code>|<file>|<MB>|<pid:status:sku,...>
 *   @@INDEX built=<UTC> files=<n> codes=<n>
 *
 * Run: wp eval-file tools/diag-r2-index.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$idx = get_option('af_r2_index');
if (!is_array($idx) || empty($idx['map'])) { echo "@@INDEX never built\n"; return; }

global $wpdb;
$holders = array();
foreach ($wpdb->get_results("SELECT p.ID, p.post_status, m.meta_value AS code FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_taf_art_code' AND m.meta_value <> '' WHERE p.post_type = 'product' AND p.post_status <> 'trash'") as $r) {
    $holders[strtoupper(trim($r->code))][] = $r->ID . ':' . $r->post_status . ':' . trim((string) get_post_meta($r->ID, '_sku', true));
}
ksort($idx['map']);
foreach ($idx['map'] as $code => $o) {
    echo '@@M|' . $code . '|' . basename($o['key']) . '|' . number_format($o['size'] / 1e6, 1) . '|' . implode(',', $holders[$code] ?? array()) . "\n";
}
echo '@@INDEX built=' . gmdate('Y-m-d H:i', (int) $idx['built']) . ' files=' . (int) $idx['objects'] . ' codes=' . count($idx['map']) . "\n";
