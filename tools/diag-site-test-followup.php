<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. The facts behind the open items of the 2026-10-23 full site test,
 * so each fix is written against what is stored rather than a guess.
 * Makes no changes, prints no secrets.
 *
 *   1. products whose slug ends -2/-3 and the listing they shadow: status, SKU,
 *      reviews, main picture and a hash of its file (same file = same artwork)
 *   2. product categories that share a name
 *   3. pictures in published content that point at an uploads file missing on disk
 *   4. Rank Math's robots and description settings for tags and categories
 *   5. empty product categories
 *   6. where the Google Pay error text lives in the plugins
 *   7. pages the test found without a description or H1: what they are
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
$J = function ($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); };
$up = wp_get_upload_dir();

function af_fu_img($id) {
    $tid = (int) get_post_thumbnail_id($id);
    if (!$tid) return array('thumb' => 0);
    $file = get_attached_file($tid);
    return array('thumb' => $tid, 'file' => $file ? basename($file) : '', 'exists' => $file && file_exists($file),
        'md5' => ($file && file_exists($file)) ? md5_file($file) : '', 'bytes' => ($file && file_exists($file)) ? filesize($file) : 0);
}

echo "=== 1. SUFFIXED PRODUCT SLUGS (-2, -3 ...) AND THE LISTING THEY SHADOW ===\n";
$rows = $wpdb->get_results("SELECT ID, post_name, post_status, post_title FROM {$wpdb->posts}
    WHERE post_type='product' AND post_status IN ('publish','private','draft') AND post_name REGEXP '-[0-9]+$' ORDER BY post_name");
$n = 0;
foreach ($rows as $r) {
    $base = preg_replace('/-[0-9]+$/', '', $r->post_name);
    $orig = $wpdb->get_row($wpdb->prepare("SELECT ID, post_status, post_title FROM {$wpdb->posts} WHERE post_type='product' AND post_name=%s LIMIT 1", $base));
    if (!$orig) continue;   // the number is part of the name (e.g. ...-36-60), not a duplicate suffix
    $n++;
    foreach (array('DUP' => $r, 'ORIG' => $orig) as $k => $p) {
        $pr = function_exists('wc_get_product') ? wc_get_product($p->ID) : null;
        $i = af_fu_img($p->ID);
        printf("  %-4s #%d %-8s %s\n       slug=%s sku=%s price=%s reviews=%d cats=%s\n       img=%s exists=%s md5=%s bytes=%d\n",
            $k, $p->ID, $p->post_status, mb_substr($p->post_title, 0, 70),
            get_post_field('post_name', $p->ID), $pr ? $pr->get_sku() : '?', $pr ? $pr->get_price() : '?',
            (int) get_comments_number($p->ID),
            implode(',', wp_get_post_terms($p->ID, 'product_cat', array('fields' => 'slugs'))),
            $i['file'] ?? '-', var_export($i['exists'] ?? false, true), $i['md5'] ?? '', $i['bytes'] ?? 0);
    }
    $a = af_fu_img($r->ID); $b = af_fu_img($orig->ID);
    echo '       SAME PICTURE FILE: ' . ((!empty($a['md5']) && $a['md5'] === ($b['md5'] ?? '')) ? 'YES' : 'no') . "\n\n";
}
echo "  pairs: $n\n";
$map = get_option('af_duplicate_redirects');
echo '  af_duplicate_redirects entries: ' . (is_array($map) ? count($map) : 0) . "\n";

echo "\n=== 2. PRODUCT CATEGORIES THAT SHARE A NAME ===\n";
$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
$byName = array();
foreach ($terms as $t) $byName[strtolower(html_entity_decode($t->name))][] = $t;
foreach ($byName as $name => $ts) {
    if (count($ts) < 2) continue;
    echo "  \"$name\"\n";
    foreach ($ts as $t) {
        $parent = $t->parent ? get_term($t->parent, 'product_cat') : null;
        printf("     term %d slug=%s parent=%s count=%d link=%s rm_canonical=%s\n", $t->term_id, $t->slug,
            $parent && !is_wp_error($parent) ? $parent->slug : '-', $t->count, get_term_link($t),
            (string) get_term_meta($t->term_id, 'rank_math_canonical_url', true));
    }
}

echo "\n=== 3. PICTURES IN PUBLISHED CONTENT POINTING AT A MISSING UPLOADS FILE ===\n";
$posts = $wpdb->get_results("SELECT ID, post_type, post_name, post_content FROM {$wpdb->posts}
    WHERE post_status='publish' AND post_type IN ('product','page','post') AND post_content LIKE '%wp-content/uploads/%'");
$missing = 0; $checked = 0;
foreach ($posts as $p) {
    if (!preg_match_all('~<img\b[^>]*\bsrc\s*=\s*["\']([^"\']*/wp-content/uploads/([^"\'?#]+))~i', $p->post_content, $m, PREG_SET_ORDER)) continue;
    foreach ($m as $hit) {
        $checked++;
        $rel = rawurldecode($hit[2]);
        if (!file_exists(trailingslashit($up['basedir']) . $rel)) {
            $missing++;
            printf("  #%d %s %s\n     %s\n", $p->ID, $p->post_type, $p->post_name, $rel);
        }
    }
}
echo "  checked $checked inline pictures, $missing missing\n";

echo "\n=== 4. RANK MATH: TAGS AND CATEGORIES ===\n";
$o = get_option('rank-math-options-titles');
if (is_array($o)) {
    foreach ($o as $k => $v) {
        if (preg_match('/^tax_(product_tag|product_cat|category|post_tag)_/', $k) || preg_match('/noindex_empty|remove_.*base/', $k)) {
            echo "  $k = " . $J($v) . "\n";
        }
    }
} else echo "  rank-math-options-titles not set\n";
$g = get_option('rank-math-options-general');
if (is_array($g)) foreach (array('noindex_empty_taxonomies') as $k) if (isset($g[$k])) echo "  general.$k = " . $J($g[$k]) . "\n";
$tags = get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false));
$tc = array('total' => 0, 'empty' => 0, 'with_desc' => 0, 'one_product' => 0);
foreach ($tags as $t) { $tc['total']++; if (!$t->count) $tc['empty']++; if ($t->count === 1) $tc['one_product']++; if (trim($t->description) !== '') $tc['with_desc']++; }
echo '  product tags: ' . $J($tc) . "\n";

echo "\n=== 5. EMPTY PRODUCT CATEGORIES ===\n";
foreach ($terms as $t) {
    if ($t->count) continue;
    $kids = get_term_children($t->term_id, 'product_cat');
    $kidCount = 0; foreach ((array) $kids as $k) { $kt = get_term($k, 'product_cat'); if ($kt && !is_wp_error($kt)) $kidCount += $kt->count; }
    printf("  %d %s  (children holding %d products)  rm_robots=%s\n", $t->term_id, get_term_link($t), $kidCount,
        $J(get_term_meta($t->term_id, 'rank_math_robots', true)));
}

echo "\n=== 6. GOOGLE PAY ERROR TEXT IN PLUGINS ===\n";
$needle = 'An unexpected error occurred while using Google Pay';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(WP_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS));
$found = 0;
foreach ($it as $f) {
    if ($found >= 8) break;
    $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, array('js', 'php'), true) || $f->getSize() > 3000000) continue;
    $c = @file_get_contents($f->getPathname());
    if ($c === false || strpos($c, $needle) === false) continue;
    $found++;
    $pos = strpos($c, $needle);
    echo '  ' . str_replace(WP_PLUGIN_DIR . '/', '', $f->getPathname()) . "\n     …" . $J(substr($c, max(0, $pos - 500), 700)) . "…\n";
}
if (!$found) echo "  not found in any plugin file\n";
$sq = get_option('woocommerce_square_credit_card_settings');
if (is_array($sq)) foreach ($sq as $k => $v) if (preg_match('/google|apple|digital_wallet|express/i', $k)) echo "  square.$k = " . $J($v) . "\n";

echo "\n=== 7. PAGES WITHOUT A DESCRIPTION OR H1 IN THE TEST ===\n";
foreach (array('frame-the-moment', 'try-on-wall', 'all-artists', 'all-artists-2', 'my-orders', 'store-listing', 'icons',
               'terms-of-use', 'terms-of-service', 'terms-conditions', 'cart-2', 'my-account', 'wishlist', 'cart') as $slug) {
    $p = get_page_by_path($slug);
    if (!$p) { echo "  /$slug/  not a page\n"; continue; }
    $menus = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_menu_item_object_id' AND meta_value=%d", $p->ID));
    printf("  /%s/ #%d %s template=%s elementor=%s words=%d menu_items=%d rm_desc=%s rm_robots=%s shortcode=%s\n",
        $slug, $p->ID, $p->post_status, get_page_template_slug($p->ID) ?: '-',
        get_post_meta($p->ID, '_elementor_edit_mode', true) ?: '-',
        str_word_count(wp_strip_all_tags($p->post_content)), (int) $menus,
        $J(mb_substr((string) get_post_meta($p->ID, 'rank_math_description', true), 0, 60)),
        $J(get_post_meta($p->ID, 'rank_math_robots', true)),
        preg_match('/\[([a-z_\-]+)/i', $p->post_content, $sm) ? $sm[1] : '-');
}
echo "\nDONE\n";
