'use client';

import { useTranslations } from 'next-intl';

export function WorkflowOverviewPage() {
  const t = useTranslations('erp.workflow');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('inbox')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('inboxDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('definitions')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('definitionsDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('delegations')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('delegationsDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function WorkflowInboxPage() {
  const t = useTranslations('erp.workflow');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('inbox')}</h1>
      <p className="text-sm text-muted-foreground">{t('inboxDesc')}</p>
    </div>
  );
}

export function WorkflowDefinitionsPage() {
  const t = useTranslations('erp.workflow');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('definitions')}</h1>
      <p className="text-sm text-muted-foreground">{t('definitionsDesc')}</p>
    </div>
  );
}

export function WorkflowDelegationsPage() {
  const t = useTranslations('erp.workflow');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('delegations')}</h1>
      <p className="text-sm text-muted-foreground">{t('delegationsDesc')}</p>
    </div>
  );
}

export function WorkflowHistoryPage() {
  const t = useTranslations('erp.workflow');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('history')}</h1>
      <p className="text-sm text-muted-foreground">{t('historyDesc')}</p>
    </div>
  );
}
