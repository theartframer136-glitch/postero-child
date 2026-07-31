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
 *     marketing category being off, reachable from the banner and the footer
 *   • a "Cookie preferences" footer link reopens the panel at any time
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
    /* footer reopen links — pills, not bare underlined text */
    .af-ck-footwrap{display:flex;gap:10px;justify-content:center;align-items:center;flex-wrap:wrap;
      margin:18px auto 6px;padding:14px 16px 0;border-top:1px solid rgba(150,135,100,.18);max-width:720px;}
    .af-ck-foot{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;letter-spacing:.02em;
      color:#6b6250;background:#fffdf8;border:1.5px solid #e2d9c4;border-radius:999px;padding:9px 16px;cursor:pointer;
      text-transform:none;text-decoration:none;line-height:1;transition:border-color .15s,color .15s,box-shadow .15s;}
    .af-ck-foot:hover{border-color:#c9a84c;color:#a8872e;box-shadow:0 4px 14px rgba(70,54,26,.10);}
    .af-ck-foot .af-ck-ico{font-size:14px;line-height:1;}
    @media(max-width:600px){.af-ck-card{padding:14px;}.af-ck-actions{width:100%;}}
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
      if (have) { hookFooter(); return; }        // already answered — stay hidden
      box.hidden = false;

      var opts = box.querySelector('.af-ck-opts');
      function finish(c){ write(c); box.hidden = true; hookFooter(); }
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

      // footer links to reopen the panel (added once, wherever the footer menu is)
      function hookFooter(){
        if (document.getElementById('af-ck-reopen')) return;
        var host = document.querySelector('.site-footer .footer-bottom, footer .copyright, footer');
        if (!host) return;
        function reopen(){
          var c = read() || {};
          document.getElementById('af-ck-analytics').checked = !!c.analytics;
          document.getElementById('af-ck-marketing').checked = !!c.marketing;
          opts.hidden = false;
          document.getElementById('af-ck-prefs').hidden = true;
          document.getElementById('af-ck-save').hidden = false;
          box.hidden = false;
        }
        function pill(id, icon, label){
          var b = document.createElement('button');
          b.type = 'button'; b.id = id; b.className = 'af-ck-foot';
          b.innerHTML = '<span class="af-ck-ico" aria-hidden="true">' + icon + '</span>' + label;
          b.addEventListener('click', reopen);
          return b;
        }
        var wrap = document.createElement('div');
        wrap.className = 'af-ck-footwrap';
        wrap.appendChild(pill('af-ck-reopen', '🍪', 'Cookie Preferences'));
        wrap.appendChild(pill('af-ck-ccpa', '🔒', 'Do Not Sell or Share My Personal Information'));
        host.appendChild(wrap);
      }
      hookFooter();
    })();
    </script>
    <?php
}, 60);
