#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
  echo "[webinoerp] Backend not installed. Run composer install in backend/ first." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "[webinoerp] Missing backend/.env." >&2
  exit 1
fi

exec "$@"
