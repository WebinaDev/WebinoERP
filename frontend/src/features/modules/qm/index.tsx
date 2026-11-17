'use client';

import { useTranslations } from 'next-intl';

export function QmOverviewPage() {
  const t = useTranslations('erp.qm');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('plans')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('plansDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('inspections')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('inspectionsDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('ncrs')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('ncrsDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function QmPlansPage() {
  const t = useTranslations('erp.qm');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('plans')}</h1>
      <p className="text-sm text-muted-foreground">{t('plansDesc')}</p>
    </div>
  );
}

export function QmLotsPage() {
  const t = useTranslations('erp.qm');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('lots')}</h1>
      <p className="text-sm text-muted-foreground">{t('lotsDesc')}</p>
    </div>
  );
}

export function QmInspectionsPage() {
  const t = useTranslations('erp.qm');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('inspections')}</h1>
      <p className="text-sm text-muted-foreground">{t('inspectionsDesc')}</p>
    </div>
  );
}

export function QmNcrsPage() {
  const t = useTranslations('erp.qm');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('ncrs')}</h1>
      <p className="text-sm text-muted-foreground">{t('ncrsDesc')}</p>
    </div>
  );
}

export function QmCapasPage() {
  const t = useTranslations('erp.qm');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('capas')}</h1>
      <p className="text-sm text-muted-foreground">{t('capasDesc')}</p>
    </div>
  );
}

export function QmAnalyticsPage() {
  const t = useTranslations('erp.qm');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('analytics')}</h1>
      <p className="text-sm text-muted-foreground">{t('analyticsDesc')}</p>
    </div>
  );
}
