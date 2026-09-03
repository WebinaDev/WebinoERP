import type { TFunction } from 'i18next'
import { matchPath } from 'react-router-dom'

import type { DashboardRouteDef } from '@/routes/routes.config'
import { dashboardRoutes } from '@/routes/routes.config'

function routeMatchPriority(path: string): number {
  const segs = path.split('/').filter(Boolean)
  let staticSegs = 0
  for (const s of segs) {
    if (!s.startsWith(':')) staticSegs += 1
  }
  return staticSegs * 10_000 + segs.length * 100 + path.length
}

const routesByMatchOrder: DashboardRouteDef[] = [...dashboardRoutes].sort(
  (a, b) => routeMatchPriority(b.path) - routeMatchPriority(a.path),
)

function withLeadingSlash(p: string): string {
  if (!p || p === '/') return '/'
  return p.startsWith('/') ? p : `/${p}`
}

function stripSlashes(p: string): string {
  return p.replace(/^\/+|\/+$/g, '')
}

export function navTitleForPath(pathname: string, items: { to: string; label: string }[]): string | null {
  const path = stripSlashes(pathname) || '/'
  const ranked = [...items].sort((a, b) => b.to.length - a.to.length)
  for (const it of ranked) {
    const t = it.to === '/' ? '/' : stripSlashes(it.to) || '/'
    if (t === '/' && path === '/') return it.label
    if (t !== '/' && (path === t || path.startsWith(`${t}/`))) return it.label
  }
  return null
}

/**
 * Prefer an explicit route title (edit/detail screens), then the deepest matching sidebar label.
 */
export function resolveSiteHeaderTitle(
  pathname: string,
  navLabels: { to: string; label: string }[],
  t: TFunction,
): string {
  const trimmed = pathname.replace(/\/$/, '') || '/'
  const path = withLeadingSlash(trimmed === '/' ? '/' : stripSlashes(trimmed))

  for (const def of routesByMatchOrder) {
    if (!def.headerTitleKey) continue
    const pattern = withLeadingSlash(def.path)
    const m = matchPath({ path: pattern, end: true }, path)
    if (!m) continue

    if (def.headerParamKeys && m.params) {
      const interp: Record<string, string> = {}
      for (const [paramName, i18nKey] of Object.entries(def.headerParamKeys)) {
        const v = m.params[paramName]
        if (v != null && v !== '') interp[i18nKey] = v
      }
      return t(def.headerTitleKey, interp)
    }

    return t(def.headerTitleKey)
  }

  return navTitleForPath(pathname, navLabels) ?? t('app.title')
}
