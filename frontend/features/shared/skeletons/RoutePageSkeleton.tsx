'use client'

import { usePathname } from 'next/navigation'

import { CardGridSkeleton } from './CardGridSkeleton'
import { CardListSkeleton } from './CardListSkeleton'
import { ChatSkeleton } from './ChatSkeleton'
import { DashboardSkeleton } from './DashboardSkeleton'
import { DetailSkeleton } from './DetailSkeleton'
import { FormSettingsSkeleton } from './FormSettingsSkeleton'
import { KanbanSkeleton } from './KanbanSkeleton'
import { ListPageSkeleton } from './ListPageSkeleton'
import { ReportsSkeleton } from './ReportsSkeleton'
import { resolveSkeletonVariant } from './resolveSkeletonVariant'

export function RoutePageSkeleton() {
  const pathname = usePathname() ?? ''
  const variant = resolveSkeletonVariant(pathname)

  switch (variant) {
    case 'dashboard':
      return <DashboardSkeleton />
    case 'reports':
      return <ReportsSkeleton />
    case 'settingsForm':
      return <FormSettingsSkeleton />
    case 'cardGrid':
      return <CardGridSkeleton />
    case 'cardList':
      return <CardListSkeleton />
    case 'detail':
      return <DetailSkeleton />
    case 'kanban':
      return <KanbanSkeleton />
    case 'chat':
      return <ChatSkeleton />
    case 'list':
    default:
      return <ListPageSkeleton />
  }
}
