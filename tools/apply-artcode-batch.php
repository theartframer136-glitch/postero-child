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

// product ID => brochure art code. Radha Krishna batch 1 (RK 01–10).
$MAP = array(
    31772 => 'RK 01', // Radha Krishna Mosaic Art (mosaic faces, flowers, birds)
    21686 => 'RK 02', // Golden Radha Krishna Duet (all-gold among leaves)
     7811 => 'RK 03', // Radha Krishna Love (realistic embrace, orange)
    18964 => 'RK 04', // Krishna with Radha (pink lotus mandala arch)
    19392 => 'RK 05', // Radha Krishna with Peacocks (golden, flute)
     8301 => 'RK 06', // Radha Krishna Peacock (close-up faces, dense flowers)
     7802 => 'RK 07', // Radha Krishna Abstract (cubist faces)
     7819 => 'RK 08', // Sleeping Baby Krishna (yellow, peacock feathers)
     7822 => 'RK 09', // Lord Krishna Blessing (blue Krishna, devotee, golden)
      223 => 'RK 10', // Krishna Moonlight (flute against golden full moon)
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
