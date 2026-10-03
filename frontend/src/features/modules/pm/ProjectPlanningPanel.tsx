'use client';

import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { useLocale } from '@/hooks/use-locale-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Row = Record<string, unknown>;

function isoDay(value: unknown): string {
  const raw = String(value ?? '');
  const match = /^(\d{4}-\d{2}-\d{2})/.exec(raw);
  return match ? match[1] : '';
}

function PlanningTable({
  rows,
  empty,
  children,
}: {
  rows: Row[];
  empty: string;
  children: ReactNode;
}) {
  const { isRtl } = useLocale();
  return (
    <div className="space-y-3" dir={isRtl ? 'rtl' : 'ltr'}>
      {children}
      {rows.length === 0 ? <p className="text-sm text-muted-foreground">{empty}</p> : null}
    </div>
  );
}

export function ProjectPlanningPanel({ projectId }: { projectId: string }) {
  const t = useTranslations('pm.projects');
  const tCommon = useTranslations('common');
  const { formatNumber, isRtl } = useLocale();
  const [milestones, setMilestones] = useState<Row[]>([]);
  const [sprints, setSprints] = useState<Row[]>([]);
  const [epics, setEpics] = useState<Row[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const [milestoneTitle, setMilestoneTitle] = useState('');
  const [milestoneDue, setMilestoneDue] = useState('');
  const [sprintName, setSprintName] = useState('');
  const [sprintStart, setSprintStart] = useState('');
  const [sprintEnd, setSprintEnd] = useState('');
  const [epicTitle, setEpicTitle] = useState('');
  const [epicDescription, setEpicDescription] = useState('');

  const load = useCallback(async () => {
    setError(null);
    try {
      const [milestoneRes, sprintRes, epicRes] = await Promise.all([
        apiClient.get(`/v1/projects/projects/${projectId}/milestones`),
        apiClient.get('/v1/projects/sprints', { params: { project_id: projectId } }),
        apiClient.get('/v1/projects/epics', { params: { project_id: projectId } }),
      ]);
      setMilestones(normalizeListPayload(milestoneRes.data));
      setSprints(normalizeListPayload(sprintRes.data));
      setEpics(normalizeListPayload(epicRes.data));
    } catch (err) {
      setError(getAxiosMessage(err));
    }
  }, [projectId]);

  useEffect(() => {
    void load();
  }, [load]);

  async function run(action: () => Promise<void>) {
    setBusy(true);
    setError(null);
    try {
      await action();
      await load();
    } catch (err) {
      setError(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4" dir={isRtl ? 'rtl' : 'ltr'}>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-start text-base">
            {t('milestones')} ({formatNumber(milestones.length)})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <PlanningTable rows={milestones} empty={t('noMilestones')}>
            <div className="flex flex-wrap items-end gap-2">
              <div className="min-w-[180px] flex-1 space-y-1">
                <Label>{t('taskTitle')}</Label>
                <Input value={milestoneTitle} onChange={(e) => setMilestoneTitle(e.target.value)} />
              </div>
              <div className="w-[180px] space-y-1">
                <Label>{t('dueDate')}</Label>
                <LocaleDatePicker value={milestoneDue} onChange={setMilestoneDue} />
              </div>
              <Button
                type="button"
                size="sm"
                disabled={busy || !milestoneTitle.trim()}
                onClick={() =>
                  void run(async () => {
                    await apiClient.post(`/v1/projects/projects/${projectId}/milestones`, {
                      title: milestoneTitle.trim(),
                      due_date: milestoneDue || null,
                      status: 'open',
                    });
                    setMilestoneTitle('');
                    setMilestoneDue('');
                  })
                }
              >
                {tCommon('add')}
              </Button>
            </div>
            {milestones.map((row) => (
              <MilestoneRow
                key={String(row.id)}
                row={row}
                busy={busy}
                onSave={(payload) =>
                  void run(async () => {
                    await apiClient.patch(`/v1/projects/milestones/${String(row.id)}`, payload);
                  })
                }
                onDelete={() =>
                  void run(async () => {
                    await apiClient.delete(`/v1/projects/milestones/${String(row.id)}`);
                  })
                }
              />
            ))}
          </PlanningTable>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-start text-base">
            {t('sprints')} ({formatNumber(sprints.length)})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <PlanningTable rows={sprints} empty={t('noSprints')}>
            <div className="flex flex-wrap items-end gap-2">
              <div className="min-w-[180px] flex-1 space-y-1">
                <Label>{t('name')}</Label>
                <Input value={sprintName} onChange={(e) => setSprintName(e.target.value)} />
              </div>
              <div className="w-[160px] space-y-1">
                <Label>{t('startDate')}</Label>
                <LocaleDatePicker value={sprintStart} onChange={setSprintStart} />
              </div>
              <div className="w-[160px] space-y-1">
                <Label>{t('endDate')}</Label>
                <LocaleDatePicker value={sprintEnd} onChange={setSprintEnd} />
              </div>
              <Button
                type="button"
                size="sm"
                disabled={busy || !sprintName.trim()}
                onClick={() =>
                  void run(async () => {
                    await apiClient.post('/v1/projects/sprints', {
                      project_id: Number(projectId),
                      name: sprintName.trim(),
                      starts_at: sprintStart || null,
                      ends_at: sprintEnd || null,
                      status: 'planned',
                    });
                    setSprintName('');
                    setSprintStart('');
                    setSprintEnd('');
                  })
                }
              >
                {tCommon('add')}
              </Button>
            </div>
            {sprints.map((row) => (
              <SprintRow
                key={String(row.id)}
                row={row}
                busy={busy}
                onSave={(payload) =>
                  void run(async () => {
                    await apiClient.patch(`/v1/projects/sprints/${String(row.id)}`, payload);
                  })
                }
                onDelete={() =>
                  void run(async () => {
                    await apiClient.delete(`/v1/projects/sprints/${String(row.id)}`);
                  })
                }
                onStart={() =>
                  void run(async () => {
                    await apiClient.post(`/v1/projects/sprints/${String(row.id)}/start`);
                  })
                }
                onFinish={() =>
                  void run(async () => {
                    await apiClient.post(`/v1/projects/sprints/${String(row.id)}/finish`);
                  })
                }
              />
            ))}
          </PlanningTable>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-start text-base">
            {t('epics')} ({formatNumber(epics.length)})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <PlanningTable rows={epics} empty={t('noEpics')}>
            <div className="space-y-2">
              <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-[180px] flex-1 space-y-1">
                  <Label>{t('taskTitle')}</Label>
                  <Input value={epicTitle} onChange={(e) => setEpicTitle(e.target.value)} />
                </div>
                <Button
                  type="button"
                  size="sm"
                  disabled={busy || !epicTitle.trim()}
                  onClick={() =>
                    void run(async () => {
                      await apiClient.post('/v1/projects/epics', {
                        project_id: Number(projectId),
                        title: epicTitle.trim(),
                        description: epicDescription.trim() || null,
                        state: 'open',
                      });
                      setEpicTitle('');
                      setEpicDescription('');
                    })
                  }
                >
                  {tCommon('add')}
                </Button>
              </div>
              <Textarea
                value={epicDescription}
                onChange={(e) => setEpicDescription(e.target.value)}
                placeholder={t('description')}
                rows={2}
              />
            </div>
            {epics.map((row) => (
              <EpicRow
                key={String(row.id)}
                row={row}
                busy={busy}
                onSave={(payload) =>
                  void run(async () => {
                    await apiClient.patch(`/v1/projects/epics/${String(row.id)}`, payload);
                  })
                }
                onDelete={() =>
                  void run(async () => {
                    await apiClient.delete(`/v1/projects/epics/${String(row.id)}`);
                  })
                }
              />
            ))}
          </PlanningTable>
        </CardContent>
      </Card>
    </div>
  );
}

function MilestoneRow({
  row,
  busy,
  onSave,
  onDelete,
}: {
  row: Row;
  busy: boolean;
  onSave: (payload: Record<string, unknown>) => void;
  onDelete: () => void;
}) {
  const t = useTranslations('pm.projects');
  const tCommon = useTranslations('common');
  const [title, setTitle] = useState(String(row.title ?? ''));
  const [due, setDue] = useState(isoDay(row.due_date));
  const [status, setStatus] = useState(String(row.status ?? 'open'));

  return (
    <div className="flex flex-wrap items-end gap-2 rounded-md border p-2">
      <Input className="min-w-[160px] flex-1" value={title} onChange={(e) => setTitle(e.target.value)} />
      <div className="w-[160px]">
        <LocaleDatePicker value={due} onChange={setDue} />
      </div>
      <Select value={status} onValueChange={setStatus}>
        <SelectTrigger className="w-[140px]">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="open">{t('statusOpen')}</SelectItem>
          <SelectItem value="done">{t('statusDone')}</SelectItem>
        </SelectContent>
      </Select>
      <Button type="button" size="sm" variant="secondary" disabled={busy || !title.trim()} onClick={() => onSave({ title: title.trim(), due_date: due || null, status })}>
        {tCommon('save')}
      </Button>
      <Button type="button" size="sm" variant="ghost" disabled={busy} onClick={onDelete}>
        {tCommon('delete')}
      </Button>
    </div>
  );
}

function SprintRow({
  row,
  busy,
  onSave,
  onDelete,
  onStart,
  onFinish,
}: {
  row: Row;
  busy: boolean;
  onSave: (payload: Record<string, unknown>) => void;
  onDelete: () => void;
  onStart: () => void;
  onFinish: () => void;
}) {
  const t = useTranslations('pm.projects');
  const tCommon = useTranslations('common');
  const { formatNumber } = useLocale();
  const [name, setName] = useState(String(row.name ?? ''));
  const [starts, setStarts] = useState(isoDay(row.starts_at));
  const [ends, setEnds] = useState(isoDay(row.ends_at));
  const [status, setStatus] = useState(String(row.status ?? 'planned'));
  const taskCount = Number(row.tasks_count ?? 0);

  return (
    <div className="space-y-2 rounded-md border p-2">
      <div className="flex flex-wrap items-end gap-2">
        <Input className="min-w-[160px] flex-1" value={name} onChange={(e) => setName(e.target.value)} />
        <div className="w-[150px]">
          <LocaleDatePicker value={starts} onChange={setStarts} />
        </div>
        <div className="w-[150px]">
          <LocaleDatePicker value={ends} onChange={setEnds} />
        </div>
        <Select value={status || 'planned'} onValueChange={setStatus}>
          <SelectTrigger className="w-[140px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="planned">{t('sprintPlanned')}</SelectItem>
            <SelectItem value="active">{t('statusActive')}</SelectItem>
            <SelectItem value="completed">{t('statusCompleted')}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs text-muted-foreground">
          {t('tasks')}: {formatNumber(taskCount)}
        </span>
        <Button type="button" size="sm" variant="secondary" disabled={busy || !name.trim()} onClick={() => onSave({ name: name.trim(), starts_at: starts || null, ends_at: ends || null, status })}>
          {tCommon('save')}
        </Button>
        <Button type="button" size="sm" variant="outline" disabled={busy} onClick={onStart}>
          {t('sprintStart')}
        </Button>
        <Button type="button" size="sm" variant="outline" disabled={busy} onClick={onFinish}>
          {t('sprintFinish')}
        </Button>
        <Button type="button" size="sm" variant="ghost" disabled={busy} onClick={onDelete}>
          {tCommon('delete')}
        </Button>
      </div>
    </div>
  );
}

function EpicRow({
  row,
  busy,
  onSave,
  onDelete,
}: {
  row: Row;
  busy: boolean;
  onSave: (payload: Record<string, unknown>) => void;
  onDelete: () => void;
}) {
  const t = useTranslations('pm.projects');
  const tCommon = useTranslations('common');
  const { formatNumber } = useLocale();
  const [title, setTitle] = useState(String(row.title ?? ''));
  const [description, setDescription] = useState(String(row.description ?? ''));
  const [state, setState] = useState(String(row.state ?? 'open'));
  const [start, setStart] = useState(isoDay(row.start_date));
  const [end, setEnd] = useState(isoDay(row.end_date));
  const taskCount = Number(row.tasks_count ?? 0);

  return (
    <div className="space-y-2 rounded-md border p-2">
      <div className="flex flex-wrap items-end gap-2">
        <Input className="min-w-[160px] flex-1" value={title} onChange={(e) => setTitle(e.target.value)} />
        <div className="w-[150px]">
          <LocaleDatePicker value={start} onChange={setStart} />
        </div>
        <div className="w-[150px]">
          <LocaleDatePicker value={end} onChange={setEnd} />
        </div>
        <Select value={state || 'open'} onValueChange={setState}>
          <SelectTrigger className="w-[140px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="open">{t('statusOpen')}</SelectItem>
            <SelectItem value="done">{t('statusDone')}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <Textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs text-muted-foreground">
          {t('tasks')}: {formatNumber(taskCount)}
        </span>
        <Button
          type="button"
          size="sm"
          variant="secondary"
          disabled={busy || !title.trim()}
          onClick={() =>
            onSave({
              title: title.trim(),
              description: description.trim() || null,
              state,
              start_date: start || null,
              end_date: end || null,
            })
          }
        >
          {tCommon('save')}
        </Button>
        <Button type="button" size="sm" variant="ghost" disabled={busy} onClick={onDelete}>
          {tCommon('delete')}
        </Button>
      </div>
    </div>
  );
}
