(function(){
  // The plugin renders its add-to-cart control with no label of its own, which
  // is why the page showed a shopping-bag emoji. Give it the words and the
  // icon the rest of the store uses. Runs again when the table re-renders.
  var CART = '<svg class="af-wl-cart-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
           + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
           + '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>'
           + '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>';

  function label(a){
    if (a.dataset.afWlDone) return;
    a.dataset.afWlDone = '1';
    a.classList.add('button');
    a.classList.add('af-wl-labelled');
    a.innerHTML = CART + '<span>Add to Cart</span>';
    a.setAttribute('aria-label', 'Add to cart');
  }
  // Only rewrite a link that has no words of its own (the emoji control).
  // A link that already says something keeps its text.
  function bare(a){
    var t = (a.textContent || '').replace(/[\s\u200b]/g, '');
    return t.length <= 3;   // '', an emoji, or an icon ligature
  }
  function heading(){
    var table = document.querySelector('.woosw-items, .woosw-list, .wishlist_table, table.shop_table');
    if (!table || document.querySelector('.af-wl-head')) return;
    var h = document.createElement('div');
    h.className = 'af-wl-head';
    var n = table.querySelectorAll('tbody tr').length;
    h.innerHTML = '<h1>Your Wishlist</h1><p>' +
      (n ? n + (n === 1 ? ' piece saved' : ' pieces saved') + ' — ready when you are.'
         : 'Nothing saved yet. Tap the heart on any piece to keep it here.') + '</p>';
    var host = table.closest('.woocommerce, .entry-content, main') || table.parentElement;
    host.insertBefore(h, host.firstChild);
  }
  // Inline styles, deliberately. The stylesheet version of these rules ships
  // on the page and still loses — the control is positioned by rules this
  // theme applies with higher specificity, so a stylesheet fight is not
  // winnable here. An inline property with priority beats every stylesheet,
  // which is the same lesson the product-card buttons taught earlier.
  // The button was styled and still clipped: the plugin's table's column
  // minimums push the actions cell past the card edge — geometry, not cascade.
  // Stop using table layout entirely: each row becomes a flex line (image,
  // shrinking title block, button pinned right), which cannot overflow.
  function unclip(){
    var table = document.querySelector('.woosw-items');
    if (!table) return;
    table.style.setProperty('width', '100%', 'important');
    table.style.setProperty('max-width', '100%', 'important');
    table.style.setProperty('display', 'block', 'important');
    table.querySelectorAll('tbody').forEach(function(tb){
      tb.style.setProperty('display', 'block', 'important');
    });
    // A phone has no room for image, words and button on one line. Below
    // 781px the row wraps and the buying control takes a line of its own;
    // above it, the single row this was written for is unchanged.
    var narrow = window.innerWidth <= 781;
    table.querySelectorAll('tr').forEach(function(tr){
      tr.style.setProperty('display', 'flex', 'important');
      tr.style.setProperty('align-items', narrow ? 'flex-start' : 'center', 'important');
      tr.style.setProperty('flex-wrap', narrow ? 'wrap' : 'nowrap', 'important');
      tr.style.setProperty('position', 'relative', 'important');
      tr.style.setProperty('gap', '14px', 'important');
      tr.style.setProperty('width', '100%', 'important');
      tr.style.setProperty('box-sizing', 'border-box', 'important');
      tr.style.setProperty('padding', '14px 18px', 'important');
    });
    table.querySelectorAll('td').forEach(function(td){
      td.style.setProperty('display', 'block', 'important');
      td.style.setProperty('overflow', 'visible', 'important');
      td.style.setProperty('padding', '0', 'important');
      td.style.setProperty('border', '0', 'important');
      var isInfo = td.className.indexOf('info') !== -1 || td.querySelector('.woosw-item--name');
      td.style.setProperty('flex', isInfo ? '1 1 auto' : 'none', 'important');
      if (isInfo) td.style.setProperty('min-width', '0', 'important');
      if (td.className.indexOf('actions') !== -1 || td.querySelector('a[href*="add-to-cart="]')) {
        // On a phone this is the one control the card exists for. Give it the
        // full width of its own line; pinning it right leaves 180px of empty
        // gutter beside it and a target too small to aim at with a thumb.
        td.style.setProperty('margin-left', narrow ? '0' : 'auto', 'important');
        td.style.setProperty('min-width', '0', 'important');
        td.style.setProperty('text-align', narrow ? 'left' : 'right', 'important');
        td.style.setProperty('flex', narrow ? '0 0 100%' : 'none', 'important');
        if (narrow) td.style.setProperty('width', '100%', 'important');
      }
      // The x belongs in the corner of the card, not beside the photograph
      // competing with it for the first line.
      if (narrow && td.className.indexOf('remove') !== -1) {
        td.style.setProperty('position', 'absolute', 'important');
        td.style.setProperty('top', '6px', 'important');
        td.style.setProperty('right', '8px', 'important');
        td.style.setProperty('flex', '0 0 auto', 'important');
        td.style.setProperty('z-index', '2', 'important');
      }
    });
    table.querySelectorAll('.woosw-item--image img, td img').forEach(function(im){
      im.style.setProperty('width', '88px', 'important');
      im.style.setProperty('height', '88px', 'important');
      im.style.setProperty('object-fit', 'cover', 'important');
      im.style.setProperty('border-radius', '10px', 'important');
    });
    var name = table.querySelector('.woosw-item--info, .woosw-item--name');
    if (name) name.style.setProperty('word-break', 'break-word', 'important');
  }

  // The saved-item card and the related row sat on different left/right edges,
  // because each is constrained by a different container. Rather than guess a
  // width that matches, measure where the related row's cards actually start
  // and end and put the saved-item card on exactly those edges.
  function alignSections(){
    var list = document.querySelector('.woosw-list');
    if (!list) return;
    var heads = ['.af-wl-head', '.woosw-actions'].map(function(sel){
      return document.querySelector(sel);
    });

    // On a phone there are no two columns to line up. The related row is a
    // carousel whose first card starts well inside the page, and copying that
    // inset onto the saved-item card squeezed it to a sliver: measured on the
    // live page at 420px wide, this set margin-left AND margin-right to 121px,
    // leaving 178px of usable width. Clear whatever a wider layout wrote and
    // let the container's own width stand. This runs on every resize, so a
    // window dragged narrow is corrected too.
    if (window.innerWidth <= 781) {
      [list].concat(heads).forEach(function(el){
        if (!el) return;
        ['margin-left','margin-right','max-width','width',
         'padding-left','padding-right','box-sizing'].forEach(function(prop){
          el.style.removeProperty(prop);
        });
      });
      return;
    }

    var rel = document.querySelector('.af-wl-related');
    if (!rel || !list.parentElement) return;
    var cards = rel.querySelectorAll('li.product');
    if (!cards.length) return;
    var first = cards[0].getBoundingClientRect();
    var last  = cards[cards.length - 1].getBoundingClientRect();
    var host  = list.parentElement.getBoundingClientRect();
    if (!first.width || !host.width) return;
    var left  = Math.round(first.left - host.left);
    var right = Math.round(host.right - last.right);
    if (left < 0 || right < 0) return;
    list.style.setProperty('box-sizing', 'border-box', 'important');
    list.style.setProperty('width', 'auto', 'important');
    list.style.setProperty('max-width', 'none', 'important');
    list.style.setProperty('margin-left', left + 'px', 'important');
    list.style.setProperty('margin-right', right + 'px', 'important');
    // the heading and the share row belong on the same edges
    heads.forEach(function(el){
      if (!el) return;
      el.style.setProperty('box-sizing', 'border-box', 'important');
      el.style.setProperty('max-width', 'none', 'important');
      el.style.setProperty('margin-left', left + 'px', 'important');
      el.style.setProperty('margin-right', right + 'px', 'important');
      el.style.setProperty('padding-left', '0', 'important');
      el.style.setProperty('padding-right', '0', 'important');
    });
  }
  function forceButton(a){
    // Width matters most: the theme renders this control as a small round icon
    // button (fixed width, overflow hidden), which is why the label showed as
    // the middle fragment "D TO" of "ADD TO CART" rather than the whole words.
    // Releasing the width is what makes the button whole.
    var st = {display:'inline-flex', alignItems:'center', justifyContent:'center', gap:'8px',
              background:'#c9a84c', border:'0', color:'#fff', fontSize:'14px', fontWeight:'600',
              padding:'11px 20px', borderRadius:'6px', whiteSpace:'nowrap', textDecoration:'none',
              position:'static', transform:'none', opacity:'1', visibility:'visible',
              margin:'0', right:'auto', left:'auto', top:'auto', bottom:'auto',
              width:(window.innerWidth <= 781 ? '100%' : 'auto'), minWidth:'0', maxWidth:'none',
              height:'auto', minHeight:(window.innerWidth <= 781 ? '44px' : '0'),
              overflow:'visible', textIndent:'0', lineHeight:'1.2', flex:'none',
              boxSizing:'border-box', textTransform:'none'};
    for (var k in st) a.style.setProperty(k.replace(/[A-Z]/g, function(m){return '-'+m.toLowerCase();}), st[k], 'important');
    a.addEventListener('mouseenter', function(){ a.style.setProperty('background', '#8b6a2b', 'important'); });
    a.addEventListener('mouseleave', function(){ a.style.setProperty('background', '#c9a84c', 'important'); });
    // the wrappers the theme parks off-edge
    var n = a.parentElement, hops = 0;
    while (n && hops++ < 4 && !n.matches('td,.woosw-item')) {
      n.style.setProperty('width', 'auto', 'important');
      n.style.setProperty('max-width', 'none', 'important');
      ['position','transform','opacity','visibility','right','left','margin','overflow'].forEach(function(prop){
        n.style.setProperty(prop, prop === 'position' ? 'static'
                                : prop === 'transform' ? 'none'
                                : prop === 'opacity' ? '1'
                                : prop === 'visibility' ? 'visible'
                                : prop === 'overflow' ? 'visible'
                                : prop === 'margin' ? '0' : 'auto', 'important');
      });
      n.style.setProperty('display', 'block', 'important');
      n = n.parentElement;
    }
    if (n && n.matches('td')) {
      n.style.setProperty('overflow', 'visible', 'important');
      // 190px and right-aligned is the desktop row. On a phone that is what
      // stranded the button at 172px with an empty gutter beside it.
      var narrow = window.innerWidth <= 781;
      n.style.setProperty('min-width', narrow ? '0' : '190px', 'important');
      n.style.setProperty('text-align', narrow ? 'left' : 'right', 'important');
    }
  }
  // an art code printed outside a product card is noise between sections
  function dropStrayCodes(){
    document.querySelectorAll('.af-art-code').forEach(function(el){
      if (el.closest('li.product') || el.closest('.woosw-item')) return;   // inside a card: keep
      el.style.setProperty('display', 'none', 'important');
    });
  }
  function run(){
    document.querySelectorAll('.woosw-items a[href*="add-to-cart="], .wishlist_table a[href*="add-to-cart="]').forEach(label);
    document.querySelectorAll('.woosw-item a[href*="add-to-cart="], .woosw-item--atc a.button').forEach(forceButton);
    unclip();
    alignSections();
    dropStrayCodes();
    // the same control wherever the plugin put it, as long as it is wordless
    document.querySelectorAll('a[href*="add-to-cart="]').forEach(function(a){
      if (!a.closest('.af-wl-related') && bare(a)) label(a);
    });
    heading();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  window.addEventListener('load', run);
  [400, 1200, 2500].forEach(function(d){ setTimeout(run, d); });
  var _art; window.addEventListener('resize', function(){ clearTimeout(_art); _art = setTimeout(run, 200); });
  try {
    new MutationObserver(function(m){
      for (var i = 0; i < m.length; i++) { if (m[i].addedNodes && m[i].addedNodes.length) { run(); break; } }
    }).observe(document.body, {childList:true, subtree:true});
  } catch(e){}
})();
