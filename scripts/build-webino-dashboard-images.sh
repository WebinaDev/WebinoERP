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
set -euo pipefail

GIT_URL="${WEBINO_DASHBOARD_GIT_URL:-https://github.com/Webinadev/WebinoDashboard.git}"
GIT_REF="${WEBINO_DASHBOARD_GIT_REF:-main}"
SRC="${WEBINO_DASHBOARD_SRC:-/var/lib/webino/src/WebinoDashboard}"

log() { echo "$*" >&2; }

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

  log "Building ${tag} from ${context} (-f ${dockerfile})"

  if [[ ! -d "$context" ]]; then
    log "error: docker build context is not a directory: ${context}"
    exit 1
  fi
  if [[ ! -f "${context}/${dockerfile}" ]]; then
    log "error: missing ${context}/${dockerfile}"
    exit 1
  fi

  if docker buildx version >/dev/null 2>&1; then
    docker buildx build --load -t "${tag}" -f "${context}/${dockerfile}" "${context}"
  else
    docker build -t "${tag}" -f "${context}/${dockerfile}" "${context}"
  fi
}

CONTEXT=""
if has_dockerfiles "${WEBINO_DASHBOARD_PATH:-}"; then
  CONTEXT="$(cd "${WEBINO_DASHBOARD_PATH}" && pwd)"
else
  sync_from_git
  CONTEXT="$SRC"
fi

log "Dashboard source: ${CONTEXT}"

build_one webino-backend:latest docker/php/Dockerfile.platform "$CONTEXT"
build_one webino-next:latest docker/next/Dockerfile "$CONTEXT"

# Optional channel tag (beta). latest is always built; channel tag is an additional tag.
IMAGE_TAG="${WEBINO_IMAGE_TAG:-}"
if [[ -n "$IMAGE_TAG" && "$IMAGE_TAG" != "latest" ]]; then
  docker tag webino-backend:latest "webino-backend:${IMAGE_TAG}"
  docker tag webino-next:latest "webino-next:${IMAGE_TAG}"
  log "OK: webino-backend:${IMAGE_TAG} and webino-next:${IMAGE_TAG} (also :latest)"
else
  log "OK: webino-backend:latest and webino-next:latest"
fi
