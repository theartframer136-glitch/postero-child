#!/usr/bin/env python3
"""
Match gallery images to each product's MAIN image by VISUAL CONTENT.

The gallery folder isn't labeled, so instead of filenames we compare pictures:
for each product's main image, find the gallery images that contain the same
artwork (even framed or in a room scene) using OpenCV ORB feature matching.

Input:  products_matched.csv (must already have a main image per product)
Output: products_visual.csv  (main image + its matched gallery images)
        visual_report.txt     (what matched — review this)

Setup:  pip install opencv-python numpy
Usage:  python visual_match.py
        python visual_match.py --min-matches 18 --max-gallery 6
"""

import argparse
import csv
from pathlib import Path

import cv2
import numpy as np

HERE = Path(__file__).parent
SRC = HERE / "products_matched.csv"
OUT = HERE / "products_visual.csv"
REPORT = HERE / "visual_report.txt"
GALLERY_DIR = Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\Gallary Pic")
IMG_EXT = {".jpg", ".jpeg", ".png", ".webp"}

orb = cv2.ORB_create(nfeatures=1200)
bf = cv2.BFMatcher(cv2.NORM_HAMMING)
CACHE = HERE / "output" / "orb_cache"


def features(path, max_dim=900, center=0.6):
    import hashlib
    path = Path(path)
    try:
        mt = path.stat().st_mtime
    except OSError:
        return None
    CACHE.mkdir(parents=True, exist_ok=True)
    key = hashlib.md5(f"{path}|{mt}|{max_dim}|{center}".encode()).hexdigest()
    cf = CACHE / f"{key}.npy"
    if cf.exists():                       # cached -> instant
        d = np.load(cf, allow_pickle=False)
        return d if d.size else None

    data = np.fromfile(str(path), dtype=np.uint8)   # handles unicode paths on Windows
    img = cv2.imdecode(data, cv2.IMREAD_GRAYSCALE)
    if img is None:
        np.save(cf, np.array([], dtype=np.uint8))
        return None
    # Crop to the central region so we match the ARTWORK, not the surrounding
    # wall/frame/room that every mockup shares (avoids wrong-artwork matches).
    h, w = img.shape
    ch, cw = int(h * center), int(w * center)
    y0, x0 = (h - ch) // 2, (w - cw) // 2
    img = img[y0:y0 + ch, x0:x0 + cw]
    h, w = img.shape
    scale = max_dim / max(h, w)
    if scale < 1:
        img = cv2.resize(img, (int(w * scale), int(h * scale)))
    des = orb.detectAndCompute(img, None)[1]  # descriptors
    np.save(cf, des if des is not None else np.array([], dtype=np.uint8))
    return des


def good_matches(d1, d2):
    if d1 is None or d2 is None or len(d1) < 2 or len(d2) < 2:
        return 0
    good = 0
    for pair in bf.knnMatch(d1, d2, k=2):
        if len(pair) == 2 and pair[0].distance < 0.75 * pair[1].distance:
            good += 1
    return good


def list_images(folder):
    return [p for p in folder.rglob("*") if p.is_file() and p.suffix.lower() in IMG_EXT] \
        if folder.exists() else []


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--min-matches", type=int, default=18,
                    help="min good feature matches to count as the same artwork")
    ap.add_argument("--max-gallery", type=int, default=6)
    args = ap.parse_args()

    if not SRC.exists():
        raise SystemExit(f"{SRC} not found — run match_images.py first.")
    gallery = list_images(GALLERY_DIR)
    if not gallery:
        raise SystemExit(f"No gallery images in {GALLERY_DIR}")
    print(f"Indexing {len(gallery)} gallery images (one-time) ...")
    gfeat = {g: features(g) for g in gallery}

    with open(SRC, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.DictReader(f))

    used = set()
    lines = []
    for i, r in enumerate(rows, 1):
        imgs = [x for x in r.get("image", "").split("|") if x]
        main = imgs[0] if imgs else ""
        print(f"[{i}/{len(rows)}] {r['subject']}")
        if not main:
            lines.append(f"[SKIP] {r['subject']} — no main image")
            continue
        md = features(Path(main))
        scored = []
        for g, gd in gfeat.items():
            if g in used:
                continue
            n = good_matches(md, gd)
            if n >= args.min_matches:
                scored.append((n, g))
        scored.sort(reverse=True, key=lambda x: x[0])
        picks = [g for n, g in scored[: args.max_gallery]]
        for g in picks:
            used.add(g)
        r["image"] = "|".join([main] + [str(g) for g in picks])
        lines.append(f"{r['subject']}: main={Path(main).name} "
                     f"gallery={[g.name for g in picks] or '-'} "
                     f"(scores={[n for n, _ in scored[:args.max_gallery]]})")

    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=rows[0].keys())
        w.writeheader()
        w.writerows(rows)
    REPORT.write_text("\n".join(lines), encoding="utf-8")
    print(f"\nDone. Wrote {OUT} and {REPORT}")
    print("Review visual_report.txt, then: python publish.py --no-ai-images --limit 1 --csv products_visual.csv")


if __name__ == "__main__":
    main()
