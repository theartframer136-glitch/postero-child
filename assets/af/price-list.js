    (function(){
      // The price list is in US dollars; the page shows the shopper's
      // currency at the rate the cart converts with (data-rate, 1 for USD).
      var fx = 1;
      function money(sym,val){ return sym + (Math.round(val * fx * 100) / 100).toFixed(2); }
      document.addEventListener('click', function(e){
        var b = e.target.closest('.af-chip-opt, .af-swatch'); if(!b) return;
        if(b.disabled || b.classList.contains('af-chip-oos')) return;   // out of stock
        var wrap = b.closest('.af-opts'); if(!wrap) return;
        var type = b.getAttribute('data-type');
        wrap.querySelectorAll('[data-type="'+type+'"]').forEach(function(x){ x.classList.remove('active'); });
        b.classList.add('active');
        var input = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
        if(input) input.value = b.getAttribute('data-val');
        recalc(wrap);
      });

      // Size is a <select>, frame and colour are still chips. One reader for
      // both shapes, so nothing downstream has to care which is which.
      function chosen(wrap, type){
        var sel = wrap.querySelector('select[data-type="'+type+'"]');
        if(sel) return sel.value;
        var chip = wrap.querySelector('[data-type="'+type+'"].active');
        return chip ? chip.getAttribute('data-val') : '';
      }

      document.addEventListener('change', function(e){
        var sel = e.target && e.target.closest ? e.target.closest('select[data-type]') : null;
        if(!sel) return;
        var wrap = sel.closest('.af-opts'); if(!wrap) return;
        var type = sel.getAttribute('data-type');
        var input = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
        if(input) input.value = sel.value;
        recalc(wrap);
      });

      function recalc(wrap){
        fx = parseFloat(wrap.getAttribute('data-rate')) || 1;
        // data-base is already in the shopper's currency; the list is not
        var base = (parseFloat(wrap.getAttribute('data-base'))||0) / fx;
        var cfg = {}; try{ cfg = JSON.parse(wrap.getAttribute('data-config')); }catch(e){ return; }
        var sym = wrap.getAttribute('data-symbol')||'$';
        var sizeVal  = chosen(wrap, 'size');
        var frameVal = chosen(wrap, 'frame');
        var colorVal = chosen(wrap, 'color');
        // the size IS the price, from the rate card (matches af_calc_price)
        var sizePrice = (sizeVal && cfg.sizes[sizeVal]) ? cfg.sizes[sizeVal] : base;
        // A frame priced by area (cfg.frame_sqft, Aluminium at $4/sq ft) costs
        // square feet × rate for this size; the rest keep their flat fee.
        // Matches af_frame_fee().
        var sq = (sizeVal || '').match(/\((\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*in\)/);
        function frameFee(f){
          var r = cfg.frame_sqft && cfg.frame_sqft[f];
          if (r && sq) return Math.round(sq[1] * sq[2] / 144 * r * 100) / 100;
          return (cfg.frames && cfg.frames[f]) || 0;
        }
        // the chip labels follow the size
        wrap.querySelectorAll('.af-frame-chips .af-chip-opt').forEach(function(b){
          var f = b.getAttribute('data-val'), em = b.querySelector('em');
          if (em && cfg.frame_sqft && cfg.frame_sqft[f]) em.textContent = '+' + money(sym, frameFee(f)).replace(/\.00$/, '');
        });
        // no frame means no frame-finish surcharge (matches af_calc_price)
        var fee  = frameFee(frameVal)
                 + ((colorVal && frameVal !== 'Without Frame') ? (cfg.colors[colorVal]||0) : 0);
        var price = Math.round((sizePrice + fee)*100)/100;
        // Stretcher bars, when the chosen "What you receive" includes them:
        // priced by area, the same af_bar_price() the cart charges.
        var kg = document.getElementById('af-kit-group');
        var rate = kg ? (parseFloat(kg.getAttribute('data-bar-rate')) || 0) : 0;
        var inch = (sizeVal || '').match(/\((\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*in\)/);
        var bar = inch ? Math.round(inch[1] * inch[2] / 144 * rate * 100) / 100 : 0;
        if (kg) {
          // "+ frame" includes a frame: its add-on is the bars AND the frame
          // (the chosen one, or the first in stock while the page is still on
          // Without Frame) plus that frame's colour finish. The cart charges
          // the same, so the label and the total never disagree.
          var realFrame = kg.getAttribute('data-frame-real') || '';
          var effFrame  = (frameVal && frameVal !== 'Without Frame') ? frameVal : realFrame;
          var frameAdd  = effFrame ? (frameFee(effFrame) + ((colorVal && cfg.colors[colorVal]) || 0)) : 0;
          kg.querySelectorAll('.af-kit-add').forEach(function(s){
            var k = s.getAttribute('data-kit') || '', add = 0;
            if (k === 'painting_bar') add = bar;
            else if (k === 'painting_bar_frame') add = bar + frameAdd;
            s.textContent = add > 0 ? '+' + money(sym, add) : '';
          });
          var kit = kg.querySelector('input[name="af_kit"]:checked');
          if (kit && /^painting_bar/.test(kit.value)) price = Math.round((price + bar) * 100) / 100;
          // the chip switches a moment after the kit does: show the framed
          // price in between, never a frameless one for a framed kit
          if (kit && kit.value === 'painting_bar_frame' && frameVal === 'Without Frame' && effFrame) {
            price = Math.round((price + frameAdd) * 100) / 100;
          }
        }
        var el = wrap.querySelector('#af-live-price');
        if(el) el.innerHTML = '<span class="amount">'+money(sym,price)+'</span>';
        syncColorFee(wrap);
      }

      // The +$10 on Gold and Rose Gold is charged only when there is a frame to
      // finish — af_calc_price gates it the same way, and so does the fee line
      // above. A page opens on "Without Frame", so without this the swatch
      // would advertise a surcharge the cart would not take, which is the same
      // class of fault as advertising one it does take silently.
      function syncColorFee(wrap){
        var noFrame = chosen(wrap, 'frame') === 'Without Frame';
        wrap.classList.toggle('af-color-free', noFrame);
        // An unframed print has no moulding, so there is no colour to choose.
        // The price and the cart already ignored the colour in this state;
        // the swatches did not, and went on accepting clicks that changed
        // nothing (the owner's recording). They are switched off now, and
        // come back exactly as they were when a frame is chosen again.
        wrap.querySelectorAll('.af-color-chips .af-swatch').forEach(function(sw){
          sw.disabled = noFrame;
          if(noFrame) sw.setAttribute('aria-disabled','true'); else sw.removeAttribute('aria-disabled');
        });
      }
      // This script is printed in <head>, before the options exist, so a
      // check here alone found nothing - and the page OPENS on Without Frame,
      // which is exactly when the colours must already be off. Measured live:
      // on load the swatches were fully active until Without Frame was
      // clicked again. Run it once the options are in the page, and again at
      // load for anything (quick view) drawn late.
      function syncAll(){ document.querySelectorAll('.af-opts').forEach(syncColorFee); }
      syncAll();
      if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', syncAll);
      window.addEventListener('load', syncAll);
      // Quick view injects a fresh .af-opts after load; catch that too.
      if(window.MutationObserver){
        new MutationObserver(function(ms){
          for(var i=0;i<ms.length;i++){ for(var j=0;j<ms[i].addedNodes.length;j++){
            var n=ms[i].addedNodes[j];
            if(n.nodeType===1 && (n.matches('.af-opts') || n.querySelector('.af-opts'))){ syncAll(); return; }
          } }
        }).observe(document.documentElement, {childList:true, subtree:true});
      }

      // Arriving from Try On Wall? Pre-select what the visitor configured there,
      // so the price they were shown is the price they land on.
      function preselect(){
        var q = new URLSearchParams(location.search);
        var want = { size:q.get('af_size'), frame:q.get('af_frame'), color:q.get('af_color') };
        if(!(want.size || want.frame || want.color)) return;
        document.querySelectorAll('.af-opts').forEach(function(wrap){
          Object.keys(want).forEach(function(type){
            if(!want[type]) return;
            // dropdown types: only honour a value the select still offers
            var sel = wrap.querySelector('select[data-type="'+type+'"]');
            if(sel){
              var has = Array.prototype.some.call(sel.options, function(o){ return o.value === want[type]; });
              if(!has) return;
              sel.value = want[type];
              var si = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
              if(si) si.value = want[type];
              return;
            }
            var target = null;
            wrap.querySelectorAll('[data-type="'+type+'"]').forEach(function(x){
              if(x.getAttribute('data-val') === want[type]) target = x;
            });
            // never restore a Try-On-Wall choice we can no longer make
            if(!target || target.disabled || target.classList.contains('af-chip-oos')) return;
            wrap.querySelectorAll('[data-type="'+type+'"]').forEach(function(x){ x.classList.remove('active'); });
            target.classList.add('active');
            var input = wrap.parentNode.querySelector('input[name="af_'+type+'"]') || document.querySelector('input[name="af_'+type+'"]');
            if(input) input.value = want[type];
          });
          recalc(wrap);
        });
      }
      if(document.readyState !== 'loading') preselect();
      else document.addEventListener('DOMContentLoaded', preselect);
    })();
    
