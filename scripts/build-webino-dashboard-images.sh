#!/usr/bin/env bash
# Build published WebinoDashboard images used by LocalSameVpsProvisioner / remote compose.
# Usage (from anywhere):
#   ./scripts/build-webino-dashboard-images.sh
# Or:
#   WEBINO_DASHBOARD_PATH=/path/to/WebinoDashboard ./scripts/build-webino-dashboard-images.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ERP_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [[ -n "${WEBINO_DASHBOARD_PATH:-}" ]]; then
  DASHBOARD_ROOT="$(cd "${WEBINO_DASHBOARD_PATH}" && pwd)"
elif [[ -d "${ERP_ROOT}/../WebinoDashboard" ]]; then
  DASHBOARD_ROOT="$(cd "${ERP_ROOT}/../WebinoDashboard" && pwd)"
else
  echo "error: WebinoDashboard not found. Set WEBINO_DASHBOARD_PATH or place it at ../WebinoDashboard relative to WebinoERP." >&2
  exit 1
fi

echo "Building from: ${DASHBOARD_ROOT}"
cd "${DASHBOARD_ROOT}"

docker build -t webino-backend:latest -f docker/php/Dockerfile.platform .
docker build -t webino-next:latest -f docker/next/Dockerfile .

echo "OK: webino-backend:latest and webino-next:latest"
