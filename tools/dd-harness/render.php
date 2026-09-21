<?php
/**
 * Renders the Digital Download modal out of functions.php into a standalone
 * page, so its behaviour can be driven offline.
 *
 *   php tools/dd-harness/render.php
 *
 * The block lives inside a wp_footer closure and needs a handful of WordPress
 * functions; they are stubbed below. The page it writes also carries a fake
 * product card and - the point of the exercise - the CLICK TRAP the live quick
 * view leaves behind: a document-level CAPTURE listener that calls
 * stopPropagation() on every click. That single line is why the × did nothing
 * on the live site, so the harness would prove nothing without it.
 */
$root = dirname(__DIR__, 2);
$src  = file_get_contents($root . '/functions.php');

$a = strpos($src, '<div id="af-dd-overlay"');
if ($a === false) { fwrite(STDERR, "modal markup not found\n"); exit(1); }
$b = strpos($src, '</style>', $a);
if ($b === false) { fwrite(STDERR, "modal style end not found\n"); exit(1); }
$block = substr($src, $a, $b - $a + 8);

// The only PHP inside the block: two wp_json_encode calls and the price html.
$block = preg_replace('/<\?php\s+echo\s+wp_json_encode\(admin_url\([^)]*\)\);\s*\?>/', '"/admin-ajax.php"', $block);
$block = preg_replace('/<\?php\s+echo\s+\$price_html;\s*\?>/', '<span class="price">$9.99</span>', $block);
$block = preg_replace('/<\?php.*?\?>/s', 'null', $block);

$html = <<<HTML
<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DD modal harness</title>
<style>body{font-family:system-ui;margin:0;padding:24px}
.product{border:1px solid #ddd;padding:12px;width:260px}
.woosq-btn{padding:8px 12px}</style>
</head><body>
<h1>Digital Download modal — offline</h1>
<!-- Spread the card across the viewport, BEHIND the modal. On the live
     category page the modal covers a grid of cards, so a stray click that
     lands after the modal has gone hits a card and reopens the quick view -
     which is exactly the reopen this harness has to be able to catch. -->
<li class="product post-8424 digital-download-card" style="position:fixed;inset:0;width:auto;z-index:1">
  <a href="/product/divine-varanasi/"><img src="/preview.png" alt="art" width="40" height="40"></a>
  <h3 class="product-title"><a href="/product/divine-varanasi/">Divine Varanasi Ganga Aarti</a></h3>
  <button class="woosq-btn quick-view-btn" data-product-id="8424">eye</button>
  <span class="digital-download">Digital Download</span>
</li>

<script>
/* THE TRAP. This is what the live page does, reduced to its one relevant line:
   a document-level capture listener that stops every click from descending.
   Verified on theartframer.us - the capture chain recorded "document" and
   nothing after it. Set window.__noTrap before load to run without it. */
if (!window.__noTrap) {
  document.addEventListener('click', function (e) { e.stopPropagation(); }, true);
}
</script>

$block
</body></html>
HTML;

$dir = __DIR__ . '/www';
if (!is_dir($dir)) mkdir($dir, 0777, true);
file_put_contents($dir . '/index.html', $html);
fwrite(STDERR, sprintf("wrote %s (%d bytes)\n", $dir . '/index.html', strlen($html)));
