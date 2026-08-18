<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. How many product images are the wrong way round?
 *
 * The report was one screenshot: a portrait print shown in a landscape
 * flat-lay scene. Before touching a single file we need the size of the
 * problem, because the three ways to fix it differ enormously in cost and in
 * what they destroy — and the scenes are flattened photographs, so anything
 * that edits them is irreversible.
 *
 * Ground truth for what a product IS comes from its TITLED SIZE, not from its
 * pictures: a "2×3 ft" print is portrait whatever its photographs happen to
 * be. Every image is then measured against that.
 *
 * Also samples a few mismatched scenes and asks whether the framed artwork
 * inside them can even be located — that is the precondition for rotating the
 * art without rotating the background, and tools/make-wall-mockup.php needed
 * hand-tuned coordinates for four images, so it is worth knowing the rate
 * before anyone commits to that route.
 *
 * Makes no changes.
 *
 * Run: wp eval-file tools/diag-image-orientation.php --allow-root
 * Env: AF_ORI_PROBE (default 8)  how many scenes to open for frame detection
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$PROBE = (int) ( getenv( 'AF_ORI_PROBE' ) ?: 8 );

echo "=== PRODUCT IMAGE ORIENTATION ===\n";
echo "read-only — nothing is modified\n\n";

/** portrait / landscape / square from a width and height. */
function afo_shape( $w, $h ) {
    $w = (int) $w; $h = (int) $h;
    if ( $w <= 0 || $h <= 0 ) return '';
    if ( abs( $w - $h ) <= max( 2, round( min( $w, $h ) * 0.02 ) ) ) return 'square';
    return $h > $w ? 'portrait' : 'landscape';
}

/**
 * What the product IS, from the size in its title — "2×3 ft" is 2 wide by 3
 * tall, so portrait. This is the only orientation that is a fact about the
 * product rather than about a photograph of it.
 */
function afo_intended( $product ) {
    if ( ! function_exists( 'af_size_label_for_product' ) ) return '';
    $label = af_size_label_for_product( $product );
    if ( $label === '' ) return '';
    // "2.5×3 ft (30×36 in)" → 2.5 and 3
    if ( ! preg_match( '/^\s*([\d.]+)\s*[×x]\s*([\d.]+)/u', $label, $m ) ) return '';
    return afo_shape( (float) $m[1] * 100, (float) $m[2] * 100 );
}

/** A styled room/flat-lay photograph, as opposed to the artwork itself. */
function afo_is_scene( $id ) {
    $file = get_post_meta( $id, '_wp_attached_file', true );
    return (bool) preg_match( '/(scene|room|placement|mockup|lifestyle|interior)/i', (string) $file );
}

$products = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ) );
$stats = array(
    'products' => 0, 'no_size' => 0, 'images' => 0,
    'match' => 0, 'mismatch' => 0, 'square' => 0, 'unknown' => 0,
);
$by_kind = array( 'portrait_product_landscape_image' => 0, 'landscape_product_portrait_image' => 0 );
$scene_mismatch = 0; $art_mismatch = 0;
$examples = array(); $probe_ids = array();

foreach ( $products as $pid ) {
    $p = wc_get_product( $pid );
    if ( ! $p ) continue;
    if ( function_exists( 'af_pricing_applies' ) && ! af_pricing_applies( $p ) ) continue;
    $stats['products']++;

    $want = afo_intended( $p );
    if ( $want === '' || $want === 'square' ) { $stats['no_size']++; continue; }

    $img_ids = array();
    if ( $p->get_image_id() ) $img_ids[] = (int) $p->get_image_id();
    foreach ( (array) $p->get_gallery_image_ids() as $g ) if ( $g ) $img_ids[] = (int) $g;

    foreach ( $img_ids as $aid ) {
        $meta = wp_get_attachment_metadata( $aid );
        if ( ! $meta || empty( $meta['width'] ) ) { $stats['unknown']++; continue; }
        $stats['images']++;
        $got = afo_shape( $meta['width'], $meta['height'] );
        if ( $got === 'square' ) { $stats['square']++; continue; }
        if ( $got === $want )    { $stats['match']++;  continue; }

        $stats['mismatch']++;
        $scene = afo_is_scene( $aid );
        $scene ? $scene_mismatch++ : $art_mismatch++;
        $key = $want . '_product_' . $got . '_image';
        if ( isset( $by_kind[ $key ] ) ) $by_kind[ $key ]++;

        if ( count( $examples ) < 8 ) {
            $examples[] = sprintf( '#%d %s | product %s, image %dx%d %s | %s',
                $pid, mb_substr( wp_strip_all_tags( $p->get_name() ), 0, 40 ),
                $want, $meta['width'], $meta['height'], $got,
                $scene ? 'SCENE photo' : 'artwork' );
        }
        if ( $scene && count( $probe_ids ) < 40 ) $probe_ids[] = $aid;
    }
}

printf( "products priced from the card: %d  (%d have no titled size — not judged)\n",
    $stats['products'], $stats['no_size'] );
printf( "images measured: %d\n\n", $stats['images'] );
printf( "  right way round : %d\n", $stats['match'] );
printf( "  WRONG way round : %d\n", $stats['mismatch'] );
printf( "  square (neither): %d\n", $stats['square'] );
printf( "  unmeasurable    : %d\n\n", $stats['unknown'] );
printf( "  portrait product showing a landscape image: %d\n", $by_kind['portrait_product_landscape_image'] );
printf( "  landscape product showing a portrait image: %d\n\n", $by_kind['landscape_product_portrait_image'] );
printf( "of the wrong ones, %d are styled scene photos and %d are the artwork itself.\n",
    $scene_mismatch, $art_mismatch );
echo "That split matters: a scene is a flattened photo and cannot be fixed by\n";
echo "rotation; the artwork itself usually can.\n";

if ( $examples ) {
    echo "\nexamples:\n";
    foreach ( $examples as $e ) echo "  {$e}\n";
}

// ── can the framed art even be found inside a scene? ────────────────────
// This is the precondition for "rotate the art but not the background". If
// the rate is poor, that route is not viable unattended and the honest answer
// is to say so rather than to attempt it across hundreds of files.
echo "\n=== CAN THE ART BE LOCATED INSIDE A SCENE? ===\n";
if ( ! function_exists( 'imagecreatefromstring' ) ) {
    echo "GD unavailable — cannot probe.\n=== DONE ===\n";
    return;
}
if ( ! $probe_ids ) {
    echo "no mismatched scene photos to probe.\n=== DONE ===\n";
    return;
}

$found = $missed = 0;
$deadline = time() + 45;
foreach ( array_slice( $probe_ids, 0, $PROBE ) as $aid ) {
    if ( time() >= $deadline ) { echo "  (time budget reached)\n"; break; }
    $path = get_attached_file( $aid );
    if ( ! $path || ! file_exists( $path ) ) continue;
    $raw = @file_get_contents( $path );
    if ( $raw === false ) continue;
    $im = @imagecreatefromstring( $raw );
    if ( ! $im ) continue;

    // Same heuristic tools/make-wall-mockup.php uses when it has no explicit
    // box: a region markedly darker than, or different from, the wall colour.
    $W = imagesx( $im ); $H = imagesy( $im );
    $wr = $wg = $wb = $n = 0;
    for ( $x = (int) ( $W * 0.40 ); $x < (int) ( $W * 0.60 ); $x++ ) {
        $c = imagecolorat( $im, $x, (int) ( $H * 0.06 ) );
        $wr += ( $c >> 16 ) & 255; $wg += ( $c >> 8 ) & 255; $wb += $c & 255; $n++;
    }
    if ( ! $n ) { imagedestroy( $im ); continue; }
    $wall = array( $wr / $n, $wg / $n, $wb / $n );

    $minx = $W; $maxx = 0; $miny = $H; $maxy = 0; $hit = 0;
    $st = max( 1, (int) ( $W / 400 ) );
    for ( $y = (int) ( $H * 0.03 ); $y < (int) ( $H * 0.90 ); $y += $st ) {
        for ( $x = (int) ( $W * 0.05 ); $x < (int) ( $W * 0.95 ); $x += $st ) {
            $c = imagecolorat( $im, $x, $y );
            $r = ( $c >> 16 ) & 255; $g = ( $c >> 8 ) & 255; $b = $c & 255;
            $dark = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) < 70;
            $diff = ( abs( $r - $wall[0] ) + abs( $g - $wall[1] ) + abs( $b - $wall[2] ) ) > 110;
            if ( $dark || $diff ) {
                if ( $x < $minx ) $minx = $x; if ( $x > $maxx ) $maxx = $x;
                if ( $y < $miny ) $miny = $y; if ( $y > $maxy ) $maxy = $y;
                $hit++;
            }
        }
    }
    imagedestroy( $im );

    // A believable frame: enough hits, and a box that is neither a speck nor
    // most of the picture. A box covering 90% of the frame means the detector
    // locked onto the whole styled scene, which is a miss dressed as a hit.
    $area = ( $maxx > $minx && $maxy > $miny )
        ? ( ( $maxx - $minx ) * ( $maxy - $miny ) ) / ( $W * $H ) : 0;
    $ok = $hit >= 60 && $area > 0.03 && $area < 0.80;
    $ok ? $found++ : $missed++;
    printf( "  #%-7d %dx%d  box %.0f%% of the frame  %s\n", $aid, $W, $H, $area * 100,
        $ok ? 'located' : 'NOT located' );
}

$tot = $found + $missed;
if ( $tot ) {
    printf( "\nlocated in %d of %d probed (%.0f%%).\n", $found, $tot, 100 * $found / $tot );
    echo "Rotating the art without the background needs this to be near-perfect on\n";
    echo "every image, unattended. Read the rate above before choosing that route.\n";
}
echo "=== DONE ===\n";
