<?php
/**
 * Shipping, logistics and returns  —  requirements §12
 *
 * Canvas art ships as a tube or a flat crate, and the carrier charges on
 * whichever is greater: actual weight or the volume the parcel occupies.
 * Everything here derives from the size the customer picked, because that is
 * the only dimension data these products carry.
 */
if (!defined('ABSPATH')) exit;

/** Inches of the printed piece, read from a size label like "3×5 ft (36×60 in)". */
function af_ship_inches($size_label) {
    if (preg_match('/\((\d+(?:\.\d+)?)\s*[x×X]\s*(\d+(?:\.\d+)?)\s*in\)/', (string) $size_label, $m)) {
        return array((float) $m[1], (float) $m[2]);
    }
    if (preg_match('/(\d+(?:\.\d+)?)\s*[x×X]\s*(\d+(?:\.\d+)?)\s*ft/', (string) $size_label, $m)) {
        return array((float) $m[1] * 12, (float) $m[2] * 12);
    }
    return null;
}

/**
 * How a piece travels. Rolled in a tube up to 3 ft on its short side and
 * unframed; anything framed or larger goes flat in a corner-protected crate.
 */
function af_ship_package($size_label, $frame) {
    $in = af_ship_inches($size_label);
    if (!$in) return null;
    sort($in);                        // [short, long]
    list($short, $long) = $in;
    $framed = ($frame && $frame !== 'Without Frame');

    if (!$framed && $short <= 36) {
        // rolled: a tube whose girth is fixed, length follows the short side
        $d = 6;
        return array(
            'method' => 'tube',
            'l' => $long + 4, 'w' => $d, 'h' => $d,
            // canvas + tube, scaled by area
            'weight' => max(2.0, ($short * $long) / 720 + 1.5),
            'label'  => 'Rolled in a protective tube',
        );
    }
    // flat crate: the piece plus packing, depth grows with the frame
    $depth = $framed ? 4 : 3;
    return array(
        'method' => 'crate',
        'l' => $long + 6, 'w' => $short + 6, 'h' => $depth,
        'weight' => max(5.0, ($short * $long) / 240 + ($framed ? 6 : 3)),
        'label'  => $framed ? 'Flat crate, corner-protected' : 'Flat pack, board-backed',
    );
}

/** Dimensional weight — carriers bill the greater of this and actual weight. */
function af_ship_dim_weight($l, $w, $h, $divisor = 139) {
    return ($l * $w * $h) / $divisor;
}

function af_ship_billable_weight($pkg) {
    if (!$pkg) return 0;
    $dim = af_ship_dim_weight($pkg['l'], $pkg['w'], $pkg['h']);
    return max($pkg['weight'], $dim);
}

/** Give each cart item real dimensions and weight, so any carrier plugin works. */
add_filter('woocommerce_add_cart_item_data', function($data, $product_id) { return $data; }, 5, 2);

add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $item) {
        if (empty($item['af_size']) || !isset($item['data'])) continue;
        $pkg = af_ship_package($item['af_size'], isset($item['af_frame']) ? $item['af_frame'] : '');
        if (!$pkg) continue;
        $p = $item['data'];
        $p->set_weight(round(af_ship_billable_weight($pkg), 2));
        $p->set_length(round($pkg['l'], 1));
        $p->set_width(round($pkg['w'], 1));
        $p->set_height(round($pkg['h'], 1));
    }
}, 20);

/** Size-based surcharge: oversized pieces cost more to move, whoever carries them. */
function af_ship_surcharge_table() {
    return apply_filters('af_ship_surcharge_table', array(
        // longest side (in) => surcharge
        array('over' => 96, 'fee' => 85.00, 'note' => 'Freight — over 8 ft'),
        array('over' => 72, 'fee' => 45.00, 'note' => 'Oversize — over 6 ft'),
        array('over' => 48, 'fee' => 20.00, 'note' => 'Large — over 4 ft'),
    ));
}

add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    $worst = null;
    foreach ($cart->get_cart() as $item) {
        if (empty($item['af_size'])) continue;
        $pkg = af_ship_package($item['af_size'], isset($item['af_frame']) ? $item['af_frame'] : '');
        if (!$pkg || $pkg['method'] !== 'crate') continue;
        $long = max($pkg['l'], $pkg['w']);
        foreach (af_ship_surcharge_table() as $band) {
            if ($long > $band['over']) {
                if (!$worst || $band['fee'] > $worst['fee']) $worst = $band;
                break;
            }
        }
    }
    if ($worst) {
        $cart->add_fee('Oversize handling (' . $worst['note'] . ')', $worst['fee'], true);
    }
}, 20);

/** Tell the customer how their order travels, on the product page and in the cart. */
add_action('woocommerce_single_product_summary', function() {
    global $product;
    if (!$product || !function_exists('af_pricing_applies') || !af_pricing_applies($product)) return;
    echo '<p class="af-ship-note" style="margin:10px 0 0;font-size:12.5px;color:#5a5140;">'
       . '📦 <strong>Rolled</strong> in a protective tube up to 3&nbsp;ft unframed; larger or framed pieces ship '
       . '<strong>flat in a corner-protected crate</strong>. Oversize handling is shown at checkout.'
       . '</p>';
}, 26);

// ── customs documentation (international) ────────────────────────────
function af_ship_customs_lines($order) {
    $lines = array();
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $size = $frame = '';
        foreach ($item->get_meta_data() as $m) {
            $d = $m->get_data();
            if ($d['key'] === 'af_size')  $size  = (string) $d['value'];
            if ($d['key'] === 'af_frame') $frame = (string) $d['value'];
        }
        $pkg = $size ? af_ship_package($size, $frame) : null;
        $lines[] = array(
            'description' => 'Printed canvas artwork' . ($frame && $frame !== 'Without Frame' ? ', framed' : ', unframed'),
            'hs_code'     => ($frame && $frame !== 'Without Frame') ? '9701.10' : '4911.91',
            'origin'      => apply_filters('af_ship_country_of_origin', 'US'),
            'qty'         => $item->get_quantity(),
            'value'       => $item->get_total(),
            'weight'      => $pkg ? round(af_ship_billable_weight($pkg), 2) : '',
            'sku'         => $product ? $product->get_sku() : '',
        );
    }
    return $lines;
}

function af_ship_needs_customs($order) {
    $to = $order->get_shipping_country() ?: $order->get_billing_country();
    $from = apply_filters('af_ship_country_of_origin', 'US');
    return $to && $to !== $from;
}

// ── partial shipments ────────────────────────────────────────────────
function af_ship_shipments($order) {
    $s = $order->get_meta('_af_shipments');
    return is_array($s) ? $s : array();
}

function af_ship_shipped_qty($order, $item_id) {
    $n = 0;
    foreach (af_ship_shipments($order) as $sh) {
        if (isset($sh['items'][$item_id])) $n += (int) $sh['items'][$item_id];
    }
    return $n;
}

/** Record a shipment covering some or all of the remaining items. */
function af_ship_add_shipment($order, $items, $carrier, $tracking, $notify = true) {
    $shipments = af_ship_shipments($order);
    $shipments[] = array(
        'id'       => count($shipments) + 1,
        'items'    => $items,
        'carrier'  => $carrier,
        'tracking' => $tracking,
        'date'     => current_time('mysql'),
    );
    $order->update_meta_data('_af_shipments', $shipments);

    // fully shipped only once every line is accounted for
    $outstanding = 0;
    foreach ($order->get_items() as $item_id => $item) {
        $outstanding += max(0, $item->get_quantity() - af_ship_shipped_qty($order, $item_id));
    }
    $order->add_order_note(sprintf(
        'Shipment %d recorded — %s %s. %s',
        count($shipments), $carrier, $tracking,
        $outstanding ? sprintf('%d item(s) still to go.', $outstanding) : 'Order complete.'
    ));
    $order->save();

    if ($outstanding === 0 && $order->get_status() !== 'completed') {
        $order->update_status('completed', 'All items shipped.');
    }
    if ($notify) af_ship_notify_customer($order, end($shipments), $outstanding);
    return $shipments;
}

function af_ship_notify_customer($order, $shipment, $outstanding) {
    $to = $order->get_billing_email();
    if (!$to) return;
    $rows = '';
    foreach ($shipment['items'] as $item_id => $qty) {
        $item = $order->get_item($item_id);
        if (!$item) continue;
        $rows .= '<tr><td style="padding:6px 0;font:14px Arial,sans-serif;">' . esc_html($item->get_name())
               . ' × ' . (int) $qty . '</td></tr>';
    }
    $body = '<div style="background:#f6f1e6;padding:26px 14px;"><div style="max-width:560px;margin:0 auto;background:#fffdf8;border-radius:14px;padding:26px;">'
        . '<h1 style="font:700 20px Georgia,serif;margin:0 0 10px;">Your order is on its way</h1>'
        . '<p style="font:15px/1.6 Arial,sans-serif;color:#5a5140;margin:0 0 14px;">'
        . ($outstanding ? 'Part of your order has shipped — the rest follows shortly.' : 'Everything in your order has shipped.')
        . '</p><table style="width:100%;border-top:1px solid #efe6d2;">' . $rows . '</table>'
        . '<p style="font:14px Arial,sans-serif;margin:16px 0 0;"><strong>' . esc_html($shipment['carrier']) . '</strong><br>'
        . 'Tracking: ' . esc_html($shipment['tracking']) . '</p>'
        . '</div></div>';
    wp_mail($to, sprintf('[%s] Order #%s has shipped', get_bloginfo('name'), $order->get_order_number()),
            $body, array('Content-Type: text/html; charset=UTF-8'));
}

// ── admin: shipments + customs on the order screen ───────────────────
add_action('add_meta_boxes', function() {
    foreach (array('shop_order', 'woocommerce_page_wc-orders') as $screen) {
        add_meta_box('af_ship_box', 'Shipments & customs', 'af_ship_metabox', $screen, 'normal', 'default');
    }
});

function af_ship_metabox($post_or_order) {
    $order = is_a($post_or_order, 'WP_Post') ? wc_get_order($post_or_order->ID) : $post_or_order;
    if (!$order) { echo '<p>—</p>'; return; }
    wp_nonce_field('af_ship_save', 'af_ship_nonce');

    echo '<table class="widefat striped"><thead><tr><th>Item</th><th>Ordered</th><th>Shipped</th><th>Ship now</th></tr></thead><tbody>';
    $outstanding = 0;
    foreach ($order->get_items() as $item_id => $item) {
        $done = af_ship_shipped_qty($order, $item_id);
        $left = max(0, $item->get_quantity() - $done);
        $outstanding += $left;
        echo '<tr><td>' . esc_html($item->get_name()) . '</td>';
        echo '<td>' . (int) $item->get_quantity() . '</td>';
        echo '<td>' . (int) $done . '</td>';
        echo '<td>' . ($left
            ? '<input type="number" min="0" max="' . (int) $left . '" name="af_ship_qty[' . (int) $item_id . ']" value="0" style="width:70px">'
            : '<span style="color:#1e8b56;">complete</span>') . '</td></tr>';
    }
    echo '</tbody></table>';

    if ($outstanding) {
        echo '<p style="margin-top:12px;"><label>Carrier <input type="text" name="af_ship_carrier" placeholder="UPS / FedEx / USPS"></label> ';
        echo '<label>Tracking <input type="text" name="af_ship_tracking" placeholder="1Z..."></label> ';
        echo '<label><input type="checkbox" name="af_ship_notify" value="1" checked> Email the customer</label></p>';
        echo '<p><button type="submit" class="button button-primary" name="af_ship_do" value="1">Record shipment</button> ';
        echo '<span style="color:#646970;">' . (int) $outstanding . ' item(s) outstanding</span></p>';
    } else {
        echo '<p style="color:#1e8b56;margin-top:12px;"><strong>Fully shipped.</strong></p>';
    }

    $ships = af_ship_shipments($order);
    if ($ships) {
        echo '<h4 style="margin-bottom:6px;">Shipments</h4><ul style="margin:0;padding-left:18px;list-style:disc;">';
        foreach ($ships as $sh) {
            echo '<li>#' . (int) $sh['id'] . ' — ' . esc_html($sh['carrier']) . ' ' . esc_html($sh['tracking'])
               . ' <small>(' . esc_html($sh['date']) . ')</small></li>';
        }
        echo '</ul>';
    }

    if (af_ship_needs_customs($order)) {
        echo '<h4 style="margin:16px 0 6px;">Customs declaration (international)</h4>';
        echo '<p><a class="button" target="_blank" rel="noopener" href="'
           . esc_url(add_query_arg(array('af_doc' => 'customs', 'order' => $order->get_id(), 'print' => 1), home_url('/')))
           . '">Print CN22 / commercial invoice</a></p>';
    }
}

add_action('save_post_shop_order', 'af_ship_handle_save');
add_action('woocommerce_process_shop_order_meta', 'af_ship_handle_save');

function af_ship_handle_save($order_id) {
    if (empty($_POST['af_ship_do'])) return;
    if (!isset($_POST['af_ship_nonce']) || !wp_verify_nonce(wp_unslash($_POST['af_ship_nonce']), 'af_ship_save')) return;
    if (!current_user_can('edit_shop_orders')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $items = array();
    foreach ((array) ($_POST['af_ship_qty'] ?? array()) as $item_id => $qty) {
        $qty = (int) $qty;
        if ($qty > 0) $items[(int) $item_id] = $qty;
    }
    if (!$items) return;

    af_ship_add_shipment(
        $order, $items,
        sanitize_text_field(wp_unslash($_POST['af_ship_carrier'] ?? '')),
        sanitize_text_field(wp_unslash($_POST['af_ship_tracking'] ?? '')),
        !empty($_POST['af_ship_notify'])
    );
}

// ── customs document ─────────────────────────────────────────────────
add_action('template_redirect', function() {
    if (empty($_GET['af_doc']) || $_GET['af_doc'] !== 'customs') return;
    if (!current_user_can('manage_woocommerce')) {
        wp_die('You do not have permission to view this document.', 'Not allowed', array('response' => 403));
    }
    $order = wc_get_order(absint($_GET['order'] ?? 0));
    if (!$order) return;

    $lines = af_ship_customs_lines($order);
    $total = array_sum(wp_list_pluck($lines, 'value'));
    $wt    = array_sum(array_filter(wp_list_pluck($lines, 'weight')));
    nocache_headers();
    ?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Customs declaration — <?php echo esc_html($order->get_order_number()); ?></title>
    <style>
      body{font:13px/1.5 Arial,sans-serif;background:#f2f2f2;margin:0;}
      .doc{background:#fff;max-width:760px;margin:20px auto;padding:36px 40px;box-shadow:0 2px 12px rgba(0,0,0,.08);}
      h1{font-size:19px;margin:0 0 4px;text-transform:uppercase;letter-spacing:1px;}
      .sub{color:#666;margin:0 0 20px;font-size:12px;}
      table{width:100%;border-collapse:collapse;margin-top:14px;}
      th{text-align:left;font-size:11px;text-transform:uppercase;color:#666;border-bottom:1px solid #ddd;padding:0 6px 7px 0;}
      td{padding:9px 6px 9px 0;border-bottom:1px solid #f0f0f0;vertical-align:top;}
      .tot{margin-top:14px;font-weight:700;}
      .sign{margin-top:34px;border-top:1px solid #bbb;padding-top:8px;color:#666;font-size:11px;width:60%;}
      @media print{body{background:#fff;}.doc{box-shadow:none;margin:0;max-width:none;padding:0;}.noprint{display:none;}}
    </style></head><body>
    <div class="doc">
      <p class="noprint" style="text-align:right;"><button onclick="window.print()">Print</button></p>
      <h1>Customs Declaration</h1>
      <p class="sub">CN22 / commercial invoice · Order #<?php echo esc_html($order->get_order_number()); ?> · <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></p>
      <p><strong>Ship to:</strong><br><?php echo wp_kses_post($order->get_formatted_shipping_address($order->get_formatted_billing_address())); ?></p>
      <p><strong>Contents:</strong> Merchandise — original printed artwork</p>
      <table><thead><tr><th>Description</th><th>HS code</th><th>Origin</th><th>Qty</th><th>Weight (lb)</th><th>Value</th></tr></thead><tbody>
      <?php foreach ($lines as $l): ?>
        <tr><td><?php echo esc_html($l['description']); ?><?php if ($l['sku']): ?><br><small><?php echo esc_html($l['sku']); ?></small><?php endif; ?></td>
        <td><?php echo esc_html($l['hs_code']); ?></td><td><?php echo esc_html($l['origin']); ?></td>
        <td><?php echo (int) $l['qty']; ?></td><td><?php echo esc_html($l['weight']); ?></td>
        <td><?php echo wp_kses_post(wc_price($l['value'], array('currency' => $order->get_currency()))); ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <p class="tot">Total declared value: <?php echo wp_kses_post(wc_price($total, array('currency' => $order->get_currency()))); ?>
         &nbsp;·&nbsp; Total weight: <?php echo esc_html(round($wt, 2)); ?> lb</p>
      <p style="margin-top:18px;font-size:11.5px;color:#666;">I certify the particulars given are correct and this item contains no dangerous or prohibited goods.</p>
      <div class="sign">Signature &amp; date</div>
    </div></body></html>
    <?php
    exit;
});

// ── returns / RMA ────────────────────────────────────────────────────
function af_rma_table() { global $wpdb; return $wpdb->prefix . 'af_rma'; }

add_action('after_setup_theme', function() {
    if (get_option('af_rma_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE " . af_rma_table() . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        email VARCHAR(190) NOT NULL,
        items TEXT NULL,
        reason VARCHAR(60) NOT NULL,
        detail TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'requested',
        resolution VARCHAR(30) NOT NULL DEFAULT '',
        pickup_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY status (status)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('af_rma_db_ver', '1');
}, 7);

function af_rma_reasons() {
    return array(
        'damaged'   => 'Arrived damaged',
        'wrong'     => 'Wrong item or size',
        'quality'   => 'Print quality',
        'not_as'    => 'Not as pictured',
        'changed'   => 'Changed my mind',
        'other'     => 'Something else',
    );
}

/** Customers raise a return from the order they are looking at. */
add_action('woocommerce_order_details_after_order_table', function($order) {
    if (!is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id()) return;
    if (!in_array($order->get_status(), array('completed', 'processing'), true)) return;

    global $wpdb;
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . af_rma_table() . " WHERE order_id = %d ORDER BY id DESC LIMIT 1", $order->get_id()
    ));
    if ($existing) {
        echo '<div class="woocommerce-info">Return <strong>#' . (int) $existing->id . '</strong> — '
           . esc_html(ucfirst($existing->status))
           . ($existing->pickup_at ? ' · pickup ' . esc_html($existing->pickup_at) : '') . '</div>';
        return;
    }
    ?>
    <h3 style="margin-top:26px;">Need to return something?</h3>
    <form method="post" class="af-rma-form">
      <?php wp_nonce_field('af_rma_' . $order->get_id(), 'af_rma_nonce'); ?>
      <input type="hidden" name="af_rma_order" value="<?php echo (int) $order->get_id(); ?>">
      <p><label>Which items?<br>
        <?php foreach ($order->get_items() as $item_id => $item): ?>
          <label style="display:block;font-weight:400;margin:4px 0;">
            <input type="checkbox" name="af_rma_items[]" value="<?php echo (int) $item_id; ?>">
            <?php echo esc_html($item->get_name()); ?>
          </label>
        <?php endforeach; ?>
      </label></p>
      <p><label>Reason<br><select name="af_rma_reason" required>
        <?php foreach (af_rma_reasons() as $k => $v): ?><option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option><?php endforeach; ?>
      </select></label></p>
      <p><label>Anything we should know?<br><textarea name="af_rma_detail" rows="3" maxlength="1000" style="width:100%"></textarea></label></p>
      <p><button type="submit" class="button">Request a return</button></p>
    </form>
    <?php
});

add_action('template_redirect', function() {
    if (empty($_POST['af_rma_order'])) return;
    $order_id = absint($_POST['af_rma_order']);
    if (!isset($_POST['af_rma_nonce']) || !wp_verify_nonce(wp_unslash($_POST['af_rma_nonce']), 'af_rma_' . $order_id)) return;
    $order = wc_get_order($order_id);
    if (!$order || !is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id()) return;

    global $wpdb;
    $items = array_map('absint', (array) ($_POST['af_rma_items'] ?? array()));
    $wpdb->insert(af_rma_table(), array(
        'order_id'   => $order_id,
        'user_id'    => get_current_user_id(),
        'email'      => $order->get_billing_email(),
        'items'      => wp_json_encode($items),
        'reason'     => sanitize_text_field(wp_unslash($_POST['af_rma_reason'] ?? 'other')),
        'detail'     => sanitize_textarea_field(wp_unslash($_POST['af_rma_detail'] ?? '')),
        'status'     => 'requested',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ));
    $rma = (int) $wpdb->insert_id;
    $order->add_order_note(sprintf('Return #%d requested by the customer.', $rma));
    $order->save();

    wp_mail(get_option('admin_email'),
        sprintf('[%s] Return #%d requested on order #%s', get_bloginfo('name'), $rma, $order->get_order_number()),
        sprintf("A return has been requested.\n\nOrder: %s\nReason: %s\n\nReview: %s\n",
            $order->get_order_number(),
            sanitize_text_field(wp_unslash($_POST['af_rma_reason'] ?? '')),
            admin_url('admin.php?page=af-returns')));

    wc_add_notice('Your return request has been logged — we will email you within one working day.', 'success');
    wp_safe_redirect(wp_get_referer() ?: wc_get_account_endpoint_url('orders'));
    exit;
});

add_action('admin_menu', function() {
    add_submenu_page('woocommerce', 'Returns (RMA)', 'Returns (RMA)', 'manage_woocommerce', 'af-returns', 'af_rma_admin_page');
});

function af_rma_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $t = af_rma_table();

    if (isset($_POST['af_rma_update']) && check_admin_referer('af_rma_admin')) {
        $id = absint($_POST['af_rma_update']);
        $wpdb->update($t, array(
            'status'     => sanitize_text_field(wp_unslash($_POST['status'])),
            'resolution' => sanitize_text_field(wp_unslash($_POST['resolution'])),
            'pickup_at'  => $_POST['pickup_at'] ? sanitize_text_field(wp_unslash($_POST['pickup_at'])) : null,
            'updated_at' => current_time('mysql'),
        ), array('id' => $id));
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id));
        if ($row && $row->email) {
            wp_mail($row->email,
                sprintf('[%s] Return #%d — %s', get_bloginfo('name'), $id, ucfirst($row->status)),
                sprintf("Your return is now: %s\n%s%s\n",
                    ucfirst($row->status),
                    $row->resolution ? "Resolution: {$row->resolution}\n" : '',
                    $row->pickup_at ? "Pickup scheduled: {$row->pickup_at}\n" : ''));
        }
        echo '<div class="notice notice-success"><p>Return updated and the customer notified.</p></div>';
    }

    $rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 100");
    $counts = $wpdb->get_results("SELECT status, COUNT(*) n FROM {$t} GROUP BY status");

    echo '<div class="wrap"><h1>Returns (RMA)</h1>';
    echo '<div style="display:flex;gap:12px;margin:16px 0 20px;flex-wrap:wrap;">';
    foreach ($counts as $c) {
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;">'
           . '<div style="font-size:12px;color:#646970;text-transform:uppercase;">' . esc_html($c->status) . '</div>'
           . '<div style="font-size:20px;font-weight:700;">' . (int) $c->n . '</div></div>';
    }
    if (!$counts) echo '<p>No returns yet.</p>';
    echo '</div>';

    echo '<table class="widefat striped"><thead><tr><th>#</th><th>Order</th><th>Customer</th><th>Reason</th><th>Status</th><th>Update</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . (int) $r->id . '</td>';
        echo '<td><a href="' . esc_url(get_edit_post_link($r->order_id)) . '">#' . (int) $r->order_id . '</a></td>';
        echo '<td>' . esc_html($r->email) . '<br><small>' . esc_html($r->detail) . '</small></td>';
        echo '<td>' . esc_html(af_rma_reasons()[$r->reason] ?? $r->reason) . '</td>';
        echo '<td><strong>' . esc_html(ucfirst($r->status)) . '</strong>'
           . ($r->pickup_at ? '<br><small>pickup ' . esc_html($r->pickup_at) . '</small>' : '') . '</td>';
        echo '<td><form method="post" style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">';
        wp_nonce_field('af_rma_admin');
        echo '<select name="status">';
        foreach (array('requested','approved','in_transit','received','refunded','rejected') as $s) {
            echo '<option value="' . esc_attr($s) . '"' . selected($r->status, $s, false) . '>' . esc_html(ucfirst(str_replace('_',' ',$s))) . '</option>';
        }
        echo '</select>';
        echo '<select name="resolution"><option value="">—</option>';
        foreach (array('refund','replacement','store_credit','repair') as $s) {
            echo '<option value="' . esc_attr($s) . '"' . selected($r->resolution, $s, false) . '>' . esc_html(ucfirst(str_replace('_',' ',$s))) . '</option>';
        }
        echo '</select>';
        echo '<input type="datetime-local" name="pickup_at" value="' . esc_attr($r->pickup_at ? str_replace(' ', 'T', $r->pickup_at) : '') . '">';
        echo '<button class="button button-small" name="af_rma_update" value="' . (int) $r->id . '">Save</button>';
        echo '</form></td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="6">Nothing yet.</td></tr>';
    echo '</tbody></table></div>';
}
