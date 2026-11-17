'use client';

import { useTranslations } from 'next-intl';

export function ProcurementOverviewPage() {
  const t = useTranslations('erp.procurement');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('requests')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('requestsDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('orders')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('ordersDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('receipts')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('receiptsDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function ProcurementRequestsPage() {
  const t = useTranslations('erp.procurement');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('requests')}</h1>
      <p className="text-sm text-muted-foreground">{t('requestsDesc')}</p>
    </div>
  );
}

export function ProcurementRfqsPage() {
  const t = useTranslations('erp.procurement');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('rfqs')}</h1>
      <p className="text-sm text-muted-foreground">{t('rfqsDesc')}</p>
    </div>
  );
}

export function ProcurementOrdersPage() {
  const t = useTranslations('erp.procurement');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('orders')}</h1>
      <p className="text-sm text-muted-foreground">{t('ordersDesc')}</p>
    </div>
  );
}

export function ProcurementReceiptsPage() {
  const t = useTranslations('erp.procurement');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('receipts')}</h1>
      <p className="text-sm text-muted-foreground">{t('receiptsDesc')}</p>
    </div>
  );
}
