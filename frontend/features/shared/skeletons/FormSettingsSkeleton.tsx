import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

type Props = {
  cards?: number
  fieldsPerCard?: number
}

export function FormSettingsSkeleton({ cards = 2, fieldsPerCard = 5 }: Props) {
  return (
    <div className="space-y-4 max-w-2xl" aria-busy="true">
      {Array.from({ length: cards }).map((_, c) => (
        <Card key={c}>
          <CardHeader>
            <Skeleton className="h-6 w-40" />
          </CardHeader>
          <CardContent className="space-y-4">
            {Array.from({ length: fieldsPerCard }).map((_, i) => (
              <div key={i} className="space-y-2">
                <Skeleton className="h-4 w-28" />
                <Skeleton className="h-10 w-full" />
              </div>
            ))}
          </CardContent>
        </Card>
      ))}
      <Skeleton className="h-9 w-24" />
    </div>
  )
}
