<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Give Gold Foiled & UV the sub-collections its circle row needs.
 *
 * Every other tab in the homepage's Shop by Collection strip has a row of
 * round sub-collections under it — Radha Krishna, Lakshmi Ganesha, Lord Shiva
 * and the rest. The premium section had none, because tools/import-gold-foil.php
 * files each piece under gold-foiled-uv and nothing else, and
 * tools/enforce-goldfoil-category.php then keeps it that way.
 *
 * That rule is right and stays: a gold-foil piece must not turn up in the
 * ordinary categories at a second price. What it never meant to forbid is the
 * section having sections of its own — inc/gold-foil.php has priced
 * "Gold Foiled & UV -> Ganesha" since the day it was written, because
 * af_is_goldfoil() reads the whole subtree.
 *
 * So this splits the section by what each piece DEPICTS, using the same title
 * keywords that group the rest of the catalogue
 * (tools/assign-deity-categories.php), and files each piece under the matching
 * child of gold-foiled-uv. A piece nothing matches stays where it is, directly
 * under the section, and is still listed there — it just does not get a circle
 * of its own.
 *
 * Idempotent: children are created once, a piece already in the right child is
 * left alone, and thumbnails are only ever filled in when missing.
 *
 * Run: wp eval-file tools/goldfoil-subcategories.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('af_goldfoil_slug')) { echo "ABORT: the gold-foil module is not loaded.\n"; return; }
if (!function_exists('wc_get_product'))   { echo "ABORT: WooCommerce is not active.\n"; return; }

echo "=== GOLD FOILED & UV SUB-COLLECTIONS ===\n";

$parent = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
if (!$parent || is_wp_error($parent)) { echo "  the section does not exist yet.\n=== DONE ===\n"; return; }
$parent_id = (int) $parent->term_id;

/* The same subjects the rest of the shop is grouped by, worded the same way,
 * so a shopper meets one vocabulary and not two. Kept here rather than
 * imported from assign-deity-categories.php because that file is a script that
 * runs on include, not a library. */
$MAP = array(
    'Radha Krishna'   => array('radha krishna', 'radha-krishna', 'radha'),
    'Lakshmi Ganesha' => array('ganesha', 'ganesh', 'lakshmi'),
    'Lord Shiva'      => array('shiva', 'mahadev', 'nataraj'),
    'Lord Rama'       => array('ram darbar', 'lord rama', 'shri ram', 'ramdarbar', 'hanuman'),
    'Seven Horses'    => array('seven horses', '7 horses', 'running horses', 'horses'),
    'Tirupati Balaji' => array('venkateswara', 'balaji', 'tirupati'),
    'Buddha'          => array('buddha'),
    'Sikh Art'        => array('golden temple', 'amritsar', 'sikh', 'guru nanak'),
    'Swaminarayan'    => array('swaminarayan'),
    'Pichwai Art'     => array('pichwai'),
    'Durga & Devi'    => array('durga', 'kali', 'saraswati', 'devi', 'ambe'),
    'Landscapes'      => array('landscape', 'waterfall', 'forest', 'mountain', 'sunrise'),
    'Wildlife'        => array('tiger', 'lion', 'elephant', 'peacock', 'butterfly', 'birds'),
    'Abstract Art'    => array('abstract', 'geometric', 'minimalist', 'mosaic'),
    'Indian Culture'  => array('bharatanatyam', 'classical dance', 'garba', 'tanjore', 'madhubani'),
    'Still Life'      => array('still life', 'vase', 'blossom', 'lotus'),
);

/* ── the pieces ───────────────────────────────────────────────────────── */
$ids = get_posts(array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'tax_query'      => array(array(
        'taxonomy'         => 'product_cat',
        'field'            => 'term_id',
        'terms'            => array($parent_id),
        'include_children' => true,
    )),
));
printf("  pieces in the section: %d\n", count($ids));
if (!$ids) { echo "  nothing to group yet.\n=== DONE ===\n"; return; }

/* ── group them by title ──────────────────────────────────────────────── */
$groups = array();      // subject => array of product ids
$loose  = array();
foreach ($ids as $pid) {
    $title = strtolower(get_the_title($pid));
    $hit   = '';
    foreach ($MAP as $subject => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($title, $kw) !== false) { $hit = $subject; break 2; }
        }
    }
    if ($hit === '') { $loose[] = $pid; continue; }
    $groups[$hit][] = (int) $pid;
}

if (!$groups) {
    printf("  no piece's title names a subject — all %d stay directly in the section.\n", count($loose));
    echo "  (the circle row falls back to the pieces themselves; see af_goldfoil_collection_payload)\n";
    echo "=== DONE ===\n";
    return;
}

/* ── one child term per subject, and the pieces filed under it ────────── */
$made = $filed = $thumbed = 0;
foreach ($groups as $subject => $pids) {
    $slug = af_goldfoil_slug() . '-' . sanitize_title($subject);

    $child = get_term_by('slug', $slug, 'product_cat');
    if (!$child || is_wp_error($child)) {
        $r = wp_insert_term($subject, 'product_cat', array('slug' => $slug, 'parent' => $parent_id));
        if (is_wp_error($r)) { printf("  %-18s FAILED — %s\n", $subject, $r->get_error_message()); continue; }
        $child = get_term((int) $r['term_id'], 'product_cat');
        update_term_meta($child->term_id, 'display_type', '');   // products, not a wall of tiles
        $made++;
    }
    $child_id = (int) $child->term_id;

    foreach ($pids as $pid) {
        $have = wp_get_post_terms($pid, 'product_cat', array('fields' => 'ids'));
        if (is_wp_error($have)) continue;
        $have = array_map('intval', $have);
        if (in_array($child_id, $have, true)) continue;
        // The section AND its sub-collection: the parent term is what every
        // price, badge and listing in inc/gold-foil.php keys off, and dropping
        // it here would quietly take the piece out of its own section.
        wp_set_object_terms($pid, array($parent_id, $child_id), 'product_cat');
        $filed++;
    }

    // the circle's picture: the first piece in it that has one
    $thumb = (int) get_term_meta($child_id, 'thumbnail_id', true);
    $file  = $thumb ? get_attached_file($thumb) : '';
    if (!$thumb || !$file || !@file_exists($file)) {
        foreach ($pids as $pid) {
            $att = get_post_thumbnail_id($pid);
            $f   = $att ? get_attached_file($att) : '';
            if ($att && $f && @file_exists($f)) {
                update_term_meta($child_id, 'thumbnail_id', (int) $att);
                $thumbed++;
                break;
            }
        }
    }

    wp_update_term_count_now(array($child_id), 'product_cat');
    $child = get_term($child_id, 'product_cat');
    printf("  %-18s #%d  %d product(s)\n", $subject, $child_id, (int) $child->count);
}

wp_update_term_count_now(array($parent_id), 'product_cat');

printf("\n  sub-collections created: %d | pieces filed: %d | circle pictures set: %d\n", $made, $filed, $thumbed);
if ($loose) printf("  %d piece(s) name no subject and stay directly in the section\n", count($loose));
echo "=== DONE ===\n";
