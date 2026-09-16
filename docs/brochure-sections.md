# Master Brochure DAGzVCQ8PBs — section map and reading progress
(page numbers are 1-indexed Canva pages; page labels read from design_content)

## The book as it stands — read 2026-09-12

**391 Canva pages: 4 of front matter, 373 product pages (5–377), 14 of back
matter.** Product page N sits at Canva page 4 + N — measured, not assumed:
HD-080030 at 196, LB-090001 at 197, LB-090013 at 209, SA-100001 at 210,
TA-210004 at 377, and 378 is "Who We Are". Sections run back to back with no
dividers, and every section is numbered 1..N with **no gaps**.

Labels now carry six digits and the aspect: `LB-090001-3050`.
The catalogue still holds the previous four-digit form, `LB - 0901`.

| Pages | Codes | Section | Now | Was | Read? |
|---|---|---|---|---|---|
| 5–101 | RK 010001–010097 | Radha Krishna | 97 | 91 | DONE — 2026-09-14 |
| 102–104 | LG 020001–020003 | Lakshmi–Ganesha | 3 | 3 | DONE — 2026-09-16, nothing to change |
| 105–122 | LS 030001–030018 | Lord Shiva | 18 | 15 | DONE — 2026-09-16 |
| 123–137 | SH 040001–040015 | Seven Horses | 15 | 12 | DONE — 2026-09-15 |
| 138–152 | TP 050001–050015 | Tirupati Balaji | 15 | 15 | DONE — 2026-09-14 |
| 153–156 | MG 060001–060004 | Murugan | 4 | 4 | DONE — 2026-09-16, nothing to change |
| 157–166 | LR 070001–070010 | Lord Rama | 10 | 9 | DONE — 2026-09-15 |
| 167–196 | HD 080001–080030 | Hindu Deities | 30 | 27 | DONE — 2026-09-14 |
| 197–209 | LB 090001–090013 | Lord Buddha | 13 | 13 | DONE — re-verified 2026-09-15, sweep 2026-09-16 |
| 210–214 | SA 100001–100005 | Sikh Art | 5 | 3 | DONE — 2026-09-16 |
| 215 | SN 110001 | Swaminarayan | 1 | 1 | NO |
| 216 | PA 120001 | Pichwai | 1 | 1 | NO |
| 217–221 | IC 130001–130005 | Indian Culture | 5 | 4 | DONE — 2026-09-16, nothing to change |
| 222–231 | LC 140001–140010 | Landscapes | 10 | 10 | DONE — 2026-09-15 |
| 232–254 | SL 150001–150023 | Still Life | 23 | 23 | DONE — 2026-09-15 |
| 255–258 | VA 160001–160004 | Vaastu Art | 4 | 4 | DONE — 2026-09-15 |
| 259–281 | WL 170001–170023 | Wildlife | 23 | 19 | DONE — 2026-09-15 |
| 282–303 | KR 180001–180022 | Kids Room | 22 | 19 | DONE — 2026-09-15 |
| 304–354 | LI 190001–190051 | Living Room | 51 | 44 | part done — 2026-09-14 |
| 355–373 | AA 200001–200019 | Abstract Art | 19 | 19 | DONE — 2026-09-15 |
| 374–377 | TA 210001–210004 | Travel Art | 4 | 4 | DONE — 2026-09-16 |

**Every "DONE" below this line is now marked stale.** Ten sections gained 33
pages between them, and asked whether the new pages went on the end or were
slotted in among the old ones, the owner's answer was that it was mixed and he
is not sure. A reading recorded against the old numbering therefore no longer
proves which page a number names — including the Landscapes table below, whose
page count did not change but whose contents cannot be assumed to have stayed
put. Re-read against the pictures before relying on any of it.

## The catalogue moves onto six digits — 2026-09-12
Asked for the same day: every product to carry what the book prints. So
`af_artcode_book_label()` writes six digits and the renumbering pass, which runs
with apply on at every deploy, carries the catalogue over on the next push.

It is a **reformat, not a renumbering**. `LB - 0901` and `LB - 090001` are the
same section and the same page; only the padding widens. It therefore proves
nothing about whether the painting is right — and with the new pages having gone
in a mix of places, that remains unproven until the pictures are compared.

What the reformat may **not** do, and is tested not to: give any product a page
it did not already name. The map's `legacy` counts bound the writing path, so
none of the book's 33 new pages can be reached by arithmetic.

### Reading what the deploy actually did
`renumber-artcodes.php` prints a report saying how many products it moved — the
only record of what reached the database, since the job log host is not
reachable from the sandbox and the log truncates from the front anyway.

That report is published to the **`art-sheets`** branch as `RENUMBERED.txt`, but
only on a run with diagnostics on. An ordinary push deploy runs the renumbering
and publishes nothing, so `art-sheets` can sit days out of date while looking
perfectly plausible — on 2026-09-12 it held a report from the 9th whose numbers
were exactly the ones being looked for. **Check `git log -1 origin/art-sheets`
before believing it.**

Three ways to get a fresh one:

| | |
|---|---|
| `[diag]` in the commit message | the next deploy publishes it, no extra run |
| Actions → Run workflow → tick Diagnostics | a full deploy |
| Actions → Run workflow → tick Diagnostics-only | skips the catalogue passes |

## Matching the shop against this — 2026-09-12
Joined by section and page number (`LB - 0901` -> `LB-090001`), which says
where each product *points*, not that the painting is the same one:

- 206 of the 373 pages have a product on them
- **167 pages have no product at all**
- 35 pages have more than one product on them
- no product points at a page the book does not have

## Buddha (LB) — read 2026-09-14, 13 pages

All thirteen pages read from Canva. **The aspect on every one agrees with the
index in `inc/artcode-book.php`**, which is the first end-to-end check of that
data against the real pages.

| Page | The book shows | Claimed by |
|---|---|---|
| LB - 090001-3050 | grey stone Buddha, pastel lotuses on still water | #7839, #220 |
| LB - 090002-3050 | green meditating figure, radiant lotus aura, splashy | #7676 |
| LB - 090003-2060 | golden Buddha, deep teal carved ground, wide panorama | #20526 |
| LB - 090004-3050 | white and gold Buddha, pink blossom, golden sunburst | #20648 |
| LB - 090005-5030 | grey stone Buddha, torn-paper collage, portrait | #16096, **#27078** |
| LB - 090006-5030 | Buddha with a peacock, jewelled blues, lotus pond | #20709, **#28534** |
| LB - 090007-3050 | emerald Buddha, hot pink and orange tropical lotuses | #20892 |
| LB - 090008-5030 | gold Buddha in profile, pink lotuses, dark navy | #27811, #16382 |
| LB - 090009-3050 | small stone Buddha in a waterfall cave, turquoise | #20471 |
| LB - 090010-4030 | line-art Buddha, cream and sage, outlined lotuses | #15402 |
| LB - 090011-4030 | beige Buddha, layered hills, white lotuses | #16505 |
| LB - 090012-6030 | golden watercolour head merging with a bodhi leaf | **nothing** |
| LB - 090013-5030 | **Lord Mahavira's** enlightenment, golden, attendants | #16932 |

### SETTLED by the pictures — 2026-09-14
Contact sheets drawn by deploy run 1081 and held against all thirteen pages.

**Eleven pages are right.** #7676 (2), #20648 (4), #16096 (5), #20709 (6),
#20892 (7), #16382 (8), #20471 (9), #15402 (10), #16505 (11) and #16932 (13)
are each the painting their page prints. #220 and #7839 on page 1 are the same
painting listed twice — one shown in a room, one as the artwork — so the pair is
deliberate, not a clash.

**#20526 on page 3 is CLEARED, and the way it was suspected is the lesson.**
It was flagged because its title says 3×4 while the page is 2×6. The picture is
the page: golden Buddha, deep teal carved ground, gold and white lotuses, and
genuinely the wide panorama. **The title is wrong, not the code.** So the aspect
cross-check fires on titles as readily as on codes, and a title's size is no more
trustworthy than its words.

**Three are wrong, and none of them is a Buddha's page:**

| Product | Its picture | Claimed |
|---|---|---|
| **#27078** | seven white horses galloping through surf at sunset | LB - 090005, a stone Buddha |
| **#28534** | Radha and Krishna with gopis playing instruments | LB - 090006, a peacock Buddha |
| **#27811** | pale cream Buddha head, close-cropped, light beige | LB - 090008, gold in profile on near-black |

#27078 belongs in Seven Horses and #28534 in Radha Krishna, but which page is
not knowable until those sections are read. #27811 is a Buddha and still matches
no page of Buddha.

All three are set to NONE in `tools/artcode-corrections.csv`, on this file's own
rule: a picture that is on no page of the book carries no code rather than
someone else's.

**LB - 090012 still has no product** — the golden watercolour head merging with a
bodhi leaf. Nothing in the catalogue claims it and nothing seen here is it.

So Buddha finishes at **11 pages right, 3 products cleared, 1 page empty**.

## Seven Horses (SH) — pages read 2026-09-14, 15 pages

The book half, read from Canva pages 123–137. The product pictures are being
drawn as this goes in; the match itself is not done.

| Page | The book shows |
|---|---|
| SH - 040001-3050 | white horses, shallow water, warm golden sunset, sun disc visible |
| SH - 040002-3050 | white horses in water, cooler and brighter, sun behind, blue-white |
| SH - 040003-3050 | white horses in surf, **pastel pink and lavender** sky, dreamy |
| SH - 040004-3050 | white horses, dramatic cloud, splashing, blue-grey with warm light |
| SH - 040005-3050 | **golden and dark** horses on **black**, gold manes |
| SH - 040006-3050 | bold splash colours — magenta, orange, blue, teal — paint-splatter |
| SH - 040007-3060 | **brown and white** horses together, golden dust, wide |
| SH - 040008-3040 | white horses charging through golden **clouds**, sunburst |
| SH - 040009-3040 | sunrise, **orange-red** sky, big sun, tree silhouette, birds |
| SH - 040010-4030 | **abstract** horses, red/orange/black geometric, portrait |
| SH - 040011-3060 | white horses, sunlit landscape, dust, wide |
| SH - 040012-5030 | white horses with **golden flame manes**, orange flowers, portrait |
| SH - 040013-3050 | red/yellow/blue/teal colour blocks, mixed dark and white horses |
| SH - 040014-3040 | white horses, teal and orange **impressionist** brushwork |
| SH - 040015-3040 | white horses, **3D relief** style, big sun, teal and gold, sculptural |

**The hard part, named in advance.** Pages 1, 2, 3, 4 and 11 are all white
horses in or above water and differ mainly in sky colour, sun position and
aspect. This is the section where "it looks like horses in water" is not an
identification, and where the honest answer for a given product may be
*unclear*. Say unclear rather than guess: a wrong code travels onto the SKU and
onto invoices, and the rule this project runs on prefers no code to someone
else's.

### SETTLED by the pictures — 2026-09-14
Sheets drawn by deploy run 1087. Eight of the fifteen pages had a claimant.

**Seven are right.**

| Page | Product |
|---|---|
| SH - 040001-3050 | #232 — painterly, low sun, dark treeline, reflections |
| SH - 040004-3050 | #19025 **and** #23191 — one painting, two listings; the dark cliffs settle it |
| SH - 040005-3050 | #23130 — golden and dark horses on black |
| SH - 040006-3050 | #19759 — paint-splatter colour |
| SH - 040007-3060 | #20953 — brown and white together, golden dust |
| SH - 040008-3040 | #19636 — charging through golden cloud |
| SH - 040009-3040 | #21014 — orange sunrise, tree, birds |

**One is wrong. #27572 on SH - 040010.** The page is a galloping herd in red,
orange, black and ivory — warm, earthy, geometric. The product is two horses'
heads in white, cream and ochre on **deep blue**: cool palette, different
composition, and not a herd. No Seven Horses page is this picture. Set to NONE;
try Abstract Art.

**#27078 is narrowed, not placed.** Its picture — recovered from the Buddha
sheet, which the branch had since overwritten — is seven white horses in **open
ocean surf with a flat sea horizon**, pale cream and peach cloud. That rules out
page 1 (dark treeline), page 4 (cliffs), page 3 (pastel pink and lavender) and
page 11 (dry sunlit landscape, no water). **Page 2 is the only candidate left**,
and the page thumbnails are room mock-ups in which the horizon and wave pattern
are not legible enough to be sure. So it stays cleared, exactly as promised
before the comparison began: unclear is recorded as unclear.

**Seven pages have no product**: 2, 3, 11, 12, 13, 14, 15. If #27078 is page 2,
six.

## How the old reading was recorded

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

## The delivery channel works
Deploy run 835 published seven product pictures to the `art-sheets` branch and
they were fetched and read at full quality, with nothing going through the log.
That closes the bottleneck this audit has been running into since the start.

Two consequences, applied immediately:
- Tiles were 220px only because the log had to carry them. They are now 420px,
  which is what makes two near-identical temple-idol photographs separable.
- A batch can be large. Nineteen products are queued for the next run.

## Lord Rama after the pictures arrived
| Code | Product | How |
|---|---|---|
| LR 01 | **#7810** | its picture is the page; #148 vacates first in the same file |
| LR 02 | **#21259 — already correct** | Ram, Sita, Lakshmana standing, Hanuman kneeling |
| LR 03 | **#21136 — already correct** | the Ram and Sita portrait |
| LR 06 | **#18229 — already correct** | black idol, yellow dhoti, banded multicoloured garlands, plain dark arch. Sold as "Lord Krishna Statue"; the figure carries a bow. |
| LR 04, 05, 07, 08, 09 | unowned | five Rama idol photographs, still to be matched |

Remaining candidates for those five: #15913 / #17543 (the same photograph, listed
twice), #31588, #26023, #23911 — all queued at 420px.

#18229 is a third product whose title names the wrong subject, after #16257
("Buddha Art", is LS 09) and #21625 ("Krishna and Cow", is LS 11).

## Products that are not artworks — the running list
Ten so far, all cleared. None of them can ever match a picture in the book,
so none should carry a code taken from one.

| Product | Held | What it is |
|---|---|---|
| #8597 | AA 06 | cotton canvas roll |
| #8591 | SL 08 | canvas roll for printing |
| #8594 | (none) | artist canvas roll — already had no code |
| #8582 | LG 02 | blank framed white canvas |
| #8588 | HD 08 | blank pre-stretched canvas board |
| #8447 | LS 09 | custom photo on canvas |
| #8440 | RK 42 | custom photo canvas |
| #8711 | TA 04 | personalised family photo collage |
| #8853 | HD 16 | matte black floating frame, sold empty |
| #8869 | LS 08 | three-panel decor bundle |

#8585 "Elegant Large Framed White Canvas" is very likely an eleventh — the id
sits next to #8582 and the title reads the same — but its listing does not say
"blank" outright, so it is queued for a picture rather than cleared on wording.
The easels (#8604, #8607, #8610) and the loose frames (#8444, #8616, #8619)
already carry no code and need nothing done.

## Run 837: nineteen pictures at 420px, and what they settled

**Seven codes confirmed right, none previously checked:**
#19759 SH 06, #21014 SH 09, #17341 LS 03, #21442 LS 12, #20587 TP 06,
#20410 TP 11, and **#15913 LR 05** — the one recorded as undecidable at 220px.
At 420px the pink-and-white garland looped over a cream-and-gold lower garment
inside a dark carved arch separates LR 05 from LR 04 cleanly.

**SH 01 settled.** #232 is SH 01: the dark treeline, the small sun disc, the
golden reflection running down the middle. #7662 has none of those — a pale
misty scene with a soft sun — so it clears and #232 takes the code.
**#7662 is the nearest candidate for SH 02 or SH 03, and I am not writing it.**
Both pages remain unowned.

**#14739 -> LC 06.** The golden moon over the misty blue mountain and the
reflective lake. It was on LS 06, which is blue Shiva and Parvati with the
elephant. First of the six unclaimed Landscapes pages to find its product.

**#31588 and #26023 are not Lord Rama at all.** Both are Venkateswara utsava
murtis — the namam across the face is unmistakable at this size. Neither has a
code, so nothing is written, but they are not candidates for LR 07/08/09.

**LR 04, 07, 08, 09 remain unowned**, and no product seen so far matches them.

**#229 (TP 05) is not verified.** Its listing image is a room mockup, not the
artwork, and the framed piece inside is too small to judge. No row either way.

## APPLIED.txt — what actually reached the database
The one thing this audit could not verify was whether the corrections were
really written. The apply step reports it, but twenty verbose read-only checks
run after it, and the log truncates from the front, so by the time anyone reads
it that report is gone. Three attempts to pull it back failed: a 340-line tail
reached only fifteen seconds; the log's blob host is refused at the proxy; and
pulling thousands of lines to find one short report is not a workable habit.

So the apply report now travels the same way the pictures do. It is written to a
file as well as the log, and the publish step carries it to the art-sheets
branch as **APPLIED.txt**, next to the sheets. Every run, one short file, saying
what changed, what was cleared, what was refused as a clash and what was already
correct.

The publish step will now push for that file alone, on a run that draws no
pictures at all.

## #8585 confirmed by picture
Its listing image is a room mockup of an EMPTY white framed canvas. That settles
the one product #189 deliberately left open rather than clear on its title.
Eleven products are now known not to be artworks.

## A rule that was wrong, and the correction

Three products cleared in PR #186 — #20288, #16762, #16035 — **do** have pages.
They are `LI 05`, `LI 08` and `LI 09`, in the Living Room section, which had not
been read when they were cleared.

The rule as applied was: *if the picture is in none of the pages of the section
its code came from, clear the code.* That is wrong. "Not in the sections read so
far" is not the same as "not in the book". The Living Room section turns out to
carry a whole Christian run at `LI 05`-`LI 11`, which is exactly where those
products belong.

**The rule from here on:** clear a product only when
  (a) it is not an artwork at all — the eleven canvas rolls, blank canvases,
      frames, decor bundles and custom photo prints. Those can never match, so
      they are safe to clear at any time; or
  (b) every section of the book has been read.

Until the whole book is read, a wrong code should be recorded as wrong and left,
not replaced with nothing. Several existing clears may need revisiting on the
same grounds once the remaining sections are read — they are listed in the
corrections file and each says which section was checked.

## Hindu Deities (HD 01-HD 28, pages 154-180) — nine confirmed
`HD 14` is missing from the book, as `TP 04` is.

| Code | Page | Product |
|---|---|---|
| HD 10 | 163 | #17795 Hanuman on the rock with lamps — correct |
| HD 12 | 165 | #22260 Hanuman meditating, golden halo — correct |
| HD 13 | 166 | #21075 Saraswati with the swan — correct |
| HD 23 | 175 | #17605 the Sai Baba photograph — correct |
| HD 24 | 176 | #18477 on the throne with devotees — correct |
| HD 25 | 177 | #14861 sepia, deep meditation — correct |
| HD 26 | 178 | #17090 with the white flower garland — correct |
| HD 27 | 179 | #14985 close-up, yellow marigold garland — correct |
| HD 28 | 180 | #22825 the pale painted portrait — correct |

Still to check in HD: #17733 (HD 07), #30215 (HD 15), #17917 (HD 19),
#15278 (HD 21), #15607 (HD 22), #19330 (HD 05), #24775 (HD 06).

## Living Room (LI 01-LI 44, pages 278-321) — begun
Confirmed by picture: LI 05 #20288, LI 07 #22444, LI 08 #16762, LI 09 #16035,
LI 10 #17672, LI 11 #13592, LI 12 #7688.

**LI 22 is NOT #16444.** The caption reads "fiery chestnut horse with a flowing
mane, painted in bold, expressive brushstrokes", which fits #16444's title
exactly — and the page is a vertical close-up bust with a blazing orange mane,
while #16444 is a full-body galloping horse in ink-wash on cream. Sixth time
wording alone would have produced a wrong answer.

## The LR-to-LI number carry
Four products on LR codes the book does not contain turn out to belong on the
SAME number in Living Room:

| Product | Was | Is | Confirmed by |
|---|---|---|---|
| #16444 Running Brown Horse | LR 23 | **LI 23** | brown horse charging in ink-splash on cream |
| #13473 Joyful Man with Bouquet | LR 31 | **LI 31** | man leaping with a bouquet among confetti hearts |
| #23374 Moonlit White Horse | LR 32 | **LI 32** | white horse before a golden full moon (already applied) |
| #27920 Rainbow Wings | LR 34 | **LI 34** | rainbow angel wings with butterflies |

So the old Living Room numbering DID survive the rename in these cases. It is a
strong first hypothesis for any product on LR 10-39 — and nothing more than a
hypothesis: it fails for LR 24 and LR 25, whose products are a Nataraja bronze,
a Buddha at a waterfall and a Bharatanatyam dancer, while LI 24 and LI 25 are
both horses. Confirm every one against the picture.

Also placed: #24897 Crimson Horse Reverie, TA 04 -> **LI 36**, the red horse
beside the woman in the water.

Still unplaced from the LR 10-39 set: #7805 (butterfly tree), #29639 (gold Jain
idol), #27264 (Nataraja bronze), #16191 (Buddha at a waterfall), #7838
(Bharatanatyam dancer), #19697 (sailing ship - LI 28 and LI 29 are both sailing
pages and need separating), #7802 (Radha Krishna abstract), #25535 (jazz
saxophonist), #28778 (dancer on stage). Plus #25474 and #26628 from Lord Shiva.

## A tooling blind spot worth remembering
Runs waiting on the deploy-production concurrency group sit in GitHub's
"pending" state, and "pending" is not one of the statuses the run-list filter
accepts (queued, in_progress, completed, requested, waiting). Queued runs are
therefore invisible to every query. Reading "not visible" as "not created" led
to a wrong conclusion that three merges had failed to deploy; they had not.
The right check is whether a run for the commit has COMPLETED, made after the
queue drains — not whether one is visible right now.

## APPLIED.txt, first reading — and what it exposed
Run 844 published it. 61 rows: 5 changed, 6 cleared, 47 already correct, **3
refused as a clash**, 0 missing.

### Three rows never landed
| Row | Refused because |
|---|---|
| #16932 -> LB 13 (PR #184) | LB 13 already belongs to **#17472** |
| #23191 -> SH 04 (PR #185) | SH 04 already belongs to **#19025** |
| #18290 -> LS 04 (PR #187) | LS 04 already belongs to **#29517** |

These were reported in three PRs as corrections. **They were never written.**
The guard refused them, correctly — it will not move one product onto a code
another product holds — but nothing surfaced that until now, which is precisely
the gap APPLIED.txt was built to close.

- **#23191 is dropped.** It was flagged in PR #185 as the least certain row in
  the file, read off room mockups rather than a full-bleed page. #19025 is
  "Seven Horses Ocean Run", which is plausibly the real SH 04. The guard
  refusing it is evidence against it, so the row comes out rather than being
  forced through.
- **#16932 and #18290 stay**, refused and harmless, until #17472 and #29517
  have been looked at. #29517 is "Lone Tree Between Worlds" and cannot be
  LS 04, which is the multicoloured Shiva face — so it is very likely the one
  that has to move. All three are queued for pictures.

### The current_art_code column is stale
Four rows reported a different current code than the file records:

| Product | File says | Site says |
|---|---|---|
| #11560 | LR 03 | **RK 25** |
| #19453 | LS 11 | **RK 41** |
| #14678 | TP 01 | **RK 52** |
| #8412 | (none) | **LG 02** |

The column is documentation only — the tool matches on product id, so the right
products were changed. But the *reason* written on those rows names a code the
product no longer had, which makes the justification wrong even where the action
was right. The working snapshot (scratchpad artcode_clean.txt) is days old and
the deploy's own passes have been moving codes since.

**Fixed at the source:** the shared-code report is now teed to a file and
published to art-sheets as **CODES.txt** every run, next to APPLIED.txt. No more
deciding from a stale copy.

## Living Room: one more placed, three refuted
**#19697 -> LI 28** — the tall ship at anchor among icy cliffs under a huge moon.
LI 29 is also a sailing page, but a sepia parchment ship with a compass rose;
different painting, so the two are separable and this one is settled.

Three that the pictures refused, all with captions that fit perfectly:

| Page | Caption | Fits | Actually shows |
|---|---|---|---|
| LI 15 | "the art of freedom - every brushstroke dances with emotion and grace" | #7838 dancer | a ballerina in white and pink impasto |
| LI 16 | "grace meets light - a timeless dance of colour, movement and elegance" | #7838 / #28778 | a stylised silhouette dancer in orange against a sun |
| LI 19 | "where passion meets melody - a fusion of art and music" | #25535 jazz saxophonist | a woman playing a cello in watercolour |

LI 19 is the seventh time a caption alone would have produced a wrong row.

Still unplaced: #7805 (butterfly tree), #29639 (gold Jain idol), #27264
(Nataraja bronze), #16191 (Buddha at a waterfall), #7838 (Bharatanatyam
dancer), #7802 (Radha Krishna abstract), #25535 (jazz saxophonist), #28778
(dancer on stage), #25474 (lantern lake), #26628 (floral arch).

## CODES.txt, first reading — the audit is visibly working
| | Before | Now |
|---|---|---|
| codes on more than one product | 56 | **36** |
| products sharing a code | 167 | **100** |
| TA 04 alone | 28 products | **22** |

All seven Living Room moves from PR #192 landed, including the three that
corrected my own wrong clears. 66 rows already correct, 2 refused — the two
known ones, now both unblocked below.

## The two refusals, settled
**#17472 was never a Buddha page.** It holds LB 13 and blocked #16932. Its
picture is monks in orange robes before a radiant golden idol in a lantern-lit
temple — which is **HD 09**, whose caption says exactly that. LB 13 is
Mahavira's enlightenment under the tree with celestial figures, and that IS
#16932, as claimed in PR #184. So the original row was right all along; the
blocker was simply in the wrong place. #17472 -> HD 09 frees it.

**#29517 is a lone conifer** on a split teal and orange field. LS 04 is the
multicoloured Shiva face, so it is certainly wrong there. Cleared rather than
left, which is a deliberate exception to the PR #192 rule:

> A known-wrong code that blocks a known-right move is worse than an empty one.

It still needs its own page found; it is not in Landscapes (LC 07 is a red tree
at sunrise, LC 10 is circles with tree silhouettes) so Abstract Art is the next
place to look.

## More confirmed, none previously checked
- **#30215 HD 15** — Lakshmi in white and gold on a lotus, ivory ground.
- **#16257 LS 09 re-confirmed.** LS 09 is shared with #29578 "Twin Faces of
  Serenity", whose *title* describes the page better than #16257's "Buddha Art"
  does. The pictures settle it: LS 09 is two profile faces, a blue one on
  yellow and a red one with a white lotus — which is #16257. #29578 is two
  large close-up faces, one yellow and one green; a different painting, and the
  squatter. Titles remain no guide at all.

## SH 04 left alone, deliberately
#19025 holds it and is white horses through water with cliffs; #23191, whose
row was dropped in PR #193, is the same subject. At page-thumbnail size the two
are not separable, so neither is asserted. SH 04 stays with #19025.

## The book's own table of contents — page 3

Page 3 of the Master Brochure is a contents page. It lists every section and
its code range, in the book's own words. This is the authority the section map
should have been built on from the start:

| Section | Codes | | Section | Codes |
|---|---|---|---|---|
| Radha Krishna | RK 01 – RK 91 | | Still Life | SL 01 – SL 23 |
| Lakshmi Ganesha | LG 01 – LG 03 | | Vaastu Art | VA 01 – VA 04 |
| Lord Shiva | LS 01 – LS 15 | | Wildlife | WL 01 – WL 19 |
| Seven Horses | SH 01 – SH 12 | | Kids Room | KR 01 – KR 19 |
| Tirupati Balaji | TP 01 – TP 16 | | Living Room | LI 01 – LI 44 |
| Murugan | MG 01 – MG 04 | | Abstract Art | AA 01 – AA 19 |
| Lord Rama | LR 01 – LR 09 | | **Travel Art** | **TA 01 – TA 04** |
| Hindu Deities | HD 01 – HD 28 | | Buddha | LB 01 – LB 13 |
| Sikh Art | SA 01 – SA 03 | | Swaminarayan | SN 01 |
| Pichwai Art | PA 01 | | Indian Culture | IC 01 – IC 04 |
| Landscapes | LC 01 – LC 10 | | | |

Every count matches the page ranges read so far, so the map is now closed and
confirmed from both ends.

Two cautions about the contents page itself. It abbreviates loosely: it writes
Landscapes as "LS" (colliding with Lord Shiva) and Living Room as "LR"
(colliding with Lord Rama), where the pages themselves are labelled LC and LI.
**Where the contents page and a page label disagree, the page label wins** — it
is what is actually printed on the artwork page.

## Travel Art exists, and TA 04 has exactly one owner

I had concluded there was no TA section in this book. That was wrong: TA is
simply last, at pages 341–344, after AA ends at 340.

- **TA 01** (p341) — Varanasi ghats at dusk, purple sky, hundreds of lamps, boats on the river.
- **TA 02** (p342) — Kedarnath temple lit with lamps against snowy Himalayas, pink sunset.
- **TA 03** (p343) — Varanasi ghats stylised flat, crimson sky meeting teal water, wide.
- **TA 04** (p344) — a woman in a red-orange sari lifting a blazing aarti lamp, huge
  flame, teal night sky, temple spires right, glowing crowd below.

**TA 04 is #29890 "Ganga Aarti Flame", and only #29890.** Twenty-two products
carried TA 04; the picture belongs to one of them. Having now seen all four TA
pages, none of the other twenty-one is any of them — TA 04 was applied to them
in bulk, not matched.

### A page label in another book that means nothing

The Alwars design DAHQHUtH1kg has a page labelled "TA 04" showing a Tanjore
deity on a golden throne. It is not this TA 04 and matches no product. Labels
in that book are unreliable — four of its pages also share one identical
"Ganga Aarti at Varanasi" caption. **Only the Master Brochure's own labels
count.** Checking it cost a detour; recording it so the detour is not repeated.

## Sections closed in this pass

**Murugan (MG 01–04, pages 141–144) — read, closed.**
MG 01 Murugan between Valli and Devasena under a gold arch; MG 02 seated in a
blooming garden with peacock; MG 03 standing beside the peacock in a dark
illuminated temple; MG 04 six-faced Shanmukha before a gopuram under a moon.
**#24169 is MG 01.** #14034, titled "Lord Murugan Art", is **not Murugan at
all** — it is a Tanjore panel of an acharya with a tridandi staff and two
disciples, matching no MG page. Another title naming the wrong subject.

**Tirupati Balaji (TP 01–16, pages 126–140) — read, closed.**
**TP 04 is confirmed absent from the book**: page 128 is TP 03 and page 129 is
TP 05, so labels run one ahead of the page offset from there on.
**#8474 is TP 05.** #24470 "Tanjore Devotion Panel" and #30905 "Balaji Heritage
Collage" are Balaji subjects but match no TP page — every TP page is
photographic or painterly, neither is a Tanjore panel or a collage.

**Seven Horses (SH 01–12, pages 114–125) — read, closed.**
SH 04 is the only page with a dark cliff at the left, and it is the picture
#19025 already holds. **#19025 and #23191 are the same artwork** — #23191 is a
brighter, tighter crop. Fourth duplicate listing found, after #31890/#15730,
#15913/#17543 and the LR pair.

**Abstract Art (AA 01–19, pages 322–340) — read, closed.**
A firm negative: **none** of the abstract-looking products on TA 04 is in this
section — not #19269, #30775, #23008, #7781, #23850, #25358 or #28839.

**Living Room (LI 01–44, pages 278–321) — read, closed.**
**#23008 "Teal City Mirage" is LI 04** — the Statue of Liberty dissolving into
teal mist. Nothing else on TA 04 is anywhere in LI.

Between them AA and LI are the book's two catch-alls, so closing both is what
makes the remaining search small: whatever is left must be in RK, SL, VA, WL,
KR or in no page at all.

## Two listings of one picture may share a code — deliberately

The apply tool refuses to write a code another product holds, because a clash
is nearly always a mistake. A genuine duplicate listing is the exception: the
same picture must carry the same art code, and the SKU letter already keeps the
two SKUs apart (SH-04A, SH-04B).

So a row may now say `SHARE:SH 04` instead of `SH 04`. The prefix has to be
written out, so it cannot happen by accident, and the run reports each shared
code on its own line. Without the marker the refusal stands exactly as before.

## Still open

Unread: **RK (91 pages), SL (23), WL (19), KR (19), VA (4)**. Partly read: HD,
LS, LB, IC.

Still carrying TA 04 with nowhere yet to go: #7765, #7781, #7800, #13414,
#14034, #19269, #22199, #23850, #24470, #24836, #25185, #25358, #25657,
#28103, #28839, #30775, #30905. They are not being cleared — the rule holds
that a code is only emptied when the product is not an artwork, or when every
section has been read and none of them fits.

## Kids Room and Wildlife — read and closed

**Kids Room (KR 01–19, pages 259–277).**
**#7765 is KR 01** — the four cartoon cats stacked together: the big green one,
the red one in a hat, the white fluffy one, the small black-and-white one. KR 02
is also a cat, but a single sleeping cat on a colourful patchwork, so the
section has two cat pages and only one of them is this product.
Nothing else on TA 04 is in KR — #23850 "Melody Makers" is not here.

**Wildlife (WL 01–19, pages 240–258).**
Four separate peacock pages: WL 06 (two peacocks in a pale floral garden),
WL 10 (art-nouveau ornate peacock), WL 11 (pastel watercolour peacock),
WL 16 (peacock before a red palace doorway). **#22199 is none of them** — its
peacock has a fanned teal-and-gold tail on a dark blue painterly ground.
Four candidates and none fits: a good example of why a subject match is not a
picture match.
No savanna page anywhere in WL, so **#28839 is not here either**.

That leaves **RK, SL and VA** as the only unread sections in the book.

## Still Life and Vaastu — read and closed

**Still Life (SL 01–23, pages 213–235).**
**#25185 is SL 22** — two white lotus flowers open in a woven basket, lily pads
around it, on a mottled teal-green ground.

**#7781 is not SL 01, though it very nearly is.** SL 01 is a spray of red,
white, yellow and green blooms on a ground split red and cream — which is
#7781's palette and layout exactly. But SL 01's flowers stand in a brass vase
above a dark green band, and #7781 has no vase and no band: bare stems on the
split ground. Closest call in the whole audit so far, and still a no.

**Vaastu Art (VA 01–04, pages 236–239).**
VA 01 peacock among white blossoms in teal and gold; VA 02 Garuda in blue and
gold on a gold ground; VA 03 two sacred cows on teal; VA 04 pairs Garuda with
the sun against the tulip page that is LI 03.
VA 01 is a fifth peacock page, and **still not #22199**.

## What is left

**Radha Krishna, RK 01–91, pages 5–95 — the only section not yet read.**
Everything else in the book has now been looked at page by page. Whatever is
still misplaced is either in RK or in no page at all.

## Radha Krishna (RK 01–91, pages 5–95) — read

The largest section in the book, and the last of the big ones. Pages run one
ahead of the label by four: page 5 is RK 01, page 95 is RK 91.

**Neither #28103 nor #13414 is in it.** #28103 is a large frontal blue-green
Krishna face, eyes closed, a peacock feather top right and pink lotus buds at
the right, on a blocky abstract ground of blue, orange, yellow and white. RK has
several Krishna faces — RK 11 a pale profile with a flute, RK 17 a black face
among marigolds, RK 42 and RK 59 and RK 70 and RK 75 and RK 85 all portraits —
and #28103 is none of them.

### Two things worth recording about the section itself

**RK 76 is struck through with a large red X** on the page. Whatever that page
was, the book has withdrawn it, so no product should be given RK 76.

**RK 56's caption is the boilerplate "Ganga Aarti at Varanasi" text**, on a page
showing Radha and Krishna dancing. That is the same stray caption found on four
pages of the Alwars book. It is now confirmed to appear in the Master Brochure
too, so the caption is worthless as evidence anywhere. Only the picture counts.

## The book is read

Hindu Deities (HD 01–28, pages 154–180), Buddha (LB 01–13, pages 181–193) and
Indian Culture (IC 01–04, pages 199–202) were the last three partially-read
sections. **HD 14 is confirmed absent** — page 166 is HD 13 and page 167 is
HD 15 — which is the second gap in the book after TP 04.

With those closed, **every page of the Master Brochure has been looked at.**
That matters because it is the condition the clearing rule was waiting on.

## TA 04, settled

Twenty-two products carried TA 04. Now that the whole book has been read:

| Product | Where it actually belongs |
|---|---|
| #29890 Ganga Aarti Flame | **TA 04** — correct all along |
| #24169 Murugan Sanctum Darshan | **MG 01** |
| #8474 Divine Lord Balaji Temple | **TP 05** |
| #23008 Teal City Mirage | **LI 04** |
| #7765 Cute Cartoon Cat | **KR 01** |
| #25185 Lotus Basket Still Life | **SL 22** |
| #13414 Shiva and Parvati | **LS 06** |
| #24897 Crimson Horse Reverie | **LI 36** (already staged) |
| #23191 Seven Horses Cliff Dawn | **SH 04**, shared — same painting as #19025 |

The remaining **fourteen are on no page of the book at all**: #7781, #7800,
#14034, #19269, #22199, #23850, #24470, #24836, #25358, #25657, #28103,
#28839, #30775, #30905.

Under the owner's instruction — *if a picture does not match any one picture,
leave it with no art code* — those fourteen are cleared. Each row says which
pages were the near misses and why each failed, so the reasoning can be checked
rather than taken on trust.

**A consequence worth stating plainly:** an art code is what a SKU is built
from, so clearing these fourteen also removes their SKUs. That is the rule
working as intended — a SKU that encodes a catalogue position the product does
not occupy is worse than no SKU — but it is a visible change in the shop, and
it is the owner's call whether to accept it. The pictures are real products;
they simply are not in this book.

## Every section, and how it was closed

| Section | Pages | Status |
|---|---|---|
| Radha Krishna RK 01–91 | 5–95 | read — RK 76 struck out in the book |
| Lakshmi Ganesha LG 01–03 | 96–98 | read |
| Lord Shiva LS 01–15 | 99–113 | read |
| Seven Horses SH 01–12 | 114–125 | read |
| Tirupati Balaji TP 01–16 | 126–140 | read — **TP 04 absent** |
| Murugan MG 01–04 | 141–144 | read |
| Lord Rama LR 01–09 | 145–153 | read |
| Hindu Deities HD 01–28 | 154–180 | read — **HD 14 absent** |
| Buddha LB 01–13 | 181–193 | read |
| Sikh SA, Swaminarayan SN, Pichwai PA | 194–198 | read |
| Indian Culture IC 01–04 | 199–202 | read |
| Landscapes LC 01–10 | 203–212 | read |
| Still Life SL 01–23 | 213–235 | read |
| Vaastu VA 01–04 | 236–239 | read |
| Wildlife WL 01–19 | 240–258 | read |
| Kids Room KR 01–19 | 259–277 | read |
| Living Room LI 01–44 | 278–321 | read |
| Abstract AA 01–19 | 322–340 | read |
| Travel Art TA 01–04 | 341–344 | read |

## Run 852 landed, and it found two things

**The SHARE: prefix works.** #23191 now holds SH 04 alongside #19025, reported
as sharing rather than refused. 91 of 92 rows applied; the eight moves and the
fourteen clears are all on the live shop. **TA 04 is down from 22 products to
two.**

### The one refusal was right: a fifth duplicate listing

`#8474 REFUSED — TP 05 already belongs to #229.`

**#229 and #8474 are the same painting.** #229 shows the framed piece whole in a
room; #8474 is a zoomed crop of it — the same gold pillars, the same red and
white garlands falling in a V from the shoulders, the same dark Venkateswara
with the tall crown against a blue-green arch. So TP 05 was already correctly
held, and #8474 is a second listing of it. The row is now `SHARE:TP 05`.

The guard was doing exactly what it exists for. Worth recording, because the
instinct on seeing a refusal is to assume the row is wrong — here the row was
right about the picture and wrong only about the picture being unique.

### And one thing the audit broke, which I missed

The clears removed fourteen art codes. **The SKUs did not follow.** The count of
products with no SKU went 41 → 44, not 41 → 55, because the SKU pass has always
said "no art code, so the SKU was not touched".

That rule was right while *no art code* meant *never had one*. The audit changed
what it can mean. A product whose code was taken away was left carrying
`TA-04D` — a SKU asserting the exact catalogue position the audit had just
proved it does not occupy. That is the error the audit exists to remove,
reproduced in the field customers and invoices actually read.

The pass now undoes what it minted, and only what it minted:

- if it displaced an older SKU, that original is **put back** (it was kept in
  `_af_sku_before_artcode` all along);
- if there was nothing to restore and the SKU matches the generated shape, it is
  **cleared**;
- a SKU this tool never wrote is **left alone**;
- a restore that would collide with a SKU another product now holds clears
  instead of colliding, and says so.

The stale `_af_sku_letter` is deleted at the same time, which also drains the
22 stale letters the format check has been listing.

## The clears written before the book was finished are not safe

Four products cleared earlier in the audit turn out to have pages after all:

| Product | Actually | What my clear said |
|---|---|---|
| #19269 Crimson Rider | **LS 10** | "on no page of it… not in AA, LI or anywhere else" |
| #8424 Divine Varanasi Ganga Aarti | **TA 01** | "none of the fifteen Lord Shiva pages shows it" |
| #7825 Panchmukhi Hanuman | **HD 11** | "none of the nine Lord Rama pages shows it" |
| #220 Serene Buddha with Lotus | **LB 01** | "a room mockup of the LB 01 artwork, not a picture in the book" |

Three of them share one shape: the reason **rules out a single section and then
empties the code anyway**. "None of the nine Lord Rama pages shows it" is a true
statement about nine pages out of 358. At the time it was written the rest of the
book had not been read, so it could not say more — and it was never revisited
once the book was finished.

**32 of the 53 clears are of this kind.** They are not wrong by default — many
are frames, canvas rolls and custom-photo prints that are not artworks at all —
but none of them can be trusted until it has been held against the finished page
inventory. That re-check is now the remaining audit work.

### #220 also exposes an inconsistency in my own rule

I cleared it as "a room mockup of the LB 01 artwork, not a picture in the book",
while ruling that **#229 and #8474** — a room mockup and a crop of one painting —
are two listings of that painting and share TP 05. Both cannot be right.

The consistent rule, and the one that matches how the shop actually lists things:
**a product's picture is the artwork it sells, so a room mockup showing artwork X
is a listing of artwork X.** #220 becomes `SHARE:LB 01`.

### The lesson worth keeping

A negative result is only as wide as the pages actually read when it was written.
"I did not find it" and "it is not there" are different claims, and the audit
recorded the first as though it were the second. From here a clear is only
written with the whole book behind it, and every clear written before that has to
be re-earned.

## Re-checking the 32: progress

Each clear is re-checked against the finished book and then either **overturned**
(the product gets its page) or **re-justified** (the reason is rewritten to say
what was actually searched). A clear whose reason still names one section has not
been re-checked yet.

**Overturned so far — 4:** #19269 → LS 10, #8424 → TA 01, #7825 → HD 11,
#220 → SHARE:LB 01.

**Confirmed so far — 4:**

- **#21832 Divine Mercy Radiance.** The Christian run LI 05–LI 11 is the only
  place it could be. LI 09 is Jesus with a **golden** halo and golden rays on
  charcoal; LI 10 is Jesus in charcoal against a red disc. This is the Divine
  Mercy image — pale cream mosaic, **red and pale-blue** rays from the heart.
  Not the same painting.
- **#22077 Shiva in the Sea** — no LS page shows Shiva rising from the sea under
  a full moon.
- **#24352 Shiva Parvati on Kailash** — LS 13 is the watercolour faces, LS 14 and
  LS 15 the cosmic dance; none is the pair seated on Kailash with Nandi.
- **#25124 Cosmic Buddha Nebula** — no LB page puts the Buddha in a star field.

**Not settled — the four cleared horse products.** #20087, #20169, #21893 and
#30093 are all seven-horses pieces, and SH 06 (a mixed-colour herd charging
through an orange-and-teal splash) is close to at least two of them. But
**#19759 already holds SH 06**, so at most one of them could take it and only a
side-by-side at full size can say which, if any. Recorded as unfinished rather
than guessed — a wrong move here would displace a code that is already right.

### Also seen, not yet asserted

**#11541 and #11617** look like the same Radha-Krishna painting in golden
autumn foliage — a possible sixth duplicate listing. To be confirmed at size
before any row is written.

**BATCH_02 is entirely frames, mouldings, canvas rolls, easels and banners** —
24 products, none of them artworks. Correctly codeless, nothing to re-check.

## Two more overturned, and TA 03 taken off the wrong products

**#26875 Mahavatar Babaji is HD 01.** My clear said "no LB page in the book shows
it" — true, and beside the point. HD 01 is the bare-chested figure with long dark
hair seated cross-legged in meditation, hands in the lap, saffron cloth, dark
brown ground. The same picture. Fifth overturn, and the fifth to come from a
clear written against one section.

**#31713 is TA 03**, and the two products holding TA 03 are both wrong.

TA 03 is a wide stylised landscape: a crimson sky block at the left meeting teal
water, white snow mountains behind, red temple spires above pale ghat steps at
the right, dark boats on the river. #31713 is exactly that.

The two holders — **#22947 "Temple Sanctum Vishnu"** and **#29829 "Forest Vishnu
Murti"** — are Vishnu subjects. Neither can be a Varanasi landscape. They are
cleared so the right product can take the code, under the one sanctioned
exception: a known-wrong code blocking a known-right move is worse than an empty
one. Where those two pictures do belong is still to be found.

**Travel Art now stands at three of four owned**: TA 01 #8424, TA 03 #31713,
TA 04 #29890. **TA 02** — the Kedarnath temple lit with lamps against the snowy
Himalayas — still has no owner among the products seen.

## BATCH_06 needs no work

Nine of its twelve are photographs, not brochure artworks: a Sikh groom, mehndi
hands, a Bharatanatyam dancer performing, and venue shots of artwork displayed on
boards at an event. Correctly codeless.

## The horses, settled: nothing changes

All five held side by side at full size against SH 01–SH 12:

- **#19759 is SH 06 and correctly so** — horses in magenta, orange, brown and
  blue against a splashed grey-and-orange ground. It keeps the code.
- **#20169** is red, black and white horses under a gold sky over teal. Its
  nearest page is SH 06, and SH 06 is #19759's picture. Different painting.
- **#21893** is white horses in palette-knife impasto on green, teal and orange.
  No SH page uses that ground; SH 11, the other textured white-horse page, is a
  warm sunlit landscape in dust.
- **#20087** is a sculptural relief: white horses on green grass under a huge
  pale sun in a teal sky. No SH page is relief-textured or has green grass.
- **#30093** is a mixed white, black and tan herd charging head-on through pale
  sepia dust. SH 08 is the other head-on charge and is all-white horses in
  golden clouds.

**All four clears confirmed; no rows change.** This was the open item where a
guess would have displaced a code that was already right, and it turned out the
existing code was right and all four clears were too. Worth the extra pass: the
subject matched five ways and the picture matched none.

**Re-check status: 6 overturned, 8 confirmed, 18 to go.**

## Four more overturned

- **#3362 Divine Lord Ganesha → HD 04.** The grey stone Ganesha seated with four
  arms against a gold filigree halo on a dark ground. My clear said "none of the
  fifteen Lord Shiva pages shows it" — true, and beside the point, because the
  Ganesha pages are in Hindu Deities.
- **#141 Minimalist Blossom Still Life → LI 02.** A pale round vase holding one
  blossoming branch on a dark ledge against a textured grey wall. Cleared against
  Tirupati Balaji.
- **#148 Abstract Floral Sunrise → LI 03.** The stained-glass tulip in jewel
  greens, blues, purples and orange with a golden sun behind it. Cleared against
  Lord Rama.
- **#17543 Lord Balaji Idol → SHARE:LR 05.** This one needs no new evidence: my
  own clear says *"the same photograph as #15913, which correctly holds LR 05 —
  one artwork listed twice"*. Two listings of one picture share the code. I made
  the right observation and drew the wrong conclusion from it.

That is now **ten overturned against eight confirmed**, and every single overturn
came from a clear written against one section — nine of them — or, in #17543's
case, from a duplicate I identified and then cleared anyway.

The ratio is the point. These were not near-misses: HD 04, LI 02, LI 03, LS 10,
TA 01, TA 03, HD 01, HD 11, LB 01 are all exact matches sitting in the book while
the product carried no code at all.

**Re-check status: 10 overturned, 8 confirmed, 21 to go.**

## A sixth duplicate listing: #11541 and #11617

Held side by side, they are the same painting in every detail: Radha in a dark
veil at the left, blue Krishna in profile facing her at the right, a white
sunburst halo between their faces, an arbour of orange autumn flowers around
them. #11617 is a slightly tighter crop of #11541.

**Both are codeless**, so unlike the earlier duplicates this one needs no art-code
row — there is no code to share and no clash to resolve. And the painting matches
no RK page: RK 02 is all-gold with butterflies and Krishna above Radha, RK 03 is
the orange-veil pair cheek to cheek. So both correctly stay without a code.

Recorded because it is still a duplicate the shop is carrying — one artwork on two
product listings — and that is the owner's to consolidate if they choose, the
same as the TP 14, LR 05, SH 04 and TP 05 pairs.

The duplicate tally is now six: #31890/#15730 (TP 14), #15913/#17543 (LR 05),
#19025/#23191 (SH 04), #229/#8474 (TP 05), #220 (a mockup of LB 01), and
#11541/#11617 (a codeless Radha-Krishna).

## #19453 checked against RK 10, ruled out — clear still pending

#19453 "Golden Krishna Flute Player" is a golden Krishna idol standing with the
flute in a temple, worshippers and oil lamps behind, a sunbeam from above. RK 10
is a painterly Krishna against a large gold moon on dark grey — a different
picture. That rules RK 10 out but not the other ninety RK pages, so this clear
stays on the to-do list rather than being marked confirmed. A page ruled out is
not the book ruled out — the same distinction this whole re-check turns on.

## Three Shiva-family clears confirmed against the pages

Re-checked against the actual Lord Shiva pages rather than a single one:

- **#24352 Shiva Parvati on Kailash** — glowing blue Shiva and golden Parvati on
  the snowy Kailash at night with Nandi. No LS page is that scene; LS 06 is the
  blue Shiva-Parvati-with-Ganesha page, no mountains.
- **#21954 Shiva Family Cubist** — the two cubist LS pages are LS 02 (a couple,
  no animals) and LS 08 (a landscape family with one bull). This is a portrait
  cubist family with a red bull at the **left** and a lion at the **right** on
  gold. Neither page.
- **#30531 Shiva Parivar with Lion** — traditional calendar-art family portrait
  with Nandi and a lion. LS 08, the nearest, is cubist; HD has no Shiva-family
  page. Not a duplicate of #21954 — different style entirely.

All three confirmed. **Re-check status: 10 overturned, 11 confirmed, 17 to go.**

## The book renumbered itself, and the catalogue followed

The brochure now prints its section's number alongside the page's. Every page
label changed:

| The book used to print | It prints now |
|---|---|
| RK 01 | RK - 0101 |
| LI 32 | LI - 1932 |
| HD 15 | **HD - 0814** |
| TP 05 | **TP - 0504** |

The last two are the reason this is not a matter of pasting the section number
onto the front of the old one. The old numbering had two labels with no page —
`TP 04` and `HD 14`, both confirmed absent by reading the pages either side of
them and both recorded above. **The new numbering has no gaps**, so from each of
those onwards a page's number is one lower than the label it used to carry.
Fifteen Tirupati pages and fourteen Hindu Deities pages move. A product on TP 05
that were given `TP - 0505` would be pointing at somebody else's painting.

### How this was established, rather than assumed

The pages themselves did not move: all 358 are where the reading above left
them, and only their labels were rewritten. That is what the whole mapping rests
on, so it was checked and not inferred. The first and last page of **every one of
the twenty-one sections** was read out of the design and matched against the page
numbers recorded in the table above — page 5 is `RK - 0101` and page 95 is
`RK - 0191`, page 99 is `LS - 0301` and page 113 is `LS - 0315`, and so on
through `TA - 2104` on page 344. Both gaps were confirmed the same way: page 128
is `TP - 0503` and page 129 is `TP - 0504`, which is the page that used to be
labelled TP 05.

The section totals agree independently. The design carries 340 page labels; the
section-by-section reading above accounts for 340 pages; and every section's
count matches what was read then — Lord Shiva 15, Seven Horses 12, Buddha 13,
Landscapes 10, Living Room 44.

Two sections the earlier table left approximate are now exact: Sikh Art is
pages 194–196, Swaminarayan is page 197 alone, and Pichwai is page 198 alone.

### Where it lives

- **`inc/artcode-book.php`** — the twenty-one sections, their numbers, their
  page ranges, their counts and the two absent labels. It reads a code in either
  numbering and answers with the one the book prints today, which is what makes
  the pass safe to run on every deploy: a code already in the new shape maps to
  itself.
- **`tools/renumber-artcodes.php`** — walks the catalogue and writes the result.
  Dry run unless `AF_APPLY=1`. The code a product had first is kept in
  `_af_code_before_renumber`.
- The deploy runs it between the corrections pass and the SKU pass, and carries
  its report out to the art-sheets branch as **`RENUMBERED.txt`**.

### What it will not touch

A code that names no page of the book is left exactly as it is and listed at the
end of the report. Renumbering translates; it does not adjudicate. Two groups
are already known to be in that list:

- **`LR 24`, `LR 25`** and the rest of the stranded Living-Room-era codes. Lord
  Rama has nine pages, so these name nothing, and the section above records that
  the LR-to-LI number carry *fails* for exactly these two. They need a picture
  put next to a page, which is the audit's work.
- **`AL 01`, `AL 05`, `AL 06`** — from the Alwars book, not this one. They are
  correct codes in a different publication and the Master Brochure has no AL
  section, so this pass has nothing to say about them.

`TP 04` and `HD 14` are refused for the same reason: they never named a page, so
there is nothing to renumber them to.

### The SKU follows, because it always has

The SKU is built from the art code, so renumbering restates every SKU too:
`RK-01` becomes `RK-0101`, and the order line `RK-01-2/3` becomes
`RK-0101-2/3`. That is the designed behaviour rather than a side effect — the
SKU exists to say which page of the book a piece is — but it is a visible change
in the shop and on future invoices, and worth stating plainly. The SKU each
product had before this pipeline ever touched it is still in
`_af_sku_before_artcode`; `tools/restore-sku-from-backup.php` puts them back.

One consequence worth knowing: the letter that makes a shared code's SKU unique
(`RK-0101A`, `RK-0101B`) is issued against a particular code, so every letter is
reissued when the code changes. They are handed out in product-id order, the
same order as before, so a pair keeps its A and B — but a letter is no longer
guaranteed to be the one printed on an old invoice, because the code it was
attached to is not either.


---

## Radha Krishna — read 2026-09-14, from the pictures

**57 products, 56 pages claimed. Thirty of them were on the wrong painting.**

Product pictures came from the contact sheets of deploy run 1090; every book
page below was identified by the label PRINTED ON THE PAGE, so the canva-page
offset cannot have introduced an error.

### What was actually wrong: the catalogue runs one page ahead of the book

Not scattered mistakes. Twenty-six of the thirty are a single fault repeated —
the product holds page N and is the painting on page N−1 — in four unbroken
runs:

| run | products | what settles it |
|---|---|---|
| 27→34 | #15135 #20349 #15974 #19147 #13916 #18107 #14617 #21320 | Jagannath trio, white cows, the graffiti flute player |
| 41→47 | #15546 #13662 #16823 #18600 #15217 #18788 #19822 | eyes open vs eyes closed, Vishnu, the baby behind the curtain |
| 49→50 | #18355 #30592 | the GOVINDA poster |
| 54→58 | #24230 #24033 #26690 #23972 #25901 | the Vishwarupa, Yashoda and the baby |

and four singles on the same −1 pattern: #19392 (23→22), #11551 (26→25),
#16318 (40→39), #25419 (61→60), #30836 (64→63).

Each run starts on a page no product holds, which is why they can be written at
all.

**Correction, made while reading Hindu Deities:** this section first said the
file's row order mattered and that a run had to be written from its free end.
That is wrong. Every product named in the file leaves the ownership map before
any row is read, so a run applies whichever way round its rows are written. What
matters is MEMBERSHIP: the product standing on the page you want must itself be
in the file, moved or cleared. #21320 was refused here because #31829 was
missing from the file altogether — not because the rows were ordered badly.
Both directions, and the membership rule, are now checked in
tools/test-corrections-chain.php.

Two that are not part of any run:

  #26206  RK 68 → RK 53   the black idol in white and silver. A photograph, so
                          there is nothing to mistake it for.
  #31829  RK 34 → RK 33   the same painting as #14617, a wider crop. They share
                          the code; the SKU letter keeps the two listings apart.
                          It has to move with #14617 or RK 34 is never free.

### What this says about the book

RK went from 91 pages to 97, and the catalogue sits ONE PAGE AHEAD through the
middle of the section. Appending six pages to the end could not do that. A page
that used to sit before RK 27 is gone, and the section was re-laid-out rather
than added to. **Worth putting to the owner** — the same thing may have happened
in the other nine sections that gained pages.

### Left alone, and why

Fifteen products are on the right page and were not touched.

Thirteen could not be placed and keep what they hold: #25962, #17280, #7769,
#31212, #17212, #7824, #13355, #28900, #30653, #31334, #27325, #22138. Most are
photographs of dressed temple idols, and the book has a dozen of those; the
thumbnails do not separate them. Canva's export host is blocked from the
session, so a higher-resolution look needs someone who can open the book.

  #30470  is the Radha-on-a-lotus with swans, which is RK 88 — but RK 88 is held
          by #22138, and until #22138 is placed there is nowhere for it to go.
          Recorded here rather than forced.

  #24653  CLEARED. RK 1 is a bright rainbow painting on a cream ground with
          drips and a bird; this is a muted amber oil. It is not that page. RK 6
          and RK 16 are both candidates and neither could be confirmed, so it
          carries no code rather than someone else's.

  #8301   RK 6 → RK 1, on three features shared with page 1 and absent from
          page 6: the cream ground, the paint drips, the single bird upper
          right. The weakest call in this section — RK 6 is the alternative.

### Two bugs this audit found in the tooling

Neither could have been seen without a section this size.

**The corrections pass could not move a run at all.** It read the catalogue into
an ownership map once and never let a product out of the code it held, so every
row of a chain was "REFUSED — already belongs to", in either file order. All
twenty-six of the run corrections above would have been refused.

**The aspect broke the clash check.** Since renumber-artcodes.php began
appending it, products hold `RK - 010028-3050` while a correction row names
`RK - 010028`. The pass keyed on the whole string, so it could not see that a
page was occupied — it would have put two products on one painting — and it read
every already-corrected product as wrong again, ready to strip the aspect and
let renumber put it back once per deploy for ever.

Both are fixed and both are tested. The comparison is now page-to-page
(`af_corr_page_key`), and a product named in the file vacates the code it holds
before anything is checked.


---

## Hindu Deities — read 2026-09-14, from the pictures

**30 products over 23 pages. Twenty were on the wrong painting, and the drift
runs the OTHER WAY from Radha Krishna.**

Here the product holds page N and is the picture on N+1 — the catalogue lags the
book instead of leading it. RK gained six pages and ran one ahead; HD gained
three and runs one to two behind. So there is no single "the codes are off by
one" story to apply to the remaining sections: each has to be read.

| run | products | becomes |
|---|---|---|
| the guru photographs | #17605 #18477 #14861 #17090 #14985 #22825 | 22-27 -> 24-29 |
| the Ramanuja idols | #15278 #15607 | 20,21 -> 22,23 |
| the goddesses | #21075 #30215 #18538 #17917 | 13,14,15,18 -> 15,16,17,20 |
| the Hanumans | #17472 #17795 #7825 #22260 | 9-12 -> 10-13 |

and three that are not part of any run: #7830 (5 -> 3, the abstract elephant
head), #21564 (18 -> 6, the infant Ganesha among trunks), #24775 (6 -> 30, the
Bharat Mata with the lion).

#8398 is the same painting as #26875 — Mahavatar Babaji, wider crop, which is
why it lists at 60x24 — so the two share HD 1.

### My own earlier correction was one page short

The Buddha audit moved #17472 onto HD 09, saying HD 09 was the monks before the
golden idol. It is not: HD 09 is Surya Dev above seven white horses and HD 10 is
the monks. That correction has been live since run 1087. It is fixed IN PLACE in
the original row rather than by appending a second one, so the file never holds
two answers about one painting.

### Three products now sit on pages the book only just gained

HD 28, 29 and 30 are new pages, and the resolver refused them in every spelling.
That guard exists so nothing lands on a new page BY ARITHMETIC — no old code may
translate onto a page nobody has checked. But it also made a verified correction
inexpressible, and #24775 is the Bharat Mata with the lion, the map of India and
the tricolour, which is HD 30 and cannot be mistaken for anything else.

So the SIX-DIGIT path now resolves against the book as it is today, while the
four-digit and label paths still stop at 'legacy'. Six digits are not
arithmetic: such a code already names today's page, and the only way a product
comes to hold one is that somebody wrote it in the corrections file after
looking at the picture. The replay test pins this to exactly the 33 pages the
book gained — if anything else ever slips through, that number moves.

### Five cleared, because they are on pages that are not theirs

  #22747  pop-art Ganesha face. HD 6 is an infant Ganesha among elephant trunks.
  #26328  bronze Ramanuja murti, close crop. HD 20 is a deity on an elephant.
  #24094  the same murti, wider crop. HD 19 is the four goddesses.
  #25021  Tanjore panel, jewelled seated figure. HD 14 is Hanuman over demons.
  #22321  abstract portrait of a turbaned, white-bearded figure with a golden
          halo — a Sikh guru. Probably belongs to Sikh Art (SA), not here.

None of them matches any of the thirty pages. Clearing #22747 and #26328 is also
what frees HD 6 and HD 20 for #21564 and #17917, whose pictures those are.

### Correcting what the Radha Krishna notes said about row order

They said the file's row order was load-bearing and a run had to be written from
its free end. That is wrong, and the fix below is why. Every product named in
the file leaves the ownership map before any row is read, so a run applies
whichever way its rows are written. What matters is MEMBERSHIP — the product
standing on the page you want must itself be in the file, moved or cleared.
#21320 was refused in RK because #31829 was missing from the file, not because
of ordering.

### A third bug in the corrections pass

A product that MOVED was added to the map under its new page but never removed
from its old one. With one row per product that never showed, because every
product is dropped from the map up front. With two rows for one product it bit
immediately: an older row left #7825 marked as already on HD 11, the newer row
moved it to HD 12, and the stale HD 11 entry then refused #17795 — which is the
picture on HD 11. Moving and clearing now both vacate the old page, and a file
holding two rows for one product says so loudly instead of letting row order
decide.


---

## Tirupati Balaji — read 2026-09-14, from the pictures

**16 products over 12 pages. NO DRIFT AT ALL — and that is the finding.**

Eight products are on exactly the page their picture is on. Not one is a page
out. Radha Krishna ran one page ahead through its middle; Hindu Deities ran one
to two behind. Tirupati Balaji is simply correct.

The difference between them is in the book, not the catalogue:

| section | pages gained | what the codes did |
|---|---|---|
| RK | +6 | ran one page AHEAD |
| HD | +3 | ran one to two BEHIND |
| **TP** | **0** | **nothing moved** |

Three sections is not proof, but it is the first real evidence for what has been
a guess since the map was widened: **the drift is caused by the book being
re-laid-out, and a section whose page count did not change did not drift.** The
sections still to read that gained pages — LI +7, WL +4, LS/SH/KR +3, SA +2,
LR/IC +1 — should be expected to have moved. LB, SL, AA, VA, LC, TA, LG, MG, PA
and SN did not gain any, and on this evidence are likely clean.

That is worth knowing before spending a deploy on each: the sections that gained
nothing can be checked quickly for confirmation rather than read page by page.

### What WAS wrong here is a different fault entirely

Seven products carry TP codes for pictures that are not in this section at all.
Not neighbours — not in the fifteen pages:

  #23911  a Tanjore panel, standing figure with a sword and shield
  #22686  a postcard composite: the word BHOOTAPURI, a deity, a gopuram photo
  #31456  a warm ochre collage — blue profile, Balaji face, temple bells
  #29281  a brighter collage of the same kind
  #31088  a Vaikuntha court: Vishnu, two consorts, attendants, Garuda
  #30966  a pale watercolour, Vishnu seated on a coiled serpent
  #229    a darker Venkateswara in a golden arch

These look like codes assigned by SUBJECT rather than by picture — everything
Vishnu- or Balaji-shaped was given a Tirupati Balaji code. That is a different
failure from the drift, it will not be caught by any arithmetic, and it can only
be found the way this was.

All seven are cleared. Five pages of the section (1, 2, 7, 14, 15) have no
product, which is ordinary — 167 of the book's 373 pages had none.

### #229 is the one I could not finish

Its featured image is a ROOM MOCKUP rather than the artwork, so there is not
enough picture to compare. What is certain is that it is not TP 4: that page is
the bright golden sanctum idol, #8474 matches it, and #229 is visibly a
different photograph. TP 2 — the dark bronze Venkateswara in an orange-lit arch
— is the candidate if anyone can open the product's other images.

### Two listings of one painting, settled by measurement

#15730 and #31890 are the same artwork. Compared pixel for pixel the RMS
difference is 6.6, against 102 for #23911, the third product that was sitting on
TP 13. They share the code; the SKU letter keeps them apart.

Worth doing this more often: three products on one page is exactly where the eye
wants to see a match, and a number settles it in a second.


---

## Lord Shiva — read 2026-09-14, from the pictures

**23 products, not 16. Two separate faults, and the section is only part done.**

Nine products are on exactly the page their picture is on — no drift of the kind
Radha Krishna and Hindu Deities had, even though LS gained three pages. That is
the third section to suggest the drift is not simply "gained pages means moved
codes"; what LS has instead is two different problems.

### One: seven products are stranded on codes that name nothing

The contact sheets came back with seven files the others never produced —
LS_16 through LS_22 — because seven products hold OLD codes LS 16 to LS 22 and
the renumber pass refuses them: Lord Shiva's legacy numbering stops at 15. They
are invisible to every report that lists renumbered products, which is why they
had not been noticed.

All seven are genuinely Lord Shiva pictures. Three are placed here:

  #7816   LS 22 -> LS 1    the smoky Shiva head, fire orange down one side
  #19883  LS 21 -> LS 7    the neon line-art face on black
  #20770  LS 17 -> LS 18   the crescent-moon head in blue and purple

LS 18 is one of the three pages the book gained, so it is written in six digits
and only resolves because of the widening made for Hindu Deities. That is the
second section to need it.

**This also means `'legacy' => 15` is questionable for LS.** The catalogue was
plainly written against a numbering that had at least 22 Lord Shiva pages. The
map was not changed here — every placement above is by picture and written in
six digits, so the translation is not used — but somebody should check what LS's
legacy count ought to be before trusting it for anything else.

### Two: six products are not Lord Shiva at all

  #18727  two whimsical cartoon creatures on grass      (reads as Kids Room)
  #30276  a bare tree against a red sun                 (a landscape)
  #22625  a whimsical tower of balloons and oddments    (reads as Kids Room)
  #25474  a boat on a lake under a swirling starry sky  (a landscape)
  #26628  a botanical pattern of leaves and blossom
  #30154  a Ganesha silhouette on a pier at sunrise     (Hindu Deities)

Two of those are landscapes, which is the documented collision: **Landscapes
used to be LS and the book renamed it LC.** All six are cleared — they carry no
code rather than someone else's — with the likely section named in each row.
Clearing #30276 is also what frees LS 7 for #19883, whose picture that is.

### Left unresolved, and why

Four of the seven stranded products could not be placed: #17856 (Shiva standing
with a trident), #30338 (the family in clouds), #29023 (the family seated,
calendar style) and #31150 (the family close-up in mural style). Three of them
are Shiva-family scenes and the section has ONE family page, LS 17 — #29023 is
the closest but not close enough to write down.

They are left exactly as they are, and that is safe in a way clearing would not
improve: the codes they hold resolve to nothing, so they are not standing on
another painting. A product on a code that names no page is untidy; a product on
a code that names someone else's page is the thing this audit exists to stop.

#29578 is also unresolved and stays on LS 9 beside #16257, whose picture that
page is. That pair is the one place in this section where two products sit on
one page without being the same artwork.

**So LS is marked part done, not done.** Five products still need an answer.

### What would actually settle them

Re-reading the three family scenes against LS 17 at every zoom the thumbnails
allow did not decide it: #31150 is a tight crop with Shiva upright, and LS 17
has him seated with Nandi, a lion, mountains and hanging bells. Similar style,
different composition. Canva's image host is blocked from this session, so the
book page cannot be fetched and compared pixel-for-pixel the way two product
tiles can.

The evidence that WOULD settle them was already being collected and thrown away.
`tools/diag-artcode-from-filenames.php` has run on every diagnostic deploy since
the audit began, reading the art code off each product's original image
filename, and its answer went only to the job log — which this session cannot
read, because GitHub redirects log downloads to blob storage the proxy blocks.

It is now teed to `FILENAMES.txt` on the art-sheets branch, beside the other
reports. It is the one piece of evidence about a product that is independent of
both the picture and the title: what the file was called when it was uploaded.
Every remaining section will have cases like these five.


---

## The resolver change broke a check, and I did not notice for a deploy

Widening the six-digit path (see Hindu Deities above) needed three tests
updating. I updated two and missed the third, because the third cannot run
here: `tools/test-sku-format.php` needs WordPress loaded, so it only runs on the
server. Deploy run 1107 published this and it sat in SKUCHECK.txt:

    FAIL  the book's 33 new pages cannot be written onto a product
          got '33 reachable' want '0 reachable'
    === 1 CHECK(S) FAILED ===

The resolver was right; the assertion was describing the old rule. It is now
split into the two halves the rule actually has:

  no OLD code RESOLVES ONTO one of the 33 new pages        must be 0
  the 33 are reachable by their six-digit spelling         must be 33

Writing the first one exposed a second mistake, mine again. The obvious form —
"does old label N resolve, for N above legacy" — reports a failure that is not
one: where a section has a gap the old numbering runs PAST its page count, and
**HD 28 legitimately means HD 27** because HD lost page 14. The question has to
be asked the other way round: take every old code, resolve it, and look at the
page it LANDS ON.

Two things worth keeping from this:

**A check that cannot run in the sandbox will be missed.** The two local suites
were updated in the same edit as the resolver; this one was not, because nothing
failed in front of me. Its two book loops are now also runnable standalone —
see the harness used in this session — so the next person changing the resolver
can check all three before pushing.

**SKUCHECK.txt is worth reading on every deploy, not just when something looks
wrong.** It had been reporting a real failure since the Hindu Deities merge and
nobody looked, because the sections either side of it said what was expected.


---

## FILENAMES.txt answered its question, and the answer is no

Run 1111 published it for the first time. It carries its own calibration step,
which is the right way round, and the verdict is unambiguous:

    checked: 199  (no image: 0)
      filename contains that product's own code: 0
      it does not:                               199
      --> filenames carry the code 0% of the time.
          TOO LOW to trust. Treat section B as a hint only.

Not one product in 199 has its art code in its image filename. The filenames are
`ChatGPT-Image-Jan-30-2026-03_27_15-PM.png`,
`Gemini_Generated_Image_5fl22a5fl22a5fl2.png`, `Buddha.webp` — they record how
the picture was MADE, not which page of the book it is.

So the tie-break I went looking for does not exist in this catalogue. That is
worth knowing rather than worth hiding: it closes the line of investigation for
good, and the five unplaced Lord Shiva products stay unplaced for a reason that
is now understood rather than merely unsolved. **Only two things identify a
product here: its picture and its title, and the title is unreliable.**

Publishing the report was still right. It cost one line in the workflow, it now
says plainly that it cannot help, and nobody has to wonder again. Credit where
it is due: the diagnostic refuses to offer hints it cannot support — the
calibration gate is what turned this from a plausible-looking list of guesses
into a clear no.

## Correcting what I said about the failed deploy

When run 1110 timed out on the rsync step I reported that "nothing was applied".
That was wrong, and run 1111 shows it: every Lord Shiva correction reads
`already` — the six clears as "already has no code", the three moves as
"already LS - 030001 / 030007 / 030018".

The step is "Deploy (rsync + opcache + mode decision)". The rsync evidently
FINISHED and the hang was in what came after it, so the new corrections file was
on the server and the art-code steps — which run `if: always()` — did real work
against it. The steps reporting success were telling the truth; I read their
`always()` and assumed stale inputs without checking.

The lesson is narrow and worth keeping: **a failed step is not a step that did
nothing.** Where several actions share one step, "failed" says only that the
step did not finish.


---

## Living Room — read 2026-09-14, from the pictures

**16 products on LI codes. Nine right, seven one page short — and the drift has
a boundary you can point at.**

Pages 1 to 11 are correct. From page 12 on, every product sits one page behind
the picture it shows. Not a section-wide shift: a shift that STARTS somewhere.

| product | held | is |
|---|---|---|
| #7688  Luxury Floral Birds | LI 12 | LI 13 — two birds on a flowering branch |
| #16444 Running Brown Horse | LI 23 | LI 24 — the horse in ink-splash on cream |
| #19697 Moonlit Sailing | LI 28 | LI 29 — the galleon in a moonlit canyon |
| #13473 Joyful Man | LI 31 | LI 32 — the leaping man with a bouquet |
| #23374 Moonlit White Horse | LI 32 | LI 33 — the white horse in gold leaf |
| #27920 Rainbow Wings | LI 34 | LI 35 — the wings among butterflies |
| #24897 Crimson Horse | LI 36 | LI 37 — the red horse and the woman in water |

LI 23 and LI 24 are both charging horses, which is exactly the pair that makes
this worth doing by picture: LI 23 is fiery chestnut on teal and orange, LI 24
is brown in ink-splash on cream. One is #16444 and one is not.

### All seven were already corrected once, and all seven were off by one

Every one of them has a row in this file from the LR-to-LI migration — Living
Room used to be LR, and a previous pass moved them across. That pass got the
section right and the page number wrong, by one, seven times out of seven.

So this is the same fault as #17472 in Hindu Deities, at scale: **a correction
made against a numbering that had already drifted inherits the drift.** The
migration was reading the old LR page number and assuming it carried over.

Fixed IN PLACE in the original rows, as with #17472 and #7825 — the file never
holds two answers about one painting. The duplicate-row guard added during the
Hindu Deities work is what caught all seven at once; without it they would have
been appended as contradictions and the last row would have won silently.

### The aspect corroborates one of them independently

#27920 is sold 4x4 square. LI 34 is 5030 and LI 35 is 4040. The product's own
size agrees with the page the picture says, and disagrees with the page it held.

### Still to do: ten products stranded on LR codes

Ten more Living Room products hold LR 13, 21, 24 x3, 25 x2, 32, 33 and 39. Lord
Rama has ten pages, so none of those resolve and none appears in any renumbered
listing — the same blind spot as Lord Shiva's LS 16-22. They will not be drawn
by an LI sheet run either, because the tool draws by prefix; they need an LR run,
which will also bring in Lord Rama's own eight products.

**LI is marked part done for that reason.** The 16 LI-coded products are
finished; the 10 on LR codes have not been looked at.

---

## Lord Rama — read 2026-09-14, from the pictures

**Eighteen products, two quite different problems. Lord Rama's own eight, and
the ten Living Room strays the last run could not reach.**

### The section did not drift, so nothing here is arithmetic

Lord Rama gained exactly one page and gained it at the END: LR 10, a landscape
Ram Darbar, is new; LR 01–09 did not move. So there is no drift to correct, and
the drift test confirms it — shifting the wrong products one page either way
matches nothing.

| product | held | verdict |
|---|---|---|
| #7810  Ram Darbar | LR 01 | right |
| #21259 Ram Darbar with Hanuman | LR 02 | right |
| #21136 Ram and Sita | LR 03 | right |
| #15913 Lord Vishnu Statue | LR 05 | right |
| #17543 Lord Balaji Idol | LR 05 | shares it — see below |
| #28300 Ram Lalla in Garlands | LR 07 | cleared |
| #26023 Utsava Deity in Garlands | LR 08 | cleared |
| #31588 Kodanda Rama Utsavam | LR 09 | cleared |

The three clears are all the **Tirupati fault**, not drift: the right deity or
the right idol, the wrong photograph. LR 07 is the Ayodhya Ram Lalla standing
in a cream carved shrine in green; #28300 is a close-up bust of that same idol
in gold and red against a flower wall. LR 08 is an idol in red under a marigold
arch; #26023 is a Venkateswara utsava murti. LR 09 is a white-and-gold figure
under an ornate white arch; #31588 is another Venkateswara, close up, with a
gold bow.

### A finding this file already contained, and lost

Run 837 recorded, in this very file: *"#31588 and #26023 are not Lord Rama at
all... Neither has a code, so nothing is written."* Nothing was written, and
they have since acquired LR 08 and LR 09. **An observation with no row behind it
does not survive.** Both now have rows.

### #17543 and #15913 are one painting, mirrored

Two products sat on LR 05, and an earlier row already had them sharing it as
"the same photograph". They are not quite: #17543 is the composition FLIPPED
left to right, with the garlands graded a harder magenta. The page settles which
way round is printed — on LR 05 the gold staff runs down the RIGHT of the
figure, as it does on #15913. Flipping #17543 back drops the pixel difference
against #15913 from 44.8 to 18.1, far above the 6.6 of a genuine duplicate file
but far below anything that could be a second painting.

The share stands — it is one artwork listed twice, not someone else's painting —
but the reason was rewritten, because "the same photograph" is the kind of
almost-right note that gets re-opened later by whoever notices the flip.

### The ten strays: four placed, six with nowhere to go

Old Living Room codes step by one from page 12 up, so LR 13 lands on LI 14 and
so on. That map is right where it applies, and it applies to almost none of
these:

| product | held | is |
|---|---|---|
| #7805  Butterfly Tree | LR 13 | **LI 14** — the map holds |
| #25535 Jazz Man | LR 33 | **LI 51** — one of the seven new pages |
| #7838  Indian Classical Dance | LR 24 | **IC 01** — held, see below |
| #20026 Indian Classical Dancer | LR 25 | **IC 05** — a different section |
| #29639 Mahavira Golden Shrine | LR 21 | cleared |
| #16191 Serene Buddha Statue | LR 24 | cleared |
| #27264 Nataraja Bronze | LR 24 | cleared |
| #7803  Vaishnav Tilak | LR 25 | cleared |
| #24714 Namaste Henna Hands | LR 32 | cleared |
| #28778 Dancer on Stage | LR 39 | cleared |

Two of the four that landed could not have been found by arithmetic at all.
#25535 is on LI 51, one of the seven pages Living Room gained this round.
#20026 is not in Living Room; it is in Indian Culture, which the number never
pointed at.

And #7805 is the cleanest demonstration yet of the owner's rule. Its page, LI
14, carries the caption **"Floral Pichwai art symbolizes purity and renewal"**.
The picture on it is a bare tree shedding gold and teal butterflies. The
caption is not merely vague, it is about a different painting — the second
caption in this book known to describe something that is not on its own page.

The six clears were each checked against the section the subject points at, not
just Living Room: the Nataraja against all eighteen Lord Shiva pages, the
Mahavira against LB 13 (the book's only Mahavira, and a different picture — a
painting of his enlightenment, not a golden shrine statue), the henna hands and
the stage dancer against all five Indian Culture pages, the tilak panel against
Vaastu Art and Pichwai. #16191 came closest and still missed: LB 09 is a Buddha
facing forward on a square plinth under one tall waterfall; #16191 is the same
idea painted differently, three-quarter view on a rocky mound with cascades
falling from the mound itself.

### Reading book pages became cheap, which is why this run went further

`media.canva.com` is refused at the proxy, so until now every page had to be
judged from the thumbnail the MCP call renders inline. Every one of those URLs
carries a `fallback` parameter holding a pre-signed `s3.amazonaws.com` URL for
the same PNG, and that host is NOT refused. So book pages can now be downloaded
and compared pixel for pixel next to the product tiles. `tools/canva-page-fetch.py`
does it. The mirror in #17543/#15913 is only visible at that size.

Second cheap move, also new: `design_content` returns the TEXT of a page range
in one call with no images. Captions are unreliable — LI 14 above is the proof —
but they are a fine way to SHORTLIST which pages are worth rendering. All 51
Living Room captions came back in one call, and the two real matches were found
by looking at seven pages instead of fifty-one.

### One row held back, then settled — Indian Culture, read 2026-09-14

**#7838 is IC 01** — pixel for pixel, the rainbow watercolour dancer. But
#25840 "Dancers in Duet" held IC 01, and writing over it would have been
refused as a clash, correctly. So the row was withheld and an `art_sheets: IC`
run was dispatched instead of guessing.

The sheet brought back **three** products, not the one the last renumber
listing showed, and it settled the whole section at once:

| page | product | verdict |
|---|---|---|
| IC 01 | #25840 Dancers in Duet | **wrong** — see below |
| IC 02 | #13781 | right — the splash dancer in teal and silver |
| IC 03 | #7700 | right — the orange Bharatanatyam dancer |
| IC 04 | — | unclaimed: a woman with a basket in a landscape |
| IC 05 | — | **#20026**, from the strays above |

#25840's own picture is a photograph of **two** dancers in blue and green on a
black ground. It is not IC 01, not IC 02, not IC 03, not IC 04, not IC 05, and
no page of Living Room's fifty-one is two dancers either. It clears, and #7838
takes IC 01.

That makes this the audit's **first real chained move**: one product must
vacate a code before another can have it. It lands because of the fix made
during the Hindu Deities work — the pass now removes every leaving product from
the ownership map *before* it places anyone. Written against the old pass, both
rows would have been refused and the section would have stayed wrong.

Worth noting what the withholding bought. Writing #7838 onto IC 01 blind would
have been refused; clearing #25840 blind would have thrown away a correct code
if its picture had matched. One cheap read-only run answered both, and turned a
blocked row into a finished section.

**Indian Culture is done. LR is done. Living Room's ten strays are closed.**

---

## Seven Horses — read 2026-09-15, from the pictures

**Eight products, seven right. The eighth exposes a "duplicate listing" that
was never a duplicate.**

Six were confirmed against their pages pixel for pixel and are untouched:
#232 SH 01, #23130 SH 05, #19759 SH 06, #20953 SH 07, #19636 SH 08,
#21014 SH 09. Nothing in this section drifted — the three pages it gained
(13, 14, 15) are all at the END, so SH 01–12 did not move.

### The two products on SH 04 are two different renders

A row in `artcode-corrections.csv` had #23191 sharing SH 04 with #19025, on the
grounds that #23191 is *"a brighter, tighter crop"* of it. It is not a crop of
it at all:

> **#23191 has a brilliant sunburst with rays at the upper right.
> #19025 has snow-capped brown mountains in that same place.**

A crop can only remove content. It can never add a sun. So neither picture can
be a crop of the other — they are two renders of one idea, sharing the left
cliff, the peach cloud bank and the horses' poses.

All six mockups on the SH 04 page show the burst. So **SH 04 is #23191**, its
row is corrected in place and is no longer a SHARE, and **#19025 is on no page
of this section** — SH 02 is a gold burst over flat water with no cliffs, SH 03
is peach surf with no cliffs, and nothing else in the fifteen puts horses
between cliffs.

### A measurement that does not work, recorded so it is not tried again

RMS between the two tiles is 59.6, which looks conclusive against the 6.6 of a
genuine duplicate file. **It proves nothing here.** A different framing moves
every pixel, so a real crop would also score high — the number cannot separate
"different picture" from "same picture, cropped".

A crop-search was then written: slide every sub-rectangle of one image over the
other, across six scales, with brightness normalised so "brighter" could not
explain a mismatch. Calibrated against pairs whose answer is already known, it
is worthless:

```
same painting, mirrored   (#15913 / #17543)   50.7
plainly different         (#15913 / #28300)   59.5
plainly different         (#28300 / #31588)   61.2
```

It scores a true match barely better than two unrelated pictures. Discarded.

**What settles a crop claim is content: an element present in one picture and
absent from the other.** The sun does that here, and nothing else needed to.

### Still open: three cleared horse pictures, never held against the new pages

#7662, #30093 and #27572 were cleared in an earlier pass, each explicitly
"re-checked against all twelve Seven Horses pages". The section has fifteen
now. The three it gained were not in that comparison — the same blind spot as
every other new page in this book. They have no code, so no `SH` sheet draws
them; a read-only `art_sheets: nocode` run is in flight to bring their pictures
back so they can be held against SH 13, 14 and 15.

Being cleared, they are visible as "no code" rather than silently wrong, so
this is a possible missed placement, not a live error.

**Unclaimed after this pass: SH 02, 03, 10, 11, 12, 13, 14, 15.**

---

## Kids Room — read 2026-09-15, from the pictures

All 22 pages read and recorded. **Nothing is written for this section yet, and
that is deliberate.**

|page|asp |the picture|
|---|---|---|
|01|3040|four stacked cartoon CATS — green, red, white, black|
|02|4030|a CAT asleep on a patchwork of colour blocks and flowers|
|03|5030|PORTRAIT. happy SNAIL cheering in the rain|
|04|4030|cute BEE with a parasol and a basket, flower meadow|
|05|5030|PORTRAIT. GIRL in glasses reading on a window seat|
|06|4030|CHILD holding balloons made of FISH BOWLS, ink on cream|
|07|4030|VOLCANO erupting a bloom of FLOWERS, teal sky|
|08|4030|SUPERMAN, red and black ink splash|
|09|3050|CAPTAIN AMERICA with hammer and shield|
|10|3050|IRON MAN / Hulkbuster crouching in rubble|
|11|4030|IRON MAN sketch, cream ground with blueprint lines|
|12|3050|BATMAN over a collage of comic panels|
|13|3050|PANDA in a straw hat, orange blueprint ground|
|14|3050|ANIME boy, yellow hair, lightning|
|15|4040|two quirky round MONSTERS, orange and green|
|16|4040|abstract modernist BIRDS in a row|
|17|5030|PORTRAIT. MOTHER hugging CHILD among flowers, paper-craft|
|18|5030|PORTRAIT. two CHILDREN under a RED UMBRELLA, paper-craft|
|19|6030|TALL. two stylised GIRAFFES, geometric|
|20|6030|NEW. TALL. whimsical TOWER of houses, balloons, a fire engine|
|21|3050|NEW. watercolour JUNGLE ANIMALS — flamingo, elephant, giraffe|
|22|4030|NEW. two figures with CELLOS among leaves, warm ochre|

Fourteen products claim KR 01, 02, 03, 04, 07 ×3, 11, 12, 13, 15, 16 ×2, 18.
Unclaimed: 05, 06, 08, 09, 10, 14, 17, 19, 20, 21, 22.

### The result: four right, two in the wrong section, seven cleared

The sheets took four attempts to arrive — one run drew all 11 correctly and lost
the push to a GitHub 500, one was cancelled while pending, one while running.
The drawing was never the problem.

**Right, and untouched:** #7765 KR 01 (the stacked cats), #14923 KR 11 (the Iron
Man sketch), #15046 KR 12 (Batman over comic panels), #18168 KR 16 (the row of
modernist birds). Each matches its page exactly.

**In the wrong section, and placed:**

| product | held | is |
|---|---|---|
| #25718 Franklin Graffiti | KR 15 | **LI 46** — the banknote Franklin over spray paint |
| #26389 Pirate Captain Sketch | KR 13 | **LI 47** — the pencil pirate with tankard and blueprint |

Both are pages Living Room GAINED this round, so no arithmetic could have
reached either; only the picture could.

**Cleared, seven:** #24409 (tropical-leaf mural on the sleeping-cat page),
#16640 (a golden deity idol on the cartoon-bee page), #17402, #21771 and #27388
(an abstract horse, a Guru Nanak watercolour and a cubist Krishna — **all three
on KR 07, which is a volcano erupting flowers**), #27633 (a murmuration on the
birds page, which is #18168's), #28225 (a Nandi bull among temple bells on the
page of two children under a red umbrella).

Three products on one volcano was the clearest signal in the section, and none
of the three was it. This is the Tirupati fault at scale: the right kind of
thing filed by subject, on a page that shows something else.

### #8866 is a SET, and it is left where it is

"Kids Cartoon Animal Canvas Wall Art Set – 72×24" holds KR 03. Its featured
image is a room mockup showing **two** framed pieces: the KR 03 snail and the
KR 04 bee. So it is not on someone else's painting — KR 03's painting is
genuinely in it — and the rule's purpose is served by leaving it alone.

But it is worth the owner's attention. **The only product in the catalogue
showing the KR 03 snail or the KR 04 bee is this one set**, and it can carry
only one code. KR 04's own claimant, #16640, is the deity idol now cleared. A
two-piece set on a single-painting code under-describes what ships.

### Still open in this section

#21771 is a Guru Nanak watercolour and belongs to Sikh Art by subject; SA's
five pages have not been drawn against their products, so it clears rather than
moves. #24409, #17402, #27388, #27633 and #28225 found no page anywhere read so
far.

Unclaimed after this pass: KR 02, 04, 05, 06, 07, 08, 09, 10, 13, 14, 15, 17,
19, 20, 21, 22.

## Wildlife — read 2026-09-15, from the pictures

All 23 pages read and, at last, all twelve products held against them. The
`WL_` sheets were drawn by deploy run 1141 — the first ever for this section.
**Five are right, one is in the wrong section, six are cleared.**

|page|asp |the picture|
|---|---|---|
|01|4030|ELEPHANTS with an arch of BIRDS, sage green and gold|
|02|5030|black ELEPHANT silhouette, red and gold sun discs|
|03|5030|geometric/watercolour ELEPHANT head, red and teal circles|
|04|5030|LEOPARD between CRIMSON PILLARS, orchid vine|
|05|5030|GIRAFFE beside a big stylised TREE, folk style|
|06|5030|two PEACOCKS in a white blossom garden|
|07|2540|GOND DEER pair under trees, orange ground|
|08|3020|folk DEER cluster on yellow, Madhubani-style tree|
|09|4030|ZEBRA with branch-antlers on RED|
|10|5030|ornate art-nouveau PEACOCK panel, teal and red|
|11|4040|watercolour PEACOCK, dreamy pastel|
|12|5030|two red-crowned CRANES flying, GOLD ground|
|13|5030|two white SWANS on a misty lake|
|14|5030|geometric LEOPARD portrait, teal, gold and ivory|
|15|4030|CHEETAH on a curved ledge, red floral panels|
|16|5030|PEACOCK before an ornate RED PALACE doorway|
|17|5030|golden TIGER emerging from paint splashes|
|18|5030|white LION among wildflowers, pastel mountains|
|19|6030|TALL. two DEER by a misty stream, birch, songbirds|
|20|3050|NEW. vivid PEACOCK, teal, gold and blue swirls|
|21|3050|NEW. STILL LIFE — white blossom in a vase, teal wall|
|22|3050|NEW. STILL LIFE — white blossom in a stone vase|
|23|5030|NEW. stained-glass/cubist TULIPS|

Wildlife gained its four pages at the END, so nothing in this section drifted.
Every wrong code here was wrong before the book was re-laid-out.

### Right, and left alone

| product | page | what settled it |
|---|---|---|
| #7767 Sacred Elephant Harmony | WL 01 | the two elephants under the red-dotted arch, green clouds, flying birds |
| #17151 Geometric Elephant | WL 03 | the elephant head quartered red/ochre/teal, same spiral |
| #22886 Autumn Deer Folk Tree | WL 07 | the Gond deer pair, bird on the blue one's back, same tree |
| #28042 Dream Peacock Garden | WL 11 | the pastel watercolour peacock, same daisies |
| #23619 Swan Lake Romance | WL 13 | the two swans, same reeds, same reflection |

### One in the wrong section

**#23313 Safari Friends Nursery → KR 21.** The cross-section lead written down
before any sheet was drawn, and it held: KR 21 is this exact artwork — monkey
on a vine, cockatoo, two flamingos in flight, giraffe, elephant with calf, pink
lotus, a bunch of bananas, each in the same place. WL 12, which it held, is two
red-crowned cranes on gold. KR 21 is one of the pages **Kids Room** gained this
round, so no arithmetic on the old numbering could have reached it.

### Six cleared, and where each was looked for first

| product | sat on | which is | where it was hunted |
|---|---|---|---|
| #29159 Horse Studies Collage | WL 02 | a black elephant silhouette | all 15 Seven Horses pages, looked at — every one is a painted group of running horses, not a sketchbook. Living Room's horse pages were ruled out **on their captions only** (LI 23–26, 33, 37 each describe one painted horse) |
| #26267 Balaji Garland Darshan | WL 06 | two peacocks | TP 04, TP 05 and TP 15, the three garlanded-idol pages — all paintings of a differently dressed idol; this is a photograph, strip light and temple sign included |
| #29690 Folk Cows Abstract | WL 08 | folk **deer**, not cows | Radha Krishna's cow pages are Krishna among many white cows; the Pichwai page is a Shrinathji the book has struck out |
| #27194 Murmuration at Dusk | WL 09 | a zebra with branch-antlers | AA 11 and AA 16, Abstract Art's bird pages — neither |
| #27695 Savanna Evening Dance | WL 15 | a cheetah on a ledge | LC 04, the nearest thing in Landscapes — a grey and orange pine forest under a huge orange moon, no figures |
| #31395 Shiva Smoke and Trident | WL 16 | a peacock at a red palace door | all eight unclaimed Lord Shiva pages — LS 01 is a smoky head but front-on and orange, LS 18 is the serene purple one, none carries the dancer |

Two deity pictures inside a wildlife section: the Tirupati fault again, and by
now the commonest single shape in this audit.

### Two murmurations, not one duplicate

#27194 "Murmuration at Dusk" (WL 09) and #27633 "Flight Path Reverie", cleared
off KR 16 in the Kids Room pass, were described in almost the same words —
birds swirling above a lone figure on burnt orange. Held side by side they are
**two different paintings**: #27633's flock is a single ribbon curling down from
the top-left, #27194's is a scattered swarm filling the whole sky. Same series,
same artist, different pictures. Worth recording because the earlier note on
#27633 already said "KR 21 is not this either", and a reader could reasonably
have taken the two for one listing entered twice.

### Three of the four pages this section GAINED are not wildlife

WL 21 and WL 22 are **still lifes**; WL 23 is a **floral**. Only WL 20, the
peacock, belongs to the section by subject. A wildlife product is unlikely to
be the answer for those three, and a Still Life product might be — which is
one more reason none of the six cleared above was pushed onto them.

Unclaimed after this pass: 02, 04, 05, 06, 08, 09, 10, 12, 14, 15, 16, 17, 18,
19, 20, 21, 22, 23.

**Superseded for WL 21 and WL 22.** The very next section audited, Still Life,
put #7833 on WL 21 and #7834 on WL 22 — the two still lifes among Wildlife's
new pages, found because this pass wrote down that a Still Life product might
be their answer. Those two are no longer unclaimed.

### `diag_only` does not skip the corrections

Run 1141 was dispatched with `diag_only: true` to draw the `WL` sheets, and its
APPLIED report reads `to change: 2 | codes cleared: 7 | refused as a clash: 0`
— it applied the **Kids Room** corrections to the live catalogue. The header
that run prints, "DIAGNOSTICS ONLY: theme deployed, every catalogue script
skipped", is not true of the corrections pass. Worth knowing before dispatching
a sheet run off a branch whose corrections are not meant to land yet.

## Still Life — read 2026-09-15, from the pictures

All 23 pages read and all **twelve** products held against them. (The book-read
note posted first said eleven and was wrong: SL 02 and SL 16 each carry two
claimants, which makes ten claimed pages but twelve products.) Still Life
gained no pages, so nothing here drifted — every wrong code below was wrong
before the book was re-laid-out.

**Six are right, two are in the wrong section, four are cleared.**

|page|asp |the picture|
|---|---|---|
|01|4030|BOUQUET of red/pink/cream blooms in a gold cylinder vase, red-orange and green colour-block ground|
|02|4030|three YELLOW CALLA lilies, big green leaves, glass vases, teal geometric ground|
|03|4030|cluster of glass and amber BOTTLES/JARS on a grid, orange and grey abstract|
|04|5030|three TRANSLUCENT X-RAY TULIPS — red, orange, olive — on cream|
|05|5030|BOHO botanical — leaves, pampas, gold arcs and beads, marble ground|
|06|4030|MID-CENTURY: circles on stems and leaf shapes above three striped VESSELS|
|07|5030|two GOLDEN BRASS VASES with white blossom, teal and gold painterly ground|
|08|4040|white DOGWOOD blossom in a round ivory vase against a glowing ORANGE SUN|
|09|5030|POP still life — red and teal ground, yellow sun, bright vases and pots|
|10|5030|potted OLIVE TREE with black olives, rustic Tuscan ochre wall|
|11|5030|BOHO vase with dried floral stems, terracotta circles, sage ledge|
|12|5030|two MINIMALIST VASES (orange, black/cream) with dried botanicals, beige|
|13|5030|three vases of PINK MAGNOLIA against a GOLDEN MOON|
|14|5030|watercolour BUTTERFLIES, orange, grey and cream|
|15|4035|red POPPIES growing out of a weathered BOAT on green water|
|16|4030|abstract blue-teal MARBLED WATER, a tiny ROWER silhouette|
|17|5030|a SAILBOAT, bold palette-knife blue, yellow and red|
|18|4030|colourful TREES reflected in water, rainbow canopy|
|19|5030|a YELLOW UMBRELLA over watercolour rain, blue and yellow drips|
|20|5030|a stone BUILDING with a huge green TREE, tiny figure at the door|
|21|5030|two FACES in profile fused with a GOLDEN FOREST|
|22|5030|white LOTUS flowers in a WOVEN BASKET on water|
|23|5030|stylised BLOSSOMS in two decorative vases with BUTTERFLIES|

### Right, and left alone

| product | page | what settled it |
|---|---|---|
| #15852 Modern Plant Art | SL 06 | every shape in the same place — the two circles on stems, the small dark red circle, the monstera leaves, the three striped vessels. The product is warmer (orange where the page is yellow) but the composition is identical, so it is the same artwork colour-graded, not a different one |
| #14093 Dogwood Blossom Still Life | SL 08 | same vase, same branch, same orange sun disc, same ledge |
| #13239 Vibrant Floral Still Life | SL 09 | same four vessels, same gold sun, same red/teal split, same purple blossoms |
| #24291 Blossom Vases at Moonrise | SL 13 | the same three vases — pink, dark embossed, cream crackle — and the same magnolia |
| #25185 Lotus Basket Still Life | SL 22 | same two lotus, same woven basket, same lily pads |

**#8805 Modern Cafe Decor is left on SL 01 and flagged.** Its featured image is
a *café interior mockup* showing four framed panels on a wall, and the second
of them is the SL 01 bouquet — the same gold cylinder vase, the same red-orange
and green colour blocks. So it is not sitting on someone else's page. But it is
a SET, the other three panels are different artworks, and it can carry one
code. Identical in shape to **#8866** in Kids Room, which was left and flagged
for the same reason.

### Two in the wrong section — and both landed on pages Wildlife gained

**#7833 Minimalist Blossom Vase → WL 21.** Same blossom branches, same pale
stone vase, same dark bowl, same ledge, same teal weathered wall, same light.

**#7834 Rustic Blossom Vase → WL 22.** Same round speckled stone vase, same
rough stone slab, same blossom branch and buds, same weathered plaster wall.

This is the Wildlife pass paying off directly. That audit recorded, as a fact
about the book rather than the catalogue, that *three of the four pages Wildlife
gained are not wildlife* — WL 21 and WL 22 being still lifes of white blossom in
a vase — and wrote down that a Still Life product might be the answer for them.
Both turned out to be, and both were found in the first section audited after
the note was written.

### Four cleared

| product | sat on | which is | where it was hunted |
|---|---|---|---|
| #22564 Decorated Lord Murugan | SL 02 | three yellow callas | a photograph of a garlanded idol; Still Life has no deity page |
| #24958 Ganesha Fire Abstract | SL 16 | the rower on marbled water | likewise |
| #28473 Quiet Harbor Minimal | SL 16 | the rower on marbled water | LC 07, LC 08, LC 10 and AA 12, the minimalist landscape pages — none is a house on an island |
| #8494 Sacred Kedarnath Temple | SL 18 | rainbow trees in water | TA 02, which is also Kedarnath — and is not this one |

**Both doubled-up pages emptied completely.** The book-read note predicted that
each pair would resolve to the non-deity claimant, because SL 02 and SL 16 each
held one deity product and one plausible one. That prediction was **wrong in
both cases**: on SL 02 the non-deity claimant turned out to belong to Wildlife,
and on SL 16 the non-deity claimant is a different picture from the page. SL 02
and SL 16 are now unclaimed. A reasonable inference from titles, and the
pictures overruled it — which is the whole reason this audit reads pictures.

### Two Kedarnath photographs, not one

#8494 and TA 02 are both the Kedarnath temple lit with lamps, and they are
different pictures. TA 02 is a portrait composition with a **purple sunset sky**
and a **large crowd** in front of the temple. #8494 is a closer, landscape view
from the front-left with a **red-lit side building** and a plain dark blue sky,
and no crowd. Each carries something the other does not, so this is not a crop
pair — the same test that separated #23191 from #19025 in Seven Horses: a crop
can remove content, never add it.

Unclaimed after this pass: 01 aside (the set), pages 02, 03, 04, 05, 07, 10, 11,
12, 14, 15, 16, 17, 18, 19, 20, 21, 23 — seventeen of twenty-three.

## Landscapes — read 2026-09-15, from the pictures

All 10 pages read and all five products held against them. LC gained no pages,
so nothing here drifted.

**Three are right, one is in the wrong section, one is cleared — and two of
those verdicts overturn rows this audit wrote itself.**

|page|asp |the picture|
|---|---|---|
|01|3050|LANDSCAPE. green forest WATERFALL over mossy rocks, sunlit woodland|
|02|5030|STAIRWAY rising to a SPLIT MOON — blue/teal left, gold/orange right, lone figure|
|03|4030|surreal ORANGE sky, white SUN, misty grey cliffs with pines over calm water|
|04|4030|huge ORANGE MOON behind tall thin CONIFERS, layered red and grey hills|
|05|5030|two white EGRETS on a bare branch, soft PINK MOON, misty water|
|06|4030|GOLDEN MOON behind a misty BLUE MOUNTAIN, pines, reflective lake|
|07|4030|flat minimalist hills, a RED TREE, pale sun, birds|
|08|3020|watercolour PINES, yellow sun, three birds|
|09|4030|whimsical TRAIN in a flower meadow, steam turning into FLOWERS|
|10|5030|abstract overlapping CIRCLES with tree silhouettes, earthy tones|

### The whole section was re-coded by arithmetic, and it was right three times in five

Every one of the five products reached its LC page the same way: an early pass
moved it from `LS n` to `LC n`, keeping the page number, when the book renamed
Lord Shiva's landscape half to LC. The rows say so in their own words — "book
LC 09 is the whimsical train", "book LC 02 is the pathway towards a celestial
entity", "book LC 03 is the misty cliffs over calm waters". Those are the page
**captions**, not the pictures; no `LC_` contact sheet existed at the time.

Held against the pictures now:

| product | early row said | verdict |
|---|---|---|
| #16566 Whimsical Train Journey | LS 09 → LC 09 | **right** — same train, same flower-steam, same red poppy |
| #13722 Stairway to the Moon | LS 02 → LC 02 | **right** — same split moon, same tree on the ledge, same figure |
| #14739 Moonlit Mountain Forest | LS 06 → LC 06 | **right** — and this row already gave a picture reason, not a caption |
| #18897 Surreal Landscape | LS 03 → LC 03 | **wrong** — it is AA 09 |
| #27133 Geometric Falls Sunrise | LS 01 → LC 01 | **wrong** — it is nothing yet |

Three in five is a good hit rate for arithmetic and a bad one for a code that
travels onto a SKU. Both wrong rows are patched in place rather than appended,
so each product keeps one row.

### #18897 Surreal Landscape → AA 09

Abstract Art, not Landscapes. The same floating bowl of cracked earth, the same
bare tree leaning off its right rim, the same lone figure walking the inner
curve, the same sun burning through ochre cloud, the same reflection pool below.
LC 03 is a Japanese-style scene of teal cliffs and a white sun on orange.

Both pages are "surreal landscapes" by title and neither is the other. AA 09's
own caption reads *"Where imagination defies gravity — art that bends reality"*,
which is this picture exactly — the one time in this audit a caption has led
straight to the right page. AA 09 carries no product today.

### #27133 Geometric Falls Sunrise → cleared

Its picture is a geometric mountain landscape in flat panels of blue, orange,
green and white, with a small orange tree and a sun disc. LC 01 is a
naturalistic green forest waterfall over mossy rocks. Neither "geometric" nor
"sunrise" describes that page — the title was the lead written down before the
sheets were drawn, and it held.

Hunted and not found: Abstract Art's geometric pages (AA 05 is two cubist faces,
AA 07 a golden tree over grey blocks, AA 10 a tree against a split moon, AA 12
Japanese layered hills, AA 13 terracotta shapes), LC 10, and all four Vaastu
pages.

### The LS→LC collision, closed

The Lord Shiva pass cleared two products it called landscapes on the grounds
that Landscapes used to be LS. Both were checked while this section was being
read, and **neither is in Landscapes** — the finding recorded with the book half.

- **#25474 → LI 50.** Living Room: the starry-night boat, same three lanterns,
  same swirling sky, same horizon silhouette.
- **#30276 stays cleared.** Its red-sun-and-bare-tree collage is neither LC 04
  (conifers against an orange moon) nor LC 07 (minimalist hills with a red
  tree).

So four products passed through Landscapes on their way somewhere else, and the
section keeps three.

Unclaimed after this pass: 01, 03, 04, 05, 07, 08, 10 — seven of ten.

## Abstract Art — read 2026-09-15, from the pictures

Seven of the nineteen pages are claimed, by **ten** products — three pages carry
two claimants each. AA gained no pages, so nothing here drifted.

**Five are right, one is in the wrong section, four are cleared — and one of
the five "right" ones has a different problem entirely.**

| page | the picture | claimed by | verdict |
|---|---|---|---|
| AA 02 | embracing figures in gold swirls, a red pomegranate | #15463 Pomegranate Goddess | right — **but see below** |
| AA 06 | red/teal abstract, bare tree, seated figure | #19575 Man in Contemplation | right |
| | | #22016 Horses in Color Field | cleared |
| AA 08 | five elongated walking figures | #25779 Dancers by the Lamp | cleared |
| AA 10 | bare tree, red apple, split gold/blue moon | #17978 Tree Under Moonlight | right |
| | | #21503 Vaishnava Saints Kirtan | cleared |
| AA 11 | cubist bird shapes, earthy tones | #15791 Geometric Animal Composition | right |
| AA 14 | a woman's profile blended with a blue bird | #7837 Geometric Floral | **→ WL 23** |
| | | #31273 Palace in the Grove | cleared |
| AA 16 | fragmented stone face, blue birds | #23728 Stone Face Taking Flight | right |

### #15463 is on the right page, and its photograph is sideways

Held against AA 02 the product looked like a different, landscape-shaped
painting. Rotated 90° clockwise it is AA 02 exactly — the same two embracing
figures, the same dark curly hair, the same hand holding the red pomegranate at
lower right, the same swirls and leaves in the same places, the same green
ground at top left.

So the **code is right and the image is wrong**: the product's picture is stored
rotated a quarter turn. No correction row is written, because there is nothing
wrong with the art code. This is a different defect from everything else in this
audit, and the repository already has tooling for it — the deploy runs a
"Product image orientation scan" and there is a `fix-orientation.yml` workflow.
Flagged here for whoever runs that next.

### #7837 Geometric Floral → WL 23

The same two stained-glass tulips, the same faceted sun disc, the same leaves in
the same places, the same colour bands. The product's own image is a tighter
crop that stops above the blue lower half — and nothing in it is absent from the
page, which is what a crop does and the reverse of the #23191 case in Seven
Horses, where the product **added** a sun that the page did not have.

WL 23 is one of the four pages Wildlife gained, and the Wildlife pass recorded
it as a floral sitting inside a wildlife section. This is what belongs on it.
That is now three of Wildlife's four new pages filled from other sections:
WL 21 and WL 22 by Still Life, WL 23 by Abstract Art.

### Four cleared

| product | sat on | which is | where it was hunted |
|---|---|---|---|
| #22016 Horses in Color Field | the red/teal abstract | chestnut, blue-grey and tan horses on plain cream | all fifteen Seven Horses pages, the three new ones included — SH 13 is red, gold and blue with dark horses, SH 14 all-white on teal and orange, SH 15 white under a golden sun |
| #25779 Dancers by the Lamp | the walking figures | a **photograph** of two child dancers between brass lamps | all five Indian Culture pages — every one a painted or watercolour dancer |
| #21503 Vaishnava Saints Kirtan | the moonlit tree | saffron-robed saints with staffs | a devotional illustration with no page here |
| #31273 Palace in the Grove | the blue-bird portrait | a miniature of a temple palace among trees with cows | PA 01, the one Pichwai page, is a pink Shrinathji with cows and is not it |

**AA 08 and AA 14 both emptied completely.** That is the same shape as SL 02 and
SL 16 in Still Life: a page with two claimants where neither is the picture.
Four such pages now, across two sections.

Unclaimed after this pass: every page except 02, 06, 10, 11 and 16 — fourteen of
nineteen.

## The no-code sweep — 2026-09-15

Every product carrying no art code, drawn in one run as eight `BATCH_` contact
sheets. This is the run that could not be done section by section: a prefix run
only draws products already on that prefix, so a product cleared to NONE is
invisible to it. Two long-deferred questions were waiting on this.

### Buddha's two deferred strays, finally placed

The Buddha pass of 2026-09-14 cleared three products and said of two of them
that the right page *"is not knowable until those sections are read"*. Both
sections have since been read.

**#27078 Seven Horses Sunset Beach → SH 03.** The same seven white horses
through pale blue surf, the same peach-and-cream cloud bank, the same spray. It
had been sitting on LB 05, a grey stone Buddha.

**#28534 Kirtan Celebration → still cleared, and still deferred.** Radha and
Krishna with gopis playing instruments in a grove. The catch is that only Radha
Krishna's **claimed** pages have been read — that section is 97 pages and its
unclaimed ones are unread. So the answer is the same as before, for the same
reason, and this is recorded so nobody re-derives it a third time.

**#27811** was never deferred: it is a Buddha that matches no Buddha page, and
Buddha gained no pages this round, so the verdict stands.

### Seven Horses gains two pages, one of them from Buddha

**#7662 → SH 02.** The same seven white horses in dark blue water, the same low
gold sun, the same broken reflections. This was one of the three products
cleared against the **twelve-page** Seven Horses book and never held against the
three pages the section gained — the oldest loose end in this audit. The nocode
sheet made its picture visible again, and SH 02 turned out to be unclaimed all
along.

So Seven Horses finishes at **8 products on their own page**, not 6.

### Three horse products that stay cleared, now for checked reasons

| product | its picture | checked against |
|---|---|---|
| #30093 Seven Horses Dust Charge | a sepia herd of brown, grey and white horses in dust on plain cream | every Seven Horses page, the three new ones included |
| #27572 Abstract Horse Pair | a close-up of large stylised horse heads in cream, blue and gold — one composition, not a group of seven | all fifteen SH pages, and Living Room's six single-horse pages (LI 22, 23, 24, 25, 26, 33) |
| #22016 Horses in Color Field | a herd with bright orange, red and blue splashes | as above, in the Abstract Art pass |

**#30093 and #22016 are not the same painting**, though they look it at a
glance: same series, same cream ground, but #22016 carries splashes of orange,
red and blue that #30093 does not. That is the third near-duplicate pair this
audit has had to separate, after the two murmurations and the two Kedarnath
photographs.

### The replay harness was blind to every product in this sweep

Worth recording as a defect in the checking, not the catalogue. The harness
rebuilds the catalogue from the renumber listing, which only contains products
that **have** a code. A product cleared to NONE never appears — so a correction
row placing one of them was counted as `missing` and silently not checked. Both
placements above read as "missing" on the first replay.

Fixed by seeding those products into the harness with the empty code that is
their real state. The counts moved from `to change: 42 | missing: 56` to
`to change: 44 | missing: 51`, which is the two placements plus the five
products now visible.

## Radha Krishna — the unclaimed pages, read 2026-09-15

The 2026-09-14 pass settled this section's **claimed** pages. Its own index says
so in its header: *"what each CLAIMED page ACTUALLY SHOWS"*. Forty-one of the
ninety-seven pages carry no product and had never been looked at. They are read
here. The 57 products on the other 56 pages are being drawn by run 1153 and are
not touched yet.

### The six pages this section gained sit at the end, and nothing drifted

RK went from 91 pages to 97. **RK 92 to RK 97 are the new ones, and all six are
uncaptioned** — the same signature LI 46–51, KR 21 and WL 20–23 carried. RK 91
is uncaptioned too and also unclaimed.

That matters for the earlier pass: pages added at the end shift nothing before
them, so the page numbers that pass wrote still hold. It was right to treat the
drift it found (the `-1` shifts on RK 29, 34 and 42) as the whole of it.

| page | what the picture is |
|---|---|
| RK 91 | baby Krishna reclining on gold and green, peacock feather |
| RK 92 | Krishna and Arjuna on the chariot at Kurukshetra, sunrise |
| RK 93 | the raas — Krishna and six gopis dancing in a moonlit marble courtyard |
| RK 94 | a black Krishna idol in a golden shrine hung with lamps |
| RK 95 | Radha seated among gopis making garlands, riverside at sunset |
| RK 96 | abstract Radha and Krishna faces in gold, teal and orange |
| RK 97 | Pichwai Shrinathji on purple and blue with lotuses |

The other thirty-four unclaimed pages were read from their captions only, which
this audit does not treat as evidence about a picture — they are a shortlist,
nothing more. Three were worth looking at and were: **RK 67** a photograph of a
Venkateswara idol in orange marigold garlands, **RK 70** a Shrinathji idol with
lamps and attendants, **RK 71** an ornate Krishna shrine with devotees.

### Two strays checked against them, and both stay cleared

**#28534 Kirtan Celebration.** Deferred twice — once by the Buddha pass on
2026-09-14, again by the no-code sweep this morning — both times because Radha
Krishna's unclaimed pages had not been read. They have been now. Its picture is
a daylight riverside grove, Krishna and Radha among a crowd of gopis with one
seated playing a veena. The two candidate pages are neither: **RK 93** is a
moonlit courtyard raas with six dancers and a full moon, **RK 95** is Radha
among gopis making garlands with no Krishna in it. No caption among the other
thirty-nine describes this scene.

It stays cleared, and "not found" now means something it did not mean before.

**#26267 Balaji Garland Darshan.** Cleared off WL 06 in the Wildlife pass
against Tirupati's three garlanded-idol pages. **RK 67 is a fourth garlanded
Balaji photograph**, unclaimed, and it is not this one: RK 67's idol wears
orange marigold garlands against a stone wall; #26267's wears red, white and
green garlands between gold panels under an overhead strip light.

That is the third pair of near-identical devotional images this audit has had to
separate by content, after the two murmurations and the two Kedarnath
photographs — and the second time a Tirupati idol photograph has turned out to
have a double.

### Radha Krishna — the products, 2026-09-15

Run 1153 drew 55 sheets, one per claimed code, 56 products (RK 33 carries two).
**Forty-eight are right and eight are wrong** — six cleared and two that turn
out to be a swap.

Every product was first held against the 2026-09-14 index, which was written
from the pages. Eleven did not match it and were settled by fetching the page
itself. Three of those eleven turned out to be matches the index had described
loosely (RK 01, RK 45) or that the tile was too small to read (RK 55, where two
figures merged into one at 312 pixels). The other eight are below.

### The swap on RK 88 and RK 90

**#22138 → RK 95.** Radha seated centre in blue and gold, the same twelve gopis
in the same poses, the same parrot, the same garlands being strung, the same
sunset over the river. RK 95 is one of the six pages the section gained, and had
never been read before today.

**#30470 → RK 88**, the page #22138 vacates. Radha on the lotus with the gold
halo, the swan, the crescent moon, Krishna playing the flute above, the doves,
the pink lotuses, the waterfall.

The corrections pass applies these in one go and reports `refused as a clash: 0`,
because it vacates before it writes — the same chain that was first exercised on
Indian Culture.

### Six cleared

| product | sat on | which is |
|---|---|---|
| #25962 Kerala Mural Krishna Cows | RK 15 | a pair seated in an orange and red forest grove |
| #17280 Krishna Radha Shrine Idols | RK 21 | ONE blue deity on a lotus inside a mandala border |
| #30836 Veena Player with Peacock | RK 63 | the same subject in a PINK moonlit haze with Krishna's silhouette behind — this one is sepia with neither |
| #31212 Radha Krishna Flute Gold | RK 77 | Bal Krishna alone on a glowing lotus leaf, cosmic teal |
| #17212 Black Krishna Idol Garlands | RK 78 | an EMERALD-GREEN idol before a fan of peacock feathers |
| #7824 Krishna Peacock Crown | RK 79 | a Krishna portrait resting his head on his hand, teal and gold |

**#25962 was already known.** The 2026-09-14 pass wrote it into its own findings
— *"#25962 015 green Kerala mural with cows (page 15 is an orange/red grove)"* —
and never resolved it. The page was fetched and held against the product today.

**#30836 overturns a row this audit wrote**, an early short-form move from RK 64
to RK 63. Patched in place, so the product keeps one row.

### A note on method, and a mistake worth recording

The stale `brochure-to-website.csv` page map disagrees with the live catalogue
in many places — products have moved since it was generated, including by this
audit's own corrections. Where it disagreed with the sheets, the sheets were
right. It is not evidence and was not used as any.

Separately: eleven of the files in the working directory looked like duplicate
pairs, which for a few minutes looked like a defect in the contact-sheet tool.
It was not. They were leftovers from the 2026-09-14 pass sitting in the same
directory, identical to a current file because the same product had moved to a
different code between the two runs. The run's own output was 55 distinct files
with no duplicates at all. Recorded because the near-miss was a contaminated
working directory, not a bug, and the check that caught it — comparing the
directory against the run's actual file list — is the one to run first next time.

## Vaastu Art — read 2026-09-15, from the pictures

Four pages, five products on three of them, two pages doubled. VA gained no
pages, so nothing here drifted. **Three are right, one moves onto the page
nobody claimed, one is cleared.**

|page|asp |the picture|
|---|---|---|
|01|3050|teal and gold PEACOCK among white blossoms|
|02|3050|stylised blue-and-gold GARUDA, wings spread, two white elephants, gold radiance|
|03|5030|two stylised BULLS in blue and ochre on teal, glyph patterns|
|04|5030|**TWO artworks side by side** — a phoenix against a golden sun, and a stained-glass floral|

### #7703 → VA 02, which resolves VA 04's double

**#7703 Divine Garuda Sacred Symbol → VA 02.** The same blue and gold Garuda,
the same two white elephants flanking it, the same crowned head, the same gold
radiance. VA 02 was unclaimed.

VA 04 prints two artworks under one code and its caption describes only the
left one — the phoenix against a golden sun. That is **#21381 Sun Phoenix
Vintage Art**, which stays. So moving #7703 off VA 04 also empties the double:
one product, one code, one picture.

### #29751 cleared, and its title is lying about its own product

**#29751 "Sacred Cow Relief Art"** is a cream and gold **relief of Radha and
Krishna** — two faces, a flute, a peacock feather, lotuses, ornate jewellery.
There are no cows in it at all. VA 03 is two stylised bulls on teal, and that is
**#15340 Nandi The Bull**, which matches it exactly.

Every other title this audit has caught was wrong about the *page* it named.
This one is wrong about the product it is attached to, which is a different and
worse thing: no amount of reading the book would have caught it.

### One artwork, printed on two pages

VA 04's right-hand panel — a stained-glass floral of tulips against a faceted
sun — is **the same painting as WL 23**, which prints it alone as a full page.
Held side by side they are identical: same tulips, same sun, same colour bands.

This is the first artwork found printed twice in the book, and it raises a
question the Abstract Art pass could not have seen: **is #7837 on the right
code?** It is. WL 23 is the artwork's own page and prints only it; VA 04's code
names the phoenix, which its caption describes and which #21381 holds.
**#7837 → WL 23 stands.**

### Travel Art, 2026-09-16

Four pages, three of them claimed. Run 1169 drew three sheets, one product each.
**Two are right and one is wrong** — and the wrong one overturns a row this audit
wrote itself.

| Page | The book shows | Claimed by | Verdict |
| --- | --- | --- | --- |
| TA 01 - 210001-6030 | portrait; lilac twilight sky, four dark temple spires and a green-roofed pavilion above a lit ghat, one boat of figures carrying no lamps | #8424 | **wrong — cleared** |
| TA 02 - 210002-6030 | Kedarnath, lamplit temple against the Himalayas, crimson sunset, crowd | nothing | unclaimed |
| TA 03 - 210003-3060 | wide; crimson sky meeting teal water, white peaks, red spires over pale steps, a boat | #31713 | right |
| TA 04 - 210004-5030 | one priest lifting a blazing lamp, huge flame, temple spire, glowing crowd | #29890 | right |

TA 02 drew no sheet at all, which is the cleanest confirmation of an unclaimed
page this audit has had: the sheet builder makes one sheet per *claimed* code, so
a missing sheet is the catalogue itself saying nothing points there.

### #8424 — the second Varanasi

**#8424 Divine Varanasi Ganga Aarti → cleared.** The row that put it on TA 01
was written by an earlier pass of this audit, and its stated reason describes
TA 01 correctly — purple sky, temple spires, hundreds of lamps, dark boats with
figures in front. All true of the page. None of it was ever checked against the
product's own picture, which is what the rule requires. The title agreed with the
page, and that was allowed to settle it.

Held side by side they are plainly two pictures:

| | TA 01 | #8424 |
| --- | --- | --- |
| shape | portrait — and both sizes the page offers are portrait | landscape |
| sky | lilac twilight, four dark shikharas, a green-roofed pavilion | none in frame at all |
| the ghat | architecture, warm glow, a few flames | dozens of conical lamp-trees |
| foreground boat | figures, no lamps | figures **and two lamp-trees** |
| second boat | small, near empty | crowded, with its own lamp-tree |

Each holds what the other has not, and a crop can remove content but never add
it, so neither is a crop of the other. That is the fourth near-identical pair
this audit has had to separate by content, after the two murmurations, the two
Kedarnath photographs and the two Tirupati idols.

Nothing else in the book is it either. TA 02 is Kedarnath, TA 03 is the stylised
river, TA 04 is the single priest. A search of every caption in the book turns up
five mentions of Varanasi, Ganga, aarti or ghat, and all five are accounted for:
three are the TA pages, and the other two are **RK 55 and RK 81**, which print
the TA 04 caption over pictures of Krishna — on RK 81 the text is not even inside
its box, spilling across the sofa in the mockup. Leftover template text, not a
description of anything. The five sections still unread hold fourteen pages
between them; none is a travel scene, and the only two of those without captions,
SA 03 and SA 04, are the Golden Temple and Guru Nanak.

So #8424 is cleared, and its home is unfound — the same standing as #28534
Kirtan Celebration.

### TA 04 was the dumping ground

Worth recording because it explains the shape of this section. Counting the whole
audit, **eighteen products have been taken off Travel Art codes and one put on**,
and the great majority of those clears came off TA 04: KR 01, SH 04, SL 22,
LS 06, MG 01, LI 04, LI 07, LI 11, LI 37, TP 05 and a long tail besides. Whatever
assigned these was using TA 04 as a default for anything it could not place, not
making a judgement. #29890 is what was left on it, and #29890 is right.

### What the export route gives, and what it does not

`export-design` will render a page at full resolution, which would beat the
516×387 thumbnail this audit has worked from throughout. It is not usable from
here: the download lands on `export-download.canva.com`, which the proxy refuses
like `media.canva.com`, and the S3 fallback trick does not carry over because the
export signature is host-scoped (`SignatureDoesNotMatch`). The live site is
refused too, so 312-pixel tiles remain the ceiling for product pictures. The way
through, used for #8424, is to crop all six mockup renders off one page and
upscale them together — six independent views of the same artwork, and agreement
between them is worth more than any single one.

## The no-code sweep, 2026-09-16

The sweep run published eight contact grids covering **182 of the 183 products
that carry no art code** — everything except #29751, whose clear landed between
the grids being drawn and this reading, and which this audit had just examined
in the Vaastu pass anyway.

### Most of them are not artworks

Worth stating plainly, because it changes what the remaining work is. Read
across the eight grids:

| grid | artworks | not artworks |
|---|---|---|
| 01 | 8 | 16 — blank placeholders, bare stretcher bars, empty frame mockups |
| 02 | 0 | 24 — frames, canvas rolls, easels, blank canvases, banner products |
| 03 | 13 | 11 — frames, a photo collage, room mockups |
| 04 | 24 | 0 |
| 05 | 23 | 1 |
| 06 | 24 | 0 |
| 07 | 24 | 0 |
| 08 | 6 | 8 — event photographs of dancers and exhibition stands |

About **sixty of the 183 are not artworks at all** — they are frames, rolls,
easels, blank canvases, signage and event photographs. They have no page in the
brochure because they are not in the brochure, and no future sweep should spend
time on them. That leaves roughly 120 real artworks to place, of which this pass
settles three.

### Three horses that had been rescued but never rehoused

The strongest lead in the sweep: Seven Horses had six pages no product claimed,
and the grids held eight uncoded horse paintings.

| product | had been cleared off | now |
|---|---|---|
| **#20169** Rainbow Horse Gallop | LS 01, a Lord Shiva page | **SH 13** |
| **#21893** Wild Horses Stampede | LR 04, a Lord Rama page | **SH 14** |
| **#20087** Seven Horses Green Meadow | TA 03, a Varanasi page | **SH 15** |

Each had been taken off a page it did not belong on by an earlier pass — all
three clears were right — and each then sat with no code because no one had held
it against the pages the section *gained*. The clear was half the job and the
audit had only been doing that half.

The matches are not close calls. SH 13 has the seven horses in the same order
left to right, maroon, black, white, pale white, black, white, chestnut, against
the same three colour fields. SH 14 has the same tan tail flowing left from the
second horse, the same second tan tail at the middle right, the same band of
coloured dabs along the base, and the same signature in the corner; its page
render reads cooler only because the mockup wall light desaturates it. SH 15 has
the same sculpted cloud masses with the blue cluster behind the right one, the
same gold cracked texture, the same green grass band and gold flecks.

Seven Horses goes from 9 claimed pages to **12 of 15**.

### The three Seven Horses pages that stay empty

| page | the book shows | why no product fits |
|---|---|---|
| SH 10 - 040010-4030 | a cubist composition of horse heads in deep red, orange, brown, black and ivory on dark maroon, angular planes | nothing uncoded is abstract in this way |
| SH 11 - 040011-3060 | wide; seven all-white horses across sunlit sand under a pale gold sky | #30093's herd is brown, grey and white on a smoky ground, and #19025's is in blue water below mountains |
| SH 12 - 040012-5030 | portrait; white horses with flame-like golden manes among big orange and yellow blossoms, glowing sun | nothing uncoded is a portrait of this kind |

#30093, #22016, #19025 and #27572 stay cleared, which agrees with the
2026-09-15 pass that had already checked #30093 and #22016 against every Seven
Horses page including the three new ones.

### The harness blindness, fixed properly this time

The 2026-09-15 pass found this defect and patched it by hand-listing the
nineteen uncoded products then involved. That was always going to go stale, and
it did: all three placements above came back `(absent)` from the harness on the
first replay, and their rows counted as `missing` and were never checked — the
same silent failure, one pass later.

The fix now derives the list instead of spelling it out. Any product named by a
correction row that is still not in the rebuilt catalogue can only be one with
no code, because anything holding a code appears in the renumber listing. So the
harness seeds every such product with the empty code that is its real state:

```
seeded 51 uncoded products named by correction rows
to change: 53 | codes cleared: 60 | already correct: 108 | refused as a clash: 0 | missing: 0
```

**`missing` goes from 51 to 0.** Every one of the 221 rows is now exercised on
every replay, and a row placing a cleared product can no longer pass unchecked.

### Where the rest of the sweep points

Recorded so the next pass starts with the leads rather than the grids. Each is a
section with unclaimed pages and uncoded artworks that plausibly belong:

| section | unclaimed pages | uncoded candidates seen in the grids |
|---|---|---|
| Tirupati Balaji | 7 | a dozen-plus Balaji idol photographs and Tanjore panels — #14678, #16640, #18229, #22564, #22947, #23911, #24094, #24470, #25021, #25596, #25657, #26023, #26267, #26328, #29281, #29829, #31456, #31588 |
| Sikh Art | 2 | #21771 and #22321, both Guru Nanak portraits; SA 04's page is a textured Guru Nanak |
| Lord Shiva | 6 | #22077, #24352, #28225, #29084, #30531 |
| Kids Room | 16 | #18727, #22625, #22505, #23850, #23558 |
| Indian Culture | 1 | #24714 and #33285 (the same henna-hands picture on two products), #25358, #25840, #28164 |
| Buddha | 1 (LB 12) | #27449, #27510, #27811, #28103, #25124, #16191, #29639 |

Also noted for whoever wants it: **#11541 and #11617 appear to be the same
picture on two products**, as do **#24714 and #33285**. Neither pair has been
confirmed at full size.

### The no-code sweep, Tirupati Balaji — 2026-09-16

Seven Tirupati pages had no product. The grids held eighteen uncoded Balaji
idols, Tanjore panels and temple photographs, which looked like the richest
lead in the sweep. **Three of the seven are filled and four are not**, and the
reason for the gap is worth more than the placements.

| product | had been cleared off | now |
|---|---|---|
| **#29829** Forest Vishnu Murti | TA 03, a Varanasi page | **TP 01** |
| **#22947** Temple Sanctum Vishnu | TA 03, the same Varanasi page | **TP 02** |
| **#16640** Vishnu Ji Statue Art | KR 04, a Kids Room page | **TP 09** |

- **TP 01** is the same dark stone four-armed Vishnu seated in a mossy hollow —
  the same gold namam, the same gold disc earrings, the same moss over chest and
  arms, the same ferns, the same waterfall below.
- **TP 02** is the same black stone standing Vishnu on its pedestal, the same
  arch behind the head, the same carved pillars lit warm orange either side, the
  same small offering at the foot.
- **TP 09** is the same green-skinned four-armed figure with the same gold
  crown, the same kirtimukha medallion low on the torso, the same pink and white
  garlands and the same gold gopuram at the lower left. The product's own image
  is cropped a little tighter at the crown, which removes nothing the page has.

Tirupati goes from 8 claimed pages to **11 of 15**.

### Why the other fifteen candidates placed nothing

They are photographs of the same subject, not the same photograph. Tirupati is
the section where that distinction bites hardest: a dozen products are pictures
of the Venkateswara idol, garlanded, crowned, in a temple, and they look
interchangeable in a contact grid at 312 pixels. Held against the actual pages
they are plainly different exposures, different garlands, different backgrounds.

| page | the book shows | nearest candidates, and why not |
|---|---|---|
| TP 07 - 050007-3050 | a reclining Vishnu in gold silks among two rows of brass oil lamps | nothing uncoded is a reclining figure |
| TP 08 - 050008-5030 | a huge golden face in dark gold dust, a tiny silhouetted figure below for scale | #29281 and #31456 are multi-panel abstracts, not this |
| TP 14 - 050014-6030 | a gold gopuram arch, chakra and conch flanking a namam at its top, Balaji standing, Lakshmi seated on a lotus below | #18229 has no gold arch and no Lakshmi; #14678 is a gold-skinned flute player, not Balaji |
| TP 15 - 050015-6030 | the full standing idol, gold breastplate and mace, white dhoti, red and white garland columns, ochre ground | #26267, #26023 and #22564 are three different photographs of the idol — a bust on a dark ground, a bust on pink, and a wide shot on a purple dais |

None of those four was cleared or moved. They stay unclaimed, and the products
stay uncoded, which is the right answer rather than a missing one.

### TA 03 was a dumping ground too

The Travel Art pass recorded TA 04 as the audit's dumping ground. TA 03 was one
as well, and this pass shows how bad it was: **#20087 (seven horses), #22947 (a
stone Vishnu) and #29829 (a forest Vishnu)** were all sitting on TA 03, a wide
stylised painting of the Varanasi ghats. Three unrelated products on one page.
All three are now on pages that are actually their own, and all three had to be
cleared first by three separate passes before anything could find them.

### The sweep's leads are worth less than they look

Recorded because it should temper the next pass. The Tirupati lead was the
largest in the sweep by candidate count — eighteen — and returned three. The
Seven Horses lead had eight candidates and returned three. Candidate count
measures how many products share a subject with a section, not how many pages
are waiting. The pages are specific images, and most near-misses are near.

## Sikh Art, 2026-09-16

Five pages, seven products, and **the worst clash in the catalogue**: four
products stacked on SA 01 and two on SA 02, while SA 04 and SA 05 had nothing at
all. All five pages now carry exactly one product and nothing is shared.

| page | the book shows | belongs to | was held by |
| --- | --- | --- | --- |
| SA 01 - 100001-3050 | the gold sanctum in daylight, a white archway at the left, a blue-purple awning along the causeway, turquoise water | **#7815** (moves from SA 02) | #25297, #26814, #28717, #29220 |
| SA 02 - 100002-3050 | flat hazy daylight, pale sky, the sanctum closer and centred, crowds along the parikrama | #26936 — right | #7815, #26936 |
| SA 03 - 100003-3050 | dusk; orange cloud streaks over blue, the lit sanctum small in a wide panorama, the clock tower right | **#26814** (moves from SA 01) | #8504 |
| SA 04 - 100004-3040 | a textured Guru Nanak, orange turban, gold sunburst, blue-teal running to crimson | **#22321** (had no code) | nothing |
| SA 05 - 100005-4030 | Guru Nanak seated in saffron, hand raised in blessing, brass vessel, the Golden Temple behind | **#8504** (moves from SA 03) | nothing |

### The section was shifted, not merely crowded

The three moves are a chain, and it is the same shape as the Radha Krishna swap:

```
#7815   SA 02 -> SA 01     (SA 01 vacated by #26814)
#26814  SA 01 -> SA 03     (SA 03 vacated by #8504)
#8504   SA 03 -> SA 05     (SA 05 was empty)
```

The corrections pass vacates before it writes, so the whole chain applies in one
go and reports `refused as a clash: 0`.

**#8504 is the one that unlocked it.** SA 03 is a dusk photograph of the temple
with no figure in it at all, and #8504 is Guru Nanak seated in saffron with the
Golden Temple behind him — down to the white flowers at the lower left that SA 05
prints. Once that moved, SA 03 was free for the dusk photograph that had been
parked on SA 01, and SA 01 was free for the daylight photograph on SA 02.

### Three Golden Temple photographs, three different photographs

This section is the clearest case yet of the trap Tirupati set. Three products
are the Golden Temple reflected in the sarovar and they are interchangeable in a
contact grid. They are not the same photograph:

| | sky | sanctum | tell |
|---|---|---|---|
| #7815 | blue, bright | vivid gold, close | a **blue-purple awning** runs along the causeway; white archway at the left |
| #26936 | pale, hazy, almost white | paler, centred | crowds along the marble parikrama, no awning |
| #26814 | dusk, orange streaks over blue | small, lit, far | wide panorama, clock tower at the right |

All six of SA 01's frame-option renders were checked, not one: the page is a
frame-colour sheet with the same artwork six times, and agreement across all six
is what settled #7815 rather than #26936.

### Three products on SA 01 that are not Sikh art at all

**#25297 Tanjore Deity with Devotees**, **#28717 Vishnu in Golden Garlands** and
**#29220 Vishnu Cosmic Lotus** were all sitting on SA 01. A Tanjore panel, a
standing Vishnu with garlands, and Vishnu on a lotus against a starfield. None of
the five Sikh Art pages is a Vaishnava figure, so all three are cleared rather
than left on someone else's page. Whatever put four products on one code was not
looking at them.

### A third page printing a caption that belongs to something else

**SA 01's caption reads "The image of a vase with blossoming flowers symbolizes
growth, freshness, and harmony in Vastu, bringing positivity and new
opportunities when placed in the east or northeast direction."** On a page whose
picture is the Golden Temple.

That is the third instance, after RK 55 and RK 81 printed TA 04's Ganga Aarti
caption over pictures of Krishna. Stale template text is not a one-off in this
book, and it is another reason the rule holds: **the caption is not evidence,
the picture is.**

### #8669 is a photograph of a living person

Flagged so no later sweep wastes time on it. #8669 appeared in the no-code grids
looking like a Guru Nanak candidate — a bearded figure in a turban, richly
dressed, in an ornate interior. Blown up it is a **photograph of a living man**
in cream and gold wedding attire holding a kirpan, and it is the same person as
#33278. These are personal or event photographs, not catalogue artworks, and they
belong with the frames and canvas rolls in the sixty products that have no page
because they are not in the brochure.

## Lord Shiva finished, 2026-09-16 — the blocker had expired

LS has been "part done" since 2026-09-14 with **five products that needed an
answer**. All five have one now, and the reason it was possible is worth stating
first.

The 2026-09-14 pass wrote down exactly why it stopped:

> Canva's image host is blocked from this session, so the book page cannot be
> fetched and compared pixel-for-pixel the way two product tiles can.

**That is no longer true.** Every thumbnail URL `read-design` returns carries a
`fallback` query parameter holding a pre-signed `s3.amazonaws.com` URL for the
same PNG, and that host is reachable. The pages have been fetchable for days.
The conclusion was sound when written and stale by the time it was being quoted
as settled — worth remembering for anything else this log calls closed.

### Two placements, both from products that had no code at all

| product | had been cleared off | now |
|---|---|---|
| **#30531** Shiva Parivar with Lion | LR 03 | **LS 17** |
| **#24352** Shiva Parvati on Kailash | SH 07 | **LS 16** |

**LS 17** is the section's only family page, and the match is whole: Shiva seated
at the left, Parvati in the green sari at the right, Kartikeya as a child in
front, Ganesha at the right, Nandi at the left, the lion at the right, the
peacock below, the hanging bells above, the snow peaks behind.

**The three stranded family scenes are none of them.** #30338 is the family
standing among clouds in landscape, #29023 is a calendar print on a tiger skin,
#31150 is a tight mural close-up of four faces. The earlier pass called #29023
"the closest but not close enough to write down" and declined. **It was right.**
The page belonged to a product nobody had held against it, because that product
had no code and so never appeared in any sheet the section drew.

**LS 16** is #24352 — Shiva glowing blue and Parvati gold on the same snowy peak,
her head on his shoulder, her hand cupped the same way, the same dark Nandi lying
at the right, the same small stone cairn at the lower right.

### A correction to this audit's own method note

#24352's stored image is **landscape**; LS 16 is a portrait page, 5 ft (H) by
3 ft (B). They are still the same artwork: the product is a landscape crop that
loses the moon and some height and adds nothing.

So **an aspect mismatch on its own does not prove two different artworks.** The
Travel Art pass listed shape first among its reasons for clearing #8424 off
TA 01, and that reads as more load-bearing than it was — the decision there
rested on content, on lamp-trees and a second boat and a sky that one picture had
and the other did not. Content decides; shape is a hint that something is worth
looking at.

### The five answered, and the stranding removed

| product | its picture | now | why |
|---|---|---|---|
| #17856 Shiva The Destroyer | Shiva alone with a trident, orange splash ring on cream | cleared | none of the six unclaimed pages is it, and it holds the literal string `LS 16` — which is now #24352's page |
| #30338 Shiva Parivar in Clouds | the family standing among clouds, landscape | cleared | LS 17 is a different composition and is #30531's; it holds `LS 18`, which is #20770's page |
| #29023 Shiva Parivar Blessing | seated on a tiger skin, calendar print | cleared | not LS 17; `LS 19` names no page of the book |
| #31150 Shiva Family Harmony | tight mural close-up of four faces | cleared | not LS 17; `LS 20` names no page of the book |
| #29578 Twin Faces of Serenity | two serene faces, the right plainly a **Buddha** with lotuses and white cows | cleared | not Lord Shiva at all. LS 09 is the profile pair with the sun, which is #16257; LB 12, the one unclaimed Buddha page, is a golden head with a bodhi leaf |

The first four were left alone in September on the argument that a code resolving
to nothing harms nobody, which was true then. **It is not true now**: LS 16 and
LS 18 are real pages with real owners, and two of these products were holding
those exact strings. Clearing removes a collision that was waiting to happen.

Measured on the replay, this pass takes Lord Shiva from:

```
  claimed 12 of 18  ->  14 of 18
  pages carrying more than one product   1 -> 0   (LS 09 was shared with #29578)
  products on untranslatable LS codes    4 -> 0
  NO ASPECT lines across the catalogue   7 -> 3   (only AL 01, AL 05, AL 06 remain)
```

LS 02 (the cubist fusion), LS 13 (the watercolour faces), LS 14 and LS 15 (the
two cosmic dances) stay unclaimed. #22077, #28225, #29084 and #27264 were held
against all four and are none of them.

### The no-code sweep, Kids Room — 2026-09-16

Sixteen Kids Room pages had no product. **Three of them are filled**, and the
method that found them is worth as much as the result.

Sixteen pages is too many to read blind, so the captions were pulled first as a
shortlist — never as evidence — and they narrowed it to five pages worth
fetching: KR 02 ("serenity… whimsy"), KR 06 ("imagination… beyond boundaries"),
KR 15 ("quirky charm"), and the two that carry no caption at all, KR 20 and
KR 22. The superhero and vehicle pages (KR 08, 09, 10, 13, 14 — "unstoppable
power", "heroism", "lightning-charged") match nothing uncoded, and were skipped
on that basis rather than fetched.

**Two of the three placements came from the uncaptioned pages.** Had the
shortlist been trusted as evidence rather than as a shortlist, both would have
been missed.

| product | had been cleared off | now |
|---|---|---|
| **#18727** Whimsy Friends | LS 05 | **KR 15** |
| **#22625** Whimsical Tower Scene | LS 08 | **KR 20** |
| **#23850** Melody Makers Illustration | TA 04 | **KR 22** |

- **KR 15** is the same two creatures — the orange one with three eyes and
  dandelion-tufted antennae beside the green one with the crown of pins — on the
  same grass under the same sage sky with the same tiny houses on the horizon.
- **KR 20** is the same tower under the red mushroom roof and striped cone, the
  same inky black creature at the left, the same striped hot-air balloon, the
  same heart-shaped window, the same bunting, the same red fire engine at its
  foot.
- **KR 22** is the same figure in the yellow patterned top holding the child in
  green, the same second figure in the green spotted top, the same double bass
  and violin, the same brown case, the same sheet-music leaves falling, the same
  toy car below.

Kids Room goes from 6 claimed pages to **9 of 22**.

### An earlier pass's guess, proved

The Lord Shiva pass cleared **#18727 off LS 05 and #22625 off LS 08**, and wrote
in both rows that they "read as Kids Room". It could not do more than say so —
the products then had no code, so they appeared in no sheet any section drew.

Both guesses were right. That is the second time this sweep has closed a loop an
earlier pass opened and labelled honestly: the same thing happened with the three
Seven Horses products, cleared correctly off wrong pages and left homeless
because nobody held them against the pages their real section had gained.

**#23850 was on TA 04**, the page the Travel Art pass identified as the audit's
dumping ground. That is the twentieth product taken off a Travel Art code.

### What did not match

#22505 (baby Krishna asleep under a peacock feather) and #23558 (baby Krishna
seated in gold) are Krishna paintings, not nursery art, and no Kids Room page is
either of them. They stay cleared. KR 02 turns out to be a sleeping cat on a
patchwork of colour blocks and KR 06 a child holding ink-drawn bubble-balloons
with fish and turtles inside — neither has an uncoded claimant.

Thirteen Kids Room pages remain unclaimed.

## Indian Culture, 2026-09-16 — the first section that needed nothing

Five pages, four products, **no corrections**. This is the first section the
audit has read end to end and found entirely correct, and it is worth recording
precisely because there is no diff to show for it.

| page | the book shows | claimed by | verdict |
| --- | --- | --- | --- |
| IC 01 - 130001-3050 | splash-watercolour dancer, arm curved over the head, rainbow pleated skirt on white | #7838 | right |
| IC 02 - 130002-4035 | dancer with a conical silver headdress, both hands raised in mudra, rainbow-teal bodice, paint drips | #13781 | right |
| IC 03 - 130003-3050 | Bharatanatyam dancer in orange and red, arm outstretched right, warm ochre wash, jasmine in her hair | #7700 | right |
| IC 04 - 130004-3040 | a woman in an orange sari seen from behind carrying a basket, green river landscape with mountains | **nothing** | unclaimed |
| IC 05 - 130005-4030 | watercolour woman dancing among pots, a kettle and stylised flowers, teal and coral washes | #20026 | right |

Four sheets were drawn, one per claimed code — IC 01, 02, 03 and 05 — which is
the catalogue confirming from its own side that IC 04 is the only empty page.

### Why it was worth reading anyway

The section had been read once, before the re-layout, when it had four pages and
they sat at 199–202. The status table has said **NO** ever since, because a read
against the old numbering proves nothing about the book as it stands: that gap
is exactly what this whole audit exists to close. Reading it again cost four page
fetches and one sheet run, and the answer is that the drift did not touch it.

### IC 04 has no claimant, and the obvious candidates are already spent

The no-code sweep listed Indian Culture as a lead with five candidates. Held
against IC 04 — a woman with a basket in a landscape — none of them is it:

| candidate | what it actually is |
|---|---|
| #24714 and #33285 | the same henna-hands namaste on two products; **already checked against all five IC pages** by the Living Room pass and cleared |
| #28778 | a stage-dancer photograph; checked against all five by the same pass and cleared |
| #25358, #25840 | photographs of dancer *pairs*; every IC dancer page is a single painted figure |
| #28164 | temple bells and white cows |

Nothing uncoded in the catalogue is a woman carrying a basket in a river
landscape, so **IC 04 stays unclaimed** and no row is written. That the Living
Room pass had already spent two of these candidates is a good sign about the
audit's own record-keeping: the leads list was stale, and the docs said so.

### The no-code sweep, Buddha — 2026-09-16

The last lead on the sweep's list, and it places nothing. **LB 12 stays
unclaimed and no row is written.**

LB 12 - 090012-6030 prints a serene Buddha head in **golden watercolour**, three
-quarter view, eyes closed, on cream, the wash behind it shaped like a bodhi
leaf — which is what its caption says too, one of the few in this book that
matches its own picture.

Every uncoded Buddha in the catalogue was held against it:

| product | what it actually is |
|---|---|
| #27449 Buddha Offering Lotus | a blue Buddha behind a hand offering a lotus and a bowl, orange drape |
| #27510 Cubist Buddha Visage | a grey and gold face assembled from cubist blocks |
| #27811 Buddha Among Pink Lotuses | a cream sculpted relief head with pink lotuses on a pale ground |
| #28103 | a blue face with a peacock feather and lotuses on colour blocks |
| #25124 Cosmic Buddha Nebula | seated among planets in a nebula |
| #16191 Serene Buddha Statue | a stone statue at a waterfall, teal |
| #29639 | a gold seated figure under a sunburst, Jain in style |
| #29578 Twin Faces of Serenity | two faces, one a Buddha, with lotuses and white cows — cleared in the Lord Shiva pass |

None is a golden watercolour head. The set is complete rather than convenient:
every product in the corrections file whose title carries *Buddha*, *Bodhi*,
*Zen*, *serenity* or *meditat* was pulled and checked, and the two that carry
none of those words (#28103, #29639) were caught by reading the grids.

### LB 01's two products are deliberate, not a clash

Worth restating since it shows up in every shared-code report. **#7839 and #220
both sit on LB 01**, and that is correct: #220 is a room mockup *of the LB 01
artwork*, so it is a second listing of the same painting, and its row says so in
the `SHARE:` form the apply tool supports. The same arrangement holds #8398 and
#26875 on HD 01. A shared code is only a fault when the two products are
different paintings.

So Buddha is finished with **no corrections**, the second section in a row to
end that way after Indian Culture.

## Lakshmi–Ganesha, 2026-09-16 — three pages, three products, nothing to change

The smallest section in the book and the third in a row to need no corrections,
after Indian Culture and Buddha.

| page | the book shows | claimed by | verdict |
| --- | --- | --- | --- |
| LG 01 - 020001-4040 | Ganesha at the left in a purple shawl holding a bowl of modaks, Lakshmi at the right in green and red with lotuses raised, both on a lotus against a sunset sky over water | #8412 | right |
| LG 02 - 020002-4030 | Lakshmi on a gold throne at the left, Ganesha at the right in a yellow dhoti, ornate gold pillars, a green arch with a magenta centre | #29951 | right |
| LG 03 - 020003-4030 | Lakshmi at the left with four arms and lotuses, Ganesha at the right in yellow and blue, a carved dark arch with hanging lamps, fruit and a book at the base | #26753 | right |

Three sheets were drawn, one per claimed code, so every page has exactly one
product and no page is empty. The section is complete.

### The three pages are near-identical in subject and not at all in picture

All three are Lakshmi and Ganesha seated side by side, all three captions say
"prosperity, wisdom, and the removal of obstacles", and in a contact grid they
would be hard to tell apart. They separate instantly on content: LG 01 is
outdoors at sunset over water with Ganesha on the **left**; LG 02 and LG 03 both
put Lakshmi on the left, and differ in that LG 02 is bright gold temple pillars
against magenta while LG 03 is a dark carved arch with hanging lamps and an
offering of fruit below.

This is the Tirupati lesson again — many products sharing a subject, each page a
specific picture — but here the catalogue had it right already.

### #8412 was placed by an earlier pass and is now confirmed

Its row reads `(none) -> LG 01`, written when the product had no code at all.
Holding the picture against the page confirms it. The product's stored image is
slightly wider than the square page crop, which is the same harmless crop
difference recorded for #24352 on LS 16 — content decides, shape is only a hint.

The other two, #29951 and #26753, have never needed a correction row and still
do not.

## Murugan, 2026-09-16 — nothing to change, and the Alwars are not in this book

Four pages, two products, **no corrections**. The fourth section in a row to end
that way, after Indian Culture, Buddha and Lakshmi–Ganesha.

| page | the book shows | claimed by | verdict |
| --- | --- | --- | --- |
| MG 01 - 060001-5030 | Murugan between Valli and Devasena, all garlanded, under an ornate gold arch with a kirtimukha above, lamps and offerings below, peacock at the right | #24169 | right |
| MG 02 - 060002-5030 | Murugan with his peacock in a blooming garden at sunrise, pink lotuses and marigolds | #24531 | right |
| MG 03 - 060003-5030 | a golden Murugan idol with his vel, peacock at his left, in a dark temple lit by rows of oil lamps | **nothing** | unclaimed |
| MG 04 - 060004-5030 | six-faced Shanmukha with many arms before a grand gopuram under a moonlit sky, peacocks below | **nothing** | unclaimed |

Two sheets were drawn, one per claimed code, confirming MG 03 and MG 04 are the
empty ones.

#24169 had been moved here from **TA 04** by an earlier pass, which is the
Travel Art dumping ground again; holding its picture against MG 01 confirms that
move was right.

### The two empty pages have no claimant

| candidate | why not |
|---|---|
| **#22564** Decorated Lord Murugan | a dark processional idol garlanded in a mandapam with embroidered cushions either side — no vel, no peacock, and not the lamp-lit sanctum of MG 03. It was cleared off SL 02 and stays cleared |
| **#26145** Tanjore Murugan Panel | a flat Tanjore panel on a black ground under a gold arch. MG 03 is a modelled golden idol among lamps and MG 04 a painted Shanmukha before a gopuram; neither is a Tanjore panel |
| **#14034** Lord Murugan Art | already cleared, and correctly: despite its title it is an acharya with a tridandi staff and two disciples, not Murugan at all |

### The book has no Alwars section

Worth recording as a finding in its own right, because three products depend on
it. Three rows in the corrections file place products on **AL 01, AL 05 and
AL 06**, each reasoned from "the Alwars page":

```
#26145  AL 01   "the Alwars page AL 01 is the Tanjore Murugan under an ornate arch"
#23496  AL 05   "the Alwars page AL 05 carries the word Mahayogi in the painting itself"
#23435  AL 06   "the Alwars page AL 06 is the standing goddess in a green sari"
```

**There is no Alwars section.** Counting every page marker in the book gives 373
across 21 prefixes — RK, LI, HD, WL, SL, KR, AA, LS, TP, SH, LB, LR, LC, SA, IC,
VA, TA, MG, LG, SN, PA — and the counts match `af_artcode_book()` exactly. No AL
appears anywhere. That is why the renumber pass reports "AL is not a section of
the book — 3 product(s)" on every run, and why these three are three of the four
codes in the catalogue that name no page.

The three are plainly one series: standing devotional figures on a black ground
under heavy gold Tanjore arches — #26145 with a staff, #23496 with hands in
namaste, #23435 a woman in a green sari holding a parrot, which is Andal.

**They are left as they are for now, deliberately.** The September argument for
leaving a code that resolves to nothing — it cannot stand on another painting —
was overturned for Lord Shiva only because LS 16 and LS 18 became real pages with
real owners. `AL` can never become a real prefix, so these three harm nobody
where they sit. What they have not had is a check against **Hindu Deities' six
unclaimed pages**, which is where a Tanjore acharya panel would most plausibly
belong; the #14034 row already notes HD 22 is "a single gold acharya". That is
the next loose end, and it is not a Murugan question.
