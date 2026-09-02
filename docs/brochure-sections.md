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
