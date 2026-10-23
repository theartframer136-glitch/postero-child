<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests the pure parts of inc/markup-fixes.php with WordPress stubbed:
 *     php tools/test-markup-fixes.php
 */
define('ABSPATH', '/nowhere/');
function add_action() {} function add_filter() {}
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
require __DIR__ . '/../inc/markup-fixes.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

echo "=== H1 fallback ===\n";
$page = '<html><body class="page"><div><h2>Terms of Use</h2></div></body></html>';
$out = af_mf_hidden_h1($page, 'Terms Of Use');
same('a page with no H1 gets one, straight after <body>', (bool) preg_match('~<body class="page"><h1 class="af-sr-only"[^>]*>Terms Of Use</h1><div>~', $out), true);
same('the visible content is unchanged', strpos($out, '<h2>Terms of Use</h2>') !== false, true);
same('a page that has an H1 is left alone', af_mf_hidden_h1('<body><h1>X</h1></body>', 'Y'), '<body><h1>X</h1></body>');
same('an H1 only inside a script does not count', af_mf_has_h1('<body><script>var a="<h1>x</h1>"</script></body>'), false);
same('an empty title adds nothing', af_mf_hidden_h1($page, ''), $page);
same('the title is escaped', strpos(af_mf_hidden_h1('<body></body>', 'A & <b>'), 'A &amp; &lt;b&gt;') !== false, true);

echo "\n=== card pictures ===\n";
$have = function ($u) { return strpos($u, 'scene1-149-768x576.jpg') !== false; };
same('a 1024px hover picture switches to the 768px copy',
    af_mf_card_images('<img class="hover-img" src="https://x.us/wp-content/uploads/2026/09/scene1-149-1024x768.jpg" alt="">', $have),
    '<img class="hover-img" src="https://x.us/wp-content/uploads/2026/09/scene1-149-768x576.jpg" alt="">');
same('left alone when the 768px copy does not exist',
    af_mf_card_images('<img class="hover-img" src="https://x.us/wp-content/uploads/2026/09/scene1-150-1024x768.jpg">', $have),
    '<img class="hover-img" src="https://x.us/wp-content/uploads/2026/09/scene1-150-1024x768.jpg">');
same('a picture already under 1000px is left alone',
    af_mf_card_images('<img class="main-img" src="/wp-content/uploads/a-600x800.jpg">', function () { return true; }),
    '<img class="main-img" src="/wp-content/uploads/a-600x800.jpg">');
same('a picture with a srcset is left to the browser',
    af_mf_card_images('<img class="main-img" srcset="x 1x" src="/wp-content/uploads/a-1024x768.jpg">', function () { return true; }),
    '<img class="main-img" srcset="x 1x" src="/wp-content/uploads/a-1024x768.jpg">');
same('other pictures are never touched',
    af_mf_card_images('<img class="wp-post-image" src="/wp-content/uploads/a-1024x768.jpg">', function () { return true; }),
    '<img class="wp-post-image" src="/wp-content/uploads/a-1024x768.jpg">');

echo "\n=== logo ===\n";
$set = function ($u) { return strpos($u, 'Business-Cards') !== false ? 'a-300x100.png 300w, a-768x256.png 768w' : ''; };
$logo = '<img class="hfe-site-logo-img elementor-animation-" src="https://x.us/wp-content/uploads/2026/02/Business-Cards.pdf-scaled.png" alt="">';
same('the logo gets a srcset and sizes', (bool) preg_match('~^<img srcset="a-300x100.png 300w, a-768x256.png 768w" sizes="[^"]+" class="hfe-site-logo-img~', af_mf_logo_srcset($logo, $set)), true);
same('no srcset known: unchanged', af_mf_logo_srcset(str_replace('Business-Cards', 'other', $logo), $set), str_replace('Business-Cards', 'other', $logo));

echo "\n=== pictures saved under a new name ===\n";
$disk = array('https://x.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25-1.webp' => 1,
              'https://x.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25-1-1024x572.webp' => 1,
              'https://x.us/wp-content/uploads/2026/02/fine.jpg' => 1);
$ex = function ($u) use ($disk) { return isset($disk[$u]); };
$in = '<a href="https://x.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25.webp"><img src="https://x.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25-1024x572.webp" srcset="https://x.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25-1024x572.webp 1024w"></a>';
$out = af_mf_mend_renamed_uploads($in, $ex);
same('the full-size link is mended to the -1 file', strpos($out, 'href="https://x.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25-1.webp"') !== false, true);
same('the sized src is mended with its size kept', strpos($out, 'src="https://x.us/wp-content/uploads/2026/02/wall-art-care-202604031610-69cf9a2f4de25-1-1024x572.webp"') !== false, true);
same('srcset entries are mended too', strpos($out, '-1-1024x572.webp 1024w') !== false, true);
same('a file that exists is never touched', af_mf_mend_renamed_uploads('<img src="https://x.us/wp-content/uploads/2026/02/fine.jpg">', $ex), '<img src="https://x.us/wp-content/uploads/2026/02/fine.jpg">');
same('a missing file with no -1 twin is left as it is', af_mf_mend_renamed_uploads('<img src="https://x.us/wp-content/uploads/2026/02/gone.jpg">', $ex), '<img src="https://x.us/wp-content/uploads/2026/02/gone.jpg">');

echo "\n=== page numbers in titles ===\n";
same('page 2 goes before the site name', af_mf_paged_title('Shop Hindu God Paintings | The Art Framer', 2), 'Shop Hindu God Paintings – Page 2 | The Art Framer');
same('page 1 is unchanged', af_mf_paged_title('Shop | The Art Framer', 1), 'Shop | The Art Framer');
same('a title that already says the page is unchanged', af_mf_paged_title('Shop Page 3 | X', 3), 'Shop Page 3 | X');
same('a title with no separator gets it at the end', af_mf_paged_title('Wall Art', 4), 'Wall Art – Page 4');
same('the last separator is used', af_mf_paged_title('Radha Krishna | Wall Decor | USA', 2), 'Radha Krishna | Wall Decor – Page 2 | USA');

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
