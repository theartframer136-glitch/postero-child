#!/usr/bin/env python3
"""
Image-FIRST product builder — the reliable pipeline.

The old flow named a product from the brochure and then GUESSED which photo
matched that name (visual_main.py). When two artworks looked alike (a haloed
figure), the guess was wrong and the name landed on the wrong picture
("Surya Dev" on a Jesus image). This script removes that guess entirely:

  1. Start from the MAIN image file itself (the artwork you actually have).
  2. Read the picture with vision -> the NAME and CATEGORY come FROM the image,
     so the name can never disagree with the picture.
  3. Skip images already on the store (visual ORB match, not names).
  4. Attach gallery photos whose WALL shows the SAME artwork as this main
     image (visual ORB match, strict threshold).

Output: products_named.csv  (ready for publish.py)
        build_from_images_report.txt

Setup:  pip install opencv-python numpy requests pillow python-dotenv
        GROQ_API_KEY (free) in .env   (same key the brochure reader uses)

Run the ONE-product test first:
    python build_from_images.py --limit 1
    python publish.py --no-ai-images --limit 1 --csv products_named.csv --fresh-skus
Then the full batch (drafts):
    python build_from_images.py
"""

import argparse
import csv
import json
import os
import re
import time
from pathlib import Path

from dotenv import load_dotenv
from PIL import Image

# Your canvases are legit print-resolution art (4ft @ 300 DPI ~ 187M pixels), which
# trips Pillow's "decompression bomb" guard. These are your own trusted files, so
# lift the limit — otherwise vision naming fails and products get filename titles.
Image.MAX_IMAGE_PIXELS = None

from visual_match import features, good_matches, list_images
from extract_brochure import _encode_image, _post_with_retry, GROQ_URL, GROQ_MODEL

HERE = Path(__file__).parent
OUT = HERE / "products_named.csv"
REPORT = HERE / "build_from_images_report.txt"
RAW = HERE / "output" / "products_raw.json"       # existing store products
STORE_CACHE = HERE / "output" / "store_images"
STATE = HERE / "output" / "build_state.json"        # per-image results cache

MAIN_DIRS = [
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far"),
    Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\New"),
]
GALLERY_DIR = Path(r"C:\Users\user\SynologyDrive\Final Edited Photos so far\Gallary Pic")
IMG_EXT = {".jpg", ".jpeg", ".png", ".webp"}

# Categories that already exist on the store — vision must pick from THIS list.
CATEGORIES = [
    "Radha Krishna", "Buddha", "Sikh Art", "Christian Art", "Hindu Deities",
    "Seven Horses", "Wildlife", "Kids Room Decor", "Indian Culture",
    "Landscapes", "Still Life", "Abstract Art",
]
DEFAULT_SIZES = ["3x4 Feet", "4x5 Feet", "4x6 Feet"]

NAME_PROMPT = (
    "You are looking at ONE canvas wall-art product photo (the artwork may be "
    "shown on its own or hung on a wall in a room). Identify the ARTWORK, not "
    "the room. Return a JSON object exactly like:\n"
    '{"name": "<short product title, 2-5 words, Title Case>", '
    '"category": "<ONE of: ' + ", ".join(CATEGORIES) + '>", '
    '"subject": "<one plain sentence describing the artwork>"}\n'
    "Pick the closest category from that list. If the artwork is a Hindu god or "
    "goddess use 'Hindu Deities'; a Christian figure -> 'Christian Art'; a Sikh "
    "shrine/guru -> 'Sikh Art'. Do not invent categories outside the list."
)


def name_image(api_key, img_path):
    b64 = _encode_image(img_path)
    body = {
        "model": GROQ_MODEL,
        "temperature": 0,
        "response_format": {"type": "json_object"},
        "messages": [{"role": "user", "content": [
            {"type": "text", "text": NAME_PROMPT},
            {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{b64}"}},
        ]}],
    }
    data = _post_with_retry(GROQ_URL, {"Authorization": f"Bearer {api_key}"}, body)
    txt = data["choices"][0]["message"]["content"]
    try:
        obj = json.loads(txt)
    except json.JSONDecodeError:
        m = re.search(r"\{.*\}", txt, re.S)
        obj = json.loads(m.group(0)) if m else {}
    return obj if isinstance(obj, dict) else {}


def list_main_images():
    files, seen = [], set()
    for d in MAIN_DIRS:
        if not d.exists():
            print(f"  [warn] main folder not found: {d}")
            continue
        for f in d.iterdir():
            if f.is_file() and f.suffix.lower() in IMG_EXT and f.resolve() not in seen:
                seen.add(f.resolve())
                files.append(f)
    return files


def load_store_features():
    """One center-cropped ORB descriptor per existing store product (featured img)."""
    if not RAW.exists():
        print(f"  [warn] {RAW} not found — store dedupe skipped (nothing filtered).")
        return []
    import requests
    STORE_CACHE.mkdir(parents=True, exist_ok=True)
    feats = []
    for p in json.loads(RAW.read_text(encoding="utf-8")):
        ims = p.get("images") or []
        if not ims:
            continue
        url = ims[0].get("src")
        if not url:
            continue
        dest = STORE_CACHE / f"{p.get('id')}_0{Path(url).suffix or '.jpg'}"
        if not dest.exists():
            try:
                r = requests.get(url, timeout=60)
                r.raise_for_status()
                dest.write_bytes(r.content)
            except Exception as e:
                print(f"  [warn] fetch {url}: {e}")
                continue
        d = features(dest)
        if d is not None:
            feats.append((p.get("name", ""), d))
    return feats


def load_state():
    if STATE.exists():
        return json.loads(STATE.read_text(encoding="utf-8"))
    return {}


def save_state(st):
    STATE.parent.mkdir(parents=True, exist_ok=True)
    STATE.write_text(json.dumps(st), encoding="utf-8")


# Filename signals that a file is an internal/working/duplicate copy, NOT a
# finished product. Approved files (even tagged "[KJ-Approved]") are KEPT.
WORKING_SIGNALS = [
    "unapprov", "not approved", "not really needed", "please ", "to be removed",
    "to be decided", "keep it aside", "let_s keep", "let's keep",
    "do not consider", "redownload", "recovered", "deleted", "copy of",
    "- copy", "too deep", "too much", "cannot manage",
    "i think this has to be", "second right hand",
]


def is_working_file(path):
    n = Path(path).name.lower()
    return any(sig in n for sig in WORKING_SIGNALS)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0, help="build only N products (test)")
    ap.add_argument("--skip-working", action="store_true",
                    help="skip internal/unapproved/duplicate working files (recommended)")
    ap.add_argument("--store-min", type=int, default=22,
                    help="ORB score to treat a main image as ALREADY on the store")
    ap.add_argument("--gallery-min", type=int, default=20,
                    help="ORB score for a gallery photo to count as the SAME artwork")
    ap.add_argument("--max-gallery", type=int, default=6)
    ap.add_argument("--min-gallery", type=int, default=0,
                    help="only keep products that have at least N matching wall photos")
    ap.add_argument("--delay", type=float, default=3.0, help="seconds between vision calls")
    ap.add_argument("--append-only", action="store_true",
                    help="keep existing CSV rows frozen; only append new products "
                         "(safe --skip counting for already-published rows)")
    ap.add_argument("--main-dir", action="append", default=None, metavar="PATH",
                    help="source folder of main product images (repeatable); "
                         "overrides the built-in canvas-art folders")
    ap.add_argument("--gallery-dir", default=None, metavar="PATH",
                    help="folder of wall/room photos to match against (optional)")
    ap.add_argument("--force-category", default=None, metavar="NAME",
                    help="put EVERY product in this one store category "
                         "(e.g. \"Gold Foiled & UV\"); skips vision category guessing")
    ap.add_argument("--no-gallery", action="store_true",
                    help="do not match any wall/room photos (for catalogs that "
                         "have none — pair with --gallery-fill at publish)")
    ap.add_argument("--out", default=None, metavar="CSV",
                    help="output CSV path (use a separate file per catalog)")
    ap.add_argument("--state", default=None, metavar="JSON",
                    help="per-image cache path (use a separate file per catalog)")
    args = ap.parse_args()

    # Per-catalog overrides. A distinct --state and --out keep this catalog's
    # cache and CSV from colliding with the canvas-art run.
    global MAIN_DIRS, GALLERY_DIR, OUT, STATE, REPORT
    if args.main_dir:
        MAIN_DIRS = [Path(p) for p in args.main_dir]
    if args.gallery_dir:
        GALLERY_DIR = Path(args.gallery_dir)
    elif args.main_dir:
        # A custom catalog with no gallery given must NOT borrow the canvas
        # gallery — that would attach wrong wall photos.
        args.no_gallery = True
    if args.state:
        STATE = Path(args.state)
    if args.out:
        OUT = Path(args.out)
        REPORT = OUT.with_name(OUT.stem + "_report.txt")

    load_dotenv(HERE / ".env")
    groq = os.getenv("GROQ_API_KEY")
    if not groq:
        raise SystemExit("Set GROQ_API_KEY (free) in .env first.")

    mains = list_main_images()
    if not mains:
        raise SystemExit("No main images found — check MAIN_DIRS paths.")
    print(f"{len(mains)} main images found.")
    if args.skip_working:
        before = len(mains)
        mains = [m for m in mains if not is_working_file(m)]
        print(f"  {before - len(mains)} internal/working/duplicate files skipped, "
              f"{len(mains)} kept.")

    # Per-image RESULTS cache. Once an image is analysed (store check + vision
    # name + gallery match scores), the result is saved keyed by path+size and
    # NEVER recomputed. A re-run reuses it instantly; heavy store/gallery
    # indexing below only happens for images not yet in this cache.
    cache = load_state()

    def ckey(p):
        try:
            return f"{p.resolve()}|{p.stat().st_size}"
        except OSError:
            return str(p.resolve())

    pending = [mp for mp in mains if ckey(mp) not in cache]
    if args.limit:                      # for a quick test, only analyse as many
        pending = pending[: max(args.limit * 3, args.limit)]  # new ones as needed
    if pending:
        print(f"{len(pending)} new image(s) to analyse (rest served from cache).")
        print("Loading existing store images (for visual dedupe) ...")
        store_feat = load_store_features()
        print(f"  comparing against {len(store_feat)} live products.")
        if args.no_gallery:
            print("Gallery matching disabled for this catalog "
                  "(use --gallery-fill at publish for composited mockups).\n")
            gallery, gfeat = [], {}
        else:
            print("Indexing gallery folder ...")
            gallery = list_images(GALLERY_DIR)
            gfeat = {g: features(g) for g in gallery}
            print(f"  {len(gallery)} gallery photos.\n")
    else:
        print("All images already analysed — using cache, nothing re-read.\n")
        store_feat, gfeat = [], {}

    # Analyse only the not-yet-cached images (this is the one-time heavy work).
    for j, mp in enumerate(pending, 1):
        md = features(mp)
        best_s, best_name = 0, ""
        for name, sf in store_feat:
            n = good_matches(md, sf)
            if n > best_s:
                best_s, best_name = n, name
        try:
            info = name_image(groq, mp)
            vision_ok = True
        except Exception as e:
            print(f"  [{j}/{len(pending)}] {mp.name} -> vision error: {e}")
            info = {"name": mp.stem, "category": "", "subject": ""}
            vision_ok = False
        else:
            time.sleep(args.delay)
        # keep ALL gallery scores >= 8 so thresholds can change later WITHOUT
        # re-matching (re-thresholding a cached list is free).
        gscores = []
        for g, gd in gfeat.items():
            n = good_matches(md, gd)
            if n >= 8:
                gscores.append([n, str(g)])
        gscores.sort(reverse=True, key=lambda x: x[0])
        cache[ckey(mp)] = {
            "main": str(mp),
            "name": (info.get("name") or "").strip() or mp.stem,
            "category": (info.get("category") or "").strip(),
            "subject": (info.get("subject") or "").strip(),
            "store_score": best_s, "store_name": best_name,
            "gscores": gscores, "named": vision_ok,
        }
        save_state(cache)               # crash-safe after every image
        print(f"  [{j}/{len(pending)}] {mp.name} -> \"{cache[ckey(mp)]['name']}\" "
              f"(store {best_s}, {len(gscores)} gallery cand.)")

    def looks_like_filename(nm, mp):
        return (not nm or nm == Path(mp).stem
                or bool(re.search(r"(\d\s*[xX]\s*\d|@\d|^\d+$|^\[)", nm)))

    # SELECT the winners first (store + gallery filters use only cached scores —
    # no names needed). Only these actually become products.
    winners, used_gallery, lines = [], set(), []
    for mp in mains:
        if args.limit and len(winners) >= args.limit:
            break
        res = cache.get(ckey(mp))
        if not res:
            continue
        if res["store_score"] >= args.store_min:
            lines.append(f"[ON STORE {res['store_score']}] {mp.name} ~ {res['store_name']}")
            continue
        picks = []
        for n, gpath in res["gscores"]:
            if n < args.gallery_min:
                break
            if gpath in used_gallery:
                continue
            picks.append(gpath)
            if len(picks) >= args.max_gallery:
                break
        if len(picks) < args.min_gallery:
            lines.append(f"[NO-GALLERY] {res.get('name', mp.name)} main={mp.name} "
                         f"({len(picks)} wall matches < {args.min_gallery})")
            continue
        used_gallery.update(picks)
        winners.append((mp, picks))

    # Re-name ONLY the winners whose name still looks like a filename. This is a
    # handful of calls, not hundreds — so it won't exhaust the free vision quota.
    to_rename = [mp for mp, _ in winners
                 if not cache[ckey(mp)].get("named", True)
                 or looks_like_filename(cache[ckey(mp)].get("name", ""), mp)]
    if to_rename:
        print(f"\nRe-naming {len(to_rename)} product image(s) ...")
        fails = 0
        for mp in to_rename:
            try:
                info = name_image(groq, mp)
            except Exception as e:
                fails += 1
                print(f"  {mp.name} -> {e}")
                if fails >= 3:      # quota clearly spent — stop, don't grind for hours
                    print("  Vision quota looks exhausted. Stopping re-naming; the "
                          "products keep their current names. Re-run this command "
                          "later (after the quota resets) to finish the names.")
                    break
                continue
            fails = 0
            res = cache[ckey(mp)]
            res["name"] = (info.get("name") or "").strip() or mp.stem
            res["category"] = (info.get("category") or "").strip()
            res["subject"] = (info.get("subject") or "").strip()
            res["named"] = True
            save_state(cache)
            print(f"  {mp.name} -> \"{res['name']}\"")
            time.sleep(args.delay)

    # Build the CSV rows from the winners. Winners still carrying a filename-style
    # name (vision quota ran out) are HELD BACK — they'll be appended on a later
    # run once named, so published products never get filename titles.
    rows, held = [], 0
    for i, (mp, picks) in enumerate(winners, 1):
        res = cache[ckey(mp)]
        name = res["name"]
        if looks_like_filename(name, mp):
            held += 1
            continue
        if args.force_category:
            cats = args.force_category
        else:
            cat = res["category"] if res["category"] in CATEGORIES else ""
            cats = "Digital Canvas Prints" + (f"|{cat}" if cat else "")
        slug = re.sub(r"[^A-Z0-9]+", "-", name.upper()).strip("-")[:20]
        rows.append({
            "subject": name,
            "size": DEFAULT_SIZES[0],
            "sizes": "|".join(DEFAULT_SIZES),
            "style": "Premium Digital Canvas Print",
            "use_case": "Living Room & Home Spiritual Wall Décor",
            "categories": cats,
            "tags": "",
            "focus_keyword": f"{name} canvas wall art".lower(),
            "price": "",
            "image": "|".join([str(mp)] + picks),
            "sku": f"TAF-{slug}-{i:03d}",
        })
        lines.append(f"[NEW] {name} ({cat or 'no-cat'}) main={mp.name} "
                     f"gallery={[Path(g).name for g in picks] or ['-']}")

    # --append-only: keep the existing CSV rows EXACTLY as they are (so --skip
    # row counting stays valid for already-published products) and add only
    # products whose main image isn't in the CSV yet.
    if args.append_only and OUT.exists():
        with open(OUT, newline="", encoding="utf-8-sig") as f:
            old = list(csv.DictReader(f))
        old_mains = {r["image"].split("|")[0] for r in old}
        added = [r for r in rows if r["image"].split("|")[0] not in old_mains]
        rows = old + added
        print(f"append-only: kept {len(old)} existing rows, added {len(added)} new.")

    cols = ["subject", "size", "sizes", "style", "use_case", "categories",
            "tags", "focus_keyword", "price", "image", "sku"]
    with open(OUT, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        w.writerows(rows)
    REPORT.write_text("\n".join(lines), encoding="utf-8")

    print(f"\nDone. {len(rows)} products written to {OUT}")
    if held:
        print(f"({held} winners still un-named — held back for now; re-run this "
              f"command later with --delay 25 to name them, they'll be appended.)")
    print(f"Review {REPORT} — every name is derived from its own picture.")
    print("Test ONE:")
    print("  python publish.py --no-ai-images --limit 1 --csv products_named.csv --fresh-skus")


if __name__ == "__main__":
    main()
