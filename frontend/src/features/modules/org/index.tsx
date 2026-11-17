'use client';

import { useTranslations } from 'next-intl';

export function OrgOverviewPage() {
  const t = useTranslations('erp.org');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('companies')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('companiesDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('branches')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('branchesDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('transfers')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('transfersDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function OrgCompaniesPage() {
  const t = useTranslations('erp.org');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('companies')}</h1>
      <p className="text-sm text-muted-foreground">{t('companiesDesc')}</p>
    </div>
  );
}

export function OrgBranchesPage() {
  const t = useTranslations('erp.org');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('branches')}</h1>
      <p className="text-sm text-muted-foreground">{t('branchesDesc')}</p>
    </div>
  );
}

export function OrgTransfersPage() {
  const t = useTranslations('erp.org');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('transfers')}</h1>
      <p className="text-sm text-muted-foreground">{t('transfersDesc')}</p>
    </div>
  );
}

export function OrgSequencesPage() {
  const t = useTranslations('erp.org');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('sequences')}</h1>
      <p className="text-sm text-muted-foreground">{t('sequencesDesc')}</p>
    </div>
  );
}

export function OrgConsolidatedPage() {
  const t = useTranslations('erp.org');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('consolidated')}</h1>
      <p className="text-sm text-muted-foreground">{t('consolidatedDesc')}</p>
    </div>
  );
}
