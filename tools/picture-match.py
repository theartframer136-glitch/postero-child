#!/usr/bin/env python3
"""
Which pictures on the site show the same artwork as a Cloudflare R2 master?
Runs on the GitHub runner (.github/workflows/picture-match.yml), never on the
site: reads what tools/diag-picture-files.php gathered (index.tsv, masters/,
and the pictures at the paths index.tsv names) and matches each master against every picture with SIFT features and
a RANSAC homography, so a painting shown small inside a room photo still
counts. The score is the number of features that agree on one placement:
about 25 and up is the same artwork; unrelated pictures stay near 0-10.

Prints the best matches per master and, per master, one contact sheet as
@@IMG|label|base64 (the master, then its best matches).

Run: python3 tools/picture-match.py <folder> [top]
"""
import base64, csv, io, os, sys

import cv2
import numpy as np
from PIL import Image, ImageDraw, ImageFont

root = sys.argv[1]
TOP = int(sys.argv[2]) if len(sys.argv) > 2 else 12
SIDE = 900  # longest side the pictures are compared at

sift = cv2.SIFT_create(nfeatures=3000)
bf = cv2.BFMatcher(cv2.NORM_L2)


def load(path):
    img = cv2.imread(path, cv2.IMREAD_GRAYSCALE)
    if img is None:  # formats OpenCV cannot read (some webp/png): through Pillow
        try:
            img = np.array(Image.open(path).convert('L'))
        except Exception:
            return None
    h, w = img.shape[:2]
    s = SIDE / max(h, w)
    if s < 1:
        img = cv2.resize(img, (int(w * s), int(h * s)), interpolation=cv2.INTER_AREA)
    return img


def features(path):
    img = load(path)
    if img is None:
        return None
    kp, des = sift.detectAndCompute(img, None)
    if des is None or len(kp) < 8:
        return None
    return np.float32([k.pt for k in kp]), des


def score(a, b):
    """features agreeing on one placement of a inside b (0 when unrelated)"""
    if a is None or b is None:
        return 0
    m = bf.knnMatch(a[1], b[1], k=2)
    good = [p[0] for p in m if len(p) == 2 and p[0].distance < 0.75 * p[1].distance]
    if len(good) < 8:
        return 0
    src = a[0][[g.queryIdx for g in good]].reshape(-1, 1, 2)
    dst = b[0][[g.trainIdx for g in good]].reshape(-1, 1, 2)
    H, mask = cv2.findHomography(src, dst, cv2.RANSAC, 5.0)
    return int(mask.sum()) if mask is not None else 0


def sheet(cells, h=220):
    font = None
    for f in ('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/dejavu/DejaVuSans.ttf'):
        if os.path.exists(f):
            font = ImageFont.truetype(f, 12)
    font = font or ImageFont.load_default()
    ims = []
    for path, label in cells:
        try:
            im = Image.open(path).convert('RGB')
        except Exception:
            im = Image.new('RGB', (h, h), 'grey')
        w = max(1, int(im.width * h / im.height))
        ims.append((im.resize((w, h)), label))
    pad, top = 10, 34
    per_row = 5
    rows = [ims[i:i + per_row] for i in range(0, len(ims), per_row)]
    W = max(sum(i.width for i, _ in r) + pad * (len(r) + 1) for r in rows)
    out = Image.new('RGB', (W, (h + top + pad) * len(rows)), 'white')
    d = ImageDraw.Draw(out)
    y = 0
    for r in rows:
        x = pad
        for im, label in r:
            out.paste(im, (x, y + top))
            for k, line in enumerate(label.split('\n')[:2]):
                d.text((x, y + 4 + 14 * k), line[:34], fill='black', font=font)
            x += im.width + pad
        y += h + top + pad
    buf = io.BytesIO()
    out.save(buf, 'JPEG', quality=70)
    return base64.b64encode(buf.getvalue()).decode()


rows = []
with open(os.path.join(root, 'index.tsv'), newline='', encoding='utf-8') as f:
    for r in csv.reader(f, delimiter='\t'):
        if len(r) >= 7:
            rows.append(dict(idx=r[0], pid=r[1], status=r[2], role=r[3], att=r[4], code=r[5], title=r[6], file=os.path.join(root, r[0]) if '/' in r[0] else os.path.join(root, 'img', r[0])))
masters = sorted(os.listdir(os.path.join(root, 'masters')))
print(f'{len(rows)} pictures from the site, {len(masters)} masters')

mf = {m: features(os.path.join(root, 'masters', m)) for m in masters}
for i, a in enumerate(masters):
    for b in masters[i + 1:]:
        print(f'master {a} vs master {b}: {score(mf[a], mf[b])}')

results = {m: [] for m in masters}
unread = 0
for n, r in enumerate(rows):
    f = features(r['file'])
    if f is None:
        unread += 1
        continue
    for m in masters:
        results[m].append((score(mf[m], f), r))
    if n and n % 500 == 0:
        print(f'  ... {n} compared', flush=True)
print(f'{unread} pictures could not be read')

for m in masters:
    best = sorted(results[m], key=lambda t: -t[0])[:TOP]
    print(f'\n=== {m}: best matches (25 and up: same artwork)')
    for s, r in best:
        print(f'  {s:5d}  #{r["pid"]} {r["status"]:7s} {r["role"]:8s} att #{r["att"]:6s} {r["code"]:16s} {r["title"][:70]}')
    cells = [(os.path.join(root, 'masters', m), f'MASTER\n{m}')]
    seen = set()
    for s, r in best:
        if len(cells) >= 10:
            break
        if (r['pid'], r['att']) in seen:
            continue
        seen.add((r['pid'], r['att']))
        cells.append((r['file'], f'#{r["pid"]} {r["status"]} {r["role"]}\nscore {s}  {r["code"]}'))
    print(f'@@IMG|{m} and its best matches|{sheet(cells)}')
