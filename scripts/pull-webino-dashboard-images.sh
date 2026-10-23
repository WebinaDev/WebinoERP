#!/usr/bin/env bash
# Drop-in alternative to build-webino-dashboard-images.sh: pull the tenant images
# CI already built in WebinaDev/WebinoDashboard and retag them to the local names
# tenant stacks use (webino-backend:latest, webino-next:latest). No build on the server.
#   WEBINO_DASHBOARD_IMAGE_TAG=<sha>|latest   (default latest)
#   WEBINO_IMAGE_TAG=beta                     optional extra channel tag (same as build script)
set -euo pipefail
REG="${IMAGE_REGISTRY:-ghcr.io/webinadev}"
TAG="${WEBINO_DASHBOARD_IMAGE_TAG:-latest}"
docker pull "${REG}/webino-dashboard-backend:${TAG}"
docker pull "${REG}/webino-dashboard-frontend:${TAG}"
docker tag "${REG}/webino-dashboard-backend:${TAG}" webino-backend:latest
docker tag "${REG}/webino-dashboard-frontend:${TAG}" webino-next:latest
if [[ -n "${WEBINO_IMAGE_TAG:-}" && "${WEBINO_IMAGE_TAG}" != latest ]]; then
  docker tag webino-backend:latest "webino-backend:${WEBINO_IMAGE_TAG}"
  docker tag webino-next:latest "webino-next:${WEBINO_IMAGE_TAG}"
fi
echo "OK: webino-backend:latest and webino-next:latest from ${REG} (${TAG})" >&2
