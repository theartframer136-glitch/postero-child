<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove the §15 marketplace feeds are real: fetch each one over HTTP, parse it,
 * and check the items carry what Google/Meta/Pinterest actually require.
 * Read-only.
 * Run: wp eval-file tools/verify-marketplace.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;

function af_mkv($label, $ok, &$fail, $extra = '') {
    printf("  %-40s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}
function af_mkv_get($url) {
    $a = array('timeout'=>90,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify'));
    for ($i=0; $i<3; $i++) {
        $r = wp_remote_get($url, $a);
        if (is_wp_error($r)) { sleep(3); continue; }
        $c = (int) wp_remote_retrieve_response_code($r);
        if (!in_array($c, array(508,503,429), true)) return $r;
        sleep(4);
    }
    return $r;
}

echo "=== MARKETPLACE (§15) ===\n";
af_mkv('module loaded', function_exists('af_mk_channels'), $fail);
if (!function_exists('af_mk_channels')) { echo "\n=== RESULT: 1 PROBLEM(S) ===\n"; return; }

$key = af_mk_key();
af_mkv('feed key present', strlen($key) >= 16, $fail);
af_mkv('sync log table', (bool) $GLOBALS['wpdb']->get_var("SHOW TABLES LIKE '" . af_mk_table() . "'"), $fail);
af_mkv('inventory REST route', (bool) rest_get_server()->get_routes('af-marketplace/v1'), $fail);
af_mkv('outbound stock hook', (bool) has_action('woocommerce_product_set_stock'), $fail);
af_mkv('per-product settings saved', (bool) has_action('save_post_product'), $fail);

$first = true;
foreach (af_mk_channels() as $ch => $meta) {
    // six full-catalogue feeds back to back is what tripped the host limit
    if (!$first) sleep(3);
    $first = false;
    echo "\n--- {$meta['label']} ({$ch}) ---\n";
    $count = count(af_mk_products($ch));
    echo "  products included: {$count}\n";

    $r = af_mkv_get(af_mk_feed_url($ch));
    if (is_wp_error($r)) { af_mkv('feed fetch', false, $fail, $r->get_error_message()); continue; }
    $code = (int) wp_remote_retrieve_response_code($r);
    $body = wp_remote_retrieve_body($r);
    af_mkv('feed responds 200', $code === 200, $fail, "HTTP {$code}, " . number_format(strlen($body)/1024, 0) . " KB");
    if ($code !== 200) continue;

    if ($meta['kind'] === 'feed') {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml  = @simplexml_load_string($body);
        $errs = libxml_get_errors();
        af_mkv('valid XML', $xml !== false, $fail);
        if ($xml === false) {
            foreach (array_slice($errs, 0, 3) as $e) {
                printf("      line %d col %d: %s\n", $e->line, $e->column, trim($e->message));
            }
            printf("      first 220 bytes: %s\n", str_replace("\n", '\n', substr($body, 0, 220)));
            printf("      last 120 bytes:  %s\n", str_replace("\n", '\n', substr($body, -120)));
            continue;
        }
        $items = $xml->channel->item;
        $n = $items ? count($items) : 0;
        af_mkv('items present', $n > 0, $fail, "{$n} items");
        if ($n > 0) {
            $g = $items[0]->children('http://base.google.com/ns/1.0');
            foreach (array('id','title','link','image_link','availability','price','brand') as $req) {
                af_mkv("item has g:{$req}", isset($g->$req) && (string) $g->$req !== '', $fail,
                       $req === 'price' ? (string) $g->price : '');
            }
            // Google rejects items with no identifier unless they are told there is none
            $has_ident = (isset($g->gtin) && (string) $g->gtin !== '') || (isset($g->identifier_exists) && (string) $g->identifier_exists === 'no');
            af_mkv('identifier declared', $has_ident, $fail);
            // Meta words availability differently
            $av = (string) $g->availability;
            $want = ($ch === 'meta') ? array('in stock','out of stock') : array('in_stock','out_of_stock');
            af_mkv('availability wording', in_array($av, $want, true), $fail, $av);
        }
    } else {
        $lines = array_filter(explode("\n", trim($body)));
        af_mkv('CSV has header + rows', count($lines) >= 2, $fail, (count($lines)-1) . ' rows');
        $head = str_getcsv($lines[0]);
        af_mkv('CSV columns match channel', $head === af_mk_csv_columns($ch), $fail, count($head) . ' columns');
    }
}

// a wrong key must be refused
$bad = af_mkv_get(add_query_arg(array('af_feed'=>'google','key'=>'not-the-key'), home_url('/')));
if (!is_wp_error($bad)) {
    af_mkv('wrong feed key refused', (int) wp_remote_retrieve_response_code($bad) === 403, $fail,
           'HTTP ' . wp_remote_retrieve_response_code($bad));
}
// unauthenticated inventory push must be refused
$push = wp_remote_post(rest_url('af-marketplace/v1/inventory'), array(
    'timeout'=>45,'sslverify'=>false,
    'headers'=>array('Content-Type'=>'application/json'),
    'body'=>wp_json_encode(array('channel'=>'probe','updates'=>array(array('sku'=>'nope','stock'=>1)))),
));
if (!is_wp_error($push)) {
    $pc = (int) wp_remote_retrieve_response_code($push);
    af_mkv('inventory push needs the key', $pc === 401 || $pc === 403, $fail, "HTTP {$pc}");
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
