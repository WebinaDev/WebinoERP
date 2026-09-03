#!/usr/bin/env bash
# WebinoERP server install — auto-installs dependencies, clones from GitHub, runs Docker.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/WebinaDev/WebinoERP/main/install.sh | bash
#   curl -fsSL ... | INSTALL_DIR=/opt/webina WEB_HTTP_PORT=3080 APP_URL=http://1.2.3.4:3080 bash
#
# Auto-installs when missing: git, curl, ca-certificates, Docker Engine + Compose v2 plugin.
set -euo pipefail
trap 'echo "ERROR: install.sh failed at line ${LINENO} (exit $?)" >&2' ERR

# ─────────────────────────── configuration ────────────────────────────────────
ERP_REPO="${ERP_REPO:-https://github.com/WebinaDev/WebinoERP.git}"
ERP_REF="${ERP_REF:-main}"
UI_REPO="${UI_REPO:-https://github.com/WebinaDev/WebinaDashboard.git}"
INSTALL_DIR="${INSTALL_DIR:-/opt/webina}"
WEB_HTTP_PORT="${WEB_HTTP_PORT:-3080}"
WEB_HTTPS_PORT="${WEB_HTTPS_PORT:-3443}"
SKIP_DEPS="${SKIP_DEPS:-0}"

# ─────────────────────────── auto-detect APP_URL ──────────────────────────────
if [ -z "${APP_URL:-}" ]; then
  HOST_IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
  if [ -n "${HOST_IP}" ]; then
    APP_URL="http://${HOST_IP}:${WEB_HTTP_PORT}"
  else
    APP_URL="http://127.0.0.1:${WEB_HTTP_PORT}"
  fi
fi

# ─────────────────────────── root / sudo helper ───────────────────────────────
if [ "$(id -u)" -eq 0 ]; then
  SUDO=""
else
  SUDO="sudo"
  if ! command -v sudo >/dev/null 2>&1; then
    echo "ERROR: Run as root or install sudo first." >&2
    exit 1
  fi
fi

# ─────────────────────────── utility functions ────────────────────────────────
log()     { echo "==> $*"; }
has_cmd() { command -v "$1" >/dev/null 2>&1; }

run_cmd() {
  if [ -n "${SUDO}" ]; then ${SUDO} "$@"; else "$@"; fi
}

run_apt_get() {
  # Avoids "DEBIAN_FRONTEND=...: command not found" when SUDO is empty (root)
  if [ -n "${SUDO}" ]; then
    ${SUDO} env DEBIAN_FRONTEND=noninteractive apt-get "$@"
  else
    DEBIAN_FRONTEND=noninteractive apt-get "$@"
  fi
}

compose_cli() {
  if docker compose version >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    docker compose "$@"
  elif docker compose version >/dev/null 2>&1; then
    run_cmd docker compose "$@"
  else
    echo "ERROR: Docker Compose v2 is not available." >&2
    exit 1
  fi
}

# ─────────────────────────── OS detection ─────────────────────────────────────
detect_os() {
  if [ -f /etc/os-release ]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-unknown}"
    OS_ID_LIKE="${ID_LIKE:-}"
  else
    OS_ID="unknown"
    OS_ID_LIKE=""
  fi
}

# ─────────────────────────── base package install ─────────────────────────────
install_base_packages() {
  detect_os
  log "Installing base packages (git, curl, ca-certificates)…"
  case "${OS_ID}" in
    ubuntu|debian|linuxmint|pop)
      run_cmd apt-get update -qq
      run_apt_get install -y ca-certificates curl git gnupg lsb-release apt-transport-https
      ;;
    fedora)
      run_cmd dnf install -y ca-certificates curl git
      ;;
    rhel|centos|rocky|almalinux|ol)
      run_cmd dnf install -y ca-certificates curl git 2>/dev/null || \
        run_cmd yum install -y ca-certificates curl git
      ;;
    opensuse*|sles)
      run_cmd zypper -n install ca-certificates curl git
      ;;
    arch|manjaro)
      run_cmd pacman -Sy --noconfirm ca-certificates curl git
      ;;
    *)
      log "Unknown OS '${OS_ID}' — skipping base package install (install git+curl manually if this fails)"
      ;;
  esac
}

# ─────────────────────────── Docker Engine install ────────────────────────────
install_docker_engine() {
  if has_cmd docker && docker compose version >/dev/null 2>&1; then
    log "Docker + Compose already installed — skipping"
    return 0
  fi

  log "Installing Docker Engine + Compose plugin…"
  detect_os

  case "${OS_ID}" in
    ubuntu|debian|linuxmint|pop)
      local docker_distro="${OS_ID}"
      # Pop!_OS and Mint are ubuntu-based
      if [ "${OS_ID}" = "linuxmint" ] || [ "${OS_ID}" = "pop" ]; then
        docker_distro="ubuntu"
      fi
      local codename=""
      codename="$(. /etc/os-release && echo "${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}")" || true
      [ -z "${codename}" ] && codename="$(lsb_release -cs 2>/dev/null || true)"
      run_cmd install -m 0755 -d /etc/apt/keyrings
      curl -fsSL "https://download.docker.com/linux/${docker_distro}/gpg" \
        | run_cmd gpg --dearmor -o /etc/apt/keyrings/docker.gpg
      run_cmd chmod a+r /etc/apt/keyrings/docker.gpg
      printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/%s %s stable\n' \
        "$(dpkg --print-architecture)" "${docker_distro}" "${codename}" \
        | run_cmd tee /etc/apt/sources.list.d/docker.list >/dev/null
      run_cmd apt-get update -qq
      run_apt_get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
      ;;
    fedora|rhel|centos|rocky|almalinux|ol)
      run_cmd dnf -y install dnf-plugins-core 2>/dev/null || true
      run_cmd dnf config-manager \
        --add-repo https://download.docker.com/linux/centos/docker-ce.repo 2>/dev/null || \
        curl -fsSL https://get.docker.com | run_cmd sh
      if ! has_cmd docker; then
        run_cmd dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin \
          2>/dev/null || \
          run_cmd yum install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
      fi
      ;;
    *)
      log "Using Docker convenience script (get.docker.com)…"
      curl -fsSL https://get.docker.com | run_cmd sh
      ;;
  esac
}

# ─────────────────────────── Docker service start ─────────────────────────────
ensure_docker_running() {
  if run_cmd systemctl is-active docker >/dev/null 2>&1; then
    :
  else
    log "Starting Docker service…"
    run_cmd systemctl enable --now docker 2>/dev/null || \
      run_cmd service docker start 2>/dev/null || true
    sleep 3
  fi

  # If running as non-root, ensure user is in docker group
  if [ "$(id -u)" -ne 0 ] && ! docker info >/dev/null 2>&1; then
    log "Adding $(whoami) to docker group…"
    run_cmd usermod -aG docker "$(whoami)" 2>/dev/null || true
    # Try sg for the current session without requiring re-login
    if command -v sg >/dev/null 2>&1 && sg docker -c "docker info" >/dev/null 2>&1; then
      log "docker group active via sg — continuing"
      exec sg docker "$0" "$@"
    fi
  fi

  if ! compose_cli version >/dev/null 2>&1; then
    echo "ERROR: Docker Compose plugin missing after install." >&2
    exit 1
  fi
}

# ─────────────────────────── dependency gate ──────────────────────────────────
ensure_dependencies() {
  [ "${SKIP_DEPS}" = "1" ] && return 0

  if ! has_cmd git || ! has_cmd curl; then
    install_base_packages
  fi

  if ! has_cmd git; then
    echo "ERROR: git still missing after install attempt." >&2; exit 1
  fi

  if ! has_cmd docker || ! docker compose version >/dev/null 2>&1; then
    install_docker_engine
  fi

  ensure_docker_running
}

# ─────────────────────────── install dir setup ────────────────────────────────
ensure_install_dir() {
  if [ ! -d "${INSTALL_DIR}" ]; then
    run_cmd mkdir -p "${INSTALL_DIR}"
  fi
  if [ ! -w "${INSTALL_DIR}" ]; then
    run_cmd chown -R "$(id -u):$(id -g)" "${INSTALL_DIR}"
  fi
}

# ═══════════════════════════════ MAIN ═════════════════════════════════════════
ensure_dependencies
ensure_install_dir

log "Install dir : ${INSTALL_DIR}"
log "App URL     : ${APP_URL}"

mkdir -p "${INSTALL_DIR}/packages"
cd "${INSTALL_DIR}"

# ── clone / update WebinoERP ─────────────────────────────────────────────────
if [ ! -d WebinoERP/.git ]; then
  log "Cloning WebinoERP (branch: ${ERP_REF})"
  git clone --branch "${ERP_REF}" --depth 1 "${ERP_REPO}" WebinoERP
else
  log "Updating WebinoERP to latest ${ERP_REF}"
  git -C WebinoERP remote set-url origin "${ERP_REPO}"
  git -C WebinoERP fetch origin "${ERP_REF}" 2>/dev/null || \
    git -C WebinoERP fetch --depth 1 origin "${ERP_REF}" || true
  git -C WebinoERP checkout -B "${ERP_REF}" "origin/${ERP_REF}" 2>/dev/null || true
  git -C WebinoERP reset --hard "origin/${ERP_REF}" 2>/dev/null || true
  # Remove stale untracked files (old deleted files like MarketingContent.php)
  git -C WebinoERP clean -fd
fi

# ── fetch @webina/ui (frontend build dependency) ─────────────────────────────
if [ ! -f packages/webina-ui/package.json ]; then
  log "Fetching @webina/ui from WebinaDashboard (needed for frontend build)"
  tmp="$(mktemp -d)"
  git clone --filter=blob:none --sparse --depth 1 "${UI_REPO}" "${tmp}/dash"
  git -C "${tmp}/dash" sparse-checkout set packages/webina-ui
  if [ -d "${tmp}/dash/packages/webina-ui" ]; then
    cp -a "${tmp}/dash/packages/webina-ui" "${INSTALL_DIR}/packages/webina-ui"
    log "@webina/ui fetched successfully"
  else
    echo "ERROR: packages/webina-ui not found in ${UI_REPO}" >&2
    rm -rf "${tmp}"; exit 1
  fi
  rm -rf "${tmp}"
fi

cd "${INSTALL_DIR}/WebinoERP"

# ── environment files ─────────────────────────────────────────────────────────
[ -f .env ]              || cp .env.example .env
[ -f backend/.env ]     || cp backend/.env.example backend/.env
[ -f frontend/.env ]    || cp frontend/.env.example frontend/.env
[ -f frontend/.env.local ] || cp frontend/.env.example frontend/.env.local

# ── randomise DB password on first install ────────────────────────────────────
PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24 2>/dev/null || true)"
PASS="${PASS:-webinoChangeMe999}"

_has_default_pass() {
  grep -qE "^POSTGRES_PASSWORD=(postgres|webino|changeme)?$" .env 2>/dev/null
}

if _has_default_pass; then
  sed -i.bak "s/^POSTGRES_PASSWORD=.*/POSTGRES_PASSWORD=${PASS}/" .env
fi

# Ensure sane default user / db name
grep -q "^POSTGRES_USER=postgres$" .env && \
  sed -i.bak 's/^POSTGRES_USER=.*/POSTGRES_USER=webino/' .env

grep -q "^POSTGRES_DB=" .env && \
  sed -i.bak 's/^POSTGRES_DB=.*/POSTGRES_DB=webina_crm/' .env

# ── HTTP port in root .env ────────────────────────────────────────────────────
if grep -q "^WEB_HTTP_PORT=" .env; then
  sed -i.bak "s/^WEB_HTTP_PORT=.*/WEB_HTTP_PORT=${WEB_HTTP_PORT}/" .env
else
  printf '\nWEB_HTTP_PORT=%s\nWEB_HTTPS_PORT=%s\n' "${WEB_HTTP_PORT}" "${WEB_HTTPS_PORT}" >> .env
fi

# ── sync credentials into backend/.env ───────────────────────────────────────
DB_PASS="$(grep "^POSTGRES_PASSWORD=" .env | cut -d= -f2-)"
DB_USER="$(grep "^POSTGRES_USER="     .env | cut -d= -f2-)"
DB_NAME="$(grep "^POSTGRES_DB="       .env | cut -d= -f2-)"

sed -i.bak \
  -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
  -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
  -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
  -e "s|^DB_HOST=.*|DB_HOST=db|" \
  -e "s|^REDIS_HOST=.*|REDIS_HOST=redis|" \
  backend/.env

# ── ensure required Laravel directories exist and are writable ────────────────
log "Ensuring Laravel storage & cache directories…"
mkdir -p \
  backend/bootstrap/cache \
  backend/storage/app/public \
  backend/storage/framework/cache/data \
  backend/storage/framework/sessions \
  backend/storage/framework/views \
  backend/storage/logs
chmod -R 777 backend/bootstrap/cache backend/storage 2>/dev/null || true
chmod +x docker/php/entrypoint.sh docker/php/entrypoint-platform.sh 2>/dev/null || true

# ── build backend image first (needed for composer + key:generate) ────────────
log "Building backend Docker image…"
compose_cli build backend

# ── composer install (inside backend image, no network to db needed) ──────────
log "Installing PHP dependencies (composer via Docker — no DB needed here)"
compose_cli run --rm -T --no-deps \
  -e APP_ENV=local \
  -e DB_HOST=localhost \
  --entrypoint composer backend \
  install --no-interaction --prefer-dist --no-dev </dev/null

# ── APP_KEY (openssl — no artisan boot, no extra container) ───────────────────
if ! grep -q "^APP_KEY=base64:" backend/.env; then
  log "Generating APP_KEY"
  KEY="$(openssl rand -base64 32 2>/dev/null | tr -d '\n' || true)"
  if [ -z "${KEY}" ]; then
    KEY="$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  fi
  sed -i.bak "s|^APP_KEY=.*|APP_KEY=base64:${KEY}|" backend/.env
fi

# ── build & start all containers ──────────────────────────────────────────────
log "Building and starting all Docker services (this may take several minutes)…"
compose_cli up -d --build --remove-orphans

# ── wait for Postgres to be healthy ──────────────────────────────────────────
log "Waiting for database to become ready…"
READY=0
for i in $(seq 1 60); do
  if compose_cli exec -T db pg_isready -U "${DB_USER}" -d "${DB_NAME}" >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 2
done

if [ "${READY}" -eq 0 ]; then
  echo "ERROR: database did not become ready in 120 s." >&2
  compose_cli logs db | tail -30
  exit 1
fi

# ── wait for backend HTTP (metrics — works before migrations) ────────────────
log "Waiting for backend to boot…"
BACKEND_READY=0
for i in $(seq 1 60); do
  if compose_cli exec -T backend curl -sf http://127.0.0.1:8080/api/v1/core/health/metrics >/dev/null 2>&1; then
    BACKEND_READY=1
    break
  fi
  sleep 3
done
if [ "${BACKEND_READY}" -eq 0 ]; then
  echo "ERROR: backend did not start. Last logs:" >&2
  compose_cli logs backend --tail 80 >&2 || true
  compose_cli ps -a >&2 || true
  exit 1
fi

# ── migrate & seed ────────────────────────────────────────────────────────────
log "Running database migrations"
compose_cli exec -T backend php artisan migrate --force

log "Seeding database"
compose_cli exec -T backend php artisan db:seed --force

compose_cli exec -T backend php artisan storage:link 2>/dev/null || true

# ── wait for frontend to finish building (Next.js standalone) ─────────────────
log "Waiting for frontend to become ready (Next.js build may take a few minutes)…"
for i in $(seq 1 90); do
  if compose_cli exec -T frontend wget -qO- http://localhost:3000/ >/dev/null 2>&1; then
    log "Frontend is ready!"
    break
  fi
  sleep 4
done

# ── done ──────────────────────────────────────────────────────────────────────
echo
echo "╔══════════════════════════════════════════════╗"
echo "║           WebinoERP installed! 🎉            ║"
echo "╠══════════════════════════════════════════════╣"
echo "║  Admin:  ${APP_URL}/admin"
echo "║  API:    ${APP_URL}/api/v1"
echo "║  Login:  admin@webina.local  /  password"
echo "╠══════════════════════════════════════════════╣"
echo "║  ⚠  Change the default password immediately! ║"
echo "╚══════════════════════════════════════════════╝"
echo
echo "Project path : ${INSTALL_DIR}/WebinoERP"
if ! docker info >/dev/null 2>&1 && id -nG 2>/dev/null | grep -qw docker; then
  echo "NOTE: Log out and back in (or run: newgrp docker) to use docker without sudo."
fi
