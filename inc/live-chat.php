<?php
/**
 * Live chat widget  (requirements §5 quick-access / audit item 15)
 *
 * A real launcher, not a bare link: bubble bottom-left (the existing
 * quick-access panel owns the right edge), opening a panel with quick
 * topics and a message box. Sending hands the typed message to WhatsApp,
 * with email as the fallback — the channels the studio actually staffs.
 */
if (!defined('ABSPATH')) exit;

function af_chat_wa_number() { return apply_filters('af_chat_wa_number', '16104707280'); }

add_action('wp_footer', function() {
    if (is_admin()) return;
    $wa    = af_chat_wa_number();
    $email = get_option('woocommerce_email_from_address', get_option('admin_email'));
    ?>
    <div id="af-chat" class="af-chat">
      <button type="button" class="af-chat-bub" id="af-chat-open" aria-label="Chat with us">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true">
          <path d="M12 3C7 3 3 6.6 3 11c0 2.2 1 4.1 2.7 5.5-.2 1-.7 2.2-1.5 3.1 1.7-.2 3.2-.8 4.3-1.5 1.1.4 2.3.6 3.5.6 5 0 9-3.6 9-8S17 3 12 3z"/>
        </svg>
        <span class="af-chat-dot" aria-hidden="true"></span>
      </button>
      <div class="af-chat-panel" id="af-chat-panel" hidden>
        <div class="af-chat-head">
          <strong>The Art Framer</strong>
          <span>Usually replies within the hour</span>
          <button type="button" id="af-chat-close" aria-label="Close chat">✕</button>
        </div>
        <div class="af-chat-body">
          <p class="af-chat-msg">Hi! 👋 How can we help — a piece you love, a custom frame, or an order on its way?</p>
          <div class="af-chat-chips">
            <button type="button" data-msg="Hi! I have a question about an order I placed.">📦 My order</button>
            <button type="button" data-msg="Hi! I would like help choosing art for my wall.">🖼 Help me choose</button>
            <button type="button" data-msg="Hi! I am interested in custom framing / Frame The Moment.">✂️ Custom framing</button>
            <button type="button" data-msg="Hi! I would like a quote for a bulk / corporate order.">🏢 Bulk order</button>
          </div>
          <form class="af-chat-form" id="af-chat-form">
            <input type="text" id="af-chat-text" maxlength="500" placeholder="Type your message…" aria-label="Your message">
            <button type="submit" aria-label="Send on WhatsApp">➤</button>
          </form>
          <p class="af-chat-alt">Prefer email? <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
        </div>
      </div>
    </div>
    <style>
    .af-chat{position:fixed;left:18px;bottom:18px;z-index:99990;font-family:inherit;}
    .af-chat-bub{position:relative;width:56px;height:56px;border-radius:50%;border:0;cursor:pointer;
      background:#1a1a1a;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.32);display:flex;align-items:center;justify-content:center;
      transition:transform .15s;}
    .af-chat-bub:hover{transform:scale(1.07);}
    .af-chat-dot{position:absolute;top:3px;right:3px;width:11px;height:11px;border-radius:50%;background:#25a366;border:2px solid #fff;}
    .af-chat-panel{position:absolute;left:0;bottom:70px;width:320px;max-width:calc(100vw - 36px);
      background:#fffdf8;border:1px solid #e6dcc4;border-radius:16px;box-shadow:0 18px 50px rgba(40,30,10,.25);overflow:hidden;}
    .af-chat-head{background:#1a1a1a;color:#fff;padding:14px 44px 12px 16px;position:relative;}
    .af-chat-head strong{display:block;font-size:14px;}
    .af-chat-head span{font-size:11px;color:#cbc2ac;}
    .af-chat-head button{position:absolute;top:10px;right:10px;background:none;border:0;color:#cbc2ac;font-size:15px;cursor:pointer;}
    .af-chat-body{padding:14px;}
    .af-chat-msg{background:#f2ecdd;border-radius:12px 12px 12px 3px;padding:10px 12px;font-size:13px;line-height:1.5;color:#3d342a;margin:0 0 10px;}
    .af-chat-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
    .af-chat-chips button{border:1.5px solid #e2d9c4;background:#fff;border-radius:999px;font-size:12px;font-weight:600;
      color:#5a5140;padding:7px 11px;cursor:pointer;}
    .af-chat-chips button:hover{border-color:#25a366;color:#1e8b56;}
    .af-chat-form{display:flex;gap:6px;}
    .af-chat-form input{flex:1;min-width:0;padding:10px 12px;border:1.5px solid #e2d9c4;border-radius:10px;font-size:13px;background:#fff;}
    .af-chat-form input:focus{outline:none;border-color:#25a366;}
    .af-chat-form button{width:42px;border:0;border-radius:10px;background:#25a366;color:#fff;font-size:16px;cursor:pointer;}
    .af-chat-alt{margin:10px 0 0;font-size:11.5px;color:#8a8170;}
    .af-chat-alt a{color:#a8872e;}
    @media(max-width:600px){.af-chat{left:12px;bottom:76px;}}
    </style>
    <script>
    (function(){
      var wa = <?php echo wp_json_encode($wa); ?>;
      var panel = document.getElementById('af-chat-panel');
      function send(msg){
        msg = (msg || '').trim();
        if (!msg) return;
        window.open('https://wa.me/' + wa + '?text=' + encodeURIComponent(msg + ' (via theartframer.us)'), '_blank', 'noopener');
      }
      document.getElementById('af-chat-open').addEventListener('click', function(){ panel.hidden = !panel.hidden; });
      document.getElementById('af-chat-close').addEventListener('click', function(){ panel.hidden = true; });
      panel.addEventListener('click', function(e){
        var chip = e.target.closest('.af-chat-chips button');
        if (chip) send(chip.getAttribute('data-msg'));
      });
      document.getElementById('af-chat-form').addEventListener('submit', function(e){
        e.preventDefault();
        var t = document.getElementById('af-chat-text');
        send(t.value); t.value = '';
      });
    })();
    </script>
    <?php
}, 62);
