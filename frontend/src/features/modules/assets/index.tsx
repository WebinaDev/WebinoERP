'use client';

import { useTranslations } from 'next-intl';

export function AssetsOverviewPage() {
  const t = useTranslations('erp.assets');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('items')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('itemsDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('depreciation')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('depreciationDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('maintenancePlans')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('maintenancePlansDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function AssetsItemsPage() {
  const t = useTranslations('erp.assets');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('items')}</h1>
      <p className="text-sm text-muted-foreground">{t('itemsDesc')}</p>
    </div>
  );
}

export function AssetsDepreciationPage() {
  const t = useTranslations('erp.assets');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('depreciation')}</h1>
      <p className="text-sm text-muted-foreground">{t('depreciationDesc')}</p>
    </div>
  );
}

export function AssetsMaintenancePlansPage() {
  const t = useTranslations('erp.assets');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('maintenancePlans')}</h1>
      <p className="text-sm text-muted-foreground">{t('maintenancePlansDesc')}</p>
    </div>
  );
}

export function AssetsMaintenanceOrdersPage() {
  const t = useTranslations('erp.assets');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('maintenanceOrders')}</h1>
      <p className="text-sm text-muted-foreground">{t('maintenanceOrdersDesc')}</p>
    </div>
  );
}

export function AssetsInsurancesPage() {
  const t = useTranslations('erp.assets');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('insurances')}</h1>
      <p className="text-sm text-muted-foreground">{t('insurancesDesc')}</p>
    </div>
  );
}
