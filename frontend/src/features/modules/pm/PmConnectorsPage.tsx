'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Connector = {
  kind: string;
  status?: string;
  label?: string | null;
  config?: { webhook_url?: string | null; external_id?: string | null } | null;
  last_message?: string | null;
};

const KINDS = [
  { id: 'google_calendar', label: 'google' },
  { id: 'outlook_calendar', label: 'outlook' },
  { id: 'slack', label: 'slack' },
  { id: 'bale', label: 'bale' },
] as const;

export function PmConnectorsPage() {
  const t = useTranslations('suite');
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, Connector>>({});
  const [drafts, setDrafts] = useState<Record<string, { label: string; webhook_url: string; external_id: string }>>({});

  const load = useCallback(async () => {
    const res = await apiClient.get('/v1/projects/connectors');
    const list = unwrapData<Connector[]>(res) ?? [];
    const next: Record<string, Connector> = {};
    const forms: Record<string, { label: string; webhook_url: string; external_id: string }> = {};
    for (const row of list) {
      next[row.kind] = row;
      forms[row.kind] = {
        label: row.label ?? '',
        webhook_url: row.config?.webhook_url ?? '',
        external_id: row.config?.external_id ?? '',
      };
    }
    setRows(next);
    setDrafts(forms);
  }, []);

  useEffect(() => {
    void load().catch((error) => applyAxiosError(error));
  }, [load, applyAxiosError]);

  function statusLabel(status?: string) {
    if (status === 'connected') return t('connected');
    if (status === 'stub') return t('stub');
    return t('disconnected');
  }

  async function save(kind: string) {
    const draft = drafts[kind];
    if (!draft) return;
    try {
      await apiClient.put(`/v1/projects/connectors/${kind}`, draft);
      setSuccess(t('save'));
      await load();
    } catch (error) {
      applyAxiosError(error);
    }
  }

  return (
    <CrmPageLayout title={t('connectorsTitle')} description={t('connectorsHint')} {...layoutProps}>
      <div className="grid gap-4 md:grid-cols-2">
        {KINDS.map((kind) => {
          const draft = drafts[kind.id] ?? { label: '', webhook_url: '', external_id: '' };
          return (
            <Card key={kind.id}>
              <CardHeader>
                <CardTitle className="flex items-center justify-between text-base">
                  <span>{t(kind.label)}</span>
                  <span className="text-xs font-normal text-muted-foreground">{statusLabel(rows[kind.id]?.status)}</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <Label htmlFor={`${kind.id}-label`}>{t('label')}</Label>
                <Input id={`${kind.id}-label`} value={draft.label} onChange={(event) => setDrafts((current) => ({ ...current, [kind.id]: { ...draft, label: event.target.value } }))} />
                <Label htmlFor={`${kind.id}-url`}>{t('webhookUrl')}</Label>
                <Input id={`${kind.id}-url`} dir="ltr" value={draft.webhook_url} onChange={(event) => setDrafts((current) => ({ ...current, [kind.id]: { ...draft, webhook_url: event.target.value } }))} />
                <Label htmlFor={`${kind.id}-ext`}>{t('externalId')}</Label>
                <Input id={`${kind.id}-ext`} dir="ltr" value={draft.external_id} onChange={(event) => setDrafts((current) => ({ ...current, [kind.id]: { ...draft, external_id: event.target.value } }))} />
                <div className="flex gap-2">
                  <Button type="button" size="sm" onClick={() => void save(kind.id)}>{t('save')}</Button>
                  <Button type="button" size="sm" variant="outline" onClick={() => void apiClient.post(`/v1/projects/connectors/${kind.id}/test`).then(() => load()).catch(applyAxiosError)}>{t('test')}</Button>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>
    </CrmPageLayout>
  );
}
