import { Skeleton } from '@/components/ui/skeleton'

type Props = {
  rows?: number
  columns?: number
  withAvatarColumn?: boolean
}

export function TableListSkeleton({
  rows = 8,
  columns = 5,
  withAvatarColumn = false,
}: Props) {
  return (
    <div className="space-y-3" aria-hidden>
      <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}>
        {Array.from({ length: columns }).map((_, i) => (
          <Skeleton key={`head-${i}`} className="h-4 w-full" />
        ))}
      </div>
      {Array.from({ length: rows }).map((_, row) => (
        <div
          key={row}
          className="grid items-center gap-3"
          style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}
        >
          {Array.from({ length: columns }).map((_, col) => (
            <div key={`${row}-${col}`} className="flex items-center gap-2">
              {withAvatarColumn && col === 0 ? <Skeleton className="h-8 w-8 rounded-full" /> : null}
              <Skeleton className="h-4 w-full" />
            </div>
          ))}
        </div>
      ))}
    </div>
  )
}
