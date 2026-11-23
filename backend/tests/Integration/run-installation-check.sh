#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PROJECT_ROOT="$(dirname "$ROOT")"
NETWORK=webino-install-validation-1123
CONTAINER=webino-install-validation-db-1123
IMAGE="${WEBINO_TEST_BACKEND_IMAGE:-ghcr.io/webinadev/webino-erp-backend:fcf9425e1b4ec1b4d2ff7851994db532bd5e0f0e}"
TEST_DIR="$(mktemp -d)"
NETWORK_CREATED=0
CONTAINER_CREATED=0
cleanup() {
  if [ "$CONTAINER_CREATED" = 1 ]; then docker stop "$CONTAINER" >/dev/null || true; fi
  if [ "$NETWORK_CREATED" = 1 ]; then docker network rm "$NETWORK" >/dev/null || true; fi
  rm -rf -- "$TEST_DIR"
}
trap cleanup EXIT
mkdir -p "$TEST_DIR/storage/framework/cache/data" "$TEST_DIR/storage/framework/sessions" "$TEST_DIR/storage/framework/views" "$TEST_DIR/storage/logs"
chmod -R 777 "$TEST_DIR/storage"
docker network create --internal "$NETWORK" >/dev/null
NETWORK_CREATED=1
docker run -d --rm --name "$CONTAINER" --network "$NETWORK" -e POSTGRES_PASSWORD=isolated_test_password -e POSTGRES_USER=webino_test -e POSTGRES_DB=webino_install_test_3 postgres:15-alpine >/dev/null
CONTAINER_CREATED=1
for _ in $(seq 1 60); do
  if docker exec "$CONTAINER" pg_isready -h 127.0.0.1 -U webino_test -d webino_install_test_3 >/dev/null 2>&1; then break; fi
  sleep 1
done
compose_cli() {
  [ "$1" = exec ] && [ "$2" = -T ] && [ "$3" = db ] || return 1
  shift 3
  docker exec -i "$CONTAINER" "$@"
}
log() { :; }
DB_USER=webino_test
DB_NAME=webino_install_test_3
source "$PROJECT_ROOT/scripts/prepare-installation-state.sh"
prepare_installation_state >/dev/null
[ "$INSTALL_PENDING" = 1 ]
prepare_installation_state >/dev/null
[ "$INSTALL_PENDING" = 1 ]
docker exec "$CONTAINER" psql -v ON_ERROR_STOP=1 -U webino_test -d postgres -c 'CREATE DATABASE webino_install_existing_test' >/dev/null
docker exec "$CONTAINER" psql -v ON_ERROR_STOP=1 -U webino_test -d webino_install_existing_test -c 'CREATE TABLE existing_data (id integer PRIMARY KEY); INSERT INTO existing_data VALUES (1)' >/dev/null
DB_NAME=webino_install_existing_test
prepare_installation_state >/dev/null
[ "$INSTALL_PENDING" = 0 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d "$DB_NAME" -tAc "SELECT count(*) FROM existing_data")" = 1 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d "$DB_NAME" -tAc "SELECT to_regclass('public.webino_installation_state') IS NULL")" = t ]
DB_NAME=webino_install_test_3
TEST_DB="$DB_NAME"
run_php() {
  docker run --rm --network "$NETWORK" -v "$ROOT:/app:ro" -v "$TEST_DIR/storage:/app/storage" --tmpfs /app/bootstrap/cache:rw -w /app \
    -e WEBINO_INSTALL_TEST=1 -e APP_ENV=production -e APP_KEY=base64:2fl+KtvkdphvQyVjU5vYjM5k8h8pV1o2nJ8xQ0wR4sE= \
    -e DB_CONNECTION=pgsql -e DB_HOST="$CONTAINER" -e DB_PORT=5432 -e DB_DATABASE="$TEST_DB" \
    -e DB_USERNAME=webino_test -e DB_PASSWORD=isolated_test_password -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
    --entrypoint php "$IMAGE" "$@"
}
run_php artisan migrate --force --no-interaction >/dev/null
run_php artisan site-builder:assert-schema >/dev/null
run_php tests/Integration/installation-check.php
# Exercise pending legacy migrations on a populated isolated database.
docker exec -i "$CONTAINER" psql -v ON_ERROR_STOP=1 -U webino_test -d webino_install_test_3 <<'SQL' >/dev/null
ALTER TABLE authz_role_permissions ALTER COLUMN effect TYPE varchar(64);
UPDATE webino_dashboard_features SET default_enabled = false WHERE slug = 'link-hub';
UPDATE webino_business_type_features SET default_selected = false WHERE feature_id = (SELECT id FROM webino_dashboard_features WHERE slug = 'link-hub');
DELETE FROM roles WHERE name = 'hr_manager' AND guard_name = 'web';
DELETE FROM webino_business_types WHERE slug = 'blog';
DELETE FROM migrations WHERE migration IN (
 '2026_04_24_000002_seed_phase1_roles',
 '2026_10_03_190000_seed_hr_manager_role',
 '2026_10_23_120000_seed_blog_resume_site_types',
 '2026_10_26_120000_add_link_hub_dashboard_feature',
 '2026_10_26_130000_mark_builtin_dashboard_features',
 '2026_11_23_000001_expand_approval_effect'
);
SQL
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT default_enabled FROM webino_dashboard_features WHERE slug='link-hub'")" = f ]
ROLE_PERMISSIONS_BEFORE="$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT md5(COALESCE(string_agg(permission_id::text, ',' ORDER BY permission_id), '')) FROM role_has_permissions WHERE role_id=(SELECT id FROM roles WHERE name='system_manager' AND guard_name='web')")"
run_php artisan migrate --force --no-interaction >"$TEST_DIR/replay.log"
run_php artisan core:upgrade-legacy-installation --no-interaction
run_php artisan core:upgrade-legacy-installation --no-interaction
run_php artisan site-builder:assert-schema >/dev/null
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM roles WHERE name='hr_manager' AND guard_name='web'")" = 1 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM role_has_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.name='hr_manager' AND r.guard_name='web' AND p.name='hrm.ess.view'")" = 1 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM webino_business_types WHERE slug='blog'")" = 1 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM webino_packages WHERE sku='pkg-blog-starter'")" = 1 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT md5(COALESCE(string_agg(permission_id::text, ',' ORDER BY permission_id), '')) FROM role_has_permissions WHERE role_id=(SELECT id FROM roles WHERE name='system_manager' AND guard_name='web')")" = "$ROLE_PERMISSIONS_BEFORE" ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM core_legacy_upgrades WHERE completed_at IS NULL")" = 0 ]
docker exec -i "$CONTAINER" psql -v ON_ERROR_STOP=1 -U webino_test -d webino_install_test_3 <<'SQL' >/dev/null
UPDATE core_legacy_upgrades SET completed_at = NULL WHERE name = 'site_types';
UPDATE webino_business_categories SET slug = 'test_missing_category' WHERE slug = 'site_types';
SQL
if run_php artisan core:upgrade-legacy-installation --no-interaction >/dev/null 2>&1; then
  echo 'Legacy upgrade accepted a missing prerequisite.' >&2
  exit 1
fi
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM core_legacy_upgrades WHERE name='site_types' AND completed_at IS NULL")" = 1 ]
docker exec "$CONTAINER" psql -v ON_ERROR_STOP=1 -U webino_test -d webino_install_test_3 -c "UPDATE webino_business_categories SET slug='site_types' WHERE slug='test_missing_category'" >/dev/null
run_php artisan core:upgrade-legacy-installation --no-interaction
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM core_legacy_upgrades WHERE completed_at IS NULL")" = 0 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT character_maximum_length FROM information_schema.columns WHERE table_schema='public' AND table_name='authz_role_permissions' AND column_name='effect'")" = 64 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT value FROM system_settings WHERE key='brand_name'")" = test_custom_brand ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT default_enabled FROM webino_dashboard_features WHERE slug='link-hub'")" = f ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc "SELECT count(*) FROM webino_business_type_features WHERE feature_id = (SELECT id FROM webino_dashboard_features WHERE slug='link-hub') AND default_selected")" = 0 ]
for operation in 'db:seed' 'migrate:fresh' 'migrate:refresh' 'migrate:reset' 'migrate:rollback' 'db:wipe'; do
  if run_php artisan "$operation" --force --no-interaction >/dev/null 2>&1; then
    echo "Production guard failed for $operation" >&2
    exit 1
  fi
done
if run_php artisan migrate --seed --force --no-interaction >/dev/null 2>&1; then
  echo 'Production guard failed for migrate --seed' >&2
  exit 1
fi
[ "$(docker exec "$CONTAINER" psql -U webino_test -d webino_install_test_3 -tAc 'SELECT count(*) FROM users')" = 1 ]
echo 'Production command guards passed.'

docker exec "$CONTAINER" psql -v ON_ERROR_STOP=1 -U webino_test -d postgres -c 'CREATE DATABASE webino_install_terminal_test' >/dev/null
DB_NAME=webino_install_terminal_test
TEST_DB="$DB_NAME"
prepare_installation_state >/dev/null
[ "$INSTALL_PENDING" = 1 ]
run_php artisan migrate --force --no-interaction >/dev/null
python3 "$ROOT/tests/Integration/installation-terminal-check.py" docker run --rm -it --network "$NETWORK" \
  -v "$ROOT:/app:ro" -v "$TEST_DIR/storage:/app/storage" --tmpfs /app/bootstrap/cache:rw -w /app \
  -e APP_ENV=production -e APP_LOCALE=en -e APP_KEY=base64:2fl+KtvkdphvQyVjU5vYjM5k8h8pV1o2nJ8xQ0wR4sE= \
  -e DB_CONNECTION=pgsql -e DB_HOST="$CONTAINER" -e DB_PORT=5432 -e DB_DATABASE="$TEST_DB" \
  -e DB_USERNAME=webino_test -e DB_PASSWORD=isolated_test_password -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
  --entrypoint php "$IMAGE" artisan core:initialize-installation
[ "$(docker exec "$CONTAINER" psql -U webino_test -d "$TEST_DB" -tAc "SELECT count(*) FROM users WHERE email='test2@example.local'")" = 1 ]
[ "$(docker exec "$CONTAINER" psql -U webino_test -d "$TEST_DB" -tAc "SELECT status FROM webino_installation_state WHERE id=1")" = completed ]
