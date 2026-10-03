'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { expansionApi, type Row } from '@/features/expansion/api';
import { AiAssistPanel } from '@/features/expansion/AiAssistPanel';
import { enqueueOfflineOp, readOfflineOps, replaceOfflineOps, type OfflineOp } from '@/features/expansion/offline-queue';

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';

export function PlanningPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [projectId, setProjectId] = useState('');
  const [path, setPath] = useState<Row | null>(null);
  const [baselines, setBaselines] = useState<Row[]>([]);
  const [baselineName, setBaselineName] = useState('');
  const [capacity, setCapacity] = useState<Row[]>([]);
  const [capForm, setCapForm] = useState({ user_id: '', weekday: '1', hours: '8' });
  const [leave, setLeave] = useState({ user_id: '', starts_on: '', ends_on: '', note: '' });
  const [board, setBoard] = useState({ id: '', swimlane_field: 'assignee' });
  const [queued, setQueued] = useState<OfflineOp[]>([]);
  const [offlineTask, setOfflineTask] = useState('');

  const load = useCallback(async () => {
    setQueued(readOfflineOps());
    try {
      const [base, cap] = await Promise.all([
        expansionApi.baselines(projectId ? Number(projectId) : undefined),
        expansionApi.capacity(),
      ]);
      setBaselines(base);
      setCapacity(cap);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, projectId]);

  useEffect(() => {
    void load();
  }, [load]);

  async function flush() {
    const ops = readOfflineOps();
    if (!ops.length || !navigator.onLine) return;
    try {
      await expansionApi.offlineOps(ops);
      replaceOfflineOps([]);
      setQueued([]);
      setSuccess(t('planning.synced'));
    } catch (err) {
      applyAxiosError(err);
    }
  }

  return (
    <CrmPageLayout title={t('planning.title')} description={t('planning.description')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('planning.critical')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('planning.projectId')} value={projectId} onChange={(e) => setProjectId(e.target.value)} />
            <Button type="button" onClick={() => void expansionApi.criticalPath(projectId ? { project_id: Number(projectId) } : {}).then(setPath).catch(applyAxiosError)}>{t('planning.compute')}</Button>
            {path ? (
              <pre className="max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs">{JSON.stringify(path, null, 2)}</pre>
            ) : null}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('planning.baseline')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('planning.baselineName')} value={baselineName} onChange={(e) => setBaselineName(e.target.value)} />
            <Button type="button" onClick={() => void expansionApi.saveBaseline({ name: baselineName, project_id: projectId ? Number(projectId) : null }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
            <ul className="text-sm">{baselines.map((row) => <li key={String(row.id)}>{String(row.name)}</li>)}</ul>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('planning.capacity')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <div className="grid grid-cols-3 gap-2">
              <Input placeholder={t('planning.userId')} value={capForm.user_id} onChange={(e) => setCapForm({ ...capForm, user_id: e.target.value })} />
              <Input placeholder={t('planning.weekday')} value={capForm.weekday} onChange={(e) => setCapForm({ ...capForm, weekday: e.target.value })} />
              <Input placeholder={t('planning.hours')} value={capForm.hours} onChange={(e) => setCapForm({ ...capForm, hours: e.target.value })} />
            </div>
            <Button type="button" onClick={() => void expansionApi.saveCapacity({ user_id: Number(capForm.user_id), weekday: Number(capForm.weekday), hours: Number(capForm.hours) }).then(() => load()).catch(applyAxiosError)}>{t('common.save')}</Button>
            <ul className="text-sm">
              {capacity.map((row) => (
                <li key={String(row.user_id)}>
                  #{String(row.user_id)} {t('planning.available')}: {String(row.available_hours)} / {t('planning.allocated')}: {String(row.allocated_hours)}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('planning.leave')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('planning.userId')} value={leave.user_id} onChange={(e) => setLeave({ ...leave, user_id: e.target.value })} />
            <Input type="date" value={leave.starts_on} onChange={(e) => setLeave({ ...leave, starts_on: e.target.value })} />
            <Input type="date" value={leave.ends_on} onChange={(e) => setLeave({ ...leave, ends_on: e.target.value })} />
            <Input placeholder={t('planning.note')} value={leave.note} onChange={(e) => setLeave({ ...leave, note: e.target.value })} />
            <Button type="button" onClick={() => void expansionApi.saveLeave({ ...leave, user_id: Number(leave.user_id), kind: 'leave' }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('planning.swimlane')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('planning.boardId')} value={board.id} onChange={(e) => setBoard({ ...board, id: e.target.value })} />
            <select className={selectClass} value={board.swimlane_field} onChange={(e) => setBoard({ ...board, swimlane_field: e.target.value })}>
              <option value="none">{t('planning.laneNone')}</option>
              <option value="assignee">{t('planning.laneAssignee')}</option>
              <option value="priority">{t('planning.lanePriority')}</option>
              <option value="label">{t('planning.laneLabel')}</option>
              <option value="custom">{t('planning.laneCustom')}</option>
            </select>
            <p className="text-xs text-muted-foreground">{t('planning.wipHint')}</p>
            <Button type="button" variant="outline" onClick={() => void expansionApi.updateBoard(Number(board.id), { swimlane_field: board.swimlane_field }).then(() => setSuccess(t('common.saved'))).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('planning.offline')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('planning.taskTitle')} value={offlineTask} onChange={(e) => setOfflineTask(e.target.value)} />
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  enqueueOfflineOp({ action: 'create_task', payload: { title: offlineTask, project_id: projectId ? Number(projectId) : null } });
                  setOfflineTask('');
                  setQueued(readOfflineOps());
                  setSuccess(t('planning.queued'));
                }}
              >
                {t('planning.queue')}
              </Button>
              <Button type="button" onClick={() => void flush()}>{t('planning.flush')}</Button>
            </div>
            <p className="text-xs text-muted-foreground">{t('planning.queuedCount', { count: queued.length })}</p>
          </CardContent>
        </Card>
      </div>
      <AiAssistPanel purpose="task_plan" endpoint="pm" />
    </CrmPageLayout>
  );
}
