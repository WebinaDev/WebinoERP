import { Skeleton } from '@/components/ui/skeleton'

import { PmPageHeaderSkeleton } from './PmPageHeaderSkeleton'

type Props = {
  columns?: number
  showPageHeader?: boolean
}

export function KanbanSkeleton({ columns = 4, showPageHeader = true }: Props) {
  return (
    <div className="space-y-4" aria-busy="true">
      {showPageHeader ? <PmPageHeaderSkeleton /> : null}
      <div className="flex flex-wrap gap-2">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-24" />
        ))}
      </div>
      <div className="flex gap-4 overflow-hidden">
        {Array.from({ length: columns }).map((_, i) => (
          <div key={i} className="min-w-[260px] flex-1 space-y-3 rounded-lg border p-3">
            <Skeleton className="h-5 w-24" />
            <Skeleton className="h-24 w-full rounded-lg" />
            <Skeleton className="h-24 w-full rounded-lg" />
            <Skeleton className="h-24 w-full rounded-lg" />
          </div>
        ))}
      </div>
    </div>
  )
}
