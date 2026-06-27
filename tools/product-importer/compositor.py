#!/usr/bin/env python3
"""
Image compositor for The Art Framer.

Turns ONE artwork image into a set of clean product-gallery images by
*compositing* (overlaying) — never regenerating — so your actual art is
preserved exactly. Produces framed mockups (black / oak / white) and a styled
room scene.

Standalone test:
    python compositor.py path/to/art.jpg
    # writes images into ./output/gallery/
"""

from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

# Frame colors (RGB)
FRAMES = {
    "black": (24, 24, 24),
    "oak": (156, 116, 62),
    "white": (244, 244, 244),
}
WALL = (231, 227, 220)
STUDIO_BG = (244, 242, 238)


def _drop_shadow(canvas_rgba, box, blur=22, alpha=105, offset=(0, 16)):
    """Soft drop shadow behind the rectangle `box` (x0, y0, x1, y1)."""
    shadow = Image.new("RGBA", canvas_rgba.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(shadow)
    x0, y0, x1, y1 = box
    d.rectangle([x0 + offset[0], y0 + offset[1], x1 + offset[0], y1 + offset[1]],
                fill=(0, 0, 0, alpha))
    shadow = shadow.filter(ImageFilter.GaussianBlur(blur))
    canvas_rgba.alpha_composite(shadow)


def frame_art(art, frame_color, mat_w=0):
    """Wrap the artwork in a solid frame (optional white mat)."""
    art = art.convert("RGB")
    w, h = art.size
    frame_w = max(16, int(min(w, h) * 0.05))
    inner = art
    if mat_w:
        m = Image.new("RGB", (w + 2 * mat_w, h + 2 * mat_w), (255, 255, 255))
        m.paste(art, (mat_w, mat_w))
        inner = m
    iw, ih = inner.size
    framed = Image.new("RGB", (iw + 2 * frame_w, ih + 2 * frame_w), frame_color)
    # subtle inner bevel line
    bevel = Image.new("RGB", (iw + frame_w, ih + frame_w),
                      tuple(min(255, c + 35) for c in frame_color))
    framed.paste(bevel, ((framed.width - bevel.width) // 2,
                         (framed.height - bevel.height) // 2))
    framed.paste(inner, (frame_w, frame_w))
    return framed


def on_studio_bg(framed, bg=STUDIO_BG, pad_ratio=0.22):
    """Center the framed art on a clean studio background with a shadow."""
    w, h = framed.size
    pad = int(max(w, h) * pad_ratio)
    canvas = Image.new("RGBA", (w + 2 * pad, h + 2 * pad), bg + (255,))
    x, y = (canvas.width - w) // 2, (canvas.height - h) // 2
    _drop_shadow(canvas, (x, y, x + w, y + h))
    canvas.paste(framed, (x, y))
    return canvas.convert("RGB")


def room_scene(framed, wall=WALL):
    """Place the framed art on a softly lit wall above a floor band."""
    W, H = 1500, 1500
    # vertical wall gradient (lighter at top)
    grad = Image.new("L", (1, H))
    for yy in range(H):
        grad.putpixel((0, yy), int(250 - 40 * yy / H))
    grad = grad.resize((W, H))
    canvas = Image.composite(Image.new("RGB", (W, H), (250, 248, 244)),
                             Image.new("RGB", (W, H), wall), grad).convert("RGBA")
    # floor band
    ImageDraw.Draw(canvas).rectangle([0, int(H * 0.80), W, H],
                                     fill=(213, 205, 193, 255))
    # scale framed art to ~42% of width
    target = int(W * 0.42)
    fr = framed.resize((target, int(framed.height * target / framed.width)))
    x, y = (W - fr.width) // 2, int(H * 0.24)
    _drop_shadow(canvas, (x, y, x + fr.width, y + fr.height),
                 blur=34, alpha=85, offset=(0, 26))
    canvas.paste(fr, (x, y))
    return canvas.convert("RGB")


def make_gallery(src_path, out_dir, base_name):
    """Generate the gallery image files from one artwork. Returns file paths."""
    out_dir = Path(out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    art = Image.open(src_path)
    outputs = []

    for name, color in FRAMES.items():
        img = on_studio_bg(frame_art(art, color, mat_w=int(min(art.size) * 0.04)))
        p = out_dir / f"{base_name}-{name}-frame.jpg"
        img.save(p, "JPEG", quality=90)
        outputs.append(str(p))

    room = room_scene(frame_art(art, FRAMES["black"]))
    pr = out_dir / f"{base_name}-room.jpg"
    room.save(pr, "JPEG", quality=90)
    outputs.append(str(pr))

    return outputs


if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        raise SystemExit("usage: python compositor.py path/to/art.jpg")
    files = make_gallery(sys.argv[1], Path(__file__).parent / "output" / "gallery",
                         base_name=Path(sys.argv[1]).stem)
    print("Wrote:")
    for f in files:
        print("  ", f)
