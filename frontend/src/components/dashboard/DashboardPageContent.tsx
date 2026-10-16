'use client';

import { resolveDashboardPage } from '@/lib/dashboard-page-map';
import type { DashboardRouteMeta } from '@/lib/dashboard-routes';
import { normalizeDashboardPath } from '@/lib/route-resolver';
import { resolveRoutePermission } from '@/lib/route-guards';
import { ModuleRouteGuard } from '@/features/shared/auth/ModuleRouteGuard';
import {
  InitialDashboardStatsProvider,
  type InitialDashboardStats,
} from '@/lib/initial-dashboard-context';

type Props = {
  path: string;
  meta: DashboardRouteMeta;
  initialStats?: InitialDashboardStats | null;
};

export function renderDashboardPage(path: string, _meta: DashboardRouteMeta) {
  return resolveDashboardPage(path);
}

export function DashboardPageContent({ path, meta, initialStats = null }: Props) {
  const normalized = normalizeDashboardPath(path);

  return (
    <InitialDashboardStatsProvider value={initialStats}>
      <div className="wd-dash-page min-w-0 max-w-full space-y-4 overflow-x-hidden text-start sm:space-y-5">
        <ModuleRouteGuard permission={resolveRoutePermission(normalized)}>
          {renderDashboardPage(normalized, meta)}
        </ModuleRouteGuard>
      </div>
    </InitialDashboardStatsProvider>
  );
}
