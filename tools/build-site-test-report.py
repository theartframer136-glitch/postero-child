#!/usr/bin/env python3
"""
Build docs/The-Art-Framer-Site-Test-26-Oct-2026.pdf from the results of the
full site test of 2026-10-26 (Audit Site runs 35 and 36, QA personas runs 190
and 191). Writes the HTML next to the PDF; Chromium (Playwright) prints it.

Run: python3 tools/build-site-test-report.py
"""
import html
import os
import subprocess

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_HTML = os.path.join(ROOT, 'docs', 'The-Art-Framer-Site-Test-26-Oct-2026.html')
OUT_PDF = os.path.join(ROOT, 'docs', 'The-Art-Framer-Site-Test-26-Oct-2026.pdf')

e = html.escape


def table(headers, rows, widths=None):
    th = ''.join(f'<th{" style=\"width:%s\"" % widths[i] if widths else ""}>{e(h)}</th>' for i, h in enumerate(headers))
    trs = []
    for r in rows:
        tds = ''.join(f'<td>{c if c.startswith("<") else e(c)}</td>' for c in r)
        trs.append(f'<tr>{tds}</tr>')
    return f'<table><thead><tr>{th}</tr></thead><tbody>{"".join(trs)}</tbody></table>'


def sev(s):
    cls = {'High': 'high', 'Medium': 'med', 'Low': 'low', 'Info': 'info'}[s]
    return f'<span class="pill {cls}">{s}</span>'


# ---------------------------------------------------------------- content

JOURNEY = [
    ('Arrives on the home page', 'A cookie notice covers a third of the screen on desktop and two thirds on a phone. "Necessary only" closes it and it stays closed.', 'OK'),
    ('Opens a category from the menu', 'Digital Canvas Prints opened with 12 pieces on the first screen. Sorting by price (low to high) works and the order is right.', 'OK'),
    ('Opens a product', 'Price shown on the first screen on desktop. Choosing another size updates the price ($60 to $100).', 'OK'),
    ('Opens a product on a phone', 'The price and the Add to Cart button are below the first screen, but a buy bar pinned to the bottom of the screen keeps both in reach and shows the same price as the page.', 'Note'),
    ('Adds to cart', 'Clear confirmation with a View Cart link. The cart shows one line and a total.', 'OK'),
    ('Goes to checkout as a guest', 'Checkout opens with 17 fields. Delivery cost appears once a US address is typed ($23.92 for the test address). Four ways to pay: Zelle, credit card, Cash App Pay, cash on delivery.', 'OK'),
    ('Looks for the small print', 'Returns, shipping, contact, privacy and terms are all linked from the checkout footer.', 'OK'),
    ('Creates an account and comes back', 'Every My Account page opens. A saved address is kept and fills checkout. The wishlist survives a reload. Log out works.', 'OK'),
    ('Searches', '"canvas" returns 12 products; a nonsense word gets a clear "nothing found" message.', 'OK'),
    ('Clicks "Shop" on the home page', 'One home-page link points at /Shop with a capital S. That address opened the shop page with no products on it. Fixed in this branch (see findings).', 'Fault'),
    ('Taps "Sign Up" in the header', 'The page that opens has a login form and no way to register. Fixed in this branch: it now sends a signed-out visitor to the My Account register form (see findings).', 'Fault'),
    ('Taps "Sign In" on the sign-up page', 'The button had no address and did nothing. Fixed in this branch (see findings).', 'Fault'),
    ('Mistypes a page address', 'The 404 page offers a way back.', 'OK'),
]

COUNTS = [
    ('Pages read', '2,652 (every sitemap URL and every page linked from them)'),
    ('Pages that answered an error', '6, all product-tag pages, all 508 "resource limit" from the host; each opened normally when read on its own afterwards'),
    ('Pages with PHP errors, placeholder text or soft 404s', '0'),
    ('Images checked', '3,000 of 8,633 unique, 0 broken'),
    ('External links', '15 checked, 0 dead (Facebook confirmed by a second request)'),
    ('Product pages missing a price, picture or buy button', '0'),
    ('Empty categories', '0'),
    ('Redirect chains', '0'),
    ('Average time to first byte', '1.8 s; 40 pages over 3 s'),
    ('Browser pass', '95 pages in a real Chromium, desktop and phone'),
    ('Shopper journey', '45 checks passed, 0 failed, 2 phone notes'),
    ('Release checklist', '15 passed, 1 blocking, 6 worth fixing'),
]

# severity, title, what a shopper sees, cause, fix / status
FINDINGS = [
    ('High', 'A home-page link to /Shop opens the shop with no products',
     'The home page carries one link to /Shop (capital S). That address answered 200 with the shop page\'s title and zero product cards: a shopper who clicks it sees an empty shop.',
     'WordPress matches a page name without regard to case, so /Shop/ reaches the shop template, but WooCommerce\'s product query does not run for the odd spelling. Nothing redirected it.',
     'Fixed in this branch: any path with a capital letter is sent once (301) to its lower-case spelling, so /Shop lands on /shop/ with its products. inc/markup-fixes.php, part 14. The link itself can also be corrected in the home page\'s Elementor content.'),

    ('High', 'Six product-tag pages answered "508 resource limit" to the crawl',
     'Under load, tapping a tag such as "nature" or "housewarming gift" beneath a product can show the host\'s error page instead of the tag\'s products.',
     'The host caps the CPU of the shared plan. Product-tag archives are not cached, so each is built from scratch (1.5 to 2.6 s alone, 4.8 s under load); three at once tipped it over.',
     'Not a code fault: read one at a time afterwards, all six opened with 9 to 12 pieces each. The fix is on the host side (cache tag archives, or a plan with more CPU). See "Left for the owner".'),

    ('Medium', 'Script errors on two category pages during the browser pass',
     'On one load each of /product-category/wall-art/ and /posters/ the page scripts failed ("jQuery is not defined" and six errors that follow from it), so sliders, quick view and the cart drawer would not have worked on that load.',
     'Three stylesheet requests on the same load were closed by the host (ERR_CONNECTION_CLOSED) and the jQuery file with them: the host dropping connections under load, the same cause as the 508s.',
     'Not a code fault: opened alone in a real browser afterwards (wall-art twice, posters once) both pages loaded with jQuery present, no script errors and no failed requests. Host side, with the 508s.'),

    ('High', 'The "Sign Up" page has no sign-up form',
     'The header\'s "Sign Up" opens /sign-up/, which shows a login form (username or email, password) and a lost-password form, and nothing to register with: no email, name or new-password field. A shopper who wants an account before buying is stuck. Guest checkout still works, and the account form on /my-account/ still works.',
     'The page is an Elementor login-register widget. Open registration was switched off in the 24 October security cleanup, and the widget then draws only its login half. WooCommerce\'s own registration on /my-account/ does not depend on that setting and is still on.',
     'Fixed in this branch: while WordPress registration is off, a signed-out visitor who opens /sign-up/ is sent on (302) to /my-account/, where the register form is. inc/markup-fixes.php, part 17. Owner\'s alternative: point the header\'s Sign Up at /my-account/ directly, or switch the widget to its register form.'),

    ('Medium', '"Sign In" button with no address on the login and sign-up pages',
     'A shopper who already has an account taps "Sign In" on the sign-up page and nothing happens. The same empty button sits on the login page.',
     'Elementor widgets with href="". An earlier database fix wrote post_content, which Elementor does not render from, so the empty buttons stayed.',
     'Fixed in this branch: on those two pages an empty-href button labelled Sign In points at /login/ and Register / Sign Up at /sign-up/ as the finished page leaves the server. inc/markup-fixes.php, part 15.'),

    ('Medium', 'Header Sign In and Register links go through a redirect on every click',
     'Every page links Sign In as /?page_id=6030 and Register as /?page_id=6032. Each click is first answered with a 301 to /login/ or /sign-up/: a round trip the shopper waits through on a 1.8 s host.',
     'The header menu was built with the pages\' IDs rather than their addresses.',
     'Fixed in this branch: the permalink replaces each ?page_id= address of a published page as the page leaves the server. inc/markup-fixes.php, part 16.'),

    ('Medium', 'Google Pay throws an error on product pages',
     'In the console only: "An unexpected error occurred while using Google Pay". A shopper sees nothing, but the express wallet button may not appear.',
     'Square\'s digital-wallet script runs on every product page and fails in browsers without Google Pay.',
     'Not changed; Square\'s script, not the theme. It stops if the Google Pay button is switched off in Square\'s settings, or stays if the button is wanted.'),

    ('Low', 'Three pairs of different pieces share one title',
     'Radha Krishna Love, Krishna Playing Flute and Buddha Art each exist twice with the same title and price. They are different artworks (different SKUs and pictures), so a shopper sees two identical names and cannot tell them apart in search results or the cart; Google sees duplicate titles.',
     'Two pieces were imported under the same name; the second got "-2" in its address.',
     'Owner: rename one of each pair (for example with the pose or colour: "Radha Krishna Love, yellow"). The crawl\'s duplicate-title list has the six addresses.'),

    ('Low', '31 categories are marked noindex',
     'Nothing a shopper sees. Gifts, Home Decor, Framed Canvases, Gold Foiled & UV, the frame-size and frame-colour categories and others carry "noindex" and so no canonical tag. They show 8 pieces each.',
     'Rank Math marks an archive noindex when it holds no products of its own; the 8 pieces shown are the theme\'s suggestions. Correct for size and colour archives; a question for Gifts and Home Decor if they are meant to rank.',
     'Owner: if Gifts, Home Decor or Gold Foiled & UV should appear in Google, assign products to them (or untick "noindex empty archives" in Rank Math). The 959 tag pages and the ?layout= and double-path category addresses were checked and are handled correctly (noindex, or a canonical to the main address).'),

    ('Low', 'Small tap targets on a tablet; two unnamed links',
     'On a tablet the header\'s "Sign Up" and "Login" are 19 px tall, the footer bar\'s Shop, Account and Wishlist 15 px, the slide dots 6 px and the "Read More" links 19 px: hard to hit with a thumb. The two language-flag links (English, Hindi) have no text for a screen reader; the checklist\'s other "unnamed" links are menu entries whose text sits in a closed dropdown.',
     'Header and footer links styled as small text; flag images without alt text.',
     'Not changed in this pass (header and footer are Elementor templates). Owner or next pass: 32 px minimum height on those links, alt text "English" and "Hindi" on the flags.'),

    ('Low', 'Home page serves 20 images at three times their shown size',
     'A few hundred kilobytes more than needed on the first visit; nothing visible.',
     'Icons and thumbnails delivered at 150 px and shown at 46 px.',
     'Not changed; the product pictures were already sized in October. Worth a pass over the icon widgets later.'),

    ('Low', 'Searching for a <script> tag returns an almost empty page',
     'Only someone typing HTML into the search box sees it; a normal search works and a nonsense word gets a "nothing found" message.',
     'The host\'s web firewall answers the request with a bare page.',
     'Not changed; the firewall is doing its job.'),

    ('Info', 'Console: "Fetch API cannot load data:," on the login and account pages',
     'Nothing visible; error lines in the browser console.',
     'Cloudflare Turnstile\'s own script probes a data: address and the page\'s Content Security Policy refuses it. Nothing in the theme sends it.',
     'Nothing to do; Turnstile works regardless.'),

    ('Info', 'Phone: price and Add to Cart below the first screen',
     'On a phone the product picture fills the first screen; the price and button come after it.',
     'Gallery first, then the summary: the usual WooCommerce order.',
     'No change needed: the buy bar at the foot of the screen shows the price and an Add to Cart button as soon as the page\'s own button scrolls out of view, and the test confirmed it matches the page price.'),

    ('Info', 'Release-checklist run saw an empty cart after adding',
     'That one script\'s add-to-cart did not carry to the cart page. The shopper journey (desktop, phone, signed in) and the browser pass all added to the cart and saw the line in the cart and at checkout.',
     'The checklist script adds from the product page in a way the theme\'s AJAX cart answered differently; the other three passes used the real button.',
     'Not reproduced by the other passes; treated as a test-script difference, not a shop fault.'),

    ('Info', 'Facebook link',
     'The footer link to facebook.com/theartframer answered 400 to the crawl.',
     'Facebook refuses bots. A second request was answered with a redirect to Facebook\'s login page, which is what a live page does.',
     'Nothing to do.'),
]

# Follow-up, one request per page: page, result, robots, canonical, note
FOLLOWUP_ROWS = [
    ('/product-tag/home-interior-decoration/', '200, 2.6 s', 'noindex', '(none, by design)', '9 pieces; 508 to the crawl'),
    ('/product-tag/housewarming-gift/', '200, 1.8 s', 'noindex', '(none, by design)', '9 pieces; 508 to the crawl'),
    ('/product-tag/nature/', '200, 1.6 s', 'noindex', '(none, by design)', '12 pieces; 508 to the crawl'),
    ('/product-tag/bharatanatyam-canvas-print/', '200, 1.7 s', 'noindex', '(none, by design)', '9 pieces; 508 to the crawl'),
    ('/product-tag/artistic-wall-decoration/', '200, 1.8 s', 'noindex', '(none, by design)', '9 pieces; 508 to the crawl'),
    ('/product-tag/bharatanatyam-dancer-painting/', '200, 2.1 s', 'noindex', '(none, by design)', '9 pieces; 508 to the crawl'),
    ('/shop/', '200, 0.1 s', 'index', '/shop/', '12 pieces (cached)'),
    ('/shop/?layout=list and ?layout=grid', '200, 1.6 s', 'index', '/shop/', 'layout variants point at the main address: fine'),
    ('/Shop/', '200, 1.8 s', 'index', '/shop/', '0 pieces: the fault above; now redirected'),
    ('/product-category/radha-krishna/', '200, 1.6 s', 'index', '/product-category/digital-canvas-prints/radha-krishna/', 'short path points at the full one: fine'),
    ('/product-category/lord-shiva/', '200, 1.7 s', 'index', '/product-category/digital-canvas-prints/lord-shiva/', 'same'),
    ('/product-category/digital-canvas-prints/page/2/', '200, 1.9 s', 'index', 'itself', 'page number in the title: fine'),
    ('/product-category/gifts/, /home-decor/, /framed-canvases/, /gold-foiled-uv/, /frame-sizes/2x3-ft/', '200, 1.4 to 2.1 s', 'noindex', '(none)', '8 suggested pieces each; see finding'),
    ('/blog/?topic=vastu-decor-guides', '200, 1.8 s', 'index', '/blog/', 'fine'),
    ('/my-account/, /cart/, /wishlist/', '200', 'noindex', '(none)', 'correct for personal pages'),
    ('/product/radha-krishna-love-canvas-wall-art/ and -2', '200', 'index', 'each itself', 'SKU RK-010027-5030 vs RK-010003-3040, different pictures, both $80'),
    ('/product/krishna-playing-flute-canvas-wall-art/ and -2', '200', 'index', 'each itself', 'SKU RK-010041-5030 vs RK-010033-3050, different pictures'),
    ('/product/buddha-art-canvas-wall-art/ and -2', '200', 'index', 'each itself', 'SKU LS-030009-3040 vs LB-090005-5030, different pictures'),
    ('/login/ and /sign-up/', '200, 1.8 s', 'noindex', '(none)', 'one empty-href "Sign In" button on each; now mended'),
    ('/?page_id=6032 and /?page_id=6030', '301', '', '', 'to /sign-up/ and /login/; linked from every page\'s header; now rewritten'),
    ('facebook.com/theartframer', '302 to Facebook login', '', '', 'the page exists'),
]


def render(browser_rows):
    rows_findings = [(sev(s), t, w, c, f) for (s, t, w, c, f) in FINDINGS]
    fixed = [r for r in FINDINGS if 'Fixed in this branch' in r[4]]
    counts = {k: sum(1 for r in FINDINGS if r[0] == k) for k in ('High', 'Medium', 'Low', 'Info')}
    pill = {'OK': 'ok', 'Note': 'info', 'Fault': 'high'}
    journey_rows = [(s, w, f'<span class="pill {pill[ok]}">{ok}</span>') for (s, w, ok) in JOURNEY]

    body = f'''
<header>
  <h1>The Art Framer Site Test</h1>
  <p class="meta"><span class="chip">26 October 2026</span> <span class="chip">theartframer.us</span> <span class="chip">as a 35-year-old shopper</span></p>
  <p class="lede">The whole shop was read page by page, then walked through in a real browser the way a first-time buyer would: home, a category, a product, the cart, guest checkout to the payment step, then again with an account. This report lists what a shopper would run into, what caused it, what was fixed and what is left.</p>
</header>

<h2>Summary</h2>
<p>A shopper can find a piece, choose a size, add it to the cart and reach the payment step on desktop and on a phone without hitting a fault: the 45-step shopper journey passed with no failures. Three things a shopper could trip on were found around that path and are fixed in this branch: the home page's "Shop" link, spelt /Shop, opened the shop with no products on it; the "Sign Up" page had no form to sign up with; and the "Sign In" button on that page went nowhere. The rest is the host dropping requests under load (six tag pages, two category-page loads), three pairs of pieces sharing a name, and some search-engine hygiene.</p>
<p>{counts['High']} high, {counts['Medium']} medium, {counts['Low']} low findings and {counts['Info']} notes. {len(fixed)} are fixed in the code on branch <code>claude/loving-allen-9owz8p</code>, ready to merge and deploy; the rest are on the host, in Square, in Elementor, or decisions for the owner, and each says so.</p>

{table(['What was tested', 'Result'], COUNTS, ['38%', '62%'])}

<h2>The shopper's walk through the shop</h2>
<p>Desktop (1366 px), then a phone (iPhone size), then signed in with a throwaway account that was deleted afterwards. No order was placed and nothing was emailed; every request that would reach a person was answered locally.</p>
{table(['Step', 'What happened', ''], journey_rows, ['26%', '64%', '10%'])}

<h2>Findings</h2>
<p>Ordered by how much they matter to a sale. "Fix / status" says what was changed in this branch, or who holds the fix when it is not in the theme.</p>
{table(['', 'Finding', 'What a shopper sees', 'Cause', 'Fix / status'], rows_findings, ['7%', '20%', '27%', '22%', '24%'])}

<h2>Follow-up checks, one page at a time</h2>
<p>The crawl reads three pages at once, which is exactly what this host dislikes. Each open question above was re-read with a single request per page, a few seconds apart, the way one shopper arrives.</p>
{table(['Page', 'Alone', 'Robots', 'Canonical', 'Note'], FOLLOWUP_ROWS, ['32%', '11%', '9%', '24%', '24%'])}

<h3>In a real browser</h3>
{table(['Check', 'Result'], browser_rows, ['34%', '66%'])}

<h2>What was changed in the code</h2>
<ul>
  <li><b>Capital letters in an address</b> redirect once (301) to the lower-case page, so /Shop no longer opens an empty shop. The query string keeps its case; 404s are untouched. <code>inc/markup-fixes.php</code>, part 14.</li>
  <li><b>Empty "Sign In" / "Register" buttons</b> on the login and sign-up pages are given their address as the finished page leaves the server. <code>inc/markup-fixes.php</code>, part 15.</li>
  <li><b>Header links written as ?page_id=</b> are replaced with the page's own address, so Sign In and Register open in one request instead of two. <code>inc/markup-fixes.php</code>, part 16.</li>
  <li><b>The Sign Up page</b> sends a signed-out visitor on to the My Account register form while WordPress registration is off (a 302, so nothing to undo if it is switched back on). <code>inc/markup-fixes.php</code>, part 17.</li>
  <li><b>Both "View Product" buttons</b> (the download modal and the try-on-wall tool) start with the shop address instead of none; their scripts still swap in the product when one is chosen. <code>functions.php</code>.</li>
  <li>Two read-only probes, <code>tools/probe-crawl-followups.mjs</code> and <code>tools/probe-browser-followups.mjs</code>, so the follow-up tables can be re-run after any deploy.</li>
</ul>
<p>Eleven unit checks cover the three new helpers and all pass; the two redirects are plain WordPress calls guarded by the page and the settings. The change is on the branch and not yet deployed: deploy happens when it is merged to <code>main</code>. After the deploy, run <i>Audit Site</i> with pass <code>links</code> and the two probes to confirm /Shop redirects, the Sign In buttons have an address and the header links are direct.</p>

<h2>Left for the owner</h2>
<ul>
  <li><b>Host capacity.</b> The 508s and dropped connections are the shared plan's CPU cap. Two things help without a plan change: let LiteSpeed cache product-tag archives like categories, and keep the daily cache warm covering the tag pages shoppers reach from products. If 508s keep appearing in the host's logs, the plan is the fix.</li>
  <li><b>Sign Up.</b> Decide where "Sign Up" should lead for good: the My Account register form (then point the header link there and retire /sign-up/), or the Elementor widget's own register form, which needs WordPress registration switched back on.</li>
  <li><b>Same-name pieces.</b> Rename one of each of the three pairs so shoppers and Google can tell them apart.</li>
  <li><b>Noindex categories.</b> Decide whether Gifts, Home Decor and Gold Foiled &amp; UV should rank; if so, give them products of their own.</li>
  <li><b>Google Pay.</b> Decide in Square whether the express wallet button should show on product pages; the console error goes with it either way.</li>
  <li><b>Home page link.</b> The /Shop link now redirects, but correcting it to /shop/ in the home page's Elementor content saves that hop too.</li>
</ul>

<h2>How the test was run</h2>
<p>GitHub Actions on the repository: <i>Audit Site</i> runs 35 (full crawl, browser pass, release checklist) and 36 (shopper journey), and <i>QA personas</i> runs 190 and 191 carrying the two follow-up probes. Scripts: <code>tools/audit-full.mjs</code>, <code>tools/audit-browser.mjs</code>, <code>tools/qa-veteran.mjs</code>, <code>tools/qa-customer.mjs</code>, and the two probes above. Logs and screenshots are attached to each run as artifacts.</p>
'''
    css = '''
@page { size: A4; margin: 18mm 16mm 20mm 16mm; }
body { font: 10.5pt/1.45 "Helvetica Neue", Arial, sans-serif; color: #1f1b16; margin: 0; }
header { margin-bottom: 8mm; }
h1 { font: 600 24pt/1.15 Georgia, "Times New Roman", serif; margin: 0 0 4mm; letter-spacing: -0.2px; }
h2 { font: 600 15pt/1.25 Georgia, "Times New Roman", serif; margin: 9mm 0 3mm; page-break-after: avoid; }
h3 { font: 600 11.5pt/1.25 Georgia, "Times New Roman", serif; margin: 5mm 0 2mm; page-break-after: avoid; }
p { margin: 0 0 3mm; }
.meta { margin-bottom: 4mm; }
.chip { display: inline-block; background: #f3efe6; border-radius: 4px; padding: 1px 7px; font-size: 9.5pt; margin-right: 4px; }
.lede { font-size: 11pt; color: #3a342c; }
table { width: 100%; border-collapse: collapse; margin: 2mm 0 5mm; font-size: 9.4pt; page-break-inside: auto; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th { text-align: left; background: #f6f3ec; color: #5d5547; font-weight: 600; padding: 5px 7px; border: 1px solid #e3ddd0; }
td { vertical-align: top; padding: 5px 7px; border: 1px solid #e3ddd0; word-break: break-word; }
code { font: 9pt Menlo, Consolas, monospace; background: #f3efe6; padding: 0 3px; border-radius: 3px; }
.pill { display: inline-block; font-size: 8.5pt; font-weight: 600; padding: 1px 7px; border-radius: 9px; white-space: nowrap; }
.pill.high { background: #fde2e1; color: #9b1c1c; }
.pill.med { background: #fff1d6; color: #8a5a00; }
.pill.low { background: #e8f0fb; color: #1d4e89; }
.pill.info { background: #eeebe4; color: #5d5547; }
.pill.ok { background: #e2f3e6; color: #1e6b33; }
ul { padding-left: 5mm; margin: 0 0 3mm; }
li { margin-bottom: 1.5mm; }
'''
    return f'<!doctype html><html lang="en"><head><meta charset="utf-8"><title>The Art Framer Site Test, 26 October 2026</title><style>{css}</style></head><body>{body}</body></html>'


def main():
    rows_path = os.path.join(ROOT, 'docs', 'site-test-26-oct-browser-rows.txt')
    browser_rows = []
    if os.path.exists(rows_path):
        with open(rows_path) as f:
            for line in f:
                line = line.rstrip('\n')
                if '\t' in line:
                    a, b = line.split('\t', 1)
                    browser_rows.append((a, b))
    if not browser_rows:
        browser_rows = [('Second follow-up run', 'still running when this copy was printed')]
    doc = render(browser_rows)
    os.makedirs(os.path.dirname(OUT_HTML), exist_ok=True)
    with open(OUT_HTML, 'w') as f:
        f.write(doc)
    js = f'''
const {{ chromium }} = require('playwright');
(async () => {{
  const b = await chromium.launch();
  const p = await b.newPage();
  await p.goto('file://{OUT_HTML}', {{ waitUntil: 'load' }});
  await p.pdf({{ path: '{OUT_PDF}', format: 'A4', printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<div style="font-size:7.5pt;color:#8a8275;width:100%;padding:0 16mm;">The Art Framer Site Test, 26 October 2026</div>',
    footerTemplate: '<div style="font-size:7.5pt;color:#8a8275;width:100%;padding:0 16mm;text-align:right;">Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>',
    margin: {{ top: '18mm', bottom: '20mm', left: '16mm', right: '16mm' }} }});
  await b.close();
  console.log('wrote {OUT_PDF}');
}})();
'''
    subprocess.run(['node', '-e', js], check=True)


if __name__ == '__main__':
    main()
