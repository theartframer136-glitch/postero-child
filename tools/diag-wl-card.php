<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * What does a "You may also like" card actually render?
 *
 * The cards in that row are missing rows the archive cards have — the art
 * code line and the colour/size strip — and guessing which of four plausible
 * causes it is has a deploy attached to every wrong guess. So: build one card
 * exactly the way the row builds it, and print what comes out.
 *
 * Run: wp eval-file tools/diag-wl-card.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$picks = wc_get_products( array( 'status' => 'publish', 'limit' => 3,
                                 'orderby' => 'popularity', 'visibility' => 'catalog' ) );
if ( ! $picks ) { echo "no products\n"; return; }

foreach ( $picks as $p ) {
    $id = $p->get_id();
    echo "=== #{$id}  " . substr( wp_strip_all_tags( $p->get_name() ), 0, 60 ) . "\n";
    echo "   type            : " . $p->get_type() . "\n";
    echo "   purchasable     : " . ( $p->is_purchasable() ? 'yes' : 'NO' ) . "\n";
    echo "   pricing_applies : " . ( function_exists('af_pricing_applies')
                                     ? ( af_pricing_applies( $p ) ? 'yes' : 'NO' ) : '(fn missing)' ) . "\n";
    echo "   art code        : " . ( function_exists('af_get_art_code')
                                     ? ( af_get_art_code( $p ) !== '' ? af_get_art_code( $p ) : '(none)' ) : '(fn missing)' ) . "\n";
    $vars = function_exists('af_card_vars_html') ? af_card_vars_html( $p ) : '(fn missing)';
    echo "   card_vars_html  : " . ( $vars === '' ? '(EMPTY STRING)' : substr( preg_replace( '/\s+/', ' ', $vars ), 0, 160 ) ) . "\n";
    echo "\n";
}

// Now render one card through the same path the row uses, and show which of
// our rows survived into the markup.
$p    = $picks[0];
$post = get_post( $p->get_id() );
$keep = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

foreach ( array( 'as the row does it' => false, 'with wc_setup_product_data' => true ) as $label => $setup ) {
    unset( $GLOBALS['product'] );
    $GLOBALS['post'] = $post;
    setup_postdata( $GLOBALS['post'] );
    if ( $setup && function_exists( 'wc_setup_product_data' ) ) wc_setup_product_data( $post );

    ob_start();
    echo '<ul class="products">';
    wc_get_template_part( 'content', 'product' );
    echo '</ul>';
    $html = ob_get_clean();

    echo "--- {$label} ---\n";
    echo "   global \$product set : " . ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product
                                          ? '#' . $GLOBALS['product']->get_id() : 'NO' ) . "\n";
    foreach ( array( 'af-art-code', 'af-card-vars', 'af-card-from', 'af-acts',
                     'star-rating', 'product-action', 'price' ) as $needle ) {
        echo "   " . str_pad( $needle, 16 ) . ( strpos( $html, $needle ) !== false ? 'present' : '—' ) . "\n";
    }
    echo "   bytes: " . strlen( $html ) . "\n\n";
}

$GLOBALS['post'] = $keep;
wp_reset_postdata();

echo "=== page gates ===\n";
echo "  af_is_wishlist_page() exists : " . ( function_exists('af_is_wishlist_page') ? 'yes' : 'no' ) . "\n";

echo "=== gates as the wishlist page sees them ===\n";
echo "  af_cards_secondary_page() exists : " . ( function_exists('af_cards_secondary_page') ? 'yes' : 'NO' ) . "\n";

echo "\n=== the ajax payload the card injector reads ===\n";
$_POST['ids'] = array_map( function( $x ) { return $x->get_id(); }, array_slice( $picks, 0, 2 ) );
ob_start();
af_card_variations_handler();
$json = ob_get_clean();
$d = json_decode( $json, true );
if ( ! $d || empty( $d['success'] ) ) { echo "  payload did not decode\n"; }
else {
    echo "  label : " . ( $d['data']['meta']['label'] ?? '(none)' ) . "\n";
    foreach ( $d['data']['items'] as $id => $i ) {
        echo "  #{$id}  ok=" . ( $i['ok'] ?? '?' )
           . "  from=" . ( $i['from'] ?? '-' )
           . "  code=" . ( isset($i['code']) ? ( $i['code'] !== '' ? $i['code'] : '(empty)' ) : 'KEY MISSING' ) . "\n";
    }
}
