<?php
if (!defined('ABSPATH')) exit;
/**
 * A "Gold Foiled & UV" band on the home page, built like the one above it.
 *
 * Owner, 2026-08-26: "create a section on homepage names gold foiled and uv and
 * take the image from this path and show them ... like Exclusively Customised
 * Creations this section".
 *
 * WHY IT IS NOT AN ELEMENTOR EDIT
 * That section is Elementor: a heading widget, a subtitle, and a shortcode that
 * renders the Personalised Prints category as a lightbox grid. Adding a twin
 * the same way means writing into the home page's _elementor_data — a single
 * 193 KB JSON blob. A malformed edit there takes the home page down, and this
 * one has been down twice today already. So nothing is written to the page:
 * the band is printed straight after the Customised Creations container, using
 * Elementor's own after-render hook, and is removed simply by deleting this
 * file. (That is not a theoretical escape hatch — deleting a file is exactly
 * what got the site back this afternoon, because the module loader guards with
 * a runtime file_exists().)
 *
 * WHAT IT SHOWS
 * The Gold Foiled & UV category, newest first: the same relationship the band
 * above has to Personalised Prints. Nothing is hardcoded to today's seven
 * pieces — anything the importer adds to assets/gold-foil/ appears here on its
 * own, and if the category is empty the band prints nothing at all rather than
 * an empty frame.
 *
 * The markup and class names are copied from the section above so it inherits
 * that styling instead of introducing a second look.
 */

/** The container Elementor renders for the Customised Creations grid. */
function af_gfs_anchor_id() {
    return (string) apply_filters('af_goldfoil_section_after', '85e906b');
}

/** How many tiles the band shows. */
function af_gfs_limit() {
    return (int) apply_filters('af_goldfoil_section_limit', 8);
}

/**
 * The pieces in the band, newest first.
 *
 * WP_Query on the taxonomy, NOT wc_get_products(). WooCommerce resolves a
 * category query through its own wc_product_meta_lookup table, which is filled
 * by a background job after a product is created — so freshly imported pieces
 * are invisible to it for a while. That was visible on the live page: the band
 * showed three of the seven, then four, gaining one at a time as the job caught
 * up. The term relationship is written the moment the product is saved, so
 * asking the taxonomy gives the true list immediately.
 */
function af_gfs_products() {
    $ids = get_transient('af_gfs_ids_v2');
    if (!is_array($ids)) {
        $ids = array();
        if (function_exists('af_goldfoil_slug')) {
            $q = new WP_Query(array(
                'post_type'              => 'product',
                'post_status'            => 'publish',
                'posts_per_page'         => af_gfs_limit(),
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'fields'                 => 'ids',
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'tax_query'              => array(array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => af_goldfoil_slug(),
                )),
            ));
            $ids = array_map('intval', $q->posts);
        }
        // Short TTL: the band is new and its contents change as artwork is
        // added. Fifteen minutes of a wrong list is fifteen minutes too many.
        set_transient('af_gfs_ids_v2', $ids, 5 * MINUTE_IN_SECONDS);
    }
    return $ids;
}

/** One tile: the piece's image, opening full size in the same lightbox. */
function af_gfs_tile($pid) {
    $product = wc_get_product($pid);
    if (!$product) return '';
    $img_id = $product->get_image_id();
    if (!$img_id) return '';
    $full = wp_get_attachment_image_url($img_id, 'full');
    // Built by hand rather than with wp_get_attachment_image(): these are phone
    // photographs, several smaller than the 'large' threshold, so the sizes
    // WordPress would normally hand back do not all exist. Falling back through
    // large -> full means an unusual attachment cannot silently drop a tile.
    $src = wp_get_attachment_image_url($img_id, 'large');
    if (!$src) $src = $full;
    if (!$src) return '';
    $thumb = '<img src="' . esc_url($src) . '" alt="' . esc_attr($product->get_name())
           . '" loading="lazy" decoding="async">';
    return '<div class="random-product-item"><div class="image-wrapper">'
         . '<a href="' . esc_url($full) . '" data-fancybox="product-' . (int) $pid . '"'
         . ' aria-label="' . esc_attr($product->get_name()) . '">'
         . $thumb . '</a></div></div>';
}

function af_gfs_render() {
    $ids = af_gfs_products();
    if (!$ids) return '';                      // nothing yet: print no band at all
    $tiles = '';
    foreach ($ids as $pid) $tiles .= af_gfs_tile($pid);
    if ($tiles === '') return '';

    $link = '';
    if (function_exists('af_goldfoil_slug')) {
        $term = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
        if ($term && !is_wp_error($term)) {
            $link = '<a class="af-gfs-more" href="' . esc_url(get_term_link($term)) . '">'
                  . 'View the collection &rarr;</a>';
        }
    }

    // NO e-con / e-con-inner here. Two reasons, both real on this site:
    // .e-con{max-width:100%!important} means "boxed" does not box anything, and
    // a global .e-con-inner{display:flex!important;flex-wrap:nowrap!important}
    // would lay the heading and the grid out as one nowrap row. The band is
    // printed INSIDE the neighbouring shortcode's wrapper instead, so it
    // inherits that container's width and gutters exactly.
    return '<div class="af-gfs">'
         . '<div class="af-gfs-head">'
         . '<h2 class="elementor-heading-title af-gfs-title">'
         . '<span style="color:#926921">Gold Foiled</span> &amp; UV</h2>'
         . $link . '</div>'
         . '<p class="af-gfs-sub">Real gold foil detailing, sealed under a UV-cured coat.</p>'
         . '<div class="random-product-grid af-gfs-grid">' . $tiles . '</div>'
         . '</div>';
}

/* ── Placement ─────────────────────────────────────────────────────────────
 * The band is appended to the OUTPUT of [random_product_grid] — the shortcode
 * that draws the Customised Creations grid — rather than printed after the
 * Elementor container that holds it.
 *
 * The first attempt used elementor/frontend/container/after_render, which puts
 * the band OUTSIDE that container, as a sibling of the page's top-level
 * elements. Those get their width from Elementor per-element CSS the band does
 * not carry, so it ran the full width of the window: the heading was cut off at
 * the left edge and the button at the right. Copying the container's classes
 * does not fix it either, because this site overrides them
 * (.e-con{max-width:100%!important}, and an .e-con-inner rule that forces a
 * nowrap flex row).
 *
 * Appending to the shortcode's output puts the band INSIDE the same wrapper as
 * the grid above it, so it inherits that container's width, gutters and
 * centring exactly — and keeps doing so if the page's layout is ever changed in
 * Elementor. Nothing about the width is hardcoded here.
 *
 * Wrapped in catch (\Throwable): if the band ever throws, the page serves
 * without it rather than serving a critical error.
 */
add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'random_product_grid') return $output;
    if (!is_front_page() && !is_home()) return $output;
    static $done = false;
    if ($done) return $output;                  // one band per page
    $done = true;
    try {
        return $output . af_gfs_render();
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('af-goldfoil-section: ' . $e->getMessage());
        return $output;
    }
}, 10, 2);

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return; ?>
<style>
.af-gfs{width:100%;margin:52px 0 8px;}
.af-gfs-head{display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.af-gfs-title{font-family:Georgia,"Times New Roman",serif;font-weight:bold;font-size:30px;
  line-height:1.3;letter-spacing:.6px;color:#4E423D;margin:0;}
.af-gfs-sub{color:#6a6055;font-size:15px;margin:10px 0 26px;}
.af-gfs-more{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
  color:#8a6d1f;text-decoration:none;border:.8px solid #c9a84c;border-radius:50px;
  padding:10px 20px;white-space:nowrap;transition:background .18s,color .18s;}
.af-gfs-more:hover,.af-gfs-more:focus{background:#c9a84c;color:#fff;}
/* the grid itself reuses .random-product-grid from the section above, so the
   two bands cannot drift apart; only the tile height is pinned here because
   these are photographs of prints rather than flat artwork */
.af-gfs-grid .random-product-item img{width:100%;height:300px;object-fit:cover;
  border-radius:2px;transition:transform .4s ease;}
.af-gfs-grid .random-product-item{overflow:hidden;}
.af-gfs-grid .random-product-item:hover img{transform:scale(1.05);}
@media(max-width:600px){
  .af-gfs-title{font-size:22px;}
  .af-gfs-grid .random-product-item img{height:200px;}
}
</style>
<?php }, 20);

/* Keep the band in step when the importer adds a piece. */
add_action('save_post_product', function () { delete_transient('af_gfs_ids_v2'); }, 10, 0);
