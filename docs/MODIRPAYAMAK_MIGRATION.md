# ModirPayamak domain numbers migration runbook

## Why

Migration `Modules/Integrations/Database/Migrations/2026_09_13_000001_modirpayamak_numbers_patterns_messages.php` creates:

- `modirpayamak_domain_numbers`
- `modirpayamak_pattern_registry`
- `modirpayamak_messages`

Until it runs, SMS number attachment and pattern registry can raise `SQLSTATE[42P01]`. Phase 11 makes ERP handlers graceful when the table is missing (`domain_numbers_ready: false`) and Dashboard should treat that as «در دسترس نیست» until migrated.

## Run (Docker)

From the WebinoERP repo root (destination behind `WEBINO_BASE_URL`):

```bash
docker compose up -d db redis backend
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan migrate:status | grep modirpayamak
```

Expected: `2026_09_13_000001_modirpayamak_numbers_patterns_messages` is **Ran**.

## Run (host PHP)

```bash
cd backend
php artisan migrate --force --path=Modules/Integrations/Database/Migrations/2026_09_13_000001_modirpayamak_numbers_patterns_messages.php
```

## Verify

```bash
docker compose exec backend php artisan tinker --execute="echo Schema::hasTable('modirpayamak_domain_numbers') ? 'ok' : 'missing';"
```

## Rollback (dev only)

```bash
docker compose exec backend php artisan migrate:rollback --path=Modules/Integrations/Database/Migrations/2026_09_13_000001_modirpayamak_numbers_patterns_messages.php
```
