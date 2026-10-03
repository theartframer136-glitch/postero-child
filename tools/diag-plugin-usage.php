<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * What every plugin is actually used for on this site.
 *
 * Owner, 3 Oct: move what the plugins do into the theme's own code and
 * deactivate them, changing nothing a visitor sees. A plugin can only be
 * replaced exactly once it is known what of it the site uses. Read-only:
 *
 *   1. shortcodes: each registered shortcode with the plugin that owns it, and
 *      every post, page, template or widget that uses it
 *   2. Elementor: every widget type used in every Elementor document (pages,
 *      header, footer, templates, popups), by the plugin that provides it, and
 *      elements carrying an addon's extension settings (visibility rules,
 *      effects)
 *   3. hooks: what each plugin attaches to the storefront (head, footer,
 *      content, WooCommerce templates)
 *   4. each plugin's own data and settings that matter for a replacement
 *
 * Run: wp eval-file tools/diag-plugin-usage.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb, $shortcode_tags, $wp_filter;

function af_pu_owner_file($f) {
    $f = str_replace('\\', '/', (string) $f);
    if (preg_match('#/wp-content/plugins/([^/]+)#', $f, $m)) return preg_replace('/\.php$/', '', $m[1]);
    if (preg_match('#/wp-content/mu-plugins/([^/]+)#', $f, $m)) return 'mu:' . preg_replace('/\.php$/', '', $m[1]);
    if (preg_match('#/wp-content/themes/([^/]+)#', $f, $m)) return 'theme:' . $m[1];
    if (strpos($f, '/wp-includes/') !== false || strpos($f, '/wp-admin/') !== false) return 'core';
    return $f === '' ? '?' : 'other';
}
function af_pu_owner_cb($fn) {
    try {
        if ($fn instanceof Closure) return af_pu_owner_file((new ReflectionFunction($fn))->getFileName());
        if (is_string($fn) && strpos($fn, '::') !== false) { list($c, $m) = explode('::', $fn, 2); return af_pu_owner_file((new ReflectionMethod($c, $m))->getFileName()); }
        if (is_string($fn)) return function_exists($fn) ? af_pu_owner_file((new ReflectionFunction($fn))->getFileName()) : '?';
        if (is_array($fn) && count($fn) === 2) return af_pu_owner_file((new ReflectionMethod($fn[0], $fn[1]))->getFileName());
        if (is_object($fn) && method_exists($fn, '__invoke')) return af_pu_owner_file((new ReflectionMethod($fn, '__invoke'))->getFileName());
    } catch (Throwable $e) {}
    return '?';
}
function af_pu_doc($id) {
    $p = get_post($id); if (!$p) return "#$id";
    return "#$id " . $p->post_type . '/' . $p->post_status . ' "' . mb_substr(wp_strip_all_tags($p->post_title), 0, 40) . '"';
}

// ── 1. shortcodes ─────────────────────────────────────────────────────────────
echo "=== 1. SHORTCODES BY OWNER, AND WHERE EACH IS USED ===\n";
$by = array();
foreach ($shortcode_tags as $tag => $cb) $by[af_pu_owner_cb($cb)][] = $tag;
ksort($by);
$skip_types = "'revision','nav_menu_item','customize_changeset','oembed_cache','user_request','wp_global_styles','shop_order','shop_order_refund','scheduled-action','shop_coupon'";
foreach ($by as $owner => $tags) {
    if ($owner === 'core') continue;
    $used = array();
    foreach ($tags as $tag) {
        $like = '%' . $wpdb->esc_like('[' . $tag) . '%';
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ($skip_types) AND post_status IN ('publish','private','draft','future') AND post_content LIKE %s LIMIT 50", $like));
        $eids = $wpdb->get_col($wpdb->prepare("SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_elementor_data' AND p.post_status IN ('publish','private','draft') AND pm.meta_value LIKE %s LIMIT 50", '%' . $wpdb->esc_like('[' . $tag) . '%'));
        $wid = 0;
        foreach ((array) get_option('widget_text', array()) as $w) if (is_array($w) && isset($w['text']) && strpos($w['text'], '[' . $tag) !== false) $wid++;
        foreach ((array) get_option('widget_custom_html', array()) as $w) if (is_array($w) && isset($w['content']) && strpos($w['content'], '[' . $tag) !== false) $wid++;
        $all = array_unique(array_merge($ids, $eids));
        if ($all || $wid) $used[$tag] = count($all) . ' docs' . ($wid ? " + $wid widgets" : '') . ': ' . implode('; ', array_map('af_pu_doc', array_slice($all, 0, 6)));
    }
    echo "  [$owner] " . count($tags) . ' shortcodes: ' . implode(' ', array_slice($tags, 0, 30)) . (count($tags) > 30 ? ' …' : '') . "\n";
    foreach ($used as $t => $u) echo "      USED [$t] $u\n";
    if (!$used) echo "      (none used in posts, pages, templates or text widgets)\n";
}

// ── 2. Elementor widgets ─────────────────────────────────────────────────────
echo "\n=== 2. ELEMENTOR: WIDGET TYPES IN USE, BY THE PLUGIN THAT PROVIDES THEM ===\n";
$wowner = array();
if (class_exists('\Elementor\Plugin')) {
    try {
        foreach (\Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $name => $w) $wowner[$name] = af_pu_owner_file((new ReflectionClass($w))->getFileName());
    } catch (Throwable $e) { echo "  (widget list failed: " . $e->getMessage() . ")\n"; }
}
$docs = $wpdb->get_results("SELECT p.ID, p.post_type, p.post_status, p.post_title, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_elementor_data' AND p.post_status IN ('publish','private') AND p.post_type NOT IN ('revision')");
$uses = array(); $ext = array(); $docTypes = array();
$prefixes = array('eael_' => 'essential-addons-for-elementor-lite', 'premium_' => 'premium-addons-for-elementor', 'pa_' => 'premium-addons-for-elementor', 'dce_' => 'dynamic-visibility-for-elementor', 'enabled_visibility' => 'dynamic-visibility-for-elementor', 'hfe_' => 'header-footer-elementor', 'uae_' => 'header-footer-elementor', 'motion_fx' => 'elementor-pro', 'sticky' => 'elementor-pro', '_animation' => 'elementor (entrance animation)', 'ekit_' => 'elementskit');
foreach ($docs as $d) {
    $data = json_decode($d->meta_value, true); if (!is_array($data)) continue;
    $ttype = get_post_meta($d->ID, '_elementor_template_type', true);
    $docTypes[$d->ID] = $d->post_type . ($ttype ? ':' . $ttype : '') . '/' . $d->post_status . ' "' . mb_substr($d->post_title, 0, 36) . '"';
    $walk = function ($els) use (&$walk, &$uses, &$ext, $d, $prefixes) {
        foreach ($els as $el) {
            if (!is_array($el)) continue;
            if (($el['elType'] ?? '') === 'widget' && !empty($el['widgetType'])) { $uses[$el['widgetType']][$d->ID] = ($uses[$el['widgetType']][$d->ID] ?? 0) + 1; }
            foreach ((array) ($el['settings'] ?? array()) as $k => $v) {
                foreach ($prefixes as $px => $pl) {
                    if (strpos($k, $px) === 0 && $v !== '' && $v !== null && $v !== array() && $v !== 'no' && $v !== 'none') { $ext[$pl][$k][$d->ID] = true; break; }
                }
            }
            if (!empty($el['elements'])) $walk($el['elements']);
        }
    };
    $walk($data);
}
$byOwner = array();
foreach ($uses as $wt => $where) $byOwner[$wowner[$wt] ?? '(not registered now)'][$wt] = $where;
ksort($byOwner);
foreach ($byOwner as $owner => $wts) {
    $n = 0; foreach ($wts as $w) $n += array_sum($w);
    echo "  [$owner] " . count($wts) . " widget types, $n uses\n";
    foreach ($wts as $wt => $where) {
        $ids = array_keys($where);
        echo "      $wt x" . array_sum($where) . ' in ' . count($ids) . ' docs: ' . implode('; ', array_map(function ($id) use ($docTypes) { return "#$id " . ($docTypes[$id] ?? ''); }, array_slice($ids, 0, 5))) . (count($ids) > 5 ? ' …' : '') . "\n";
    }
}
echo "  extension settings on elements (not widgets):\n";
foreach ($ext as $pl => $keys) {
    $docsN = array(); foreach ($keys as $k => $ids) foreach ($ids as $id => $_) $docsN[$id] = true;
    echo "    [$pl] " . count($keys) . ' setting keys in ' . count($docsN) . ' docs: ' . implode(', ', array_slice(array_keys($keys), 0, 14)) . "\n";
}
echo "  Elementor documents: " . count($docs) . "; theme-builder conditions: " . wp_json_encode(get_option('elementor_pro_theme_builder_conditions')) . "\n";

// ── 3. storefront hooks ──────────────────────────────────────────────────────
echo "\n=== 3. WHAT EACH PLUGIN ATTACHES TO THE STOREFRONT (callbacks per hook) ===\n";
$front = array('wp_head', 'wp_footer', 'wp_body_open', 'wp_enqueue_scripts', 'the_content', 'body_class', 'template_redirect', 'template_include', 'wp', 'init', 'wp_loaded', 'woocommerce_before_single_product', 'woocommerce_single_product_summary', 'woocommerce_after_single_product_summary', 'woocommerce_before_add_to_cart_button', 'woocommerce_after_add_to_cart_button', 'woocommerce_after_shop_loop_item', 'woocommerce_before_shop_loop_item_title', 'woocommerce_after_shop_loop_item_title', 'woocommerce_before_cart', 'woocommerce_cart_contents', 'woocommerce_after_cart_table', 'woocommerce_before_checkout_form', 'woocommerce_review_order_before_payment', 'woocommerce_thankyou', 'woocommerce_product_get_price', 'woocommerce_get_price_html', 'woocommerce_cart_item_name', 'woocommerce_single_product_image_thumbnail_html', 'woocommerce_product_thumbnails', 'woocommerce_dropdown_variation_attribute_options_html', 'wp_ajax_nopriv_', 'rest_api_init', 'woocommerce_payment_gateways', 'woocommerce_email', 'comment_form', 'woocommerce_product_tabs', 'wp_nav_menu_items', 'get_footer', 'woocommerce_currency', 'woocommerce_currency_symbol', 'raw_woocommerce_price', 'option_', 'gettext', 'locale', 'the_title', 'widget_text');
$tbl = array();
foreach ($wp_filter as $tag => $hook) {
    if (!($hook instanceof WP_Hook)) continue;
    $match = false; foreach ($front as $f) { if ($tag === $f || (substr($f, -1) === '_' && strpos($tag, $f) === 0)) { $match = true; break; } }
    if (!$match) continue;
    foreach ($hook->callbacks as $prio => $cbs) foreach ($cbs as $cb) { $o = af_pu_owner_cb($cb['function']); if ($o === 'core') continue; $tbl[$o][$tag] = ($tbl[$o][$tag] ?? 0) + 1; }
}
ksort($tbl);
foreach ($tbl as $o => $tags) { arsort($tags); $ajax = 0; foreach ($tags as $t => $n) if (strpos($t, 'wp_ajax_nopriv_') === 0) $ajax += $n; echo "  [$o] " . implode(', ', array_map(function ($t, $n) { return "$t:$n"; }, array_keys(array_slice(array_filter($tags, function ($k) { return strpos($k, 'wp_ajax_nopriv_') !== 0; }, ARRAY_FILTER_USE_KEY), 0, 18, true)), array_slice(array_filter($tags, function ($k) { return strpos($k, 'wp_ajax_nopriv_') !== 0; }, ARRAY_FILTER_USE_KEY), 0, 18, true))) . ($ajax ? " | guest AJAX actions: $ajax (" . implode(' ', array_map(function ($t) { return substr($t, 15); }, array_slice(array_keys(array_filter($tags, function ($k) { return strpos($k, 'wp_ajax_nopriv_') === 0; }, ARRAY_FILTER_USE_KEY)), 0, 10))) . ')' : '') . "\n"; }

// ── 4. each plugin's own data ────────────────────────────────────────────────
echo "\n=== 4. PLUGIN DATA AND SETTINGS ===\n";
// Never print a credential into a job log: values of keys that name a token,
// key, secret, password or code are masked, as is any long opaque string.
function af_pu_mask($name, $v) {
    if (preg_match('/token|secret|passw|api_?key|bypass|auth|license|nonce|salt/i', $name)) return '[masked, ' . strlen($v) . ' chars]';
    $v = preg_replace('/(s:\d+:"[^"]*(?:token|secret|passw|api_?key|bypass|auth|license|code)[^"]*";s:\d+:")[^"]*/i', '$1[masked]', $v);
    return preg_replace('/[A-Za-z0-9_\-]{40,}/', '[masked]', $v);
}
$opt_prefix = function ($px, $max = 40) use ($wpdb) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, LENGTH(option_value) AS len, LEFT(option_value, 300) AS v, autoload FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT %d", $wpdb->esc_like($px) . '%', $max));
    foreach ($rows as $r) echo '      ' . $r->option_name . ' (' . $r->len . ' b, autoload ' . $r->autoload . '): ' . af_pu_mask($r->option_name, preg_replace('/\s+/', ' ', $r->v)) . "\n";
    if (!$rows) echo "      (no options starting $px)\n";
};
$cnt = function ($sql) use ($wpdb) { $v = $wpdb->get_var($sql); return $v === null ? 'n/a' : $v; };
$P = $wpdb->prefix;
echo "  [click-to-chat-for-whatsapp]\n"; $opt_prefix('ht_ctc_', 30);
echo "  [wpc-estimated-delivery-date]\n"; $opt_prefix('wpced', 30);
echo "  [wpc-smart-messages] message posts: " . $cnt("SELECT COUNT(*) FROM {$P}posts WHERE post_type LIKE 'wpcsm%' AND post_status='publish'") . "\n"; $opt_prefix('wpcsm', 20);
foreach ($wpdb->get_results("SELECT ID, post_title, post_type, LEFT(post_content, 300) c FROM {$P}posts WHERE post_type LIKE 'wpcsm%' AND post_status='publish' LIMIT 10") as $r) echo "      #{$r->ID} {$r->post_type} \"{$r->post_title}\": " . preg_replace('/\s+/', ' ', $r->c) . "\n";
echo "  [mas-woocommerce-brands] taxonomies: " . implode(', ', array_filter(get_taxonomies(array(), 'names'), function ($t) { return stripos($t, 'brand') !== false; })) . "\n";
foreach (array_filter(get_taxonomies(array(), 'names'), function ($t) { return stripos($t, 'brand') !== false; }) as $tx) echo "      $tx: " . wp_count_terms(array('taxonomy' => $tx, 'hide_empty' => false)) . ' terms, ' . $cnt($wpdb->prepare("SELECT COUNT(DISTINCT tr.object_id) FROM {$P}term_relationships tr JOIN {$P}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = %s", $tx)) . " products assigned\n";
$opt_prefix('mas_wcbr', 10);
echo "  [yith-woocommerce-featured-video] products with a video: " . $cnt("SELECT COUNT(DISTINCT post_id) FROM {$P}postmeta WHERE meta_key IN ('_video_url','_yith_wc_featured_video','_ywcfav_video','_ywcfav_audio') AND meta_value <> ''") . "\n";
foreach ($wpdb->get_results("SELECT DISTINCT meta_key FROM {$P}postmeta WHERE meta_key LIKE '%ywcfav%' OR meta_key LIKE '%featured_video%' OR meta_key = '_video_url' LIMIT 10") as $r) echo "      meta key: {$r->meta_key} (" . $cnt($wpdb->prepare("SELECT COUNT(*) FROM {$P}postmeta WHERE meta_key = %s AND meta_value <> ''", $r->meta_key)) . " non-empty)\n";
$opt_prefix('ywcfav', 15);
echo "  [woo-variation-swatches] attribute types: ";
if (function_exists('wc_get_attribute_taxonomies')) foreach (wc_get_attribute_taxonomies() as $a) echo $a->attribute_name . '=' . $a->attribute_type . ' ';
echo "| variable products: " . $cnt("SELECT COUNT(*) FROM {$P}posts p JOIN {$P}term_relationships tr ON tr.object_id = p.ID JOIN {$P}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id JOIN {$P}terms t ON t.term_id = tt.term_id WHERE p.post_type='product' AND p.post_status='publish' AND tt.taxonomy='product_type' AND t.slug='variable'") . "\n"; $opt_prefix('woo_variation_swatches', 5);
echo "  [woo-smart-quick-view]\n"; $opt_prefix('woosq', 40);
echo "  [woo-smart-wishlist] wishlist keys stored: " . $cnt("SELECT COUNT(*) FROM {$P}options WHERE option_name LIKE 'woosw_list_%'") . " | users with a key: " . $cnt("SELECT COUNT(*) FROM {$P}usermeta WHERE meta_key LIKE 'woosw%'") . "\n";
foreach ($wpdb->get_results("SELECT option_name, LENGTH(option_value) len, LEFT(option_value,200) v FROM {$P}options WHERE option_name LIKE 'woosw%' AND option_name NOT LIKE 'woosw_list_%' ORDER BY option_name LIMIT 60") as $r) echo "      {$r->option_name} ({$r->len} b): " . preg_replace('/\s+/', ' ', $r->v) . "\n";
echo "  [contact-form-7] forms: " . $cnt("SELECT COUNT(*) FROM {$P}posts WHERE post_type='wpcf7_contact_form' AND post_status='publish'") . "\n";
foreach ($wpdb->get_results("SELECT ID, post_title FROM {$P}posts WHERE post_type='wpcf7_contact_form' AND post_status='publish' LIMIT 10") as $r) echo "      #{$r->ID} \"{$r->post_title}\" mail to: " . (get_post_meta($r->ID, '_mail', true)['recipient'] ?? '?') . "\n";
echo "  [mailchimp-for-wp] forms: " . $cnt("SELECT COUNT(*) FROM {$P}posts WHERE post_type='mc4wp-form' AND post_status='publish'") . " | API key set: " . (((array) get_option('mc4wp', array()))['api_key'] ?? '') !== '' ? 'yes' : 'no'; echo "\n"; $opt_prefix('mc4wp', 10);
echo "  [embedder-for-google-reviews]\n"; $opt_prefix('grwp', 20); $opt_prefix('google_reviews', 10);
echo "  [insta-gallery]\n"; $opt_prefix('insta_gallery', 10); $opt_prefix('qligg', 15);
echo "  [customer-reviews-woocommerce] product reviews: " . $cnt("SELECT COUNT(*) FROM {$P}comments WHERE comment_type='review' AND comment_approved='1'") . " | with CR meta: " . $cnt("SELECT COUNT(DISTINCT comment_id) FROM {$P}commentmeta WHERE meta_key LIKE 'ivole%'") . "\n";
foreach ($wpdb->get_results("SELECT option_name, LEFT(option_value,120) v FROM {$P}options WHERE option_name IN ('ivole_enable','ivole_enable_coupon','ivole_reviews_shortcode','ivole_ajax_reviews','ivole_attach_image','ivole_reviews_histogram','ivole_reviews_verified','ivole_enable_qna','ivole_disable_lightbox','ivole_form_attach_media','ivole_verified_reviews','ivole_reviews_nobranding','ivole_enable_manual')") as $r) echo "      {$r->option_name}: {$r->v}\n";
echo "  [revslider] sliders: ";
foreach ((array) $wpdb->get_results("SELECT id, title, alias FROM {$P}revslider_sliders LIMIT 20") as $r) echo "#{$r->id} {$r->alias}; ";
foreach ((array) $wpdb->get_results("SELECT id, title, alias FROM {$P}revslider_sliders7 LIMIT 20") as $r) echo "v7#{$r->id} {$r->alias}; ";
echo "\n";
echo "  [code-snippets] snippets:\n";
foreach ((array) $wpdb->get_results("SELECT id, name, active, scope, priority, LENGTH(code) len, code FROM {$P}snippets ORDER BY id") as $r) { echo "      #{$r->id} \"{$r->name}\" active={$r->active} scope={$r->scope} prio={$r->priority} ({$r->len} b)\n"; if ($r->active) echo "        CODE>>> " . str_replace("\n", "\n        | ", af_pu_mask('', $r->code)) . "\n"; }
echo "  [header-footer-code-manager] snippets:\n";
foreach ((array) $wpdb->get_results("SHOW TABLES LIKE '{$P}hfcm_scripts'") as $t) foreach ((array) $wpdb->get_results("SELECT script_id, name, location, display_on, device_type, status, LENGTH(snippet) len, snippet FROM {$P}hfcm_scripts ORDER BY script_id") as $r) { echo "      #{$r->script_id} \"{$r->name}\" {$r->status} at {$r->location} on {$r->display_on} device {$r->device_type} ({$r->len} b)\n"; if ($r->status === 'active') echo "        SNIPPET>>> " . str_replace("\n", "\n        | ", af_pu_mask('', $r->snippet)) . "\n"; }
echo "  [custom-payment-gateways-woocommerce] gateways:\n";
if (function_exists('WC')) foreach (WC()->payment_gateways()->payment_gateways() as $id => $g) echo "      $id \"" . $g->get_title() . "\" enabled=" . $g->enabled . ' class=' . get_class($g) . ' owner=' . af_pu_owner_file((new ReflectionClass($g))->getFileName()) . "\n";
echo "  [woocommerce-currency-switcher] "; $opt_prefix('woocs', 70);
echo "  [transposh] translations stored: " . $cnt("SELECT COUNT(*) FROM {$P}translations") . "\n"; $opt_prefix('transposh', 10); $opt_prefix('cfxlsft', 5);
echo "  [dynamic-visibility-for-elementor]\n"; $opt_prefix('dce_', 10);
echo "  [deployer-for-git]\n"; $opt_prefix('wpdfg', 10); $opt_prefix('deployer', 10);
echo "  [classic-editor]\n"; $opt_prefix('classic-editor', 5);
echo "  [google-listings-and-ads] merchant/ads connected: "; $opt_prefix('gla_', 25);
echo "  [hostinger]\n"; $opt_prefix('hostinger', 25);
echo "  [premium-addons] / [essential-addons] / [UAE] settings:\n"; $opt_prefix('pa_save_settings', 2); $opt_prefix('eael_save_settings', 2); $opt_prefix('uae_', 5); $opt_prefix('_hfe', 5);
echo "  sgpopup / popup maker / firebox data present: sgpopup " . $cnt("SELECT COUNT(*) FROM {$P}options WHERE option_name LIKE 'sgpopup%'") . ", pum " . $cnt("SELECT COUNT(*) FROM {$P}options WHERE option_name LIKE 'pum_%'") . "\n";
echo "\n=== END PLUGIN USAGE ===\n";
