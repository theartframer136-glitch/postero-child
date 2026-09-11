<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests the demo-link guard — TAF-01, the mobile bottom bar pointing at the
 * theme vendor's demo store.
 *
 * Runs the REAL functions from inc/demo-guard.php with WordPress stubbed, so
 * this cannot drift from what ships. Plain `php` runs it:
 *
 *     php tools/test-demo-guard.php
 */
define('ABSPATH', '/nowhere/');
function add_action() {}
function add_filter() {}
function apply_filters($tag, $value) { return $value; }
function home_url() { return 'https://theartframer.us'; }

require __DIR__ . '/../inc/demo-guard.php';

const HOME = 'https://theartframer.us';

$pass = 0; $fail = 0;
function is_same($label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; printf("  OK    %s\n", $label); }
    else { $fail++; printf("  FAIL  %s\n        got  %s\n        want %s\n", $label, var_export($got, true), var_export($want, true)); }
}
function rewrites($label, $in, $want) { is_same($label, af_demo_rewrite($in, HOME), $want); }

echo "=== the bottom bar, exactly as the audit found it ===\n";
rewrites('Shop',
    '<a href="https://demo2wpopal.b-cdn.net/postero/shop/">Shop</a>',
    '<a href="https://theartframer.us/shop/">Shop</a>');
rewrites('Account',
    '<a href="https://demo2wpopal.b-cdn.net/postero/my-account/">Account</a>',
    '<a href="https://theartframer.us/my-account/">Account</a>');
rewrites('Wishlist',
    '<a href="https://demo2wpopal.b-cdn.net/postero/wishlist">Wishlist</a>',
    '<a href="https://theartframer.us/wishlist">Wishlist</a>');

echo "\n=== every other way a URL can be written ===\n";
rewrites('http, not https',
    '<a href="http://demo2wpopal.b-cdn.net/postero/cart/">Cart</a>',
    '<a href="https://theartframer.us/cart/">Cart</a>');
rewrites('protocol-relative, as a CDN host often is',
    '<a href="//demo2wpopal.b-cdn.net/postero/shop/">Shop</a>',
    '<a href="https://theartframer.us/shop/">Shop</a>');
rewrites('the bare host with no /postero prefix',
    '<a href="https://demo2wpopal.b-cdn.net/thing/">Thing</a>',
    '<a href="https://theartframer.us/thing/">Thing</a>');
rewrites('the old staging hostname',
    '<a href="https://chocolate-chicken-365829.hostingersite.com/about/">About</a>',
    '<a href="https://theartframer.us/about/">About</a>');
rewrites('inside a script, not just an href',
    '<script>var u="https://demo2wpopal.b-cdn.net/postero/api";</script>',
    '<script>var u="https://theartframer.us/api";</script>');
rewrites('several on one line, all of them',
    '<a href="https://demo2wpopal.b-cdn.net/postero/a">A</a><a href="//demo2wpopal.b-cdn.net/postero/b">B</a>',
    '<a href="https://theartframer.us/a">A</a><a href="https://theartframer.us/b">B</a>');

echo "\n=== THE GUARANTEE: a real link is never touched ===\n";
$untouched = array(
    'the site itself'      => '<a href="https://theartframer.us/shop/">Shop</a>',
    'a relative link'      => '<a href="/cart/">Cart</a>',
    'an outbound link'     => '<a href="https://www.paypal.com/checkout">Pay</a>',
    'another b-cdn tenant' => '<a href="https://someoneelse.b-cdn.net/img.jpg">img</a>',
    'a lookalike host'     => '<a href="https://demo2wpopal.b-cdn.net.evil.test/x">x</a>',
    'plain page copy'      => '<p>Canvas prints, gallery wrapped.</p>',
);
foreach ($untouched as $label => $html) is_same($label . ': unchanged', af_demo_rewrite($html, HOME), $html);

echo "\n=== the cheap path: pages with nothing to do ===\n";
is_same('a clean page is detected as clean', af_demo_present('<html><body>Hello</body></html>'), false);
is_same('a dirty page is detected as dirty', af_demo_present('<a href="https://demo2wpopal.b-cdn.net/postero/">x</a>'), true);
is_same('af_demo_filter_page leaves a clean page identical',
        af_demo_filter_page('<html><body>Hello</body></html>'), '<html><body>Hello</body></html>');
is_same('empty string survives',  af_demo_filter_page(''), '');
is_same('a non-string is returned as-is', af_demo_filter_page(null), null);

echo "\n=== running it twice changes nothing more ===\n";
$once  = af_demo_rewrite('<a href="https://demo2wpopal.b-cdn.net/postero/shop/">S</a>', HOME);
$twice = af_demo_rewrite($once, HOME);
is_same('idempotent', $twice, $once);

echo "\n=== a trailing slash on the home URL does not double up ===\n";
is_same('home_url with a trailing slash',
        af_demo_rewrite('<a href="https://demo2wpopal.b-cdn.net/postero/shop/">S</a>', 'https://theartframer.us/'),
        '<a href="https://theartframer.us/shop/">S</a>');

echo "\n=== end to end: a real page through the output buffer ===\n";
// Not the pure function this time — ob_start() with the very callback the
// module hands to WordPress, so the mechanism that does the work on the live
// site is the mechanism under test.
// Two buffers, deliberately. ob_get_clean() returns the RAW buffer — PHP
// never applies the output callback to it — so capturing that way tests
// nothing. The inner buffer is flushed THROUGH the callback into an outer
// plain buffer, which is what WordPress does at the end of a request.
ob_start();                          // outer: plain capture
ob_start('af_demo_filter_page');     // inner: the module's own callback
?>
<nav class="postero-mobile-nav">
  <a href="https://demo2wpopal.b-cdn.net/postero/shop/">Shop</a>
  <a href="https://demo2wpopal.b-cdn.net/postero/my-account/">Account</a>
  <a href="https://demo2wpopal.b-cdn.net/postero/wishlist">Wishlist</a>
  <a href="/cart/">Cart</a>
</nav>
<?php
ob_end_flush();                      // inner flushes through the callback
$page = ob_get_clean();              // outer now holds the processed page
is_same('no demo host survives the buffer', strpos($page, 'demo2wpopal') === false, true);
is_same('Shop now points at this site',
        strpos($page, 'href="https://theartframer.us/shop/"') !== false, true);
is_same('Account now points at this site',
        strpos($page, 'href="https://theartframer.us/my-account/"') !== false, true);
is_same('Wishlist now points at this site',
        strpos($page, 'href="https://theartframer.us/wishlist"') !== false, true);
is_same('the relative Cart link is left exactly alone',
        strpos($page, 'href="/cart/"') !== false, true);
is_same('all four links still present', substr_count($page, '<a href='), 4);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
