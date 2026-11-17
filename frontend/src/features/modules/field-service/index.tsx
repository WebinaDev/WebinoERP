'use client';

import { useTranslations } from 'next-intl';

export function FieldServiceOverviewPage() {
  const t = useTranslations('erp.field_service');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('installBase')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('installBaseDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('orders')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('ordersDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('technicians')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('techniciansDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function FieldServiceInstallBasePage() {
  const t = useTranslations('erp.field_service');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('installBase')}</h1>
      <p className="text-sm text-muted-foreground">{t('installBaseDesc')}</p>
    </div>
  );
}

export function FieldServiceOrdersPage() {
  const t = useTranslations('erp.field_service');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('orders')}</h1>
      <p className="text-sm text-muted-foreground">{t('ordersDesc')}</p>
    </div>
  );
}

export function FieldServiceTechniciansPage() {
  const t = useTranslations('erp.field_service');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('technicians')}</h1>
      <p className="text-sm text-muted-foreground">{t('techniciansDesc')}</p>
    </div>
  );
}

export function FieldServiceReportsPage() {
  const t = useTranslations('erp.field_service');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('reports')}</h1>
      <p className="text-sm text-muted-foreground">{t('reportsDesc')}</p>
    </div>
  );
}
