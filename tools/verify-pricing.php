<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The printed "Pine Wood Framing Sizes & Pricing" card is the price book.
 * Prove the engine charges exactly what the card says, on every surface that
 * computes a price: af_calc_price (the cart's authority), the product page's
 * embedded config, and the Try-On-Wall config. If someone reintroduces
 * multiplier pricing, this fails the deploy with the card row that broke.
 * Read-only.
 * Run: wp eval-file tools/verify-pricing.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;

function af_prv($label, $ok, &$fail, $extra = '') {
    printf("  %-52s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra !== '' ? '  ' . $extra : '');
    if (!$ok) $fail++;
}

echo "=== PRICING vs THE RATE CARD ===\n";

// every size the card prints, verbatim
$card = array(
    '2×3 ft (24×36 in)' => 60,
    '3×4 ft (36×48 in)' => 80,
    '3×6 ft (36×72 in)' => 120,
    '4×3 ft (48×36 in)' => 80,
    '4×4 ft (48×48 in)' => 110,
    '4×5 ft (48×60 in)' => 130,
    '4×6 ft (48×72 in)' => 150,
);
$cfg = af_pricing_config();
$bad = array();
foreach ($card as $size => $usd) {
    if (!isset($cfg['sizes'][$size]) || (float)$cfg['sizes'][$size] !== (float)$usd) {
        $bad[] = $size . ' => ' . (isset($cfg['sizes'][$size]) ? $cfg['sizes'][$size] : 'missing') . " (card: {$usd})";
    }
}
af_prv('config matches every card row', !$bad, $fail, $bad ? implode('; ', $bad) : count($card) . ' rows exact');
af_prv('floating frame is the card\'s +$50', (float)$cfg['frames']['Floating Frame'] === 50.0, $fail,
       '+$' . $cfg['frames']['Floating Frame']);

// the authoritative calculator, spot-checked against the card
$cases = array(
    array('2×3 ft (24×36 in)', 'Without Frame',   'Black', 60.0,  'card 2×3, unframed'),
    array('4×4 ft (48×48 in)', 'Aluminium Frame', 'Black', 165.0, 'card 4×4 + alu 55'),
    array('4×6 ft (48×72 in)', 'Floating Frame',  'Black', 200.0, 'card 4×6 + floating 50'),
    array('4×6 ft (48×72 in)', 'Aluminium Frame', 'Gold',  215.0, 'card 4×6 + alu 55 + gold 10'),
    array('3×6 ft (36×72 in)', 'Without Frame',   'Gold',  120.0, 'unframed never pays a colour fee'),
);
foreach ($cases as $c) {
    $got = af_calc_price(179, $c[0], $c[1], $c[2]);
    af_prv($c[4], abs($got - $c[3]) < 0.005, $fail, '$' . $got . ' (want $' . $c[3] . ')');
}
// the listing price must no longer leak into the size price
$a = af_calc_price(999, '4×4 ft (48×48 in)', 'Without Frame', 'Black');
$b = af_calc_price(10,  '4×4 ft (48×48 in)', 'Without Frame', 'Black');
af_prv('listing price does not change the size price', $a === $b, $fail, "\${$a} at both \$999 and \$10 listings");
// and the old multiplier ceiling is gone
$max = af_calc_price(179, '4×6 ft (48×72 in)', 'Aluminium Frame', 'Rose Gold');
af_prv('dearest possible piece is card-sized, not $600+', $max < 250, $fail, "\${$max}");

// the same numbers must reach the browsers: product page + try-on-wall configs
function af_prv_get($url) {
    $args = array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify'));
    for ($i=0; $i<3; $i++) {
        $r = wp_remote_get($url, $args);
        if (is_wp_error($r)) { sleep(3); continue; }
        if (!in_array((int)wp_remote_retrieve_response_code($r), array(508,503,429), true)) return $r;
        sleep(4);
    }
    return $r;
}
$pid = 0;
foreach (wc_get_products(array('status'=>'publish','limit'=>8,'return'=>'ids','orderby'=>'date','order'=>'DESC')) as $cand) {
    $p = wc_get_product($cand);
    if ($p && $p->get_image_id() && af_pricing_applies($p)) { $pid = $cand; break; }
}
$pages = array('try-on-wall' => home_url('/try-on-wall/?nocache=' . time()));
if ($pid) $pages['product page'] = get_permalink($pid);
foreach ($pages as $where => $url) {
    $r = af_prv_get($url);
    if (is_wp_error($r)) { af_prv("{$where} fetch", false, $fail, $r->get_error_message()); continue; }
    $body = wp_remote_retrieve_body($r);
    // the embedded config carries card dollars, not multipliers. Normalise the
    // two encodings it travels in: esc_attr'd quotes inside data-config, and
    // wp_json_encode writing × as \u00d7 — the first version of this probe
    // matched the literal × and failed against perfectly card-priced pages.
    $norm = str_replace(array('&quot;', '\\u00d7'), array('"', '×'), $body);
    $has60  = (bool) preg_match('/2×3 ft \(24×36 in\)[^,}]{0,20}?:60\b/u', $norm);
    $has150 = (bool) preg_match('/4×6 ft \(48×72 in\)[^,}]{0,20}?:150\b/u', $norm);
    af_prv("{$where} config carries card prices", $has60 && $has150, $fail,
           ($has60?'':'2×3:60 missing ') . ($has150?'':'4×6:150 missing'));
}

// ── listings must carry the same book ──
// a product titled 3×4 must LIST at $80, open its options on the 3×4 chip,
// and quick-add to the cart at $80 — one number, three surfaces
$sampled = 0; $wrong = array(); $backed = 0;
foreach (wc_get_products(array('status'=>'publish','limit'=>60,'return'=>'ids','orderby'=>'date','order'=>'DESC')) as $sid) {
    $sp = wc_get_product($sid);
    if (!$sp || !af_pricing_applies($sp) || $sp->is_type('variable')) continue;
    $slabel = af_size_label_for_product($sp);
    if ($slabel === '') continue;
    $sampled++;
    $cfg2 = af_pricing_config();
    $want = (float) $cfg2['sizes'][$slabel];
    if ((float) $sp->get_price() !== $want) $wrong[] = "#{$sid} {$slabel} lists \${$sp->get_price()} (card \${$want})";
    if (get_post_meta($sid, '_af_price_backup', true)) $backed++;
    if ($sampled >= 25) break;
}
af_prv('listings priced from the card (' . $sampled . ' sampled)', !$wrong, $fail,
       $wrong ? implode('; ', array_slice($wrong, 0, 3)) : 'all exact');
af_prv('old prices backed up for reversal', $sampled === 0 || $backed > 0, $fail, "{$backed} of {$sampled}");
if ($pid) {
    $pp = wc_get_product($pid);
    $plabel = af_size_label_for_product($pp);
    if ($plabel !== '') {
        $r2 = af_prv_get(get_permalink($pid));
        if (!is_wp_error($r2)) {
            $b2 = wp_remote_retrieve_body($r2);
            af_prv('options panel opens on the titled size',
                   strpos($b2, 'af-chip-opt active" data-type="size" data-val="' . $plabel . '"') !== false, $fail, $plabel);
        }
    }
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
