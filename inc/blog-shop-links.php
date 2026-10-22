<?php
/**
 * "Shop the look": a short paragraph of in-text links from the two blog posts
 * that Google already shows, to the product categories they talk about.
 *
 * Search Console, 17 Sep – 14 Oct 2026:
 *
 *     /top-wall-decor-trends-in-2026/              544 impressions, position 9.1
 *     /how-to-maintain-and-clean-canvas-wall-art/  530 impressions, position 9.1
 *     /product-category/.../radha-krishna/          126 impressions, position 12.3
 *     /product-category/.../buddha/                 108 impressions, position 23.9
 *
 * The posts reach page 1 but their bodies link to no product at all; the only
 * shop links on them are the menu and the "Shop by Deity & Vastu" row
 * (inc/deity-links.php). A sentence with descriptive anchor text tells Google
 * what each category is about and gives a reader a next step.
 *
 * Appended to the post's main content at priority 19, so it sits above the
 * deity row (20). Categories that do not exist are left out, and a post
 * whose links all resolve to nothing gets no section.
 */
if (!defined('ABSPATH')) exit;

/** post slug => heading, intro, and items of [text before, category slug, anchor, text after]. */
function af_blog_shop_links() {
    return array(
        'top-wall-decor-trends-in-2026' => array(
            'heading' => 'Shop the 2026 trends',
            'intro'   => 'Every trend above is available as canvas wall art, printed and framed to order:',
            'items'   => array(
                array('Spiritual statement walls: ', 'radha-krishna', 'Radha Krishna canvas art', ' for the living room.'),
                array('Welcoming entryways: ', 'lakshmi-ganesha', 'Lakshmi Ganesh wall art', ' for the entrance or prayer room.'),
                array('Calm, minimal spaces: ', 'buddha', 'Buddha canvas prints', ' in soft, neutral tones.'),
                array('Bold colour and texture: ', 'abstract-art', 'abstract canvas art', ' for modern interiors.'),
                array('Vastu-inspired decor: ', 'seven-horses', 'Seven Horses paintings', ' — a popular Vastu choice.'),
                array('Oversized focal pieces: ', 'extra-large-canvas-paintings', 'extra large canvas paintings', ' up to 3×5 ft.'),
            ),
        ),
        'how-to-maintain-and-clean-canvas-wall-art' => array(
            'heading' => 'Find your next canvas',
            'intro'   => 'Looking for a piece worth caring for? These collections are printed on canvas and framed to order:',
            'items'   => array(
                array('', 'radha-krishna', 'Radha Krishna canvas paintings', ' for living rooms and prayer spaces.'),
                array('', 'buddha', 'Buddha wall art', ' for peaceful bedrooms and meditation corners.'),
                array('', 'lakshmi-ganesha', 'Lakshmi Ganesh canvas prints', ' for the home temple or entryway.'),
                array('', 'landscapes', 'Landscape canvas prints', ' for living rooms and offices.'),
                array('Frames that finish the look: ', 'premium-aluminium-frames', 'premium aluminium floating frames', '.'),
            ),
        ),
    );
}

function af_blog_shop_links_html($slug) {
    $all = af_blog_shop_links();
    if (empty($all[$slug]) || !taxonomy_exists('product_cat')) return '';
    $cfg = $all[$slug];
    $lis = '';
    foreach ($cfg['items'] as $it) {
        list($before, $term_slug, $anchor, $after) = $it;
        $term = get_term_by('slug', $term_slug, 'product_cat');
        if (!$term || is_wp_error($term)) continue;
        $url = get_term_link($term);
        if (is_wp_error($url)) continue;
        $lis .= '<li>' . esc_html($before) . '<a href="' . esc_url($url) . '">' . esc_html($anchor) . '</a>' . esc_html($after) . '</li>';
    }
    if ($lis === '') return '';
    return '<style id="af-blog-shop">.af-blog-shop{margin:32px 0 8px;padding:20px 22px;border:1px solid #e6e0d4;border-radius:10px;background:#faf8f3;clear:both}'
         . '.af-blog-shop h2{margin:0 0 8px;font-size:22px}.af-blog-shop p{margin:0 0 10px}'
         . '.af-blog-shop ul{margin:0;padding-left:20px}.af-blog-shop li{margin:6px 0}'
         . '.af-blog-shop a{color:#8a5a00;text-decoration:underline;font-weight:600}</style>'
         . '<section class="af-blog-shop" aria-labelledby="af-blog-shop-h">'
         . '<h2 id="af-blog-shop-h">' . esc_html($cfg['heading']) . '</h2>'
         . '<p>' . esc_html($cfg['intro']) . '</p><ul>' . $lis . '</ul></section>';
}

// Same guards as inc/deity-links.php: only the main content of the post being
// viewed, only after <head>, never inside an excerpt or feed.
add_filter('the_content', function ($content) {
    try {
        static $done = false;
        if ($done || !is_singular('post') || is_feed()) return $content;
        if (!did_action('wp_head') || doing_action('wp_head')) return $content;
        if (doing_filter('get_the_excerpt')) return $content;
        if (!in_the_loop() || !is_main_query()) return $content;
        if ((int) get_the_ID() !== (int) get_queried_object_id()) return $content;
        $html = af_blog_shop_links_html((string) get_post_field('post_name', get_the_ID()));
        if ($html === '') return $content;
        $done = true;
        return $content . $html;
    } catch (\Throwable $e) {
        return $content;
    }
}, 19);
