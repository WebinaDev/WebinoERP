export type SkeletonVariant =
  | 'dashboard'
  | 'reports'
  | 'settingsForm'
  | 'cardGrid'
  | 'cardList'
  | 'detail'
  | 'kanban'
  | 'chat'
  | 'list'

export function resolveSkeletonVariant(pathname: string): SkeletonVariant {
  const path = pathname.replace(/^\/[a-z]{2}(?=\/|$)/, '').replace(/^\/dashboard\/?/, '')

  if (!path || path === '/') return 'dashboard'
  if (path === 'reports' || path.startsWith('reports/')) return 'reports'
  if (path === 'settings' || path.endsWith('/settings')) return 'settingsForm'
  if (path === 'tasks' || path.startsWith('tasks/')) return 'kanban'
  if (path.includes('chat')) return 'chat'
  if (/\/\d+(\/|$)/.test(`/${path}`)) return 'detail'
  if (path.includes('grid') || path.includes('portfolio')) return 'cardGrid'
  if (path.includes('cards')) return 'cardList'

  return 'list'
}
