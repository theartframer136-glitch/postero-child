<?php
/**
 * Multi-channel marketplace integration  —  requirements §15
 *
 * Two halves, split by what each channel actually needs:
 *
 *  1. Feeds. Google Merchant Center, Meta (Facebook/Instagram Shops) and
 *     Pinterest all ingest a product feed URL you paste into their dashboard.
 *     No API credentials, no app review — so these work today.
 *
 *  2. Listing APIs. Amazon, Etsy and eBay need per-seller OAuth apps. Until
 *     those credentials exist we generate their bulk-upload files instead,
 *     which is how most sellers list in volume anyway.
 *
 * Inventory moves both ways: an authenticated endpoint channels push stock and
 * orders into, and outbound notifications when stock changes here.
 */
if (!defined('ABSPATH')) exit;

function af_mk_channels() {
    return array(
        'google'    => array('label' => 'Google Merchant Center', 'kind' => 'feed'),
        'meta'      => array('label' => 'Meta (Facebook & Instagram)', 'kind' => 'feed'),
        'pinterest' => array('label' => 'Pinterest', 'kind' => 'feed'),
        'amazon'    => array('label' => 'Amazon', 'kind' => 'csv'),
        'etsy'      => array('label' => 'Etsy', 'kind' => 'csv'),
        'ebay'      => array('label' => 'eBay', 'kind' => 'csv'),
    );
}

/** Shared secret in the feed URL, so the catalogue is not trivially scrapable. */
function af_mk_key() {
    $k = get_option('af_mk_key');
    if (!$k) { $k = wp_generate_password(24, false, false); update_option('af_mk_key', $k, false); }
    return $k;
}

function af_mk_feed_url($channel) {
    return add_query_arg(array('af_feed' => $channel, 'key' => af_mk_key()), home_url('/'));
}

function af_mk_table() { global $wpdb; return $wpdb->prefix . 'af_mk_sync_log'; }

add_action('after_setup_theme', function() {
    if (get_option('af_mk_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = af_mk_table();
    dbDelta("CREATE TABLE {$t} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        channel VARCHAR(30) NOT NULL,
        direction VARCHAR(10) NOT NULL,
        action VARCHAR(40) NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        detail TEXT NULL,
        ok TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY channel_created (channel, created_at)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('af_mk_db_ver', '1');
}, 7);

function af_mk_log($channel, $direction, $action, $product_id = 0, $detail = '', $ok = true) {
    global $wpdb;
    $wpdb->insert(af_mk_table(), array(
        'channel' => $channel, 'direction' => $direction, 'action' => $action,
        'product_id' => (int) $product_id, 'detail' => is_scalar($detail) ? (string) $detail : wp_json_encode($detail),
        'ok' => $ok ? 1 : 0, 'created_at' => current_time('mysql'),
    ));
}

// ── per-product channel settings ─────────────────────────────────────

/** The identifier this product is listed under on a channel. */
function af_mk_sku($product, $channel) {
    $custom = get_post_meta($product->get_id(), '_af_mk_sku_' . $channel, true);
    if ($custom !== '') return $custom;
    $sku = $product->get_sku();
    if ($sku !== '') return $sku;
    $code = get_post_meta($product->get_id(), '_taf_art_code', true);
    return $code !== '' ? $code : 'TAF-' . $product->get_id();
}

function af_mk_excluded($product_id, $channel) {
    $ex = (array) get_post_meta($product_id, '_af_mk_exclude', true);
    return in_array($channel, $ex, true);
}

/** Channel-specific title/description overrides (§15). */
function af_mk_field($product, $channel, $field, $default) {
    $v = get_post_meta($product->get_id(), '_af_mk_' . $field . '_' . $channel, true);
    return $v !== '' ? $v : $default;
}

add_action('add_meta_boxes', function() {
    add_meta_box('af_mk_box', 'Marketplace channels', function($post) {
        $product = wc_get_product($post->ID);
        if (!$product) return;
        wp_nonce_field('af_mk_save', 'af_mk_nonce');
        $ex = (array) get_post_meta($post->ID, '_af_mk_exclude', true);
        echo '<p style="margin-top:0;color:#646970;">Untick a channel to hold this piece back from it. Leave a SKU blank to use ' . esc_html($product->get_sku() ?: 'the art code') . '.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Channel</th><th>List</th><th>Channel SKU / ID</th><th>Title override</th></tr></thead><tbody>';
        foreach (af_mk_channels() as $key => $meta) {
            $sku   = get_post_meta($post->ID, '_af_mk_sku_' . $key, true);
            $title = get_post_meta($post->ID, '_af_mk_title_' . $key, true);
            echo '<tr><td><strong>' . esc_html($meta['label']) . '</strong></td>';
            echo '<td><input type="checkbox" name="af_mk_on[]" value="' . esc_attr($key) . '" ' . (in_array($key, $ex, true) ? '' : 'checked') . '></td>';
            echo '<td><input type="text" style="width:100%" name="af_mk_sku[' . esc_attr($key) . ']" value="' . esc_attr($sku) . '" placeholder="' . esc_attr(af_mk_sku($product, $key)) . '"></td>';
            echo '<td><input type="text" style="width:100%" name="af_mk_title[' . esc_attr($key) . ']" value="' . esc_attr($title) . '" placeholder="' . esc_attr(wp_trim_words($product->get_name(), 8, '')) . '"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p><label><strong>Brand</strong><br><input type="text" style="width:100%" name="af_mk_brand" value="' . esc_attr(get_post_meta($post->ID, '_af_mk_brand', true)) . '" placeholder="' . esc_attr(get_bloginfo('name')) . '"></label></p>';
        echo '<p><label><strong>GTIN / UPC</strong> <span style="color:#646970;">(blank is fine — handmade art rarely has one)</span><br><input type="text" style="width:100%" name="af_mk_gtin" value="' . esc_attr(get_post_meta($post->ID, '_af_mk_gtin', true)) . '"></label></p>';
        echo '<p><label><strong>Google product category</strong><br><input type="text" style="width:100%" name="af_mk_gcat" value="' . esc_attr(get_post_meta($post->ID, '_af_mk_gcat', true)) . '" placeholder="500044 — Home &amp; Garden &gt; Decor &gt; Artwork"></label></p>';
    }, 'product', 'normal', 'default');
});

add_action('save_post_product', function($post_id) {
    if (!isset($_POST['af_mk_nonce']) || !wp_verify_nonce(wp_unslash($_POST['af_mk_nonce']), 'af_mk_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;

    $on  = isset($_POST['af_mk_on']) ? array_map('sanitize_key', (array) wp_unslash($_POST['af_mk_on'])) : array();
    $all = array_keys(af_mk_channels());
    update_post_meta($post_id, '_af_mk_exclude', array_values(array_diff($all, $on)));

    foreach ($all as $key) {
        foreach (array('sku', 'title') as $f) {
            $val = isset($_POST['af_mk_' . $f][$key]) ? sanitize_text_field(wp_unslash($_POST['af_mk_' . $f][$key])) : '';
            if ($val === '') delete_post_meta($post_id, '_af_mk_' . $f . '_' . $key);
            else update_post_meta($post_id, '_af_mk_' . $f . '_' . $key, $val);
        }
    }
    foreach (array('brand' => 'af_mk_brand', 'gtin' => 'af_mk_gtin', 'gcat' => 'af_mk_gcat') as $meta => $field) {
        $val = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
        if ($val === '') delete_post_meta($post_id, '_af_mk_' . $meta);
        else update_post_meta($post_id, '_af_mk_' . $meta, $val);
    }
});

// ── what goes in a feed ──────────────────────────────────────────────

function af_mk_products($channel, $limit = -1) {
    $ids = get_posts(array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ));
    $out = array();
    foreach ($ids as $id) {
        if (af_mk_excluded($id, $channel)) continue;
        $p = wc_get_product($id);
        if (!$p || !$p->is_purchasable()) continue;
        if ($p->get_catalog_visibility() === 'hidden') continue;
        $out[] = $p;
    }
    return $out;
}

/** Everything a feed row needs, resolved once. */
function af_mk_row($product, $channel) {
    $price = (float) wc_get_price_to_display($product);
    $reg   = (float) $product->get_regular_price();
    $imgs  = array();
    if ($product->get_image_id()) $imgs[] = wp_get_attachment_image_url($product->get_image_id(), 'full');
    foreach (array_slice($product->get_gallery_image_ids(), 0, 10) as $gid) {
        $u = wp_get_attachment_image_url($gid, 'full');
        if ($u) $imgs[] = $u;
    }
    $cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));

    return array(
        'id'          => af_mk_xml_text(af_mk_sku($product, $channel)),
        'title'       => af_mk_xml_text(wp_strip_all_tags(af_mk_field($product, $channel, 'title', $product->get_name()))),
        'description' => af_mk_xml_text(wp_trim_words(wp_strip_all_tags($product->get_description() ?: $product->get_short_description() ?: $product->get_name()), 150, '')),
        'link'        => get_permalink($product->get_id()),
        'images'      => $imgs,
        'price'       => $price,
        'regular'     => $reg > 0 ? $reg : $price,
        'currency'    => get_woocommerce_currency(),
        'stock'       => $product->is_in_stock(),
        'brand'       => af_mk_xml_text(get_post_meta($product->get_id(), '_af_mk_brand', true) ?: get_bloginfo('name')),
        'gtin'        => af_mk_xml_text(get_post_meta($product->get_id(), '_af_mk_gtin', true)),
        'gcat'        => af_mk_xml_text(get_post_meta($product->get_id(), '_af_mk_gcat', true)),
        'type'        => af_mk_xml_text($cats ? implode(' > ', $cats) : 'Wall Art'),
        'sku'         => af_mk_xml_text($product->get_sku()),
        'qty'         => $product->get_stock_quantity(),
    );
}

/**
 * XML 1.0 forbids most control characters outright, and one of them anywhere in
 * a product description makes the whole feed unparseable — so strip them before
 * escaping rather than serving a document no channel can read.
 */
function af_mk_xml_text($s) {
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $s);
}

/** Nothing may precede the XML declaration — discard anything already buffered. */
function af_mk_clean_output() {
    while (ob_get_level() > 0) { @ob_end_clean(); }
}

/** Google / Meta / Pinterest all read RSS 2.0 with the g: namespace. */
function af_mk_render_feed($channel) {
    $products = af_mk_products($channel);
    af_mk_clean_output();
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
    echo '<title>' . esc_html(get_bloginfo('name')) . "</title>\n";
    echo '<link>' . esc_url(home_url('/')) . "</link>\n";
    echo '<description>' . esc_html(get_bloginfo('description')) . "</description>\n";

    foreach ($products as $product) {
        $r = af_mk_row($product, $channel);
        if (!$r['images']) continue;                      // every channel rejects an item with no image
        // Meta words availability differently from Google/Pinterest
        $avail = $r['stock']
            ? ($channel === 'meta' ? 'in stock' : 'in_stock')
            : ($channel === 'meta' ? 'out of stock' : 'out_of_stock');

        echo "<item>\n";
        echo '  <g:id>' . esc_html($r['id']) . "</g:id>\n";
        echo '  <g:title>' . esc_html($r['title']) . "</g:title>\n";
        echo '  <g:description>' . esc_html($r['description']) . "</g:description>\n";
        echo '  <g:link>' . esc_url($r['link']) . "</g:link>\n";
        echo '  <g:image_link>' . esc_url($r['images'][0]) . "</g:image_link>\n";
        foreach (array_slice($r['images'], 1, 10) as $extra) {
            echo '  <g:additional_image_link>' . esc_url($extra) . "</g:additional_image_link>\n";
        }
        echo '  <g:availability>' . esc_html($avail) . "</g:availability>\n";
        echo '  <g:condition>new</g:condition>' . "\n";
        echo '  <g:price>' . esc_html(number_format($r['regular'], 2, '.', '') . ' ' . $r['currency']) . "</g:price>\n";
        if ($r['price'] < $r['regular']) {
            echo '  <g:sale_price>' . esc_html(number_format($r['price'], 2, '.', '') . ' ' . $r['currency']) . "</g:sale_price>\n";
        }
        echo '  <g:brand>' . esc_html($r['brand']) . "</g:brand>\n";
        if ($r['gtin']) {
            echo '  <g:gtin>' . esc_html($r['gtin']) . "</g:gtin>\n";
        } else {
            // original art has no barcode; saying so stops Google rejecting the item
            echo '  <g:identifier_exists>no</g:identifier_exists>' . "\n";
        }
        if ($r['sku']) echo '  <g:mpn>' . esc_html($r['sku']) . "</g:mpn>\n";
        if ($r['gcat']) echo '  <g:google_product_category>' . esc_html($r['gcat']) . "</g:google_product_category>\n";
        echo '  <g:product_type>' . esc_html($r['type']) . "</g:product_type>\n";
        echo "</item>\n";
    }
    echo '</channel></rss>';
}

/** Bulk-upload columns for the channels whose APIs need seller credentials. */
function af_mk_csv_columns($channel) {
    switch ($channel) {
        case 'etsy':
            return array('title','description','price','quantity','tags','materials','image1','image2','image3','sku','who_made','when_made','is_supply');
        case 'ebay':
            return array('Action','Category','Title','Description','PicURL','Quantity','Format','StartPrice','Duration','Location','CustomLabel');
        default: // amazon flat file essentials
            return array('sku','product-id','product-id-type','item-name','item-description','listing-price','quantity','main-image-url','brand-name','manufacturer','condition-type');
    }
}

function af_mk_csv_row($r, $channel) {
    $img = isset($r['images'][0]) ? $r['images'][0] : '';
    switch ($channel) {
        case 'etsy':
            return array(
                $r['title'], $r['description'], number_format($r['price'], 2, '.', ''),
                $r['qty'] !== null ? $r['qty'] : 10,
                'wall art,canvas,home decor', 'canvas,ink',
                $img, isset($r['images'][1]) ? $r['images'][1] : '', isset($r['images'][2]) ? $r['images'][2] : '',
                $r['id'], 'i_did', 'made_to_order', 'false',
            );
        case 'ebay':
            return array(
                'Add', '', $r['title'], $r['description'], $img,
                $r['qty'] !== null ? $r['qty'] : 10,
                'FixedPriceItem', number_format($r['price'], 2, '.', ''), 'GTC', '', $r['id'],
            );
        default:
            return array(
                $r['id'], $r['gtin'], $r['gtin'] ? 'UPC' : '', $r['title'], $r['description'],
                number_format($r['price'], 2, '.', ''),
                $r['qty'] !== null ? $r['qty'] : 10,
                $img, $r['brand'], $r['brand'], 'New',
            );
    }
}

function af_mk_render_csv($channel) {
    $products = af_mk_products($channel);
    af_mk_clean_output();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $channel . '-listings-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, af_mk_csv_columns($channel));
    foreach ($products as $product) {
        $r = af_mk_row($product, $channel);
        if (!$r['images']) continue;
        fputcsv($out, af_mk_csv_row($r, $channel));
    }
    fclose($out);
}

// ── serving the feeds ────────────────────────────────────────────────
add_action('template_redirect', function() {
    if (empty($_GET['af_feed'])) return;
    $channel  = sanitize_key(wp_unslash($_GET['af_feed']));
    $channels = af_mk_channels();
    if (!isset($channels[$channel])) return;

    $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
    if (!hash_equals(af_mk_key(), $key) && !current_user_can('manage_woocommerce')) {
        wp_die('Invalid feed key.', 'Forbidden', array('response' => 403));
    }

    nocache_headers();
    if ($channels[$channel]['kind'] === 'csv') af_mk_render_csv($channel);
    else af_mk_render_feed($channel);
    af_mk_log($channel, 'out', 'feed_served');
    exit;
});

// ── inventory sync: inbound ──────────────────────────────────────────
add_action('rest_api_init', function() {
    register_rest_route('af-marketplace/v1', '/inventory', array(
        'methods'  => 'POST',
        'callback' => 'af_mk_rest_inventory',
        'permission_callback' => 'af_mk_rest_auth',
    ));
    register_rest_route('af-marketplace/v1', '/status', array(
        'methods'  => 'GET',
        'callback' => 'af_mk_rest_status',
        'permission_callback' => 'af_mk_rest_auth',
    ));
});

function af_mk_rest_auth($request) {
    $key = $request->get_header('X-AF-Key');
    if (!$key) $key = $request->get_param('key');
    return $key && hash_equals(af_mk_key(), (string) $key);
}

/** Find a product by any identifier a channel might send back. */
function af_mk_find_product($identifier) {
    $identifier = trim((string) $identifier);
    if ($identifier === '') return null;
    $id = wc_get_product_id_by_sku($identifier);
    if ($id) return wc_get_product($id);
    global $wpdb;
    // a channel-specific SKU, or the art code
    $found = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_value = %s AND (meta_key LIKE '_af_mk_sku_%%' OR meta_key = '_taf_art_code') LIMIT 1",
        $identifier
    ));
    if ($found) return wc_get_product((int) $found);
    if (ctype_digit($identifier)) return wc_get_product((int) $identifier);
    return null;
}

function af_mk_rest_inventory($request) {
    $body    = $request->get_json_params();
    $channel = isset($body['channel']) ? sanitize_key($body['channel']) : 'unknown';
    $updates = isset($body['updates']) && is_array($body['updates']) ? $body['updates'] : array();
    if (!$updates) return new WP_Error('af_mk_empty', 'No updates supplied.', array('status' => 400));

    $done = array(); $missed = array();
    foreach ($updates as $u) {
        $ident   = isset($u['sku']) ? $u['sku'] : (isset($u['id']) ? $u['id'] : '');
        $product = af_mk_find_product($ident);
        if (!$product) { $missed[] = $ident; af_mk_log($channel, 'in', 'stock_unmatched', 0, $ident, false); continue; }

        if (isset($u['stock'])) {
            $qty = max(0, (int) $u['stock']);
            // guard against a channel echoing stale stock back at us
            $product->set_manage_stock(true);
            $product->set_stock_quantity($qty);
            $product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
        } elseif (isset($u['sold'])) {
            $sold = max(0, (int) $u['sold']);
            $have = (int) $product->get_stock_quantity();
            $product->set_manage_stock(true);
            $product->set_stock_quantity(max(0, $have - $sold));
            if ($have - $sold <= 0) $product->set_stock_status('outofstock');
        }
        // remember the channel's own id so the next update matches first time
        if (!empty($u['channel_sku']) && $channel !== 'unknown') {
            update_post_meta($product->get_id(), '_af_mk_sku_' . $channel, sanitize_text_field($u['channel_sku']));
        }
        remove_action('woocommerce_product_set_stock', 'af_mk_stock_changed');   // do not echo back
        $product->save();
        add_action('woocommerce_product_set_stock', 'af_mk_stock_changed');
        $done[] = $product->get_id();
        af_mk_log($channel, 'in', 'stock_set', $product->get_id(), wp_json_encode($u));
    }
    return array('updated' => count($done), 'unmatched' => $missed);
}

function af_mk_rest_status($request) {
    $out = array();
    foreach (af_mk_channels() as $key => $meta) {
        $out[$key] = array(
            'label'    => $meta['label'],
            'kind'     => $meta['kind'],
            'products' => count(af_mk_products($key)),
            'url'      => af_mk_feed_url($key),
        );
    }
    return $out;
}

// ── inventory sync: outbound ─────────────────────────────────────────
function af_mk_endpoints() {
    return array_filter(array_map('trim', explode("\n", (string) get_option('af_mk_push_urls', ''))));
}

function af_mk_stock_changed($product) {
    $urls = af_mk_endpoints();
    if (!$urls) return;
    if (is_numeric($product)) $product = wc_get_product($product);
    if (!$product) return;

    $payload = wp_json_encode(array(
        'event'   => 'stock.changed',
        'sku'     => $product->get_sku(),
        'id'      => $product->get_id(),
        'stock'   => $product->get_stock_quantity(),
        'status'  => $product->get_stock_status(),
        'channel_skus' => array_reduce(array_keys(af_mk_channels()), function($c, $k) use ($product) {
            $v = get_post_meta($product->get_id(), '_af_mk_sku_' . $k, true);
            if ($v) $c[$k] = $v;
            return $c;
        }, array()),
        'at'      => gmdate('c'),
    ));
    foreach ($urls as $url) {
        $res = wp_remote_post($url, array(
            'timeout'  => 8,
            'blocking' => false,
            'headers'  => array(
                'Content-Type' => 'application/json',
                // lets the receiver prove the call came from us
                'X-AF-Signature' => hash_hmac('sha256', $payload, af_mk_key()),
            ),
            'body' => $payload,
        ));
        af_mk_log('outbound', 'out', 'stock_push', $product->get_id(), $url, !is_wp_error($res));
    }
}
add_action('woocommerce_product_set_stock', 'af_mk_stock_changed');
add_action('woocommerce_variation_set_stock', 'af_mk_stock_changed');

// ── admin screen ─────────────────────────────────────────────────────
add_action('admin_menu', function() {
    add_submenu_page('woocommerce', 'Marketplace', 'Marketplace', 'manage_woocommerce', 'af-marketplace', 'af_mk_admin_page');
});

function af_mk_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;

    if (isset($_POST['af_mk_settings_nonce']) && wp_verify_nonce(wp_unslash($_POST['af_mk_settings_nonce']), 'af_mk_settings')) {
        update_option('af_mk_push_urls', sanitize_textarea_field(wp_unslash($_POST['af_mk_push_urls'])));
        if (!empty($_POST['af_mk_regen'])) { update_option('af_mk_key', wp_generate_password(24, false, false), false); }
        echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }

    global $wpdb;
    $log = $wpdb->get_results("SELECT * FROM " . af_mk_table() . " ORDER BY id DESC LIMIT 40");

    echo '<div class="wrap"><h1>Marketplace</h1>';
    echo '<p>Feeds are live now — paste the URL into each channel and it pulls the catalogue on its own schedule. '
       . 'Amazon, Etsy and eBay need seller API credentials before we can list through their APIs, so those export the bulk-upload file instead.</p>';

    echo '<h2>Channels</h2><table class="widefat striped"><thead><tr><th>Channel</th><th>Products</th><th>Feed / export</th><th>Issues</th></tr></thead><tbody>';
    foreach (af_mk_channels() as $key => $meta) {
        $items   = af_mk_products($key);
        $noimage = 0;
        foreach ($items as $p) { if (!$p->get_image_id()) $noimage++; }
        echo '<tr><td><strong>' . esc_html($meta['label']) . '</strong><br><small>' . ($meta['kind'] === 'feed' ? 'Automatic feed' : 'Bulk upload file') . '</small></td>';
        echo '<td>' . count($items) . '</td>';
        echo '<td><input type="text" readonly style="width:100%;font-family:monospace;font-size:11px;" value="' . esc_attr(af_mk_feed_url($key)) . '" onclick="this.select()"> '
           . '<a class="button button-small" style="margin-top:5px;" target="_blank" rel="noopener" href="' . esc_url(af_mk_feed_url($key)) . '">Open</a></td>';
        echo '<td>' . ($noimage ? '<span style="color:#b32d2e;">' . $noimage . ' with no image (skipped)</span>' : '<span style="color:#1e8b56;">none</span>') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<h2>Two-way inventory sync</h2>';
    echo '<p>Channels push stock in here:</p>';
    echo '<p><code>POST ' . esc_html(rest_url('af-marketplace/v1/inventory')) . '</code><br>'
       . '<small>Header <code>X-AF-Key: ' . esc_html(af_mk_key()) . '</code> — body '
       . '<code>{"channel":"etsy","updates":[{"sku":"TAF-123","stock":4}]}</code>. '
       . 'Send <code>"sold": 1</code> instead of <code>"stock"</code> to decrement.</small></p>';

    echo '<form method="post">';
    wp_nonce_field('af_mk_settings', 'af_mk_settings_nonce');
    echo '<h3>Push stock changes out to</h3>';
    echo '<p><textarea name="af_mk_push_urls" rows="3" style="width:100%;max-width:640px;" placeholder="https://your-middleware.example/hooks/stock">'
       . esc_textarea(get_option('af_mk_push_urls', '')) . '</textarea><br>'
       . '<small>One URL per line. Each gets a signed JSON POST whenever stock changes here. Verify with the <code>X-AF-Signature</code> header (HMAC-SHA256 of the body, keyed with the feed key).</small></p>';
    echo '<p><label><input type="checkbox" name="af_mk_regen" value="1"> Generate a new feed key <small>(invalidates the URLs above — re-paste them into each channel)</small></label></p>';
    submit_button('Save');
    echo '</form>';

    echo '<h2>Recent sync activity</h2><table class="widefat striped"><thead><tr><th>When</th><th>Channel</th><th>Direction</th><th>Action</th><th>Product</th><th>Detail</th></tr></thead><tbody>';
    if (!$log) echo '<tr><td colspan="6">Nothing yet.</td></tr>';
    foreach ($log as $l) {
        echo '<tr' . ($l->ok ? '' : ' style="background:#fcf0f1;"') . '>';
        echo '<td>' . esc_html($l->created_at) . '</td><td>' . esc_html($l->channel) . '</td>';
        echo '<td>' . esc_html($l->direction === 'in' ? '← in' : '→ out') . '</td>';
        echo '<td>' . esc_html($l->action) . '</td>';
        echo '<td>' . ($l->product_id ? '<a href="' . esc_url(get_edit_post_link($l->product_id)) . '">#' . (int) $l->product_id . '</a>' : '—') . '</td>';
        echo '<td><small>' . esc_html(mb_strimwidth((string) $l->detail, 0, 90, '…')) . '</small></td></tr>';
    }
    echo '</tbody></table></div>';
}
