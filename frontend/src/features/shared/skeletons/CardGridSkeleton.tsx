import { Skeleton } from '@/components/ui/skeleton'

import { PmPageHeaderSkeleton } from './PmPageHeaderSkeleton'

type Props = {
  tabs?: number
  cards?: number
  showPageHeader?: boolean
}

export function CardGridSkeleton({ tabs = 4, cards = 6, showPageHeader = true }: Props) {
  return (
    <div className="space-y-6" aria-busy="true">
      {showPageHeader ? <PmPageHeaderSkeleton /> : null}
      {tabs > 0 ? (
        <div className="flex flex-wrap gap-2">
          {Array.from({ length: tabs }).map((_, i) => (
            <Skeleton key={i} className="h-9 w-24" />
          ))}
        </div>
      ) : null}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {Array.from({ length: cards }).map((_, i) => (
          <Skeleton key={i} className="h-48 rounded-xl" />
        ))}
      </div>
    </div>
  )
}
