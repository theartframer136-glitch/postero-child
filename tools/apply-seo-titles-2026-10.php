<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Search titles and descriptions for the ten pages Google shows most
 * (Search Console, Jul–Oct 2026), rewritten for the Indian community in
 * the USA. Written to Rank Math's own fields, so the plugin shows them
 * as if typed into its snippet editor.
 *
 * Owner-approved copy, 2026-10-14. No page may promise free shipping:
 * the shop does not ship free (see af_shipping_copy()).
 *
 * 2026-10-22, owner-approved: "ready to hang" and "framed" replaced by
 * "rolled, stretched or framed" in the descriptions. A buyer can order the
 * painting only (shipped rolled), on stretcher bars, or framed
 * (inc/kit-choices.php), so the old wording promised more than every order
 * gets. Titles are unchanged.
 *
 * Before the first write, every target's current title and description
 * are saved once to the option af_seo_titles_2026_10_backup, so the
 * change can be undone with:
 *   wp eval-file .../tools/apply-seo-titles-2026-10.php rollback --allow-root
 *
 * Idempotent: a target already holding the new text is left alone, and
 * the backup is never overwritten by a later run.
 *
 * seo-improvements.php only fills empty or generic ("Home…") values, so
 * it does not undo these on later deploys.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$log = function ($m) { echo "[seo-titles] $m\n"; };
$OPT = 'af_seo_titles_2026_10_backup';

// [kind, slug-or-path, title, description]
$T = array(
    array('front', '', 'Hindu God Paintings & Indian Wall Art | The Art Framer',
        'Radha Krishna, Ganesha, Balaji & 7 Horses canvas wall art for Indian homes in the USA. Rolled, stretched or framed. Perfect for Diwali & housewarming gifts.'),
    array('term', 'radha-krishna', 'Radha Krishna Painting for Home | Canvas Wall Art USA',
        'Shop Radha Krishna canvas paintings for your living room, bedroom or pooja room: rolled, stretched or framed, shipped across the USA. A loved Diwali gift.'),
    array('term', 'digital-canvas-prints', 'Indian Canvas Wall Art & Spiritual Paintings | The Art Framer',
        'Explore Hindu deity, Vastu and Indian art canvas prints: Krishna, Ganesha, Balaji, Buddha, Pichwai and more. Rolled, stretched or framed, shipped across the USA.'),
    array('term', 'hindu-deities', 'Hindu God Paintings for Home & Pooja Room | Canvas Art',
        'Canvas paintings of Ganesha, Lakshmi, Shiva, Durga, Ram and Krishna for your home mandir and walls. Rolled, stretched or framed, shipped across the USA.'),
    array('term', 'lakshmi-ganesha', 'Lakshmi Ganesh Painting for Diwali | Canvas Wall Art',
        'Welcome prosperity this Diwali with Lakshmi Ganesh canvas art for your pooja room or entrance. Rolled, stretched or framed, shipped across the USA. Order early.'),
    array('term', 'buddha', 'Buddha Canvas Wall Art & Paintings for Living Room',
        'Calm Buddha wall art for living rooms, bedrooms and meditation spaces. Large canvas prints, rolled, stretched or framed, shipped across the USA.'),
    array('term', 'pichwai-art', 'Pichwai Painting Canvas Prints | Shrinathji Wall Art',
        'Traditional Pichwai art of Shrinathji, cows and lotus on premium canvas. Bring Indian heritage to your home. Rolled, stretched or framed, shipped across the USA.'),
    array('term', 'diy-floating-frames', 'Canvas Floating Frames | Floater Frame Kits for Canvas',
        'DIY floating frames for stretched canvas in black, gold, silver and natural wood. Easy to assemble, many sizes, shipped across the USA.'),
    array('post', 'top-wall-decor-trends-in-2026', 'Top Wall Decor Trends 2026: Ideas for Every Room',
        'The biggest wall decor trends of 2026, from oversized canvas and spiritual art to gallery walls, with ideas for living rooms, bedrooms and pooja rooms.'),
    array('post', 'best-online-canvas-printing-service-near-delaware', 'Best Online Canvas Printing Near Delaware (2026 Guide)',
        'Looking for canvas printing in Delaware? Compare options, sizes and turnaround, and see custom photo-to-canvas and Indian art prints from The Art Framer.'),
);

// Resolve each target to ['post'|'term', id, label]; null when it isn't on the site.
$resolve = function ($kind, $slug) {
    if ($kind === 'front') {
        $id = (int) get_option('page_on_front');
        return $id ? array('post', $id, 'front page') : null;
    }
    if ($kind === 'term') {
        $t = get_term_by('slug', $slug, 'product_cat');
        return ($t && !is_wp_error($t)) ? array('term', (int) $t->term_id, "category $slug") : null;
    }
    $p = get_page_by_path($slug, OBJECT, array('post', 'page'));
    return $p ? array('post', (int) $p->ID, "/$slug/") : null;
};
$get = function ($type, $id, $key) {
    return (string) ($type === 'term' ? get_term_meta($id, $key, true) : get_post_meta($id, $key, true));
};
$put = function ($type, $id, $key, $val) {
    return $type === 'term' ? update_term_meta($id, $key, $val) : update_post_meta($id, $key, $val);
};

$rollback = isset($args) && is_array($args) && in_array('rollback', $args, true);
$backup = get_option($OPT, null);

if ($rollback) {
    if (!is_array($backup)) { $log('no backup saved, nothing to roll back'); return; }
    foreach ($backup as $b) {
        $put($b['type'], (int) $b['id'], 'rank_math_title', $b['title']);
        $put($b['type'], (int) $b['id'], 'rank_math_description', $b['description']);
        $log("restored {$b['label']}");
    }
    return;
}

$targets = array();
foreach ($T as $row) {
    list($kind, $slug, $title, $desc) = $row;
    $r = $resolve($kind, $slug);
    if (!$r) { $log("SKIP not found: $kind $slug"); continue; }
    $targets[] = array($r[0], $r[1], $r[2], $title, $desc);
}

if (!is_array($backup)) {
    $backup = array();
    foreach ($targets as $t) {
        $backup[] = array('type' => $t[0], 'id' => $t[1], 'label' => $t[2],
            'title' => $get($t[0], $t[1], 'rank_math_title'),
            'description' => $get($t[0], $t[1], 'rank_math_description'));
    }
    update_option($OPT, $backup, false);
    $log('backup saved for ' . count($backup) . ' targets');
}

$changed = 0;
foreach ($targets as $t) {
    list($type, $id, $label, $title, $desc) = $t;
    $did = false;
    if ($get($type, $id, 'rank_math_title') !== $title) { $put($type, $id, 'rank_math_title', $title); $did = true; }
    if ($get($type, $id, 'rank_math_description') !== $desc) { $put($type, $id, 'rank_math_description', $desc); $did = true; }
    $log(($did ? 'updated ' : 'already current ') . $label);
    $changed += $did ? 1 : 0;
}
$log("done: $changed changed of " . count($targets));
