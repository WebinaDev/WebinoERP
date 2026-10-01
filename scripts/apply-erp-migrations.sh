# Sourced by update.sh and install.sh. Requires compose_cli() and log().
# migrate --force exits 0 only when the command itself succeeds; it does not
# prove every file ran. site-builder:assert-schema exits non-zero when any
# migration is still Pending or webino_site_provisions.progress is missing.
apply_erp_migrations() {
  log "Applying migrations (php artisan migrate --force)"
  if ! compose_cli exec -T backend php artisan migrate --force; then
    echo "ERROR: php artisan migrate --force failed." >&2
    echo "Site launch writes webino_site_provisions.progress. Until migrations apply, «ایجاد سایت» leaves the site draft." >&2
    compose_cli logs --tail=80 backend >&2 || true
    exit 1
  fi

  log "Verifying no pending migrations and webino_site_provisions.progress exists"
  if ! compose_cli exec -T backend php artisan site-builder:assert-schema; then
    echo "ERROR: database schema is still behind the code after migrate --force." >&2
    echo "Pending migrations were not applied, or column webino_site_provisions.progress is missing" >&2
    echo "(migration 2026_09_04_163000_add_progress_to_webino_site_provisions)." >&2
    echo "Launch will fail and the provision stays draft. Fix the error above and re-run update.sh." >&2
    compose_cli exec -T backend php artisan migrate:status --pending >&2 || true
    exit 1
  fi
}
