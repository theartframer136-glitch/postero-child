<?php
/**
 * Bulk product importer for The Art Framer.
 *
 * Run on the server with WP-CLI:
 *   wp eval-file wp-content/themes/postero-child/tools/bulk-import-products.php
 *
 * Behaviour:
 *   - Reads the $PRODUCTS array below.
 *   - Skips any product whose title (or SKU) already exists — no duplicates.
 *   - Converts Google-Drive "view" links to direct-download links.
 *   - Side-loads the image into the Media Library and sets it as the product image.
 *   - Generates description, short description, SKU, tags.
 *   - Assigns Category > Sub-category hierarchy.
 *   - Creates products as DRAFT so you can review before publishing.
 *
 * Re-running is safe: existing products are detected and skipped.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "This script must be run through WP-CLI (wp eval-file ...).\n");
    exit(1);
}
if (!function_exists('wc_get_product')) {
    WP_CLI::error('WooCommerce is not active.');
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/* ---------------------------------------------------------------------------
 * PRODUCT DATA  (prices corrected: regular > sale)
 * Add more rows here in the same shape to import more products.
 * ------------------------------------------------------------------------- */
$PRODUCTS = array(
    array(
        'name'           => 'Radha Krishna Divine Love Digital Canvas Print',
        'image'          => 'https://drive.google.com/file/d/1R9e_SRmXWa-Bn-daO4Hj6-FY16YwALJL/view?usp=drive_link',
        'regular_price'  => '120',
        'sale_price'     => '70',
        'category'       => 'Digital Canvas Print',
        'subcategory'    => 'Radha Krishna',
    ),
    array(
        'name'           => 'Radha Krishna Eternal Bond Digital Canvas Print',
        'image'          => 'https://drive.google.com/file/d/1PYYLVdUG9JBaQ6g2rIqCmGzXUyF9zQKR/view?usp=drive_link',
        'regular_price'  => '150',
        'sale_price'     => '100',
        'category'       => 'Digital Canvas Print',
        'subcategory'    => 'Radha Krishna',
    ),
);

/* ---------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

/** Convert a Google-Drive share link to a direct-download URL. */
function af_drive_direct_url($url) {
    if (preg_match('#/file/d/([^/]+)#', $url, $m)) {
        return 'https://drive.google.com/uc?export=download&id=' . $m[1];
    }
    if (preg_match('#[?&]id=([^&]+)#', $url, $m)) {
        return 'https://drive.google.com/uc?export=download&id=' . $m[1];
    }
    return $url; // already a direct link or non-Drive URL
}

/** Build a unique SKU from a name. */
function af_make_sku($name) {
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', substr($name, 0, 18)));
    $base = 'TAF-' . substr($base, 0, 10);
    $sku  = $base;
    $i    = 1;
    while (wc_get_product_id_by_sku($sku)) {
        $sku = $base . '-' . $i++;
    }
    return $sku;
}

/** Generate tags from name + subcategory. */
function af_make_tags($name, $sub) {
    $tags = array('canvas print', 'wall art', 'digital canvas', 'home decor', 'wall decor');
    if ($sub) {
        $tags[] = strtolower($sub);
        $tags[] = strtolower($sub) . ' art';
    }
    return array_values(array_unique($tags));
}

/** Generate a full HTML description. */
function af_make_description($name, $sub) {
    $subject = $sub ? $sub : 'this artwork';
    return
        '<p>Bring home the timeless beauty of <strong>' . esc_html($name) . '</strong>, a premium digital canvas print crafted for art lovers who appreciate detail, color, and devotion. This piece captures the essence of ' . esc_html($subject) . ' with rich, vibrant tones and museum-grade clarity.</p>'
        . '<p>Printed on high-quality cotton-blend canvas using fade-resistant archival inks, each piece is built to retain its brilliance for years. The gallery-wrapped finish gives it a clean, gallery-ready look that fits beautifully in living rooms, bedrooms, pooja spaces, and office walls.</p>'
        . '<ul>'
        . '<li>Premium fade-resistant archival inks</li>'
        . '<li>High-grade cotton-blend canvas</li>'
        . '<li>Gallery-wrapped, ready to hang</li>'
        . '<li>Handcrafted finish with attention to detail</li>'
        . '</ul>'
        . '<p>A perfect centerpiece for your home or a thoughtful gift for someone special.</p>';
}

/** Generate a short description. */
function af_make_short_description($name, $sub) {
    return '<p>Premium ' . esc_html($sub ? $sub : 'canvas') . ' digital canvas print on high-grade cotton-blend canvas with fade-resistant archival inks. Gallery-wrapped and ready to hang.</p>';
}

/** Ensure a Category > Sub-category term hierarchy exists; return term IDs. */
function af_ensure_category_terms($category, $subcategory) {
    $ids = array();

    $parent_id = 0;
    if ($category) {
        $term = get_term_by('name', $category, 'product_cat');
        if (!$term) {
            $res = wp_insert_term($category, 'product_cat');
            if (!is_wp_error($res)) $parent_id = (int) $res['term_id'];
        } else {
            $parent_id = (int) $term->term_id;
        }
        if ($parent_id) $ids[] = $parent_id;
    }

    if ($subcategory) {
        $term = get_term_by('name', $subcategory, 'product_cat');
        if ($term && (int) $term->parent === $parent_id) {
            $ids[] = (int) $term->term_id;
        } else {
            $res = wp_insert_term($subcategory, 'product_cat', array('parent' => $parent_id));
            if (!is_wp_error($res)) {
                $ids[] = (int) $res['term_id'];
            } elseif (isset($res->error_data['term_exists'])) {
                $ids[] = (int) $res->error_data['term_exists'];
            }
        }
    }

    return array_values(array_unique($ids));
}

/** Does a product with this exact title already exist? (WP 6.2-safe) */
function af_product_title_exists($title) {
    $q = new WP_Query(array(
        'post_type'              => 'product',
        'post_status'            => array('publish', 'draft', 'pending', 'private'),
        'title'                  => $title,
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ));
    return !empty($q->posts) ? (int) $q->posts[0] : 0;
}

/** Side-load an image URL into the media library, return attachment ID or WP_Error. */
function af_sideload_image($url, $post_id, $desc) {
    $tmp = download_url($url, 60);
    if (is_wp_error($tmp)) return $tmp;

    // Determine a sane filename + extension (Drive links have no extension).
    $type = wp_check_filetype(basename(parse_url($url, PHP_URL_PATH)));
    $ext  = $type['ext'];
    if (!$ext) {
        $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : 'image/jpeg';
        $map  = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');
        $ext  = isset($map[$mime]) ? $map[$mime] : 'jpg';
    }
    $filename = sanitize_title($desc) . '.' . $ext;

    $file_array = array('name' => $filename, 'tmp_name' => $tmp);
    $id = media_handle_sideload($file_array, $post_id, $desc);
    if (is_wp_error($id)) {
        @unlink($tmp);
        return $id;
    }
    return $id;
}

/* ---------------------------------------------------------------------------
 * Import loop
 * ------------------------------------------------------------------------- */
$created = 0; $skipped = 0; $failed = 0;

foreach ($PRODUCTS as $row) {
    $name = trim($row['name']);
    if ($name === '') { continue; }

    // ---- Duplicate check ----
    $existing_id = af_product_title_exists($name);
    if ($existing_id) {
        WP_CLI::log("SKIP  (already exists #$existing_id): $name");
        $skipped++;
        continue;
    }

    // ---- Create product ----
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_status('draft');
    $product->set_catalog_visibility('visible');
    $product->set_description(af_make_description($name, $row['subcategory']));
    $product->set_short_description(af_make_short_description($name, $row['subcategory']));

    $regular = preg_replace('/[^0-9.]/', '', (string) $row['regular_price']);
    $sale    = preg_replace('/[^0-9.]/', '', (string) $row['sale_price']);
    $product->set_regular_price($regular);
    if ($sale !== '' && (float) $sale < (float) $regular) {
        $product->set_sale_price($sale);
    }

    $product->set_sku(af_make_sku($name));
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');

    $cat_ids = af_ensure_category_terms($row['category'], $row['subcategory']);
    if ($cat_ids) $product->set_category_ids($cat_ids);

    $product->set_tag_ids(array()); // tags set after save via wp_set_object_terms (names)

    $product_id = $product->save();
    if (!$product_id) {
        WP_CLI::warning("FAIL  (could not create): $name");
        $failed++;
        continue;
    }

    // Tags by name
    wp_set_object_terms($product_id, af_make_tags($name, $row['subcategory']), 'product_tag');

    // ---- Image ----
    $direct = af_drive_direct_url($row['image']);
    $att_id = af_sideload_image($direct, $product_id, $name);
    if (is_wp_error($att_id)) {
        WP_CLI::warning("  image failed for '$name': " . $att_id->get_error_message());
    } else {
        set_post_thumbnail($product_id, $att_id);
    }

    WP_CLI::log("CREATE (#$product_id, draft): $name");
    $created++;
}

WP_CLI::success("Done. Created: $created | Skipped (existing): $skipped | Failed: $failed");
WP_CLI::log("Review them under Products → filter by 'Draft', then Publish.");
