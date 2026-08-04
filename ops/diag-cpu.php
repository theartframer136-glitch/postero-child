<?php
/**
 * Read-only WordPress-side CPU diagnostic, run via `wp eval-file` by
 * .github/workflows/diagnose-cpu.yml. Prints scheduler/queue/config state
 * that the shell-side log analysis cannot see. Changes NOTHING.
 */
if (!defined('ABSPATH')) { exit; }
global $wpdb;

echo "=== ACTIVE PLUGINS ===\n";
foreach ((array) get_option('active_plugins', array()) as $p) { echo "  $p\n"; }

echo "\n=== WP-CRON EVENTS (next 40) ===\n";
$crons = _get_cron_array();
$now = time();
$rows = array();
foreach ((array) $crons as $ts => $hooks) {
    foreach ((array) $hooks as $hook => $events) {
        foreach ((array) $events as $ev) {
            $rows[] = array($ts, $hook, isset($ev['schedule']) ? $ev['schedule'] : 'once');
        }
    }
}
usort($rows, function ($a, $b) { return $a[0] - $b[0]; });
foreach (array_slice($rows, 0, 40) as $r) {
    printf("  %+6d min  %-55s %s\n", (int) round(($r[0] - $now) / 60), substr($r[1], 0, 55), $r[2]);
}
echo "  total scheduled events: " . count($rows) . "\n";

echo "\n=== ACTION SCHEDULER QUEUE ===\n";
$t = $wpdb->prefix . 'actionscheduler_actions';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t) {
    $res = $wpdb->get_results("SELECT hook, status, COUNT(*) c FROM `$t` GROUP BY hook, status ORDER BY c DESC LIMIT 30");
    foreach ((array) $res as $r) { printf("  %-62s %-10s %d\n", substr($r->hook, 0, 62), $r->status, $r->c); }
    $past = $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE status='pending' AND scheduled_date_gmt < UTC_TIMESTAMP()");
    echo "  PAST-DUE pending actions: " . (int) $past . "\n";
    $running = $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE status='in-progress'");
    echo "  in-progress right now: " . (int) $running . "\n";
    $last24 = $wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE last_attempt_gmt > UTC_TIMESTAMP() - INTERVAL 1 DAY");
    echo "  actions attempted in last 24h: " . (int) $last24 . "\n";
    $recent = $wpdb->get_results("SELECT hook, COUNT(*) c FROM `$t` WHERE last_attempt_gmt > UTC_TIMESTAMP() - INTERVAL 1 DAY GROUP BY hook ORDER BY c DESC LIMIT 15");
    echo "  -- attempts last 24h by hook --\n";
    foreach ((array) $recent as $r) { printf("  %-62s %d\n", substr($r->hook, 0, 62), $r->c); }
} else {
    echo "  (no actionscheduler_actions table)\n";
}

echo "\n=== LITESPEED CACHE CONFIG (hot keys) ===\n";
foreach (array('cache', 'crawler', 'cache-ttl_pub', 'cache-priv', 'esi', 'optm-css_min', 'optm-js_min', 'optm-ccss_gen', 'optm-ucss', 'media-webp', 'img_optm-auto') as $k) {
    $v = get_option('litespeed.conf.' . $k, '(unset)');
    printf("  %-18s %s\n", $k, is_scalar($v) ? var_export($v, true) : json_encode($v));
}
echo "  crawler cron event: " . (wp_next_scheduled('litespeed_crawler_trigger') ? date('c', wp_next_scheduled('litespeed_crawler_trigger')) : 'none') . "\n";

echo "\n=== IMAGE OPT QUEUE (LiteSpeed) ===\n";
$img = $wpdb->prefix . 'litespeed_img_optming';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $img)) === $img) {
    $res = $wpdb->get_results("SELECT optm_status, COUNT(*) c FROM `$img` GROUP BY optm_status");
    foreach ((array) $res as $r) { echo "  status {$r->optm_status}: {$r->c}\n"; }
} else {
    echo "  (no litespeed image queue table)\n";
}

echo "\n=== AUTOLOAD / DB WEIGHT ===\n";
echo "  autoloaded options: " . round((int) $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'") / 1024) . " KB in "
    . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload='yes'") . " rows\n";
$big = $wpdb->get_results("SELECT option_name, LENGTH(option_value) l FROM {$wpdb->options} WHERE autoload='yes' ORDER BY l DESC LIMIT 8");
foreach ((array) $big as $r) { printf("  %-55s %d KB\n", substr($r->option_name, 0, 55), round($r->l / 1024)); }
echo "  transient rows: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%'") . "\n";
echo "  expired transients: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_%' AND option_value < UNIX_TIMESTAMP()") . "\n";

echo "\n=== CATALOG SIZE ===\n";
foreach (array('product' => 'products', 'post' => 'posts', 'page' => 'pages', 'attachment' => 'attachments', 'af_reel' => 'reels') as $pt => $label) {
    $c = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) c FROM {$wpdb->posts} WHERE post_type=%s", $pt));
    echo "  $label: " . (int) $c->c . "\n";
}
echo "  postmeta rows: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}") . "\n";
echo "  options rows: " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}") . "\n";
echo "  sessions (wc): " . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_sessions") . "\n";

echo "\n=== MYSQL PROCESSLIST (long-running) ===\n";
$pl = $wpdb->get_results('SHOW FULL PROCESSLIST');
$n = 0;
foreach ((array) $pl as $p) {
    if ($n++ >= 12) break;
    printf("  %ss %-8s %s\n", $p->Time, $p->Command, substr((string) $p->Info, 0, 110));
}

echo "\n=== THEME GUARD/WM FLAGS ===\n";
foreach (array('af_wm_enabled', 'af_guard_search_stripped') as $k) {
    echo "  $k = " . var_export(get_option($k, '(unset)'), true) . "\n";
}

echo "\nAF-DIAG-PHP-DONE\n";
