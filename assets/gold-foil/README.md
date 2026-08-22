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

## Size

Keep this to real artwork. Git stores every version of every file forever, so
a hundred 8 MB photographs make the repository permanently heavier for
everyone who clones it. For a large library, upload to the Media Library
instead and import with `media:since:<date>`.
