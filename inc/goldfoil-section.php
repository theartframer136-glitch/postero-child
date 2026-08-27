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
 * The products in the section, newest first. Cached: this runs on the home
 * page, and the home page is the most-hit page on a CPU-capped host.
 */
function af_gfs_products() {
    $ids = get_transient('af_gfs_ids');
    if (!is_array($ids)) {
        $ids = array();
        if (function_exists('af_goldfoil_slug') && function_exists('wc_get_products')) {
            $found = wc_get_products(array(
                'status'   => 'publish',
                'limit'    => af_gfs_limit(),
                'orderby'  => 'date',
                'order'    => 'DESC',
                'return'   => 'ids',
                'category' => array(af_goldfoil_slug()),
            ));
            if (is_array($found)) $ids = array_map('intval', $found);
        }
        set_transient('af_gfs_ids', $ids, 15 * MINUTE_IN_SECONDS);
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
    $thumb = wp_get_attachment_image($img_id, 'large', false, array(
        'loading'  => 'lazy',
        'decoding' => 'async',
        'alt'      => $product->get_name(),
    ));
    if (!$thumb) return '';
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

    return '<div class="af-gfs e-con e-con-boxed"><div class="e-con-inner">'
         . '<div class="af-gfs-head">'
         . '<h2 class="elementor-heading-title af-gfs-title">'
         . '<span style="color:#926921">Gold Foiled</span> &amp; UV</h2>'
         . $link . '</div>'
         . '<p class="af-gfs-sub">Real gold foil detailing, sealed under a UV-cured coat.</p>'
         . '<div class="random-product-grid af-gfs-grid">' . $tiles . '</div>'
         . '</div></div>';
}

/* ── Place it directly after the Customised Creations container ───────────
 * Hooked on BOTH the container-specific and the generic after_render: which of
 * the two fires depends on the Elementor version and on whether the anchor is a
 * container or a legacy section. The static guard means hooking both cannot
 * print the band twice.
 *
 * The whole render is inside a catch. A home page that has been taken down
 * twice in one afternoon does not get a third feature that can do it again:
 * if anything in here throws, the band is silently skipped and the rest of the
 * page is served exactly as it would have been. The failure is recorded for
 * inc/fatal-recorder.php to surface rather than swallowed unseen.
 */
function af_gfs_maybe_render($element) {
    if (!is_front_page() && !is_home()) return;
    if (!is_object($element) || !method_exists($element, 'get_id')) return;
    if ($element->get_id() !== af_gfs_anchor_id()) return;
    static $done = false;
    if ($done) return;                          // one band, whatever the page does
    $done = true;
    try {
        echo af_gfs_render(); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped above
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('af-goldfoil-section: ' . $e->getMessage());
        }
    }
}
add_action('elementor/frontend/container/after_render', 'af_gfs_maybe_render', 10, 1);
add_action('elementor/frontend/after_render', 'af_gfs_maybe_render', 10, 1);

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return; ?>
<style>
.af-gfs{width:100%;margin:44px 0 0;}
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
add_action('save_post_product', function () { delete_transient('af_gfs_ids'); }, 10, 0);
