#!/usr/bin/env bash
# Deploy using prebuilt GHCR images (no docker build on the server).
# See docs/deploy-prebuilt-images.md.
#
#   IMAGE_TAG=<full 40-char git sha> scripts/deploy-pull.sh
#
# Env:
#   IMAGE_TAG        required: full commit sha built by .github/workflows/images.yml
#   ALLOW_LATEST=1   allow IMAGE_TAG=latest/unset (not for production)
#   COMPOSE_FILES    ':'-separated ordered base files (default: docker-compose.yml:docker-compose.prod.yml:docker-compose.iran.yml)
#                    docker-compose.prod-images.yml is always appended last.
#   COMPOSE_PROJECT  compose project name (default: webinoerm)
#   GITHUB_TOKEN / GH_TOKEN  optional: if set, refuse unless the images.yml run for IMAGE_TAG is green
#   SKIP_CI_CHECK=1  skip that check even with a token
#   SKIP_MIGRATE=1   skip migrations after up
set -euo pipefail
cd "$(dirname "$0")/.."
REPO_SLUG="WebinaDev/WebinoERP"
log() { echo "[deploy-pull] $*" >&2; }
die() { echo "[deploy-pull] ERROR: $*" >&2; exit 1; }

IMAGE_TAG="${IMAGE_TAG:-}"
if [[ -z "$IMAGE_TAG" || "$IMAGE_TAG" == latest ]]; then
  [[ "${ALLOW_LATEST:-0}" == 1 ]] || die "set IMAGE_TAG=<full commit sha> (or ALLOW_LATEST=1 for non-production)"
  IMAGE_TAG=latest
elif [[ ! "$IMAGE_TAG" =~ ^[0-9a-f]{40}$ ]]; then
  die "IMAGE_TAG must be a full 40-char commit sha (got '$IMAGE_TAG')"
fi
export IMAGE_TAG

TOKEN="${GITHUB_TOKEN:-${GH_TOKEN:-}}"
if [[ "$IMAGE_TAG" != latest && -n "$TOKEN" && "${SKIP_CI_CHECK:-0}" != 1 ]]; then
  log "checking CI run for ${IMAGE_TAG} in ${REPO_SLUG}"
  res="$(curl -fsS -H "Authorization: Bearer ${TOKEN}" -H 'Accept: application/vnd.github+json' \
    "https://api.github.com/repos/${REPO_SLUG}/actions/workflows/images.yml/runs?head_sha=${IMAGE_TAG}&per_page=20")" \
    || die "GitHub API request failed"
  if ! grep -q '"conclusion": *"success"' <<<"$res"; then
    die "no successful images.yml run for ${IMAGE_TAG} (still running or failed?)"
  fi
  log "CI run is green"
fi

IFS=':' read -r -a base_files <<<"${COMPOSE_FILES:-docker-compose.yml:docker-compose.prod.yml:docker-compose.iran.yml}"
files=()
for f in "${base_files[@]}"; do [[ -f "$f" ]] || die "missing compose file $f"; files+=(-f "$f"); done
files+=(-f docker-compose.prod-images.yml)
PROJECT="${COMPOSE_PROJECT:-webinoerm}"
proj=(); [[ -n "$PROJECT" ]] && proj=(-p "$PROJECT")
compose_cli() { docker compose "${proj[@]}" "${files[@]}" "$@"; }

log "project=${PROJECT:-<dir name>} files=${files[*]} tag=${IMAGE_TAG}"
# backend/worker/scheduler/ws bind-mount ./backend, so the host checkout IS the PHP code.
# The image only carries the runtime; make sure the checkout matches the image sha.
if [[ "$IMAGE_TAG" != latest && "${ALLOW_SHA_MISMATCH:-0}" != 1 ]]; then
  head="$(git rev-parse HEAD 2>/dev/null || true)"
  [[ "$head" == "$IMAGE_TAG" ]] || die "checkout HEAD ($head) != IMAGE_TAG; run: git fetch origin && git checkout --detach $IMAGE_TAG (or ALLOW_SHA_MISMATCH=1)"
fi
compose_cli config -q
compose_cli pull backend worker scheduler ws frontend
compose_cli up -d --no-build
if [[ "${SKIP_MIGRATE:-0}" != 1 ]]; then
  # Same as update.sh: wait for backend, pg_dump backup, migrate --force + assert-schema.
  for i in $(seq 1 90); do compose_cli exec -T backend php -v >/dev/null 2>&1 && break; sleep 2
    [[ $i -eq 90 ]] && { compose_cli logs --tail=120 backend; die "backend not running"; }; done
  PG_USER="${POSTGRES_USER:-webino}"; PG_DB="${POSTGRES_DB:-webina_crm}"
  mkdir -p /var/lib/webino/backups/db
  compose_cli exec -T db pg_dump -U "$PG_USER" -d "$PG_DB" -Fc > "/var/lib/webino/backups/db/$(date +%Y%m%d-%H%M%S).dump" || log "WARN: pg_dump failed"
  # shellcheck source=scripts/apply-erp-migrations.sh
  source scripts/apply-erp-migrations.sh
  apply_erp_migrations
fi
compose_cli ps
log "done"
