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
    if (!(is_shop() || is_product_taxonomy() || is_product() || is_front_page() || is_search())) return;
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
      width:30px !important;min-width:30px !important;max-width:30px !important;
      height:30px !important;min-height:30px !important;max-height:30px !important;
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

    /* The cart anchor is given .af-icon-only elsewhere in this theme, and
       assets/css/custom.css sizes that class at 36px with overflow:hidden.
       That is the very clip this file exists to undo, and it would have eaten
       the cart's tooltip and drawn it as an oval six pixels wider than its
       neighbours. The class is also removed in the script; this is the belt to
       that pair of braces. */
    html body ul.products li.product .af-acts > a.add_to_cart_button,
    html body ul.products li.product .af-acts > a.add_to_cart_button.af-icon-only{
      width:30px !important;min-width:30px !important;max-width:30px !important;
      overflow:visible !important;}

    /* WooCommerce drops a "View cart" link in beside the button it was clicked
       from. Inside this row that link would become a fifth, empty circle. */
    html body ul.products li.product .af-acts > .added_to_cart{display:none !important;}
    /* A click deserves an answer: the cart stays gold once the piece is in. */
    html body ul.products li.product .af-acts > .add_to_cart_button.added{
      color:#c9a84c !important;}

    /* WooCommerce, on an AJAX add-to-cart, dims the button to 25% and paints
       a spinner and then a tick into its ::after — the same ::after that
       carries the tooltip here. The two collide into a squashed dark blob
       beside a greyed-out icon, which is the "animation" the owner wants gone.
       The button stays as it is; the answer to the click is the toast below
       and the icon turning gold once the piece is in. */
    html body ul.products li.product .af-acts > .add_to_cart_button.loading,
    html body ul.products li.product .af-acts > .add_to_cart_button.added{
      opacity:1 !important;}
    html body ul.products li.product .af-acts > .add_to_cart_button.loading::after,
    html body ul.products li.product .af-acts > .add_to_cart_button.added::after{
      animation:none !important;font-family:"Instrument Sans",system-ui,sans-serif !important;
      vertical-align:baseline !important;margin:0 !important;}
    @media not all and (hover:hover) and (pointer:fine){
      html body ul.products li.product .af-acts > .add_to_cart_button.loading::after,
      html body ul.products li.product .af-acts > .add_to_cart_button.added::after{
        display:none !important;}
    }

    /* ── THE TOAST ──────────────────────────────────────────────────────────
       One line, bottom centre, gone in three seconds. Dark like the tooltip,
       a gold edge like the rest of the shop. On a phone it sits above the
       bottom navigation bar rather than under it. */
    #af-toast{
      position:fixed;left:50%;bottom:calc(26px + env(safe-area-inset-bottom,0px));
      transform:translateX(-50%) translateY(14px);
      background:#1a1a1a;color:#fff;border-left:3px solid #c9a84c;
      padding:12px 18px;border-radius:8px;
      font:600 13px/1.3 "Instrument Sans",system-ui,sans-serif;letter-spacing:.2px;
      box-shadow:0 8px 28px rgba(0,0,0,.28);white-space:nowrap;
      max-width:calc(100vw - 32px);overflow:hidden;text-overflow:ellipsis;
      opacity:0;pointer-events:none;z-index:2147483000;
      transition:opacity .2s ease, transform .2s ease;}
    #af-toast.on{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:auto;}
    #af-toast a{color:#c9a84c;text-decoration:none;margin-left:12px;
      border-bottom:1px solid rgba(201,168,76,.55);}
    #af-toast a:hover{border-bottom-color:#c9a84c;}
    @media (max-width:781px){
      #af-toast{bottom:calc(84px + env(safe-area-inset-bottom,0px));}
    }

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
       the wrong one here: the rating row sits at the top of the caption, so a
       tooltip above it must escape the caption's top edge — and this card has
       overflow:hidden on div.product-block, on li.product and on div.hfeed.site.
       Below, it opens into the caption over the title, with nothing in its way.

       font-size is stated outright because the button is set to font-size 0 to
       hide its label, and a pseudo-element inherits that. A tooltip at 0px is
       no tooltip at all — one of the two reasons it never appeared.

       Only where there is a cursor to hover with. Keying this to screen width
       would take the tooltip away from a narrow desktop window and leave it
       stuck, mid-card, after a tap on a large tablet. */
    @media (hover:hover) and (pointer:fine){
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

      /* The last two sit within a label's width of the card's right edge, and
         "Add to wishlist" is the longest of the four. Centred, it would hang
         past the card and be cut off by the very overflow:hidden described
         above. These grow inward instead. */
      html body ul.products li.product .af-acts > *:nth-last-child(-n+2)[data-af-tip]::after{
        left:auto;right:-2px;transform:translateY(-3px);}
      html body ul.products li.product .af-acts > *:nth-last-child(-n+2)[data-af-tip]:hover::after{
        transform:translateY(0);}
    }

    /* A finger needs more room than a cursor, and a touch screen never sends
       the hover that draws the tooltip. */
    @media (max-width:781px){
      html body ul.products li.product .af-acts > *{
        width:34px !important;min-width:34px !important;max-width:34px !important;
        height:34px !important;min-height:34px !important;max-height:34px !important;}
      html body ul.products li.product .af-acts [data-af-tip]::after{
        display:none !important;}
      /* A phone card is ~200px wide; stars plus four finger-sized buttons
         need ~270px on one line and the last button fell off the card. The
         row wraps: stars on the first line, the four buttons on the next,
         still kept to the right. */
      html body ul.products li.product .product-action{
        flex-wrap:wrap !important;row-gap:6px !important;}
      html body ul.products li.product .product-action > .count-review,
      html body ul.products li.product .product-action > .woocommerce-product-rating{
        flex:1 1 100% !important;min-width:0 !important;
        display:flex !important;align-items:center !important;gap:6px !important;}
      /* The second line is a toolbar: the four buttons spread evenly across
         the card under a hairline, so the row reads as one designed strip
         rather than icons huddled in a corner. */
      html body ul.products li.product .af-acts{
        flex:0 0 100% !important;display:flex !important;
        justify-content:space-around !important;margin-left:0 !important;
        padding-top:6px !important;border-top:1px solid rgba(78,66,61,.12) !important;}
      html body ul.products li.product .af-acts > *{
        width:32px !important;min-width:32px !important;max-width:32px !important;
        height:32px !important;min-height:32px !important;max-height:32px !important;
        background:rgba(78,66,61,.06) !important;}
      html body ul.products li.product .af-acts svg.af-ico{width:18px !important;height:18px !important;}
    }
    </style>
    <script>
    (function(){
      // One stroked set on a 24px grid, taking the button's colour, so the four
      // read as a single row rather than three icon libraries side by side.
      var S = '<svg class="af-ico" viewBox="0 0 24 24" aria-hidden="true">';
      var ICON = {
        // A trolley, not a bag: an outline bag at this size reads as a dustbin.
        cart:    S + '<circle cx="10" cy="18.5" r="1.3"/><circle cx="17.5" cy="18.5" r="1.3"/>'
                   + '<path d="M2.8 4.5h2.1l2.5 10.2h10.4l2.1-7.4H6"/></svg>',
        // Both arrowheads need two barbs. With one, it reads as a flick, not
        // an arrow, and the pair does not say "compare" at nineteen pixels.
        compare: S + '<path d="M4 9.3h13.4m-3.5-3.5 3.5 3.5-3.5 3.5"/>'
                   + '<path d="M20 14.7H6.6m3.5-3.5-3.5 3.5 3.5 3.5"/></svg>',
        quick:   S + '<path d="M2.4 12S6 6.4 12 6.4 21.6 12 21.6 12 18 17.6 12 17.6 2.4 12 2.4 12Z"/>'
                   + '<circle cx="12" cy="12" r="2.6"/></svg>',
        wish:    S + '<path d="M12 19.3s-6.7-4.3-6.7-8.9a3.75 3.75 0 0 1 6.7-1.9 3.75 3.75 0 0 1 6.7 1.9'
                   + 'c0 4.6-6.7 8.9-6.7 8.9Z"/></svg>',
        // Saved: the same heart, filled. The plugin shows its own added state
        // in a ::before that this file suppresses, so without this the heart
        // would stay hollow forever and the click would look ignored.
        wishOn:  '<svg class="af-ico af-ico-on" viewBox="0 0 24 24" aria-hidden="true">'
                   + '<path d="M12 19.3s-6.7-4.3-6.7-8.9a3.75 3.75 0 0 1 6.7-1.9 3.75 3.75 0 0 1 6.7 1.9'
                   + 'c0 4.6-6.7 8.9-6.7 8.9Z" fill="currentColor" stroke="currentColor"/></svg>'
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
        // order the owner filmed. Collect them, then correct the order only
        // when it is actually wrong.
        var found = [];
        WANTED.forEach(function(pair){
          var el = card.querySelector(pair[0]);
          if (el) found.push([el, pair]);
        });
        if (!found.length) return;

        // Compare against OUR buttons only, never against the row's child
        // count. WooCommerce inserts a "View cart" link into this row after an
        // add-to-cart, and counting children would then make the order look
        // wrong forever: every pass would re-append, every append would wake
        // the observer that called it, and the tab would spin until it was
        // closed.
        var mine = [];
        for (var i = 0; i < acts.children.length; i++) {
          for (var j = 0; j < found.length; j++) {
            if (acts.children[i] === found[j][0]) { mine.push(acts.children[i]); break; }
          }
        }
        var wrong = mine.length !== found.length
                 || found.some(function(f, k){ return mine[k] !== f[0]; });

        found.forEach(function(f){
          var el = f[0], pair = f[1];
          if (wrong || el.parentElement !== acts) acts.appendChild(el);

          // Another rule in this theme sizes .af-icon-only at 36px with
          // overflow:hidden, which would clip this button's tooltip and draw
          // it as an oval. It has no business on a button that now lives here.
          el.classList.remove('af-icon-only');

          // The theme and the plugins put a native title on these. Left alone,
          // the browser draws its own bubble a beat after ours, in a different
          // place, sometimes saying something different. Removed on every pass,
          // because a plugin redraw puts it back.
          if (el.hasAttribute('title')) el.removeAttribute('title');

          // Drawn by us. The wishlist button has two states and the plugin
          // shows its own in a ::before we suppress, so the heart is re-stamped
          // when that state changes — and only then, or a redraw would chase
          // its own tail through the observer.
          var on  = /(^|\s)(woosw-added|added)(\s|$)/.test(el.className);
          var key = pair[2] === 'wish' ? (on ? 'wishOn' : 'wish') : pair[2];
          // Two conditions, and the second is the one that matters: the
          // wishlist plugin syncs its buttons over AJAX after load and wipes
          // their innerHTML. The attribute survived that, the SVG did not, and
          // a guard on the attribute alone declared the heart "already drawn"
          // over an empty button — which is precisely what the owner filmed.
          if (el.getAttribute('data-af-ico') !== key || !el.querySelector('svg.af-ico')) {
            el.innerHTML = ICON[key];
            el.setAttribute('data-af-ico', key);
          }

          if (pair[2] === 'wish') watchWishlist(el);

          var tip = (pair[2] === 'wish' && on) ? 'In your wishlist' : pair[1];
          if (el.getAttribute('data-af-tip') !== tip) {
            el.setAttribute('data-af-tip', tip);
            el.setAttribute('aria-label', tip);
          }
        });

        // Only once the controls are safely in their new home do the empty
        // containers go. Hiding them in the stylesheet would mean that if this
        // script ever failed to run, the buttons would vanish altogether
        // rather than simply stay where the theme put them.
        ['.group-action', '.af-icon-corner'].forEach(function(sel){
          var box = card.querySelector(sel);
          if (box && !box.querySelector('a, button')) {
            box.style.setProperty('display', 'none', 'important');
          }
        });
      }

      // ── THE TOAST ───────────────────────────────────────────────────────
      var toastEl = null, toastTimer = null;
      function toast(msg, linkText, linkHref){
        if (!toastEl) {
          toastEl = document.createElement('div');
          toastEl.id = 'af-toast';
          toastEl.setAttribute('role', 'status');
          toastEl.setAttribute('aria-live', 'polite');
          document.body.appendChild(toastEl);
        }
        toastEl.textContent = msg;
        if (linkText && linkHref) {
          var a = document.createElement('a');
          a.href = linkHref; a.textContent = linkText;
          toastEl.appendChild(a);
        }
        clearTimeout(toastTimer);
        // Force a frame between "off" and "on" so the transition runs even
        // when two toasts follow each other.
        toastEl.classList.remove('on');
        requestAnimationFrame(function(){
          toastEl.classList.add('on');
          toastTimer = setTimeout(function(){ toastEl.classList.remove('on'); }, 3200);
        });
      }

      // Add to cart: WooCommerce announces a successful AJAX add on the body.
      // The "View cart" link it would have dropped into the row is hidden
      // there, so it is offered here instead, where it can be read.
      if (window.jQuery) {
        jQuery(document.body).on('added_to_cart', function(){
          var url = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.cart_url) || '/cart/';
          toast('Added to your cart', 'View cart', url);
        });
      }

      // Wishlist: the plugin says nothing usable, but it flips a class on the
      // button. Watch for that flip and speak when it happens — and only on a
      // real change, so a page load with saved pieces stays quiet.
      function watchWishlist(el){
        if (el.dataset.afWatched) return;
        el.dataset.afWatched = '1';
        var was = /(^|\s)(woosw-added|added)(\s|$)/.test(el.className);
        try {
          new MutationObserver(function(){
            var now = /(^|\s)(woosw-added|added)(\s|$)/.test(el.className);
            if (now !== was) {
              was = now;
              toast(now ? 'Saved to your wishlist' : 'Removed from your wishlist',
                    now ? 'View wishlist' : '', now ? '/wishlist/' : '');
              schedule();   // redraw the heart, filled or hollow
            }
          }).observe(el, {attributes:true, attributeFilter:['class']});
        } catch(e){}
      }

      // Not re-entrant. Everything below moves nodes, and moving a node wakes
      // the observer that asked for the move; without this guard the two call
      // each other until the tab gives up.
      var busy = false, pending = false;
      function run(){
        if (busy) return;
        busy = true;
        try { document.querySelectorAll('ul.products li.product').forEach(place); }
        finally { busy = false; }
      }
      function schedule(){
        if (pending) return;
        pending = true;
        requestAnimationFrame(function(){ pending = false; run(); });
      }

      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
      else run();
      window.addEventListener('load', run);

      // A filter change re-renders the listing and the new cards arrive bare.
      // Only insertions that could plausibly be a card or one of these buttons
      // are worth a pass — this page already carries several unfiltered
      // observers and they are not free.
      try {
        new MutationObserver(function(muts){
          for (var i = 0; i < muts.length; i++) {
            var added = muts[i].addedNodes;
            for (var j = 0; j < added.length; j++) {
              var n = added[j];
              if (n.nodeType !== 1) continue;
              if (n.matches && (n.matches('li.product, .woosq-btn, .woosw-btn, .add_to_cart_button, .af-cmp-btn')
                  || n.querySelector('li.product, .woosq-btn, .woosw-btn, .add_to_cart_button, .af-cmp-btn'))) {
                schedule();
                return;
              }
            }
          }
        }).observe(document.body, {childList:true, subtree:true});
      } catch(e){}
      [400, 1200, 2600].forEach(function(d){ setTimeout(run, d); });
    })();
    </script>
    <?php
}, 62);
