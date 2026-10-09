<?php
/**
 * Digital downloads from Cloudflare R2, matched by art code.
 *
 * Owner, 7 Oct 2026: the full-resolution files are uploaded as a folder to a
 * private Cloudflare R2 bucket, each file named after the piece's art code
 * (e.g. "RK-010008-3040.tif", or "RK-010008-3040 Sleeping Krishna.tif"); the
 * website is not moved to Cloudflare. A buyer who paid for a digital download
 * gets that file straight from Cloudflare through a link that works for a few
 * minutes only, after WooCommerce has checked the order, the download limit
 * and the expiry and has counted the download, exactly as before. The server
 * never carries the file itself.
 *
 * - Where the files are: option af_r2_config (account, bucket, access key,
 *   secret), written by .github/workflows/r2-setup.yml from GitHub secrets.
 *   Without it this file does nothing and downloads work as they do today.
 * - Which file a product gets: the bucket is listed (S3 ListObjectsV2) into
 *   option af_r2_index, art code => object, twice a day, and on demand from
 *   Products > Download masters. A file matches the art code its name is, or
 *   starts with followed by a space, "-", "_" or ".". An exact name wins.
 * - Delivery: woocommerce_download_product_filepath hands WooCommerce a
 *   signed R2 link (AWS Signature V4 query string, region "auto"), saved as
 *   "<product title> <art code>.<ext>", and woocommerce_file_download_method
 *   makes it a redirect. WooCommerce (11.1.2, checked 9 Oct) then runs its
 *   own checks, saves the download, counts and logs it, and only then
 *   redirects. (woocommerce_download_product, the hook first used here, fires
 *   BEFORE the count: leaving from it would never use up a download limit.)
 *   A product with no master in the bucket gets the file WooCommerce has
 *   today, unchanged. Only the product that carries the art code itself (its
 *   SKU is the code) gets the master: a product sharing the code under a
 *   lettered SKU (the gift card #14600 shares SL-150004-5030) keeps its own.
 * - Admin: Products > Download masters lists every art code with or without a
 *   master, refreshes the index and opens any master (admins only).
 */
defined('ABSPATH') || exit;

if (!function_exists('af_r2_config')) {

    /** The bucket settings, or null when not set up. */
    function af_r2_config() {
        $c = get_option('af_r2_config');
        if (!is_array($c)) return null;
        foreach (array('account', 'bucket', 'key', 'secret') as $k) {
            if (empty($c[$k]) || !is_string($c[$k])) return null;
        }
        return $c;
    }

    function af_r2_host($c) {
        return $c['account'] . '.r2.cloudflarestorage.com';
    }

    /** RFC 3986 encoding as S3 wants it; '/' kept in object paths. */
    function af_r2_enc($s, $keep_slash = false) {
        $e = rawurlencode($s);
        return $keep_slash ? str_replace('%2F', '/', $e) : $e;
    }

    function af_r2_signing_key($secret, $date, $region, $service) {
        $k = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $k = hash_hmac('sha256', $region, $k, true);
        $k = hash_hmac('sha256', $service, $k, true);
        return hash_hmac('sha256', 'aws4_request', $k, true);
    }

    /**
     * AWS Signature V4, query-string form (a presigned URL). $path is already
     * encoded. $extra are further query parameters (unencoded).
     */
    function af_r2_presign($host, $path, $key, $secret, $region, $expires, $extra = array(), $now = null) {
        $now   = $now === null ? time() : $now;
        $amz   = gmdate('Ymd\THis\Z', $now);
        $date  = gmdate('Ymd', $now);
        $scope = "$date/$region/s3/aws4_request";
        $q = array_merge($extra, array(
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "$key/$scope",
            'X-Amz-Date'          => $amz,
            'X-Amz-Expires'       => (string) (int) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ));
        ksort($q, SORT_STRING);
        $qs = array();
        foreach ($q as $k => $v) $qs[] = af_r2_enc($k) . '=' . af_r2_enc($v);
        $qs = implode('&', $qs);
        $canon = "GET\n$path\n$qs\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
        $sts   = "AWS4-HMAC-SHA256\n$amz\n$scope\n" . hash('sha256', $canon);
        $sig   = hash_hmac('sha256', $sts, af_r2_signing_key($secret, $date, $region, 's3'));
        return "https://$host$path?$qs&X-Amz-Signature=$sig";
    }

    /** A signed link to one object, valid $ttl seconds. */
    function af_r2_object_url($object, $ttl = 300, $filename = '') {
        $c = af_r2_config();
        if (!$c) return '';
        $extra = array();
        if ($filename !== '') {
            $safe = preg_replace('/[^\w .()\-]+/u', '', $filename);
            $extra['response-content-disposition'] = 'attachment; filename="' . $safe . '"';
        }
        $path = '/' . af_r2_enc($c['bucket']) . '/' . af_r2_enc($object, true);
        return af_r2_presign(af_r2_host($c), $path, $c['key'], $c['secret'], 'auto', $ttl, $extra);
    }

    /** Every object in the bucket: key => size. Null on failure. */
    function af_r2_list_objects() {
        $c = af_r2_config();
        if (!$c) return null;
        $out = array();
        $token = '';
        for ($page = 0; $page < 200; $page++) {
            $extra = array('list-type' => '2', 'max-keys' => '1000');
            if ($token !== '') $extra['continuation-token'] = $token;
            $url = af_r2_presign(af_r2_host($c), '/' . af_r2_enc($c['bucket']), $c['key'], $c['secret'], 'auto', 120, $extra);
            $r = wp_remote_get($url, array('timeout' => 30));
            if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
                update_option('af_r2_last_error', is_wp_error($r) ? $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r) . ' ' . substr(wp_strip_all_tags(wp_remote_retrieve_body($r)), 0, 200), false);
                return null;
            }
            $xml = @simplexml_load_string(wp_remote_retrieve_body($r));
            if (!$xml) { update_option('af_r2_last_error', 'unreadable bucket listing', false); return null; }
            foreach ($xml->Contents as $o) {
                $k = (string) $o->Key;
                if ($k !== '' && substr($k, -1) !== '/') $out[$k] = (int) $o->Size;
            }
            if ((string) $xml->IsTruncated !== 'true') break;
            $token = (string) $xml->NextContinuationToken;
            if ($token === '') break;
        }
        delete_option('af_r2_last_error');
        return $out;
    }

    /** Normalised form for matching: upper case, no spaces around. */
    function af_r2_norm_code($code) {
        return strtoupper(trim((string) $code));
    }

    /**
     * Rebuild art code => object. Exact file names win over "code + words";
     * among several candidates the largest file wins (the full-size master).
     */
    function af_r2_rebuild_index() {
        $objects = af_r2_list_objects();
        if ($objects === null) return false;
        global $wpdb;
        $codes = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_taf_art_code' AND meta_value <> ''");
        $want = array();
        foreach ($codes as $c) $want[af_r2_norm_code($c)] = true;
        $exact = array(); $prefix = array();
        foreach ($objects as $key => $size) {
            $base = pathinfo(basename($key), PATHINFO_FILENAME);
            $u = af_r2_norm_code($base);
            if (isset($want[$u])) {
                if (!isset($exact[$u]) || $size > $exact[$u]['size']) $exact[$u] = array('key' => $key, 'size' => $size);
                continue;
            }
            if (preg_match('/^(.+?)[ ._\-]/', $u . ' ', $m)) {
                // longest art code the name starts with
                $best = '';
                foreach (array_keys($want) as $code) {
                    if (strlen($code) > strlen($best) && strpos($u, $code) === 0 && preg_match('/^[ ._\-]/', substr($u, strlen($code)) . ' ')) $best = $code;
                }
                if ($best !== '' && (!isset($prefix[$best]) || $size > $prefix[$best]['size'])) $prefix[$best] = array('key' => $key, 'size' => $size);
            }
        }
        $index = $prefix;
        foreach ($exact as $code => $o) $index[$code] = $o;
        update_option('af_r2_index', array('built' => time(), 'objects' => count($objects), 'map' => $index), false);
        return $index;
    }

    /** The master for a product, or null. */
    function af_r2_master_for_product($pid) {
        $code = af_r2_norm_code(get_post_meta($pid, '_taf_art_code', true));
        if ($code === '') return null;
        $idx = get_option('af_r2_index');
        if (!is_array($idx) || empty($idx['map'][$code])) return null;
        return $idx['map'][$code] + array('code' => $code);
    }

    // Twice a day, refresh the index.
    add_action('af_r2_refresh_index', 'af_r2_rebuild_index');
    add_action('init', function () {
        if (af_r2_config() && !wp_next_scheduled('af_r2_refresh_index')) {
            wp_schedule_event(time() + 300, 'twicedaily', 'af_r2_refresh_index');
        }
    });

    /** The master a buyer of this product gets, or null (see the header). */
    function af_r2_master_for_buyer($product) {
        if (!af_r2_config() || !$product) return null;
        $pid = (int) $product->get_id();
        $m = af_r2_master_for_product($pid);
        if (!$m) return null;
        if (af_r2_norm_code($product->get_sku()) !== $m['code']) return null;
        return $m;
    }

    /** The file name the buyer's download is saved as. */
    function af_r2_download_name($pid, $m) {
        $ext   = pathinfo($m['key'], PATHINFO_EXTENSION);
        $title = wp_strip_all_tags(get_the_title((int) $pid));
        $title = trim(preg_replace('/\s+[–-]\s+.*$/u', '', $title)); // "Name Canvas Wall Art 3x4 Feet – ..." -> before the dash
        return trim($title . ' ' . $m['code']) . ($ext !== '' ? '.' . $ext : '');
    }

    // Delivery, step 1: the file WooCommerce will hand out is the signed R2
    // link. Its checks, the save, the count and the log all still follow.
    add_filter('woocommerce_download_product_filepath', function ($file_path, $email, $order, $product, $download) {
        $m = af_r2_master_for_buyer($product);
        if (!$m) return $file_path;
        $url = af_r2_object_url($m['key'], 300, af_r2_download_name($product->get_id(), $m));
        return $url !== '' ? $url : $file_path;
    }, 10, 5);

    // Delivery, step 2: a signed R2 link is always a redirect (never streamed
    // through this server, whatever the shop-wide download method is).
    add_filter('woocommerce_file_download_method', function ($method, $product_id, $file_path) {
        $c = af_r2_config();
        if ($c && strpos((string) $file_path, 'https://' . af_r2_host($c) . '/') === 0) return 'redirect';
        return $method;
    }, 10, 3);

    // Admin: Products > Download masters.
    add_action('admin_menu', function () {
        add_submenu_page('edit.php?post_type=product', 'Download masters', 'Download masters', 'manage_woocommerce', 'af-download-masters', 'af_r2_admin_page');
    });

    add_action('admin_post_af_r2_refresh', function () {
        if (!current_user_can('manage_woocommerce')) wp_die('Not allowed');
        check_admin_referer('af_r2_refresh');
        $ok = af_r2_rebuild_index();
        wp_safe_redirect(add_query_arg(array('page' => 'af-download-masters', 'refreshed' => $ok === false ? 'fail' : 'ok'), admin_url('edit.php?post_type=product')));
        exit;
    });

    add_action('admin_post_af_r2_open', function () {
        if (!current_user_can('manage_woocommerce')) wp_die('Not allowed');
        check_admin_referer('af_r2_open');
        $pid = isset($_GET['pid']) ? (int) $_GET['pid'] : 0;
        $m = af_r2_master_for_product($pid);
        if (!$m) wp_die('No master file for this product.');
        wp_redirect(af_r2_object_url($m['key'], 120));
        exit;
    });

    function af_r2_admin_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $c   = af_r2_config();
        $idx = get_option('af_r2_index');
        $map = is_array($idx) && isset($idx['map']) ? $idx['map'] : array();
        global $wpdb;
        $rows = $wpdb->get_results("SELECT p.ID, p.post_title, m.meta_value AS code FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_taf_art_code' AND m.meta_value <> '' WHERE p.post_type = 'product' AND p.post_status = 'publish' ORDER BY m.meta_value");
        $have = 0;
        foreach ($rows as $r) if (isset($map[af_r2_norm_code($r->code)])) $have++;
        echo '<div class="wrap"><h1>Download masters</h1>';
        if (isset($_GET['refreshed'])) {
            echo $_GET['refreshed'] === 'ok'
                ? '<div class="notice notice-success"><p>The list of files was read again from Cloudflare.</p></div>'
                : '<div class="notice notice-error"><p>Cloudflare could not be read: ' . esc_html((string) get_option('af_r2_last_error')) . '</p></div>';
        }
        if (!$c) {
            echo '<p><strong>Not connected yet.</strong> Until it is, buyers get the files WooCommerce has today.</p></div>';
            return;
        }
        echo '<p>Bucket <code>' . esc_html($c['bucket']) . '</code>. Name each file after the art code (e.g. <code>RK-010008-3040.tif</code>; words after a space, dash or underscore are allowed). ';
        echo 'Files read: ' . (int) ($idx['objects'] ?? 0) . ', last read ' . (!empty($idx['built']) ? esc_html(human_time_diff((int) $idx['built'])) . ' ago' : 'never') . '.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="af_r2_refresh">';
        wp_nonce_field('af_r2_refresh');
        submit_button('Read the files again now', 'secondary', 'submit', false);
        echo '</form>';
        printf('<p><strong>%d of %d</strong> published products with an art code have their master.</p>', $have, count($rows));
        echo '<table class="widefat striped"><thead><tr><th>Art code</th><th>Product</th><th>Master file</th><th>Size</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $m = $map[af_r2_norm_code($r->code)] ?? null;
            $open = $m ? wp_nonce_url(admin_url('admin-post.php?action=af_r2_open&pid=' . (int) $r->ID), 'af_r2_open') : '';
            printf('<tr><td><code>%s</code></td><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>',
                esc_html($r->code), esc_url(get_edit_post_link($r->ID)), esc_html(wp_trim_words($r->post_title, 8)),
                $m ? '<a href="' . esc_url($open) . '">' . esc_html($m['key']) . '</a>' : '<span style="color:#b32d2e">missing</span>',
                $m ? esc_html(size_format($m['size'], 1)) : '');
        }
        echo '</tbody></table></div>';
    }
}
