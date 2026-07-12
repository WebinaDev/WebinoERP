#!/usr/bin/env node
/**
 * Cross-check ERP module-registry paths vs dashboard-page-map wiring.
 * Usage: node scripts/audit-routes.mjs [--json] [--fail-on-missing]
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ALLOWLIST_MISSING, ROUTE_INVENTORY } from './route-inventory.mjs';
import { buildManifestFromInventory, suggestTestsStatus } from './manifest-builder.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');
const REPO = join(ROOT, '..');

const args = new Set(process.argv.slice(2));
const asJson = args.has('--json');
const failOnMissing = args.has('--fail-on-missing');

function read(rel) {
  return readFileSync(join(ROOT, rel), 'utf8');
}

function readRepo(rel) {
  return readFileSync(join(REPO, rel), 'utf8');
}

/** Extract menu paths from module-registry.ts */
function parseRegistryPaths(src) {
  const paths = [];
  const idRe = /id:\s*'([^']+)'/g;
  const pathRe = /path:\s*'([^']+)'/g;
  const lines = src.split('\n');
  for (const line of lines) {
    if (!line.includes('path:')) continue;
    const m = line.match(/path:\s*'([^']+)'/);
    if (m && m[1] !== '#') paths.push(m[1]);
  }
  // dashboard items
  paths.push('', 'reports');
  return [...new Set(paths)];
}

function parseExactPageMap(src) {
  const keys = [];
  const re = /^\s*'([^']*)':\s*</gm;
  let m;
  while ((m = re.exec(src))) keys.push(m[1]);
  return keys;
}

function parseFinanceSegments(src) {
  const keys = [];
  const block = src.match(/const FINANCE_PAGES[^=]+=\s*\{([^}]+)\}/s);
  if (!block) return keys;
  const re = /'([^']+)':/g;
  let m;
  while ((m = re.exec(block[1]))) keys.push(m[1]);
  return keys;
}

function parseDetailRoots(src) {
  const block = src.match(/const DETAIL_ROOTS = new Set\(\[([^\]]+)\]/s);
  if (!block) return [];
  return [...block[1].matchAll(/'([^']+)'/g)].map((x) => x[1]);
}

function parseLegacyRedirects(src) {
  const out = [];
  const re = /\{\s*from:\s*'([^']+)',\s*to:\s*'([^']+)'\s*\}/g;
  let m;
  while ((m = re.exec(src))) out.push({ from: m[1], to: m[2] });
  return out;
}

function parseWebinocrmRoutes(src) {
  const paths = [];
  const re = /paths:\s*\[([^\]]+)\]/g;
  let m;
  while ((m = re.exec(src))) {
    const inner = m[1];
    for (const p of inner.matchAll(/'([^']+)'/g)) {
      paths.push(p[1]);
    }
  }
  return [...new Set(paths)];
}

function normalizeRouteKey(route) {
  return route.replace(/^\//, '').replace(/\/$/, '');
}

function isAllowlistedMissing(path) {
  const n = normalizeRouteKey(path);
  if (ALLOWLIST_MISSING.has(n)) return true;
  return false;
}

function main() {
  const registrySrc = read('lib/module-registry.ts');
  const pageMapSrc = read('lib/dashboard-page-map.tsx');
  let webinocrmSrc = '';
  try {
    webinocrmSrc = readRepo('../Wordpress/webinocrm/client/src/routes/routes.config.tsx');
  } catch {
    webinocrmSrc = '';
  }

  const registryPaths = parseRegistryPaths(registrySrc);
  const exactPaths = parseExactPageMap(pageMapSrc);
  const financePaths = parseFinanceSegments(pageMapSrc);
  const detailRoots = parseDetailRoots(pageMapSrc);
  const redirects = parseLegacyRedirects(registrySrc);
  const webinocrmPaths = webinocrmSrc ? parseWebinocrmRoutes(webinocrmSrc) : [];

  const wiredPaths = new Set([...exactPaths, ...financePaths]);
  const inventoryRoutes = ROUTE_INVENTORY.map((r) => normalizeRouteKey(r.route));

  const missingFromPageMap = registryPaths.filter((p) => {
    const n = normalizeRouteKey(p);
    if (!n || n === 'reports') return false;
    if (isAllowlistedMissing(n)) return false;
    if (wiredPaths.has(n)) return false;
    if (n.startsWith('admin/settings')) return false; // dynamic
    return true;
  });

  const missingWebinocrm = webinocrmPaths.filter((p) => {
    const base = p.replace(/:id/g, '1').replace(/:tab\?/g, 'general').replace(/\/\*/g, '');
    const inv = ROUTE_INVENTORY.find((r) => {
      const pat = r.route.replace(/:id/g, '1').replace(/:tab\?/g, 'general');
      return pat === base || r.legacy.includes(p) || r.route === p;
    });
    return !inv || inv.fe === '❌';
  });

  const manifest = buildManifestFromInventory(ROUTE_INVENTORY);
  const inventoryWithTests = ROUTE_INVENTORY.map((row) => ({
    ...row,
    tests: suggestTestsStatus(row, manifest.routes),
  }));

  const report = {
    generatedAt: new Date().toISOString(),
    counts: {
      registryMenuPaths: registryPaths.length,
      pageMapExact: exactPaths.length,
      financeSegments: financePaths.length,
      detailRoots: detailRoots.length,
      legacyRedirects: redirects.length,
      webinocrmReferencePaths: webinocrmPaths.length,
      routeInventoryRows: ROUTE_INVENTORY.length,
      manifestRoutes: manifest.routes.length,
      apiSmokeRoutes: manifest.routes.filter((r) => r.apiSmoke).length,
      e2eAuthRoutes: manifest.routes.filter((r) => r.e2eAuth).length,
      missingFromPageMap,
      webinocrmGaps: missingWebinocrm.length,
    },
    missingFromPageMap,
    detailRoots,
    redirects,
    inventory: inventoryWithTests,
    manifestSummary: {
      generatedAt: manifest.generatedAt,
      routes: manifest.routes.length,
    },
  };

  const outPath = join(REPO, 'PROJECT_STATUS.audit.json');
  writeFileSync(outPath, JSON.stringify(report, null, 2));

  if (asJson) {
    console.log(JSON.stringify(report, null, 2));
  } else {
    console.log('Route audit summary');
    console.log('  Registry menu paths:', report.counts.registryMenuPaths);
    console.log('  Page-map EXACT keys:', report.counts.pageMapExact);
    console.log('  Finance segments:', report.counts.financeSegments);
    console.log('  Legacy redirects:', report.counts.legacyRedirects);
    console.log('  Inventory rows:', report.counts.routeInventoryRows);
    console.log('  Manifest routes:', report.counts.manifestRoutes, '(API smoke:', report.counts.apiSmokeRoutes + ')');
    console.log('  Missing from page-map:', missingFromPageMap.join(', ') || '(none)');
    console.log('  Webinocrm parity gaps:', missingWebinocrm.length);
    console.log('  Wrote', outPath);
  }

  if (failOnMissing && missingFromPageMap.length > 0) {
    console.error('FAIL: registry paths without page-map wiring:', missingFromPageMap);
    process.exit(1);
  }
}

main();
