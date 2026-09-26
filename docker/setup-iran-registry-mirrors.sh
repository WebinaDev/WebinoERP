#!/usr/bin/env bash
# Configure Docker daemon to pull Hub images via Iranian mirrors (Docker Hub blocks IR IPs with 403).
# Usage: sudo bash docker/setup-iran-registry-mirrors.sh
set -euo pipefail

DAEMON_JSON="${DAEMON_JSON:-/etc/docker/daemon.json}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

# Prefer mirrors that act as Docker Hub pull-through caches.
# Order can be changed; Docker tries them in sequence.
python3 - <<'PY' "$DAEMON_JSON" "$tmpdir/daemon.json"
import json, pathlib, sys
path = pathlib.Path(sys.argv[1])
out = pathlib.Path(sys.argv[2])
mirrors = [
    "https://docker.arvancloud.ir",
    "https://hub.hamdocker.ir",
    "https://docker.iranserver.com",
    "https://focker.ir",
    "https://docker.kernel.ir",
]
data = {}
if path.exists():
    try:
        data = json.loads(path.read_text() or "{}")
    except json.JSONDecodeError as e:
        raise SystemExit(f"Invalid JSON in {path}: {e}") from e
existing = data.get("registry-mirrors") or []
# Keep user mirrors first, then append ours without duplicates
merged = []
for m in list(existing) + mirrors:
    if m and m not in merged:
        merged.append(m)
data["registry-mirrors"] = merged
out.write_text(json.dumps(data, indent=2) + "\n")
print(json.dumps(data, indent=2))
PY

install -m 0644 "$tmpdir/daemon.json" "$DAEMON_JSON"
echo "Wrote $DAEMON_JSON"

if command -v systemctl >/dev/null 2>&1; then
  systemctl restart docker
  echo "Restarted docker via systemctl"
elif command -v service >/dev/null 2>&1; then
  service docker restart
  echo "Restarted docker via service"
else
  echo "Restart Docker manually, then verify: docker info | grep -A5 'Registry Mirrors'"
fi

echo
echo "Quick test:"
echo "  docker pull node:22-alpine"
echo "  docker pull php:8.3-cli-bookworm"
echo
echo "If pulls still fail, use compose overlay:"
echo "  docker compose -f docker-compose.yml -f docker-compose.iran.yml pull"
echo "  docker compose -f docker-compose.yml -f docker-compose.iran.yml up -d --build"
