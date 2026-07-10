#!/usr/bin/env python3
"""
Match extracted brochure products to your REAL image files.

Scans your main-image folders and gallery folder, fuzzy-matches each product
(from products_clean.csv) to its files by filename, and writes:
  - products_matched.csv  (main image + gallery images filled in, fresh SKUs)
  - match_report.txt      (what matched what — review this!)

Products already on the store were removed earlier by clean_csv.py; SKUs are
regenerated with a -N suffix so they can't collide with trashed test products.

Usage:
    python match_images.py
    # review match_report.txt, then:
    # python publish.py --no-ai-images --limit 1 --csv products_matched.csv
"""

import csv
import re
from difflib import SequenceMatcher
from pathlib import Path

HERE = Path(__file__).parent
SRC = HERE / "products_clean.csv"
OUT = HERE / "products_matched.csv"
REPORT = HERE / "match_report.txt"

MAIN_DIRS = [
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far"),
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\New"),
]
GALLERY_DIR = Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\Gallary Pic")
IMG_EXT = {".jpg", ".jpeg", ".png", ".webp"}

STOPWORDS = {"the", "a", "an", "of", "in", "on", "with", "and", "art", "artwork",
             "canvas", "wall", "print", "painting", "lord", "goddess", "ji"}


def norm(s):
    return re.sub(r"[^a-z0-9]", "", s.lower())


def tokens(s):
    return {t for t in re.split(r"[^a-z0-9]+", s.lower()) if t and t not in STOPWORDS}


def list_images(folder, recursive=False):
    if not folder.exists():
        return []
    it = folder.rglob("*") if recursive else folder.iterdir()
    return [p for p in it if p.is_file() and p.suffix.lower() in IMG_EXT]


def score(subject, filename):
    """Similarity between a product name and a file name (0..1)."""
    st, ft = tokens(subject), tokens(filename)
    if not st or not ft:
        return 0.0
    overlap = len(st & ft) / len(st)
    seq = SequenceMatcher(None, norm(subject), norm(filename)).ratio()
    return 0.65 * overlap + 0.35 * seq


def best_match(subject, files, threshold=0.55):
    scored = sorted(((score(subject, f.stem), f) for f in files), reverse=True,
                    key=lambda x: x[0])
    if scored and scored[0][0] >= threshold:
        return scored[0]
    return (scored[0][0] if scored else 0.0), None


def gallery_matches(subject, files, threshold=0.5, cap=6):
    scored = sorted(((score(subject, f.stem), f) for f in files), reverse=True,
                    key=lambda x: x[0])
    return [f for s, f in scored[:cap] if s >= threshold]


def main():
    if not SRC.exists():
        raise SystemExit(f"{SRC} not found — run clean_csv.py first.")

    main_files = []
    seen = set()
    for d in MAIN_DIRS:
        for f in list_images(d):
            if f.resolve() not in seen:
                seen.add(f.resolve())
                main_files.append(f)
    gallery_files = list_images(GALLERY_DIR, recursive=True)

    print(f"Found {len(main_files)} main images, {len(gallery_files)} gallery images.")
    if not main_files:
        raise SystemExit("Main image folders not found or empty — check the paths in this script.")

    with open(SRC, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    used_mains = set()
    matched, unmatched, lines = [], [], []
    for i, r in enumerate(rows, 1):
        subject = r["subject"]
        avail = [f for f in main_files if f not in used_mains]
        s, main = best_match(subject, avail)
        gal = gallery_matches(subject, gallery_files)
        if main:
            used_mains.add(main)
            images = [str(main)] + [str(g) for g in gal]
            r["image"] = "|".join(images)
            r["sku"] = f"TAF-{re.sub(r'[^A-Z0-9]+', '-', subject.upper()).strip('-')[:18]}-N{i:03d}"
            matched.append(r)
            lines.append(f"[OK {s:.2f}] {subject}\n    main: {main.name}\n"
                         f"    gallery ({len(gal)}): {', '.join(g.name for g in gal) or '-'}")
        else:
            unmatched.append(r)
            lines.append(f"[NO MATCH {s:.2f}] {subject}  (kept brochure page as image)")

    cols = list(rows[0].keys())
    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        w.writerows(matched)

    REPORT.write_text(
        f"Matched {len(matched)} / {len(rows)} products "
        f"({len(unmatched)} unmatched)\n\n" + "\n".join(lines), encoding="utf-8")

    print(f"\nMatched {len(matched)} / {len(rows)} products; {len(unmatched)} unmatched.")
    print(f"Wrote {OUT}")
    print(f"Review {REPORT} (open with: start notepad.exe match_report.txt)")
    print("Then test: python publish.py --no-ai-images --limit 1 --csv products_matched.csv")


if __name__ == "__main__":
    main()
