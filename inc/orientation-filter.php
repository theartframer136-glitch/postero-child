<?php
/**
 * Orientation filter — portrait / landscape / square  (requirements §6)
 *
 * Orientation is a fact about the artwork, not an attribute anyone should
 * have to key in: it is read from the featured image's own dimensions and
 * stored as product meta, refreshed whenever the image changes. The shop
 * toolbar gains an Orientation dropdown that filters on it.
 */
if (!defined('ABSPATH')) exit;

/** portrait / landscape / square, from the featured image's pixels. */
function af_orientation_from_image($product_id) {
    $thumb = get_post_thumbnail_id($product_id);
    if (!$thumb) return '';
    $meta = wp_get_attachment_metadata($thumb);
    if (empty($meta['width']) || empty($meta['height'])) return '';
    $w = (int) $meta['width']; $h = (int) $meta['height'];
    if (!$w || !$h) return '';
    if ($h > $w * 1.08) return 'portrait';
    if ($w > $h * 1.08) return 'landscape';
    return 'square';
}

function af_orientation_refresh($product_id) {
    if (get_post_type($product_id) !== 'product') return;
    $o = af_orientation_from_image($product_id);
    if ($o) update_post_meta($product_id, '_af_orientation', $o);
    else delete_post_meta($product_id, '_af_orientation');
}
add_action('save_post_product', 'af_orientation_refresh', 20);
add_action('updated_post_meta', function($meta_id, $post_id, $key) {
    if ($key === '_thumbnail_id') af_orientation_refresh($post_id);
}, 10, 3);

/** ?orientation=portrait|landscape|square on shop and category pages. */
function af_orientation_current() {
    $o = isset($_GET['orientation']) ? sanitize_key(wp_unslash($_GET['orientation'])) : '';
    return in_array($o, array('portrait', 'landscape', 'square'), true) ? $o : '';
}

add_action('pre_get_posts', function($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) return;
    $o = af_orientation_current();
    if (!$o) return;
    $mq   = (array) $q->get('meta_query');
    $mq[] = array('key' => '_af_orientation', 'value' => $o);
    $q->set('meta_query', $mq);
});
