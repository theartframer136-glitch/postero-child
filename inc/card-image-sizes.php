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
