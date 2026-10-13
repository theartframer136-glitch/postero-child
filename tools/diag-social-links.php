<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Where the site's Facebook, Instagram, YouTube, Pinterest and
 * WhatsApp links live, and what they point to (13 Oct: the footer icons were
 * said to link to plain facebook.com / instagram.com; the theme's own footer
 * block in functions.php is switched off, so the visible ones come from
 * Elementor templates or widgets in the database).
 *
 *   wp eval-file tools/diag-social-links.php
 *
 * Lists every published post, page, Elementor template and menu item whose
 * content, Elementor data or URL holds such a link: ID, type, title, and each
 * distinct address, marked BARE when it is only the network's home page.
 */
global $wpdb;
$re = '#https?://(?:www\.|m\.)?(facebook\.com|fb\.com|instagram\.com|youtube\.com|youtu\.be|pinterest\.[a-z.]+|wa\.me|api\.whatsapp\.com|twitter\.com|x\.com|tiktok\.com|linkedin\.com)(/[^\s"\'<>\\\\)]*)?#i';
$bare = function ($u) { $p = parse_url($u, PHP_URL_PATH); return $p === null || $p === '' || $p === '/'; };

$found = array();
$scan = function ($id, $where, $text) use ($re, &$found) {
    if (!is_string($text) || $text === '') return;
    $t = str_replace('\\/', '/', $text);
    if (!preg_match_all($re, $t, $m)) return;
    foreach ($m[0] as $u) $found[$id][$where][rtrim($u, '.,;')] = true;
};

$posts = $wpdb->get_results("SELECT ID, post_type, post_status, post_title, post_content FROM {$wpdb->posts}
    WHERE post_status IN ('publish','private') AND post_type NOT IN ('revision','attachment','product_variation','shop_order','af_reel')
      AND (post_content LIKE '%facebook.%' OR post_content LIKE '%instagram.%' OR post_content LIKE '%youtube.%' OR post_content LIKE '%youtu.be%' OR post_content LIKE '%pinterest.%' OR post_content LIKE '%wa.me%' OR post_content LIKE '%whatsapp%')");
foreach ($posts as $p) $scan($p->ID, 'content', $p->post_content);

$meta = $wpdb->get_results("SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
    WHERE p.post_status IN ('publish','private') AND m.meta_key IN ('_elementor_data','_menu_item_url')
      AND (m.meta_value LIKE '%facebook.%' OR m.meta_value LIKE '%instagram.%' OR m.meta_value LIKE '%youtube.%' OR m.meta_value LIKE '%youtu.be%' OR m.meta_value LIKE '%pinterest.%' OR m.meta_value LIKE '%wa.me%' OR m.meta_value LIKE '%whatsapp%')");
foreach ($meta as $r) $scan($r->post_id, $r->meta_key === '_elementor_data' ? 'elementor' : 'menu', $r->meta_value);

foreach (array('widget_text', 'widget_custom_html', 'widget_media_image', 'theme_mods_postero', 'theme_mods_postero-child', 'elementor_active_kit') as $opt) {
    $v = get_option($opt);
    if ($v !== false) $scan('option:' . $opt, 'option', is_string($v) ? $v : wp_json_encode($v));
}

echo "=== social links by where they live (published content only)\n";
$bareCount = 0;
foreach ($found as $id => $wheres) {
    if (is_numeric($id)) {
        $type = get_post_type($id);
        $title = get_the_title($id);
        $extra = $type === 'elementor_library' ? ' [' . (get_post_meta($id, '_elementor_template_type', true) ?: 'template') . ']' : '';
        echo "#{$id} {$type}{$extra} \"" . mb_substr($title, 0, 60) . "\"\n";
    } else {
        echo "{$id}\n";
    }
    foreach ($wheres as $where => $urls) {
        foreach (array_keys($urls) as $u) {
            $b = $bare($u);
            if ($b) $bareCount++;
            echo "    {$where}: {$u}" . ($b ? '   <- BARE (the network\'s home page, not ours)' : '') . "\n";
        }
    }
}
echo "=== " . count($found) . " place(s), {$bareCount} bare link(s)\n";
