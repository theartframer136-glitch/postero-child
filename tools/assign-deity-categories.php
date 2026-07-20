<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Populate deity/theme subcategories by assigning each product to the correct
 * subcategory based on its title. Fixes near-empty subcategories (Lakshmi
 * Ganesha, Lord Shiva, etc.). Appends categories (never removes). Idempotent.
 * Prefers the subcategory under "Digital Canvas Prints" when names duplicate.
 * Run: wp eval-file tools/assign-deity-categories.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

// Subcategory name => title keywords (lowercase)
$MAP = array(
    'Radha Krishna'   => array('radha krishna','radha-krishna','radha'),
    'Lakshmi Ganesha' => array('ganesha','ganesh','lakshmi'),
    'Lord Shiva'      => array('shiva','mahadev','vaishnav tilak','shankh chakra'),
    'Lord Rama'       => array('ram darbar','lord rama','shri ram','ramdarbar'),
    'Seven Horses'    => array('seven horses','7 horses','running horses','white horses','horses'),
    'Tirupati Balaji' => array('venkateswara','balaji','tirupati'),
    'Buddha'          => array('buddha'),
    'Sikh Art'        => array('golden temple','amritsar','sikh','guru nanak'),
    'Landscapes'      => array('landscape','waterfall','forest','mountain','sunrise'),
    'Still Life'      => array('still life','blossom vase','rustic blossom','vase','fruit','lemon'),
    'Wildlife'        => array('wildlife','tiger','lion','elephant','peacock','butterfly','birds','penguin'),
    'Kids Room'       => array('kids','cartoon','cute cartoon','baby krishna','sleeping baby'),
    'Abstract Art'    => array('abstract','geometric','minimalist'),
    'Indian Culture'  => array('bharatanatyam','classical dance','indian classical','garba'),
    'Vaastu Art'      => array('vastu','vaastu'),
);

// Resolve the "Digital Canvas Prints" parent to disambiguate duplicate names
$parent = get_term_by('slug', 'digital-canvas-prints', 'product_cat');
$parent_id = $parent ? (int)$parent->term_id : 0;

function af_find_term($name, $parent_id) {
    $terms = get_terms(array('taxonomy'=>'product_cat','name'=>$name,'hide_empty'=>false));
    if (is_wp_error($terms) || !$terms) return null;
    if ($parent_id) foreach ($terms as $t) { if ((int)$t->parent === $parent_id) return $t; }
    return $terms[0];
}

// Resolve target term IDs
$targets = array();
foreach ($MAP as $name => $kws) {
    $t = af_find_term($name, $parent_id);
    if ($t) $targets[$name] = array('id'=>(int)$t->term_id, 'kws'=>$kws);
    else echo "  (subcategory not found: {$name})\n";
}

echo "=== BEFORE counts ===\n";
foreach ($targets as $name => $info) {
    $c = (int) get_term($info['id'])->count;
    echo sprintf("  %-18s = %d\n", $name, $c);
}

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
echo "\n=== ASSIGNING (" . count($ids) . " products) ===\n";
$assigned = 0;
foreach ($ids as $pid) {
    $p = wc_get_product($pid); if (!$p) continue;
    $title = ' ' . strtolower($p->get_name()) . ' ';
    $add = array();
    foreach ($targets as $name => $info) {
        foreach ($info['kws'] as $kw) {
            if (strpos($title, ' ' . strtolower($kw)) !== false || strpos($title, strtolower($kw)) !== false) {
                $add[] = $info['id']; break;
            }
        }
    }
    if ($add) {
        wp_set_object_terms($pid, array_values(array_unique($add)), 'product_cat', true); // append
        $assigned++;
    }
}

// Recount (term counts update async; force)
if (function_exists('wc_recount_all_terms')) { /* heavy; skip */ }
foreach ($targets as $name => $info) {
    wp_update_term_count_now(array($info['id']), 'product_cat');
}

echo "\n=== AFTER counts ===\n";
foreach ($targets as $name => $info) {
    $term = get_term($info['id']);
    echo sprintf("  %-18s = %d\n", $name, (int)$term->count);
}
echo "\nProducts touched: {$assigned}\n=== DONE ===\n";
