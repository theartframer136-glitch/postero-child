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

        // Two different Quick Views answer this icon depending on the page.
        // The homepage card carries <a class="quick-view-btn"> and opens the
        // theme's own #quickViewModal: wide, two columns, thumbnail strip,
        // sticky add-to-cart bar. A shop or category card carries
        // <button class="woosq-btn"> and opens the WPC plugin's popup, which
        // is the cramped one the owner filmed — a title wrapping a word to a
        // line and a brochure button escaping the panel.
        //
        // #quickViewModal is already printed on shop and category pages; it is
        // only the button that points elsewhere. So give that button the class
        // the theme's handler listens for. That handler is registered in the
        // CAPTURE phase and calls stopImmediatePropagation(), so it runs before
        // the plugin's delegated click on document and the plugin never sees
        // the event. One modal everywhere, and nothing is dequeued or patched
        // inside the plugin.
        found.forEach(function(entry){
          var el = entry[0];
          if (entry[1][2] !== 'quick') return;
          if (!el.classList.contains('woosq-btn')) return;
          if (el.classList.contains('quick-view-btn')) return;
          el.classList.add('quick-view-btn');
          // The theme's handler finds the product from the card's own link and
          // falls back to this attribute. The plugin names it data-id.
          if (!el.getAttribute('data-product-id')) {
            var pid = el.getAttribute('data-id');
            if (pid) el.setAttribute('data-product-id', pid);
          }
        });

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
    
