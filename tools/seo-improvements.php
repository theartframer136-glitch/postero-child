<?php
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
    $t = 'Shop Canvas Prints, Wall Art & Picture Frames | The Art Framer';
    $d = 'Browse the full Art Framer collection — canvas prints, personalized portraits, frames and studio accessories. Made to order, delivered anywhere in the USA.';
    $generic_title = function ($cur) { return stripos(trim($cur), 'shop') === 0 && mb_strlen(trim($cur)) < 30; };
    if ($set_meta($shop_id, 'rank_math_title', $t, $generic_title)) $log('shop page: title set');
    if ($set_meta($shop_id, 'rank_math_description', $d))           $log('shop page: description set');
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
        'd' => 'Explore every art print in one place — spiritual, abstract, nature and more, printed on premium canvas and shipped ready to hang across the USA.',
        'i' => 'Every print in the studio, one collection — printed to order on archival canvas and shipped ready to hang anywhere in the USA.',
    ),
    'gallery-prints' => array(
        't' => 'Gallery-Quality Art Prints | The Art Framer',
        'd' => 'Gallery-quality prints with rich color and archival inks. Custom sizes and frames, made to order and delivered across the USA.',
        'i' => 'Gallery-quality prints with rich, true-to-life color — finished in the size and frame your wall calls for.',
    ),
    'digital-canvas-prints' => array(
        't' => 'Digital Canvas Prints & Wall Art | The Art Framer',
        'd' => 'Digital canvas prints across spiritual, abstract and nature themes — archival quality, eco-friendly inks and USA-wide delivery.',
        'i' => 'Digital canvas prints across spiritual, abstract and nature themes — archival inks, multiple sizes, ready to hang.',
    ),
    'buddha' => array(
        't' => 'Buddha Canvas Wall Art & Spiritual Prints | The Art Framer',
        'd' => 'Serene Buddha canvas wall art for meditation rooms, yoga studios and calm living spaces. Premium archival prints, multiple sizes — USA shipping.',
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
        't' => 'DIY Floating Frames for Canvas | The Art Framer',
        'd' => 'Easy DIY floating frame kits that give stretched canvas a premium gallery finish. Custom sizes, simple assembly, shipped across the USA.',
        'i' => 'Give stretched canvas a premium gallery finish — easy-assembly floating frame kits in custom sizes.',
    ),
    'frame-colors' => array(
        't' => 'Frame Colors – Black, Gold, Silver & More | The Art Framer',
        'd' => 'Pick the perfect frame color — black, gold, rose gold, silver and more, matched to your art and your room.',
        'i' => 'Black, gold, rose gold, silver and more — find the frame color that ties the room together.',
    ),
);

$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
$n_title = $n_desc = $n_intro = 0;
if (!is_wp_error($terms)) {
    foreach ($terms as $term) {
        $m = isset($cat_map[$term->slug]) ? $cat_map[$term->slug] : null;
        $t = $m ? $m['t'] : sprintf('%s – Shop Online | The Art Framer', $term->name);
        $d = $m ? $m['d'] : sprintf('Shop the %s collection at The Art Framer — premium quality, made to order and shipped fast across the USA.', $term->name);

        if ((string) get_term_meta($term->term_id, 'rank_math_title', true) === '') {
            update_term_meta($term->term_id, 'rank_math_title', $t);
            $n_title++;
        }
        if ((string) get_term_meta($term->term_id, 'rank_math_description', true) === '') {
            update_term_meta($term->term_id, 'rank_math_description', $d);
            $n_desc++;
        }
        if ($m && $m['i'] !== '' && trim((string) $term->description) === '') {
            wp_update_term($term->term_id, 'product_cat', array('description' => $m['i']));
            $n_intro++;
        }
    }
    $log("categories: $n_title titles, $n_desc descriptions, $n_intro intros set");
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
