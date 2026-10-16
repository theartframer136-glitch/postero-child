(function(){
  var items = document.querySelectorAll('.af-motion-item');
  // (clones are added below and picked up by the same setup pass)
  if (!items.length) return;

  // Only what is on screen plays. Nine clips autoplaying at once would cost the
  // visitor's bandwidth and the server's, for tiles nobody is looking at.
  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      var v = e.target.querySelector('video');
      if (!v) return;
      if (e.isIntersecting){
        if (v.preload === 'none') v.preload = 'auto';
        var p = v.play();
        if (p && p.catch) p.catch(function(){});     // autoplay refused: leave the poster
        e.target.classList.add('af-playing');
      } else if (!e.target.classList.contains('af-open')) {
        v.pause();
        e.target.classList.remove('af-playing');
      }
    });
  }, {threshold: 0.25}) : null;

  // ── sliding ──────────────────────────────────────────────────────────
  // A continuous glide rather than a stepped carousel: the owner asked for the
  // row to slide, and stepping every few seconds reads as jumping.
  //
  // IT NO LONGER SCROLLS. The version before this wrote track.scrollLeft sixty
  // times a second, and the owner reported the result as laggy and juddery
  // (screen recording, 2026-09-09). Measured off that recording frame by frame:
  // every step landed on a whole number of pixels — 2, 2, 2, 0, 4 — and 19% of
  // frames did not move at all, so the row covered 53px/s where 66 was asked
  // for. Two causes, both inherent to moving something by scrolling it:
  //
  //   1. scrollLeft is quantised to whole pixels. However precisely the
  //      position is computed, 2.2 renders at 2 and the remainder is dropped,
  //      so the steps come out uneven — which is what judder is.
  //   2. writing it every frame drives scrolling, layout and paint on the main
  //      thread, on a row holding twenty <video> elements. Frames get dropped,
  //      and a dropped frame is a visible stall.
  //
  // So the row is moved by a transform instead, handed to the browser once
  // through the Web Animations API. Transforms take fractional pixels and are
  // animated off the main thread, so neither cause survives: there is no
  // per-frame work left to be late, and video decoding cannot stall it. The
  // travel is exactly one set of tiles, which lands on an identical frame, so
  // the loop is invisible.
  //
  // It still yields to the visitor: paused on hover, while dragging, while a
  // clip is open with sound, and while the tab is hidden. prefers-reduced-
  // motion leaves it still, and draggable.
  var track = document.querySelector('.circle-gallery-slider');
  if (track) {
    var GAP = 14;                       // must match the gap in the CSS above
    var SPEED = 66;                     // px per second

    // One element holding every tile, so one transform moves the row. The CSS
    // keeps both this and the slider a flex row, so a failure here leaves the
    // tiles in a row rather than stacked down the left.
    var lane = document.createElement('div');
    lane.className = 'af-motion-track';
    while (track.firstChild) lane.appendChild(track.firstChild);
    track.appendChild(lane);

    var originals = [].slice.call(lane.children);
    if (originals.length) {
      originals.forEach(function(n){
        var c = n.cloneNode(true);
        c.setAttribute('aria-hidden','true');
        c.setAttribute('data-af-clone','1');
        lane.appendChild(c);
      });
      // Re-read the tiles: the list above was taken before these clones
      // existed, and a clone that never gets the play/click wiring is a dead
      // black rectangle sitting in the middle of the row.
      items = document.querySelectorAll('.af-motion-item');
    }

    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var anim = null, dur = 0;

    // How far to travel for the second set to land exactly where the first
    // began. The lane is 2N tiles with a gap between each, so its width counts
    // 2N-1 gaps while one set plus its trailing gap is N — hence the +GAP
    // before halving. Taken from the fractional box, not scrollWidth, which
    // rounds and would leave the loop a fraction of a pixel out of true.
    function advance(){ return (lane.getBoundingClientRect().width + GAP) / 2; }

    function build(){
      var at = anim ? (Number(anim.currentTime) || 0) / (dur || 1) : 0;   // keep the place
      if (anim) { anim.cancel(); anim = null; }
      var adv = advance();
      if (!(adv > 0) || !lane.animate) return;
      dur = adv / SPEED * 1000;
      anim = lane.animate(
        [ { transform: 'translate3d(0,0,0)' },
          { transform: 'translate3d(' + (-adv) + 'px,0,0)' } ],
        { duration: dur, iterations: Infinity, easing: 'linear' }
      );
      anim.currentTime = (at % 1) * dur;
      if (held()) anim.pause();
    }

    // Every reason the row should stand still, by name, so two overlapping
    // ones (hovering while a clip is open) cannot cancel each other out.
    var why = {};
    function held(){ for (var k in why) if (why[k]) return true; return false; }
    function yieldFor(k, on){
      if (on) why[k] = 1; else delete why[k];
      if (!anim) return;
      if (held()) { if (anim.playState === 'running') anim.pause(); }
      else if (anim.playState !== 'running') anim.play();
    }
    if (reduce) why.reduce = 1;

    track.addEventListener('mouseenter', function(){ yieldFor('hover', 1); });
    track.addEventListener('mouseleave', function(){ yieldFor('hover', 0); });
    document.addEventListener('visibilitychange', function(){
      yieldFor('hidden', document.hidden);
    });

    // A clip playing with sound stops the row. Polled four times a second
    // rather than wired into each open and close: the tile click, the popup
    // and the popup's several ways of closing would each have to remember to
    // call this, and the one that forgot would leave the row moving under a
    // video the visitor is watching. Four cheap reads a second cannot judder.
    var wasOpen = false;
    setInterval(function(){
      var open = !!(document.querySelector('.af-motion-item.af-open') ||
                    document.querySelector('.af-motion-lb.open'));
      if (open !== wasOpen) { wasOpen = open; yieldFor('open', open); }
    }, 250);

    // ── drag ───────────────────────────────────────────────────────────
    // The row used to be a native scroller, so it could be flicked. Overflow
    // is hidden now, so the flick is given back here: dragging seeks the
    // animation, one pixel of finger to one pixel of row.
    var SLOP = 6;                       // a click may wander a few px; a drag means it
    var down = false, lastX = 0, moved = 0, caught = false, pid = 0;
    track.addEventListener('pointerdown', function(e){
      if (e.button) return;
      down = true; moved = 0; caught = false; lastX = e.clientX; pid = e.pointerId;
      yieldFor('drag', 1);
    });
    track.addEventListener('pointermove', function(e){
      if (!down) return;
      var dx = e.clientX - lastX; lastX = e.clientX; moved += Math.abs(dx);
      // The pointer is captured only once this is plainly a drag, never for a
      // click. Capturing it on pointerdown moves the pointerup target from the
      // tile to the row, and the browser then sends the click to the common
      // ancestor of the two — the row — so the tile's own click never runs and
      // NO CLIP OPENS. That is what capturing early did here, caught in a
      // browser: pointerdown on VIDEO, pointerup on the row, click on the row.
      if (!caught && moved > SLOP) {
        caught = true;
        track.classList.add('af-grabbing');
        try { track.setPointerCapture(pid); } catch(err){}
      }
      if (!anim || !dur) return;
      // dragging right pulls the row back, so it walks the animation backwards
      var t = (Number(anim.currentTime) || 0) - dx / SPEED * 1000;
      anim.currentTime = ((t % dur) + dur) % dur;
    });
    function release(){
      if (!down) return;
      down = false;
      track.classList.remove('af-grabbing');
      // A drag ends in a click on whatever was under the finger. Six pixels of
      // travel means it was a drag, so the tile is deafened for one frame and
      // the row does not open a video every time it is pushed along.
      if (moved > SLOP) {
        track.classList.add('af-dragged');
        setTimeout(function(){ track.classList.remove('af-dragged'); }, 0);
      }
      yieldFor('drag', 0);
    }
    ['pointerup','pointercancel'].forEach(function(e){
      track.addEventListener(e, release);
    });

    // The travel is measured in pixels, so a resize that changes the tile
    // width has to be measured again. Debounced: a drag of the window edge
    // fires this continuously.
    var rt = 0;
    window.addEventListener('resize', function(){
      clearTimeout(rt); rt = setTimeout(build, 200);
    });

    // Tiles are clamp(180px,19vw,364px) wide, so the lane is only its final
    // width once layout has settled; measuring before that sets the loop to
    // the wrong distance and the wrap becomes visible.
    build();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(build);
    window.addEventListener('load', build);
  }

  // ── the popup ─────────────────────────────────────────────────────────
  // Built here rather than borrowed from the still-image row's lightbox: that
  // row is hidden on this page, and a control that depends on a hidden
  // section is a control that breaks the next time the section moves.
  var lb = null, lbFrame = null, lbPaused = null;
  function afMotionBuildLb(){
    if (lb) return;
    lb = document.createElement('div');
    lb.className = 'af-motion-lb';
    lb.innerHTML = '<button type="button" class="af-motion-lb-x" aria-label="Close">&times;</button>'
      // The classes and data attributes are not decoration: they are the
      // opt-out flags every lazy-loading plugin on this stack reads. Measured
      // 2026-09-09 — the box came out a correct 1354x761 and the iframe INSIDE
      // it measured 0x0, which an element with explicit pixel width and height
      // can only do when something has set it to display:none. That is a lazy
      // loader waiting for the frame to be scrolled into view, and it never
      // will be: it lives in a popup that is only built at the moment it opens.
                 + '<div class="af-motion-lb-box"><iframe class="skip-lazy no-lazy"'
                 + ' data-no-lazy="1" data-skip-lazy="1" data-lazy-loaded="1" loading="eager"'
                 + ' allow="autoplay; encrypted-media; fullscreen"'
                 + ' allowfullscreen frameborder="0"></iframe></div>';
    document.body.appendChild(lb);
    lbFrame = lb.querySelector('iframe');
    lb.querySelector('.af-motion-lb-x').addEventListener('click', afMotionCloseLb);
    lb.addEventListener('click', function(e){ if (e.target === lb) afMotionCloseLb(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') afMotionCloseLb(); });
    // A window resize while the popup is open would otherwise leave it at the
    // old window's pixels.
    window.addEventListener('resize', function(){
      if (lb && lb.classList.contains('open')) afMotionSizeLb();
    });
  }
  /* The player's real size, in pixels, from the window it has to fit in:
     the largest 16:9 rectangle inside 94% of the width and 85% of the
     height. Applied !important to the box and the iframe both, and to the
     iframe's width/height attributes as well, so it holds even if the
     stylesheet never arrives. */
  function afMotionSizeLb(){
    if (!lb) return;
    var box = lb.querySelector('.af-motion-lb-box');
    if (!box) return;
    var maxW = Math.max(240, window.innerWidth * 0.94);
    var maxH = Math.max(135, window.innerHeight * 0.85);
    var w = Math.min(maxW, maxH * 16 / 9);
    var h = Math.round(w * 9 / 16);
    w = Math.round(w);
    box.style.setProperty('width',  w + 'px', 'important');
    box.style.setProperty('height', h + 'px', 'important');
    if (lbFrame) {
      lbFrame.style.setProperty('width',  w + 'px', 'important');
      lbFrame.style.setProperty('height', h + 'px', 'important');
      // display, visibility and opacity as well as the size. A pixel width
      // means nothing to an element a lazy loader has set to display:none,
      // which is exactly how a correctly sized box came to hold a 0x0 player.
      lbFrame.style.setProperty('display', 'block', 'important');
      lbFrame.style.setProperty('visibility', 'visible', 'important');
      lbFrame.style.setProperty('opacity', '1', 'important');
      lbFrame.setAttribute('width', w);
      lbFrame.setAttribute('height', h);
    }
  }
  function afMotionOpenLb(vid, tileVideo){
    afMotionBuildLb();
    // rel=0 keeps YouTube's own end-screen to this channel, and the branding
    // stays clickable so "click YouTube and go to YouTube" still works.
    lbFrame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(vid)
                + '?autoplay=1&rel=0&playsinline=1';
    lb.classList.add('open');
    document.documentElement.style.overflow = 'hidden';
    // The size is stated in pixels HERE, every time, not left to CSS and not
    // deferred to a timer. Twice now a stylesheet on this page has flattened
    // this box to 0x0 — YouTube answering 200 into a frame nobody can see —
    // and a popup that opens empty is worse than no popup at all. Pixels
    // computed from the window cannot be collapsed by a cascade, and doing it
    // before the frame paints means there is no blank moment to observe.
    afMotionSizeLb();
    if (tileVideo) { try { tileVideo.pause(); lbPaused = tileVideo; } catch(e){} }
  }
  function afMotionCloseLb(){
    if (!lb || !lb.classList.contains('open')) return;
    lb.classList.remove('open');
    // about:blank, not '': an empty src reloads this page inside the frame.
    lbFrame.src = 'about:blank';
    document.documentElement.style.overflow = '';
    if (lbPaused) { try { lbPaused.play().catch(function(){}); } catch(e){} lbPaused = null; }
  }

  items.forEach(function(item){
    var v = item.querySelector('video');
    if (!v) return;
    if (io) io.observe(item);
    else { v.preload = 'auto'; v.play().catch(function(){}); item.classList.add('af-playing'); }

    // Click opens the full clip, as it did before: sound on, controls, from
    // the start, and no longer muted-loop wallpaper.
    item.addEventListener('click', function(){
      // Owner, 2026-09-08: "when user click on the video then video popup and
      // show on youtube as like it was like previous one". The tiles became
      // self-hosted clips and took their click with them — it started playing
      // the local file inline, with a native control bar and a caption over
      // the artwork. The popup is what was asked for and what was there
      // before, so the click goes back to it whenever the tile knows its
      // YouTube id; a tile that does not falls through to the old behaviour
      // rather than doing nothing.
      var yt = item.getAttribute('data-yt');
      if (yt) { afMotionOpenLb(yt, v); return; }
      if (item.classList.contains('af-open')) return;
      item.classList.add('af-open');
      v.muted = false; v.loop = false; v.controls = true;
      try { v.currentTime = 0; } catch(e){}
      v.play().catch(function(){ v.muted = true; v.play().catch(function(){}); });
    });

    v.addEventListener('ended', function(){
      item.classList.remove('af-open');
      v.controls = false; v.muted = true; v.loop = true;
      v.play().catch(function(){});
    });
  });
})();
