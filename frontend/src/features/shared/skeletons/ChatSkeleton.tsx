import { Skeleton } from '@/components/ui/skeleton'

import { PmPageHeaderSkeleton } from './PmPageHeaderSkeleton'

type Props = {
  showPageHeader?: boolean
}

export function ChatSkeleton({ showPageHeader = true }: Props) {
  return (
    <div className="space-y-4" aria-busy="true">
      {showPageHeader ? <PmPageHeaderSkeleton withActions={false} /> : null}
      <div className="grid h-[min(70vh,640px)] gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
        <div className="space-y-2 rounded-lg border p-3">
          {Array.from({ length: 8 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full rounded-md" />
          ))}
        </div>
        <div className="flex flex-col rounded-lg border">
          <Skeleton className="h-14 w-full rounded-none rounded-t-lg" />
          <div className="flex-1 space-y-3 p-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className={`h-12 ${i % 2 === 0 ? 'w-2/3' : 'ms-auto w-1/2'}`} />
            ))}
          </div>
          <Skeleton className="h-14 w-full rounded-none rounded-b-lg" />
        </div>
      </div>
    </div>
  )
}
