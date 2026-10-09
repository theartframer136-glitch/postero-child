<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Digital downloads from Cloudflare R2 (inc/digital-masters.php):
 * is the bucket connected, can the site read it, how many products have their
 * master, which files in the bucket are not used and why, and does the link a
 * buyer would get really open: the same GET a browser makes, for the first byte
 * only, so nothing is downloaded and no download is counted. (Not HEAD: the
 * link is signed for GET, and Cloudflare refuses it for anything else.) Wrong,
 * expired and unsigned links must be refused. The secret is never printed.
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
$tp = null;
if (!is_array($idx)) { echo "  never built\n"; } else {
    echo '  built ' . gmdate('Y-m-d H:i', (int) $idx['built']) . ' UTC: ' . (int) $idx['objects'] . ' files in the bucket, ' . count($idx['map']) . " art codes matched\n";
    if (function_exists('af_r2_usage_line') && ($u = af_r2_usage_line($idx))) echo "  storage: $u\n";
    global $wpdb;
    $rows = $wpdb->get_results("SELECT p.ID, m.meta_value AS code FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_taf_art_code' AND m.meta_value <> '' WHERE p.post_type = 'product' AND p.post_status = 'publish'");
    $have = 0; $buyer = 0; $dl = 0; $shown = 0; $published = array(); $notbuyer = array();
    foreach ($rows as $r) {
        $code = af_r2_norm_code($r->code);
        $m = $idx['map'][$code] ?? null;
        if (!$m) continue;
        $have++; $published[$code] = true;
        $p = wc_get_product($r->ID);
        if ($p && af_r2_master_for_buyer($p)) {
            $buyer++;
            if ($p->is_downloadable() && count($p->get_downloads()) > 0) { $dl++; if (!$tp) $tp = $p; }
        } else {
            $notbuyer[] = sprintf('#%d %s (SKU %s)', $r->ID, $code, $p ? ($p->get_sku() === '' ? 'empty' : $p->get_sku()) : '?');
        }
        if ($shown < 10) { printf("    #%-6d %-18s %s (%s)\n", $r->ID, $r->code, $m['key'], size_format($m['size'], 1)); $shown++; }
    }
    printf("  %d of %d published products have a master; %d of them deliver it to a digital buyer (SKU = art code)\n", $have, count($rows), $buyer);
    printf("  %d of those %d are set up as downloadable with a file, which WooCommerce needs before it gives a buyer any download link\n", $dl, $buyer);
    foreach ($notbuyer as $n) {
        echo "    master not delivered: $n: the SKU is not the art code, so a buyer of it keeps the file WooCommerce has today\n";
        $code = preg_replace('/^#\d+ (\S+).*$/', '$1', $n);
        $all = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_status, p.post_title FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_taf_art_code' WHERE UPPER(TRIM(m.meta_value)) = %s AND p.post_type = 'product' ORDER BY p.ID", $code));
        foreach ($all as $x) printf("      #%d %s, SKU %s: %s\n", $x->ID, $x->post_status, get_post_meta($x->ID, '_sku', true) ?: '(none)', mb_substr($x->post_title, 0, 60));
    }
    foreach (array_keys($idx['map']) as $code) {
        if (isset($published[$code])) continue;
        $st = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_status FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_taf_art_code' WHERE UPPER(TRIM(m.meta_value)) = %s AND p.post_type IN ('product', 'product_variation')", $code));
        $who = array(); foreach ($st as $x) $who[] = '#' . $x->ID . ' ' . $x->post_status;
        echo "    matched, but no published product carries it: $code => " . $idx['map'][$code]['key'] . ' (' . ($who ? implode(', ', $who) : 'not on any product') . ")\n";
    }

    echo "\n=== files in the bucket that are not used\n";
    $objs = af_r2_list_objects();
    if (!is_array($objs)) { echo '  could not list the bucket: ' . get_option('af_r2_last_error') . "\n"; } else {
        $used = array(); foreach ($idx['map'] as $code => $m) $used[$m['key']] = $code;
        $n = 0;
        foreach ($objs as $k => $size) {
            if (isset($used[$k])) continue;
            $n++;
            $u = af_r2_norm_code(basename($k));
            $why = 'its name starts with no art code on the site';
            foreach ($used as $uk => $code) {
                if (strpos($u, $code) === 0 && preg_match('/^[ ._\-]/', substr($u, strlen($code)) . ' ')) { $why = "same art code as $uk, which is larger and is the one used"; break; }
            }
            printf("    %s (%s): %s\n", $k, size_format($size, 1), $why);
            // the same picture number under another size or a changed code?
            if (strpos($why, 'no art code') !== false && preg_match('/^([A-Z]{2}-\d{6})-/', $u, $mm)) {
                $near = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_status, p.post_title, m.meta_key, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('_taf_art_code', '_sku') WHERE UPPER(m.meta_value) LIKE %s AND p.post_type IN ('product', 'product_variation') ORDER BY p.ID", $mm[1] . '-%'));
                if (!$near) echo "      no product's art code or SKU starts with {$mm[1]}-\n";
                foreach ($near as $x) printf("      #%d %s, %s %s: %s\n", $x->ID, $x->post_status, $x->meta_key === '_sku' ? 'SKU' : 'art code', $x->meta_value, mb_substr($x->post_title, 0, 60));
            }
        }
        if (!$n) echo "  none: every file is some product's master\n";
    }
}

echo "\n=== a buyer's link, really opened (first byte only: nothing downloaded, nothing counted)\n";
if (!$tp) { echo "  no product to try yet\n"; } else {
    try {
        $url = apply_filters('woocommerce_download_product_filepath', 'unchanged', '', null, $tp, null);
        $method = apply_filters('woocommerce_file_download_method', get_option('woocommerce_file_download_method'), $tp->get_id(), $url);
    } catch (\Throwable $e) { $url = ''; $method = 'ERROR ' . $e->getMessage(); }
    $ours = strpos((string) $url, 'https://' . af_r2_host($c) . '/') === 0;
    echo '  #' . $tp->get_id() . ' ' . $tp->get_sku() . ': WooCommerce would hand out ' . ($ours ? 'a signed Cloudflare link' : 'NOT a Cloudflare link (' . substr((string) $url, 0, 40) . ')') . ", by \"$method\" (must be \"redirect\")\n";
    $get = function ($u) {
        $r = wp_remote_get($u, array('timeout' => 30, 'redirection' => 0, 'headers' => array('Range' => 'bytes=0-0')));
        if (is_wp_error($r)) return 'ERROR ' . $r->get_error_message();
        $code = (int) wp_remote_retrieve_response_code($r);
        $out = "HTTP $code";
        if ($code === 200 || $code === 206) {
            $out .= ', ' . (wp_remote_retrieve_header($r, 'content-range') ?: 'length ' . wp_remote_retrieve_header($r, 'content-length'))
                  . ', ' . wp_remote_retrieve_header($r, 'content-type')
                  . ', saved as: ' . wp_remote_retrieve_header($r, 'content-disposition');
        } else {
            $b = wp_remote_retrieve_body($r);
            if (preg_match('#<Code>([^<]+)</Code>#', $b, $mm)) $out .= ' ' . $mm[1];
        }
        return $out;
    };
    if ($ours) {
        echo '  the link:                     ' . $get($url) . " (must be 206 or 200)\n";
        echo '  with a wrong signature:       ' . $get(preg_replace('/X-Amz-Signature=[0-9a-f]+/', 'X-Amz-Signature=' . str_repeat('0', 64), $url)) . " (must be refused)\n";
        $m = af_r2_master_for_buyer($tp);
        $path = '/' . af_r2_enc($c['bucket']) . '/' . af_r2_enc($m['key'], true);
        echo '  signed an hour ago (expired): ' . $get(af_r2_presign(af_r2_host($c), $path, $c['key'], $c['secret'], 'auto', 300, array(), time() - 3600)) . " (must be refused)\n";
        echo '  no signature at all:          ' . $get('https://' . af_r2_host($c) . $path) . " (must be refused: the bucket is private)\n";
    }
}

echo "\n=== the shop's download settings\n";
foreach (array('woocommerce_file_download_method', 'woocommerce_downloads_require_login', 'woocommerce_downloads_grant_access_after_payment') as $o) echo "  $o = " . json_encode(get_option($o)) . "\n";
echo "=== END\n";
