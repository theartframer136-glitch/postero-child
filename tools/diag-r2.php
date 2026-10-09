<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Digital downloads from Cloudflare R2 (inc/digital-masters.php):
 * is the bucket connected, can the site read it, how many products have their
 * master, and does a signed link to one master really open (asked with HEAD:
 * nothing is downloaded, no download is counted). The secret is never printed.
 *
 * Run: wp eval-file tools/diag-r2.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);

echo "=== module\n";
if (!function_exists('af_r2_config')) { echo "  inc/digital-masters.php is not loaded on the site (not deployed yet)\n=== END\n"; return; }
$c = af_r2_config();
if (!$c) { echo "  not connected: buyers get the files WooCommerce has today\n=== END\n"; return; }
echo '  connected: account ' . substr($c['account'], 0, 6) . '…, bucket ' . $c['bucket'] . ', access key ' . substr($c['key'], 0, 4) . '… (' . strlen($c['secret']) . "-character secret, not shown)\n";
echo '  next index refresh: ' . (($t = wp_next_scheduled('af_r2_refresh_index')) ? gmdate('Y-m-d H:i', $t) . ' UTC' : 'not scheduled yet') . "\n";
if ($e = get_option('af_r2_last_error')) echo "  LAST ERROR: $e\n";

echo "\n=== the index (art code => file)\n";
$idx = get_option('af_r2_index');
if (!is_array($idx)) { echo "  never built\n"; } else {
    echo '  built ' . gmdate('Y-m-d H:i', (int) $idx['built']) . ' UTC: ' . (int) $idx['objects'] . ' files in the bucket, ' . count($idx['map']) . " art codes matched\n";
    global $wpdb;
    $rows = $wpdb->get_results("SELECT p.ID, m.meta_value AS code FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_taf_art_code' AND m.meta_value <> '' WHERE p.post_type = 'product' AND p.post_status = 'publish'");
    $have = 0; $buyer = 0; $shown = 0;
    foreach ($rows as $r) {
        $m = $idx['map'][af_r2_norm_code($r->code)] ?? null;
        if (!$m) continue;
        $have++;
        $p = wc_get_product($r->ID);
        if ($p && af_r2_master_for_buyer($p)) $buyer++;
        if ($shown < 10) { printf("    #%-6d %-18s %s (%s)\n", $r->ID, $r->code, $m['key'], size_format($m['size'], 1)); $shown++; }
    }
    printf("  %d of %d published products have a master; %d of them deliver it to a digital buyer (SKU = art code)\n", $have, count($rows), $buyer);
    $unmatched = (int) $idx['objects'] - count($idx['map']);
    if ($unmatched > 0) echo "  $unmatched file(s) in the bucket match no art code (check their names)\n";
}

echo "\n=== a signed link, really opened (HEAD only)\n";
$first = is_array($idx) && !empty($idx['map']) ? reset($idx['map']) : null;
if (!$first) { echo "  no master to try yet\n"; } else {
    $url = af_r2_object_url($first['key'], 120, 'test');
    $r = wp_remote_head($url, array('timeout' => 30, 'redirection' => 0));
    echo '  ' . $first['key'] . ': ' . (is_wp_error($r) ? 'ERROR ' . $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r) . ', ' . size_format((int) wp_remote_retrieve_header($r, 'content-length'), 1) . ', ' . wp_remote_retrieve_header($r, 'content-type')) . "\n";
    $bad = preg_replace('/X-Amz-Signature=[0-9a-f]+/', 'X-Amz-Signature=' . str_repeat('0', 64), $url);
    $r2 = wp_remote_head($bad, array('timeout' => 30, 'redirection' => 0));
    echo '  the same link with a wrong signature: ' . (is_wp_error($r2) ? 'ERROR' : 'HTTP ' . wp_remote_retrieve_response_code($r2)) . " (must be refused: the bucket is private)\n";
}

echo "\n=== the shop's download settings\n";
foreach (array('woocommerce_file_download_method', 'woocommerce_downloads_require_login', 'woocommerce_downloads_grant_access_after_payment') as $o) echo "  $o = " . json_encode(get_option($o)) . "\n";
echo "=== END\n";
