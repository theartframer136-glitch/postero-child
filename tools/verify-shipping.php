<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove the §12 shipping logic computes real numbers, not just that hooks exist.
 * Read-only.
 * Run: wp eval-file tools/verify-shipping.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;
function af_sv($label, $ok, &$fail, $extra = '') {
    printf("  %-44s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}

echo "=== SHIPPING & LOGISTICS (§12) ===\n";
af_sv('module loaded', function_exists('af_ship_package'), $fail);
if (!function_exists('af_ship_package')) { echo "\n=== RESULT: 1 PROBLEM(S) ===\n"; return; }

echo "\n--- size parsing ---\n";
$in = af_ship_inches('3×5 ft (36×60 in)');
af_sv('reads inches from the label', $in === array(36.0, 60.0), $fail, $in ? implode('x', $in) : 'null');
af_sv('falls back to feet', af_ship_inches('2×3 ft') === array(24.0, 36.0), $fail);
af_sv('ignores nonsense', af_ship_inches('gift card') === null, $fail);

echo "\n--- packaging decisions ---\n";
$small = af_ship_package('2×3 ft (24×36 in)', 'Without Frame');
af_sv('small unframed rolls in a tube', $small && $small['method'] === 'tube', $fail, $small['method'] ?? '');
$framed = af_ship_package('2×3 ft (24×36 in)', 'Fibre Frame');
af_sv('framed always goes flat', $framed && $framed['method'] === 'crate', $fail, $framed['method'] ?? '');
$big = af_ship_package('4×6 ft (48×72 in)', 'Without Frame');
af_sv('oversized goes flat even unframed', $big && $big['method'] === 'crate', $fail, $big['method'] ?? '');

echo "\n--- dimensional weight ---\n";
// a 48x36x4 carton at divisor 139 is ~49.7 lb billable even though it weighs less
$dim = af_ship_dim_weight(54, 42, 4, 139);
af_sv('dim weight formula', abs($dim - (54*42*4/139)) < 0.01, $fail, round($dim, 2) . ' lb');
$bill_small = af_ship_billable_weight($small);
$bill_big   = af_ship_billable_weight($big);
af_sv('billable >= actual (small)', $bill_small >= $small['weight'], $fail, round($bill_small, 2) . ' lb');
af_sv('billable >= actual (large)', $bill_big >= $big['weight'], $fail, round($bill_big, 2) . ' lb');
af_sv('bigger piece costs more to ship', $bill_big > $bill_small, $fail,
      round($bill_small, 1) . ' vs ' . round($bill_big, 1) . ' lb');

echo "\n--- surcharge bands ---\n";
$bands = af_ship_surcharge_table();
af_sv('bands descend by size', $bands[0]['over'] > $bands[count($bands)-1]['over'], $fail, count($bands) . ' bands');
af_sv('cart fee hook', (bool) has_action('woocommerce_cart_calculate_fees'), $fail);
af_sv('dimensions applied to cart items', (bool) has_action('woocommerce_before_calculate_totals'), $fail);

echo "\n--- customs ---\n";
af_sv('customs line builder', function_exists('af_ship_customs_lines'), $fail);
af_sv('international detection', function_exists('af_ship_needs_customs'), $fail);

echo "\n--- partial shipments ---\n";
af_sv('shipment recorder', function_exists('af_ship_add_shipment'), $fail);
af_sv('shipped-qty accounting', function_exists('af_ship_shipped_qty'), $fail);
af_sv('order screen metabox', (bool) has_action('add_meta_boxes'), $fail);

echo "\n--- returns / RMA (extends the existing system) ---\n";
global $wpdb;
$t = af_rma_table();
af_sv('rma table', $wpdb->get_var("SHOW TABLES LIKE '{$t}'") === $t, $fail, $t);
af_sv('single rma implementation', function_exists('af_rma_admin_page'), $fail);
af_sv('pickup field hooked in', (bool) has_action('af_rma_admin_extra'), $fail);
af_sv('pickup save hooked in', (bool) has_action('af_rma_admin_saved'), $fail);
$open = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");
echo "    returns on file: {$open}\n";

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
