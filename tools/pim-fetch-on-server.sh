#!/bin/bash
# Fetch the Products In Motion reels onto the site, from the site.
#
# WHY HERE AND NOT ANYWHERE ELSE
# YouTube refuses video downloads from datacenter addresses. Measured three
# times: twice from GitHub Actions runners, once from the agent container. The
# one address never tried is this one - the web host itself. Shared hosting
# does not look like CI to YouTube, so it may simply be served. If it is, the
# whole thing is automatic: the site fetches its own reels on deploy and the
# owner does nothing at all.
#
# If YouTube refuses here too, this exits cleanly saying so and the row keeps
# what it shows now: drifting poster stills, with no title, no buttons and no
# spinner. Not an embed - an embed cannot meet that spec, because a paused or
# throttled YouTube player draws its own centre buttons whatever page CSS
# says. Nothing here can break a deploy: every failure is per-video and the
# script always exits 0.
#
# Run on the server:  bash wp-content/themes/postero-child/tools/pim-fetch-on-server.sh

set -u
WEB="$(cd "$(dirname "$0")/../../../.." && pwd)"     # .../public_html
DEST="$WEB/wp-content/uploads/pim"
BIN="$HOME/.af-bin"
YTDLP="$BIN/yt-dlp"
MAX_PER_RUN="${MAX_PER_RUN:-16}"

# Everything this prints also goes to a file the LAST deploy step prints back.
# This step runs early, and a full deploy's log is long enough that the Actions
# API only ever returns the tail - so three separate times now a step has
# failed in a way I could not see, and I reasoned about it instead of reading
# it. The log lands where it can be read.
mkdir -p "$DEST" "$BIN" 2>/dev/null
LOG="$DEST/last-fetch.log"
# Not `tee` through process substitution: that races the script's own exit and
# can truncate the very last lines - which are the ones that say what happened.
# Redirect everything to the file, and copy the finished file to the real
# stdout on exit, however the script leaves.
exec 3>&1
exec > "$LOG" 2>&1
trap 'cat "$LOG" >&3 2>/dev/null' EXIT

# Find a PHP binary. Not `wp` (not on PATH in a child shell) and not the
# site's own URL over curl (the host will not connect back to itself - a
# 60-second timeout with zero bytes, measured). PHP loading WordPress
# in-process needs neither.
PHP=""
for c in php php8.3 php8.2 php8.1 /usr/bin/php /usr/local/bin/php /opt/alt/php83/usr/bin/php; do
    if command -v "$c" >/dev/null 2>&1 && "$c" -r 'exit(0);' >/dev/null 2>&1; then PHP="$c"; break; fi
done
echo "  php   : ${PHP:-NOT FOUND}"
if [ -z "$PHP" ]; then
    echo "  no PHP binary - cannot read the row's ids. Nothing fetched."
    echo "=== DONE ==="
    exit 0
fi

# -- which reels still need a local copy -----------------------------------
IDS_SCRIPT="$WEB/wp-content/themes/postero-child/tools/pim-ids-cli.php"
RAW="$("$PHP" "$IDS_SCRIPT" missing 2>&1)"
RC=$?
echo "  ids script exit: $RC"
printf '%s\n' "$RAW" | head -3 | sed 's/^/    | /'
# An empty list means "nothing missing"; a NON-ZERO exit means the question
# was never answered. The first version could not tell those apart and
# reported "every reel is already local" after a curl timeout - a failure
# dressed as success, which is the whole reason this took another deploy.
if [ "$RC" -ne 0 ]; then
    echo "  could not read the row's ids - fetching nothing this run."
    echo "=== DONE ==="
    exit 0
fi
IDS="$(printf '%s\n' "$RAW" | tr -d '\r' | grep -E '^[A-Za-z0-9_-]{11}$' || true)"
TOTAL=$(printf '%s\n' "$IDS" | grep -c . || true)
echo "  reels still needed: $TOTAL"
[ "$TOTAL" -gt 0 ] || { echo "  nothing missing - every reel is already local."; echo "=== DONE ==="; exit 0; }

# The endpoint already excluded anything local, but a file can appear between
# its answer and this line, so check the disk too rather than re-downloading.
MISSING=""
for id in $IDS; do
    [ -s "$DEST/$id.mp4" ] || MISSING="$MISSING $id"
done
NMISS=$(printf '%s\n' $MISSING | tr ' ' '\n' | grep -c . || true)
echo "  to fetch this run : $NMISS"
[ "$NMISS" -gt 0 ] || { echo "  all present on disk already."; echo "=== DONE ==="; exit 0; }

# -- the downloader -------------------------------------------------------
# yt-dlp_linux is a self-contained build: no python, no pip, no packages to
# install on shared hosting. Refreshed when older than a week, because
# YouTube changes its player often and a stale copy simply stops working.
NEED_DL=1
if [ -x "$YTDLP" ]; then
    if [ -z "$(find "$YTDLP" -mtime +7 2>/dev/null)" ]; then NEED_DL=0; fi
fi
if [ "$NEED_DL" = "1" ]; then
    echo "  fetching yt-dlp..."
    curl -sSL --retry 2 --max-time 180 \
        -o "$YTDLP.new" \
        https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux 2>/dev/null \
        && mv -f "$YTDLP.new" "$YTDLP" && chmod +x "$YTDLP" 2>/dev/null
fi
if ! "$YTDLP" --version >/dev/null 2>&1; then
    echo "  yt-dlp will not run here (shared hosting may forbid it)."
    echo "  The row keeps its drifting posters. Nothing is broken."
    echo "=== DONE ==="
    exit 0
fi
echo "  yt-dlp $("$YTDLP" --version 2>/dev/null)"

# -- fetch ----------------------------------------------------------------
# 720p is plenty for a 290px-wide card and keeps each file a few MB. The
# merge branch comes first; the progressive stream is only ~360p and would
# otherwise always win.
FMT='bv*[ext=mp4][height<=720]+ba[ext=m4a]/b[ext=mp4][height<=720]/b[height<=720]/b'
GOT=0; FAIL=0; TRIED=0
for id in $MISSING; do
    [ "$TRIED" -ge "$MAX_PER_RUN" ] && break
    TRIED=$((TRIED + 1))
    if "$YTDLP" --quiet --no-warnings --no-playlist \
         -f "$FMT" --merge-output-format mp4 --max-filesize 40M \
         --socket-timeout 30 --retries 2 \
         -o "$DEST/$id.mp4" "https://www.youtube.com/watch?v=$id" >/dev/null 2>&1 \
       && [ -s "$DEST/$id.mp4" ]; then
        GOT=$((GOT + 1))
        echo "    ok    $id.mp4  ($(du -h "$DEST/$id.mp4" 2>/dev/null | cut -f1))"
    else
        FAIL=$((FAIL + 1))
        rm -f "$DEST/$id.mp4" "$DEST/$id.mp4.part" 2>/dev/null
        echo "    no    $id"
    fi
done

echo "  fetched $GOT, failed $FAIL, of $TRIED attempted"
if [ "$GOT" = "0" ] && [ "$FAIL" -gt 0 ]; then
    echo "  YouTube refused this address too - all three routes now measured."
    echo "  The row keeps its drifting posters, which need nothing from anyone."
fi
chmod 644 "$DEST"/*.mp4 2>/dev/null
echo "=== DONE ==="
exit 0
