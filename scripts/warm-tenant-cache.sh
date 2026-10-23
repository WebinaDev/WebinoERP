#!/usr/bin/env bash
# Warm edge HTML + /_next/image caches for tenant sites after a deploy.
# Usage: scripts/warm-tenant-cache.sh <domain> [domain...]   (BASE=https://<domain> by default)
# Requests the homepage and every /_next/image URL found in it, with desktop and mobile UAs,
# Accept: image/avif,image/webp (first AVIF conversion can take ~35s, so we pay it here).
set -uo pipefail
UA_D='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'
UA_M='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
ACC='image/avif,image/webp,image/*,*/*;q=0.8'
for d in "$@"; do
  base="${BASE:-https://$d}"
  for ua in "$UA_D" "$UA_M"; do
    html=$(curl -sk -m 60 -A "$ua" -H 'Accept: text/html' "$base/") || { echo "$d: homepage failed"; continue; }
    urls=$(printf '%s' "$html" | grep -oE '/_next/image\?[^" ,]+' | sed 's/&amp;/\&/g' | sort -u)
    n=0; ok=0
    for u in $urls; do
      n=$((n+1))
      c=$(curl -sk -m 90 -o /dev/null -w '%{http_code}' -A "$ua" -H "Accept: $ACC" "$base$u")
      [ "$c" = 200 ] && ok=$((ok+1))
    done
    echo "$d [$( [ "$ua" = "$UA_M" ] && echo mobile || echo desktop )]: images $ok/$n OK"
  done
done
