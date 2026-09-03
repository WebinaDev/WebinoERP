'use client';

import { useTranslations } from 'next-intl';

import { Badge } from '@/components/ui/badge';

export function ModirPayamakStatusBadge({ status }: { status: string }) {
  const t = useTranslations();

  const normalized = status.toLowerCase();
  const variant =
    normalized.includes('open') || normalized.includes('active') || normalized.includes(t('auto.integrations_modirpayamak_status_badge.s_6f637966'))
      ? 'default'
      : normalized.includes('close') || normalized.includes('done')
        ? 'secondary'
        : 'outline';

  return <Badge variant={variant}>{status === '—' ? '—' : status}</Badge>;
}
