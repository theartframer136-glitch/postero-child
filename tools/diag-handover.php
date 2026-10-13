<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The facts the 13 Oct connections handover needs from inside the
 * site, for its open items:
 *   2  GA4 ecommerce events: which tag(s) the pages print, which GA4 id the
 *      theme's own analytics module has saved, whether the HFCM snippet
 *      still prints the second tag, and how consent is granted;
 *   3  the /hioduct/ URL Google indexed: the WooCommerce permalink base, any
 *      'hioduct' in posts, meta, options, terms, rewrite rules or Rank Math's
 *      redirections, and what the live URL answers today;
 *   6  the Meta product feed: whether it exists and how many items it has.
 * Prints no key: the feed key is reported by length only.
 *
 *   wp eval-file tools/diag-handover.php
 */
global $wpdb;
$len = function ($v) { $v = (string) $v; return $v === '' ? 'absent' : 'present (' . strlen($v) . ' chars)'; };

echo "=== GA4 (handover item 2)\n";
echo '  af_ga4_id (theme analytics module): ' . (af_ga4_id() !== '' ? af_ga4_id() : '(empty: the module prints nothing)') . "\n";
echo '  af_gtm_id: ' . (af_gtm_id() !== '' ? af_gtm_id() : '(empty)') . "\n";
echo '  HFCM plugin class present: ' . (class_exists('NNR_HFCM') ? 'yes (plugin active, port idle)' : 'no (the port prints snippet #1, tag G-F3PB80TT6Y, on every page, no consent gate)') . "\n";
$ap = (array) get_option('active_plugins', array());
foreach (array('header-footer-code-manager', 'google-site-kit', 'woocommerce-google-analytics-integration') as $slug) {
    $on = false; foreach ($ap as $p) if (strpos($p, $slug . '/') === 0) $on = true;
    echo "  plugin {$slug}: " . ($on ? 'ACTIVE' : 'inactive') . "\n";
}
// what a product page's <head> would print, in a nutshell
ob_start(); do_action('wp_head'); $head = ob_get_clean();
preg_match_all('#gtag/js\?id=(G-[A-Z0-9]+)#', $head, $m);
echo '  gtag loaders printed in wp_head (CLI, front page context): ' . ($m[1] ? implode(', ', $m[1]) : 'none') . "\n";
echo '  consent-gated scripts in wp_head: ' . preg_match_all('#type="text/plain" data-consent="analytics"#', $head) . "\n";
echo '  GA4 purchase events already sent (orders with _af_ga4_purchase_sent): ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_af_ga4_purchase_sent'") . (function_exists('wc_get_container') ? ' (posts table; HPOS orders not counted)' : '') . "\n";
foreach (array('af_cc_enabled', 'af_cookie_consent', 'af_cc_mode', 'af_cc_default') as $o) { $v = get_option($o, null); if ($v !== null) echo "  option {$o}: " . wp_json_encode($v) . "\n"; }

echo "\n=== the /hioduct/ URL (handover item 3)\n";
$perm = get_option('woocommerce_permalinks');
echo '  woocommerce_permalinks: ' . wp_json_encode($perm) . "\n";
echo '  permalink_structure: ' . get_option('permalink_structure') . "\n";
$hits = array();
$hits['posts'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%hioduct%' OR post_name LIKE '%hioduct%' OR guid LIKE '%hioduct%'");
$hits['postmeta'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE '%hioduct%'");
$hits['options'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE '%hioduct%'");
$hits['terms'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->terms} WHERE slug LIKE '%hioduct%' OR name LIKE '%hioduct%'");
$hits['comments'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_content LIKE '%hioduct%' OR comment_author_url LIKE '%hioduct%'");
foreach ($hits as $k => $n) echo "  'hioduct' in {$k}: {$n}\n";
if ($hits['options']) foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE '%hioduct%' LIMIT 10") as $o) echo "    option: {$o}\n";
if ($hits['postmeta']) foreach ($wpdb->get_results("SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value LIKE '%hioduct%' LIMIT 10") as $r) echo "    postmeta: #{$r->post_id} {$r->meta_key}\n";
if ($hits['posts']) foreach ($wpdb->get_results("SELECT ID, post_type, post_name FROM {$wpdb->posts} WHERE post_content LIKE '%hioduct%' OR post_name LIKE '%hioduct%' OR guid LIKE '%hioduct%' LIMIT 10") as $r) echo "    post: #{$r->ID} {$r->post_type} {$r->post_name}\n";
$rules = get_option('rewrite_rules');
$rr = is_array($rules) ? array_filter(array_keys($rules), function ($k) { return stripos($k, 'hioduct') !== false; }) : array();
echo '  rewrite rules mentioning hioduct: ' . count($rr) . "\n";
foreach (array('rank_math_redirections', 'rank_math_redirections_cache') as $t) {
    $tn = $wpdb->prefix . $t;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tn)) === $tn) {
        $n = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tn}");
        $h = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tn} WHERE " . ($t === 'rank_math_redirections' ? "sources LIKE '%hioduct%' OR url_to LIKE '%hioduct%'" : "from_url LIKE '%hioduct%'"));
        echo "  {$t}: {$n} rows, {$h} mention hioduct\n";
    } else echo "  {$t}: no table\n";
}
$p = get_page_by_path('tanjore-devotion-panel-canvas-wall-art', OBJECT, 'product');
echo '  product tanjore-devotion-panel-canvas-wall-art: ' . ($p ? "#{$p->ID} {$p->post_status}, real URL " . get_permalink($p) : 'not found') . "\n";
foreach (array('/hioduct/tanjore-devotion-panel-canvas-wall-art/', '/hioduct/', '/product/tanjore-devotion-panel-canvas-wall-art/') as $path) {
    $r = wp_remote_get(home_url($path), array('timeout' => 20, 'redirection' => 0, 'user-agent' => 'Mozilla/5.0 (af-diag-handover)', 'headers' => array('Accept' => 'text/html')));
    if (is_wp_error($r)) { echo "  live {$path}: " . $r->get_error_message() . "\n"; continue; }
    $code = wp_remote_retrieve_response_code($r); $loc = wp_remote_retrieve_header($r, 'location');
    $title = preg_match('#<title>([^<]*)</title>#i', wp_remote_retrieve_body($r), $tm) ? trim($tm[1]) : '';
    echo "  live {$path}: HTTP {$code}" . ($loc ? " -> {$loc}" : '') . ($title ? " | title: " . mb_substr($title, 0, 70) : '') . "\n";
}

echo "\n=== Meta product feed (handover item 6)\n";
if (function_exists('af_mk_feed_url')) {
    echo '  feed key: ' . $len(get_option('af_mk_key')) . "\n";
    $r = wp_remote_get(af_mk_feed_url('meta'), array('timeout' => 60, 'user-agent' => 'Mozilla/5.0 (af-diag-handover)'));
    if (is_wp_error($r)) echo '  fetching the feed: ' . $r->get_error_message() . "\n";
    else {
        $b = wp_remote_retrieve_body($r);
        $items = preg_match_all('#<item>#', $b) ?: max(0, substr_count($b, "\n") - 1);
        echo '  feed answers HTTP ' . wp_remote_retrieve_response_code($r) . ', ' . wp_remote_retrieve_header($r, 'content-type') . ', ' . strlen($b) . " bytes, about {$items} items\n";
        echo '  where to copy the address: wp-admin → Marketplace (the Meta row, "Open")' . "\n";
    }
} else echo "  marketplace module not loaded\n";
echo "=== END\n";
