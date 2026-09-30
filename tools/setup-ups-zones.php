<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Load the studio's own UPS zone chart, so the zone at checkout is the one
 * UPS will bill rather than the one the mileage bands predict.
 *
 * UPS publishes a zone chart per origin ZIP prefix. Download the one for 197
 * (ups.com → Shipping support → Daily rates and zone charts → enter 19707 →
 * "Download zone chart"), put the CSV on the server, and run:
 *
 *   wp eval-file tools/setup-ups-zones.php --allow-root -- /path/to/197.csv
 *
 * The chart's first column is the destination ZIP prefix or range
 * ("004-005", "010", "995-999"); the "Ground" column is the zone. Anything
 * the chart lists as a non-numeric zone (Alaska/Hawaii codes and the like)
 * is left to the state rule in af_ups_zone(). Idempotent: re-running with
 * the same file changes nothing; running with no file prints what is loaded.
 *
 * Reversible:  wp option delete af_ups_zones
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== UPS ZONE CHART ===\n";
$have = get_option('af_ups_zones');
if (is_string($have)) $have = json_decode($have, true);
printf("  ZIP3 prefixes loaded now: %d\n", is_array($have) ? count($have) : 0);

$file = null;
foreach ((array) $argv as $a) {
    if (substr($a, -4) === '.csv' && is_readable($a)) $file = $a;
}
if (!$file) { echo "  no CSV given — nothing changed\n=== DONE ===\n"; return; }

$fh = fopen($file, 'r');
if (!$fh) { echo "  cannot open {$file}\n=== DONE ===\n"; return; }

$ground_col = null;
$zones = array();
$rows = 0;
while (($cols = fgetcsv($fh)) !== false) {
    if (!isset($cols[0])) continue;
    if ($ground_col === null) {
        foreach ($cols as $i => $c) {
            if (strcasecmp(trim($c), 'Ground') === 0) $ground_col = $i;
        }
        if ($ground_col !== null) continue;         // header row found
        if (!preg_match('/^\d{3}/', trim($cols[0]))) continue;
        $ground_col = 1;                            // headerless: assume ZIP, Ground
    }
    $rows++;
    $range = trim($cols[0]);
    $zone  = isset($cols[$ground_col]) ? trim($cols[$ground_col]) : '';
    if (!preg_match('/^(\d{3})(?:\s*-\s*(\d{3}))?$/', $range, $m)) continue;
    if (!preg_match('/^\d+$/', $zone)) continue;   // AK/HI codes: handled by state
    $z = (int) $zone;
    if ($z < 2 || $z > 8) continue;
    $from = (int) $m[1];
    $to   = isset($m[2]) ? (int) $m[2] : $from;
    for ($p = $from; $p <= $to; $p++) $zones[str_pad((string) $p, 3, '0', STR_PAD_LEFT)] = $z;
}
fclose($fh);

printf("  rows read: %d, ZIP3 prefixes with a Ground zone: %d\n", $rows, count($zones));
if (count($zones) < 100) { echo "  too few prefixes — is this a UPS zone chart? nothing changed\n=== DONE ===\n"; return; }

ksort($zones);
if (is_array($have) && $have == $zones) {
    echo "  already loaded — nothing to do\n";
} else {
    update_option('af_ups_zones', $zones, false);
    echo "  saved to option af_ups_zones\n";
}
$counts = array_count_values($zones);
ksort($counts);
foreach ($counts as $z => $n) printf("    zone %d: %4d prefixes\n", $z, $n);

// WooCommerce caches computed package rates
delete_transient('shipping-transient-version');
echo "=== DONE ===\n";
