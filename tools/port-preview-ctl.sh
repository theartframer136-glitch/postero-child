#!/usr/bin/env bash
# Puts the preview drop-in (tools/port-preview-dropin.php) in place as
# wp-content/db.php for one Preview Ports / Preview Look run, checks it, and
# takes it away again. Owner, 5 Oct: "I approve the db.php approach".
#
#   AF_PV=<secret> tools/port-preview-ctl.sh put
#   tools/port-preview-ctl.sh remove
#
# Needs SSH_PORT, SSH_DEST (user@host) and the key in ~/.ssh/id_ed25519.
#
# put:
#   - fills the run's secret and an end time (now + 45 min) into a copy and
#     checks it parses
#   - checks the home page answers this runner normally before anything is
#     put in place (a firewall or rate limit refusing the runner is then
#     told apart from the file), and says who answered when it does not
#   - refuses if a wp-content/db.php is there that is not ours: a real
#     database drop-in is never touched
#   - writes the copy next to it and moves it into place
#   - checks the home page as a visitor gets it (HTTP 200, no WordPress error
#     page) and that a request with the secret is answered as a preview;
#     if either fails it takes the file away at once and fails
# remove:
#   - deletes wp-content/db.php only if it is ours, and fails if ours is
#     still there afterwards
set -uo pipefail
SITE=https://theartframer.us
WP='~/websites/OPu0sKi4J/public_html'
F="$WP/wp-content/db.php"
MARK=AF-PORT-PREVIEW-DROPIN
TTL=${AF_PV_TTL:-2700}
UA='Mozilla/5.0 Chrome/124'

# Who answered, when a check fails: status, the server / cache / firewall
# headers and the start of the page's text (all public).
show_answer() {
  grep -iE '^(HTTP/|server:|x-litespeed|x-hcdn|x-turbo|x-cache|cf-|via:|x-sucuri|x-firewall|retry-after:|location:)' "$1" 2>/dev/null | tr -d '\r' | head -12 | sed 's/^/    /'
  printf '    page: %s\n' "$(sed -e 's/<[^>]*>/ /g' "$2" 2>/dev/null | tr -s ' \n\t' ' ' | head -c 300)"
}

ssh_run() {
  ssh -p "$SSH_PORT" -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=40 -i ~/.ssh/id_ed25519 "$SSH_DEST" "$@"
}

remove() {
  local i out rc
  for i in 1 2 3 4; do
    out=$(ssh_run "rm -f $F.af-new; if [ -e $F ]; then if grep -q $MARK $F; then rm -f $F; echo 'preview file removed'; else echo 'wp-content/db.php is not ours: left alone'; fi; else echo 'no preview file there'; fi; if [ -e $F ] && grep -q $MARK $F; then echo 'ours is STILL there'; exit 3; fi" 2>&1); rc=$?
    [ $rc -eq 0 ] && { echo "$out"; return 0; }
    echo "  attempt $i failed (exit $rc): $out"; sleep 15
  done
  return 1
}

put() {
  local until tmp i out rc code hdr
  case "${AF_PV:-}" in ''|*[!0-9a-f]*) echo "AF_PV (hex) is needed"; return 2;; esac
  until=$(( $(date +%s) + TTL ))
  tmp=$(mktemp)
  sed -e "s/__AF_PREVIEW_TOKEN__/$AF_PV/g" -e "s/__AF_PREVIEW_UNTIL__/$until/g" tools/port-preview-dropin.php > "$tmp"
  if ! grep -q "$MARK" "$tmp" || grep -q '__AF_PREVIEW_' "$tmp"; then echo "the filled copy is wrong"; rm -f "$tmp"; return 2; fi
  if command -v php >/dev/null && ! php -l "$tmp" >/dev/null; then echo "the filled copy does not parse"; rm -f "$tmp"; return 2; fi

  # Before anything is put in place, the site must answer this runner as it
  # answers a visitor; otherwise a refusal (a firewall, a rate limit) would
  # be blamed on the preview file.
  code=$(curl -sS -m 60 -A "$UA" -D /tmp/af-before.h -o /tmp/af-before.html -w '%{http_code}' "$SITE/?afr=$RANDOM$RANDOM")
  if [ "$code" != 200 ]; then
    echo "before anything was put in place the home page answered this runner HTTP $code: nothing touched"
    show_answer /tmp/af-before.h /tmp/af-before.html
    rm -f "$tmp"; return 1
  fi

  rc=1
  for i in 1 2 3; do
    out=$(ssh_run "if [ -e $F ] && ! grep -q $MARK $F; then echo 'a wp-content/db.php is there that is not ours: not touching it'; exit 3; fi; cat > $F.af-new && mv -f $F.af-new $F && grep -c $MARK $F" < "$tmp" 2>&1); rc=$?
    [ $rc -eq 0 ] && break
    echo "  attempt $i failed (exit $rc): $out"
    [ $rc -eq 3 ] && break
    sleep 15
  done
  rm -f "$tmp"
  [ $rc -eq 0 ] || { echo "could not put the preview file in place"; return 1; }

  code=$(curl -sS -m 60 -A "$UA" -D /tmp/af-visitor.h -o /tmp/af-visitor.html -w '%{http_code}' "$SITE/?afr=$RANDOM$RANDOM")
  if [ "$code" != 200 ] || grep -q 'There has been a critical error' /tmp/af-visitor.html; then
    echo "with the file in place the home page answered visitors HTTP $code: taking it away"
    show_answer /tmp/af-visitor.h /tmp/af-visitor.html
    remove; return 1
  fi
  hdr=$(curl -sS -m 90 -A "$UA" -D - -o /tmp/af-preview.html "$SITE/?afr=$RANDOM$RANDOM&af_pv=$AF_PV&af_skip=none" | tr -d '\r')
  if ! printf '%s\n' "$hdr" | grep -qi '^x-af-port-preview: on' && ! grep -q 'name="af-port-preview" content="none"' /tmp/af-preview.html; then
    echo "a request with the secret was not answered as a preview: taking the file away"
    remove; return 1
  fi
  echo "preview file in place until $(date -u -d "@$until" +%H:%M) UTC; the home page answers visitors normally and the secret turns the preview on"
}

case "${1:-}" in
  put) put ;;
  remove) remove ;;
  *) echo "usage: $0 put|remove"; exit 2 ;;
esac
