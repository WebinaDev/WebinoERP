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

  const key = `modirpayamak.statuses.${normalized.replace(/[\s-]+/g, '_')}`;
  const label = status === '—' || !status ? '—' : t.has(key) ? t(key) : status;
  return <Badge variant={variant}>{label}</Badge>;
}
