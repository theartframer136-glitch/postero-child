<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Put brochure pages that no product sells into the shop.
 *
 * The caption audit left one open question the book cannot answer: 156 of the
 * 373 artwork pages have no product behind them. The shop prints them and does
 * not sell them. Still Life is the worst of it — 17 of 23 pages unclaimed.
 *
 * This creates a product for each row of a CSV whose columns come straight off
 * the brochure page: art code, page number, the artwork's name, the caption it
 * carries, the sizes the page advertises.
 *
 * What it will NOT do, and why:
 *
 *   - It never invents a price. The price is read from the theme's own rate
 *     card, af_pricing_config()['sizes'], for the size the product is titled
 *     as. A size the card does not list is refused, not guessed — a wrong
 *     price travels onto an invoice.
 *   - It never creates a product without an image. An imageless product is
 *     what tools/draft-imageless.php exists to clean up afterwards; better not
 *     to make one. The artwork is found in the Media Library by its art code,
 *     which is how the source files were named before upload. No match, no
 *     product — the row is reported and skipped.
 *   - It never creates a taxonomy term. Every category and subcategory must
 *     already exist by name, or the row is refused. A typo here would spawn a
 *     duplicate category on a live shop.
 *   - It never touches a product that already exists. A page is "claimed" when
 *     some product carries its art code, compared on letters and digits only
 *     so that "SL - 150002-4030" and "SL-150002-4030" are the same code.
 *
 * Products are created as DRAFT so they can be read before anyone can buy
 * them. STATUS=publish overrides that deliberately.
 *
 * DRY BY DEFAULT:
 *   wp eval-file tools/add-brochure-products.php --allow-root                 (plan)
 *   APPLY=1 wp eval-file tools/add-brochure-products.php --allow-root         (create drafts)
 *   APPLY=1 STATUS=publish wp eval-file ... --allow-root                      (create live)
 *   CSV=tools/brochure-products-wildlife.csv APPLY=1 wp eval-file ...         (another section)
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$apply  = getenv( 'APPLY' ) === '1';
$status = getenv( 'STATUS' ) === 'publish' ? 'publish' : 'draft';
$csv    = getenv( 'CSV' ) ?: 'wp-content/themes/postero-child/tools/brochure-products-still-life.csv';
if ( ! file_exists( $csv ) ) {
    $alt = dirname( __FILE__ ) . '/' . basename( $csv );
    if ( file_exists( $alt ) ) { $csv = $alt; }
}
if ( ! file_exists( $csv ) ) { echo "No such CSV: {$csv}\n"; exit(1); }

/** Letters and digits only, upper case: the one form two spellings of a code share. */
function af_abp_key( $s ) {
    return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $s ) );
}

/** The art code in the spelling this shop stores: "SL-150002-4030" -> "SL - 150002-4030". */
function af_abp_store_code( $code ) {
    $code = trim( (string) $code );
    return preg_match( '/^([A-Za-z]+)-(\d{4,6})(.*)$/', $code, $m )
        ? strtoupper( $m[1] ) . ' - ' . $m[2] . $m[3]
        : $code;
}

/** "3×5 ft (36×60 in)" -> "3×5 Feet", the size as the shop writes it in a title. */
function af_abp_title_size( $label ) {
    return preg_match( '/^(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?)\s*ft/u', $label, $m )
        ? $m[1] . '×' . $m[2] . ' Feet' : '';
}

/**
 * The Media Library attachment holding this artwork, or 0.
 *
 * The source files were renamed with their art code before upload, so the code
 * is in the filename — in whatever punctuation the person used that day
 * ("SL-150002-4030", "SL_150002_4030", "SL 150002 4030"). Comparing on letters
 * and digits alone makes all of those the same string.
 */
function af_abp_find_image( $code ) {
    global $wpdb;
    static $files = null;
    if ( $files === null ) {
        $files = array();
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" );
        foreach ( $rows as $r ) {
            $base = pathinfo( (string) $r->meta_value, PATHINFO_FILENAME );
            $files[] = array( (int) $r->post_id, af_abp_key( $base ) );
        }
    }
    $want = af_abp_key( $code );
    if ( $want === '' ) return 0;
    foreach ( $files as $f ) {
        if ( strpos( $f[1], $want ) !== false ) return $f[0];
    }
    return 0;
}

/** Term ids for a list of category names. Missing names are reported, never created. */
function af_abp_terms( $names, &$missing ) {
    $ids = array();
    foreach ( $names as $name ) {
        $name = trim( $name );
        if ( $name === '' ) continue;
        $t = get_term_by( 'name', $name, 'product_cat' );
        if ( ! $t ) { $t = get_term_by( 'slug', sanitize_title( $name ), 'product_cat' ); }
        if ( ! $t ) { $missing[] = $name; continue; }
        $ids[] = (int) $t->term_id;
    }
    return array_values( array_unique( $ids ) );
}

/** The product description, in the same shape the shop's other canvas prints use. */
function af_abp_description( $name, $caption, $sizes, $page ) {
    $n = esc_html( $name );
    $c = esc_html( rtrim( trim( $caption ), '.' ) );
    $s = esc_html( $sizes );
    return "<h3>{$n} — Premium Digital Canvas Print</h3>\n"
         . "<p>{$c}.</p>\n"
         . "<p>Printed with archival pigment inks on premium cotton-blend canvas, so the colours stay"
         . " true for decades. Finished with a floating frame and delivered ready to hang.</p>\n"
         . "<h4>Product Highlights</h4>\n<ul>\n"
         . "<li>Premium cotton-blend canvas — soft texture, gallery quality</li>\n"
         . "<li>Fade-resistant archival inks</li>\n"
         . "<li>Floating frame, ready to hang out of the box</li>\n"
         . "<li>Sizes shown in our catalogue: {$s}</li>\n"
         . "<li>Catalogue page {$page}</li>\n</ul>\n"
         . "<h4>Gifting</h4>\n"
         . "<p>An elegant gift for a housewarming, a wedding or any occasion that deserves something"
         . " lasting. Ships in protective packaging.</p>";
}

/**
 * PROBE=1 — answer "is the artwork missing, or is the lookup wrong?"
 *
 * The first dry run refused all 17 rows for want of an image. All of them
 * failing the same way is as likely to mean this tool looks for the wrong
 * string as it is to mean nobody uploaded the files, and those two conclusions
 * lead opposite ways. So before anyone is told to upload 17 files, this prints
 * what the naming convention on this site actually is:
 *
 *   - the filename of the featured image on every product that already carries
 *     a code from the same section, which IS the convention, whatever it is
 *   - for each wanted code, how far a match gets: the whole code, then the code
 *     without its trailing size group, then just the section and serial
 *   - the nearest filenames, so a near miss is visible rather than inferred
 *
 * Reads only. Creates nothing.
 */
function af_abp_probe( $rows ) {
    global $wpdb;
    $atts = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" );
    $files = array();
    foreach ( $atts as $a ) {
        $base = pathinfo( (string) $a->meta_value, PATHINFO_FILENAME );
        $files[] = array( (int) $a->post_id, $base, af_abp_key( $base ) );
    }
    echo "=== PROBE — reads only, creates nothing ===\n";
    echo "  attachments with a file: " . count( $files ) . "\n\n";

    // What do the products that DO carry a code from this section look like?
    $prefix = strtoupper( substr( trim( $rows[0]['art_code'] ), 0, 2 ) );
    echo "=== HOW {$prefix} PRODUCTS THAT ALREADY EXIST NAME THEIR IMAGE ===\n";
    $seen = 0;
    foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any',
        'posts_per_page' => -1, 'fields' => 'ids' ) ) as $pid ) {
        $code = trim( (string) get_post_meta( $pid, '_taf_art_code', true ) );
        if ( $code === '' || stripos( $code, $prefix ) !== 0 ) continue;
        $thumb = (int) get_post_thumbnail_id( $pid );
        $f = $thumb ? pathinfo( (string) get_post_meta( $thumb, '_wp_attached_file', true ), PATHINFO_FILENAME ) : '(no featured image)';
        printf( "  #%-7d %-22s %s\n", $pid, $code, $f );
        $seen++;
    }
    if ( ! $seen ) echo "  (no product carries a {$prefix} code at all)\n";

    echo "\n=== HOW CLOSE DOES EACH WANTED CODE GET ===\n";
    foreach ( $rows as $row ) {
        $code = trim( $row['art_code'] );
        $full = af_abp_key( $code );                                  // SL1500024030
        $noqty = preg_match( '/^([A-Za-z]+)-(\d{4,6})/', $code, $m )   // SL150002
            ? af_abp_key( $m[1] . $m[2] ) : $full;
        $stem = substr( $noqty, 0, strlen( $noqty ) - 2 );            // SL1500
        printf( "  %-16s (p%s)\n", $code, $row['page'] );
        foreach ( array( 'whole code' => $full, 'without size' => $noqty, 'section+serial' => $stem ) as $what => $needle ) {
            $hits = array();
            foreach ( $files as $f ) {
                if ( $needle !== '' && strpos( $f[2], $needle ) !== false ) { $hits[] = $f; }
                if ( count( $hits ) >= 4 ) break;
            }
            printf( "      %-15s %-14s %s\n", $what, $needle,
                $hits ? '' : '(nothing)' );
            foreach ( $hits as $h ) printf( "          #%-7d %s\n", $h[0], $h[1] );
            if ( $hits ) break;   // the tightest match that finds anything is the answer
        }
    }
    // If no filename carries a code, the only other way to find the right
    // picture is to look at the pictures. That is only tractable if the pool
    // is small, so size it: images no product uses at all.
    $used = array();
    foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any',
        'posts_per_page' => -1, 'fields' => 'ids' ) ) as $pid ) {
        $t = (int) get_post_thumbnail_id( $pid );
        if ( $t ) { $used[ $t ] = 1; }
        $gal = (string) get_post_meta( $pid, '_product_image_gallery', true );
        foreach ( array_filter( array_map( 'intval', explode( ',', $gal ) ) ) as $g ) { $used[ $g ] = 1; }
    }
    $free = array();
    foreach ( $files as $f ) { if ( empty( $used[ $f[0] ] ) ) { $free[] = $f; } }

    echo "\n=== HOW BIG IS THE POOL TO MATCH BY EYE ===\n";
    printf( "  attachments in all      : %d\n", count( $files ) );
    printf( "  used by some product    : %d\n", count( $used ) );
    printf( "  used by no product      : %d\n", count( $free ) );
    echo "\n  a sample of the unused ones:\n";
    $n = 0;
    foreach ( $free as $f ) {
        $m = wp_get_attachment_metadata( $f[0] );
        printf( "    #%-7d %-44s %s\n", $f[0], substr( $f[1], 0, 44 ),
            ! empty( $m['width'] ) ? "{$m['width']}x{$m['height']}" : '?' );
        if ( ++$n >= 25 ) break;
    }
    echo "=== DONE ===\n";
}

// ── Read the CSV ────────────────────────────────────────────────────────────
$fh   = fopen( $csv, 'r' );
$head = fgetcsv( $fh );
$rows = array();
while ( ( $r = fgetcsv( $fh ) ) !== false ) {
    if ( count( array_filter( $r, 'strlen' ) ) === 0 ) continue;
    $rows[] = array_combine( $head, array_pad( array_slice( $r, 0, count( $head ) ), count( $head ), '' ) );
}
fclose( $fh );

if ( getenv( 'PROBE' ) === '1' ) { af_abp_probe( $rows ); return; }

echo $apply ? "=== ADDING BROCHURE PRODUCTS ===\n" : "=== DRY RUN — nothing is created ===\n";
echo "  source : {$csv}\n";
echo "  rows   : " . count( $rows ) . "\n";
echo "  status : {$status}" . ( $status === 'draft' ? "  (review in Products -> Drafts, then publish)" : "  (LIVE on the shop)" ) . "\n\n";

// Every art code already on a product, so a page counts as claimed once.
$claimed = array();
foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any',
    'posts_per_page' => -1, 'fields' => 'ids' ) ) as $pid ) {
    $c = af_abp_key( get_post_meta( $pid, '_taf_art_code', true ) );
    if ( $c !== '' ) { $claimed[ $c ] = (int) $pid; }
}
echo "  art codes already on a product: " . count( $claimed ) . "\n\n";

$card    = af_pricing_config()['sizes'];
$created = $skipped = $refused = 0;

foreach ( $rows as $row ) {
    $code = trim( $row['art_code'] );
    $name = trim( $row['name'] );
    echo "  {$code}  p{$row['page']}  {$name}\n";

    $key = af_abp_key( $code );
    if ( isset( $claimed[ $key ] ) ) {
        echo "      SKIP — already sold as product #{$claimed[$key]}\n\n"; $skipped++; continue;
    }

    $size = trim( $row['size_label'] );
    if ( ! isset( $card[ $size ] ) ) {
        echo "      REFUSED — '{$size}' is not on the rate card, so there is no price to use\n\n";
        $refused++; continue;
    }
    $price = (float) $card[ $size ];
    $tsize = af_abp_title_size( $size );
    $title = $name . ' Canvas Wall Art ' . $tsize
           . ' – Floating Frame – Premium Digital Canvas Print – Living Room & Home Décor';

    // The page may advertise a size the shop does not make; say so rather than
    // quietly selling a different shape. Compared as feet, in either
    // orientation, on the same 0.26 ft tolerance af_size_label_for_product()
    // uses — the rate card lists one label per area, so a 5x3 portrait and a
    // 3x5 landscape are the same entry and must not be reported as a mismatch.
    $adv = trim( $row['brochure_sizes'] );
    if ( $adv !== '' && preg_match( '/^(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?)/u', $tsize, $tm ) ) {
        $hit = false;
        if ( preg_match_all( '/(\d+(?:\.\d+)?)\s*ft[^0-9]+(\d+(?:\.\d+)?)\s*ft/i', $adv, $am, PREG_SET_ORDER ) ) {
            foreach ( $am as $a ) {
                $x = (float) $a[1]; $y = (float) $a[2];
                $u = (float) $tm[1]; $v = (float) $tm[2];
                if ( ( abs( $x - $u ) < 0.26 && abs( $y - $v ) < 0.26 )
                  || ( abs( $y - $u ) < 0.26 && abs( $x - $v ) < 0.26 ) ) { $hit = true; break; }
            }
        }
        if ( ! $hit ) {
            echo "      note: page advertises {$adv} — the shop makes neither, so this is\n";
            echo "            priced and titled as {$size}, the nearest size on the rate card\n";
        }
    }

    $missing = array();
    $terms = af_abp_terms( array_merge(
        explode( '|', $row['categories'] ), array( $row['subcategory'] ) ), $missing );
    if ( $missing ) {
        echo "      REFUSED — no such category: " . implode( ', ', $missing ) . "\n\n";
        $refused++; continue;
    }

    $img = af_abp_find_image( $code );
    if ( ! $img ) {
        echo "      REFUSED — no image in the Media Library whose filename carries {$code}\n\n";
        $refused++; continue;
    }
    $m = wp_get_attachment_metadata( $img );
    $dim = ( ! empty( $m['width'] ) ) ? "{$m['width']}x{$m['height']}" : 'size unknown';

    $sku = function_exists( 'af_sku_code_part' ) ? af_sku_code_part( $code ) : strtoupper( $code );
    if ( $sku !== '' && wc_get_product_id_by_sku( $sku ) ) {
        echo "      REFUSED — SKU {$sku} is already in use\n\n"; $refused++; continue;
    }

    printf( "      price \$%s (%s, from the rate card)   image #%d %s   sku %s\n",
        number_format( $price, 2 ), $size, $img, $dim, $sku );
    echo "      title {$title}\n";

    if ( ! $apply ) { echo "\n"; continue; }

    $p = new WC_Product_Simple();
    $p->set_name( $title );
    $p->set_status( $status );
    $p->set_catalog_visibility( 'visible' );
    $p->set_short_description( trim( $row['caption'] ) );
    $p->set_description( af_abp_description( $name, $row['caption'], $adv, $row['page'] ) );
    $p->set_regular_price( (string) $price );
    $p->set_sku( $sku );
    $p->set_manage_stock( false );
    $p->set_stock_status( 'instock' );
    $p->set_category_ids( $terms );
    $p->set_image_id( $img );
    $pid = $p->save();

    if ( ! $pid ) { echo "      !! save failed — nothing created\n\n"; $refused++; continue; }

    update_post_meta( $pid, '_taf_art_code', af_abp_store_code( $code ) );
    update_post_meta( $pid, '_taf_brochure_page', (int) $row['page'] );
    $claimed[ $key ] = (int) $pid;
    $created++;
    echo "      created #{$pid} ({$status})\n\n";
}

echo "=== SUMMARY ===\n";
printf( "  %s : %d\n", $apply ? 'created' : 'would create',
    $apply ? $created : count( $rows ) - $skipped - $refused );
printf( "  already sold  : %d\n", $skipped );
printf( "  refused       : %d\n", $refused );
if ( $apply && $created ) {
    echo "  Created as {$status}." . ( $status === 'draft'
        ? " Review them in Products -> Drafts and publish when they read right.\n" : "\n" );
    if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients(); }
} elseif ( ! $apply ) {
    echo "\n  Nothing was created. To create them as drafts:\n";
    echo "    APPLY=1 wp eval-file tools/add-brochure-products.php --allow-root\n";
}
echo "=== DONE ===\n";
