#!/usr/bin/env bash
# WebinoERP in-place update — pulls latest code, rebuilds containers, migrates.
# Does NOT wipe volumes, drop the database, or re-seed.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/WebinaDev/WebinoERP/main/update.sh | bash
#   curl -fsSL ... | INSTALL_DIR=/opt/webina bash
#
# Optional env:
#   INSTALL_DIR   parent dir of the clone (default /opt/webina)
#   ERP_DIR       exact repo path if not INSTALL_DIR/WebinoERP
#   ERP_REF       git branch (default main)
#   SKIP_UI       1 = do not refresh packages/webina-ui
set -euo pipefail
trap 'echo "ERROR: update.sh failed at line ${LINENO} (exit $?)" >&2' ERR

ERP_REPO="${ERP_REPO:-https://github.com/WebinaDev/WebinoERP.git}"
ERP_REF="${ERP_REF:-main}"
UI_REPO="${UI_REPO:-https://github.com/WebinaDev/WebinaDashboard.git}"
INSTALL_DIR="${INSTALL_DIR:-/opt/webina}"
SKIP_UI="${SKIP_UI:-0}"

if [ "$(id -u)" -eq 0 ]; then
  SUDO=""
else
  SUDO="sudo"
  if ! command -v sudo >/dev/null 2>&1; then
    echo "ERROR: Run as root or install sudo first." >&2
    exit 1
  fi
fi

log() { echo "==> $*"; }
run_cmd() {
  if [ -n "${SUDO}" ]; then ${SUDO} "$@"; else "$@"; fi
}

COMPOSE_FILES=(-f docker-compose.yml -f docker-compose.prod.yml)

compose_cli() {
  if docker compose version >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    docker compose "${COMPOSE_FILES[@]}" "$@"
  elif docker compose version >/dev/null 2>&1; then
    run_cmd docker compose "${COMPOSE_FILES[@]}" "$@"
  else
    echo "ERROR: Docker Compose v2 is not available." >&2
    exit 1
  fi
}

resolve_erp_dir() {
  if [ -n "${ERP_DIR:-}" ]; then
    if [ ! -f "${ERP_DIR}/docker-compose.yml" ]; then
      echo "ERROR: ERP_DIR=${ERP_DIR} has no docker-compose.yml" >&2
      exit 1
    fi
    return 0
  fi
  if [ -f "${INSTALL_DIR}/WebinoERP/docker-compose.yml" ]; then
    ERP_DIR="${INSTALL_DIR}/WebinoERP"
  elif [ -f "${INSTALL_DIR}/docker-compose.yml" ]; then
    ERP_DIR="${INSTALL_DIR}"
  else
    echo "ERROR: WebinoERP not found. Looked at:" >&2
    echo "  ${INSTALL_DIR}/WebinoERP" >&2
    echo "  ${INSTALL_DIR}" >&2
    echo "Set INSTALL_DIR or ERP_DIR and retry." >&2
    exit 1
  fi
}

# ═══════════════════════════════ MAIN ═════════════════════════════════════════
if ! command -v git >/dev/null 2>&1; then
  echo "ERROR: git is required." >&2
  exit 1
fi
if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: docker is required." >&2
  exit 1
fi

resolve_erp_dir
log "ERP dir     : ${ERP_DIR}"
log "Git ref     : ${ERP_REF}"
log "Volumes     : kept (no down -v, no migrate:fresh, no seed)"

if [ ! -d "${ERP_DIR}/.git" ]; then
  echo "ERROR: ${ERP_DIR} is not a git clone. Cannot update." >&2
  exit 1
fi

BACKUP_DIR="$(mktemp -d /tmp/webinoerp-update.XXXXXX)"
cleanup_backup() { rm -rf "${BACKUP_DIR}"; }
trap 'cleanup_backup' EXIT
trap 'echo "ERROR: update.sh failed at line ${LINENO} (exit $?)" >&2' ERR

log "Backing up env and Caddyfile (secrets + domain stay)"
mkdir -p "${BACKUP_DIR}/backend" "${BACKUP_DIR}/frontend" "${BACKUP_DIR}/docker/caddy"
[ -f "${ERP_DIR}/.env" ] && cp -a "${ERP_DIR}/.env" "${BACKUP_DIR}/.env"
[ -f "${ERP_DIR}/backend/.env" ] && cp -a "${ERP_DIR}/backend/.env" "${BACKUP_DIR}/backend/.env"
[ -f "${ERP_DIR}/frontend/.env" ] && cp -a "${ERP_DIR}/frontend/.env" "${BACKUP_DIR}/frontend/.env"
[ -f "${ERP_DIR}/frontend/.env.local" ] && cp -a "${ERP_DIR}/frontend/.env.local" "${BACKUP_DIR}/frontend/.env.local"
[ -f "${ERP_DIR}/docker/caddy/Caddyfile" ] && cp -a "${ERP_DIR}/docker/caddy/Caddyfile" "${BACKUP_DIR}/docker/caddy/Caddyfile"

cd "${ERP_DIR}"
run_cmd git -C "${ERP_DIR}" remote set-url origin "${ERP_REPO}" || true
log "Fetching ${ERP_REF}…"
run_cmd git -C "${ERP_DIR}" fetch origin "${ERP_REF}"
run_cmd git -C "${ERP_DIR}" checkout -B "${ERP_REF}" "origin/${ERP_REF}"
run_cmd git -C "${ERP_DIR}" reset --hard "origin/${ERP_REF}"
run_cmd git -C "${ERP_DIR}" clean -fd \
  -e .env \
  -e backend/.env \
  -e frontend/.env \
  -e frontend/.env.local \
  -e docker/caddy/Caddyfile

log "Restoring env and Caddyfile"
[ -f "${BACKUP_DIR}/.env" ] && cp -a "${BACKUP_DIR}/.env" "${ERP_DIR}/.env"
[ -f "${BACKUP_DIR}/backend/.env" ] && cp -a "${BACKUP_DIR}/backend/.env" "${ERP_DIR}/backend/.env"
[ -f "${BACKUP_DIR}/frontend/.env" ] && cp -a "${BACKUP_DIR}/frontend/.env" "${ERP_DIR}/frontend/.env"
[ -f "${BACKUP_DIR}/frontend/.env.local" ] && cp -a "${BACKUP_DIR}/frontend/.env.local" "${ERP_DIR}/frontend/.env.local"
[ -f "${BACKUP_DIR}/docker/caddy/Caddyfile" ] && cp -a "${BACKUP_DIR}/docker/caddy/Caddyfile" "${ERP_DIR}/docker/caddy/Caddyfile"

PARENT_DIR="$(dirname "${ERP_DIR}")"
if [ "${SKIP_UI}" != "1" ]; then
  log "Refreshing @webina/ui (frontend Docker build context)"
  run_cmd mkdir -p "${PARENT_DIR}/packages"
  tmp="$(mktemp -d)"
  git clone --filter=blob:none --sparse --depth 1 "${UI_REPO}" "${tmp}/dash"
  git -C "${tmp}/dash" sparse-checkout set packages/webina-ui
  if [ -d "${tmp}/dash/packages/webina-ui" ]; then
    run_cmd rm -rf "${PARENT_DIR}/packages/webina-ui"
    run_cmd cp -a "${tmp}/dash/packages/webina-ui" "${PARENT_DIR}/packages/webina-ui"
  else
    echo "WARN: packages/webina-ui not found in ${UI_REPO} — keeping existing copy" >&2
  fi
  rm -rf "${tmp}"
fi

cd "${ERP_DIR}"
log "Rebuilding containers (named volumes db_data / redis_data are untouched)"
compose_cli up -d --build --force-recreate --remove-orphans

log "Waiting for Postgres…"
for i in $(seq 1 60); do
  if compose_cli exec -T postgres pg_isready -U webino >/dev/null 2>&1 \
    || compose_cli exec -T postgres pg_isready -U postgres >/dev/null 2>&1; then
    break
  fi
  sleep 2
  if [ "${i}" -eq 60 ]; then
    echo "ERROR: Postgres did not become ready." >&2
    compose_cli logs --tail=80 postgres
    exit 1
  fi
done

log "Applying migrations only (no seed, no fresh)"
compose_cli exec -T backend php artisan migrate --force
compose_cli exec -T backend php artisan config:clear || true
compose_cli exec -T backend php artisan cache:clear || true
compose_cli exec -T backend php artisan config:cache || true

log "Done. Data volumes were not removed."
compose_cli ps
