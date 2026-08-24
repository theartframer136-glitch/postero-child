# Gold Foiled & UV artwork

Drop the artwork for the Gold Foiled & UV section in this folder, commit it,
and the next deploy turns each picture into a product.

## Why here

The website runs on a server in a data centre and the deploy runs on GitHub's
machines. Neither can read a folder on someone's own PC, and a cloud Claude
session cannot either. But this theme IS rsynced to the server on every
deploy — so a picture committed here arrives on the server as a matter of
course, with no upload step at all. That makes this the one route that works
when the artwork can only reach Claude as a chat attachment.

## What happens to a file placed here

`tools/import-gold-foil.php` runs on every full deploy and, for each new
image, creates one published product:

- **title** from the filename, cleaned up and title-cased
- **size** read out of the filename when it names one (`36x48`, `3x4-feet`),
  otherwise the smallest size the studio offers, written into the title so the
  listing price and the size the selector opens on can never disagree
- **price** the rate card's price for that size x `af_goldfoil_ratio` (1.40 —
  40% more than a normal print), with the usual struck-through figure above it
- **category** Gold Foiled & UV, and the first picture imported becomes the
  category's own icon
- **description** the gold-foil and UV-coat copy, same shape as the rest of
  the catalogue

It is safe to re-run: each product records the file it came from, so a second
deploy only picks up what is new. Nothing here is ever deleted or renamed.

## Names are worth a moment

The filename becomes the product name, so `radha-krishna-eternal-36x48.jpg`
reads better than `IMG_2481.jpg`. Words like `final`, `copy` and `edited` are
stripped, and a leading sequence number is dropped.

## No new artwork at all

Gold foil and a UV coat are a *finish*, so a piece the studio already sells
can be offered in it without a single new file:

    Actions → Deploy Theme → Run workflow → Gold Foiled & UV source
    category:some-category-slug

`tools/diag-goldfoil-populate.php` lists every category with a true count of
the products in it, so the slug is chosen from what is really there. The
source piece's picture, gallery and words come with it; the price is worked
out from the rate card as it is for everything else in the section.

## Size

Keep this to real artwork. Git stores every version of every file forever, so
a hundred 8 MB photographs make the repository permanently heavier for
everyone who clones it. For a large library, drag the folder into the site's
own Media Library instead (wp-admin → Media → Add New) — new uploads that
nothing on the site uses yet are imported automatically on the next deploy,
so that drag is the whole procedure. `wp option update af_goldfoil_source off`
switches the automatic routes off.
