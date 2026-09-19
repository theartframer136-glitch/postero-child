# Artwork for `add-brochure-products.php`

Drop the picture for a brochure page here, named as the `image` column of the
matching CSV row says. `tools/brochure-products-still-life.csv` expects:

```
p233-SL-150002-4030.jpg      p246-SL-150015-4035.jpg
p234-SL-150003-4030.jpg      p247-SL-150016-4030.jpg
p235-SL-150004-5030.jpg      p248-SL-150017-5030.jpg
p236-SL-150005-5030.jpg      p249-SL-150018-4030.jpg
p238-SL-150007-5030.jpg      p250-SL-150019-5030.jpg
p241-SL-150010-5030.jpg      p251-SL-150020-5030.jpg
p242-SL-150011-5030.jpg      p252-SL-150021-5030.jpg
p243-SL-150012-5030.jpg      p254-SL-150023-5030.jpg
p245-SL-150014-5030.jpg
```

These files travel to the server in the workflow checkout, so they live in the
repository. That puts a ceiling on them: **web-ready, not print masters.** The
listing image only ever renders a few hundred pixels wide on a card and around
1500 in the lightbox, so roughly 2000–3000px on the long edge at JPEG quality
85 is plenty, and a multi-hundred-megabyte print master in git is not.

The print file is a separate concern and does not belong here — nothing in this
tool reads it.

## What happens to a file dropped here

On a dry run: nothing. The run reports `would be uploaded from
tools/artwork/<name>` and writes nothing at all.

On an apply run it is **copied** (not moved — the original belongs to the
deploy), side-loaded into the Media Library, and set as the product's featured
image. A re-run finds the attachment already there by name and reuses it rather
than uploading a second copy.

## Naming is the contract

The `image` column is matched by basename only, so the folder layout here does
not matter, but the name must agree with the CSV exactly. A row whose file is
absent is refused and named in the output — it is never created without a
picture, and never given somebody else's.

Instead of a filename the column also accepts an attachment id (`#24291`) or a
URL already on this site, either of which skips the upload entirely.
