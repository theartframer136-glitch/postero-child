<?php
/**
 * Masonry layout on category / listing pages  (requirements §6)
 *
 * A Grid ⇄ Masonry toggle beside the shop toolbar. Grid stays the default —
 * masonry packs the cards by height (CSS grid row-spans, recalculated as
 * images load and on resize), and the visitor's choice is remembered.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function() {
    if (is_admin()) return;
    if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) return;
    ?>
    <style>
    .af-layout-toggle{display:inline-flex;gap:0;border:1.5px solid #e2d9c4;border-radius:10px;overflow:hidden;
      vertical-align:middle;margin:0 0 14px 10px;}
    .af-layout-toggle button{border:0;background:#fffdf8;color:#6b6250;font-size:12px;font-weight:700;
      padding:8px 13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
    .af-layout-toggle button.on{background:#1a1a1a;color:#fff;}
    .af-layout-toggle svg{width:14px;height:14px;fill:currentColor;}
    /* masonry mode: rows become a fine grid and each card spans what it needs */
    ul.products.af-masonry{display:grid !important;grid-auto-rows:8px !important;align-items:start !important;}
    ul.products.af-masonry li.product{height:auto !important;margin-bottom:0 !important;}
    </style>
    <script>
    (function(){
      var KEY = 'af_shop_layout';
      var grids = Array.from(document.querySelectorAll('ul.products'));
      if (!grids.length) return;

      function span(grid){
        if (!grid.classList.contains('af-masonry')) return;
        var st   = getComputedStyle(grid);
        var row  = parseFloat(st.gridAutoRows) || 8;
        var gap  = parseFloat(st.rowGap) || 0;
        grid.querySelectorAll('li.product').forEach(function(li){
          li.style.gridRowEnd = '';
          var h = li.getBoundingClientRect().height;
          li.style.gridRowEnd = 'span ' + Math.max(1, Math.ceil((h + gap) / (row + gap)));
        });
      }
      function apply(mode){
        grids.forEach(function(g){
          g.classList.toggle('af-masonry', mode === 'masonry');
          g.querySelectorAll('li.product').forEach(function(li){ if (mode !== 'masonry') li.style.gridRowEnd = ''; });
          span(g);
        });
        document.querySelectorAll('.af-layout-toggle button').forEach(function(b){
          b.classList.toggle('on', b.dataset.mode === mode);
        });
        try { localStorage.setItem(KEY, mode); } catch(e){}
      }

      // the toggle, next to the result count / ordering toolbar
      var host = document.querySelector('.woocommerce-result-count, .woocommerce-ordering, .shop-control-bar');
      var wrap = document.createElement('span');
      wrap.className = 'af-layout-toggle';
      wrap.innerHTML =
        '<button type="button" data-mode="grid" aria-label="Grid layout">' +
          '<svg viewBox="0 0 16 16"><path d="M1 1h6v6H1zM9 1h6v6H9zM1 9h6v6H1zM9 9h6v6H9z"/></svg>Grid</button>' +
        '<button type="button" data-mode="masonry" aria-label="Masonry layout">' +
          '<svg viewBox="0 0 16 16"><path d="M1 1h6v9H1zM9 1h6v5H9zM1 12h6v3H1zM9 8h6v7H9z"/></svg>Masonry</button>';
      if (host && host.parentNode) host.parentNode.insertBefore(wrap, host);
      else grids[0].parentNode.insertBefore(wrap, grids[0]);
      wrap.addEventListener('click', function(e){
        var b = e.target.closest('button'); if (b) apply(b.dataset.mode);
      });

      var mode = 'grid';
      try { mode = localStorage.getItem(KEY) || 'grid'; } catch(e){}
      if (mode !== 'masonry') mode = 'grid';
      apply(mode);

      // heights change as images arrive and on resize — keep the packing true
      var t = null;
      function respan(){ clearTimeout(t); t = setTimeout(function(){ grids.forEach(span); }, 120); }
      window.addEventListener('resize', respan);
      document.querySelectorAll('ul.products img').forEach(function(img){
        if (!img.complete) img.addEventListener('load', respan, { once:true });
      });
      try { new MutationObserver(respan).observe(grids[0], { childList:true, subtree:true }); } catch(e){}
    })();
    </script>
    <?php
}, 61);
