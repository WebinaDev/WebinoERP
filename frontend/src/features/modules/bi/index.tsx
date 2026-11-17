'use client';

import { useTranslations } from 'next-intl';

export function BiOverviewPage() {
  const t = useTranslations('erp.bi');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('kpis')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('kpisDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('dashboards')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('dashboardsDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('subscriptions')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('subscriptionsDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function BiKpisPage() {
  const t = useTranslations('erp.bi');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('kpis')}</h1>
      <p className="text-sm text-muted-foreground">{t('kpisDesc')}</p>
    </div>
  );
}

export function BiDashboardsPage() {
  const t = useTranslations('erp.bi');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('dashboards')}</h1>
      <p className="text-sm text-muted-foreground">{t('dashboardsDesc')}</p>
    </div>
  );
}

export function BiSubscriptionsPage() {
  const t = useTranslations('erp.bi');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('subscriptions')}</h1>
      <p className="text-sm text-muted-foreground">{t('subscriptionsDesc')}</p>
    </div>
  );
}

export function BiAlertsPage() {
  const t = useTranslations('erp.bi');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('alerts')}</h1>
      <p className="text-sm text-muted-foreground">{t('alertsDesc')}</p>
    </div>
  );
}
