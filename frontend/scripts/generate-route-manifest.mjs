#!/usr/bin/env node
/**
 * Generate routes.manifest.json for PHPUnit matrix + Playwright E2E.
 * Usage: node scripts/generate-route-manifest.mjs
 */
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildManifestFromInventory } from './manifest-builder.mjs';
import { ROUTE_INVENTORY } from './route-inventory.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');
const REPO = join(ROOT, '..');

const manifest = buildManifestFromInventory(ROUTE_INVENTORY);

const feOut = join(ROOT, 'e2e/routes.manifest.json');
const beOut = join(REPO, 'backend/tests/fixtures/routes.manifest.json');

writeFileSync(feOut, JSON.stringify(manifest, null, 2) + '\n');
writeFileSync(beOut, JSON.stringify(manifest, null, 2) + '\n');

console.log(`Generated ${manifest.routes.length} routes`);
console.log('  FE:', feOut);
console.log('  BE:', beOut);
console.log(
  '  API smoke:',
  manifest.routes.filter((r) => r.apiSmoke).length,
  '| E2E auth:',
  manifest.routes.filter((r) => r.e2eAuth).length,
);
