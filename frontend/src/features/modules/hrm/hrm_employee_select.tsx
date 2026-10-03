'use client';

import { useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getStaff } from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

type Props = {
  value: string;
  onChange: (id: string) => void;
  placeholder?: string;
};

export function HrmEmployeeSelect({ value, onChange, placeholder }: Props) {
  const t = useTranslations('hrm');
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [query, setQuery] = useState('');

  useEffect(() => {
    void getStaff({ per_page: 100 })
      .then((res) => setRows(normalizeListPayload(res)))
      .catch(() => setRows([]));
  }, []);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter((r) => {
      const blob = `${r.first_name ?? ''} ${r.last_name ?? ''} ${r.employee_code ?? ''}`.toLowerCase();
      return blob.includes(q);
    });
  }, [query, rows]);

  return (
    <div className="space-y-2">
      <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t('wizard.searchEmployee')} />
      <Select value={value || undefined} onValueChange={onChange}>
        <SelectTrigger>
          <SelectValue placeholder={placeholder ?? t('employee')} />
        </SelectTrigger>
        <SelectContent>
          {filtered.map((r) => (
            <SelectItem key={String(r.id)} value={String(r.id)}>
              {`${String(r.first_name ?? '')} ${String(r.last_name ?? '')}`.trim()} · {String(r.employee_code ?? r.id)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
