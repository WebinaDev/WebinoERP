'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { edgeField, edgeListPatterns, type EdgeRow } from '@/lib/api/modirpayamak-edge';

type Props = {
  value: string;
  onChange: (value: string) => void;
  onSelectRow?: (row: EdgeRow | null) => void;
  disabled?: boolean;
};

export function ModirPayamakPatternSelect({ value, onChange, onSelectRow, disabled }: Props) {
  const t = useTranslations('modirpayamak');
  const [patterns, setPatterns] = useState<EdgeRow[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    const res = await edgeListPatterns(1, 100);
    setPatterns(res.items);
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const handleChange = (code: string) => {
    onChange(code);
    const row = patterns.find((p) => edgeField(p, 'code', 'pattern_code', 'id') === code) ?? null;
    onSelectRow?.(row);
  };

  if (loading) {
    return <div className="h-10 animate-pulse rounded-md bg-muted" />;
  }

  return (
    <Select value={value || undefined} onValueChange={handleChange} disabled={disabled}>
      <SelectTrigger>
        <SelectValue placeholder={t('patternCode')} />
      </SelectTrigger>
      <SelectContent>
        {patterns.map((row, i) => {
          const code = edgeField(row, 'code', 'pattern_code', 'id');
          const title = edgeField(row, 'title', 'name', 'description');
          return (
            <SelectItem key={`${code}-${i}`} value={code}>
              {title !== '—' ? `${code} — ${title}` : code}
            </SelectItem>
          );
        })}
      </SelectContent>
    </Select>
  );
}
