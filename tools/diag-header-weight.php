<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. What the site header's ~210 KB of HTML is made of: each Elementor
 * widget in it with its size, how often each menu appears, and the biggest
 * repeated blocks. Fetches /cart/ as a guest (page cache bypassed).
 */
if (!defined('ABSPATH')) exit;
$r = wp_remote_get(home_url('/cart/?afhw=' . mt_rand()), array('timeout' => 40, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 AF-header-weight'));
$html = is_wp_error($r) ? '' : wp_remote_retrieve_body($r);
if (!preg_match('~<header id="masthead".*?</header>~s', $html, $m)) { echo "no header found\n"; return; }
$h = $m[0];
printf("page %d KB, header %d KB\n\n", strlen($html) / 1024, strlen($h) / 1024);
$dom = new DOMDocument(); libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8"?>' . $h); libxml_clear_errors();
$xp = new DOMXPath($dom);
$rows = array();
foreach ($xp->query('//*[@data-widget_type]') as $n) {
    $len = strlen($dom->saveHTML($n));
    // hidden at which widths (Elementor's classes on the widget or a parent)
    $hid = array(); for ($p = $n; $p && $p instanceof DOMElement; $p = $p->parentNode) { if (preg_match_all('~elementor-hidden-([a-z_]+)~', $p->getAttribute('class'), $mm)) $hid = array_merge($hid, $mm[1]); }
    $rows[] = array($len, $n->getAttribute('data-widget_type'), $n->getAttribute('data-id'), implode(',', array_unique($hid)));
}
usort($rows, function ($a, $b) { return $b[0] <=> $a[0]; });
echo "=== widgets, biggest first (KB, type, id, hidden on)\n";
foreach (array_slice($rows, 0, 25) as $w) printf("  %6.1f KB  %-34s %-9s %s\n", $w[0] / 1024, $w[1], $w[2], $w[3] ?: '-');
echo "\n=== repeated things\n";
foreach (array('menu-item-' => '~id="menu-item-\d+"~', 'category options' => '~<option class="level-~', 'cat-item li' => '~class="cat-item ~', 'svg' => '~<svg~', 'inline style blocks' => '~<style~', 'inline scripts' => '~<script~') as $k => $re) echo "  $k: " . preg_match_all($re, $h) . "\n";
preg_match_all('~<style[^>]*>.*?</style>~s', $h, $st); $sum = 0; foreach ($st[0] as $s) $sum += strlen($s); printf("  inline CSS in header: %.1f KB\n", $sum / 1024);
preg_match_all('~<svg.*?</svg>~s', $h, $sv); $sum = 0; foreach ($sv[0] as $s) $sum += strlen($s); printf("  inline SVG in header: %.1f KB\n", $sum / 1024);
