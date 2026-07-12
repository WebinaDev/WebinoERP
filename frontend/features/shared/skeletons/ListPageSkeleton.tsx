import { TableListSkeleton } from '@/components/TableListSkeleton'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

import { PmPageHeaderSkeleton } from './PmPageHeaderSkeleton'

type Props = {
  showPageHeader?: boolean
  filterFields?: number
  tableRows?: number
  tableColumns?: number
  withAvatarColumn?: boolean
  withStatCards?: number
}

export function ListPageSkeleton({
  showPageHeader = true,
  filterFields = 3,
  tableRows = 8,
  tableColumns = 5,
  withAvatarColumn = false,
  withStatCards = 0,
}: Props) {
  return (
    <div className="space-y-4" aria-busy="true">
      {showPageHeader ? <PmPageHeaderSkeleton /> : null}
      {withStatCards > 0 ? (
        <div className="grid gap-4 sm:grid-cols-3">
          {Array.from({ length: withStatCards }).map((_, i) => (
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
      ) : null}
      <Card>
        <CardContent className="space-y-4 pt-6">
          {filterFields > 0 ? (
            <div className="flex flex-wrap items-end gap-3">
              {Array.from({ length: filterFields }).map((_, i) => (
                <Skeleton key={i} className="h-10 min-w-[8rem] flex-1 max-w-xs" />
              ))}
              <Skeleton className="h-10 w-24" />
            </div>
          ) : null}
          {tableRows > 0 ? (
            <TableListSkeleton rows={tableRows} columns={tableColumns} withAvatarColumn={withAvatarColumn} />
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}
