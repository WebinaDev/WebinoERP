#!/usr/bin/env bash
# Drop-in alternative to build-webina-studio-images.sh: pull the tenant images that
# «وبینا استودیو» (Webina Studio, formerly WebinoDashboard) CI built (same Dockerfiles:
# docker/php/Dockerfile.platform, docker/next/Dockerfile) and retag them to the names tenant
# stacks use (TenantSiteStack: webino-backend:<tag>, webino-next:<tag>; unchanged). No build on the server.
#
# Registry names: webina-studio-backend / webina-studio-next are tried first, then the
# legacy webino-dashboard-backend / webino-dashboard-frontend.
#
#   WEBINA_STUDIO_IMAGE_TAG=<full Webina Studio sha>   required (ALLOW_LATEST=1 to use latest)
#                                (legacy WEBINO_DASHBOARD_IMAGE_TAG still accepted)
#   WEBINO_IMAGE_TAG=beta        optional channel tag, also applied (same as the build script)
#   GITHUB_TOKEN/GH_TOKEN        optional: refuse unless the Dashboard images.yml run for that sha is green
# Tenant containers are NOT recreated here; recreate + migrate per site from the ERP panel as today.
set -euo pipefail
log() { echo "[pull-webina-studio-images] $*" >&2; }
die() { echo "[pull-webina-studio-images] ERROR: $*" >&2; exit 1; }
REG="${IMAGE_REGISTRY:-ghcr.io/webinadev}"
TAG="${WEBINA_STUDIO_IMAGE_TAG:-${WEBINO_DASHBOARD_IMAGE_TAG:-}}"
if [[ -z "$TAG" || "$TAG" == latest ]]; then
  [[ "${ALLOW_LATEST:-0}" == 1 ]] || die "set WEBINA_STUDIO_IMAGE_TAG=<full Webina Studio sha> (or ALLOW_LATEST=1)"
  TAG=latest
elif [[ ! "$TAG" =~ ^[0-9a-f]{40}$ ]]; then
  die "WEBINA_STUDIO_IMAGE_TAG must be a full 40-char sha"
fi
TOKEN="${GITHUB_TOKEN:-${GH_TOKEN:-}}"
if [[ "$TAG" != latest && -n "$TOKEN" && "${SKIP_CI_CHECK:-0}" != 1 ]]; then
  res=""
  for repo in webina-studio WebinoDashboard; do
    res="$(curl -fsS -H "Authorization: Bearer ${TOKEN}" -H 'Accept: application/vnd.github+json' \
      "https://api.github.com/repos/WebinaDev/${repo}/actions/workflows/images.yml/runs?head_sha=${TAG}&per_page=20")" && break
    res=""
  done
  [[ -n "$res" ]] || die "GitHub API request failed"
  grep -q '"conclusion": *"success"' <<<"$res" || die "no successful images.yml run for ${TAG}"
  log "CI run is green"
fi
pull_first() {
  local name
  for name in "$@"; do
    if docker pull "${REG}/${name}:${TAG}" >&2; then
      printf '%s' "${REG}/${name}:${TAG}"
      return 0
    fi
    log "not found: ${REG}/${name}:${TAG}"
  done
  return 1
}
BACKEND_IMG="$(pull_first webina-studio-backend webino-dashboard-backend)" || die "no backend image for ${TAG}"
FRONTEND_IMG="$(pull_first webina-studio-next webina-studio-frontend webino-dashboard-frontend)" || die "no frontend image for ${TAG}"
docker tag "$BACKEND_IMG" webino-backend:latest
docker tag "$FRONTEND_IMG" webino-next:latest
if [[ -n "${WEBINO_IMAGE_TAG:-}" && "${WEBINO_IMAGE_TAG}" != latest ]]; then
  docker tag webino-backend:latest "webino-backend:${WEBINO_IMAGE_TAG}"
  docker tag webino-next:latest "webino-next:${WEBINO_IMAGE_TAG}"
fi
log "OK: webino-backend <- ${BACKEND_IMG}; webino-next <- ${FRONTEND_IMG}"
