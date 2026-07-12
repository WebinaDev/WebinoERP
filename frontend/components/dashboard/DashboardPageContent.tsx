'use client';

import { useParams } from 'next/navigation';
import { resolveDashboardPage } from '@/lib/dashboard-page-map';
import type { DashboardRouteMeta } from '@/lib/dashboard-routes';
import { normalizeDashboardPath } from '@/lib/route-resolver';
import { resolveRoutePermission } from '@/lib/route-guards';
import { ModuleRouteGuard } from '@/features/shared/auth/ModuleRouteGuard';

type Props = {
  path: string;
  meta: DashboardRouteMeta;
};

export function renderDashboardPage(path: string, _meta: DashboardRouteMeta) {
  return resolveDashboardPage(path);
}

export function DashboardPageContent({ path, meta }: Props) {
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const normalized = normalizeDashboardPath(path);
  const title = locale === 'fa' ? meta.titleFa : meta.titleEn;
  const subtitle = locale === 'fa' ? meta.titleEn : meta.titleFa;

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {meta.group ?? 'ERP'}
        </p>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        <p className="text-sm text-muted-foreground">{subtitle}</p>
      </div>
      <ModuleRouteGuard permission={resolveRoutePermission(normalized)}>
        {renderDashboardPage(normalized, meta)}
      </ModuleRouteGuard>
    </div>
  );
}
