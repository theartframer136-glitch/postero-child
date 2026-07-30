<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Build every marketplace feed once, from the command line, into the cache.
 *
 * Each feed is 341 products with a permalink and image lookups apiece. Built on
 * demand over HTTP, three in a row is enough to hit the host's resource limit
 * and hand a channel a 508 instead of a catalogue. Warming them here means the
 * expensive work happens in CLI where no web request is waiting, and every
 * later fetch — by us or by Google — is served from cache.
 * Run: wp eval-file tools/warm-marketplace-feeds.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('af_mk_channels')) { echo "Marketplace module not loaded — skipped.\n"; return; }

echo "=== WARMING MARKETPLACE FEEDS ===\n";
af_mk_flush_feeds();                       // rebuild against the current catalogue
foreach (af_mk_channels() as $channel => $meta) {
    $t0  = microtime(true);
    $doc = af_mk_feed_cached($channel, $meta['kind']);
    printf("  %-26s %7s KB  %4.1fs\n", $meta['label'], number_format(strlen($doc) / 1024, 0), microtime(true) - $t0);
    usleep(400000);                        // let the box breathe between builds
}
echo "=== DONE ===\n";
