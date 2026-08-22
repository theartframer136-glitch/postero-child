# Importing the Personalised folder into Gold Foiled & UV

The same pipeline that built the main catalogue, pointed at a different folder
and a different section. Nothing about the process changes — vision still names
each product from its own picture, the cache is still crash-safe, and re-runs
still resume where they stopped.

Three options were added to `build_from_images.py` for this:

| option | what it does |
|---|---|
| `--main-dir PATH` | read this folder instead of the Final Edited Photos ones (repeatable) |
| `--gallery-dir none` | this collection has no room shots; don't look for any |
| `--category NAME`  | file every product under this section outright |
| `--out FILE`       | write a separate CSV, so the main run's CSV is untouched |

## Get the change

Git only works inside the repository, so start with the `cd` — running these
from `C:\WINDOWS\System32` gives "fatal: not a git repository".

    cd C:\Users\user\Documents\postero-child
    git fetch origin claude/exciting-sagan-ELMgW
    git checkout origin/claude/exciting-sagan-ELMgW -- tools/product-importer/build_from_images.py

That takes the one file and leaves the branch you are on alone.

## Build the products

One line. PowerShell continues a line with a backtick, not with `^` — `^` is
Command Prompt syntax and silently breaks the command in PowerShell.

    cd tools\product-importer
    python build_from_images.py --main-dir "C:\Users\user\SynologyDrive\Personalised" --gallery-dir none --category "Gold Foiled & UV" --out products_goldfoil.csv --store-min 9999 --delay 25

`--store-min 9999` is the important one. That flag normally drops any image
that visually matches something already on the store — which is exactly what a
gold-foiled version of an existing artwork WOULD do. Setting it high keeps
every picture.

`--delay 25` paces the vision calls under Groq's free-tier limit, the same
setting that fixed the stalling on the main run.

## Publish them

    python publish.py --no-ai-images --csv products_goldfoil.csv --fresh-skus

Test one first if you like: add `--limit 1` to both commands.

## The price is not in the CSV, on purpose

The `price` column stays empty, and that is deliberate. This store prices a
canvas from its SIZE against the printed rate card, and the site applies the
Gold Foiled & UV ratio — currently 1.40, so 40% more than a normal print — on
top of that card. Because these products land in the Gold Foiled & UV
category, the site prices them itself: a 2x3 ft at $85, a 3x4 ft at $110, a
4x6 ft at $150 -> $210.

Writing a price here instead would create a second price book that drifts the
first time the card changes. One command moves every price in the section:

    wp option update af_goldfoil_ratio 1.4

## After publishing

They arrive as drafts. Skim them, publish the ones you want, and the section's
category icon becomes the first real gold-foil artwork automatically.
