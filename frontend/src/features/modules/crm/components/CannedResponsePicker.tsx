'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

type CannedRow = { id: number; title: string; body?: string; content?: string };

type Props = {
  onSelect: (content: string) => void;
};

export function CannedResponsePicker({ onSelect }: Props) {
  const t = useTranslations('crm.tickets');
  const tc = useTranslations('common');
  const [items, setItems] = useState<CannedRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [value, setValue] = useState('');

  useEffect(() => {
    void (async () => {
      setLoading(true);
      try {
        const res = await apiClient.get('/v1/core/canned-responses');
        const data = unwrapData<CannedRow[]>(res);
        setItems(Array.isArray(data) ? data : []);
      } catch {
        setItems([]);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  if (loading) {
    return <p className="text-sm text-muted-foreground">{tc('loading')}</p>;
  }

  if (items.length === 0) return null;

  return (
    <div className="space-y-2">
      <Label>{t('cannedResponses')}</Label>
      <Select
        value={value}
        onValueChange={(id) => {
          setValue(id);
          const item = items.find((x) => String(x.id) === id);
          if (item) {
            onSelect(item.body ?? item.content ?? '');
            setValue('');
          }
        }}
      >
        <SelectTrigger className="max-w-xs">
          <SelectValue placeholder={t('pickCanned')} />
        </SelectTrigger>
        <SelectContent>
          {items.map((item) => (
            <SelectItem key={item.id} value={String(item.id)}>
              {item.title}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
