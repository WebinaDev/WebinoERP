# Observability guide

WebinoERM observability stack (phases 16–17):

## Health endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /up` | Laravel built-in liveness (Docker / load balancers) |
| `GET /api/v1/core/health/readiness` | DB + Redis + queue checks |
| `GET /api/v1/core/health/metrics` | Lightweight app metrics JSON |

## Logging

- Application logs: `storage/logs/laravel.log`
- Structured fields on system logs: `severity`, `error_code`, `ip`, `user_agent` (`SystemLogger`)
- **Telescope** (local only): install dev dependency, enabled when `APP_ENV=local`

## Performance artifacts (CI)

- `frontend/perf-summary.json` — Lighthouse LCP/TTFB
- `frontend/api-perf-summary.json` — API p95 smoke on 10 endpoints

## Optional Sentry

Set `SENTRY_LARAVEL_DSN` in `.env` to enable error reporting (hook in `AppServiceProvider` when package installed).

## Monitoring checklist

1. Alert on `/api/v1/core/health/readiness` returning non-200 in production
2. Track queue depth via readiness `queue.pending`
3. Review weekly `crm:recompute-lead-scores` and `backup:run` scheduler logs
