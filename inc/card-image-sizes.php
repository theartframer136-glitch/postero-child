<?php
/**
 * Product card pictures: tell the browser how wide a card really is.
 *
 * Every card image already carries a srcset (169, 577, 768, 800… px wide),
 * but WordPress pairs it with its default sizes="(max-width: 800px) 100vw,
 * 800px", which says "this picture is drawn 800px wide". So the browser takes
 * the 800px file on every screen. Measured 23 Oct 2026 (tools/diag-element.js
 * on a category page, tools/audit-perf.mjs):
 *
 *     390px phone    card 172px wide (2 per row)
 *     1920px desktop card 333px wide
 *     the 800px card files weigh 270-400 KB each; a category page is 2.1 MB,
 *     most of it these pictures
 *
 * With the real width the browser picks the 577px file for a desktop card or
 * a 3x phone, and the 169px one where that is enough: the same sharpness on
 * screen for about half the bytes. Only woocommerce_thumbnail (the card size,
 * functions.php af_card_image_width keeps it at 800 for 2x screens) is touched; the
 * product page's own picture keeps WordPress's sizes.
 */
if (!defined('ABSPATH')) exit;

function af_card_image_sizes_attr() {
    // Cards are 2 per row up to tablet width, then 3-4, never wider than ~340px.
    return (string) apply_filters('af_card_image_sizes', '(max-width: 767px) 48vw, (max-width: 1199px) 31vw, 340px');
}

add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    if (is_admin() || $size !== 'woocommerce_thumbnail' || empty($attr['srcset'])) return $attr;
    $attr['sizes'] = af_card_image_sizes_attr();
    return $attr;
}, 20, 3);

/*
 * A 400px copy of the card picture, same shape as the card's 800px one.
 *
 * Between the 200-300px copies WordPress makes and the 577-800px ones there
 * was nothing, so a card drawn 170-200px wide on a 2x phone, or 340px on a
 * plain desktop, had to take the 577px+ file — about twice the pixels it can
 * show. With a 400px copy in the srcset the browser takes that instead, and
 * still never less than the card times the screen's density: the browser only
 * picks a smaller file when it covers the space. Made by the deploy's
 * "Rebuild product image sizes" step (tools/regenerate-image-sizes.php), which
 * only adds the missing copy to pictures it has already rebuilt; new uploads
 * get it on upload. Nothing is re-encoded from an existing copy — each is
 * resized from the original at the theme's quality.
 */
add_action('init', function () {
    $w = (int) apply_filters('af_card_small_width', 400);
    $t = function_exists('wc_get_image_size') ? wc_get_image_size('woocommerce_thumbnail') : array();
    $crop = !empty($t['crop']) && !empty($t['width']) && !empty($t['height']);
    add_image_size('af_card_400', $w, $crop ? (int) round($t['height'] * $w / $t['width']) : 0, $crop);
}, 20);
