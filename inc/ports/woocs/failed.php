<?php
/*
 * The theme copy of the currency switcher stopped with an error while it
 * started (inc/ports/woocs.php caught it in $af_woocs_error, after taking
 * $af_woocs_hooks, the hooks as they were before it ran). With the plugin an
 * error there was a fatal on every page; here the shop keeps running, in US
 * dollars only:
 *  - AF_WOOCS_PORT_FAILED makes af_active_currency() answer USD, so the
 *    theme's woocommerce_currency filters, cookies and switcher links all say
 *    USD and WooCommerce prints and charges the stored US prices;
 *  - every callback and shortcode the copy added before it stopped is taken
 *    out again, so no half-built WOOCS object converts anything;
 *  - global $WOOCS becomes a stand-in offering USD only. The parent theme
 *    builds its header switcher whenever class WOOCS_STARTER exists (it does
 *    once boot.php is loaded) and reads $WOOCS->get_currencies(),
 *    ->current_currency and ->shop_is_cached; without it every page would
 *    stop in the header.
 * The error goes to the PHP error log.
 */
defined('ABSPATH') || exit;

define('AF_WOOCS_PORT_FAILED', true);
error_log(sprintf('inc/ports/woocs.php: the currency switcher copy failed to start, the shop runs in USD only: %s in %s:%d',
    $af_woocs_error->getMessage(), $af_woocs_error->getFile(), $af_woocs_error->getLine()));

foreach ($GLOBALS['wp_filter'] as $af_tag => $af_hook) {
    if (!($af_hook instanceof WP_Hook)) continue;
    foreach ($af_hook->callbacks as $af_prio => $af_cbs) {
        foreach ($af_cbs as $af_key => $af_cb) {
            if (!isset($af_woocs_hooks[$af_tag][$af_prio]) || !in_array($af_key, $af_woocs_hooks[$af_tag][$af_prio], true)) {
                remove_filter($af_tag, $af_cb['function'], $af_prio);
            }
        }
    }
}
foreach ($GLOBALS['shortcode_tags'] as $af_tag => $af_cb) {
    if (is_array($af_cb) && isset($af_cb[0]) && $af_cb[0] instanceof WOOCS) remove_shortcode($af_tag);
}
unset($af_tag, $af_hook, $af_prio, $af_cbs, $af_key, $af_cb);

$GLOBALS['WOOCS'] = new class {
    public $default_currency = 'USD';
    public $current_currency = 'USD';
    public $shop_is_cached   = 0;

    public function get_currencies() {
        $all = get_option('woocs', array());
        $usd = (is_array($all) && isset($all['USD']) && is_array($all['USD'])) ? $all['USD'] : array();
        return array('USD' => array_merge(array('name' => 'USD', 'rate' => 1, 'symbol' => '&#36;', 'position' => 'right', 'is_etalon' => 1, 'hide_cents' => 0, 'hide_on_front' => 0, 'rate_plus' => '', 'decimals' => 2, 'separators' => '0', 'description' => '', 'flag' => ''), $usd));
    }
};
