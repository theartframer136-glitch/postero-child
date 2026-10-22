# SEO plan for the USA market: website and YouTube

Prepared 14 Oct 2026 from a read-only inventory of the live site
(`tools/diag-seo-inventory.php`, run 98 of "Run Diagnostic") and the
public YouTube Data API. Nothing in this plan is live. The website part
runs only when the branch is merged to `main` and the deploy runs the two
SEO scripts it already runs; the YouTube part runs only when the owner
executes `tools/youtube-seo.mjs apply` after reviewing the diff.

Rules carried through every change: no "free shipping" anywhere, prices in
USD, shipping cost shown at checkout, made in India and shipped across the
USA in 7–14 days.

## 1. Who we are optimising for

The buyer is an Indian family in the USA furnishing a home, a pooja room or
an office, or buying a Diwali, Griha Pravesh (housewarming) or wedding gift.
They search in English, with Indian terms, and expect US delivery.

### Keyword map

| Intent | Head terms (title tag, H1, URL) | Supporting terms (description, intro, tags) |
|---|---|---|
| Deity art, general | hindu god paintings, indian wall art, hindu wall art | pooja room decor, mandir wall decor, indian home decor, spiritual wall art, diwali gift |
| Radha Krishna | radha krishna painting, radha krishna wall art, krishna wall art | radha krishna canvas, krishna painting for home, radha krishna painting for living room / bedroom, wedding gift indian couple |
| Ganesha | ganesha painting, ganesha wall art, lakshmi ganesh painting | ganesha painting for entrance vastu, ganpati painting, lord ganesha canvas, griha pravesh gift, ganesh chaturthi |
| Shiva | lord shiva painting, shiva wall art, mahadev painting | meditating shiva, nataraja painting, shiva painting for living room, mahashivratri gift |
| Balaji | tirupati balaji painting, balaji wall art, venkateswara painting | balaji painting for pooja room, lord venkateswara canvas, telugu home decor |
| Durga, Ram, others | durga maa painting, lord ram painting, hanuman painting, guru nanak painting, swaminarayan painting | navratri decor, ram darbar, sikh art, golden temple painting |
| Vastu | 7 horses painting, seven horses painting vastu, vastu painting for living room | 7 horses painting vastu direction, 7 white horses painting, vastu painting for office, running horses canvas, wealth / success |
| Buddha | buddha painting, buddha wall art | buddha painting for living room, zen wall art, meditation room art, yoga studio wall art |
| Product form | canvas wall art, framed canvas prints, floating frame canvas | large wall art, 3x4 ft canvas, ready to hang, museum-grade canvas |
| Occasion | diwali gifts, housewarming gift indian, wedding gift indian couple | griha pravesh gift, anniversary gift couple, corporate diwali gifts |
| Location signals | USA, Delaware, Indian-American home | ships across the USA, 7–14 days, US studio |

These are the terms the deity and Vastu market is known to use. Monthly US
volumes should be confirmed from Search Console once the SB session pulls
the last 16 months of queries; the map is written so a term can be swapped
without touching code.

## 2. Website: what is wrong today

Measured from the live database on 14 Oct:

| Finding | Where | Effect |
|---|---|---|
| 15 product meta descriptions say "Free US shipping." | written by `tools/fix-product-seo-meta.php` on every deploy | breaks the owner's rule and misleads Google's snippet |
| Deity and Vastu categories carry the template title "X – Shop Online \| The Art Framer" | lord-shiva (19 products), seven-horses (16), tirupati-balaji (9), lord-rama (7), sikh-art (6), vaastu-art, swaminarayan, indian-culture (16) and others | the pages that should rank say nothing a shopper types |
| 14 of the 16 art categories have no intro text above the grid | term description empty | thin category pages |
| Product categories are not in the XML sitemap | Rank Math `tax_product_cat_sitemap` off | Google is not told the category pages exist |
| 50 of 97 categories hold 0 products and are indexable | frame sizes, colours, placeholders | thin pages dilute the crawl |
| Shop page title is generic | "Shop Canvas Prints, Wall Art & Picture Frames" | misses the niche entirely |
| The blog hub page has no title or description | page #3301 | /blog/ renders with a bare template title |
| Product SEO titles omit "Canvas Wall Art" | e.g. "Radha Krishna Moonlit Melody \| The Art Framer" | the head term every deity search carries is missing |
| Organization schema lists Facebook and Instagram but not YouTube | functions.php sameAs | the channel is not tied to the entity |
| Knowledge-graph name is "The ART FRAMER" | Rank Math titles option | odd casing in rich results |
| Blog holds 2023 demo posts about streetwear, fitness and fashion | 10 of 16 posts | off-topic content on a devotional art store |
| No business address in the Organization schema | `af_business_address` option absent | weaker local and entity signal |

What is already right and left alone: the front page title and description
(hand-written, on target), the hindu-deities, radha-krishna, lakshmi-ganesha,
buddha and pichwai titles, the product price-range schema, the noindex rules,
robots.txt, the breadcrumb and FAQ schema, the shipping copy in schema.

## 3. Website: the changes in this branch

All three files run only on a deploy from `main`. Each change replaces a
value only when that value is empty, generic, or was written by the same
script on an earlier run. Hand-written copy is never overwritten.

### 3a. `tools/fix-product-seo-meta.php` (476 products)

1. The description tail "Free US shipping." is gone, and any description
   still carrying "free shipping" or "free delivery" is rewritten.
2. A product in a deity or Vastu category gets a description that says what
   it is and where it hangs. Example, before and after:

   Before: `Radha Krishna Moonlit Melody — premium canvas wall art, archival inks, multiple sizes and frames. Gallery-wrapped and ready to hang.`

   After: `Radha Krishna Moonlit Melody: Radha Krishna canvas art for the living room, bedroom or pooja room. Ready to hang. Ships across the USA.`

   The phrase per category:

   | Category | Phrase |
   |---|---|
   | radha-krishna | Radha Krishna canvas art for the living room, bedroom or pooja room. |
   | lakshmi-ganesha | Lakshmi Ganesh canvas art for the pooja room, entrance or Diwali gifting. |
   | seven-horses | 7 running horses painting as per Vastu for the living room or office. |
   | vaastu-art | Vastu painting for the living room, office or entrance. |
   | lord-shiva | Lord Shiva canvas art for the living room, meditation space or pooja room. |
   | tirupati-balaji | Tirupati Balaji canvas art for the pooja room or living room. |
   | lord-rama | Lord Ram canvas art for the pooja room or living room. |
   | buddha | Buddha canvas art for the living room, meditation room or yoga space. |
   | sikh-art | Sikh canvas art for the living room or prayer room. |
   | swaminarayan | Swaminarayan canvas art for the pooja room or living room. |
   | pichwai-art | Pichwai canvas art for the living room or pooja room. |
   | hindu-deities | Hindu god canvas art for the living room or pooja room. |
   | indian-culture | Indian canvas art for the living room or entrance. |
   | christian-art | Christian canvas art for the living room or prayer corner. |

3. Accessories (frames, bars, rolled canvas, banners, certificates) stop
   being described as "premium canvas wall art" and read "made to order by
   The Art Framer, a Delaware studio, and shipped across the USA."
4. The SEO title gains "Canvas Wall Art" when the product name does not
   already say canvas and the result fits in 60 characters:
   `Blue Kintsugi Bird Profile | The Art Framer` becomes
   `Blue Kintsugi Bird Profile Canvas Wall Art | The Art Framer`.
   When the brand suffix no longer fits, the keyword wins over the brand.

Tested on six stubbed products including a hand-written one, which was left
untouched; a second run changes nothing.

### 3b. `tools/seo-improvements.php` (pages, categories, settings)

1. Shop page: title becomes
   `Shop Hindu God Paintings, Vastu Art & Canvas Prints | The Art Framer`,
   description `Browse every canvas: Radha Krishna, Ganesha, Shiva, Balaji, 7 horses Vastu paintings, Buddha, abstract and more. Floating frames, ready to hang, shipped across the USA.`
2. Blog hub page gets a title and description aimed at Vastu and deity guides.
3. 24 categories get a title, description and intro. The full copy is in the
   file under "USA-market pass"; the titles:

   | Category | New title |
   |---|---|
   | seven-horses | 7 Horses Painting as per Vastu for Living Room & Office |
   | vaastu-art | Vastu Paintings for Living Room & Office \| Canvas Art |
   | lord-shiva | Lord Shiva Painting for Home \| Mahadev Canvas Wall Art |
   | tirupati-balaji | Tirupati Balaji Painting for Home \| Venkateswara Wall Art |
   | lord-rama | Lord Ram Painting for Home & Pooja Room \| Canvas Wall Art |
   | sikh-art | Sikh Art & Guru Nanak Paintings \| Canvas Wall Art USA |
   | swaminarayan | Swaminarayan Painting for Home \| Canvas Wall Art USA |
   | indian-culture | Indian Culture Wall Art \| Classical Dance & Heritage Canvas |
   | christian-art | Christian Wall Art & Religious Canvas Paintings \| USA |
   | wildlife | Wildlife Canvas Wall Art – Horses, Tigers, Peacocks \| USA |
   | landscapes | Landscape Canvas Wall Art for Living Room \| The Art Framer |
   | still-life | Still Life Canvas Wall Art – Florals & Vases \| The Art Framer |
   | living-room | Living Room Wall Art – Large Canvas Paintings \| USA |
   | kids-room | Kids Room Wall Art – Canvas Paintings for Children \| USA |
   | travel-art | Travel Canvas Wall Art – Cities & Journeys \| The Art Framer |
   | deals-discounts | Canvas Wall Art Deals & Discounts \| The Art Framer |
   | corporate-printing | Corporate Printing – Banners, Signage & Certificates \| USA |
   | home-decor-by-space | Wall Art by Room – Living Room, Kids Room, Café \| USA |
   | gifts | Wall Art Gifts – Diwali, Housewarming & Wedding \| USA |
   | hindu-deities, radha-krishna, lakshmi-ganesha, buddha, pichwai-art | title kept; intro text added |

4. Rank Math settings, flipped once: product categories go into the sitemap;
   empty categories become noindex; the organization name is cased
   "The Art Framer". If the owner changes any of these later, the script does
   not flip them back.

### 3c. `functions.php`

One line: the YouTube channel joins Facebook and Instagram in the
Organization schema's `sameAs`.

## 4. YouTube: what is wrong today

| Finding | Detail |
|---|---|
| Channel description is generic | talks about "custom photo to canvas" and "modern interior decor"; never says Hindu, Vastu, pooja room or USA |
| Channel keywords target India | "wall art india", "canvas prints india", "customized gifts india" |
| One video promises free delivery | U_a3R44j_S4: "Free Delivery: Delaware, Pennsylvania, Maryland, New Jersey" |
| Two videos carry the generic channel blurb as their description | wJGiXVAtndk (Lord Ram), U_a3R44j_S4 |
| Hashtags are India-market or vanity | #homeDecorIndia, #interiorInspo, up to 25 per video |
| Playlists cover 8 of 21 videos | no Seven Horses playlist, no Radha Krishna playlist, Lord Ram in none |
| Tag "the art framer" and deity tags are good | kept |

## 5. YouTube: the changes in `tools/youtube-seo-plan.json`

Applied by `tools/youtube-seo.mjs`, which backs up every current value to a
file outside the repository first, prints a before/after diff, and writes
only on `apply`. A `restore` mode puts the backup back.

1. Channel description rewritten around Hindu god paintings and Vastu art
   for Indian-American homes, Delaware studio, shipped across the USA.
2. Channel keywords replaced with the USA keyword map above.
3. Every video description rebuilt from one template: keyword-led hook,
   shop link and shipping line (cost at checkout), the existing meaning
   copy trimmed, a product and studio footer, three to five topic hashtags
   plus #Shorts. The "free delivery" line is removed.
4. Titles tightened to lead with the search term, all under 100 characters,
   emojis dropped from the keyword position. Examples:
   - `Bring Lord Ram's Divine Blessings Home | Premium Canvas Wall Art Modern Wall Decor Ideas |` → `Lord Ram Painting for Home | Shri Ram Canvas Wall Art for Pooja Room & Living Room`
   - `7 Horses Painting Vastu Direction 🐎 | Running Horses Canvas Wall Art` → `7 Horses Painting Vastu Direction | Where to Hang Running Horses Canvas at Home`
   - `Custom Canvas Prints & Framed Wall Art for Home | The Art Framer` → `Hindu God Paintings & Vastu Canvas Wall Art for US Homes | The Art Framer`
5. Tags: 8 to 11 specific tags per video plus 8 common ones, India-market
   tags removed.
6. Playlists: create "Seven Horses Vastu Paintings" (5 videos), "Radha
   Krishna Wall Art" (6), "Lord Rama" (1), "Indian Wall Art for US Homes" (2);
   add the six Radha Krishna videos to the existing "Lord Krishna" playlist.

To run, from a session in the SB - Art Framer environment:

```
node tools/youtube-seo.mjs backup
node tools/youtube-seo.mjs diff
node tools/youtube-seo.mjs apply      # only after the diff is approved
```

## 6. Content plan (not written yet, owner's call)

Eight posts that match what the audience searches, each linking to the
category it sells:

1. 7 horses painting Vastu: direction, wall and room, with what to avoid
2. Pooja room wall ideas for an American apartment
3. Which Hindu deity painting suits which room
4. Ganesha at the entrance: Vastu placement and the right size
5. Diwali gift guide: deity and Vastu canvas art under $100 / $150
6. Radha Krishna painting for the bedroom: meaning and placement
7. How to choose the right canvas size for a US living room wall
8. Griha Pravesh gift ideas for an Indian family in the USA

Also recommended: unpublish or redirect the ten 2023 demo posts (streetwear,
fitness, fashion) and the demo post categories.

## 7. Owner to-dos the code cannot do

1. Set the business address once, with `tools/set-business-address.php`,
   so the Organization schema carries a real Delaware address.
2. In Search Console, confirm the sitemap now lists `product_cat` after the
   first deploy, and request indexing for the 8 deity category pages.
3. In YouTube Studio, after the apply run: set the channel's default
   upload language to English (United States) and add the website link to
   the channel header if it is not already there.
4. Pull 16 months of Search Console queries from the SB session and give
   me the top 200 by impressions; I will adjust the keyword map and any
   title that the data contradicts.

## 8. Found on 15 Oct: the deity categories were orphans

URL Inspection in Search Console, the day after the titles went live:

| Category | Verdict | Referring pages |
|---|---|---|
| seven-horses | URL is on Google | some |
| lord-shiva | Crawled, currently not indexed | a faceted /shop/ filter URL and pichwai-art |
| lakshmi-ganesha | URL is unknown to Google | none |

The header's CATEGORIES menu lists the top level only and the sidebar hides
the children behind a client-side "+", so most deity categories had no
crawlable link anywhere on the site. `inc/deity-links.php` adds a
server-rendered "Shop by Deity & Vastu" row of links to all 13 devotional and
Vastu categories on the shop page, every category page, every product page,
the front page and every post. The sitemap was resubmitted the same day and
indexing requested for the eight main deity categories by hand.

## 9. Batch 2 (15 Oct) and what only the owner can do

Shipped in PR 402: the deity link row on every selling page; every
remaining free-shipping claim (49 places) rewritten on every deploy; eight
articles created as drafts with Rank Math meta; FAQPage schema for any post
ending in a "Frequently asked questions" section (`inc/post-faq-schema.php`);
YouTube descriptions linking to each video's category page.

Left for the owner, with the text ready to use:

**Publish the eight drafts.** Posts → Drafts, read, press Publish. Add a
featured image from the matching collection to each.

**Reviews, for star ratings in Google.** Email past customers once:

> Subject: How does your canvas look on the wall?
>
> Namaste {first name}, it has been a few weeks since your {product name}
> arrived from The Art Framer. Would you take one minute to leave a review
> and, if you like, a photo of it on your wall? It helps other Indian
> families in the USA choose with confidence. {review link}. Thank you,
> The Art Framer, Hockessin, Delaware.

**Backlinks and mentions**, in order of effort:
1. List the studio on Google Business Profile with the Hockessin address
   and the same name, address and phone as the site footer.
2. Pinterest: one board per deity category, each pin linking to the
   category page; the eight articles pinned with their cover images.
3. Temple and community newsletters in Delaware, Pennsylvania, New Jersey
   and Maryland: offer a Diwali gift guide article in exchange for a link.
4. Indian-American lifestyle blogs and Diwali gift roundups: send the
   Diwali gift guide and a sample image.

**Two decisions.** Product names: the customer-facing names repeat
"Canvas Wall Art 3×4 Feet – Floating Frame – Premium Digital Canvas Print –
Living Room & Home Spiritual Wall Décor"; a shorter pattern ("Radha Krishna
Moonlit Melody Canvas Wall Art") is better for Google and for the cart, but
other tooling reads sizes from these names, so it is a planned change, not
a quick one. Home page H1: removed at the owner's request on 16 Jul 2026;
a visually hidden H1 ("Hindu God Paintings & Vastu Canvas Wall Art for
Indian Homes in the USA") would help and is a one-line change if the owner
agrees.

## 10. Live check after batch 2 (15 Oct, 10:10 to 10:30 UTC)

Verified on the server after the deploy finished at 10:05 UTC:

| Check | Result |
|---|---|
| "Shop by Deity & Vastu" row | Present on the category, shop, front and product pages fetched from the server |
| Category sitemap | `product_cat-sitemap.xml` listed in the index; lakshmi-ganesha and seven-horses in it |
| Eight articles | Posts 35024 to 35031 exist as drafts with Rank Math meta |
| Free-shipping wording | **36 places still said it** (down from 49) |

The free-shipping count did not reach zero because the deploy itself put the
words back. `tools/restore-shipping-copy.php`, added on 5 Oct after the
rewrite's `wp_update_post()` save let kses strip builder markup, ran later
in the same deploy and restored every page and Elementor design from its
pre-rewrite backup. Three deploy scripts (`tools/restyle-pages.php`,
`tools/about-real-content.php`, the FAQ templates) also carried the sentences
and re-applied them. PR 403 removes the restore step, makes the rewrite write
straight to the table, matches the sentences as Elementor stores them (JSON
escapes, `&amp;`, `<b>` wrappers, all capitalisations), clears Elementor's
render caches and fixes the wording in the scripts. Expected after merge: 0.

URL Inspection on the 13 deity and Vastu categories at 10:14 UTC (read-only,
from the SB session), before Google had recrawled anything from the deploy
(latest crawl 11 Oct):

| Category | Coverage | Last crawl | In sitemap (per Google) |
|---|---|---|---|
| hindu-deities | Submitted and indexed | 9 Oct | yes |
| radha-krishna | Submitted and indexed | 11 Oct | no |
| lakshmi-ganesha | Discovered, currently not indexed | never | yes |
| lord-shiva | Crawled, currently not indexed | 9 Jul | no |
| seven-horses | Submitted and indexed | 6 Oct | yes |
| vaastu-art | Submitted and indexed | 3 Oct | yes |
| tirupati-balaji | Discovered, currently not indexed | never | yes |
| lord-rama | Submitted and indexed | 3 Oct | yes |
| buddha | Submitted and indexed | 28 Sep | yes |
| sikh-art | Submitted and indexed | 23 Sep | yes |
| swaminarayan | Crawled, currently not indexed | 24 May | no |
| pichwai-art | Submitted and indexed | 2 Oct | yes |
| indian-culture | Submitted and indexed | 4 Oct | no |

Nine of thirteen are indexed; canonicals match on all thirteen. The four
that are not need the owner to press Request Indexing in Search Console
(Lakshmi Ganesha, Tirupati Balaji, Lord Shiva, Swaminarayan); the new link
row gives Google a reason to keep them once it recrawls.

**Update 11:40 UTC.** PR 403 merged at 10:50 but did not deploy on its own:
every file it touched sits in the deploy's `paths-ignore` (tools, docs,
workflows). The deploy was started by hand at 11:12 and finished at 11:29.
`tools/diag-free-shipping.php` then reported 3 places, down from 36, none of
them content: the ZIP checker's JavaScript literal for the never-taken free
branch (Shipping page source), a JavaScript comment that reaches the Cart
page's inline script, and the home page's `_af_elementor_backup_col` row,
which is a backup. PR 404 removes the two script places and stops the
diagnostic counting backups. Expected after it deploys: 0.
