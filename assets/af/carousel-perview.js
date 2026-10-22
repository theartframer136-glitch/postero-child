(function(){
  var GAP = 20;
  function perView(){
    var w = window.innerWidth;
    if (w <= 600) return 1;
    if (w <= 900) return 2;
    if (w <= 1200) return 3;
    return 4;
  }
  function sizeCards(ul){
    var n = perView();
    ul.querySelectorAll('li.product').forEach(function(li){
      li.style.setProperty('flex', '0 0 calc((100% - ' + (GAP * (n - 1)) + 'px) / ' + n + ')', 'important');
      li.style.setProperty('max-width', 'calc((100% - ' + (GAP * (n - 1)) + 'px) / ' + n + ')', 'important');
      li.style.setProperty('margin', '0', 'important');
      li.style.setProperty('scroll-snap-align', 'start', 'important');
    });
  }
  // Make every card's image area one fixed box that both the artwork and the
  // room preview fill. Without this the preview keeps its own proportions on
  // hover and leaves a white band under it, and the card visibly changes shape
  // as the pointer moves across the row.
  function fillImages(sec){
    var H = (window.innerWidth <= 520) ? 260 : 300;
    sec.querySelectorAll('li.product').forEach(function(li){
      var link = li.querySelector('a');
      if (!link) return;
      var imgs = link.querySelectorAll('img');
      if (!imgs.length) return;
      var host = imgs[0].parentElement;   // whatever wraps the artwork
      if (!host) return;
      host.style.setProperty('position', 'relative', 'important');
      host.style.setProperty('display', 'block', 'important');
      host.style.setProperty('height', H + 'px', 'important');
      host.style.setProperty('min-height', H + 'px', 'important');
      host.style.setProperty('overflow', 'hidden', 'important');
      host.style.setProperty('padding-bottom', '0', 'important');
      Array.prototype.forEach.call(imgs, function(im, i){
        im.style.setProperty('position', 'absolute', 'important');
        im.style.setProperty('top', '0', 'important');
        im.style.setProperty('left', '0', 'important');
        im.style.setProperty('width', '100%', 'important');
        im.style.setProperty('height', '100%', 'important');
        im.style.setProperty('max-width', 'none', 'important');
        im.style.setProperty('object-fit', 'cover', 'important');
        im.style.setProperty('margin', '0', 'important');
        im.style.setProperty('z-index', i === 0 ? '1' : '2', 'important');
      });
      // If the site's own hover swap never initialised on these cards, the
      // second image would simply sit on top forever. Give it the same
      // behaviour: hidden until the card is hovered.
      if (imgs.length > 1 && !li.dataset.afRelHover) {
        li.dataset.afRelHover = '1';
        var hover = imgs[1];
        hover.style.setProperty('opacity', '0', 'important');
        hover.style.setProperty('transition', 'opacity .35s ease', 'important');
        li.addEventListener('mouseenter', function(){ hover.style.setProperty('opacity', '1', 'important'); });
        li.addEventListener('mouseleave', function(){ hover.style.setProperty('opacity', '0', 'important'); });
      }
    });
  }

  // The four card actions — cart, compare, quick view, wishlist — as one row.
  //
  // Moving the theme's own controls into a row did not hold: several other
  // scripts on this site restyle those elements with inline !important rules
  // of their own, so whatever we set was overwritten moments later, and some
  // of the controls are injected only on first hover. So build a row of our
  // own buttons — elements nothing else on the site knows about — and have
  // each one CLICK the original control, which keeps every plugin behaviour
  // (ajax add to cart, compare list, quick view modal, wishlist save) exactly
  // as it is. The originals are parked in a zero-size holder rather than
  // removed, so any plugin that looks for them still finds them.
  var ICONS = {
    cart:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
    compare: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>',
    quick:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
    wish:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"></path></svg>'
  };

  function findControl(li, sels){
    var found = null;
    sels.split(',').some(function(sel){
      var list = li.querySelectorAll(sel.trim());
      for (var i = 0; i < list.length; i++) {
        var el = list[i];
        if (el.closest('.af-card-actions') || el.closest('.af-card-orig')) continue;
        if (el.tagName === 'A' && el.querySelector('img')) continue;   // the product link
        found = el;
        return true;
      }
      return false;
    });
    return found;
  }

  function makeBtn(kind, title, orig, gold){
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'af-ca-btn af-ca-' + kind;
    b.title = title;
    b.setAttribute('aria-label', title);
    b.innerHTML = ICONS[kind];
    // 38px on a desktop card, 40px on a phone. These are written inline with
    // !important, which beats any stylesheet — so a media query cannot raise
    // them and the size has to be decided here. 40px is the smallest
    // comfortable touch target; the mobile audit flagged this button at 38
    // after every other control on the page had been lifted.
    var CA = (window.innerWidth <= 781) ? '40px' : '38px';
    [['width',CA],['height',CA],['min-width',CA],['border-radius','50%'],
     ['display','inline-flex'],['align-items','center'],['justify-content','center'],
     ['padding','0'],['margin','0'],['border','0'],['cursor','pointer'],
     ['box-shadow','0 2px 8px rgba(0,0,0,.18)'],['flex','0 0 auto'],
     ['background', gold ? '#c9a84c' : '#fff'],['color', gold ? '#fff' : '#1a1a1a'],
     ['transition','background .2s,color .2s']].forEach(function(p){
      b.style.setProperty(p[0], p[1], 'important');
    });
    var svg = b.querySelector('svg');
    if (svg) {
      svg.style.setProperty('width', '17px', 'important');
      svg.style.setProperty('height', '17px', 'important');
      svg.style.setProperty('display', 'block', 'important');
      svg.style.setProperty('fill', 'none', 'important');
      svg.style.setProperty('stroke', 'currentColor', 'important');
    }
    b.addEventListener('mouseenter', function(){
      b.style.setProperty('background', gold ? '#8b6a2b' : '#c9a84c', 'important');
      b.style.setProperty('color', '#fff', 'important');
    });
    b.addEventListener('mouseleave', function(){
      b.style.setProperty('background', gold ? '#c9a84c' : '#fff', 'important');
      b.style.setProperty('color', gold ? '#fff' : '#1a1a1a', 'important');
    });
    b.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      if (orig && typeof orig.click === 'function') orig.click();
    });
    return b;
  }

  var KINDS = [
    ['cart',    'Add to cart',      '.add-to-cart-btn, a.add_to_cart_button, [class*="add-to-cart"], [class*="add_to_cart"]', true],
    ['compare', 'Compare',          '[class*="compare"]', false],
    ['quick',   'Quick view',       '[class*="quick-view"], [class*="quickview"], [class*="quick_view"], .view-btn, [data-quick-view]', false],
    ['wish',    'Add to wishlist',  '[class*="wishlist"], [class*="wcwl"], [class*="wl-btn"]', false]
  ];

  // Incremental on purpose: the theme injects wishlist and quick view only when
  // a card is first hovered, so a card that has just cart and compare at load
  // must be able to gain the other two later. Each pass adds whatever kinds are
  // now present and have not been added yet, keeping the row in a fixed order.
  function arrangeActions(sec){
    sec.querySelectorAll('li.product').forEach(function(li){
      var firstImg = li.querySelector('img');
      var imgHost  = firstImg ? firstImg.parentElement : null;
      if (!imgHost) return;

      var bar = li.querySelector(':scope > .af-card-actions')
             || (imgHost.querySelector(':scope > .af-card-actions'));
      var holder = li.querySelector('.af-card-orig');
      var have = (li.dataset.afKinds || '').split(',').filter(Boolean);

      var pending = KINDS.filter(function(k){ return have.indexOf(k[0]) === -1; });
      if (!pending.length) return;

      var additions = [];
      pending.forEach(function(k){
        var el = findControl(li, k[2]);
        if (el) additions.push([k, el]);
      });
      if (!additions.length) return;

      if (!bar) {
        bar = document.createElement('div');
        bar.className = 'af-card-actions';
        [['position','absolute'],['left','0'],['right','0'],['bottom','10px'],
         ['display','flex'],['justify-content','center'],['align-items','center'],
         ['gap','8px'],['z-index','7'],
         // hidden until the card is hovered — every action appears together
         ['opacity','0'],['pointer-events','none'],['transition','opacity .25s ease']
        ].forEach(function(p){ bar.style.setProperty(p[0], p[1], 'important'); });

        var target = imgHost.closest('a') ? li : imgHost;
        target.style.setProperty('position', 'relative', 'important');
        if (target === li) {
          var h = imgHost.getBoundingClientRect().height ||
                  ((window.innerWidth <= 520) ? 260 : 300);
          bar.style.setProperty('bottom', 'auto', 'important');
          bar.style.setProperty('top', Math.max(0, Math.round(h - 52)) + 'px', 'important');
        }
        target.appendChild(bar);

        li.addEventListener('mouseenter', function(){
          bar.style.setProperty('opacity', '1', 'important');
          bar.style.setProperty('pointer-events', 'auto', 'important');
        });
        li.addEventListener('mouseleave', function(){
          bar.style.setProperty('opacity', '0', 'important');
          bar.style.setProperty('pointer-events', 'none', 'important');
        });
      }
      if (!holder) {
        holder = document.createElement('div');
        holder.className = 'af-card-orig';
        [['position','absolute'],['width','1px'],['height','1px'],['overflow','hidden'],
         ['opacity','0'],['pointer-events','none'],['left','-9999px'],['top','0']
        ].forEach(function(p){ holder.style.setProperty(p[0], p[1], 'important'); });
        li.appendChild(holder);
      }

      additions.forEach(function(pair){
        var k = pair[0], el = pair[1];
        bar.appendChild(makeBtn(k[0], k[1], el, k[3]));
        var home = el.parentElement;
        holder.appendChild(el);
        if (home && home !== holder && !home.querySelector('img, svg, a, button')
            && (home.textContent || '').trim() === '') {
          home.style.setProperty('display', 'none', 'important');
        }
        have.push(k[0]);
      });
      li.dataset.afKinds = have.join(',');

      // Any control the theme leaves floating over the artwork after our row
      // exists would duplicate it — park those too, so the card shows exactly
      // one set of actions and only on hover.
      KINDS.forEach(function(k){
        var stray = findControl(li, k[2]);
        if (stray && !stray.closest('.af-card-orig') && !stray.closest('.af-card-actions')) {
          holder.appendChild(stray);
        }
      });

      // keep the fixed order regardless of when each kind arrived
      var order = {cart: 1, compare: 2, quick: 3, wish: 4};
      Array.prototype.slice.call(bar.children)
        .sort(function(a, b){
          function rank(el){
            var m = (el.className || '').match(/af-ca-(\w+)/);
            return m ? (order[m[1]] || 9) : 9;
          }
          return rank(a) - rank(b);
        })
        .forEach(function(el){ bar.appendChild(el); });
    });
  }

  // Some controls are injected by the theme only when a card is first hovered,
  // so one pass at load can miss them. Re-collect on hover and on DOM changes.
  function watchCards(sec){
    if (sec.dataset.afActionWatch) return;
    sec.dataset.afActionWatch = '1';
    sec.querySelectorAll('li.product').forEach(function(li){
      // run on the FIRST hover too: that is when the theme injects wishlist
      // and quick view, and the row must pick them up straight away
      li.addEventListener('mouseenter', function(){
        arrangeActions(sec);
        setTimeout(function(){ arrangeActions(sec); }, 60);
      });
    });
    try {
      new MutationObserver(function(){ arrangeActions(sec); })
        .observe(sec, {childList: true, subtree: true});
    } catch(e){}
  }

  function build(){
    document.querySelectorAll('.af-wl-related').forEach(function(sec){
      var ul = sec.querySelector('ul.products');
      if (!ul || sec.dataset.afRelSlider) return;
      sec.dataset.afRelSlider = '1';

      var vp = document.createElement('div');
      vp.className = 'af-rel-vp';
      ul.parentNode.insertBefore(vp, ul);
      vp.appendChild(ul);

      // one scrolling line instead of a wrapping grid
      ul.style.setProperty('display', 'flex', 'important');
      ul.style.setProperty('flex-wrap', 'nowrap', 'important');
      ul.style.setProperty('gap', GAP + 'px', 'important');
      ul.style.setProperty('overflow-x', 'auto', 'important');
      ul.style.setProperty('scroll-behavior', 'smooth', 'important');
      ul.style.setProperty('scroll-snap-type', 'x mandatory', 'important');
      ul.style.setProperty('margin', '0', 'important');
      ul.style.setProperty('padding', '0', 'important');
      ul.style.setProperty('list-style', 'none', 'important');
      sizeCards(ul);
      fillImages(sec);
      arrangeActions(sec);
      watchCards(sec);

      var prev = document.createElement('button');
      var next = document.createElement('button');
      prev.className = 'af-rel-nav af-rel-prev';
      next.className = 'af-rel-nav af-rel-next';
      prev.type = next.type = 'button';
      prev.setAttribute('aria-label', 'Previous products');
      next.setAttribute('aria-label', 'Next products');
      prev.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
      next.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
      vp.appendChild(prev);
      vp.appendChild(next);

      // The chevrons rendered blank: this theme has global button rules that
      // flatten inline svg inside buttons (the same fault that once produced
      // icon-less black circles on the chat launcher). Inline properties beat
      // those rules; and if the svg still measures zero after paint, swap in a
      // text chevron, which no button rule can shrink away.
      function forceIcon(btn, chr){
        btn.style.setProperty('color', '#fff', 'important');
        btn.style.setProperty('padding', '0', 'important');
        btn.style.setProperty('overflow', 'visible', 'important');
        btn.style.setProperty('line-height', '1', 'important');
        btn.style.setProperty('font-size', '26px', 'important');
        var svg = btn.querySelector('svg');
        if (svg) {
          svg.setAttribute('width', '20');
          svg.setAttribute('height', '20');
          svg.style.setProperty('width', '20px', 'important');
          svg.style.setProperty('height', '20px', 'important');
          svg.style.setProperty('display', 'block', 'important');
          svg.style.setProperty('fill', 'none', 'important');
          svg.style.setProperty('stroke', 'currentColor', 'important');
          svg.style.setProperty('stroke-width', '2.5', 'important');
          svg.style.setProperty('opacity', '1', 'important');
          svg.style.setProperty('visibility', 'visible', 'important');
        }
        setTimeout(function(){
          var g = btn.querySelector('svg');
          var w = g ? g.getBoundingClientRect().width : 0;
          if (w < 4) {
            btn.textContent = chr;
            btn.style.setProperty('font-weight', '700', 'important');
            btn.style.setProperty('font-family', 'system-ui, -apple-system, Segoe UI, sans-serif', 'important');
          }
        }, 300);
      }
      forceIcon(prev, '\u2039');
      forceIcon(next, '\u203A');

      function step(){
        var card = ul.querySelector('li.product');
        return card ? (card.getBoundingClientRect().width + GAP) : ul.clientWidth;
      }
      prev.addEventListener('click', function(){ ul.scrollBy({left: -step(), behavior: 'smooth'}); });
      next.addEventListener('click', function(){ ul.scrollBy({left:  step(), behavior: 'smooth'}); });

      function paint(){
        var max = ul.scrollWidth - ul.clientWidth - 2;
        prev.hidden = ul.scrollLeft <= 2;
        next.hidden = ul.scrollLeft >= max;
      }
      ul.addEventListener('scroll', paint, {passive: true});
      paint();
      var rt;
      window.addEventListener('resize', function(){
        clearTimeout(rt);
        rt = setTimeout(function(){ sizeCards(ul); fillImages(sec); paint(); }, 180);
      });
    });
  }
  function report(){
    if (location.search.indexOf('af_debug_cards=1') === -1) return;
    var box = document.getElementById('af-carddbg');
    if (!box) {
      box = document.createElement('div');
      box.id = 'af-carddbg';
      box.style.cssText = 'position:fixed;left:10px;bottom:10px;z-index:99999;background:#111;color:#9f9;'
        + 'font:12px/1.5 monospace;padding:10px 14px;border-radius:8px;max-width:520px;max-height:45vh;overflow:auto';
      document.body.appendChild(box);
    }
    var out = [];
    document.querySelectorAll('.af-wl-related li.product').forEach(function(li, i){
      if (i > 2) return;
      var found = [];
      li.querySelectorAll('a,button').forEach(function(el){
        var c = (el.className || '').toString().slice(0, 46);
        if (/cart|compare|quick|wish|wcwl|view/i.test(c)) found.push(el.tagName.toLowerCase() + '.' + c);
      });
      out.push('card ' + (i + 1) + ' row:' + (li.dataset.afActionRow || 'no') + '<br>&nbsp;&nbsp;' + (found.join('<br>&nbsp;&nbsp;') || 'none'));
    });
    box.innerHTML = out.join('<br>');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build);
  else build();
  window.addEventListener('load', build);
  [500, 1500, 3000].forEach(function(d){ setTimeout(build, d); });
  [1200, 3200].forEach(function(d){ setTimeout(report, d); });
  // images arrive lazily; re-fit after they land
  [800, 2000, 3500].forEach(function(d){
    setTimeout(function(){
      document.querySelectorAll('.af-wl-related').forEach(function(sec){
        fillImages(sec); arrangeActions(sec);
      });
    }, d);
  });
})();
