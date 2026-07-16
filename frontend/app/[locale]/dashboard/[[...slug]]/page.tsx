import { DashboardPageContent } from '@/components/dashboard/DashboardPageContent';
import { resolveDashboardRoute } from '@/lib/dashboard-routes';
import { normalizeDashboardPath } from '@/lib/route-resolver';
import { apiServer } from '@/lib/api-server';
import type { InitialDashboardStats } from '@/lib/initial-dashboard-context';

type Props = {
  params: { locale: string; slug?: string[] };
};

export default async function DashboardCatchAllPage({ params }: Props) {
  const path = params.slug?.length ? params.slug.join('/') : '';
  const normalized = normalizeDashboardPath(path);
  const meta = resolveDashboardRoute(normalized);

  let initialStats: InitialDashboardStats | null = null;
  if (!normalized) {
    initialStats = await apiServer<InitialDashboardStats>('/v1/core/dashboard/stats');
  }

  return (
    <DashboardPageContent
      path={normalized}
      meta={meta}
      initialStats={initialStats}
    />
  );
}
