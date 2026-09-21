# The Digital Download modal, offline

    php tools/dd-harness/render.php
    DD_PORT=9410 NODE_PATH=/opt/node22/lib/node_modules node tools/dd-harness/run.mjs trap
    DD_PORT=9411 NODE_PATH=/opt/node22/lib/node_modules node tools/dd-harness/run.mjs notrap

`render.php` lifts the `#af-dd-overlay` markup, script and style straight out
of `functions.php` (between `<div id="af-dd-overlay"` and the end of the
following `<style>`), stubs the two PHP expressions inside it, and drops the
result on a page with a fake product card.

## The trap

The page also installs the one line that made this worth building:

```js
document.addEventListener('click', (e) => e.stopPropagation(), true);
```

That is what the live quick view leaves behind. Capture runs
`document → html → body → … → target`, so a `stopPropagation()` at document
level means the event never descends *at all* — nothing bound inside the modal
can hear it. Measured on theartframer.us: the capture chain recorded exactly
one entry, `"document"`, and pressing × did nothing while Escape (a different
event type) worked. Run with `notrap` to see the same page without it.

## What it asserts

| | |
|---|---|
| opens | the modal appears from a card click |
| says it is working | `Preparing preview…` and a shimmer while the fetch is out, never a silent blank pane |
| then shows the picture | the waiting state is replaced and the message cleared |
| Add to Cart does **not** close it | the overlay carries `data-dd-close` and is an ancestor of every control, so a careless `closest()` would shut the modal on its own buy button |
| the title does not close it | same trap, different control |
| × closes it | **with the click trap installed** |
| the backdrop closes it | |
| Escape closes it | it always did; it must go on doing so |
| the logo still goes home | the archive body carries `term-digital-downloads-2`, which `[class*="digital-download"]` matches — so `<body>` was being treated as the trigger *and* as the card |
| no console errors | |

The server answers the first preview request slowly on purpose (`DD_SLOW_MS`,
default 1500ms) — that wait is real on the live site, where the first visitor
to open a given piece waits while GD renders its watermarked preview from the
master, and it was that wait the owner filmed as an empty white panel.

## The body-class trap

WordPress puts the term slug on the body of a category archive, so
`/product-category/digital-downloads-2/` serves
`<body class="... term-digital-downloads-2 ...">`. The trigger's substring
selector `[class*="digital-download"]` matched that, and `<body>` also answers
`CARD_SEL` (`.product` among others), so on that one archive **every** click
resolved to a Digital Download trigger, called `preventDefault()` and opened
the modal — the logo included. Measured live:

    "selectorMatch": "body.archive.tax-product_cat.term-digital-downloads-2"
    "cardFromLogo":  "body.archive.tax-product_cat.term-digital-downloads-2"

`render.php` now gives the harness page that exact body class plus a header and
logo, so the trap is reproduced rather than described. A working logo really
navigates, which tears the page down; the runner waits for that load instead of
dying on a destroyed context.
