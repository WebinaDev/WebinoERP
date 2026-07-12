import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

import { PmPageHeaderSkeleton } from './PmPageHeaderSkeleton'

type Props = {
  compact?: boolean
  showPageHeader?: boolean
}

export function DashboardSkeleton({ compact = false, showPageHeader = true }: Props) {
  if (compact) {
    return (
      <div className="space-y-4" aria-busy="true">
        <PmPageHeaderSkeleton withDescription={false} withActions={false} />
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-32 rounded-lg" />
          <Skeleton className="h-32 rounded-lg" />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6" aria-busy="true">
      {showPageHeader ? <PmPageHeaderSkeleton /> : null}
      <div className="grid gap-4 md:grid-cols-2">
        {Array.from({ length: 2 }).map((_, i) => (
          <Card key={i}>
            <CardHeader>
              <Skeleton className="h-5 w-32" />
            </CardHeader>
            <CardContent className="space-y-3">
              <Skeleton className="h-8 w-20" />
              <Skeleton className="h-9 w-28" />
            </CardContent>
          </Card>
        ))}
      </div>
      <Card>
        <CardHeader>
          <Skeleton className="h-5 w-40" />
        </CardHeader>
        <CardContent>
          <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
            {Array.from({ length: 9 }).map((_, i) => (
              <Skeleton key={i} className="h-10 w-full" />
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
