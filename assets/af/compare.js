(function(){
  var KEY = 'af_compare_ids', MAX = 4;
  function ids(){ try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch(e){ return []; } }
  function save(a){ localStorage.setItem(KEY, JSON.stringify(a.slice(0, MAX))); refresh(); }
  function toggle(id){
    var a = ids(), i = a.indexOf(id);
    if (i >= 0) a.splice(i, 1);
    else { if (a.length >= MAX) { alert('You can compare up to ' + MAX + ' artworks — remove one first.'); return; } a.push(id); }
    save(a);
  }
  function refresh(){
    var a = ids();
    document.querySelectorAll('.af-cmp-btn').forEach(function(b){
      b.classList.toggle('on', a.indexOf(parseInt(b.dataset.id, 10)) >= 0);
    });
    var bar = document.getElementById('afCmpBar');
    if (a.length > 0) {
      if (!bar) {
        bar = document.createElement('div');
        bar.id = 'afCmpBar';
        bar.innerHTML = '<span id="afCmpCount"></span>' +
          '<a class="af-cmp-go" href="/compare/">Compare Now →</a>' +
          '<button type="button" class="af-cmp-clear" id="afCmpClear">Clear</button>';
        document.body.appendChild(bar);
        document.getElementById('afCmpClear').addEventListener('click', function(){ save([]); });
      }
      bar.style.display = 'flex';
      document.getElementById('afCmpCount').textContent = a.length + ' of ' + MAX + ' selected';
    } else if (bar) { bar.style.display = 'none'; }
  }
  document.addEventListener('click', function(e){
    var b = e.target.closest('.af-cmp-btn');
    if (!b) return;
    e.preventDefault(); e.stopPropagation();
    toggle(parseInt(b.dataset.id, 10));
  });

  // The Elementor/Postero product grid doesn't fire the WooCommerce loop
  // hooks, so inject compare buttons from the cards' data-product_id.
  function makeBtn(id, cls){
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'af-cmp-btn' + (cls ? ' ' + cls : '');
    b.dataset.id = id;
    if (cls === 'af-cmp-single') {
      b.textContent = '⇄ Add to Compare';
    } else {
      b.setAttribute('title', 'Compare');
      b.setAttribute('aria-label', 'Add to compare');
      var icon = document.createElement('span');
      icon.className = 'af-cmp-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = '⇄';
      b.appendChild(icon);
    }
    return b;
  }
  function inject(){
    document.querySelectorAll('a[data-product_id]').forEach(function(a){
      var card = a.closest('li.product, .type-product, .product');
      if (!card || card.querySelector('.af-cmp-btn')) return;
      var id = parseInt(a.getAttribute('data-product_id'), 10);
      if (!id) return;
      (a.parentElement || card).appendChild(makeBtn(id));
    });
    if (document.body.classList.contains('single-product')) {
      var m = document.body.className.match(/postid-(\d+)/);
      var host = document.querySelector('.summary form.cart, .summary .cart, .summary');
      if (m && host && !host.querySelector('.af-cmp-btn') && !document.querySelector('.af-cmp-single')) {
        host.appendChild(makeBtn(parseInt(m[1], 10), 'af-cmp-single'));
      }
    }
    refresh();
  }
  document.addEventListener('DOMContentLoaded', inject);
  window.addEventListener('load', inject);
  setTimeout(inject, 800); setTimeout(inject, 2000);
  refresh();

  // Compare page renderer
  var wrap = document.getElementById('afCompareWrap');
  if (!wrap) return;
  // quotes too: titles and URLs go inside alt="…" and href="…"
  function esc(t){ return (t == null ? '' : String(t)).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function render(){
    var a = ids(), empty = document.getElementById('afCmpEmpty'), tbl = document.getElementById('afCmpTable');
    if (!a.length) { empty.style.display = 'block'; tbl.innerHTML = ''; return; }
    empty.style.display = 'none';
    tbl.innerHTML = '<p style="text-align:center;color:#888;">Loading comparison…</p>';
    var fd = new FormData();
    fd.append('action', 'af_compare_data');
    a.forEach(function(id){ fd.append('ids[]', id); });
    fetch(wrap.dataset.ajax, { method:'POST', credentials:'same-origin', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        var items = (res && res.data) || [];
        if (!items.length) { empty.style.display = 'block'; tbl.innerHTML = ''; return; }
        var rows = [
          ['Artwork', function(p){ return '<a href="' + esc(p.url) + '"><img src="' + esc(p.img) + '" alt="' + esc(p.title) + '" style="width:100%;max-width:170px;display:block;margin:0 auto 10px;"><b>' + esc(p.title) + '</b></a>'; }],
          ['Price', function(p){ return esc(p.price); }],
          ['Rating', function(p){ return esc(p.rating); }],
          ['Sizes', function(p){ return esc(p.sizes); }],
          ['Frame Types', function(p){ return esc(p.frames); }],
          ['Frame Colours', function(p){ return esc(p.colours); }],
          ['Options', function(p){ return esc(p.type); }],
          ['Digital Download', function(p){ return esc(p.digital); }],
          ['Availability', function(p){ return esc(p.stock); }],
          ['', function(p){ return '<a class="taf-btn" href="' + esc(p.url) + '">View Product</a> <button type="button" class="af-cmp-rm taf-btn-alt" data-id="' + p.id + '">Remove</button>'; }]
        ];
        var h = '<div style="overflow-x:auto;"><table class="taf-table af-cmp-table"><tbody>';
        rows.forEach(function(row){
          h += '<tr><td style="white-space:nowrap;font-weight:700;">' + row[0] + '</td>';
          items.forEach(function(p){ h += '<td style="text-align:center;">' + row[1](p) + '</td>'; });
          h += '</tr>';
        });
        h += '</tbody></table></div>';
        tbl.innerHTML = h;
        tbl.querySelectorAll('.af-cmp-rm').forEach(function(b){
          b.addEventListener('click', function(){ toggle(parseInt(b.dataset.id, 10)); render(); });
        });
      })
      .catch(function(){ tbl.innerHTML = '<p style="text-align:center;color:#a13232;">Could not load the comparison — please refresh.</p>'; });
  }
  render();
})();
