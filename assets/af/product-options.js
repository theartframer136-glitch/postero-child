(function(){
  var opts = document.querySelector('.af-opts');

  // ── Variable-product bridge: our engine is the UI; silently pick the
  // first Woo variation so add-to-cart validates, then hide Woo's selects.
  var vform = document.querySelector('form.variations_form');
  if (opts && vform) {
    vform.querySelectorAll('.variations select').forEach(function(sel){
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value) { sel.value = sel.options[i].value; break; }
      }
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
    if (window.jQuery) jQuery(vform).trigger('check_variations');
    var css = document.createElement('style');
    css.textContent = '.af-opts ~ * .variations, form.variations_form table.variations,' +
      'form.variations_form .reset_variations, form.variations_form .woocommerce-variation.single_variation,' +
      '.variable-items-wrapper{display:none !important;}';
    document.head.appendChild(css);
  }
  if (!opts) return;

  var cfg = JSON.parse(opts.dataset.config || '{}');

  // ── Wall-suitability hint follows the selected size
  var hintEl = document.getElementById('afWallHint');
  function refreshExtras(){
    var sz = (opts.querySelector('input[name="af_size"]') || {}).value;
    if (hintEl) hintEl.textContent = (cfg.hints && cfg.hints[sz]) ? '📐 ' + cfg.hints[sz] : '';
    // MRP strikethrough plus the matching discount badge, so the product page
    // shows the saving too. The multiplier is this product's own — the same
    // one af_mrp_multiplier() gave tools/apply-mrp-markup.php when it wrote
    // the card price — because the live price recomputes as the size and frame
    // change, so the strike-through has to be derived here rather than read.
    var live = document.getElementById('af-live-price'),
        mrp  = document.getElementById('af-live-mrp'),
        disc = document.getElementById('af-live-disc');
    if (live && mrp) {
      var num = parseFloat((live.textContent || '').replace(/[^0-9.]/g, ''));
      var symM = (live.textContent || '').match(/^[^0-9]*/);
      var mult = parseFloat(opts.dataset.mrpMult) || 1.40;
      if (num) {
        var mrpVal = num * mult;
        mrp.textContent = (symM ? symM[0] : '$') + mrpVal.toFixed(2);
        if (disc) {
          var pct = Math.round((mrpVal - num) / mrpVal * 100);
          disc.textContent = pct > 0 ? '(' + pct + '% OFF)' : '';
        }

        // The price beside the title follows the panel.
        //
        // It did not, and the two sat on screen together: choosing 3×5 ft
        // moved the panel to $100.00 while the heading still read $80.00.
        // The cart charged the panel price, so the heading was the wrong one —
        // but a shopper cannot know that, and the one they are most likely to
        // read is the big one at the top.
        //
        // Everything needed is already computed here for the panel's own
        // strike-through, so the heading is written from the same two numbers
        // rather than from a second derivation that could drift from it. The
        // screen-reader sentences WooCommerce pairs with del/ins are rewritten
        // too: stale assistive text is the same bug, only quieter.
        var head = document.querySelector('.summary .price, .entry-summary .price, p.price');
        if (head) {
          var sym2 = symM ? symM[0] : '$';
          var fmt  = function(v){ return sym2 + v.toFixed(2); };
          var del  = head.querySelector('del'), ins = head.querySelector('ins');
          var cur  = (ins || head).querySelector('.woocommerce-Price-amount, .amount');
          if (cur) cur.innerHTML = fmt(num);
          var was = del ? del.querySelector('.woocommerce-Price-amount, .amount') : null;
          if (was) was.innerHTML = fmt(mrpVal);
          head.querySelectorAll('.screen-reader-text').forEach(function(sr){
            if (/original price/i.test(sr.textContent)) sr.textContent = 'Original price was: ' + fmt(mrpVal) + '.';
            else if (/current price/i.test(sr.textContent)) sr.textContent = 'Current price is: ' + fmt(num) + '.';
          });
          var pctEl = head.querySelector('.af-pct-off');
          if (pctEl) {
            var pct2 = Math.round((mrpVal - num) / mrpVal * 100);
            pctEl.textContent = pct2 > 0 ? '(' + pct2 + '% off)' : '';
          }
        }
      }
    }
  }
  opts.addEventListener('click', function(){ setTimeout(refreshExtras, 30); });
  // the size dropdown also changes by keyboard, which never fires a click
  opts.addEventListener('change', function(){ setTimeout(refreshExtras, 30); });
  refreshExtras();

  // ── Dimension overlay toggle on the main product image (spec: ON/OFF)
  var gallery = document.querySelector('.woocommerce-product-gallery__wrapper') ||
                document.querySelector('.woocommerce-product-gallery');
  if (gallery) {
    var holder = gallery.querySelector('.woocommerce-product-gallery__image') || gallery;
    holder.style.position = 'relative';
    var ov = document.createElement('div');
    ov.className = 'af-dim-overlay';
    ov.innerHTML = '<div class="af-dim-w"><span id="afDimW"></span></div><div class="af-dim-h"><span id="afDimH"></span></div>';
    holder.appendChild(ov);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'af-dim-toggle';
    btn.innerHTML = '📐 Show dimensions';
    // Place after the categories/tags meta block (user request), with fallbacks
    var metaEl = document.querySelector('.summary .product_meta') ||
                 document.querySelector('.product_meta');
    (metaEl || document.querySelector('.woocommerce-product-gallery') || holder).after(btn);
    function dims(){
      var sz = (opts.querySelector('input[name="af_size"]') || {}).value || '';
      var m = sz.match(/(\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) ft \((\d+)×(\d+) in\)/);
      if (m) {
        document.getElementById('afDimH').textContent = m[1] + ' ft (' + m[3] + ' in)';
        document.getElementById('afDimW').textContent = m[2] + ' ft (' + m[4] + ' in)';
      }
    }
    btn.addEventListener('click', function(){
      var on = ov.classList.toggle('show');
      btn.classList.toggle('on', on);
      btn.innerHTML = on ? '📐 Hide dimensions' : '📐 Show dimensions';
      dims();
    });
    opts.addEventListener('click', function(){ setTimeout(dims, 30); });
    opts.addEventListener('change', function(){ setTimeout(dims, 30); });

    // ── Frame-color preview: swap main image to the matching frame photo
    var colorImgs = {
      'Black':     'https://theartframer.us/wp-content/uploads/2026/03/black-frame.webp',
      'Silver':    'https://theartframer.us/wp-content/uploads/2026/03/silver-frame.webp',
      'Gold':      'https://theartframer.us/wp-content/uploads/2026/03/gold-frame.webp',
      'Rose Gold': 'https://theartframer.us/wp-content/uploads/2026/03/rosegold-frame.webp'
    };
    var mainImg = holder.querySelector('img');
    if (mainImg) {
      var orig = { src: mainImg.src, srcset: mainImg.getAttribute('srcset') };
      opts.querySelectorAll('.af-swatch').forEach(function(sw){
        sw.addEventListener('dblclick', function(){ restore(); });
        sw.addEventListener('click', function(){
          var u = colorImgs[sw.dataset.val];
          if (!u) return;
          mainImg.src = u; mainImg.removeAttribute('srcset');
          clearTimeout(mainImg._afT);
          mainImg._afT = setTimeout(restore, 3500); // brief preview, then back to art
        });
      });
      function restore(){ mainImg.src = orig.src; if (orig.srcset) mainImg.setAttribute('srcset', orig.srcset); }
    }

    // ── Video labels (spec: "Watch how it looks in real homes") + DIY guides
    var cap = document.createElement('div');
    cap.className = 'af-vid-caption';
    cap.innerHTML = '▶ <span>Watch how it looks in real homes</span>' +
      '<div class="af-diy-strip">' +
      '<a href="https://www.youtube.com/channel/UC_GX4vXRQrN4GsvSfgmZxYw" target="_blank" rel="noopener">🎬 Product &amp; framing videos</a>' +
      '<a href="https://www.youtube.com/channel/UC_GX4vXRQrN4GsvSfgmZxYw/search?query=DIY" target="_blank" rel="noopener">🛠️ DIY hanging guide</a>' +
      '</div>';
    btn.after(cap);
  }

  // ── Verified Buyers badge beside the star rating
  var rating = document.querySelector('.woocommerce-product-rating');
  if (rating && !rating.querySelector('.af-verified')) {
    var b = document.createElement('span');
    b.className = 'af-verified';
    b.textContent = '✓ Verified Buyers';
    rating.appendChild(b);
  }
})();
