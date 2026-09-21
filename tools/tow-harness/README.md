# tow-harness — offline test rig for "Try It On Your Wall"

Runs the live-camera calibration of the `/try-on-wall` page with no WordPress,
no WooCommerce and no network. PHP renders the real page markup + script out of
`functions.php` against stubs; Playwright/Chromium plays a synthetic wall clip
through a fake camera and reports what the calibration did.

Nothing here touches `functions.php`. Everything lives in `tools/tow-harness/`.

## Prerequisites (already on this machine)

- `/usr/bin/php` 8.4 with GD
- `/usr/bin/ffmpeg`
- Node 22 at `/opt/node22`, Playwright 1.56 in `/opt/node22/lib/node_modules`
- Chromium in `/opt/pw-browsers` (`PLAYWRIGHT_BROWSERS_PATH` is set) — do **not** run `playwright install`

## Commands

```sh
cd /home/user/postero-child

# 1. render the page (reads functions.php, writes www/index.html + placeholder images)
php tools/tow-harness/render.php
TOW_LOGGED=0 php tools/tow-harness/render.php          # logged-out variant (AFPreview.cfg.logged=false)

# 2. build the fake-camera clips (wall-lock.y4m locks, wall-miss.y4m must not)
php tools/tow-harness/make-walls.php

# 3. drive the page:  <lock|miss> <mobile|desktop>
NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run.mjs lock mobile
NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run.mjs miss mobile
NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run.mjs lock desktop
```

Each run finishes in well under 40 s (lock ≈ 7 s, miss ≈ 17 s), always exits 0,
serves `www/` on `127.0.0.1:<port>` and stops the server when done. The port is
`TOW_PORT` if set, otherwise `9000 + (pid % 900)`, so the three runs can go in
parallel. The rendered page uses relative URLs (`/art-101.png`, `/admin-ajax.php`)
so it works on any port. If a run is interrupted and a port stays busy:
`pkill -f "[r]un.mjs (lock|miss)"`.

## What the runner does

1. Static server on `127.0.0.1:<port>` for `www/`. `POST /admin-ajax.php` is
   recorded (action, source, product, size, frame, layout, image prefix +
   length) and answered with a canned success JSON. Any missing
   `/uploads/mockups/*.jpg` falls back to a generated room photo so no 404s
   reach the console.
2. Chromium with `--use-fake-device-for-media-stream` and
   `--use-file-for-fake-video-capture=<absolute y4m>`, camera permission
   granted, downloads accepted. `mobile` = 423×820, isMobile, touch;
   `desktop` = 1280×900.
3. Opens `index.html`, picks the first real product in `#tow-prod`, waits
   1.5 s, attaches `download` listener, clicks `#tow-cambtn`, then polls every
   200 ms for up to 15 s reading `window.AFCal`, `#tow-calbox` classes,
   `#tow-cam`, `#tow-wallimg`, `#tow-framebox`, `#tow-toast`.
4. Prints a timeline (one line per state change) and a `REPORT` block, then
   `PASS/FAIL` for (a) lock with wall-lock, (b) no lock with wall-miss,
   (c) no console errors. Freeze / download / ajax facts are reported as INFO
   only, not asserted.

## The clips

640×480, 2 s, 15 fps, yuv420p. Light-grey wall `#d9d4c8` with ±2-level grain,
6 px black ceiling and floor lines full width.

| clip | lines at rows | fraction of height | expected |
|------|---------------|--------------------|----------|
| `wall-lock.y4m` | 77 / 403 | 0.16 / 0.84 (= `CAL_TOP` / `CAL_BOT`) | locks in ~1 s |
| `wall-miss.y4m` | 29 / 451 | 0.06 / 0.94 | never locks |

Why they map 1:1: the stage is narrower than 4:3 at both viewports
(349×360 mobile, 796×750 desktop), so `object-fit:cover` scales the 4:3 feed
to the stage **height** and crops only horizontally — a row at fraction *f* of
the video lands at fraction *f* of the stage. If a future layout makes the
stage wider than 4:3, `calRows()` will crop vertically and the rows here need
recomputing.

## Stubs (render.php)

`render.php` finds the closure by its markers (the `add_action('template_redirect', function(){`
line immediately followed by the `is_page(array('try-on-wall','try-it-on-your-wall'))` line, through
the matching `}, 1);` after `get_footer(); exit;`) and `af_preview_share_assets()` (its `function`
line through the line before `// ── PHASE 28`). It wraps the closure body as `af_tow_render()`,
replaces the trailing `exit;`, lints the temp file with `php -l`, includes it, and captures the
output. The temp file is left at `tools/tow-harness/.tow-page.tmp.php` for inspection.

Stubbed: `esc_url/esc_attr/esc_html`, `home_url` → relative (`$BASE=''`), `admin_url`,
`add_query_arg`, `wp_json_encode`, `wp_create_nonce` → `testnonce`, `is_user_logged_in`
(env `TOW_LOGGED`), `is_page`, `is_wp_error`, `get_header/get_footer`, `get_terms` (2 cats),
`wc_get_products` → `[101,102]`, `wc_get_product` (get_image_id/get_name), `wp_get_attachment_image_url`
→ `/art-<id>.png`, `wc_get_price_to_display` → 120, `wp_get_post_terms`, `get_permalink`,
`af_goldfoil_factor` → 1, `get_woocommerce_currency_symbol` → `$`, `wp_get_upload_dir`,
`af_frames_in_stock` (all four frames, so every frame is selectable in tests — the real function
currently returns only Without Frame + Aluminium Frame), `af_sizes_available` (same shape as the
real one: array_values of price-book labels), `af_pricing_config` (same keys as the real one:
sizes/groups/hints/frames/colors), `wc_get_page_permalink`, `wc_get_endpoint_url`.

## Files

```
render.php        page renderer (PHP CLI)
make-walls.php    wall PNG + y4m generator (GD + ffmpeg)
run.mjs           Playwright runner
www/              generated: index.html, art-101.png, art-102.png, saved.png, uploads/mockups/*.jpg
wall-lock.y4m     generated (13 MB) — gitignored
wall-miss.y4m     generated (13 MB) — gitignored
```

## run-recover.mjs — the ways OUT of the frozen state

    NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-recover.mjs \
        <scan-again|scene|camtoggle|upload|resize|twice> [mobile|desktop]

`run.mjs` proves the lock freezes the wall and saves the shot. This proves the
page can leave that state cleanly, which is where a freeze feature usually
rots. Every scenario first drives a real lock, then does one thing and reports
the state before and after:

| scenario     | the one thing                    | what must hold afterwards |
|--------------|----------------------------------|---------------------------|
| `scan-again` | tap the recalibrate pill/chip    | camera live again, still dropped, nothing left locked |
| `camtoggle`  | press the camera button          | same |
| `scene`      | pick a room scene                | room photo back, its thumbnail lit, frozen cleared |
| `upload`     | upload a wall photo              | uploaded wall shown, frozen cleared |
| `resize`     | shrink the window                | the artwork tracks the frozen still by the object-fit:cover factor, and nothing is saved again |
| `twice`      | scan again and lock again        | one save per lock, never more than two |

Notes that cost time to learn:

* Click with `page.click()`, never `el.click()` inside `page.evaluate`.
  Playwright scrolls the target into view first, and headless Chromium only
  renders video frames for an element that is actually on screen — off screen,
  `drawImage()` reads back solid black and the edge detector can never fire.
* On a phone the in-stage recalibrate pill is hidden by the ≤600px rule that
  keeps everything off the video; the reachable control is
  `.af-tow-camhead-recal` in the strip above it.
* `render.php` also splices in the `#af-mobile-responsive` stylesheet, which
  lives in a different `wp_head` hook. Without it the stage has no
  `aspect-ratio:4/3` and a phone run silently measures desktop geometry.
* Pass `TOW_PORT` (9100–9900) so parallel runs cannot collide.

## The four single-claim probes

Each of these came out of the review as an attempt to break one specific
guarantee, and each stayed because it is the cheapest way to notice that
guarantee going again. They print facts and a short verdict; none of them
needs an argument.

| probe | the guarantee it guards |
|-------|-------------------------|
| `run-repeat.mjs` | the automatic account save is capped per visit (3), the manual button is not |
| `run-savedurl.mjs` | an automatic save never arms WhatsApp / Email / Copy with the room photo |
| `run-toastshare.mjs` | the "add it to your Photos" offer does not outlive its toast and leave it swallowing taps |
| `run-wallready.mjs` | the auto-save waits for the frozen wall to decode, so no half-drawn wall is baked in |

Run them the same way as the others, each with its own `TOW_PORT`:

    php tools/tow-harness/render.php
    TOW_PORT=9310 NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-repeat.mjs

Expected on the current code, in order: `POSTs=4 downloads=6` (three
automatic, one manual); `NO LEAK: share still points at the product/page
link`; `pe=none` once the toast has gone; `opaqueFrac 1` with one save.
