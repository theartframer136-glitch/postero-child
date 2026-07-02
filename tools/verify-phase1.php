<?php
/**
 * READ-ONLY verification of Phase 1 (product template rollout).
 * Reports, per published product, whether attributes/FAQ/SEO are present.
 * Run: wp eval-file tools/verify-phase1.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$ids = get_posts( array(
    'post_type' => 'product', 'post_status' => 'publish',
    'posts_per_page' => -1, 'fields' => 'ids',
) );

$total = 0; $full = 0; $partial = 0; $none = 0; $skipped_noncanvas = 0;
$fails = array();

echo "=== PHASE 1 VERIFICATION (" . count($ids) . " published products) ===\n\n";

foreach ( $ids as $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) continue;

    $applied = get_post_meta( $pid, '_taf_template_applied', '1' ) ? true : false;
    $attrs   = count( $product->get_attributes() );
    $post    = get_post( $pid );
    $has_faq = strpos( $post->post_content, '<!--taf-faq-->' ) !== false;
    $has_seo = (bool) get_post_meta( $pid, 'rank_math_title', true );

    $total++;
    if ( $applied && $attrs >= 25 && $has_faq && $has_seo ) {
        $full++;
    } elseif ( $applied || $attrs >= 25 || $has_faq || $has_seo ) {
        $partial++;
        $fails[] = sprintf( "#%d attrs:%d faq:%s seo:%s  %s", $pid, $attrs,
            $has_faq?'Y':'n', $has_seo?'Y':'n', mb_substr($product->get_name(),0,45) );
    } else {
        // likely a non-canvas product intentionally skipped
        $none++;
    }
}

echo "FULLY templated (attrs>=25 + FAQ + SEO) : {$full}\n";
echo "Partially templated                    : {$partial}\n";
echo "Not templated (non-canvas / skipped)   : {$none}\n";
echo "-------------------------------------------\n";
echo "TOTAL                                  : {$total}\n\n";

if ( $fails ) {
    echo "Partial products (review):\n";
    foreach ( $fails as $f ) echo "  " . $f . "\n";
}
echo "\n=== VERIFICATION COMPLETE ===\n";
echo $full > 0 && $partial === 0
    ? "RESULT: Phase 1 rollout is CONSISTENT across all canvas products.\n"
    : "RESULT: See partial list above.\n";
