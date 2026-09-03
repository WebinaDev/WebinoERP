#!/usr/bin/env bash
# WebinoERP server install — clone from GitHub and run with Docker.
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/WebinaDev/WebinoERP/main/install.sh | bash
#   INSTALL_DIR=/opt/webina WEB_HTTP_PORT=3080 bash install.sh
set -euo pipefail

ERP_REPO="${ERP_REPO:-https://github.com/WebinaDev/WebinoERP.git}"
ERP_REF="${ERP_REF:-main}"
UI_REPO="${UI_REPO:-https://github.com/WebinaDev/WebinoDashboard.git}"
INSTALL_DIR="${INSTALL_DIR:-/opt/webina}"
WEB_HTTP_PORT="${WEB_HTTP_PORT:-3080}"
WEB_HTTPS_PORT="${WEB_HTTPS_PORT:-3443}"
APP_URL="${APP_URL:-http://$(hostname -I 2>/dev/null | awk '{print $1}'):${WEB_HTTP_PORT}}"
APP_URL="${APP_URL:-http://127.0.0.1:${WEB_HTTP_PORT}}"

need() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Missing dependency: $1" >&2
    exit 1
  }
}

need git
need docker
docker compose version >/dev/null 2>&1 || {
  echo "Docker Compose v2 is required (docker compose)." >&2
  exit 1
}

echo "==> Install dir: ${INSTALL_DIR}"
echo "==> App URL:     ${APP_URL}"
mkdir -p "${INSTALL_DIR}/packages"
cd "${INSTALL_DIR}"

if [ ! -d WebinoERP/.git ]; then
  echo "==> Cloning WebinoERP (${ERP_REF})"
  git clone --branch "${ERP_REF}" --depth 1 "${ERP_REPO}" WebinoERP
else
  echo "==> Updating WebinoERP"
  git -C WebinoERP fetch --depth 1 origin "${ERP_REF}"
  git -C WebinoERP checkout "${ERP_REF}"
  git -C WebinoERP pull --ff-only origin "${ERP_REF}" || true
fi

if [ ! -f packages/webina-ui/package.json ]; then
  echo "==> Fetching @webina/ui (needed by the frontend Docker build)"
  tmp="$(mktemp -d)"
  git clone --filter=blob:none --sparse --depth 1 "${UI_REPO}" "${tmp}/dash"
  git -C "${tmp}/dash" sparse-checkout set packages/webina-ui
  if [ -d "${tmp}/dash/packages/webina-ui" ]; then
    cp -a "${tmp}/dash/packages/webina-ui" "${INSTALL_DIR}/packages/webina-ui"
  else
    echo "Could not find packages/webina-ui in ${UI_REPO}" >&2
    echo "Place @webina/ui at ${INSTALL_DIR}/packages/webina-ui and re-run." >&2
    rm -rf "${tmp}"
    exit 1
  fi
  rm -rf "${tmp}"
fi

cd "${INSTALL_DIR}/WebinoERP"

[ -f .env ] || cp .env.example .env
[ -f backend/.env ] || cp backend/.env.example backend/.env
[ -f frontend/.env ] || cp frontend/.env.example frontend/.env
[ -f frontend/.env.local ] || cp frontend/.env.example frontend/.env.local

PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24 || true)"
PASS="${PASS:-webinoChangeMe}"

if grep -q '^POSTGRES_PASSWORD=postgres$' .env 2>/dev/null || grep -q '^POSTGRES_PASSWORD=$' .env 2>/dev/null; then
  sed -i.bak "s/^POSTGRES_PASSWORD=.*/POSTGRES_PASSWORD=${PASS}/" .env
fi
if grep -q '^POSTGRES_USER=postgres$' .env; then
  sed -i.bak 's/^POSTGRES_USER=.*/POSTGRES_USER=webino/' .env
fi
if grep -q '^POSTGRES_DB=' .env; then
  sed -i.bak 's/^POSTGRES_DB=.*/POSTGRES_DB=webina_crm/' .env
fi

grep -q '^WEB_HTTP_PORT=' .env && sed -i.bak "s/^WEB_HTTP_PORT=.*/WEB_HTTP_PORT=${WEB_HTTP_PORT}/" .env \
  || printf '\nWEB_HTTP_PORT=%s\nWEB_HTTPS_PORT=%s\n' "${WEB_HTTP_PORT}" "${WEB_HTTPS_PORT}" >> .env

DB_PASS="$(grep '^POSTGRES_PASSWORD=' .env | cut -d= -f2-)"
DB_USER="$(grep '^POSTGRES_USER=' .env | cut -d= -f2-)"
DB_NAME="$(grep '^POSTGRES_DB=' .env | cut -d= -f2-)"

sed -i.bak \
  -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
  -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
  -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
  -e "s|^DB_HOST=.*|DB_HOST=db|" \
  -e "s|^REDIS_HOST=.*|REDIS_HOST=redis|" \
  backend/.env

echo "==> Installing PHP dependencies"
docker compose run --rm --no-deps --entrypoint composer backend install --no-interaction --prefer-dist --no-dev || \
  docker compose run --rm --no-deps --entrypoint composer backend install --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=base64:' backend/.env; then
  echo "==> Generating APP_KEY"
  docker compose run --rm --no-deps --entrypoint php backend artisan key:generate --force
fi

echo "==> Building and starting Docker services"
docker compose up -d --build

echo "==> Waiting for database"
for i in $(seq 1 30); do
  if docker compose exec -T db pg_isready -U "${DB_USER}" -d "${DB_NAME}" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

echo "==> Migrating and seeding"
docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan db:seed --force
docker compose exec -T backend php artisan storage:link >/dev/null 2>&1 || true

echo
echo "WebinoERP is up."
echo "  Admin:  ${APP_URL}/admin"
echo "  API:    ${APP_URL}/api/v1"
echo "  Login:  admin@webina.local / password"
echo
echo "Change the demo password immediately."
echo "Compose project: ${INSTALL_DIR}/WebinoERP"
