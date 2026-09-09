<?php
if (!defined('ABSPATH')) exit;
/**
 * "Products In Motion" — make the tiles actually play.
 *
 * Owner, 2026-09-07: "the videos are not playing they are just zoom in and zoom
 * out ... take the video from there and show on here".
 *
 * WHAT WAS WRONG
 * [youtube_circle_slider] renders eight <iframe>s pointing at YouTube
 * PLAYLISTS — /embed/videoseries?list=…&autoplay=1&mute=1&loop=1. A playlist
 * embed is the one form YouTube will not autoplay: it holds on the first
 * video's poster frame. So every tile was a still picture, and the only motion
 * was the slider's own CSS scale — exactly the "zoom in and zoom out" that was
 * reported. Nothing was broken; it simply never played.
 *
 * WHAT IT DOES NOW
 * Each tile becomes a self-hosted <video>, muted, looping, playsinline —
 * which browsers DO autoplay — using the studio's own clips from the media
 * library. Clicking a tile still opens the full video, as before: the poster
 * preview is swapped for the same clip with sound and controls.
 *
 * ASPECT: the clips are 1280x720 landscape; the tiles are 9:16 portrait. The
 * first version widened the tiles to 16:9 to fit the footage, and the owner
 * asked for the tall tiles back (2026-09-07) — the row of tall cards is the
 * design. So the footage is centre-cropped instead, which was checked frame by
 * frame first: on eight of the nine clips the artwork sits dead centre and the
 * crop lands on it cleanly. The two long marketing clips, whose middles carry
 * text overlays, are ordered last where they matter least.
 *
 * IT OWNS THE ROW LAYOUT TOO. The shortcode used to ship its own inline
 * stylesheet for .circle-gallery-slider / .circle-item, and rewriting the
 * shortcode's output took that with it — leaving nine unstyled divs stacked
 * down the left of the page instead of a row (reported 2026-09-07). Nothing on
 * the page defines those classes any more, so the flex row, the horizontal
 * scroll and the tile size are all declared here. The section can no longer be
 * broken by CSS that lives somewhere else.
 *
 * KIND TO THE SERVER, which matters on a CPU-capped host with a bandwidth bill:
 *   - preload="none": nothing is fetched until a tile is actually on screen
 *   - an IntersectionObserver plays only visible tiles and pauses the rest, so
 *     scrolling past the band costs one clip, not nine
 *   - the poster image is the product still, so the row looks right before a
 *     single byte of video moves
 */

/** Attachment ids of the clips, in the order they appear. Empty = do nothing. */
function af_motion_video_ids() {
    $ids = get_option('af_motion_video_ids', array());
    if (!is_array($ids) || !$ids) {
        // All ten clips from the studio's folder. The two long marketing
        // films are last: their middles carry text overlays, which a 9:16
        // centre crop lands badly on.
        $ids = array(33349, 33350, 33353, 33351, 33352, 33357,
                     33354, 33356, 33355, 33348);
    }
    return array_values(array_filter(array_map('intval', (array) apply_filters('af_motion_video_ids', $ids))));
}

/** url + title for each clip, resolved once and cached. */
function af_motion_videos() {
    // Key derived from the id list, so adding or reordering a clip picks a new
    // cache entry by itself. A fixed key held a stale nine-clip list for twelve
    // hours after the tenth was added, and the row went on showing nine.
    $key = 'af_motion_videos_' . substr(md5(implode(',', af_motion_video_ids())), 0, 10);
    $out = get_transient($key);
    if (is_array($out)) return $out;
    $out = array();
    foreach (af_motion_video_ids() as $id) {
        $url = wp_get_attachment_url($id);
        if (!$url) continue;
        // The YouTube id for this clip, so a click can open the video on
        // YouTube as it did before these tiles became self-hosted. The
        // matcher in functions.php already stores videoid => local URL; this
        // is that map read backwards, and it costs nothing.
        $yt = '';
        $local = get_option('af_pim_local');
        if (is_array($local)) {
            foreach ($local as $vid => $vurl) {
                if ($vurl === $url || basename((string) $vurl) === basename((string) $url)) {
                    $yt = (string) $vid;
                    break;
                }
            }
        }
        $out[] = array(
            'url'   => $url,
            'title' => get_the_title($id) ?: 'The Art Framer',
            'yt'    => $yt,
        );
    }
    set_transient($key, $out, 12 * HOUR_IN_SECONDS);
    return $out;
}

/**
 * Rebuild the slider's tiles around real video.
 * Returns the original markup untouched if anything is missing or goes wrong.
 */
function af_motion_rewrite($html) {
    $videos = af_motion_videos();
    if (!$videos) return $html;
    if (strpos($html, 'circle-item') === false) return $html;

    $tiles = '';
    foreach ($videos as $i => $v) {
        $tiles .= '<div class="circle-item video-circle af-motion-item"'
                . (!empty($v['yt']) ? ' data-yt="' . esc_attr($v['yt']) . '"' : '') . '>'
                . '<video class="af-motion-video" muted loop playsinline preload="none"'
                . ' disablepictureinpicture controlslist="nodownload noplaybackrate"'
                . ' aria-label="' . esc_attr($v['title']) . '">'
                . '<source src="' . esc_url($v['url']) . '" type="video/mp4">'
                . '</video>'
                . '<button type="button" class="af-motion-play" aria-label="'
                . esc_attr__('Play', 'postero-child') . ' ' . esc_attr($v['title']) . '">'
                . '<span></span></button>'
                . '<span class="af-motion-cap">' . esc_html($v['title']) . '</span>'
                . '</div>';
    }
    if ($tiles === '') return $html;

    $tiles .= '';   // arrows are siblings of the track, added below

    // Keep the slider's own wrapper so its arrows and drag-scroll keep working.
    $new = preg_replace(
        '#(<div class="circle-gallery-slider"[^>]*>).*?(</div>)\s*$#s',
        '$1' . str_replace('\\', '\\\\', str_replace('$', '\\$', $tiles)) . '$2',
        $html, 1, $count
    );
    if (!$count || !$new) {
        // wrapper not shaped as expected — wrap our own rather than lose the band
        $new = '<div class="circle-gallery-slider af-motion-slider">' . $tiles . '</div>';
    }
    // No arrows: the owner asked for the row to slide, not to be clicked
    // through (2026-09-07). It moves on its own and can still be dragged or
    // flicked, so nothing is unreachable without a control.
    return '<div class="af-motion-shell">' . $new . '</div>';
}

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'youtube_circle_slider') return $output;
    try {
        return af_motion_rewrite($output);
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('af-motion: ' . $e->getMessage());
        return $output;                       // never lose the section over this
    }
}, 20, 2);

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return; ?>
<style>
/* ── The other "Products In Motion" ────────────────────────────────────────
 * A second row sits above this one: .af-pim-*, a marquee of 128 STILL images
 * (four frames per card, cycled) built in functions.php by a parallel session
 * while this video version was being built. Both ended up on the page, which
 * is the "still has 2 section" the owner reported.
 *
 * It is hidden rather than deleted: it is someone else's code, in a file this
 * host will not recompile anyway, and hiding is reversible in one line. The
 * video row below it is the one the owner asked for — frame-cycled stills are
 * exactly the "just zoom in and zoom out" that started this work.
 *
 * To bring it back and drop this one instead, delete this rule and this file.
 */
.af-pim-wrap,
.af-pim-section-heading + .af-pim-wrap{display:none !important;}

/* The row itself. The shortcode's own stylesheet no longer reaches the page,
   so these are declared here rather than inherited from it. The existing
   arrows and drag-scroll both call scrollBy() on this element, so it has to
   stay a horizontal scroller for them to keep working. */
/* !important and the doubled selector are deliberate. The plain rule was on the
   page, nothing in the HTML or in any of the 83 bundles contradicted it, and the
   nine tiles still wrapped into two rows — which is what the owner reported as
   "still has 2 sections". Something outside the stylesheet reaches this element,
   so the row is nailed down rather than argued with. */
.elementor-shortcode .circle-gallery-slider,
.circle-gallery-slider.circle-gallery-slider{
  display:flex !important;
  flex-wrap:nowrap !important;
  flex-direction:row !important;
  align-items:flex-start !important;
  gap:14px;
  /* hidden, not auto. The row is moved by a transform on .af-motion-track now,
     not by scrolling it — see the note above the slider in the script. A
     transformed child also changes a scroller's scrollWidth as it travels,
     which would drag the scrollbar range about under the visitor's finger. */
  overflow:hidden !important;
  /* the drag below is horizontal; this leaves the vertical swipe to the page,
     so the row cannot trap a visitor scrolling past it on a phone. */
  touch-action:pan-y;
  cursor:grab;
  padding:2px 0 14px;width:100%;}
.circle-gallery-slider.af-grabbing{cursor:grabbing;}
/* The tiles sit in this one element so a single transform moves the whole row.
   It is a flex row itself, AND the slider above stays one too, so if the
   script never runs the tiles are still a row rather than a stack down the
   left — the failure the owner reported on 2026-09-07. */
.af-motion-track{display:flex;flex-wrap:nowrap;flex-direction:row;
  align-items:flex-start;gap:14px;width:max-content;flex:0 0 auto;
  /* its own compositor layer, so the transform never repaints the row */
  will-change:transform;transform:translate3d(0,0,0);backface-visibility:hidden;}
.circle-gallery-slider > .circle-item,
.af-motion-track > .circle-item{flex:0 0 auto !important;}
.circle-gallery-slider::-webkit-scrollbar{display:none;}
/* A dragged row must not also open a clip; the script adds this for the
   moment between the drag ending and the click it would otherwise become. */
.circle-gallery-slider.af-dragged .af-motion-item{pointer-events:none;}
.af-motion-shell{position:relative;}
.af-motion-item{position:relative;flex:0 0 auto;width:clamp(180px,19vw,364px);
  aspect-ratio:9/16;border-radius:14px;overflow:hidden;background:#0f0d0b;
  box-shadow:0 2px 10px rgba(40,30,10,.10);cursor:pointer;}
.af-motion-item video{width:100%;height:100%;object-fit:cover;display:block;}
/* No play button by default: the tile is already moving, so a badge over it
   would only be clutter. It appears on hover to say "click for sound". */
.af-motion-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  border:0;background:rgba(0,0,0,.18);cursor:pointer;padding:0;
  opacity:0;transition:opacity .2s;}
.af-motion-item:hover .af-motion-play,
.af-motion-item:focus-within .af-motion-play{opacity:1;}
.af-motion-play span{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.92);
  position:relative;box-shadow:0 2px 12px rgba(0,0,0,.3);}
.af-motion-play span:after{content:"";position:absolute;top:50%;left:56%;transform:translate(-50%,-50%);
  border-style:solid;border-width:10px 0 10px 17px;border-color:transparent transparent transparent #1a1a1a;}
.af-motion-item.af-open .af-motion-play{display:none;}
/* caption on hover only, so the row reads as clean video at rest */
.af-motion-cap{position:absolute;left:0;right:0;bottom:0;padding:26px 12px 11px;
  font-size:12.5px;font-weight:600;color:#fff;pointer-events:none;
  opacity:0;transition:opacity .2s;
  background:linear-gradient(to top,rgba(0,0,0,.68),rgba(0,0,0,0));}
.af-motion-item:hover .af-motion-cap{opacity:1;}
/* z-index at the top of the range, because 99999 was not enough: measured
   2026-09-09, with the player finally sized and visible at 1354x761, the
   element actually painted at its centre was DIV.af-popup-left — a plugin's
   own overlay stacking above this one. Nothing else is touched to achieve
   it; that popup keeps its own behaviour, it simply no longer sits in
   front of a video the visitor just asked to watch. */
.af-motion-lb{position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.88);
  display:none;align-items:center;justify-content:center;padding:24px;}
.af-motion-lb.open{display:flex;}
/* An explicit height, NOT aspect-ratio. Measured 2026-09-08 by clicking a
   tile in a real browser: YouTube answered 200 and the player loaded into a
   box of 0x0 — "the iframe has no size, a layout fault, not a video fault".
   aspect-ratio needs a definite width to resolve against and something on this
   page denies it one, so the height is stated outright and the width derived
   from it. max-width keeps a narrow window honest; YouTube letterboxes inside
   its own frame if that ever bites, which is a far smaller problem than a
   popup with nothing in it. */
.af-motion-lb-box{position:relative;
  height:min(85vh,619px);
  width:calc(min(85vh,619px) * 16 / 9);
  max-width:94vw;}
/* display and visibility are stated here for the same reason they are stated
   in the script: a lazy loader on this site sets iframes to display:none until
   they scroll into view, and this one is inside a popup that never scrolls. A
   box of 1354x761 holding a player of 0x0 was measured before this line. */
.af-motion-lb-box iframe{position:absolute;inset:0;width:100%!important;height:100%!important;
  display:block!important;visibility:visible!important;opacity:1!important;
  border:0;border-radius:10px;background:#000;}
.af-motion-lb-x{position:absolute;top:16px;right:22px;z-index:2;background:none;border:0;
  color:#fff;font-size:40px;line-height:1;cursor:pointer;padding:4px 10px;}
@media(max-width:600px){
  .af-motion-lb-box{width:96vw;}
  .af-motion-item{width:60vw;}
  .af-motion-cap{opacity:1;font-size:12px;}
}
</style>
<?php }, 20);

add_action('wp_footer', function () {
    if (!is_front_page() && !is_home()) return; ?>
<script>
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
</script>
<?php }, 60);
