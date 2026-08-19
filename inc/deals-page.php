<?php
/**
 * /clearance/ — every product discounted 40% or more, shown as the shop page.
 *
 * Owner request (2026-08-18, the Stock Clearance banner video): a page holding
 * only the pieces whose discount beats a threshold, with everything the shop
 * archive has — the sidebar filters, colours, sizes, tags, three-across cards.
 *
 * Rather than build a lookalike template, the REAL shop archive is reused: a
 * rewrite maps /clearance/ to the product archive carrying an af_deal query
 * var, and the product query is then restricted to the ids whose discount
 * qualifies. Every filter, widget and card behaviour that works on the shop
 * works here, because it IS the shop.
 *
 * The threshold rides in the URL (/clearance/ = 40, /clearance/60/ = 60), so a
 * deeper-cut page needs no new code. Ids are computed from _price against
 * _regular_price — variations included, credited to their parent — and cached
 * for fifteen minutes.
 */
if (!defined('ABSPATH')) exit;

/** Product ids whose discount is at least $min percent. */
function af_deal_ids($min) {
    $min = max(1, min(95, (int) $min));
    $key = 'af_deal_ids_' . $min;
    $ids = get_transient($key);
    if (is_array($ids)) return $ids;

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT p.ID, p.post_parent, p.post_type,
                pr.meta_value AS price, rg.meta_value AS regular
           FROM {$wpdb->posts} p
           JOIN {$wpdb->postmeta} pr ON pr.post_id = p.ID AND pr.meta_key = '_price'
           JOIN {$wpdb->postmeta} rg ON rg.post_id = p.ID AND rg.meta_key = '_regular_price'
          WHERE p.post_type IN ('product','product_variation')
            AND p.post_status = 'publish'");
    $best = array();
    foreach ($rows as $r) {
        $price   = (float) $r->price;
        $regular = (float) $r->regular;
        if ($regular <= 0 || $price <= 0 || $price >= $regular) continue;
        $pct = ($regular - $price) / $regular * 100;
        // a variation's discount belongs to the product the shop lists
        $pid = ($r->post_type === 'product_variation' && $r->post_parent)
             ? (int) $r->post_parent : (int) $r->ID;
        if (!isset($best[$pid]) || $pct > $best[$pid]) $best[$pid] = $pct;
    }
    $ids = array();
    foreach ($best as $pid => $pct) {
        if ($pct + 0.0001 >= $min) $ids[] = $pid;
    }
    // only products that are actually visible in the catalogue
    if ($ids) {
        $ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish',
            'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'post__in' => $ids));
    }
    set_transient($key, $ids, 15 * MINUTE_IN_SECONDS);
    return $ids;
}

add_filter('query_vars', function ($vars) {
    $vars[] = 'af_deal';
    return $vars;
});

add_action('init', function () {
    add_rewrite_rule('^clearance/?$',
        'index.php?post_type=product&af_deal=40', 'top');
    add_rewrite_rule('^clearance/(\d{1,2})/?$',
        'index.php?post_type=product&af_deal=$matches[1]', 'top');
});

/** Restrict the product archive to qualifying ids when af_deal is present. */
add_action('woocommerce_product_query', function ($q) {
    $min = (int) get_query_var('af_deal');
    if ($min < 1) return;
    $ids = af_deal_ids($min);
    // no qualifying product: an impossible id shows the shop's own "no
    // products" state instead of every product
    $q->set('post__in', $ids ? $ids : array(0));
});

/** The page must say what it is, not "Products". */
add_filter('woocommerce_page_title', function ($title) {
    $min = (int) get_query_var('af_deal');
    return $min >= 1 ? sprintf('Stock Clearance — %d%%+ Off', $min) : $title;
});
add_filter('pre_get_document_title', function ($title) {
    $min = (int) get_query_var('af_deal');
    return $min >= 1
        ? sprintf('Stock Clearance — %d%%+ Off | The Art Framer', $min)
        : $title;
}, 20);
add_action('woocommerce_archive_description', function () {
    $min = (int) get_query_var('af_deal');
    if ($min < 1) return;
    printf('<p class="af-deal-sub">Every piece below is at least <strong>%d%% off</strong> its regular price — while stock lasts.</p>', $min);
    echo '<style>.af-deal-sub{color:#6b6b6b;font-size:14.5px;margin:4px 0 10px}.af-deal-sub strong{color:#8a6d1f}</style>';
});

/**
 * The homepage's Stock Clearance banner should LEAD here. The banner is
 * Elementor content, so its image is linked in the browser rather than by
 * editing the page: any banner image whose filename or alt speaks of the
 * clearance sale gets the /clearance/ link — wrapped if it has no link, or
 * repointed if its link is dead ("#" or the homepage itself).
 */
add_action('wp_footer', function () {
    if (!is_front_page()) return;
    ?>
<script id="af-deal-banner-link">
(function(){
  var URL = <?php echo wp_json_encode(home_url('/clearance/')); ?>;
  var SALE = /clearance|stock[-_ ]?clear|upto[-_ ]?40|40[-_ ]?off/;

  // The first version wrapped the banner <img> in a link. That held only until
  // the widget that owns the banner re-rendered its DOM and threw the wrapper
  // away — and it never worked at all when the banner is painted as a CSS
  // background rather than an <img>. So: identify the banner (img OR
  // background-image, by filename/alt or by the Stock Clearance heading), and
  // navigate from a DELEGATED capture-phase click handler on the document,
  // which no re-render can remove.
  function isBanner(el){
    if (!el || el.nodeType !== 1) return false;
    if (el.dataset && el.dataset.afDealBanner) return true;
    if (el.tagName === 'IMG') {
      var hay = ((el.getAttribute('src') || '') + ' ' + (el.getAttribute('alt') || '')).toLowerCase();
      if (SALE.test(hay)) return true;
    }
    var bg = (el.style && el.style.backgroundImage) || '';
    if (!bg && window.getComputedStyle) bg = getComputedStyle(el).backgroundImage || '';
    return bg && bg !== 'none' && SALE.test(bg.toLowerCase());
  }

  // mark the banner under the Stock Clearance heading, for files whose name
  // says nothing about the sale (a Canva export)
  function markByHeading(){
    var heads = document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title');
    for (var i = 0; i < heads.length; i++) {
      if (!/stock\s*clearance/i.test(heads[i].textContent || '')) continue;
      var sec = heads[i].closest('section, .e-con, .elementor-section') || heads[i].parentElement;
      for (var hop = 0; sec && hop < 3; hop++) {
        var els = sec.querySelectorAll('img, [style*="background-image"], .elementor-widget-image, .rev_slider, [class*="slide"]');
        for (var j = 0; j < els.length; j++) {
          var r = els[j].getBoundingClientRect();
          if (r.width > 400 && r.height > 120) {
            els[j].dataset.afDealBanner = '1';
            els[j].style.setProperty('cursor', 'pointer');
            return;
          }
        }
        sec = sec.nextElementSibling;
      }
      return;
    }
  }
  function paintCursor(){
    document.querySelectorAll('img').forEach(function(img){
      if (isBanner(img)) img.style.setProperty('cursor', 'pointer');
    });
  }

  document.addEventListener('click', function(e){
    // walk from the click target upwards; the first banner hit navigates
    for (var el = e.target; el && el !== document.body; el = el.parentElement) {
      if (el.tagName === 'A' && el.getAttribute('href')
          && el.getAttribute('href') !== '#'
          && el.getAttribute('href').indexOf('javascript:') !== 0) return; // a real link wins
      if (isBanner(el)) {
        e.preventDefault();
        window.location.href = URL;
        return;
      }
    }
  }, true);

  function boot(){ markByHeading(); paintCursor(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.addEventListener('load', boot);
  [800, 2000, 4000].forEach(function(d){ setTimeout(boot, d); });
})();
</script>
    <?php
}, 26);
