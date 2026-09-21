# Product export

`products.tsv` is every product in the live catalogue, tab separated,
one row per product in ascending product id, with a header row:

    art_code  category  sub_category  link  product_id  title  status

`category` holds the TOP-LEVEL product categories and `sub_category`
the child ones, each joined with " | " when a product sits in more
than one. WooCommerce has no real notion of a primary category, so
nothing is dropped by picking one.

Read it with:

    git fetch origin product-export
    git show origin/product-export:products.tsv

This branch is a delivery channel, not history. It is force-pushed by
every run of Check Product Export and holds only the newest rows.

424 products, from run 7, commit 369638659bfcd2d309ef81602edd69ba4bc01562.
