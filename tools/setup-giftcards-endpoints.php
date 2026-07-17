<?php
/**
 * Creates the purchasable Gift Card product, links it from the Gift Cards
 * page, and flushes rewrite rules so the new My Account endpoints
 * (/messages/, /returns/) resolve. Idempotent.
 * Run: wp eval-file tools/setup-giftcards-endpoints.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

echo "=== Gift card product + account endpoints ===\n";

/* 1. Gift Card product */
$existing = (int) get_option( 'af_gc_product_id', 0 );
if ( $existing && get_post_status( $existing ) === 'publish' ) {
    echo "SKIP  gift card product already exists (#{$existing})\n";
} else {
    $found = get_posts( array(
        'post_type'   => 'product',
        'post_status' => array( 'publish', 'draft' ),
        'name'        => 'the-art-framer-gift-card',
        'numberposts' => 1,
    ) );
    if ( $found ) {
        $pid = $found[0]->ID;
        wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
        echo "FOUND existing gift card product (#{$pid}) — republished\n";
    } else {
        $pid = wp_insert_post( array(
            'post_title'   => 'The Art Framer Gift Card',
            'post_name'    => 'the-art-framer-gift-card',
            'post_status'  => 'publish',
            'post_type'    => 'product',
            'post_excerpt' => 'Give the gift of art. Choose an amount, add a personal message, and we email a beautifully designed gift card — instantly redeemable on any product. Never expires; unused balance stays on the card.',
            'post_content' => '<h3>How it works</h3><p>Choose an amount above, tell us who it is for, and add a personal message. The gift card code is emailed to the recipient (or to you) within a few hours of purchase.</p>
<h3>Good to know</h3><ul><li>Never expires</li><li>Works on every product, including custom prints and framing</li><li>Unused balance stays on the card for next time</li><li>Combinable with sale prices</li></ul>
<p>Full details: <a href="/gift-cards/">Gift Cards</a></p>',
        ) );
        if ( is_wp_error( $pid ) ) { echo "ERROR creating product: " . $pid->get_error_message() . "\n"; return; }
        echo "CREATE gift card product -> #{$pid}\n";
    }

    wp_set_object_terms( $pid, 'simple', 'product_type' );
    update_post_meta( $pid, '_virtual', 'yes' );
    update_post_meta( $pid, '_downloadable', 'no' );
    update_post_meta( $pid, '_sold_individually', 'no' );
    update_post_meta( $pid, '_regular_price', '50' );
    update_post_meta( $pid, '_price', '50' );
    update_post_meta( $pid, '_manage_stock', 'no' );
    update_post_meta( $pid, '_stock_status', 'instock' );
    update_post_meta( $pid, '_af_is_gift_card', 'yes' );
    update_option( 'af_gc_product_id', $pid );
    echo "SET   af_gc_product_id = {$pid}\n";

    /* Give it the gift-card image if one exists in the media library */
    if ( ! get_post_thumbnail_id( $pid ) ) {
        $img = get_posts( array( 'post_type' => 'attachment', 'numberposts' => 1, 's' => 'gift', 'fields' => 'ids' ) );
        if ( $img ) { set_post_thumbnail( $pid, $img[0] ); echo "IMG   featured image set from media library\n"; }
    }
}

/* 2. Link the Gift Cards page CTA to the product */
$pid = (int) get_option( 'af_gc_product_id', 0 );
if ( $pid ) {
    $page = get_page_by_path( 'gift-cards' );
    if ( $page ) {
        $url  = get_permalink( $pid );
        $body = $page->post_content;
        if ( strpos( $body, 'af-gc-buy-btn' ) === false ) {
            $cta = '<p style="text-align:center;margin:26px 0 6px;"><a class="taf-btn af-gc-buy-btn" href="' . esc_url( $url ) . '">🎁 Buy a Gift Card Now</a></p>';
            $body = preg_replace( '/(<div class="taf-section">\s*<h2 class="taf-h2">Choose Your Amount<\/h2>)/', '$1' . $cta, $body, 1 );
            if ( $body !== $page->post_content ) {
                wp_update_post( array( 'ID' => $page->ID, 'post_content' => $body ) );
                echo "LINK  Gift Cards page -> product CTA added\n";
            }
        } else {
            echo "SKIP  Gift Cards page already links the product\n";
        }
    }
}

/* 3. Flush rewrites so /my-account/messages/ and /my-account/returns/ work */
flush_rewrite_rules( false );
echo "FLUSH rewrite rules (My Account endpoints: messages, returns)\n";

echo "=== DONE ===\n";
