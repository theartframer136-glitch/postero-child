(function(){
  var g = document.getElementById('af-kit-group');
  if (!g) return;
  g.addEventListener('change', function(e){
    if (!e.target.matches('input[name="af_kit"]')) return;
    g.querySelectorAll('.af-kit-item').forEach(function(l){ l.classList.remove('is-on'); });
    var item = e.target.closest('.af-kit-item');
    if (item) item.classList.add('is-on');
    // the frame choice is only meaningful when a frame is actually included
    var frameGroup = document.querySelector('.af-opt-group-frame, [data-af-opt="frame"]');
    if (frameGroup) {
      var needsFrame = e.target.value === 'painting_bar_frame';
      frameGroup.style.opacity = needsFrame ? '1' : '';
    }
    document.dispatchEvent(new CustomEvent('af:kit-changed', {detail:{kit: e.target.value}}));
    setTimeout(function(){ frameFor(e.target.value); digital(e.target.value === 'digital'); }, 60);
  });

  // Digital download: the price is the file's (the same figure the Digital
  // Download modal and the cart charge), and size and frame are hidden.
  // Any other choice puts them back and re-prices from the chosen size.
  var wasDigital = false;
  function digital(on){
    document.body.classList.toggle('af-kit-digital', on);
    if (!on) {
      // re-price from the chosen size: leaving the file, or moving between
      // options that do and don't include stretcher bars
      var sel = document.getElementById('af-size-select');
      if (sel) sel.dispatchEvent(new Event('change', {bubbles:true}));
      wasDigital = false;
      return;
    }
    wasDigital = true;
    var now = parseFloat(g.getAttribute('data-dd-now')), was = parseFloat(g.getAttribute('data-dd-was'));
    if (!(now > 0)) return;
    var live = document.getElementById('af-live-price');
    var sym  = live ? ((live.textContent || '').match(/^[^0-9]*/) || ['$'])[0] || '$' : '$';
    var fmt  = function(v){ return sym + v.toFixed(2); };
    var pct  = was > now ? Math.round((was - now) / was * 100) : 0;
    if (live) live.innerHTML = '<span class="amount">' + fmt(now) + '</span>';
    var mrp = document.getElementById('af-live-mrp'), disc = document.getElementById('af-live-disc');
    if (mrp)  mrp.textContent  = was > now ? fmt(was) : '';
    if (disc) disc.textContent = pct > 0 ? '(' + pct + '% OFF)' : '';
    var head = document.querySelector('.summary .price, .entry-summary .price, p.price');
    if (head) {
      var ins = head.querySelector('ins'), del = head.querySelector('del');
      var cur = (ins || head).querySelector('.woocommerce-Price-amount, .amount');
      if (cur) cur.innerHTML = fmt(now);
      var old = del ? del.querySelector('.woocommerce-Price-amount, .amount') : null;
      if (old) old.innerHTML = fmt(was);
      var p = head.querySelector('.af-pct-off');
      if (p) p.textContent = pct > 0 ? '(' + pct + '% off)' : '';
      head.querySelectorAll('.screen-reader-text').forEach(function(sr){
        if (/original price/i.test(sr.textContent)) sr.textContent = 'Original price was: ' + fmt(was) + '.';
        else if (/current price/i.test(sr.textContent)) sr.textContent = 'Current price is: ' + fmt(now) + '.';
      });
    }
  }
  // Only "Painting + structure bars + frame + DIY kit" has a frame in it.
  // Painting only, and painting on bars, arrive rolled: no Frame Type, no
  // Frame Color, and the price is the unframed one (the cart enforces the
  // same). The frame the visitor had picked comes back if they return to the
  // framed option.
  var FRAMED = 'painting_bar_frame', keptFrame = null;
  function frameGroups(){
    var opts = document.querySelector('.af-opts'); if (!opts) return;
    opts.querySelectorAll('.af-opt-group').forEach(function(gr){
      if (gr.querySelector('.af-frame-chips')) gr.classList.add('af-grp-frame');
      if (gr.querySelector('.af-color-chips')) gr.classList.add('af-grp-color');
    });
    return opts;
  }
  function frameFor(kit){
    var opts = frameGroups(); if (!opts) return;
    var noFrame = kit === 'painting' || kit === 'painting_bar';
    document.body.classList.toggle('af-kit-noframe', noFrame);
    var active = opts.querySelector('.af-frame-chips .af-chip-opt.active');
    var cur = active ? active.getAttribute('data-val') : '';
    if (noFrame) {
      if (cur && cur !== 'Without Frame') {
        keptFrame = cur;
        var none = opts.querySelector('.af-frame-chips .af-chip-opt[data-val="Without Frame"]');
        if (none) none.click();
      }
    } else if (kit === FRAMED && cur === 'Without Frame') {
      // A framed kit has a frame in it. Bring back the one the visitor had
      // picked, else the first frame in stock — "+ frame" is never frameless.
      var want = keptFrame || g.getAttribute('data-frame-real') || '';
      var back = want ? opts.querySelector('.af-frame-chips .af-chip-opt[data-val="' + want + '"]:not([disabled])') : null;
      if (!back) back = opts.querySelector('.af-frame-chips .af-chip-opt:not([disabled]):not([data-val="Without Frame"])');
      if (back) back.click();
      keptFrame = null;
    }
  }
  // Choosing Without Frame while on the framed kit is choosing the kit
  // without its frame: move to that kit, so the price and the parcel agree.
  document.addEventListener('click', function(e){
    var chip = e.target.closest ? e.target.closest('.af-frame-chips .af-chip-opt[data-val="Without Frame"]') : null;
    if (!chip || chip.disabled) return;
    var on = g.querySelector('input[name="af_kit"]:checked');
    if (!on || on.value !== FRAMED) return;
    var bars = g.querySelector('input[name="af_kit"][value="painting_bar"]');
    if (!bars) return;
    bars.checked = true;
    bars.dispatchEvent(new Event('change', {bubbles:true}));
  });
  // On arrival: a frame already chosen elsewhere (Try On Wall passes one in
  // the address) means the visitor wants it framed, so open on the framed
  // option instead of silently dropping their frame.
  function startUp(){
    // The page always opens on the shop's own default (Painting only).
    // Browsers restore a radio choice on reload or Back, so a visitor who
    // had picked Digital download reopened the page at $9.99 with size and
    // frame hidden, and no choice made on this visit (owner's recording,
    // 29 Sep). Put every option back to how the page was built.
    g.querySelectorAll('input[name="af_kit"]').forEach(function(r){
      r.checked = r.defaultChecked;
      var it = r.closest('.af-kit-item'); if (it) it.classList.toggle('is-on', r.defaultChecked);
    });
    var start = g.querySelector('input[name="af_kit"]:checked');
    var opts = frameGroups();
    var active = opts ? opts.querySelector('.af-frame-chips .af-chip-opt.active') : null;
    if (start && start.value !== FRAMED && start.value !== 'digital' && active
        && active.getAttribute('data-val') !== 'Without Frame') {
      var framed = g.querySelector('input[name="af_kit"][value="' + FRAMED + '"]');
      if (framed) { framed.checked = true; framed.dispatchEvent(new Event('change', {bubbles:true})); return; }
    }
    if (start) { frameFor(start.value); if (start.value === 'digital') digital(true); }
  }
  if (document.readyState === 'complete') setTimeout(startUp, 300);
  else window.addEventListener('load', function(){ setTimeout(startUp, 300); });
})();
