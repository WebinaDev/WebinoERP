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
    echo "error: git is required to clone ${GIT_URL}" >&2
    exit 1
  fi

  mkdir -p "$(dirname "$SRC")"
  export GIT_TERMINAL_PROMPT=0
  local url
  url="$(authenticated_url "$GIT_URL")"

  echo "Syncing ${GIT_URL} (${GIT_REF}) → ${SRC}"

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
    echo "error: clone succeeded but docker/php/Dockerfile.platform is missing in ${SRC}" >&2
    exit 1
  fi
}

resolve_source() {
  if has_dockerfiles "${WEBINO_DASHBOARD_PATH:-}"; then
    (cd "${WEBINO_DASHBOARD_PATH}" && pwd)
    return
  fi
  sync_from_git
  (cd "$SRC" && pwd)
}

build_one() {
  local tag="$1"
  local dockerfile="$2"
  local context="$3"

  echo "Building ${tag} from ${context} (-f ${dockerfile})"

  if docker buildx version >/dev/null 2>&1; then
    docker buildx build --load -t "${tag}" -f "${context}/${dockerfile}" "${context}"
  else
    docker build -t "${tag}" -f "${context}/${dockerfile}" "${context}"
  fi
}

CONTEXT="$(resolve_source)"
echo "Dashboard source: ${CONTEXT}"

build_one webino-backend:latest docker/php/Dockerfile.platform "$CONTEXT"
build_one webino-next:latest docker/next/Dockerfile "$CONTEXT"

echo "OK: webino-backend:latest and webino-next:latest"
