(function(){
  var w = document.getElementById('afCty');
  if (!w || w.dataset.bound) return;
  w.dataset.bound = '1';
  var btn = w.querySelector('.af-cty-btn');
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    var open = w.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function(){ w.classList.remove('open'); });
  w.querySelectorAll('.af-cty-item').forEach(function(b){
    b.addEventListener('click', function(){
      document.cookie = 'af_country=' + b.dataset.code + '; path=/; max-age=' + (86400*365);
      var u = new URL(location.href);
      u.searchParams.set('currency', b.dataset.cur);
      location.href = u.toString();
    });
  });

  // Sit the selector in the header contact row, between the phone number and
  // the Facebook icon. That row is theme markup, so the node is moved into it
  // (the listeners above survive the move). Anchoring on the phone number is
  // far more reliable than the social icon's href, and it lands the selector
  // exactly where it belongs. Falls back to the social icons, then leaves the
  // selector hidden rather than dropping it somewhere wrong.
  function inFooter(el){
    return !!(el.closest && el.closest('footer, .site-footer, #footer, .elementor-location-footer, .footer'));
  }
  function put(anchor, where){
    var host = (anchor.closest && anchor.closest('.elementor-widget, li')) || anchor;
    if (!host.parentNode || host === w || host.contains(w)) return false;
    host.parentNode.insertBefore(w, where === 'after' ? host.nextSibling : host);
    w.dataset.moved = '1';
    w.classList.add('af-cty--inline');
    // Keep the contact row on ONE clean line and pack its items together
    // instead of letting them spread edge-to-edge (which was forcing the email
    // and phone text to wrap). Apply to the flex ancestors that hold the row.
    // The row's spacing in three numbers: GAP between items, PAD inside each
    // one, SEP before a contact detail. PAD matters as much as GAP — the
    // theme's own padding sits inside each item, where no gap value reaches
    // it, which is why passes that only touched GAP appeared to do nothing.
    // Tune here.
    var GAP = '10px', PAD = '4px', SEP = '18px';
    var p = w.parentElement, hops = 0;
    while (p && hops < 4) {
      var cs = window.getComputedStyle(p);
      if (cs.display === 'flex' || cs.display === 'inline-flex') {
        // ONE line, and one that fits. Wrapping put the tail on a second row
        // and the row read as two half-rows; nowrap on its own overflowed or
        // got clipped. Neither is what this bar should do — so it stays on one
        // line and afFitRow() below shrinks it until it fits the page.
        p.style.setProperty('flex-wrap', 'nowrap', 'important');
        p.style.setProperty('align-items', 'center', 'important');
        p.style.setProperty('overflow', 'visible', 'important');
        // Pack from the start rather than spreading edge to edge. Centring
        // was how the social icons first left the page: a centred row that
        // runs wide spills past BOTH edges, and the left-hand spill cannot be
        // scrolled back to.
        if (/space-|center/.test(cs.justifyContent)) {
          p.style.setProperty('justify-content', 'flex-start', 'important');
        }
        // One gap for the whole row rather than a conditional range, so the
        // items sit at even distances instead of keeping whatever each
        // ancestor started with.
        p.style.setProperty('column-gap', GAP, 'important');
        p.style.setProperty('gap', GAP, 'important');
        // The gap alone barely moved these items, because it is not what was
        // holding them apart: each one carries the theme's own horizontal
        // PADDING, and padding sits inside the item, so no gap value can
        // shrink it. Margin and padding both have to come down for the row to
        // actually close up.
        for (var m = 0; m < p.children.length; m++) {
          var it = p.children[m];
          it.style.setProperty('margin-left', '0', 'important');
          it.style.setProperty('margin-right', '0', 'important');
          it.style.setProperty('padding-left', PAD, 'important');
          it.style.setProperty('padding-right', PAD, 'important');
          // …and on the link inside it, which usually carries the padding
          var ins = it.querySelectorAll('a,.elementor-widget-container,.menu-item');
          for (var r = 0; r < ins.length; r++) {
            ins[r].style.setProperty('padding-left', PAD, 'important');
            ins[r].style.setProperty('padding-right', PAD, 'important');
            ins[r].style.setProperty('margin-left', '0', 'important');
            ins[r].style.setProperty('margin-right', '0', 'important');
          }
          // The nav links want to sit close together; the contact details do
          // NOT. "My account" hard against an email address reads as one long
          // string, which is what made that pair look broken even once they
          // stopped overlapping. Give anything that is a mailto: or tel: its
          // own breathing room, so the row is tight where it should be tight
          // and separated where the meaning changes.
          if (it.querySelector('a[href^="mailto:"], a[href^="tel:"]') ||
              /^(mailto|tel):/.test(it.getAttribute('href') || '')) {
            it.style.setProperty('margin-left', SEP, 'important');
          }
        }
        for (var k = 0; k < p.children.length; k++) {
          var ch = p.children[k];
          // white-space is inherited, so this stops the email/phone text inside
          // each item from breaking onto two lines
          ch.style.setProperty('white-space', 'nowrap', 'important');
          // Tracked-out uppercase (MY ACCOUNT) is wider than the box the theme
          // measured for it, so the tail of one item printed on top of the
          // next. Normal spacing costs a little style and buys a row that
          // reads; the items keep their own size and stop being squeezed.
          ch.style.setProperty('letter-spacing', 'normal', 'important');
          ch.style.setProperty('word-spacing', 'normal', 'important');
          // shrinkable, so a tight window narrows the row instead of
          // pushing its last items out of the page
          ch.style.setProperty('flex', '0 1 auto', 'important');
          ch.style.setProperty('min-width', '0', 'important');
          var kids = ch.querySelectorAll('a,span,p,div,strong,em');
          for (var q = 0; q < kids.length; q++) {
            kids[q].style.setProperty('letter-spacing', 'normal', 'important');
            kids[q].style.setProperty('word-spacing', 'normal', 'important');
          }
        }
      }
      p = p.parentElement; hops++;
    }
    afFitRow(afRowOf(w));
    return true;
  }

  // The flex row that actually holds these items.
  function afRowOf(el){
    var n = el && el.parentElement, guard = 0;
    while (n && guard < 4) {
      var d = window.getComputedStyle(n).display;
      if (d === 'flex' || d === 'inline-flex') return n;
      n = n.parentElement; guard++;
    }
    return null;
  }

  // Make the row fit its container on ONE line, by measuring rather than
  // guessing. Every fixed set of numbers tried here was wrong at some window
  // width — too loose and the tail left the page, too tight and the row was
  // unreadable at widths where it did not need to be. So: start at a
  // comfortable spacing, and give ground only while the row is still too wide,
  // cheapest thing first — gap, then padding, then type size, with a floor so
  // it can never shrink to unreadable.
  function afFitRow(row){
    if (!row || !row.clientWidth) return;   // not rendered yet — measuring it
                                            // would only prove it "fits"

    // Start every pass from the top of the ladder, not from wherever the last
    // pass stopped. This routine only ever gives ground, so without a reset it
    // is a one-way trip: a phone-width pass wraps the row, and a wrapped row
    // can never overflow, so the next pass at desktop width measures "fits"
    // and returns — the header stays wrapped and small at a width that had
    // room to spare. Undo the previous pass first, then re-measure.
    row.style.removeProperty('flex-wrap');
    row.style.removeProperty('row-gap');
    row.style.setProperty('flex-wrap', 'nowrap', 'important');
    // Only take back the type size if WE set it — the theme puts inline sizes
    // on some of these elements and wiping those would be a different bug.
    // Our own marks are unmistakable: the flag on the row, and 'inherit' on
    // the descendants, which is the only value this routine ever writes there.
    if (row.getAttribute('data-af-fit-size') === '1') {
      row.style.removeProperty('font-size');
      row.removeAttribute('data-af-fit-size');
    }
    var prev = row.querySelectorAll('a,span,p,div,strong,em,li');
    for (var r = 0; r < prev.length; r++) {
      if (prev[r].style.fontSize === 'inherit') prev[r].style.removeProperty('font-size');
    }

    var gap = 10, pad = 4, size = 0;      // size 0 = leave the theme's own
    function apply(){
      row.style.setProperty('column-gap', gap + 'px', 'important');
      row.style.setProperty('gap', gap + 'px', 'important');
      for (var i = 0; i < row.children.length; i++) {
        var it = row.children[i];
        it.style.setProperty('padding-left', pad + 'px', 'important');
        it.style.setProperty('padding-right', pad + 'px', 'important');
        var ins = it.querySelectorAll('a,.elementor-widget-container,.menu-item');
        for (var j = 0; j < ins.length; j++) {
          ins[j].style.setProperty('padding-left', pad + 'px', 'important');
          ins[j].style.setProperty('padding-right', pad + 'px', 'important');
        }
      }
      if (size) {
        row.style.setProperty('font-size', size + 'px', 'important');
        row.setAttribute('data-af-fit-size', '1');
        var t = row.querySelectorAll('a,span,p,div,strong,em,li');
        for (var k = 0; k < t.length; k++) t[k].style.setProperty('font-size', 'inherit', 'important');
      }
    }
    function tooWide(){ return row.scrollWidth > row.clientWidth + 1; }

    apply();
    if (!tooWide()) return;               // it already fits — leave it alone

    var guard = 0;
    while (tooWide() && guard < 40) {
      if (gap > 2)            gap -= 1;
      else if (pad > 0)       pad -= 1;
      else if (!size)         size = 13;  // only now start on the type
      else if (size > 10)     size -= 0.5;
      else break;                          // floor: readable beats fitting
      apply();
      guard++;
    }

    // Narrow enough — a phone — and one line is simply not possible without
    // shrinking the text past reading. Wrap there instead: a second line is a
    // worse layout than one line, but it is a far better outcome than text at
    // 8px or a row running off the screen. One line where it fits, legible
    // everywhere.
    if (tooWide()) {
      row.style.setProperty('flex-wrap', 'wrap', 'important');
      row.style.setProperty('row-gap', '4px', 'important');
    }
  }

  // ── Re-fit whenever the answer could have changed ──────────────────
  // A single resize listener is not enough. The row is measured, so it has to
  // be re-measured every time the measurement could differ: a narrower window,
  // yes, but also web fonts arriving after first paint (they change every
  // width), a container that resizes without the window doing so (the sticky
  // header shrinking on scroll), a phone turning sideways, and the case that
  // silently did nothing before — being measured while hidden, where
  // clientWidth is 0 and the row looks like it fits when it has not rendered.
  var afFitT = null, afFitting = false;
  function afRefit(){
    var row = afRowOf(w);
    if (!row) return;
    if (!row.clientWidth) return;        // hidden: nothing meaningful to measure
    afFitting = true;                    // our own writes must not re-trigger us
    afFitRow(row);
    setTimeout(function(){ afFitting = false; }, 60);
  }
  function afRefitSoon(){
    if (afFitting) return;
    clearTimeout(afFitT);
    // Longer than the 60ms afRefit holds afFitting for, so a burst of resize
    // events settles into one measurement instead of a measurement per frame.
    afFitT = setTimeout(afRefit, 120);
  }

  // The events the note above lists, finally attached to something. Until this
  // commit afRefitSoon was never called — and its body was missing, which left
  // a brace unclosed and took THIS ENTIRE SCRIPT with it: the whole block
  // failed to parse, so the country selector rendered and did nothing when
  // clicked. Measured in a browser: "Unexpected token ')'", the widget never
  // marked bound, the dropdown never opening.
  window.addEventListener('resize', afRefitSoon);
  window.addEventListener('orientationchange', afRefitSoon);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(afRefitSoon);

  // A container can change width without the window doing so — the sticky
  // header shrinking on scroll — and a row measured while hidden reports
  // clientWidth 0, so becoming visible is a size change too. One observer on
  // the row catches both. Attached after the selector is placed, because that
  // is when the row exists; guarded so it is only ever attached once.
  var afRO = null;
  function afWatchRow(){
    if (afRO || typeof ResizeObserver === 'undefined') return;
    var row = afRowOf(w);
    if (!row) return;
    afRO = new ResizeObserver(function(){ afRefitSoon(); });
    afRO.observe(row);
  }

  // Deepest element in the header area whose text contains the phone number.
  function phoneEl(){
    var re = /470[\s .\-]?7280/;
    var all = document.body ? document.body.querySelectorAll('a,span,div,p,li,strong,em') : [];
    for (var i = 0; i < all.length; i++) {
      var el = all[i];
      if (inFooter(el)) continue;
      if (!re.test(el.textContent || '')) continue;
      var childHit = false;
      for (var j = 0; j < el.children.length; j++) {
        if (re.test(el.children[j].textContent || '')) { childHit = true; break; }
      }
      if (!childHit) return el;   // deepest match, header comes first in the DOM
    }
    return null;
  }
  var tries = 0;
  function relocate(){
    if (w.dataset.moved) return true;
    var i, links;
    // 1) right AFTER the phone number (matched by its text) → before the FB icon
    var pe = phoneEl();
    if (pe && put(pe, 'after')) return true;
    // 2) after a tel: link
    links = document.querySelectorAll('a[href^="tel:"]');
    for (i = 0; i < links.length; i++) { if (!inFooter(links[i]) && put(links[i], 'after')) return true; }
    // 3) before the first social icon
    links = document.querySelectorAll('a[href*="facebook"],a[href*="instagram"],a[aria-label*="Facebook" i],a[aria-label*="Instagram" i]');
    for (i = 0; i < links.length; i++) { if (!inFooter(links[i]) && put(links[i], 'before')) return true; }
    // 4) after the email address
    links = document.querySelectorAll('a[href^="mailto:"]');
    for (i = 0; i < links.length; i++) { if (!inFooter(links[i]) && put(links[i], 'after')) return true; }
    return false;
  }
  // Last resort: never let the selector stay hidden. If it can't be placed in
  // the contact row after several tries, show it fixed in the top-right corner
  // so currency switching is always reachable.
  function ensureVisible(){
    if (w.dataset.moved) return;
    w.dataset.moved = '1';
    w.classList.add('af-cty--inline', 'af-cty--fallback');
    document.body.appendChild(w);
  }
  function tick(){
    if (relocate()) { afWatchRow(); return; }
    if (++tries >= 12) ensureVisible();
  }
  document.addEventListener('DOMContentLoaded', tick);
  window.addEventListener('load', tick);
  [300, 700, 1200, 1800, 2600, 3600, 5000].forEach(function(d){ setTimeout(tick, d); });
  try {
    var mo = new MutationObserver(function(){
      if (w.dataset.moved) { mo.disconnect(); return; }
      relocate();
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) {}
})();
