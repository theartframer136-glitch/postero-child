<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The exact stored start of the four floating frame descriptions,
 * escaped, so the importer's "canvas print" sentence can be matched exactly.
 * Makes no changes, prints no secrets.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$bad = '/^\s*(<p>)?\s*This (?:[^<]|<\/?(?:strong|b|em)>){0,500}? is a premium gallery-wrapped canvas print, handcrafted to order in our Delaware studio\.\s*(<\/p>)?\s*/u';
foreach (array(8518, 8521, 8526, 8529) as $id) {
    $c = (string) get_post_field('post_content', $id);
    $pos = strpos($c, 'gallery-wrapped canvas print');
    printf("#%d len=%d sentence_at=%s matches_pattern=%d\n  start: %s\n", $id, strlen($c), var_export($pos, true),
        preg_match($bad, $c), json_encode(substr($c, 0, 420), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($pos !== false && $pos > 420) echo '  around: ' . json_encode(substr($c, max(0, $pos - 300), 360), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
