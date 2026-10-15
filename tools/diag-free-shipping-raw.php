<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The raw bytes around the free-shipping phrases that survived
 * tools/rewrite-free-shipping.php, exactly as stored (JSON escapes, entities,
 * line breaks shown), so the replacement map can be matched to reality.
 * Also whether the rewrite's backups exist (proof it ran on that post) and
 * which Elementor cache rows still hold the old words.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-free-shipping-raw.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$show = function ($label, $text, $needle, $width = 260) {
    $pos = stripos((string) $text, $needle);
    if ($pos === false) { echo "  $label: needle not found\n"; return; }
    $start = max(0, $pos - 80);
    $snip = substr((string) $text, $start, $width);
    echo "  $label:\n    " . addcslashes($snip, "\0..\37") . "\n";
};

echo "=== PAGE #11652 Shipping & Delivery (post_content)\n";
$p = get_post(11652);
if ($p) {
    $show('around "Free Delivery"', $p->post_content, 'Free Delivery');
    $show('around "Free across"', $p->post_content, 'Free across');
    echo "  backup meta present: " . (get_post_meta(11652, '_af_shipping_copy_backup', true) ? 'yes' : 'no') . "\n";
    echo "  post_modified: {$p->post_modified}\n";
}

echo "\n=== PAGE #75 Home (post_content + elementor)\n";
$p = get_post(75);
if ($p) {
    $show('content around "Free Shipping"', $p->post_content, 'Free Shipping');
    $ed = get_post_meta(75, '_elementor_data', true);
    $show('_elementor_data around "Free Shipping"', $ed, 'Free Shipping');
    $show('_elementor_data around "Free Delivery In"', $ed, 'Free Delivery In');
    $ec = get_post_meta(75, '_elementor_element_cache', true);
    echo "  _elementor_element_cache: " . (is_string($ec) ? strlen($ec) . ' bytes, says Free Shipping: ' . (stripos($ec, 'Free Shipping') !== false ? 'YES' : 'no') : gettype($ec)) . "\n";
    echo "  backups present: content=" . (get_post_meta(75, '_af_shipping_copy_backup', true) ? 'yes' : 'no') . " elementor=" . (get_post_meta(75, '_af_elementor_backup', true) ? 'yes' : 'no') . "\n";
}

echo "\n=== PRODUCT #7816 Divine Lord Shiva (_elementor_data)\n";
$ed = get_post_meta(7816, '_elementor_data', true);
$show('around "eligible for free delivery"', $ed, 'eligible for free delivery', 320);
$show('around "Free delivery is available"', $ed, 'Free delivery is available', 420);
echo "  elementor backup present: " . (get_post_meta(7816, '_af_elementor_backup', true) ? 'yes' : 'no') . "\n";

echo "\n=== PAGE #146 About / #159 Contact / #14162 Legal / #14164 Customize (post_content)\n";
foreach (array(146 => 'Free USA Shipping', 159 => 'free delivery', 14162 => 'free delivery', 14164 => 'Free shipping options') as $id => $needle) {
    $p = get_post($id); if (!$p) continue;
    $show("#$id around \"$needle\"", $p->post_content, $needle);
    echo "    backup present: " . (get_post_meta($id, '_af_shipping_copy_backup', true) ? 'yes' : 'no') . " | post_modified: {$p->post_modified}\n";
}

echo "\n=== ELEMENTOR CACHE ROWS STILL SAYING FREE SHIPPING/DELIVERY\n";
$rows = $wpdb->get_results("SELECT post_id, meta_key, LENGTH(meta_value) AS len FROM {$wpdb->postmeta}
    WHERE meta_key IN ('_elementor_element_cache','_elementor_css','_af_elementor_backup_col')
      AND (LOWER(meta_value) LIKE '%free shipping%' OR LOWER(meta_value) LIKE '%free delivery%') LIMIT 40");
foreach ($rows as $r) echo "  #{$r->post_id} {$r->meta_key} ({$r->len} bytes)\n";
echo "  rows: " . count($rows) . "\n";

echo "\n=== SEO DRAFT POSTS (tools/create-seo-posts.php)\n";
$drafts = $wpdb->get_results("SELECT p.ID, p.post_status, p.post_name FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_af_seo_post' WHERE p.post_type='post' ORDER BY p.ID");
foreach ($drafts as $d) echo "  #{$d->ID} {$d->post_status} /{$d->post_name}/\n";
echo "  count: " . count($drafts) . "\n";
echo "\n=== DONE ===\n";
