<?php
if (!defined('ABSPATH')) exit;
/**
 * The "Gold Foiled & UV" band on the home page — pictures, not products.
 *
 * Owner, 2026-08-27: "this section image is just for home page advertisement
 * not the section product ... remove this all from product but keep on the home
 * page section and the product image i am giving you after".
 *
 * So the band is now a straight picture wall. It reads the images out of
 * assets/goldfoil-promo/ and shows them; there is no product behind any tile,
 * nothing is for sale from here, and the Gold Foiled & UV category is left empty
 * and waiting for the real artwork.
 *
 * WHY THE PICTURES LIVE IN THEIR OWN FOLDER
 * They were in assets/gold-foil/, which tools/import-gold-foil.php scans on
 * every full deploy and turns into products — that is how the seven products
 * appeared. Deleting those products while the files stayed there would simply
 * have recreated them on the next deploy, over and over. Moving them out of that
 * folder is what actually stops it, and this folder is scanned by nothing.
 *
 * To change what the band shows: add or remove a JPG in assets/goldfoil-promo/.
 * The filename becomes the caption/alt text, so name it like the piece.
 *
 * When the real products arrive they go in assets/gold-foil/ as before, and the
 * "View the collection" button reappears by itself — it is drawn only when the
 * category actually has something in it.
 */

/** Where the advertising pictures live. Scanned by nothing else. */
function af_gfp_dir() {
    return get_stylesheet_directory() . '/assets/goldfoil-promo';
}
function af_gfp_uri() {
    return get_stylesheet_directory_uri() . '/assets/goldfoil-promo';
}

/** Most tiles to show. */
function af_gfp_limit() {
    return (int) apply_filters('af_goldfoil_promo_limit', 8);
}

/**
 * The picture filenames, alphabetical. Cached: this runs on the home page, and
 * a directory listing per visitor on a CPU-capped box is waste.
 */
function af_gfp_files() {
    $files = get_transient('af_gfp_files');
    if (!is_array($files)) {
        $files = array();
        foreach ((array) glob(af_gfp_dir() . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $p) {
            if (is_file($p)) $files[] = basename($p);
        }
        sort($files);
        set_transient('af_gfp_files', $files, HOUR_IN_SECONDS);
    }
    return array_slice($files, 0, af_gfp_limit());
}

/** "Seven Horses Gold Run Gold Foiled UV Canvas Art 4x3 Feet.jpg" -> the name. */
function af_gfp_caption($file) {
    $n = pathinfo($file, PATHINFO_FILENAME);
    $n = preg_split('/\s+Gold\s+Foiled\s+UV\b/i', $n);
    $n = trim($n[0]);
    return $n !== '' ? $n : 'Gold Foiled & UV';
}

function af_gfp_render() {
    $files = af_gfp_files();
    if (!$files) return '';                     // no pictures: print no band

    $tiles = '';
    foreach ($files as $f) {
        $url = af_gfp_uri() . '/' . rawurlencode($f);
        $cap = af_gfp_caption($f);
        $tiles .= '<div class="random-product-item"><div class="image-wrapper">'
                . '<a href="' . esc_url($url) . '" data-fancybox="af-goldfoil"'
                . ' aria-label="' . esc_attr($cap) . '">'
                . '<img src="' . esc_url($url) . '" alt="' . esc_attr($cap) . '"'
                . ' loading="lazy" decoding="async">'
                . '</a></div></div>';
    }

    // The button is drawn only when the category has something to show, so it
    // cannot send anyone to an empty page while the real artwork is awaited.
    $link = '';
    if (function_exists('af_goldfoil_slug')) {
        $term = get_term_by('slug', af_goldfoil_slug(), 'product_cat');
        if ($term && !is_wp_error($term) && (int) $term->count > 0) {
            $link = '<a class="af-gfs-more" href="' . esc_url(get_term_link($term)) . '">'
                  . 'View the collection &rarr;</a>';
        }
    }

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
 * Appended to the OUTPUT of [random_product_grid] — the shortcode that draws
 * the Customised Creations grid — so the band renders INSIDE that same wrapper
 * and inherits its width, gutters and centring exactly. Printing it after the
 * Elementor container instead put it outside any container and it ran the full
 * width of the window, with the heading clipped at the left edge.
 *
 * Wrapped in catch (\Throwable): if the band throws, the page serves without it
 * rather than serving a critical error.
 */
add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'random_product_grid') return $output;
    if (!is_front_page() && !is_home()) return $output;
    static $done = false;
    if ($done) return $output;                  // one band per page
    $done = true;
    try {
        return $output . af_gfp_render();
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('af-goldfoil-promo: ' . $e->getMessage());
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
/* the grid reuses .random-product-grid from the section above so the two bands
   cannot drift apart; only the tile height is pinned, because these are
   photographs of prints rather than flat artwork */
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
