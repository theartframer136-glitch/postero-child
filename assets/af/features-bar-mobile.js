(function() {
    function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

    var activeShell = null; // track current shell so we can tear it down

    function buildSlider(container, grid) {
        // Tear down previous shell — discard old cards entirely (don't return to grid)
        // The theme has already loaded new category cards into #productGrid while shell was active
        if (activeShell && activeShell.parentNode) {
            activeShell.parentNode.removeChild(activeShell);
            activeShell = null;
        }
        grid.classList.remove('af-grid-hidden');

        // Only grab cards currently in the grid (freshly loaded by the theme)
        var freshCards = Array.from(grid.querySelectorAll('.product-card'));
        if (!freshCards.length) return;
        // Guard: only enhance REAL product sliders (cards with a price / add-to-cart).
        // Skips non-product sliders like the "Products In Motion" video section.
        var isProductSlider = freshCards.some(function(c){
            return c.querySelector('.price, .price-section, .add-cart, .add_to_cart_button, [class*="price"]');
        });
        if (!isProductSlider) { grid.classList.remove('af-grid-hidden'); return; }

        var shell  = document.createElement('div');
        var btnP   = document.createElement('button');
        var vp     = document.createElement('div');
        var track  = document.createElement('div');
        var btnN   = document.createElement('button');

        shell.className  = 'af-shell';
        btnP.className   = 'af-shell-btn';  btnP.innerHTML = '&#8249;'; btnP.setAttribute('aria-label','Prev');
        btnN.className   = 'af-shell-btn';  btnN.innerHTML = '&#8250;'; btnN.setAttribute('aria-label','Next');
        track.className  = 'af-shell-track';

        function sp(el, p, v) { el.style.setProperty(p, v, 'important'); }

        function styleCard(c) {
            // ── Card shell: flex column, full height so all cards in a row stretch equally ──
            sp(c,'background',    '#fff');
            sp(c,'border',        '1px solid #e8e8e8');
            sp(c,'border-radius', '12px');
            sp(c,'overflow',      'hidden');
            sp(c,'display',       'flex');
            sp(c,'flex-direction','column');
            sp(c,'height',        '100%');   // stretch to fill track cell
            sp(c,'position',      'relative');
            sp(c,'box-shadow',    '0 2px 12px rgba(0,0,0,.10)');
            sp(c,'margin',        '0');

            // ── Image wrapper — padding-bottom 75% = reliable 4:3 for every card ──
            var imgWrap = c.querySelector('.product-image, .image-wrapper, .woocommerce-loop-product__link');
            if (imgWrap) {
                sp(imgWrap,'display',        'block');
                sp(imgWrap,'position',       'relative');
                sp(imgWrap,'width',          '100%');
                sp(imgWrap,'height',         '0');
                sp(imgWrap,'padding-bottom', '75%');
                sp(imgWrap,'overflow',       'hidden');
                sp(imgWrap,'flex-shrink',    '0');
                sp(imgWrap,'background',     '#f0f0f0');
                imgWrap.querySelectorAll('img').forEach(function(i){
                    sp(i,'position',   'absolute');
                    sp(i,'inset',      '0');
                    sp(i,'width',      '100%');
                    sp(i,'height',     '100%');
                    sp(i,'object-fit', 'cover');
                    sp(i,'display',    'block');
                });
            }

            // ── product-info: consistent container padding ──
            var info = c.querySelector('.product-info');
            if (info) {
                sp(info,'display',        'flex');
                sp(info,'flex-direction', 'column');
                sp(info,'flex',           '1 1 auto');
                sp(info,'padding',        '12px 14px 0');
                sp(info,'margin',         '0');
                sp(info,'min-height',     '0');
                // Zero individual element padding so container is the only source of spacing
                info.querySelectorAll('.product-title,.rating,.rating-count,.price-section,.desc,.woocommerce-loop-product__title,.woocommerce-product-rating,.price,h2,h3').forEach(function(el) {
                    sp(el,'padding-left',  '0');
                    sp(el,'padding-right', '0');
                });
            }

            // ── Title: exactly 3-line height so all cards align below ──
            var title = c.querySelector('.product-title, h2, h3, .woocommerce-loop-product__title');
            if (title) {
                sp(title,'font-size',          '13.5px');
                sp(title,'font-weight',        '700');
                sp(title,'line-height',        '1.45');
                sp(title,'color',              '#1a1a1a');
                sp(title,'margin',             '0 0 8px');
                sp(title,'padding',            '0');
                sp(title,'display',            '-webkit-box');
                sp(title,'-webkit-line-clamp', '3');
                sp(title,'-webkit-box-orient', 'vertical');
                sp(title,'overflow',           'hidden');
                sp(title,'height',             'calc(1.45em * 3)');
                sp(title,'flex-shrink',        '0');
            }

            // ── Rating row ──
            var rating = c.querySelector('.rating, .woocommerce-product-rating, .product-meta-row');
            if (rating) {
                sp(rating,'display',     'flex');
                sp(rating,'align-items', 'center');
                sp(rating,'flex-wrap',   'nowrap');
                sp(rating,'gap',         '4px');
                sp(rating,'margin',      '0 0 4px');
                sp(rating,'padding',     '0');
                sp(rating,'line-height', '1.4');
                sp(rating,'height',      '22px');
                sp(rating,'overflow',    'hidden');
                sp(rating,'flex-shrink', '0');
            }
            var ratingCount = c.querySelector('.rating-count');
            if (ratingCount) {
                sp(ratingCount,'font-size',   '12px');
                sp(ratingCount,'color',       '#555');
                sp(ratingCount,'margin',      '0');
                sp(ratingCount,'padding',     '0');
                sp(ratingCount,'white-space', 'nowrap');
                sp(ratingCount,'flex-shrink', '0');
            }
            var stars = c.querySelector('.star-rating');
            if (stars) { sp(stars,'color','#c9a84c'); sp(stars,'font-size','12px'); sp(stars,'margin','0'); sp(stars,'padding','0'); sp(stars,'flex-shrink','0'); }

            // ── Price ──
            var price = c.querySelector('.price-section, .price, .product-price');
            if (price) {
                sp(price,'display',     'flex');
                sp(price,'flex-direction','row');
                sp(price,'flex-wrap',   'nowrap');
                sp(price,'align-items', 'center');
                sp(price,'gap',         '6px');
                sp(price,'font-size',   '14px');
                sp(price,'font-weight', '700');
                sp(price,'color',       '#1a1a1a');
                sp(price,'margin',      '0 0 6px');
                sp(price,'padding',     '0');
                sp(price,'min-height',  '22px');
                sp(price,'overflow',    'visible');
                sp(price,'flex-shrink', '0');
                sp(price,'white-space', 'nowrap');
            }
            var ins = c.querySelector('.price-section ins, .price ins');
            if (ins) { sp(ins,'display','inline-block'); sp(ins,'text-decoration','none'); sp(ins,'font-weight','700'); sp(ins,'color','#1a1a1a'); sp(ins,'font-size','15px'); }
            var del = c.querySelector('.price-section del, .price del, .old-price');
            if (del) {
                sp(del,'display','inline-block'); sp(del,'position','relative');
                sp(del,'color','#999'); sp(del,'font-weight','400'); sp(del,'font-size','13px');
                sp(del,'text-decoration','line-through'); sp(del,'text-decoration-color','#999'); sp(del,'text-decoration-thickness','1.5px');
                del.querySelectorAll('*').forEach(function(el){
                    sp(el,'color','#999'); sp(el,'text-decoration','line-through'); sp(el,'font-size','13px');
                });
            }

            var discount = c.querySelector('.price-section .discount, .discount-percentage, span.discount');
            if (discount) {
                sp(discount,'color','var(--af-save, #2e7d32)');
                sp(discount,'font-weight','600');
                sp(discount,'font-size','13px');
                discount.querySelectorAll('*').forEach(function(el){ sp(el,'color','var(--af-save, #2e7d32)'); });
            }

            // ── Description: 2-line clamp ──
            var desc = c.querySelector('.desc, p.desc, .product-excerpt, .short-description');
            if (desc) {
                sp(desc,'font-size',          '13px');
                sp(desc,'color',              '#555');
                sp(desc,'line-height',        '1.55');
                sp(desc,'margin',             '0 0 12px');
                sp(desc,'padding',            '0');
                sp(desc,'display',            '-webkit-box');
                sp(desc,'-webkit-line-clamp', '2');
                sp(desc,'-webkit-box-orient', 'vertical');
                sp(desc,'overflow',           'hidden');
                sp(desc,'height',             'calc(1.55em * 2)');
                sp(desc,'flex',               '0 0 auto');
            }

            // ── Spacer: fills remaining space to push buttons to bottom ──
            var spacer = c.querySelector('.product-spacer');
            if (!spacer) {
                spacer = document.createElement('div');
                spacer.className = 'product-spacer';
                var actionsEl = c.querySelector('.product-actions,.card-actions');
                if (actionsEl && actionsEl.parentNode) actionsEl.parentNode.insertBefore(spacer, actionsEl);
                else if (info) info.appendChild(spacer);
            }
            sp(spacer,'flex','1 1 auto');

            // ── Buttons row: always at bottom ──
            var actions = c.querySelector('.product-actions,.card-actions,.add-to-cart-wrap,.buttons-wrap,.product-buttons');
            if (actions) {
                sp(actions,'display',     'flex');
                sp(actions,'gap',         '8px');
                sp(actions,'padding',     '0 0 14px');
                sp(actions,'margin-top',  '0');
                sp(actions,'flex-shrink', '0');
                Array.from(actions.children).forEach(function(btn) {
                    sp(btn,'flex',            '1 1 50%');
                    sp(btn,'min-width',       '0');
                    sp(btn,'border',          'none');
                    sp(btn,'border-radius',   '7px');
                    sp(btn,'font-size',       '13px');
                    sp(btn,'font-weight',     '600');
                    sp(btn,'padding',         '11px 6px');
                    sp(btn,'cursor',          'pointer');
                    sp(btn,'display',         'flex');
                    sp(btn,'align-items',     'center');
                    sp(btn,'justify-content', 'center');
                    // 8px, matching every other card section. This inline
                    // !important is what silently beat the stylesheet's gap:
                    // it is the styler that builds the carousel cards, so
                    // Trending Today kept its 5px however the CSS was written.
                    sp(btn,'gap',             '8px');
                    sp(btn,'white-space',     'nowrap');
                    sp(btn,'text-decoration', 'none');
                    sp(btn,'line-height',     '1');
                    var cls = btn.className || '';
                    // Every spelling the sections ship: add-cart, add_to_cart_button,
                    // add-to-cart-btn. The old /add.cart|add_to_cart/ needed exactly
                    // ONE character between "add" and "cart", so add-to-cart-btn fell
                    // through and got painted black like Quick View.
                    var isCart = /add[-_ ]?(to[-_ ]?)?cart/i.test(cls);
                    sp(btn,'background', isCart ? '#c9a84c' : '#1a1a1a');
                    sp(btn,'color','#fff');
                    // inline !important cannot be hovered by a stylesheet
                    if (!btn.dataset.afHoverBound) {
                        btn.dataset.afHoverBound = '1';
                        btn.addEventListener('mouseenter', function(){
                            sp(btn,'background', isCart ? '#8b6a2b' : '#333');
                        });
                        btn.addEventListener('mouseleave', function(){
                            sp(btn,'background', isCart ? '#c9a84c' : '#1a1a1a');
                        });
                    }
                });
            }
        }


        // Log first card structure once to console so we can verify selectors.
        // Debug switch only (inc/debug-flag.php, DEF-12): it was 2.5 KB of
        // markup in every visitor's console.
        if (window.afDebugOn === true && freshCards.length && !window._afCardLogged) {
            window._afCardLogged = true;
            console.log('[AF] product-card HTML:', freshCards[0].outerHTML.substring(0, 2000));
        }

        freshCards.forEach(function(c) {
            track.appendChild(c);
            styleCard(c);

            // Completely hide the original YITH wishlist widget — our custom button triggers it
            var yith = c.querySelector('.yith-wcwl-add-to-wishlist');
            if (yith) yith.style.setProperty('display', 'none', 'important');

            // Create our own heart button, absolutely over the image top-right
            var heart = document.createElement('button');
            heart.className = 'af-wishlist-btn';
            heart.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
            heart.setAttribute('aria-label', 'Add to wishlist');
            heart.style.cssText = [
                'position:absolute','top:12px','right:12px','z-index:20',
                'width:36px','height:36px','border-radius:50%',
                'background:rgba(255,255,255,0.92)','border:none','cursor:pointer',
                'display:flex','align-items:center','justify-content:center',
                'box-shadow:0 2px 8px rgba(0,0,0,0.22)','padding:0','margin:0',
                'color:#555','transition:color 0.2s,background 0.2s'
            ].join('!important;') + '!important';
            heart.querySelector('svg').style.cssText = 'width:18px!important;height:18px!important;display:block!important';
            heart.addEventListener('mouseenter', function(){ this.style.setProperty('color','#c9a84c','important'); });
            heart.addEventListener('mouseleave', function(){ this.style.setProperty('color','#555','important'); });
            heart.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                // Click the real YITH button
                var realBtn = c.querySelector('.add_to_wishlist, .yith-wcwl-add-button a, .yith-wcwl-add-to-wishlist a');
                if (realBtn) realBtn.click();
                // Toggle filled heart
                var filled = this.dataset.wishlisted === '1';
                this.dataset.wishlisted = filled ? '0' : '1';
                this.querySelector('svg').setAttribute('fill', filled ? 'none' : '#c9a84c');
                this.style.setProperty('color', filled ? '#555' : '#c9a84c', 'important');
            });
            c.appendChild(heart);
        });
        vp.appendChild(track);
        shell.appendChild(btnP);
        shell.appendChild(vp);
        shell.appendChild(btnN);

        // Watch every rating row — if WooCommerce JS re-sets padding, instantly clear it
        // Disconnect before mutating to prevent observer→mutate→observer infinite loop
        var ratingMo = new MutationObserver(function() {
            ratingMo.disconnect();
            track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
                r.style.setProperty('padding', '0', 'important');
            });
            track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
                ratingMo.observe(r, { attributes: true, attributeFilter: ['style'] });
            });
        });
        function zeroRatingPadding() {
            track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
                r.style.setProperty('padding', '0', 'important');
            });
        }
        zeroRatingPadding();
        [200, 600, 1200].forEach(function(d){ setTimeout(zeroRatingPadding, d); });
        track.querySelectorAll('.rating,.woocommerce-product-rating,.product-meta-row').forEach(function(r) {
            ratingMo.observe(r, { attributes: true, attributeFilter: ['style'] });
        });

        grid.parentNode.insertBefore(shell, grid.nextSibling);
        grid.classList.add('af-grid-hidden');
        activeShell = shell;

        // Hide original widget nav buttons
        var ob = container.querySelector('.prev-prod');
        var nb = container.querySelector('.next-prod');
        if (ob) sp(ob, 'display', 'none');
        if (nb) sp(nb, 'display', 'none');

        /* ── Layout engine ───────────────────────────────── */
        var idx = 0;
        var GAP = 12;
        var BTN = 44;

        function vis() {
            return window.innerWidth <= 600 ? 1 : window.innerWidth <= 768 ? 2 : 4;
        }

        // Step 1: set shell/vp/btn structure so vp gets its natural flex width
        function applyShellLayout() {
            sp(shell, 'display',     'flex');
            sp(shell, 'align-items', 'center');
            sp(shell, 'gap',         GAP + 'px');
            sp(shell, 'width',       '100%');
            sp(shell, 'box-sizing',  'border-box');

            [btnP, btnN].forEach(function(b) {
                sp(b, 'flex',            '0 0 ' + BTN + 'px');
                sp(b, 'width',           BTN + 'px');
                sp(b, 'height',          BTN + 'px');
                sp(b, 'display',         'flex');
                sp(b, 'align-items',     'center');
                sp(b, 'justify-content', 'center');
            });

            // Let vp grow naturally to fill remaining width
            sp(vp, 'flex',      '1 1 auto');
            sp(vp, 'min-width', '0');
            sp(vp, 'overflow',  'hidden');

            sp(track, 'display',        'flex');
            sp(track, 'flex-direction', 'row');
            sp(track, 'flex-wrap',      'nowrap');
            sp(track, 'gap',            GAP + 'px');
        }

        // Step 2: measure vp actual width, then size cards
        function sizeCards() {
            var vpW = vp.getBoundingClientRect().width;
            if (!vpW || vpW < 60) return 0;
            var v  = Math.min(vis(), freshCards.length || 1);
            var cw = Math.floor((vpW - GAP * (v - 1)) / v);
            if (cw < 60) return 0;
            freshCards.forEach(function(c) {
                sp(c, 'flex',      '0 0 ' + cw + 'px');
                sp(c, 'width',     cw + 'px');
                sp(c, 'min-width', cw + 'px');
                sp(c, 'max-width', cw + 'px');
            });
            return cw;
        }

        function go(newIdx) {
            var v   = Math.min(vis(), freshCards.length || 1);
            var max = Math.max(0, freshCards.length - v);
            idx     = Math.max(0, Math.min(newIdx, max));
            var cw  = sizeCards() || 200;
            sp(track, 'transform', 'translateX(' + (-(idx * (cw + GAP))) + 'px)');
        }

        applyShellLayout();
        // Measure after browser has done flex layout (two rAF passes)
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                go(0);
            });
        });
        setTimeout(function() { go(0); }, 300);
        setTimeout(function() { go(0); }, 900);

        btnP.addEventListener('click', function() { go(idx - vis()); });
        btnN.addEventListener('click', function() { go(idx + vis()); });
        window.addEventListener('resize', function() { idx = 0; go(0); });
    }

    var _sliderInitDone = false;
    function init() {
        // RE-ENABLED (homepage only): the vanishing-products bug was traced to
        // the category-page card styler, not this slider. Guards below keep the
        // slider strictly off archive/category/shop pages, and the
        // ensureProductsVisible() safety net un-hides the grid if the shell
        // ever fails to render cards.
        var b = document.body;
        var isHome = b && (b.classList.contains('af-front-page') || b.classList.contains('home') || b.classList.contains('front-page'));
        var isArchive = b && (b.classList.contains('archive') || b.classList.contains('tax-product_cat') || b.classList.contains('post-type-archive-product') || b.classList.contains('woocommerce-shop') || b.classList.contains('search'));
        if (!isHome || isArchive) return;

        // Find the REAL product slider (a container whose cards have prices),
        // so we never grab the "Products In Motion" video section.
        var containers = document.querySelectorAll('.product-container');
        var container = null, grid = null;
        for (var i = 0; i < containers.length; i++) {
            var g = containers[i].querySelector('#productGrid') || containers[i].querySelector('.product-slider');
            if (!g) continue;
            var cards = Array.from(g.querySelectorAll('.product-card'));
            if (!cards.length) continue;
            var hasPrice = cards.some(function(c){
                return c.querySelector('.price, .price-section, .add-cart, .add_to_cart_button, [class*="price"]');
            });
            if (hasPrice) { container = containers[i]; grid = g; break; }
        }
        if (!container || !grid) return;

        if (_sliderInitDone) return; // observer already attached, skip
        _sliderInitDone = true;

        // Initial build
        buildSlider(container, grid);

        // Watch for AJAX loads and show/hide category switches
        var rebuildTimer = null;
        var _building = false;
        var mo = new MutationObserver(function(mutations) {
            if (_building) return;
            // Only trigger when product-cards are ADDED (not removed, not attribute changes)
            var hasAddedCards = mutations.some(function(m) {
                if (m.type !== 'childList' || !m.addedNodes.length) return false;
                return Array.from(m.addedNodes).some(function(n) {
                    return n.nodeType === 1 && (
                        (n.classList && n.classList.contains('product-card')) ||
                        (n.querySelectorAll && n.querySelectorAll('.product-card').length > 0)
                    );
                });
            });
            if (!hasAddedCards) return;
            clearTimeout(rebuildTimer);
            // 500ms debounce — wait for all AJAX cards to finish inserting
            rebuildTimer = setTimeout(function() {
                if (_building) return;
                var newCards = Array.from(grid.querySelectorAll('.product-card'));
                if (!newCards.length) return; // grid not ready yet, skip
                _building = true;
                buildSlider(container, grid);
                setTimeout(function() { _building = false; }, 400);
            }, 500);
        });

        // Watch only childList (card additions/removals), not attribute changes
        mo.observe(grid, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }
    window.addEventListener('load', function() {
        init();
        setTimeout(init, 500);
    });
    // The theme fills #productGrid via AJAX; since the collection now loads ALL
    // products the response can land well after the retries above — keep
    // retrying until the cards exist (up to ~30s), then stop.
    (function(){
        var tries = 0;
        var poll = setInterval(function(){
            if (_sliderInitDone || ++tries > 60) { clearInterval(poll); return; }
            init();
        }, 500);
    })();

    // Safety net: never leave a product grid hidden unless a slider shell is
    // actually showing its cards. Guarantees products stay visible everywhere.
    function ensureProductsVisible(){
        document.querySelectorAll('.af-grid-hidden').forEach(function(grid){
            if (!grid.querySelector('.product-card')) return;
            var shell = grid.parentNode ? grid.parentNode.querySelector('.af-shell') : null;
            var shellCards = shell ? shell.querySelectorAll('.product-card').length : 0;
            if (shellCards === 0) grid.classList.remove('af-grid-hidden');
        });
    }
    setInterval(ensureProductsVisible, 700);
}());
