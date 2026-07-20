<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Write HTTP security headers into the DOCROOT .htaccess.
 * PHP `send_headers` headers don't appear on LiteSpeed cache HITS (PHP is
 * skipped), so set them at the web-server layer where they apply to every
 * response, cached or not. Wrapped in <IfModule mod_headers.c> so it is a
 * no-op (never a 500) if the module is unavailable.
 *
 * Idempotent: replaces its own BEGIN/END block, leaves the rest untouched.
 * Run: wp eval-file tools/set-security-headers.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$htaccess = rtrim( ABSPATH, '/\\' ) . '/.htaccess';

$block  = "# BEGIN Art Framer Security Headers\n";
$block .= "<IfModule mod_headers.c>\n";
$block .= "    Header always set X-Frame-Options \"SAMEORIGIN\"\n";
$block .= "    Header always set X-Content-Type-Options \"nosniff\"\n";
$block .= "    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n";
$block .= "    Header always set Permissions-Policy \"geolocation=(), camera=(), microphone=()\"\n";
$block .= "    Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"\n";
$block .= "</IfModule>\n";
$block .= "# END Art Framer Security Headers\n";

$existing = file_exists( $htaccess ) ? file_get_contents( $htaccess ) : '';
if ( $existing === false ) { echo "Cannot read {$htaccess}\n"; exit(1); }

// Strip any previous copy of our block, then prepend a fresh one.
$clean = preg_replace(
    '/# BEGIN Art Framer Security Headers.*?# END Art Framer Security Headers\s*/s',
    '',
    $existing
);

$new = $block . "\n" . ltrim( $clean );

// Only write if changed, and only if the target is writable.
if ( $new === $existing ) { echo "Security headers already current.\n=== DONE ===\n"; return; }
if ( file_exists( $htaccess ) && ! is_writable( $htaccess ) ) {
    echo "WARNING: {$htaccess} is not writable — headers NOT applied.\n"; exit(1);
}
if ( file_put_contents( $htaccess, $new ) === false ) {
    echo "WARNING: failed to write {$htaccess}\n"; exit(1);
}
echo "Security headers written to docroot .htaccess.\n=== DONE ===\n";
