<?php
/**
 * The Art Framer assistant — an on-site chatbot.
 *
 * Answers on the page. It does not hand the visitor to WhatsApp: every reply
 * is composed here from the shop's own live data — the real size and frame
 * price table, the real shipping rules, the real catalogue — so nothing can
 * drift out of date. A human hand-off exists, but only when the visitor asks
 * for one or the bot genuinely cannot help.
 *
 * No third-party AI service, so no API key, no per-message cost, no customer
 * text leaving the server. Intent matching is keyword scoring against a
 * knowledge base; product questions run a real catalogue search and answer
 * with buyable cards.
 */
if (!defined('ABSPATH')) exit;

function af_bot_table() { global $wpdb; return $wpdb->prefix . 'af_bot_log'; }

/**
 * Every reply that names a way to reach a human goes through the shared
 * studio-contact helper, so the bot can never publish the WordPress admin's
 * personal address the way it used to.
 */
function af_bot_contact() {
    return function_exists('af_studio_contact')
        ? af_studio_contact()
        : array('email' => 'theartframer136@gmail.com', 'phone' => '+1 (610) 470-7280', 'tel' => 'tel:+16104707280');
}

add_action('after_setup_theme', function() {
    if (get_option('af_bot_db_ver') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE " . af_bot_table() . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        question TEXT NOT NULL,
        intent VARCHAR(40) NOT NULL DEFAULT '',
        answered TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY answered_created (answered, created_at)
    ) " . $wpdb->get_charset_collate() . ";");
    update_option('af_bot_db_ver', '1');
}, 7);

// ── the knowledge base, built from live shop data ────────────────────

function af_bot_money($n) { return function_exists('wc_price') ? strip_tags(wc_price($n)) : ('$' . number_format($n, 2)); }

/** Cheapest real product price, so "how much" answers are never invented. */
function af_bot_from_price() {
    $cached = get_transient('af_bot_from_price');
    if ($cached !== false) return (float) $cached;
    $q = wc_get_products(array('status' => 'publish', 'limit' => 1, 'orderby' => 'meta_value_num',
        'meta_key' => '_price', 'order' => 'ASC', 'return' => 'objects'));
    $p = ($q && isset($q[0])) ? (float) $q[0]->get_price() : 0;
    set_transient('af_bot_from_price', $p, HOUR_IN_SECONDS);
    return $p;
}

function af_bot_intents() {
    $cfg    = function_exists('af_pricing_config') ? af_pricing_config() : array('sizes' => array(), 'frames' => array(), 'colors' => array());
    $sizes  = array_keys($cfg['sizes']);
    $size_prices = array_values(array_filter(array_map('floatval', $cfg['sizes'])));
    $frames = $cfg['frames'];
    $colors = $cfg['colors'];
    $home   = home_url('/');

    $frame_list = array();
    foreach ($frames as $name => $fee) $frame_list[] = $name . ($fee > 0 ? ' (+' . af_bot_money($fee) . ')' : ' (no extra cost)');
    $colour_list = array();
    foreach ($colors as $name => $fee) $colour_list[] = $name . ($fee > 0 ? ' (+' . af_bot_money($fee) . ')' : '');

    return array(
        'sizes' => array(
            'k' => array('size', 'sizes', 'dimension', 'how big', 'inches', 'feet', 'ft', 'large', 'small', 'measurement'),
            'a' => "We print " . count($sizes) . " standard sizes, from " . ($sizes ? $sizes[0] : '2×3 ft') .
                   " up to " . ($sizes ? end($sizes) : '4×6 ft') . ".\n\nThe most popular are 3×4 ft and 3×5 ft for a living-room wall, and 2×3 ft for a hallway or bedroom.\n\nNot sure what fits? Preview any piece true-to-scale on a photo of your own wall.",
            'c' => array('Try it on my wall' => home_url('/try-on-wall/'), 'See all sizes' => home_url('/shop/')),
        ),
        'frames' => array(
            'k' => array('frame', 'framed', 'framing', 'moulding', 'border', 'unframed', 'colour', 'color', 'gold', 'silver', 'black'),
            'a' => "Every piece can be ordered unframed or in one of three frames:\n• " . implode("\n• ", $frame_list) .
                   "\n\nFrame colours: " . implode(', ', $colour_list) . ".\n\nGallery-wrapped canvas (no frame) is ready to hang as it is — the image continues around the edges.",
            'c' => array('See frames on a wall' => home_url('/try-on-wall/'), 'Browse art' => home_url('/shop/')),
        ),
        'price' => array(
            'k' => array('price', 'cost', 'how much', 'expensive', 'cheap', 'budget', 'afford', 'discount', 'offer', 'sale'),
            'a' => "The size sets the price, straight from our pine-wood framing price list: " .
                   af_bot_money($size_prices ? min($size_prices) : 60) . " for a 2×3 ft up to " .
                   af_bot_money($size_prices ? max($size_prices) : 150) . " for a 4×6 ft.\n\nA frame adds a flat fee (" .
                   af_bot_money(min(array_filter($frames))) . "–" . af_bot_money(max($frames)) . "); gold and rose gold add " . af_bot_money(10) . ".\n\nThe price updates live on the product page as you choose — nothing is hidden until checkout.",
            'c' => array('Browse art' => home_url('/shop/'), 'Digital downloads' => home_url('/product-category/digital-downloads/')),
        ),
        'shipping' => array(
            'k' => array('ship', 'shipping', 'delivery', 'deliver', 'post', 'courier', 'how long', 'arrive', 'dispatch', 'tracking number', 'freight'),
            // wording comes from af_shipping_copy() so the bot cannot promise
            // something different from what the page says
            'a' => (function_exists('af_shipping_copy') ? af_shipping_copy()['blurb']
                    : 'We ship throughout the USA — the cost is shown at checkout.')
                   . "\n\nSmaller unframed prints travel rolled in a protective tube; framed and larger pieces ship flat in a corner-protected crate. Very large pieces may go by freight, and oversize handling is shown at checkout before you pay.\n\nEverything is made to order, so allow a few days for production before it ships. You get tracking by email the moment it leaves us.",
            'c' => array('Track my order' => home_url('/my-account/orders/'), 'Shipping policy' => home_url('/shipping-delivery/')),
        ),
        'returns' => array(
            'k' => array('return', 'refund', 'exchange', 'damaged', 'broken', 'cancel', 'wrong item', 'not happy', 'money back'),
            'a' => "If a piece arrives damaged or isn't what you ordered, we replace or refund it — start the return from the order in your account within 14 days of delivery and we'll arrange collection.\n\nBecause every canvas is printed for your order, change-of-mind returns are limited, but tell us what happened — we'd rather fix it than lose you.",
            'c' => array('Start a return' => home_url('/my-account/orders/'), 'Return policy' => home_url('/return-refund-policy/')),
        ),
        'order' => array(
            'k' => array('my order', 'order status', 'where is my', 'track', 'tracking', 'shipped yet', 'order number', 'delivery status'),
            'a' => "I can look that up. Sign in and open your orders — every order shows its status, tracking and invoice.\n\nIf you checked out as a guest, use the tracking link in your confirmation email, or tell our team your order number and we'll chase it.",
            'c' => array('My orders' => home_url('/my-account/orders/'), 'Track an order' => home_url('/order-tracking/')),
        ),
        'digital' => array(
            'k' => array('digital', 'download', 'downloadable', 'file', 'jpg', 'print at home', 'instant', 'printable'),
            'a' => "Most artworks are also available as an instant digital download for " . af_bot_money(af_digital_price()) . ".\n\nYou get a high-resolution, print-ready file by email straight after payment — print it at home or at any print shop. The link allows 5 downloads within 30 days.\n\nLook for the “Digital Download” option on a product card.",
            'c' => array('Digital downloads' => home_url('/product-category/digital-downloads/')),
        ),
        'custom' => array(
            'k' => array('custom', 'personalised', 'personalized', 'my photo', 'own photo', 'my picture', 'bespoke', 'frame my', 'portrait of'),
            'a' => "Yes — send us your own photograph and we'll print and frame it exactly like our own art.\n\nOn Frame The Moment you upload the photo, choose the size and frame, and see it on a real wall before you order. We'll tell you straight away if the resolution is too low for the size you picked.",
            'c' => array('Frame my photo' => home_url('/frame-the-moment/'), 'Custom orders' => home_url('/customize-your-picture/')),
        ),
        'ar' => array(
            'k' => array('try on wall', 'preview', 'see it on my wall', 'how will it look', 'ar', 'augmented', 'camera', 'room'),
            'a' => "You can see any piece on your own wall before buying.\n\nUpload a photo of the wall — or point your phone's camera at it — and the artwork appears true to scale, in the size and frame you chose. You can even preview it as a 2 or 4 panel split set.",
            'c' => array('Try it on my wall' => home_url('/try-on-wall/'), 'Frame my own photo' => home_url('/frame-the-moment/')),
        ),
        'bulk' => array(
            'k' => array('bulk', 'wholesale', 'corporate', 'office', 'hotel', 'trade', 'quantity', 'reseller', 'business', 'signage', 'banner'),
            'a' => "We do corporate and bulk work — office and hotel art programmes, signage, banners and large-format printing, with trade pricing on volume.\n\nTell our team the quantity, sizes and deadline and you'll get a quote, usually the same working day.",
            'c' => array('Corporate & bulk' => home_url('/wholesale-corporate/'), 'Contact the studio' => home_url('/contact/')),
        ),
        'payment' => array(
            'k' => array('pay', 'payment', 'card', 'paypal', 'visa', 'mastercard', 'secure', 'checkout', 'currency', 'installment'),
            'a' => "Checkout takes major cards and PayPal, over a secure encrypted connection — we never see or store your card details. Prices are shown in USD and CAD.\n\nGift cards can be redeemed at checkout too.",
            'c' => array('Gift cards' => home_url('/gift-cards/'), 'Browse art' => home_url('/shop/')),
        ),
        'care' => array(
            'k' => array('care', 'clean', 'fade', 'last', 'durable', 'quality', 'material', 'ink', 'canvas quality', 'maintain'),
            'a' => "Every piece is printed with archival pigment inks on gallery-grade canvas — colour-fast for decades indoors and away from direct sunlight.\n\nTo clean, wipe gently with a dry or barely damp soft cloth. No solvents, no household sprays.",
            'c' => array('Browse art' => home_url('/shop/')),
        ),
        'gift' => array(
            'k' => array('gift', 'present', 'voucher', 'gift card', 'birthday', 'anniversary', 'wedding'),
            'a' => "A gift card is the safe choice when you're not sure which piece they'd love — it's delivered by email and redeemed at checkout.\n\nArt makes a good wedding or housewarming gift too; devotional pieces are our most-gifted category.",
            'c' => array('Gift cards' => home_url('/gift-cards/'), 'Browse art' => home_url('/shop/')),
        ),
        'human' => array(
            'k' => array('human', 'person', 'agent', 'talk to someone', 'representative', 'call', 'phone', 'email you', 'contact', 'support'),
            'a' => "Of course — our studio team answers directly.\n\nEmail: " . af_bot_contact()['email'] .
                   "\nPhone: " . af_bot_contact()['phone'] .
                   "\n\nOr send us a message and we'll reply, usually within the hour during business hours.",
            'c' => array('Contact form' => home_url('/contact/')),
        ),
        'greeting' => array(
            'k' => array('hi', 'hello', 'hey', 'good morning', 'good evening', 'namaste', 'yo', 'hola'),
            'a' => "Hello! 👋 I'm the Art Framer assistant.\n\nI can help you find a piece, explain sizes, frames and prices, check on delivery, or set up a custom print of your own photo. What are you looking for?",
            'c' => array(),
        ),
        'thanks' => array(
            'k' => array('thank', 'thanks', 'cheers', 'appreciate', 'great', 'perfect', 'awesome'),
            'a' => "Happy to help! If you'd like, I can show you pieces for a particular room or theme — just tell me what you're after.",
            'c' => array('Browse art' => home_url('/shop/')),
        ),
    );
}

/** Score the message against every intent; highest wins. */
function af_bot_match($msg) {
    $m    = ' ' . strtolower(trim($msg)) . ' ';
    $best = ''; $score = 0;
    foreach (af_bot_intents() as $key => $intent) {
        $s = 0;
        foreach ($intent['k'] as $kw) {
            if (strpos($m, ' ' . $kw . ' ') !== false)      $s += 3;   // whole word
            elseif (strpos($m, $kw) !== false)              $s += 2;   // inside a word
        }
        // a bare greeting shouldn't beat a real question that happens to say "hi"
        if ($key === 'greeting' && str_word_count($m) > 4) $s = max(0, $s - 3);
        if ($s > $score) { $score = $s; $best = $key; }
    }
    return $score >= 2 ? $best : '';
}

/** Search the real catalogue so product questions get buyable answers. */
function af_bot_products($msg, $limit = 3) {
    $stop = array('show','me','some','the','a','an','of','for','and','do','you','have','any','i','want','looking','art','artwork','canvas','print','prints','wall','need','buy','get','find','with','please','can');
    $words = array_filter(preg_split('/[^a-z0-9]+/i', strtolower($msg)), function($w) use ($stop) {
        return strlen($w) >= 3 && !in_array($w, $stop, true);
    });
    if (!$words) return array();
    $q = wc_get_products(array(
        'status' => 'publish', 'limit' => $limit, 'orderby' => 'relevance',
        's' => implode(' ', array_slice($words, 0, 4)),
    ));
    $out = array();
    foreach ($q as $p) {
        if (!$p->get_image_id()) continue;
        $out[] = array(
            'name'  => wp_trim_words($p->get_name(), 8, '…'),
            'price' => strip_tags(wc_price(wc_get_price_to_display($p))),
            'img'   => wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail'),
            'url'   => get_permalink($p->get_id()),
        );
    }
    return $out;
}

// ── the endpoint ─────────────────────────────────────────────────────
function af_bot_reply_handler() {
    check_ajax_referer('af_bot', 'nonce');
    $msg = isset($_POST['msg']) ? sanitize_textarea_field(wp_unslash($_POST['msg'])) : '';
    $msg = trim(mb_substr($msg, 0, 400));
    if ($msg === '') wp_send_json_error();

    // light rate limit: this endpoint is public
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    $key = 'af_bot_rl_' . md5($ip);
    $n   = (int) get_transient($key);
    if ($n > 40) wp_send_json_error(array('reply' => 'Let’s slow down a moment — try again shortly, or email the studio.'));
    set_transient($key, $n + 1, HOUR_IN_SECONDS);

    $intent   = af_bot_match($msg);
    $intents  = af_bot_intents();
    $products = array();
    $answered = 1;

    if ($intent && isset($intents[$intent])) {
        $reply = $intents[$intent]['a'];
        $chips = $intents[$intent]['c'];
        // a themed question ("radha krishna canvas") deserves real pieces too
        if (in_array($intent, array('price', 'sizes', 'frames', 'gift'), true)) {
            $products = af_bot_products($msg);
        }
    } else {
        $products = af_bot_products($msg);
        if ($products) {
            $reply = "Here's what I found in the collection — tap any piece to see its sizes, frames and price:";
            $chips = array('See more' => home_url('/shop/'), 'Try on my wall' => home_url('/try-on-wall/'));
        } else {
            $answered = 0;
            $c = af_bot_contact();
            $reply = "I don't have a good answer for that one yet — I'd rather send you to a person than guess.\n\nEmail " .
                     $c['email'] . " or call " . $c['phone'] . " and the studio will help.";
            $chips = array('Contact the studio' => home_url('/contact/'), 'Browse art' => home_url('/shop/'));
        }
    }

    global $wpdb;
    $wpdb->insert(af_bot_table(), array(
        'question' => $msg, 'intent' => $intent, 'answered' => $answered, 'created_at' => current_time('mysql'),
    ));

    wp_send_json_success(array('reply' => $reply, 'chips' => $chips, 'products' => $products));
}
add_action('wp_ajax_af_bot_reply',        'af_bot_reply_handler');
add_action('wp_ajax_nopriv_af_bot_reply', 'af_bot_reply_handler');

// ── admin: what people asked that the bot could not answer ───────────
add_action('admin_menu', function() {
    add_submenu_page('woocommerce', 'Chatbot Questions', 'Chatbot Questions', 'manage_woocommerce', 'af-chatbot', function() {
        if (!current_user_can('manage_woocommerce')) return;
        global $wpdb; $t = af_bot_table();
        $tot  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");
        $miss = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE answered=0");
        echo '<div class="wrap"><h1>Chatbot Questions</h1>';
        printf('<p>%d questions asked · <strong>%d unanswered</strong> (%s%% answered). Every unanswered question is a gap worth filling.</p>',
            $tot, $miss, $tot ? number_format(($tot - $miss) / $tot * 100, 1) : '100');
        echo '<h2>Unanswered</h2><table class="widefat striped"><thead><tr><th>When</th><th>Question</th></tr></thead><tbody>';
        $rows = $wpdb->get_results("SELECT * FROM {$t} WHERE answered=0 ORDER BY id DESC LIMIT 50");
        if (!$rows) echo '<tr><td colspan="2">None — the bot answered everything so far.</td></tr>';
        foreach ($rows as $r) echo '<tr><td>' . esc_html($r->created_at) . '</td><td>' . esc_html($r->question) . '</td></tr>';
        echo '</tbody></table>';
        echo '<h2>Most common topics</h2><table class="widefat striped"><thead><tr><th>Topic</th><th>Asked</th></tr></thead><tbody>';
        $top = $wpdb->get_results("SELECT intent, COUNT(*) n FROM {$t} WHERE intent<>'' GROUP BY intent ORDER BY n DESC LIMIT 15");
        if (!$top) echo '<tr><td colspan="2">Nothing yet.</td></tr>';
        foreach ($top as $r) echo '<tr><td>' . esc_html($r->intent) . '</td><td>' . (int) $r->n . '</td></tr>';
        echo '</tbody></table></div>';
    });
});

// ── the widget ───────────────────────────────────────────────────────
add_action('wp_footer', function() {
    if (is_admin()) return;
    $contact = af_bot_contact();
    $email   = $contact['email'];
    ?>
    <div id="af-chat" class="af-chat">
      <button type="button" class="af-chat-bub" id="af-chat-open" aria-label="Chat with the Art Framer assistant">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true">
          <path d="M12 3C7 3 3 6.6 3 11c0 2.2 1 4.1 2.7 5.5-.2 1-.7 2.2-1.5 3.1 1.7-.2 3.2-.8 4.3-1.5 1.1.4 2.3.6 3.5.6 5 0 9-3.6 9-8S17 3 12 3z"/>
        </svg>
        <span class="af-chat-dot" aria-hidden="true"></span>
      </button>

      <div class="af-chat-panel" id="af-chat-panel" hidden>
        <div class="af-chat-head">
          <strong>Art Framer Assistant</strong>
          <span id="af-chat-status">Online · answers instantly</span>
          <button type="button" id="af-chat-close" aria-label="Close chat">✕</button>
        </div>

        <div class="af-chat-thread" id="af-chat-thread" role="log" aria-live="polite"></div>

        <div class="af-chat-chips" id="af-chat-chips">
          <button type="button" data-q="What sizes do you offer?">📏 Sizes</button>
          <button type="button" data-q="What frames and colours can I choose?">🖼 Frames</button>
          <button type="button" data-q="How much does it cost?">💲 Pricing</button>
          <button type="button" data-q="How long does delivery take?">🚚 Delivery</button>
          <button type="button" data-q="Can I frame my own photo?">📸 My own photo</button>
          <button type="button" data-q="Where is my order?">📦 My order</button>
        </div>

        <form class="af-chat-form" id="af-chat-form">
          <input type="text" id="af-chat-text" maxlength="400" autocomplete="off"
                 placeholder="Ask me anything…" aria-label="Your message">
          <button type="submit" aria-label="Send">➤</button>
        </form>
        <p class="af-chat-alt">Prefer a person? <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
          &nbsp;·&nbsp; <a href="<?php echo esc_attr($contact['tel']); ?>"><?php echo esc_html($contact['phone']); ?></a></p>
      </div>
    </div>

    <style>
    .af-chat{position:fixed;left:18px;bottom:18px;z-index:99990;font-family:inherit;}
    .af-chat-bub{position:relative;width:56px;height:56px;border-radius:50%;border:0;cursor:pointer;
      background:#1a1a1a;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.32);display:flex;align-items:center;justify-content:center;
      transition:transform .15s;}
    .af-chat-bub:hover{transform:scale(1.07);}
    .af-chat-dot{position:absolute;top:3px;right:3px;width:11px;height:11px;border-radius:50%;background:#25a366;border:2px solid #fff;}
    .af-chat-panel{position:absolute;left:0;bottom:70px;width:356px;max-width:calc(100vw - 36px);
      background:#fffdf8;border:1px solid #e6dcc4;border-radius:16px;box-shadow:0 18px 50px rgba(40,30,10,.25);
      overflow:hidden;display:flex;flex-direction:column;max-height:min(74vh,560px);}
    /* display:flex above outranks the browser's [hidden] rule, so hiding the
       panel needs saying explicitly — without this the close button fires and
       the panel stays on screen. */
    .af-chat-panel[hidden]{display:none !important;}
    .af-chat-head{background:#1a1a1a;color:#fff;padding:14px 44px 12px 16px;position:relative;flex:0 0 auto;}
    .af-chat-head strong{display:block;font-size:14px;}
    .af-chat-head span{font-size:11px;color:#9fd8b8;}
    .af-chat-head button{position:absolute;top:10px;right:10px;background:none;border:0;color:#cbc2ac;font-size:15px;cursor:pointer;}
    .af-chat-thread{flex:1 1 auto;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:9px;min-height:150px;}
    .af-chat-msg{max-width:86%;padding:10px 12px;font-size:13px;line-height:1.55;border-radius:13px;white-space:pre-line;}
    .af-chat-bot{align-self:flex-start;background:#f2ecdd;color:#3d342a;border-bottom-left-radius:3px;}
    .af-chat-me{align-self:flex-end;background:#1a1a1a;color:#fff;border-bottom-right-radius:3px;}
    .af-chat-links{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start;max-width:92%;}
    .af-chat-links a{font-size:11.5px;font-weight:700;color:#a8872e;background:#fffdf8;border:1.5px solid #e2d9c4;
      border-radius:999px;padding:6px 11px;text-decoration:none;}
    .af-chat-links a:hover{border-color:#c9a84c;}
    .af-chat-cards{display:flex;flex-direction:column;gap:6px;align-self:flex-start;width:92%;}
    .af-chat-card{display:flex;gap:9px;align-items:center;border:1px solid #ece4cf;border-radius:11px;
      padding:7px 9px;background:#fffdf8;text-decoration:none;}
    .af-chat-card:hover{border-color:#c9a84c;}
    .af-chat-card img{width:42px;height:42px;border-radius:8px;object-fit:cover;flex:0 0 auto;}
    .af-chat-card b{display:block;font-size:12px;color:#1a1a1a;line-height:1.35;font-weight:600;}
    .af-chat-card em{font-style:normal;font-size:12px;color:#a8872e;font-weight:700;}
    .af-chat-typing{align-self:flex-start;display:flex;gap:4px;padding:11px 13px;background:#f2ecdd;border-radius:13px;}
    .af-chat-typing i{width:6px;height:6px;border-radius:50%;background:#b3a88c;animation:afbob 1s infinite;}
    .af-chat-typing i:nth-child(2){animation-delay:.15s;} .af-chat-typing i:nth-child(3){animation-delay:.3s;}
    @keyframes afbob{0%,60%,100%{transform:translateY(0);opacity:.5;}30%{transform:translateY(-4px);opacity:1;}}
    .af-chat-chips{display:flex;flex-wrap:wrap;gap:6px;padding:0 14px 10px;flex:0 0 auto;}
    .af-chat-chips button{border:1.5px solid #e2d9c4;background:#fff;border-radius:999px;font-size:11.5px;font-weight:600;
      color:#5a5140;padding:7px 11px;cursor:pointer;}
    .af-chat-chips button:hover{border-color:#c9a84c;color:#a8872e;}
    .af-chat-form{display:flex;gap:6px;padding:0 14px 8px;flex:0 0 auto;}
    .af-chat-form input{flex:1;min-width:0;padding:10px 12px;border:1.5px solid #e2d9c4;border-radius:10px;font-size:13px;background:#fff;}
    .af-chat-form input:focus{outline:none;border-color:#c9a84c;}
    .af-chat-form button{width:42px;border:0;border-radius:10px;background:#1a1a1a;color:#fff;font-size:15px;cursor:pointer;}
    .af-chat-form button:hover{background:#000;}
    .af-chat-alt{margin:0;padding:0 14px 12px;font-size:11px;color:#8a8170;flex:0 0 auto;}
    .af-chat-alt a{color:#a8872e;}
    @media(max-width:600px){.af-chat{left:12px;bottom:76px;}.af-chat-panel{max-height:70vh;}}
    </style>

    <script>
    (function(){
      var AJAX  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
      var NONCE = <?php echo wp_json_encode(wp_create_nonce('af_bot')); ?>;
      var panel  = document.getElementById('af-chat-panel');
      var thread = document.getElementById('af-chat-thread');
      var input  = document.getElementById('af-chat-text');
      var greeted = false;

      function scroll(){ thread.scrollTop = thread.scrollHeight; }
      function bubble(text, who){
        var d = document.createElement('div');
        d.className = 'af-chat-msg ' + (who === 'me' ? 'af-chat-me' : 'af-chat-bot');
        d.textContent = text;
        thread.appendChild(d); scroll(); return d;
      }
      function links(map){
        var keys = Object.keys(map || {});
        if (!keys.length) return;
        var w = document.createElement('div'); w.className = 'af-chat-links';
        keys.forEach(function(label){
          var a = document.createElement('a');
          a.href = map[label]; a.textContent = label;
          w.appendChild(a);
        });
        thread.appendChild(w); scroll();
      }
      function cards(list){
        if (!list || !list.length) return;
        var w = document.createElement('div'); w.className = 'af-chat-cards';
        list.forEach(function(p){
          var a = document.createElement('a'); a.className = 'af-chat-card'; a.href = p.url;
          var im = document.createElement('img'); im.src = p.img; im.alt = '';
          var t  = document.createElement('span');
          var b  = document.createElement('b'); b.textContent = p.name;
          var e  = document.createElement('em'); e.textContent = p.price;
          t.appendChild(b); t.appendChild(e);
          a.appendChild(im); a.appendChild(t); w.appendChild(a);
        });
        thread.appendChild(w); scroll();
      }
      function typing(on){
        var t = document.getElementById('af-chat-typing');
        if (on && !t){
          var d = document.createElement('div'); d.className = 'af-chat-typing'; d.id = 'af-chat-typing';
          d.innerHTML = '<i></i><i></i><i></i>';
          thread.appendChild(d); scroll();
        } else if (!on && t){ t.remove(); }
      }

      function ask(msg){
        bubble(msg, 'me');
        typing(true);
        var body = new URLSearchParams();
        body.set('action','af_bot_reply'); body.set('nonce', NONCE); body.set('msg', msg);
        fetch(AJAX, {method:'POST', credentials:'same-origin', body:body})
          .then(function(r){ return r.json(); })
          .then(function(j){
            // a beat of "typing" reads as considered rather than canned
            setTimeout(function(){
              typing(false);
              if (j && j.success && j.data){
                bubble(j.data.reply, 'bot');
                cards(j.data.products);
                links(j.data.chips);
              } else {
                bubble('Sorry — something went wrong on my side. Email the studio and they will help.', 'bot');
              }
            }, 380);
          })
          .catch(function(){
            typing(false);
            bubble('I could not reach the studio just now. Please try again in a moment.', 'bot');
          });
      }

      function greet(){
        if (greeted) return; greeted = true;
        bubble('Hello! 👋 I am the Art Framer assistant.\nAsk me about sizes, frames, prices or delivery — or tell me what art you are looking for and I will find it.', 'bot');
      }

      // Belt and braces. The stylesheet rule alone already failed once here
      // (the panel's display:flex outranked [hidden]), and a cached or
      // overridden stylesheet could do it again — so closing also forces the
      // strongest thing JS can set, which no ordinary rule can beat.
      function openPanel(){
        panel.hidden = false;
        panel.style.setProperty('display', 'flex', 'important');
        greet();
        setTimeout(function(){ input.focus(); }, 60);
      }
      function closePanel(){
        panel.hidden = true;
        panel.style.setProperty('display', 'none', 'important');
      }
      closePanel();   // never sit open on load, whatever the CSS says

      document.getElementById('af-chat-open').addEventListener('click', function(e){
        e.stopPropagation();
        panel.hidden ? openPanel() : closePanel();
      });
      document.getElementById('af-chat-close').addEventListener('click', function(e){
        e.preventDefault(); e.stopPropagation(); closePanel();
      });
      // the other two ways people expect to dismiss a chat panel
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closePanel(); });
      document.addEventListener('click', function(e){
        if (!panel.hidden && !e.target.closest('#af-chat')) closePanel();
      });
      document.getElementById('af-chat-chips').addEventListener('click', function(e){
        var b = e.target.closest('button'); if (!b) return;
        ask(b.getAttribute('data-q'));
      });
      document.getElementById('af-chat-form').addEventListener('submit', function(e){
        e.preventDefault();
        var v = input.value.trim(); if (!v) return;
        input.value = ''; ask(v);
      });
    })();
    </script>
    <?php
}, 62);
