<?php
/**
 * Apply the full product template to a category of products.
 * SAFE & ADDITIVE: never changes title, price, slug, or images.
 * Idempotent: tracks completion with meta _taf_template_applied and
 * only appends FAQ/specs blocks if a marker is absent.
 *
 * Scope is controlled by $TARGET_CATEGORY_SLUG below.
 *
 * Run: wp eval-file tools/apply-template.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$TARGET_CATEGORY_SLUG = 'radha-krishna'; // PILOT scope
$DIR = __DIR__;

/* ── Shared attribute defaults (apply to every canvas product) ──
   Values left as '' are intentionally skipped (product-specific). */
$SHARED_ATTRS = array(
    'Original'         => 'Yes',
    'Brand'            => 'The Art Framer',
    'Type'             => 'Canvas Wall Art',
    'Material'         => 'Premium Canvas',
    'Support Base'     => 'Canvas',
    'Print Method'     => 'XYZ Colour Digital Printing',
    'Ink Type'         => 'Eco-Friendly Ink',
    'Frame Type'       => 'Custom Frame Available',
    'Orientation'      => 'Portrait / Landscape',
    'Shape'            => 'Rectangular / Square',
    'Colour'           => 'Multicolor',
    'Use / Room Type'  => 'Living Room / Bedroom / Puja Room',
    'Indoor / Outdoor' => 'Indoor',
    'Selling Unit'     => 'Single Piece',
    'Sample'           => 'Provided',
    'OEM / ODM'        => 'Available',
    'Number of Items'  => '1',
    'Recommended Use'  => 'Home / Office Decoration',
    'Wall Art Form'    => 'Canvas Wall Art',
    'Style'            => 'Modern',
    'Colour Family'    => 'Multicolor',
    'Age Range'        => 'All Ages',
    'Pattern'          => 'Artistic',
    'Special Feature'  => 'Fade Resistant Print',
    'Frame Material'   => 'Wood / Fibre / Aluminium',
    'Mounting Type'    => 'Wall Mount',
    'Finish Type'      => 'Matte / Glossy',
    'Is Framed'        => 'Yes',
    'Manufacturer'     => 'The Art Framer',
    'Country Of Origin'=> 'India',
);

/* ── Shared FAQ block (appended once, marked) ── */
$FAQ_MARKER = '<!--taf-faq-->';
$FAQ_HTML = $FAQ_MARKER . "\n" . file_get_contents( $DIR . '/template-faq.html' );

// ── Resolve target products ────────────────────────────────────
$term = get_term_by( 'slug', $TARGET_CATEGORY_SLUG, 'product_cat' );
if ( ! $term ) { echo "Category '$TARGET_CATEGORY_SLUG' not found\n"; exit(1); }

$ids = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'tax_query'      => array( array(
        'taxonomy' => 'product_cat',
        'field'    => 'term_id',
        'terms'    => $term->term_id,
    ) ),
) );

echo "=== Apply Template: '{$TARGET_CATEGORY_SLUG}' (" . count($ids) . " products) ===\n\n";

$done = 0;
foreach ( $ids as $pid ) {
    $product = wc_get_product( $pid );
    if ( ! $product ) { continue; }
    $title = $product->get_name();
    echo "#{$pid} {$title}\n";

    /* 1. ATTRIBUTES → fills Additional Information tab.
          Preserve any existing attributes; add shared ones that are missing. */
    $existing = $product->get_attributes();
    $attr_objs = $existing;
    $pos = count( $existing );
    foreach ( $SHARED_ATTRS as $name => $val ) {
        if ( $val === '' ) { continue; }
        $slug = sanitize_title( $name );
        if ( isset( $attr_objs[ $slug ] ) ) { continue; } // keep existing
        $a = new WC_Product_Attribute();
        $a->set_id( 0 );
        $a->set_name( $name );
        $a->set_options( array( $val ) );
        $a->set_position( $pos++ );
        $a->set_visible( true );
        $a->set_variation( false );
        $attr_objs[ $slug ] = $a;
    }
    $product->set_attributes( $attr_objs );
    $product->save();
    echo "   attrs set: " . count($attr_objs) . "\n";

    /* 2. SEO meta (only if missing) */
    if ( ! get_post_meta( $pid, 'rank_math_title', true ) ) {
        $seo_title = $title . ' | Premium Canvas Wall Art – The Art Framer';
        $seo_desc  = 'Shop ' . $title . ' — premium digital canvas wall art by The Art Framer. Archival inks, eco-friendly, multiple sizes & frames, ready to hang.';
        update_post_meta( $pid, 'rank_math_title', $seo_title );
        update_post_meta( $pid, 'rank_math_description', $seo_desc );
        update_post_meta( $pid, 'rank_math_focus_keyword', $title );
        echo "   SEO meta written\n";
    } else {
        echo "   SEO meta already present (skipped)\n";
    }

    /* 3. Append FAQ block to description (only once) */
    $post = get_post( $pid );
    if ( strpos( $post->post_content, $FAQ_MARKER ) === false ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            array( 'post_content' => $post->post_content . "\n\n" . $FAQ_HTML ),
            array( 'ID' => $pid ),
            array( '%s' ), array( '%d' )
        );
        echo "   FAQ block appended\n";
    } else {
        echo "   FAQ already present (skipped)\n";
    }

    update_post_meta( $pid, '_taf_template_applied', '1' );
    clean_post_cache( $pid );
    wc_delete_product_transients( $pid );
    $done++;
    echo "\n";
}

echo "=== DONE. Templated {$done} product(s). ===\n";
