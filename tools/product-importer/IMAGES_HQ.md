# High-quality product images — two options

The pipeline picks the best available image source automatically, in this order:
1. **Dynamic Mockups API** (photoreal mockups) — if configured.
2. **AI image generation** (Nano Banana / OpenAI) — if a key is set.
3. **Real frame templates** — if you added template PNGs.
4. **Built-in drawn compositor** — the free default.

You don't change any commands; you just enable one of the options below.

---

## Option 1 — Dynamic Mockups API (photorealistic, paid)

Composites your REAL artwork into professional photographed frame/room mockups.

1. Sign up at https://dynamicmockups.com and get an **API key**.
2. In their app, pick or build the mockups you want (frames, rooms).
3. Put the key in `.env`:
   ```
   DYNAMIC_MOCKUPS_API_KEY=your_key_here
   ```
4. Find your mockup UUIDs:
   ```powershell
   python mockups_api.py list
   ```
5. Copy the config and fill in the UUIDs from step 4:
   ```powershell
   copy mockups.json.example mockups.json
   notepad mockups.json
   ```
   Each entry needs a `mockup_uuid` and its `smart_object_uuid`.

That's it — next time you add a product with a gallery, it uses photoreal renders
(hosted by Dynamic Mockups) instead of the drawn frames.

---

## Option 1b — AI image generation (Nano Banana / ChatGPT)

Places your real artwork into framed/room scenes using an AI image model.

1. Get a key:
   - **Nano Banana** (Gemini 2.5 Flash Image): https://aistudio.google.com/apikey
   - **OpenAI** GPT-Image: https://platform.openai.com/api-keys
2. Put ONE of them in `.env`:
   ```
   GEMINI_API_KEY=your_key      # or
   OPENAI_API_KEY=your_key
   ```

Next product with a gallery uses AI-generated images (3 per product: black frame,
oak frame, room scene). Prompts instruct the model to keep the artwork unchanged,
but AI can subtly alter art — review important products before publishing.

## Option 2 — Free real-frame templates

Zero cost, much more realistic than the drawn frames.

1. Get 3–5 **frame mockup PNGs** with a **transparent centre window** (the empty
   area where the art shows through). Free sources: search "free frame mockup png
   transparent", or export from Canva/Photoshop.
2. Drop them into:
   ```
   tools/product-importer/templates/frames/
   ```
   (create the folder if needed). Name them meaningfully, e.g. `oak.png`,
   `black.png`, `gold.png`.

The compositor auto-detects the transparent window and fits your art into each
frame — no coordinates needed. If the folder is empty, it uses the drawn frames.

---

## Which to use
- Want the best quality and don't mind paying → **Option 1**.
- Want a big quality jump for free → **Option 2** (just add good template PNGs).
- Want zero setup → do nothing; the drawn compositor still works.
