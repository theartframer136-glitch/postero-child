<?php
if (!defined('ABSPATH')) exit;
/**
 * Hide the second video carousel under "Products In Motion".
 *
 * Owner, 2026-09-07: "here we have 2 video section keep the top video slider
 * section and remove the buttom video section".
 *
 * ── WHY THIS FILE WAS REWRITTEN (2026-09-08) ──────────────────────────────
 * The first version hid the whole Elementor container d49c1e8, and that
 * container holds more than the video carousel: the New Arrivals product
 * carousel is inside it too. So New Arrivals went blank the day this module
 * started loading, and stayed blank through a week of looking everywhere else.
 * The browser finally said it plainly — the products carousel is initialised,
 * has 42 slides, and every one of its ancestors is 0px tall because one of
 * them is display:none:
 *
 *   DIV.products swiper-wrapper            h=0
 *   DIV.woocommerce eael-woo-product-...   h=0
 *   DIV.swiper-container-wrap              h=0
 *   DIV.elementor-element ...              h=0
 *   DIV.e-con-inner                        h=0
 *   DIV.elementor-element ...              h=0  d=none   <-- this rule
 *   DIV.elementor elementor-75             h=11148
 *
 * Two lessons kept: hide the WIDGET, never the container — a container is
 * someone else's furniture as well as yours; and never hide anything that has
 * products inside it, which the guard below enforces at runtime rather than
 * trusting a hand-copied element id.
 *
 * ── AND NO OUTPUT BUFFERING ───────────────────────────────────────────────
 * The old version also opened an output buffer on that container and threw the
 * contents away. It never fired on this Elementor version (the markup is all
 * present in the HTML), so it was doing nothing but carrying the risk that an
 * unbalanced buffer swallows the rest of the page. Gone.
 */

/** The container the video carousel lives in. Filterable, no deploy needed. */
function af_hidden_carousel_id() {
    return (string) apply_filters('af_hidden_carousel_id', 'd49c1e8');
}

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return;
    $id = esc_attr(af_hidden_carousel_id()); ?>
<style>
/* Only the video widgets inside that container — every Elementor spelling of
   "a widget that plays video". The container itself, and anything else sharing
   it, is left alone. */
.elementor-element-<?php echo $id; ?> > .e-con-inner > .elementor-widget-video,
.elementor-element-<?php echo $id; ?> .elementor-widget-video,
.elementor-element-<?php echo $id; ?> .elementor-widget-video-playlist,
.elementor-element-<?php echo $id; ?> .elementor-widget-n-carousel,
.elementor-element-<?php echo $id; ?> .elementor-widget-media-carousel{
    display:none !important;
}
</style>
<?php }, 20);

/**
 * The guard. If anything on the page is hiding a block that contains product
 * cards — this file's rule, a leftover from an older deploy still in a cached
 * stylesheet, or a hand edit in Elementor — un-hide it. A hidden video is a
 * cosmetic preference; a hidden row of products is a hole in the shop, and the
 * shop wins every time.
 */
add_action('wp_footer', function () {
    if (!is_front_page() && !is_home()) return; ?>
<script>
(function(){
  function unhideProductRows(){
    document.querySelectorAll('.products, .product-card, ul.products, .eael-woo-product-carousel').forEach(function(row){
      var n = row;
      for (var i = 0; i < 14 && n && n !== document.body; i++, n = n.parentElement) {
        var cs = getComputedStyle(n);
        if (cs.display === 'none' || cs.visibility === 'hidden') {
          n.style.setProperty('display', 'block', 'important');
          n.style.setProperty('visibility', 'visible', 'important');
          n.setAttribute('data-af-unhidden', '1');
        }
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', unhideProductRows);
  else unhideProductRows();
  window.addEventListener('load', unhideProductRows);
  setTimeout(unhideProductRows, 1500);
})();
</script>
<?php }, 96);
