<?php
/**
 * A guest's wishlist shows what that guest saved. Owner's report, 24 Sep:
 * the heart counter said 1, the wishlist page said "no products".
 *
 * Measured live as a first-time guest (tools/diag-wladd.mjs):
 *
 * 1. The header heart sent everyone to /wishlist/WOOSW. WOOSW is the
 *    plugin's stand-in for "this visitor has no key yet". The page cache
 *    stores each page as a keyless visitor sees it, so every cached page
 *    carries that link, and a list nobody owns is always empty. A freshly
 *    built page links to the visitor's own key; the cached one never does.
 *    The /?add-to-wishlist= links redirect there too.
 *
 * 2. /wishlist/ itself was served from the page cache, public, for 7 days.
 *    The second run was shown the FIRST run's list (key UV6RP0), so a
 *    visitor could be handed someone else's wishlist.
 *
 * So:
 *   - Wishlist pages are never cached. The plugin reads the visitor's own
 *     list from their cookie, which only works on a page built for them.
 *   - /wishlist/WOOSW goes to /wishlist/, where the cookie decides. Shared
 *     links (/wishlist/<a real key>/) are untouched.
 *   - Links already sitting in cached pages are pointed at /wishlist/ in
 *     the browser, so the visitor skips the extra hop.
 */
if (!defined('ABSPATH')) exit;

function af_wl_is_wishlist_request() {
    $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    return (bool) preg_match('#^/wishlist(/|$)#i', $path);
}

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || !af_wl_is_wishlist_request()) return;

    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    do_action('litespeed_control_set_nocache', 'wishlist: built from the visitor\'s own cookie');
    nocache_headers();

    $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#^/wishlist/WOOSW/?$#i', $path)) {
        wp_safe_redirect(home_url('/wishlist/'), 302);
        exit;
    }
}, 0);

// The plugin's own address for a keyless visitor: send them to the plain page.
add_filter('woosw_wishlist_url', function ($url, $key = null) {
    if (is_string($url) && preg_match('#/wishlist/WOOSW/?$#i', $url)) {
        return home_url('/wishlist/');
    }
    return $url;
}, 10, 2);

add_action('wp_footer', function () {
    ?>
<script id="af-wl-guest-links">
(function(){
  function fix(root){
    var as=(root||document).querySelectorAll('a[href*="/wishlist/WOOSW"],a[href*="/wishlist/woosw"]');
    for(var i=0;i<as.length;i++){as[i].setAttribute('href','/wishlist/');}
  }
  fix();
  var queued=false;
  if(window.MutationObserver){new MutationObserver(function(){
      if(queued)return;queued=true;
      setTimeout(function(){queued=false;fix();},200);})
    .observe(document.documentElement,{childList:true,subtree:true});}
})();
</script>
    <?php
}, 99);
