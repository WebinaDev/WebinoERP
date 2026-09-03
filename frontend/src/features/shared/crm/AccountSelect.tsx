'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { unwrapData } from '@/lib/api-helpers';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

type AccountOpt = { id: number; name: string };

type Props = {
  value: string;
  onChange: (id: string) => void;
  placeholder?: string;
  allowEmpty?: boolean;
  className?: string;
};

export function AccountSelect({ value, onChange, placeholder, allowEmpty, className }: Props) {
  const t = useTranslations('common');
  const [items, setItems] = useState<AccountOpt[]>([]);

  useEffect(() => {
    void (async () => {
      try {
        const res = await apiClient.get('/v1/crm/accounts', { params: { per_page: 100 } });
        const rows = normalizeListPayload(unwrapData(res));
        setItems(
          rows
            .map((r) => ({
              id: Number(r.id),
              name: String(r.name ?? r.id),
            }))
            .filter((r) => Number.isFinite(r.id)),
        );
      } catch {
        setItems([]);
      }
    })();
  }, []);

  return (
    <Select value={value || undefined} onValueChange={onChange}>
      <SelectTrigger className={className}>
        <SelectValue placeholder={placeholder ?? t('select')} />
      </SelectTrigger>
      <SelectContent>
        {allowEmpty ? <SelectItem value="none">{t('emptyValue')}</SelectItem> : null}
        {items.map((a) => (
          <SelectItem key={a.id} value={String(a.id)}>
            {a.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
