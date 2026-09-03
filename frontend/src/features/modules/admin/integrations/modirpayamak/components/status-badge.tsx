'use client';

import { Badge } from '@/components/ui/badge';

export function ModirPayamakStatusBadge({ status }: { status: string }) {
  const normalized = status.toLowerCase();
  const variant =
    normalized.includes('open') || normalized.includes('active') || normalized.includes('فعال')
      ? 'default'
      : normalized.includes('close') || normalized.includes('done')
        ? 'secondary'
        : 'outline';

  return <Badge variant={variant}>{status === '—' ? '—' : status}</Badge>;
}
