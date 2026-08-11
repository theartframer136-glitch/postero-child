<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Replace the free-shipping claims that live in the DATABASE, which is where
 * most of them are: Elementor designs (the homepage badge titles are Elementor
 * widgets, not theme code, which is why editing the theme never changed them),
 * page content, and product descriptions.
 *
 * Only exact known phrases are replaced — never a blind search-and-replace on
 * the owner's prose. Every post touched keeps a one-time backup of its original
 * content and Elementor data in postmeta, so the whole operation reverses.
 * Idempotent: a second run reports 0 changed.
 *
 * Run: wp eval-file tools/rewrite-free-shipping.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$copy  = function_exists('af_shipping_copy') ? af_shipping_copy()
       : array('label'=>'Shipping Throughout the USA','short'=>'Shipping cost shown at checkout',
               'blurb'=>'Delivered anywhere in the USA — shipping cost is shown at checkout before you pay.','cost'=>'');
$label = $copy['label'];
$short = $copy['short'];
$blurb = $copy['blurb'];

// left = what the site says today, right = what it should say. Ordered longest
// first so a broad phrase never eats a specific one.
$map = array(
    // product Elementor blocks and descriptions
    'Get free shipping over $65.' => $short . '.',
    'Get free shipping over $65'  => $short,
    // the homepage badge strip (Elementor widgets)
    'Fast, safe, and reliable delivery with on-time assurance.' => $blurb,
    'Fast, safe, and reliable delivery with on-time assurance'  => $blurb,
    // policy and about pages
    'Free across major serviceable US locations — including Delaware, Pennsylvania, Maryland, New Jersey and nearby areas.'
        => 'We ship to every state in the USA. ' . $blurb,
    'free delivery in Delaware, Pennsylvania, Maryland, New Jersey and nearby areas'
        => 'shipping throughout the United States',
    'Free delivery: Delaware, Pennsylvania, Maryland, New Jersey &amp; nearby areas.'
        => ucfirst($short) . '.',
    'Free delivery: Delaware, Pennsylvania, Maryland, New Jersey & nearby areas.'
        => ucfirst($short) . '.',
    'Free shipping options across our delivery areas' => 'Shipping throughout the USA',
    'Free USA Shipping' => $label,
    'Free Delivery'     => 'Shipping Throughout the USA',
    'Free Shipping'     => $label,
    'free shipping'     => 'shipping throughout the USA',
    'free delivery'     => 'shipping throughout the USA',
);

function af_rw_apply($text, $map, &$hits) {
    $before = $text;
    foreach ($map as $from => $to) {
        if ($from === '' || strpos($text, $from) === false) continue;
        $n = substr_count($text, $from);
        $text = str_replace($from, $to, $text);
        $hits += $n;
    }
    return $text === $before ? null : $text;   // null = nothing to do
}

echo "=== REWRITE FREE-SHIPPING CLAIMS (DB CONTENT) ===\n";
printf("  new label : %s\n  new line  : %s\n\n", $label, $short);

$changed_posts = 0; $changed_meta = 0; $total_hits = 0;

// ── 1. post content (pages, products) ──
$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_status IN ('publish','draft','private')
        AND post_type NOT IN ('revision','attachment')
        AND (LOWER(post_content) LIKE '%free shipping%'
          OR LOWER(post_content) LIKE '%free delivery%'
          OR LOWER(post_content) LIKE '%free usa shipping%'
          OR LOWER(post_excerpt) LIKE '%free shipping%'
          OR LOWER(post_excerpt) LIKE '%free delivery%')
      LIMIT 200");
foreach ($ids as $pid) {
    $post = get_post($pid);
    if (!$post) continue;
    $hits = 0;
    $new_content = af_rw_apply($post->post_content, $map, $hits);
    $new_excerpt = af_rw_apply($post->post_excerpt, $map, $hits);
    if ($new_content === null && $new_excerpt === null) continue;
    if (!get_post_meta($pid, '_af_shipping_copy_backup', true)) {
        update_post_meta($pid, '_af_shipping_copy_backup', wp_slash(wp_json_encode(array(
            'content' => $post->post_content, 'excerpt' => $post->post_excerpt, 'when' => gmdate('c'),
        ))));
    }
    $update = array('ID' => $pid);
    if ($new_content !== null) $update['post_content'] = $new_content;
    if ($new_excerpt !== null) $update['post_excerpt'] = $new_excerpt;
    // wp_update_post expects slashed data
    $update = wp_slash($update);
    $res = wp_update_post($update, true);
    if (is_wp_error($res)) { echo "  FAILED #{$pid}: " . $res->get_error_message() . "\n"; continue; }
    $changed_posts++; $total_hits += $hits;
    printf("  content  #%-6d %-9s %s (%d)\n", $pid, $post->post_type,
           mb_strimwidth(get_the_title($pid), 0, 46, '…'), $hits);
}

// ── 2. Elementor designs in postmeta ──
// _elementor_data is JSON stored as a slashed string; replacing inside it is
// safe because every phrase here is plain display text, not markup or a key.
$rows = $wpdb->get_results(
    "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta}
      WHERE meta_key = '_elementor_data'
        AND (LOWER(meta_value) LIKE '%free shipping%'
          OR LOWER(meta_value) LIKE '%free delivery%'
          OR LOWER(meta_value) LIKE '%free usa shipping%')
      LIMIT 300");
foreach ($rows as $row) {
    $hits = 0;
    // inside JSON the em dash and quotes are escaped; feed the map through the
    // same encoder so replacements match what is actually stored
    $jmap = array();
    foreach ($map as $from => $to) {
        $jf = trim(wp_json_encode($from), '"');
        $jt = trim(wp_json_encode($to), '"');
        $jmap[$jf] = $jt;
    }
    $new = af_rw_apply($row->meta_value, $jmap, $hits);
    if ($new === null) continue;
    if (!get_post_meta($row->post_id, '_af_elementor_backup', true)) {
        update_post_meta($row->post_id, '_af_elementor_backup', wp_slash($row->meta_value));
    }
    $ok = $wpdb->update($wpdb->postmeta, array('meta_value' => $new), array('meta_id' => $row->meta_id));
    if ($ok === false) { echo "  FAILED meta #{$row->meta_id}\n"; continue; }
    $changed_meta++; $total_hits += $hits;
    printf("  elementor #%-6d %s (%d)\n", $row->post_id,
           mb_strimwidth(get_the_title($row->post_id), 0, 46, '…'), $hits);
}

echo "\n  posts changed:      {$changed_posts}\n";
echo "  elementor changed:  {$changed_meta}\n";
echo "  phrases replaced:   {$total_hits}\n";

if ($changed_posts || $changed_meta) {
    // Elementor caches rendered CSS/HTML per post; clear it or the old words
    // keep showing from cache
    if (class_exists('\\Elementor\\Plugin')) {
        try { \Elementor\Plugin::$instance->files_manager->clear_cache(); echo "  elementor cache cleared\n"; }
        catch (\Throwable $e) { echo "  (elementor cache clear skipped: " . $e->getMessage() . ")\n"; }
    }
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    if (function_exists('af_mk_flush_feeds')) af_mk_flush_feeds();
    echo "  product transients and feeds flushed\n";
}
echo "=== DONE ===\n";
