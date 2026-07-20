<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Assign meaningful product tags by matching keywords in each product title.
 * Gives the shop tag filter useful, clickable buttons.
 * SAFE: appends tags (does not remove existing). Idempotent.
 * Run: wp eval-file tools/assign-tags.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

// Tag name => keywords to look for in the (lowercased) title
$MAP = array(
    'Radha Krishna' => array('radha krishna','radha-krishna','radha'),
    'Krishna'       => array('krishna'),
    'Buddha'        => array('buddha'),
    'Ganesha'       => array('ganesha','ganesh'),
    'Shiva'         => array('shiva','mahadev'),
    'Rama'          => array('ram darbar','lord rama',' rama'),
    'Durga'         => array('durga'),
    'Lakshmi'       => array('lakshmi'),
    'Hanuman'       => array('hanuman'),
    'Tirupati Balaji' => array('venkateswara','balaji','tirupati'),
    'Seven Horses'  => array('seven horses','7 horses','running horses','white horses','horses'),
    'Peacock'       => array('peacock'),
    'Floral'        => array('floral','flower','blossom','vase'),
    'Abstract'      => array('abstract'),
    'Landscape'     => array('landscape','waterfall','forest','mountain','sunrise'),
    'Butterfly'     => array('butterfly'),
    'Elephant'      => array('elephant'),
    'Birds'         => array('bird','birds'),
    'Nature'        => array('nature','tree'),
    'Spiritual'     => array('divine','spiritual','sacred','temple','pooja','puja','mandir','shrinathji','garuda'),
    'Modern'        => array('modern','minimalist','contemporary'),
    'Vastu'         => array('vastu','vaastu','prosperity','positivity'),
    'Wildlife'      => array('wildlife','tiger','lion'),
    'Kids Room'     => array('kids','cartoon','cat','cute'),
    'Meditation'    => array('meditation','lotus','zen'),
    'Dance'         => array('dance','bharatanatyam','classical'),
);

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
echo "=== ASSIGN PRODUCT TAGS (" . count($ids) . " products) ===\n\n";

$tagged = 0;
foreach ($ids as $pid) {
    $product = wc_get_product($pid);
    if (!$product) continue;
    $title = ' ' . strtolower($product->get_name()) . ' ';
    $tags = array();
    foreach ($MAP as $tag => $kws) {
        foreach ($kws as $kw) {
            if (strpos($title, strtolower($kw)) !== false) { $tags[] = $tag; break; }
        }
    }
    // Every canvas/art product also gets a couple of broad tags for discovery
    if (strpos($title,'canvas') !== false) $tags[] = 'Canvas Art';
    $tags[] = 'Wall Art';
    $tags = array_values(array_unique($tags));
    if ($tags) {
        wp_set_object_terms($pid, $tags, 'product_tag', true); // append
        clean_post_cache($pid);
        wc_delete_product_transients($pid);
        $tagged++;
    }
}
echo "Tagged {$tagged} products.\n";
echo "=== DONE ===\n";
