<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * SEO improvements (run on deploy via: wp eval-file tools/seo-improvements.php)
 *
 *  1. Physical robots.txt (the web server 404s /robots.txt before WP,
 *     so the virtual robots_txt filter in PHASE 24 never gets asked).
 *  2. Front page + Shop page Rank Math title/description (current live
 *     values are "Home - The ART FRAMER" and a broken "×" description).
 *  3. Meta descriptions for key trust pages (only where empty).
 *  4. Product-category Rank Math titles/descriptions + archive intro
 *     text (only where empty — never overwrites hand-written copy).
 *  5. Backfill missing image alt text from parent product / filename.
 *
 * Idempotent: safe to run on every deploy.
 */

if (!defined('ABSPATH')) exit;

$log = function ($m) { echo "[seo] $m\n"; };

// ── 1. Physical robots.txt ──────────────────────────────────────────
$robots_path = ABSPATH . 'robots.txt';
$robots = function_exists('af_seo_robots_txt') ? af_seo_robots_txt() : '';
if ($robots !== '') {
    $existing = file_exists($robots_path) ? (string) file_get_contents($robots_path) : '';
    // Ours (or absent) => rewrite. This also strips the old
    // "Disallow: /items/" tourniquet from the pre-cleanup version.
    if ($existing === '' || strpos($existing, '# theartframer-seo') !== false) {
        if ($existing !== $robots) {
            file_put_contents($robots_path, $robots);
            $log('robots.txt written');
        } else {
            $log('robots.txt already current');
        }
    } else {
        $log('robots.txt exists with foreign content — NOT overwriting, review manually');
    }
} else {
    $log('af_seo_robots_txt() missing (theme not active?) — robots.txt skipped');
}

// ── helpers ─────────────────────────────────────────────────────────
// Sets post meta when the current value is empty or $replace_if says
// the current value is broken/generic.
$set_meta = function ($post_id, $key, $value, $replace_if = null) {
    $cur = (string) get_post_meta($post_id, $key, true);
    $should = ($cur === '') || (is_callable($replace_if) && $replace_if($cur));
    if ($should && $cur !== $value) {
        update_post_meta($post_id, $key, $value);
        return true;
    }
    return false;
};

// ── 2. Front page + Shop page ───────────────────────────────────────
$front_id = (int) get_option('page_on_front');
if ($front_id) {
    $t = 'Custom Canvas Prints, Wall Art & Picture Framing | The Art Framer';
    $d = 'Museum-quality canvas prints, personalized wall art and handcrafted picture frames — made to order and shipped fast across the USA.';
    $generic_title = function ($cur) { return stripos(trim($cur), 'home') === 0; };
    $broken_desc   = function ($cur) {
        $c = trim(html_entity_decode($cur, ENT_QUOTES | ENT_HTML5));
        return $c === '' || mb_strlen($c) < 20;
    };
    if ($set_meta($front_id, 'rank_math_title', $t, $generic_title))      $log('front page: title set');
    if ($set_meta($front_id, 'rank_math_description', $d, $broken_desc)) $log('front page: description set');
} else {
    $log('no static front page found — homepage meta skipped');
}

$shop_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
if ($shop_id > 0) {
    $t = 'Shop Hindu God Paintings, Vastu Art & Canvas Prints | The Art Framer';
    $d = 'Browse every canvas: Radha Krishna, Ganesha, Shiva, Balaji, 7 horses Vastu paintings, Buddha, abstract and more. Rolled, stretched or framed, shipped across the USA.';
    // Replace an empty, generic, or this-script's-own earlier value; a
    // hand-written title stays.
    $ours_t = array('Shop Canvas Prints, Wall Art & Picture Frames | The Art Framer');
    $ours_d = array(
        'Browse the full Art Framer collection — canvas prints, personalized portraits, frames and studio accessories. Made to order, delivered anywhere in the USA.',
        'Browse every canvas: Radha Krishna, Ganesha, Shiva, Balaji, 7 horses Vastu paintings, Buddha, abstract and more. Floating frames, ready to hang, shipped across the USA.',
    );
    $generic_title = function ($cur) use ($ours_t) { return in_array($cur, $ours_t, true) || (stripos(trim($cur), 'shop') === 0 && mb_strlen(trim($cur)) < 30); };
    $generic_desc  = function ($cur) use ($ours_d) { return in_array($cur, $ours_d, true); };
    if ($set_meta($shop_id, 'rank_math_title', $t, $generic_title))      $log('shop page: title set');
    if ($set_meta($shop_id, 'rank_math_description', $d, $generic_desc)) $log('shop page: description set');
}

// The blog hub (/blog/, inc/blog-hub.php) has a page of its own with no meta.
$blog = get_page_by_path('blog');
if ($blog) {
    if ($set_meta($blog->ID, 'rank_math_title', 'Vastu & Hindu Wall Art Guides for US Homes | The Art Framer Blog')) $log('blog page: title set');
    if ($set_meta($blog->ID, 'rank_math_description', 'Where to hang a 7 horses painting, how to set up a pooja room wall, which deity suits which room: guides for Indian families decorating homes in the USA.')) $log('blog page: description set');
}

// ── 3. Key pages / posts — description only, never overwrite ────────
$page_descs = array(
    'about'                         => 'Meet The Art Framer — a US canvas printing and framing studio crafting museum-quality wall art, made to order and delivered across the USA.',
    'contact'                       => 'Questions about a print, frame or order? Contact The Art Framer by phone, WhatsApp or email — fast, friendly US-based support.',
    'faqs'                          => 'Answers to common questions about ordering canvas prints, framing options, shipping times, returns and digital downloads at The Art Framer.',
    'shipping-delivery'             => 'Shipping and delivery information for The Art Framer — production times, US delivery estimates, order tracking and packaging details.',
    'track-your-order'              => 'Track your Art Framer order — enter your order number to see live production and delivery status.',
    'top-wall-decor-trends-in-2026' => "Discover 2026's top wall decor trends — statement canvas art, warm minimalism, gallery walls and more, with tips to style each look at home.",
);
foreach ($page_descs as $slug => $desc) {
    $p = get_page_by_path($slug, OBJECT, array('page', 'post'));
    if (!$p) continue;
    if ($set_meta($p->ID, 'rank_math_description', $desc)) $log("/$slug: description set");
}

// ── 4. Product categories ───────────────────────────────────────────
// t = Rank Math title, d = meta description, i = archive intro text
// (term description, shown on the category page — indexable copy).
$cat_map = array(
    'all-art-prints' => array(
        't' => 'All Art Prints – Canvas Wall Art Collection | The Art Framer',
        'd' => 'Explore every art print in one place — spiritual, abstract, nature and more, printed on premium canvas, rolled, stretched or framed, shipped across the USA.',
        'i' => 'Every print in the studio, one collection — printed to order on archival canvas, rolled, stretched or framed, shipped anywhere in the USA.',
    ),
    'gallery-prints' => array(
        't' => 'Gallery-Quality Art Prints | The Art Framer',
        'd' => 'Gallery-quality prints with rich color and archival inks. Custom sizes and frames, made to order and delivered across the USA.',
        'i' => 'Gallery-quality prints with rich, true-to-life color — finished in the size and frame your wall calls for.',
    ),
    'digital-canvas-prints' => array(
        't' => 'Indian Canvas Wall Art & Spiritual Paintings | The Art Framer',
        'd' => 'Explore Hindu deity, Vastu and Indian art canvas prints: Krishna, Ganesha, Balaji, Buddha, Pichwai and more. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Digital canvas prints across spiritual, abstract and nature themes — archival inks, multiple sizes, rolled, stretched or framed.',
    ),
    'buddha' => array(
        't' => 'Buddha Canvas Wall Art & Paintings for Living Room',
        'd' => 'Calm Buddha wall art for living rooms, bedrooms and meditation spaces. Large canvas prints, rolled, stretched or framed, shipped across the USA.',
        'i' => 'Bring calm home with Buddha canvas art — serene, gallery-quality prints for meditation corners, yoga spaces and living rooms.',
    ),
    'abstract-art' => array(
        't' => 'Abstract Canvas Wall Art & Modern Prints | The Art Framer',
        'd' => 'Bold abstract canvas prints for modern homes and offices. Museum-quality inks, custom sizes and frames, made to order in the USA.',
        'i' => 'Statement abstracts for modern walls — color, texture and movement, printed on archival canvas in the size your space needs.',
    ),
    'personalised-prints' => array(
        't' => 'Personalized Prints & Custom Wall Art | The Art Framer',
        'd' => 'Personalized wall art made from your photos and ideas — canvas prints, portraits and custom pieces crafted to order and delivered across the USA.',
        'i' => 'Wall art that could only be yours — made from your photos and ideas, crafted to order.',
    ),
    'portraits' => array(
        't' => 'Custom Portrait Prints from Your Photos | The Art Framer',
        'd' => 'Turn your photos into custom portrait wall art. Premium canvas, multiple sizes and frames — a personal gift shipped across the USA.',
        'i' => 'Your photos, elevated — custom portraits printed on premium canvas and finished with handcrafted frames.',
    ),
    'art-accessories' => array(
        't' => 'Art Accessories – Frames, Stretcher Bars & More | The Art Framer',
        'd' => 'Frames, canvas stretcher bars, floating frames and studio accessories — everything to finish and hang your art, shipped across the USA.',
        'i' => 'Everything that finishes a piece — frames, stretcher bars and studio accessories, built to order.',
    ),
    'wooden-frames' => array(
        't' => 'Wooden Picture Frames – Custom Sizes | The Art Framer',
        'd' => 'Handcrafted wooden picture frames in custom sizes and classic finishes. Built to order for canvas and prints, shipped across the USA.',
        'i' => 'Handcrafted wooden frames in classic finishes — built to your size, made for canvas and prints.',
    ),
    'premium-aluminium-frames' => array(
        // "Aluminum" is the US spelling searchers actually type; the
        // category name/URL keeps the existing "Aluminium".
        't' => 'Premium Aluminum Picture Frames | The Art Framer',
        'd' => 'Sleek premium aluminum picture frames for a clean, modern gallery look. Custom sizes, durable finishes, USA delivery.',
        'i' => 'Sleek aluminum frames for a clean, modern gallery look — light, durable and cut to your size.',
    ),
    'diy-floating-frames' => array(
        't' => 'Canvas Floating Frames | Floater Frame Kits for Canvas',
        'd' => 'DIY floating frames for stretched canvas in black, silver, gold and rose gold, from $24. Easy to assemble, five sizes, shipped across the USA.',
        'i' => 'Give stretched canvas a premium gallery finish — easy-assembly floating frame kits in custom sizes.',
    ),
    'frame-colors' => array(
        't' => 'Frame Colors – Black, Gold, Silver & More | The Art Framer',
        'd' => 'Pick the perfect frame color — black, gold, rose gold, silver and more, matched to your art and your room.',
        'i' => 'Black, gold, rose gold, silver and more — find the frame color that ties the room together.',
    ),

    // ── USA-market pass (Oct 2026): the devotional and Vastu categories ──
    // The shop's real audience is the Indian community in the USA. Titles
    // lead with the term a shopper types ("7 horses painting", "lord shiva
    // painting"), descriptions say where the piece hangs and that it ships
    // across the USA, and intros are short indexable copy above the grid.
    // Nothing here says free shipping. Seven of these categories are also
    // in tools/apply-seo-titles-2026-10.php (owner-approved copy, runs after
    // this script on deploy); their title and description here are that
    // script's exact text, so the two never disagree. The intro is this
    // script's alone.
    'hindu-deities' => array(
        't' => 'Hindu God Paintings for Home & Pooja Room | Canvas Art',
        'd' => 'Canvas paintings of Ganesha, Lakshmi, Shiva, Durga, Ram and Krishna for your home mandir and walls. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Hindu god paintings for Indian homes in the USA: Ganesha, Lakshmi, Shiva, Durga, Hanuman, Ram and more on museum-grade canvas for the living room or pooja room, rolled, on stretcher bars or in a floating frame.',
    ),
    'radha-krishna' => array(
        't' => 'Radha Krishna Painting for Home | Canvas Wall Art USA',
        'd' => 'Shop Radha Krishna canvas paintings for your living room, bedroom or pooja room: rolled, stretched or framed, shipped across the USA. A loved Diwali gift.',
        'i' => 'Radha Krishna paintings on canvas for the living room, bedroom and pooja room: divine love in rich color, printed to order and available rolled, on stretcher bars or in a floating frame, shipped anywhere in the USA. A favorite wedding, anniversary and Diwali gift.',
    ),
    'lakshmi-ganesha' => array(
        't' => 'Lakshmi Ganesh Painting for Diwali | Canvas Wall Art',
        'd' => 'Welcome prosperity this Diwali with Lakshmi Ganesh canvas art for your pooja room or entrance. Rolled, stretched or framed, shipped across the USA. Order early.',
        'i' => 'Lakshmi Ganesh paintings for prosperity and new beginnings: the Diwali and Griha Pravesh classic for the pooja room or entrance, on museum-grade canvas, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'seven-horses' => array(
        't' => '7 Horses Painting as per Vastu for Living Room & Office',
        'd' => 'Seven running horses paintings as per Vastu for the living room and office: success, speed and prosperity on canvas, rolled, stretched or framed, shipped across the USA.',
        'i' => '7 running horses paintings as per Vastu Shastra: hang one on the south or east wall of the living room or office, horses running into the room, for success and forward momentum. Museum-grade canvas, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'vaastu-art' => array(
        't' => 'Vastu Paintings for Living Room & Office | Canvas Art',
        'd' => 'Vastu paintings for the home and office: seven horses, sacred symbols and prosperity art on canvas. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Vastu paintings chosen for direction and meaning: seven running horses, sacred symbols and prosperity art for the living room, office and entrance, on museum-grade canvas shipped across the USA.',
    ),
    'lord-shiva' => array(
        't' => 'Lord Shiva Painting for Home | Mahadev Canvas Wall Art',
        'd' => 'Lord Shiva and Mahadev canvas paintings for the living room, meditation space and pooja room. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Lord Shiva paintings on canvas: meditating Mahadev, Nataraja and Shiva family for the living room, meditation corner or pooja room. Rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'tirupati-balaji' => array(
        't' => 'Tirupati Balaji Painting for Home | Venkateswara Wall Art',
        'd' => 'Tirupati Balaji and Lord Venkateswara canvas paintings for the pooja room and living room. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Tirupati Balaji paintings on canvas for the pooja room and living room: Lord Venkateswara in temple gold, rolled, stretched or in a floating frame, shipped anywhere in the USA.',
    ),
    'lord-rama' => array(
        't' => 'Lord Ram Painting for Home & Pooja Room | Canvas Wall Art',
        'd' => 'Shri Ram and Ram Darbar canvas paintings for the pooja room and living room. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Lord Ram paintings on canvas, including Ram Darbar, for the pooja room, entrance and living room. Museum-grade canvas, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'buddha' => array(
        't' => 'Buddha Canvas Wall Art & Paintings for Living Room',
        'd' => 'Serene Buddha canvas paintings for living rooms, meditation rooms and yoga spaces. Museum-grade canvas, rolled, stretched or framed, shipped across the USA.',
        'i' => 'Buddha paintings on canvas for a calm living room, meditation corner or yoga space, printed to order and available rolled, on stretcher bars or in a floating frame, shipped anywhere in the USA.',
    ),
    'sikh-art' => array(
        't' => 'Sikh Art & Guru Nanak Paintings | Canvas Wall Art USA',
        'd' => 'Sikh canvas art for the home: Guru Nanak Dev Ji, the Golden Temple and Ik Onkar. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Sikh art on canvas: Guru Nanak Dev Ji, Harmandir Sahib and sacred symbols for the living room or prayer room, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'swaminarayan' => array(
        't' => 'Swaminarayan Painting for Home | Canvas Wall Art USA',
        'd' => 'Swaminarayan canvas paintings for the pooja room and living room. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Swaminarayan paintings on museum-grade canvas for the home mandir and living room, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'pichwai-art' => array(
        't' => 'Pichwai Painting Canvas Prints | Shrinathji Wall Art',
        'd' => 'Traditional Pichwai art of Shrinathji, cows and lotus on premium canvas. Bring Indian heritage to your home. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Pichwai paintings of Shrinathji, cows and lotus ponds reproduced on museum-grade canvas for the living room or pooja room, shipped across the USA.',
    ),
    'indian-culture' => array(
        't' => 'Indian Culture Wall Art | Classical Dance & Heritage Canvas',
        'd' => 'Indian culture on canvas: classical dancers, Tanjore and folk art for the living room and entrance. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Indian heritage on canvas: Bharatanatyam and Kathak dancers, Tanjore-style art and folk motifs for the living room, dining room or entrance. Rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'christian-art' => array(
        't' => 'Christian Wall Art & Religious Canvas Paintings | USA',
        'd' => 'Christian canvas paintings of Jesus, Mary and the saints for the living room and prayer corner. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Christian art on canvas for the home: Jesus, the Madonna and the saints, printed to order and available rolled, on stretcher bars or in a floating frame, shipped across the USA.',
    ),
    'wildlife' => array(
        't' => 'Wildlife Canvas Wall Art – Horses, Tigers, Peacocks | USA',
        'd' => 'Wildlife canvas art for the living room and office: horses, tigers, elephants, peacocks and birds. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Wildlife on canvas: horses, tigers, elephants and peacocks for the living room, office or kids room. Museum-grade canvas, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'landscapes' => array(
        't' => 'Landscape Canvas Wall Art for Living Room | The Art Framer',
        'd' => 'Landscape canvas paintings: mountains, lakes, forests and Indian scenery for the living room and bedroom. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Landscape art on canvas: mountains, lakes, forests and Indian scenery for the living room or bedroom, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'still-life' => array(
        't' => 'Still Life Canvas Wall Art – Florals & Vases | The Art Framer',
        'd' => 'Still life canvas art: florals, vases and botanicals for the dining room, kitchen and living room. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Still life on canvas: florals, vases and botanicals for the dining room, kitchen or living room, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'living-room' => array(
        't' => 'Living Room Wall Art – Large Canvas Paintings | USA',
        'd' => 'Large canvas wall art for the living room: statement pieces in 3×4 ft and bigger. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Living room wall art sized for the sofa wall: statement canvases from 2×3 ft to 3×5 ft, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'kids-room' => array(
        't' => 'Kids Room Wall Art – Canvas Paintings for Children | USA',
        'd' => 'Kids room canvas art: animals, fairy tales, space and playful scenes. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Kids room wall art on canvas: animals, fairy tales and playful scenes, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'travel-art' => array(
        't' => 'Travel Canvas Wall Art – Cities & Journeys | The Art Framer',
        'd' => 'Travel-themed canvas art of cities, coasts and journeys for the living room and office. Rolled, stretched or framed, shipped across the USA.',
        'i' => 'Travel art on canvas: cities, coasts and journeys for the living room or office, rolled, stretched or in a floating frame, shipped across the USA.',
    ),
    'deals-discounts' => array(
        't' => 'Canvas Wall Art Deals & Discounts | The Art Framer',
        'd' => 'Current deals on canvas wall art: Hindu god paintings, Vastu art, abstract and more at reduced prices. Rolled, stretched or framed, shipped across the USA.',
        'i' => '',
    ),
    'corporate-printing' => array(
        't' => 'Corporate Printing – Banners, Signage & Certificates | USA',
        'd' => 'Corporate printing for US businesses: banners, backdrops, stands, signage and certificates, made to order and shipped across the USA.',
        'i' => '',
    ),
    'home-decor-by-space' => array(
        't' => 'Wall Art by Room – Living Room, Kids Room, Café | USA',
        'd' => 'Canvas wall art chosen by room: living room, kids room and café decor. Rolled, stretched or framed, shipped across the USA.',
        'i' => '',
    ),
    'gifts' => array(
        't' => 'Wall Art Gifts – Diwali, Housewarming & Wedding | USA',
        'd' => 'Canvas wall art gifts for Diwali, Griha Pravesh and weddings: deity paintings, Vastu art and personalized canvases, shipped across the USA.',
        'i' => '',
    ),
);

$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
$n_title = $n_desc = $n_intro = 0;
// A value this script's own template wrote on an earlier run. It may be
// replaced by hand-written copy from $cat_map; anything else is the owner's.
$is_template = function ($cur, $term) {
    $name = $term->name;
    return $cur === sprintf('%s – Shop Online | The Art Framer', $name)
        || $cur === sprintf('Shop the %s collection at The Art Framer — premium quality, made to order and shipped fast across the USA.', $name)
        || $cur === sprintf('%s – Shop Online | The Art Framer', html_entity_decode($name, ENT_QUOTES | ENT_HTML5))
        || $cur === sprintf('Shop the %s collection at The Art Framer — premium quality, made to order and shipped fast across the USA.', html_entity_decode($name, ENT_QUOTES | ENT_HTML5));
};
// Earlier hand-written values of this script, superseded by the USA pass.
$superseded = array(
    'Hindu God Paintings for Home & Pooja Room | Canvas Art',
    'Radha Krishna Painting for Home | Canvas Wall Art USA',
    'Lakshmi Ganesh Painting for Diwali | Canvas Wall Art',
    'Buddha Canvas Wall Art & Spiritual Prints | The Art Framer',
    'Buddha Canvas Wall Art & Paintings for Living Room',
    'Pichwai Painting Canvas Prints | Shrinathji Wall Art',
    'Serene Buddha canvas wall art for meditation rooms, yoga studios and calm living spaces. Premium archival prints, multiple sizes — USA shipping.',
    'Bring calm home with Buddha canvas art — serene, gallery-quality prints for meditation corners, yoga spaces and living rooms.',
    // 22 Oct 2026: these said every piece comes "finished with a floating frame"
    // and "ready to hang". A buyer can choose the painting only (shipped rolled),
    // on stretcher bars with a DIY kit, or framed (inc/kit-choices.php), so the
    // copy above now names all three. The old wording is listed so the next run
    // replaces it.
    'Hindu god paintings for Indian homes in the USA: Ganesha, Lakshmi, Shiva, Durga, Hanuman, Ram and more on museum-grade canvas with a floating frame, ready to hang in the living room or pooja room.',
    'Radha Krishna paintings on canvas for the living room, bedroom and pooja room: divine love in rich color, finished with a floating frame and shipped ready to hang anywhere in the USA. A favorite wedding, anniversary and Diwali gift.',
    'Lakshmi Ganesh paintings for prosperity and new beginnings: the Diwali and Griha Pravesh classic for the pooja room or entrance, on museum-grade canvas with a floating frame, shipped across the USA.',
    'Seven running horses paintings as per Vastu for the living room and office: success, speed and prosperity on floating-frame canvas, shipped across the USA.',
    '7 running horses paintings as per Vastu Shastra: hang one on the south or east wall of the living room or office, horses running into the room, for success and forward momentum. Museum-grade canvas, floating frame, shipped across the USA.',
    'Vastu paintings for the home and office: seven horses, sacred symbols and prosperity art on canvas. Floating frames, ready to hang, shipped across the USA.',
    'Lord Shiva and Mahadev canvas paintings for the living room, meditation space and pooja room. Floating frames, ready to hang, shipped across the USA.',
    'Lord Shiva paintings on canvas: meditating Mahadev, Nataraja and Shiva family for the living room, meditation corner or pooja room. Floating frame, ready to hang, shipped across the USA.',
    'Tirupati Balaji and Lord Venkateswara canvas paintings for the pooja room and living room. Floating frames, ready to hang, shipped across the USA.',
    'Tirupati Balaji paintings on canvas for the pooja room and living room: Lord Venkateswara in temple gold, with a floating frame, shipped ready to hang anywhere in the USA.',
    'Shri Ram and Ram Darbar canvas paintings for the pooja room and living room. Floating frames, ready to hang, shipped across the USA.',
    'Lord Ram paintings on canvas, including Ram Darbar, for the pooja room, entrance and living room. Museum-grade canvas, floating frame, shipped across the USA.',
    'Serene Buddha canvas paintings for living rooms, meditation rooms and yoga spaces. Museum-grade canvas, floating frames, ready to hang, shipped across the USA.',
    'Buddha paintings on canvas for a calm living room, meditation corner or yoga space, finished with a floating frame and shipped ready to hang anywhere in the USA.',
    'Sikh canvas art for the home: Guru Nanak Dev Ji, the Golden Temple and Ik Onkar. Floating frames, ready to hang, shipped across the USA.',
    'Sikh art on canvas: Guru Nanak Dev Ji, Harmandir Sahib and sacred symbols for the living room or prayer room, with a floating frame, shipped across the USA.',
    'Swaminarayan canvas paintings for the pooja room and living room. Floating frames, ready to hang, shipped across the USA.',
    'Swaminarayan paintings on museum-grade canvas for the home mandir and living room, with a floating frame, shipped across the USA.',
    'Indian culture on canvas: classical dancers, Tanjore and folk art for the living room and entrance. Floating frames, ready to hang, shipped across the USA.',
    'Indian heritage on canvas: Bharatanatyam and Kathak dancers, Tanjore-style art and folk motifs for the living room, dining room or entrance. Floating frame, shipped across the USA.',
    'Christian canvas paintings of Jesus, Mary and the saints for the living room and prayer corner. Floating frames, ready to hang, shipped across the USA.',
    'Christian art on canvas for the home: Jesus, the Madonna and the saints, finished with a floating frame and shipped ready to hang across the USA.',
    'Wildlife canvas art for the living room and office: horses, tigers, elephants, peacocks and birds. Floating frames, ready to hang, shipped across the USA.',
    'Wildlife on canvas: horses, tigers, elephants and peacocks for the living room, office or kids room. Museum-grade canvas, floating frame, shipped across the USA.',
    'Landscape canvas paintings: mountains, lakes, forests and Indian scenery for the living room and bedroom. Floating frames, ready to hang, shipped across the USA.',
    'Landscape art on canvas: mountains, lakes, forests and Indian scenery for the living room or bedroom, with a floating frame, shipped across the USA.',
    'Still life canvas art: florals, vases and botanicals for the dining room, kitchen and living room. Floating frames, ready to hang, shipped across the USA.',
    'Still life on canvas: florals, vases and botanicals for the dining room, kitchen or living room, with a floating frame, shipped across the USA.',
    'Large canvas wall art for the living room: statement pieces in 3×4 ft and bigger. Floating frames, ready to hang, shipped across the USA.',
    'Living room wall art sized for the sofa wall: statement canvases from 2×3 ft to 3×5 ft with a floating frame, shipped ready to hang across the USA.',
    'Kids room canvas art: animals, fairy tales, space and playful scenes. Floating frames, ready to hang, shipped across the USA.',
    'Kids room wall art on canvas: animals, fairy tales and playful scenes with a floating frame, shipped ready to hang across the USA.',
    'Travel-themed canvas art of cities, coasts and journeys for the living room and office. Floating frames, ready to hang, shipped across the USA.',
    'Travel art on canvas: cities, coasts and journeys for the living room or office, with a floating frame, shipped across the USA.',
    'Current deals on canvas wall art: Hindu god paintings, Vastu art, abstract and more at reduced prices. Floating frames, ready to hang, shipped across the USA.',
    'Canvas wall art chosen by room: living room, kids room and café decor. Floating frames, ready to hang, shipped across the USA.',
    'Explore every art print in one place — spiritual, abstract, nature and more, printed on premium canvas and shipped ready to hang across the USA.',
    'Every print in the studio, one collection — printed to order on archival canvas and shipped ready to hang anywhere in the USA.',
    'Digital canvas prints across spiritual, abstract and nature themes — archival inks, multiple sizes, ready to hang.',
    // 22 Oct 2026: floating frames now have prices (inc/aluminium-frame-pricing.php).
    'DIY floating frames for stretched canvas in black, gold, silver and natural wood. Easy to assemble, many sizes, shipped across the USA.',
);
if (!is_wp_error($terms)) {
    foreach ($terms as $term) {
        $m = isset($cat_map[$term->slug]) ? $cat_map[$term->slug] : null;
        $t = $m ? $m['t'] : sprintf('%s – Shop Online | The Art Framer', $term->name);
        $d = $m ? $m['d'] : sprintf('Shop the %s collection at The Art Framer — premium quality, made to order and shipped fast across the USA.', $term->name);

        $cur_t = (string) get_term_meta($term->term_id, 'rank_math_title', true);
        if ($cur_t !== $t && ($cur_t === '' || ($m && ($is_template($cur_t, $term) || in_array($cur_t, $superseded, true))))) {
            update_term_meta($term->term_id, 'rank_math_title', $t);
            $n_title++;
        }
        $cur_d = (string) get_term_meta($term->term_id, 'rank_math_description', true);
        if ($cur_d !== $d && ($cur_d === '' || ($m && ($is_template($cur_d, $term) || in_array($cur_d, $superseded, true))))) {
            update_term_meta($term->term_id, 'rank_math_description', $d);
            $n_desc++;
        }
        $cur_i = trim(wp_strip_all_tags((string) $term->description));
        if ($m && $m['i'] !== '' && ($cur_i === '' || in_array($cur_i, $superseded, true))) {
            wp_update_term($term->term_id, 'product_cat', array('description' => $m['i']));
            $n_intro++;
        }
    }
    $log("categories: $n_title titles, $n_desc descriptions, $n_intro intros set");
}

// ── 4b. Rank Math settings the category pages need ──────────────────
// Measured 14 Oct (tools/diag-seo-inventory.php): product categories are
// OUT of the sitemap (tax_product_cat_sitemap off) while 97 exist and the
// deity ones are the pages that should rank; and empty categories (frame
// sizes, colors, placeholders) are indexable thin pages. Categories go
// into the sitemap and empty ones become noindex; both are Rank Math's
// own switches, flipped once and left alone if the owner changes them.
if (get_option('af_seo_rm_settings_rev') !== '1') {
    update_option('af_seo_rm_settings_rev', '1', true);
    $sm = get_option('rank-math-options-sitemap', array());
    if (is_array($sm) && (!isset($sm['tax_product_cat_sitemap']) || $sm['tax_product_cat_sitemap'] !== 'on')) {
        $sm['tax_product_cat_sitemap'] = 'on';
        update_option('rank-math-options-sitemap', $sm);
        $log('rank math: product categories added to the sitemap');
    }
    $tt = get_option('rank-math-options-titles', array());
    $changed = false;
    if (is_array($tt)) {
        if (!isset($tt['noindex_empty_taxonomies']) || $tt['noindex_empty_taxonomies'] !== 'on') { $tt['noindex_empty_taxonomies'] = 'on'; $changed = true; }
        if (isset($tt['knowledgegraph_name']) && $tt['knowledgegraph_name'] === 'The ART FRAMER') { $tt['knowledgegraph_name'] = 'The Art Framer'; $changed = true; }
        if ($changed) { update_option('rank-math-options-titles', $tt); $log('rank math: empty categories noindexed, organization name cased'); }
    }
    if (is_callable(array('RankMath\\Sitemap\\Cache', 'invalidate_storage'))) { \RankMath\Sitemap\Cache::invalidate_storage(); }
}

// ── 5. Backfill missing image alt text ──────────────────────────────
$q = new WP_Query(array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'image',
    'posts_per_page' => 3000,
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'meta_query'     => array(
        'relation' => 'OR',
        array('key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'),
        array('key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '='),
    ),
));
$n_alt = 0;
foreach ($q->posts as $aid) {
    $p = get_post($aid);
    if (!$p) continue;
    $alt = $p->post_parent ? (string) get_the_title($p->post_parent) : '';
    if ($alt === '') {
        // humanize filename-ish titles: "buddha-lotus-canvas_36x60" → words
        $alt = trim(preg_replace('/[-_]+/', ' ', (string) $p->post_title));
    }
    if ($alt !== '') {
        update_post_meta($aid, '_wp_attachment_image_alt', wp_strip_all_tags($alt));
        $n_alt++;
    }
}
$log("alt text backfilled for $n_alt images");

// ── 5b. Accessory descriptions that call the product a canvas print ──
// The product importer opened every description with "This <name> canvas
// wall art is a premium gallery-wrapped canvas print, handcrafted to order in
// our Delaware studio." On a frame, stretcher bar or tool that sentence is
// wrong, and on the floating frame pages Google reads it first. Only that
// generated opening sentence is removed; the hand-written text after it stays.
$acc = get_term_by('slug', 'art-accessories', 'product_cat');
if ($acc && function_exists('wc_get_products')) {
    $n_acc = 0;
    // The product name inside it is wrapped in <strong> on the live pages, so
    // inline tags are allowed; a paragraph left empty is dropped with it.
    $bad = '/^\s*(<p>)?\s*This (?:[^<]|<\/?(?:strong|b|em)>){0,500}? is a premium gallery-wrapped canvas print, handcrafted to order in our Delaware studio\.\s*(<\/p>)?\s*/u';
    foreach (wc_get_products(array('category' => array('art-accessories'), 'limit' => 500, 'return' => 'ids', 'status' => array('publish', 'private', 'draft'))) as $pid) {
        $c = (string) get_post_field('post_content', $pid);
        $new = preg_replace_callback($bad, function ($m) {
            return (!empty($m[1]) && empty($m[2])) ? '<p>' : '';
        }, $c, 1);
        if (is_string($new) && $new !== $c && trim(wp_strip_all_tags($new)) !== '') {
            wp_update_post(array('ID' => $pid, 'post_content' => $new));
            $n_acc++;
        }
    }
    $log("accessory descriptions: canvas-print opening removed from $n_acc");
}

// ── 6. Social share image (og:image) ────────────────────────────────
// The current og:image is a scanned business card. Build a proper
// 1200x630 JPG from the live hero banner (the store's own branded
// artwork) and set it for the front page + as the sitewide default.
$uploads = wp_get_upload_dir();
$og_path = trailingslashit($uploads['basedir']) . 'og-home-1200x630.jpg';
$og_url  = trailingslashit($uploads['baseurl']) . 'og-home-1200x630.jpg';
if (!file_exists($og_path)) {
    $src = trailingslashit($uploads['basedir']) . '2026/04/Banner-1-1.webp';
    if (file_exists($src)) {
        $editor = wp_get_image_editor($src);
        if (!is_wp_error($editor)) {
            $editor->resize(1200, 630, true); // center hard-crop
            $saved = $editor->save($og_path, 'image/jpeg');
            if (!is_wp_error($saved)) $log('og:image built from hero banner');
            else $log('og:image save failed: ' . $saved->get_error_message());
        } else {
            $log('og:image editor failed: ' . $editor->get_error_message());
        }
    } else {
        $log('og:image source banner not found — skipped');
    }
}
if (file_exists($og_path)) {
    // Rank Math resolves social images by attachment ID, so the JPG
    // must exist in the media library — register it once.
    $att_id = 0;
    $existing = get_posts(array(
        'post_type' => 'attachment', 'posts_per_page' => 1, 'fields' => 'ids',
        'meta_key' => '_af_og_home', 'meta_value' => '1',
    ));
    if ($existing) $att_id = (int) $existing[0];
    if (!$att_id) {
        $ft = wp_check_filetype(basename($og_path), null);
        $ins = wp_insert_attachment(array(
            'post_mime_type' => $ft['type'],
            'post_title'     => 'The Art Framer — Custom Canvas Prints & Frames',
            'post_content'   => '',
            'post_status'    => 'inherit',
        ), $og_path);
        if (!is_wp_error($ins) && $ins) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata($ins, wp_generate_attachment_metadata($ins, $og_path));
            update_post_meta($ins, '_af_og_home', '1');
            update_post_meta($ins, '_wp_attachment_image_alt', 'The Art Framer — custom canvas prints and frames');
            $att_id = (int) $ins;
            $log("og:image registered as attachment $att_id");
        }
    }
    // Replaceable = empty, the business-card scan, or our own og-home
    // value from a previous run (so we can repair the missing ID).
    $replaceable = function ($cur) {
        return $cur === '' || stripos($cur, 'Business-Cards') !== false || stripos($cur, 'og-home') !== false;
    };
    if ($att_id && $front_id) {
        $cur = (string) get_post_meta($front_id, 'rank_math_facebook_image', true);
        if ($replaceable($cur)) {
            update_post_meta($front_id, 'rank_math_facebook_image', $og_url);
            update_post_meta($front_id, 'rank_math_facebook_image_id', $att_id);
            $log('front page og:image set (url + id)');
        }
    }
    // sitewide default social image (used by pages with none of their own)
    $titles = get_option('rank-math-options-titles', array());
    if ($att_id && is_array($titles)) {
        $cur = isset($titles['open_graph_image']) ? (string) $titles['open_graph_image'] : '';
        if ($replaceable($cur)) {
            $titles['open_graph_image']    = $og_url;
            $titles['open_graph_image_id'] = $att_id;
            update_option('rank-math-options-titles', $titles);
            $log('sitewide default og:image set (url + id)');
        }
    }
}

$log('done');
