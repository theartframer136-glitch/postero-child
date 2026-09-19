<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Product URLs that were cut off mid-word at import.
 *
 * A screen recording hovered two products on the Digital Downloads page and
 * left the browser's status bar showing:
 *
 *   /product/sacred-kedarnath-temple-with-lord-shiva-wall-art-72-24-inches-divine-h/
 *   /product/divine-swaminarayan-temple-golden-murti-wall-art-36-48-inches-sacred-r/
 *
 * "divine-h" is "Divine Himalayan" and "sacred-r" is "Sacred & Radiant", both
 * chopped in the middle of a word. 49 products across the catalogue carry a
 * slug of EXACTLY 70 characters — the signature of an importer cap; nothing in
 * this theme truncates a slug, so the cut happened before the data arrived. Of
 * those 49, 35 land inside a word and are what this fixes; the other 14 happen
 * to stop on a word boundary, which is untidy rather than broken, and are left
 * alone.
 *
 * These are not dead links — the slug in the database IS the truncated one, so
 * they resolve. They are mangled ones, which is what a customer sees when they
 * share or read a URL.
 *
 * What this does, per product:
 *   - rebuilds the slug from the product's own title, cut only at a WORD
 *     boundary, with "72 x 24 Inches" folded to "72x24-inches" to buy room
 *   - keeps the old slug in _wp_old_slug, which is what WordPress core's
 *     wp_old_slug_redirect() reads to 301 the old URL to the new one. Anything
 *     already shared, indexed or bookmarked keeps working.
 *
 * Writes post_name directly rather than through wp_update_post(). Same reason
 * tools/enable-digital-downloads.php avoids WC_Product::save(): an update on a
 * live product fires the whole save_post chain, and this job has no business
 * triggering price, SKU or sync recalculation across the catalogue.
 *
 * DRY BY DEFAULT — it changes public URLs, so it prints the plan and stops.
 *   wp eval-file tools/fix-truncated-slugs.php --allow-root            (preview)
 *   APPLY=1 wp eval-file tools/fix-truncated-slugs.php --allow-root    (write)
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$apply = getenv( 'APPLY' ) === '1';
$limit = (int) ( getenv( 'SLUG_MAX' ) ?: 80 );   // characters, cut at a word boundary

/** Words that carry no meaning in a URL. Nothing descriptive is ever dropped. */
function af_slug_stopwords() {
    return array( 'with', 'the', 'and', 'for', 'of', 'a', 'an', 'your', 'to', 'in', 'on' );
}

/**
 * A readable slug built from the title.
 *
 * The one rule that matters: never end inside a word. Everything else here
 * exists to make the cut land later — folding dimensions, dropping stopwords,
 * collapsing a word repeated back to back ("...-print-print-...").
 */
function af_clean_slug( $title, $limit ) {
    $s = sanitize_title( $title );

    // "36-x-60" and "36-60-inches" both mean 36x60. Folding them frees 3-4
    // characters right where these titles are longest.
    $s = preg_replace( '/(\d+)-x-(\d+)/', '$1x$2', $s );
    $s = preg_replace( '/(\d+)-(\d+)-inches/', '$1x$2-inches', $s );
    $s = preg_replace( '/(\d+)-(\d+)-ft\b/', '$1x$2-ft', $s );

    $stop  = af_slug_stopwords();
    $parts = array();
    foreach ( explode( '-', $s ) as $p ) {
        if ( $p === '' || in_array( $p, $stop, true ) ) continue;
        if ( $parts && end( $parts ) === $p ) continue;   // "print-print"
        $parts[] = $p;
    }
    $s = implode( '-', $parts );
    if ( strlen( $s ) <= $limit ) return $s;

    $cut = substr( $s, 0, $limit );
    $pos = strrpos( $cut, '-' );
    if ( $pos !== false ) $cut = substr( $cut, 0, $pos );
    return trim( $cut, '-' );
}

global $wpdb;
// Every product, whatever its status. Only published ones are rewritten, but a
// draft holds its slug just as firmly, so all of them have to be counted when
// checking that a rebuilt slug is free.
$rows = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts}
      WHERE post_type = 'product' AND post_status NOT IN ('trash','auto-draft','inherit')
      ORDER BY ID"
);

echo $apply ? "=== FIXING TRUNCATED PRODUCT SLUGS ===\n" : "=== DRY RUN — nothing is written ===\n";
echo "  word-boundary limit: {$limit} characters (override with SLUG_MAX)\n";
echo "  products examined  : " . count( $rows ) . " (all statuses; only published are rewritten)\n\n";

// Every slug in use, so a rebuilt one can never land on another product's URL.
$taken = array();
foreach ( $rows as $r ) $taken[ $r->post_name ] = (int) $r->ID;

$plan = array();
foreach ( $rows as $r ) {
    if ( $r->post_status !== 'publish' ) continue;   // counted above, not rewritten
    // Compare against the RAW slug WordPress would have made from this title —
    // not against af_clean_slug(), which folds dimensions and drops stopwords.
    // The importer had none of those rules, so measuring its output against
    // ours finds no prefix and every truncated slug slips through.
    $raw = sanitize_title( $r->post_title );
    if ( $r->post_name === $raw ) continue;                       // already whole
    if ( strpos( $raw, $r->post_name ) !== 0 ) continue;          // not a cut of this title

    // A cut is only a defect if it lands INSIDE a word. The character the
    // stored slug stopped before tells us: a '-' there means it ended tidily
    // on a word boundary and is nobody's problem.
    $next = substr( $raw, strlen( $r->post_name ), 1 );
    if ( $next === '' || $next === '-' ) continue;

    $new = af_clean_slug( $r->post_title, $limit );
    if ( $new === '' || $new === $r->post_name ) continue;
    $base = $new; $n = 2;
    while ( isset( $taken[ $new ] ) && $taken[ $new ] !== (int) $r->ID ) { $new = $base . '-' . $n; $n++; }
    $taken[ $new ] = (int) $r->ID;
    $plan[] = array( 'id' => (int) $r->ID, 'old' => $r->post_name, 'new' => $new, 'title' => $r->post_title );
}

if ( ! $plan ) { echo "  Nothing to do: no product slug ends mid-word.\n=== DONE ===\n"; return; }

$done = 0;
foreach ( $plan as $p ) {
    printf( "  #%-7d %s\n", $p['id'], substr( wp_strip_all_tags( $p['title'] ), 0, 74 ) );
    printf( "      old (%2d) %s\n", strlen( $p['old'] ), $p['old'] );
    printf( "      new (%2d) %s\n", strlen( $p['new'] ), $p['new'] );
    if ( ! $apply ) { echo "\n"; continue; }

    $ok = $wpdb->update( $wpdb->posts, array( 'post_name' => $p['new'] ), array( 'ID' => $p['id'] ) );
    if ( $ok === false ) { echo "      !! DB update failed — left as it was\n\n"; continue; }

    // The redirect. Core's wp_old_slug_redirect() 301s any request for a slug
    // listed here, so every old URL keeps resolving to the product.
    $existing = get_post_meta( $p['id'], '_wp_old_slug' );
    if ( ! in_array( $p['old'], (array) $existing, true ) ) {
        add_post_meta( $p['id'], '_wp_old_slug', $p['old'] );
    }
    clean_post_cache( $p['id'] );
    if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients( $p['id'] );
    $done++;
    echo "      redirect kept: /product/{$p['old']}/ -> /product/{$p['new']}/\n\n";
}

echo "=== SUMMARY ===\n";
printf( "  slugs %s : %d\n", $apply ? 'rewritten' : 'that would change', count( $plan ) );
if ( $apply ) {
    printf( "  written          : %d\n", $done );
    echo "  Old URLs 301 to the new ones via _wp_old_slug (WordPress core).\n";
    echo "  Flush permalinks if anything looks stale: wp rewrite flush --allow-root\n";
} else {
    echo "\n  Nothing was written. To apply:\n";
    echo "    APPLY=1 wp eval-file tools/fix-truncated-slugs.php --allow-root\n";
}
echo "=== DONE ===\n";
