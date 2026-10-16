/**
 * Page builder (صفحه‌ساز) should open in a new browser tab so it never
 * replaces the ERP dashboard shell. Editor routes also render fullscreen
 * (DashboardShell bypasses chrome).
 */

const REL = 'noopener noreferrer' as const

export const PAGE_BUILDER_NEW_TAB_PROPS = {
  target: '_blank' as const,
  rel: REL,
}

/** Strip locale / origin noise and normalize to a dashboard-relative path. */
export function normalizeBuilderHref(href: string): string {
  if (!href) return ''
  let path = href.trim()
  try {
    if (/^https?:\/\//i.test(path)) {
      path = new URL(path).pathname
    }
  } catch {
    /* keep raw */
  }
  // drop locale prefix: /fa/dashboard/... or /en/dashboard/...
  path = path.replace(/^\/(fa|en)(?=\/)/, '')
  if (!path.startsWith('/')) path = `/${path}`
  // collapse // 
  path = path.replace(/\/{2,}/g, '/')
  // treat bare builder paths as dashboard
  if (path === '/builder' || path.startsWith('/builder/')) {
    path = `/dashboard${path}`
  }
  if (path === '/theme-builder' || path.startsWith('/theme-builder/')) {
    path = `/dashboard${path}`
  }
  return path.replace(/\/$/, '') || '/'
}

/**
 * Entry + editor surfaces for صفحه‌ساز / theme editor that must open externally.
 * Keeps settings & collections & theme list inside the dashboard.
 */
export function isPageBuilderNewTabPath(href: string): boolean {
  const path = normalizeBuilderHref(href)
  if (path === '/dashboard/builder') return true
  if (path === '/dashboard/builder/site') return true
  if (path === '/dashboard/builder/new') return true
  if (path.startsWith('/dashboard/builder/chrome/')) return true
  // numeric (or uuid-like) page id → editor
  if (/^\/dashboard\/builder\/[^/]+$/.test(path)) {
    const seg = path.slice('/dashboard/builder/'.length)
    if (seg === 'settings' || seg === 'collections' || seg === 'site' || seg === 'new') {
      return seg === 'site' || seg === 'new'
    }
    return true
  }
  // theme editor: /dashboard/theme-builder/:kind/:id
  if (/^\/dashboard\/theme-builder\/[^/]+\/[^/]+$/.test(path)) return true
  return false
}

/** Fullscreen editor (no dashboard chrome). List/settings stay in shell. */
export function isPageBuilderFullscreenPath(href: string): boolean {
  const path = normalizeBuilderHref(href)
  if (path === '/dashboard/builder/new') return true
  if (path.startsWith('/dashboard/builder/chrome/')) return true
  if (/^\/dashboard\/builder\/\d+$/.test(path)) return true
  if (/^\/dashboard\/theme-builder\/[^/]+\/[^/]+$/.test(path)) return true
  return false
}

export function openPageBuilderInNewTab(href: string): void {
  if (typeof window === 'undefined') return
  const url = href.startsWith('http') ? href : href.startsWith('/') ? href : `/${href}`
  window.open(url, '_blank', 'noopener,noreferrer')
}
