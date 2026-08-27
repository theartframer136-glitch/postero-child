<?php
/**
 * Gold Foiled & UV in "Shop by Collection".
 *
 * Owner request (2026-08-26, screen recording): the homepage's Shop by
 * Collection strip was walked from end to end with its own arrows — Digital
 * Canvas Prints, Art Accessories, Banners & Signage, Framed Canvases, Direct
 * from Artists, Digital Downloads, Home Decor by Space, Personalised Prints,
 * Gifts — and the premium section is in none of them. Neither is anything of
 * it in the circle row underneath. The section exists, it is priced, it is in
 * the header menu and the shop sidebar, and the one place a visitor actually
 * browses collections from does not know it is there.
 *
 * WHY IT IS MISSING IS NOT KNOWABLE FROM HERE
 * That strip is the PARENT theme's markup (#topCatSlider > a.top-cat-btn, with
 * .cat-nav arrows either side; the circles are #subcategorySlider / li.cat-item
 * — see assets/css/custom.css "SHOP BY COLLECTION"). Nothing in this repo
 * renders it and the live site cannot be read from this session, so the reason
 * it stops at nine could be a term query that excludes the section, a count, a
 * limit, or a list chosen in the theme's own settings. Guessing which one and
 * fixing only that is how the sidebar list was "fixed" twice before it was
 * measured (see af_sidebar_cat_menu's history).
 *
 * So this does not guess. It closes the gap from BOTH ends:
 *
 *   1. If the strip is built from a term query, the section is put into that
 *      query's results — the theme then renders the tab itself, with its own
 *      markup and its own click handling, and there is nothing to maintain.
 *   2. Whatever the strip turns out to be built from, the browser checks that
 *      the tab is actually there once the page has rendered, and if it is not,
 *      clones one of the theme's own tabs into place and owns its click:
 *      products swap into the same grid through the theme's own AJAX contract,
 *      the circle row fills with the section's own sub-collections, and a
 *      request that answers with nothing still lands the visitor on the
 *      section's archive rather than nowhere.
 *
 * Belt and braces on purpose: (1) is the fix worth having, (2) is the one that
 * cannot fail to show up. tools/diag-topcat-strip.php reports which of the two
 * did the work on the live site, so the next deploy can drop the other.
 */
if (!defined('ABSPATH')) exit;
if (!function_exists('af_goldfoil_slug')) return;   // inc/gold-foil.php owns the section

/**
 * Everything the front end needs to draw the section into the strip: the tab
 * itself, and the circles that belong under it.
 *
 * The circles are the section's OWN sub-collections — children of
 * gold-foiled-uv, built by tools/goldfoil-subcategories.php from what each
 * piece depicts — which is exactly what the circle row holds for every other
 * tab. A section too small to have been split up yet would leave that row
 * looking broken, so below two sub-collections the row shows the pieces
 * themselves instead: same round thumbnails, each opening its own product.
 */
function af_goldfoil_collection_payload() {
    static $payload = null;
    if ($payload !== null) return $payload;

    $payload = null;
    $term = af_goldfoil_term();
    if (!$term) return $payload;

    $url = get_term_link($term);
    if (is_wp_error($url)) return $payload;

    $img = function ($attachment_id) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) return '';
        $src = wp_get_attachment_image_url($attachment_id, 'woocommerce_thumbnail');
        return $src ? $src : (string) wp_get_attachment_image_url($attachment_id, 'thumbnail');
    };

    $circles = array();
    $kids = get_terms(array(
        'taxonomy'   => 'product_cat',
        'parent'     => (int) $term->term_id,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));
    if (!is_wp_error($kids)) {
        foreach ($kids as $kid) {
            if ((int) $kid->count === 0) continue;          // a circle that leads to an empty page is worse than no circle
            $kurl = get_term_link($kid);
            if (is_wp_error($kurl)) continue;
            $circles[] = array(
                's' => $kid->slug,
                'n' => html_entity_decode($kid->name, ENT_QUOTES, 'UTF-8'),
                'u' => $kurl,
                'i' => $img(get_term_meta($kid->term_id, 'thumbnail_id', true)),
            );
        }
    }

    // Too few sub-collections to make a row: show the pieces themselves.
    if (count($circles) < 2) {
        $circles = array();
        $ids = get_posts(array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 14,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'tax_query'      => array(array(
                'taxonomy'         => 'product_cat',
                'field'            => 'term_id',
                'terms'            => array((int) $term->term_id),
                'include_children' => true,
            )),
        ));
        foreach ($ids as $pid) {
            $circles[] = array(
                's' => '',                                   // a piece, not a category: no filtering, it opens
                'n' => html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8'),
                'u' => (string) get_permalink($pid),
                'i' => $img(get_post_thumbnail_id($pid)),
            );
        }
    }

    $payload = array(
        'slug'    => $term->slug,
        'id'      => (int) $term->term_id,
        'label'   => html_entity_decode(af_goldfoil_name(), ENT_QUOTES, 'UTF-8'),
        'url'     => $url,
        'count'   => (int) $term->count,
        'circles' => $circles,
        'ajax'    => admin_url('admin-ajax.php'),
    );
    return $payload;
}

/* ── 1. Put the section into the term list the strip is built from ────────
 *
 * Scoped as tightly as the strip can be described without having read the
 * theme: the front page only, top-level product categories only, and only
 * when the query has already returned a list of terms that does not include
 * this one. Appending would bury it past GIFTS, off the end of a strip the
 * owner had to walk with arrows, so it goes in second — the shop's main
 * collection keeps the opening slot, and the premium line is visible without
 * scrolling anything.
 */
add_filter('get_terms', function ($terms, $taxonomies, $args) {
    // is_front_page() has no answer until the main query has run, and asking
    // it earlier is a _doing_it_wrong notice on every term query the site makes
    if (!did_action('wp')) return $terms;
    if (is_admin() || !is_front_page()) return $terms;
    if (!is_array($terms) || count($terms) < 2) return $terms;
    if (!is_array($taxonomies) || $taxonomies !== array('product_cat')) return $terms;

    // objects only — an ids/count/names query is not the strip
    if (isset($args['fields']) && $args['fields'] !== 'all') return $terms;
    if (!($terms[0] instanceof WP_Term)) return $terms;

    // top-level only
    if (!isset($args['parent']) || (string) $args['parent'] !== '0') return $terms;

    $term = af_goldfoil_term();
    if (!$term || (int) $term->parent !== 0) return $terms;

    foreach ($terms as $t) {
        if ($t instanceof WP_Term && (int) $t->term_id === (int) $term->term_id) return $terms;
    }

    array_splice($terms, 1, 0, array($term));
    return $terms;
}, 20, 3);

/* ── 2. The fallback the browser can always fall back TO ──────────────────
 * The theme's own load_products endpoint is asked first, so the swapped-in
 * cards are the theme's own markup. This answers only if that comes back with
 * nothing — an endpoint that does not understand a top-level slug, say — and
 * renders the section through WooCommerce's own loop templates, which every
 * later pass in this theme (the art code line, the gold-foil badge, the price
 * strip) already knows how to decorate.
 */
function af_goldfoil_render_cards() {
    // No nonce, deliberately. The homepage is served from a full-page cache, so
    // a nonce printed into it is stale for the next guest who reads it — and a
    // stale nonce here would fail exactly the visitors this endpoint exists
    // for. There is nothing to protect: it takes no input beyond a slug it
    // refuses to leave the section for, changes nothing, and answers with
    // published products that are already on a public archive page.
    $term = af_goldfoil_term();
    if (!$term) wp_die('', '', array('response' => 200));

    $slug = isset($_REQUEST['subcategory']) ? sanitize_title(wp_unslash($_REQUEST['subcategory'])) : '';
    if ($slug === '') $slug = $term->slug;

    // Only ever this section or one of its own sub-collections: the endpoint
    // is public, and it is not a general-purpose catalogue reader.
    $target = get_term_by('slug', $slug, 'product_cat');
    if (!$target || is_wp_error($target)) $target = $term;
    if ((int) $target->term_id !== (int) $term->term_id
        && !in_array((int) $term->term_id, array_map('intval', get_ancestors($target->term_id, 'product_cat')), true)) {
        $target = $term;
    }

    $q = new WP_Query(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 24,
        'no_found_rows'  => true,
        'tax_query'      => array(array(
            'taxonomy'         => 'product_cat',
            'field'            => 'term_id',
            'terms'            => array((int) $target->term_id),
            'include_children' => true,
        )),
    ));

    if ($q->have_posts()) {
        woocommerce_product_loop_start();
        while ($q->have_posts()) {
            $q->the_post();
            wc_get_template_part('content', 'product');
        }
        woocommerce_product_loop_end();
    }
    wp_reset_postdata();
    wp_die('', '', array('response' => 200));
}
add_action('wp_ajax_af_gf_cards', 'af_goldfoil_render_cards');
add_action('wp_ajax_nopriv_af_gf_cards', 'af_goldfoil_render_cards');

/* ── 3. Make sure the tab is on the page, and own it ──────────────────── */
add_action('wp_footer', function () {
    if (is_admin() || !is_front_page()) return;
    $gf = af_goldfoil_collection_payload();
    if (!$gf) return;
    ?>
<style id="af-gf-collection-css">
/* The clone inherits every rule the theme's own tabs carry; this only says
   which one is the premium line, in the same gold the strip already uses for
   its active tab. */
#topCatSlider .top-cat-btn[data-af-gf],
.top-category-slider .top-cat-btn[data-af-gf] { color: #8a6d1f !important; }
#topCatSlider .top-cat-btn[data-af-gf].active,
.top-category-slider .top-cat-btn[data-af-gf].active { border-bottom-color: #c9a84c !important; }
/* A circle built here is the theme's own circle markup, cloned — the only
   thing worth saying is that a picture that failed to load must not leave a
   torn icon in a row of round photographs. */
.af-gf-circle img { object-fit: cover !important; }
</style>
<script id="af-gf-collection-js">
(function () {
  var GF = <?php echo wp_json_encode($gf); ?>;
  if (!GF || !GF.slug) return;

  var TAB_STRIP  = '#topCatSlider, .top-category-slider';
  var TAB_ITEM   = '.top-cat-btn';
  var CIRC_STRIP = '#subcategorySlider, .subcategory-slider, ul.postero-scroll-content';
  var CIRC_ITEM  = 'li.cat-item, .sub-cat';
  var GRID_SEL   = '#productGrid, .product-slider, .custom-product-track, ul.products';

  function norm(s) { return (s || '').toLowerCase().replace(/[^a-z0-9]+/g, ''); }
  var WANT = norm(GF.label);

  /* Rewrite the words without throwing away whatever else the theme put in
   * there. A tab or a circle may hold an icon, a picture, a counter; setting
   * textContent on the whole element would delete all of it, so the words are
   * replaced where the words actually are. */
  function setLabel(el, text) {
    var host = el.querySelector('span, .cat-name, figcaption, p');
    if (host && !host.querySelector('*')) { host.textContent = text; return; }
    var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false), node, first = null;
    while ((node = walker.nextNode())) { if (node.nodeValue.trim() !== '') { first = node; break; } }
    if (first) first.nodeValue = text;
    else el.appendChild(document.createTextNode(text));
  }

  /* ---- the tab ---------------------------------------------------------
   * Cloned rather than hand-built: the theme's tab carries whatever classes,
   * attributes and inline styling it carries, and a clone matches all of it
   * without this file having to know any of it. The only things rewritten are
   * the ones that say WHICH category the tab stands for — its words, its link,
   * and any data-* attribute that held the cloned tab's own slug or term id.
   */
  function makeTab(sample) {
    var tab = sample.cloneNode(true);
    tab.classList.remove('active');
    tab.setAttribute('data-af-gf', '1');

    // what the sample stood for, read off the sample itself
    var href = sample.getAttribute('href') || '';
    var m = href.match(/product-category\/(?:[^\/?#]+\/)*([^\/?#]+)\/?/);
    var sampleSlug = m ? m[1] : '';

    var sampleText = (sample.textContent || '').trim();

    // Exact matches first, and only then a looser one. "Digital Canvas Prints"
    // and "digital-canvas-prints" are the same word to norm(), so a loose test
    // run first hands data-title the slug — measured, and it is the difference
    // between a tab that reads GOLD FOILED & UV and one that reads
    // gold-foiled-uv.
    Array.prototype.slice.call(tab.attributes).forEach(function (a) {
      if (a.name.indexOf('data-') !== 0 || a.name === 'data-af-gf') return;
      var v = (a.value || '').trim();
      if (v === '') return;
      if (/^\d+$/.test(v))              { tab.setAttribute(a.name, String(GF.id)); return; }   // a term id
      if (sampleSlug && v === sampleSlug) { tab.setAttribute(a.name, GF.slug);      return; }
      if (v === sampleText)              { tab.setAttribute(a.name, GF.label);     return; }
      if (norm(v) !== norm(sampleSlug) && norm(v) !== norm(sampleText)) return;     // not an identifier
      // the same words punctuated differently: spaces mean it is being read,
      // hyphens mean it is being looked up
      tab.setAttribute(a.name, /\s/.test(v) ? GF.label : GF.slug);
    });
    if (!tab.hasAttribute('data-val')) tab.setAttribute('data-val', GF.slug);

    if (tab.tagName === 'A') tab.setAttribute('href', GF.url);
    setLabel(tab, GF.label);
    return tab;
  }

  // Every strip on the page, not just the first: a theme that lays the
  // homepage out twice — one strip for wide screens, one for narrow — hides
  // the one it is not using, and putting the tab only in document order would
  // land it in whichever of the two the visitor cannot see.
  function ensureTab() {
    var strips = document.querySelectorAll(TAB_STRIP);
    for (var s = 0; s < strips.length; s++) ensureTabIn(strips[s]);
  }

  function ensureTabIn(strip) {
    if (!strip) return null;
    var tabs = strip.querySelectorAll(TAB_ITEM);
    if (!tabs.length) return null;

    var mine = strip.querySelector('[data-af-gf]');
    if (mine) return mine;

    for (var i = 0; i < tabs.length; i++) {
      // ...but not one this file put there: a clone matches by its words too,
      // and marking it "the theme's" would hand the click to a handler that
      // has never heard of the element.
      if (tabs[i].hasAttribute('data-af-gf')) continue;
      if (norm(tabs[i].textContent) === WANT) {
        // the theme rendered it itself — nothing to add, and its own handler
        // is welcome to the click
        tabs[i].setAttribute('data-af-gf-native', '1');
        return tabs[i];
      }
    }

    var tab = makeTab(tabs[0]);
    if (tabs[1]) strip.insertBefore(tab, tabs[1]); else strip.appendChild(tab);
    return tab;
  }

  /* ---- the circles ----------------------------------------------------- */
  function circleStrip() {
    var strips = document.querySelectorAll(CIRC_STRIP);
    for (var i = 0; i < strips.length; i++) if (strips[i].offsetParent) return strips[i];
    return strips[0] || null;
  }

  function makeCircle(sample, c) {
    var el = sample.cloneNode(true);
    el.classList.remove('active');
    el.classList.add('af-gf-circle');
    var img = el.querySelector('img');
    if (img) {
      if (c.i) { img.setAttribute('src', c.i); img.removeAttribute('srcset'); img.removeAttribute('data-src'); }
      img.setAttribute('alt', c.n);
      img.setAttribute('loading', 'lazy');
    }
    setLabel(el, c.n);
    var a = el.tagName === 'A' ? el : el.querySelector('a');
    if (a) {
      a.setAttribute('href', c.u);
      if (c.s) a.setAttribute('data-val', c.s); else a.removeAttribute('data-val');
      a.setAttribute('data-title', c.n);
    }
    // A circle standing for a PIECE, not a sub-collection, has to say so. The
    // circle handler in functions.php resolves an unlabelled circle by
    // matching its caption against the category names, and a piece called
    // "Radha Krishna Gold Foil ..." contains a category name — so without this
    // it would quietly filter the grid to the ordinary Radha Krishna
    // collection instead of opening the artwork.
    if (!c.s) el.setAttribute('data-af-gf-piece', '1');
    return el;
  }

  // Is the row already showing this section — either ours, or the theme's own
  // once the sub-collections became real terms it knows about? Then leave it
  // alone: replacing a correct row with an identical one can only lose
  // whatever behaviour the theme hung on its own markup.
  function alreadyShowingSection(strip) {
    if (strip.querySelector('.af-gf-circle')) return true;
    var first = strip.querySelector(CIRC_ITEM);
    if (!first) return false;
    // By slug, never by name. The section's sub-collections are called Radha
    // Krishna and Lakshmi Ganesha because that is what they depict — the same
    // words the ordinary collections use — so matching on the words says the
    // row is already ours while it is still showing Digital Canvas Prints.
    // Every sub-collection of this section is slugged and filed under it, so
    // its slug is in both the link and the value the theme filters on.
    var link = (first.matches && first.matches('a')) ? first : first.querySelector('a');
    if (!link) return false;
    var probe = (link.getAttribute('href') || '') + ' ' + (link.getAttribute('data-val') || '');
    return probe.indexOf(GF.slug) !== -1;
  }

  // The theme rebuilds this row when another tab is picked, so what is here
  // before we touch it is kept and put back the moment the visitor leaves.
  function showCircles() {
    var strip = circleStrip();
    if (!strip || !GF.circles || !GF.circles.length) return;
    var sample = strip.querySelector(CIRC_ITEM);
    if (!sample) return;
    // The flag alone is not enough: the theme can rebuild this row from under
    // us after it is set, and then "open" would mean an empty promise.
    if (alreadyShowingSection(strip)) { strip.setAttribute('data-af-gf-open', '1'); return; }
    if (!strip.hasAttribute('data-af-gf-saved')) strip.setAttribute('data-af-gf-saved', strip.innerHTML);
    strip.setAttribute('data-af-gf-open', '1');
    var frag = document.createDocumentFragment();
    GF.circles.forEach(function (c) { frag.appendChild(makeCircle(sample, c)); });
    strip.innerHTML = '';
    strip.appendChild(frag);
    strip.scrollLeft = 0;
  }

  function restoreCircles() {
    var strip = circleStrip();
    if (!strip || !strip.getAttribute('data-af-gf-open')) return;
    strip.removeAttribute('data-af-gf-open');
    var saved = strip.getAttribute('data-af-gf-saved');
    if (saved !== null) strip.innerHTML = saved;
  }

  /* ---- the products ----------------------------------------------------- */
  function gridFor(tab) {
    for (var n = tab; n && n !== document.body; n = n.parentElement) {
      var g = n.querySelector(GRID_SEL);
      if (g && !g.contains(tab)) return g;
    }
    return document.querySelector(GRID_SEL);
  }

  function visibleAreaFor(grid) {
    if (grid.classList.contains('af-grid-hidden') && grid.parentNode) {
      var shell = grid.parentNode.querySelector('.af-shell');
      if (shell) return shell;
    }
    return grid;
  }

  function hasCards(html) {
    return !!html && (html.indexOf('product-card') !== -1 || html.indexOf('woocommerce-loop-product') !== -1
      || html.indexOf('class="product') !== -1);
  }

  function post(body) {
    return fetch(GF.ajax, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (r) { return r.ok ? r.text() : ''; }).catch(function () { return ''; });
  }

  var busy = false;
  function openSection(tab, e) {
    var grid = gridFor(tab);
    if (!grid) return;                                  // no grid here: the link does the work
    if (e) e.preventDefault();
    if (busy) return;
    busy = true;

    var strip = tab.closest(TAB_STRIP);
    if (strip) strip.querySelectorAll('.active').forEach(function (x) { x.classList.remove('active'); });
    tab.classList.add('active');

    var area = visibleAreaFor(grid);
    area.style.opacity = '.45';
    var unDim = setTimeout(function () { area.style.opacity = ''; }, 5000);

    // The row answers now, not when the request comes back: a strip that sits
    // still for a second after a tap reads as a dead control, which is the
    // whole reason the circles were rewired in the first place.
    showCircles();

    var theirs = new URLSearchParams();
    theirs.set('action', 'load_products');
    theirs.set('subcategory', GF.slug);

    post(theirs).then(function (html) {
      if (hasCards(html)) return html;
      var ours = new URLSearchParams();               // the theme's endpoint had nothing to say
      ours.set('action', 'af_gf_cards');
      ours.set('subcategory', GF.slug);
      return post(ours);
    }).then(function (html) {
      busy = false;
      clearTimeout(unDim);
      if (!hasCards(html)) { area.style.opacity = ''; window.location.href = GF.url; return; }
      grid.innerHTML = html;
      if (area === grid) area.style.opacity = '';
      showCircles();
      document.dispatchEvent(new Event('af_products_appended'));
      if (window.jQuery) jQuery(document.body).trigger('wc_fragments_refreshed');
    }).catch(function () {
      busy = false; clearTimeout(unDim); area.style.opacity = '';
      window.location.href = GF.url;
    });
  }

  /* ---- wiring ----------------------------------------------------------
   * Capture phase, and ONLY for a tab this file put on the page: the theme
   * knows nothing about that element, so letting its handler see the click
   * can only produce a second request for a category it cannot place. Every
   * other tab is untouched — it is the theme's, and it still works the way it
   * always did, including putting its own circles back.
   */
  document.addEventListener('click', function (e) {
    if (!e.target || !e.target.closest) return;

    var piece = e.target.closest('[data-af-gf-piece]');
    if (piece) {                                    // a circle that IS an artwork: open it
      var link = piece.matches('a') ? piece : piece.querySelector('a[href]');
      if (link && link.getAttribute('href')) {
        e.preventDefault();                         // and keep the circle filter off it
        e.stopPropagation();
        window.location.href = link.getAttribute('href');
      }
      return;
    }

    var tab = e.target.closest(TAB_ITEM);
    if (!tab) return;
    if (tab.hasAttribute('data-af-gf')) {
      e.stopPropagation();
      openSection(tab, e);
      return;
    }
    if (tab.hasAttribute('data-af-gf-native')) { [700, 2200].forEach(function (d) { setTimeout(showCircles, d); }); return; }
    restoreCircles();
  }, true);

  function run() { ensureTab(); }
  if (document.readyState !== 'loading') run();
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  [500, 1500, 3000].forEach(function (d) { setTimeout(run, d); });

  // The strip is re-rendered by the theme on a tab switch; the tab has to
  // survive that without this file re-running on a timer forever.
  try {
    var host = document.querySelector('.top-category-container') || document.body;
    new MutationObserver(function () { ensureTab(); }).observe(host, { childList: true, subtree: true });
  } catch (err) {}
})();
</script>
    <?php
}, 47);
