<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove distance shipping works end to end: the geo table is populated, known
 * cities compute sane distances from 19707, each distance lands in the right
 * tier, and the method is enabled in a zone. Read-only.
 *
 * Run: wp eval-file tools/verify-distance-shipping.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== VERIFY DISTANCE SHIPPING ===\n";
$fail = 0;

$t = $wpdb->prefix . 'af_zip_geo';
$rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");
printf("  zip geo rows: %d  %s\n", $rows, $rows > 30000 ? 'OK' : 'LOW/EMPTY (fallback rate will apply)');
if ($rows < 30000) $fail++;

if (!function_exists('af_zip_distance_miles')) {
    echo "  af_zip_distance_miles missing — inc/shipping-distance.php not loaded\n  FAIL\n";
    echo "=== DONE ===\n"; return;
}

// expected straight-line miles from 19707, generous ±20% band
$samples = array(
    '19711' => array('Newark DE',        8),
    '19104' => array('Philadelphia PA', 28),
    '10001' => array('New York NY',    112),
    '60601' => array('Chicago IL',     640),
    '33101' => array('Miami FL',       990),
    '90001' => array('Los Angeles CA', 2320),
);
foreach ($samples as $zip => $info) {
    $mi = af_zip_distance_miles('19707', $zip);
    if ($mi === null) {
        printf("  %-6s %-16s distance: UNKNOWN  rate: $%.2f (fallback)\n", $zip, $info[0], af_distance_rate(null));
        $fail++;
        continue;
    }
    $ok = abs($mi - $info[1]) <= $info[1] * 0.2 + 5;
    printf("  %-6s %-16s ~%4d mi (expected ~%d)  rate: $%.2f  %s\n",
        $zip, $info[0], round($mi), $info[1], af_distance_rate($mi), $ok ? 'OK' : 'OUT OF BAND');
    if (!$ok) $fail++;
}

echo "\n-- tiers in force --\n";
foreach (af_distance_tiers() as $tier) {
    printf("  up to %5d mi  $%-6.2f %s\n", (int) $tier['mi'], (float) $tier['cost'],
        isset($tier['label']) ? $tier['label'] : '');
}
printf("  source: %s\n", get_option('af_distance_tiers') ? 'af_distance_tiers option (owner-set)' : 'DEFAULTS — owner has not set real rates yet');

echo "\n-- zones --\n";
if (class_exists('WC_Shipping_Zones')) {
    $zones = WC_Shipping_Zones::get_zones();
    $zones[] = array('zone_name' => 'Rest of the world', 'id' => 0,
                     'shipping_methods' => WC_Shipping_Zones::get_zone(0)->get_shipping_methods());
    $found = false;
    foreach ($zones as $z) {
        foreach ((array) $z['shipping_methods'] as $m) {
            $on = (isset($m->enabled) && $m->enabled === 'yes') ? 'enabled' : 'disabled';
            printf("  %-22s %-28s %s\n", mb_strimwidth($z['zone_name'], 0, 22), $m->id, $on);
            if ($m->id === 'af_distance' && $on === 'enabled') $found = true;
        }
    }
    printf("  af_distance enabled in a zone: %s\n", $found ? 'OK' : 'MISSING');
    if (!$found) $fail++;
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
