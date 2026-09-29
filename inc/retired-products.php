<?php
/**
 * Fourteen listings deleted on 29 Sep 2026 because each showed the same
 * picture as another listing.
 *
 * Owner, 29 Sep: of every pair with the same main picture, keep the one whose
 * main image is the better and delete the other. The pairs are the "Same
 * picture" groups of theartframer-duplicates.xlsx (groups 1-14; group 15 is
 * three frame products sharing one room photo, which stay). Kept: the larger
 * or sharper main image, or the one showing the whole artwork where the other
 * was a crop; identical images went to the listing carrying the plain code.
 * The three survivors that carried a letter took the plain code (#13355
 * RK-010097-3020, #18600 RK-010040-5030, #34601 WL-170001-4030), and #7824's
 * review moved to #33925. All fourteen were deleted for good, at the owner's
 * word, through the REST API — nothing here deletes anything.
 *
 * What this file does: their addresses had been indexed (six since February),
 * so each is added to the af_duplicate_redirects map that
 * inc/duplicate-listings.php already serves: an old link answers 301 to the
 * listing that stays. Runs once per revision, on the first request after the
 * deploy.
 */
if (!defined('ABSPATH')) exit;

define('AF_RETIRED_PRODUCTS_REV', '1');   // bump after changing the list

/** deleted product's address slug => array(its id, the id that stays) */
function af_retired_products() {
    return array(
        'shrinathji-pichwai-lotus-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor' => array(34164, 13355),   // Shrinathji Pichwai Lotus Canvas Wall Art
        'golden-krishna-temple-shrine-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor' => array(34003, 34147),   // Golden Krishna Temple Shrine Canvas Wall
        'radha-krishna-lotus-embrace-canvas-wall-art-2-5x3-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor' => array(33952, 18964),   // Radha Krishna Lotus Embrace Canvas Wall 
        'golden-krishna-peacock-halo-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor' => array(33993, 18600),   // Golden Krishna Peacock Halo Canvas Wall 
        'balaji-starlit-devotee-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor' => array(33945, 34214),   // Balaji Starlit Devotee Canvas Wall Art 3
        'divine-shrinathji-canvas-wall-art' => array(7769, 34305),   // Divine Shrinathji Canvas Wall Art – Stun
        'elephant-harmony-canvas-wall-art' => array(7767, 34601),   // Sacred Elephant Harmony Canvas Wall Art 
        'divine-varanasi-ganga-aarti-canvas-wall-art' => array(8424, 34725),   // Divine Varanasi Ganga Aarti Canvas Wall 
        'sacred-kedarnath-temple-with-lord-shiva-wall-art-72-24-inches-divine-h' => array(8494, 34732),   // Sacred Kedarnath Temple with Lord Shiva 
        'bal-krishna-flute-canvas-wall-art' => array(7824, 33925),   // Bal Krishna Flute Canvas Wall Art – 60 x
        'minimalist-blossom-vase-canvas-wall-art' => array(7833, 34529),   // Minimalist Blossom Vase Canvas Wall Art 
        'radha-krishna-with-peacocks-canvas-wall-art' => array(19392, 33911),   // Radha Krishna with Peacocks Canvas Wall 
        'namaste-henna-hands-gold-foiled-uv-canvas-art-3x5-feet' => array(33285, 24714),   // Namaste Henna Hands Canvas Wall Art 3x5 
        'rhythm-in-orange-gold-foiled-uv-canvas-art-3x2-feet' => array(33291, 28778),   // Rhythm In Orange Canvas Wall Art 3x4 Fee
    );
}

add_action('wp_loaded', function () {
    if (get_option('af_retired_products_rev') === AF_RETIRED_PRODUCTS_REV) return;
    update_option('af_retired_products_rev', AF_RETIRED_PRODUCTS_REV, true);
    $map = get_option('af_duplicate_redirects');
    if (!is_array($map)) $map = array();
    $n = 0;
    foreach (af_retired_products() as $slug => $pair) {
        $map[strtolower($slug)] = array((int) $pair[0], (int) $pair[1]);
        $n++;
    }
    update_option('af_duplicate_redirects', $map, true);
    update_option('af_retired_products', $n . ' redirects added @ ' . gmdate('c'), false);
}, 98);
