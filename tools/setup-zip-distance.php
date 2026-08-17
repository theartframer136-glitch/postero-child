<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Load the US ZIP → latitude/longitude table the distance shipping needs.
 *
 * Source: US Census ZCTA centre points (public domain, ~33k rows), fetched
 * once from a stable mirror. Idempotent — if the table is already populated
 * it reports the count and stops. If the download fails the method still
 * works: unknown ZIPs fall back to the flat af_distance_fallback rate.
 *
 * Run: wp eval-file tools/setup-zip-distance.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== ZIP GEO TABLE ===\n";
$t = $wpdb->prefix . 'af_zip_geo';

$wpdb->query("CREATE TABLE IF NOT EXISTS {$t} (
    zip CHAR(5) NOT NULL PRIMARY KEY,
    lat DECIMAL(9,6) NOT NULL,
    lng DECIMAL(9,6) NOT NULL
) DEFAULT CHARSET=ascii");

$have = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");
printf("  rows already present: %d\n", $have);
if ($have > 30000) { echo "  table is populated — nothing to do\n=== DONE ===\n"; return; }

// Public-domain 2013 Census ZCTA gazetteer, mirrored as plain CSV (ZIP,LAT,LNG).
$src = 'https://gist.githubusercontent.com/erichurst/7882666/raw/'
     . '5bdc46db47d9515269ab12ed6fb2850377fd869e/US%20Zip%20Codes%20from%202013%20Government%20Data';
echo "  downloading census ZIP centre points...\n";
$res = wp_remote_get($src, array('timeout' => 120, 'sslverify' => true));
if (is_wp_error($res)) {
    echo "  DOWNLOAD FAILED: " . $res->get_error_message() . "\n";
    echo "  (shipping still works — unknown ZIPs use the flat fallback rate)\n=== DONE ===\n";
    return;
}
$body = wp_remote_retrieve_body($res);
printf("  received %d bytes\n", strlen($body));

$lines = preg_split('/\r?\n/', $body);
$batch = array();
$n = 0;
foreach ($lines as $i => $line) {
    if ($i === 0 && stripos($line, 'ZIP') !== false) continue;   // header
    $p = explode(',', trim($line));
    if (count($p) < 3) continue;
    $zip = str_pad(preg_replace('/\D/', '', $p[0]), 5, '0', STR_PAD_LEFT);
    $lat = (float) $p[1];
    $lng = (float) $p[2];
    if (strlen($zip) !== 5 || !$lat || !$lng) continue;
    $batch[] = $wpdb->prepare('(%s,%f,%f)', $zip, $lat, $lng);
    if (count($batch) >= 1000) {
        $wpdb->query("REPLACE INTO {$t} (zip,lat,lng) VALUES " . implode(',', $batch));
        $n += count($batch);
        $batch = array();
    }
}
if ($batch) {
    $wpdb->query("REPLACE INTO {$t} (zip,lat,lng) VALUES " . implode(',', $batch));
    $n += count($batch);
}
printf("  rows loaded: %d\n", $n);
printf("  table now holds: %d\n", (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}"));
echo "=== DONE ===\n";
