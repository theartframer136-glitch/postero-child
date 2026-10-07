#!/usr/bin/env python3
"""
AI image generation for product galleries.

Providers:
  - Gemini 2.5 Flash Image ("Nano Banana")  -> GEMINI_API_KEY
  - OpenAI GPT-Image                          -> OPENAI_API_KEY

Both take your REAL artwork and place it into framed / room scenes via image
EDITING (the prompts instruct the model to keep the artwork unchanged). AI can
still subtly alter art, so review before publishing on important products.

Get keys:
  - Gemini: https://aistudio.google.com/apikey
  - OpenAI: https://platform.openai.com/api-keys
"""

import base64
import os
from pathlib import Path

import requests

GEMINI_MODEL = "gemini-2.5-flash-image"
GEMINI_URL = (f"https://generativelanguage.googleapis.com/v1beta/models/"
              f"{GEMINI_MODEL}:generateContent")
OPENAI_EDIT_URL = "https://api.openai.com/v1/images/edits"

# (filename tag, prompt) — one gallery image per entry.
GALLERY_PROMPTS = [
    ("black-frame",
     "Place this exact artwork, completely unchanged, inside a slim black wooden "
     "picture frame with a thin white mat, hanging centered on a smooth light-grey "
     "wall. Soft natural daylight, straight-on product photo, photorealistic, high "
     "resolution. Do not modify, redraw, or restyle the artwork itself."),
    ("oak-frame",
     "Place this exact artwork, completely unchanged, inside a natural oak wood "
     "frame, hanging on a warm off-white wall, soft daylight, straight-on "
     "photorealistic product photo. Keep the artwork pixel-for-pixel identical."),
    ("room-scene",
     "Show this exact artwork in a thin black frame hanging on the wall above a "
     "modern wooden console table in a bright, cozy living room with a plant and "
     "warm lighting. Photorealistic interior lifestyle photo. Keep the artwork "
     "unchanged."),
]


def provider_from_env():
    if os.getenv("GEMINI_API_KEY"):
        return "gemini"
    if os.getenv("OPENAI_API_KEY"):
        return "openai"
    return None


def is_configured():
    return provider_from_env() is not None


def _gemini_edit(api_key, art_bytes, prompt):
    body = {"contents": [{"parts": [
        {"text": prompt},
        {"inline_data": {"mime_type": "image/jpeg",
                         "data": base64.b64encode(art_bytes).decode()}},
    ]}]}
    r = requests.post(GEMINI_URL,
                      headers={"x-goog-api-key": api_key, "Content-Type": "application/json"},
                      json=body, timeout=180)
    r.raise_for_status()
    data = r.json()
    parts = (data.get("candidates") or [{}])[0].get("content", {}).get("parts", [])
    for part in parts:
        inline = part.get("inline_data") or part.get("inlineData")
        if inline and inline.get("data"):
            return base64.b64decode(inline["data"])
    raise RuntimeError(f"Gemini returned no image: {str(data)[:200]}")


def _openai_edit(api_key, art_bytes, prompt):
    r = requests.post(
        OPENAI_EDIT_URL,
        headers={"Authorization": f"Bearer {api_key}"},
        files={"image": ("art.png", art_bytes, "image/png")},
        data={"model": "gpt-image-1", "prompt": prompt, "size": "1024x1024"},
        timeout=180,
    )
    r.raise_for_status()
    d = r.json()["data"][0]
    if d.get("b64_json"):
        return base64.b64decode(d["b64_json"])
    if d.get("url"):
        return requests.get(d["url"], timeout=120).content
    raise RuntimeError("OpenAI returned no image data")


def generate_gallery(art_bytes, out_dir, base_name, provider=None):
    """Generate gallery images from one artwork. Returns saved file paths."""
    provider = provider or provider_from_env()
    out_dir = Path(out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    outputs = []
    for tag, prompt in GALLERY_PROMPTS:
        try:
            if provider == "gemini":
                img = _gemini_edit(os.getenv("GEMINI_API_KEY"), art_bytes, prompt)
            else:
                img = _openai_edit(os.getenv("OPENAI_API_KEY"), art_bytes, prompt)
            p = out_dir / f"{base_name}-{tag}.png"
            p.write_bytes(img)
            outputs.append(str(p))
        except Exception as e:
            print(f"  [warn] AI image '{tag}' failed: {e}")
    return outputs
