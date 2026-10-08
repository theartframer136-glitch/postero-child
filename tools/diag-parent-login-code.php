<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Prints the parent theme's (postero) login code and its My
 * Account login template, so Cloudflare Turnstile can be added to them
 * without guessing: inc/modules/class-login.php, woocommerce/myaccount/
 * form-login.php, the scripts that post to postero_login, and every template
 * that prints the login popup. Theme code only; nothing secret.
 *
 * Run: wp eval-file tools/diag-parent-login-code.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);
$dir = get_template_directory();
function af_pl_show($path, $max = 260) {
    if (!is_file($path)) { echo "  (missing) $path\n"; return; }
    $lines = file($path);
    echo "----- " . basename(dirname($path)) . '/' . basename($path) . ' (' . count($lines) . " lines)\n";
    foreach (array_slice($lines, 0, $max) as $i => $l) printf("%4d %s", $i + 1, rtrim($l, "\r\n") . "\n");
}
af_pl_show($dir . '/inc/modules/class-login.php');
af_pl_show($dir . '/woocommerce/myaccount/form-login.php');
echo "\n=== files mentioning postero_login / postero-login / ajax-login\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$hits = array();
foreach ($it as $f) {
    if (!$f->isFile() || !preg_match('/\.(php|js)$/', $f->getFilename()) || strpos($f->getPathname(), '/merlin/') !== false) continue;
    $src = @file_get_contents($f->getPathname()); if ($src === false) continue;
    if (preg_match('/postero_login|postero-login|ajax-login|ajax_login|login-form|postero_ajax_login/i', $src)) $hits[] = $f->getPathname();
}
foreach ($hits as $h) echo '  ' . substr($h, strlen($dir)) . ' (' . filesize($h) . " bytes)\n";
foreach ($hits as $h) {
    if (preg_match('/\.min\.js$/', $h)) continue;
    if (preg_match('/class-login\.php$|form-login\.php$/', $h)) continue;
    if (filesize($h) < 9000) af_pl_show($h, 200);
    else {
        $src = file($h);
        echo "----- " . substr($h, strlen($dir)) . " (excerpts)\n";
        foreach ($src as $i => $l) if (preg_match('/postero_login|postero-login|ajax-login|ajax_login|login-form|wp_signon|user_login|password/i', $l)) printf("%4d %s\n", $i + 1, rtrim(substr($l, 0, 220)));
    }
}
echo "=== END\n";
