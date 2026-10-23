#!/usr/bin/env bash
# Drop-in alternative to build-webino-dashboard-images.sh: pull the tenant images that
# WebinaDev/WebinoDashboard CI built (same Dockerfiles: docker/php/Dockerfile.platform,
# docker/next/Dockerfile) and retag them to the names tenant stacks use
# (TenantSiteStack: webino-backend:<tag>, webino-next:<tag>). No build on the server.
#
#   WEBINO_DASHBOARD_IMAGE_TAG=<full WebinoDashboard sha>   required (ALLOW_LATEST=1 to use latest)
#   WEBINO_IMAGE_TAG=beta        optional channel tag, also applied (same as the build script)
#   GITHUB_TOKEN/GH_TOKEN        optional: refuse unless the Dashboard images.yml run for that sha is green
# Tenant containers are NOT recreated here; recreate + migrate per site from the ERP panel as today.
set -euo pipefail
log() { echo "[pull-dashboard-images] $*" >&2; }
die() { echo "[pull-dashboard-images] ERROR: $*" >&2; exit 1; }
REG="${IMAGE_REGISTRY:-ghcr.io/webinadev}"
TAG="${WEBINO_DASHBOARD_IMAGE_TAG:-}"
if [[ -z "$TAG" || "$TAG" == latest ]]; then
  [[ "${ALLOW_LATEST:-0}" == 1 ]] || die "set WEBINO_DASHBOARD_IMAGE_TAG=<full WebinoDashboard sha> (or ALLOW_LATEST=1)"
  TAG=latest
elif [[ ! "$TAG" =~ ^[0-9a-f]{40}$ ]]; then
  die "WEBINO_DASHBOARD_IMAGE_TAG must be a full 40-char sha"
fi
TOKEN="${GITHUB_TOKEN:-${GH_TOKEN:-}}"
if [[ "$TAG" != latest && -n "$TOKEN" && "${SKIP_CI_CHECK:-0}" != 1 ]]; then
  res="$(curl -fsS -H "Authorization: Bearer ${TOKEN}" -H 'Accept: application/vnd.github+json' \
    "https://api.github.com/repos/WebinaDev/WebinoDashboard/actions/workflows/images.yml/runs?head_sha=${TAG}&per_page=20")" \
    || die "GitHub API request failed"
  grep -q '"conclusion": *"success"' <<<"$res" || die "no successful images.yml run for ${TAG}"
  log "CI run is green"
fi
docker pull "${REG}/webino-dashboard-backend:${TAG}"
docker pull "${REG}/webino-dashboard-frontend:${TAG}"
docker tag "${REG}/webino-dashboard-backend:${TAG}" webino-backend:latest
docker tag "${REG}/webino-dashboard-frontend:${TAG}" webino-next:latest
if [[ -n "${WEBINO_IMAGE_TAG:-}" && "${WEBINO_IMAGE_TAG}" != latest ]]; then
  docker tag webino-backend:latest "webino-backend:${WEBINO_IMAGE_TAG}"
  docker tag webino-next:latest "webino-next:${WEBINO_IMAGE_TAG}"
fi
log "OK: webino-backend / webino-next <- ${REG} (${TAG})"
