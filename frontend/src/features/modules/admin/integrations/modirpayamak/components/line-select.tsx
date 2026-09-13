'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { edgeField, edgeListNumbers, type EdgeRow } from '@/lib/api/modirpayamak-edge';
import { dashboardHref } from '@/lib/route-resolver';

type Props = {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
};

export function ModirPayamakLineSelect({ value, onChange, disabled }: Props) {
  const t = useTranslations('modirpayamak');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const [lines, setLines] = useState<EdgeRow[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    const res = await edgeListNumbers();
    setLines(res.items);
    if (!value && res.items.length > 0) {
      const first = edgeField(res.items[0], 'number', 'line', 'from_number', 'sender');
      if (first !== '—') onChange(first);
    }
    setLoading(false);
  }, [onChange, value]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return <div className="h-10 animate-pulse rounded-md bg-muted" />;
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Select value={value || undefined} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger>
          <SelectValue placeholder={t('fromNumber')} />
        </SelectTrigger>
        <SelectContent>
          {lines.map((row, i) => {
            const num = edgeField(row, 'number', 'line', 'from_number', 'sender');
            const label = edgeField(row, 'title', 'name', 'type');
            return (
              <SelectItem key={`${num}-${i}`} value={num}>
                {label !== '—' ? `${num} (${label})` : num}
              </SelectItem>
            );
          })}
        </SelectContent>
      </Select>
      <Button variant="link" size="sm" className="h-auto p-0" asChild>
        <Link href={dashboardHref(locale, 'admin/integrations/modirpayamak/numbers')}>{t('manageLines')}</Link>
      </Button>
    </div>
  );
}
