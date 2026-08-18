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
// This runs at the tail of a deploy, while the host is still busy, so a 508
// or 503 is the SERVER declining to render — not evidence about the row. Give
// it a couple of tries, and if the page never renders, say inconclusive
// rather than blaming the hooks for a page that was never built.
function afx_get( $url ) {
    $resp = null;
    for ( $i = 0; $i < 3; $i++ ) {
        $resp = wp_remote_get( $url, array( 'timeout' => 45, 'sslverify' => false,
            'headers' => array( 'User-Agent' => 'af-xsell-diag' ) ) );
        if ( is_wp_error( $resp ) ) { sleep( 4 ); continue; }
        $c = (int) wp_remote_retrieve_response_code( $resp );
        if ( ! in_array( $c, array( 508, 503, 429, 502, 504 ), true ) ) return $resp;
        sleep( 5 );
    }
    return $resp;
}

$ok = $missing = $unknown = 0;
foreach ( array_slice( $thin, 0, $FETCH ) as $t ) {
    $link = get_term_link( $t );
    if ( is_wp_error( $link ) ) { echo "  {$t->name}: no permalink\n"; continue; }
    $url  = add_query_arg( 'afnocache', time(), $link );
    $resp = afx_get( $url );
    if ( is_wp_error( $resp ) ) {
        $unknown++;
        echo sprintf( "  %-38s FETCH ERROR %s  (inconclusive)\n", $t->name, $resp->get_error_message() );
        continue;
    }

    $code = (int) wp_remote_retrieve_response_code( $resp );
    $html = wp_remote_retrieve_body( $resp );
    if ( $code !== 200 ) {
        $unknown++;
        echo sprintf( "  %-38s http %d  the host did not render the page (inconclusive)\n", $t->name, $code );
        continue;
    }
    $has  = strpos( $html, 'af-xsell' ) !== false;
    // how many cards the row actually put on the page
    $cards = 0;
    if ( $has && preg_match( '#<section class="af-xsell".*?</section>#s', $html, $m ) ) {
        $cards = preg_match_all( '#<li[^>]*class="[^"]*\bproduct\b#', $m[0] );
    }
    $has ? $ok++ : $missing++;
    echo sprintf( "  %-38s http %d  cross-sell: %s%s\n", $t->name, $code,
        $has ? 'PRESENT' : 'MISSING  <<<', $has ? "  ({$cards} cards)" : '' );

    // The sidebar rides on these same pages, so read it off the first one
    // that renders. The widget is filtered down to the header menu's nine
    // categories; printing what the page actually shipped is the only way to
    // know the filter reached the markup instead of just the code.
    if ( ! isset( $sidebar_done ) ) {
        $sidebar_done = true;
        // Non-greedy ".*?</ul>" stops at the FIRST closing tag, which with
        // nested subcategory lists is the end of the first child list — the
        // readout came back as one parent and its twenty children, looking
        // exactly like the other eight parents had vanished. Walk the tags
        // and close on the matching one instead.
        $block = '';
        $at = stripos( $html, 'product-categories' );
        if ( $at !== false ) {
            $open = strrpos( substr( $html, 0, $at ), '<ul' );
            if ( $open !== false ) {
                $depth = 0; $i = $open; $len = strlen( $html );
                while ( $i < $len ) {
                    if ( substr( $html, $i, 3 ) === '<ul' ) { $depth++; }
                    elseif ( substr( $html, $i, 5 ) === '</ul>' ) {
                        $depth--;
                        if ( $depth === 0 ) { $block = substr( $html, $open, $i + 5 - $open ); break; }
                    }
                    $i++;
                }
            }
        }
        if ( $block !== '' ) {
            // Walk the block so nesting shows: a parent and its children are
            // the point, and a flat list of names cannot tell them apart.
            preg_match_all( '#<ul\b|</ul>|<a[^>]*>(.*?)</a>#s', $block, $am, PREG_SET_ORDER );
            $depth = 0; $rows = array(); $parents = 0; $kids = 0;
            foreach ( $am as $m ) {
                if ( $m[0] === '</ul>' )      { $depth = max( 0, $depth - 1 ); continue; }
                if ( strpos( $m[0], '<ul' ) === 0 ) { $depth++; continue; }
                $n = trim( html_entity_decode( wp_strip_all_tags( isset( $m[1] ) ? $m[1] : '' ) ) );
                $n = trim( preg_replace( '/\s*\(\d+\)\s*$/', '', $n ) );   // drop the count
                if ( $n === '' ) continue;
                $depth > 1 ? $kids++ : $parents++;
                $rows[] = str_repeat( '    ', max( 0, $depth - 1 ) ) . $n;
            }
            echo "\nsidebar as delivered — {$parents} categories, {$kids} subcategories:\n";
            foreach ( $rows as $r ) echo "    {$r}\n";
        } else {
            echo "\nsidebar: no ul.product-categories in the page\n";
        }
    }
}

// ── the same question for a TAG-filtered listing ────────────────────────
// The "Filter by Tag" bar filters in place with ?product_tag=, so the page is
// still whatever archive the visitor was on. Picking a tag with only a couple
// of products is exactly the thin case, and the row is supposed to fill from
// that tag first. Whether it does is a question about the delivered page.
echo "\n=== TAG-FILTERED LISTINGS ===\n";
$tags = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => true ) );
if ( is_wp_error( $tags ) || ! $tags ) {
    echo "no product tags to check\n";
} else {
    $thin_tags = array();
    foreach ( $tags as $t ) if ( (int) $t->count < $MIN ) $thin_tags[] = $t;
    usort( $thin_tags, function ( $a, $b ) { return $a->count <=> $b->count; } );
    echo "tags total: " . count( $tags ) . "  |  thin: " . count( $thin_tags ) . "\n";

    $shop = get_permalink( wc_get_page_id( 'shop' ) );
    foreach ( array_slice( $thin_tags, 0, 2 ) as $t ) {
        $url  = add_query_arg( array( 'product_tag' => $t->slug, 'afnocache' => time() ), $shop );
        $resp = afx_get( $url );
        if ( is_wp_error( $resp ) ) {
            echo sprintf( "  %-30s FETCH ERROR (inconclusive)\n", $t->name ); continue;
        }
        $code = (int) wp_remote_retrieve_response_code( $resp );
        if ( $code !== 200 ) {
            echo sprintf( "  %-30s http %d — not rendered (inconclusive)\n", $t->name, $code ); continue;
        }
        $body  = wp_remote_retrieve_body( $resp );
        $has   = strpos( $body, 'af-xsell' ) !== false;
        $cards = 0; $head = '';
        if ( $has && preg_match( '#<section class="af-xsell".*?</section>#s', $body, $m ) ) {
            $cards = preg_match_all( '#<li[^>]*class="[^"]*\bproduct\b#', $m[0] );
            if ( preg_match( '#<h2>(.*?)</h2>#s', $m[0], $hm ) ) $head = trim( wp_strip_all_tags( $hm[1] ) );
        }
        // Naming the tag in the heading is the visible proof the row knew
        // WHICH tag was chosen — a generic heading means it fell through to
        // the old category path.
        $named = $head !== '' && stripos( $head, $t->name ) !== false;
        echo sprintf( "  %-30s %d product(s) | row: %s%s\n", $t->name, (int) $t->count,
            $has ? "PRESENT ({$cards} cards)" : 'MISSING  <<<',
            $has ? '  heading: "' . $head . '"' . ( $named ? '  [names the tag]' : '  [GENERIC — tag not used?]' ) : '' );
    }
}

echo "\npresent: {$ok}  missing: {$missing}  inconclusive: {$unknown}\n";
if ( $missing ) {
    echo "MISSING is the real signal: the page rendered (http 200) and the row\n";
    echo "was not in it — the archive hooks did not fire on that template, and\n";
    echo "the row needs a different injection point there.\n";
}
if ( $unknown ) {
    echo "INCONCLUSIVE pages never rendered (the host was busy), so they say\n";
    echo "nothing either way about the row.\n";
}
echo "=== DONE ===\n";
