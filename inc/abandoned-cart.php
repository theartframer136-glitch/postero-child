<?php
/**
 * Abandoned cart recovery  —  requirements §11
 *
 * A cart is "abandoned" once we know who owns it (they signed in, or typed
 * their email at checkout) and they leave without ordering. We keep a copy,
 * email a reminder on a schedule with a one-click restore link, and stop the
 * moment they order or ask us to stop.
 */
if (!defined('ABSPATH')) exit;

function af_ac_table() { global $wpdb; return $wpdb->prefix . 'af_abandoned_carts'; }

// ── storage ──────────────────────────────────────────────────────────
add_action('after_setup_theme', function() {
    if (get_option('af_ac_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = af_ac_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$t} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token CHAR(32) NOT NULL,
        email VARCHAR(190) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        first_name VARCHAR(120) NOT NULL DEFAULT '',
        contents LONGTEXT NOT NULL,
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        currency CHAR(3) NOT NULL DEFAULT 'USD',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        emails_sent TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_email_at DATETIME NULL,
        order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY email (email),
        KEY status_updated (status, updated_at)
    ) {$charset};");
    update_option('af_ac_db_ver', '1');
}, 7);

// ── capture ──────────────────────────────────────────────────────────

/** The cart, reduced to what we need to rebuild it later. */
function af_ac_snapshot() {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return null;
    $items = array();
    foreach (WC()->cart->get_cart() as $item) {
        $keep = array(
            'product_id'   => isset($item['product_id']) ? (int) $item['product_id'] : 0,
            'variation_id' => isset($item['variation_id']) ? (int) $item['variation_id'] : 0,
            'quantity'     => isset($item['quantity']) ? (int) $item['quantity'] : 1,
            'variation'    => isset($item['variation']) && is_array($item['variation']) ? $item['variation'] : array(),
            'options'      => array(),
        );
        // the size / frame / colour engine stores its choices on the cart item
        foreach (array('af_size', 'af_frame', 'af_color', 'af_art_code') as $k) {
            if (isset($item[$k]) && is_scalar($item[$k])) $keep['options'][$k] = (string) $item[$k];
        }
        if ($keep['product_id']) $items[] = $keep;
    }
    if (!$items) return null;
    return array(
        'items'    => $items,
        'total'    => (float) WC()->cart->get_total('edit'),
        'currency' => get_woocommerce_currency(),
    );
}

/** Record (or refresh) the current cart against an email address. */
function af_ac_capture($email, $first_name = '') {
    global $wpdb;
    $email = sanitize_email($email);
    if (!$email || !is_email($email)) return;
    $snap = af_ac_snapshot();
    if (!$snap) return;

    $t   = af_ac_table();
    $now = current_time('mysql');
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$t} WHERE email = %s ORDER BY id DESC LIMIT 1", $email));

    $data = array(
        'email'      => $email,
        'user_id'    => get_current_user_id(),
        'first_name' => sanitize_text_field($first_name),
        'contents'   => wp_json_encode($snap['items']),
        'total'      => $snap['total'],
        'currency'   => $snap['currency'],
        'updated_at' => $now,
    );

    if ($row && $row->status === 'open') {
        $wpdb->update($t, $data, array('id' => $row->id));
        return (int) $row->id;
    }
    // a recovered / stopped cart starts a fresh row when they shop again
    $data['token']       = wp_generate_password(32, false, false);
    $data['status']      = 'open';
    $data['emails_sent'] = 0;
    $data['created_at']  = $now;
    $wpdb->insert($t, $data);
    return (int) $wpdb->insert_id;
}

/** Signed-in shoppers are captured as soon as the cart changes. */
function af_ac_capture_logged_in() {
    if (!is_user_logged_in()) return;
    $u = wp_get_current_user();
    if (!$u || !$u->user_email) return;
    af_ac_capture($u->user_email, $u->first_name);
}
add_action('woocommerce_add_to_cart',        'af_ac_capture_logged_in', 20);
add_action('woocommerce_cart_item_removed',  'af_ac_capture_logged_in', 20);
add_action('woocommerce_after_cart_item_quantity_update', 'af_ac_capture_logged_in', 20);

/** Guests are captured when they type their email at checkout. */
function af_ac_capture_handler() {
    check_ajax_referer('af_ac', 'nonce');
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $first = isset($_POST['first']) ? sanitize_text_field(wp_unslash($_POST['first'])) : '';
    if (!$email || !is_email($email)) wp_send_json_error();
    af_ac_capture($email, $first);
    wp_send_json_success();
}
add_action('wp_ajax_af_ac_capture',        'af_ac_capture_handler');
add_action('wp_ajax_nopriv_af_ac_capture', 'af_ac_capture_handler');

add_action('woocommerce_after_checkout_form', function() {
    ?>
    <script>
    (function(){
      var sent = '', t;
      function grab(){
        var e = document.getElementById('billing_email');
        var f = document.getElementById('billing_first_name');
        if (!e || !e.value || e.value === sent || e.value.indexOf('@') < 1) return;
        sent = e.value;
        var body = new URLSearchParams();
        body.set('action','af_ac_capture');
        body.set('nonce', <?php echo wp_json_encode(wp_create_nonce('af_ac')); ?>);
        body.set('email', e.value);
        body.set('first', f ? f.value : '');
        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
              {method:'POST', credentials:'same-origin', body:body}).catch(function(){});
      }
      document.addEventListener('change', function(ev){
        if (ev.target && ev.target.id === 'billing_email') { clearTimeout(t); t = setTimeout(grab, 400); }
      });
      document.addEventListener('blur', function(ev){
        if (ev.target && ev.target.id === 'billing_email') { clearTimeout(t); t = setTimeout(grab, 400); }
      }, true);
    })();
    </script>
    <?php
});

// ── close the loop when they order ───────────────────────────────────
add_action('woocommerce_checkout_order_processed', function($order_id) {
    global $wpdb;
    $order = wc_get_order($order_id);
    if (!$order) return;
    $email = $order->get_billing_email();
    if (!$email) return;
    $wpdb->query($wpdb->prepare(
        "UPDATE " . af_ac_table() . " SET status = 'recovered', order_id = %d, updated_at = %s
         WHERE email = %s AND status = 'open'",
        $order_id, current_time('mysql'), $email
    ));
}, 10, 1);

// ── restore link ─────────────────────────────────────────────────────
add_action('template_redirect', function() {
    if (empty($_GET['af_restore'])) return;
    global $wpdb;
    $token = sanitize_text_field(wp_unslash($_GET['af_restore']));
    if (strlen($token) !== 32) return;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . af_ac_table() . " WHERE token = %s", $token));
    if (!$row || $row->status !== 'open') {
        wc_add_notice('That basket link has expired — but everything is still in the shop.', 'notice');
        wp_safe_redirect(wc_get_page_permalink('shop')); exit;
    }
    $items = json_decode($row->contents, true);
    if (!is_array($items) || !function_exists('WC') || !WC()->cart) return;

    WC()->cart->empty_cart();
    $added = 0;
    foreach ($items as $it) {
        $pid = isset($it['product_id']) ? (int) $it['product_id'] : 0;
        if (!$pid) continue;
        $product = wc_get_product($pid);
        if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) continue;
        $qty  = max(1, isset($it['quantity']) ? (int) $it['quantity'] : 1);
        $vid  = isset($it['variation_id']) ? (int) $it['variation_id'] : 0;
        $var  = isset($it['variation']) && is_array($it['variation']) ? $it['variation'] : array();
        $data = isset($it['options']) && is_array($it['options']) ? $it['options'] : array();
        if (WC()->cart->add_to_cart($pid, $qty, $vid, $var, $data)) $added++;
    }
    if ($added) {
        wc_add_notice('Welcome back — your basket is exactly as you left it.', 'success');
    } else {
        wc_add_notice('Those items are no longer available, sorry.', 'notice');
    }
    wp_safe_redirect(wc_get_cart_url()); exit;
});

// ── stop-reminders link ──────────────────────────────────────────────
add_action('template_redirect', function() {
    if (empty($_GET['af_ac_stop'])) return;
    global $wpdb;
    $token = sanitize_text_field(wp_unslash($_GET['af_ac_stop']));
    if (strlen($token) !== 32) return;
    $wpdb->update(af_ac_table(), array('status' => 'stopped', 'updated_at' => current_time('mysql')), array('token' => $token));
    wp_die(
        '<h1>Reminders stopped</h1><p>We will not email you about this basket again.</p>'
        . '<p><a href="' . esc_url(home_url('/')) . '">Back to The Art Framer</a></p>',
        'Reminders stopped', array('response' => 200)
    );
});

// ── the reminder schedule ────────────────────────────────────────────
add_action('init', function() {
    if (!wp_next_scheduled('af_ac_scan')) wp_schedule_event(time() + 600, 'hourly', 'af_ac_scan');
});

/** hours after abandonment => which reminder number that is */
function af_ac_schedule() {
    return apply_filters('af_ac_schedule', array(
        1  => array('subject' => 'You left something beautiful behind',       'lead' => 'Your basket is still waiting.'),
        24 => array('subject' => 'Still thinking it over?',                    'lead' => 'Your picks are saved — one click brings them back.'),
        72 => array('subject' => 'Last reminder about your basket',            'lead' => 'We will keep this to ourselves after this one.'),
    ));
}

add_action('af_ac_scan', function() {
    global $wpdb;
    $t     = af_ac_table();
    $sched = af_ac_schedule();
    $steps = array_keys($sched);          // e.g. 1, 24, 72
    sort($steps);

    // never chase a cart older than a fortnight
    $wpdb->query($wpdb->prepare(
        "UPDATE {$t} SET status = 'lost' WHERE status = 'open' AND updated_at < %s",
        gmdate('Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS)
    ));

    $rows = $wpdb->get_results("SELECT * FROM {$t} WHERE status = 'open' AND emails_sent < " . count($steps) . " ORDER BY id ASC LIMIT 50");
    foreach ($rows as $row) {
        $n = (int) $row->emails_sent;                 // 0 => first reminder due
        if (!isset($steps[$n])) continue;
        $due_after = $steps[$n] * HOUR_IN_SECONDS;
        if ((time() - strtotime($row->updated_at . ' GMT')) < $due_after) continue;
        if (af_ac_send_reminder($row, $sched[$steps[$n]])) {
            $wpdb->update($t, array(
                'emails_sent'   => $n + 1,
                'last_email_at' => current_time('mysql'),
            ), array('id' => $row->id));
        }
    }
});

function af_ac_send_reminder($row, $copy) {
    $items = json_decode($row->contents, true);
    if (!is_array($items) || !$items) return false;

    $restore = add_query_arg('af_restore', $row->token, home_url('/'));
    $stop    = add_query_arg('af_ac_stop', $row->token, home_url('/'));
    $name    = $row->first_name ? ' ' . $row->first_name : '';

    $lines = '';
    foreach ($items as $it) {
        $p = wc_get_product((int) $it['product_id']);
        if (!$p) continue;
        $img  = wp_get_attachment_image_url($p->get_image_id(), 'thumbnail');
        $bits = array();
        foreach (array('af_size' => '', 'af_frame' => '', 'af_color' => '') as $k => $_) {
            if (!empty($it['options'][$k])) $bits[] = $it['options'][$k];
        }
        $lines .= '<tr>'
            . '<td style="padding:10px 12px 10px 0;width:78px;">' . ($img ? '<img src="' . esc_url($img) . '" width="70" height="70" style="border-radius:8px;display:block;" alt="">' : '') . '</td>'
            . '<td style="padding:10px 0;font:14px/1.5 Arial,sans-serif;color:#1a1a1a;">'
            . '<strong>' . esc_html($p->get_name()) . '</strong>'
            . ($bits ? '<br><span style="font-size:12px;color:#7a7263;">' . esc_html(implode(' · ', $bits)) . '</span>' : '')
            . '<br><span style="font-size:12px;color:#7a7263;">Qty ' . (int) $it['quantity'] . '</span>'
            . '</td></tr>';
    }
    if ($lines === '') return false;

    $html = '<div style="background:#f6f1e6;padding:28px 14px;">'
      . '<div style="max-width:560px;margin:0 auto;background:#fffdf8;border:1px solid #efe6d2;border-radius:16px;padding:28px 26px;">'
      . '<h1 style="margin:0 0 6px;font:700 21px/1.3 Georgia,serif;color:#1a1a1a;">Hello' . esc_html($name) . ',</h1>'
      . '<p style="margin:0 0 18px;font:15px/1.6 Arial,sans-serif;color:#5a5140;">' . esc_html($copy['lead']) . '</p>'
      . '<table cellpadding="0" cellspacing="0" style="width:100%;border-top:1px solid #efe6d2;">' . $lines . '</table>'
      . '<p style="margin:22px 0 0;text-align:center;">'
      . '<a href="' . esc_url($restore) . '" style="display:inline-block;background:#1a1a1a;color:#fff;text-decoration:none;font:700 14px Arial,sans-serif;padding:14px 26px;border-radius:10px;">Return to my basket</a>'
      . '</p>'
      . '<p style="margin:18px 0 0;font:12px/1.6 Arial,sans-serif;color:#8a8170;text-align:center;">'
      . 'Prefer not to be reminded? <a href="' . esc_url($stop) . '" style="color:#8a8170;">Stop these emails</a>.'
      . '</p>'
      . '</div></div>';

    $to      = $row->email;
    $subject = $copy['subject'] . ' — ' . get_bloginfo('name');
    $headers = array('Content-Type: text/html; charset=UTF-8');
    return (bool) wp_mail($to, $subject, $html, $headers);
}

// ── admin screen ─────────────────────────────────────────────────────
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce', 'Abandoned Carts', 'Abandoned Carts',
        'manage_woocommerce', 'af-abandoned-carts', 'af_ac_admin_page'
    );
});

function af_ac_admin_page() {
    if (!current_user_can('manage_woocommerce')) return;
    global $wpdb;
    $t = af_ac_table();

    $open      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='open'");
    $recovered = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='recovered'");
    $lostval   = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$t} WHERE status='open'");
    $wonval    = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$t} WHERE status='recovered'");
    $rows      = $wpdb->get_results("SELECT * FROM {$t} ORDER BY updated_at DESC LIMIT 100");

    echo '<div class="wrap"><h1>Abandoned Carts</h1>';
    echo '<p>Baskets we know the owner of, and whether they came back. Reminders go out 1 hour, 1 day and 3 days after the basket goes quiet.</p>';
    echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:18px 0 22px;">';
    foreach (array(
        'Open baskets'    => $open,
        'Recovered'       => $recovered,
        'Value at risk'   => strip_tags(wc_price($lostval)),
        'Value recovered' => strip_tags(wc_price($wonval)),
    ) as $label => $val) {
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:150px;">'
           . '<div style="font-size:12px;color:#646970;text-transform:uppercase;letter-spacing:.04em;">' . esc_html($label) . '</div>'
           . '<div style="font-size:22px;font-weight:700;">' . esc_html($val) . '</div></div>';
    }
    echo '</div>';

    echo '<table class="widefat striped"><thead><tr>'
       . '<th>Email</th><th>Items</th><th>Value</th><th>Status</th><th>Reminders</th><th>Last activity</th><th>Order</th>'
       . '</tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="7">Nothing yet.</td></tr>';
    foreach ($rows as $r) {
        $items = json_decode($r->contents, true);
        $count = is_array($items) ? count($items) : 0;
        $badge = array('open' => '#b26b00', 'recovered' => '#1e8b56', 'lost' => '#787c82', 'stopped' => '#787c82');
        $col   = isset($badge[$r->status]) ? $badge[$r->status] : '#787c82';
        echo '<tr>';
        echo '<td>' . esc_html($r->email) . '</td>';
        echo '<td>' . (int) $count . '</td>';
        echo '<td>' . wp_kses_post(wc_price($r->total)) . '</td>';
        echo '<td><span style="color:' . esc_attr($col) . ';font-weight:600;">' . esc_html(ucfirst($r->status)) . '</span></td>';
        echo '<td>' . (int) $r->emails_sent . '</td>';
        echo '<td>' . esc_html($r->updated_at) . '</td>';
        echo '<td>' . ($r->order_id ? '<a href="' . esc_url(get_edit_post_link($r->order_id)) . '">#' . (int) $r->order_id . '</a>' : '—') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
