<?php
/**
 * Corporate Printing in "Shop by Collection".
 *
 * WHY THIS EXISTS, measured rather than assumed.
 *
 * The tab is now in the strip - it was added to the Elementor HTML widget that
 * holds that hand-written list. But clicking it did nothing at all: the product
 * grid kept whatever the previous tab had put there, and so did the circle row.
 *
 * The server is not the problem. Asked directly, the theme's own endpoint
 * answers for this slug exactly as it does for the others:
 *
 *     action=load_products&subcategory=banners-signage     ->   5 cards
 *     action=load_products&subcategory=corporate-printing  ->  33 cards
 *     action=load_products&subcategory=art-accessories     ->  36 cards
 *
 * So the data is there and the fault is on the page: the theme's click handler
 * only acts on the tabs it shipped with, and a tab someone adds later is one
 * it does not recognise. Nothing in the parent theme can be edited to fix that
 * without touching a theme this site does not own.
 *
 * So the child theme drives this one tab itself, using the same contract the
 * theme uses - its own endpoint, its own card markup, its own circle markup -
 * which is the approach already proven for the Embossed Prints tab next door.
 * Everything else on the strip is left to the theme, untouched.
 */
if (!defined('ABSPATH')) exit;

function af_corp_slug() { return 'corporate-printing'; }

function af_corp_term() {
    static $t = false;
    if ($t !== false) return $t;
    $x = get_term_by('slug', af_corp_slug(), 'product_cat');
    $t = ($x && !is_wp_error($x)) ? $x : null;
    return $t;
}

/**
 * The tab's own circle row: this category's sub-collections.
 *
 * Empty ones are kept. The section is stocked, but a sub-collection can be
 * waiting on its first piece, and hiding it makes the category look like it
 * has less structure than it has - the same mistake that hid the embossed
 * finishes.
 */
function af_corp_payload() {
    static $payload = null;
    if ($payload !== null) return $payload;
    $payload = null;

    $term = af_corp_term();
    if (!$term) return $payload;
    $url = get_term_link($term);
    if (is_wp_error($url)) return $payload;

    $circles = array();
    $kids = get_terms(array(
        'taxonomy' => 'product_cat', 'parent' => (int) $term->term_id,
        'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC',
    ));
    if (!is_wp_error($kids)) {
        foreach ($kids as $kid) {
            $kurl = get_term_link($kid);
            if (is_wp_error($kurl)) continue;
            $thumb = (int) get_term_meta($kid->term_id, 'thumbnail_id', true);
            $img = $thumb ? wp_get_attachment_image_url($thumb, 'woocommerce_thumbnail') : '';
            $circles[] = array(
                's' => $kid->slug,
                'n' => html_entity_decode($kid->name, ENT_QUOTES, 'UTF-8'),
                'u' => $kurl,
                'i' => $img ? $img : '',
            );
        }
    }

    $payload = array(
        'slug'    => $term->slug,
        'label'   => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
        'url'     => $url,
        'count'   => (int) $term->count,
        'circles' => $circles,
        'ajax'    => admin_url('admin-ajax.php'),
    );
    return $payload;
}

add_action('wp_footer', function () {
    if (!is_front_page()) return;
    $cp = af_corp_payload();
    if (!$cp) return;
    ?>
<style id="af-cp-collection-css">
/* A sub-collection with no picture yet keeps a real <img>, so it takes the
   size and shape the theme gives its own circles; the disc is drawn inside
   that image (see blankDisc) rather than styled around it. */
.af-cp-circle--blank img { border-radius: 50% !important; object-fit: cover !important; }
</style>
<script id="af-cp-collection-js">
(function () {
  var CP = <?php echo wp_json_encode($cp); ?>;
  if (!CP || !CP.slug) return;

  var TAB_STRIP  = '#topCatSlider, .top-category-slider';
  var TAB_ITEM   = '.top-cat-btn';
  var CIRC_STRIP = '#subcategorySlider, .subcategory-slider, ul.postero-scroll-content';
  var CIRC_ITEM  = 'li.cat-item, .sub-cat';
  var GRID_SEL   = '#productGrid, .product-slider, .custom-product-track, ul.products';
  var busy = false;

  function ourTab() {
    var tabs = document.querySelectorAll(TAB_ITEM);
    for (var i = 0; i < tabs.length; i++) {
      if ((tabs[i].getAttribute('data-cat') || '') === CP.slug) return tabs[i];
    }
    return null;
  }

  // Write into #productGrid, and nowhere else.
  //
  // This needed reading the site's own slider code rather than guessing. The
  // carousel people actually see is built by functions.php from the cards in
  // #productGrid: it lifts them into a .af-shell-track, hides the grid, and
  // keeps a MutationObserver on the GRID so that when new cards are loaded
  // into it the shell is rebuilt from them.
  //
  // That makes #productGrid the integration point. An earlier version here
  // aimed at the cards' own parent instead, on the grounds that the grid
  // measured empty - but it measures empty precisely because the shell has
  // taken its cards, and writing into the shell's track puts the cards
  // somewhere nothing is watching, so the next rebuild discards them.
  function gridFor(tab) {
    for (var n = tab; n && n !== document.body; n = n.parentElement) {
      var g = n.querySelector('#productGrid') || n.querySelector(GRID_SEL);
      if (g && !g.contains(tab)) return g;
    }
    return document.querySelector('#productGrid') || document.querySelector(GRID_SEL);
  }

  function hasCards(html) {
    return !!html && (html.indexOf('product-card') !== -1 || html.indexOf('woocommerce-loop-product') !== -1
      || html.indexOf('class="product') !== -1);
  }

  function post(body) {
    return fetch(CP.ajax, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (r) { return r.ok ? r.text() : ''; }).catch(function () { return ''; });
  }

  /* ---- the circles ------------------------------------------------------ */
  function circleStrip() {
    var strips = document.querySelectorAll(CIRC_STRIP);
    for (var i = 0; i < strips.length; i++) if (strips[i].offsetParent) return strips[i];
    return strips[0] || null;
  }

  // A round swatch with one letter in it, as a data URI: no request, no
  // placeholder file to deploy, and it scales to whatever box the theme gives
  // it. Used only where a sub-collection has no thumbnail yet.
  function blankDisc(name) {
    var ch = (name || '?').trim().charAt(0).toUpperCase().replace(/[<>&"']/g, '');
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120">' +
              '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' +
              '<stop offset="0" stop-color="#efe7db"/><stop offset="1" stop-color="#cfc3ad"/>' +
              '</linearGradient></defs>' +
              '<circle cx="60" cy="60" r="60" fill="url(#g)"/>' +
              '<text x="60" y="60" text-anchor="middle" dominant-baseline="central" ' +
              'font-family="Georgia,serif" font-size="52" fill="#6b5b3e">' + ch + '</text></svg>';
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  function makeCircle(sample, c) {
    var el = sample.cloneNode(true);
    el.classList.remove('active');
    el.classList.add('af-cp-circle');
    var img = el.querySelector('img');
    if (img) {
      if (c.i) {
        img.setAttribute('src', c.i); img.removeAttribute('srcset'); img.removeAttribute('data-src');
        el.classList.remove('af-cp-circle--blank');
      } else {
        // The clone carries the picture of whichever circle it came from, so a
        // sub-collection with no thumbnail would wear another one's photograph.
        img.removeAttribute('srcset'); img.removeAttribute('data-src');
        img.setAttribute('src', blankDisc(c.n));
        el.classList.add('af-cp-circle--blank');
      }
      img.setAttribute('alt', c.n);
      img.setAttribute('loading', 'lazy');
    }
    // The caption: replace the words, keep whatever else the markup holds.
    var span = el.querySelector('span') || el;
    span.textContent = c.n;
    // A circle in this row is a <div data-subcat="..."> with no link inside it;
    // that attribute is what the theme filters on.
    el.setAttribute('data-subcat', c.s);
    var a = el.tagName === 'A' ? el : el.querySelector('a');
    if (a) a.setAttribute('href', c.u);
    return el;
  }

  function showCircles() {
    var strip = circleStrip();
    if (!strip || !CP.circles || !CP.circles.length) return;
    var sample = strip.querySelector(CIRC_ITEM);
    if (!sample) return;
    if (strip.querySelector('.af-cp-circle')) return;      // already ours
    var frag = document.createDocumentFragment();
    CP.circles.forEach(function (c) { frag.appendChild(makeCircle(sample, c)); });
    strip.innerHTML = '';
    strip.appendChild(frag);
    strip.scrollLeft = 0;
  }

  /* ---- the click -------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    if (!e.target || !e.target.closest) return;
    var tab = e.target.closest(TAB_ITEM);
    if (!tab || (tab.getAttribute('data-cat') || '') !== CP.slug) return;
    // Leaves a trail a probe can read, so "nothing happened" can be told apart
    // from "the handler never ran".
    try { console.log('[af-cp] tab clicked'); } catch (x) {}
    document.documentElement.setAttribute('data-af-cp', 'clicked');

    // The theme does not know this tab, so nothing else is going to answer it.
    e.preventDefault();
    e.stopPropagation();
    if (busy) return;
    busy = true;

    var strip = tab.closest(TAB_STRIP);
    if (strip) strip.querySelectorAll('.active').forEach(function (x) { x.classList.remove('active'); });
    tab.classList.add('active');

    var grid = gridFor(tab);
    try { console.log('[af-cp] grid = ' + (grid ? grid.tagName + '.' + grid.className : 'NULL')); } catch (x) {}
    document.documentElement.setAttribute('data-af-cp-grid', grid ? (grid.id || grid.className || grid.tagName) : 'NULL');
    if (grid) grid.style.opacity = '.45';
    showCircles();

    var body = new URLSearchParams();
    body.set('action', 'load_products');
    body.set('subcategory', CP.slug);

    post(body).then(function (html) {
      busy = false;
      try { console.log('[af-cp] answer ' + (html ? html.length : 0) + ' bytes, cards=' + hasCards(html)); } catch (x) {}
      document.documentElement.setAttribute('data-af-cp-bytes', String(html ? html.length : 0));
      if (grid) grid.style.opacity = '';
      if (!hasCards(html)) { window.location.href = CP.url; return; }
      if (!grid) { window.location.href = CP.url; return; }
      // Everything from here on is wrapped, because a throw inside a promise
      // becomes an unhandled rejection - silent in the page and invisible to a
      // probe watching for page errors. The last run stopped somewhere in this
      // block and left no trace of where.
      try {
        grid.innerHTML = html;
        document.documentElement.setAttribute('data-af-cp-wrote',
          String(grid.querySelectorAll('.product-card, li.product').length));
        showCircles();
        document.documentElement.setAttribute('data-af-cp-circles',
          String(document.querySelectorAll('.af-cp-circle').length));
        document.dispatchEvent(new Event('af_products_appended'));
        if (window.jQuery) jQuery(document.body).trigger('wc_fragments_refreshed');
        document.documentElement.setAttribute('data-af-cp-done', 'yes');
      } catch (err) {
        document.documentElement.setAttribute('data-af-cp-error', String(err && err.message || err));
        try { console.log('[af-cp] ERROR ' + err); } catch (x) {}
      }
    }).catch(function () {
      busy = false;
      if (grid) grid.style.opacity = '';
      window.location.href = CP.url;
    });
  }, true);
})();
</script>
    <?php
}, 99);
