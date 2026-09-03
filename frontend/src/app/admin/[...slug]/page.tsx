import { DashboardPageContent } from "@/components/dashboard/DashboardPageContent"
import { resolveDashboardRoute } from "@/lib/dashboard-routes"
import { normalizeDashboardPath } from "@/lib/route-resolver"
import { apiServer } from "@/lib/api-server"
import type { InitialDashboardStats } from "@/lib/initial-dashboard-context"

export const dynamic = "force-dynamic"

type Props = {
  params: Promise<{ slug?: string[] }>
}

export default async function AdminCatchAllPage({ params }: Props) {
  const slug = (await params).slug ?? []
  const path = slug.length ? slug.join("/") : ""
  const normalized = normalizeDashboardPath(path)
  const meta = resolveDashboardRoute(normalized)

  let initialStats: InitialDashboardStats | null = null
  if (!normalized) {
    initialStats = await apiServer<InitialDashboardStats>("/v1/core/dashboard/stats")
  }

  return (
    <DashboardPageContent
      path={normalized}
      meta={meta}
      initialStats={initialStats}
    />
  )
}
