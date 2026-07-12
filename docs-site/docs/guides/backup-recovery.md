# Backup & recovery

## Automated backups

When `spatie/laravel-backup` is installed:

```bash
cd backend
php artisan backup:run --only-db
```

Scheduled daily at **02:40** via `bootstrap/app.php` (when package present).

Backups are stored under `storage/app/backup/` by default.

## Manual export

```bash
# Database dump (PostgreSQL in Docker)
docker compose exec db pg_dump -U postgres webina_crm > backup.sql

# Application storage
tar -czf storage-backup.tgz backend/storage/app
```

## Restore procedure

1. Stop queue workers and put app in maintenance: `php artisan down`
2. Restore database: `psql -U postgres -d webina_crm < backup.sql`
3. Restore `storage/app` if needed
4. Run migrations if schema drift: `php artisan migrate --force`
5. Clear caches: `php artisan optimize:clear`
6. Bring app up: `php artisan up`

## Disaster recovery notes

- Keep off-site copies of DB dumps and `.env` secrets (not in git)
- Test restore quarterly on staging
- Document RPO/RTO targets per deployment (CI gates are regression-only, not SLA)
