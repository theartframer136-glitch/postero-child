<?php
/**
 * Search results — a shop aisle, not a blog roll.
 *
 * ── WHY THIS FILE EXISTS ──────────────────────────────────────────────────
 * Search finds the right pieces now (inc/search-all.php, 2026-09-09), but the
 * results page was still the parent theme's blog template: every canvas
 * rendered as a blog post — one enormous featured image, a huge title, a
 * stray "ART CODE" line, an excerpt, "Continue reading →" and "View brochure"
 * — one per screen, beside a sidebar of Recent Posts and Blog Categories.
 * No price. No Add to Cart. No grid. A customer who searched an art code got
 * an article about their own artwork.
 *
 * A child search.php overrides the parent's outright, and this file loads ONLY
 * when is_search() is true, so everything in it is scoped by construction.
 *
 * ── THE SIDEBAR ───────────────────────────────────────────────────────────
 * The blog sidebar is the parent's do_action('postero_sidebar'). Nothing in
 * this repo hooks it (grep returns zero matches), so simply not calling it
 * removes the sidebar from search and from nowhere else.
 *
 * ── THE CARDS ─────────────────────────────────────────────────────────────
 * Products render through the parent's own content-product.php, exactly as
 * /shop/ does — so price, Add to Cart, wishlist, art code, discount, swatches
 * and "Try on Wall" all come for free from hooks that already fire.
 *
 * The catch, and the reason this file carries CSS: almost every rule that
 * styles those cards is scoped to body.tax-product_cat / body.woocommerce-page
 * or PHP-gated to is_shop() — none of which a search page satisfies. The
 * markup would arrive correct and completely unstyled. So the archive's own
 * proven rules are copied here under .af-sr rather than widening a dozen
 * shared gates and risking every other page in the shop.
 */

$wpq = $GLOBALS['wp_query'];

/* The term. inc/search-all.php:140 — 's' comes back EMPTY on this stack (some
   plugin here empties it), so use the module's own resolver, which falls
   through to the request. */
$af_term = function_exists('af_search_query_string')
    ? af_search_query_string($wpq)
    : get_search_query(false);

/* Read $wpq->posts DIRECTLY — never have_posts()/the_post(), never a second
   WP_Query.
     - Something on this stack walks the main loop before the template runs:
       inc/search-all.php recorded the parent template taking its "Nothing
       Found" branch while the query held ten posts. Reading the array is
       immune to that and needs no rewind_posts().
     - inc/search-all.php only widens the MAIN query (its guard requires
       is_main_query), so a secondary WP_Query would silently lose every
       art-code, SKU and attribute match the search fix added. */
$af_rows   = isset($wpq->posts) ? (array) $wpq->posts : array();
$af_prods  = array();   // array of array( WP_Post, WC_Product )
$af_others = array();   // posts and pages

foreach ($af_rows as $af_p) {
    if (isset($af_p->post_type) && $af_p->post_type === 'product' && function_exists('wc_get_product')) {
        /* The search fix ORs its matched ids onto the WHERE clause as a whole,
           which by construction escapes the post_status and visibility
           conditions beside it. On an archive WooCommerce would have applied
           those; here nothing has. Re-apply them, or a hidden or
           catalog-excluded piece can surface in a customer's results. */
        $af_o = wc_get_product($af_p->ID);
        if (!$af_o || !$af_o->is_visible()) continue;
        $af_prods[] = array($af_p, $af_o);
    } else {
        $af_others[] = $af_p;
    }
}

$af_paged = max(1, (int) get_query_var('paged'));
$af_found = (int) $wpq->found_posts;
$af_empty = (!$af_prods && !$af_others);

/* A results page is not something to index. Registered before get_header() so
   it lands in <head> rather than the body. */
if ($af_empty) {
    add_action('wp_head', function () {
        echo '<meta name="robots" content="noindex,follow">' . "\n";
    }, 1);
}

/** The refine form, reused by the header and the empty state. */
function af_sr_form($term) {
    $explicit = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
    ?>
    <form class="af-sr-refine" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="search" name="s" value="<?php echo esc_attr($term); ?>"
               placeholder="Search art, an art code, a colour…" aria-label="Search">
        <?php if ($explicit !== '') : ?>
            <input type="hidden" name="post_type" value="<?php echo esc_attr($explicit); ?>">
        <?php endif; ?>
        <button type="submit">Search</button>
    </form>
    <?php
}

get_header();
?>

<div class="af-sr">

    <header class="af-sr-head">
        <p class="af-sr-eyebrow">Search</p>
        <h1 class="af-sr-title">Results for <em>&ldquo;<?php echo esc_html($af_term); ?>&rdquo;</em></h1>
        <?php if (!$af_empty) : ?>
            <p class="af-sr-count">
                <?php
                printf(
                    /* translators: %s: number of results */
                    esc_html(_n('%s result', '%s results', $af_found, 'postero-child')),
                    esc_html(number_format_i18n($af_found))
                );
                if ($wpq->max_num_pages > 1) {
                    printf(
                        esc_html__(' — page %1$s of %2$s', 'postero-child'),
                        esc_html(number_format_i18n($af_paged)),
                        esc_html(number_format_i18n((int) $wpq->max_num_pages))
                    );
                }
                ?>
            </p>
        <?php endif; ?>
        <?php af_sr_form($af_term); ?>
    </header>

<?php
/* A late page can empty out even though the query counted results, because the
   visibility filter above drops rows the count still includes. Never tell a
   customer nothing matched a query that plainly has matches. */
if ($af_empty && $af_paged > 1 && $af_found > 0) : ?>

    <div class="af-sr-empty">
        <p class="af-sr-eyebrow">End of results</p>
        <h2>Nothing further on this page.</h2>
        <p class="af-sr-sub">Some pieces further down the list are no longer on display.</p>
        <div class="af-sr-actions">
            <a class="solid" href="<?php echo esc_url(remove_query_arg('paged')); ?>">Back to the first page</a>
        </div>
    </div>

<?php elseif ($af_empty) :

    $af_flat = function_exists('af_search_flatten') ? af_search_flatten($af_term) : '';
    $af_is_code = ($af_flat !== '' && preg_match('/^[a-z]{1,4}[0-9]{2,6}$/', $af_flat));
    ?>

    <div class="af-sr-empty">
        <p class="af-sr-eyebrow">No matches</p>
        <h1>Nothing hanging under &ldquo;<?php echo esc_html($af_term); ?>&rdquo;.</h1>
        <p class="af-sr-sub">
            <?php if ($af_is_code) : ?>
                Art codes look like RK 0118 or HD 15-GF. Spacing and hyphens don&rsquo;t matter —
                RK-0118, RK 0118 and rk0118 all find the same piece — so it is worth checking
                the letters and the digits.
            <?php else : ?>
                Try a single word: an artist or deity name, a colour, a room — or the art code
                printed in the brochure.
            <?php endif; ?>
        </p>

        <?php af_sr_form($af_term); ?>

        <?php
        $af_cats = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 6,
            'parent'     => 0,
        ));
        $af_skip = array('uncategorized', 'frame-colors', 'sizes');
        if ($af_cats && !is_wp_error($af_cats)) :
            $af_shown = array();
            foreach ($af_cats as $af_c) {
                if (in_array($af_c->slug, $af_skip, true)) continue;
                $af_shown[] = $af_c;
            }
            if ($af_shown) : ?>
                <div class="af-sr-pills">
                    <?php foreach ($af_shown as $af_c) : ?>
                        <a href="<?php echo esc_url(get_term_link($af_c)); ?>"><?php echo esc_html($af_c->name); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif;
        endif; ?>

        <div class="af-sr-actions">
            <a class="solid" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>">Browse the shop</a>
            <a href="<?php echo esc_url(home_url('/')); ?>">Back to home</a>
        </div>
    </div>

<?php else : ?>

    <?php if ($af_prods) : ?>
    <section class="af-sr-sec woocommerce">
        <?php if ($af_others) : ?><h2 class="af-sr-h2">Artworks</h2><?php endif; ?>
        <?php
        /* Loop props so the parent's content-product.php reads sane values and
           the wrapper comes out as "products columns-3" — the same three
           across the shop uses (loop_shop_columns is filtered to 3). */
        if (function_exists('wc_setup_loop')) {
            wc_setup_loop(array(
                'columns'      => 3,
                'name'         => 'search',
                'total'        => count($af_prods),
                'per_page'     => count($af_prods),
                'current_page' => 1,
                'total_pages'  => 1,
                'is_paginated' => false,
            ));
        }
        woocommerce_product_loop_start();

        $af_keep = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
        foreach ($af_prods as $af_row) {
            $GLOBALS['post'] = $af_row[0];
            setup_postdata($GLOBALS['post']);
            /* REQUIRED. setup_postdata() does not fire the `the_post` action,
               which is what normally populates global $product — and the art
               code line reads `global $product` directly. Without this every
               card loses the very thing customers search by. */
            $GLOBALS['product'] = $af_row[1];
            wc_get_template_part('content', 'product');
        }
        unset($GLOBALS['product']);
        wp_reset_postdata();          // this overwrites $GLOBALS['post'] …
        $GLOBALS['post'] = $af_keep;  // … so restore ours after, not before
        if (function_exists('wc_reset_loop')) wc_reset_loop();

        woocommerce_product_loop_end();
        ?>
    </section>
    <?php endif; ?>

    <?php if ($af_others) : ?>
    <section class="af-sr-sec">
        <h2 class="af-sr-h2"><?php
            echo $af_prods ? 'Articles &amp; pages' : 'No artwork matched — but we wrote about it';
        ?></h2>
        <ul class="af-sr-posts">
            <?php foreach ($af_others as $af_p) :
                $af_ex = get_the_excerpt($af_p);
                if ($af_ex === '') $af_ex = wp_strip_all_tags($af_p->post_content);
                $af_kind = (get_post_type($af_p) === 'page') ? 'Page' : 'Article';
                ?>
                <li class="af-sr-post">
                    <?php if (has_post_thumbnail($af_p)) : ?>
                        <a class="af-sr-post-thumb" href="<?php echo esc_url(get_permalink($af_p)); ?>" aria-hidden="true" tabindex="-1">
                            <?php echo get_the_post_thumbnail($af_p, 'thumbnail'); ?>
                        </a>
                    <?php endif; ?>
                    <div class="af-sr-post-body">
                        <span class="af-sr-post-kind"><?php echo esc_html($af_kind); ?></span>
                        <h3><a href="<?php echo esc_url(get_permalink($af_p)); ?>"><?php echo esc_html(get_the_title($af_p)); ?></a></h3>
                        <p><?php echo esc_html(wp_trim_words($af_ex, 28)); ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php
    if ($wpq->max_num_pages > 1) {
        /* add_query_arg on the current URL, never a hand-built link: it keeps
           ?s= (and post_type=). The search module reads the term from the
           request and canonical redirects are switched off for searches, so a
           link that dropped the term would silently lose every widened match
           on page two with nothing to catch it. */
        echo '<nav class="af-sr-pager" aria-label="Search results pages">' . paginate_links(array(
            'base'      => esc_url_raw(add_query_arg('paged', '%#%')),
            'format'    => '',
            'current'   => $af_paged,
            'total'     => (int) $wpq->max_num_pages,
            'mid_size'  => 2,
            'prev_text' => '&lsaquo; Prev',
            'next_text' => 'Next &rsaquo;',
            'type'      => 'list',
        )) . '</nav>';
    }
    ?>

<?php endif; ?>

</div><!-- .af-sr -->

<style id="af-search-results">
/* ═══ A. LAYOUT ══════════════════════════════════════════════════════════
   Un-float the parent's #primary — that narrow blog column is why results
   looked like a blog. .col-full is deliberately NOT touched: the parent's
   header and footer use it too. */
body.search #primary{width:100%!important;float:none!important;margin:0!important;}
body.search .site-main{max-width:none!important;width:100%!important;}
body.search #secondary,body.search .widget-area,body.search .sidebar{display:none!important;}
.af-sr{max-width:1200px;margin:0 auto;padding:8px 16px 56px;}
.af-sr-head{margin:0 0 26px;}
.af-sr-sec{margin:0 0 42px;}

/* ═══ B. HEADINGS — copy of the archive page title (custom.css:2346), which is
   scoped to body.tax-product_cat and so cannot be inherited here. */
.af-sr-eyebrow{display:inline-block;color:#c9a84c;font-size:12.5px;font-weight:700;
  letter-spacing:.22em;text-transform:uppercase;margin:0 0 10px;}
.af-sr-title{font-size:clamp(24px,3vw,34px);font-weight:800;color:#1a1a1a;
  margin:0 0 10px;padding-bottom:12px;position:relative;line-height:1.2;}
.af-sr-title::after{content:"";position:absolute;left:0;bottom:0;width:64px;height:4px;
  border-radius:2px;background:linear-gradient(90deg,#c9a84c,#e0c26a);}
.af-sr-title em{font-style:normal;color:#a8872e;}
.af-sr-count{color:#7b7365;font-size:13.5px;margin:0 0 16px;}
.af-sr-h2{font-size:22px;font-weight:800;color:#1a1a1a;margin:0 0 16px;}

/* ═══ C. REFINE FORM AND PILLS — 404.php's tokens ══════════════════════ */
.af-sr-refine{display:flex;gap:8px;max-width:440px;margin:0 0 4px;}
.af-sr-refine input[type=search]{flex:1;min-width:0;padding:13px 15px;border:1.5px solid #e2d9c4;
  border-radius:11px;font-size:14px;background:#fffdf8;color:#1a1a1a;font-family:inherit;}
.af-sr-refine input[type=search]:focus{outline:none;border-color:#c9a84c;}
.af-sr-refine button{padding:13px 22px;border:0;border-radius:11px;background:#1a1a1a;
  color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;}
.af-sr-refine button:hover{background:#000;}
.af-sr-pills{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 26px;}
.af-sr-pills a{padding:8px 15px;border:1.5px solid #e2d9c4;border-radius:999px;
  background:#fffdf8;color:#5a5140;font-size:12.5px;font-weight:700;text-decoration:none;}
.af-sr-pills a:hover{border-color:#1a1a1a;background:#1a1a1a;color:#fff;}

/* ═══ D. THE GRID — the archive's copy is body.tax-product_cat-scoped and the
   other is PHP-gated to is_shop(), so neither reaches this page. Restated. */
.af-sr ul.products{display:grid!important;grid-template-columns:repeat(3,1fr)!important;
  gap:22px!important;margin:0!important;padding:0!important;list-style:none!important;}
.af-sr ul.products::before,.af-sr ul.products::after{display:none!important;content:none!important;}
.af-sr ul.products li.product{width:auto!important;max-width:100%!important;margin:0!important;
  float:none!important;clear:none!important;height:auto!important;position:relative!important;}
@media(max-width:1024px){.af-sr ul.products{grid-template-columns:repeat(2,1fr)!important;gap:16px!important;}}
@media(max-width:560px){.af-sr ul.products{grid-template-columns:1fr!important;}}

/* ═══ E. THE IMAGE CHAIN — the critical block ═══════════════════════════
   Copy of the archive's rules (custom.css:2446-2470), re-scoped.

   The `html body div.af-sr` prefix is load-bearing, not cosmetic. It has to
   outrank two rules that fire on any page the moment a .woocommerce wrapper
   exists — functions.php:1501 (height:0, padding-bottom:75%, background:#f5f5f5)
   and custom.css:2255 (aspect-ratio:1/1) — both of which target
   a.woocommerce-loop-product__link. In THIS theme that link is EMPTY: it is the
   click layer over the artwork, not a box beneath it. Left alone, those two
   rules paint a full-width grey band under every canvas. Do not simplify these
   selectors away.

   The band is a fixed 300px on #f5f2ed, not an aspect ratio, because canvas art
   is mixed-orientation — this is exactly what every category page does. */
html body div.af-sr ul.products li.product .product-block .product-transition{
  position:relative!important;display:block!important;width:100%!important;
  height:300px!important;max-height:300px!important;overflow:hidden!important;
  background:#f5f2ed!important;flex-shrink:0!important;
  aspect-ratio:auto!important;padding-bottom:0!important;}
html body div.af-sr ul.products li.product a.woocommerce-loop-product__link{
  position:absolute!important;inset:0!important;top:0!important;left:0!important;
  display:block!important;width:100%!important;height:100%!important;
  max-height:none!important;min-height:0!important;aspect-ratio:auto!important;
  margin:0!important;padding:0!important;padding-bottom:0!important;
  background:transparent!important;z-index:2!important;}
/* The action buttons sit ABOVE that overlay — a positioned element paints over
   static siblings whatever the source order, so without this the click layer
   swallows every press of Add to cart, wishlist and Quick view. */
html body div.af-sr ul.products li.product .product-transition .group-action,
html body div.af-sr ul.products li.product .product-transition .onsale,
html body div.af-sr ul.products li.product .af-icon-corner{
  position:relative!important;z-index:3!important;}
html body div.af-sr ul.products li.product .product-img-wrap{
  height:300px!important;max-height:300px!important;min-height:300px!important;
  overflow:hidden!important;position:relative!important;display:block!important;width:100%!important;}
html body div.af-sr ul.products li.product .product-img-wrap .inner{
  position:relative!important;height:100%!important;width:100%!important;display:block!important;}
html body div.af-sr ul.products li.product .product-img-wrap .inner > *{
  position:absolute!important;top:0!important;left:0!important;right:0!important;bottom:0!important;
  height:100%!important;width:100%!important;margin:0!important;display:block!important;}
html body div.af-sr ul.products li.product .product-img-wrap img,
html body div.af-sr ul.products li.product .product-transition img,
html body div.af-sr ul.products li.product a.woocommerce-loop-product__link img{
  position:absolute!important;top:0!important;left:0!important;
  width:100%!important;height:100%!important;object-fit:cover!important;
  display:block!important;max-width:none!important;max-height:100%!important;margin:0!important;}
@media(max-width:520px){
  html body div.af-sr ul.products li.product .product-block .product-transition,
  html body div.af-sr ul.products li.product .product-img-wrap{
    height:260px!important;max-height:260px!important;min-height:260px!important;}}

/* ═══ F. CARD CONTENT — the shop's card, restated. The original block is
   scoped to body.tax-product_cat / body.woocommerce-page; this page is
   deliberately given neither body class, because adding woocommerce-page would
   switch on a great deal of parent-theme behaviour nobody here can read. */
html body div.af-sr ul.products li.product{
  display:flex!important;flex-direction:column!important;padding:0 0 4px!important;
  background:#fff!important;border:1px solid #ececec!important;border-radius:12px!important;
  overflow:hidden!important;box-shadow:0 2px 12px rgba(0,0,0,.08)!important;
  transition:box-shadow .25s,transform .25s!important;}
html body div.af-sr ul.products li.product:hover{
  box-shadow:0 8px 28px rgba(0,0,0,.14)!important;transform:translateY(-2px)!important;}
/* Equal-height cards: pin the price to the bottom so a row lines up whether or
   not a card carries an art code or a second line of title. */
html body div.af-sr ul.products li.product .price{margin-top:auto!important;}
html body div.af-sr ul.products li.product .woocommerce-loop-product__title{
  font-size:13.5px!important;font-weight:600!important;color:#1a1a1a!important;
  line-height:1.45!important;padding:13px 16px 3px!important;margin:0!important;
  height:56px!important;overflow:hidden!important;display:-webkit-box!important;
  -webkit-line-clamp:2!important;-webkit-box-orient:vertical!important;box-sizing:border-box!important;}
html body div.af-sr ul.products li.product .woocommerce-product-rating,
html body div.af-sr ul.products li.product .rating{
  display:flex!important;align-items:center!important;padding-left:0!important;
  padding-right:16px!important;margin:8px 0 2px!important;box-sizing:border-box!important;}
html body div.af-sr ul.products li.product .star-rating{
  font-size:12px!important;margin:0 0 0 16px!important;padding:0!important;float:none!important;}
html body div.af-sr ul.products li.product .price{
  display:flex!important;align-items:baseline!important;gap:6px!important;
  flex-wrap:wrap!important;padding:2px 16px 14px!important;box-sizing:border-box!important;}
html body div.af-sr ul.products li.product .price ins{
  font-size:15px!important;font-weight:700!important;color:#1a1a1a!important;text-decoration:none!important;}
html body div.af-sr ul.products li.product .price del{
  font-size:12px!important;color:#aaa!important;text-decoration:line-through!important;font-weight:400!important;}
html body div.af-sr ul.products li.product .af-art-code--card{
  padding:0 16px!important;margin:2px 0 4px!important;box-sizing:border-box!important;width:100%!important;}
html body div.af-sr ul.products li.product .af-card-vars{
  padding:4px 16px 14px!important;margin:2px 0 0!important;box-sizing:border-box!important;width:100%!important;}
/* The price row already prints the green saving; the standalone badge would
   say it twice. This is what the archive does. */
html body div.af-sr ul.products li.product .af-shop-discount-badge{display:none!important;}
@media(max-width:640px){
  html body div.af-sr ul.products li.product .woocommerce-loop-product__title{
    font-size:12.5px!important;height:52px!important;padding:11px 12px 3px!important;}
  html body div.af-sr ul.products li.product .woocommerce-product-rating,
  html body div.af-sr ul.products li.product .rating{padding:5px 12px 2px 0!important;}
  html body div.af-sr ul.products li.product .star-rating{margin-left:12px!important;}
  html body div.af-sr ul.products li.product .af-art-code--card{padding:0 12px!important;}
  html body div.af-sr ul.products li.product .af-card-vars{padding:4px 12px 12px!important;}
  html body div.af-sr ul.products li.product .price{padding:2px 12px 12px!important;}}

/* ═══ G. "TRY ON WALL" — the markup is ungated, its CSS is gated to
   is_shop()/is_product_category(). Without this it prints as a stray inline
   link with an SVG in the middle of every card. */
.af-sr .af-card-ar{position:absolute;left:10px;bottom:64px;z-index:6;display:inline-flex;
  align-items:center;gap:5px;background:rgba(20,20,20,.86);color:#fff;font-size:11.5px;
  font-weight:600;padding:6px 10px;border-radius:20px;text-decoration:none;opacity:0;
  transform:translateY(6px);transition:opacity .25s,transform .25s,background .2s;}
.af-sr ul.products li.product:hover .af-card-ar{opacity:1;transform:translateY(0);}
.af-sr .af-card-ar:hover{background:#c9a84c;}
.af-sr .af-card-ar svg{width:14px;height:14px;}
@media(max-width:768px){
  .af-sr .af-card-ar{opacity:1;transform:none;left:8px;top:8px;bottom:auto;padding:5px 8px;font-size:11px;}
  .af-sr .af-card-ar span{display:none;}}

/* ═══ H. SWATCHES AND "FROM $x" — same story: markup ungated, CSS gated. */
.af-sr .af-card-vars{display:flex;align-items:center;gap:8px;flex-wrap:wrap;
  font-size:11.5px;color:#8a6d1f;font-weight:700;}
.af-sr .af-card-dots{display:inline-flex;gap:4px;}
.af-sr .af-card-dots i{width:14px;height:14px;border-radius:50%;border:1.5px solid #fff;
  box-shadow:0 0 0 1px #d9d0bd;display:inline-block;}
.af-sr .af-card-vars a{color:#8a6d1f;text-decoration:none;display:inline-flex;
  align-items:center;gap:8px;flex-wrap:wrap;}
.af-sr .af-card-vars a:hover{color:#141414;}
.af-sr .af-card-from{color:#141414;font-weight:800;}

/* ═══ I. ARTICLES AND PAGES — deliberately not a card that looks like a
   product, and carrying no class containing "product": two separate injectors
   on this site match li.product / [class*="type-product"] and would staple an
   ART CODE line and a VIEW BROCHURE button onto a blog post. That is exactly
   what the old page did. */
.af-sr-posts{list-style:none;margin:0;padding:0;display:grid;gap:12px;}
.af-sr-post{display:flex;gap:16px;align-items:flex-start;background:#fff;
  border:1px solid #ece5d4;border-radius:14px;padding:14px 16px;
  box-shadow:0 3px 14px rgba(20,20,20,.05);transition:transform .22s ease,box-shadow .22s ease;}
.af-sr-post:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(20,20,20,.10);}
.af-sr-post-thumb{flex:0 0 120px;height:90px;border-radius:10px;overflow:hidden;
  background:#f5f2ed;display:block;}
.af-sr-post-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.af-sr-post-body{min-width:0;}
.af-sr-post-kind{display:inline-block;font-size:11.5px;font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:#a8872e;margin:0 0 4px;}
.af-sr-post h3{font-size:16px;font-weight:700;color:#1a1a1a;line-height:1.35;margin:0 0 5px;}
.af-sr-post h3 a{color:inherit;text-decoration:none;}
.af-sr-post h3 a:hover{color:#a8872e;}
.af-sr-post p{color:#6b6b6b;font-size:14.5px;line-height:1.7;margin:0;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
@media(max-width:560px){.af-sr-post-thumb{flex:0 0 84px;height:70px;}}

/* ═══ J. PAGINATION ════════════════════════════════════════════════════ */
.af-sr-pager{margin:36px 0 0;display:flex;justify-content:center;}
.af-sr-pager ul{list-style:none;display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;}
.af-sr-pager li{margin:0;}
.af-sr-pager .page-numbers{display:inline-flex;align-items:center;justify-content:center;
  min-width:40px;height:40px;padding:0 12px;border:1.5px solid #e2d9c4;border-radius:11px;
  background:#fffdf8;color:#5a5140;font-size:13.5px;font-weight:700;text-decoration:none;}
.af-sr-pager .page-numbers:hover{border-color:#1a1a1a;background:#1a1a1a;color:#fff;}
.af-sr-pager .page-numbers.current{background:#c9a84c;border-color:#c9a84c;color:#fff;}

/* ═══ K. EMPTY STATE — 404.php's tokens, the child theme's existing
   dead-end-that-still-sells page. */
.af-sr-empty{max-width:760px;margin:0 auto;text-align:center;padding:50px 0 80px;}
.af-sr-empty h1,.af-sr-empty h2{font-size:clamp(24px,3.4vw,36px);margin:0 0 12px;
  letter-spacing:-.5px;color:#1a1a1a;line-height:1.2;}
.af-sr-sub{color:#6b6250;font-size:15.5px;line-height:1.7;margin:0 auto 26px;max-width:540px;}
.af-sr-empty .af-sr-refine{margin:0 auto 22px;justify-content:center;}
.af-sr-empty .af-sr-pills{justify-content:center;}
.af-sr-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:8px;}
.af-sr-actions a{padding:13px 22px;border:1.5px solid #e2d9c4;border-radius:11px;
  background:#fffdf8;color:#5a5140;font-weight:700;font-size:13.5px;text-decoration:none;}
.af-sr-actions a:hover{border-color:#c9a84c;}
.af-sr-actions a.solid{background:#1a1a1a;border-color:#1a1a1a;color:#fff;}
.af-sr-actions a.solid:hover{background:#000;}
</style>

<?php
get_footer();
