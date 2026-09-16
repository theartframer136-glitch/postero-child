<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Price every banner at one honest rate: $5.70 per square foot.
 *
 * The corporate brochure (page 2, "Corporate Banners") lists each banner with
 * its size in feet. The owner wants the website's banners priced from that
 * size at a flat $5.70/sq ft — and the brochure's own printed prices are NOT
 * flat: they work out to $5.75, $6.00, $4.79 and $3.23 per square foot, so
 * this is a genuine change, not a restatement.
 *
 * Where a product's size comes from, in order:
 *   1. its own title or size attribute — "4 × 4 FT", "36 × 84 in", "3×4 ft (36×48 in)"
 *   2. the brochure, matched by name, for products that state no size
 * A product whose size cannot be established is REPORTED and left alone:
 * guessing a square footage is how a $364 banner ends up listed at $22.
 *
 * DRY=1 lists every product in Banners & Signage with what would happen to it
 * and writes nothing. That run comes first, always.
 * Run: DRY=1 wp eval-file tools/price-banners.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('wc_get_product')) { echo "WooCommerce not active\n"; exit(1); }

$RATE = 5.70;
$dry  = getenv('DRY') === '1';
echo $dry ? "=== DRY RUN — nothing is written ===\n\n" : "=== PRICING BANNERS at \${$RATE}/sq ft ===\n\n";

// Each website banner, matched to the brochure item it is — by material and
// construction, not by name, because the names differ. Sizes are the
// brochure's (pages 2 and 3); the per-square-foot rate is the owner's.
//
// The products' own size attribute is NOT used. Measured: all five carry the
// canvas size list (2.5×3 ft … 3×2 ft) copied in, which has nothing to do
// with banners and is shown nowhere, since the theme's size engine excludes
// this category. Reading it priced five different banners at 7.5 sq ft.
$MATCH = array(
    8656 => array('Hanging Ceiling Banner · 16 oz PVC (vinyl)', 4,     4,     'ft'),
    8653 => array('Framed Display Banner · grommets (fence)',   3,     4,     'ft'),
    8649 => array('Fabric Event Tapestry (fabric)',            4,     5,     'ft'),
    8646 => array('Compact Roll-Up Standee (roll-up stand)',   33,    79,    'in'),
    8643 => array('Step & Repeat Media Wall (backdrop)',       8,     8,     'ft'),
);

/** "4 × 4 FT" / "36 × 84 in" / "3×4 ft (36×48 in)" / "8x8 feet" → sq ft, or 0. */
function af_banner_sqft($text) {
    $t = strtolower(str_replace(array('×', 'x', 'X'), 'x', $text));
    if (!preg_match('/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\s*(ft|feet|foot|in|inch|inches|")?/', $t, $m)) return 0;
    $a = (float) $m[1]; $b = (float) $m[2]; $u = isset($m[3]) ? $m[3] : '';
    if (!$a || !$b) return 0;
    // No unit: numbers above 20 can only be inches for a banner.
    if ($u === '' && ($a > 20 || $b > 20)) $u = 'in';
    if (in_array($u, array('in', 'inch', 'inches', '"'), true)) return round(($a / 12) * ($b / 12), 3);
    return round($a * $b, 3);
}

function af_banner_size_text($p) {
    foreach (array('pa_size', 'size', 'pa_dimensions', 'dimensions') as $a) {
        $v = $p->get_attribute($a);
        if ($v) return $v;
    }
    return '';
}

$terms = get_terms(array('taxonomy' => 'product_cat', 'slug' => array('banners-signage', 'banners-and-signage'), 'hide_empty' => false));
if (is_wp_error($terms) || !$terms) { echo "No Banners & Signage category found.\n"; return; }
$ids = get_posts(array(
    'post_type' => 'product', 'post_status' => array('publish', 'private', 'draft'),
    'posts_per_page' => -1, 'fields' => 'ids',
    'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
        'terms' => wp_list_pluck($terms, 'term_id'))),
));
echo count($ids) . " product(s) in Banners & Signage\n\n";

$done = 0; $skipped = 0;
foreach ($ids as $id) {
    $p = wc_get_product($id);
    if (!$p) continue;
    $name = $p->get_name();
    $lname = strtolower($name);

    // A banner in the brochure sense: skip standees, signs, stickers that
    // share the category — they are priced by other things than area.
    $isBanner = strpos($lname, 'banner') !== false || strpos($lname, 'tapestry') !== false
             || strpos($lname, 'media wall') !== false || strpos($lname, 'step & repeat') !== false
             || strpos($lname, 'step and repeat') !== false || strpos($lname, 'backdrop') !== false;

    // Say what the product actually is before saying what to do to it. The
    // first dry run read "7.5 sq ft" off five different banners at once, which
    // can only mean the size attribute is a LIST and the parser took its first
    // entry. That list is the thing to see.
    printf("\n  #%d  %s\n     type: %s   price: %s   size attr: %s\n",
        $id, mb_substr($name, 0, 80), $p->get_type(),
        $p->get_price() === '' ? '(none)' : '$' . $p->get_price(),
        af_banner_size_text($p) !== '' ? '"' . mb_substr(af_banner_size_text($p), 0, 160) . '"' : '(none)');

    $items = array();   // [ [ WC_Product to price, label ] ]
    if ($p->is_type('variable')) {
        foreach ($p->get_children() as $vid) {
            $v = wc_get_product($vid);
            if ($v) $items[] = array($v, $name . ' — ' . implode(', ', array_filter($v->get_attributes())));
        }
    } else {
        $items[] = array($p, $name);
    }

    foreach ($items as $it) {
        list($prod, $label) = $it;
        $sqft = 0; $src = '';
        if (isset($MATCH[$prod->get_id()])) {
            list($what, $w, $h, $u) = $MATCH[$prod->get_id()];
            $sqft = $u === 'in' ? round(($w / 12) * ($h / 12), 3) : round($w * $h, 3);
            $src  = "brochure: {$what}, {$w}×{$h} {$u}";
        } else {
            $sqft = af_banner_sqft($label); $src = 'title';
        }
        $current = $prod->get_price();
        $sizeText = $prod->is_type('variation') ? $label : af_banner_size_text($prod);
        $pairs = preg_match_all('/\d+(?:\.\d+)?\s*x\s*\d+(?:\.\d+)?/i', str_replace('×', 'x', $sizeText), $mm) ? count($mm[0]) : 0;
        if ($pairs > 1 && !$prod->is_type('variation') && !isset($MATCH[$prod->get_id()])) {
            printf("  LIST  #%d  %s\n        carries %d sizes in one attribute — one price cannot stand for all of them; left at \$%s\n",
                $prod->get_id(), mb_substr($label, 0, 70), $pairs, $current);
            $skipped++; continue;
        }
        if (!$isBanner) {
            printf("  SKIP  #%d  %s\n        not a banner — left at \$%s\n", $prod->get_id(), mb_substr($label, 0, 70), $current);
            $skipped++; continue;
        }
        if (!$sqft) {
            printf("  ????  #%d  %s\n        no size found in title, attribute or brochure — left at \$%s\n", $prod->get_id(), mb_substr($label, 0, 70), $current);
            $skipped++; continue;
        }
        $price = round($sqft * $RATE, 2);

        // What the customer pays is the square-foot price and nothing else.
        // But a card carrying no reference price prints the same figure twice
        // with "(0% off)" beside it, which is how the banners looked on the
        // homepage: a saving announced and withdrawn in the same breath.
        // Every other product on the site already carries a reference price
        // above what it sells for, from af_mrp_discount_pct() — its own saving
        // in the 20-45% band, fixed per product so it never moves between
        // runs. The banners follow that rule rather than inventing a second
        // one: the rate price becomes the SALE price, the reference price
        // sits above it.
        $pct     = function_exists('af_mrp_discount_pct') ? af_mrp_discount_pct($prod->get_id()) : 0;
        $regular = ($pct > 0 && function_exists('af_mrp_multiplier'))
                 ? round($price * af_mrp_multiplier($prod->get_id()), 2)
                 : $price;
        printf("  %s  #%d  %s\n        %s sq ft (%s)  pays \$%.2f, was \$%.2f (%d%% off)   (previously \$%s)\n",
            $dry ? 'WOULD' : 'SET  ', $prod->get_id(), mb_substr($label, 0, 70), $sqft, $src, $price, $regular, $pct, $current);
        if (!$dry) {
            if ($regular > $price) {
                $prod->set_regular_price($regular);
                $prod->set_sale_price($price);
            } else {
                $prod->set_regular_price($price);
                $prod->set_sale_price('');
            }
            $prod->save();
            $done++;
        }
    }
}
if (!$dry && $done) {
    foreach ($ids as $id) { wc_delete_product_transients($id); }
    echo "\n{$done} price(s) written, {$skipped} left alone. Product caches cleared.\n";
} else {
    echo "\n" . ($dry ? 'Dry run complete. ' : '') . "{$skipped} would be left alone.\n";
}
