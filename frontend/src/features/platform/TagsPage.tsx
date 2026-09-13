'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { createTag, deleteTag, fetchTags, type PlatformTag } from '@/lib/api/platform';
import { getAxiosMessage } from '@/lib/api-helpers';
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
      setError(getAxiosMessage(e) || tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate() {
    if (!name.trim()) {
      setError(tP('saveError'));
      return;
    }
    setPending(true);
    setError(null);
    try {
      await createTag({ name: name.trim(), color: color || undefined });
      setName('');
      setColor('');
      await load();
    } catch (e) {
      setError(getAxiosMessage(e) || tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleDelete(row: PlatformTag) {
    if (!window.confirm(tP('delete') + `: ${row.name}?`)) return;
    setPending(true);
    setError(null);
    try {
      await deleteTag(row.id);
      await load();
    } catch (e) {
      setError(getAxiosMessage(e) || tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      error={error}
      actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}
    >
      <Card>
        <CardContent className="grid gap-3 pt-6 md:grid-cols-3">
          <div className="grid gap-2">
            <Label>{t('name')}</Label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="grid gap-2">
            <Label>{t('color')}</Label>
            <Input value={color} onChange={(e) => setColor(e.target.value)} placeholder="#3b82f6" dir="ltr" />
          </div>
          <div className="flex items-end">
            <Button disabled={pending || !name.trim()} onClick={() => void handleCreate()}>
              {t('add')}
            </Button>
          </div>
        </CardContent>
      </Card>
      <div className="flex flex-wrap gap-2">
        {rows.map((row) => (
          <Badge
            key={row.id}
            variant="secondary"
            className="gap-1 pe-1"
            style={row.color ? { backgroundColor: row.color } : undefined}
          >
            {row.name}
            <button
              type="button"
              className="hover:bg-background/40 rounded-sm p-0.5"
              disabled={pending}
              aria-label={tP('delete')}
              onClick={() => void handleDelete(row)}
            >
              <X className="size-3" />
            </button>
          </Badge>
        ))}
        {!rows.length ? <p className="text-muted-foreground text-sm">{tP('noData')}</p> : null}
      </div>
    </PlatformPageLayout>
  );
}
