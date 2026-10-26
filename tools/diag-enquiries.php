<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only: what came in through the website in the last 30 days.
 * Contact messages, orders, customers, newsletter, returns, abandoned carts,
 * activity log, reviews. Personal data is masked (first name + initial,
 * em***@domain, phone last 4) because the Actions log is readable.
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
$since = gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
$adsSince = '2026-10-23 12:00:00';
$mask_name = function ($n) { $n = trim((string) $n); if ($n === '') return '-'; $p = preg_split('/\s+/', $n); $o = $p[0]; if (count($p) > 1) $o .= ' ' . mb_substr(end($p), 0, 1) . '.'; return $o; };
$mask_text = function ($t) {
    $t = preg_replace('/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[a-z]{2,})/i', '**@$1', (string) $t);
    $t = preg_replace_callback('/\+?\d[\d\s().-]{7,}\d/', function ($m) { $d = preg_replace('/\D/', '', $m[0]); return '***' . substr($d, -4); }, $t);
    return preg_replace('/\s+/', ' ', trim($t));
};
$tbl_exists = function ($t) use ($wpdb) { return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)); };
$cols = function ($t) use ($wpdb) { return array_map(function ($r) { return $r->Field; }, $wpdb->get_results("SHOW COLUMNS FROM `$t`")); };

echo "=== 1. CONTACT FORM MESSAGES (table af_contact_messages)\n";
$t = $wpdb->prefix . 'af_contact_messages';
if (!$tbl_exists($t)) echo "  table missing\n";
else {
    $c = $cols($t);
    $hasParent = in_array('parent_id', $c, true);
    $where = $hasParent ? 'AND (parent_id IS NULL OR parent_id = 0)' : '';
    echo "  total ever: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE 1 $where") . " | last 30 days: " . (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE created_at >= %s $where", $since)) . " | since the ad started (23 Oct): " . (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE created_at >= %s $where", $adsSince)) . "\n";
    foreach ($wpdb->get_results($wpdb->prepare("SELECT subject, status, COUNT(*) n FROM `$t` WHERE created_at >= %s $where GROUP BY subject, status ORDER BY n DESC", $since)) as $r) printf("  %-40s %-8s %d\n", $r->subject ?: '(no subject)', $r->status, $r->n);
    echo "  --- messages, newest first (last 30 days, max 40) ---\n";
    foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM `$t` WHERE created_at >= %s $where ORDER BY created_at DESC LIMIT 40", $since)) as $r) {
        $em = preg_replace('/^(.{0,2}).*@/', '$1**@', (string) $r->email);
        $ph = $r->phone ? '***' . substr(preg_replace('/\D/', '', $r->phone), -4) : '-';
        printf("  %s | %-28s | %s | %s | %s | %s\n    %s\n", $r->created_at, $r->subject ?: '(no subject)', $mask_name($r->name), $em, $ph, $r->status, mb_substr($mask_text($r->message), 0, 220));
    }
    if ($hasParent) echo "  replies sent from the account area (parent_id > 0), last 30 days: " . (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE created_at >= %s AND parent_id > 0", $since)) . "\n";
}

echo "\n=== 2. ORDERS (WooCommerce, last 30 days)\n";
if (function_exists('wc_get_orders')) {
    $orders = wc_get_orders(array('limit' => 200, 'date_created' => '>=' . (time() - 30 * DAY_IN_SECONDS), 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order'));
    $byStatus = array(); $sum = array(); $sinceAds = 0;
    foreach ($orders as $o) { $s = $o->get_status(); $byStatus[$s] = ($byStatus[$s] ?? 0) + 1; $cur = $o->get_currency(); $sum[$cur] = ($sum[$cur] ?? 0) + (float) $o->get_total(); if ($o->get_date_created() && $o->get_date_created()->getTimestamp() >= strtotime($adsSince . ' UTC')) $sinceAds++; }
    echo "  orders: " . count($orders) . " | since 23 Oct: $sinceAds | by status: " . json_encode($byStatus) . " | totals: " . json_encode(array_map(function ($v) { return round($v, 2); }, $sum)) . "\n";
    foreach (array_slice($orders, 0, 40) as $o) {
        $items = array(); foreach ($o->get_items() as $it) $items[] = mb_substr($it->get_name(), 0, 40) . ' x' . $it->get_quantity();
        printf("  #%s | %s | %-10s | %s %s | %s, %s | %s | %s\n", $o->get_order_number(), $o->get_date_created() ? $o->get_date_created()->date('Y-m-d H:i') : '-', $o->get_status(), $o->get_currency(), $o->get_total(), $o->get_shipping_city() ?: $o->get_billing_city(), $o->get_shipping_country() ?: $o->get_billing_country(), $o->get_payment_method_title(), implode('; ', array_slice($items, 0, 3)));
    }
    echo "  customer notes on these orders: " . count(array_filter($orders, function ($o) { return trim((string) $o->get_customer_note()) !== ''; })) . "\n";
} else echo "  WooCommerce not loaded\n";

echo "\n=== 3. CUSTOMERS, NEWSLETTER, RETURNS, CARTS, REVIEWS\n";
echo "  new customer accounts last 30 days: " . count(get_users(array('role' => 'customer', 'date_query' => array(array('after' => '30 days ago')), 'fields' => 'ID'))) . " | since 23 Oct: " . count(get_users(array('role' => 'customer', 'date_query' => array(array('after' => $adsSince)), 'fields' => 'ID'))) . "\n";
$subs = get_option('af_newsletter_subscribers', array()); $recent = 0;
if (is_array($subs)) foreach ($subs as $k => $v) { $d = is_array($v) ? ($v['date'] ?? $v['time'] ?? $v['created'] ?? null) : null; if ($d && strtotime(is_numeric($d) ? '@' . $d : $d) >= time() - 30 * DAY_IN_SECONDS) $recent++; }
echo "  newsletter subscribers: total " . (is_array($subs) ? count($subs) : 0) . " | with a date in the last 30 days: $recent\n";
foreach (array('af_rma_requests' => 'return requests', 'af_abandoned_carts' => 'abandoned carts', 'af_bot_log' => 'bot log rows', 'af_activity_log' => 'activity log rows') as $short => $label) {
    $t = $wpdb->prefix . $short;
    if (!$tbl_exists($t)) { echo "  $label: table missing\n"; continue; }
    $c = $cols($t); $dc = null; foreach (array('created_at', 'created', 'time', 'logged_at', 'updated_at', 'ts') as $cand) if (in_array($cand, $c, true)) { $dc = $cand; break; }
    $n30 = $dc ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$t` WHERE `$dc` >= %s", $since)) : '?';
    echo "  $label: total " . (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t`") . " | last 30 days: $n30" . ($dc ? '' : ' (no date column: ' . implode(',', $c) . ')') . "\n";
    if ($short === 'af_activity_log' && $dc) { $ec = null; foreach (array('event', 'action', 'type', 'event_type') as $cand) if (in_array($cand, $c, true)) { $ec = $cand; break; }
        if ($ec) foreach ($wpdb->get_results($wpdb->prepare("SELECT `$ec` e, COUNT(*) n FROM `$t` WHERE `$dc` >= %s GROUP BY `$ec` ORDER BY n DESC LIMIT 15", $since)) as $r) printf("    %-40s %d\n", $r->e, $r->n); }
    if ($short === 'af_rma_requests' && $dc) foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM `$t` WHERE `$dc` >= %s ORDER BY `$dc` DESC LIMIT 10", $since)) as $r) { $r = (array) $r; printf("    %s | order #%s | %s | %s\n", $r[$dc], $r['order_id'] ?? '?', $r['status'] ?? '?', mb_substr($mask_text($r['reason'] ?? ($r['message'] ?? '')), 0, 120)); }
}
$rev = get_comments(array('type' => 'review', 'date_query' => array(array('after' => '30 days ago')), 'count' => true));
$cmt = get_comments(array('type' => 'comment', 'date_query' => array(array('after' => '30 days ago')), 'count' => true));
echo "  product reviews last 30 days: $rev | blog comments: $cmt (pending: " . get_comments(array('status' => 'hold', 'count' => true)) . ")\n";

echo "\n=== 4. OTHER af_ TABLES (row counts, for completeness)\n";
foreach ($wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}af\\_%'") as $t) echo "  " . str_replace($wpdb->prefix, '', $t) . ": " . (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t`") . "\n";
echo "\n=== DONE ===\n";
