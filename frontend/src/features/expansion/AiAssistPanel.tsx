'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { expansionApi, type Row } from '@/features/expansion/api';

type Purpose = 'lead_suggest' | 'summarize_note' | 'email_draft' | 'deal_risk' | 'content_brief' | 'task_plan';

type Props = {
  purpose: Purpose;
  endpoint?: 'crm' | 'pm';
  context?: Row;
  label?: string;
};

export function AiAssistPanel({ purpose, endpoint = 'crm', context, label }: Props) {
  const t = useTranslations('expansion');
  const [note, setNote] = useState('');
  const [result, setResult] = useState('');
  const [source, setSource] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function run() {
    setBusy(true);
    setError('');
    try {
      const payload = { ...(context ?? {}), note, text: note };
      const data = endpoint === 'pm' ? await expansionApi.pmAssist(purpose, payload) : await expansionApi.crmAssist(purpose, payload);
      setResult(String(data.text ?? ''));
      setSource(String(data.source ?? ''));
    } catch {
      setError(t('ai.error'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="text-base">{label ?? t(`ai.${purpose}`)}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} placeholder={t('ai.placeholder')} />
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" size="sm" onClick={() => void run()} disabled={busy}>
            {busy ? t('common.working') : t('ai.run')}
          </Button>
          {source ? <span className="text-xs text-muted-foreground">{t('ai.source', { source })}</span> : null}
        </div>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {result ? <p className="whitespace-pre-wrap text-sm leading-7">{result}</p> : null}
      </CardContent>
    </Card>
  );
}
