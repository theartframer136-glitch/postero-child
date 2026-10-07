# Gold Foiled & UV — home page pictures

Advertising photographs for the **Gold Foiled & UV** band on the home page.
They are pictures only: no product exists behind any of them and nothing here
is for sale.

`inc/goldfoil-promo.php` lists this folder and shows what it finds. To change
the band, add or remove a JPG here — the filename becomes the caption, so name
it like the piece.

## Do not put these in `assets/gold-foil/`

That folder is scanned by `tools/import-gold-foil.php` on every full deploy and
each picture in it becomes a published product. These images started there, and
that is exactly how seven products nobody wanted got created. Deleting those
products while the files stayed put would have recreated them on the very next
deploy.

`assets/gold-foil/` is for real artwork that should be sold. This folder is for
pictures that should only be looked at.
