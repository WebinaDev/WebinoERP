'use client';

import { useCallback, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { HrmEmployeeSelect } from '@/features/modules/hrm/hrm_employee_select';
import { employeeLabel, HrmField, useHrmRows, type HrmRow } from '@/features/modules/hrm/hrm_shared';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import {
  completeMyOffboardingTask,
  deleteOffboardingTemplate,
  getMyOffboarding,
  getOffboardings,
  getOffboardingTemplates,
  saveOffboardingTemplate,
  setOffboardingTaskStatus,
  startOffboarding,
  updateMyOffboarding,
  updateOffboarding,
} from '@/lib/api/hrm';

const KINDS = ['checklist', 'asset_return', 'access_revoke', 'settlement', 'exit_interview', 'handover', 'document'] as const;
const OWNERS = ['employee', 'hr', 'it', 'finance', 'manager'] as const;
const REASONS = ['resignation', 'termination', 'contract_end', 'retirement', 'transfer', 'other'] as const;
const TASK_STATUSES = ['pending', 'done', 'skipped'] as const;
const ALL = 'all';

type ItemDraft = { title: string; kind: string; owner: string; required: boolean; description?: string };

const DEFAULT_ITEMS: Array<{ key: string; kind: string; owner: string; required: boolean }> = [
  { key: 'handover', kind: 'handover', owner: 'employee', required: true },
  { key: 'assets', kind: 'asset_return', owner: 'employee', required: true },
  { key: 'access', kind: 'access_revoke', owner: 'it', required: true },
  { key: 'settlement', kind: 'settlement', owner: 'finance', required: true },
  { key: 'clearance', kind: 'document', owner: 'hr', required: true },
  { key: 'interview', kind: 'exit_interview', owner: 'hr', required: false },
];

function ProgressLine({ progress }: { progress: unknown }) {
  const t = useTranslations('hrm');
  const { formatNumber } = useLocale();
  const p = (progress ?? {}) as HrmRow;
  const percent = Number(p.percent ?? 0);
  return (
    <div className="space-y-1">
      <Progress value={percent} />
      <div className="text-xs text-muted-foreground">
        {t('offboarding.progressText', { done: formatNumber(Number(p.done ?? 0)), total: formatNumber(Number(p.total ?? 0)), percent: formatNumber(percent) })}
      </div>
    </div>
  );
}

function TaskStatusBadge({ status }: { status: unknown }) {
  const t = useTranslations('hrm');
  const s = String(status ?? 'pending');
  const variant = s === 'done' ? 'default' : s === 'skipped' ? 'secondary' : 'outline';
  return <Badge variant={variant}>{t(`offboarding.taskStatus.${s === 'done' || s === 'skipped' ? s : 'pending'}`)}</Badge>;
}

export function OffboardingAdminPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const templatesLoader = useCallback(() => getOffboardingTemplates(), []);
  const templates = useHrmRows(templatesLoader, applyAxiosError);

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.offboarding')} description={t('offboarding.hint')} {...layoutProps}>
      <Tabs defaultValue="cases" className="space-y-4">
        <TabsList>
          <TabsTrigger value="cases">{t('offboarding.tabs.cases')}</TabsTrigger>
          <TabsTrigger value="templates">{t('offboarding.tabs.templates')}</TabsTrigger>
        </TabsList>
        <TabsContent value="cases">
          <CasesTab templates={templates.rows} onError={applyAxiosError} onSuccess={setSuccess} />
        </TabsContent>
        <TabsContent value="templates">
          <TemplatesTab templates={templates.rows} loading={templates.loading} reload={templates.load} onError={applyAxiosError} onSuccess={setSuccess} />
        </TabsContent>
      </Tabs>
    </HrmPageLayout>
  );
}

type Common = { onError: (err: unknown) => void; onSuccess: (msg: string) => void };

function TemplatesTab({ templates, loading, reload, onError, onSuccess }: Common & { templates: HrmRow[]; loading: boolean; reload: () => Promise<void> }) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const [editId, setEditId] = useState<number | null>(null);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [items, setItems] = useState<ItemDraft[]>([]);

  const reset = () => { setEditId(null); setName(''); setDescription(''); setItems([]); };
  const fillDefaults = () => setItems(DEFAULT_ITEMS.map((d) => ({ title: t(`offboarding.defaults.${d.key}`), kind: d.kind, owner: d.owner, required: d.required })));
  const edit = (tpl: HrmRow) => {
    setEditId(Number(tpl.id));
    setName(String(tpl.name ?? ''));
    setDescription(String(tpl.description ?? ''));
    setItems((Array.isArray(tpl.items) ? (tpl.items as HrmRow[]) : []).map((i) => ({
      title: String(i.title ?? ''), kind: String(i.kind ?? 'checklist'), owner: String(i.owner ?? 'hr'), required: Boolean(i.required ?? true),
    })));
  };
  const patchItem = (idx: number, patch: Partial<ItemDraft>) => setItems((list) => list.map((it, i) => (i === idx ? { ...it, ...patch } : it)));

  const save = async () => {
    try {
      await saveOffboardingTemplate({
        name,
        description: description || null,
        items: items.filter((i) => i.title.trim()).map((i, idx) => ({ ...i, sort_order: idx })),
      }, editId ?? undefined);
      onSuccess(tNav('common.saved'));
      reset();
      void reload();
    } catch (err) {
      onError(err);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader><CardTitle className="text-base">{editId ? t('offboarding.editTemplate') : t('offboarding.newTemplate')}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          <div className="grid gap-3 md:grid-cols-2">
            <HrmField label={t('offboarding.templateName')}><Input value={name} onChange={(e) => setName(e.target.value)} /></HrmField>
            <HrmField label={t('description')}><Input value={description} onChange={(e) => setDescription(e.target.value)} /></HrmField>
          </div>
          <div className="space-y-2">
            {items.map((it, idx) => (
              <div key={idx} className="grid items-end gap-2 rounded border p-2 md:grid-cols-[2fr_1fr_1fr_auto_auto]">
                <HrmField label={t('offboarding.taskTitle')}><Input value={it.title} onChange={(e) => patchItem(idx, { title: e.target.value })} /></HrmField>
                <HrmField label={t('offboarding.kind')}>
                  <Select value={it.kind} onValueChange={(v) => patchItem(idx, { kind: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{KINDS.map((k) => <SelectItem key={k} value={k}>{t(`offboarding.kinds.${k}`)}</SelectItem>)}</SelectContent>
                  </Select>
                </HrmField>
                <HrmField label={t('offboarding.owner')}>
                  <Select value={it.owner} onValueChange={(v) => patchItem(idx, { owner: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{OWNERS.map((o) => <SelectItem key={o} value={o}>{t(`offboarding.owners.${o}`)}</SelectItem>)}</SelectContent>
                  </Select>
                </HrmField>
                <label className="flex items-center gap-2 pb-2 text-sm">
                  <Checkbox checked={it.required} onCheckedChange={(v) => patchItem(idx, { required: v === true })} />
                  {t('offboarding.required')}
                </label>
                <Button variant="ghost" onClick={() => setItems((list) => list.filter((_, i) => i !== idx))}>{tNav('common.delete')}</Button>
              </div>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => setItems((list) => [...list, { title: '', kind: 'checklist', owner: 'hr', required: true }])}>{t('offboarding.addTask')}</Button>
            <Button variant="outline" onClick={fillDefaults}>{t('offboarding.fillDefaults')}</Button>
            <Button disabled={!name.trim() || items.length === 0} onClick={() => void save()}>{tNav('common.save')}</Button>
            {editId ? <Button variant="ghost" onClick={reset}>{tNav('common.cancel')}</Button> : null}
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('offboarding.templates')}</CardTitle></CardHeader>
        <CardContent>
          {loading ? <PageLoadingState /> : (
            <Table>
              <TableHeader><TableRow>
                <TableHead>{t('offboarding.templateName')}</TableHead>
                <TableHead>{t('offboarding.taskCount')}</TableHead>
                <TableHead>{tNav('common.actions')}</TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {templates.length === 0 ? (
                  <TableRow><TableCell colSpan={3}><PageEmptyState /></TableCell></TableRow>
                ) : templates.map((tpl) => (
                  <TableRow key={String(tpl.id)}>
                    <TableCell>{String(tpl.name ?? '')}</TableCell>
                    <TableCell><HrmDigits value={Array.isArray(tpl.items) ? tpl.items.length : 0} /></TableCell>
                    <TableCell className="space-x-2 space-x-reverse">
                      <Button size="sm" variant="outline" onClick={() => edit(tpl)}>{tNav('common.edit')}</Button>
                      <Button size="sm" variant="destructive" onClick={() => {
                        if (!window.confirm(tNav('common.confirmDelete'))) return;
                        void deleteOffboardingTemplate(tpl.id as number).then(() => { onSuccess(tNav('common.deleted')); void reload(); }).catch(onError);
                      }}>{tNav('common.delete')}</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function CasesTab({ templates, onError, onSuccess }: Common & { templates: HrmRow[] }) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate } = useLocale();
  const [employeeId, setEmployeeId] = useState('');
  const [templateId, setTemplateId] = useState('');
  const [lastDay, setLastDay] = useState('');
  const [reason, setReason] = useState<string>('resignation');
  const [status, setStatus] = useState<string>('in_progress');
  const loader = useCallback(() => getOffboardings({ status: status === ALL ? undefined : status }), [status]);
  const cases = useHrmRows(loader, onError);

  const start = async () => {
    try {
      await startOffboarding({ employee_id: Number(employeeId), template_id: Number(templateId), last_day: lastDay || null, reason });
      onSuccess(t('offboarding.started'));
      setEmployeeId('');
      void cases.load();
    } catch (err) {
      onError(err);
    }
  };
  const setTask = (taskId: number, value: string) =>
    void setOffboardingTaskStatus(taskId, value).then(() => cases.load()).catch(onError);

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader><CardTitle className="text-base">{t('offboarding.start')}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <HrmField label={t('employee')}><HrmEmployeeSelect value={employeeId} onChange={setEmployeeId} /></HrmField>
          <HrmField label={t('offboarding.template')}>
            <Select value={templateId || undefined} onValueChange={setTemplateId}>
              <SelectTrigger><SelectValue placeholder={tNav('common.select')} /></SelectTrigger>
              <SelectContent>{templates.map((tpl) => <SelectItem key={String(tpl.id)} value={String(tpl.id)}>{String(tpl.name ?? '')}</SelectItem>)}</SelectContent>
            </Select>
          </HrmField>
          <HrmField label={t('offboarding.lastDay')}><LocaleDatePicker value={lastDay} onChange={setLastDay} /></HrmField>
          <HrmField label={t('offboarding.reason')}>
            <Select value={reason} onValueChange={setReason}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{REASONS.map((r) => <SelectItem key={r} value={r}>{t(`offboarding.reasons.${r}`)}</SelectItem>)}</SelectContent>
            </Select>
          </HrmField>
          <div className="md:col-span-2">
            <Button disabled={!employeeId || !templateId} onClick={() => void start()}>{t('offboarding.start')}</Button>
          </div>
        </CardContent>
      </Card>

      <div className="flex items-end gap-3">
        <HrmField label={t('status')}>
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger className="w-48"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>{tNav('common.all')}</SelectItem>
              {(['in_progress', 'completed', 'cancelled'] as const).map((s) => <SelectItem key={s} value={s}>{t(`offboarding.caseStatus.${s}`)}</SelectItem>)}
            </SelectContent>
          </Select>
        </HrmField>
      </div>

      {cases.loading ? <PageLoadingState /> : cases.rows.length === 0 ? <PageEmptyState /> : cases.rows.map((c) => {
        const tasks = Array.isArray(c.tasks) ? (c.tasks as HrmRow[]) : [];
        const caseStatus = String(c.status ?? '');
        return (
          <Card key={String(c.id)}>
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center justify-between gap-2 text-base">
                <span>{employeeLabel(c.employee)} · <HrmDigits value={(c.employee as HrmRow | undefined)?.employee_code} /></span>
                <Badge variant={caseStatus === 'completed' ? 'default' : 'outline'}>{t.has(`offboarding.caseStatus.${caseStatus}`) ? t(`offboarding.caseStatus.${caseStatus}`) : caseStatus}</Badge>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid gap-2 text-sm md:grid-cols-3">
                <div>{t('offboarding.reason')}: {c.reason ? t(`offboarding.reasons.${String(c.reason)}`) : '—'}</div>
                <div>{t('offboarding.lastDay')}: {c.last_day ? formatDate(String(c.last_day)) : '—'}</div>
                <div>{t('offboarding.template')}: {String((c.template as HrmRow | undefined)?.name ?? '—')}</div>
              </div>
              <ProgressLine progress={c.progress} />
              <Table>
                <TableHeader><TableRow>
                  <TableHead>{t('offboarding.taskTitle')}</TableHead>
                  <TableHead>{t('offboarding.kind')}</TableHead>
                  <TableHead>{t('offboarding.owner')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{t('offboarding.assetLabel')}</TableHead>
                  <TableHead>{tNav('common.actions')}</TableHead>
                </TableRow></TableHeader>
                <TableBody>
                  {tasks.map((task) => (
                    <TableRow key={String(task.id)}>
                      <TableCell>{String(task.title ?? '')}{task.required ? null : <span className="ms-1 text-xs text-muted-foreground">({t('offboarding.optional')})</span>}</TableCell>
                      <TableCell>{t.has(`offboarding.kinds.${String(task.kind)}`) ? t(`offboarding.kinds.${String(task.kind)}`) : String(task.kind ?? '')}</TableCell>
                      <TableCell>{t.has(`offboarding.owners.${String(task.owner)}`) ? t(`offboarding.owners.${String(task.owner)}`) : String(task.owner ?? '')}</TableCell>
                      <TableCell><TaskStatusBadge status={task.status} /></TableCell>
                      <TableCell>{task.asset_label ? <HrmDigits value={task.asset_label} /> : '—'}</TableCell>
                      <TableCell>
                        {caseStatus === 'cancelled' ? null : (
                          <Select value={String(task.status ?? 'pending')} onValueChange={(v) => setTask(task.id as number, v)}>
                            <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
                            <SelectContent>{TASK_STATUSES.map((s) => <SelectItem key={s} value={s}>{t(`offboarding.taskStatus.${s}`)}</SelectItem>)}</SelectContent>
                          </Select>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              {c.exit_interview_notes ? (
                <div className="rounded border p-2 text-sm"><span className="font-medium">{t('offboarding.exitNotes')}: </span>{String(c.exit_interview_notes)}</div>
              ) : null}
              {caseStatus === 'in_progress' ? (
                <Button variant="outline" size="sm" onClick={() => {
                  if (!window.confirm(t('offboarding.cancelConfirm'))) return;
                  void updateOffboarding(c.id as number, { status: 'cancelled' }).then(() => cases.load()).catch(onError);
                }}>{t('offboarding.cancelCase')}</Button>
              ) : null}
            </CardContent>
          </Card>
        );
      })}
    </div>
  );
}

export function MyOffboardingPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const loader = useCallback(() => getMyOffboarding(), []);
  const list = useHrmRows(loader, applyAxiosError);
  const [assets, setAssets] = useState<Record<number, string>>({});
  const [notes, setNotes] = useState<Record<number, string>>({});

  const complete = async (taskId: number) => {
    try {
      await completeMyOffboardingTask(taskId, { asset_label: assets[taskId] || undefined });
      setSuccess(tNav('common.saved'));
      void list.load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.myOffboarding')} description={t('offboarding.myHint')} {...layoutProps}>
      {list.loading ? <PageLoadingState /> : list.rows.length === 0 ? (
        <PageEmptyState description={t('offboarding.noCase')} />
      ) : list.rows.map((c) => {
        const id = Number(c.id);
        const tasks = Array.isArray(c.tasks) ? (c.tasks as HrmRow[]) : [];
        return (
          <Card key={id} className="mb-4">
            <CardHeader>
              <CardTitle className="flex flex-wrap items-center justify-between gap-2 text-base">
                <span>{t('offboarding.myCase')}</span>
                <HrmStatus value={c.status} />
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid gap-2 text-sm md:grid-cols-2">
                <div>{t('offboarding.lastDay')}: {c.last_day ? formatDate(String(c.last_day)) : '—'}</div>
                <div>{t('offboarding.reason')}: {c.reason ? t(`offboarding.reasons.${String(c.reason)}`) : '—'}</div>
              </div>
              <ProgressLine progress={c.progress} />
              <Table>
                <TableHeader><TableRow>
                  <TableHead>{t('offboarding.taskTitle')}</TableHead>
                  <TableHead>{t('offboarding.owner')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{tNav('common.actions')}</TableHead>
                </TableRow></TableHeader>
                <TableBody>
                  {tasks.map((task) => {
                    const tid = Number(task.id);
                    const mine = task.owner === 'employee' && task.status === 'pending' && c.status === 'in_progress';
                    return (
                      <TableRow key={tid}>
                        <TableCell>{String(task.title ?? '')}</TableCell>
                        <TableCell>{t.has(`offboarding.owners.${String(task.owner)}`) ? t(`offboarding.owners.${String(task.owner)}`) : String(task.owner ?? '')}</TableCell>
                        <TableCell><TaskStatusBadge status={task.status} /></TableCell>
                        <TableCell>
                          {mine ? (
                            <div className="flex flex-wrap items-center gap-2">
                              {task.kind === 'asset_return' ? (
                                <Input className="w-40" placeholder={t('offboarding.assetLabel')} value={assets[tid] ?? ''} onChange={(e) => setAssets((a) => ({ ...a, [tid]: e.target.value }))} />
                              ) : null}
                              <Button size="sm" onClick={() => void complete(tid)}>{t('offboarding.markDone')}</Button>
                            </div>
                          ) : task.owner !== 'employee' && task.status === 'pending' ? (
                            <span className="text-xs text-muted-foreground">{t('offboarding.waitingFor', { owner: t(`offboarding.owners.${String(task.owner)}`) })}</span>
                          ) : null}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
              {c.status === 'in_progress' ? (
                <div className="space-y-2">
                  <HrmField label={t('offboarding.exitNotes')} hint={t('offboarding.exitNotesHint')}>
                    <Textarea value={notes[id] ?? String(c.exit_interview_notes ?? '')} onChange={(e) => setNotes((n) => ({ ...n, [id]: e.target.value }))} />
                  </HrmField>
                  <Button variant="outline" size="sm" onClick={() => void updateMyOffboarding(id, { exit_interview_notes: notes[id] ?? '' }).then(() => { setSuccess(tNav('common.saved')); void list.load(); }).catch(applyAxiosError)}>{tNav('common.save')}</Button>
                </div>
              ) : null}
            </CardContent>
          </Card>
        );
      })}
    </HrmPageLayout>
  );
}
