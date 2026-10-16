(function() {
  // This used to be `if (window.innerWidth > 768) return;` - one decision,
  // taken at load, and then the whole block, its resize listener included,
  // did not exist. Measured on the live homepage: a phone that LOADS at
  // 423px gets its hero (the mobile widget lays out at 423x190); a window
  // that loads wide and is then narrowed - a tablet turned sideways, a
  // split screen, the owner's DevTools recording - gets nothing at all. The
  // mobile widget's Swiper had initialised while its container was
  // display:none, its slide widths were meaningless, and the one thing that
  // would have called swiper.update() had declined to load.
  //
  // So the width is asked every time the fix runs, and the fix is
  // symmetrical: below the breakpoint it applies, above it every inline
  // property it wrote is taken back and Swiper is told to re-measure, so a
  // window that comes back up does not keep 100vw slides in a desktop hero.
  var MOBILE = 768;
  function isPhone() { return window.innerWidth <= MOBILE; }
  // Every inline write goes through here and is remembered on the element,
  // so undo can remove exactly what was set and nothing else.
  var touched = [];
  function sp(el, p, v) {
    el.style.setProperty(p, v, 'important');
    if (!el.__afHeroProps) el.__afHeroProps = {};
    el.__afHeroProps[p] = 1;
    if (touched.indexOf(el) === -1) touched.push(el);
  }

  // How tall is a slide? The shape of the picture in it — never a number
  // picked in advance.
  //
  // This block used to stamp min-height:420px and background-size:cover onto
  // every slide as an INLINE important style, which beats any stylesheet no
  // matter what it says. So the fault could not be corrected in CSS at all,
  // and the owner kept seeing the same thing after each "fix": the Google
  // review banners are 2.23:1, their whole width at 390px is 175px tall, and
  // forcing 420px of height out of them scaled each one 2.4x until only the
  // middle — one star — was still inside the box.
  //
  // So measure the picture and give it a box its own shape. Contain never
  // crops; a box the right shape leaves no empty bands. Until the image has
  // loaded, 420 stays, so a slider can never collapse to nothing.
  var slideRatio = 0;
  function slideH() {
    return slideRatio ? Math.round(window.innerWidth / slideRatio) : 420;
  }
  function withSlideRatio(next) {
    if (slideRatio) { next(); return; }
    // Read the COMPUTED background, not the style attribute. Elementor sets a
    // slide's background-image from its generated stylesheet, so the inline
    // style has no url in it at all — which is why the first version of this
    // measured nothing, kept the 420px default, and the owner quite correctly
    // said it was still not fixed.
    var bg = null, url = '';
    // There are TWO hero widgets, one per breakpoint, and they hold different
    // pictures: the desktop one 2.23:1 landscape banners, the phone one
    // 414x896 portrait banners. This used to take the first background in
    // the DOM - the hidden desktop widget's - so the phone's portrait
    // banners were fitted into a 190px landscape box and painted as an 88px
    // sliver: 97% of the hero was white. Measure the widget that is laid out.
    var all = document.querySelectorAll('.elementor-slides .swiper-slide-bg, .elementor-widget-slides .swiper-slide-bg');
    all = Array.prototype.filter.call(all, function (el) {
      var w = el.closest('.elementor-widget-slides, .elementor-slides-wrapper');
      return !w || w.getBoundingClientRect().width > 0;
    });
    for (var i = 0; i < all.length && !url; i++) {
      var cand = getComputedStyle(all[i]).backgroundImage
        || (all[i].getAttribute('style') || '');
      var mm = cand.match(/url\(\s*['"]?([^'")]+)/i);
      if (mm) { bg = all[i]; url = mm[1]; }
    }
    var m = url ? [null, url] : null;
    if (!m) { next(); return; }
    var im = new Image();
    im.onload = function () {
      if (im.naturalWidth && im.naturalHeight) slideRatio = im.naturalWidth / im.naturalHeight;
      next();
    };
    im.onerror = next;
    im.src = m[1];
  }

  function fixContainers() {
    // Fix Elementor lazy-load: mark all e-con ancestors of any slider as loaded
    // so the rule `.e-con:nth-of-type(n+3):not(.e-lazyloaded) * { background-image:none!important }` stops firing
    document.querySelectorAll('.elementor-slides, .elementor-slides-wrapper, .elementor-widget-slides').forEach(function(el) {
      var node = el.parentElement;
      while (node && node !== document.body) {
        if (node.classList) {
          node.classList.add('e-lazyloaded');
          node.classList.remove('e-lazyloading');
        }
        node = node.parentElement;
      }
    });

    // Also force background-image via inline important on every slide-bg
    // (reads the CSS variable or computed inline style set by Elementor)
    document.querySelectorAll('.swiper-slide-bg').forEach(function(bg) {
      var inlineBg = bg.getAttribute('style') || '';
      var match = inlineBg.match(/background-image\s*:\s*(url\([^)]+\))/i);
      if (match) {
        bg.style.setProperty('background-image', match[1], 'important');
      }
    });

    document.querySelectorAll('.elementor-widget-slides, .elementor-slides-wrapper').forEach(function(w) {
      sp(w, 'width', '100vw'); sp(w, 'max-width', '100vw');
      sp(w, 'padding', '0');   sp(w, 'margin', '0'); sp(w, 'overflow', 'hidden');
      var el = w.parentElement;
      for (var i = 0; i < 5 && el && el.tagName !== 'BODY'; i++) {
        sp(el, 'padding-left', '0'); sp(el, 'padding-right', '0');
        sp(el, 'max-width', '100vw'); sp(el, 'overflow', 'hidden');
        el = el.parentElement;
      }
    });
    document.querySelectorAll(
      '.elementor-slides-wrapper,' +
      '.elementor-widget-slides .swiper,' +
      '.elementor-widget-slides .swiper-container'
    ).forEach(function(c) {
      sp(c, 'width', '100vw'); sp(c, 'max-width', '100vw'); sp(c, 'overflow', 'hidden');
    });
    // The track must NOT clip: it is translated off screen to bring a slide
    // on, so overflow:hidden on it hides the very slide being shown (see the
    // stylesheet note). Said inline so no rule can put it back.
    document.querySelectorAll('.elementor-widget-slides .swiper-wrapper').forEach(function(t) {
      sp(t, 'overflow', 'visible');
    });
  }

  function fixSwiperAPI() {
    // Tell Swiper to recalculate sizes using the (now correct) container width
    var swiperEls = document.querySelectorAll(
      '.elementor-slides-wrapper,' +
      '.elementor-widget-slides .swiper,' +
      '.elementor-widget-slides .swiper-container'
    );
    swiperEls.forEach(function(el) {
      var swiper = el.swiper;
      if (swiper && typeof swiper.update === 'function') {
        swiper.params.width  = null; // clear fixed width so it recalculates
        swiper.params.height = null;
        swiper.update();
      }
    });
  }

  function fixSlideInlineWidths() {
    document.querySelectorAll('.elementor-slides .swiper-slide, .elementor-widget-slides .swiper-slide').forEach(function(s) {
      sp(s, 'width',      '100vw'); sp(s, 'min-width',  '100vw');
      sp(s, 'max-width',  '100vw'); sp(s, 'flex-shrink','0');
      sp(s, 'min-height', slideH() + 'px'); sp(s, 'position',   'relative');
      sp(s, 'overflow',   'hidden'); sp(s, 'box-sizing', 'border-box');
    });
    document.querySelectorAll('.elementor-slides .swiper-slide-bg, .elementor-widget-slides .swiper-slide-bg').forEach(function(bg) {
      sp(bg, 'position',            'absolute');
      sp(bg, 'top',    '0'); sp(bg, 'left',   '0');
      sp(bg, 'right',  '0'); sp(bg, 'bottom', '0');
      sp(bg, 'width',  '100%'); sp(bg, 'height', '100%');
      sp(bg, 'min-height',          slideH() + 'px');
      sp(bg, 'background-size',     'contain');
      sp(bg, 'background-repeat',   'no-repeat');
      sp(bg, 'background-position', 'center center');
      sp(bg, 'z-index',             '0');
    });
    document.querySelectorAll('.elementor-slides .swiper-slide-inner, .elementor-widget-slides .swiper-slide-inner').forEach(function(inner) {
      sp(inner, 'position',   'relative'); sp(inner, 'z-index',     '1');
      sp(inner, 'width',      '100%');     sp(inner, 'max-width',   '100%');
      sp(inner, 'min-height', slideH() + 'px');    sp(inner, 'box-sizing',  'border-box');
    });
  }

  var applied = false;
  function undoFix() {
    if (!applied) return;
    applied = false;
    touched.forEach(function(el) {
      var props = el.__afHeroProps || {};
      Object.keys(props).forEach(function(p) { el.style.removeProperty(p); });
      el.__afHeroProps = null;
    });
    touched = [];
    // Swiper re-measures against the desktop layout it now has back.
    fixSwiperAPI();
  }

  function fullFix() {
    if (!isPhone()) { undoFix(); return; }
    // Measure first: every rule below writes the height inline, so it has to
    // know the picture's shape before it writes anything.
    withSlideRatio(function () {
      if (!isPhone()) { undoFix(); return; }   // width changed while the image loaded
      fixContainers();
      fixSwiperAPI();
      fixSlideInlineWidths();
      applied = true;
    });
  }

  [300, 900, 2000].forEach(function(d) { setTimeout(fullFix, d); });
  // Resizes come in bursts; the last one is the one that matters.
  var resizeT = null;
  window.addEventListener('resize', function() {
    clearTimeout(resizeT);
    resizeT = setTimeout(fullFix, 150);
  });

  // MutationObserver: lock slide width to 100vw regardless of when Swiper rewrites it
  // Swiper uses setProperty('width', Xpx, 'important') which beats stylesheet !important,
  // but our observer fires immediately after and resets it.
  function attachSlideLock() {
    var slides = [];
    var mo = new MutationObserver(function(muts) {
      mo.disconnect();
      // Above the breakpoint this lock has no business forcing 100vw onto a
      // desktop hero; keep watching, change nothing.
      if (!isPhone()) {
        slides.forEach(function(s) { mo.observe(s, { attributes: true, attributeFilter: ['style'] }); });
        return;
      }
      var changed = false;
      muts.forEach(function(m) {
        var s = m.target;
        if (s.classList && s.classList.contains('swiper-slide')) {
          // Skip if already set to 100vw — prevents re-firing on our own mutation
          if (s.style.width === '100vw') return;
          changed = true;
          s.style.setProperty('width',      '100vw', 'important');
          s.style.setProperty('min-width',  '100vw', 'important');
          s.style.setProperty('max-width',  '100vw', 'important');
          s.style.setProperty('min-height', slideH() + 'px', 'important');
          s.style.setProperty('position',   'relative', 'important');
          s.style.setProperty('overflow',   'hidden', 'important');
        }
      });
      slides.forEach(function(s) { mo.observe(s, { attributes: true, attributeFilter: ['style'] }); });
    });
    slides = Array.from(document.querySelectorAll('.elementor-slides .swiper-slide, .elementor-widget-slides .swiper-slide'));
    if (!slides.length) return; // nothing to lock
    slides.forEach(function(s) { mo.observe(s, { attributes: true, attributeFilter: ['style'] }); });
  }
  // Attach once — two calls created two observers on same slides causing mutual infinite loop
  var _slideLockAttached = false;
  function tryAttachSlideLock() {
    if (_slideLockAttached) return;
    var sl = document.querySelectorAll('.elementor-slides .swiper-slide, .elementor-widget-slides .swiper-slide');
    if (!sl.length) return; // slides not ready yet
    _slideLockAttached = true;
    attachSlideLock();
  }
  setTimeout(tryAttachSlideLock, 500);
  setTimeout(tryAttachSlideLock, 1500);
  setTimeout(tryAttachSlideLock, 3000);
}());
