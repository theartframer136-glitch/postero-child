<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. Does checkout add sales tax? The tax rates WooCommerce holds, and the settings around them. */
if (!defined('ABSPATH')) exit;
global $wpdb;
echo 'calc_taxes=' . get_option('woocommerce_calc_taxes') . ' prices_include_tax=' . get_option('woocommerce_prices_include_tax') . ' tax_display_shop=' . get_option('woocommerce_tax_display_shop') . ' tax_display_cart=' . get_option('woocommerce_tax_display_cart') . ' tax_based_on=' . get_option('woocommerce_tax_based_on') . "\n";
$rows = $wpdb->get_results("SELECT tax_rate_country c, tax_rate_state s, tax_rate r, tax_rate_name n, tax_rate_class k, tax_rate_shipping sh FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_order LIMIT 80");
echo 'tax rates: ' . count($rows) . "\n";
foreach ($rows as $r) echo "  {$r->c} {$r->s} {$r->r}% '{$r->n}' class='{$r->k}' shipping={$r->sh}\n";
foreach (array('US:DE' => array('US', 'DE', '19707'), 'US:PA' => array('US', 'PA', '19103'), 'US:NY' => array('US', 'NY', '10001'), 'US:CA' => array('US', 'CA', '90001')) as $k => $a) {
    $rates = WC_Tax::find_rates(array('country' => $a[0], 'state' => $a[1], 'postcode' => $a[2], 'city' => '', 'tax_class' => ''));
    $tot = 0; foreach ($rates as $x) $tot += (float) $x['rate'];
    echo "  rate applied for $k: {$tot}%\n";
}
