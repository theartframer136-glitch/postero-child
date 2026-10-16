(function(){
    var track = document.getElementById('afPimTrack');
    var vp    = document.getElementById('afPimVp');
    var lb    = document.getElementById('afPimLb');
    var lbFr  = document.getElementById('afPimLbFrame');
    var lbX   = document.getElementById('afPimLbX');
    var cards = Array.prototype.slice.call(document.querySelectorAll('#afPimTrack .af-pim-card'));

    var reduceMotion = false;
    try { reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}

    // ── Easing the row to a stop and back ───────────────────────────────
    // The scroll itself stays a CSS animation, so at a steady speed it runs on
    // the compositor and costs the main thread nothing — which is the whole
    // reason it glides while six video players are working. What CSS cannot do
    // is change speed gradually: animation-duration is not transitionable, and
    // animation-play-state only has "running" and "stopped", so pausing on
    // hover means stopping dead mid-stride.
    //
    // So take hold of the very same animation object and ramp its playbackRate
    // instead. Script runs only during the ~450ms of a ramp; the rest of the
    // time the compositor is left alone. If the browser will not hand the
    // animation over, the class-based hard pause below still works.
    var anim = null, haveAnim = false;
    function marquee(){
        if (haveAnim) return anim;
        haveAnim = true;
        try {
            var list = track.getAnimations ? track.getAnimations() : [];
            for (var i = 0; i < list.length; i++) {
                if (list[i].animationName === 'af-pim-marquee') { anim = list[i]; break; }
            }
            if (!anim && list.length) anim = list[0];
        } catch (e) { anim = null; }
        return anim;
    }

    var rateNow = 1, rateWant = 1, ramping = false;
    function ease(k){ return k < 0.5 ? 2*k*k : 1 - Math.pow(-2*k + 2, 2) / 2; }
    function speed(target){
        rateWant = target;
        var a = marquee();
        if (!a) {                                   // no handle on it: hard pause
            track.classList.toggle('af-pim-hold', target === 0);
            return;
        }
        track.classList.remove('af-pim-hold');
        if (ramping) return;                        // the running ramp reads rateWant
        ramping = true;
        var from = rateNow, t0 = 0;
        (function step(now){
            if (!t0) t0 = now;
            var k = Math.min(1, (now - t0) / 450);
            rateNow = from + (rateWant - from) * ease(k);
            try { a.playbackRate = rateNow; } catch (e) {}
            if (k < 1) { requestAnimationFrame(step); return; }
            ramping = false;
            // The target may have changed while this ramp was running — chase
            // it rather than settling on a stale one.
            if (Math.abs(rateNow - rateWant) > 0.01) speed(rateWant);
        })(0);
    }

    // How many players may run at once. The row used to create one per card
    // and roughly twenty autoplaying YouTube embeds made the browser throttle
    // them — several tiles just sat black behind a spinner. Only the cards on
    // screen get a player, and never more than this many.
    var MAX_LIVE = 6;
    var live = [];      // in the order they started, so the oldest is dropped first

    function stop(card){
        var i = live.indexOf(card);
        if (i !== -1) live.splice(i, 1);
        card.classList.remove('af-pim-live');
        var fr = card.querySelector('video.af-pim-video');
        if (!fr) return;
        // Marked BEFORE the timer, because the pause/load below rejects the
        // element's pending play() promise with AbortError, and the rejection
        // handler must be able to tell that apart from a genuinely broken
        // file. Without it an ordinary eviction — the marquee moving a card
        // out, MAX_LIVE dropping the oldest, the lightbox opening — looked
        // identical to a media error and struck data-mp4 off the card for the
        // rest of the page, so a card that HAS its reel went permanently
        // quiet. That is the exact failure this whole row was meant to end.
        fr.dataset.afTearingDown = '1';
        // Let the fade finish before the node goes, so a card leaving the row
        // dissolves back to its poster instead of blinking. The handle is kept
        // so a card that comes straight back can cancel its own teardown
        // instead of being blocked by the node it is about to inherit.
        card._afPimTeardown = setTimeout(function(){
            card._afPimTeardown = null;
            try { fr.pause(); fr.removeAttribute('src'); fr.load(); } catch(e){}
            if (fr.parentNode) fr.parentNode.removeChild(fr);
        }, 500);
    }
    // The YouTube in-card player was removed here for the second and final
    // time. The full attempt — controls=0, muted autoplay, a working API
    // handshake, a page-driven loop that never let the ended state draw —
    // still ended with the player painting a persistent centre pause chip on
    // playing cards (owner's recordings, 09:55 and 11:34, the second with
    // every steering fix live). The chip is the player's own UI, inside its
    // frame, beyond page CSS. Cards therefore play ONLY same-origin files;
    // without one, the drifting stills stand in. Do not put the embed back.

    function start(card){
        if (reduceMotion) return;
        // Same-origin files ONLY — see the note above on the embed's removal.
        // A card without its file keeps the drifting stills, which cannot
        // show a button, and upgrades itself the moment the file appears.
        var mp4 = card.getAttribute('data-mp4');
        if (!mp4) return;
        if (lb.classList.contains('open')) return;      // the lightbox has the stage

        // A card can come back inside its own 500ms fade — a scroll bounce, or
        // the marquee nudging it over the boundary twice. Its old node is
        // still here; adopt it rather than declining, which used to leave the
        // card visible, poster-only and outside live[], with no further
        // observer callback to rescue it until it crossed the margin again.
        var old = card.querySelector('video.af-pim-video');
        if (old) {
            if (card._afPimTeardown) {
                clearTimeout(card._afPimTeardown);
                card._afPimTeardown = null;
                delete old.dataset.afTearingDown;
                if (live.indexOf(card) === -1) {
                    while (live.length >= MAX_LIVE) stop(live[0]);
                    live.push(card);
                }
                if (!old.paused) card.classList.add('af-pim-live');
                else { var rp = old.play(); if (rp && rp.catch) rp.catch(function(){}); }
            }
            return;                                     // never two players in one card
        }

        while (live.length >= MAX_LIVE) stop(live[0]);
        live.push(card);

        var v = document.createElement('video');
        v.className = 'af-pim-video';
        v.muted = true; v.loop = true; v.playsInline = true;
        v.setAttribute('muted', '');                 // the property alone does
        v.setAttribute('playsinline', '');           // not survive some parsers
        v.preload = 'auto';
        v.src = mp4;
        // Only reveal if this node is still the card's player. A 'playing'
        // event can land inside a teardown window, and re-adding the class to
        // a card whose node is about to vanish left it flagged live with no
        // video — so the NEXT player was born under opacity:1 and popped in
        // instead of cross-fading.
        v.addEventListener('playing', function(){
            if (v.dataset.afTearingDown) return;
            if (v.parentNode !== card) return;
            card.classList.add('af-pim-live');
        });

        // A bad FILE ends at the poster — never at an embed, and never on a
        // mere interruption. An eviction pauses the element, which rejects the
        // pending play() with AbortError; treating that as a broken file is
        // what used to demote a perfectly good card permanently.
        var fell = false;
        function giveUp(){
            if (fell) return;
            fell = true;
            var i = live.indexOf(card);
            if (i !== -1) live.splice(i, 1);
            card.classList.remove('af-pim-live');
            try { v.pause(); } catch(e){}
            if (v.parentNode) v.parentNode.removeChild(v);
            card.removeAttribute('data-mp4');       // this file is no good
        }
        v.addEventListener('error', giveUp);
        card.insertBefore(v, card.firstChild);
        var p = v.play();
        if (p && p.catch) p.catch(function(err){
            // Interrupted, not broken: leave data-mp4 alone so the card plays
            // again the next time it comes round.
            if (v.dataset.afTearingDown) return;
            if (err && (err.name === 'AbortError' || err.name === 'NotAllowedError')) return;
            giveUp();
        });
    }

    // Play what is on screen. The row moves continuously, so this fires as
    // cards drift in and out — the generous margin means a card is already
    // playing by the time it is properly visible, rather than starting from
    // black in front of the visitor.
    try {
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(e){
                if (e.isIntersecting) start(e.target);
                else                  stop(e.target);
            });
        // The vertical margin is the preload. A card begins loading while the
        // section is still most of a screen below the fold, so by the time the
        // visitor arrives the row is already moving — instead of a wall of
        // stills that blink into life once they have been looked at. The
        // horizontal margin does the same job sideways, for cards about to
        // slide in from the right.
        }, { root: null, rootMargin: '700px 260px 700px 260px', threshold: 0.2 });
        cards.forEach(function(c){ io.observe(c); });
    } catch(e){
        cards.slice(0, MAX_LIVE).forEach(start);        // no observer: play the first few
    }

    // The whole section leaving the viewport stops everything, so nothing
    // burns data or CPU further down the page — and it picks up again on the
    // way back.
    try {
        new IntersectionObserver(function(en){
            en.forEach(function(e){
                if (!e.isIntersecting) { live.slice().forEach(stop); speed(0); }
                else                    { speed(1); }
            });
        // Matched to the card margin above. With a plain threshold this
        // observer fired "not visible" for the whole approach and tore down
        // every player the preload had just built.
        }, { rootMargin: '750px 0px 750px 0px', threshold: 0 })
          .observe(document.getElementById('afPimWrap'));
    } catch(e){}

    // Ease to a stop under the pointer, and back up on the way out.
    if (!reduceMotion) {
        vp.addEventListener('mouseenter', function(){ speed(0); });
        vp.addEventListener('mouseleave', function(){ speed(1); });
        // A background tab should not be animating at all, and coming back to
        // one that kept running looks like the row jumped while you were away.
        document.addEventListener('visibilitychange', function(){
            speed(document.hidden ? 0 : 1);
        });
    }

    // Click still opens the video with sound.
    cards.forEach(function(c){
        var ov = c.querySelector('.af-pim-overlay');
        if (!ov) return;
        ov.addEventListener('click', function(){
            lbFr.src = 'https://www.youtube-nocookie.com/embed/' + c.getAttribute('data-vid')
                     + '?autoplay=1&rel=0&playsinline=1';
            lb.classList.add('open');
            live.slice().forEach(stop);                 // one thing playing at a time
            speed(0);
        });
    });

    function closeLb() {
        lb.classList.remove('open');
        // 'about:blank', not '': an empty src navigates the iframe to the
        // page's own URL, silently re-downloading the whole page.
        lbFr.src = 'about:blank';
        speed(1);                                // the row eases back into motion
        // Opening the lightbox stopped every card. The per-card observer only
        // fires on boundary crossings, so a card already on screen would get
        // no callback and sit as a still poster until the marquee carried it
        // out and back — the row looked frozen after every preview. Restart
        // what is on screen, next frame, once the overlay is really gone.
        requestAnimationFrame(function(){
            cards.forEach(function(c){
                var r = c.getBoundingClientRect();
                if (r.right > 0 && r.left < window.innerWidth && r.bottom > 0
                    && r.top < window.innerHeight) start(c);
            });
        });
    }
    lbX.onclick = closeLb;
    lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLb(); });

    // Find the "Products In Motion" section, hide its video content, inject our slider inside it
    function placeSlider() {
        var wrap = document.getElementById('afPimWrap');
        if (!wrap || wrap.dataset.placed) return;

        // Find the heading
        var heading = null;
        document.querySelectorAll('h2,h3,h4,.elementor-heading-title').forEach(function(h) {
            if (!heading && /product.*mot/i.test(h.textContent)) heading = h;
        });
        if (!heading) return;

        // Left-align the heading
        var headWidget = heading.closest('.elementor-widget') || heading.parentElement;
        headWidget.classList.add('af-pim-section-heading');
        heading.style.setProperty('text-align', 'left', 'important');
        headWidget.style.setProperty('text-align', 'left', 'important');

        // Walk up to the outermost Elementor section containing this heading
        var section = null;
        var el = heading;
        for (var i = 0; i < 20; i++) {
            el = el.parentElement;
            if (!el || el === document.body) break;
            if (/elementor-top-section|elementor-section-wrap/.test(el.className || '')) { section = el; break; }
            if (/elementor-section/.test(el.className || '')) section = el;
        }
        if (!section) section = heading.parentElement;

        wrap.dataset.placed = '1';

        // Hide iframes ONLY inside the Products In Motion section (not page-wide)
        section.querySelectorAll('iframe').forEach(function(fr) {
            if (fr.closest('#afPimWrap') || fr.closest('.af-pim-lb')) return;
            var col = fr.closest('.elementor-column, .e-con, .elementor-widget');
            if (col) col.style.setProperty('display', 'none', 'important');
            else fr.style.setProperty('display', 'none', 'important');
        });

        // Also hide video/playlist widgets inside the section
        section.querySelectorAll('.elementor-widget').forEach(function(w) {
            var type = w.getAttribute('data-widget_type') || '';
            if (/^(heading|text-editor|text)\b/.test(type)) return;
            w.style.setProperty('display', 'none', 'important');
        });

        // Find the widget-wrap container that holds the heading (direct parent of elementor-widgets)
        var container = heading.closest('.elementor-widget-wrap, .e-con-inner, .e-con') || section;

        // Find description text widget in that same container
        var descWidget = null;
        container.querySelectorAll(':scope > .elementor-widget, :scope > .elementor-element').forEach(function(w) {
            var type = w.getAttribute('data-widget_type') || '';
            if (/^(text-editor|text)\b/.test(type)) descWidget = w;
        });
        // Fallback: search anywhere in section
        if (!descWidget) {
            section.querySelectorAll('.elementor-widget').forEach(function(w) {
                var type = w.getAttribute('data-widget_type') || '';
                if (/^(text-editor|text)\b/.test(type)) descWidget = w;
            });
        }

        // Append slider to container, then move desc just before it → title → desc → slider
        container.appendChild(wrap);
        if (descWidget) {
            container.insertBefore(descWidget, wrap);
        }
    }

    document.addEventListener('DOMContentLoaded', placeSlider);
    window.addEventListener('load', placeSlider);
    setTimeout(placeSlider, 500);
    setTimeout(placeSlider, 1500);
}());
