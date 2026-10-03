<?php
/**
 * Main pictures set from files in this theme, once per revision.
 *
 * Owner, 3 Oct, with the Personal Pic file CO-240006-0000.jpg (the stage
 * dancer in orange, the backdrop changed to the Nataraja between two lamp
 * towers): "update the code and picture too", for #33294 only. #33294 "Dance
 * Duet On Stage" was the last product on a temporary code (TMP-1166): its
 * picture was the same stage shot with a second dancer behind her. It takes
 * CO-240006-0000, shared with #28778, which keeps its own red-stage photo
 * (tools/artcode-corrections.csv, tools/artcode-primary.csv), and this file
 * becomes its main picture.
 *
 * The only copy of the file to hand was the owner's screenshot of it, so the
 * picture is 635 x 952 (the owner chose that over waiting for the original).
 * A better copy later only needs the file replaced here and the revision
 * raised: a product already showing that exact file is left alone.
 *
 * For each product it:
 *   - checks the product still carries one of the art codes named, so an id
 *     that comes to hold another piece is never touched
 *   - copies the file into the uploads as a new attachment of the product,
 *     with its sizes made and the product's name as its alt text
 *   - makes it the main picture, and takes the old main picture out of the
 *     gallery if it was listed there (the rest of the gallery is untouched)
 *   - keeps the old picture's id in _af_picture_before; nothing is deleted,
 *     the old picture stays in the media library
 *   - purges the product page and the shop
 *   - takes out of the gallery any picture whose file name begins with one
 *     of the 'drop' names, and keeps the gallery as it was in
 *     _af_gallery_before (the pictures stay in the media library)
 * and writes what it did to the option af_product_pictures.
 *
 * Revision 2, 3 Oct: #33294's gallery held four room scenes of its OLD
 * two-dancer photo ("Dance-Duet-On-Stage-Gold-Foiled-UV-Canvas-Art-3x4-Feet-
 * scene1-1.jpg" to "-scene4-1.jpg", read live by tools/verify-product-
 * pictures.mjs). Asked whether they should stay beside the new picture, the
 * owner chose "Remove them": they leave the gallery, so the page shows only
 * the new picture. Revision 1 set the main picture; this revision finds it
 * already there and only takes the scenes out.
 */
if (!defined('ABSPATH')) exit;

define('AF_PRODUCT_PICTURES_REV', '2');

/**
 * id => array(
 *   'codes' => the art codes it may carry (the code it has before this
 *              deploy's corrections pass, and the one it has after),
 *   'file'  => the picture, in assets/product-pictures/
 *   'drop'  => file-name beginnings of gallery pictures to take out (optional)
 * )
 */
function af_product_pictures() {
    return array(
        33294 => array(
            'codes' => array('TMP-1166', 'CO-240006-0000'),
            'file'  => 'co-240006-0000.jpg',
            'drop'  => array('Dance-Duet-On-Stage-Gold-Foiled-UV-Canvas-Art-3x4-Feet-scene'),
        ),
    );
}

/** An art code without spaces or dash style, upper case. */
function af_product_pictures_key($code) {
    return strtoupper(preg_replace('/[\s\x{2010}-\x{2015}\x{2212}-]+/u', '', (string) $code));
}

function af_product_pictures_dir() {
    return get_stylesheet_directory() . '/assets/product-pictures/';
}

/**
 * One product: make $p['file'] its main picture, and take the 'drop' pictures
 * out of its gallery. Returns one line for the log.
 */
function af_product_picture_apply($id, array $p) {
    $file = $p['file'];
    $drop = isset($p['drop']) ? (array) $p['drop'] : array();
    $product = wc_get_product($id);
    if (!$product) {
        return $id . ' not found';
    }
    $has = (string) get_post_meta($id, '_taf_art_code', true);
    $ok = false;
    foreach ($p['codes'] as $c) {
        if (af_product_pictures_key($c) === af_product_pictures_key($has)) { $ok = true; }
    }
    if (!$ok) {
        return $id . ' left alone: art code is now "' . substr($has, 0, 30) . '"';
    }
    $src = af_product_pictures_dir() . $file;
    $data = is_readable($src) ? file_get_contents($src) : false;
    if ($data === false || $data === '') {
        return $id . ' left alone: ' . $file . ' is not in the theme';
    }
    $source = $file . ' ' . md5($data);
    $old = (int) get_post_thumbnail_id($id);
    $main = $old;
    $did = array();

    if ($old && get_post_meta($old, '_af_picture_source', true) === $source) {
        $did[] = 'already shows ' . $file;
    } else {
        $up = wp_upload_bits($file, null, $data);
        if (!empty($up['error'])) {
            return $id . ' upload failed: ' . $up['error'];
        }
        $type = wp_check_filetype($up['file']);
        $name = html_entity_decode(wp_strip_all_tags($product->get_name()), ENT_QUOTES, 'UTF-8');
        $att = wp_insert_attachment(array(
            'post_mime_type' => $type['type'] ? $type['type'] : 'image/jpeg',
            'post_title'     => $name,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ), $up['file'], $id, true);
        if (is_wp_error($att) || !$att) {
            return $id . ' attachment failed: ' . (is_wp_error($att) ? $att->get_error_message() : 'no id');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $meta = wp_generate_attachment_metadata($att, $up['file']);
        wp_update_attachment_metadata($att, $meta);
        update_post_meta($att, '_wp_attachment_image_alt', $name);
        update_post_meta($att, '_af_picture_source', $source);
        if ($old && get_post_meta($id, '_af_picture_before', true) === '') {
            update_post_meta($id, '_af_picture_before', $old);
        }
        $main = (int) $att;
        $did[] = sprintf('main picture %d -> %d (%s, %dx%d)', $old, $main, $file,
            isset($meta['width']) ? (int) $meta['width'] : 0, isset($meta['height']) ? (int) $meta['height'] : 0);
    }

    // The gallery: the old main picture leaves it, and so does any picture
    // whose file name begins with a 'drop' name. Everything else stays, in
    // its order.
    $gallery = array_map('intval', (array) $product->get_gallery_image_ids());
    $keep = array();
    $out = array();
    foreach ($gallery as $g) {
        if ($g === $main) { $out[] = $g; continue; }
        if ($main !== $old && $g === $old) { $out[] = $g; continue; }
        $name = basename((string) get_post_meta($g, '_wp_attached_file', true));
        foreach ($drop as $begin) {
            if ($begin !== '' && stripos($name, $begin) === 0) { $out[] = $g; continue 2; }
        }
        $keep[] = $g;
    }
    if ($main === $old && !$out) {
        return $id . ' ' . implode('; ', $did) . '; gallery unchanged (' . count($gallery) . ')';
    }
    if ($out && get_post_meta($id, '_af_gallery_before', true) === '') {
        update_post_meta($id, '_af_gallery_before', implode(',', $gallery));
    }
    if ($main !== $old) {
        $product->set_image_id($main);
    }
    $product->set_gallery_image_ids($keep);
    $product->save();

    // The save purges the product itself; the shop is a separate cache entry.
    do_action('litespeed_purge_post', $id);
    do_action('litespeed_purge_posttype', 'product');
    if (function_exists('wc_get_page_permalink')) {
        do_action('litespeed_purge_url', wc_get_page_permalink('shop'));
    }
    return sprintf('%d %s; gallery %d -> %d%s', $id, implode('; ', $did), count($gallery), count($keep),
        $out ? ' (took out ' . implode(', ', $out) . ')' : '');
}

add_action('wp_loaded', function () {
    try {
        if (get_option('af_product_pictures_rev') === AF_PRODUCT_PICTURES_REV) {
            return;
        }
        if (!function_exists('wc_get_product')) {
            return;
        }
        // The deploy copies the theme file by file. Wait until every picture
        // the list names has arrived before claiming the revision.
        foreach (af_product_pictures() as $p) {
            if (!is_readable(af_product_pictures_dir() . $p['file'])) {
                return;
            }
        }
        // Claim the revision before any work: two requests arriving together
        // must not both upload the picture, and a failure below must not
        // repeat on every request. add_option() refuses a name that exists.
        if (!add_option('af_product_pictures_claim_' . AF_PRODUCT_PICTURES_REV, gmdate('Y-m-d H:i:s'), '', 'no')) {
            return;
        }
        update_option('af_product_pictures_rev', AF_PRODUCT_PICTURES_REV, true);
        $log = array();
        foreach (af_product_pictures() as $id => $p) {
            $log[] = af_product_picture_apply($id, $p);
        }
        update_option('af_product_pictures', gmdate('Y-m-d H:i:s') . ' UTC, revision ' . AF_PRODUCT_PICTURES_REV . ': ' . implode('; ', $log), false);
    } catch (\Throwable $e) {
        update_option('af_product_pictures', gmdate('Y-m-d H:i:s') . ' UTC, revision ' . AF_PRODUCT_PICTURES_REV . ' error: ' . $e->getMessage(), false);
    }
});
