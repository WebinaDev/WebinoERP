#!/usr/bin/env node
/**
 * Verifies fa/en key parity and flags likely hardcoded UI strings in features/modules.
 */
import { readFileSync, readdirSync, statSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');
const messagesDir = join(ROOT, 'messages');
const modulesDir = join(ROOT, 'features', 'modules');

const STRICT_LITERALS = process.argv.includes('--strict-literals');
const PERSIAN_RE = /[\u0600-\u06FF]{3,}/;
const ENGLISH_UI_RE = />\s*[A-Za-z][A-Za-z\s,'-]{4,}\s*</;
const SKIP_FILES = ['types.ts', 'config.ts', 'constants.ts', 'schema.ts', 'index.ts'];
const SKIP_DIRS = ['hooks'];

function flattenKeys(obj, prefix = '') {
  const keys = [];
  for (const [k, v] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${k}` : k;
    if (v && typeof v === 'object' && !Array.isArray(v)) {
      keys.push(...flattenKeys(v, path));
    } else {
      keys.push(path);
    }
  }
  return keys.sort();
}

function walk(dir, acc = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    const st = statSync(full);
    if (st.isDirectory()) {
      walk(full, acc);
    } else if (/\.(tsx|ts)$/.test(name) && !name.includes('.test.')) {
      acc.push(full);
    }
  }
  return acc;
}

function shouldSkip(filePath) {
  const rel = filePath.replace(ROOT + '/', '');
  const base = filePath.split('/').pop() ?? '';
  if (SKIP_FILES.some((s) => base.endsWith(s) || base === s)) return true;
  if (SKIP_DIRS.some((d) => rel.includes(`/${d}/`))) return true;
  if (/Provider\.tsx$/.test(base) || base === 'FinanceSection.tsx' || base === 'status-badge.tsx') return true;
  return false;
}

const fa = JSON.parse(readFileSync(join(messagesDir, 'fa.json'), 'utf8'));
const en = JSON.parse(readFileSync(join(messagesDir, 'en.json'), 'utf8'));

const faKeys = flattenKeys(fa);
const enKeys = flattenKeys(en);

const missingInEn = faKeys.filter((k) => !enKeys.includes(k));
const missingInFa = enKeys.filter((k) => !faKeys.includes(k));

let failed = false;

if (missingInEn.length || missingInFa.length) {
  console.error('i18n key mismatch:');
  if (missingInEn.length) console.error('  Missing in en.json:', missingInEn.slice(0, 20));
  if (missingInFa.length) console.error('  Missing in fa.json:', missingInFa.slice(0, 20));
  failed = true;
} else {
  console.log(`i18n OK: ${faKeys.length} keys in parity`);
}

if (STRICT_LITERALS) {
  const moduleFiles = walk(modulesDir).filter((f) => !shouldSkip(f));
  const withoutI18n = moduleFiles.filter((f) => !readFileSync(f, 'utf8').includes('useTranslations'));
  if (withoutI18n.length > 0) {
    console.error(`i18n strict: ${withoutI18n.length} module files without useTranslations:`);
    withoutI18n.slice(0, 15).forEach((f) => console.error('  ', f.replace(ROOT + '/', '')));
    failed = true;
  }

  const literalHits = [];
  for (const file of moduleFiles) {
    const src = readFileSync(file, 'utf8');
    if (PERSIAN_RE.test(src) || ENGLISH_UI_RE.test(src)) {
      literalHits.push(file.replace(ROOT + '/', ''));
    }
  }
  if (literalHits.length > 0) {
    console.error(`i18n strict: ${literalHits.length} files with likely hardcoded UI literals:`);
    literalHits.slice(0, 15).forEach((f) => console.error('  ', f));
    failed = true;
  } else {
    console.log('i18n strict: no hardcoded UI literals detected in features/modules');
  }
}

process.exit(failed ? 1 : 0);
