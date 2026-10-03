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
import { useLocale } from '@/hooks/use-locale-next';

type ScoreRule = { id: number; name: string; kind: string; target: string; weight: number };
type Rule = { id: number; name: string; trigger: string; is_active: boolean };

export function CrmAutomationPage() {
  const t = useTranslations('suite');
  const { formatNumber } = useLocale();
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [scores, setScores] = useState<ScoreRule[]>([]);
  const [rules, setRules] = useState<Rule[]>([]);
  const [ruleName, setRuleName] = useState('');
  const [weight, setWeight] = useState('10');
  const [target, setTarget] = useState('email');
  const [autoName, setAutoName] = useState('');
  const [stageId, setStageId] = useState('');
  const [taskTitle, setTaskTitle] = useState('');
  const [webhook, setWebhook] = useState('');

  const load = useCallback(async () => {
    const [scoreRes, ruleRes] = await Promise.all([
      apiClient.get('/v1/crm/score-rules'),
      apiClient.get('/v1/crm/automation-rules'),
    ]);
    setScores(unwrapData<ScoreRule[]>(scoreRes) ?? []);
    setRules(unwrapData<Rule[]>(ruleRes) ?? []);
  }, []);

  useEffect(() => {
    void load().catch((error) => applyAxiosError(error));
  }, [load, applyAxiosError]);

  return (
    <CrmPageLayout title={t('automationTitle')} description={t('automationHint')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('scoreRules')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {scores.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : scores.map((rule) => (
              <p key={rule.id} className="text-sm">{rule.name} · {rule.target} · {formatNumber(rule.weight)}</p>
            ))}
            <form className="grid gap-2" onSubmit={(event) => {
              event.preventDefault();
              if (!ruleName.trim()) return;
              void apiClient.post('/v1/crm/score-rules', {
                name: ruleName.trim(),
                kind: 'field',
                target,
                operator: 'present',
                weight: Number(weight),
              }).then(() => { setRuleName(''); setSuccess(t('add')); return load(); }).catch(applyAxiosError);
            }}>
              <Label htmlFor="score-name">{t('ruleName')}</Label>
              <Input id="score-name" value={ruleName} onChange={(event) => setRuleName(event.target.value)} />
              <Label htmlFor="score-target">{t('target')}</Label>
              <Input id="score-target" value={target} onChange={(event) => setTarget(event.target.value)} dir="ltr" />
              <Label htmlFor="score-weight">{t('weight')}</Label>
              <Input id="score-weight" inputMode="numeric" value={weight} onChange={(event) => setWeight(event.target.value)} />
              <Button type="submit" size="sm" disabled={!ruleName.trim()}>{t('add')}</Button>
            </form>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('rules')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {rules.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : rules.map((rule) => (
              <p key={rule.id} className="text-sm">{rule.name} · {rule.trigger}</p>
            ))}
            <form className="grid gap-2" onSubmit={(event) => {
              event.preventDefault();
              const actions: { type: string; payload: Record<string, string> }[] = [];
              if (taskTitle.trim()) actions.push({ type: 'create-task', payload: { title: taskTitle.trim() } });
              if (webhook.trim()) actions.push({ type: 'webhook', payload: { url: webhook.trim() } });
              if (!autoName.trim() || actions.length === 0) return;
              void apiClient.post('/v1/crm/automation-rules', {
                name: autoName.trim(),
                trigger: 'crm.deal.stage_changed',
                conditions: stageId ? { stage_id: Number(stageId) } : {},
                actions,
              }).then(() => { setAutoName(''); setTaskTitle(''); setWebhook(''); setSuccess(t('add')); return load(); }).catch(applyAxiosError);
            }}>
              <Label htmlFor="auto-name">{t('ruleName')}</Label>
              <Input id="auto-name" value={autoName} onChange={(event) => setAutoName(event.target.value)} />
              <Label htmlFor="auto-stage">{t('stage')}</Label>
              <Input id="auto-stage" inputMode="numeric" value={stageId} onChange={(event) => setStageId(event.target.value)} />
              <Label htmlFor="auto-task">{t('taskTitle')}</Label>
              <Input id="auto-task" value={taskTitle} onChange={(event) => setTaskTitle(event.target.value)} />
              <Label htmlFor="auto-hook">{t('webhook')}</Label>
              <Input id="auto-hook" value={webhook} onChange={(event) => setWebhook(event.target.value)} dir="ltr" />
              <Button type="submit" size="sm">{t('add')}</Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </CrmPageLayout>
  );
}
