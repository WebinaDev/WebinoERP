import { Skeleton } from '@/components/ui/skeleton'

type Props = {
  withDescription?: boolean
  withActions?: boolean
}

export function PmPageHeaderSkeleton({ withDescription = true, withActions = true }: Props) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4" aria-hidden>
      <div className="space-y-2 text-start">
        <Skeleton className="h-8 w-48 max-w-full" />
        {withDescription ? <Skeleton className="h-4 w-64 max-w-full" /> : null}
      </div>
      {withActions ? (
        <div className="flex flex-wrap gap-2">
          <Skeleton className="h-9 w-24" />
          <Skeleton className="h-9 w-28" />
        </div>
      ) : null}
    </div>
  )
}
