<?php
/**
 * Orientation filter — portrait / landscape / square  (requirements §6)
 *
 * Orientation is a fact about the artwork, not an attribute anyone should
 * have to key in: it is read from the featured image's own dimensions and
 * stored as product meta, refreshed whenever the image changes. The shop
 * toolbar gains an Orientation dropdown that filters on it.
 */
if (!defined('ABSPATH')) exit;

/** portrait / landscape / square, from the featured image's pixels. */
function af_orientation_from_image($product_id) {
    $thumb = get_post_thumbnail_id($product_id);
    if (!$thumb) return '';
    $meta = wp_get_attachment_metadata($thumb);
    if (empty($meta['width']) || empty($meta['height'])) return '';
    $w = (int) $meta['width']; $h = (int) $meta['height'];
    if (!$w || !$h) return '';
    if ($h > $w * 1.08) return 'portrait';
    if ($w > $h * 1.08) return 'landscape';
    return 'square';
}

function af_orientation_refresh($product_id) {
    if (get_post_type($product_id) !== 'product') return;
    $o = af_orientation_from_image($product_id);
    if ($o) update_post_meta($product_id, '_af_orientation', $o);
    else delete_post_meta($product_id, '_af_orientation');
}
add_action('save_post_product', 'af_orientation_refresh', 20);
add_action('updated_post_meta', function($meta_id, $post_id, $key) {
    if ($key === '_thumbnail_id') af_orientation_refresh($post_id);
}, 10, 3);

/** ?orientation=portrait|landscape|square on shop and category pages. */
function af_orientation_current() {
    $o = isset($_GET['orientation']) ? sanitize_key(wp_unslash($_GET['orientation'])) : '';
    return in_array($o, array('portrait', 'landscape', 'square'), true) ? $o : '';
}

add_action('pre_get_posts', function($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) return;
    $o = af_orientation_current();
    if (!$o) return;
    $mq   = (array) $q->get('meta_query');
    $mq[] = array('key' => '_af_orientation', 'value' => $o);
    $q->set('meta_query', $mq);
});

/**
 * The shop toolbar renders through woocommerce_before_shop_loop, which the
 * parent theme's product-category template never fires — so a server-side
 * dropdown there exists only on /shop/. On every other listing page this
 * footer fallback inserts the same dropdown next to the toolbar the page
 * actually has (the masonry toggle earns its position the same way).
 * The [data-af-orient] guard keeps /shop/ from getting it twice.
 */
add_action('wp_footer', function() {
    if (is_admin()) return;
    if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) return;
    $cur  = af_orientation_current();
    $keep = $_GET; unset($keep['paged']);
    $links = array();
    foreach (array('portrait' => '▯ Portrait', 'landscape' => '▭ Landscape', 'square' => '□ Square') as $val => $label) {
        $q = $keep; $q['orientation'] = $val;
        $links[] = '<a href="' . esc_url('?' . http_build_query($q)) . '">' . esc_html($label) . '</a>';
    }
    if ($cur !== '') {
        $q = $keep; unset($q['orientation']);
        $links[] = '<a href="' . esc_url($q ? '?' . http_build_query($q) : strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) . '">✕ Any orientation</a>';
    }
    ?>
    <template id="af-orient-tpl">
      <span class="af-lt-drop af-orient-drop" data-af-orient="1">
        <button type="button" class="af-lt-dbtn"><?php echo $cur ? esc_html(ucfirst($cur)) : 'Orientation'; ?> ▾</button>
        <span class="af-lt-menu"><?php echo implode('', $links); ?></span>
      </span>
    </template>
    <style>
    .af-orient-drop{display:inline-block;vertical-align:middle;margin:0 0 14px 10px;}
    .af-orient-drop .af-lt-menu{display:none;}
    .af-orient-drop .af-lt-menu.open{display:block;}
    .af-orient-drop .af-lt-menu a{display:block;}
    </style>
    <script>
    (function(){
      if (document.querySelector('[data-af-orient]')) return;   // /shop/ renders it server-side
      var tpl = document.getElementById('af-orient-tpl');
      if (!tpl) return;
      var host = document.querySelector('.af-layout-toggle') ||
                 document.querySelector('.woocommerce-result-count, .woocommerce-ordering');
      var grid = document.querySelector('ul.products');
      var node = tpl.content.firstElementChild.cloneNode(true);
      if (host && host.parentNode) host.parentNode.insertBefore(node, host.nextSibling);
      else if (grid && grid.parentNode) grid.parentNode.insertBefore(node, grid);
      else return;
      var btn = node.querySelector('.af-lt-dbtn'), menu = node.querySelector('.af-lt-menu');
      btn.addEventListener('click', function(e){ e.stopPropagation(); menu.classList.toggle('open'); });
      document.addEventListener('click', function(){ menu.classList.remove('open'); });
    })();
    </script>
    <?php
}, 65);
