<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Stop the pictures being fetched as files.
 *
 * The page-level guard closes "Save image as", the drag, and Ctrl+S. It cannot
 * close the route that does not go through the page at all: the image's own
 * URL. Copy image address, open image in new tab, paste the URL in the address
 * bar, or link to it from another site, and the web server hands over the full
 * file without WordPress ever running. No script on the page can prevent that,
 * because no script on the page is involved.
 *
 * Apache and LiteSpeed will, though, and Hostinger runs LiteSpeed. The rules
 * below go into wp-content/uploads/.htaccess and turn on the one distinction
 * that matters: a browser asking for a picture IN ORDER TO DRAW A PAGE sends
 * Sec-Fetch-Dest: image, while a browser asking for it as a destination of its
 * own — a new tab, a typed URL, "save link as" — sends Sec-Fetch-Dest:
 * document. The first is the site working. The second is a download.
 *
 * So: pictures still render everywhere, and Inspect, the network tab and
 * view-source all keep working untouched. What stops is the file arriving as a
 * file.
 *
 * WHAT IS DELIBERATELY LEFT OPEN
 *   - Old browsers that send no Sec-Fetch-Dest header at all are allowed
 *     through when the referer is this site, so the site does not break for
 *     them. They are a rounding error and they still cannot hotlink.
 *   - The major search crawlers, so Google Images keeps indexing the work.
 *     A person who forges a crawler's user-agent is past the point any of
 *     this was built to stop.
 *   - woocommerce_uploads/ is never touched. Paid files live there and
 *     WooCommerce serves them through PHP.
 *
 * WHAT THIS STILL CANNOT DO, and should not be sold as doing: a screenshot,
 * and the network tab of anyone who opens it. The page shows a preview; the
 * thing being sold is the full-resolution master.
 *
 * Run:  DRY=1 wp eval-file tools/protect-images.php --allow-root   (reports only)
 *       wp eval-file tools/protect-images.php --allow-root         (writes)
 *       REVERT=1 wp eval-file tools/protect-images.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$dry    = (bool) getenv( 'DRY' );
$revert = (bool) getenv( 'REVERT' );

$START = '# BEGIN The Art Framer image protection';
$END   = '# END The Art Framer image protection';

$up   = wp_upload_dir();
$base = isset( $up['basedir'] ) ? $up['basedir'] : '';
$file = rtrim( $base, '/' ) . '/.htaccess';

echo "=== IMAGE PROTECTION " . ( $revert ? '(REVERT)' : ( $dry ? '(REPORT ONLY)' : '(WRITING)' ) ) . " ===\n\n";
echo "  uploads dir : {$base}\n";
echo "  htaccess    : {$file}\n";
echo "  server      : " . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '(cli — unknown)' ) . "\n";
echo "  home url    : " . home_url() . "\n";

// A guard, not a nicety. If WooCommerce hands paid files over by redirecting
// the customer to the file's own URL, these rules would block the thing the
// customer just paid for. Check before writing anything.
$dl_method = get_option( 'woocommerce_file_download_method', 'force' );
echo "  WC download : {$dl_method}\n";
if ( ! $revert && $dl_method === 'redirect' ) {
    echo "\nABORT: WooCommerce is set to 'Redirect only' for file downloads.\n";
    echo "  That sends a paying customer to the file's own URL, which is exactly\n";
    echo "  what these rules block. Switch WooCommerce to 'Force downloads' or\n";
    echo "  'X-Accel-Redirect' first (WooCommerce > Settings > Products > Downloadable\n";
    echo "  products), then run this again. Nothing has been changed.\n";
    return;
}

if ( ! $base || ! is_dir( $base ) ) { echo "\nABORT: uploads directory not found.\n"; return; }

$existing = file_exists( $file ) ? file_get_contents( $file ) : '';
echo "\n  existing .htaccess: " . ( $existing === '' ? '(none)' : strlen( $existing ) . " bytes" ) . "\n";
if ( $existing !== '' ) {
    echo "  ---- current contents ----\n";
    foreach ( explode( "\n", rtrim( $existing ) ) as $l ) echo "  | {$l}\n";
    echo "  --------------------------\n";
}

$host = wp_parse_url( home_url(), PHP_URL_HOST );
$host_re = preg_quote( $host, '#' );

$block = <<<HTA
{$START}
# Managed by tools/protect-images.php in the postero-child theme. Edits between
# these markers are replaced on the next run; put anything of your own outside.
<IfModule mod_rewrite.c>
RewriteEngine On

# woocommerce_uploads holds files people have paid for; WooCommerce serves
# those through PHP and must not be interfered with here.
RewriteRule ^woocommerce_uploads/ - [L]

# Let the search crawlers through so the work stays indexed.
RewriteCond %{HTTP_USER_AGENT} (googlebot|google-inspectiontool|bingbot|duckduckbot|yandex|baiduspider|applebot|slurp|pinterest|facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot) [NC]
RewriteRule ^ - [L]

# A picture requested AS A PAGE — new tab, typed URL, copied image address —
# is a download, not the site rendering. Refuse it.
#
# Only 'document' is listed. 'empty' is what a fetch() or XHR sends, and the
# wall visualiser and the lazy-loaders pull pictures that way from our own
# pages; blocking it would break them. Those are caught by the referer rule
# underneath if they come from anywhere but here.
RewriteCond %{HTTP:Sec-Fetch-Dest} ^document\$ [NC]
RewriteRule \\.(jpe?g|png|gif|webp|avif|bmp|tiff?|svg)\$ - [F,L]

# Anything from another site, or with no referer at all, when the browser did
# not say it was drawing a page. Hotlinking and bare fetchers.
RewriteCond %{HTTP:Sec-Fetch-Dest} !^image\$ [NC]
RewriteCond %{HTTP_REFERER} !^https?://(www\\.)?{$host_re}/ [NC]
RewriteCond %{HTTP_REFERER} !^\$
RewriteRule \\.(jpe?g|png|gif|webp|avif|bmp|tiff?|svg)\$ - [F,L]
</IfModule>
{$END}
HTA;

// Strip any previous copy of our block, keep everything else exactly as it was.
$clean = $existing;
if ( strpos( $clean, $START ) !== false ) {
    $clean = preg_replace( '#\n*' . preg_quote( $START, '#' ) . '.*?' . preg_quote( $END, '#' ) . '\n*#s', "\n", $clean );
}
$clean = trim( $clean );

$new = $revert ? ( $clean === '' ? '' : $clean . "\n" )
               : ( $clean === '' ? $block . "\n" : $clean . "\n\n" . $block . "\n" );

if ( $new === $existing ) { echo "\nAlready exactly as wanted. Nothing written.\n"; return; }

echo "\n  ---- would write ----\n";
foreach ( explode( "\n", rtrim( $new ) ) as $l ) echo "  | {$l}\n";
echo "  ---------------------\n";

if ( $dry ) { echo "\nReport only. Nothing was written.\n"; return; }

if ( $existing !== '' ) {
    $bak = $file . '.af-backup-' . gmdate( 'Ymd-His' );
    file_put_contents( $bak, $existing );
    echo "\n  previous file backed up to " . basename( $bak ) . "\n";
}

if ( $new === '' ) { @unlink( $file ); echo "  .htaccess removed (it held nothing else).\n"; }
else {
    $ok = file_put_contents( $file, $new );
    if ( $ok === false ) { echo "\nFAILED to write {$file} — check permissions. Nothing changed.\n"; return; }
    @chmod( $file, 0644 );
    echo "  wrote " . $ok . " bytes.\n";
}
echo "\nDone. " . ( $revert ? 'Protection removed.' : 'Direct-URL and hotlink access to images is now refused.' ) . "\n";
