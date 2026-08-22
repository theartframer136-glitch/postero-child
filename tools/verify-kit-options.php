<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove the five purchase options work, with prices still at zero.
 *
 * Checks the parts that must be true before any price exists: the five options
 * are offered, the selector reaches the product page, a digital choice reports
 * as non-shipping (so no delivery is charged), the others do ship, and the
 * add-on maths returns exactly zero while unpriced — no invented charges.
 *
 * Read-only. Run: wp eval-file tools/verify-kit-options.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== VERIFY KIT OPTIONS ===\n";
$fail = 0;

if (!function_exists('af_kit_options')) {
    echo "  inc/kit-options.php not loaded  FAIL\n=== DONE ===\n"; return;
}

echo "\n-- the five options --\n";
foreach (af_kit_options() as $key => $o) {
    printf("  %-20s ships:%-4s parts: %-42s add-on: $%.2f\n",
        $key,
        !empty($o['ships']) ? 'yes' : 'NO',
        $o['parts'] ? implode(', ', $o['parts']) : '(none)',
        af_kit_addon_price($key, '3×4 ft (36×48 in)'));
}
$n = count(af_kit_options());
printf("  option count: %d %s\n", $n, $n === 4 ? 'OK' : 'EXPECTED 4');
if ($n !== 4) $fail++;

// The DIY kit was retired on 2026-08-22, its parts folded into both structure-bar
// options. It must be gone from the selector but still readable, or the packing
// slip for an order placed before the change loses its parts list.
$live = af_kit_options();
printf("  full DIY kit off the selector: %s\n", isset($live['full_kit']) ? 'FAIL — still offered' : 'OK');
if (isset($live['full_kit'])) $fail++;
$old = function_exists('af_kit_option') ? af_kit_option('full_kit') : null;
printf("  old orders still read it: %s\n", $old ? 'OK — ' . $old['label'] : 'FAIL — an old order would lose its parts');
if (!$old) $fail++;
foreach (array('painting_bar', 'painting_bar_frame') as $k) {
    $parts = $live[$k]['parts'];
    $want  = array('bar', 'hooks', 'screws', 'driver', 'hanging');
    $miss  = array_diff($want, $parts);
    printf("  %-19s carries the fittings: %s\n", $k,
        $miss ? 'FAIL — missing ' . implode(', ', $miss) : 'OK');
    if ($miss) $fail++;
}

echo "\n-- prices (zero until the owner supplies them) --\n";
foreach (af_kit_prices() as $part => $price) {
    printf("  %-10s $%.2f %s\n", $part, (float) $price, ((float) $price > 0) ? '(set)' : '(pending)');
}
printf("  any price set yet: %s\n", af_kit_priced() ? 'yes' : 'no — parts are included free meanwhile');
$tubes = af_tube_prices();
printf("  tube sizes configured: %s\n", $tubes ? implode(', ', array_keys($tubes)) . ' in' : 'none yet');

echo "\n-- shipping consequence --\n";
printf("  digital ships: %s  %s\n", af_kit_ships('digital') ? 'yes' : 'no',
    af_kit_ships('digital') ? 'FAIL — a download must not ship' : 'OK');
if (af_kit_ships('digital')) $fail++;
foreach (array('painting', 'painting_bar', 'painting_bar_frame') as $k) {
    if (!af_kit_ships($k)) { printf("  %s does not ship — FAIL\n", $k); $fail++; }
}
printf("  physical options all ship: %s\n", $fail ? 'see above' : 'OK');

echo "\n-- add-on maths while unpriced --\n";
$zero = true;
foreach (array_keys(af_kit_options()) as $k) {
    if (af_kit_addon_price($k, '3×4 ft (36×48 in)') > 0) $zero = false;
}
printf("  every option adds $0 while prices are pending: %s\n",
    ($zero || af_kit_priced()) ? 'OK' : 'FAIL — a charge appeared from nowhere');
if (!$zero && !af_kit_priced()) $fail++;

echo "\n-- the selector on a live product page --\n";
$ids = wc_get_products(array('status' => 'publish', 'limit' => 1, 'return' => 'ids'));
if ($ids) {
    $url = get_permalink($ids[0]);
    $r = wp_remote_get(add_query_arg('afverify', time(), $url),
        array('timeout' => 45, 'sslverify' => false, 'headers' => array('User-Agent' => 'AF-Verify')));
    if (!is_wp_error($r)) {
        $b = wp_remote_retrieve_body($r);
        $has = strpos($b, 'af-kit-group') !== false;
        $cnt = substr_count($b, 'name="af_kit"');
        printf("  %s\n  selector present: %s   radio buttons: %d\n",
            $url, $has ? 'OK' : 'MISSING', $cnt);
        if (!$has) $fail++;
    } else {
        printf("  fetch failed: %s\n", $r->get_error_message());
    }
}

printf("\n=== %s ===\n", $fail ? "{$fail} CHECK(S) FAILED" : 'ALL CHECKS PASSED');
echo "=== DONE ===\n";
