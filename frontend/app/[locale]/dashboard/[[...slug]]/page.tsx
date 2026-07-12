import { DashboardPageContent } from '@/components/dashboard/DashboardPageContent';
import { resolveDashboardRoute } from '@/lib/dashboard-routes';
import { normalizeDashboardPath } from '@/lib/route-resolver';

type Props = {
  params: { locale: string; slug?: string[] };
};

export default async function DashboardCatchAllPage({ params }: Props) {
  const path = params.slug?.length ? params.slug.join('/') : '';
  const normalized = normalizeDashboardPath(path);
  const meta = resolveDashboardRoute(normalized);

  return <DashboardPageContent path={normalized} meta={meta} />;
}
