#!/usr/bin/env python3
"""
Assign each product its CORRECT main image by visual content.

The product's NAME comes from its brochure page, so the correct main image is the
one in your photo folders whose ARTWORK matches that brochure page. This compares
each product's brochure page against every main-image file (center-cropped, so it
matches the art, not the room) and picks the best.

Input:  products_clean.csv   (image column = the brochure page)
Output: products_matched.csv (image column = the correct main image)
        main_match_report.txt

Run:    python visual_main.py
        then: python visual_match.py   (adds gallery)
"""

import csv
from pathlib import Path

from visual_match import features, good_matches

HERE = Path(__file__).parent
SRC = HERE / "products_clean.csv"
OUT = HERE / "products_matched.csv"
REPORT = HERE / "main_match_report.txt"
MAIN_DIRS = [
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far"),
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\New"),
]
IMG_EXT = {".jpg", ".jpeg", ".png", ".webp"}


def main():
    if not SRC.exists():
        raise SystemExit(f"{SRC} not found — run clean_csv.py first.")
    main_files, seen = [], set()
    for d in MAIN_DIRS:
        if not d.exists():
            continue
        for f in d.iterdir():
            if f.is_file() and f.suffix.lower() in IMG_EXT and f.resolve() not in seen:
                seen.add(f.resolve())
                main_files.append(f)
    if not main_files:
        raise SystemExit("No main images found — check the folder paths.")
    print(f"Indexing {len(main_files)} main images (one-time) ...")
    main_feat = [(f, features(f)) for f in main_files]

    with open(SRC, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    used, lines = set(), []
    for i, r in enumerate(rows, 1):
        page = r.get("image", "")            # brochure page for this product
        pf = features(Path(page)) if page else None
        best_n, best_f = 0, None
        for f, ff in main_feat:
            if f in used:
                continue
            n = good_matches(pf, ff)
            if n > best_n:
                best_n, best_f = n, f
        if best_f:
            used.add(best_f)
            r["image"] = str(best_f)
        lines.append(f"[{best_n:>4}] {r['subject']} -> {best_f.name if best_f else 'NO MATCH'}")
        print(f"[{i}/{len(rows)}] {r['subject']} -> {best_f.name if best_f else 'NO MATCH'} ({best_n})")

    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=rows[0].keys())
        w.writeheader()
        w.writerows(rows)
    REPORT.write_text("\n".join(lines), encoding="utf-8")
    print(f"\nDone. Wrote {OUT} and {REPORT}")
    print("Next: python visual_match.py   (adds gallery images)")


if __name__ == "__main__":
    main()
