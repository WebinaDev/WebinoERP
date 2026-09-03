'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Clock, Pause, Play, Square } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { useLocale } from '@/hooks/use-locale';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmConfirmDialog, PmEmptyState, PmFilterBar } from '@/features/shared/pm';
import { TableListSkeleton } from '@/components/TableListSkeleton';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

type TimeEntry = {
  id: number;
  user_id?: number;
  task_id?: number | null;
  project_id?: number | null;
  description?: string | null;
  started_at?: string | null;
  ended_at?: string | null;
  paused_at?: string | null;
  duration_seconds?: number | null;
  is_running?: boolean;
  is_billable?: boolean;
  status?: 'running' | 'paused' | 'stopped';
};

type ProjectOption = { id: number; name: string };

type ReportData = {
  total_seconds: number;
  billable_seconds: number;
  non_billable_seconds: number;
  by_task: { task_id: number | null; total_seconds: number }[];
};

function entryStatus(entry: TimeEntry): 'running' | 'paused' | 'stopped' {
  if (entry.is_running) return 'running';
  if (entry.paused_at) return 'paused';
  return 'stopped';
}

function withStatus(entry: TimeEntry): TimeEntry {
  return { ...entry, status: entryStatus(entry) };
}

function formatDurationMinutes(seconds: number | null | undefined, formatNumber: (n: number) => string) {
  if (seconds == null || Number.isNaN(Number(seconds))) return '—';
  return formatNumber(Math.round(Number(seconds) / 60));
}

function formatSeconds(seconds: number, formatNumber: (n: number) => string) {
  const total = Math.max(0, Math.floor(seconds));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  if (h > 0) return `${formatNumber(h)}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  return `${formatNumber(m)}:${String(s).padStart(2, '0')}`;
}

export function TimeTrackingPage() {
  const t = useTranslations('pm.timeTracking');
  const tCommon = useTranslations('common');
  const tNav = useTranslations();
  const { isRtl, formatDateTime, formatNumber } = useLocale();
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();

  const [tab, setTab] = useState<'entries' | 'report'>('entries');
  const [timer, setTimer] = useState<TimeEntry | null>(null);
  const [entries, setEntries] = useState<TimeEntry[]>([]);
  const [report, setReport] = useState<ReportData | null>(null);
  const [projects, setProjects] = useState<ProjectOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [savingManual, setSavingManual] = useState(false);
  const [timerBusy, setTimerBusy] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);

  const [dateFrom, setDateFrom] = useState(() => {
    const d = new Date();
    d.setDate(1);
    return d.toISOString().slice(0, 10);
  });
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().slice(0, 10));

  const [description, setDescription] = useState('');
  const [timerProjectId, setTimerProjectId] = useState<string>('');

  const [manualProjectId, setManualProjectId] = useState<string>('');
  const [manualDate, setManualDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [manualMinutes, setManualMinutes] = useState('60');
  const [manualDescription, setManualDescription] = useState('');
  const [manualBillable, setManualBillable] = useState(false);

  const loadProjects = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/projects/projects', { params: { per_page: 100 } });
      const rows = normalizeListPayload(unwrapData(res));
      setProjects(
        rows
          .map((row) => ({
            id: Number(row.id),
            name: String(row.name ?? row.title ?? `#${row.id}`),
          }))
          .filter((p) => Number.isFinite(p.id) && p.id > 0),
      );
    } catch {
      // non-blocking — picker stays empty
    }
  }, []);

  const loadTimer = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/projects/time-entries/active');
      const active = unwrapData<TimeEntry | null>(res);
      if (active && typeof active === 'object' && active.id) {
        setTimer(withStatus(active));
        return;
      }

      const listRes = await apiClient.get('/v1/projects/time-entries', { params: { per_page: 10 } });
      const rows = normalizeListPayload(unwrapData(listRes)) as TimeEntry[];
      const paused = rows.find((row) => Boolean(row.paused_at) && !row.is_running && !row.ended_at);
      setTimer(paused ? withStatus(paused) : null);
    } catch {
      // keep previous timer state on poll failure
    }
  }, []);

  const loadEntries = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/time-entries', {
        params: { from: dateFrom, to: dateTo, per_page: 100 },
      });
      const rows = normalizeListPayload(unwrapData(res)) as TimeEntry[];
      setEntries(rows.map(withStatus));
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setLoading(false);
    }
  }, [dateFrom, dateTo, t, setError, applyAxiosError]);

  const loadReport = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/time-entries/report', {
        params: { from: dateFrom, to: dateTo },
      });
      const data = unwrapData<ReportData>(res);
      setReport({
        total_seconds: Number(data?.total_seconds ?? 0),
        billable_seconds: Number(data?.billable_seconds ?? 0),
        non_billable_seconds: Number(data?.non_billable_seconds ?? 0),
        by_task: Array.isArray(data?.by_task) ? data.by_task : [],
      });
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setLoading(false);
    }
  }, [dateFrom, dateTo, t, setError, applyAxiosError]);

  useEffect(() => {
    void loadProjects();
  }, [loadProjects]);

  useEffect(() => {
    void loadTimer();
    const id = window.setInterval(() => void loadTimer(), 15000);
    return () => window.clearInterval(id);
  }, [loadTimer]);

  useEffect(() => {
    if (tab === 'entries') void loadEntries();
    else void loadReport();
  }, [tab, loadEntries, loadReport]);

  const refreshCurrentTab = () => {
    if (tab === 'entries') void loadEntries();
    else void loadReport();
  };

  const handleStart = async () => {
    setTimerBusy(true);
    setError(null);
    try {
      const res = await apiClient.post('/v1/projects/time-entries/start', {
        description: description.trim() || undefined,
        project_id: timerProjectId ? Number(timerProjectId) : undefined,
      });
      const entry = unwrapData<TimeEntry>(res);
      setDescription('');
      setTimer(entry ? withStatus(entry) : null);
      if (tab === 'entries') await loadEntries();
      setSuccess(t('started'));
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setTimerBusy(false);
    }
  };

  const handleStop = async () => {
    setTimerBusy(true);
    setError(null);
    try {
      // Backend stop only finds is_running=true; resume paused timers first.
      if (timer?.status === 'paused') {
        await apiClient.post('/v1/projects/time-entries/resume');
      }
      await apiClient.post('/v1/projects/time-entries/stop');
      setTimer(null);
      refreshCurrentTab();
      setSuccess(t('stopped'));
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setTimerBusy(false);
    }
  };

  const handlePause = async () => {
    setTimerBusy(true);
    setError(null);
    try {
      const res = await apiClient.post('/v1/projects/time-entries/pause');
      const data = unwrapData<{ entry?: TimeEntry }>(res);
      if (data?.entry) setTimer(withStatus({ ...data.entry, paused_at: data.entry.paused_at ?? new Date().toISOString() }));
      else await loadTimer();
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setTimerBusy(false);
    }
  };

  const handleResume = async () => {
    setTimerBusy(true);
    setError(null);
    try {
      const res = await apiClient.post('/v1/projects/time-entries/resume');
      const data = unwrapData<{ entry?: TimeEntry }>(res);
      if (data?.entry) setTimer(withStatus(data.entry));
      else await loadTimer();
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setTimerBusy(false);
    }
  };

  const handleManualSubmit = async () => {
    const minutes = Number(manualMinutes);
    if (!Number.isFinite(minutes) || minutes <= 0) {
      setError(t('invalidDuration'));
      return;
    }
    const durationSeconds = Math.min(86400, Math.round(minutes * 60));
    setSavingManual(true);
    setError(null);
    try {
      await apiClient.post('/v1/projects/time-entries/manual', {
        project_id: manualProjectId ? Number(manualProjectId) : undefined,
        date: manualDate || undefined,
        duration_seconds: durationSeconds,
        description: manualDescription.trim() || undefined,
        is_billable: manualBillable,
      });
      setManualDescription('');
      setManualMinutes('60');
      setManualBillable(false);
      setSuccess(t('manualSaved'));
      if (tab === 'entries') await loadEntries();
      else await loadReport();
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setSavingManual(false);
    }
  };

  const confirmDelete = async () => {
    if (deleteId == null) return;
    setDeleting(true);
    setError(null);
    try {
      await apiClient.delete(`/v1/projects/time-entries/${deleteId}`);
      setEntries((prev) => prev.filter((e) => e.id !== deleteId));
      setDeleteId(null);
      setSuccess(tCommon('deleted'));
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setDeleting(false);
    }
  };

  const projectLabel = (projectId?: number | null) => {
    if (!projectId) return '—';
    const found = projects.find((p) => p.id === projectId);
    return found ? found.name : `#${projectId}`;
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.pm.timeTracking')} {...layoutProps}>
      <div className="space-y-4" dir={isRtl ? 'rtl' : 'ltr'}>
        <Card className="text-start">
          <CardHeader className="text-start">
            <CardTitle className="flex items-center gap-2 text-base text-start">
              <Clock className="h-4 w-4" />
              {t('active')}
            </CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap items-end gap-3">
            {timer ? (
              <>
                <div className="text-sm">
                  <span className="text-muted-foreground">{t('project')}: </span>
                  {projectLabel(timer.project_id)}
                  {timer.task_id ? (
                    <span className="ms-2 text-muted-foreground">
                      {t('task')} #{timer.task_id}
                    </span>
                  ) : null}
                  {timer.description ? <p className="mt-1">{timer.description}</p> : null}
                  <p className="mt-1 text-xs text-muted-foreground">
                    {timer.started_at ? formatDateTime(timer.started_at) : '—'} — {t(`status_${timer.status ?? 'stopped'}`)}
                  </p>
                </div>
                {timer.status === 'running' ? (
                  <Button type="button" variant="outline" size="sm" disabled={timerBusy} onClick={() => void handlePause()}>
                    <Pause className="me-1 h-4 w-4" />
                    {t('pause')}
                  </Button>
                ) : null}
                {timer.status === 'paused' ? (
                  <Button type="button" variant="outline" size="sm" disabled={timerBusy} onClick={() => void handleResume()}>
                    <Play className="me-1 h-4 w-4" />
                    {t('resume')}
                  </Button>
                ) : null}
                <Button type="button" variant="destructive" size="sm" disabled={timerBusy} onClick={() => void handleStop()}>
                  <Square className="me-1 h-4 w-4" />
                  {t('stop')}
                </Button>
              </>
            ) : (
              <>
                <div className="grid w-full max-w-xl gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label>{t('project')}</Label>
                    <Select value={timerProjectId || 'none'} onValueChange={(v) => setTimerProjectId(v === 'none' ? '' : v)}>
                      <SelectTrigger>
                        <SelectValue placeholder={t('selectProject')} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">{t('noProject')}</SelectItem>
                        {projects.map((p) => (
                          <SelectItem key={p.id} value={String(p.id)}>
                            {p.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>{t('description')}</Label>
                    <Input
                      value={description}
                      onChange={(e) => setDescription(e.target.value)}
                      placeholder={t('descriptionPlaceholder')}
                    />
                  </div>
                </div>
                <Button type="button" disabled={timerBusy} onClick={() => void handleStart()}>
                  <Play className="me-1 h-4 w-4" />
                  {t('start')}
                </Button>
                <span className="text-sm text-muted-foreground">{t('noTimer')}</span>
              </>
            )}
          </CardContent>
        </Card>

        <Card className="text-start">
          <CardHeader className="text-start">
            <CardTitle className="text-base text-start">{t('manualTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-1.5">
              <Label>{t('project')}</Label>
              <Select value={manualProjectId || 'none'} onValueChange={(v) => setManualProjectId(v === 'none' ? '' : v)}>
                <SelectTrigger>
                  <SelectValue placeholder={t('selectProject')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">{t('noProject')}</SelectItem>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>{t('date')}</Label>
              <LocaleDatePicker value={manualDate} onChange={setManualDate} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('durationMinutes')}</Label>
              <Input
                type="number"
                min={1}
                max={1440}
                value={manualMinutes}
                onChange={(e) => setManualMinutes(e.target.value)}
              />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label>{t('description')}</Label>
              <Input
                value={manualDescription}
                onChange={(e) => setManualDescription(e.target.value)}
                placeholder={t('descriptionPlaceholder')}
              />
            </div>
            <div className="flex items-end gap-3">
              <label className="flex items-center gap-2 text-sm">
                <Checkbox checked={manualBillable} onCheckedChange={(v) => setManualBillable(v === true)} />
                {t('billable')}
              </label>
              <Button type="button" disabled={savingManual} onClick={() => void handleManualSubmit()}>
                {savingManual ? tCommon('loading') : t('addManual')}
              </Button>
            </div>
          </CardContent>
        </Card>

        <PmFilterBar onApply={refreshCurrentTab} applyLabel={tCommon('apply')}>
          <div className="space-y-1.5">
            <Label>{t('dateFrom')}</Label>
            <LocaleDatePicker value={dateFrom} onChange={setDateFrom} />
          </div>
          <div className="space-y-1.5">
            <Label>{t('dateTo')}</Label>
            <LocaleDatePicker value={dateTo} onChange={setDateTo} />
          </div>
        </PmFilterBar>

        <Tabs
          value={tab}
          onValueChange={(v) => setTab(v as 'entries' | 'report')}
          dir={isRtl ? 'rtl' : 'ltr'}
          className="text-start"
        >
          <TabsList className="text-start">
            <TabsTrigger value="entries">{t('entries')}</TabsTrigger>
            <TabsTrigger value="report">{t('report')}</TabsTrigger>
          </TabsList>

          <TabsContent value="entries" className="mt-4 text-start">
            <Card className="text-start">
              <CardContent className="pt-6 text-start">
                {loading ? (
                  <TableListSkeleton rows={8} columns={5} />
                ) : entries.length === 0 ? (
                  <PmEmptyState title={tCommon('noData')} />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t('colProject')}</TableHead>
                        <TableHead>{t('colDescription')}</TableHead>
                        <TableHead>{t('colMinutes')}</TableHead>
                        <TableHead>{t('colStart')}</TableHead>
                        <TableHead>{t('colStatus')}</TableHead>
                        <TableHead>{t('colBillable')}</TableHead>
                        <TableHead />
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {entries.map((e) => (
                        <TableRow key={e.id}>
                          <TableCell>{projectLabel(e.project_id)}</TableCell>
                          <TableCell className="max-w-[240px] truncate">{e.description || '—'}</TableCell>
                          <TableCell>{formatDurationMinutes(e.duration_seconds, formatNumber)}</TableCell>
                          <TableCell className="text-sm text-muted-foreground">
                            {e.started_at ? formatDateTime(e.started_at) : '—'}
                          </TableCell>
                          <TableCell>{t(`status_${e.status ?? entryStatus(e)}`)}</TableCell>
                          <TableCell>{e.is_billable ? t('yes') : t('no')}</TableCell>
                          <TableCell>
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              className="text-destructive"
                              onClick={() => setDeleteId(e.id)}
                            >
                              {tCommon('delete')}
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="report" className="mt-4 space-y-4 text-start">
            {loading ? (
              <Card>
                <CardContent className="pt-6">
                  <TableListSkeleton rows={6} columns={3} />
                </CardContent>
              </Card>
            ) : !report ? (
              <PmEmptyState title={tCommon('noData')} />
            ) : (
              <>
                <div className="grid gap-3 sm:grid-cols-3">
                  <Card>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-sm font-medium text-muted-foreground">{t('totalTime')}</CardTitle>
                    </CardHeader>
                    <CardContent className="text-2xl font-semibold">
                      {formatSeconds(report.total_seconds, formatNumber)}
                    </CardContent>
                  </Card>
                  <Card>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-sm font-medium text-muted-foreground">{t('billableTime')}</CardTitle>
                    </CardHeader>
                    <CardContent className="text-2xl font-semibold">
                      {formatSeconds(report.billable_seconds, formatNumber)}
                    </CardContent>
                  </Card>
                  <Card>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-sm font-medium text-muted-foreground">{t('nonBillableTime')}</CardTitle>
                    </CardHeader>
                    <CardContent className="text-2xl font-semibold">
                      {formatSeconds(report.non_billable_seconds, formatNumber)}
                    </CardContent>
                  </Card>
                </div>
                <Card>
                  <CardHeader>
                    <CardTitle className="text-base">{t('byTask')}</CardTitle>
                  </CardHeader>
                  <CardContent>
                    {report.by_task.length === 0 ? (
                      <PmEmptyState title={tCommon('noData')} />
                    ) : (
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>{t('colTask')}</TableHead>
                            <TableHead>{t('colMinutes')}</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {report.by_task.map((row, i) => (
                            <TableRow key={`${row.task_id ?? 'none'}-${i}`}>
                              <TableCell>
                                {row.task_id != null ? `${t('task')} #${row.task_id}` : t('noTask')}
                              </TableCell>
                              <TableCell>{formatDurationMinutes(row.total_seconds, formatNumber)}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    )}
                  </CardContent>
                </Card>
              </>
            )}
          </TabsContent>
        </Tabs>
      </div>

      <PmConfirmDialog
        open={deleteId != null}
        title={tCommon('delete')}
        description={tCommon('confirmDelete')}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteId(null)}
        pending={deleting}
      />
    </CrmPageLayout>
  );
}
