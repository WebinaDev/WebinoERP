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

# Performance: php -S (artisan serve) is the CLI SAPI, so opcache is off unless
# enable_cli is set. Also run several php -S workers instead of one thread.
PERF_INI=/usr/local/etc/php/conf.d/zz-webino-perf.ini
if [ -w /usr/local/etc/php/conf.d ]; then
  cat > "$PERF_INI" <<'INI'
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=30000
opcache.validate_timestamps=1
opcache.revalidate_freq=10
realpath_cache_size=4096K
realpath_cache_ttl=600
memory_limit=256M
INI
fi

warm_caches() {
  php artisan config:cache >/dev/null 2>&1 || php artisan config:clear >/dev/null 2>&1 || true
  php artisan route:cache >/dev/null 2>&1 || php artisan route:clear >/dev/null 2>&1 || true
  php artisan event:cache >/dev/null 2>&1 || true
}

# Copied frankenphp is missing libwatcher-c.so.0 — Octane dies with exit 127.
# Intercept so even an old compose command still boots.
case " $* " in
  *" octane:start "*|*" octane:start")
    echo "[webinoerp] Skipping Octane/FrankenPHP; starting artisan serve on :8080"
    warm_caches
    export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"
    exec php artisan serve --host=0.0.0.0 --port=8080 --no-reload
    ;;
esac

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[webinoerp] Running migrations (RUN_MIGRATIONS=1)"
  php artisan migrate --force
  echo "[webinoerp] Verifying pending migrations and webino_site_provisions.progress"
  php artisan site-builder:assert-schema
fi

case " $* " in
  *" artisan serve "*)
    warm_caches
    export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"
    # Laravel ignores PHP_CLI_SERVER_WORKERS unless --no-reload is passed.
    case " $* " in *" --no-reload "*) ;; *) set -- "$@" --no-reload ;; esac
    ;;
esac

exec "$@"
