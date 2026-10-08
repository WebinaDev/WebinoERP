'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmField, type HrmRow } from '@/features/modules/hrm/hrm_shared';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { normalizeListPayload } from '@/lib/list-utils';
import { deleteApprovalFlow, getApprovalFlows, saveApprovalFlow, searchApprovalUsers } from '@/lib/api/hrm';

type UserRef = { id: number; name: string };
type StepDraft = { type: string; role: string; users: UserRef[] };

function useRoleLabel() {
  const t = useTranslations('hrm');
  return (name: string, fallback?: string) => (t.has(`flows.roles.${name}`) ? t(`flows.roles.${name}`) : (fallback ?? name));
}

/** Search-select for application users (approvers). */
function UserSearchSelect({ onPick }: { onPick: (u: UserRef) => void }) {
  const t = useTranslations('hrm');
  const [query, setQuery] = useState('');
  const [options, setOptions] = useState<UserRef[]>([]);
  useEffect(() => {
    const handle = setTimeout(() => {
      void searchApprovalUsers(query)
        .then((res) => setOptions(normalizeListPayload(res).map((u) => ({ id: Number(u.id), name: String(u.name ?? u.email ?? u.id) }))))
        .catch(() => setOptions([]));
    }, 250);
    return () => clearTimeout(handle);
  }, [query]);
  return (
    <div className="space-y-2">
      <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t('flows.searchUser')} />
      <Select value="" onValueChange={(v) => { const u = options.find((o) => String(o.id) === v); if (u) onPick(u); }}>
        <SelectTrigger><SelectValue placeholder={t('flows.pickUser')} /></SelectTrigger>
        <SelectContent>
          {options.map((o) => <SelectItem key={o.id} value={String(o.id)}>{o.name}</SelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );
}

function FlowEditor({ type, flow, roles, onSaved, onError }: {
  type: string;
  flow: HrmRow | undefined;
  roles: Array<{ name: string; label: string }>;
  onSaved: (msg: string) => void;
  onError: (err: unknown) => void;
}) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const roleLabel = useRoleLabel();
  const initialSteps = (): StepDraft[] => (Array.isArray(flow?.steps) ? (flow?.steps as HrmRow[]) : []).map((s) => ({
    type: String(s.type ?? 'role'),
    role: String(s.role ?? ''),
    users: (Array.isArray(s.users) ? (s.users as HrmRow[]) : []).map((u) => ({ id: Number(u.id), name: String(u.name ?? u.id) })),
  }));
  const [steps, setSteps] = useState<StepDraft[]>(initialSteps);
  const [active, setActive] = useState<boolean>(flow ? Boolean(flow.is_active) : true);

  const patch = (idx: number, p: Partial<StepDraft>) => setSteps((list) => list.map((s, i) => (i === idx ? { ...s, ...p } : s)));
  const move = (idx: number, dir: -1 | 1) => setSteps((list) => {
    const next = [...list];
    const target = idx + dir;
    if (target < 0 || target >= next.length) return list;
    [next[idx], next[target]] = [next[target], next[idx]];
    return next;
  });
  const valid = steps.length > 0 && steps.every((s) => (s.type === 'role' ? Boolean(s.role) : s.type === 'user' ? s.users.length > 0 : true));

  const save = async () => {
    try {
      await saveApprovalFlow({
        request_type: type,
        is_active: active,
        steps: steps.map((s) => ({
          type: s.type,
          role: s.type === 'role' ? s.role : null,
          user_ids: s.type === 'user' ? s.users.map((u) => u.id) : null,
        })),
      });
      onSaved(tNav('common.saved'));
    } catch (err) {
      onError(err);
    }
  };

  const remove = async () => {
    if (!flow?.id || !window.confirm(t('flows.resetConfirm'))) return;
    try {
      await deleteApprovalFlow(flow.id as number);
      setSteps([]);
      onSaved(tNav('common.deleted'));
    } catch (err) {
      onError(err);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex flex-wrap items-center justify-between gap-2 text-base">
          <span>{t(`flows.types.${type}`)}</span>
          {flow ? <Badge>{t('flows.custom')}</Badge> : <Badge variant="secondary">{t('flows.defaultChain')}</Badge>}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {steps.length === 0 ? <p className="text-sm text-muted-foreground">{t('flows.noSteps')}</p> : null}
        {steps.map((s, idx) => (
          <div key={idx} className="grid items-start gap-2 rounded border p-3 md:grid-cols-[auto_1fr_2fr_auto]">
            <div className="pt-7 font-semibold"><HrmDigits value={idx + 1} /></div>
            <HrmField label={t('flows.stepType')}>
              <Select value={s.type} onValueChange={(v) => patch(idx, { type: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {(['direct_manager', 'role', 'user'] as const).map((k) => <SelectItem key={k} value={k}>{t(`flows.stepTypes.${k}`)}</SelectItem>)}
                </SelectContent>
              </Select>
            </HrmField>
            <div>
              {s.type === 'role' ? (
                <HrmField label={t('flows.role')}>
                  <Select value={s.role || undefined} onValueChange={(v) => patch(idx, { role: v })}>
                    <SelectTrigger><SelectValue placeholder={tNav('common.select')} /></SelectTrigger>
                    <SelectContent>
                      {roles.map((r) => <SelectItem key={r.name} value={r.name}>{roleLabel(r.name, r.label)}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </HrmField>
              ) : s.type === 'user' ? (
                <HrmField label={t('flows.users')}>
                  <div className="mb-2 flex flex-wrap gap-1">
                    {s.users.map((u) => (
                      <Badge key={u.id} variant="outline" className="gap-1">
                        {u.name}
                        <button type="button" aria-label={tNav('common.delete')} onClick={() => patch(idx, { users: s.users.filter((x) => x.id !== u.id) })}>×</button>
                      </Badge>
                    ))}
                  </div>
                  <UserSearchSelect onPick={(u) => { if (!s.users.some((x) => x.id === u.id)) patch(idx, { users: [...s.users, u] }); }} />
                </HrmField>
              ) : (
                <p className="pt-7 text-sm text-muted-foreground">{t('flows.directManagerHint')}</p>
              )}
            </div>
            <div className="flex gap-1 pt-6">
              <Button size="sm" variant="ghost" disabled={idx === 0} onClick={() => move(idx, -1)}>{t('flows.up')}</Button>
              <Button size="sm" variant="ghost" disabled={idx === steps.length - 1} onClick={() => move(idx, 1)}>{t('flows.down')}</Button>
              <Button size="sm" variant="ghost" onClick={() => setSteps((list) => list.filter((_, i) => i !== idx))}>{tNav('common.delete')}</Button>
            </div>
          </div>
        ))}
        <div className="flex flex-wrap items-center gap-3">
          <Button variant="outline" disabled={steps.length >= 8} onClick={() => setSteps((list) => [...list, { type: list.length === 0 ? 'direct_manager' : 'role', role: '', users: [] }])}>{t('flows.addStep')}</Button>
          <label className="flex items-center gap-2 text-sm">
            <Switch checked={active} onCheckedChange={setActive} />
            {t('flows.active')}
          </label>
          <Button disabled={!valid} onClick={() => void save()}>{tNav('common.save')}</Button>
          {flow ? <Button variant="ghost" onClick={() => void remove()}>{t('flows.reset')}</Button> : null}
        </div>
      </CardContent>
    </Card>
  );
}

export function ApprovalFlowsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [data, setData] = useState<HrmRow | null>(null);
  const [version, setVersion] = useState(0);

  const load = useCallback(async () => {
    try {
      setData(await getApprovalFlows());
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);
  useEffect(() => { void load(); }, [load]);

  const flows = Array.isArray(data?.flows) ? (data?.flows as HrmRow[]) : [];
  const types = Array.isArray(data?.request_types) ? (data?.request_types as string[]) : [];
  const roles = Array.isArray(data?.roles) ? (data?.roles as Array<{ name: string; label: string }>) : [];

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.approvalFlows')} description={t('flows.hint')} {...layoutProps}>
      {!data ? <PageLoadingState /> : (
        <div className="space-y-4">
          <p className="text-sm text-muted-foreground">{t('flows.defaultExplain')}</p>
          {types.map((type) => (
            <FlowEditor
              key={`${type}-${version}`}
              type={type}
              flow={flows.find((f) => f.request_type === type)}
              roles={roles}
              onError={applyAxiosError}
              onSaved={(msg) => { setSuccess(msg); void load().then(() => setVersion((v) => v + 1)); }}
            />
          ))}
        </div>
      )}
    </HrmPageLayout>
  );
}
