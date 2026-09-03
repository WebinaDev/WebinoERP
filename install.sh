#!/usr/bin/env bash
# WebinoERP server install — installs dependencies, clones from GitHub, runs Docker.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/WebinaDev/WebinoERP/main/install.sh | bash
#   curl -fsSL ... | INSTALL_DIR=/opt/webina WEB_HTTP_PORT=3080 APP_URL=http://1.2.3.4:3080 bash
#
# Installs when missing: git, curl, ca-certificates, Docker Engine + Compose plugin.
set -euo pipefail

ERP_REPO="${ERP_REPO:-https://github.com/WebinaDev/WebinoERP.git}"
ERP_REF="${ERP_REF:-main}"
UI_REPO="${UI_REPO:-https://github.com/WebinaDev/WebinoDashboard.git}"
INSTALL_DIR="${INSTALL_DIR:-/opt/webina}"
WEB_HTTP_PORT="${WEB_HTTP_PORT:-3080}"
WEB_HTTPS_PORT="${WEB_HTTPS_PORT:-3443}"
SKIP_DEPS="${SKIP_DEPS:-0}"

if [ -z "${APP_URL:-}" ]; then
  HOST_IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
  if [ -n "${HOST_IP}" ]; then
    APP_URL="http://${HOST_IP}:${WEB_HTTP_PORT}"
  else
    APP_URL="http://127.0.0.1:${WEB_HTTP_PORT}"
  fi
fi

if [ "$(id -u)" -eq 0 ]; then
  SUDO=""
else
  SUDO="sudo"
  if ! command -v sudo >/dev/null 2>&1; then
    echo "Run as root or install sudo first." >&2
    exit 1
  fi
fi

log() { echo "==> $*"; }

has_cmd() { command -v "$1" >/dev/null 2>&1; }

run_cmd() {
  if [ -n "${SUDO}" ]; then
    ${SUDO} "$@"
  else
    "$@"
  fi
}

run_apt_get() {
  if [ -n "${SUDO}" ]; then
    ${SUDO} env DEBIAN_FRONTEND=noninteractive apt-get "$@"
  else
    DEBIAN_FRONTEND=noninteractive apt-get "$@"
  fi
}

docker_cli() {
  if has_cmd docker && docker info >/dev/null 2>&1; then
    docker "$@"
  elif has_cmd docker; then
    run_cmd docker "$@"
  else
    echo "Docker is not available." >&2
    exit 1
  fi
}

compose_cli() {
  if has_cmd docker && docker compose version >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    docker compose "$@"
  elif has_cmd docker && docker compose version >/dev/null 2>&1; then
    run_cmd docker compose "$@"
  else
    echo "Docker Compose v2 is not available." >&2
    exit 1
  fi
}

detect_os() {
  if [ -f /etc/os-release ]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-unknown}"
    OS_ID_LIKE="${ID_LIKE:-}"
    OS_VERSION="${VERSION_ID:-}"
  else
    OS_ID="unknown"
    OS_ID_LIKE=""
  fi
}

install_base_packages() {
  detect_os
  log "Installing base packages (git, curl, ca-certificates)…"
  case "${OS_ID}" in
    ubuntu|debian|linuxmint|pop)
      run_cmd apt-get update -qq
      run_apt_get install -y \
        ca-certificates curl git gnupg lsb-release apt-transport-https
      ;;
    fedora)
      run_cmd dnf install -y ca-certificates curl git
      ;;
    rhel|centos|rocky|almalinux|ol)
      run_cmd dnf install -y ca-certificates curl git || \
        run_cmd yum install -y ca-certificates curl git
      ;;
    opensuse*|sles)
      run_cmd zypper -n install ca-certificates curl git
      ;;
    arch|manjaro)
      run_cmd pacman -Sy --noconfirm ca-certificates curl git
      ;;
    *)
      log "Unknown OS (${OS_ID}). Install git and curl manually if this step fails."
      ;;
  esac
}

install_docker_engine() {
  if has_cmd docker && docker compose version >/dev/null 2>&1; then
    log "Docker + Compose already installed"
    return 0
  fi

  log "Installing Docker Engine + Compose plugin…"
  detect_os

  case "${OS_ID}" in
    ubuntu|debian|linuxmint|pop)
      local docker_distro="${OS_ID}"
      if [ "${OS_ID}" = "linuxmint" ] || [ "${OS_ID}" = "pop" ]; then
        docker_distro="ubuntu"
      fi
      local codename=""
      codename="$(. /etc/os-release && echo "${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}")"
      if [ -z "${codename}" ]; then
        codename="$(lsb_release -cs 2>/dev/null || true)"
      fi
      run_cmd install -m 0755 -d /etc/apt/keyrings
      curl -fsSL "https://download.docker.com/linux/${docker_distro}/gpg" | run_cmd gpg --dearmor -o /etc/apt/keyrings/docker.gpg
      run_cmd chmod a+r /etc/apt/keyrings/docker.gpg
      echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/${docker_distro} ${codename} stable" | \
        run_cmd tee /etc/apt/sources.list.d/docker.list >/dev/null
      run_cmd apt-get update -qq
      run_apt_get install -y \
        docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
      ;;
    fedora|rhel|centos|rocky|almalinux|ol)
      run_cmd dnf -y install dnf-plugins-core 2>/dev/null || true
      run_cmd dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo 2>/dev/null || \
        curl -fsSL https://get.docker.com | run_cmd sh
      if ! has_cmd docker; then
        run_cmd dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin || \
          run_cmd yum install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
      fi
      ;;
    *)
      log "Using Docker convenience script (get.docker.com)…"
      curl -fsSL https://get.docker.com | run_cmd sh
      ;;
  esac
}

ensure_docker_running() {
  if run_cmd systemctl is-active docker >/dev/null 2>&1; then
    :
  else
    log "Starting Docker service…"
    run_cmd systemctl enable --now docker 2>/dev/null || run_cmd service docker start 2>/dev/null || true
  fi

  if [ "$(id -u)" -ne 0 ] && ! docker info >/dev/null 2>&1; then
    log "Adding $(whoami) to docker group (re-login may be required)…"
    run_cmd usermod -aG docker "$(whoami)" 2>/dev/null || true
    if ! docker info >/dev/null 2>&1; then
      log "Using sudo for docker commands in this session…"
    fi
  fi

  if ! compose_cli version >/dev/null 2>&1; then
    echo "Docker Compose plugin missing after install." >&2
    exit 1
  fi
}

ensure_dependencies() {
  if [ "${SKIP_DEPS}" = "1" ]; then
    return 0
  fi

  if ! has_cmd git || ! has_cmd curl; then
    install_base_packages
  fi

  if ! has_cmd git; then
    echo "git is still missing after install attempt." >&2
    exit 1
  fi

  if ! has_cmd docker || ! docker compose version >/dev/null 2>&1; then
    install_docker_engine
  fi

  ensure_docker_running
}

ensure_install_dir() {
  if [ ! -d "${INSTALL_DIR}" ]; then
    run_cmd mkdir -p "${INSTALL_DIR}"
  fi
  if [ ! -w "${INSTALL_DIR}" ]; then
    run_cmd chown -R "$(id -u):$(id -g)" "${INSTALL_DIR}"
  fi
}

ensure_dependencies
ensure_install_dir

log "Install dir: ${INSTALL_DIR}"
log "App URL:     ${APP_URL}"

mkdir -p "${INSTALL_DIR}/packages"
cd "${INSTALL_DIR}"

if [ ! -d WebinoERP/.git ]; then
  log "Cloning WebinoERP (${ERP_REF})"
  git clone --branch "${ERP_REF}" --depth 1 "${ERP_REPO}" WebinoERP
else
  log "Updating WebinoERP"
  git -C WebinoERP fetch --depth 1 origin "${ERP_REF}" || true
  git -C WebinoERP checkout "${ERP_REF}" || true
  git -C WebinoERP pull --ff-only origin "${ERP_REF}" 2>/dev/null || true
fi

if [ ! -f packages/webina-ui/package.json ]; then
  log "Fetching @webina/ui (frontend build dependency)"
  tmp="$(mktemp -d)"
  git clone --filter=blob:none --sparse --depth 1 "${UI_REPO}" "${tmp}/dash"
  git -C "${tmp}/dash" sparse-checkout set packages/webina-ui
  if [ -d "${tmp}/dash/packages/webina-ui" ]; then
    cp -a "${tmp}/dash/packages/webina-ui" "${INSTALL_DIR}/packages/webina-ui"
  else
    echo "Could not find packages/webina-ui in ${UI_REPO}" >&2
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

PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24 2>/dev/null || true)"
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

if grep -q '^WEB_HTTP_PORT=' .env; then
  sed -i.bak "s/^WEB_HTTP_PORT=.*/WEB_HTTP_PORT=${WEB_HTTP_PORT}/" .env
else
  printf '\nWEB_HTTP_PORT=%s\nWEB_HTTPS_PORT=%s\n' "${WEB_HTTP_PORT}" "${WEB_HTTPS_PORT}" >> .env
fi

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

log "Ensuring backend cache and storage directories exist with write permissions"
mkdir -p backend/bootstrap/cache \
         backend/storage/app/public \
         backend/storage/framework/cache/data \
         backend/storage/framework/sessions \
         backend/storage/framework/views \
         backend/storage/logs
chmod -R 777 backend/storage backend/bootstrap/cache 2>/dev/null || true

log "Installing PHP dependencies (Composer via Docker)"
compose_cli run --rm --no-deps --entrypoint composer backend install --no-interaction --prefer-dist --no-dev 2>/dev/null || \
  compose_cli run --rm --no-deps --entrypoint composer backend install --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=base64:' backend/.env; then
  log "Generating APP_KEY"
  compose_cli run --rm --no-deps --entrypoint php backend artisan key:generate --force
fi

log "Building and starting Docker services (may take several minutes)"
compose_cli up -d --build

log "Waiting for database"
for _ in $(seq 1 45); do
  if compose_cli exec -T db pg_isready -U "${DB_USER}" -d "${DB_NAME}" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

log "Migrating and seeding"
compose_cli exec -T backend php artisan migrate --force
compose_cli exec -T backend php artisan db:seed --force
compose_cli exec -T backend php artisan storage:link >/dev/null 2>&1 || true

echo
echo "WebinoERP is up."
echo "  Admin:  ${APP_URL}/admin"
echo "  API:    ${APP_URL}/api/v1"
echo "  Login:  admin@webina.local / password"
echo
echo "Change the demo password immediately."
echo "Project path: ${INSTALL_DIR}/WebinoERP"
if ! docker info >/dev/null 2>&1 && groups | grep -q docker; then
  echo "Note: log out and back in (or run: newgrp docker) to use docker without sudo."
fi
