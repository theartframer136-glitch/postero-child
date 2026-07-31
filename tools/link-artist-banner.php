<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The homepage "Discover Original Works of Artists" banner had no link at
 * all — not on the collage, not on the Discover Now button. Write real
 * hrefs into the Elementor data: the button and every image inside that
 * banner section now point at the Direct from Artists category.
 * Idempotent — re-running changes nothing once the links are set.
 * Run: wp eval-file tools/link-artist-banner.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$term = get_term_by('slug', 'direct-from-artists', 'product_cat');
$dest = $term ? get_term_link($term) : home_url('/product-category/direct-from-artists/');
if (is_wp_error($dest)) $dest = home_url('/product-category/direct-from-artists/');
echo "target: {$dest}\n";

$front = (int) get_option('page_on_front');
if (!$front) { echo "No static front page — skipped.\n"; return; }
$data = get_post_meta($front, '_elementor_data', true);
if (!is_string($data) || $data === '') { echo "No Elementor data on page {$front} — skipped.\n"; return; }
$tree = json_decode($data, true);
if (!is_array($tree)) { echo "Elementor data did not decode — skipped.\n"; return; }

/** Does this element's subtree contain the banner heading? */
function af_ab_contains($el, $needle) {
    return stripos(wp_json_encode($el), $needle) !== false;
}

/** Walk a subtree, linking buttons and images. */
function af_ab_link(&$el, $dest, &$stats) {
    if (isset($el['widgetType'])) {
        if ($el['widgetType'] === 'button') {
            $cur = isset($el['settings']['link']['url']) ? $el['settings']['link']['url'] : '';
            if ($cur === '' || $cur === '#') {
                $el['settings']['link'] = array('url' => $dest, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '');
                $stats['button']++;
            } else { $stats['button_kept']++; }
        }
        if ($el['widgetType'] === 'image') {
            $to  = isset($el['settings']['link_to']) ? $el['settings']['link_to'] : 'none';
            $cur = isset($el['settings']['link']['url']) ? $el['settings']['link']['url'] : '';
            if ($to !== 'custom' || $cur === '' || $cur === '#') {
                $el['settings']['link_to'] = 'custom';
                $el['settings']['link'] = array('url' => $dest, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '');
                $stats['image']++;
            } else { $stats['image_kept']++; }
        }
    }
    if (!empty($el['elements']) && is_array($el['elements'])) {
        foreach ($el['elements'] as &$child) af_ab_link($child, $dest, $stats);
        unset($child);
    }
}

$stats = array('button' => 0, 'image' => 0, 'button_kept' => 0, 'image_kept' => 0);
$found = false;
foreach ($tree as &$top) {
    if (!af_ab_contains($top, 'Discover Original Works')) continue;
    $found = true;
    af_ab_link($top, $dest, $stats);
}
unset($top);

if (!$found) { echo "Banner section not found in Elementor data — nothing changed.\n"; return; }
if ($stats['button'] + $stats['image'] > 0) {
    update_post_meta($front, '_elementor_data', wp_slash(wp_json_encode($tree)));
    if (class_exists('\Elementor\Plugin')) {
        try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (\Throwable $t) {}
    }
    if (function_exists('do_action')) do_action('litespeed_purge_all');
}
printf("banner found — buttons linked: %d (already linked: %d), images linked: %d (already linked: %d)\n",
    $stats['button'], $stats['button_kept'], $stats['image'], $stats['image_kept']);
echo "=== DONE ===\n";
