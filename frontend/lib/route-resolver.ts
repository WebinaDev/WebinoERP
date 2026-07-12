/**
 * Normalize dashboard paths: apply legacy redirects from ERP module registry.
 */
import { getAllLegacyRedirects } from '@/lib/module-registry';

const REDIRECTS = getAllLegacyRedirects().sort((a, b) => b.from.length - a.from.length);

function matchRedirect(path: string, pattern: string): string | null {
  const pathParts = path.split('/').filter(Boolean);
  const patternParts = pattern.split('/').filter(Boolean);

  if (patternParts.length !== pathParts.length && !pattern.endsWith('/*')) {
    if (!pattern.includes(':') && patternParts.length !== pathParts.length) {
      return null;
    }
  }

  const params: Record<string, string> = {};
  const compareLen = pattern.endsWith('/*')
    ? patternParts.length - 1
    : patternParts.length;

  if (pattern.endsWith('/*')) {
    if (pathParts.length < compareLen) return null;
  } else if (pathParts.length !== patternParts.length) {
    return null;
  }

  for (let i = 0; i < compareLen; i++) {
    const pp = patternParts[i]!;
    const pv = pathParts[i];
    if (pp.startsWith(':')) {
      params[pp.slice(1).replace('?', '')] = pv ?? '';
    } else if (pp !== pv) {
      return null;
    }
  }

  let target = REDIRECTS.find((r) => r.from === pattern)?.to ?? '';
  if (!target && pattern.endsWith('/*')) {
    const base = pattern.slice(0, -2);
    const match = REDIRECTS.find((r) => r.from === `${base}/*`);
    target = match?.to ?? '';
    if (target && pathParts.length > compareLen) {
      const rest = pathParts.slice(compareLen).join('/');
      target = `${target}/${rest}`.replace(/\/+/g, '/');
    }
  }

  for (const [k, v] of Object.entries(params)) {
    target = target.replace(`:${k}?`, v).replace(`:${k}`, v);
  }

  return target || null;
}

export function normalizeDashboardPath(path: string): string {
  const normalized = path.replace(/^\/+|\/+$/g, '').trim();

  for (const { from, to } of REDIRECTS) {
    if (from === normalized) return to;
    if (from.endsWith('/*')) {
      const prefix = from.slice(0, -2);
      if (normalized === prefix || normalized.startsWith(`${prefix}/`)) {
        const rest = normalized.slice(prefix.length).replace(/^\//, '');
        return rest ? `${to}/${rest}`.replace(/\/+/g, '/') : to;
      }
    }
    const m = matchRedirect(normalized, from);
    if (m) return m.replace(/^\/+|\/+$/g, '');
  }

  return normalized;
}

export function dashboardHref(_locale: string, path: string): string {
  const p = normalizeDashboardPath(path);
  return `/dashboard${p ? `/${p}` : ''}`;
}
