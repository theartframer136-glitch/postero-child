<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Find every "free shipping" claim on the whole site, not just in theme code.
 *
 * The wording lives in more places than a grep of the theme reveals: page and
 * post content, Elementor's JSON in postmeta, product descriptions, category
 * descriptions, widgets, menu items, options, and the rendered pages
 * themselves (which is where a plugin's own copy shows up). This reports all
 * of them with enough context to fix each one, and changes nothing.
 *
 * Run: wp eval-file tools/diag-free-shipping.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$needles = array('free shipping', 'free usa shipping', 'free delivery', 'shipping is free',
                 'shipping free', 'free ship');
$like = array();
foreach ($needles as $n) $like[] = "LOWER(%s) LIKE '%" . $wpdb->esc_like(strtolower($n)) . "%'";

function af_fs_snip($text, $needles) {
    $low = strtolower((string) $text);
    foreach ($needles as $n) {
        $pos = strpos($low, strtolower($n));
        if ($pos !== false) {
            $start = max(0, $pos - 45);
            return trim(preg_replace('/\s+/', ' ', mb_substr((string) $text, $start, 130)));
        }
    }
    return '';
}

echo "=== FREE-SHIPPING CLAIMS ACROSS THE SITE ===\n";
$total = 0;

// 1. post/page/product content and excerpts
echo "\n-- content (pages, posts, products) --\n";
$where = array();
foreach ($needles as $n) {
    $esc = '%' . $wpdb->esc_like($n) . '%';
    $where[] = $wpdb->prepare("LOWER(post_content) LIKE %s", $esc);
    $where[] = $wpdb->prepare("LOWER(post_excerpt) LIKE %s", $esc);
    $where[] = $wpdb->prepare("LOWER(post_title) LIKE %s", $esc);
}
$rows = $wpdb->get_results("SELECT ID, post_type, post_title, post_status, post_content, post_excerpt
    FROM {$wpdb->posts}
    WHERE post_status IN ('publish','draft','private') AND post_type NOT IN ('revision','attachment')
      AND (" . implode(' OR ', $where) . ") LIMIT 60");
if ($rows) {
    foreach ($rows as $r) {
        $total++;
        printf("  #%-6d %-10s %s\n", $r->ID, $r->post_type, mb_strimwidth($r->post_title, 0, 60, '…'));
        $s = af_fs_snip($r->post_content . ' ' . $r->post_excerpt, $needles);
        if ($s) echo "          …{$s}…\n";
        echo "          edit: " . admin_url('post.php?post=' . $r->ID . '&action=edit') . "\n";
    }
} else { echo "  none\n"; }

// 2. postmeta — this is where Elementor keeps page designs
echo "\n-- postmeta (Elementor designs, custom fields) --\n";
$mwhere = array();
foreach ($needles as $n) $mwhere[] = $wpdb->prepare("LOWER(meta_value) LIKE %s", '%' . $wpdb->esc_like($n) . '%');
// exclude the rewrite tool's own backups: they hold the ORIGINAL wording by
// design, and counting them as live claims overstates what is left to fix
$meta = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
    WHERE (" . implode(' OR ', $mwhere) . ")
      AND meta_key NOT IN ('_af_shipping_copy_backup','_af_elementor_backup') LIMIT 40");
if ($meta) {
    foreach ($meta as $m) {
        $total++;
        $t = get_the_title($m->post_id);
        printf("  #%-6d %-24s %s\n", $m->post_id, mb_strimwidth($m->meta_key, 0, 24), mb_strimwidth($t, 0, 44, '…'));
        $s = af_fs_snip($m->meta_value, $needles);
        if ($s) echo "          …{$s}…\n";
    }
} else { echo "  none\n"; }

// 3. options — widgets, theme mods, plugin settings
echo "\n-- options (widgets, theme settings, plugins) --\n";
$owhere = array();
foreach ($needles as $n) $owhere[] = $wpdb->prepare("LOWER(option_value) LIKE %s", '%' . $wpdb->esc_like($n) . '%');
$opts = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options}
    WHERE (" . implode(' OR ', $owhere) . ") AND option_name NOT LIKE '_transient%' LIMIT 30");
if ($opts) {
    foreach ($opts as $o) {
        $total++;
        printf("  %-40s\n", $o->option_name);
        $s = af_fs_snip($o->option_value, $needles);
        if ($s) echo "          …{$s}…\n";
    }
} else { echo "  none\n"; }

// 4. term descriptions (category text shows above product grids)
echo "\n-- category / tag descriptions --\n";
$twhere = array();
foreach ($needles as $n) $twhere[] = $wpdb->prepare("LOWER(description) LIKE %s", '%' . $wpdb->esc_like($n) . '%');
$terms = $wpdb->get_results("SELECT term_id, description FROM {$wpdb->term_taxonomy}
    WHERE (" . implode(' OR ', $twhere) . ") LIMIT 20");
if ($terms) {
    foreach ($terms as $t) {
        $total++;
        $term = get_term($t->term_id);
        printf("  %s\n", ($term && !is_wp_error($term)) ? $term->name : ('term #' . $t->term_id));
        $s = af_fs_snip($t->description, $needles);
        if ($s) echo "          …{$s}…\n";
    }
} else { echo "  none\n"; }

// 5. what visitors actually see — the rendered pages
echo "\n-- rendered pages (what a visitor reads) --\n";
$pages = array(
    'home'    => home_url('/'),
    'shop'    => get_permalink(wc_get_page_id('shop')),
    'about'   => home_url('/about/'),
    'shipping'=> home_url('/shipping-delivery/'),
    'faqs'    => home_url('/faqs/'),
    'cart'    => wc_get_cart_url(),
);
$first = wc_get_products(array('status'=>'publish','limit'=>1,'return'=>'ids'));
if ($first) $pages['a product'] = get_permalink($first[0]);
foreach ($pages as $name => $url) {
    if (!$url) continue;
    $r = wp_remote_get(add_query_arg('nocache', time(), $url),
        array('timeout'=>45, 'sslverify'=>false, 'headers'=>array('User-Agent'=>'AF-Diag')));
    if (is_wp_error($r)) { printf("  %-10s FETCH FAILED %s\n", $name, $r->get_error_message()); continue; }
    $body = wp_remote_retrieve_body($r);
    $hits = array();
    foreach ($needles as $n) {
        if (stripos($body, $n) !== false) $hits[] = $n;
    }
    if ($hits) {
        $total++;
        printf("  %-10s CLAIMS: %s\n", $name, implode(', ', array_unique($hits)));
        echo "          …" . af_fs_snip(wp_strip_all_tags($body), $needles) . "…\n";
    } else {
        printf("  %-10s clean\n", $name);
    }
}

// WooCommerce's own shipping methods: a cart saying "Free shipping" is usually
// a zone method, not copy — a store setting only the owner should change
echo "\n-- WooCommerce shipping methods (settings, not copy) --\n";
if (class_exists('WC_Shipping_Zones')) {
    $zones = WC_Shipping_Zones::get_zones();
    $zones[] = array('zone_name' => 'Rest of the world',
                     'shipping_methods' => WC_Shipping_Zones::get_zone(0)->get_shipping_methods());
    foreach ($zones as $z) {
        foreach ((array) $z['shipping_methods'] as $mth) {
            $on = (isset($mth->enabled) && $mth->enabled === 'yes') ? 'enabled ' : 'disabled';
            $cost = isset($mth->cost) && $mth->cost !== '' ? ('  cost: ' . $mth->cost) : '';
            printf("  %-22s %-22s %s%s\n", mb_strimwidth($z['zone_name'], 0, 22),
                   $mth->title, $on, $cost);
            if ($mth->id === 'free_shipping' && $on === 'enabled ') {
                echo "      ^ this is why the cart says free shipping — a setting, not text\n";
            }
        }
    }
} else {
    echo "  (WooCommerce shipping API unavailable)\n";
}

echo "\n=== {$total} PLACE(S) TO FIX (content only; settings listed above) ===\n";
echo "=== DONE ===\n";
