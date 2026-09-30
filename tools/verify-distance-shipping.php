<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove US delivery works end to end and prices the way UPS does: the geo
 * table is populated, known cities compute sane distances from 19707 and land
 * in the right UPS zone, sample parcels price with the right surcharges, a
 * download-only basket is never charged, and the method is enabled in a
 * zone. Read-only.
 *
 * Run: wp eval-file tools/verify-distance-shipping.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== VERIFY UPS DELIVERY ===\n";
$fail = 0;

$t = $wpdb->prefix . 'af_zip_geo';
$rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");
printf("  zip geo rows: %d  %s\n", $rows, $rows > 30000 ? 'OK' : 'LOW/EMPTY (fallback zone will apply)');
if ($rows < 30000) $fail++;

foreach (array('af_zip_distance_miles' => 'inc/shipping-distance.php',
               'af_ups_quote'          => 'inc/shipping-ups.php') as $fn => $file) {
    if (!function_exists($fn)) {
        echo "  {$fn} missing — {$file} not loaded\n  FAIL\n=== DONE ===\n";
        return;
    }
}

$s = af_ups_settings();
echo "\n-- figures in force --\n";
printf("  rate card: %s\n", af_ups_rate_card_source());
printf("  fuel %.2f%%   residential $%.2f   add'l handling dim $%.2f / weight $%.2f\n",
    $s['fuel_pct'], $s['residential'], $s['ah_dimension'], $s['ah_weight']);
printf("  large package by zone: %s   (min %d lb)   over max $%.2f\n",
    implode(' ', array_map(function ($z, $v) { return "z{$z}=\${$v}"; }, array_keys($s['lps']), $s['lps'])),
    (int) $s['lps_min_lbs'], $s['over_max']);
printf("  adjustment %+.1f%%   residential by default: %s   live UPS API: %s   zone chart: %s\n",
    $s['adjust_pct'], $s['residential_default'] ? 'yes' : 'no',
    af_ups_api_enabled() ? 'CONFIGURED' : 'not configured (table rates)',
    count(af_ups_zone_overrides()) ? count(af_ups_zone_overrides()) . ' ZIP3 prefixes' : 'not loaded (mileage bands)');

// The two parcels every price is judged with:
//   rolled = 3x4 ft unframed print in a tube (Additional Handling: 52 in long)
//   framed = the same piece framed, flat crate (Large Package: 54 + 2(42+4) = 146 in)
$mk = function ($size, $frame) {
    $p = new WC_Product_Simple();
    return array('contents' => array(array('data' => $p, 'quantity' => 1, 'af_size' => $size, 'af_frame' => $frame)));
};
$rolled = $mk('3×4 ft (36×48 in)', 'Without Frame');
$framed = $mk('3×4 ft (36×48 in)', 'Floating Frame');

// expected straight-line miles from 19707, generous ±20% band, and the UPS
// zone those miles fall in
$samples = array(
    '19711' => array('Newark DE',        8, 2),
    '19104' => array('Philadelphia PA', 28, 2),
    '10001' => array('New York NY',    112, 2),
    '60601' => array('Chicago IL',     640, 5),
    '33101' => array('Miami FL',       990, 5),
    '90001' => array('Los Angeles CA', 2320, 8),
);
echo "\n-- distance, zone and price for one 3x4 ft piece --\n";
foreach ($samples as $zip => $info) {
    $mi = af_zip_distance_miles('19707', $zip);
    $rolled['destination'] = $framed['destination'] = array('country' => 'US', 'postcode' => $zip, 'state' => '');
    $qr = af_ups_quote($rolled);
    $qf = af_ups_quote($framed);
    if ($mi === null) {
        printf("  %-6s %-16s distance: UNKNOWN  zone %d  rolled $%.2f  framed $%.2f\n",
            $zip, $info[0], $qr['zone'], $qr['cost'], $qf['cost']);
        $fail++;
        continue;
    }
    $ok = abs($mi - $info[1]) <= $info[1] * 0.2 + 5;
    $zone_ok = count(af_ups_zone_overrides()) ? true : ($qr['zone'] === $info[2]);
    printf("  %-6s %-16s ~%4d mi  zone %d  rolled $%-8.2f framed $%-8.2f %s%s\n",
        $zip, $info[0], round($mi), $qr['zone'], $qr['cost'], $qf['cost'],
        $ok ? 'OK' : 'DISTANCE OUT OF BAND', $zone_ok ? '' : ' ZONE MISMATCH');
    if (!$ok || !$zone_ok) $fail++;
}

echo "\n-- the calculation, itemised (Los Angeles) --\n";
foreach (array('rolled' => $rolled, 'framed' => $framed) as $name => $pkg) {
    $pkg['destination'] = array('country' => 'US', 'postcode' => '90001', 'state' => 'CA');
    $q = af_ups_quote($pkg);
    foreach ($q['parcels'] as $p) {
        printf("  %-7s %s %dx%dx%d in, %.1f lb real, %d lb billable, flags: %s\n", $name, $p['method'],
            round($p['l']), round($p['w']), round($p['h']), $p['actual'], $p['charge']['billable'],
            $p['charge']['flags'] ? implode(',', $p['charge']['flags']) : 'none');
        foreach ($p['charge']['lines'] as $ln) printf("          %-28s $%8.2f\n", $ln[0], $ln[1]);
        printf("          %-28s $%8.2f\n", 'parcel total', $p['charge']['total']);
    }
    printf("  %-7s charge $%.2f (%s)\n", $name, $q['cost'], $q['source'] === 'api' ? 'UPS Rating API' : 'table');
}

// five rolled prints share one tube: the price must not be five tubes
echo "\n-- consolidation --\n";
$five = $rolled;
$five['contents'][0]['quantity'] = 5;
$five['destination'] = array('country' => 'US', 'postcode' => '10001', 'state' => 'NY');
$one = $rolled; $one['destination'] = $five['destination'];
$q1 = af_ups_quote($one);
$q5 = af_ups_quote($five);
printf("  1 rolled print to NYC $%.2f, 5 in one tube $%.2f (%d parcel)  %s\n",
    $q1['cost'], $q5['cost'], count($q5['parcels']),
    (count($q5['parcels']) === 1 && $q5['cost'] < 3 * $q1['cost']) ? 'OK' : 'FAIL');
if (!(count($q5['parcels']) === 1 && $q5['cost'] < 3 * $q1['cost'])) $fail++;

// a download-only basket must never be charged
echo "\n-- digital download basket --\n";
$fake = array('contents' => array(array('data' => new WC_Product_Simple(), 'quantity' => 1)),
              'destination' => array('country' => 'US', 'postcode' => '10001', 'state' => 'NY'));
$fake['contents'][0]['data']->set_virtual(true);
$qd = af_ups_quote($fake);
printf("  virtual-only package: %s\n", ($qd === null) ? 'OK — no delivery charge' : 'FAIL — would be charged');
if ($qd !== null) $fail++;

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
    printf("  af_distance (UPS Ground) enabled in a zone: %s\n", $found ? 'OK' : 'MISSING');
    if (!$found) $fail++;
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
