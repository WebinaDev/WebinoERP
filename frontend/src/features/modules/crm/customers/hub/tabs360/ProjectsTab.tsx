'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { AlertTriangle, CalendarDays, ExternalLink, ListPlus, Plus, Search, Users } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale-next';
import { hubBase, useHubList, type Row } from '../hub-api';
import { useHub } from '../HubContext';
import { DataTable, Empty, HubCard, KV, Loading, Pager, StatusBadge, useFmt } from '../ui';
import { DetailSheet, FilterBar, TabForm, body, selectCls, type TabMeta, type TField, type TValues } from './shared';

export const PROJECT_STATUSES = ['draft', 'active', 'on_hold', 'completed', 'cancelled'] as const;

export function ProjectsTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, refresh } = useHub();
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const list = useHubList(id, 'projects', { q: q.trim() || undefined, status: status || undefined }, true, version);
  const meta = list.meta as TabMeta | null;
  const [form, setForm] = useState<null | { row?: Row }>(null);
  const [detailId, setDetailId] = useState<number | null>(null);
  const contracts = useHubList(id, 'contracts', { per_page: '100' }, !!form, version);
  const base = hubBase(id);
  const ps = (s: string) => t(`t360.ps.${s}` as never);
  const counts = meta?.status_counts ?? {};

  const fields: TField[] = [
    { name: 'name', label: t('t360.projectName'), required: true },
    { name: 'status', label: t('t360.status'), type: 'select', required: !!form?.row, options: PROJECT_STATUSES.map((s) => [s, ps(s)]) },
    { name: 'start_date', label: t('t360.startDate'), type: 'date' },
    { name: 'due_date', label: t('t360.dueDate'), type: 'date' },
    { name: 'budget', label: t('t360.budget'), type: 'number' },
    { name: 'progress_pct', label: t('t360.progress'), type: 'number', hint: t('t360.progressHint') },
    { name: 'contract_id', label: t('t360.contract'), type: 'select', options: contracts.rows.filter((c) => c.source === 'projects' && c.status !== 'cancelled').map((c) => [String(c.id), c.title] as [string, string]) },
    { name: 'description', label: t('t360.description'), type: 'textarea' },
  ];
  const submit = async (v: TValues) => {
    const b = body(v, ['budget', 'progress_pct', 'contract_id']);
    if (form?.row) await apiClient.patch(`${base}/projects/${form.row.id}`, { ...b, contract_id: v.contract_id ? Number(v.contract_id) : null });
    else await apiClient.post(`${base}/projects`, b);
    refresh();
  };
  const initial = (r?: Row): TValues => (r ? {
    name: r.name ?? '', status: r.status ?? 'draft', start_date: r.start_date ?? '', due_date: r.due_date ?? '',
    budget: r.budget === null || r.budget === undefined ? '' : String(r.budget), progress_pct: '', contract_id: r.contract?.id ? String(r.contract.id) : '', description: r.description ?? '',
  } : { status: 'draft' });

  return (
    <HubCard title={t('t360.projects')} actions={meta?.can?.create ? <Button size="sm" onClick={() => setForm({})}><Plus className="me-1 h-4 w-4" />{t('t360.newProject')}</Button> : null}>
      <FilterBar>
        <div className="relative">
          <Search className="pointer-events-none absolute start-2 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input className="h-9 w-48 ps-8" placeholder={t('t360.search')} aria-label={t('t360.search')} value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className="flex flex-wrap gap-1" role="group" aria-label={t('t360.status')}>
          <Button size="sm" variant={status === '' ? 'default' : 'outline'} onClick={() => setStatus('')}>{t('t360.allStatuses')}</Button>
          {PROJECT_STATUSES.map((s) => (
            <Button key={s} size="sm" variant={status === s ? 'default' : 'outline'} onClick={() => setStatus(s)}>
              {ps(s)}{counts[s] ? <Badge variant="secondary" className="ms-1">{f.num(counts[s])}</Badge> : null}
            </Button>
          ))}
        </div>
      </FilterBar>
      {list.loading && !list.rows.length ? <Loading /> : !list.rows.length ? <Empty /> : (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {list.rows.map((p) => (
            <button key={p.id} type="button" onClick={() => setDetailId(p.id)} className="rounded-lg border p-4 text-start transition hover:border-primary hover:shadow-sm" data-testid="project-card">
              <div className="flex items-start justify-between gap-2">
                <span className="font-medium">{p.name}</span>
                <StatusBadge value={p.status} label={ps(p.status)} />
              </div>
              <div className="mt-3 flex items-center gap-2">
                <Progress value={p.progress_pct ?? 0} className="h-2 flex-1" aria-label={t('t360.progress')} />
                <span className="text-xs tabular-nums">{f.num(p.progress_pct ?? 0)}٪</span>
              </div>
              <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" />{f.date(p.due_date)}</span>
                <span className="inline-flex items-center gap-1"><Users className="h-3.5 w-3.5" />{f.num((p.members ?? []).length)}</span>
                <span>{t('t360.tasksDone', { done: f.num(p.tasks?.done ?? 0), total: f.num(p.tasks?.total ?? 0) })}</span>
                {p.overdue ? <span className="inline-flex items-center gap-1 text-destructive"><AlertTriangle className="h-3.5 w-3.5" />{t('t360.overdue')}</span> : null}
              </div>
              {p.contract ? <div className="mt-2 truncate text-xs">{t('t360.contract')}: {p.contract.title}</div> : null}
            </button>
          ))}
        </div>
      )}
      <Pager meta={list.meta} onPage={list.setPage} />
      <TabForm open={!!form} onOpenChange={(o) => { if (!o) setForm(null); }} title={form?.row ? t('edit') : t('t360.newProject')}
        fields={form?.row ? fields : fields.filter((x) => x.name !== 'progress_pct')} initial={initial(form?.row)} onSubmit={submit} />
      {detailId !== null ? <ProjectDetail projectId={detailId} onClose={() => setDetailId(null)} onEdit={(r) => setForm({ row: r })} /> : null}
    </HubCard>
  );
}

function ProjectDetail({ projectId, onClose, onEdit }: { projectId: number; onClose: () => void; onEdit: (r: Row) => void }) {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, refresh, setNotice } = useHub();
  const [p, setP] = useState<Row | null>(null);
  const [task, setTask] = useState(false);
  const { locale } = useLocale();
  const base = hubBase(id);
  useEffect(() => {
    let live = true;
    apiClient.get(`${base}/projects/${projectId}`)
      .then((res) => { if (live) setP((res.data as { data: Row }).data); })
      .catch((e) => { if (live) { setNotice(getApiErrorMessage(e)); onClose(); } });
    return () => { live = false; };
  }, [base, projectId, version, setNotice, onClose]);
  const ps = (s: string) => t(`t360.ps.${s}` as never);
  return (
    <DetailSheet open onOpenChange={(o) => { if (!o) onClose(); }} title={p ? p.name : t('loading')}>
      {!p ? <Loading /> : <>
        <div className="flex flex-wrap gap-2">
          {p.can?.update ? <Button size="sm" variant="outline" onClick={() => onEdit(p)}>{t('edit')}</Button> : null}
          {p.can?.add_task ? <Button size="sm" variant="outline" onClick={() => setTask(true)}><ListPlus className="me-1 h-4 w-4" />{t('t360.addTask')}</Button> : null}
          <Button size="sm" variant="ghost" asChild><a href={dashboardHref(locale, `pm/projects/${p.id}`)}><ExternalLink className="me-1 h-4 w-4" />{t('t360.openProject')}</a></Button>
        </div>
        <div className="flex items-center gap-2">
          <Progress value={p.progress_pct ?? 0} className="h-2 flex-1" />
          <span className="text-xs tabular-nums">{f.num(p.progress_pct ?? 0)}٪</span>
        </div>
        <KV items={[
          [t('t360.status'), <StatusBadge key="s" value={p.status} label={ps(p.status)} />],
          [t('t360.manager'), p.manager?.name],
          [t('t360.startDate'), f.date(p.start_date)],
          [t('t360.dueDate'), <span key="d" className={p.overdue ? 'text-destructive' : ''}>{f.date(p.due_date)}</span>],
          [t('t360.budget'), f.money(p.budget)],
          [t('t360.spentHours'), f.num(p.spent_hours)],
          [t('t360.billable'), f.money(p.billable_amount)],
          [t('t360.invoiced'), f.money(p.invoiced_amount)],
          [t('t360.contract'), p.contract?.title],
          [t('t360.filesTickets'), `${f.num(p.files_count ?? 0)} / ${f.num(p.tickets_count ?? 0)}`],
          [t('t360.description'), p.description ? <span className="whitespace-pre-wrap">{p.description}</span> : null],
        ]} />
        <section>
          <h4 className="mb-2 font-medium">{t('t360.members')}</h4>
          {(p.members ?? []).length ? (
            <div className="flex flex-wrap gap-1">{p.members.map((m: Row) => <Badge key={m.id} variant={m.role === 'manager' ? 'default' : 'outline'}>{m.name ?? `#${m.id}`}</Badge>)}</div>
          ) : <Empty />}
        </section>
        <section>
          <h4 className="mb-2 font-medium">{t('t360.milestones')}</h4>
          <DataTable rows={p.milestones ?? []} cols={[
            { key: 'title', label: t('doc.title') },
            { key: 'due_date', label: t('t360.dueDate'), render: (m) => f.date(m.due_date) },
            { key: 'status', label: t('t360.status'), render: (m) => <StatusBadge value={m.status} /> },
          ]} />
        </section>
        <section>
          <h4 className="mb-2 font-medium">{t('t360.openTasks')}</h4>
          <DataTable rows={p.open_tasks ?? []} cols={[
            { key: 'title', label: t('doc.title') },
            { key: 'assignee', label: t('t360.assignee'), render: (x) => x.assignee?.name ?? '—' },
            { key: 'due_at', label: t('t360.dueDate'), render: (x) => <span className={x.overdue ? 'text-destructive' : ''}>{f.date(x.due_at)}</span> },
            { key: 'status', label: t('t360.status'), render: (x) => <StatusBadge value={x.status} /> },
          ]} />
        </section>
        {(p.recent_time ?? []).length ? (
          <section>
            <h4 className="mb-2 font-medium">{t('t360.recentTime')}</h4>
            <DataTable rows={p.recent_time} cols={[
              { key: 'user', label: t('t360.assignee'), render: (x) => x.user?.name ?? '—' },
              { key: 'hours', label: t('t360.spentHours'), render: (x) => f.num(x.hours) },
              { key: 'started_at', label: t('t360.date'), render: (x) => f.date(x.started_at) },
            ]} />
          </section>
        ) : null}
        <TabForm open={task} onOpenChange={setTask} title={t('t360.addTask')}
          fields={[
            { name: 'title', label: t('doc.title'), required: true },
            { name: 'due_at', label: t('t360.dueDate'), type: 'date' },
            { name: 'priority', label: t('t360.priority'), type: 'select', options: ['low', 'medium', 'high', 'urgent'].map((x) => [x, t(`t360.pr.${x}` as never)]) },
          ]}
          onSubmit={async (v) => { await apiClient.post(`${base}/projects/${p.id}/tasks`, body(v)); refresh(); }} />
      </>}
    </DetailSheet>
  );
}
