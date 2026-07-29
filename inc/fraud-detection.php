<?php
/**
 * Fraud screening  —  requirements §11
 *
 * Scores every order against the signals that actually correlate with
 * chargebacks, records why, and surfaces it in admin. It never cancels
 * anything on its own: by default it only flags, because a false positive
 * on a real customer costs more than a manual look. Auto-hold for the
 * highest band can be switched on with the af_fraud_autohold filter.
 */
if (!defined('ABSPATH')) exit;

function af_fraud_disposable_domains() {
    return apply_filters('af_fraud_disposable_domains', array(
        'mailinator.com','guerrillamail.com','10minutemail.com','tempmail.com','temp-mail.org',
        'throwawaymail.com','yopmail.com','trashmail.com','sharklasers.com','getnada.com',
        'dispostable.com','maildrop.cc','fakeinbox.com','mailnesia.com','tempinbox.com',
        'spamgourmet.com','mytemp.email','moakt.com','emailondeck.com','tempr.email',
    ));
}

function af_fraud_thresholds() {
    return apply_filters('af_fraud_thresholds', array('medium' => 30, 'high' => 60));
}

/** Work out a risk score plus the human reasons behind it. */
function af_fraud_assess($order) {
    $flags = array();
    $score = 0;

    $bill_country = $order->get_billing_country();
    $ship_country = $order->get_shipping_country();
    $total        = (float) $order->get_total();
    $email        = strtolower(trim($order->get_billing_email()));
    $ip           = $order->get_customer_ip_address();

    // 1. Billing and shipping in different countries
    if ($bill_country && $ship_country && $bill_country !== $ship_country) {
        $score += 25;
        $flags[] = sprintf('Billing country (%s) differs from shipping country (%s)', $bill_country, $ship_country);
    }

    // 2. Unusually large order
    $big   = (float) apply_filters('af_fraud_large_order', 500);
    $huge  = (float) apply_filters('af_fraud_huge_order', 1500);
    if ($total >= $huge)      { $score += 25; $flags[] = sprintf('Very large order (%s)', strip_tags(wc_price($total))); }
    elseif ($total >= $big)   { $score += 15; $flags[] = sprintf('Large order (%s)', strip_tags(wc_price($total))); }

    // 3. Disposable email address
    if ($email && strpos($email, '@') !== false) {
        $domain = substr($email, strpos($email, '@') + 1);
        if (in_array($domain, af_fraud_disposable_domains(), true)) {
            $score += 30;
            $flags[] = sprintf('Disposable email domain (%s)', $domain);
        }
    }

    // 4. Customer's network location disagrees with their billing country
    if ($ip && $bill_country && class_exists('WC_Geolocation')) {
        $geo = WC_Geolocation::geolocate_ip($ip);
        if (!empty($geo['country']) && $geo['country'] !== $bill_country) {
            $score += 25;
            $flags[] = sprintf('Connecting from %s but billing to %s', $geo['country'], $bill_country);
        }
    }

    // 5. A burst of orders from the same address
    if ($email) {
        $recent = wc_get_orders(array(
            'billing_email' => $email,
            'date_created'  => '>' . (time() - HOUR_IN_SECONDS),
            'limit'         => 6,
            'return'        => 'ids',
            'exclude'       => array($order->get_id()),
        ));
        if (is_array($recent) && count($recent) >= 3) {
            $score += 30;
            $flags[] = sprintf('%d other orders from this email in the past hour', count($recent));
        }
    }

    // 6. No usable phone number for a high-value delivery
    $digits = preg_replace('/\D/', '', (string) $order->get_billing_phone());
    if ($total >= $big && strlen($digits) < 7) {
        $score += 10;
        $flags[] = 'No usable phone number on a high-value order';
    }

    // 7. Guest checkout on a high-value order
    if (!$order->get_customer_id() && $total >= $big) {
        $score += 10;
        $flags[] = 'Guest checkout on a high-value order';
    }

    // 8. Name and email that share nothing at all, on a large order
    $name = strtolower(trim($order->get_billing_first_name() . $order->get_billing_last_name()));
    if ($total >= $huge && $name && $email) {
        $local = substr($email, 0, strpos($email, '@'));
        $local = preg_replace('/[^a-z]/', '', strtolower($local));
        $nm    = preg_replace('/[^a-z]/', '', $name);
        if ($local && $nm && strpos($local, substr($nm, 0, 3)) === false && strpos($nm, substr($local, 0, 3)) === false) {
            $score += 10;
            $flags[] = 'Name and email address appear unrelated';
        }
    }

    $t     = af_fraud_thresholds();
    $level = $score >= $t['high'] ? 'high' : ($score >= $t['medium'] ? 'medium' : 'low');

    return array('score' => $score, 'level' => $level, 'flags' => $flags);
}

add_action('woocommerce_checkout_order_processed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $risk = af_fraud_assess($order);
    $order->update_meta_data('_af_risk_score', $risk['score']);
    $order->update_meta_data('_af_risk_level', $risk['level']);
    $order->update_meta_data('_af_risk_flags', $risk['flags']);

    if ($risk['flags']) {
        $order->add_order_note(sprintf(
            "Fraud screening: %s risk (score %d)\n• %s",
            strtoupper($risk['level']), $risk['score'], implode("\n• ", $risk['flags'])
        ));
    }
    $order->save();

    if ($risk['level'] === 'high') {
        // tell the shop, and optionally park the order for a human
        $admin = get_option('admin_email');
        if ($admin) {
            wp_mail(
                $admin,
                sprintf('[%s] High-risk order #%s needs review', get_bloginfo('name'), $order->get_order_number()),
                sprintf(
                    "Order #%s scored %d on fraud screening.\n\nReasons:\n- %s\n\nReview: %s\n",
                    $order->get_order_number(), $risk['score'],
                    implode("\n- ", $risk['flags']),
                    admin_url('post.php?post=' . $order_id . '&action=edit')
                )
            );
        }
        if (apply_filters('af_fraud_autohold', false, $order, $risk)) {
            $order->update_status('on-hold', 'Held automatically by fraud screening.');
        }
    }
}, 20, 1);

// ── admin: a column in the orders list ───────────────────────────────
function af_fraud_badge($level, $score) {
    $map = array(
        'high'   => array('#b32d2e', 'High'),
        'medium' => array('#b26b00', 'Medium'),
        'low'    => array('#1e8b56', 'Low'),
    );
    if (!isset($map[$level])) return '—';
    return '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;color:#fff;background:'
        . esc_attr($map[$level][0]) . '">' . esc_html($map[$level][1]) . ' ' . (int) $score . '</span>';
}

function af_fraud_add_column($cols) {
    $new = array();
    foreach ($cols as $k => $v) {
        $new[$k] = $v;
        if ($k === 'order_status') $new['af_risk'] = 'Risk';
    }
    if (!isset($new['af_risk'])) $new['af_risk'] = 'Risk';
    return $new;
}
add_filter('manage_edit-shop_order_columns', 'af_fraud_add_column');
add_filter('woocommerce_shop_order_list_table_columns', 'af_fraud_add_column');   // HPOS

function af_fraud_render_column($col, $order_or_id) {
    if ($col !== 'af_risk') return;
    $order = is_object($order_or_id) ? $order_or_id : wc_get_order($order_or_id);
    if (!$order) return;
    $level = $order->get_meta('_af_risk_level');
    $score = (int) $order->get_meta('_af_risk_score');
    echo $level ? wp_kses_post(af_fraud_badge($level, $score)) : '—';
}
add_action('manage_shop_order_posts_custom_column', 'af_fraud_render_column', 10, 2);
add_action('woocommerce_shop_order_list_table_custom_column', 'af_fraud_render_column', 10, 2);   // HPOS

// ── admin: the reasons, on the order screen ──────────────────────────
add_action('add_meta_boxes', function() {
    $screens = array('shop_order', 'woocommerce_page_wc-orders');
    foreach ($screens as $s) {
        add_meta_box('af_fraud_box', 'Fraud screening', function($post_or_order) {
            $order = is_a($post_or_order, 'WP_Post') ? wc_get_order($post_or_order->ID) : $post_or_order;
            if (!$order) { echo '<p>—</p>'; return; }
            $level = $order->get_meta('_af_risk_level');
            $score = (int) $order->get_meta('_af_risk_score');
            $flags = $order->get_meta('_af_risk_flags');
            if (!$level) { echo '<p>This order was placed before screening was enabled.</p>'; return; }
            echo '<p style="margin-top:0;">' . wp_kses_post(af_fraud_badge($level, $score)) . '</p>';
            if (is_array($flags) && $flags) {
                echo '<ul style="margin:0;padding-left:18px;list-style:disc;">';
                foreach ($flags as $f) echo '<li>' . esc_html($f) . '</li>';
                echo '</ul>';
            } else {
                echo '<p>Nothing unusual found.</p>';
            }
        }, $s, 'side', 'default');
    }
});
