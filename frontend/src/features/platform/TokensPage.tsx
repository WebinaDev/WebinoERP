'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PlatformPageLayout } from '@/features/platform/PlatformPageLayout';
import {
  createPlatformToken,
  deletePlatformToken,
  fetchPlatformTokens,
  type PlatformApiToken,
} from '@/lib/api/platform';
import { getAxiosMessage } from '@/lib/api-helpers';

const ABILITIES = ['read', 'read:sensitive', 'write', 'deploy'] as const;

export function TokensPage() {
  const t = useTranslations('platform.tokens');
  const [rows, setRows] = useState<PlatformApiToken[]>([]);
  const [name, setName] = useState('');
  const [abilities, setAbilities] = useState<string[]>(['read']);
  const [plain, setPlain] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const load = useCallback(async () => {
    try {
      setRows(await fetchPlatformTokens());
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function onCreate() {
    setPending(true);
    setError(null);
    setPlain(null);
    try {
      const res = await createPlatformToken({ name, abilities });
      setPlain(res.plain_token);
      setName('');
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setPending(false);
    }
  }

  function toggleAbility(a: string) {
    setAbilities((prev) => (prev.includes(a) ? prev.filter((x) => x !== a) : [...prev, a]));
  }

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')}>
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
      {plain ? (
        <Card className="mb-4 border-amber-500/40">
          <CardContent className="pt-6 text-sm">
            <p className="mb-2 font-medium">{t('copyOnce')}</p>
            <code className="block break-all rounded bg-muted p-2 font-mono text-xs" dir="ltr">
              {plain}
            </code>
          </CardContent>
        </Card>
      ) : null}
      <Card className="mb-4">
        <CardContent className="grid gap-3 pt-6 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <Label>{t('name')}</Label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="sm:col-span-2 flex flex-wrap gap-2">
            {ABILITIES.map((a) => (
              <Button
                key={a}
                type="button"
                size="sm"
                variant={abilities.includes(a) ? 'default' : 'outline'}
                onClick={() => toggleAbility(a)}
              >
                {a}
              </Button>
            ))}
          </div>
          <Button type="button" disabled={pending || !name.trim() || abilities.length === 0} onClick={() => void onCreate()}>
            {t('create')}
          </Button>
        </CardContent>
      </Card>
      <div className="grid gap-2">
        {rows.map((row) => (
          <Card key={row.id}>
            <CardContent className="flex flex-wrap items-center justify-between gap-2 py-4 text-sm">
              <div>
                <p className="font-medium">{row.name}</p>
                <p className="text-xs text-muted-foreground" dir="ltr">
                  {(row.abilities ?? []).join(', ')}
                </p>
              </div>
              <Button
                type="button"
                size="sm"
                variant="destructive"
                onClick={() => void deletePlatformToken(row.id).then(load)}
              >
                {t('revoke')}
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>
    </PlatformPageLayout>
  );
}
