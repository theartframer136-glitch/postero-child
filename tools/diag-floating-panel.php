<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * What is the round toggle at the right edge, and what does it open?
 *
 * A visitor reports a floating button that used to open a bar of options and
 * now opens nothing. Before changing anything, find out which element it is:
 * print every floating/side-panel-looking container the homepage serves, what
 * links each one holds, and which plugin ships it. Changes nothing.
 *
 * Run: wp eval-file tools/diag-floating-panel.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== FLOATING PANEL DIAGNOSIS ===\n";

$res = wp_remote_get(add_query_arg('afdiag', time(), home_url('/')),
    array('timeout' => 60, 'sslverify' => false,
          'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Diag')));
if (is_wp_error($res)) { echo "  FETCH FAILED: " . $res->get_error_message() . "\n=== DONE ===\n"; return; }
$html = wp_remote_retrieve_body($res);
printf("  homepage: %d bytes\n", strlen($html));

// containers whose class or id suggests a floating / slide-out panel
echo "\n-- candidate floating panels --\n";
$pat = '#<(?:div|aside|nav|section)[^>]*(?:class|id)=["\'][^"\']*'
     . '(?:float|sticky-side|side-?bar-?float|slide-?out|slideout|toggle-?bar|'
     . 'sticky-?contact|contact-?bar|social-?bar|share-?bar|quick-?contact|'
     . 'callnow|call-?now|chat-?bar|widget-?float|elfsight|joinchat|wa-?float)'
     . '[^"\']*["\'][^>]*>#i';
if (preg_match_all($pat, $html, $m, PREG_OFFSET_CAPTURE)) {
    $shown = 0;
    foreach ($m[0] as $hit) {
        list($tag, $off) = $hit;
        echo "\n  " . trim(preg_replace('/\s+/', ' ', mb_strimwidth($tag, 0, 240, '…'))) . "\n";
        // what is inside it, roughly: the next 2500 characters
        $inside = substr($html, $off, 2500);
        if (preg_match_all('#<a[^>]+href=["\']([^"\']+)["\'][^>]*>#i', $inside, $links)) {
            foreach (array_slice(array_unique($links[1]), 0, 8) as $href) {
                echo "      link: " . mb_strimwidth($href, 0, 90, '…') . "\n";
            }
        } else {
            echo "      (no links in the first 2500 chars)\n";
        }
        if (++$shown >= 8) break;
    }
    if (!$shown) echo "  none\n";
} else { echo "  none matched\n"; }

// every whatsapp link on the page, with the tag that carries it — this is what
// the de-duplicate script inspects in the browser
echo "\n-- whatsapp links on the page --\n";
if (preg_match_all('#<a[^>]*href=["\'][^"\']*(?:wa\.me|whatsapp)[^"\']*["\'][^>]*>#i', $html, $wa)) {
    foreach ($wa[0] as $tag) {
        echo "  " . trim(preg_replace('/\s+/', ' ', mb_strimwidth($tag, 0, 200, '…'))) . "\n";
    }
    printf("  total: %d\n", count($wa[0]));
} else { echo "  none\n"; }

// plugins that ship floating buttons
echo "\n-- active plugins that could own a floating bar --\n";
foreach ((array) get_option('active_plugins', array()) as $p) {
    if (preg_match('#float|chat|whatsapp|social|share|contact|sticky|button#i', $p)) echo "  {$p}\n";
}

echo "=== DONE ===\n";
