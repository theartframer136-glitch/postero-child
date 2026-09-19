# Kids' Room: five pages deliberately NOT in the CSV

`tools/brochure-products-kids-room.csv` has 8 rows. The section has 13 unsold
pages. These five are held back, and the reason is not artwork, sizing or
pricing — those are all fine. It is that each one depicts a character somebody
else owns.

| Page | Art code | What the artwork shows | Rights holder |
|---|---|---|---|
| 289 | KR-180008-4030 | Superman — the pentagonal S-shield on the chest, red cape, the hair curl | DC Comics / Warner Bros. |
| 290 | KR-180009-3050 | Captain America holding Mjolnir — star shield, scale mail, the Endgame moment | Marvel / Disney |
| 291 | KR-180010-3050 | The Hulkbuster Iron Man armour | Marvel / Disney |
| 294 | KR-180013-3050 | Po from *Kung Fu Panda*, in the straw hat | DreamWorks / Universal |
| 295 | KR-180014-3050 | Zenitsu Agatsuma from *Demon Slayer* — the haori, the hair, the lightning | Shueisha / Aniplex |

The brochure captions describe these as generic ("Unleash unstoppable strength
— power, courage, and heroism redefined"), which is why they were not obvious
from the caption audit. They are obvious from the pictures.

## Why this is held rather than flagged and shipped

Listing these for sale is not a grey area. Selling canvas prints of Superman or
Captain America without a licence is copyright infringement of the character
design and trademark infringement of the marks on it — the shield, the
S-shield. The realistic consequences are a takedown of the listing, removal
from any marketplace the shop syndicates to, a payment-processor complaint, and
a demand letter. None of that is worth $80 a print.

Being a redrawing rather than a copied frame does not help: what is protected
is the character, and these are recognisably the character. Being an AI
generation does not help either — the person who lists it for sale is the one
who infringes.

## What would change this

- **A licence.** If TheArtFramer holds one for any of these properties, say so
  and the row goes into the CSV like any other.
- **Redrawing them as generic.** A superhero silhouette that is nobody in
  particular sells fine. A blue-and-red figure with a star shield does not
  become generic by removing the name.
- **Pulling the pages from the brochure.** Worth considering separately: the
  book is a sales document that currently shows five pieces the shop should
  probably not sell, and it goes to customers.

## What was done with them

Nothing. They are not in the CSV, so `add-brochure-products.php` never sees
them and cannot create them — not even by accident on an `APPLY=1` run. If the
decision is to sell them, adding the rows is a two-minute edit; the names,
sizes and prices are already worked out and recorded here:

| Page | Size | Price |
|---|---|---|
| 289 | 3×4 ft (36×48 in) | $80 |
| 290 | 3×5 ft (36×60 in) | $100 |
| 291 | 3×5 ft (36×60 in) | $100 |
| 294 | 3×5 ft (36×60 in) | $100 |
| 295 | 3×5 ft (36×60 in) | $100 |
