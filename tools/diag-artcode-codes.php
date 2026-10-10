<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Every product's art code and SKU as the site holds them now, with
 * the code it had before the corrections pass first touched it, its SKU letter
 * if it shares a code, and whether a digital buyer gets its Cloudflare master.
 * The text-only companion to tools/diag-artcode-sheet.php, for checking a
 * deploy of art-code corrections.
 *
 * Output, one line each:
 *   @@C|<pid>|<status>|<art code>|<sku>|<code before fix>|<sku letter>|<master file or ->
 *   @@CODES DONE n=<products> masters=<products a digital buyer gets a master for>
 *
 * Run: wp eval-file tools/diag-artcode-codes.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

global $wpdb;
$ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'private', 'draft', 'pending') ORDER BY ID");
$n = 0; $m = 0;
foreach ($ids as $pid) {
    $master = '-';
    if (function_exists('af_r2_master_for_buyer') && ($p = wc_get_product($pid)) && ($o = af_r2_master_for_buyer($p))) { $master = basename($o['key']); $m++; }
    echo '@@C|' . $pid . '|' . get_post_status($pid)
        . '|' . trim((string) get_post_meta($pid, '_taf_art_code', true))
        . '|' . trim((string) get_post_meta($pid, '_sku', true))
        . '|' . trim((string) get_post_meta($pid, '_af_code_before_fix', true))
        . '|' . trim((string) get_post_meta($pid, '_af_sku_letter', true))
        . '|' . $master . "\n";
    $n++;
}
echo "@@CODES DONE n={$n} masters={$m}\n";
