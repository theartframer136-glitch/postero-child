<?php
/**
 * Category guides: a heading, intro, collapsed guide & FAQ, related links and
 * FAQPage data for the product categories Google shows most, each worded the
 * way people search for it. One config per category below.
 *
 * Search Console, 18 Sep – 15 Oct 2026 (category → impressions, position):
 *
 *   diy-floating-frames   584, 42.7, 0 clicks. "floating frame" (66),
 *                         "floating framed canvas" (36), "floating frame for
 *                         canvas" (29), "canvas floater frame" (20)...
 *                         Went live 17 Oct (theartframer136-glitch/
 *                         postero-child#411) as inc/floating-frames-page.php.
 *   radha-krishna         122, 12.1, one step off page 1. Over 90 days:
 *                         "radha krishna wall art" (20, pos 9.5), "radha
 *                         krishna wall decor" (15, 11.9), "krishna wall art"
 *                         (13, 12.4), "radha krishna art" (12, 18.5),
 *                         "radha krishna canvas" (9, 15.1), "radha krishna
 *                         print" (8, 7.9)...
 *   buddha                107, 23.9. Over 90 days: "buddha canvas" (50, pos
 *                         28.4), "buddha framed wall art" (12, 23.8), "buddha
 *                         wall art" (11, 34.3), "framed buddha prints" (7,
 *                         21.3), "buddha canvas painting" (6, 36.8)...
 *   pichwai-art           67, 21.7. Over 90 days: "pichwai art" (39, pos ~33),
 *                         "pichwai paintings" (5, 59.0), "pichwai frame" (4,
 *                         16.2), "pichwai prints" (2, 37.0), "pichwai
 *                         paintings on canvas" (2, 47.5)... Only 3 products.
 *   abstract-art          63, 34.2. Over 90 days: "abstract canvas art" (31,
 *                         pos 63.9), "abstract canvas print" (28, 31.9),
 *                         "abstract canvas prints" (23, 42.3), "digital
 *                         abstract wall art" (17, 28.2), "abstract wall art"
 *                         (8, 57.1)... 50 products.
 *
 * Per category: 'heading' replaces the H1 (af_archive_heading, see
 * inc/page-headings.php); 'title' replaces the Rank Math <title> (optional);
 * 'intro' are paragraphs; 'faq' are [question, answer]; 'related' are
 * category slug => label. In intro and answers, [[cat:slug|text]] becomes a
 * link to that category and [[contact|text]] a link to /contact/; a category
 * that does not exist leaves the plain text. Page 2 and later pages of a
 * category are left alone.
 *
 * The copy only states what the products and the shop already say. Nothing
 * about prices, frame depths or gap widths is promised.
 */
if (!defined('ABSPATH')) exit;

function af_cat_guides() {
    return array(
        'diy-floating-frames' => array(
            'heading' => 'Floating Frames for Canvas',
            'intro'   => array(
                'A <strong>floating frame</strong> (or floater frame) holds a stretched canvas with a slim shadow gap around it, so the art seems to float inside the frame. Our DIY floating frame kits for canvas come in <strong>black, white, natural wood and gold</strong> and are made to your canvas size.',
                'Pick a style below and press <strong>Enquire</strong> with your canvas width, height and depth for a quote.',
            ),
            'faq_title' => 'Floating frame guide &amp; FAQ',
            'faq' => array(
                array('What is a floating frame for canvas?',
                    'A floating frame, also called a floater frame, holds a stretched canvas from behind and leaves a narrow shadow gap between the edge of the canvas and the frame. The painting looks as if it floats inside the frame, and the whole canvas, edges included, stays on show.'),
                array('Which canvases does a floating frame fit?',
                    'Floating frames are made for stretched, gallery-wrapped canvas. Measure the width, height and depth (thickness) of your canvas and send them with your enquiry so the frame is made to fit. If your canvas is not stretched yet, see our [[cat:canvas-stretcher-bars|canvas stretcher bars]].'),
                array('Which frame color should I choose?',
                    'Black suits modern and colorful art, white keeps light and minimal rooms airy, natural wood adds warmth, and gold is a favorite for devotional and traditional paintings such as Radha Krishna, Lakshmi Ganesh or Pichwai art.'),
                array('How do I order a floating frame in my size?',
                    'Choose a frame below and press <strong>Enquire</strong>, or [[contact|contact us]], with your canvas size and the color you want, and we will reply with a quote. Prefer art that arrives ready to hang? Browse our [[cat:standard-framed-canvas|framed canvas prints]] and [[cat:extra-large-canvas-paintings|extra large canvas paintings]].'),
                array('Do you deliver across the USA?',
                    'Yes. Frames are made to order and delivered across the USA; the delivery cost for your size and address is shown before you pay.'),
            ),
            'related' => array('canvas-stretcher-bars' => 'Canvas stretcher bars', 'rolled-canvas' => 'Rolled canvas',
                               'premium-aluminium-frames' => 'Aluminium frames', 'frame-sizes' => 'Frame sizes',
                               'standard-framed-canvas' => 'Framed canvas prints'),
        ),
        'radha-krishna' => array(
            'heading' => 'Radha Krishna Wall Art & Canvas Prints',
            'title'   => 'Radha Krishna Wall Art & Canvas Prints | Wall Decor USA',
            'intro'   => array(
                'Bring the divine love of Radha and Krishna into your home with <strong>Radha Krishna wall art</strong> printed on canvas. Choose from moonlit, floral, mandala and modern line-art designs for the living room, bedroom or pooja room, and pick how it arrives: rolled canvas, on stretcher bars ready to hang, or finished in a frame.',
            ),
            'faq_title' => 'Radha Krishna wall decor guide &amp; FAQ',
            'faq' => array(
                array('Where should I hang a Radha Krishna painting?',
                    'Most families choose the living room, where guests see it first, the bedroom, as a symbol of love between partners, or the pooja room. Hang it at eye level on a wall with space around it, away from direct sunlight.'),
                array('Which size Radha Krishna wall art should I choose?',
                    'Most designs here come in 3×2 ft and 3×4 ft. A 3×4 ft canvas makes a focal point above a sofa or bed; 3×2 ft suits a pooja room, hallway or smaller wall. Open a design to see every size it comes in.'),
                array('Does it come framed and ready to hang?',
                    'You choose on each design: the painting only (rolled canvas in a tube), the painting on wooden stretcher bars with a DIY hanging kit, or stretched and finished in the frame you pick. A digital download is offered too. Already have a canvas? See our [[cat:diy-floating-frames|floating frames for canvas]].'),
                array('Is a Radha Krishna painting a good gift?',
                    'It is one of the most-loved gifts for weddings, anniversaries, housewarmings (Griha Pravesh) and Diwali, because it stands for love, devotion and harmony at home.'),
                array('Do you deliver across the USA?',
                    'Yes. Every canvas is made to order and delivered across the USA; the delivery cost for your size and address is shown before you pay.'),
            ),
            'related' => array('hindu-deities' => 'Hindu Gods', 'lakshmi-ganesha' => 'Lakshmi Ganesh', 'pichwai-art' => 'Pichwai art',
                               'lord-shiva' => 'Lord Shiva', 'seven-horses' => '7 Horses (Vastu)', 'diy-floating-frames' => 'Floating frames'),
        ),
        'buddha' => array(
            'heading' => 'Buddha Canvas Wall Art & Framed Prints',
            'title'   => 'Buddha Canvas Wall Art & Framed Buddha Prints | The Art Framer',
            'intro'   => array(
                'Create a calm, mindful space with <strong>Buddha canvas wall art</strong>. Choose from golden, lotus, zen, waterfall and cosmic designs, mostly in a large 3×4 ft size, and pick how it arrives: rolled canvas, on stretcher bars ready to hang, or as a <strong>framed Buddha print</strong>.',
            ),
            'faq_title' => 'Buddha wall art guide &amp; FAQ',
            'faq' => array(
                array('Where should I hang Buddha wall art?',
                    'Living rooms, bedrooms, meditation corners, yoga spaces and home offices all suit a Buddha painting. Many people hang it facing the entrance or in a quiet spot, at or above eye level, and keep it out of bathrooms and laundry areas as a mark of respect.'),
                array('Which size Buddha canvas should I choose?',
                    'Most designs here are 3×4 ft, a large statement piece for the wall above a sofa, bed or console. For a smaller wall or a meditation nook, open a design to see the other sizes it comes in.'),
                array('Can I buy a framed Buddha print?',
                    'Yes. On each design you choose how it arrives: the painting only (rolled canvas in a tube), the painting on wooden stretcher bars with a DIY hanging kit, or stretched and finished in the frame you pick. A digital download is offered too. For a canvas you already own, see our [[cat:diy-floating-frames|floating frames for canvas]].'),
                array('Which Buddha design suits my room?',
                    'Golden and lotus designs add warmth to living rooms and entryways, zen and soft teal pieces keep bedrooms and meditation rooms calm, and bold neon or cosmic Buddha art makes a modern statement in a studio or office.'),
                array('Do you deliver across the USA?',
                    'Yes. Every canvas is made to order and delivered across the USA; the delivery cost for your size and address is shown before you pay.'),
            ),
            'related' => array('hindu-deities' => 'Hindu Gods', 'radha-krishna' => 'Radha Krishna', 'landscapes' => 'Landscapes',
                               'abstract-art' => 'Abstract art', 'living-room' => 'Living room', 'diy-floating-frames' => 'Floating frames'),
        ),
        'pichwai-art' => array(
            'heading' => 'Pichwai Art & Paintings on Canvas',
            'title'   => 'Pichwai Art & Pichwai Paintings on Canvas | Shrinathji Prints',
            'intro'   => array(
                '<strong>Pichwai art</strong> is the devotional painting tradition of Nathdwara in Rajasthan, made to hang behind the idol of Shrinathji, the child form of Lord Krishna. Our Pichwai paintings are high-resolution prints on canvas of Shrinathji, lotus and Krishna designs, for the pooja room, entrance or living room.',
            ),
            'faq_title' => 'Pichwai art guide &amp; FAQ',
            'faq' => array(
                array('What is Pichwai art?',
                    'Pichwai, from the Hindi for "that which hangs at the back", is a centuries-old style of temple painting from Nathdwara, Rajasthan. Pichwai paintings show Shrinathji with lotuses, cows, gopis and festival scenes, in rich colors and fine detail, and were hung behind the deity in the temple.'),
                array('Are these Pichwai paintings hand-painted?',
                    'No. They are printed reproductions of Pichwai artwork on canvas, which keeps the detail and color of the style at a price that suits a home. Every print is made to order.'),
                array('Where should I hang a Pichwai painting?',
                    'Behind or beside the home mandir, as in the temple tradition, is the classic place. A large Pichwai also makes a striking focal wall in the living room or at the entrance.'),
                array('Which size and framing can I choose?',
                    'The designs here come in 3×4 ft and 3×2 ft; open a design to see every size. You choose how it arrives: the painting only (rolled canvas in a tube), on wooden stretcher bars with a DIY hanging kit, or stretched and finished in the frame you pick. For a Pichwai you already own, see our [[cat:diy-floating-frames|floating frames for canvas]].'),
                array('Do you deliver across the USA?',
                    'Yes. Every canvas is made to order and delivered across the USA; the delivery cost for your size and address is shown before you pay.'),
            ),
            'related' => array('radha-krishna' => 'Radha Krishna', 'hindu-deities' => 'Hindu Gods', 'indian-culture' => 'Indian Culture',
                               'tirupati-balaji' => 'Tirupati Balaji', 'lakshmi-ganesha' => 'Lakshmi Ganesh', 'diy-floating-frames' => 'Floating frames'),
        ),
        'abstract-art' => array(
            'heading' => 'Abstract Canvas Art & Abstract Wall Art Prints',
            'title'   => 'Abstract Canvas Art & Abstract Canvas Prints | Modern Wall Art',
            'intro'   => array(
                'Bold color, shape and texture for modern walls: our <strong>abstract canvas prints</strong> range from geometric and cubist designs to pop-art portraits and abstract takes on Ganesha, Radha Krishna and the Seven Horses. Order a canvas, ready to hang or framed, or, where offered, a <strong>digital abstract wall art</strong> file to print yourself.',
            ),
            'faq_title' => 'Abstract wall art guide &amp; FAQ',
            'faq' => array(
                array('What size abstract canvas should I choose?',
                    'A good rule is art about two-thirds the width of the furniture below it. Most designs here are 3×4 ft, a statement size above a sofa, bed or desk; open a design to see every size it comes in.'),
                array('Can I buy abstract wall art as a digital download?',
                    'Yes, where a design offers it: choose <strong>Digital download</strong> to receive the high-resolution file and print it at home or at any print shop. Nothing is posted, so there is no delivery charge. More files are in our [[cat:printable-art|printable art]].'),
                array('Does the canvas come framed?',
                    'You choose on each design: the painting only (rolled canvas in a tube), on wooden stretcher bars with a DIY hanging kit, or stretched and finished in the frame you pick. For a canvas you already own, see our [[cat:diy-floating-frames|floating frames for canvas]].'),
                array('Which abstract style suits my room?',
                    'Geometric and teal or blue pieces suit modern living rooms and offices, warm amber and gold abstracts add energy to dining rooms and entryways, and pop-art portraits make a fun statement in a studio, game room or teen bedroom.'),
                array('Do you deliver across the USA?',
                    'Yes. Every canvas is made to order and delivered across the USA; the delivery cost for your size and address is shown before you pay.'),
            ),
            'related' => array('living-room' => 'Living room', 'landscapes' => 'Landscapes', 'wildlife' => 'Wildlife',
                               'printable-art' => 'Printable art', 'extra-large-canvas-paintings' => 'Extra large canvas', 'diy-floating-frames' => 'Floating frames'),
        ),
    );
}

/** The config of the category being viewed, page 1 only, or null. */
function af_cat_guide_current() {
    if (!function_exists('is_product_category') || !is_product_category() || is_paged()) return null;
    $t = get_queried_object();
    $all = af_cat_guides();
    return ($t && isset($t->slug) && isset($all[$t->slug])) ? $all[$t->slug] : null;
}

function af_cat_guide_term_url($slug) {
    $t = get_term_by('slug', $slug, 'product_cat');
    if (!$t || is_wp_error($t)) return '';
    $u = get_term_link($t);
    return is_wp_error($u) ? '' : $u;
}

/** [[cat:slug|text]] and [[contact|text]] → links; $plain drops the markup instead. */
function af_cat_guide_text($s, $plain = false) {
    $s = preg_replace_callback('/\[\[(cat:([a-z0-9-]+)|contact)\|([^\]]+)\]\]/', function ($m) use ($plain) {
        if ($plain) return $m[3];
        $u = $m[1] === 'contact' ? home_url('/contact/') : af_cat_guide_term_url($m[2]);
        return $u === '' ? esc_html($m[3]) : '<a href="' . esc_url($u) . '">' . esc_html($m[3]) . '</a>';
    }, $s);
    return $plain ? html_entity_decode(wp_strip_all_tags($s), ENT_QUOTES, 'UTF-8') : $s;
}

add_filter('af_archive_heading', function ($name, $term) {
    $all = af_cat_guides();
    return ($term && isset($term->slug) && !empty($all[$term->slug]['heading'])) ? $all[$term->slug]['heading'] : $name;
}, 10, 2);

add_filter('rank_math/frontend/title', function ($title) {
    try {
        $g = af_cat_guide_current();
        return !empty($g['title']) ? $g['title'] : $title;
    } catch (\Throwable $e) {
        return $title;
    }
}, 20);

// After the H1 (5) and WooCommerce's own one-line description (10).
add_action('woocommerce_archive_description', function () {
    try {
        $g = af_cat_guide_current();
        if (!$g) return;
        $intro = '';
        foreach ($g['intro'] as $p) $intro .= '<p>' . wp_kses_post(af_cat_guide_text($p)) . '</p>';
        $qa = '';
        foreach ($g['faq'] as $f) $qa .= '<h3>' . esc_html($f[0]) . '</h3><p>' . wp_kses_post(af_cat_guide_text($f[1])) . '</p>';
        $rel = '';
        foreach ($g['related'] as $slug => $label) {
            $u = af_cat_guide_term_url($slug);
            if ($u !== '') $rel .= '<li><a href="' . esc_url($u) . '">' . esc_html($label) . '</a></li>';
        }
        echo '<style id="af-cat-guide">.af-cat-guide{margin:6px 0 22px;max-width:900px}.af-cat-guide>p{margin:0 0 10px;line-height:1.6}'
           . '.af-cat-guide details{border:1px solid #e6e0d4;border-radius:10px;background:#faf8f3;padding:4px 16px;margin:12px 0}'
           . '.af-cat-guide summary{cursor:pointer;font-weight:700;padding:10px 0;min-height:44px;display:flex;align-items:center}'
           . '.af-cat-guide h3{font-size:16px;margin:14px 0 4px}.af-cat-guide details p{margin:0 0 8px;line-height:1.6}'
           . '.af-cat-guide a{color:#8a5a00;text-decoration:underline}'
           . '.af-cat-guide ul{list-style:none;display:flex;flex-wrap:wrap;gap:8px;padding:0;margin:8px 0 0}'
           . '.af-cat-guide li a{display:inline-block;padding:6px 12px;border:1px solid #d9c89a;border-radius:999px;text-decoration:none;color:#1a1a1a;background:#fff}</style>'
           . '<div class="af-cat-guide">' . $intro
           . '<details><summary>' . $g['faq_title'] . '</summary>' . $qa . '</details>'
           . ($rel !== '' ? '<ul aria-label="Related">' . $rel . '</ul>' : '')
           . '</div>';
    } catch (\Throwable $e) {
    }
}, 12);

add_action('wp_head', function () {
    try {
        $g = af_cat_guide_current();
        if (!$g) return;
        $items = array();
        foreach ($g['faq'] as $f) {
            $items[] = array('@type' => 'Question', 'name' => $f[0],
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => af_cat_guide_text($f[1], true)));
        }
        echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org',
            '@type' => 'FAQPage', 'mainEntity' => $items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    } catch (\Throwable $e) {
    }
}, 30);
