# Testing guide

This guide covers automated tests, route manifest generation, and performance regression gates for WebinoERM.

## PHPUnit (backend)

From `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer test
```

Run only the API route matrix (fast smoke across manifest endpoints):

```bash
vendor/bin/phpunit --group matrix
```

### Route matrix

`tests/Feature/ApiRouteMatrixTest.php` reads `tests/fixtures/routes.manifest.json` and performs authenticated GET smoke checks for every manifest entry with `apiSmoke`. Module-specific Feature tests (CRM deals, HRM CRUD, SCM flows, etc.) remain the source of truth for behaviour.

## Playwright (frontend E2E)

From `frontend/` with the Docker stack running (`docker compose up` at repo root):

```bash
npm ci
npx playwright install chromium
npm run test:e2e
```

Key specs:

| Spec | Purpose |
|------|---------|
| `e2e/smoke.spec.ts` | Public shell smoke |
| `e2e/phase3.spec.ts` | Unauthenticated redirects |
| `e2e/phase5-auth.spec.ts` | Deep authenticated flows (forms, tables) |
| `e2e/route-matrix.spec.ts` | Parametrised smoke for ~84 authenticated dashboard routes |

Auth storage state is seeded in `e2e/global-setup.ts` using `admin@webina.local` / `password`.

## Route manifest

Single source: `frontend/scripts/route-inventory.mjs` → generated manifest:

```bash
cd frontend
npm run generate:routes
```

Outputs:

- `frontend/e2e/routes.manifest.json` (Playwright)
- `backend/tests/fixtures/routes.manifest.json` (PHPUnit)

Regenerate after inventory changes, then commit both JSON files.

## Route audit

```bash
cd frontend
npm run audit:status          # human summary + PROJECT_STATUS.audit.json
npm run audit:status:strict   # fails on page-map drift (CI)
```

The audit report derives the **Tests** column from manifest coverage (`✅` = Feature + matrix, `🟡` = matrix/E2E smoke only).

## Performance budgets

### Lighthouse (LCP + TTFB)

CI job `lighthouse` runs against login, dashboard, and representative module pages. Budgets enforced by `scripts/perf-budget.mjs`:

| Metric | Budget | CI |
|--------|--------|-----|
| LCP | ≤ 2500 ms | fail |
| TTFB | ≤ 600 ms | fail |

Local runs warn on TTFB exceedance; CI fails.

```bash
cd frontend
npm run build && npm run start -- -p 3000 &
npx @lhci/cli autorun --config=lighthouserc.json
node scripts/perf-budget.mjs
```

Summary artifact: `frontend/perf-summary.json`

### API p95 smoke

After E2E stack is up in CI, `scripts/api-p95-smoke.mjs` measures p95 latency on 10 representative GET endpoints (5 samples each):

```bash
PLAYWRIGHT_API_URL=http://localhost/api API_P95_BUDGET_MS=300 npm run test:api-p95
```

Summary artifact: `frontend/api-perf-summary.json`

Threshold is a **regression gate** for Docker/SQLite CI, not a production SLA.

## CI overview

`.github/workflows/ci.yml`:

- **backend** — PHPUnit (full suite + matrix)
- **frontend** — build, i18n, strict route audit
- **frontend-e2e** — Playwright + API p95 smoke
- **lighthouse** — LCP/TTFB gates via `perf-budget.mjs`
- **docs** — Docusaurus build

## i18n parity

```bash
cd frontend && npm run check:i18n
```

## OpenAPI freshness

```bash
cd backend && composer export-openapi
git diff --exit-code ../docs-site/openapi/openapi.json
```
