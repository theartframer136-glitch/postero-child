<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Set every canvas product's LISTED price to the printed rate card's price
 * for the size in its own title, so the number on the card grid, the number
 * the options panel opens on, and the number the cart charges are the same
 * number. The engine already charges card prices (af_calc_price); this
 * brings the catalogue's display prices into the same book.
 *
 * - Size comes from the title ("3x4 Feet", "60 x 36 Inch", …) via
 *   af_size_label_for_product(); titles whose size matches no selector label
 *   are priced by interpolating the card's own $-per-area curve.
 * - The card price is what the product SELLS for, so it is written to the sale
 *   price, with the struck-through reference price (af_mrp_multiplier) above
 *   it. This script used to clear the sale price instead, which silently
 *   undid apply-mrp-markup.php later in the same deploy — every card went
 *   back to "$80.00  $80.00  (0% off)". Both scripts now write the same pair,
 *   so the order they run in does not matter.
 * - The previous regular/sale pair is saved once to _af_price_backup, so
 *   this is reversible.
 * - Idempotent: a second run changes nothing.
 * Run: wp eval-file tools/reprice-from-card.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

// the card's price-by-area curve (sq ft => USD), for titled sizes that match
// no selector label (e.g. 5×6): 1×2=40, 2×3=60, 5×2=75, 3×4=80, 5×3=100,
// 4×4=110, 3×6=120, 4×5/5×4=130, 4×6=150, 5×6=170
function af_rp_card_by_area($sqft) {
    $pts = array(2=>40, 6=>60, 10=>75, 12=>80, 15=>100, 16=>110, 18=>120, 20=>130, 24=>150, 30=>170);
    $keys = array_keys($pts);
    if ($sqft <= $keys[0]) return $pts[$keys[0]];
    if ($sqft >= end($keys)) return end($pts);
    for ($i = 1; $i < count($keys); $i++) {
        if ($sqft <= $keys[$i]) {
            $a = $keys[$i-1]; $b = $keys[$i];
            $v = $pts[$a] + ($pts[$b] - $pts[$a]) * ($sqft - $a) / ($b - $a);
            return round($v / 5) * 5;
        }
    }
    return end($pts);
}

$cfg   = af_pricing_config();
$page  = 1;
$done  = 0; $changed = 0; $nosize = array(); $interp = array(); $variable = 0;
echo "=== REPRICE FROM THE RATE CARD ===\n";
while (true) {
    $ids = wc_get_products(array('status'=>'publish','limit'=>60,'page'=>$page,'return'=>'ids','orderby'=>'ID','order'=>'ASC'));
    if (!$ids) break;
    foreach ($ids as $pid) {
        $p = wc_get_product($pid);
        if (!$p || !af_pricing_applies($p)) continue;
        $done++;
        if ($p->is_type('variable')) { $variable++; continue; }   // none expected; report if wrong

        $label = af_size_label_for_product($p);
        if ($label !== '' && isset($cfg['sizes'][$label])) {
            $card = (float) $cfg['sizes'][$label];
        } else {
            // titled with a size the selector doesn't offer, or no size at all
            $name = $p->get_name();
            if (preg_match('/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(ft|feet|foot|inches|inch|in)?\b/iu', $name, $m)) {
                $a=(float)$m[1]; $b=(float)$m[2];
                $u=strtolower(isset($m[3])?$m[3]:'');
                if ($u==='') $u = ($a>12||$b>12) ? 'in' : 'ft';
                if (strpos($u,'in')===0) { $a/=12; $b/=12; }
                $card = af_rp_card_by_area($a*$b);
                $interp[] = "#{$pid} {$name} => \${$card}";
            } else {
                $nosize[] = "#{$pid} {$name}";
                continue;
            }
        }

        // The card price is what the product SELLS for, so it goes in the sale
        // field, with the struck-through reference price above it. Writing the
        // pair here — rather than clearing the sale price, which is what this
        // script used to do — is what stops it undoing apply-mrp-markup.php
        // later in the same deploy. Both scripts now aim at the same two
        // numbers, so the order they run in no longer matters.
        $want_reg  = function_exists('af_mrp_multiplier')
            ? round($card * af_mrp_multiplier($pid), 2) : $card;
        $want_sale = ($want_reg > $card) ? $card : '';

        $cur_reg  = $p->get_regular_price();
        $cur_sale = $p->get_sale_price();
        if ((float)$cur_reg === (float)$want_reg
            && (float)$cur_sale === (float)$want_sale) continue;   // already card-priced

        if (!get_post_meta($pid, '_af_price_backup', true)) {
            update_post_meta($pid, '_af_price_backup', wp_json_encode(array(
                'regular' => $cur_reg, 'sale' => $cur_sale, 'when' => gmdate('c'),
            )));
        }
        $p->set_regular_price($want_reg);
        $p->set_sale_price($want_sale === '' ? '' : (string) $want_sale);
        $p->save();
        $changed++;
    }
    $page++;
}
echo "  canvas products seen:     {$done}\n";
echo "  repriced this run:        {$changed}\n";
echo "  variable (skipped):       {$variable}\n";
echo "  interpolated (non-selector sizes): " . count($interp) . "\n";
foreach (array_slice($interp, 0, 8) as $l) echo "    {$l}\n";
echo "  no size in title (untouched): " . count($nosize) . "\n";
foreach (array_slice($nosize, 0, 8) as $l) echo "    {$l}\n";

if ($changed > 0) {
    if (function_exists('af_mk_flush_feeds')) { af_mk_flush_feeds(); echo "  marketplace feeds flushed\n"; }
    delete_transient('af_bot_from_price');
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    echo "  price transients cleared\n";
}
echo "=== DONE ===\n";
