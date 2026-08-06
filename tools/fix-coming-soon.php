<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The shop is hidden: anonymous visitors get a "Coming Soon" placeholder while
 * signed-in admins see the real site, so it is invisible from the inside. This
 * names whatever is doing it and switches it off, then proves the fix by
 * fetching the homepage the way a stranger would.
 *
 * Everything it changes is a single option flip (reversible, and printed).
 * Run: wp eval-file tools/fix-coming-soon.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

function af_cs_home() {
    $r = wp_remote_get(home_url('/?af_cs=' . time()), array(
        'timeout' => 45, 'sslverify' => false, 'redirection' => 3,
        'headers' => array('User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36'),
    ));
    if (is_wp_error($r)) return array(0, '(fetch failed: ' . $r->get_error_message() . ')', '');
    $body = wp_remote_retrieve_body($r);
    preg_match('~<title[^>]*>(.*?)</title>~is', $body, $m);
    return array((int) wp_remote_retrieve_response_code($r), trim($m[1] ?? '(no title)'), $body);
}

echo "=== BEFORE ===\n";
list($code, $title, $body) = af_cs_home();
printf("  home HTTP %d  title=\"%s\"\n", $code, $title);
$was_hidden = (stripos($title, 'coming soon') !== false || stripos($title, 'under construction') !== false
               || stripos($title, 'maintenance') !== false);
echo '  verdict: ' . ($was_hidden ? "HIDDEN from customers\n" : "already public\n");

// ── 1. who ships the placeholder ─────────────────────────────────────
echo "\n=== SUSPECTS ===\n";
// the placeholder's own asset URLs name the plugin serving it
if ($was_hidden && $body) {
    preg_match_all('~/wp-content/(?:plugins|mu-plugins)/([a-z0-9\-_]+)/~i', $body, $mm);
    $srcs = array_unique($mm[1] ?? array());
    echo '  assets come from: ' . ($srcs ? implode(', ', $srcs) : '(none — inline only)') . "\n";
}
$active = (array) get_option('active_plugins', array());
$mu     = function_exists('get_mu_plugins') ? array_keys(get_mu_plugins()) : array();
foreach (array_merge($active, $mu) as $slug) {
    if (preg_match('~coming|maintenance|construction|seedprod|launch|hostinger~i', $slug)) {
        echo "  plugin: {$slug}\n";
    }
}

// ── 2. every option that could be holding the switch ─────────────────
echo "\n=== SWITCHES ===\n";
$rows = $wpdb->get_results(
    "SELECT option_name, option_value FROM {$wpdb->options}
      WHERE option_name LIKE '%coming%soon%'
         OR option_name LIKE '%coming_soon%'
         OR option_name LIKE '%maintenance%'
         OR option_name LIKE '%under_construction%'
         OR option_name LIKE 'hostinger%'
      ORDER BY option_name"
);
$flipped = array();
foreach ($rows as $r) {
    $val = $r->option_value;
    $short = strlen($val) > 90 ? substr($val, 0, 90) . '…' : $val;
    // a switch is "on" when it is a plain truthy scalar — never touch arrays,
    // JSON blobs or ids, which is where these plugins keep unrelated settings
    $on = in_array(strtolower(trim($val)), array('1', 'yes', 'true', 'on', 'enabled'), true);
    printf("  %-46s %s %s\n", $r->option_name, str_pad($short, 12), $on ? '<< ON' : '');
    if (!$on || !$was_hidden) continue;
    if (!preg_match('~coming|maintenance|construction~i', $r->option_name)) continue;
    // WooCommerce's own switch takes 'no', the plugin switches take '0'
    $off = (strtolower(trim($val)) === 'yes') ? 'no' : '0';
    update_option($r->option_name, $off);
    $flipped[] = $r->option_name . ": {$val} => {$off}";
}

// Serialized settings blobs. This is where the real switch lives: Hostinger's
// own plugin keeps hostinger_tools['maintenance_mode'] as a PHP boolean, so
// neither the scalar test above nor a string comparison can see it.
foreach ($rows as $r) {
    if (!$was_hidden) break;
    $val = maybe_unserialize($r->option_value);
    if (!is_array($val)) continue;
    $changed = false;
    foreach ($val as $k => $v) {
        if (!preg_match('~^(status|enabled|active|is_active|coming_soon|maintenance_mode|maintenance|under_construction)$~i', (string) $k)) continue;
        $on = ($v === true) || in_array(strtolower(trim((string) $v)), array('1', 'yes', 'true', 'on'), true);
        if (!$on) continue;
        $was = is_bool($v) ? 'true' : (string) $v;
        // give it back the same type it had, or the plugin may choke on it
        $val[$k] = is_bool($v) ? false : (is_int($v) ? 0 : '0');
        $changed = true;
        $flipped[] = $r->option_name . "[{$k}]: {$was} => off";
    }
    if ($changed) {
        update_option($r->option_name, $val);
        // the plugin caches its own settings in an autoloaded copy
        wp_cache_delete($r->option_name, 'options');
    }
}

// WooCommerce's own store-wide switch, belt and braces
if (get_option('woocommerce_coming_soon') === 'yes') {
    update_option('woocommerce_coming_soon', 'no');
    $flipped[] = 'woocommerce_coming_soon: yes => no';
}
// a stale .maintenance file keeps WordPress itself in maintenance mode
$mfile = ABSPATH . '.maintenance';
if (file_exists($mfile)) { @unlink($mfile); $flipped[] = '.maintenance file deleted'; }

echo "\n=== CHANGES ===\n";
if ($flipped) { foreach ($flipped as $f) echo "  {$f}\n"; }
else echo ($was_hidden ? "  none — the switch is not an option in this database  <<<\n" : "  none needed\n");

// ── 3. prove it ──────────────────────────────────────────────────────
if ($flipped) {
    if (function_exists('wp_cache_flush')) wp_cache_flush();
    sleep(2);
    echo "\n=== AFTER ===\n";
    list($code2, $title2, $body2) = af_cs_home();
    printf("  home HTTP %d  title=\"%s\"\n", $code2, $title2);
    $still = (stripos($title2, 'coming soon') !== false || stripos($title2, 'under construction') !== false);
    echo '  verdict: ' . ($still ? "STILL HIDDEN  <<<\n" : "PUBLIC — the shop is visible again\n");
    if ($still) {
        // show what the switch looks like now, so the next run is not blind
        foreach (array('hostinger_tools') as $o) {
            $v = get_option($o);
            if (is_array($v)) echo "    {$o}: " . str_replace("\n", ' ', var_export($v, true)) . "\n";
        }
    }
    if ($still && $body2) {
        // hand the next run something to work with
        preg_match_all('~<(?:script|link)[^>]+(?:src|href)="([^"]+)"~i', $body2, $am);
        foreach (array_slice(array_unique($am[1] ?? array()), 0, 8) as $a) echo "    asset: {$a}\n";
    }
}
echo "\n=== DONE ===\n";
