<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. The CSS Elementor would write for each page if it rebuilt it
 * now, next to the file visitors get today. A normal plugin switch-off
 * clears Elementor's CSS files and element cache (Switch Plugins, 5 Oct), so
 * once Elementor Pro is off every page's CSS is rebuilt by the theme's
 * Elementor Pro port (inc/ports/elementor-pro.php: the slides and breadcrumb
 * widgets, element custom CSS). The previews never exercised that: they
 * read the files Pro wrote. Run it twice, as is (Pro builds) and with
 * skip_plugins elementor-pro (the port builds), and compare.
 *
 * Builds in memory only (Post CSS get_content(): no file, no meta written).
 * For each page: rule count of today's file and of the rebuild, and every
 * rule found in only one of them (the CSS is what visitors download anyway).
 *
 * Run: wp eval-file tools/diag-elementor-css-regen.php --allow-root [--skip-plugins=elementor-pro]
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!class_exists('\Elementor\Plugin') || !class_exists('\Elementor\Core\Files\CSS\Post')) { echo "Elementor not loaded\n"; exit; }
echo 'Elementor ' . ELEMENTOR_VERSION . ', Pro loaded: ' . (defined('ELEMENTOR_PRO_VERSION') ? 'yes' : 'no') . "\n";

// The pages carrying what the port does: the home page (slides), the footer
// bar's template (custom CSS), the breadcrumb templates; then a few more
// Elementor pages, to see nothing else moves.
$ids = array(75, 2592, 1853, 3014, 3377, 3425, 5660, 5661);
global $wpdb;
foreach ($wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder' WHERE p.post_status = 'publish' AND p.post_type IN ('page','elementor_library','elementor-hf') ORDER BY p.ID LIMIT 60") as $id) {
    if (!in_array((int) $id, $ids, true)) $ids[] = (int) $id;
}

$rules = function ($css) {
    $css = preg_replace('#/\*.*?\*/#s', '', (string) $css);
    $out = array();
    // flatten @media blocks into "@media … { selector { body } }" lines
    $len = strlen($css); $i = 0; $depth = 0; $buf = ''; $media = '';
    while ($i < $len) {
        $c = $css[$i];
        if ($c === '{') {
            $head = trim($buf); $buf = '';
            if (preg_match('/^@(media|supports|container)\b/i', $head)) { $media = preg_replace('/\s+/', '', $head); $depth++; $i++; continue; }
            $end = strpos($css, '}', $i);
            if ($end === false) break;
            $body = trim(substr($css, $i + 1, $end - $i - 1));
            $decl = array_filter(array_map(function ($d) { return preg_replace('/\s*:\s*/', ':', trim($d), 1); }, explode(';', $body)), 'strlen');
            sort($decl);
            $sel = preg_replace('/\s*([>,+~])\s*/', '$1', preg_replace('/\s+/', ' ', $head));
            $out[] = ($media !== '' && $depth > 0 ? $media . ' ' : '') . $sel . '{' . implode(';', $decl) . '}';
            $i = $end + 1; continue;
        }
        if ($c === '}') { if ($depth > 0) { $depth--; if ($depth === 0) $media = ''; } $buf = ''; $i++; continue; }
        $buf .= $c; $i++;
    }
    return array_count_values($out);
};

$upload = wp_upload_dir();
$totalOnlyOld = 0; $totalOnlyNew = 0; $pages = 0;
foreach ($ids as $id) {
    $post = get_post($id);
    if (!$post) continue;
    $file = $upload['basedir'] . '/elementor/css/post-' . $id . '.css';
    $old = is_readable($file) ? file_get_contents($file) : null;
    try {
        $cssFile = \Elementor\Core\Files\CSS\Post::create($id);
        $new = (string) $cssFile->get_content();
    } catch (Throwable $e) {
        echo "\n#$id: could not build: " . $e->getMessage() . "\n"; continue;
    }
    $pages++;
    $a = $old === null ? array() : $rules($old);
    $b = $rules($new);
    $onlyOld = array_diff_key($a, $b);
    $onlyNew = array_diff_key($b, $a);
    $title = mb_substr($post->post_title, 0, 30);
    $keys = array_keys($b); sort($keys);
    $fp = substr(md5(implode("\n", $keys)), 0, 8);
    $line = "#$id {$post->post_type} \"$title\": rebuilt " . count($b) . " rules, fingerprint $fp, ";
    if ($old === null) { echo "NOFILE $line" . "no file today (built on first view)\n"; continue; }
    $line .= "today's file " . count($a) . ' rules';
    $totalOnlyOld += count($onlyOld); $totalOnlyNew += count($onlyNew);
    if (!$onlyOld && !$onlyNew) { echo "SAME   $line\n"; continue; }
    echo "DIFF   $line, only today " . count($onlyOld) . ', only rebuilt ' . count($onlyNew) . "\n";
    // Rules visitors have today that the rebuild would lose matter most;
    // the port's own pages are shown in full.
    $detail = $onlyOld || in_array($id, array(75, 2592, 1853, 3014, 3377, 3425, 5660, 5661), true);
    if (!$detail) continue;
    foreach (array_slice(array_keys($onlyOld), 0, 30) as $r) echo "         only today:   " . substr($r, 0, 260) . "\n";
    foreach (array_slice(array_keys($onlyNew), 0, 30) as $r) echo "         only rebuilt: " . substr($r, 0, 260) . "\n";
}
echo "\n$pages pages; on the pages with a file today: $totalOnlyOld rules only in today's files, $totalOnlyNew only in the rebuild\ndone\n";
