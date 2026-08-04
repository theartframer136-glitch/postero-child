#!/bin/bash
# Read-only CPU diagnostic — streamed to the host over SSH stdin by
# .github/workflows/diagnose-cpu.yml. Gathers evidence for the sustained
# high-CPU condition: what is running, what is hitting PHP, and what the
# schedulers are doing. Writes NOTHING except /tmp scratch on the host.
set -u
cd ~/websites/OPu0sKi4J/public_html 2>/dev/null || { echo "AF-DIAG-FAIL: no docroot"; exit 1; }

echo "=== HOST / LOAD ==="
date -u
uptime 2>/dev/null
echo "cores: $(nproc 2>/dev/null || echo '?')"

echo
echo "=== PROCESSES (this account, by CPU) ==="
ps -eo pid,pcpu,pmem,etime,cmd --sort=-pcpu 2>/dev/null | head -30

echo
echo "=== PROCESS COUNTS ==="
ps -eo cmd 2>/dev/null | awk '{print $1}' | sort | uniq -c | sort -rn | head -15

echo
echo "=== CRONTAB ==="
crontab -l 2>&1 | head -30

echo
echo "=== WP-CONFIG: cron + crawl-guard hook ==="
grep -n -i "CRON\|af-crawl-guard\|WP_CACHE" wp-config.php 2>/dev/null | head -12

echo
echo "=== CRAWL GUARD DEPLOYED? ==="
ls -la wp-content/af-crawl-guard.php wp-content/mu-plugins/ 2>&1 | head -10

echo
echo "=== LOG CANDIDATES ==="
ls -la ~/logs 2>/dev/null | head -12
ls -la ~/websites/OPu0sKi4J/logs 2>/dev/null | head -12
find ~ -maxdepth 4 \( -iname "*access*log*" -o -iname "*.log" \) -mmin -2880 -size +10k 2>/dev/null | grep -v -i "public_html\|error" | head -12

echo
echo "=== ACCESS LOG ANALYSIS (most recent, last 200k lines) ==="
AL=$(find ~ -maxdepth 5 -iname "*access*" -mmin -1440 -size +10k 2>/dev/null | grep -v -i "public_html\|error" | head -1)
if [ -z "$AL" ]; then
  echo "no access log found within the account"
else
  echo "log file: $AL"
  wc -l "$AL" 2>/dev/null
  TMP=/tmp/af-diag-recent.$$
  tail -n 200000 "$AL" > "$TMP" 2>/dev/null
  echo "--- first + last line timestamps ---"
  head -1 "$TMP"
  tail -1 "$TMP"
  echo "--- HTTP status counts ---"
  awk '{for(i=1;i<=NF;i++) if($i ~ /^"?(GET|POST|HEAD)/){print $(i+2); break}}' "$TMP" | sort | uniq -c | sort -rn | head -12
  echo "--- top 15 user agents ---"
  awk -F'"' '{print $(NF-1)}' "$TMP" | cut -c1-90 | sort | uniq -c | sort -rn | head -15
  echo "--- top 20 request paths ---"
  awk -F'"' '{split($2,a," "); print a[2]}' "$TMP" | cut -d'?' -f1 | cut -c1-80 | sort | uniq -c | sort -rn | head -20
  echo "--- top 12 client IPs ---"
  awk '{print $1}' "$TMP" | sort | uniq -c | sort -rn | head -12
  echo "--- PHP-expensive endpoints (search/ajax/cron/checkout) ---"
  grep -c "wp-cron.php" "$TMP" 2>/dev/null | xargs echo "wp-cron.php hits:"
  grep -c "admin-ajax.php" "$TMP" 2>/dev/null | xargs echo "admin-ajax.php hits:"
  grep -c "wc-ajax" "$TMP" 2>/dev/null | xargs echo "wc-ajax hits:"
  grep -c "?s=" "$TMP" 2>/dev/null | xargs echo "search (?s=) hits:"
  grep -c "wp-login.php" "$TMP" 2>/dev/null | xargs echo "wp-login.php hits:"
  grep -c "xmlrpc.php" "$TMP" 2>/dev/null | xargs echo "xmlrpc.php hits:"
  grep -c "add-to-cart=" "$TMP" 2>/dev/null | xargs echo "add-to-cart hits:"
  echo "--- requests per hour (timestamp bucket) ---"
  awk -F'[' '{print substr($2,1,14)}' "$TMP" | sort | uniq -c | tail -30
  rm -f "$TMP"
fi

echo
echo "=== PHP ERROR LOG TAILS ==="
for f in wp-content/debug.log error_log wp-admin/error_log ~/logs/error_log; do
  if [ -f "$f" ]; then echo "--- $f ($(du -h "$f" 2>/dev/null | cut -f1)) ---"; tail -5 "$f" 2>/dev/null | cut -c1-200; fi
done

echo
echo "AF-DIAG-SHELL-DONE"
