'use client';

import { useTranslations } from 'next-intl';
import { PendingTab } from './PendingTab';
import { P3_AREAS } from './phase3-api';

export function OrdersTab() {
  const t = useTranslations('crm.hub.c360');
  return <PendingTab area={P3_AREAS.orders} title={t('orders')} />;
}

export function AccountingTab() {
  const t = useTranslations('crm.hub.c360');
  return <PendingTab area={P3_AREAS.accounting} title={t('accounting')} />;
}

export function SmsTab() {
  const t = useTranslations('crm.hub.c360');
  return <PendingTab area={P3_AREAS.sms} title={t('sms')} />;
}
