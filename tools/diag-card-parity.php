<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Compare the homepage's product-card sections FUNCTION by FUNCTION.
 * Slices the rendered homepage at its section headings and counts, per
 * section, how many cards carry each functional element — so a feature
 * present in one section and absent in another names itself. Read-only.
 * Run: wp eval-file tools/diag-card-parity.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

$r = null;
for ($i = 0; $i < 4; $i++) {
    $r = wp_remote_get(home_url('/?nocache=' . time()),
        array('timeout' => 90, 'sslverify' => false, 'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Diag')));
    if (!is_wp_error($r) && (int) wp_remote_retrieve_response_code($r) === 200) break;
    sleep(5);
}
if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
    echo "Homepage fetch failed (" . (is_wp_error($r) ? $r->get_error_message() : wp_remote_retrieve_response_code($r)) . ")\n";
    return;
}
$html = wp_remote_retrieve_body($r);
echo "homepage: " . number_format(strlen($html) / 1024) . " KB\n\n";

// Section anchors, in whatever order they appear in the page
$anchors = array(
    'Shop by Collection'   => 'Shop by Collection',
    'Trending Today'       => 'Trending Today',
    'Corporate Signages'   => 'Corporate Signages',
    'New Arrivals'         => 'New Arrivals',
);
$pos = array();
foreach ($anchors as $label => $needle) {
    $p = stripos($html, $needle);
    if ($p !== false) $pos[$label] = $p;
}
asort($pos);
$labels = array_keys($pos);
$slices = array();
for ($i = 0; $i < count($labels); $i++) {
    $start = $pos[$labels[$i]];
    $end   = isset($labels[$i + 1]) ? $pos[$labels[$i + 1]] : min(strlen($html), $start + 400000);
    $slices[$labels[$i]] = substr($html, $start, $end - $start);
}

// The functional elements a complete card carries
$functions = array(
    'product link'        => '#href="[^"]*/product/#i',
    'price shown'         => '#woocommerce-Price-amount|class="price#i',
    'old-price strike'    => '#<del|old-price#i',
    'discount label'      => '#\(\s*\d+%\s*off\s*\)|class="discount#i',
    'star rating'         => '#star-rating|af-stars#i',
    'art code'            => '#af-art-code#i',
    'wishlist'            => '#wishlist|woosw|tinvwl#i',
    'quick view'          => '#quick-?view|woosq#i',
    'add to cart'         => '#add_to_cart|add-to-cart#i',
    'digital download'    => '#digital[- ]download#i',
    'try on wall (AR)'    => '#af-card-ar|\?tow=#i',
    'sale badge'          => '#class="[^"]*onsale#i',
    'sold count'          => '#af-sold#i',
);

$counts = array();
foreach ($slices as $label => $chunk) {
    // card count: li.product or .product-card occurrences
    preg_match_all('#<li[^>]+class="[^"]*\bproduct\b|class="[^"]*product-card#i', $chunk, $mm);
    $cards = count($mm[0]);
    $counts[$label] = array('cards' => $cards);
    foreach ($functions as $fn => $re) {
        preg_match_all($re, $chunk, $m);
        $counts[$label][$fn] = count($m[0]);
    }
}

// print the matrix
$fn_names = array_keys($functions);
printf("%-20s", 'FUNCTION \ SECTION');
foreach ($labels as $l) printf(" | %-18s", substr($l, 0, 18));
echo "\n";
printf("%-20s", 'cards found');
foreach ($labels as $l) printf(" | %-18d", $counts[$l]['cards']);
echo "\n" . str_repeat('-', 22 + 21 * count($labels)) . "\n";
foreach ($fn_names as $fn) {
    printf("%-20s", $fn);
    foreach ($labels as $l) {
        $n = $counts[$l][$fn];
        $c = $counts[$l]['cards'];
        $mark = ($c > 0 && $n === 0) ? ' <<< MISSING' : '';
        printf(" | %-18s", $n . ($mark ? $mark : ''));
    }
    echo "\n";
}

echo "\nMISSING SUMMARY (function absent in a section that has cards):\n";
$any = false;
foreach ($fn_names as $fn) {
    $missing = array();
    $has     = array();
    foreach ($labels as $l) {
        if ($counts[$l]['cards'] === 0) continue;
        if ($counts[$l][$fn] === 0) $missing[] = $l; else $has[] = $l;
    }
    if ($missing && $has) {
        $any = true;
        echo "  - {$fn}: MISSING in [" . implode(', ', $missing) . "] but present in [" . implode(', ', $has) . "]\n";
    } elseif ($missing && !$has) {
        $any = true;
        echo "  - {$fn}: missing in ALL sections\n";
    }
}
if (!$any) echo "  (none — all sections carry the same functions)\n";
echo "\n=== DONE ===\n";
