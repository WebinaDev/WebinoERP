#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "[webinoerp] Backend not installed. Run composer install in backend/ first." >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "[webinoerp] Missing backend/.env." >&2
  exit 1
fi

# Docker env_file injects APP_KEY= (empty) which overrides Laravel's .env file.
# Always materialize a real key in both the file and this process.
if ! grep -q '^APP_KEY=base64:' .env || [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:" ]; then
  KEY="$(php -r 'echo base64_encode(random_bytes(32));' 2>/dev/null || true)"
  if [ -z "${KEY}" ]; then
    KEY="$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  fi
  if grep -q '^APP_KEY=' .env; then
    sed -i "s|^APP_KEY=.*|APP_KEY=base64:${KEY}|" .env
  else
    printf '\nAPP_KEY=base64:%s\n' "${KEY}" >> .env
  fi
  export APP_KEY="base64:${KEY}"
  echo "[webinoerp] Generated APP_KEY"
fi

exec "$@"
