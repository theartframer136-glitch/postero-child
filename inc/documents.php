<?php
/**
 * Invoices and packing slips  —  requirements §11
 *
 * Printable documents rendered from the order itself, so they always match
 * what was actually bought — including the size / frame / colour the pricing
 * engine recorded. Staff can open either document; a customer can open the
 * invoice for their own order and nobody else's.
 */
if (!defined('ABSPATH')) exit;

function af_doc_can_view($order, $type) {
    if (!$order) return false;
    if (current_user_can('manage_woocommerce')) return true;
    if ($type !== 'invoice') return false;                 // packing slips are staff-only
    // the customer's own order, proved by key or by being signed in as them
    $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
    if ($key && hash_equals($order->get_order_key(), $key)) return true;
    $uid = get_current_user_id();
    return $uid && (int) $order->get_customer_id() === $uid;
}

/** The shop's own details, from WooCommerce settings. */
function af_doc_store() {
    return array(
        'name'     => get_bloginfo('name'),
        'address'  => get_option('woocommerce_store_address'),
        'address2' => get_option('woocommerce_store_address_2'),
        'city'     => get_option('woocommerce_store_city'),
        'postcode' => get_option('woocommerce_store_postcode'),
        'country'  => get_option('woocommerce_default_country'),
        // an invoice goes to the customer, so it carries the studio's public
        // address — never whoever happens to own the WordPress login
        'email'    => af_studio_contact()['email'],
        'site'     => home_url('/'),
    );
}

function af_doc_url($order, $type = 'invoice', $print = true) {
    $args = array('af_doc' => $type, 'order' => $order->get_id());
    if ($print) $args['print'] = 1;
    if (!current_user_can('manage_woocommerce')) $args['key'] = $order->get_order_key();
    return add_query_arg($args, home_url('/'));
}

add_action('template_redirect', function() {
    if (empty($_GET['af_doc'])) return;

    $type = sanitize_key(wp_unslash($_GET['af_doc']));
    if (!in_array($type, array('invoice', 'packing-slip'), true)) return;

    $ids = array();
    if (!empty($_GET['orders'])) {
        // bulk print (staff only — checked below via af_doc_can_view per order)
        foreach (explode(',', sanitize_text_field(wp_unslash($_GET['orders']))) as $bit) {
            $bit = absint($bit);
            if ($bit) $ids[] = $bit;
        }
    } elseif (!empty($_GET['order'])) {
        $ids[] = absint($_GET['order']);
    }
    if (!$ids) return;

    $orders = array();
    foreach ($ids as $id) {
        $o = wc_get_order($id);
        if ($o && af_doc_can_view($o, $type)) $orders[] = $o;
    }
    if (!$orders) {
        wp_die('You do not have permission to view this document.', 'Not allowed', array('response' => 403));
    }

    nocache_headers();
    af_doc_render($orders, $type, !empty($_GET['print']));
    exit;
});

function af_doc_render($orders, $type, $autoprint) {
    $store    = af_doc_store();
    $is_slip  = ($type === 'packing-slip');
    $title    = $is_slip ? 'Packing Slip' : 'Invoice';
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($title . ' — ' . $store['name']); ?></title>
<style>
  *{box-sizing:border-box;}
  body{margin:0;background:#f2f2f2;font:13px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:#1a1a1a;}
  .doc{background:#fff;max-width:820px;margin:22px auto;padding:44px 46px;box-shadow:0 2px 14px rgba(0,0,0,.08);}
  .doc + .doc{margin-top:0;}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;padding-bottom:22px;border-bottom:2px solid #1a1a1a;}
  .brand{font:700 22px/1.2 Georgia,serif;letter-spacing:-.3px;}
  .brand small{display:block;font:400 12px/1.6 Arial,sans-serif;color:#666;margin-top:6px;white-space:pre-line;}
  .docmeta{text-align:right;}
  .docmeta h1{margin:0 0 8px;font:700 24px/1 Georgia,serif;text-transform:uppercase;letter-spacing:1px;}
  .docmeta table{border-collapse:collapse;margin-left:auto;}
  .docmeta td{padding:2px 0 2px 14px;font-size:12px;}
  .docmeta td:first-child{color:#666;padding-left:0;}
  .addrs{display:flex;gap:36px;margin:24px 0 8px;}
  .addr{flex:1 1 0;}
  .addr h3{margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#666;}
  .addr p{margin:0;font-size:13px;line-height:1.6;}
  table.items{width:100%;border-collapse:collapse;margin-top:22px;}
  table.items th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#666;
    border-bottom:1px solid #ddd;padding:0 8px 8px 0;}
  table.items th.num,table.items td.num{text-align:right;padding-right:0;}
  table.items td{padding:11px 8px 11px 0;border-bottom:1px solid #f0f0f0;vertical-align:top;}
  table.items .opts{display:block;color:#666;font-size:11.5px;margin-top:3px;}
  .totals{margin-top:18px;margin-left:auto;width:290px;border-collapse:collapse;}
  .totals td{padding:6px 0;font-size:13px;}
  .totals td:last-child{text-align:right;}
  .totals tr.grand td{border-top:2px solid #1a1a1a;padding-top:10px;font-weight:700;font-size:16px;}
  .note{margin-top:26px;padding-top:16px;border-top:1px solid #eee;font-size:11.5px;color:#666;line-height:1.7;}
  .signbox{margin-top:34px;display:flex;gap:30px;}
  .signbox div{flex:1 1 0;border-top:1px solid #bbb;padding-top:7px;font-size:11px;color:#666;}
  .toolbar{max-width:820px;margin:18px auto 0;text-align:right;}
  .toolbar button{background:#1a1a1a;color:#fff;border:0;border-radius:7px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;}
  @media print{
    body{background:#fff;}
    .toolbar{display:none;}
    .doc{box-shadow:none;margin:0;max-width:none;padding:0;page-break-after:always;}
    .doc:last-child{page-break-after:auto;}
  }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
<?php foreach ($orders as $order):
    $addr_store = trim(implode("\n", array_filter(array(
        $store['address'], $store['address2'],
        trim($store['city'] . ' ' . $store['postcode']),
        $store['email'],
    ))));
    ?>
  <div class="doc">
    <div class="top">
      <div class="brand"><?php echo esc_html($store['name']); ?><small><?php echo esc_html($addr_store); ?></small></div>
      <div class="docmeta">
        <h1><?php echo esc_html($title); ?></h1>
        <table>
          <tr><td><?php echo $is_slip ? 'Order' : 'Invoice'; ?></td><td><strong>#<?php echo esc_html($order->get_order_number()); ?></strong></td></tr>
          <tr><td>Date</td><td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td></tr>
          <?php if (!$is_slip): ?>
            <tr><td>Payment</td><td><?php echo esc_html($order->get_payment_method_title() ?: '—'); ?></td></tr>
          <?php endif; ?>
          <tr><td>Status</td><td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td></tr>
        </table>
      </div>
    </div>

    <div class="addrs">
      <?php if (!$is_slip): ?>
        <div class="addr"><h3>Bill to</h3><p><?php echo wp_kses_post($order->get_formatted_billing_address('—')); ?>
          <?php if ($order->get_billing_email()): ?><br><?php echo esc_html($order->get_billing_email()); ?><?php endif; ?>
          <?php if ($order->get_billing_phone()): ?><br><?php echo esc_html($order->get_billing_phone()); ?><?php endif; ?>
        </p></div>
      <?php endif; ?>
      <div class="addr"><h3>Ship to</h3><p><?php
        echo wp_kses_post($order->get_formatted_shipping_address($order->get_formatted_billing_address('—')));
        if ($is_slip && $order->get_billing_phone()) echo '<br>' . esc_html($order->get_billing_phone());
      ?></p></div>
    </div>

    <table class="items">
      <thead>
        <tr>
          <th>Item</th>
          <?php if ($is_slip): ?><th>SKU</th><?php endif; ?>
          <th class="num">Qty</th>
          <?php if (!$is_slip): ?><th class="num">Unit</th><th class="num">Total</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($order->get_items() as $item):
        $product = $item->get_product();
        $opts    = array();
        foreach ($item->get_meta_data() as $meta) {
            $d = $meta->get_data();
            $k = isset($d['key']) ? $d['key'] : '';
            if ($k === '' || substr($k, 0, 1) === '_') continue;
            $v = is_scalar($d['value']) ? (string) $d['value'] : '';
            if ($v !== '') $opts[] = wc_attribute_label($k) . ': ' . $v;
        }
        $code = $product ? get_post_meta($product->get_id(), '_taf_art_code', true) : '';
        if ($code) $opts[] = 'Art code: ' . $code;
        ?>
        <tr>
          <td><strong><?php echo esc_html($item->get_name()); ?></strong>
            <?php if ($opts): ?><span class="opts"><?php echo esc_html(implode(' · ', $opts)); ?></span><?php endif; ?>
          </td>
          <?php if ($is_slip): ?><td><?php echo esc_html($product ? ($product->get_sku() ?: '—') : '—'); ?></td><?php endif; ?>
          <td class="num"><?php echo esc_html($item->get_quantity()); ?></td>
          <?php if (!$is_slip): ?>
            <td class="num"><?php echo wp_kses_post(wc_price($item->get_quantity() ? $item->get_total() / $item->get_quantity() : 0, array('currency' => $order->get_currency()))); ?></td>
            <td class="num"><?php echo wp_kses_post(wc_price($item->get_total(), array('currency' => $order->get_currency()))); ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (!$is_slip): ?>
      <table class="totals">
        <?php foreach ($order->get_order_item_totals() as $key => $t): ?>
          <tr class="<?php echo $key === 'order_total' ? 'grand' : ''; ?>">
            <td><?php echo esc_html($t['label']); ?></td>
            <td><?php echo wp_kses_post($t['value']); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <?php if ($order->get_customer_note()): ?>
      <div class="note"><strong>Order note:</strong> <?php echo esc_html($order->get_customer_note()); ?></div>
    <?php endif; ?>

    <?php if ($is_slip): ?>
      <div class="signbox">
        <div>Packed by</div><div>Checked by</div><div>Date</div>
      </div>
    <?php else: ?>
      <div class="note">
        Thank you for supporting original art. Questions about this invoice? Reply to
        <?php echo esc_html($store['email']); ?> quoting order #<?php echo esc_html($order->get_order_number()); ?>.
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if ($autoprint): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 350); });</script>
<?php endif; ?>
</body>
</html>
<?php
}

// ── admin: buttons on the order screen ───────────────────────────────
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    if (!current_user_can('manage_woocommerce')) return;
    echo '<p class="form-field form-field-wide" style="margin-top:10px;">';
    echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(af_doc_url($order, 'invoice')) . '">Invoice</a> ';
    echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(af_doc_url($order, 'packing-slip')) . '">Packing slip</a>';
    echo '</p>';
});

// ── admin: bulk print from the orders list ───────────────────────────
function af_doc_bulk_actions($actions) {
    $actions['af_print_invoices'] = 'Print invoices';
    $actions['af_print_slips']    = 'Print packing slips';
    return $actions;
}
add_filter('bulk_actions-edit-shop_order', 'af_doc_bulk_actions');
add_filter('bulk_actions-woocommerce_page_wc-orders', 'af_doc_bulk_actions');   // HPOS

function af_doc_bulk_handle($redirect, $action, $ids) {
    if (!in_array($action, array('af_print_invoices', 'af_print_slips'), true)) return $redirect;
    if (!current_user_can('manage_woocommerce') || !$ids) return $redirect;
    return add_query_arg(array(
        'af_doc' => $action === 'af_print_slips' ? 'packing-slip' : 'invoice',
        'orders' => implode(',', array_map('absint', $ids)),
        'print'  => 1,
    ), home_url('/'));
}
add_filter('handle_bulk_actions-edit-shop_order', 'af_doc_bulk_handle', 10, 3);
add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'af_doc_bulk_handle', 10, 3);   // HPOS

// ── customer: invoice from My Account ────────────────────────────────
add_filter('woocommerce_my_account_my_orders_actions', function($actions, $order) {
    $actions['af_invoice'] = array(
        'url'  => af_doc_url($order, 'invoice'),
        'name' => 'Invoice',
    );
    return $actions;
}, 10, 2);

add_action('woocommerce_order_details_after_order_table', function($order) {
    if (!af_doc_can_view($order, 'invoice')) return;
    echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url(af_doc_url($order, 'invoice')) . '">Download invoice (PDF)</a></p>';
});
