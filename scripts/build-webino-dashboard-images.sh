#!/usr/bin/env bash
# Fetch WebinoDashboard from GitHub and build tenant images.
#
# Default source: https://github.com/Webinadev/WebinoDashboard
# Checkout lives at /var/lib/webino/src/WebinoDashboard so the host Docker daemon
# and the ERP container see the same path (via the /var/lib/webino bind mount).
#
# Override:
#   WEBINO_DASHBOARD_GIT_URL  WEBINO_DASHBOARD_GIT_REF  WEBINO_DASHBOARD_GIT_TOKEN
#   WEBINO_DASHBOARD_SRC      WEBINO_DASHBOARD_PATH (local checkout, skips git)
#   WEBINO_FORCE_GIT=1       ignore WEBINO_DASHBOARD_PATH; always sync from git
#   WEBINO_DOCKER_NO_CACHE=1 pass --no-cache to docker build (backend + frontend)
#   WEBINO_IRAN_DOCKER       auto|1|0 — use Hub mirrors (default auto=on)
#   PHP_IMAGE  COMPOSER_IMAGE  NODE_IMAGE — base image overrides
set -euo pipefail

GIT_URL="${WEBINO_DASHBOARD_GIT_URL:-https://github.com/Webinadev/WebinoDashboard.git}"
GIT_REF="${WEBINO_DASHBOARD_GIT_REF:-main}"
SRC="${WEBINO_DASHBOARD_SRC:-/var/lib/webino/src/WebinoDashboard}"
WEBINO_IRAN_DOCKER="${WEBINO_IRAN_DOCKER:-auto}"
WEBINO_FORCE_GIT="${WEBINO_FORCE_GIT:-0}"
WEBINO_DOCKER_NO_CACHE="${WEBINO_DOCKER_NO_CACHE:-0}"

log() { echo "$*" >&2; }

iran_docker_enabled() {
  case "${WEBINO_IRAN_DOCKER}" in
    0|false|no|NO) return 1 ;;
    *) return 0 ;;
  esac
}

# Resolve base images: explicit env wins; else Iran mirrors when enabled; else Hub.
# Use library/php (not dunglas/frankenphp) — frankenphp is often missing on IR mirrors.
# COMPOSER_MIRROR: Packagist itself often fails from IR (curl HTTP/2 error 18).
if iran_docker_enabled; then
  PHP_IMAGE="${PHP_IMAGE:-hub.hamdocker.ir/library/php:8.3-cli-bookworm}"
  COMPOSER_IMAGE="${COMPOSER_IMAGE:-hub.hamdocker.ir/library/composer:2}"
  NODE_IMAGE="${NODE_IMAGE:-hub.hamdocker.ir/library/node:22-alpine}"
  COMPOSER_MIRROR="${COMPOSER_MIRROR:-https://mirrors.aliyun.com/composer/}"
  log "Iran Docker mirrors enabled (WEBINO_IRAN_DOCKER=${WEBINO_IRAN_DOCKER})"
else
  PHP_IMAGE="${PHP_IMAGE:-php:8.3-cli-bookworm}"
  COMPOSER_IMAGE="${COMPOSER_IMAGE:-composer:2}"
  NODE_IMAGE="${NODE_IMAGE:-node:22-alpine}"
  COMPOSER_MIRROR="${COMPOSER_MIRROR:-}"
  log "Using Docker Hub base images (WEBINO_IRAN_DOCKER=${WEBINO_IRAN_DOCKER})"
fi

# Reject blank overrides (would produce "base name should not be blank").
for _pair in "PHP_IMAGE=${PHP_IMAGE}" "COMPOSER_IMAGE=${COMPOSER_IMAGE}" "NODE_IMAGE=${NODE_IMAGE}"; do
  _key="${_pair%%=*}"
  _val="${_pair#*=}"
  if [[ -z "${_val// }" ]]; then
    log "error: ${_key} is empty"
    exit 1
  fi
done

log "Base images: PHP_IMAGE=${PHP_IMAGE}"
log "Base images: COMPOSER_IMAGE=${COMPOSER_IMAGE}"
log "Base images: NODE_IMAGE=${NODE_IMAGE}"
log "Composer mirror: ${COMPOSER_MIRROR:-<packagist.org>}"

has_dockerfiles() {
  local root="${1:-}"
  [[ -n "$root" && -f "${root}/docker/php/Dockerfile.platform" && -f "${root}/docker/next/Dockerfile" ]]
}

authenticated_url() {
  local url="$1"
  local token="${WEBINO_DASHBOARD_GIT_TOKEN:-}"
  if [[ -z "$token" ]]; then
    printf '%s' "$url"
    return
  fi
  if [[ "$url" == https://* ]]; then
    printf 'https://x-access-token:%s@%s' "$token" "${url#https://}"
  else
    printf '%s' "$url"
  fi
}

sync_from_git() {
  if ! command -v git >/dev/null 2>&1; then
    log "error: git is required to clone ${GIT_URL}"
    exit 1
  fi

  mkdir -p "$(dirname "$SRC")"
  export GIT_TERMINAL_PROMPT=0
  local url
  url="$(authenticated_url "$GIT_URL")"

  log "Syncing ${GIT_URL} (${GIT_REF}) → ${SRC}"

  if [[ -d "${SRC}/.git" ]]; then
    git -C "$SRC" remote set-url origin "$url"
    git -C "$SRC" fetch --depth 1 origin "$GIT_REF"
    git -C "$SRC" checkout -f --detach FETCH_HEAD
  else
    rm -rf "$SRC"
    if ! git clone --depth 1 --branch "$GIT_REF" "$url" "$SRC"; then
      rm -rf "$SRC"
      git clone --depth 1 "$url" "$SRC"
      git -C "$SRC" fetch --depth 1 origin "$GIT_REF"
      git -C "$SRC" checkout -f --detach FETCH_HEAD
    fi
  fi

  if ! has_dockerfiles "$SRC"; then
    log "error: clone succeeded but docker/php/Dockerfile.platform is missing in ${SRC}"
    exit 1
  fi
}

build_one() {
  local tag="$1"
  local dockerfile="$2"
  local context="$3"
  shift 3
  local -a extra_args=("$@")

  log "Building ${tag} from ${context} (-f ${dockerfile})"

  if [[ ! -d "$context" ]]; then
    log "error: docker build context is not a directory: ${context}"
    exit 1
  fi
  if [[ ! -f "${context}/${dockerfile}" ]]; then
    log "error: missing ${context}/${dockerfile}"
    exit 1
  fi

  case "${WEBINO_DOCKER_NO_CACHE}" in
    1|true|yes|YES)
      extra_args+=(--no-cache)
      log "Docker --no-cache enabled (WEBINO_DOCKER_NO_CACHE=${WEBINO_DOCKER_NO_CACHE})"
      ;;
  esac

  if docker buildx version >/dev/null 2>&1; then
    docker buildx build --load \
      "${extra_args[@]}" \
      -t "${tag}" \
      -f "${context}/${dockerfile}" \
      "${context}"
  else
    docker build \
      "${extra_args[@]}" \
      -t "${tag}" \
      -f "${context}/${dockerfile}" \
      "${context}"
  fi
}

CONTEXT=""
FORCE_GIT=0
case "${WEBINO_FORCE_GIT}" in
  1|true|yes|YES) FORCE_GIT=1 ;;
esac

if [[ "$FORCE_GIT" -eq 1 ]]; then
  log "Force git sync (WEBINO_FORCE_GIT=${WEBINO_FORCE_GIT}); ignoring WEBINO_DASHBOARD_PATH"
  sync_from_git
  CONTEXT="$SRC"
elif has_dockerfiles "${WEBINO_DASHBOARD_PATH:-}"; then
  CONTEXT="$(cd "${WEBINO_DASHBOARD_PATH}" && pwd)"
  log "Using local dashboard path (skips git): ${CONTEXT}"
else
  sync_from_git
  CONTEXT="$SRC"
fi

log "Dashboard source: ${CONTEXT}"
if [[ -d "${CONTEXT}/.git" ]] && command -v git >/dev/null 2>&1; then
  HEAD_SHA="$(git -C "$CONTEXT" rev-parse --short HEAD 2>/dev/null || true)"
  HEAD_FULL="$(git -C "$CONTEXT" rev-parse HEAD 2>/dev/null || true)"
  log "Dashboard HEAD=${HEAD_SHA:-unknown} (${HEAD_FULL:-unknown})"
else
  log "Dashboard HEAD=<no .git in context>"
fi

backend_args=(
  --build-arg "PHP_IMAGE=${PHP_IMAGE}"
  --build-arg "COMPOSER_IMAGE=${COMPOSER_IMAGE}"
)
if [[ -n "${COMPOSER_MIRROR}" ]]; then
  backend_args+=(--build-arg "COMPOSER_MIRROR=${COMPOSER_MIRROR}")
fi

build_one webino-backend:latest docker/php/Dockerfile.platform "$CONTEXT" \
  "${backend_args[@]}"

build_one webino-next:latest docker/next/Dockerfile "$CONTEXT" \
  --build-arg "NODE_IMAGE=${NODE_IMAGE}"

# Optional channel tag (beta). latest is always built; channel tag is an additional tag.
IMAGE_TAG="${WEBINO_IMAGE_TAG:-}"
if [[ -n "$IMAGE_TAG" && "$IMAGE_TAG" != "latest" ]]; then
  docker tag webino-backend:latest "webino-backend:${IMAGE_TAG}"
  docker tag webino-next:latest "webino-next:${IMAGE_TAG}"
  log "OK: webino-backend:${IMAGE_TAG} and webino-next:${IMAGE_TAG} (also :latest)"
else
  log "OK: webino-backend:latest and webino-next:latest"
fi
