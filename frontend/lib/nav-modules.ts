type TranslateFn = (key: string, values?: { defaultValue?: string }) => string
import { LayoutDashboard, type LucideIcon } from 'lucide-react'

import { resolveLayoutNavKey } from '@/i18n/merge-locales'
import { moduleIcon } from '@/lib/module-icons'
import type { DashboardModule } from '@/types/modules'
import type { NavMainChild, NavMainSection, NavSidebarSection } from '@/types/nav'

export function routeFromModulePath(path: string): string {
  const raw = (path || '').trim()
  if (!raw || raw === '#') {
    return '/'
  }
  const p = raw.replace(/^\//, '')
  if (p === '') {
    return '/'
  }
  return `/${p}`
}

function stableModuleId(m: DashboardModule): string {
  const id = m.id?.trim()
  if (id) return id
  const raw = routeFromModulePath(m.path).replace(/^\//, '').replace(/\//g, '-') || 'root'
  return raw
}

function validChildren(m: DashboardModule): DashboardModule[] {
  return (m.children ?? []).filter((c) => typeof c?.title === 'string' && c.title.trim().length > 0)
}

export function moduleNavTitle(t: TranslateFn, id: string, fallback: string): string {
  const layoutKey = resolveLayoutNavKey(id)
  const key = `layout.${layoutKey}`
  const val = t(key)
  return val === key ? fallback : val
}

function mapNavChild(c: DashboardModule, parent: DashboardModule, t: TranslateFn): NavMainChild {
  const nested = validChildren(c)
  if (nested.length > 0) {
    return {
      id: c.id,
      to: routeFromModulePath(c.path),
      title: moduleNavTitle(t, c.id, c.title),
      icon: moduleIcon(c.icon ?? parent.icon),
      children: nested.map((ch) => mapNavChild(ch, parent, t)),
    }
  }
  return {
    id: c.id,
    to: routeFromModulePath(c.path),
    title: moduleNavTitle(t, c.id, c.title),
    icon: moduleIcon(c.icon ?? parent.icon),
  }
}

function mapModuleChildToNavSection(c: DashboardModule, parent: DashboardModule, t: TranslateFn): NavMainSection {
  const nested = validChildren(c)
  if (nested.length > 0) {
    return {
      kind: 'group',
      id: c.id,
      title: moduleNavTitle(t, c.id, c.title),
      icon: moduleIcon(c.icon ?? parent.icon),
      children: nested.map((ch) => mapNavChild(ch, parent, t)),
    }
  }
  return {
    kind: 'item',
    id: c.id,
    to: routeFromModulePath(c.path),
    title: moduleNavTitle(t, c.id, c.title),
    icon: moduleIcon(c.icon ?? parent.icon),
  }
}

/** Legacy flat mapping — one collapsible group per bootstrap module node. */
export function modulesToNavSections(modules: DashboardModule[], t: TranslateFn): NavMainSection[] {
  return modules
    .filter((m) => typeof m?.title === 'string' && m.title.trim().length > 0)
    .map((m) => {
      const groupId = stableModuleId(m)
      const children = validChildren(m)
      if (children.length > 0) {
        const ParentIcon = moduleIcon(m.icon)
        return {
          kind: 'group' as const,
          id: groupId,
          title: moduleNavTitle(t, groupId, m.title),
          icon: ParentIcon,
          children: children.map((c) => mapNavChild(c, m, t)),
        }
      }
      const leafId = m.id?.trim() || groupId
      return {
        kind: 'item' as const,
        id: leafId,
        to: routeFromModulePath(m.path),
        title: moduleNavTitle(t, leafId, m.title),
        icon: moduleIcon(m.icon),
      }
    })
}

/** Pinned dashboard/reports + one sidebar section per ERP module category. */
export function buildSidebarSections(
  modules: DashboardModule[],
  t: TranslateFn,
): { pinned: NavMainSection[]; sections: NavSidebarSection[] } {
  const pinned: NavMainSection[] = []
  const sections: NavSidebarSection[] = []

  for (const m of modules) {
    const pinnedModule = m as DashboardModule & { pinned?: boolean }
    if (m.id === 'dashboard' || m.id === 'reports' || pinnedModule.pinned) {
      pinned.push({
        kind: 'item',
        id: m.id,
        to: routeFromModulePath(m.path),
        title: moduleNavTitle(t, m.id, m.title),
        icon: moduleIcon(m.icon),
      })
      continue
    }

    const children = validChildren(m)
    if (children.length === 0) continue

    const sectionId = m.id.replace(/^cat-/, '') || m.id
    sections.push({
      id: m.id,
      label: moduleNavTitle(t, sectionId, m.title),
      items: children.map((c) => mapModuleChildToNavSection(c, m, t)),
    })
  }

  return { pinned, sections }
}

function flattenChildLabels(child: NavMainChild, out: { to: string; label: string }[]) {
  if (child.children?.length) {
    for (const nested of child.children) {
      flattenChildLabels(nested, out)
    }
    return
  }
  out.push({ to: child.to, label: child.title })
}

export function flattenNavLabels(sections: NavMainSection[]): { to: string; label: string }[] {
  const out: { to: string; label: string }[] = []
  for (const s of sections) {
    if (s.kind === 'item') {
      out.push({ to: s.to, label: s.title })
    } else {
      for (const c of s.children) {
        flattenChildLabels(c, out)
      }
    }
  }
  return out
}

export type Sidebar08NavSubItem = {
  id: string
  title: string
  url: string
  items?: Sidebar08NavSubItem[]
}

export type Sidebar08MainNavItem = {
  id: string
  title: string
  url: string
  icon: LucideIcon
  isActive?: boolean
  items?: Sidebar08NavSubItem[]
}

function pathIsActive(pathname: string, to: string): boolean {
  const p = pathname.replace(/\/$/, '') || '/'
  if (to === '/') return p === '/'
  const t = to.replace(/\/$/, '')
  return p === t || p.startsWith(`${t}/`)
}

function navChildToSidebar08SubItem(child: NavMainChild, pathname: string): Sidebar08NavSubItem {
  const nested = child.children?.filter((c) => c.title.trim().length > 0) ?? []
  if (nested.length > 0) {
    return {
      id: child.id,
      title: child.title,
      url: child.to,
      items: nested.map((c) => navChildToSidebar08SubItem(c, pathname)),
    }
  }
  return {
    id: child.id,
    title: child.title,
    url: child.to,
  }
}

function navChildIsActive(child: NavMainChild, pathname: string): boolean {
  if (child.children?.length) {
    return child.children.some((c) => navChildIsActive(c, pathname))
  }
  return pathIsActive(pathname, child.to)
}

/** Maps bootstrap nav sections to shadcn sidebar-08 `NavMain` items (SPA routes). */
export function navSectionsToSidebar08MainItems(
  sections: NavMainSection[],
  pathname: string,
): Sidebar08MainNavItem[] {
  return sections.map((section) => {
    const Icon = section.icon ?? LayoutDashboard
    if (section.kind === 'item') {
      return {
        id: section.id,
        title: section.title,
        url: section.to,
        icon: Icon,
        isActive: pathIsActive(pathname, section.to),
      }
    }
    const childActive = section.children.some((c) => navChildIsActive(c, pathname))
    return {
      id: section.id,
      title: section.title,
      url: '#',
      icon: Icon,
      isActive: childActive,
      items: section.children.map((c) => navChildToSidebar08SubItem(c, pathname)),
    }
  })
}
