<?php
/**
 * Corporate Printing: the custom quote and the catalogue.
 *
 * Owner, 24 Nov 2026, sending TheArtframer_Corporate_Printing_Brochure_1.pdf:
 *   "add this images and pricing in our art framer website, also give option
 *    to contact us for customized quote ... and share catalogue to all those
 *    enquiries."
 * The introductory offer and the ad in the same message are marketing, which
 * the owner's co-developer handles (owner, same day), so nothing here prices,
 * limits or advertises an offer.
 *
 * The products and prices themselves are data, written by the gateway script
 * artframer-ops/scripts/corporate-brochure-sync.php. This file is everything a
 * visitor sees around them:
 *
 *   1. THE CATALOGUE. The brochure, as a PDF in assets/corporate-brochure/, its
 *      metadata title set to the catalogue's public name (a PDF's title is what
 *      the browser tab shows). One URL, af_corp_catalogue_url(); the option
 *      af_corp_catalogue_url overrides it without a deploy when a new edition
 *      goes to the Media Library.
 *
 *   2. THE QUOTE FORM, [af_corporate_quote]. Saved in the same table and the
 *      same wp-admin screen as the contact form (Contact Messages), emailed to
 *      the studio with Reply-To set to the customer, and answered AT ONCE with
 *      the catalogue by email — "share catalogue to all those enquiries". On
 *      success the browser reports a Meta pixel Lead and a GA4 generate_lead,
 *      so any ad can be measured on enquiries, not just clicks.
 *
 *   3. THE CONTACT FORM'S SALES ENQUIRIES get the catalogue too, through the
 *      af_contact_message_saved action the contact handler now fires. Support
 *      subjects (tracking, returns) do not: a rate card is not an answer to
 *      "where is my parcel".
 *
 *   4. WHERE THE FORM APPEARS: under the add-to-cart of every Corporate
 *      Printing product (folded, product preselected), under the grid of the
 *      Corporate Printing category (with a strip at the top pointing to it and
 *      to the catalogue), under the text of /wholesale-corporate/, and on the
 *      page /corporate-printing-quote/ ([af_corporate_quote_page]), a focused
 *      place to send people from an ad or an email.
 *
 * WHY A FRESH TOKEN BEFORE EVERY SEND. Pages are cached for logged-out
 * visitors (LiteSpeed), and a nonce printed into a cached page goes stale
 * after a day — the contact form's known limit (see the note above
 * af_ajax in functions.php). For a form that paid ads send people to, a
 * stale page must not mean a lost enquiry, so the browser asks admin-ajax for
 * a token (a POST: never cached by LiteSpeed or Cloudflare) and sends the
 * form with it. (The same holds for anyone arriving from an ad.) Spam is held off the way the contact form does it — honeypot,
 * Cloudflare Turnstile when switched on, five sends an hour per address — plus
 * a fill-time check that drops anything submitted faster than a person can.
 */
if (!defined('ABSPATH')) exit;

/* ───────────────────────────── the catalogue ───────────────────────────── */

function af_corp_catalogue_file() {
    return 'TheArtFramer-Corporate-Printing-Catalogue-2026.pdf';
}

function af_corp_catalogue_url() {
    $o = trim((string) get_option('af_corp_catalogue_url', ''));
    if ($o !== '' && wp_http_validate_url($o)) return $o;
    return get_stylesheet_directory_uri() . '/assets/corporate-brochure/' . af_corp_catalogue_file();
}

/** Contact-form subjects that are sales enquiries, and so get the catalogue. */
function af_corp_catalogue_subjects() {
    return apply_filters('af_corp_catalogue_subjects', array(
        'General Question', 'Custom Order / Personalised Print', 'Bulk & Corporate Order',
    ));
}

/** The studio's public email and phone (functions.php af_studio_contact()), with a fallback that names no one. */
function af_corp_contact() {
    if (function_exists('af_studio_contact')) return af_studio_contact();
    $e = (string) get_option('admin_email');
    return array('email' => $e, 'phone' => '', 'tel' => '', 'wa' => '');
}

/** Where the studio is told about an enquiry: the WordPress admin address and the studio's own. */
function af_corp_studio_recipients() {
    $to = array();
    foreach (array(get_option('admin_email'), af_corp_contact()['email']) as $e) {
        $e = sanitize_email((string) $e);
        if ($e && is_email($e) && !in_array(strtolower($e), array_map('strtolower', $to), true)) $to[] = $e;
    }
    return $to;
}

/**
 * The catalogue, emailed to someone who asked us something. HTML in the house
 * style of the basket reminders (inc/abandoned-cart.php). Replies go to the
 * studio's inbox, so "reply with your logo" reaches a person.
 */
function af_corp_send_catalogue($email, $name = '', $context = array()) {
    $email = sanitize_email((string) $email);
    if (!$email || !is_email($email)) return false;
    $c       = af_corp_contact();
    $parts   = preg_split('/\s+/', trim((string) $name));
    $first   = is_array($parts) && isset($parts[0]) ? $parts[0] : '';
    $about   = isset($context['product']) && $context['product'] !== '' ? $context['product'] : '';
    $pdf     = af_corp_catalogue_url();
    $shop    = get_term_link('corporate-printing', 'product_cat');
    $shop    = is_wp_error($shop) ? home_url('/') : $shop;

    $p  = 'margin:0 0 14px;font:15px/1.6 Arial,sans-serif;color:#4a4334;';
    $html = '<div style="background:#f6f1e6;padding:28px 14px;">'
      . '<div style="max-width:580px;margin:0 auto;background:#fffdf8;border:1px solid #efe6d2;border-radius:16px;padding:30px 28px;">'
      . '<p style="margin:0 0 4px;font:700 11px/1.4 Arial,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#8a6d1f;">The Art Framer · Corporate Printing</p>'
      . '<h1 style="margin:0 0 14px;font:700 22px/1.3 Georgia,serif;color:#1a1a1a;">Thank you' . ($first !== '' ? ', ' . esc_html($first) : '') . '</h1>'
      . '<p style="' . $p . '">We have your enquiry' . ($about !== '' ? ' about <strong>' . esc_html($about) . '</strong>' : '')
      . ' and will come back to you with a quote within one working day — usually the same day. Here is our 2026 corporate catalogue with every product and price:</p>'
      . '<p style="margin:22px 0;text-align:center;"><a href="' . esc_url($pdf) . '" style="display:inline-block;background:#1a1a1a;color:#ffffff;text-decoration:none;font:700 15px Arial,sans-serif;padding:15px 28px;border-radius:10px;">Download the catalogue (PDF)</a></p>'
      . '<p style="' . $p . '"><strong>Send us your logo — we will send back 2 designs.</strong> Just reply to this email with your logo or artwork and the item you have in mind. Artwork and two design variations are included free with every order.</p>'
      . '<ul style="margin:0 0 18px;padding:0 0 0 18px;font:14px/1.7 Arial,sans-serif;color:#4a4334;">'
      . '<li>Every catalogue price is already 40% off list</li>'
      . '<li>Free design proof — nothing prints until you approve it</li>'
      . '<li>Free local delivery in Delaware, Pennsylvania, Maryland and New Jersey</li>'
      . '</ul>'
      . '<p style="' . $p . '">Browse and order online: <a href="' . esc_url($shop) . '" style="color:#8a5a00;">' . esc_html(preg_replace('#^https?://#', '', untrailingslashit($shop))) . '</a>'
      . ($c['phone'] !== '' ? '<br>Call or WhatsApp: <a href="' . esc_url($c['tel']) . '" style="color:#8a5a00;">' . esc_html($c['phone']) . '</a>' : '') . '</p>'
      . '<p style="margin:20px 0 0;font:12px/1.6 Arial,sans-serif;color:#8a8170;">You are receiving this because you asked The Art Framer for a quote or information on theartframer.us. We will not add you to a mailing list unless you asked us to.</p>'
      . '</div></div>';

    $headers = array('Content-Type: text/html; charset=UTF-8', 'Reply-To: The Art Framer <' . $c['email'] . '>');
    $subject = 'Your corporate printing catalogue — The Art Framer';
    return (bool) wp_mail($email, $subject, $html, $headers);
}

/* Contact form: its sales enquiries get the catalogue as well. */
add_action('af_contact_message_saved', function ($id, $m) {
    if (!is_array($m) || empty($m['email'])) return;
    if (!in_array((string) ($m['subject'] ?? ''), af_corp_catalogue_subjects(), true)) return;
    $sent = af_corp_send_catalogue($m['email'], $m['name'] ?? '', array());
    if ($sent && $id) {
        global $wpdb;
        $t = function_exists('af_contact_table') ? af_contact_table() : $wpdb->prefix . 'af_contact_messages';
        $wpdb->query($wpdb->prepare("UPDATE {$t} SET message = CONCAT(message, %s) WHERE id = %d", "\n\n[Catalogue emailed automatically]", (int) $id));
    }
}, 10, 2);
add_filter('af_contact_success_message', function ($msg, $subject) {
    return in_array((string) $subject, af_corp_catalogue_subjects(), true)
        ? 'Thank you! Your message has been sent and our catalogue is on its way to your inbox — we usually reply within 24 hours.'
        : $msg;
}, 10, 2);

/* ───────────────────────────── the quote form ───────────────────────────── */

/** "Gold Foil Business Cards | Heavyweight Cards …" -> "Gold Foil Business Cards". */
function af_corp_short_name($title) {
    $t = html_entity_decode((string) $title, ENT_QUOTES, 'UTF-8');
    $p = strpos($t, ' | ');
    return trim($p !== false ? substr($t, 0, $p) : $t);
}

/** id => short name, for the form's product list. Cached; any product save clears it. */
function af_corp_quote_products() {
    $list = get_transient('af_corp_quote_products');
    if (is_array($list)) return $list;
    $list = array();
    $ids = get_posts(array(
        'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids',
        'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC',
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'corporate-printing')),
    ));
    foreach ($ids as $id) $list[(int) $id] = af_corp_short_name(get_the_title($id));
    asort($list, SORT_NATURAL | SORT_FLAG_CASE);
    set_transient('af_corp_quote_products', $list, HOUR_IN_SECONDS);
    return $list;
}
add_action('save_post_product', function () { delete_transient('af_corp_quote_products'); }, 10, 0);

/** The form. $a: product (id to preselect), source (where it sits), heading. */
function af_corp_quote_form($a = array()) {
    $a = wp_parse_args($a, array('product' => 0, 'source' => 'page', 'button' => 'Get my quote & catalogue'));
    static $n = 0;
    $n++;
    $GLOBALS['af_cq_needed'] = true;
    $fid  = 'afCorpQuote' . $n;
    $list = af_corp_quote_products();
    $pre  = (int) $a['product'];
    ob_start(); ?>
<form class="taf-form af-cq-form" id="<?php echo esc_attr($fid); ?>" novalidate
      data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
      data-source="<?php echo esc_attr($a['source']); ?>">
  <div class="taf-form-row">
    <label>Full name *<input type="text" name="af_name" required maxlength="120" autocomplete="name"></label>
    <label>Email *<input type="email" name="af_email" required maxlength="190" autocomplete="email"></label>
  </div>
  <div class="taf-form-row">
    <label>Phone<input type="tel" name="af_phone" maxlength="40" autocomplete="tel"></label>
    <label>Company<input type="text" name="af_company" maxlength="120" autocomplete="organization"></label>
  </div>
  <label>What would you like printed?
    <select name="af_product">
      <option value="">Not sure yet — send me the catalogue</option>
      <?php foreach ($list as $id => $label) {
          echo '<option value="' . (int) $id . '"' . selected($pre, (int) $id, false) . '>' . esc_html($label) . '</option>';
      } ?>
      <option value="other">Something else / a custom job</option>
    </select>
  </label>
  <div class="taf-form-row">
    <label>Quantity<input type="text" name="af_qty" maxlength="80" placeholder="e.g. 500 cards, 2 banners"></label>
    <label>Size or finish<input type="text" name="af_size" maxlength="120" placeholder="e.g. 3 × 6 ft, gold foil, matte"></label>
  </div>
  <div class="taf-form-row">
    <label>Needed by<input type="date" name="af_date" min="<?php echo esc_attr(current_time('Y-m-d')); ?>"></label>
    <label class="af-cq-check"><span><input type="checkbox" name="af_optin" value="1"> Send me occasional offers (unsubscribe any time)</span></label>
  </div>
  <label>Anything else we should know?<textarea name="af_message" rows="4" maxlength="3000" placeholder="Event date, delivery address, artwork you have…"></textarea></label>
  <input type="text" name="af_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="af-cq-hp">
  <?php if (function_exists('af_turnstile_box')) af_turnstile_box('quote'); ?>
  <button type="submit" class="taf-form-submit"><?php echo esc_html($a['button']); ?></button>
  <p class="af-cq-fine">We reply within one working day. Our catalogue comes to your inbox straight away.</p>
  <p class="taf-form-msg" role="status" aria-live="polite" style="display:none;"></p>
</form>
    <?php
    return ob_get_clean();
}

add_shortcode('af_corporate_quote', function ($atts) {
    $atts = shortcode_atts(array('product' => 0, 'source' => 'shortcode'), $atts, 'af_corporate_quote');
    return af_corp_quote_form($atts);
});

/** A fresh token for the send (see the header: cached pages carry stale nonces). */
function af_corp_quote_token_handler() {
    wp_send_json_success(array('nonce' => wp_create_nonce('af_corp_quote')));
}
add_action('wp_ajax_af_corp_quote_token',        'af_corp_quote_token_handler');
add_action('wp_ajax_nopriv_af_corp_quote_token', 'af_corp_quote_token_handler');

function af_corp_quote_submit_handler() {
    $thanks = 'Thank you! Your request is in — our catalogue is on its way to your inbox and we will reply with your quote within one working day.';
    if (!check_ajax_referer('af_corp_quote', 'nonce', false)) {
        wp_send_json_error(array('message' => 'This page has been open a while — please press the button once more.'));
    }
    $field = function ($k, $max = 200) {
        return isset($_POST[$k]) && is_string($_POST[$k]) ? mb_substr(sanitize_text_field(wp_unslash($_POST[$k])), 0, $max) : '';
    };
    // Bots: the honeypot, or a form sent faster than anyone can fill it in. Told "thank you", given nothing.
    $elapsed = isset($_POST['af_elapsed']) ? (int) $_POST['af_elapsed'] : 0;
    if ($field('af_hp') !== '' || ($elapsed > 0 && $elapsed < 1500)) {
        wp_send_json_success(array('message' => $thanks, 'pdf' => af_corp_catalogue_url()));
    }
    if (function_exists('af_turnstile_require_ajax')) af_turnstile_require_ajax('quote');
    $ip = function_exists('af_visitor_ip') ? af_visitor_ip() : '';
    if (function_exists('af_rl_over') && af_rl_over('corp_quote|' . $ip, 5, HOUR_IN_SECONDS)) {
        wp_send_json_error(array('message' => 'Too many requests from this connection — please try again in an hour, or call us.'));
    }

    $name    = $field('af_name', 120);
    $email   = sanitize_email($field('af_email', 190));
    $phone   = $field('af_phone', 40);
    $company = $field('af_company', 120);
    $qty     = $field('af_qty', 80);
    $size    = $field('af_size', 120);
    $date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $field('af_date', 10)) ? $field('af_date', 10) : '';
    $optin   = !empty($_POST['af_optin']);
    $note    = isset($_POST['af_message']) && is_string($_POST['af_message']) ? mb_substr(sanitize_textarea_field(wp_unslash($_POST['af_message'])), 0, 3000) : '';
    $page    = isset($_POST['af_page']) && is_string($_POST['af_page']) ? esc_url_raw(mb_substr(wp_unslash($_POST['af_page']), 0, 500)) : '';
    $utm     = $field('af_utm', 400);
    $source  = sanitize_key($field('af_source', 30));

    if (mb_strlen($name) < 2)         wp_send_json_error(array('message' => 'Please enter your name.'));
    if (!$email || !is_email($email)) wp_send_json_error(array('message' => 'Please enter a valid email address.'));

    $pick = $field('af_product', 20);
    $list = af_corp_quote_products();
    if ($pick === 'other')                        $product = 'Something else / a custom job';
    elseif (preg_match('/^\d+$/', $pick) && isset($list[(int) $pick])) $product = $list[(int) $pick];
    else                                          $product = '';

    $lines = array(
        'Product: '    . ($product !== '' ? $product : 'Not sure yet — sent the catalogue'),
        'Quantity: '   . ($qty !== '' ? $qty : '—'),
        'Size/finish: '. ($size !== '' ? $size : '—'),
        'Needed by: '  . ($date !== '' ? $date : '—'),
        'Company: '    . ($company !== '' ? $company : '—'),
        'Offers opt-in: ' . ($optin ? 'yes' : 'no'),
    );
    if ($note !== '') $lines[] = "\n" . $note;
    $lines[] = "\nPage: " . ($page !== '' ? $page : '—') . ($source !== '' ? ' (' . $source . ')' : '');
    if ($utm !== '') $lines[] = 'Campaign: ' . $utm;
    $body    = implode("\n", $lines);
    $subject = mb_substr('Corporate quote: ' . ($product !== '' ? $product : 'catalogue request'), 0, 120);

    global $wpdb;
    $table = function_exists('af_contact_table') ? af_contact_table() : $wpdb->prefix . 'af_contact_messages';
    $ok = $wpdb->insert($table, array(
        'created_at' => current_time('mysql'),
        'name'       => $name,
        'email'      => mb_substr($email, 0, 190),
        'phone'      => $phone,
        'subject'    => $subject,
        'message'    => $body,
        'ip'         => $ip,
        'status'     => 'new',
    ), array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
    $row = $ok ? (int) $wpdb->insert_id : 0;

    // The customer first: the catalogue is the promise the button made.
    $sent = af_corp_send_catalogue($email, $name, array('product' => $product));
    if ($row) {
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET message = CONCAT(message, %s) WHERE id = %d",
            $sent ? "\n\n[Catalogue emailed automatically]" : "\n\n[Catalogue email FAILED — please send it by hand]", $row));
    }

    // Then the studio, with Reply-To on the customer so "reply" answers them.
    $safe_name = trim(preg_replace('/[^\p{L}\p{N} .\'-]+/u', '', $name)) ?: 'Website visitor';
    $to = af_corp_studio_recipients();
    if ($to) {
        wp_mail($to, '[The Art Framer] New corporate quote request — ' . ($product !== '' ? $product : 'catalogue request'),
            "From: {$name} <{$email}>" . ($phone !== '' ? " / {$phone}" : '') . "\n\n{$body}\n\n"
            . ($sent ? 'The catalogue was emailed to the customer automatically.' : 'The catalogue email to the customer FAILED — please send it by hand.')
            . ($row ? "\n— Saved in wp-admin → Contact Messages" : "\n— NOT saved in Contact Messages (database error); this email is the only copy."),
            array('Reply-To: ' . $safe_name . ' <' . $email . '>'));
    }

    // "Send me occasional offers": the same list the footer newsletter form keeps.
    if ($optin) {
        $subs = get_option('af_newsletter_subscribers', array());
        if (!is_array($subs)) $subs = array();
        $have = false;
        foreach ($subs as $s) if (isset($s['email']) && strtolower($s['email']) === strtolower($email)) { $have = true; break; }
        if (!$have) {
            $subs[] = array('email' => $email, 'time' => current_time('mysql'), 'ip' => $ip, 'source' => 'corporate-quote');
            if (count($subs) > 5000) $subs = array_slice($subs, -5000);
            update_option('af_newsletter_subscribers', $subs, false);
        }
    }

    if (!$row && !$to) {
        wp_send_json_error(array('message' => 'We could not save your request — please email us at ' . af_corp_contact()['email'] . '.'));
    }
    do_action('af_corp_quote_received', $row, compact('name', 'email', 'phone', 'company', 'product', 'qty', 'size', 'date'));
    wp_send_json_success(array('message' => $thanks, 'pdf' => af_corp_catalogue_url(), 'product' => $product));
}
add_action('wp_ajax_af_corp_quote_submit',        'af_corp_quote_submit_handler');
add_action('wp_ajax_nopriv_af_corp_quote_submit', 'af_corp_quote_submit_handler');

/* The form's script and look, printed once, only on pages that drew a form. */
add_action('wp_footer', function () {
    if (empty($GLOBALS['af_cq_needed'])) return;
    ?>
<style id="af-cq-css">
.af-cq-form{max-width:760px}
.af-cq-form .af-cq-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;opacity:0}
.af-cq-form .af-cq-check{justify-content:flex-end}
.af-cq-form .af-cq-check span{display:flex;gap:9px;align-items:flex-start;font-weight:600;font-size:13.5px;line-height:1.4;color:var(--taf-ink,#2b2b2b)}
.af-cq-form .af-cq-check input{width:18px;height:18px;margin:1px 0 0;padding:0;flex:0 0 auto}
.af-cq-form .af-cq-fine{margin:-6px 0 0;font-size:13px;color:var(--taf-mut,#6b6b6b)}
.af-cq-form .taf-form-msg a{color:inherit;text-decoration:underline}
.af-cq-done{display:inline-block;margin-top:10px;background:var(--taf-dark,#141414);color:#fff!important;text-decoration:none!important;padding:11px 20px;font-weight:700}
</style>
<script id="af-cq-js">
(function () {
  var forms = document.querySelectorAll('form.af-cq-form');
  if (!forms.length) return;
  // Keep the ad's campaign tags for the length of the visit, so a quote sent
  // three pages after landing still says which campaign brought it.
  try {
    var q = new URLSearchParams(location.search), utm = [];
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'fbclid'].forEach(function (k) {
      if (q.get(k)) utm.push(k + '=' + q.get(k).slice(0, 60));
    });
    if (utm.length) sessionStorage.setItem('af_utm', utm.join('&'));
  } catch (e) {}
  var opened = Date.now();

  function say(form, ok, text, pdf) {
    var m = form.querySelector('.taf-form-msg');
    m.style.display = 'block';
    m.className = 'taf-form-msg ' + (ok ? 'ok' : 'err');
    m.textContent = text;
    if (ok && pdf) {
      var a = document.createElement('a');
      a.href = pdf; a.target = '_blank'; a.rel = 'noopener'; a.className = 'af-cq-done';
      a.textContent = 'Open the catalogue now (PDF)';
      m.appendChild(document.createElement('br'));
      m.appendChild(a);
    }
  }
  function post(url, fd) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (r) { return r.json(); });
  }

  Array.prototype.forEach.call(forms, function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = form.querySelector('[name="af_name"]'), email = form.querySelector('[name="af_email"]');
      if (!name.value || name.value.trim().length < 2) { say(form, false, 'Please enter your name.'); name.focus(); return; }
      if (!email.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { say(form, false, 'Please enter a valid email address.'); email.focus(); return; }
      var btn = form.querySelector('.taf-form-submit'), label = btn.textContent;
      btn.disabled = true; btn.textContent = 'Sending…';
      var ajax = form.getAttribute('data-ajax');
      var t = new FormData(); t.append('action', 'af_corp_quote_token');
      post(ajax, t).then(function (tok) {
        var fd = new FormData(form);
        fd.append('action', 'af_corp_quote_submit');
        fd.append('nonce', tok && tok.data && tok.data.nonce ? tok.data.nonce : '');
        fd.append('af_elapsed', String(Date.now() - opened));
        fd.append('af_page', location.href.slice(0, 500));
        fd.append('af_source', form.getAttribute('data-source') || '');
        try { fd.append('af_utm', sessionStorage.getItem('af_utm') || ''); } catch (x) {}
        return post(ajax, fd);
      }).then(function (res) {
        var ok = !!(res && res.success), d = (res && res.data) || {};
        say(form, ok, d.message || 'Something went wrong — please try again, or call us.', ok ? d.pdf : '');
        if (ok) {
          form.reset();
          try { if (window.fbq) fbq('track', 'Lead', { content_name: d.product || 'Corporate quote', content_category: 'Corporate Printing' }); } catch (x) {}
          try { if (window.gtag) gtag('event', 'generate_lead', { form_name: 'corporate_quote', item_name: d.product || '' }); } catch (x) {}
        }
      }).catch(function () {
        say(form, false, 'Network error — please try again, or email us directly.');
      }).then(function () {
        btn.disabled = false; btn.textContent = label;
        if (window.afTsApi) window.afTsApi.reset(form);
      });
    });
  });
})();
</script>
    <?php
}, 30);

/* ───────────────────── where the form and catalogue appear ───────────────────── */

function af_corp_is_corporate_product($pid) {
    return function_exists('af_kit_is_corporate') ? af_kit_is_corporate($pid) : has_term('corporate-printing', 'product_cat', $pid);
}

// Product page. Under the add-to-cart (30) only a pointer; the form itself sits
// below the summary (woocommerce_after_single_product_summary, before the tabs
// at 10). A <form> printed inside the summary could end up inside another form
// if a template ever wraps the summary in one, and a nested form is silently
// dropped by the browser — the quote would look fine and send nothing.
function af_corp_product_wants_quote() {
    global $product;
    if (!($product instanceof WC_Product) || !af_corp_is_corporate_product($product->get_id())) return 0;
    return (int) $product->get_id();
}
add_action('woocommerce_single_product_summary', function () {
    $pid = af_corp_product_wants_quote();
    if (!$pid) return;
    echo '<div class="af-cq-product">'
       . '<p class="af-cq-pointer"><span>Need another size, quantity or finish?</span> '
       . '<a href="#af-corp-quote">Get a custom quote</a></p>'
       . '<a class="af-cq-pdf" href="' . esc_url(af_corp_catalogue_url()) . '" target="_blank" rel="noopener">Download the 2026 corporate catalogue (PDF)</a>'
       . '</div>';
}, 32);
add_action('woocommerce_after_single_product_summary', function () {
    $pid = af_corp_product_wants_quote();
    if (!$pid) return;
    echo '<section class="af-cq-section af-cq-section--product" id="af-corp-quote"><h2>Get a custom quote</h2>'
       . '<p>Any size, quantity or finish — tell us what you need. We reply within one working day and email you our catalogue right away.</p>'
       . af_corp_quote_form(array('product' => $pid, 'source' => 'product', 'button' => 'Send my quote request'))
       . '</section>';
}, 7);

// Category page: a strip at the top, the form under the grid.
function af_corp_is_corporate_archive() {
    return function_exists('is_product_category') && is_product_category('corporate-printing');
}
add_action('woocommerce_archive_description', function () {
    if (!af_corp_is_corporate_archive()) return;
    echo '<div class="af-cq-strip">'
       . '<p><strong>Corporate rate card 2026.</strong> Every price here is already 40% off list. Need another size, quantity or finish? We quote the same day.</p>'
       . '<p class="af-cq-strip-actions"><a class="af-cq-btn af-cq-btn--dark" href="#af-corp-quote">Get a custom quote</a>'
       . '<a class="af-cq-btn" href="' . esc_url(af_corp_catalogue_url()) . '" target="_blank" rel="noopener">Download the catalogue (PDF)</a></p>'
       . '</div>';
}, 12);
add_action('woocommerce_after_shop_loop', function () {
    if (!af_corp_is_corporate_archive()) return;
    echo '<section class="af-cq-section" id="af-corp-quote"><h2>Get a custom quote</h2>'
       . '<p>Tell us what you need — any size, quantity or finish. We reply within one working day and email you our catalogue right away.</p>'
       . af_corp_quote_form(array('source' => 'category'))
       . '</section>';
}, 20);

// /wholesale-corporate/: the form and the catalogue under the page's own text.
add_filter('the_content', function ($html) {
    if (!is_page('wholesale-corporate') || !in_the_loop() || !is_main_query()) return $html;
    return $html
        . '<section class="af-cq-section" id="af-corp-quote"><h2>Request a quote</h2>'
        . '<p><a class="af-cq-btn" href="' . esc_url(af_corp_catalogue_url()) . '" target="_blank" rel="noopener">Download the 2026 corporate catalogue (PDF)</a></p>'
        . af_corp_quote_form(array('source' => 'wholesale'))
        . '</section>';
}, 20);

add_action('wp_head', function () {
    $on = af_corp_is_corporate_archive() || is_page()
        || (is_singular('product') && af_corp_is_corporate_product(get_queried_object_id()));
    if (!$on) return;
    ?>
<style id="af-cq-place-css">
.af-cq-product{margin:18px 0 8px;clear:both}
.af-cq-pointer{display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:48px;margin:0 0 8px;padding:10px 16px;border:1px solid #e6dcc4;background:#fbf8f1;font-size:14.5px;color:#2b2b2b;box-sizing:border-box}
.af-cq-pointer a{color:#8a5a00;font-weight:700;text-decoration:underline}
.af-cq-pdf{display:inline-block;font-size:14px;color:#8a5a00;text-decoration:underline;margin:2px 0 0}
.af-cq-section--product{clear:both;max-width:900px}
.af-cq-strip{margin:6px 0 22px;padding:16px 18px;border:1px solid #e6dcc4;background:#fbf8f1}
.af-cq-strip p{margin:0 0 10px;line-height:1.55}
.af-cq-strip .af-cq-strip-actions{display:flex;flex-wrap:wrap;gap:10px;margin:0}
.af-cq-btn{box-sizing:border-box;max-width:100%;display:inline-flex;align-items:center;min-height:44px;padding:10px 18px;border:1px solid #1a1a1a;color:#1a1a1a!important;background:#fff;font-weight:700;font-size:14px;text-decoration:none!important}
.af-cq-btn--dark{background:#1a1a1a;color:#fff!important}
.af-cq-section{margin:34px 0 10px;padding:26px 0 0;border-top:1px solid #ece5d4;scroll-margin-top:110px}
.af-cq-section h2{margin:0 0 6px}
.af-cq-section>p{margin:0 0 16px;color:#5a5140}
@media(max-width:640px){.af-cq-strip{padding:14px}.af-cq-btn{width:100%;justify-content:center}}
</style>
    <?php
}, 20);

/* ─────────────────────── the quote & catalogue page ─────────────────────── */

/**
 * /corporate-printing-quote/: one page with the quote form and the catalogue,
 * for anyone sent from an ad, an email or a QR code.
 *
 * The page itself is made once, on the first request after this deploys (the
 * pattern inc/duplicate-listings.php uses): a page created before the theme
 * knew the shortcode would have shown "[af_corporate_quote_page]" as text.
 * A five-minute lock keeps two first visitors from making it twice. If the
 * studio deletes the page later it is not brought back (the option remembers).
 */
add_action('wp_loaded', function () {
    if (get_option('af_corp_quote_page_made')) return;
    if (get_transient('af_corp_quote_page_lock')) return;
    set_transient('af_corp_quote_page_lock', 1, 5 * MINUTE_IN_SECONDS);
    try {
        $page = get_page_by_path('corporate-printing-quote');
        $id   = $page ? (int) $page->ID : 0;
        if (!$id) {
            $id = wp_insert_post(array(
                'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'corporate-printing-quote',
                'post_title' => 'Corporate Printing — Free Quote & 2026 Catalogue',
                'post_content' => '[af_corporate_quote_page]',
                // An excerpt of its own, so nothing renders the shortcode to describe the page in <head>.
                'post_excerpt' => 'Business cards, banners, standees, stickers and more — 40% off list, design included, free local delivery in DE, PA, MD and NJ. Get a free quote and our 2026 catalogue.',
                'comment_status' => 'closed', 'ping_status' => 'closed',
            ), true);
            if (is_wp_error($id) || !$id) return;
            update_post_meta($id, 'rank_math_title', 'Corporate Printing — Free Quote & 2026 Catalogue | The Art Framer');
            update_post_meta($id, 'rank_math_description', 'Business cards, banners, standees, stickers and more, printed and delivered from Delaware. 40% off list, design included. Get a free quote and the 2026 catalogue.');
        }
        update_option('af_corp_quote_page_made', (int) $id, false);
    } catch (\Throwable $e) {
        // A missing landing page is recoverable; an error on every request is not.
    }
}, 30);

add_shortcode('af_corporate_quote_page', function () {
    $pdf  = af_corp_catalogue_url();
    $cat  = get_term_link('corporate-printing', 'product_cat');
    $cat  = is_wp_error($cat) ? home_url('/shop/') : $cat;
    $c    = af_corp_contact();
    ob_start(); ?>
<div class="af-lp">
  <section class="af-lp-hero">
    <p class="af-lp-kicker">40% off list price</p>
    <h1>Your brand, printed big.</h1>
    <p class="af-lp-lead">Business cards, banners, standees, stickers, table cloths, certificates, tote bags, fliers and brochures — printed, framed and delivered from Delaware.</p>
    <p class="af-lp-cta">
      <a class="af-cq-btn af-cq-btn--dark" href="#af-corp-quote">Get a free quote</a>
      <a class="af-cq-btn" href="<?php echo esc_url($pdf); ?>" target="_blank" rel="noopener">Download the catalogue (PDF)</a>
    </p>
  </section>

  <section class="af-lp-points">
    <div><h3>Priced at low cost</h3><p>One honest rate across every size and material — no hidden set-up, no minimum order.</p></div>
    <div><h3>Design included</h3><p>Send us your logo and we send back 2 designs. Artwork and two variations are free.</p></div>
    <div><h3>Free local delivery</h3><p>Delaware, Pennsylvania, Maryland and New Jersey. Free design proof, support 24/7.</p></div>
  </section>

  <section class="af-lp-block af-cq-section" id="af-corp-quote">
    <h2>Get a free quote</h2>
    <p>Any product, size, quantity or finish. We reply within one working day — and email you the full catalogue right away.</p>
    <?php echo af_corp_quote_form(array('source' => 'landing')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php if ($c['phone'] !== '') { ?><p class="af-lp-note">Prefer to talk? Call or WhatsApp <a href="<?php echo esc_url($c['tel']); ?>"><?php echo esc_html($c['phone']); ?></a>.</p><?php } ?>
  </section>

  <section class="af-lp-block">
    <h2>Popular with businesses</h2>
    <?php echo do_shortcode('[products category="corporate-printing" limit="8" columns="4" orderby="popularity"]'); ?>
    <p><a class="af-cq-btn af-cq-btn--dark" href="<?php echo esc_url($cat); ?>">See all corporate printing</a></p>
  </section>
</div>
<style id="af-lp-css">
.af-lp{max-width:1180px;margin:0 auto}
.af-lp-hero{background:#1a1a1a;color:#fff;padding:40px 34px;margin:0 0 22px}
.af-lp-hero h1{color:#fff;font-size:clamp(28px,4.2vw,46px);line-height:1.12;margin:0 0 12px}
.af-lp-kicker{display:inline-block;margin:0 0 14px;padding:6px 12px;background:#c9a84c;color:#1a1206;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase}
.af-lp-lead{max-width:640px;font-size:16.5px;line-height:1.6;color:#e9e4d8;margin:0 0 20px}
.af-lp-cta{display:flex;flex-wrap:wrap;gap:10px;margin:0}
.af-lp-hero .af-cq-btn:not(.af-cq-btn--dark){background:transparent;color:#fff!important;border-color:#fff}
.af-lp-hero .af-cq-btn--dark{background:#fff;color:#1a1a1a!important;border-color:#fff}
.af-lp-points{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:0 0 26px}
.af-lp-points>div{background:#f6f1e6;border:1px solid #e9dfc8;padding:18px}
.af-lp-points h3{margin:0 0 6px;font-size:17px}
.af-lp-points p{margin:0;font-size:14.5px;line-height:1.55;color:#4a4334}
.af-lp-block{margin:10px 0 30px}
.af-lp-block>h2{margin:0 0 8px}
.af-lp-note{font-size:14px;color:#6b6b6b;margin:6px 0 14px}
@media(max-width:760px){.af-lp-hero{padding:28px 18px}.af-lp-points{grid-template-columns:1fr}}
</style>
    <?php
    return ob_get_clean();
});
