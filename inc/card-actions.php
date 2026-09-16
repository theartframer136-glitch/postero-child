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
      flex-wrap:nowrap !important;overflow:visible !important;}
    html body ul.products li.product .af-acts{
      display:inline-flex !important;align-items:center !important;
      gap:4px !important;margin-left:auto !important;flex:0 0 auto !important;
      overflow:visible !important;}

    /* One button. Icon only — no words, nothing to compete with the artwork.

       overflow is stated as VISIBLE deliberately. The previous version set it
       to hidden to stop stray label text showing, and that is what swallowed
       the tooltip: the tooltip is drawn above the button, outside its box, so
       clipping the button clips the tooltip with it. font-size:0 hides the
       words on its own and costs nothing. */
    html body ul.products li.product .af-acts > *{
      width:30px !important;min-width:30px !important;height:30px !important;
      padding:0 !important;margin:0 !important;border:0 !important;
      background:transparent !important;border-radius:50% !important;
      display:inline-flex !important;align-items:center !important;
      justify-content:center !important;cursor:pointer !important;
      color:#4E423D !important;line-height:1 !important;
      font-size:0 !important;overflow:visible !important;
      white-space:nowrap !important;
      opacity:1 !important;visibility:visible !important;
      position:relative !important;transform:none !important;
      box-shadow:none !important;text-decoration:none !important;
      transition:color .15s ease, background .15s ease !important;}
    html body ul.products li.product .af-acts > *:hover{
      color:#c9a84c !important;background:rgba(201,168,76,.14) !important;}

    /* One icon set, drawn one way. The four arrived with three different
       kinds of mark between them — a colour emoji bag, a bare arrows
       character, and two glyphs from the theme's icon font — which is why the
       row looked assembled from spare parts. They are replaced below with one
       stroked set that takes its colour from the button. */
    html body ul.products li.product .af-acts svg.af-ico{
      width:19px !important;height:19px !important;display:block !important;
      fill:none !important;stroke:currentColor !important;
      stroke-width:1.7 !important;stroke-linecap:round !important;
      stroke-linejoin:round !important;}
    /* Nothing else inside a button may take up room. */
    html body ul.products li.product .af-acts > * > *:not(svg){
      position:absolute !important;width:1px !important;height:1px !important;
      overflow:hidden !important;clip:rect(0 0 0 0) !important;}
    html body ul.products li.product .af-acts > *::before{display:none !important;}

    /* The tooltip, drawn by the button itself.

       It hangs BELOW the icon, not above. Above is the conventional place and
       it is the wrong one here: the rating row sits at the very top of the
       caption, so a tooltip above it has to escape the caption's top edge — and
       this card has overflow:hidden on div.product-block, on li.product and on
       div.hfeed.site. Below, it opens into the caption over the title, with
       nothing in its way.

       font-size is stated outright because the button is set to font-size 0 to
       hide its label, and a pseudo-element inherits that. A tooltip at 0px is
       no tooltip at all — which is one of the two reasons it never appeared. */
    html body ul.products li.product .af-acts [data-af-tip]::after{
      content:attr(data-af-tip);
      position:absolute;top:calc(100% + 7px);left:50%;
      transform:translateX(-50%) translateY(-3px);
      background:#1a1a1a;color:#fff;font-size:11px !important;font-weight:600;
      font-family:"Instrument Sans",system-ui,sans-serif;letter-spacing:.2px;
      line-height:1;padding:6px 9px;border-radius:4px;white-space:nowrap;
      opacity:0;pointer-events:none;z-index:60;
      box-shadow:0 3px 10px rgba(0,0,0,.22);
      transition:opacity .15s ease, transform .15s ease;}
    html body ul.products li.product .af-acts [data-af-tip]:hover::after{
      opacity:1;transform:translateX(-50%) translateY(0);}

    /* A finger needs more room than a cursor, and a touch screen never sends
       the hover that draws the tooltip. */
    @media (max-width:781px){
      html body ul.products li.product .af-acts > *{
        width:34px !important;min-width:34px !important;height:34px !important;}
      html body ul.products li.product .af-acts [data-af-tip]::after{
        display:none !important;}
    }
    </style>
    <script>
    (function(){
      // One stroked set on a 24px grid, taking the button's colour, so the four
      // read as a single row rather than three icon libraries side by side.
      var S = '<svg class="af-ico" viewBox="0 0 24 24" aria-hidden="true">';
      var ICON = {
        // A trolley, not a bag. The previous bag outline read as a dustbin at
        // this size — two wheels and a handle say "cart" unmistakably.
        cart:    S + '<circle cx="9.5" cy="19.5" r="1.4"/><circle cx="17.5" cy="19.5" r="1.4"/>'
                   + '<path d="M2.5 3.5h2.2l2.6 11.2h11l2.2-8H6.2"/></svg>',
        compare: S + '<path d="M4 9.2h13.2l-3.4-3.4"/><path d="M20 14.8H6.8l3.4 3.4"/></svg>',
        quick:   S + '<path d="M2.4 12S6 6.4 12 6.4 21.6 12 21.6 12 18 17.6 12 17.6 2.4 12 2.4 12Z"/>'
                   + '<circle cx="12" cy="12" r="2.6"/></svg>',
        wish:    S + '<path d="M12 19.7s-6.9-4.4-6.9-9.1a3.85 3.85 0 0 1 6.9-2 3.85 3.85 0 0 1 6.9 2'
                   + 'c0 4.7-6.9 9.1-6.9 9.1Z"/></svg>'
      };
      var WANTED = [
        ['.af-icon-corner a.add_to_cart_button, .product-block a.add_to_cart_button', 'Add to cart', 'cart'],
        ['.af-cmp-btn',  'Compare',         'compare'],
        ['.woosq-btn',   'Quick view',      'quick'],
        ['.woosw-btn',   'Add to wishlist', 'wish']
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
        // The four do not all exist at the same moment — the plugins add theirs
        // after the theme adds its own — so first-come placement produced the
        // order the owner filmed: eye, heart, cart, arrows. Collect them, then
        // put them in the intended order whenever that order is wrong.
        var found = [];
        WANTED.forEach(function(pair){
          var el = card.querySelector(pair[0]);
          if (el) found.push([el, pair]);
        });
        var moved = found.length;
        var wrong = found.some(function(f, i){ return acts.children[i] !== f[0]; })
                 || acts.children.length !== found.length;
        found.forEach(function(f){
          var el = f[0], pair = f[1];
          if (wrong || el.parentElement !== acts) acts.appendChild(el);
          // Drawn by us, and only when it is not already ours: the wishlist
          // plugin rewrites its own button when a piece is saved, and redrawing
          // on every mutation would chase its own tail.
          if (!el.querySelector('svg.af-ico')) el.innerHTML = ICON[pair[2]];
          if (el.getAttribute('data-af-tip') !== pair[1]) {
            el.setAttribute('data-af-tip', pair[1]);
            el.setAttribute('aria-label', pair[1]);
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
