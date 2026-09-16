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

// The brochure, page 2. Feet.
$BROCHURE = array(
    'hanging ceiling banner'   => array(4, 4),
    'framed display banner'    => array(3, 4),
    'fabric event tapestry'    => array(4, 5),
    'step & repeat media wall' => array(8, 8),
    'step and repeat'          => array(8, 8),
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
        $src = 'title';
        $sqft = af_banner_sqft($label);
        if (!$sqft) { $sqft = af_banner_sqft(af_banner_size_text($prod)); $src = 'size attribute'; }
        if (!$sqft) {
            foreach ($BROCHURE as $key => $wh) {
                if (strpos($lname, $key) !== false) { $sqft = $wh[0] * $wh[1]; $src = "brochure ({$wh[0]}×{$wh[1]} ft)"; break; }
            }
        }
        $current = $prod->get_price();
        if (!$isBanner) {
            printf("  SKIP  #%d  %s\n        not a banner — left at \$%s\n", $prod->get_id(), mb_substr($label, 0, 70), $current);
            $skipped++; continue;
        }
        if (!$sqft) {
            printf("  ????  #%d  %s\n        no size found in title, attribute or brochure — left at \$%s\n", $prod->get_id(), mb_substr($label, 0, 70), $current);
            $skipped++; continue;
        }
        $price = round($sqft * $RATE, 2);
        printf("  %s  #%d  %s\n        %s sq ft (%s)  →  \$%.2f   (was \$%s)\n",
            $dry ? 'WOULD' : 'SET  ', $prod->get_id(), mb_substr($label, 0, 70), $sqft, $src, $price, $current);
        if (!$dry) {
            $prod->set_regular_price($price);
            $prod->set_sale_price('');          // the rate IS the price; no strike-through
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
