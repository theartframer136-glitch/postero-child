(function(){
  // The first version moved the gallery with translateY on every scroll frame.
  // Scroll paints first and the correction lands a frame later, which the eye
  // reads as the column JUMPING (owner recording, 2026-08-19). So no per-scroll
  // JS at all: the gallery column is stretched to end where the details column
  // ends, and a sticky wrapper INSIDE it pins the images while scrolling. The
  // browser's own compositor does the pinning — glassy smooth — and the sticky
  // range ends at the stretched column's bottom, which is exactly where the
  // Description / Reviews tabs begin.
  var TOP_GAP = 96;
  var g, s, inner;

  function pick(){
    g = document.querySelector('div.product .woocommerce-product-gallery');
    s = document.querySelector('div.product .summary.entry-summary, div.product .summary');
    return g && s;
  }
  function absTop(el){ var r = el.getBoundingClientRect(); return r.top + window.pageYOffset; }

  function wrapOnce(){
    if (inner) return;
    inner = document.createElement('div');
    inner.className = 'af-sg-inner';
    inner.style.setProperty('position', 'sticky');
    inner.style.setProperty('top', TOP_GAP + 'px');
    while (g.firstChild) inner.appendChild(g.firstChild);
    g.appendChild(inner);
  }

  // The gallery box is a flex container in this theme. That makes the wrapper a
  // flex item, and a flex item's default cross-axis behaviour is STRETCH: the
  // wrapper is pulled to the full height of the column we just stretched, so it
  // has no room left to slide in and sticky pins nothing. Measured in Chromium:
  //   flex gallery -> innerH 2000 of 2000, scroll 800 gives top -780 (not pinned)
  //   align-self:flex-start -> innerH 400 of 2000, scroll 800 gives top 96 (pinned)
  // Only the vertical stretch is cancelled. In a row flex container that is
  // align-self; the width is kept by letting the item grow along the row. In a
  // COLUMN container the axes are swapped -- align-self would shrink the width
  // instead -- so there we only make sure the item is not grown vertically.
  function unstretch(on){
    if (!inner) return;
    if (!on) {
      inner.style.removeProperty('align-self');
      inner.style.removeProperty('flex');
      return;
    }
    var cs = window.getComputedStyle(g);
    var d  = cs.display;
    if (d === 'flex' || d === 'inline-flex') {
      if ((cs.flexDirection || 'row').indexOf('column') === -1) {
        inner.style.setProperty('align-self', 'flex-start');
        inner.style.setProperty('flex', '1 1 auto');   // keep the full column width
      } else {
        inner.style.setProperty('flex', '0 0 auto');   // never grown down the column
      }
    } else if (d === 'grid' || d === 'inline-grid') {
      inner.style.setProperty('align-self', 'start');  // block axis only; width still stretches
    } else {
      inner.style.removeProperty('align-self');
      inner.style.removeProperty('flex');
    }
  }

  var sizing = false;
  function size(){
    if (!g || !s || sizing) return;
    sizing = true;
    if (window.innerWidth < 992) {              // stacked layout: no pinning
      g.style.height = '';
      if (inner) inner.style.position = 'static';
      unstretch(false);
      sizing = false;
      return;
    }
    if (inner) inner.style.position = 'sticky';
    unstretch(true);                            // before measuring: a stretched
                                                // wrapper reports the column's
                                                // height, not the images'
    g.style.height = '';                        // measure natural sizes first
    var innerH = inner ? inner.offsetHeight : g.offsetHeight;
    var target = absTop(s) + s.offsetHeight - absTop(g);
    if (target > innerH + 40) {
      g.style.height = Math.round(target) + 'px';
    }
    sizing = false;
  }

  // position:sticky silently does nothing if ANY ancestor clips overflow —
  // and theme wrappers often do. Walk up from the gallery and lift the clip on
  // those containers (recorded on the element so it is visible in devtools).
  // The gallery's own internals are left alone.
  // The walk below stops at <body> — but the two elements ABOVE it decide
  // whether anything can stick at all. When html AND body both set an overflow
  // (themes add overflow-x:hidden to both to kill a horizontal scrollbar),
  // body no longer propagates its value to the viewport and becomes a scroll
  // container in its own right. That container never scrolls, so a sticky
  // element inside it can never move. Reproduced in Chromium against the live
  // geometry: with both set the wrapper tracked the page 1:1 (top 300 - scrollY)
  // even with a 1020px wrapper inside a 2275px column.
  //
  // Simply clearing it would undo what the theme wanted: measured in Chromium,
  // an overflowing element then scrolls the page sideways again. So swap the
  // hidden for CLIP, which crops exactly the same but does NOT make the element
  // a scroll container -- the horizontal scrollbar stays gone and sticky works.
  // Only the horizontal axis is touched, and only where it was already hidden.
  function unclipRoot(){
    [document.documentElement, document.body].forEach(function(el){
      if (!el) return;
      var cs = window.getComputedStyle(el);
      if (cs.overflowX === 'hidden' && cs.overflowY !== 'hidden' && cs.overflowY !== 'scroll') {
        el.style.setProperty('overflow-x', 'clip', 'important');
        el.style.setProperty('overflow-y', 'visible', 'important');
        el.dataset.afSgRootClipped = '1';
      }
    });
  }

  function unclipAncestors(){
    unclipRoot();
    for (var a = g.parentElement; a && a !== document.body; a = a.parentElement) {
      var cs = window.getComputedStyle(a);
      if (cs.overflow !== 'visible' || cs.overflowY !== 'visible') {
        a.style.setProperty('overflow', 'visible', 'important');
        a.dataset.afSgUnclipped = '1';
      }
      // A transform / filter / perspective / paint-containment on an ancestor
      // makes it the sticky containing block, which quietly kills the pinning
      // just as surely as an overflow clip. Elementor's entrance animations
      // leave exactly these behind on wrapper containers. Clear them on plain
      // wrappers (marked for devtools); the gallery internals are untouched.
      var breaks = (cs.transform !== 'none') || (cs.filter && cs.filter !== 'none')
                || (cs.perspective && cs.perspective !== 'none')
                || (cs.contain && /paint|layout|content|strict/.test(cs.contain))
                || (cs.willChange && /transform|filter|perspective/.test(cs.willChange));
      if (breaks) {
        a.style.setProperty('transform', 'none', 'important');
        a.style.setProperty('filter', 'none', 'important');
        a.style.setProperty('perspective', 'none', 'important');
        a.style.setProperty('contain', 'none', 'important');
        a.style.setProperty('will-change', 'auto', 'important');
        a.dataset.afSgUnblocked = '1';
      }
    }
  }

  // Owner-only probe: /product-url/?af_debug_sticky=1 prints what the pinning
  // sees — a screenshot of this panel replaces another guessing round.
  function debugPanel(){
    if (location.search.indexOf('af_debug_sticky=1') === -1) return;
    var box = document.createElement('div');
    box.style.cssText = 'position:fixed;left:10px;bottom:10px;z-index:99999;background:#111;color:#9f9;'
      + 'font:12px/1.5 monospace;padding:10px 14px;border-radius:8px;max-width:480px;max-height:50vh;overflow:auto';
    var out = [];
    out.push('gallery: ' + (g ? 'found' : 'MISSING') + '  summary: ' + (s ? 'found' : 'MISSING'));
    if (inner) {
      var ics = getComputedStyle(inner);
      out.push('inner position: ' + ics.position + '  top: ' + ics.top);
    }
    if (g) out.push('gallery display: ' + getComputedStyle(g).display + '  inner align-self: ' + (inner ? getComputedStyle(inner).alignSelf : '-'));
    if (g) out.push('gallery height set: ' + (g.style.height || '(natural)') + '  innerH: ' + (inner ? inner.offsetHeight : '-'));
    if (s) out.push('summary height: ' + s.offsetHeight);
    if (g) {
      var n = 0;
      for (var a = g.parentElement; a && a !== document.body && n < 12; a = a.parentElement, n++) {
        var cs = getComputedStyle(a);
        out.push((a.className || a.tagName).toString().slice(0, 44)
          + ' | ov:' + cs.overflow + ' tf:' + (cs.transform === 'none' ? '-' : 'YES')
          + ' ft:' + (cs.filter === 'none' || !cs.filter ? '-' : 'YES')
          + (a.dataset.afSgUnclipped ? ' [unclipped]' : '')
          + (a.dataset.afSgUnblocked ? ' [unblocked]' : ''));
      }
    }
    box.innerHTML = out.join('<br>');
    document.body.appendChild(box);
  }

  function boot(){
    if (!pick()) return;
    wrapOnce();
    unclipAncestors();
    size();
    window.addEventListener('resize', size);
    g.querySelectorAll('img').forEach(function(im){ im.addEventListener('load', size); });
    try {
      new ResizeObserver(function(){ size(); }).observe(s);
    } catch(e){}
    debugPanel();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.addEventListener('load', boot);
  setTimeout(boot, 1200);
})();
