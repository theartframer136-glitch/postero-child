<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Bulk Product Importer — The Art Framer
 *
 * Run via WP-CLI:
 *   wp eval-file wp-content/themes/postero-child/tools/bulk-import-products.php
 *
 * - Skips products that already exist (by title or SKU) — safe to re-run.
 * - Images already in Media Library are looked up by URL (no re-upload).
 * - Creates products as DRAFT for review. Publish from Products → Drafts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "Run this via: wp eval-file ...\n" );
    exit( 1 );
}
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not active.' );
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/* ============================================================
   PRODUCT DATA  — add more rows to import more products
   ============================================================ */
$PRODUCTS = array(

    array(
        'name'          => 'Radha Krishna Divine Love Canvas Art',
        'image'         => 'https://theartframer.us/wp-content/uploads/2026/06/The-Art-Framer-Website-picture-1.webp',
        'regular_price' => '120',
        'sale_price'    => '70',
        'category'      => 'Digital Canvas Print',
        'subcategory'   => 'Radha Krishna',
        'tags'          => 'radha krishna, divine love, religious canvas art, krishna painting, spiritual wall art, devotional art, canvas print, wall decor, hindu art, pooja room decor',
        'short_desc'    => 'Radha Krishna Divine Love — a premium digital canvas print capturing the eternal love of Radha and Krishna. Printed on high-grade cotton-blend canvas with fade-resistant archival inks. Gallery-wrapped and ready to hang.',
        'description'   => '<h3>Radha Krishna Divine Love Canvas Art</h3>
<p>Celebrate the timeless and divine love story of Radha and Krishna with this exquisite premium canvas print. Crafted for art lovers and devotees who appreciate fine detail, vibrant colour, and spiritual depth, this piece brings serenity and beauty to any space it graces.</p>
<p>Printed using state-of-the-art archival inkjet technology on premium cotton-blend canvas, the colours are vivid, rich, and designed to remain bright for decades. The gallery-wrapped finish means it arrives ready to hang — no framing needed.</p>
<h4>Product Highlights</h4>
<ul>
<li>Premium cotton-blend canvas — soft texture, museum quality</li>
<li>Fade-resistant archival inks — stays vibrant for 75+ years</li>
<li>Gallery-wrapped with mirrored edges — ready to hang straight out of the box</li>
<li>Lightweight yet sturdy wooden stretcher bars</li>
<li>Perfect for living rooms, bedrooms, pooja rooms, and offices</li>
</ul>
<h4>Gifting</h4>
<p>Makes a thoughtful and elegant gift for weddings, housewarmings, Diwali, anniversaries, or any auspicious occasion. Ships in protective packaging to ensure it arrives in perfect condition.</p>',
    ),

    array(
        'name'          => 'Radha Krishna Sacred Bond Canvas Art',
        'image'         => 'https://theartframer.us/wp-content/uploads/2026/06/The-Art-Framer-Website-picture.webp',
        'regular_price' => '150',
        'sale_price'    => '100',
        'category'      => 'Digital Canvas Print',
        'subcategory'   => 'Radha Krishna',
        'tags'          => 'radha krishna, sacred bond, religious canvas art, krishna art, spiritual wall art, devotional art, canvas print, wall decor, hindu art, pooja room decor, premium canvas',
        'short_desc'    => 'Radha Krishna Sacred Bond — a premium large-format digital canvas print radiating devotion and elegance. High-grade cotton-blend canvas, archival inks, gallery-wrapped and ready to hang.',
        'description'   => '<h3>Radha Krishna Sacred Bond Canvas Art</h3>
<p>This stunning large-format canvas print brings the sacred and eternal bond of Radha and Krishna into your home with breathtaking clarity and warmth. A true centrepiece for any room, this piece radiates devotion, elegance, and artistic mastery.</p>
<p>Every detail is rendered with precision using high-resolution digital printing on premium cotton-blend canvas, ensuring colours that pop yet remain natural and true to the artwork. The gallery-wrapped presentation adds a contemporary, frame-free aesthetic that suits both traditional and modern interiors.</p>
<h4>Product Highlights</h4>
<ul>
<li>Large-format premium canvas — makes a bold, beautiful statement</li>
<li>Archival pigment inks — fade-resistant for 75+ years</li>
<li>Gallery-wrapped with mirrored edges — no additional framing required</li>
<li>Robust wooden stretcher frame — arrives taut and flat</li>
<li>Ideal for large walls in living rooms, prayer rooms, and hallways</li>
</ul>
<h4>Gifting</h4>
<p>An exceptional gift for devotees, art collectors, and anyone who appreciates meaningful, high-quality wall art. Presented in premium protective packaging for safe delivery.</p>',
    ),

);

/* ============================================================
   HELPERS
   ============================================================ */

/** Return attachment ID for a URL already in the Media Library, else 0. */
function af_attachment_id_from_url( $url ) {
    // Strip query string for reliable lookup
    $url   = strtok( $url, '?' );
    $id    = attachment_url_to_postid( $url );
    if ( $id ) return $id;

    // Fallback: search by guid
    global $wpdb;
    $id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND guid=%s LIMIT 1",
        $url
    ) );
    return $id;
}

/** Side-load an external image URL into the Media Library. */
function af_sideload_image( $url, $post_id, $desc ) {
    $tmp = download_url( $url, 60 );
    if ( is_wp_error( $tmp ) ) return $tmp;

    $type = wp_check_filetype( basename( parse_url( $url, PHP_URL_PATH ) ) );
    $ext  = $type['ext'];
    if ( ! $ext ) {
        $mime = function_exists( 'mime_content_type' ) ? mime_content_type( $tmp ) : 'image/jpeg';
        $map  = array( 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif' );
        $ext  = isset( $map[ $mime ] ) ? $map[ $mime ] : 'jpg';
    }

    $file = array( 'name' => sanitize_title( $desc ) . '.' . $ext, 'tmp_name' => $tmp );
    $id   = media_handle_sideload( $file, $post_id, $desc );
    if ( is_wp_error( $id ) ) @unlink( $tmp );
    return $id;
}

/** Resolve image: use existing attachment or side-load. */
function af_resolve_image( $url, $product_id, $name ) {
    $att_id = af_attachment_id_from_url( $url );
    if ( $att_id ) {
        WP_CLI::log( "  image: found in Media Library (#{$att_id})" );
        return $att_id;
    }
    WP_CLI::log( "  image: not in Media Library, downloading..." );
    return af_sideload_image( $url, $product_id, $name );
}

/** Generate a unique SKU. */
function af_make_sku( $name ) {
    $slug = strtoupper( preg_replace( '/[^A-Za-z0-9]+/', '', $name ) );
    $base = 'TAF-' . substr( $slug, 0, 12 );
    $sku  = $base;
    $i    = 1;
    while ( wc_get_product_id_by_sku( $sku ) ) {
        $sku = $base . '-' . $i++;
    }
    return $sku;
}

/** Ensure category > subcategory hierarchy; return array of term IDs. */
function af_ensure_categories( $category, $subcategory ) {
    $ids = array();

    $parent_id = 0;
    if ( $category ) {
        $term = get_term_by( 'name', $category, 'product_cat' );
        if ( ! $term ) {
            $r = wp_insert_term( $category, 'product_cat' );
            $parent_id = is_wp_error( $r ) ? 0 : (int) $r['term_id'];
        } else {
            $parent_id = (int) $term->term_id;
        }
        if ( $parent_id ) $ids[] = $parent_id;
    }

    if ( $subcategory && $parent_id ) {
        $term = get_term_by( 'name', $subcategory, 'product_cat' );
        if ( $term && (int) $term->parent === $parent_id ) {
            $ids[] = (int) $term->term_id;
        } else {
            $r = wp_insert_term( $subcategory, 'product_cat', array( 'parent' => $parent_id ) );
            if ( ! is_wp_error( $r ) ) {
                $ids[] = (int) $r['term_id'];
            } elseif ( isset( $r->error_data['term_exists'] ) ) {
                $ids[] = (int) $r->error_data['term_exists'];
            }
        }
    }

    return array_values( array_unique( $ids ) );
}

/** Check if a product with this title already exists (WP 6.2-safe). */
function af_title_exists( $title ) {
    $q = new WP_Query( array(
        'post_type'              => 'product',
        'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
        'title'                  => $title,
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ) );
    return ! empty( $q->posts ) ? (int) $q->posts[0] : 0;
}

/* ============================================================
   IMPORT LOOP
   ============================================================ */
$created = 0;
$skipped = 0;
$failed  = 0;

WP_CLI::log( '========================================' );
WP_CLI::log( ' The Art Framer — Bulk Product Importer' );
WP_CLI::log( ' Total to process: ' . count( $PRODUCTS ) );
WP_CLI::log( '========================================' );

foreach ( $PRODUCTS as $idx => $row ) {
    $name = trim( $row['name'] );
    WP_CLI::log( '' );
    WP_CLI::log( ( $idx + 1 ) . '. ' . $name );

    // ── Duplicate check by title ──
    $existing_id = af_title_exists( $name );
    if ( $existing_id ) {
        WP_CLI::log( "   SKIPPED — already exists as product #{$existing_id}" );
        $skipped++;
        continue;
    }

    // ── Duplicate check by SKU ──
    $sku          = af_make_sku( $name );
    $existing_sku = wc_get_product_id_by_sku( $sku );
    if ( $existing_sku ) {
        WP_CLI::log( "   SKIPPED — SKU {$sku} already in use by product #{$existing_sku}" );
        $skipped++;
        continue;
    }

    // ── Build product ──
    $product = new WC_Product_Simple();
    $product->set_name( $name );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_description( $row['description'] );
    $product->set_short_description( $row['short_desc'] );
    $product->set_sku( $sku );
    $product->set_manage_stock( false );
    $product->set_stock_status( 'instock' );

    $regular = preg_replace( '/[^0-9.]/', '', (string) $row['regular_price'] );
    $sale    = preg_replace( '/[^0-9.]/', '', (string) $row['sale_price'] );
    $product->set_regular_price( $regular );
    if ( $sale !== '' && (float) $sale > 0 && (float) $sale < (float) $regular ) {
        $product->set_sale_price( $sale );
    }

    // Categories
    $cat_ids = af_ensure_categories( $row['category'], $row['subcategory'] );
    if ( $cat_ids ) $product->set_category_ids( $cat_ids );

    // Save to get a post ID before attaching image
    $product_id = $product->save();
    if ( ! $product_id ) {
        WP_CLI::warning( "   FAILED — could not create product" );
        $failed++;
        continue;
    }
    WP_CLI::log( "   Created draft product #{$product_id} (SKU: {$sku})" );

    // Tags
    if ( ! empty( $row['tags'] ) ) {
        $tag_names = array_map( 'trim', explode( ',', $row['tags'] ) );
        wp_set_object_terms( $product_id, $tag_names, 'product_tag' );
        WP_CLI::log( '   Tags set: ' . implode( ', ', $tag_names ) );
    }

    // Image
    if ( ! empty( $row['image'] ) ) {
        $att_id = af_resolve_image( $row['image'], $product_id, $name );
        if ( is_wp_error( $att_id ) ) {
            WP_CLI::warning( '   Image failed: ' . $att_id->get_error_message() );
        } else {
            set_post_thumbnail( $product_id, $att_id );
            WP_CLI::log( "   Featured image set (attachment #{$att_id})" );
        }
    }

    WP_CLI::success( "   DONE — {$name}" );
    $created++;
}

WP_CLI::log( '' );
WP_CLI::log( '========================================' );
WP_CLI::success( "Finished. Created: {$created} | Skipped: {$skipped} | Failed: {$failed}" );
WP_CLI::log( "Review at: Products → filter by Draft → Publish when ready." );
WP_CLI::log( '========================================' );
