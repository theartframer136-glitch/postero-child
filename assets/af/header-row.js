(function(){
  var MAXW = 880;
  function sp(el, p, v){ el.style.setProperty(p, v, 'important'); }
  function vis(el){
    if (!el) return false;
    var cs = getComputedStyle(el), r = el.getBoundingClientRect();
    return cs.display !== 'none' && cs.visibility !== 'hidden' && r.width > 0 && r.height > 0;
  }
  function firstVisible(root, sel){
    var all = root.querySelectorAll(sel);
    for (var i = 0; i < all.length; i++) if (vis(all[i])) return all[i];
    return null;
  }
  function ancestors(el){ var a = []; while (el && el !== document.body){ a.push(el); el = el.parentElement; } return a; }
  function commonAncestor(els){
    var chains = els.map(ancestors);
    var first = chains[0];
    for (var i = 0; i < first.length; i++){
      var cand = first[i], ok = true;
      for (var j = 1; j < chains.length; j++) if (chains[j].indexOf(cand) === -1) { ok = false; break; }
      if (ok) return cand;
    }
    return null;
  }
  function childOf(row, el){
    var start = el;
    while (el && el.parentElement !== row) el = el.parentElement;
    return el || start;   // a collapsed chain leaves the item itself in the row
  }
  function mid(el){ var r = el.getBoundingClientRect(); return r.top + r.height / 2; }

  function layout(){
    var head = document.getElementById('masthead') || document.querySelector('header');
    if (!head) return;
    if (window.innerWidth > MAXW) return;

    var burger   = firstVisible(head, '.hfe-nav-menu__toggle, .hfe-menu-item-space-between, [class*="menu-toggle"], [class*="hamburger"]');
    var logo     = firstVisible(head, '.elementor-widget-site-logo, .hfe-site-logo, .site-logo, [class*="site-logo"]');
    var currency = firstVisible(head, '.postero-woocs-action-hover, [class*="woocs"], [class*="currency"]');
    var cart     = firstVisible(head, '.site-header-cart, .elementor-widget-postero-header-group, [class*="header-cart"], [class*="cart-contents"]');
    var items = [burger, logo, currency, cart].filter(Boolean);
    if (items.length < 2) return;

    var row = commonAncestor(items);
    if (!row || row === head) { row = items[0].parentElement; }

    // the row: one line, edge to edge, nothing wraps
    sp(row, 'display', 'flex');
    sp(row, 'flex-direction', 'row');
    sp(row, 'flex-wrap', 'nowrap');
    sp(row, 'align-items', 'center');
    sp(row, 'justify-content', 'flex-start');
    sp(row, 'gap', '6px');
    sp(row, 'width', '100%');
    sp(row, 'max-width', '100%');
    sp(row, 'box-sizing', 'border-box');
    sp(row, 'padding-left', '12px');
    sp(row, 'padding-right', '12px');
    sp(row, 'min-height', '60px');

    // its direct children: size to content, never a fixed width, never a column
    var kids = Array.prototype.slice.call(row.children);
    kids.forEach(function(k){
      sp(k, 'display', 'flex');
      sp(k, 'flex-direction', 'row');
      sp(k, 'flex-wrap', 'nowrap');
      sp(k, 'align-items', 'center');
      sp(k, 'flex', '0 0 auto');
      sp(k, 'width', 'auto');
      sp(k, 'max-width', 'none');
      sp(k, 'min-width', '0');
      sp(k, 'float', 'none');
      var kcs = getComputedStyle(k);
      if (kcs.position === 'absolute' || kcs.position === 'fixed') {
        sp(k, 'position', 'relative');
        sp(k, 'left', 'auto'); sp(k, 'right', 'auto'); sp(k, 'top', 'auto');
      }
      sp(k, 'margin', '0');
      sp(k, 'padding-left', '0'); sp(k, 'padding-right', '0');
    });

    /* Every ancestor between the row and each item, not only the row's direct
       children. That was the hole: the row was a nowrap flex row and its kids
       were too, but in the signed-in markup there are further wrappers in
       between, and any one of them being a column or a full-width block puts
       the currency above the cart no matter what the row says. Walk each
       item's chain up to the row and make the whole chain a row that sizes to
       its contents. */
    /* Forcing each wrapper to be a row was still not enough, so stop trying to
       make the wrappers behave and take them out of the layout altogether.
       display:contents makes an element generate no box at all: its children
       are laid out by ITS parent. Collapse the chain between the row and each
       item and the four controls become flex children of the row directly,
       with nothing left in between that could put them on separate lines.

       A wrapper is only collapsed when the item's subtree is the ONLY visible
       thing in it. The menu toggle's wrapper, for one, also holds the entire
       flyout navigation; collapsing that would spill the menu into the bar.
       Where there are other visible children the wrapper stays a box and is
       merely forced into a row. */
    function visChildren(el){
      return Array.prototype.filter.call(el.children, vis);
    }
    items.forEach(function(it){
      var el = it.parentElement;
      while (el && el !== row) {
        var kidsHere = visChildren(el);
        if (kidsHere.length === 1) {
          sp(el, 'display', 'contents');
        } else {
          sp(el, 'display', 'flex');
          sp(el, 'flex-direction', 'row');
          sp(el, 'flex-wrap', 'nowrap');
          sp(el, 'align-items', 'center');
          sp(el, 'float', 'none');
          sp(el, 'width', 'auto');
          sp(el, 'max-width', 'none');
          sp(el, 'min-width', '0');
          sp(el, 'flex', '0 0 auto');
          sp(el, 'clear', 'none');
        }
        el = el.parentElement;
      }
      /* the item itself never collapses — it is the thing being laid out */
      sp(it, 'flex', '0 0 auto');
      sp(it, 'float', 'none');
      sp(it, 'max-width', 'none');
    });

    /* Collapsing wrappers can make the row's child list change under us, so
       state the row's own layout again afterwards. */
    sp(row, 'display', 'flex');
    sp(row, 'flex-direction', 'row');
    sp(row, 'flex-wrap', 'nowrap');
    sp(row, 'align-items', 'center');

    // the logo is the one thing that may give way
    var logoKid = logo ? childOf(row, logo) : null;
    if (logoKid) { sp(logoKid, 'flex', '0 1 auto'); sp(logoKid, 'min-width', '0'); sp(logoKid, 'overflow', 'hidden'); }
    var img = logo ? logo.querySelector('img') : null;
    if (img) { sp(img, 'max-width', '100%'); sp(img, 'height', 'auto'); sp(img, 'width', 'auto'); sp(img, 'max-height', '44px'); }

    /* ── Spacing ────────────────────────────────────────────────────────
       Stated on whichever element actually generates a box. Collapsing the
       wrappers fixed the stacking but took their margins with them, which is
       why the currency and the cart ended up touching. A margin on a
       display:contents element does nothing, so walk up from each control and
       spend the spacing on the outermost ancestor that is still a real box —
       or on the control itself when the whole chain has been collapsed. */
    function boxFor(item){
      var el = item, last = item;
      while (el && el !== row) {
        if (getComputedStyle(el).display !== 'contents') last = el;
        el = el.parentElement;
      }
      return last;
    }

    sp(row, 'gap', '0');                 // spacing is stated per item, below
    sp(row, 'padding-top', '6px');
    sp(row, 'padding-bottom', '6px');

    var bBox = burger   ? boxFor(burger)   : null;
    var lBox = logo     ? boxFor(logo)     : null;
    var cBox = currency ? boxFor(currency) : null;
    var kBox = cart     ? boxFor(cart)     : null;

    if (bBox) sp(bBox, 'margin', '0 10px 0 0');
    /* the logo takes the slack, which puts the two controls against the right
       edge without needing an auto margin on something that may be boxless */
    if (lBox) sp(lBox, 'margin', '0 auto 0 0');
    if (cBox) sp(cBox, 'margin', '0 14px 0 0');
    if (kBox) sp(kBox, 'margin', '0');

    // the logo sits to the bar's height, not the other way round
    if (img) sp(img, 'max-height', '40px');

    // "$ USD" and its chevron, and the cart's icon and count, get a little air
    [currency, cart].forEach(function(el){ if (el) sp(el, 'gap', '5px'); });

    /* Inside the CURRENCY and CART only. Deliberately not inside the menu
       toggle: the flyout navigation's whole markup lives in there, hidden,
       and forcing rows and nowrap onto a hidden menu would mangle it the
       moment someone opened it. Nothing below touches an element that is not
       currently visible, that sits inside a nav or a submenu, or that is
       positioned out of flow — those are dropdown panels and they stay
       dropdowns. */
    var SKIP = /sub-menu|widget_shopping_cart|dropdown-menu|af-cty-menu|nav-menu|flyout|mini-cart|cart-side/i;
    [currency, cart].forEach(function(box){
      if (!box) return;
      var scope = childOf(row, box) || box;
      scope.querySelectorAll('*').forEach(function(el){
        if (!vis(el)) return;
        if (el.closest('nav, .sub-menu, [class*="nav-menu"], [class*="flyout"], [class*="widget_shopping_cart"]')) return;
        var cls = (typeof el.className === 'string') ? el.className : '';
        if (SKIP.test(cls)) return;
        /* the running total is not part of the bar — see the CSS note above */
        if (/amount|Price-amount/i.test(cls) || el.tagName === 'BDI') return;
        if (el.closest('.amount, .woocommerce-Price-amount')) return;
        var cs = getComputedStyle(el);
        if (cs.position === 'absolute' || cs.position === 'fixed') return;
        if (cs.display.indexOf('flex') !== -1) { sp(el, 'flex-direction', 'row'); sp(el, 'flex-wrap', 'nowrap'); sp(el, 'align-items', 'center'); }
        else if (cs.display === 'block' && el.children.length) { sp(el, 'display', 'flex'); sp(el, 'flex-direction', 'row'); sp(el, 'align-items', 'center'); }
        else if (cs.display === 'list-item') { sp(el, 'display', 'inline-flex'); sp(el, 'align-items', 'center'); }
        if (cs.float !== 'none') sp(el, 'float', 'none');
        if (/px$/.test(cs.width) && el.tagName !== 'IMG' && el.tagName !== 'SVG') sp(el, 'width', 'auto');
        sp(el, 'white-space', 'nowrap');
        sp(el, 'margin-left', '0'); sp(el, 'margin-right', '0');
      });
    });
    // the currency and the cart are each a real target, and a fingertip's worth
    [currency, cart, burger].forEach(function(el){ if (el) { sp(el, 'min-height', '40px'); sp(el, 'min-width', '40px'); sp(el, 'display', 'inline-flex'); sp(el, 'align-items', 'center'); sp(el, 'justify-content', 'center'); } });

    /* Measure the result. If a line still broke, the logo may give up a little
       height to make room — but only while that is actually helping, and never
       below 34px. The previous version shrank on every pass regardless, so
       when the stacking had nothing to do with width it ran all four passes
       and left the logo a 60x20 thumbnail next to a bar that was still in two
       rows. Making the header worse is not an acceptable outcome of failing
       to fix it. */
    function spreadNow(){ var m = items.map(mid); return Math.max.apply(null, m) - Math.min.apply(null, m); }
    var before = spreadNow(), maxH = 40;
    for (var pass = 0; pass < 3; pass++) {
      var over = row.scrollWidth > row.clientWidth + 1;
      if (before <= 10 && !over) break;
      if (maxH <= 34 || !img) break;
      maxH -= 5;
      sp(img, 'max-height', maxH + 'px');
      sp(row, 'gap', '4px');
      var after = spreadNow();
      if (after >= before - 0.5) {            // shrinking is not helping — put it back
        maxH += 5;
        sp(img, 'max-height', maxH + 'px');
        break;
      }
      before = after;
    }

    // say what happened, so a screenshot of the console is enough. Debug
    // switch only (inc/debug-flag.php, DEF-12): this ran on every resize for
    // every visitor, and measuring each control to build the line forces a
    // layout. Skipped entirely when off, measuring included.
    if (window.afDebugOn === true) try {
      var report = items.map(function(el){ var r = el.getBoundingClientRect(); return (el.className || el.tagName).toString().split(' ')[0] + ' y' + Math.round(r.top) + ' x' + Math.round(r.left) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height); });
      var sp2 = Math.round(spreadNow());
      console.log('[af-header-row] ' + window.innerWidth + 'px  ' + (sp2 <= 10 ? 'ONE LINE' : 'STILL STACKED, spread ' + sp2 + 'px')
        + '  row=' + (row.className || row.tagName).toString().split(' ').slice(0,3).join('.')
        + '  :: ' + report.join(' | '));
    } catch (e) {}
  }

  function run(){ try { layout(); } catch (e) { try { console.log('[af-header-row] failed: ' + e.message); } catch (_) {} } }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
  window.addEventListener('load', run);
  [400, 1200, 2500].forEach(function(d){ setTimeout(run, d); });
  var t = null;
  window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(run, 150); });
  // WooCommerce swaps the cart's markup in by AJAX after load; lay it out again
  document.body.addEventListener('wc_fragments_refreshed', run);
  document.body.addEventListener('wc_fragments_loaded', run);
  try {
    var head = document.getElementById('masthead');
    if (head) new MutationObserver(function(){ clearTimeout(t); t = setTimeout(run, 120); }).observe(head, { childList: true, subtree: true });
  } catch (e) {}
})();
