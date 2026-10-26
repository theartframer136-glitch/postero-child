<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Before af_mk_google_withheld() (inc/marketplace.php) is live:
 * which products it would keep out of the Google feed, on the live catalogue.
 * The same rules, copied, under another name.
 */
if (!defined('ABSPATH')) exit;
function af_mk_google_withheld_preview($product) {
    $name = $product->get_name();
    foreach (array('batman', 'iron ?man') as $w) if (preg_match('~\b' . $w . '\b~i', $name)) return 'licensed character';
    if (preg_match('~\bgift\s*card~i', $name)) return 'gift card';
    if (af_pricing_applies($product)) {
        $cfg = af_pricing_config($product->get_id());
        $offer = array();
        foreach (af_sizes_available() as $label) if (isset($cfg['sizes'][$label])) $offer[] = round((float) $cfg['sizes'][$label], 2);
        if ($offer && !in_array(round((float) wc_get_price_to_display($product), 2), $offer, true)) return 'price not offered on the page';
    }
    return '';
}
$n = 0; $kept = 0;
foreach (af_mk_products('google') as $p) {
    $why = af_mk_google_withheld_preview($p);
    if ($why === '') { $kept++; continue; }
    $n++;
    echo "  #{$p->get_id()} [$why] " . wc_get_price_to_display($p) . '  ' . $p->get_name() . "\n";
}
echo "withheld: $n, still sent: $kept\n";
