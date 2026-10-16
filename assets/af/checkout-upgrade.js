(function(){
  if (window.__afCoUp) return; window.__afCoUp = true;
  var pending = null;   // the change just saved, waiting for the page's redraw
  function onCart(el){ return !!(el && el.closest && el.closest('.woocommerce-cart-form')); }
  // The block is printed inside the line's name cell (checkout: beside the
  // quantity; cart: after the name); it lives in a row of its own under the
  // line. On the cart the row leaves the remove column empty and starts under
  // the photo.
  function rowFor(tr, cart){
    var row = tr.nextElementSibling;
    if (row && row.classList.contains('af-co-up-row')) return row;
    row = document.createElement('tr'); row.className = 'af-co-up-row';
    if (cart) {
      var pad = document.createElement('td'); pad.className = 'af-co-up-pad'; pad.setAttribute('aria-hidden', 'true'); row.appendChild(pad);
      var cell = document.createElement('td'); cell.className = 'af-co-up-cell'; cell.colSpan = Math.max(1, tr.children.length - 1); row.appendChild(cell);
    } else {
      var td = document.createElement('td'); td.colSpan = 2; row.appendChild(td);
    }
    tr.after(row);
    return row;
  }
  function place(){
    document.querySelectorAll('.woocommerce-checkout-review-order-table td.product-name .af-co-up, .woocommerce-cart-form table.cart td.product-name .af-co-up').forEach(function(up){
      var tr = up.closest('tr'); if (!tr) return;
      var cell = rowFor(tr, onCart(up)).lastElementChild;
      while (cell.firstChild) cell.removeChild(cell.firstChild);
      cell.appendChild(up); up.classList.add('is-placed');
    });
    align();
  }
  // cart: the labels start under the photo's left edge and the controls where
  // the product name does (both move with the screen width)
  function align(){
    document.querySelectorAll('.woocommerce-cart-form table.cart tr.af-co-up-row').forEach(function(row){
      var up = row.querySelector('.af-co-up'), cell = up && up.parentElement, line = row.previousElementSibling;
      var name = line && line.querySelector('td.product-name'); if (!up || !cell || !name) return;
      var img = line.querySelector('td.product-thumbnail img'), ib = img && img.getBoundingClientRect();
      var left = cell.getBoundingClientRect().left;
      var pad = ib && ib.width ? Math.round(ib.left - left) : 0;
      if (pad < 0 || pad > 120) pad = 0;
      cell.style.setProperty('padding-left', pad + 'px', 'important');
      var off = Math.round(name.getBoundingClientRect().left - left - pad);
      if (off >= 72 && off <= 240) up.style.setProperty('--afu-lab', off + 'px'); else up.style.removeProperty('--afu-lab');
    });
  }
  window.__afCoUpPlace = place;
  // nothing to order or check out with while a line is changing
  function lock(on){
    var po = document.getElementById('place_order'); if (po) po.disabled = !!on;
    document.documentElement.classList.toggle('af-co-up-saving', !!on);
    // cart: the totals box (checkout button, shipping estimate, coupons) and the
    // lines' quantity, remove and update controls wait too, for the keyboard as
    // well as the mouse: WooCommerce saves the whole basket at the end of each
    // request, so one overlapping the change would put the old choice back
    document.querySelectorAll('div.cart_totals, .woocommerce-cart-form .quantity, .woocommerce-cart-form td.product-remove, .woocommerce-cart-form td.actions').forEach(function(el){ el.inert = !!on; });
  }
  // after the page redraws the list: focus the chosen button again and say it worked
  function afterRedraw(){
    place();
    if (!pending) return;
    var box = document.querySelector('.af-co-up[data-key="' + pending.key + '"]');
    // redrawn only once the list holding the clicked editor has been replaced:
    // when the change joins an identical line, that line's editor exists before
    // the redraw too
    if (!box || box === pending.old || document.contains(pending.old)) return;
    var a = pending; pending = null; lock(false);
    var btn = box.querySelector(a.size ? '.af-co-up-size[data-size="' + a.size + '"]' : a.color ? '.af-co-up-color[data-color="' + a.color + '"]' : '.af-co-up-opt[data-kit="' + a.kit + '"]');
    if (btn) btn.focus({ preventScroll: true });
    var m = box.querySelector('.af-co-up-msg'); if (m) { m.classList.add('is-ok'); m.textContent = onCart(box) ? 'Updated: the price now includes your choice.' : 'Updated: the price and delivery now include your choice.'; }
  }
  // Watch the list's container itself: this script runs in the page head,
  // before jQuery and before WooCommerce's events can be listened to.
  function watch(){
    var form = document.querySelector('.woocommerce-cart-form');
    var root = document.getElementById('order_review') || document.querySelector('form.checkout') || (form && (form.closest('.woocommerce') || form.parentElement)) || document.body;
    if (!root) return;
    // placed in the observer itself (before the redraw paints), focus and the
    // message a moment later
    var t; new MutationObserver(function(){ place(); clearTimeout(t); t = setTimeout(afterRedraw, 30); }).observe(root, { childList: true, subtree: true });
    afterRedraw();
    var rt; window.addEventListener('resize', function(){ clearTimeout(rt); rt = setTimeout(align, 150); });
    window.addEventListener('load', align);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', watch); else watch();
  document.addEventListener('click', function(e){
    var b = e.target.closest('.af-co-up-opt, .af-co-up-color, .af-co-up-size'); if (!b) return;
    var box = b.closest('.af-co-up'); if (!box) return;
    e.preventDefault();
    if (b.classList.contains('is-on')) return;
    // one change at a time: two at once would each save a basket missing the other
    if (document.querySelector('.af-co-up.is-busy') || pending) return;
    var msg = box.querySelector('.af-co-up-msg');
    // Cart: a quantity typed or stepped but not yet sent (functions.php sends
    // it 0.7s later), or a cart update under way, would be lost or would undo
    // this change (the change gives the line a new key). Let it finish first.
    var form = onCart(box) ? box.closest('.woocommerce-cart-form') : null;
    if (form && (form.classList.contains('processing') || form.querySelector('.blockUI') || document.querySelector('div.cart_totals.processing, div.cart_totals .blockUI') || [].some.call(form.querySelectorAll('input.qty'), function(i){ return i.value !== i.defaultValue; }))) {
      if (msg) { msg.classList.remove('is-ok'); msg.textContent = 'One moment: your basket is updating. Please choose again when it has.'; }
      return;
    }
    var fd = new FormData();
    fd.append('key', box.dataset.key); fd.append('nonce', box.dataset.nonce);
    fd.append('kit', b.dataset.kit); if (b.dataset.color) fd.append('color', b.dataset.color); if (b.dataset.size) fd.append('size', b.dataset.size);
    if (msg) { msg.classList.remove('is-ok'); msg.textContent = ''; }
    box.classList.add('is-busy');
    // no order while the line is changing; released after the redraw
    lock(true);
    // cart: WooCommerce's cart script holds back its own updates (quantities,
    // coupon) while its form is marked as processing
    if (form) form.classList.add('processing');
    function free(){ box.classList.remove('is-busy'); lock(false); if (form) form.classList.remove('processing'); }
    var ctl = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function(){ if (ctl) ctl.abort(); }, 20000);
    fetch(box.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: fd, signal: ctl ? ctl.signal : undefined })
      .then(function(r){ return r.json().catch(function(){ return { success: false }; }); })
      .then(function(res){
        clearTimeout(timer);
        if (res && res.success) {
          var mine = pending = { key: (res.data && res.data.key) || box.dataset.key, kit: b.dataset.kit, color: b.dataset.color || '', size: b.dataset.size || '', old: box };
          // if this change's redraw never comes, do not leave ordering switched
          // off (an earlier change's timer must not end a later one)
          setTimeout(function(){ if (pending === mine) { pending = null; free(); } }, 25000);
          // The cart redraws its form and totals the way a quantity change does.
          // WooCommerce's cart script listens only from DOM-ready (its ready
          // callbacks run in turn), so a choice made while the page is still
          // loading asks after them.
          if (onCart(box)) { if (window.jQuery && typeof window.wc_cart_params !== 'undefined') jQuery(function(){ setTimeout(function(){ jQuery(document.body).trigger('wc_update_cart'); }, 0); }); else location.reload(); }
          else if (window.jQuery) jQuery(document.body).trigger('update_checkout'); else location.reload();
        } else {
          free();
          if (msg) msg.textContent = (res && res.data && res.data.message) || 'That did not work. Please refresh the page and try again.';
        }
      })
      .catch(function(){ clearTimeout(timer); free(); if (msg) msg.textContent = 'That took too long or the connection dropped. Please try again.'; });
  });
})();
