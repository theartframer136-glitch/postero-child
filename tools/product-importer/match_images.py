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


def leading_num(stem):
    m = re.match(r"(\d+)", stem.strip())
    return m.group(1) if m else None


def build_num_index(files):
    """Map a leading page number -> list of files (e.g. '103' -> [103.jpg, 103-1.jpg])."""
    idx = {}
    for f in files:
        n = leading_num(f.stem)
        if n:
            idx.setdefault(n, []).append(f)
    for n in idx:
        idx[n].sort(key=lambda p: p.stem)
    return idx


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


def gallery_for(main_file, subject, files, threshold=0.45, cap=8):
    """Gallery images that belong to this product — matched primarily against the
    MAIN image's filename (user: 'according to the main image'), then the product
    name, and boosted when the file sits in a folder named like the product/main."""
    key = main_file.stem if main_file else subject
    scored = []
    for f in files:
        if main_file and f.resolve() == main_file.resolve():
            continue  # don't repeat the main image
        s = max(score(key, f.stem), score(subject, f.stem))
        parent = norm(f.parent.name)
        if parent and (parent in norm(key) or norm(key) in parent
                       or parent in norm(subject) or norm(subject) in parent):
            s = max(s, 0.9)  # same sub-folder as the product
        scored.append((s, f))
    scored.sort(reverse=True, key=lambda x: x[0])
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
    gallery_by_num = build_num_index(gallery_files)   # '103' -> [103.jpg, ...]
    main_by_num = build_num_index(main_files)          # if main images are numbered too

    print(f"Found {len(main_files)} main images, {len(gallery_files)} gallery images.")
    if not main_files:
        raise SystemExit("Main image folders not found or empty — check the paths in this script.")

    with open(SRC, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    used_mains = set()
    matched, unmatched, lines = [], [], []
    for i, r in enumerate(rows, 1):
        subject = r["subject"]
        page = leading_num(Path(r.get("image", "")).stem)  # brochure page number

        # Gallery = same-numbered file(s) in Gallary Pic (reliable page-number link).
        gal = list(gallery_by_num.get(page, [])) if page else []

        # Main image: prefer same page number; else fall back to name matching.
        main, s = None, 0.0
        if page and main_by_num.get(page):
            main = main_by_num[page][0]
            s = 1.0
        else:
            avail = [f for f in main_files if f not in used_mains]
            s, main = best_match(subject, avail)

        if main or gal:
            if main:
                used_mains.add(main)
            first = [str(main)] if main else []
            images = first + [str(g) for g in gal if g != main]
            r["image"] = "|".join(images)
            r["sku"] = f"TAF-{re.sub(r'[^A-Z0-9]+', '-', subject.upper()).strip('-')[:18]}-N{i:03d}"
            matched.append(r)
            lines.append(f"[OK {s:.2f}] {subject}  (page {page})\n"
                         f"    main: {main.name if main else '-'}\n"
                         f"    gallery ({len(gal)}): {', '.join(g.name for g in gal) or '-'}")
        else:
            unmatched.append(r)
            lines.append(f"[NO MATCH] {subject}  (page {page})")

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
