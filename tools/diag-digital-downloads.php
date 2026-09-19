<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Why the Digital Downloads page looks broken, answered from the live database.
 *
 * A screen recording of /product-category/digital-downloads-2/ showed four
 * things at once: cards carrying "5 sizes · 2 frames" and "From $80.00" for a
 * file, the same piece priced $80.00 on the card and $9.43 in the modal, a
 * blank preview pane with Add to Cart still live, and a sidebar offering canvas
 * sizes. Three of those are now fixed in the theme. The blank pane is the one
 * that cannot be settled from the repo: it depends on which images this
 * particular site generated, so this asks.
 *
 * For every product in the digital-download categories it prints what the
 * preview endpoint would actually return, and why.
 *
 * Read-only. Prints; changes nothing.
 * Run: wp eval-file tools/diag-digital-downloads.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

echo "=== WHICH DIGITAL-DOWNLOAD TERM IS LIVE ===\n";
$slugs = function_exists( 'af_digital_cat_slugs' )
    ? af_digital_cat_slugs()
    : array( 'digital-downloads', 'digital-downloads-2', 'instant-downloads', 'printable-art' );
echo "  theme's canonical list: " . implode( ', ', $slugs ) . "\n";
if ( ! function_exists( 'af_digital_cat_slugs' ) ) {
    echo "  !! af_digital_cat_slugs() MISSING — the theme running here predates the fix\n";
}
$live = array();
foreach ( $slugs as $slug ) {
    $t = get_term_by( 'slug', $slug, 'product_cat' );
    if ( ! $t ) { printf( "  %-22s (no such term)\n", $slug ); continue; }
    printf( "  %-22s term #%-6d %d products\n", $slug, $t->term_id, $t->count );
    if ( $t->count > 0 ) $live[] = $t;
}
if ( ! $live ) { echo "\n  Nothing to inspect: no digital-download term holds a product.\n=== DONE ===\n"; return; }

$ids = array();
foreach ( $live as $t ) {
    $ids = array_merge( $ids, get_posts( array(
        'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $t->term_id ) ),
    ) ) );
}
$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

echo "\n=== THE GATES, PER PRODUCT (" . count( $ids ) . ") ===\n";
echo "  digital = af_is_digital_download()   canvas = af_pricing_applies() (should be NO for a file)\n";
echo "  wm      = af_wm_applies()            card\$ = what the card prints   file\$ = what the modal sells\n\n";
foreach ( $ids as $pid ) {
    $p = wc_get_product( $pid );
    if ( ! $p ) continue;
    $dig = function_exists( 'af_is_digital_download' ) ? ( af_is_digital_download( $p ) ? 'yes' : 'NO' ) : '?';
    $can = function_exists( 'af_pricing_applies' )     ? ( af_pricing_applies( $p )     ? 'YES' : 'no' ) : '?';
    $wm  = function_exists( 'af_wm_applies' )          ? ( af_wm_applies( $p )          ? 'yes' : 'NO' ) : '?';
    $card = wp_strip_all_tags( $p->get_price_html() );
    $file = function_exists( 'af_digital_price' ) ? wc_price( af_digital_price( $pid ) ) : '?';
    printf( "  #%-6d %-40s digital=%-3s canvas=%-3s wm=%-3s\n", $pid,
        substr( wp_strip_all_tags( $p->get_name() ), 0, 40 ), $dig, $can, $wm );
    printf( "          card\$ %-28s file\$ %-10s  code %s\n",
        substr( $card, 0, 28 ), wp_strip_all_tags( $file ),
        get_post_meta( $pid, '_taf_art_code', true ) ?: '(none)' );
}

echo "\n=== WHAT THE PREVIEW ENDPOINT WOULD RETURN ===\n";
echo "  This is the blank pane. Each line is the first branch that answers.\n\n";
$blank = 0;
foreach ( $ids as $pid ) {
    $name  = substr( wp_strip_all_tags( get_the_title( $pid ) ), 0, 34 );
    $thumb = (int) get_post_thumbnail_id( $pid );
    if ( ! $thumb ) { printf( "  #%-6d %-34s BLANK — no featured image\n", $pid, $name ); $blank++; continue; }

    $wm = function_exists( 'af_wm_preview_url' ) ? af_wm_preview_url( $thumb ) : false;
    if ( $wm && ! empty( $wm['url'] ) ) {
        printf( "  #%-6d %-34s watermarked %dx%d\n", $pid, $name, $wm['width'], $wm['height'] );
        continue;
    }

    // Why did the watermarker decline? The endpoint cannot say; here we can.
    $why = array();
    if ( ! function_exists( 'imagecreatetruecolor' ) ) $why[] = 'GD missing';
    $src = get_attached_file( $thumb );
    if ( ! $src || ! file_exists( $src ) ) $why[] = 'master file missing';
    elseif ( ! is_readable( $src ) )       $why[] = 'master unreadable';
    else {
        $mb = round( filesize( $src ) / 1048576, 1 );
        $why[] = "master {$mb}MB (memory_limit " . ini_get( 'memory_limit' ) . ')';
    }
    $up  = wp_get_upload_dir();
    $dir = trailingslashit( $up['basedir'] ) . 'af-wm';
    if ( ! is_dir( $dir ) )        $why[] = 'af-wm/ does not exist';
    elseif ( ! is_writable( $dir ) ) $why[] = 'af-wm/ not writable';

    $master = wp_get_attachment_url( $thumb );
    $meta   = wp_get_attachment_metadata( $thumb );
    $sizes  = ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) ? array_keys( $meta['sizes'] ) : array();
    $pick   = '';
    foreach ( $sizes as $s ) {
        $src2 = wp_get_attachment_image_src( $thumb, $s );
        if ( $src2 && ! empty( $src2[0] ) && $src2[0] !== $master ) { $pick = $s; break; }
    }
    printf( "  #%-6d %-34s no watermark (%s)\n", $pid, $name, implode( '; ', $why ) );
    printf( "          generated sizes: %s\n", $sizes ? implode( ', ', $sizes ) : '(NONE — only the master exists)' );
    if ( $pick !== '' ) {
        printf( "          would serve '%s' instead — pane fills\n", $pick );
    } else {
        echo   "          nothing but the master — PANE STAYS BLANK\n";
        $blank++;
    }
}

echo "\n=== VERDICT ===\n";
printf( "  products inspected : %d\n", count( $ids ) );
printf( "  panes still blank  : %d\n", $blank );
if ( $blank ) {
    echo "  Fix by regenerating intermediate sizes for those attachments, e.g.\n";
    echo "    wp media regenerate --only-missing --yes\n";
    echo "  or by giving GD the memory to build the watermarked preview.\n";
} else {
    echo "  Every product can show a preview. The blank pane is resolved.\n";
}
echo "=== DONE ===\n";
