# Master Brochure DAGzVCQ8PBs — section map and reading progress
(page numbers are 1-indexed Canva pages; page labels read from design_content)

## Confirmed section boundaries
| Pages | Codes | Section | Read? |
|---|---|---|---|
| 1–95 | RK 01–95 | Radha Krishna | NO (91+ pages, biggest left) |
| 96–98 | LG 01–03 | Lakshmi–Ganesha | NO |
| 99–113 | LS 01–15 | Lord Shiva | **DONE** |
| 114–125 | SH 01–12 | Seven Horses | **DONE** |
| 126–140 | TP 01–16 (no TP 04) | Tirupati Balaji | **DONE** |
| 141–~153 | MG 01–.. | Murugan | NO |
| ~154–180 | HD 01–~27 | Hindu Deities | NO |
| 181–193 | LB 01–13 | Lord Buddha | **DONE** |
| ~194–195 | SA 01–03 | Sikh Art | NO |
| ~196–197 | PA 01–.. | Pichwai | NO |
| ~198–202 | IC 01–04+ | Indian Culture | NO |
| 203–212 | LC 01–10 | Landscapes | **DONE** (see below) |
| 213–~239 | SL 01–~25 | Still Life | NO |
| ~240–~258 | WL 01–~16 | Wildlife | NO |
| ~259–277 | KR 01–~18 | Kids Room | NO |
| 278–~321 | LI 01–~35 | Living Room | NO |
| ~322–358 | AA 01–~20+ | Abstract Art | NO |

## Landscapes (LC 01–10, pages 203–212) — read, nothing to change
| Code | Page shows | Product |
|---|---|---|
| LC 01 | flowing waterfall, geometric sunrise | #27133 (applied) |
| LC 02 | pathway towards a celestial entity | #13722 (applied) |
| LC 03 | misty cliffs over calm waters | #18897 (applied) |
| LC 04 | orange moon over pine silhouettes, red/grey | **unclaimed** |
| LC 05 | pink moon, bare tree, two white egrets over misty water | **unclaimed** |
| LC 06 | golden moon over misty blue mountain and lake | **unclaimed** |
| LC 07 | minimal sunrise, single red tree, hills, birds | **unclaimed** |
| LC 08 | pines, big yellow sun, birds, watercolour | **unclaimed** |
| LC 09 | whimsical train through blooming meadows | #16566 (applied) |
| LC 10 | three overlapping circles, tree silhouettes, earthy | **unclaimed** |

All 147 shared-code tiles were scanned against LC 04–08 and LC 10. **None match.**
Whatever holds those six pages is among the 215 unique-code products or the 68
with no code — neither set has been drawn yet.

Two consequences worth keeping:
- #25474 (lantern lake, starry night, boat) is NOT a Landscapes picture. It is
  on LS 11 wrongly. Try Living Room (LI) next.
- #26628 (floral arch, pale botanical) is NOT a Landscapes picture. Also on
  LS 11 wrongly. Try Still Life (SL).

## Held rows — waiting on a picture that has never been drawn
- #232 -> SH 01. Blocked until #7662 is seen (it holds SH 01 now).
- #141, #14678, #229, #20587, #20410 — TP codes, pictures never drawn.
- #20169, #21954, #17341, #14739, #19453, #21442 — LS codes, never drawn.
- #19759 (SH 06), #21014 (SH 09) — never drawn.
All fourteen are in the AF_SHEET_IDS list on the deploy step in PR #185.

## Rule being applied (owner's instruction)
Match the product picture to the book picture. Captions and product titles are
unreliable in both directions — proven repeatedly. If a picture is in no book
page, the product carries no code rather than someone else's.

## Lord Rama (LR 01–09, pages 145–153) — read
Murugan MG 01–04 is 141–144; Hindu Deities HD 01 starts at 154. So the book's
Lord Rama section is **nine pages only**.

| Code | Page shows | Product |
|---|---|---|
| LR 01 | Ram, Sita and Lakshman, warm orange/cream painterly | **#7810** (currently on LR 03) — blocked, LR 01 held by #148 |
| LR 02 | Ram, Sita, Lakshmana and Hanuman kneeling, pink flowers | #21259? (no tile yet) |
| LR 03 | Ram and Sita close portrait, ornate gold, horizontal | **#21136 — already correct** |
| LR 04 | black Rama idol, pink silk, marigolds, pale carved arch | unresolved |
| LR 05 | black Rama idol, ornate silver-grey prabhavali, pink/white garlands | #15913 plausible but NOT proven at thumbnail size |
| LR 06 | black Rama idol, yellow-gold dhoti, multicoloured garlands | unresolved |
| LR 07 | Ram Lala idol, ivory and gold shrine | unresolved |
| LR 08 | Rama statue in red attire, orange marigold arch | unresolved |
| LR 09 | Rama idol in white and gold, silver ornate arch | unresolved |

### THE BIG STRUCTURAL FACT
The site has LR codes running to **LR 39**. The book has only **LR 01–09**.
Everything on **LR 10–LR 39 is definitely wrong** — 18 products. Those are old
"Living Room" codes from before the book renamed that section to LI. They are
not simply renumbered: LI 24/25/32 turned out to be horses while the LR 24/25/32
products were not, so each one still has to be matched picture to picture in the
LI section (pages 278–~321).

### Ready to commit once PR #185 merges — see staged-lr-rows.csv
Eight clears, every one checked against all nine pages with the product tile in
hand: #20288, #16762, #21832, #16035 (four Christian artworks), #30531, #19944,
#7825, #27750.

### Another duplicate listing
**#15913 "Lord Vishnu Statue" and #17543 "Lord Balaji Idol" are the same
photograph** — identical black idol, pink garland, dark carved arch. Two
products, two different titles, one picture. Same situation as #31890/#15730.

### Pictures still needed for the LR section
#148 (holds LR 01, blocking #7810), #21259, #11560, #25240, #22077, #21893,
#18229 — plus #17543 and #15913 at full size to settle LR 04 vs LR 05.

## Lakshmi–Ganesha (LG 01–03, pages 96–98) — read, and closed
Three pages, all three now matched to a product by picture:

| Code | Page shows | Product |
|---|---|---|
| LG 01 | Ganesha left, Lakshmi right, both on pink lotuses, warm sky | **#8412** — had no code at all |
| LG 02 | Lakshmi left, Ganesha right, gold throne, green arch, red curtain | **#29951 — already correct** |
| LG 03 | Lakshmi left holding raised lotuses, Ganesha right, dark maroon arch | **#26753 — already correct** |

Because all three pages are claimed by a confirmed match, **any other product
holding an LG code is wrong regardless of what its picture shows.** That
disposes of #29456, #27981, #23558 and #8582 without needing their pictures —
though #29456 and #27981 were checked anyway and are a Maratha war scene and a
veiled portrait.

This is a general rule worth reusing: a short section, fully matched, settles
every remaining holder in it by elimination.

## LS 04 found, from the LG section
#18290 "Shiva Meditation Art" was parked on LG 03. Its picture is **LS 04** —
Shiva's face with the crescent moon and gold tripundra on a field of orange,
magenta and teal — which is exactly the Lord Shiva page that had no owner.
LS 04 was held by three products that are none of the fifteen Shiva pages
(#23252 a hand with prayer beads, #8424 the Ganga Aarti, #3362 a Ganesha), so
those clear first and #18290 moves in behind them.
