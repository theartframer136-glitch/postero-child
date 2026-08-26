# Personalised / custom artwork

Source pictures for the products shown in the homepage's **Exclusively
Customised Creations** section.

These came from `C:\Users\user\SynologyDrive\Personalised` and were briefly
filed under Gold Foiled & UV by mistake. They are kept here so the originals
that produced each product stay next to the code, but NOTHING scans this
folder — the products already exist and are edited in WooCommerce.

The section itself is an Elementor block whose shortcode renders the
**Personalised Prints** category (term 148). To add a piece to it, give the
product that category; to remove one, take the category away. There is no
importer in the loop.

Do not move files back into `assets/gold-foil/`: that folder IS scanned, and
`tools/import-gold-foil.php` would turn each one into a second, gold-priced
copy of a product that already exists here.
