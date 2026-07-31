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

/** Print what the banner is actually made of, so a miss explains itself. */
function af_ab_inventory($el, $depth = 0) {
    $pad  = str_repeat('  ', $depth);
    $type = isset($el['widgetType']) ? $el['widgetType'] : (isset($el['elType']) ? $el['elType'] : '?');
    $tell = array();
    if (!empty($el['settings']) && is_array($el['settings'])) {
        foreach ($el['settings'] as $k => $v) {
            if (is_string($v) && stripos($v, 'Discover') !== false) $tell[] = $k . '~text';
            if ($k === 'link' || substr($k, -5) === '_link') $tell[] = $k . '=' . (is_array($v) ? (isset($v['url']) && $v['url'] !== '' ? $v['url'] : 'EMPTY') : 'scalar');
            if (in_array($k, array('image', 'background_image'), true) && is_array($v) && !empty($v['url'])) $tell[] = $k;
        }
    }
    echo "  {$pad}- {$type}" . ($tell ? '  [' . implode(', ', array_slice($tell, 0, 5)) . ']' : '') . "\n";
    if (!empty($el['elements']) && is_array($el['elements'])) {
        foreach ($el['elements'] as $child) af_ab_inventory($child, $depth + 1);
    }
}

/**
 * The banner turned out to be nested flex containers with the whole design as
 * one background image — and the inner container already linked to /shop/,
 * the wrong destination. Exactly one container may carry the link (nested
 * anchors are invalid HTML and the inner one wins anyway), so: the container
 * that owns the background image gets the Direct from Artists link, and every
 * other container link inside the banner is cleared. Widgets keep the earlier
 * behaviour. Idempotent.
 */
function af_ab_link(&$el, $dest, &$stats) {
    $set = array('url' => $dest, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '');
    $w   = isset($el['widgetType']) ? $el['widgetType'] : '';

    if ($w === 'button') {
        $cur = isset($el['settings']['link']['url']) ? $el['settings']['link']['url'] : '';
        if ($cur === '' || $cur === '#') { $el['settings']['link'] = $set; $stats['button']++; }
    } elseif ($w === 'image') {
        $to  = isset($el['settings']['link_to']) ? $el['settings']['link_to'] : 'none';
        $cur = isset($el['settings']['link']['url']) ? $el['settings']['link']['url'] : '';
        if ($to !== 'custom' || $cur === '' || $cur === '#') {
            $el['settings']['link_to'] = 'custom';
            $el['settings']['link']    = $set;
            $stats['image']++;
        }
    } elseif ($w !== '' && !empty($el['settings']) && is_array($el['settings'])) {
        foreach ($el['settings'] as $k => $v) {
            if ($k !== 'link' && substr($k, -5) !== '_link') continue;
            if (is_array($v) && (!isset($v['url']) || $v['url'] === '' || $v['url'] === '#')) {
                $el['settings'][$k] = $set; $stats['other']++;
            }
        }
    } elseif ($w === '' && isset($el['elType']) && in_array($el['elType'], array('container', 'section', 'column'), true)) {
        $has_bg = !empty($el['settings']['background_image']['url']);
        $cur    = isset($el['settings']['link']['url']) ? $el['settings']['link']['url'] : '';
        if ($has_bg && empty($stats['bg_done'])) {
            if ($cur !== $dest) { $el['settings']['link'] = $set; $stats['bg_set']++; }
            else $stats['bg_kept']++;
            $stats['bg_done'] = 1;
        } elseif ($cur !== '') {
            // any other linked container inside the banner would nest anchors
            $el['settings']['link'] = array('url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '');
            $stats['cleared']++;
        }
    }
    if (!empty($el['elements']) && is_array($el['elements'])) {
        foreach ($el['elements'] as &$child) af_ab_link($child, $dest, $stats);
        unset($child);
    }
}

$stats = array('button' => 0, 'image' => 0, 'other' => 0, 'bg_set' => 0, 'bg_kept' => 0, 'cleared' => 0);
$found = false;
foreach ($tree as &$top) {
    if (!af_ab_contains($top, 'Discover Original Works')) continue;
    $found = true;
    echo "banner structure:\n";
    af_ab_inventory($top);
    af_ab_link($top, $dest, $stats);
}
unset($top);

if (!$found) { echo "Banner section not found in Elementor data — nothing changed.\n"; return; }
if ($stats['button'] + $stats['image'] + $stats['other'] + $stats['bg_set'] + $stats['cleared'] > 0) {
    update_post_meta($front, '_elementor_data', wp_slash(wp_json_encode($tree)));
    if (class_exists('\Elementor\Plugin')) {
        try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (\Throwable $t) {}
    }
    if (function_exists('do_action')) do_action('litespeed_purge_all');
}
printf("banner found — bg container linked: %d (already correct: %d), stray container links cleared: %d, buttons: %d, images: %d, other: %d\n",
    $stats['bg_set'], $stats['bg_kept'], $stats['cleared'], $stats['button'], $stats['image'], $stats['other']);
echo "=== DONE ===\n";
