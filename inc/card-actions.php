<?php
/**
 * Cart, compare, quick view and wishlist — in the rating row, icons only.
 *
 * All four controls already exist on every product card; they are rendered by
 * the theme and by the WPC plugins. What they lacked was somewhere sensible to
 * be. Measured on the live listing, they sat twenty pixels BELOW a box with
 * overflow:hidden (div.product-transition, 300px tall) waiting for a hover
 * that slid them up — so they were clipped out of existence, while reporting a
 * perfect 38x38 box and full opacity to every check. That is why they appeared
 * to be missing rather than misplaced.
 *
 * They now live in the card's rating row, to the right of the stars: one line,
 * icons only, each naming itself on hover. That row already exists
 * (div.product-action holding div.count-review), it is inside the caption
 * rather than the picture, and nothing there clips.
 *
 * The move is done in JavaScript and the layout written inline with priority.
 * That is not a flourish: this theme sets these positions inline itself, and a
 * stylesheet — however specific, however many !importants — measurably loses
 * to it. Four separate faults in this codebase have now come down to the same
 * thing.
 *
 * Nothing outside the product card is touched.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_taxonomy() || is_product() || is_front_page())) return;
    ?>
    <style id="af-card-actions">
    /* The row: stars on the left, the four actions on the right. */
    html body ul.products li.product .product-action{
      display:flex !important;align-items:center !important;
      justify-content:space-between !important;gap:8px !important;
      flex-wrap:nowrap !important;}
    html body ul.products li.product .af-acts{
      display:inline-flex !important;align-items:center !important;
      gap:2px !important;margin-left:auto !important;flex:0 0 auto !important;}

    /* One button. Icon only — no words, no chip, nothing to compete with the
       artwork above it. The gold is the colour the rest of the shop uses. */
    html body ul.products li.product .af-acts > *{
      width:26px !important;min-width:26px !important;height:26px !important;
      padding:0 !important;margin:0 !important;border:0 !important;
      background:transparent !important;border-radius:50% !important;
      display:inline-flex !important;align-items:center !important;
      justify-content:center !important;cursor:pointer !important;
      color:#6b6250 !important;line-height:1 !important;
      opacity:1 !important;visibility:visible !important;
      position:relative !important;transform:none !important;
      box-shadow:none !important;text-decoration:none !important;
      transition:color .15s ease, background .15s ease !important;}
    html body ul.products li.product .af-acts > *:hover{
      color:#c9a84c !important;background:rgba(201,168,76,.12) !important;}

    /* The glyphs. The plugin buttons carry theirs in a ::before and set the
       button to font-size 0 to hide their words; the theme's two carry a
       character in a span. Both want the same size and the button's colour. */
    html body ul.products li.product .af-acts > *::before{
      font-size:15px !important;line-height:1 !important;color:inherit !important;}
    html body ul.products li.product .af-acts .af-icon-glyph,
    html body ul.products li.product .af-acts .af-cmp-icon,
    html body ul.products li.product .af-acts svg{
      font-size:15px !important;width:15px !important;height:15px !important;
      line-height:1 !important;color:inherit !important;fill:currentColor !important;}
    /* No words. Any label inside is for screen readers only. */
    html body ul.products li.product .af-acts .af-icon-label,
    html body ul.products li.product .af-acts > * > span:not(.af-icon-glyph):not(.af-cmp-icon){
      position:absolute !important;width:1px !important;height:1px !important;
      overflow:hidden !important;clip:rect(0 0 0 0) !important;
      white-space:nowrap !important;}

    /* The tooltip, drawn by the button itself. font-size is stated outright
       because these buttons are set to font-size 0 and a pseudo-element
       inherits that — a tooltip at 0px is no tooltip at all. */
    html body ul.products li.product .af-acts [data-af-tip]::after{
      content:attr(data-af-tip);
      position:absolute;bottom:calc(100% + 7px);left:50%;
      transform:translateX(-50%) translateY(3px);
      background:#1a1a1a;color:#fff;font-size:11px !important;font-weight:600;
      font-family:"Instrument Sans",system-ui,sans-serif;letter-spacing:.2px;
      line-height:1;padding:5px 8px;border-radius:4px;white-space:nowrap;
      opacity:0;pointer-events:none;z-index:40;
      transition:opacity .15s ease, transform .15s ease;}
    html body ul.products li.product .af-acts [data-af-tip]:hover::after{
      opacity:1;transform:translateX(-50%) translateY(0);}

    /* A finger needs more room than a cursor, and a touch screen never sends
       the hover that draws the tooltip. */
    @media (max-width:781px){
      html body ul.products li.product .af-acts > *{
        width:32px !important;min-width:32px !important;height:32px !important;}
      html body ul.products li.product .af-acts [data-af-tip]::after{
        display:none !important;}
    }
    </style>
    <script>
    (function(){
      // In the order a customer wants them: buy it, compare it, look closer,
      // keep it.
      var WANTED = [
        ['.af-icon-corner a.add_to_cart_button, .product-block a.add_to_cart_button', 'Add to cart'],
        ['.af-cmp-btn',  'Compare'],
        ['.woosq-btn',   'Quick view'],
        ['.woosw-btn',   'Add to wishlist']
      ];

      function place(card){
        var row = card.querySelector('.product-action');
        if (!row) return;
        var acts = row.querySelector('.af-acts');
        if (!acts) {
          acts = document.createElement('span');
          acts.className = 'af-acts';
          row.appendChild(acts);
        }
        var moved = 0;
        WANTED.forEach(function(pair){
          var el = card.querySelector(pair[0]);
          if (!el) return;
          if (el.parentElement !== acts) acts.appendChild(el);
          moved++;
          if (!el.getAttribute('data-af-tip')) {
            // Prefer the words the control already carries — the plugins put
            // real text inside and then hide it — over a name I invent.
            var own = (el.getAttribute('aria-label') || el.textContent || '')
                        .replace(/[\s​]+/g, ' ').replace(/^[^\w(]+/, '').trim();
            var tip = own.length > 2 ? own : pair[1];
            el.setAttribute('data-af-tip', tip);
            if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', tip);
          }
        });

        // Only once the controls are safely in their new home do the empty
        // containers go. Hiding them in the stylesheet would mean that if this
        // script ever failed to run, the buttons would vanish altogether
        // rather than simply stay where the theme put them.
        if (moved) {
          ['.group-action', '.af-icon-corner'].forEach(function(sel){
            var box = card.querySelector(sel);
            if (box && !box.querySelector('a, button')) {
              box.style.setProperty('display', 'none', 'important');
            }
          });
        }
      }

      function run(){
        document.querySelectorAll('ul.products li.product').forEach(place);
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
      else run();
      window.addEventListener('load', run);
      // A filter change re-renders the listing and the new cards arrive bare.
      try {
        new MutationObserver(function(m){
          for (var i = 0; i < m.length; i++) {
            if (m[i].addedNodes && m[i].addedNodes.length) { run(); break; }
          }
        }).observe(document.body, {childList:true, subtree:true});
      } catch(e){}
      [400, 1200, 2600].forEach(function(d){ setTimeout(run, d); });
    })();
    </script>
    <?php
}, 62);
