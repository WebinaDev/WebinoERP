import { Skeleton } from '@/components/ui/skeleton'

type Props = {
  withDescription?: boolean
  withActions?: boolean
}

export function PmPageHeaderSkeleton({ withDescription = true, withActions = true }: Props) {
  // Page title lives in chrome — skeleton matches description + actions only.
  if (!withDescription && !withActions) return null
  return (
    <div className="flex min-w-0 flex-wrap items-start justify-between gap-4" aria-hidden>
      {withDescription ? (
        <div className="min-w-0 space-y-2 text-start">
          <Skeleton className="h-4 w-64 max-w-full" />
        </div>
      ) : (
        <div className="min-w-0" />
      )}
      {withActions ? (
        <div className="flex flex-wrap gap-2">
          <Skeleton className="h-9 w-24" />
          <Skeleton className="h-9 w-28" />
        </div>
      ) : null}
    </div>
  )
}
