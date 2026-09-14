#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "[webinoerm] Backend vendor missing in image." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "[webinoerm] Missing .env mount." >&2
  exit 1
fi

mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch database/database.sqlite 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[webinoerm] Running migrations (RUN_MIGRATIONS=1)"
  php artisan migrate --force
fi

# Copied frankenphp is missing libwatcher-c.so.0 — Octane dies with exit 127.
# Same intercept as docker/php/entrypoint.sh (main compose).
case " $* " in
  *" octane:start "*|*" octane:start")
    echo "[webinoerm] Skipping Octane/FrankenPHP; starting artisan serve on :8080"
    exec php artisan serve --host=0.0.0.0 --port=8080
    ;;
esac

exec "$@"
