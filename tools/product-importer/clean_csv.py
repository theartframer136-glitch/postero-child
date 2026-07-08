#!/usr/bin/env python3
"""
Clean products_extracted.csv -> products_clean.csv

Fixes the raw brochure extraction:
  - drops non-products (the frame-options page, empty names)
  - normalizes sizes (feet notation -> "AxB Feet", pipe-separated for variations)
  - turns descriptive-sentence names into clean titles
  - assigns a subject-based subcategory (Hindu Deities, Buddha, Wildlife, ...)
  - de-dupes by name

Usage:
    python clean_csv.py
    # then: python publish.py --gallery --no-ai-images --csv products_clean.csv
"""

import csv
import json
import re
from pathlib import Path

HERE = Path(__file__).parent
SRC = HERE / "products_extracted.csv"
OUT = HERE / "products_clean.csv"

FRAME_NAMES = {"black frame", "silver frame", "rose gold frame",
               "golden frame", "white frame", "wooden frame", "no frame"}

# Explicit fixes for the descriptive-sentence names.
NAME_FIX = {
    "a serene depiction of devotion and enlightenment, showcasing monks offering prayers before a radiant divine idol in a beautifully lit temple.": "Monks Praying at Temple",
    "a divine portrayal of spiritual wisdom and serenity": "Divine Spiritual Wisdom",
    "a serene spiritual moment capturing devotion, reverence, and timeless wisdom in vibrant color.": "Serene Spiritual Devotion",
    "a serene spiritual leader adorned with flowers exudes wisdom and peace, embodying divine grace and devotion.": "Spiritual Guru with Flowers",
    "sail into a world of color and creativity": "Colorful Sailboat",
    "set sail beyond imagination - where dreams chart their own course": "Sailboat Dreams",
    "celebrate the art of freedom": "Freedom Abstract Art",
    "passion meets melody": "Passion Meets Melody",
    "a vase with blossoming flowers in vastu": "Vastu Blossoming Flowers",
    "imagination defies gravity": "Imagination Defies Gravity",
    "woman in a serene natural landscape": "Woman in Natural Landscape",
    "tree in front of a white building": "Tree by White Building",
    "man with bouquet of flowers and hearts": "Man with Bouquet of Flowers",
    "two birds on a flowering branch": "Two Birds on Flowering Branch",
}

CATEGORY_RULES = [
    (["radha", "krishna"], "Radha Krishna"),
    (["buddha"], "Buddha"),
    (["golden temple", "harmandir", "guru nanak"], "Sikh Art"),
    (["jesus", "mary", "christ", "cross", "holy family", "crucifix", "ascension"], "Christian Art"),
    (["hanuman", "ganesh", "lakshmi", "saraswati", "durga", "shiva", "surya",
      "garuda", "sai baba", "deity", "idol", "vishnu", "swaminarayan", "mahavira",
      "goddess", "divine", "spiritual", "sacred", "monks", "guru"], "Hindu Deities"),
    (["horse"], "Seven Horses"),
    (["elephant", "deer", "zebra", "giraffe", "peacock", "cat", "bee", "snail",
      "cow", "bird", "butterfly", "wolf"], "Wildlife"),
    (["superman", "batman", "iron man", "captain america", "kung fu", "anime"], "Kids Room Decor"),
    (["dancer", "bharatanatyam"], "Indian Culture"),
    (["waterfall", "sunrise", "landscape", "boat", "cityscape", "sail", "volcano",
      "umbrella", "tree", "pathway", "twilight"], "Landscapes"),
    (["flower", "floral", "lotus", "bloom", "vase", "poppies", "still life"], "Still Life"),
    (["abstract", "geometric", "silhouette", "reflection", "fusion", "modernist"], "Abstract Art"),
]


def existing_store_names():
    """Normalized names of products already on the store (from the analyzer pull)."""
    raw = HERE / "output" / "products_raw.json"
    if not raw.exists():
        return []
    prods = json.loads(raw.read_text(encoding="utf-8"))
    return [re.sub(r"[^a-z0-9]", "", (p.get("name") or "").lower()) for p in prods]


def normalize_sizes(raw):
    parts = re.split(r"[,/]| or ", raw)
    out = []
    for p in parts:
        nums = re.findall(r"\d+(?:\.\d+)?", p)
        if len(nums) >= 2:
            unit = "Feet" if ("ft" in p.lower() or "feet" in p.lower()) else "Inches"
            out.append(f"{nums[0]}x{nums[1]} {unit}")
    seen, res = set(), []
    for x in out:
        if x not in seen:
            seen.add(x)
            res.append(x)
    return res or ["3x4 Feet"]


def clean_name(name):
    key = name.strip().strip('"').lower()
    if key in NAME_FIX:
        return NAME_FIX[key]
    n = name.strip().strip('"')
    words = n.split()
    if len(words) > 6 or (n and n[0].islower()):
        n = " ".join(words[:5]).rstrip(",.-")
    return n[:1].upper() + n[1:] if n else n


def category_for(name):
    n = name.lower()
    for keys, cat in CATEGORY_RULES:
        if any(k in n for k in keys):
            return cat
    return None


def main():
    if not SRC.exists():
        raise SystemExit(f"{SRC} not found. Run extract_brochure.py first.")
    with open(SRC, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    existing = existing_store_names()
    cleaned, seen, skipped_existing = [], set(), 0
    for r in rows:
        subj_raw = (r.get("subject") or "").strip()
        if not subj_raw or subj_raw.lower() in FRAME_NAMES:
            continue
        if Path(r.get("image", "")).name == "191.jpg":  # frame-options page
            continue
        name = clean_name(subj_raw)
        key = re.sub(r"[^a-z0-9]", "", name.lower())
        if key in seen:
            continue
        # Skip if this product already exists on the store (name contained in a
        # live product title). Min length avoids over-matching short generic words.
        if len(key) >= 6 and any(key in e for e in existing):
            skipped_existing += 1
            print(f"  skip (already on store): {name}")
            continue
        seen.add(key)

        sizes = normalize_sizes(r.get("sizes") or r.get("size") or "")
        cat = category_for(name)
        cats = "Digital Canvas Prints" + (f"|{cat}" if cat else "")
        cleaned.append({
            "subject": name,
            "size": sizes[0],
            "sizes": "|".join(sizes),
            "style": "Premium Digital Canvas Print",
            "use_case": "Living Room & Home Spiritual Wall Décor",
            "categories": cats,
            "tags": "",
            "focus_keyword": f"{name} canvas wall art".lower(),
            "price": "",
            "image": r.get("image", ""),
            "sku": r.get("sku", ""),
        })

    # Detect collage pages: one brochure image used by several products.
    # Those can't give each product its own picture, so set them aside.
    from collections import Counter
    img_counts = Counter(p["image"] for p in cleaned)
    unique = [p for p in cleaned if img_counts[p["image"]] == 1]
    collage = [p for p in cleaned if img_counts[p["image"]] > 1]

    cols = ["subject", "size", "sizes", "style", "use_case", "categories",
            "tags", "focus_keyword", "price", "image", "sku"]
    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        w.writerows(unique)

    if collage:
        collage_path = HERE / "products_collage.csv"
        with open(collage_path, "w", newline="", encoding="utf-8") as f:
            w = csv.DictWriter(f, fieldnames=cols)
            w.writeheader()
            w.writerows(collage)

    print(f"Cleaned {len(rows)} rows -> {len(unique)} products with their own image")
    print(f"  ({skipped_existing} skipped as already on store, "
          f"{len(collage)} set aside as collage-page products)")
    print(f"Wrote {OUT}")
    if collage:
        print(f"Also wrote {HERE / 'products_collage.csv'} (shared-image ones "
              f"to handle later).")
    print("Next: python publish.py --gallery --no-ai-images --limit 3 --csv products_clean.csv")


if __name__ == "__main__":
    main()
