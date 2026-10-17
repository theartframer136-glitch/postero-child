<?php
/**
 * The DIY Floating Frames category: a heading, intro, buying guide and FAQ
 * worded the way people search for these frames.
 *
 * Search Console, 18 Sep – 15 Oct 2026: /product-category/art-accessories/
 * diy-floating-frames/ was shown 584 times, more than any other page on the
 * site, at an average position of 42.7, and was clicked 0 times. The searches
 * behind it are all one family: "floating frame" (66), "floating framed
 * canvas" (36), "floating frame for canvas" (29), "floating canvas frame"
 * (24), "floating frames" (21), "canvas floater frame" (20), "frames for
 * canvas" (18)... The page itself carried a one-line intro and four products
 * marked "Price on request", so Google had almost nothing to rank it on.
 *
 * What changes, on this category only:
 *   - the H1 reads "Floating Frames for Canvas" (af_archive_heading, see
 *     inc/page-headings.php) instead of "DIY Floating Frames";
 *   - after the category's own one-line description: a short intro, a
 *     collapsed "Floating frame guide & FAQ" so the product grid stays near
 *     the top, links to the related categories, and FAQPage structured data
 *     built from the same questions.
 *
 * The copy only states what the products and the shop already say: the four
 * kits (black, white, natural wood, gold), shadow-gap design for stretched
 * canvas, made to the customer's size, "Enquire" for a quote. No frame depth,
 * gap width or price is promised, because none is published.
 */
if (!defined('ABSPATH')) exit;

const AF_FLOAT_SLUG = 'diy-floating-frames';

function af_float_is_page() {
    if (!function_exists('is_product_category') || !is_product_category(AF_FLOAT_SLUG)) return false;
    return !is_paged();
}

/** [question, answer HTML]. Answers are plain sentences; links are added at print time. */
function af_float_faq() {
    $link = function ($slug, $text) {
        $t = get_term_by('slug', $slug, 'product_cat');
        if (!$t || is_wp_error($t)) return esc_html($text);
        $u = get_term_link($t);
        return is_wp_error($u) ? esc_html($text) : '<a href="' . esc_url($u) . '">' . esc_html($text) . '</a>';
    };
    $contact = '<a href="' . esc_url(home_url('/contact/')) . '">contact us</a>';
    return array(
        array('What is a floating frame for canvas?',
            'A floating frame, also called a floater frame, holds a stretched canvas from behind and leaves a narrow shadow gap between the edge of the canvas and the frame. The painting looks as if it floats inside the frame, and the whole canvas, edges included, stays on show.'),
        array('Which canvases does a floating frame fit?',
            'Floating frames are made for stretched, gallery-wrapped canvas. Measure the width, height and depth (thickness) of your canvas and send them with your enquiry so the frame is made to fit. If your canvas is not stretched yet, see our ' . $link('canvas-stretcher-bars', 'canvas stretcher bars') . '.'),
        array('Which frame color should I choose?',
            'Black suits modern and colorful art, white keeps light and minimal rooms airy, natural wood adds warmth, and gold is a favorite for devotional and traditional paintings such as Radha Krishna, Lakshmi Ganesh or Pichwai art.'),
        array('How do I order a floating frame in my size?',
            'Choose a frame below and press <strong>Enquire</strong>, or ' . $contact . ', with your canvas size and the color you want, and we will reply with a quote. Prefer art that arrives ready to hang? Browse our ' . $link('standard-framed-canvas', 'framed canvas prints') . ' and ' . $link('extra-large-canvas-paintings', 'extra large canvas paintings') . '.'),
        array('Do you deliver across the USA?',
            'Yes. Frames are made to order and delivered across the USA; the delivery cost for your size and address is shown before you pay.'),
    );
}

add_filter('af_archive_heading', function ($name, $term) {
    return ($term && isset($term->slug) && $term->slug === AF_FLOAT_SLUG) ? 'Floating Frames for Canvas' : $name;
}, 10, 2);

// After the H1 (5) and WooCommerce's own one-line description (10).
add_action('woocommerce_archive_description', function () {
    try {
        if (!af_float_is_page()) return;
        $faq = af_float_faq();
        $qa = '';
        foreach ($faq as $f) {
            $qa .= '<h3>' . esc_html($f[0]) . '</h3><p>' . wp_kses_post($f[1]) . '</p>';
        }
        $rel = '';
        foreach (array('canvas-stretcher-bars' => 'Canvas stretcher bars', 'rolled-canvas' => 'Rolled canvas',
                       'premium-aluminium-frames' => 'Aluminium frames', 'frame-sizes' => 'Frame sizes',
                       'standard-framed-canvas' => 'Framed canvas prints') as $slug => $label) {
            $t = get_term_by('slug', $slug, 'product_cat');
            if (!$t || is_wp_error($t)) continue;
            $u = get_term_link($t);
            if (!is_wp_error($u)) $rel .= '<li><a href="' . esc_url($u) . '">' . esc_html($label) . '</a></li>';
        }
        echo '<style id="af-float">.af-float{margin:6px 0 22px;max-width:900px}.af-float>p{margin:0 0 10px;line-height:1.6}'
           . '.af-float details{border:1px solid #e6e0d4;border-radius:10px;background:#faf8f3;padding:4px 16px;margin:12px 0}'
           . '.af-float summary{cursor:pointer;font-weight:700;padding:10px 0;min-height:44px;display:flex;align-items:center}'
           . '.af-float h3{font-size:16px;margin:14px 0 4px}.af-float details p{margin:0 0 8px;line-height:1.6}'
           . '.af-float a{color:#8a5a00;text-decoration:underline}'
           . '.af-float ul{list-style:none;display:flex;flex-wrap:wrap;gap:8px;padding:0;margin:8px 0 0}'
           . '.af-float li a{display:inline-block;padding:6px 12px;border:1px solid #d9c89a;border-radius:999px;text-decoration:none;color:#1a1a1a;background:#fff}</style>'
           . '<div class="af-float">'
           . '<p>A <strong>floating frame</strong> (or floater frame) holds a stretched canvas with a slim shadow gap around it, so the art seems to float inside the frame. Our DIY floating frame kits for canvas come in <strong>black, white, natural wood and gold</strong> and are made to your canvas size.</p>'
           . '<p>Pick a style below and press <strong>Enquire</strong> with your canvas width, height and depth for a quote.</p>'
           . '<details><summary>Floating frame guide &amp; FAQ</summary>' . $qa . '</details>'
           . ($rel !== '' ? '<ul aria-label="Related">' . $rel . '</ul>' : '')
           . '</div>';
    } catch (\Throwable $e) {
    }
}, 12);

add_action('wp_head', function () {
    try {
        if (!af_float_is_page()) return;
        $items = array();
        foreach (af_float_faq() as $f) {
            $items[] = array('@type' => 'Question', 'name' => $f[0],
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => wp_strip_all_tags($f[1])));
        }
        echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org',
            '@type' => 'FAQPage', 'mainEntity' => $items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    } catch (\Throwable $e) {
    }
}, 30);
