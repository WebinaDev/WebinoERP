#!/usr/bin/env node
/**
 * API p95 latency smoke — regression gate for representative GET endpoints.
 */
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const API_BASE = (process.env.PLAYWRIGHT_API_URL ?? 'http://localhost/api').replace(/\/$/, '');
const BUDGET_MS = Number(process.env.API_P95_BUDGET_MS ?? 300);
const SAMPLES = Number(process.env.API_P95_SAMPLES ?? 5);
const CI = Boolean(process.env.CI);

const ENDPOINTS = [
  { name: 'dashboard', path: '/v1/core/dashboard' },
  { name: 'crm_leads', path: '/v1/crm/leads' },
  { name: 'accounting_journals', path: '/v1/accounting/journals' },
  { name: 'scm_warehouses', path: '/v1/scm/warehouses' },
  { name: 'projects_projects', path: '/v1/projects/projects' },
  { name: 'hrm_employees', path: '/v1/hrm/employees' },
  { name: 'docs_contracts', path: '/v1/docs/contracts' },
  { name: 'marketplace_modules', path: '/v1/marketplace/modules' },
  { name: 'mfg_overview', path: '/v1/mfg/overview' },
  { name: 'health_readiness', path: '/v1/core/health/readiness' },
  { name: 'health_metrics', path: '/v1/core/health/metrics' },
];

function p95(values) {
  if (values.length === 0) return 0;
  const sorted = [...values].sort((a, b) => a - b);
  const idx = Math.ceil(sorted.length * 0.95) - 1;
  return sorted[Math.max(0, idx)];
}

async function login() {
  const res = await fetch(`${API_BASE}/v1/core/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: 'admin@webina.local', password: 'password' }),
  });
  if (!res.ok) {
    throw new Error(`Login failed: ${res.status}`);
  }
  const json = await res.json();
  const token = json?.data?.token;
  if (!token) throw new Error('Login response missing token');
  return token;
}

async function timedGet(url, token, auth = true) {
  const start = performance.now();
  const headers = { Accept: 'application/json' };
  if (auth && token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(url, { headers });
  const ms = performance.now() - start;
  return { status: res.status, ms };
}

async function main() {
  let token;
  try {
    token = await login();
  } catch (err) {
    console.warn('api-p95-smoke: skipping —', err.message);
    if (CI) process.exit(1);
    process.exit(0);
  }

  const results = [];
  let failed = false;

  for (const ep of ENDPOINTS) {
    const url = ep.path.startsWith('/webinocrm/')
      ? `${API_BASE}${ep.path}`
      : `${API_BASE}${ep.path}`;
    const samples = [];

    for (let i = 0; i < SAMPLES; i += 1) {
      const needsAuth = !ep.path.includes('/health/');
      const { status, ms } = await timedGet(url, token, needsAuth);
      samples.push(ms);
      if (status >= 500) {
        console.error(`FAIL ${ep.name}: HTTP ${status} on sample ${i + 1}`);
        failed = true;
      }
    }

    const p95ms = Math.round(p95(samples));
    const avg = Math.round(samples.reduce((a, b) => a + b, 0) / samples.length);
    const ok = p95ms <= BUDGET_MS;
    console.log(`${ok ? 'OK  ' : 'FAIL'} ${ep.name} p95=${p95ms}ms avg=${avg}ms (budget ${BUDGET_MS}ms)`);
    if (!ok) failed = true;

    results.push({ name: ep.name, path: ep.path, p95_ms: p95ms, avg_ms: avg, samples, budget_ms: BUDGET_MS, ok });
  }

  const summary = {
    generatedAt: new Date().toISOString(),
    budget_ms: BUDGET_MS,
    samples: SAMPLES,
    endpoints: results,
    passed: !failed,
  };

  const outPath = resolve(process.cwd(), 'api-perf-summary.json');
  writeFileSync(outPath, JSON.stringify(summary, null, 2) + '\n');
  console.log('Wrote', outPath);

  process.exit(failed ? 1 : 0);
}

main().catch((err) => {
  console.error('api-p95-smoke fatal:', err);
  process.exit(CI ? 1 : 0);
});
