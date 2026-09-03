'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { createTag, fetchTags, type PlatformTag } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function TagsPage() {
  const t = useTranslations('platform.tags');
  const tP = useTranslations('platform');
  const [rows, setRows] = useState<PlatformTag[]>([]);
  const [name, setName] = useState('');
  const [color, setColor] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchTags());
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate() {
    setPending(true);
    try {
      await createTag({ name, color: color || undefined });
      setName('');
      setColor('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')} error={error} actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}>
      <Card><CardContent className="grid gap-3 pt-6 md:grid-cols-3">
        <div className="grid gap-2"><Label>{t('name')}</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
        <div className="grid gap-2"><Label>{t('color')}</Label><Input value={color} onChange={(e) => setColor(e.target.value)} placeholder="#3b82f6" dir="ltr" /></div>
        <div className="flex items-end"><Button disabled={pending || !name} onClick={() => void handleCreate()}>{t('add')}</Button></div>
      </CardContent></Card>
      <div className="flex flex-wrap gap-2">
        {rows.map((row) => (
          <Badge key={row.id} variant="secondary" style={row.color ? { backgroundColor: row.color } : undefined}>{row.name}</Badge>
        ))}
        {!rows.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
      </div>
    </PlatformPageLayout>
  );
}
