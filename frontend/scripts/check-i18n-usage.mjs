#!/usr/bin/env node
/**
 * Detects translation keys used in code (useTranslations/getTranslations + t('key'))
 * that are missing (or not a string leaf) in messages/fa.json or messages/en.json.
 * Resolves the namespace from the nearest preceding `const x = useTranslations('ns')`.
 * Dynamic template keys (`t(\`a.${b}\`)`) are skipped. Exit 1 when anything is missing.
 */
import { readFileSync, readdirSync, statSync } from 'fs';
import { join, dirname, relative } from 'path';
import { fileURLToPath } from 'url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const catalogs = {
  fa: JSON.parse(readFileSync(join(ROOT, 'messages', 'fa.json'), 'utf8')),
  en: JSON.parse(readFileSync(join(ROOT, 'messages', 'en.json'), 'utf8')),
};
const get = (obj, key) => key.split('.').reduce((d, p) => (d && typeof d === 'object' ? d[p] : undefined), obj);

function walk(dir, acc = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    if (statSync(full).isDirectory()) walk(full, acc);
    else if (/\.(tsx?|mts)$/.test(name) && !name.includes('.test.')) acc.push(full);
  }
  return acc;
}

const DECL = /(?:const|let)\s+(\w+)\s*=\s*(?:await\s+)?(?:useTranslations|getTranslations)\(\s*(?:(['"])([^'"]*)\2|\{[^}]*namespace:\s*(['"])([^'"]*)\4[^}]*\})?\s*\)/g;
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const missing = new Map();

for (const file of walk(join(ROOT, 'src'))) {
  const src = readFileSync(file, 'utf8');
  const decls = [...src.matchAll(DECL)].map((m) => ({ at: m.index, name: m[1], ns: m[3] ?? m[5] ?? '' }));
  for (const name of new Set(decls.map((d) => d.name))) {
    const re = new RegExp(`(?<![\\w.])${esc(name)}(?:\\.rich|\\.markup|\\.raw)?\\(\\s*(['"\`])((?:(?!\\1).)*)\\1`, 'g');
    for (const m of src.matchAll(re)) {
      if (m[1] === '`' && m[2].includes('${')) continue;
      const prev = decls.filter((d) => d.name === name && d.at < m.index).pop();
      if (!prev) continue;
      const key = prev.ns ? `${prev.ns}.${m[2]}` : m[2];
      for (const [loc, cat] of Object.entries(catalogs)) {
        if (typeof get(cat, key) !== 'string') {
          const entry = missing.get(key) ?? { locales: new Set(), files: new Set() };
          entry.locales.add(loc);
          entry.files.add(relative(ROOT, file));
          missing.set(key, entry);
        }
      }
    }
  }
}

if (missing.size === 0) {
  console.log('i18n usage: all referenced keys exist in fa and en.');
  process.exit(0);
}
for (const [key, { locales, files }] of [...missing].sort()) {
  console.error(`missing [${[...locales].join(',')}] ${key}  ← ${[...files].join(', ')}`);
}
console.error(`\n${missing.size} missing translation key(s).`);
process.exit(1);
