import { DashboardPageContent } from "@/components/dashboard/DashboardPageContent"
import { resolveDashboardRoute } from "@/lib/dashboard-routes"
import { apiServer } from "@/lib/api-server"
import type { InitialDashboardStats } from "@/lib/initial-dashboard-context"

export const dynamic = "force-dynamic"

export default async function AdminRootPage() {
  const meta = resolveDashboardRoute("")
  const initialStats = await apiServer<InitialDashboardStats>("/v1/core/dashboard/stats")

  return (
    <DashboardPageContent
      path=""
      meta={meta}
      initialStats={initialStats}
    />
  )
}
