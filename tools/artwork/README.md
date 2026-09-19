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

`tools/brochure-products-wildlife.csv` expects:

```
p260-WL-170002-5030.jpg      p262-WL-170004-5030.jpg
p263-WL-170005-5030.jpg      p264-WL-170006-5030.jpg
p266-WL-170008-3020.jpg      p267-WL-170009-4030.jpg
p268-WL-170010-5030.jpg      p270-WL-170012-5030.jpg
p272-WL-170014-5030.jpg      p273-WL-170015-4030.jpg
p274-WL-170016-5030.jpg      p275-WL-170017-5030.jpg
p276-WL-170018-5030.jpg      p277-WL-170019-6030.jpg
```

`tools/brochure-products-living-interiors.csv` expects:

```
p304-LI-190001-3050.jpg        p309-LI-190006-5030.jpg
p318-LI-190015-4030.jpg        p319-LI-190016-5030.jpg
p320-LI-190017-4030.jpg        p321-LI-190018-4030.jpg
p322-LI-190019-4030.jpg        p323-LI-190020-6030.jpg
p324-LI-190021-4030.jpg        p326-LI-190023-5030.jpg
p328-LI-190025-5030.jpg        p329-LI-190026-5030.jpg
p330-LI-190027-5030.jpg        p331-LI-190028-4030.jpg
p333-LI-190030-5030.jpg        p334-LI-190031-4030.jpg
p337-LI-190034-5030.jpg        p339-LI-190036-4040.jpg
p341-LI-190038-5030.jpg        p342-LI-190039-5030.jpg
p343-LI-190040-5030.jpg        p344-LI-190041-5030.jpg
p345-LI-190042-5030.jpg        p346-LI-190043-5030.jpg
p347-LI-190044-4030.jpg        p348-LI-190045-5030.jpg
```

`tools/brochure-products-kids-room.csv` expects (8 rows; five pages are held
back — see `tools/brochure-products-kids-room-HOLD.md`):

```
p283-KR-180002-4030.jpg        p285-KR-180004-4030.jpg
p286-KR-180005-5030.jpg        p287-KR-180006-4030.jpg
p288-KR-180007-4030.jpg        p298-KR-180017-5030.jpg
p299-KR-180018-5030.jpg        p300-KR-180019-6030.jpg
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
