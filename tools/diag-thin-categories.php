<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Lists the product categories that hold fewer than a full row of
 * cards, then FETCHES a few of them and reports whether the cross-sell row
 * actually rendered.
 *
 * The distinction matters: this theme builds parts of the page in Elementor,
 * which does not always fire WooCommerce's archive hooks. A cross-sell that
 * is correct in PHP and absent from the page is the same bug as no cross-sell
 * at all — and that is exactly how the struck-through price hid for three
 * deploys. So this checks the delivered HTML, not the code path.
 *
 * Makes no changes.
 *
 * Run: wp eval-file tools/diag-thin-categories.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$MIN   = function_exists( 'af_xsell_min_cards' ) ? af_xsell_min_cards() : 4;
$FETCH = 4;   // how many thin categories to actually load

echo "=== THIN CATEGORIES / CROSS-SELL ===\n";
echo "a category is 'thin' below {$MIN} cards\n";

if ( ! function_exists( 'af_xsell_render' ) ) {
    echo "WARNING: af_xsell_render() is missing — the child theme may not be active.\n";
}

$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
if ( is_wp_error( $terms ) ) { echo "term query failed: " . $terms->get_error_message() . "\n=== DONE ===\n"; return; }

$thin = array();
foreach ( $terms as $t ) {
    if ( $t->slug === 'uncategorized' ) continue;
    if ( (int) $t->count < $MIN ) $thin[] = $t;
}
usort( $thin, function ( $a, $b ) { return $a->count <=> $b->count; } );

echo "categories total: " . count( $terms ) . "  |  thin: " . count( $thin ) . "\n";
foreach ( $thin as $t ) {
    echo sprintf( "  %-38s %d product(s)  %s\n", $t->name, (int) $t->count, get_term_link( $t ) );
}

if ( ! $thin ) { echo "nothing thin to check.\n=== DONE ===\n"; return; }

echo "\nfetching " . min( $FETCH, count( $thin ) ) . " of them to see what the page actually delivers:\n";
$ok = $missing = 0;
foreach ( array_slice( $thin, 0, $FETCH ) as $t ) {
    $link = get_term_link( $t );
    if ( is_wp_error( $link ) ) { echo "  {$t->name}: no permalink\n"; continue; }
    $url  = add_query_arg( 'afnocache', time(), $link );
    $resp = wp_remote_get( $url, array( 'timeout' => 45, 'sslverify' => false,
        'headers' => array( 'User-Agent' => 'af-xsell-diag' ) ) );
    if ( is_wp_error( $resp ) ) { echo "  {$t->name}: FETCH ERROR " . $resp->get_error_message() . "\n"; continue; }

    $code = (int) wp_remote_retrieve_response_code( $resp );
    $html = wp_remote_retrieve_body( $resp );
    $has  = strpos( $html, 'af-xsell' ) !== false;
    // how many cards the row actually put on the page
    $cards = 0;
    if ( $has && preg_match( '#<section class="af-xsell".*?</section>#s', $html, $m ) ) {
        $cards = preg_match_all( '#<li[^>]*class="[^"]*\bproduct\b#', $m[0] );
    }
    $has ? $ok++ : $missing++;
    echo sprintf( "  %-38s http %d  cross-sell: %s%s\n", $t->name, $code,
        $has ? 'PRESENT' : 'MISSING  <<<', $has ? "  ({$cards} cards)" : '' );
}

echo "\npresent: {$ok}  missing: {$missing}\n";
if ( $missing ) {
    echo "MISSING means the archive hooks did not fire on those templates —\n";
    echo "the row needs a different injection point for them.\n";
}
echo "=== DONE ===\n";
