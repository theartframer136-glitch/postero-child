<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Apply brochure art codes to products. Sets each product's _taf_art_code to
 * the value in $MAP below (product ID => "RK 01" style code), reconciled by
 * matching each product's artwork to the printed brochure. Idempotent: setting
 * a code it already has is a no-op. Only touches the product IDs listed here.
 *
 * The map is built up in confirmed batches. Re-running is safe.
 *
 * Run: wp eval-file tools/apply-artcode-batch.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

// product ID => brochure art code. Radha Krishna, verified page-by-page against
// the brochure (Canva). Only entries confirmed by direct image comparison are
// listed; ambiguous pages (RK 05, 11, 15, 16, 17, 22, 24, 25, 27, 28, 30) are
// intentionally left out until matched. #19392 is the RK 23 peacock pair (its
// original, correct code) — an earlier pass had wrongly moved it to RK 05.
$MAP = array(
    24653 => 'RK 01', // Radha Krishna Painted Faces (abstract faces, drips)
    11577 => 'RK 02', // Radha Krishna Divine Love (golden, among autumn leaves)
     7811 => 'RK 03', // Radha Krishna Love (realistic embrace, orange)
    18964 => 'RK 04', // Krishna with Radha (pink lotus mandala arch)
     8301 => 'RK 06', // Radha Krishna Peacock (close-up faces, dense flowers)
     7802 => 'RK 07', // Radha Krishna Abstract (cubist faces)
     7819 => 'RK 08', // Sleeping Baby Krishna (yellow, peacock feathers)
     7822 => 'RK 09', // Lord Krishna Blessing (blue Krishna, devotee, golden)
      223 => 'RK 10', // Krishna Moonlight (flute against golden full moon)
    20831 => 'RK 12', // Radha Krishna Peacock Elegance (two teal peacocks, lotus)
    21686 => 'RK 13', // Golden Radha Krishna Duet (golden faces, peacock, tree)
    21197 => 'RK 14', // Baby Krishna Butter Delight (butter pot, golden temple)
    31772 => 'RK 18', // Radha Krishna Mosaic Art (jewel-tone mosaic faces)
    18039 => 'RK 19', // Radha Krishna Dance (Krishna lifting Radha, petals)
    19208 => 'RK 20', // Radha Krishna Floral Arch (temple arch, marigolds)
    17280 => 'RK 21', // Radha Krishna Temple Scene (golden niche, diyas)
    19392 => 'RK 23', // Radha Krishna with Peacocks (couple facing, peacocks both sides)
    11551 => 'RK 26', // Stunning Radha Krishna Abstract (cubist blue/gold, moon)
    20349 => 'RK 29', // Radha Krishna Angel Embrace (grey clouds, angelic)
    19147 => 'RK 31', // Krishna Flute Player (grayscale sketch, couple)
    13916 => 'RK 32', // Jagannath Art (Jagannath, Balaram, Subhadra)
    18107 => 'RK 33', // Krishna Flute Collage (blue face, graffiti collage)
    31829 => 'RK 34', // Krishna Flute Panorama (vivid orange/blue splash)
    21320 => 'RK 35', // Krishna with White Cows (blue Krishna among cows)
    16318 => 'RK 40', // Krishna Art (blue face, yellow/blue abstract splash)
    16823 => 'RK 44', // Golden Cosmic Krishna (starry swirl, devotee below)
    15217 => 'RK 46', // Lord Krishna Cosmic (crowned, cosmic orange, starry)
    18788 => 'RK 47', // Vishnu Divine Avatar (radiant multi-armed golden)
    19822 => 'RK 48', // Baby Krishna Hides (peeking behind red curtain)
    18355 => 'RK 50', // Mystical Govinda Poster (dark cosmic "Govinda")
    30592 => 'RK 51', // Krishna Floral Halo (flute, golden robes, roses, halo)
    24230 => 'RK 55', // Vishvarupa Gita Vision (Vishnu cosmic + Arjuna chariot)
    24033 => 'RK 56', // Radha Krishna Melody Swirl (dancing, watercolor)
    26690 => 'RK 57', // Mother and Little Krishna (Yashoda + baby, watercolor)
    23972 => 'RK 58', // Radha Krishna Petal Dance (dancing amid petals, golden)
    25901 => 'RK 59', // Krishna Watercolor Gaze (face portrait, peacock feather)
    25419 => 'RK 61', // Satyanarayan Katha Scene (Vishnu puja ceremony)
     7769 => 'RK 76', // Divine Shrinathji (pichwai, dark deity + cows + lotus)
    15974 => 'RK 30', // Dancing Lady Portrait (dancer + Krishna sketch fusion)
    26206 => 'RK 68', // Krishna in Pearl Attire (white/gold idol, garlands)

    // --- best-guess matches (closest brochure artwork) ---
    25962 => 'RK 15', // Radha Krishna Grove Mural (vivid forest couple scene)
    11560 => 'RK 25', // Divine Radha Krishna (pastel pink/white, dancing)
    19453 => 'RK 41', // Golden Krishna Flute Player (golden statue, diyas)
    18600 => 'RK 45', // Lord Krishna Playing Flute (golden idol, dark temple)
    14678 => 'RK 52', // Golden Krishna Temple Idol (garlanded shrine idol)
    31212 => 'RK 77', // Radha Krishna Jeweled Duet (ornate golden pair, flowers)
    17212 => 'RK 78', // Beautiful Lord Krishna Statue (black idol, garlands)
     7824 => 'RK 79', // Bal Krishna Flute (baby Krishna, soft glow)
    13355 => 'RK 80', // Krishna Standing Tall (idol, peacock-feather arch)
    28900 => 'RK 83', // Krishna Kadamba Melody (Krishna on branch, moon)
    30653 => 'RK 85', // Krishna Peacock Serenade (ornate turban, lotus, flute)
    31334 => 'RK 86', // Radha in Bridal Blooms (photoreal bridal portrait)
    27325 => 'RK 87', // Radha Krishna on the Branch (couple, blossoms)
    22138 => 'RK 88', // Krishna's Gopis Gathering (vintage devotee scene)
    30470 => 'RK 90', // Moonlit Lotus Radha (lotus lake, starlit night)

    // --- cross-category fixes: Seven Horses (SH) ---
    19025 => 'SH 04', // Seven Horses Ocean Run (white horses, dark ocean)
    30093 => 'SH 05', // Horses of the Dust Plains (dark golden herd)
    19636 => 'SH 08', // Seven Horses in the Clouds (golden clouds)
    27572 => 'SH 10', // Two Horses Cubist (abstract geometric herd)

    // --- cross-category fixes: Lord Shiva (LS; 16-17 extend the printed range) ---
    17856 => 'LS 16', // Shiva The Destroyer (standing, trident, fire ring)
    20770 => 'LS 17', // Shiva Crescent Dream (blue portrait, crescent)

    // --- cross-category fixes: Buddha (LB; extends existing LB range) ---
     7676 => 'LB 12', // Abstract Buddha Lotus Meditation (green/pink)
    27811 => 'LB 13', // Buddha Among Pink Lotuses (stone Buddha, lotus)

    // --- clear wrong codes (artwork not in the brochure) ---
    29517 => '',      // Lone Tree Between Worlds (landscape; was RK 15)
    19086 => '',      // Surya in Chariot (was RK 15)
    25840 => '',      // Dancers in Duet (was RK 14)
    13722 => '',      // Stairway to the Moon (was RK 34)
    27017 => '',      // Graphite Muse Sketch (was RK 37)
     8585 => '',      // Elegant Large Framed White mockup (was RK 32)
     8440 => '',      // Large Landscape Canvas Print (was RK 42)
    27133 => '',      // Geometric Falls Sunrise (was RK 43)
    28595 => '',      // Krishna's Promise (was RK 46; RK 46 is #15217)
    26450 => '',      // Starry City Nights (was RK 58)
    28422 => '',      // Raas Leela Miniature (was TA 04)
    22505 => '',      // Sleeping Krishna Art (was TA 04)
    23558 => '',      // Bal Krishna Classic Portrait (was LG 03)
    23789 => '',      // Radha Krishna Color Duet (was WL 07)
    18229 => '',      // Lord Krishna Statue (was LR 06)
);

echo "=== APPLY ART CODE BATCH ===\n";
echo "products in map: " . count( $MAP ) . "\n\n";

global $wpdb;
$set = 0; $same = 0; $skip = 0;

// Which other products currently hold a code we're about to assign? (rebuild
// context: codes were previously duplicated/mis-assigned; report, don't touch.)
foreach ( $MAP as $pid => $code ) {
    $pid  = (int) $pid;
    $code = trim( $code );
    $post = get_post( $pid );
    if ( ! $post || $post->post_type !== 'product' || $post->post_status !== 'publish' ) {
        echo "SKIP  #{$pid}: not a published product\n";
        $skip++;
        continue;
    }
    $title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
    $old   = trim( (string) get_post_meta( $pid, '_taf_art_code', true ) );

    // empty code = remove a wrong code (artwork not in the brochure)
    if ( $code === '' ) {
        if ( $old !== '' ) {
            delete_post_meta( $pid, '_taf_art_code' );
            echo "CLEAR #{$pid} (was: {$old}) — {$title}\n";
            $set++;
        } else {
            echo "OK    #{$pid} already blank — {$title}\n";
            $same++;
        }
        continue;
    }

    // note any OTHER products that still carry this target code
    $holders = $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_taf_art_code' AND meta_value=%s AND post_id<>%d",
        $code, $pid
    ) );

    if ( $old === $code ) {
        echo "OK    #{$pid} [{$code}] unchanged — {$title}\n";
        $same++;
    } else {
        update_post_meta( $pid, '_taf_art_code', $code );
        echo "SET   #{$pid} [{$code}] (was: " . ( $old !== '' ? $old : 'NONE' ) . ") — {$title}\n";
        $set++;
    }
    if ( $holders ) {
        echo "        note: code {$code} also on #" . implode( ', #', array_map( 'intval', $holders ) ) . " (to reconcile later)\n";
    }
}

echo "\nSET: {$set} | already-correct: {$same} | skipped: {$skip}\n";
echo "=== DONE ===\n";
