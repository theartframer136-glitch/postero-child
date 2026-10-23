<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Where the picture the 2026-10-23 crawl found missing is stored
 * (uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25.webp, shown on
 * two product pages but in no post content), whether a file of that name
 * exists anywhere under uploads, and whether the store-directory plugin
 * behind /all-artists/ and /store-listing/ is active.
 * Makes no changes, prints no secrets.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
$needle = 'wall-art-care-202604031610';
$J = function ($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); };
$like = '%' . $wpdb->esc_like($needle) . '%';

echo "=== posts (any field, any type, any status) ===\n";
foreach ($wpdb->get_results($wpdb->prepare("SELECT ID, post_type, post_status, post_name, post_parent,
    (post_content LIKE %s) c, (post_excerpt LIKE %s) e, (guid LIKE %s) g FROM {$wpdb->posts}
    WHERE post_content LIKE %s OR post_excerpt LIKE %s OR guid LIKE %s LIMIT 30", $like, $like, $like, $like, $like, $like)) as $r)
    printf("  #%d %s %s %s parent=%d in: %s%s%s\n", $r->ID, $r->post_type, $r->post_status, $r->post_name, $r->post_parent, $r->c ? 'content ' : '', $r->e ? 'excerpt ' : '', $r->g ? 'guid' : '');

echo "\n=== postmeta ===\n";
foreach ($wpdb->get_results($wpdb->prepare("SELECT post_id, meta_key, LENGTH(meta_value) len FROM {$wpdb->postmeta} WHERE meta_value LIKE %s LIMIT 40", $like)) as $r) {
    $p = get_post($r->post_id);
    printf("  post #%d (%s %s) key=%s len=%d\n", $r->post_id, $p ? $p->post_type : '?', $p ? $p->post_name : '?', $r->meta_key, $r->len);
    $v = (string) get_post_meta($r->post_id, $r->meta_key, true);
    if (is_string($v) && ($i = strpos($v, $needle)) !== false) echo '     …' . $J(substr($v, max(0, $i - 220), 300)) . "…\n";
}

echo "\n=== termmeta / options ===\n";
foreach ($wpdb->get_results($wpdb->prepare("SELECT term_id, meta_key FROM {$wpdb->termmeta} WHERE meta_value LIKE %s LIMIT 20", $like)) as $r) echo "  term #{$r->term_id} key={$r->meta_key}\n";
foreach ($wpdb->get_results($wpdb->prepare("SELECT option_name, LENGTH(option_value) len FROM {$wpdb->options} WHERE option_value LIKE %s LIMIT 20", $like)) as $r) echo "  option {$r->option_name} len={$r->len}\n";

echo "\n=== files under uploads with that name ===\n";
$up = wp_get_upload_dir();
foreach ((array) glob($up['basedir'] . '/*/*/wall-art-care*') as $f) printf("  %s %d bytes\n", str_replace($up['basedir'] . '/', '', $f), filesize($f));
foreach ((array) glob($up['basedir'] . '/*/*/*wall-art-care*') as $f) if (strpos(basename($f), 'wall-art-care') !== 0) printf("  %s %d bytes\n", str_replace($up['basedir'] . '/', '', $f), filesize($f));

echo "\n=== the two product pages ===\n";
foreach (array('modern-floral-abstract-vase-wall-art', 'sacred-golden-temple-amritsar-canvas-wall-art') as $slug) {
    $p = get_page_by_path($slug, OBJECT, 'product');
    if (!$p) { echo "  $slug: not found\n"; continue; }
    $tabs = get_post_meta($p->ID, '_product_tabs', true);
    printf("  #%d %s status=%s content_len=%d excerpt_len=%d meta_keys=%s\n", $p->ID, $slug, $p->post_status, strlen($p->post_content), strlen($p->post_excerpt),
        $J(array_values(array_filter(array_keys(get_post_meta($p->ID)), function ($k) { return $k[0] !== '_' || preg_match('/tab|desc|content|care/i', $k); }))));
}

echo "\n=== store directory plugin ===\n";
$active = (array) get_option('active_plugins', array());
foreach ($active as $a) if (preg_match('/dokan|wcfm|wc-vendors|multivendor/i', $a)) echo "  active: $a\n";
echo '  shortcode dokan-stores registered: ' . (shortcode_exists('dokan-stores') ? 'yes' : 'no') . "\n";
echo '  shortcode dokan-my-orders registered: ' . (shortcode_exists('dokan-my-orders') ? 'yes' : 'no') . "\n";
echo "\nDONE\n";
