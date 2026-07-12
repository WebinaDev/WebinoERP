#!/usr/bin/env node
/**
 * Parse Lighthouse CI JSON output and fail when LCP/TTFB budgets are exceeded.
 */
import fs from 'fs';
import path from 'path';

const LCP_BUDGET_MS = 2500;
const TTFB_BUDGET_MS = 600;
const CI = Boolean(process.env.CI);
const outDir = path.resolve(process.cwd(), '.lighthouseci');

function readJsonFiles(dir) {
  if (!fs.existsSync(dir)) {
    console.warn('perf-budget: no .lighthouseci directory — skipping');
    process.exit(0);
  }
  const files = fs.readdirSync(dir).filter((f) => f.endsWith('.json'));
  return files.map((f) => JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8')));
}

const reports = readJsonFiles(outDir);
let failed = false;
const summary = {
  generatedAt: new Date().toISOString(),
  lcp_budget_ms: LCP_BUDGET_MS,
  ttfb_budget_ms: TTFB_BUDGET_MS,
  ci: CI,
  urls: [],
  passed: true,
};

for (const report of reports) {
  const url = report?.requestedUrl ?? report?.finalUrl ?? 'unknown';
  const lcp = report?.audits?.['largest-contentful-paint']?.numericValue;
  const ttfb = report?.audits?.['server-response-time']?.numericValue;
  const entry = { url, lcp_ms: null, ttfb_ms: null, lcp_ok: null, ttfb_ok: null };

  if (typeof lcp === 'number') {
    entry.lcp_ms = Math.round(lcp);
    entry.lcp_ok = lcp <= LCP_BUDGET_MS;
    if (!entry.lcp_ok) {
      console.error(`FAIL LCP ${entry.lcp_ms}ms > ${LCP_BUDGET_MS}ms on ${url}`);
      failed = true;
    } else {
      console.log(`OK   LCP ${entry.lcp_ms}ms on ${url}`);
    }
  }

  if (typeof ttfb === 'number') {
    entry.ttfb_ms = Math.round(ttfb);
    entry.ttfb_ok = ttfb <= TTFB_BUDGET_MS;
    if (!entry.ttfb_ok) {
      const msg = `TTFB ${entry.ttfb_ms}ms > ${TTFB_BUDGET_MS}ms on ${url}`;
      if (CI) {
        console.error(`FAIL ${msg}`);
        failed = true;
      } else {
        console.warn(`WARN ${msg}`);
      }
    } else {
      console.log(`OK   TTFB ${entry.ttfb_ms}ms on ${url}`);
    }
  }

  summary.urls.push(entry);
}

summary.passed = !failed;
const summaryPath = path.resolve(process.cwd(), 'perf-summary.json');
fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2) + '\n');
console.log('Wrote', summaryPath);

process.exit(failed ? 1 : 0);
