#!/usr/bin/env python3
"""
Build docs/The-Art-Framer-Site-Test-26-Oct-2026.pdf from the results of the
full site test of 2026-10-26 (Audit Site runs 35 and 36, QA personas runs 190
and 191). Writes the HTML next to the PDF; Chromium (Playwright) prints it.

Run: python3 tools/build-site-test-report.py
"""
import html
import json
import os
import subprocess
import sys

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
    cls = {'High': 'high', 'Medium': 'med', 'Low': 'low', 'Fixed': 'fixed', 'Info': 'info'}[s]
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
    ('Mistypes a page address', 'The 404 page offers a way back.', 'OK'),
]

COUNTS = [
    ('Pages read', '2,652 (every sitemap URL and every page linked from them)'),
    ('Pages that answered an error', '6, all product-tag pages, all 508 "resource limit" from the host, none in the sitemap'),
    ('Pages with PHP errors, placeholder text or soft 404s', '0'),
    ('Images checked', '3,000 of 8,633 unique, 0 broken'),
    ('External links', '15 checked, 0 dead (Facebook refused the test, see below)'),
    ('Product pages missing a price, picture or buy button', '0'),
    ('Empty categories', '0'),
    ('Redirect chains', '0'),
    ('Average time to first byte', '1.8 s; 40 pages over 3 s'),
    ('Browser pass', '95 pages in a real Chromium, desktop and phone'),
    ('Shopper journey', '45 checks passed, 0 failed, 2 phone notes'),
    ('Release checklist', '15 passed, 1 blocking, 6 worth fixing'),
]

# Each finding: severity, what a shopper sees, cause, status / fix
FINDINGS = []


def add(sev_, title, sees, cause, status):
    FINDINGS.append((sev_, title, sees, cause, status))


def build(probe):
    """probe: dict of verified facts from the two follow-up runs."""
    FINDINGS.clear()

    add('High', 'Six product-tag pages answered "508 resource limit"',
        'Tapping a tag such as "nature" or "housewarming gift" under a product can show a blank host error page instead of the tag\'s products. The crawl got 508 twice in a row on six tag pages while reading three pages at a time.',
        'The host (Hostinger shared plan) caps the CPU; product-tag archives are never cached, so each one is built from scratch and the slowest took 4.8 s. Three at once tipped it over.',
        probe.get('tags_alone', 'Re-checked one page at a time: see the follow-up table.'))

    add('High', 'Script errors on two category pages during the browser pass',
        'On /product-category/wall-art/ and /posters/ the page scripts failed ("jQuery is not defined" and six errors that follow from it): sliders, quick view and the cart drawer would not work on that load.',
        'Three stylesheet requests on the same page load were closed by the host (ERR_CONNECTION_CLOSED) and the jQuery file with them. Same cause as the 508s: the host dropping connections under load, not a code fault.',
        probe.get('cat_alone', 'Re-checked alone: see the follow-up table.'))

    add('Medium', 'Sign-up form has no email-type field',
        'On a phone the email box opens the ordinary keyboard, not the email one, and the browser cannot check the address before it is sent.',
        'The sign-up page is an Elementor form whose email field is a plain text input.',
        probe.get('signup', 'Field list in the follow-up table.'))

    add('Medium', 'Four "Sign In" buttons with no address on the sign-up page',
        'A shopper who already has an account taps "Sign In" on the sign-up page and nothing happens.',
        'The buttons are Elementor widgets with href="". An earlier database fix wrote post_content, which Elementor does not render from.',
        'Fixed in this branch: the finished page is mended as it leaves the server, so the buttons point at /login/ (inc/markup-fixes.php, part 15).')

    add('Medium', '/Shop/ with a capital S is a second copy of the shop',
        'Nothing a shopper notices, but Google sees two pages with the same title, and any link typed with a capital letter splits the page\'s ranking.',
        'WordPress matches page names without regard to case and nothing redirected the odd spelling.',
        'Fixed in this branch: any path with a capital letter is redirected (301) to its lower-case spelling (inc/markup-fixes.php, part 14).')

    add('Medium', 'Google Pay throws an error on product pages',
        'In the console only: "An unexpected error occurred while using Google Pay". A shopper sees nothing, but the express wallet button may not appear.',
        'Square\'s digital-wallet script runs on every product page and fails in browsers without Google Pay.',
        'Not changed. Square\'s script, not the theme; it stops if the Google Pay button is switched off in Square\'s settings, or kept if the button is wanted.')

    add('Medium', 'Three products exist twice',
        'Radha Krishna Love, Krishna Playing Flute and Buddha Art each have a copy with "-2" in the address and the same title. A shopper may see the same piece twice in a category; Google sees duplicate pages.',
        'Imported twice at some point.',
        probe.get('dupes', 'Compared in the follow-up table. Content decision for the owner: trash one of each pair (the tools/diag-duplicates.php report lists all pairs).'))

    add('Medium', 'Two addresses for every deity category',
        '/product-category/lord-shiva/ and /product-category/digital-canvas-prints/lord-shiva/ both open the same category with the same title (15 categories, 6 addresses each counting the layout variants).',
        'WooCommerce accepts a child category with or without its parent in the path, and both spellings are linked.',
        probe.get('double_cat', 'Harmless for shoppers. For Google it depends on the canonical tag; checked in the follow-up table.'))

    add('Low', 'Pages the crawl found without a canonical tag (1,523)',
        'Nothing a shopper sees. 959 are product-tag pages, 514 are ?layout=list / ?layout=grid variants, 31 are categories, 13 pages and 6 blog archives.',
        'Rank Math drops the canonical on pages it marks noindex, which is correct behaviour for tag pages and layout variants if they are noindex.',
        probe.get('canonical', 'Robots and canonical sampled in the follow-up table.'))

    add('Low', '24 links without an accessible name; 11 tap targets under 32 px on a tablet',
        'Icon links read as "link" to a screen reader; small buttons such as "Sign Up" are hard to hit on a tablet.',
        'Icon-only links without aria-label; small header buttons.',
        probe.get('a11y', 'Listed in the follow-up table.'))

    add('Low', 'Home page serves 20 images at three times their shown size',
        'A few hundred kilobytes more than needed on the first visit; nothing visible.',
        'Icons and thumbnails delivered at 150 px and shown at 46 px.',
        'Not changed; the earlier card-image work already covers the product pictures. Worth a pass over the icon widgets later.')

    add('Low', 'Searching for a <script> tag returns an almost empty page',
        'Only someone typing HTML into the search box sees it; a normal search works.',
        'The host\'s web firewall answers the request with a bare page.',
        'Not changed; the firewall is doing its job.')

    add('Low', 'Console: "Fetch API cannot load data:," on account pages',
        'Nothing visible.',
        probe.get('csp_cause', 'A script on the account pages fetches a data: URL, which the Content Security Policy blocks.'),
        probe.get('csp_fix', 'Identified in the follow-up table.'))

    add('Info', 'Phone: price and Add to Cart below the first screen',
        'On a phone the product picture fills the first screen; the price and button come after it.',
        'Gallery first, then the summary, the usual WooCommerce order.',
        'No change needed: the buy bar at the foot of the screen shows the price and an Add to Cart button as soon as the page\'s button scrolls out of view, and the test confirmed it matches the page price.')

    add('Info', 'Facebook link could not be verified by the crawl',
        'The footer link to facebook.com/theartframer answered 400 to the test, which is Facebook refusing a bot, not a dead link.',
        'Facebook blocks automated requests.',
        probe.get('facebook', 'Open it once by hand to confirm the page is live.'))

    add('Info', 'Release-checklist run saw an empty cart after adding',
        'That one script\'s add-to-cart did not carry to the cart page. The shopper journey (desktop, phone, signed in) and the browser pass all added to the cart and saw it in the cart and checkout.',
        'The checklist script adds from the product page in a way that the theme\'s AJAX cart answered differently; the other three passes used the real button.',
        'Not reproduced by the other passes; treated as a test-script difference, not a shop fault.')


FOLLOWUP_ROWS = []   # filled from the probe logs


def render():
    rows_findings = [(sev(s), t, w, c, f) for (s, t, w, c, f) in FINDINGS]
    fixed = [r for r in FINDINGS if 'Fixed in this branch' in r[4]]
    counts = {k: sum(1 for r in FINDINGS if r[0] == k) for k in ('High', 'Medium', 'Low', 'Info')}
    journey_rows = [(s, w, f'<span class="pill {"ok" if ok == "OK" else "info"}">{ok}</span>') for (s, w, ok) in JOURNEY]

    body = f'''
<header>
  <h1>The Art Framer Site Test</h1>
  <p class="meta"><span class="chip">26 October 2026</span> <span class="chip">theartframer.us</span> <span class="chip">as a 35-year-old shopper</span></p>
  <p class="lede">The whole shop was read page by page, then walked through in a real browser the way a first-time buyer would: home, a category, a product, the cart, guest checkout to the payment step, then again with an account. This report lists what a shopper would run into, what caused it, what was fixed and what is left.</p>
</header>

<h2>Summary</h2>
<p>A shopper can find a piece, choose a size, add it to the cart and reach the payment step on desktop and on a phone without hitting a fault: the 45-step shopper journey passed with no failures. The problems found are around the edges: the host dropping requests under load (six tag pages, two category-page loads), a sign-up form without a proper email field and with dead "Sign In" buttons, three products listed twice, and a handful of search-engine hygiene points.</p>
<p>{counts['High']} high, {counts['Medium']} medium, {counts['Low']} low findings and {counts['Info']} notes. {len(fixed)} are fixed in the code on branch <code>claude/loving-allen-9owz8p</code>, ready to merge and deploy; the rest are either on the host, in Square, or decisions for the owner, and each says so.</p>

{table(['What was tested', 'Result'], COUNTS, ['38%', '62%'])}

<h2>The shopper's walk through the shop</h2>
<p>Desktop (1366 px), then a phone (iPhone size), then signed in with a throwaway account that was deleted afterwards. No order was placed and nothing was emailed; every request that would reach a person was answered locally.</p>
{table(['Step', 'What happened', ''], journey_rows, ['26%', '64%', '10%'])}

<h2>Findings</h2>
<p>Ordered by how much they matter to a sale. "Fix / status" says what was changed in this branch, or who holds the fix when it is not in the theme.</p>
{table(['', 'Finding', 'What a shopper sees', 'Cause', 'Fix / status'], rows_findings, ['7%', '20%', '27%', '22%', '24%'])}

<h2>Follow-up checks, one page at a time</h2>
<p>The crawl reads three pages at once, which is exactly what this host dislikes. Each open question above was re-read with a single request per page, two seconds apart, the way one shopper arrives.</p>
{table(['Page', 'Alone', 'Robots', 'Canonical', 'Note'], FOLLOWUP_ROWS, ['34%', '10%', '14%', '22%', '20%'])}

<h2>What was changed in the code</h2>
<ul>
  <li><b>Capital letters in an address</b> redirect once (301) to the lower-case page, so /Shop/ no longer doubles /shop/. The query string keeps its case; 404s are untouched. <code>inc/markup-fixes.php</code>, part 14.</li>
  <li><b>Empty "Sign In" / "Register" buttons</b> on the login and sign-up pages are given their address as the finished page leaves the server. <code>inc/markup-fixes.php</code>, part 15.</li>
  <li><b>Both "View Product" buttons</b> (the download modal and the try-on-wall tool) start with the shop address instead of none; their scripts still swap in the product when one is chosen. <code>functions.php</code>.</li>
  <li>Two read-only probes, <code>tools/probe-crawl-followups.mjs</code> and <code>tools/probe-browser-followups.mjs</code>, so the follow-up table can be re-run after any deploy.</li>
</ul>
<p>Nine unit checks cover the two new helpers (lower-casing a path; mending the buttons) and all pass. The change is on the branch and not yet deployed: deploy happens when it is merged to <code>main</code>.</p>

<h2>Left for the owner</h2>
<ul>
  <li><b>Host capacity.</b> The 508s and dropped connections are the shared plan's CPU cap. Two things help without a plan change: let LiteSpeed cache product-tag archives like categories, and keep the daily cache warm covering the tag pages shoppers reach from products. If 508s keep appearing in the host's logs, the plan is the fix.</li>
  <li><b>Duplicate products.</b> Trash the "-2" copy of each of the three pairs (or the original, whichever has the sales), then run the duplicates diagnostic to confirm none remain.</li>
  <li><b>Sign-up form.</b> In Elementor, set the email field's type to Email (the form widget has the option), which brings the right keyboard on phones and browser-side checking.</li>
  <li><b>Google Pay.</b> Decide in Square whether the express wallet button should show on product pages; the console error goes with it either way.</li>
  <li><b>Facebook.</b> Open facebook.com/theartframer once by hand; the crawl cannot see past Facebook's bot wall.</li>
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
p { margin: 0 0 3mm; }
.meta { margin-bottom: 4mm; }
.chip { display: inline-block; background: #f3efe6; border-radius: 4px; padding: 1px 7px; font-size: 9.5pt; margin-right: 4px; }
.lede { font-size: 11pt; color: #3a342c; }
table { width: 100%; border-collapse: collapse; margin: 2mm 0 5mm; font-size: 9.4pt; page-break-inside: auto; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th { text-align: left; background: #f6f3ec; color: #5d5547; font-weight: 600; padding: 5px 7px; border: 1px solid #e3ddd0; }
td { vertical-align: top; padding: 5px 7px; border: 1px solid #e3ddd0; }
code { font: 9pt Menlo, Consolas, monospace; background: #f3efe6; padding: 0 3px; border-radius: 3px; }
.pill { display: inline-block; font-size: 8.5pt; font-weight: 600; padding: 1px 7px; border-radius: 9px; white-space: nowrap; }
.pill.high { background: #fde2e1; color: #9b1c1c; }
.pill.med { background: #fff1d6; color: #8a5a00; }
.pill.low { background: #e8f0fb; color: #1d4e89; }
.pill.info { background: #eeebe4; color: #5d5547; }
.pill.fixed, .pill.ok { background: #e2f3e6; color: #1e6b33; }
ul { padding-left: 5mm; margin: 0 0 3mm; }
li { margin-bottom: 1.5mm; }
'''
    return f'<!doctype html><html lang="en"><head><meta charset="utf-8"><title>The Art Framer Site Test, 26 October 2026</title><style>{css}</style></head><body>{body}</body></html>'


def main():
    probe_path = os.path.join(ROOT, 'docs', 'site-test-26-oct-probe.json')
    probe = {}
    if os.path.exists(probe_path):
        with open(probe_path) as f:
            data = json.load(f)
        probe = data.get('facts', {})
        FOLLOWUP_ROWS.extend(tuple(r) for r in data.get('rows', []))
    build(probe)
    doc = render()
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
