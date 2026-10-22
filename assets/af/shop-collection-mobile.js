(function(){
  if (window.innerWidth > 600) return;
  // Fix "Shop by Collection" title + VIEW MORE button inline on mobile
  function fixShopCollectionRow() {
    // Find the heading with "Shop" + "Collection" text
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && /shop/i.test(h.textContent) && /collection/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    // Find VIEW MORE button nearby
    var btn = null;
    document.querySelectorAll('a,button').forEach(function(b) {
      if (!btn && /view\s*more/i.test(b.textContent)) btn = b;
    });
    if (!btn) return;
    // Find their common ancestor row container
    var row = heading.closest('.e-con, .elementor-container, .elementor-row, section');
    if (!row) return;
    if (row.dataset.shopRowFixed) return;
    row.dataset.shopRowFixed = '1';
    var sp = function(el,p,v){ el.style.setProperty(p,v,'important'); };
    sp(row,'display','flex');
    sp(row,'flex-direction','row');
    sp(row,'align-items','center');
    sp(row,'justify-content','space-between');
    sp(row,'flex-wrap','nowrap');
    sp(row,'width','100%');
    sp(row,'overflow','visible');
    sp(row,'box-sizing','border-box');
    sp(row,'padding-left','12px');
    sp(row,'padding-right','12px');
    // Title: bigger font, take available space
    sp(heading,'font-size','20px');
    sp(heading,'line-height','1.3');
    sp(heading,'white-space','normal');
    sp(heading,'flex','1 1 auto');
    sp(heading,'min-width','0');
    sp(heading,'padding-left','0');
    sp(heading,'margin-left','0');
    sp(heading,'max-width','none');
    // Button wrapper: push to right
    var btnWrap = btn.closest('.elementor-widget,.e-con') || btn.parentElement;
    sp(btnWrap,'flex-shrink','0');
    sp(btnWrap,'margin-left','auto');
  }

  // Generic helper: fix any section with a heading + "MORE LIKE THIS" or "VIEW MORE" button inline
  function fixSectionRow(headingText) {
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && headingText.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    var btn = null;
    // Find nearest MORE LIKE THIS / VIEW MORE button — search from heading's section outward
    var sec = heading.closest('.e-con, .elementor-section, section') || document.body;
    sec.querySelectorAll('a,button').forEach(function(b) {
      if (!btn && /more\s*like\s*this|view\s*more/i.test(b.textContent)) btn = b;
    });
    var row = heading.closest('.e-con, .elementor-container, .elementor-row, section');
    if (!row) return;
    if (row.dataset.sectionRowFixed) return;
    row.dataset.sectionRowFixed = '1';
    var sp = function(el,p,v){ el.style.setProperty(p,v,'important'); };
    sp(row,'display','flex');
    sp(row,'flex-direction','row');
    sp(row,'align-items','center');
    sp(row,'justify-content','space-between');
    sp(row,'flex-wrap','nowrap');
    sp(row,'width','100%');
    sp(row,'overflow','visible');
    sp(row,'box-sizing','border-box');
    sp(row,'padding-left','12px');
    sp(row,'padding-right','12px');
    sp(heading,'font-size','20px');
    sp(heading,'line-height','1.3');
    sp(heading,'white-space','normal');
    sp(heading,'flex','1 1 auto');
    sp(heading,'min-width','0');
    sp(heading,'padding-left','0');
    sp(heading,'margin-left','0');
    sp(heading,'max-width','none');
    if (btn) {
      var btnWrap = btn.closest('.elementor-widget,.e-con') || btn.parentElement;
      sp(btnWrap,'flex-shrink','0');
      sp(btnWrap,'margin-left','auto');
    }
  }

  function fixAllSectionRows() {
    fixSectionRow(/trending\s*today/i);
    fixSectionRow(/new\s*arrivals/i);
    fixSectionRow(/shop.*collection|collection.*shop/i);
  }

  document.addEventListener('DOMContentLoaded', fixAllSectionRows);
  window.addEventListener('load', fixAllSectionRows);
  setTimeout(fixAllSectionRows, 400);

  // Tag the "Trending Today" section with a marker class so its price/discount
  // style can be scoped via CSS to match the Shop by Collection cards.
  function tagTrendingTodaySection() {
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && /trending\s*today/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;
    // Walk up from the heading until we find an ancestor that actually
    // contains the price elements — the heading's own .e-con may only
    // wrap the title row, not the product grid below it.
    var node = heading;
    var sec = null;
    while (node && node !== document.body) {
      if (node.querySelector && node.querySelector('.price-section, .price, li.product')) {
        sec = node;
        break;
      }
      node = node.parentElement;
    }
    if (!sec) return;
    if (!sec.classList.contains('af-trending-section')) sec.classList.add('af-trending-section');

    // Real markup confirmed via DevTools: .trending-card > .trending-content
    // > .price-section > .price / .old-price / .discount (no <del> tag at all).
    // .trending-card only exists in this section, so it's a safe, unambiguous scope.
    document.querySelectorAll('.trending-card .discount, .trending-card .discount-percentage').forEach(function(el) {
      el.style.setProperty('color', 'var(--af-save, #2e7d32)', 'important');
      el.style.setProperty('font-weight', '600', 'important');
      el.querySelectorAll('*').forEach(function(c) { c.style.setProperty('color', 'var(--af-save, #2e7d32)', 'important'); });
    });
    document.querySelectorAll('.trending-card .old-price').forEach(function(el) {
      el.style.setProperty('display', 'inline-block', 'important');
      el.style.setProperty('position', 'relative', 'important');
      el.style.setProperty('color', '#999', 'important');
      el.style.setProperty('text-decoration', 'line-through', 'important');
      el.style.setProperty('text-decoration-color', '#999', 'important');
      el.style.setProperty('text-decoration-thickness', '1.5px', 'important');
      el.querySelectorAll('*').forEach(function(c) {
        c.style.setProperty('color', '#999', 'important');
        c.style.setProperty('text-decoration', 'line-through', 'important');
      });
    });
    document.querySelectorAll('.trending-card .add-to-cart-btn, .trending-card .add_to_cart_button, .trending-card a.button').forEach(function(el) {
      el.style.setProperty('background-color', '#c9a84c', 'important');
      el.style.setProperty('border-color', '#c9a84c', 'important');
      // inline !important beats any stylesheet :hover, so bind the swap here
      if (!el.dataset.afHoverBound) {
        el.dataset.afHoverBound = '1';
        el.addEventListener('mouseenter', function(){
          el.style.setProperty('background-color', '#8b6a2b', 'important');
          el.style.setProperty('border-color', '#8b6a2b', 'important');
        });
        el.addEventListener('mouseleave', function(){
          el.style.setProperty('background-color', '#c9a84c', 'important');
          el.style.setProperty('border-color', '#c9a84c', 'important');
        });
      }
      // spacing comes from the flex gap above; force the layout in case the
      // theme left this button inline-block
      el.style.setProperty('display', 'inline-flex', 'important');
      el.style.setProperty('align-items', 'center', 'important');
      el.style.setProperty('justify-content', 'center', 'important');
      el.style.setProperty('gap', '8px', 'important');
    });
  }
  document.addEventListener('DOMContentLoaded', tagTrendingTodaySection);
  window.addEventListener('load', tagTrendingTodaySection);
  setTimeout(tagTrendingTodaySection, 400);
  setTimeout(tagTrendingTodaySection, 1200);
  setTimeout(tagTrendingTodaySection, 2500);

  // Fix WooCommerce product grid hover image gap.
  // Derives the aspect ratio from the main image's own naturalWidth/naturalHeight
  // so both images occupy the EXACT same box with no gap — regardless of each
  // product's image dimensions being portrait, landscape, or square.

  function applyHoverFix(card) {
    if (card.dataset.hoverFixed6) return;

    var allImgs = Array.from(card.querySelectorAll('img')).filter(function(img) {
      var src = img.getAttribute('src') || '';
      return src && src.indexOf('woocommerce-placeholder') === -1;
    });
    if (allImgs.length < 2) return;

    var mainImg  = allImgs[0];
    var hoverImg = allImgs[1];

    function build() {
      if (card.dataset.hoverFixed6) return;

      card.dataset.hoverFixed6 = '1';
      mainImg.classList.add('af-main-img');
      hoverImg.classList.add('af-hover-img');

      // UNIFORM box: fixed height for every card. The old per-image natural
      // ratio (padding-bottom) made each card a different height, so grids
      // (New Arrivals, etc.) staggered and hover images misaligned.
      var H = (window.innerWidth <= 520) ? 260 : 300;

      var wrap = document.createElement('div');
      wrap.className = 'af-img-ratio';
      wrap.style.setProperty('position',       'relative',    'important');
      wrap.style.setProperty('width',          '100%',        'important');
      wrap.style.setProperty('height',         H + 'px',      'important');
      wrap.style.setProperty('max-height',     H + 'px',      'important');
      wrap.style.setProperty('padding-bottom', '0',           'important');
      wrap.style.setProperty('overflow',       'hidden',      'important');
      wrap.style.setProperty('display',        'block',       'important');
      wrap.style.setProperty('margin',         '0',           'important');

      mainImg.parentElement.insertBefore(wrap, mainImg);
      wrap.appendChild(mainImg);
      wrap.appendChild(hoverImg);

      var link = wrap.parentElement;
      if (link) {
        link.style.setProperty('display',        'block',  'important');
        link.style.setProperty('width',          '100%',   'important');
        link.style.setProperty('height',         'auto',   'important');
        link.style.setProperty('padding-bottom', '0',      'important');
        link.style.setProperty('overflow',       'hidden', 'important');
      }

      [mainImg, hoverImg].forEach(function(img) {
        img.style.setProperty('position',        'absolute', 'important');
        img.style.setProperty('top',             '0',        'important');
        img.style.setProperty('left',            '0',        'important');
        img.style.setProperty('width',           '100%',     'important');
        img.style.setProperty('height',          '100%',     'important');
        img.style.setProperty('object-fit',      'cover',    'important');
        img.style.setProperty('object-position', 'center',   'important');
        img.style.setProperty('margin',          '0',        'important');
        img.style.setProperty('max-width',       'none',     'important');
      });

      mainImg.style.setProperty('z-index', '1', 'important');
      hoverImg.style.setProperty('z-index', '2', 'important');
      hoverImg.style.setProperty('opacity', '0', 'important');
      hoverImg.style.setProperty('transition', 'opacity 0.4s ease', 'important');

      card.addEventListener('mouseenter', function() {
        hoverImg.style.setProperty('opacity', '1', 'important');
      });
      card.addEventListener('mouseleave', function() {
        hoverImg.style.setProperty('opacity', '0', 'important');
      });
    }

    // Fixed-height box needs no natural dimensions — build immediately.
    build();
  }

  function fixProductHoverImages() {
    document.querySelectorAll('ul.products li.product, .woocommerce li.product, li.product').forEach(function(card) {
      applyHoverFix(card);
    });
  }

  document.addEventListener('DOMContentLoaded', fixProductHoverImages);
  window.addEventListener('load', fixProductHoverImages);
  setTimeout(fixProductHoverImages, 500);
  setTimeout(fixProductHoverImages, 1500);
  setTimeout(fixProductHoverImages, 3000);

  /* ── New Arrivals: comprehensive card fix ──────────────────────────────────
     The New Arrivals section renders li.product WITHOUT a .woocommerce parent,
     so CSS selectors like ".woocommerce ul.products li.product" never match.
     This function: tags the section, forces card-shell styles inline on each
     li.product, styles price correctly, hides text Wishlist/View links, and
     ensures the Add-to-Cart button is gold + Quick View is dark.              */
  function fixNewArrivalsCards() {
    var heading = null;
    document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title').forEach(function(h) {
      if (!heading && /new\s*arrivals/i.test(h.textContent)) heading = h;
    });
    if (!heading) return;

    // Walk up until we find an ancestor that contains li.product
    var node = heading, sec = null;
    while (node && node !== document.body) {
      if (node.querySelector && node.querySelector('li.product')) { sec = node; break; }
      node = node.parentElement;
    }
    if (!sec) return;
    if (!sec.classList.contains('af-new-arrivals')) sec.classList.add('af-new-arrivals');

    sec.querySelectorAll('li.product').forEach(function(card) {
      if (card.dataset.naFixed) return;
      card.dataset.naFixed = '1';

      // ── Card shell ──
      var sp = function(el, p, v) { el.style.setProperty(p, v, 'important'); };
      sp(card, 'background', '#fff');
      sp(card, 'border', '1px solid #ececec');
      sp(card, 'border-radius', '12px');
      sp(card, 'overflow', 'hidden');
      sp(card, 'display', 'flex');
      sp(card, 'flex-direction', 'column');
      sp(card, 'box-shadow', '0 2px 12px rgba(0,0,0,0.08)');
      sp(card, 'transition', 'box-shadow 0.25s, transform 0.25s');
      card.addEventListener('mouseenter', function() {
        sp(card, 'box-shadow', '0 8px 28px rgba(0,0,0,0.14)');
        sp(card, 'transform', 'translateY(-2px)');
      });
      card.addEventListener('mouseleave', function() {
        sp(card, 'box-shadow', '0 2px 12px rgba(0,0,0,0.08)');
        sp(card, 'transform', 'translateY(0)');
      });

      // ── Title ──
      var title = card.querySelector('.woocommerce-loop-product__title, h2, h3');
      if (title) {
        sp(title, 'font-size', '13.5px');
        sp(title, 'font-weight', '700');
        sp(title, 'line-height', '1.45');
        sp(title, 'color', '#1a1a1a');
        sp(title, 'margin', '10px 14px 6px');
        sp(title, 'padding', '0');
        sp(title, 'display', '-webkit-box');
        sp(title, '-webkit-line-clamp', '3');
        sp(title, '-webkit-box-orient', 'vertical');
        sp(title, 'overflow', 'hidden');
      }

      // ── Price: style del + ins + inject discount badge ──
      var priceEl = card.querySelector('.price');
      if (priceEl) {
        sp(priceEl, 'display', 'flex');
        sp(priceEl, 'flex-direction', 'row');
        sp(priceEl, 'flex-wrap', 'nowrap');
        sp(priceEl, 'align-items', 'center');
        sp(priceEl, 'gap', '6px');
        sp(priceEl, 'margin', '0 14px 8px');
        sp(priceEl, 'padding', '0');
        sp(priceEl, 'white-space', 'nowrap');

        var ins = priceEl.querySelector('ins');
        if (ins) { sp(ins, 'text-decoration', 'none'); sp(ins, 'font-weight', '700'); sp(ins, 'color', '#1a1a1a'); sp(ins, 'font-size', '15px'); }

        var del = priceEl.querySelector('del');
        if (del) {
          sp(del, 'display', 'inline-block'); sp(del, 'position', 'relative');
          sp(del, 'color', '#999'); sp(del, 'font-weight', '400'); sp(del, 'font-size', '13px');
          sp(del, 'text-decoration', 'line-through'); sp(del, 'text-decoration-color', '#999');
          del.querySelectorAll('*').forEach(function(c) { sp(c, 'color', '#999'); sp(c, 'text-decoration', 'line-through'); });
        }

        // Inject "(X% off)" badge if sale price exists and not already present
        if (ins && del && !priceEl.querySelector('.af-disc-badge')) {
          var saleRaw = ins.textContent.replace(/[^0-9.]/g, '');
          var origRaw = del.textContent.replace(/[^0-9.]/g, '');
          var sale = parseFloat(saleRaw), orig = parseFloat(origRaw);
          if (orig > 0 && sale < orig) {
            var pct = Math.round((orig - sale) / orig * 100);
            var badge = document.createElement('span');
            badge.className = 'af-disc-badge';
            badge.textContent = '(' + pct + '% off)';
            sp(badge, 'color', 'var(--af-save, #2e7d32)');
            sp(badge, 'font-weight', '600');
            sp(badge, 'font-size', '13px');
            priceEl.appendChild(badge);
          }
        }
      }

      // ── Hide raw Wishlist/View text links ──
      card.querySelectorAll('.yith-wcwl-add-to-wishlist, .yith-wcwl-add-button, [class*="wishlist-popup"]').forEach(function(el) {
        sp(el, 'display', 'none');
      });

      // ── Style Add to Cart button gold ──
      var addCartBtn = card.querySelector('.add_to_cart_button, a.button, button.button');
      if (addCartBtn) {
        sp(addCartBtn, 'display', 'flex');
        sp(addCartBtn, 'align-items', 'center');
        sp(addCartBtn, 'justify-content', 'center');
        sp(addCartBtn, 'gap', '8px');
        sp(addCartBtn, 'background', '#c9a84c');
        // these styles are inline !important, so a stylesheet :hover can never
        // out-rank them — the hover swap has to be scripted
        if (!addCartBtn.dataset.afHoverBound) {
          addCartBtn.dataset.afHoverBound = '1';
          addCartBtn.addEventListener('mouseenter', function(){ sp(addCartBtn, 'background', '#8b6a2b'); });
          addCartBtn.addEventListener('mouseleave', function(){ sp(addCartBtn, 'background', '#c9a84c'); });
        }
        sp(addCartBtn, 'color', '#fff');
        sp(addCartBtn, 'border', 'none');
        sp(addCartBtn, 'border-radius', '7px');
        sp(addCartBtn, 'font-size', '13px');
        sp(addCartBtn, 'font-weight', '600');
        sp(addCartBtn, 'padding', '10px 14px');
        sp(addCartBtn, 'text-decoration', 'none');
        sp(addCartBtn, 'text-align', 'center');
        sp(addCartBtn, 'width', 'calc(100% - 28px)');
        sp(addCartBtn, 'margin', '0 14px 14px');
        sp(addCartBtn, 'cursor', 'pointer');
        sp(addCartBtn, 'box-sizing', 'border-box');
      }

      // Style View/Quick View link as dark button
      var viewBtn = card.querySelector('a[class*="quick"], a[class*="view"]:not(.add_to_cart_button), .view-btn, [data-quick-view]');
      if (viewBtn && viewBtn !== addCartBtn) {
        sp(viewBtn, 'display', 'flex');
        sp(viewBtn, 'align-items', 'center');
        sp(viewBtn, 'justify-content', 'center');
        sp(viewBtn, 'gap', '8px');
        sp(viewBtn, 'background', '#1a1a1a');
        // inline !important styles: hover must be scripted, same as Add to Cart
        if (!viewBtn.dataset.afHoverBound) {
          viewBtn.dataset.afHoverBound = '1';
          viewBtn.addEventListener('mouseenter', function(){ sp(viewBtn, 'background', '#333'); });
          viewBtn.addEventListener('mouseleave', function(){ sp(viewBtn, 'background', '#1a1a1a'); });
        }
        sp(viewBtn, 'color', '#fff');
        sp(viewBtn, 'border', 'none');
        sp(viewBtn, 'border-radius', '7px');
        sp(viewBtn, 'font-size', '13px');
        sp(viewBtn, 'font-weight', '600');
        sp(viewBtn, 'padding', '10px 14px');
        sp(viewBtn, 'text-decoration', 'none');
        sp(viewBtn, 'text-align', 'center');
        sp(viewBtn, 'width', 'calc(100% - 28px)');
        sp(viewBtn, 'margin', '0 14px 14px');
        sp(viewBtn, 'cursor', 'pointer');
        sp(viewBtn, 'box-sizing', 'border-box');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', fixNewArrivalsCards);
  window.addEventListener('load', fixNewArrivalsCards);
  setTimeout(fixNewArrivalsCards, 600);
  setTimeout(fixNewArrivalsCards, 1500);
  setTimeout(fixNewArrivalsCards, 3000);

}());
