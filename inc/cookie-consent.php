<?php
/**
 * Cookie consent — GDPR & CCPA  (requirements §17)
 *
 * A first-visit banner with real choices, not a decoration:
 *   • three categories — necessary (always on), analytics, marketing
 *   • Accept all / Necessary only / a preferences panel per category
 *   • the choice lives in a first-party cookie for a year, and pages expose
 *     window.afConsent + an "af-consent" event, so any analytics or pixel
 *     added later loads only after the visitor allowed its category
 *   • CCPA: "Do Not Sell or Share My Personal Information" maps to the
 *     marketing category being off, offered in the banner's own panel
 *   • nothing is added to the footer. The two pills that used to sit there
 *     were removed at the owner's request; window.afConsentReopen() still
 *     opens the panel, for a footer link to call when one is wanted.
 */
if (!defined('ABSPATH')) exit;

function af_consent_cookie() { return 'af_consent'; }

add_action('wp_footer', function() {
    if (is_admin()) return;
    $privacy = get_privacy_policy_url();
    if (!$privacy) $privacy = home_url('/privacy-policy/');
    ?>
    <div id="af-consent" class="af-ck" hidden>
      <div class="af-ck-card" role="dialog" aria-modal="false" aria-label="Cookie preferences">
        <div class="af-ck-main">
          <strong>We value your privacy</strong>
          <p>We use cookies to run the shop and — only with your permission — to understand
             how it is used and to personalise offers.
             See our <a href="<?php echo esc_url($privacy); ?>">Privacy Policy</a>.</p>
          <div class="af-ck-opts" hidden>
            <label><input type="checkbox" checked disabled> <b>Necessary</b> — cart, checkout, sign-in. Always on.</label>
            <label><input type="checkbox" id="af-ck-analytics"> <b>Analytics</b> — anonymous usage statistics.</label>
            <label><input type="checkbox" id="af-ck-marketing"> <b>Marketing</b> — personalised ads and retargeting.
              <small>Leaving this off is your CCPA “Do&nbsp;Not&nbsp;Sell&nbsp;or&nbsp;Share” opt-out.</small></label>
          </div>
        </div>
        <div class="af-ck-actions">
          <button type="button" id="af-ck-accept" class="af-ck-btn solid">Accept all</button>
          <button type="button" id="af-ck-necessary" class="af-ck-btn">Necessary only</button>
          <button type="button" id="af-ck-prefs" class="af-ck-btn ghost">Preferences</button>
          <button type="button" id="af-ck-save" class="af-ck-btn solid" hidden>Save my choices</button>
        </div>
      </div>
    </div>
    <style>
    .af-ck{position:fixed;left:0;right:0;bottom:0;z-index:99999;padding:14px;pointer-events:none;}
    .af-ck-card{pointer-events:auto;max-width:860px;margin:0 auto;background:#fffdf8;border:1px solid #e6dcc4;border-radius:16px;
      box-shadow:0 -8px 40px rgba(40,30,10,.18);padding:18px 20px;display:flex;gap:18px;align-items:center;flex-wrap:wrap;}
    .af-ck-main{flex:1 1 340px;min-width:0;}
    .af-ck-main strong{font-size:15px;color:#1a1a1a;}
    .af-ck-main p{margin:4px 0 0;font-size:12.5px;line-height:1.55;color:#5a5140;}
    .af-ck-main a{color:#a8872e;}
    .af-ck-opts{margin-top:10px;display:flex;flex-direction:column;gap:7px;}
    .af-ck-opts label{font-size:12.5px;color:#3d342a;display:block;line-height:1.5;}
    .af-ck-opts small{display:block;color:#8a8170;margin-left:22px;}
    .af-ck-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .af-ck-btn{border:1.5px solid #d9cfb4;background:#fff;color:#5a5140;font-size:12.5px;font-weight:700;
      padding:10px 16px;border-radius:10px;cursor:pointer;}
    .af-ck-btn:hover{border-color:#c9a84c;}
    .af-ck-btn.solid{background:#1a1a1a;border-color:#1a1a1a;color:#fff;}
    .af-ck-btn.solid:hover{background:#000;}
    .af-ck-btn.ghost{background:transparent;}
    /* ── PHONES ───────────────────────────────────────────────────────────
       Measured 2026-09-10 at 420x900: this banner rendered 377px tall — over
       forty per cent of the screen — pinned to bottom:0, which put it squarely
       on top of the site's bottom navigation bar AND the chat launcher. A
       first-time visitor on a phone could not reach Shop, Account, Search or
       Wishlist until they had answered it.

       So on a phone it sits ABOVE the bottom bar rather than over it, and it
       is capped at 62% of the screen with its own scroll — a consent notice
       may be long, but it must never be the whole page, and it must never
       cover the navigation. env(safe-area-inset-bottom) keeps it clear of the
       home indicator on an iPhone. */
    @media(max-width:600px){
      .af-ck{padding:10px;bottom:calc(65px + env(safe-area-inset-bottom,0px));}
      .af-ck-card{padding:13px 14px;gap:10px;border-radius:14px;
        max-height:min(62vh,430px);overflow-y:auto;-webkit-overflow-scrolling:touch;}
      .af-ck-main{flex:1 1 100%;}
      .af-ck-main strong{font-size:14px;}
      .af-ck-main p{margin:3px 0 0;font-size:12px;line-height:1.5;}
      .af-ck-opts{margin-top:8px;gap:5px;}
      .af-ck-opts label{font-size:12px;line-height:1.45;}
      .af-ck-opts small{margin-left:20px;line-height:1.4;}
      /* Full-width buttons in a row that wraps, so no action is ever a
         28px-tall sliver at the edge of the card. */
      .af-ck-actions{width:100%;gap:7px;}
      .af-ck-btn{flex:1 1 120px;padding:12px 14px;font-size:12.5px;}
    }
    </style>
    <script>
    (function(){
      var NAME = <?php echo wp_json_encode(af_consent_cookie()); ?>;
      function read(){
        var m = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
        if (!m) return null;
        try { return JSON.parse(decodeURIComponent(m[1])); } catch(e){ return null; }
      }
      function write(c){
        c.necessary = true; c.ts = Date.now();
        document.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(c)) +
          '; path=/; max-age=' + (86400*365) + '; SameSite=Lax' +
          (location.protocol === 'https:' ? '; Secure' : '');
        window.afConsent = c;
        try { document.dispatchEvent(new CustomEvent('af-consent', { detail: c })); } catch(e){}
        // any script shipped as type="text/plain" with data-consent="<category>"
        // is activated the moment its category is allowed
        document.querySelectorAll('script[type="text/plain"][data-consent]').forEach(function(s){
          if (!c[s.getAttribute('data-consent')]) return;
          var n = document.createElement('script');
          if (s.src) n.src = s.src; else n.textContent = s.textContent;
          s.parentNode.replaceChild(n, s);
        });
      }
      var box = document.getElementById('af-consent');
      if (!box) return;
      var have = read();
      window.afConsent = have || { necessary:true, analytics:false, marketing:false };

      // Wired for everyone, before the early return below, because a visitor
      // who has already answered is exactly the one who needs the panel to
      // work when something reopens it. This used to sit after that return,
      // so on a repeat visit opts was undefined and Save had no handler.
      var opts = box.querySelector('.af-ck-opts');
      function finish(c){ write(c); box.hidden = true; }
      document.getElementById('af-ck-accept').addEventListener('click', function(){
        finish({ analytics:true, marketing:true });
      });
      document.getElementById('af-ck-necessary').addEventListener('click', function(){
        finish({ analytics:false, marketing:false });
      });
      document.getElementById('af-ck-prefs').addEventListener('click', function(){
        opts.hidden = false; this.hidden = true;
        document.getElementById('af-ck-save').hidden = false;
      });
      document.getElementById('af-ck-save').addEventListener('click', function(){
        finish({
          analytics: document.getElementById('af-ck-analytics').checked,
          marketing: document.getElementById('af-ck-marketing').checked
        });
      });

      /**
       * Reopening the panel after the first visit.
       *
       * Two pills used to be appended to the footer here — "Cookie Preferences"
       * and "Do Not Sell or Share My Personal Information" — and the owner
       * asked for them to go. Nothing is drawn now.
       *
       * The way back is kept, just not shown: window.afConsentReopen() opens
       * the panel with the visitor's saved choices filled in. Wire it to a link
       * in the footer menu whenever one is wanted —
       *
       *     <a href="#" onclick="afConsentReopen();return false">Cookie preferences</a>
       *
       * — rather than reinstating the pills. It matters because a visitor who
       * has already chosen has no other route back into the panel: the banner
       * shows once and then never again, so with nothing calling this, consent
       * can be given but not withdrawn or changed.
       */
      window.afConsentReopen = function(){
        var c = read() || {};
        document.getElementById('af-ck-analytics').checked = !!c.analytics;
        document.getElementById('af-ck-marketing').checked = !!c.marketing;
        opts.hidden = false;
        document.getElementById('af-ck-prefs').hidden = true;
        document.getElementById('af-ck-save').hidden = false;
        box.hidden = false;
      };

      if (have) return;                          // already answered — stay hidden
      box.hidden = false;
    })();
    </script>
    <?php
}, 60);
