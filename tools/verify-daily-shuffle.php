<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove the seven-day rotation is a rotation: seven different orders, the
 * eighth day the same as the first, and not one product lost or duplicated
 * along the way. Read-only — it runs the real query, and writes nothing.
 *
 * Run: wp eval-file tools/verify-daily-shuffle.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

if (!function_exists('af_shuffle_seed_for_day')) {
    echo "inc/daily-shuffle.php is not loaded — nothing to verify.\n=== DONE ===\n";
    return;
}

$fail = 0;
function af_ds_check($label, $ok, &$fail, $extra = '') {
    printf("  %-52s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}

echo "=== DAILY SHUFFLE — the seven-day loop ===\n";
$period = af_shuffle_period();
printf("  today is day %d of %d, seed %d\n", af_shuffle_day_index(), $period, af_shuffle_seed());

// ── the seeds themselves ───────────────────────────────────────────────────
$seeds = array();
for ($d = 0; $d < $period; $d++) { $seeds[$d] = af_shuffle_seed_for_day($d); }
af_ds_check('seven distinct seeds', count(array_unique($seeds)) === $period, $fail,
            implode(' ', $seeds));

// day N and day N+7 are the same day of the loop, N+1 is not
$same = true; $diff = true;
for ($d = 0; $d < $period; $d++) {
    if (af_shuffle_seed_for_day($d) !== af_shuffle_seed_for_day($d + $period)) $same = false;
    if (af_shuffle_seed_for_day($d) === af_shuffle_seed_for_day($d + 1))       $diff = false;
}
af_ds_check('day N and day N+7 share a seed (it loops)', $same, $fail);
af_ds_check('consecutive days do not', $diff, $fail);

// and the index really advances a day at a time, over a fortnight
$today = (int) current_time('timestamp');
$walk  = array();
for ($i = 0; $i < 14; $i++) { $walk[] = af_shuffle_day_index($today + $i * DAY_IN_SECONDS); }
$expected = array();
for ($i = 0; $i < 14; $i++) { $expected[] = ($walk[0] + $i) % $period; }
af_ds_check('the index walks 0..6 and wraps, over 14 days', $walk === $expected, $fail,
            implode(',', $walk));

// ── the orders the shop will actually serve ────────────────────────────────
echo "\n=== The seven orders, from the real catalogue ===\n";
global $wpdb;
$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'"
);
$total = count($ids);
echo "  published products: {$total}\n";

if ($total < 2) {
    echo "  (too few products to order — skipping the ordering checks)\n";
} else {
    $orders = array();
    for ($d = 0; $d < $period; $d++) {
        $seed = af_shuffle_seed_for_day($d);
        $orders[$d] = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_type='product' AND post_status='publish'
              ORDER BY RAND(%d)", $seed));
        printf("  day %d  seed %-8d first 8: %s\n", $d, $seed,
               implode(' ', array_slice($orders[$d], 0, 8)));
    }

    // every day must show every product exactly once
    $sorted_all = $ids; sort($sorted_all);
    $complete = true; $sized = true;
    foreach ($orders as $d => $o) {
        if (count($o) !== $total) { $sized = false; }
        $s = $o; sort($s);
        if ($s !== $sorted_all) { $complete = false; }
    }
    af_ds_check('every day lists all ' . $total . ' products', $sized, $fail);
    af_ds_check('no product dropped or duplicated on any day', $complete, $fail);

    // the seven must be different from each other
    $pairs = 0; $distinct = 0;
    for ($a = 0; $a < $period; $a++) {
        for ($b = $a + 1; $b < $period; $b++) {
            $pairs++;
            if ($orders[$a] !== $orders[$b]) $distinct++;
        }
    }
    af_ds_check('all 21 day-pairs differ in order', $pairs === $distinct, $fail,
                "{$distinct}/{$pairs}");

    // and the same seed twice must give the same order, or pagination breaks
    $again = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
          WHERE post_type='product' AND post_status='publish'
          ORDER BY RAND(%d)", af_shuffle_seed_for_day(0)));
    af_ds_check('one seed, run twice, gives one order (paging is safe)',
                $again === $orders[0], $fail);
}

// ── the rollover ───────────────────────────────────────────────────────────
echo "\n=== Rollover ===\n";
$next = wp_next_scheduled('af_shuffle_rollover');
af_ds_check('a purge is scheduled', (bool) $next, $fail,
            $next ? 'next: ' . get_date_from_gmt(gmdate('Y-m-d H:i:s', $next), 'Y-m-d H:i') . ' site time' : '');

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : 'ALL CHECKS PASSED') . " ===\n";
