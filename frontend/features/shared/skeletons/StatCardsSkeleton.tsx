import { cn } from '@/lib/utils'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

type Props = {
  count?: number
}

export function StatCardsSkeleton({ count = 3 }: Props) {
  return (
    <div
      className={cn(
        'grid gap-4',
        count === 2 && 'sm:grid-cols-2',
        count === 3 && 'sm:grid-cols-3',
        count === 4 && 'sm:grid-cols-2 lg:grid-cols-4',
        count > 4 && 'sm:grid-cols-2 lg:grid-cols-4',
      )}
      aria-busy="true"
    >
      {Array.from({ length: count }).map((_, i) => (
        <Card key={i}>
          <CardContent className="flex items-center gap-4 pt-6">
            <Skeleton className="h-12 w-12 shrink-0 rounded-lg" />
            <div className="flex-1 space-y-2">
              <Skeleton className="h-4 w-20" />
              <Skeleton className="h-7 w-16" />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
