/* "Try It On Your Wall" — upload-a-photo AR mockup (no camera, no cost).
   User uploads a photo of their wall; the product image is overlaid,
   draggable, and scalable. A "wall width" input drives real-world scale. */
(function () {
  'use strict';

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }

  function init() {
    var overlay = $('#af-arw-overlay');
    var openBtn = $('#af-arw-open');
    if (!overlay || !openBtn) return;

    var stage       = $('#af-arw-stage', overlay);
    var wallImg     = $('#af-arw-wall', overlay);
    var artImg      = $('#af-arw-art', overlay);
    var placeholder = $('#af-arw-placeholder', overlay);
    var wallInput   = $('#af-arw-wall-file', overlay);
    var scaleRange  = $('#af-arw-scale', overlay);
    var wallWidthIn = $('#af-arw-wallwidth', overlay);
    var sizeSelect  = $('#af-arw-size', overlay);
    var resetBtn    = $('#af-arw-reset', overlay);
    var saveBtn     = $('#af-arw-save', overlay);

    var artURL = openBtn.getAttribute('data-art');
    if (artURL && artImg) artImg.src = artURL;

    // Auto-open when arriving from a listing "Try on Wall" link (#try-on-wall)
    function maybeAutoOpen() {
      if (window.location.hash === '#try-on-wall') { open(); }
    }

    // ── open / close ──
    function open() { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function close() { overlay.classList.remove('open'); document.body.style.overflow = ''; }
    openBtn.addEventListener('click', open);
    overlay.querySelectorAll('[data-arw-close]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (e.target === el) close();
      });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    maybeAutoOpen();
    window.addEventListener('hashchange', maybeAutoOpen);

    // ── wall photo upload ──
    wallInput.addEventListener('change', function () {
      var file = this.files && this.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        wallImg.src = ev.target.result;
        wallImg.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
        artImg.style.display = 'block';
        applyScale();
      };
      reader.readAsDataURL(file);
    });

    // ── scale: combines the manual slider + real-world wall-width math ──
    function applyScale() {
      var stageW = stage.getBoundingClientRect().width || 500;
      // Real-world scaling: if user gives wall width (inches) and picks an art size,
      // art pixel width = stageW * (artWidthIn / wallWidthIn).
      var wallW = parseFloat(wallWidthIn.value);
      var artW  = parseFloat(sizeSelect.value); // inches, from selected size
      var base;
      if (wallW > 0 && artW > 0) {
        base = stageW * (artW / wallW);
      } else {
        base = stageW * 0.35; // default ~35% of wall width
      }
      var mult = parseFloat(scaleRange.value) / 100; // fine-tune slider 50–150%
      artImg.style.width = Math.max(40, base * mult) + 'px';
      artImg.style.height = 'auto';
    }
    scaleRange.addEventListener('input', applyScale);
    wallWidthIn.addEventListener('input', applyScale);
    if (sizeSelect) sizeSelect.addEventListener('change', applyScale);

    // ── drag the artwork around the wall ──
    var dragging = false, sx = 0, sy = 0, ox = 0, oy = 0;
    function pt(e) { return e.touches ? e.touches[0] : e; }
    artImg.addEventListener('pointerdown', function (e) {
      dragging = true; artImg.classList.add('dragging');
      var p = pt(e);
      sx = p.clientX; sy = p.clientY;
      var st = getComputedStyle(artImg);
      ox = parseFloat(st.left) || stage.clientWidth / 2;
      oy = parseFloat(st.top) || stage.clientHeight / 2;
      artImg.style.transform = 'translate(-50%,-50%)';
      e.preventDefault();
    });
    document.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      var p = pt(e);
      artImg.style.left = (ox + (p.clientX - sx)) + 'px';
      artImg.style.top  = (oy + (p.clientY - sy)) + 'px';
    });
    document.addEventListener('pointerup', function () {
      dragging = false; artImg.classList.remove('dragging');
    });

    // ── reset ──
    resetBtn.addEventListener('click', function () {
      artImg.style.left = '50%'; artImg.style.top = '50%';
      scaleRange.value = 100; applyScale();
    });

    // ── save a snapshot (compose wall + art onto a canvas, download) ──
    saveBtn.addEventListener('click', function () {
      if (!wallImg.src || wallImg.style.display === 'none') {
        alert('Please upload a photo of your wall first.');
        return;
      }
      try {
        var rect = stage.getBoundingClientRect();
        var canvas = document.createElement('canvas');
        canvas.width = rect.width; canvas.height = rect.height;
        var ctx = canvas.getContext('2d');
        // wall (cover)
        drawCover(ctx, wallImg, rect.width, rect.height);
        // art at its on-screen position/size
        var ar = artImg.getBoundingClientRect();
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 20; ctx.shadowOffsetY = 8;
        ctx.drawImage(artImg, ar.left - rect.left, ar.top - rect.top, ar.width, ar.height);
        ctx.restore();
        var link = document.createElement('a');
        link.download = 'my-wall-preview.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      } catch (err) {
        alert('Could not save preview (the artwork image may be cross-origin). You can screenshot instead.');
      }
    });

    function drawCover(ctx, img, w, h) {
      var iw = img.naturalWidth, ih = img.naturalHeight;
      var scale = Math.max(w / iw, h / ih);
      var dw = iw * scale, dh = ih * scale;
      ctx.drawImage(img, (w - dw) / 2, (h - dh) / 2, dw, dh);
    }

    window.addEventListener('resize', applyScale);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
