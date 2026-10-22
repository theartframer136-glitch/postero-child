(function() {
  var body = document.body;
  if (!body.classList.contains('tax-product_cat') &&
      !body.classList.contains('woocommerce-page') &&
      !/\/product-category\/|\/shop\//.test(window.location.pathname)) return;

  function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

  function fixShopCard(card) {
    if (card.dataset.afShopFixed) return;
    card.dataset.afShopFixed = '1';

    /* v5 — structure guard: this transform was designed for the CLASSIC card
       markup where the image link is a DIRECT child of li.product. On nested
       "product-block" layouts a partial transform exposes the theme's hover
       overlay and adds a stray 1:1 spacer — so on those cards we do NOTHING
       and let the theme's own (correct) styling stand untouched. */
    var linkProbe = card.querySelector('a.woocommerce-loop-product__link');
    if (!linkProbe || linkProbe.parentNode !== card) return;

    /* v4 — DEFINITIVE FIX
       1. Hide ALL unknown card children (theme overlay, badges) → eliminates blank space
       2. padding-top:100% wrapper → bulletproof 1:1 ratio, content-independent
       3. mainLink position:absolute inset:0 → fills wrap, zero layout contribution
       4. Custom .af-ov overlay → position:absolute inset:0, sibling of link (not nested in <a>)
       5. pointer-events:none on overlay, pointer-events:auto on buttons
    */

    // ── 1. Card shell
    sp(card,'background',    '#fff');
    sp(card,'border-radius', '10px');
    sp(card,'overflow',      'hidden');
    sp(card,'box-shadow',    '0 2px 16px rgba(0,0,0,.08)');
    sp(card,'border',        '1px solid #ede9e0');
    sp(card,'display',       'flex');
    sp(card,'flex-direction','column');
    sp(card,'transition',    'box-shadow .25s ease, transform .25s ease');
    card.addEventListener('mouseenter',function(){
      card.style.setProperty('box-shadow','0 8px 36px rgba(0,0,0,.14)','important');
      card.style.setProperty('transform','translateY(-2px)','important');
    });
    card.addEventListener('mouseleave',function(){
      card.style.setProperty('box-shadow','0 2px 16px rgba(0,0,0,.08)','important');
      card.style.setProperty('transform','translateY(0)','important');
    });

    // ── 2. Gather data
    var pd        = card.querySelector('.af-pd');
    var mainLink  = card.querySelector('a.woocommerce-loop-product__link');
    if (!mainLink) return;
    var themeCart = card.querySelector('a.add_to_cart_button,a.ajax_add_to_cart,a.button.product_type_simple');
    var cartUrl   = (pd&&pd.dataset.cart)||(themeCart&&themeCart.href)||mainLink.href||'#';
    var productUrl= (pd&&pd.dataset.url)||mainLink.href||'#';

    // Collect wishlist link data for cloning (before hiding)
    var wlSrc = card.querySelector('a.add_to_wishlist,.yith-wcwl-add-to-wishlist a,a[class*="wishlist"]');

    // ── 3. HIDE all non-content direct children → eliminates blank space absolutely
    //      SAFETY: never hide a wrapper that CONTAINS the product content.
    //      (Postero "product-block" layouts nest link/title/price inside a
    //      wrapper div — hiding it blanked the whole card on category pages.)
    Array.from(card.children).forEach(function(c){
      if (c===mainLink) return;
      var cls=c.classList;
      if (cls.contains('woocommerce-loop-product__title')) return;
      if (cls.contains('woocommerce-product-rating'))     return;
      if (cls.contains('price'))                          return;
      if (c.contains(mainLink)) return;
      sp(c,'display','none');
    });

    // ── 4. Build .af-img-wrap (only once)
    if (!card.querySelector('.af-img-wrap')) {

      var wrap=document.createElement('div');
      wrap.className='af-img-wrap';
      sp(wrap,'position',   'relative');
      sp(wrap,'width',      '100%');
      sp(wrap,'padding-top','100%');  // bulletproof 1:1 — height = width, content-independent
      sp(wrap,'overflow',   'hidden');
      sp(wrap,'background', '#f5f2ed');
      sp(wrap,'flex-shrink','0');

      // Insert relative to mainLink's ACTUAL parent (mainLink may be nested
      // inside a theme wrapper, not a direct child of the card — the old
      // card.insertBefore threw NotFoundError and aborted styling mid-loop,
      // leaving remaining cards blank).
      mainLink.parentNode.insertBefore(wrap, mainLink);
      wrap.appendChild(mainLink);

      // mainLink fills entire wrap
      sp(mainLink,'position','absolute');
      sp(mainLink,'top',    '0');
      sp(mainLink,'left',   '0');
      sp(mainLink,'right',  '0');
      sp(mainLink,'bottom', '0');
      sp(mainLink,'width',  '100%');
      sp(mainLink,'height', '100%');
      sp(mainLink,'display','block');
      sp(mainLink,'z-index','1');

      // Image fills mainLink with zoom on hover
      var img=mainLink.querySelector('img');
      if (img) {
        sp(img,'position',  'absolute');
        sp(img,'top',       '0');
        sp(img,'left',      '0');
        sp(img,'width',     '100%');
        sp(img,'height',    '100%');
        sp(img,'object-fit','cover');
        sp(img,'display',   'block');
        sp(img,'transition','transform .5s cubic-bezier(.25,.46,.45,.94)');
        card.addEventListener('mouseenter',function(){ img.style.setProperty('transform','scale(1.06)','important'); });
        card.addEventListener('mouseleave',function(){ img.style.setProperty('transform','scale(1)',   'important'); });
      }

      // Diagonal sale ribbon
      var ribbon=mainLink.querySelector('.onsale');
      if (!ribbon) ribbon=wrap.querySelector('.onsale');
      if (ribbon) {
        // Diagonal corner ribbon across the top-left corner. The band's ends
        // run off the card and are clipped by .af-img-wrap (overflow:hidden),
        // leaving a clean gold "SALE!" banner across the corner.
        sp(ribbon,'position',      'absolute');
        sp(ribbon,'top',           '15px');
        sp(ribbon,'left',          '-46px');
        sp(ribbon,'width',         '160px');
        sp(ribbon,'background',    'linear-gradient(135deg,#ecc768,#cf9f2e)');
        sp(ribbon,'color',         '#fff');
        sp(ribbon,'font-size',     '12px');
        sp(ribbon,'font-weight',   '800');
        sp(ribbon,'text-align',    'center');
        sp(ribbon,'padding',       '7px 0');
        sp(ribbon,'transform',     'rotate(-45deg)');
        sp(ribbon,'z-index',       '10');
        sp(ribbon,'letter-spacing','0.12em');
        sp(ribbon,'text-transform','uppercase');
        sp(ribbon,'text-shadow',   '0 1px 2px rgba(0,0,0,.25)');
        sp(ribbon,'line-height',   '1.3');
        sp(ribbon,'min-width',     'unset');
        sp(ribbon,'border-radius', '0');
        sp(ribbon,'margin',        '0');
        sp(ribbon,'display',       'block');
        sp(ribbon,'box-shadow',    '0 3px 8px rgba(0,0,0,.28)');
      }

      // Custom overlay — sibling of mainLink inside wrap (NOT nested in <a>)
      var ov=document.createElement('div');
      ov.className='af-ov';
      sp(ov,'position',       'absolute');
      sp(ov,'top',            '0');
      sp(ov,'left',           '0');
      sp(ov,'right',          '0');
      sp(ov,'bottom',         '0');
      sp(ov,'z-index',        '2');
      sp(ov,'display',        'flex');
      sp(ov,'align-items',    'flex-end');
      sp(ov,'justify-content','center');
      sp(ov,'gap',            '10px');
      sp(ov,'padding',        '0 12px 16px');
      sp(ov,'background',     'rgba(12,9,5,.50)');
      sp(ov,'opacity',        '0');
      sp(ov,'transition',     'opacity .3s ease');
      sp(ov,'pointer-events', 'none');
      sp(ov,'box-sizing',     'border-box');
      card.addEventListener('mouseenter',function(){ ov.style.setProperty('opacity','1','important'); });
      card.addEventListener('mouseleave',function(){ ov.style.setProperty('opacity','0','important'); });

      function iconBtn(el) {
        sp(el,'width',          '36px');
        sp(el,'height',         '36px');
        sp(el,'border-radius',  '50%');
        sp(el,'background',     'rgba(255,255,255,.9)');
        sp(el,'color',          '#222');
        sp(el,'display',        'inline-flex');
        sp(el,'align-items',    'center');
        sp(el,'justify-content','center');
        sp(el,'border',         'none');
        sp(el,'cursor',         'pointer');
        sp(el,'flex-shrink',    '0');
        sp(el,'text-decoration','none');
        sp(el,'pointer-events', 'auto');
        sp(el,'transition',     'background .2s');
      }

      // Wishlist — clone from theme element to preserve YITH data attributes
      var wlBtn;
      if (wlSrc) {
        wlBtn=wlSrc.cloneNode(false);
        wlBtn.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
      } else {
        wlBtn=document.createElement('a');
        wlBtn.href='#';
        wlBtn.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
      }
      iconBtn(wlBtn);

      // Add to Cart — primary gold pill button
      var atcBtn=document.createElement('a');
      atcBtn.href=cartUrl;
      atcBtn.className='af-ov-atc';
      atcBtn.innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Add to Cart</span>';
      sp(atcBtn,'display',        'inline-flex');
      sp(atcBtn,'align-items',    'center');
      sp(atcBtn,'gap',            '8px');
      atcBtn.addEventListener('mouseenter', function(){ sp(atcBtn,'background','#8b6a2b'); });
      atcBtn.addEventListener('mouseleave', function(){ sp(atcBtn,'background','#c9a84c'); });
      sp(atcBtn,'padding',        '9px 18px');
      sp(atcBtn,'background',     '#c9a84c');
      sp(atcBtn,'color',          '#fff');
      sp(atcBtn,'border-radius',  '4px');
      sp(atcBtn,'font-size',      '12px');
      sp(atcBtn,'font-weight',    '700');
      sp(atcBtn,'letter-spacing', '0.05em');
      sp(atcBtn,'white-space',    'nowrap');
      sp(atcBtn,'text-decoration','none');
      sp(atcBtn,'cursor',         'pointer');
      sp(atcBtn,'flex-shrink',    '0');
      sp(atcBtn,'line-height',    '1');
      sp(atcBtn,'pointer-events', 'auto');

      // Quick View — eye icon circle
      var qvBtn=document.createElement('a');
      qvBtn.href=productUrl;
      qvBtn.className='af-ov-qv';
      qvBtn.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
      iconBtn(qvBtn);

      ov.appendChild(wlBtn);
      ov.appendChild(atcBtn);
      ov.appendChild(qvBtn);
      wrap.appendChild(ov);
    }

    // ── 5. Content: Title → Rating → Price
    var titleEl =card.querySelector('.woocommerce-loop-product__title');
    var ratingEl=card.querySelector('.woocommerce-product-rating');
    var priceEl =card.querySelector('.price');

    if (titleEl) {
      sp(titleEl,'font-size',         '13.5px');
      sp(titleEl,'font-weight',       '600');
      sp(titleEl,'color',             '#1a1a1a');
      sp(titleEl,'line-height',       '1.45');
      sp(titleEl,'padding',           '13px 16px 3px');
      sp(titleEl,'margin',            '0');
      sp(titleEl,'height',            '56px');
      sp(titleEl,'overflow',          'hidden');
      sp(titleEl,'display',           '-webkit-box');
      sp(titleEl,'-webkit-line-clamp','2');
      sp(titleEl,'-webkit-box-orient','vertical');
      sp(titleEl,'box-sizing',        'border-box');
      titleEl.querySelectorAll('a').forEach(function(a){
        sp(a,'color','#1a1a1a'); sp(a,'text-decoration','none');
      });
    }
    if (ratingEl) {
      sp(ratingEl,'display',    'flex');
      sp(ratingEl,'align-items','center');
      sp(ratingEl,'padding',    '3px 16px 4px');
      sp(ratingEl,'margin',     '0');
      sp(ratingEl,'box-sizing', 'border-box');
      var starEl=ratingEl.querySelector('.star-rating');
      var cntEl =ratingEl.querySelector('.woocommerce-review-link');
      if (starEl){sp(starEl,'font-size','12px');sp(starEl,'margin','0');}
      if (cntEl) {sp(cntEl, 'font-size','11px');sp(cntEl, 'color','#888');sp(cntEl,'margin-left','4px');}
    }
    if (priceEl) {
      sp(priceEl,'display',    'flex');
      sp(priceEl,'align-items','baseline');
      sp(priceEl,'gap',        '6px');
      sp(priceEl,'padding',    '2px 16px 14px');
      sp(priceEl,'margin',     '0');
      sp(priceEl,'box-sizing', 'border-box');
      var ins=priceEl.querySelector('ins');
      var del=priceEl.querySelector('del');
      if (ins){sp(ins,'font-size','15px');sp(ins,'font-weight','700');sp(ins,'color','#1a1a1a');sp(ins,'text-decoration','none');}
      if (del){sp(del,'font-size','12px');sp(del,'color','#aaa');sp(del,'text-decoration','line-through');sp(del,'font-weight','400');}
      if (!ins){sp(priceEl,'font-size','15px');sp(priceEl,'font-weight','700');sp(priceEl,'color','#1a1a1a');}
    }
  }


  function run() {
    document.querySelectorAll('ul.products li.product').forEach(function(card){
      try { fixShopCard(card); }
      catch(e) {
        // Never leave a card half-styled/hidden if anything throws —
        // un-hide everything inside and let the theme's own styling stand.
        card.removeAttribute('data-af-shop-fixed');
        card.dataset.afShopFixed = 'error';
        card.querySelectorAll('*').forEach(function(el){
          if (el.style && el.style.display === 'none') el.style.removeProperty('display');
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', run);
  [300, 800, 1500].forEach(function(d) { setTimeout(run, d); });
}());
