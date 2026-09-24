<?php
/**
 * The homepage sends what it shows (M-09).
 *
 * Test Run 03 found the homepage heavier than the day before: 8,060 → 9,713
 * DOM nodes, 1,250 → 1,538 links and buttons, 83 → 119 href="#", 347 → 516
 * tap targets under 44 px. tools/probe-home-weight.mjs split the live page by
 * section (qa-personas runs 107-109) and two things carry most of it:
 *
 *   Shop by Collection, first tab    74 cards   3,422 nodes   592 links   74 href="#"
 *   ten sections of the page hidden
 *   at every width Elementor has     1,645 nodes   ~190 links   42 images
 *
 * 1. SHOP BY COLLECTION: 12 PIECES AND A LINK TO THE REST.
 *    The grid is filled over admin-ajax, by the theme's load_products, which
 *    asks for 12. PHASE 25 in functions.php lifted that to every product in the
 *    collection, so the homepage grew with the catalogue: the run 02 → run 03
 *    increase is 36 cards to the unit (+36 href="#", one Quick View per card;
 *    +288 links, eight per card; +1,653 nodes, 46 per card), and Radha Krishna
 *    now has 74. A shopper sees four at a time in the slider either way.
 *
 *    Asked from the homepage, load_products answers with the theme's 12 again,
 *    and says how many there are and where they all are; the script below puts
 *    "See all 74 in Radha Krishna" under the slider. Asked from anywhere else it
 *    answers with every product, as PHASE 25 did: the same endpoint filters the
 *    product list in place on category pages, and that list must stay whole.
 *    "From the homepage" is the Referer, which the site sends in full to itself
 *    (Referrer-Policy: strict-origin-when-cross-origin, functions.php).
 *
 * 2. SECTIONS HIDDEN AT EVERY WIDTH ARE NOT SENT.
 *    Ten containers in the homepage's own content are set, in Elementor, to be
 *    hidden on the desktop, laptop, tablet_extra, tablet, mobile_extra and
 *    mobile widths - every width this site has switched on - and so are never
 *    seen by anyone. Elementor still sent them: 1,388 nodes of a retired Art
 *    Accessories carousel among them. Now an element hidden at every active
 *    width is not printed. Only in the homepage's own document: the header and
 *    footer templates are on every page and are left as they are. Never in the
 *    editor or its preview, where the owner still sees and edits them. Showing
 *    one again at any width in Elementor brings it back.
 *
 * Tests: tools/test-home-weight.php. Live: tools/verify-m09.mjs.
 */
if (!defined('ABSPATH')) exit;

/** How many pieces Shop by Collection shows on the homepage. The theme's own number. */
function af_home_collection_cap() {
    return max(1, (int) apply_filters('af_home_collection_cap', 12));
}

/**
 * Is $url the homepage? Same host, same path; the query string and fragment
 * do not matter (/?utm_source=x is still the homepage).
 */
function af_home_is_home_url($url, $home) {
    if (!is_string($url) || $url === '' || !is_string($home) || $home === '') return false;
    $u = parse_url($url);
    $h = parse_url($home);
    if (!is_array($u) || !is_array($h) || empty($u['host']) || empty($h['host'])) return false;
    if (strtolower($u['host']) !== strtolower($h['host'])) return false;
    $up = '/' . trim(isset($u['path']) ? $u['path'] : '', '/');
    $hp = '/' . trim(isset($h['path']) ? $h['path'] : '', '/');
    return $up === $hp;
}

/**
 * The one product category a tax_query asks for, as array(field, value), or
 * null when it asks for none or for several (there is no single page to send
 * "see all" to).
 */
function af_home_collection_term_ref($tax_query) {
    if (!is_array($tax_query)) return null;
    foreach ($tax_query as $t) {
        if (!is_array($t) || !isset($t['taxonomy']) || $t['taxonomy'] !== 'product_cat') continue;
        $terms = isset($t['terms']) ? $t['terms'] : '';
        if (is_array($terms)) {
            if (count($terms) !== 1) return null;
            $terms = reset($terms);
        }
        $terms = trim((string) $terms);
        if ($terms === '') return null;
        $field = isset($t['field']) ? strtolower((string) $t['field']) : 'term_id';
        if (!in_array($field, array('slug', 'name', 'term_id', 'id'), true)) return null;
        return array($field === 'term_id' ? 'id' : $field, $terms);
    }
    return null;
}

/** Does a tax_query filter by product category at all? */
function af_home_has_product_cat($tax_query) {
    if (!is_array($tax_query)) return false;
    foreach ($tax_query as $t) {
        if (is_array($t) && isset($t['taxonomy']) && $t['taxonomy'] === 'product_cat') return true;
    }
    return false;
}

/**
 * The marker load_products carries after its cards when there are more than
 * it sent: where they all are, how many, and the collection's name. Empty when
 * every piece is already there.
 */
function af_home_collection_more_html($url, $total, $shown, $name) {
    $total = (int) $total; $shown = (int) $shown;
    if ($url === '' || $total <= $shown) return '';
    // Inline and !important: it lands inside the product grid, and no grid
    // rule of the theme's may give it a cell.
    return '<span class="af-coll-more" hidden style="display:none!important" data-url="'
         . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . '" data-count="' . $total
         . '" data-name="' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') . '"></span>';
}

/** Hidden at every one of $devices? $hide maps a device to its hide_<device> setting. */
function af_home_hidden_everywhere($hide, $devices) {
    if (!is_array($hide) || !is_array($devices) || !$devices) return false;
    foreach ($devices as $d) {
        if (empty($hide[$d])) return false;
    }
    return true;
}

// ── 1. Shop by Collection ────────────────────────────────────────────────
add_action('pre_get_posts', function ($q) {
    if ($q->is_main_query()) return;
    if (!wp_doing_ajax() || !isset($_REQUEST['action']) || $_REQUEST['action'] !== 'load_products') return;
    $pt = $q->get('post_type');
    if (!($pt === 'product' || (is_array($pt) && in_array('product', $pt, true)))) return;

    $from = isset($_SERVER['HTTP_REFERER']) ? wp_unslash((string) $_SERVER['HTTP_REFERER']) : '';
    if (!af_home_is_home_url($from, home_url('/'))) {
        $q->set('posts_per_page', -1);   // category pages: the whole list, exactly as PHASE 25 did
        return;
    }
    // The homepage: the grid's own query only, the one filtered by collection.
    // Any other product query in the same request keeps its own size.
    if (!af_home_has_product_cat($q->get('tax_query'))) return;
    $q->set('posts_per_page', af_home_collection_cap());
    $q->set('af_home_collection', 1);
});

add_action('loop_end', function ($q) {
    if (!($q instanceof WP_Query) || !$q->get('af_home_collection')) return;
    $ref = af_home_collection_term_ref($q->get('tax_query'));
    if (!$ref) return;
    $term = get_term_by($ref[0], $ref[1], 'product_cat');
    if (!$term || is_wp_error($term)) return;
    $url = get_term_link($term);
    if (is_wp_error($url)) return;
    echo af_home_collection_more_html($url, $q->found_posts, $q->post_count,
        html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'));
});

add_action('wp_footer', function () {
    if (!is_front_page()) return; ?>
<style id="af-coll-all-css">
.af-coll-all-wrap{display:flex;justify-content:center;margin:14px 0 6px;}
.af-coll-all{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:10px 24px;box-sizing:border-box;
  border:2px solid #c9a84c;border-radius:999px;background:#fff;color:#1f1f1f !important;font-size:15px;font-weight:600;
  line-height:1.2;text-decoration:none !important;}
.af-coll-all:hover,.af-coll-all:focus-visible{background:#c9a84c;color:#1f1f1f !important;}
</style>
<script id="af-coll-all-js">
(function(){
  // One link per grid, under what the shopper sees: the slider shell when the
  // homepage slider has taken the cards (functions.php, "10. Product card
  // slider"), the grid itself otherwise. Redrawn whenever a tab loads.
  var GRID_SEL = '#productGrid, .product-slider, .custom-product-track, ul.products';
  function after(grid){
    var n = grid.nextElementSibling;
    return n && n.classList.contains('af-shell') ? n : grid;
  }
  function place(){
    document.querySelectorAll('.af-coll-more').forEach(function(m){
      var grid = m.closest(GRID_SEL) || m.parentElement;
      if (!grid || !grid.parentNode) return;
      var wrap = grid._afMore;
      if (!wrap) {
        wrap = document.createElement('div'); wrap.className = 'af-coll-all-wrap';
        wrap.appendChild(document.createElement('a')).className = 'af-coll-all';
        wrap._afGrid = grid; grid._afMore = wrap;
      }
      // Only what changed is written: this runs from a MutationObserver, and
      // an unconditional write would wake it again, for ever.
      var a = wrap.firstChild;
      var text = 'See all ' + m.getAttribute('data-count') + ' in ' + m.getAttribute('data-name') + ' \u2192';
      if (a.getAttribute('href') !== m.getAttribute('data-url')) a.setAttribute('href', m.getAttribute('data-url'));
      if (a.textContent !== text) a.textContent = text;
      var at = after(grid);
      if (at.nextSibling !== wrap) at.parentNode.insertBefore(wrap, at.nextSibling);
    });
    // A tab with every piece already showing carries no marker: no link.
    document.querySelectorAll('.af-coll-all-wrap').forEach(function(w){
      if (!w._afGrid || !w._afGrid.querySelector('.af-coll-more')) w.remove();
    });
  }
  var t = null;
  function soon(){ clearTimeout(t); t = setTimeout(place, 150); }
  new MutationObserver(soon).observe(document.documentElement, { childList: true, subtree: true });
  document.addEventListener('af_products_appended', soon);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', soon); else soon();
})();
</script>
<?php }, 60);

// ── 2. Sections hidden at every width ────────────────────────────────────
/** The widths Elementor styles: the desktop and every breakpoint switched on. */
function af_home_elementor_devices() {
    static $devices = null;
    if ($devices !== null) return $devices;
    // The list Elementor itself builds the "Hide On" switches from.
    $bp = \Elementor\Plugin::$instance->breakpoints;
    if ($bp && method_exists($bp, 'get_active_devices_list')) {
        $devices = array_values((array) $bp->get_active_devices_list());
    } elseif ($bp && method_exists($bp, 'get_active_breakpoints')) {
        $devices = array_merge(array('desktop'), array_keys((array) $bp->get_active_breakpoints()));
    } else {
        $devices = array();   // unknown: hide_everywhere() is then false for all
    }
    return $devices;
}

/** The ids of the elements left out, for the note at the end of the page. */
function af_home_not_sent($id = null) {
    static $ids = array();
    if ($id !== null) $ids[] = (string) $id;
    return $ids;
}

function af_home_skip_hidden_everywhere($should, $element) {
    if (!$should || is_admin() || !is_front_page() || !class_exists('\Elementor\Plugin')) return $should;
    $el = \Elementor\Plugin::$instance;
    if ((isset($el->editor) && $el->editor->is_edit_mode()) || (isset($el->preview) && $el->preview->is_preview_mode())) return $should;
    $doc = isset($el->documents) ? $el->documents->get_current() : null;
    if (!$doc || (int) $doc->get_main_id() !== (int) get_option('page_on_front')) return $should;
    if (!is_object($element) || !method_exists($element, 'get_settings')) return $should;

    $devices = af_home_elementor_devices();
    $hide = array();
    foreach ($devices as $d) $hide[$d] = $element->get_settings('hide_' . $d);
    if (!af_home_hidden_everywhere($hide, $devices)) return $should;
    af_home_not_sent($element->get_id());
    return false;
}
foreach (array('section', 'column', 'container', 'widget') as $af_type) {
    add_filter("elementor/frontend/{$af_type}/should_render", 'af_home_skip_hidden_everywhere', 10, 2);
}
unset($af_type);

add_action('wp_footer', function () {
    if (!is_front_page()) return;
    $ids = af_home_not_sent();
    if ($ids) echo "\n<!-- af-home-weight: not sent, hidden at every width: " . esc_html(implode(' ', $ids)) . " -->\n";
}, 99);
