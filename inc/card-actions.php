<?php
/**
 * The four actions on a product card — visible, and labelled.
 *
 * The owner reported that hovering a card dims the picture and shows nothing.
 * Every control was already there. Measured on the live category page:
 *
 *   .woosw-btn  40x40  "Add to wishlist"  white on rgba(255,255,255,0)
 *   .woosq-btn  40x40  "Quick view"       white on rgba(255,255,255,0)
 *   .add_to_cart_button 36x36  "🛍"       white on rgba(0,0,0,0)
 *   .af-cmp-btn         36x36  "⇄"        white on rgba(0,0,0,0)
 *
 * Four white glyphs on a transparent background, over artwork that is usually
 * pale. Nothing was broken: the hover reveal worked, the icon font loaded, the
 * buttons were the right size and clickable the whole time. They just could
 * not be seen.
 *
 * So each gets a dark chip to sit on — which reads against any artwork,
 * light or dark — and the name of what it does, because an unlabelled glyph
 * is a guess. "⇄" in particular tells nobody it means compare.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!function_exists('is_shop')) return;
    if (!(is_shop() || is_product_taxonomy() || is_product() || is_front_page())) return;
    ?>
    <style id="af-card-actions">
    /* ── THE CHIP ───────────────────────────────────────────────────────────
       A circle dark enough that a white glyph reads on any picture. The
       artwork behind it is the point of the page, so the chip is translucent
       rather than solid: it darkens what is behind it without hiding it. */
    html body ul.products li.product .af-icon-corner > a.add_to_cart_button,
    html body ul.products li.product .af-icon-corner > .af-cmp-btn,
    html body ul.products li.product .shop-action .woosw-btn,
    html body ul.products li.product .shop-action .woosq-btn{
      width:38px !important;height:38px !important;min-width:38px !important;
      border-radius:50% !important;border:0 !important;
      background:rgba(26,26,26,.68) !important;
      color:#fff !important;
      display:inline-flex !important;align-items:center !important;
      justify-content:center !important;
      box-shadow:0 2px 10px rgba(0,0,0,.28) !important;
      backdrop-filter:saturate(140%) blur(2px);
      transition:background .18s ease, transform .18s ease !important;
      cursor:pointer !important;padding:0 !important;
    }
    html body ul.products li.product .af-icon-corner > a.add_to_cart_button:hover,
    html body ul.products li.product .af-icon-corner > .af-cmp-btn:hover,
    html body ul.products li.product .shop-action .woosw-btn:hover,
    html body ul.products li.product .shop-action .woosq-btn:hover{
      background:#c9a84c !important;transform:translateY(-2px) !important;
    }
    /* The glyphs themselves. The wishlist and quick view buttons carry their
       icon in a ::before at 18px; the other two carry a character. Both want
       to be white and centred, and neither wants the button's own font-size:0
       (which is how the plugin hides the words) to reach them. */
    html body ul.products li.product .shop-action .woosw-btn::before,
    html body ul.products li.product .shop-action .woosq-btn::before{
      font-size:17px !important;line-height:1 !important;color:#fff !important;}
    html body ul.products li.product .af-icon-corner .af-icon-glyph,
    html body ul.products li.product .af-icon-corner .af-cmp-icon{
      font-size:17px !important;line-height:1 !important;color:#fff !important;}

    /* ── SHOWN, FULL STOP ───────────────────────────────────────────────────
       Not on hover. On hover was the plan, and the owner reported three times
       that the buttons were still not there, so I went and read the pixels
       actually painted at each button's centre while hovering:

         cart      rgb(254,254,254)   nothing
         compare   rgb(254,254,254)   nothing
         wishlist  rgb(178,142,83)    the artwork, showing through
         quickview rgb(216,184,147)   the artwork, showing through

       Four controls reporting "fully opaque" a moment earlier and painting
       nothing. Whatever the exact mechanism — and a hover state is a fragile
       thing, lost to a repaint, a re-render of the listing, a touch screen
       that never sends one — a control that exists only while the cursor is
       held still is a control that is missing most of the time.

       These four ARE the card: buy it, look closer, compare it, keep it. They
       are small, they sit on the picture, and they cost nothing to leave
       where they can be seen. So they stay. The theme hides its own pair at
       opacity 0 until hover; that is overridden here too, for the same
       reason. */
    html body ul.products li.product .af-icon-corner,
    html body ul.products li.product .group-action,
    html body ul.products li.product .shop-action{
      opacity:1 !important;visibility:visible !important;
      transform:none !important;}

    /* ── THE LABEL ──────────────────────────────────────────────────────────
       An unlabelled glyph is a guess, and "⇄" tells nobody it means compare.
       The tooltip sits above the chip and is drawn by the button itself, so
       there is no extra element to position or to leave behind.

       font-size is stated outright: these buttons are set to font-size 0 by
       the plugin to hide their words, and a pseudo-element inherits that — a
       tooltip at 0px is no tooltip at all. */
    html body ul.products li.product [data-af-tip]{position:relative !important;}
    html body ul.products li.product [data-af-tip]::after{
      content:attr(data-af-tip);
      position:absolute;bottom:calc(100% + 9px);left:50%;
      transform:translateX(-50%) translateY(3px);
      background:#1a1a1a;color:#fff;font-size:11px !important;font-weight:600;
      letter-spacing:.2px;line-height:1;padding:6px 9px;border-radius:5px;
      white-space:nowrap;opacity:0;pointer-events:none;
      transition:opacity .15s ease, transform .15s ease;z-index:60;
      font-family:"Instrument Sans",system-ui,sans-serif;
    }
    html body ul.products li.product [data-af-tip]:hover::after{
      opacity:1;transform:translateX(-50%) translateY(0);}

    /* ── A PHONE HAS NO HOVER ───────────────────────────────────────────────
       Nothing above hangs off :hover any more, so the buttons are already
       there on a touch screen. What a phone does need is the tooltip gone — a
       finger cannot hover to read one — and a slightly larger target. */
    @media (max-width:781px){
      html body ul.products li.product [data-af-tip]::after{display:none !important;}
      html body ul.products li.product .af-icon-corner > a.add_to_cart_button,
      html body ul.products li.product .af-icon-corner > .af-cmp-btn,
      html body ul.products li.product .shop-action .woosw-btn,
      html body ul.products li.product .shop-action .woosq-btn{
        width:40px !important;height:40px !important;min-width:40px !important;}
    }
    </style>
    <script>
    (function(){
      // The words each button already carries, used as its label. The plugins
      // put real text inside their buttons and then set font-size to 0 to hide
      // it; that text is the most accurate description available, so it is
      // reused rather than invented. Only where a button has no words of its
      // own does a name get supplied.
      var NAMED = [
        ['.af-icon-corner a.add_to_cart_button', 'Add to cart'],
        ['.af-icon-corner .af-cmp-btn',          'Compare'],
        ['.shop-action .woosw-btn',              'Add to wishlist'],
        ['.shop-action .woosq-btn',              'Quick view']
      ];
      function label(){
        document.querySelectorAll('ul.products li.product').forEach(function(card){
          NAMED.forEach(function(pair){
            var el = card.querySelector(pair[0]);
            if (!el || el.getAttribute('data-af-tip')) return;
            var own = (el.getAttribute('aria-label') || el.textContent || '')
                        .replace(/[\s​]+/g, ' ').trim();
            // Strip a leading glyph: the cart button reads "🛍Add to cart".
            own = own.replace(/^[^\w(]+/, '').trim();
            el.setAttribute('data-af-tip', own.length > 2 ? own : pair[1]);
            if (!el.getAttribute('aria-label')) {
              el.setAttribute('aria-label', own.length > 2 ? own : pair[1]);
            }
          });
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', label);
      else label();
      // The listing re-renders when a filter changes, and the new cards arrive
      // without labels unless something is watching.
      try {
        new MutationObserver(function(m){
          for (var i = 0; i < m.length; i++) {
            if (m[i].addedNodes && m[i].addedNodes.length) { label(); break; }
          }
        }).observe(document.body, {childList:true, subtree:true});
      } catch(e){}
      [600, 1800].forEach(function(d){ setTimeout(label, d); });
    })();
    </script>
    <?php
}, 62);
