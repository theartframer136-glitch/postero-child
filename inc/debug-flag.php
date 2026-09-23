<?php
/**
 * One switch for the theme's console logging, off for visitors.
 *
 * DEF-12. Two debug loggers ran for every visitor:
 *
 *   [af-header-row] 796px ONE LINE row=elementor-element… :: …
 *       ~246 bytes on every resize, plus a getBoundingClientRect() of every
 *       header control to build the line, which forces a layout each time
 *   [AF] product-card HTML: <div class="product-card" …
 *       ~2.5 KB, the first card's full markup, srcsets and all, on every load
 *
 * A third, [af-cp] in inc/corporate-collection.php, logs three lines each
 * time the corporate tab is clicked. It is the same kind of trail and is
 * gated the same way.
 *
 * They were written to be read. A screenshot of the console answered layout
 * questions without another deploy, and tools/diag-corp-tab.mjs reads the
 * [af-cp] lines. So they are switched off for visitors rather than deleted,
 * and can still be turned on when needed:
 *
 *   one page:   add ?af_debug=1 to the URL
 *   sticky:     localStorage.setItem('af_debug', '1') in the console
 *               (localStorage.removeItem('af_debug') to turn it off)
 *
 * The flag is decided in the browser, not by PHP. These pages are served from
 * LiteSpeed's cache, so a flag printed per request would be cached and
 * handed to whoever came next. The same few bytes go to everyone. Only the
 * browser's own URL and storage decide whether they log.
 *
 * Printed first in <head> so it exists before the footer scripts that read
 * it. Those scripts check window.afDebugOn === true, so if this script is
 * ever missing they stay silent rather than falling back to logging.
 *
 * NOT GATED: the "failed:" and "ERROR" lines. They only print when something
 * has actually broken, which is when someone needs to see them.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
    if (is_admin()) return;
    echo "<script id=\"af-debug-flag\">window.afDebugOn=(function(){try{"
        . "return /[?&]af_debug=1(?:&|#|$)/.test(location.search)||localStorage.getItem('af_debug')==='1';"
        . "}catch(e){return false;}})();</script>\n";
}, 1);
