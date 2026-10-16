'use client';

import { useTranslations } from 'next-intl';
import { useLocale } from '@/hooks/use-locale-next';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  DndContext,
  DragEndEvent,
  PointerSensor,
  closestCorners,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { cn } from '@/lib/utils';
import { normalizeListPayload, readEntity } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { PmAlerts, PmFilterBar, PmPageHeader, PmViewToggle } from '@/features/shared/pm';
import { TaskCalendarPanel } from '@/features/modules/pm/tasks/TaskCalendarPanel';
import { TaskDetailSheet } from '@/features/modules/pm/tasks/TaskDetailSheet';
import { TaskGanttTimeline } from '@/features/modules/pm/tasks/TaskGanttTimeline';
import type { TaskCalendarEvent, TaskGanttItem, TaskRow } from '@/features/modules/pm/tasks/types';

type ViewMode = 'kanban' | 'list' | 'calendar' | 'gantt';

type KanbanData = {
  swimlane_field?: string;
  columns?: { id: number; name: string; color?: string | null; wip_limit?: number | null }[];
  cards?: {
    id: number;
    column_id?: number | null;
    title?: string;
    status?: string;
    priority?: string | null;
    swimlane_key?: string | null;
  }[];
};

function DraggableTask({
  id,
  title,
  onOpen,
  onDelete,
}: {
  id: number;
  title: string;
  onOpen: (taskId: number) => void;
  onDelete?: (taskId: number) => void;
}) {
  const t = useTranslations('pm.tasks');
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: `task-${id}` });
  const style = transform
    ? {
        transform: `translate3d(${transform.x}px, ${transform.y}px, 0)`,
      }
    : undefined;

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        'min-w-0 rounded-md border bg-background p-2 text-sm shadow-sm',
        isDragging && 'opacity-60'
      )}
    >
      <div className="flex items-start justify-between gap-1">
        <button
          type="button"
          className="min-w-0 flex-1 cursor-grab text-start hover:underline active:cursor-grabbing"
          {...listeners}
          {...attributes}
          onClick={() => onOpen(id)}
        >
          {title}
        </button>
        {onDelete ? (
          <button
            type="button"
            className="shrink-0 rounded px-1 text-xs text-destructive hover:bg-destructive/10"
            onClick={(e) => {
              e.stopPropagation();
              onDelete(id);
            }}
          >
            {t('delete')}
          </button>
        ) : null}
      </div>
    </div>
  );
}

function ColumnDrop({
  colId,
  children,
  name,
  color,
  wip,
}: {
  colId: number;
  children: React.ReactNode;
  name: string;
  color?: string | null;
  wip?: string | null;
}) {
  const { setNodeRef, isOver } = useDroppable({ id: `col-${colId}` });
  return (
    <div
      ref={setNodeRef}
      className={cn(
        'flex min-h-[240px] w-[min(100%,18rem)] shrink-0 flex-col gap-2 rounded-lg border bg-muted/30 p-2 sm:min-h-[280px] sm:w-auto sm:min-w-[13.5rem] sm:max-w-[20rem] sm:flex-1',
        isOver && 'ring-2 ring-primary/40'
      )}
      style={color ? { borderTopColor: color, borderTopWidth: 3 } : undefined}
    >
      <p className="text-xs font-semibold text-muted-foreground">
        {name}
        {wip ? <span className="ms-1 font-normal">{wip}</span> : null}
      </p>
      {children}
    </div>
  );
}

function monthRange(month: Date): { start: string; end: string } {
  const y = month.getFullYear();
  const m = month.getMonth();
  const start = new Date(y, m, 1);
  const end = new Date(y, m + 1, 0);
  const ymd = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  return { start: ymd(start), end: ymd(end) };
}

function toGanttItems(rows: TaskRow[]): TaskGanttItem[] {
  return rows.map((row) => {
    const startRaw = row.starts_at || row.created_at || row.due_at || new Date().toISOString();
    const endRaw = row.due_at || row.created_at || startRaw;
    const start = new Date(startRaw);
    const end = new Date(endRaw);
    const startMs = Number.isNaN(start.getTime()) ? Date.now() : start.getTime();
    let endMs = Number.isNaN(end.getTime()) ? startMs + 86400000 : end.getTime();
    if (endMs <= startMs) endMs = startMs + 86400000;
    const duration = Math.max(1, Math.ceil((endMs - startMs) / 86400000));
    const progress = row.status === 'done' || row.status === 'completed' ? 1 : 0.25;
    return {
      id: row.id,
      text: row.title || `#${row.id}`,
      start_date: new Date(startMs).toISOString().slice(0, 10),
      end_date: new Date(endMs).toISOString().slice(0, 10),
      duration,
      progress,
      dependsOn: Array.isArray(row.depends_on) ? row.depends_on.map((id) => Number(id)) : [],
    };
  });
}

export function TasksKanbanPage() {
  const { formatDate, formatDigits, isRtl } = useLocale();
  const t = useTranslations('pm.tasks');
  const tc = useTranslations('common');
  const none = tc('none');

  const statusLabel = useCallback(
    (status: string) => {
      if (t.has(status as 'todo')) return t(status as 'todo');
      return status;
    },
    [t]
  );

  const priorityLabel = useCallback(
    (priority: string) => {
      const map: Record<string, 'priorityLow' | 'priorityNormal' | 'priorityHigh' | 'priorityUrgent'> = {
        low: 'priorityLow',
        normal: 'priorityNormal',
        high: 'priorityHigh',
        urgent: 'priorityUrgent',
      };
      const key = map[priority];
      return key ? t(key) : priority;
    },
    [t]
  );

  const [view, setView] = useState<ViewMode>('kanban');
  const [data, setData] = useState<KanbanData | null>(null);
  const [listRows, setListRows] = useState<TaskRow[]>([]);
  const [calendarEvents, setCalendarEvents] = useState<TaskCalendarEvent[]>([]);
  const [ganttTasks, setGanttTasks] = useState<TaskGanttItem[]>([]);
  const [viewMonth, setViewMonth] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
  const [viewsLoading, setViewsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [quickTitle, setQuickTitle] = useState('');
  const [filterProject, setFilterProject] = useState('');
  const [filterAssignee, setFilterAssignee] = useState('');
  const [filterPriority, setFilterPriority] = useState('');
  const [filterLabel, setFilterLabel] = useState('');
  const [projectOpts, setProjectOpts] = useState<{ id: number; name: string }[]>([]);
  const [userOpts, setUserOpts] = useState<{ id: number; name: string }[]>([]);
  const [detailTaskId, setDetailTaskId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkStatus, setBulkStatus] = useState('');
  const [bulkBusy, setBulkBusy] = useState(false);

  useEffect(() => {
    apiClient
      .get('/v1/projects/projects', { params: { per_page: 200 } })
      .then((res) => {
        const body = res.data as { data?: { id: number; name: string }[] | { data?: { id: number; name: string }[] } };
        const list = Array.isArray(body.data)
          ? body.data
          : ((body.data as { data?: { id: number; name: string }[] })?.data ?? []);
        setProjectOpts(list as { id: number; name: string }[]);
      })
      .catch(() => {});
    apiClient
      .get('/v1/core/users', { params: { per_page: 200 } })
      .then((res) => {
        const body = res.data as { data?: { id: number; name: string }[] | { data?: { id: number; name: string }[] } };
        const list = Array.isArray(body.data)
          ? body.data
          : ((body.data as { data?: { id: number; name: string }[] })?.data ?? []);
        setUserOpts(list as { id: number; name: string }[]);
      })
      .catch(() => {});
  }, []);

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const loadKanban = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/kanban/data', {
        params: {
          project_id: filterProject || undefined,
          assignee_id: filterAssignee || undefined,
          priority: filterPriority || undefined,
          label: filterLabel || undefined,
        },
      });
      const body = res.data;
      setData(readEntity<KanbanData>(body) ?? {});
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [filterProject, filterAssignee, filterPriority, filterLabel]);

  const loadList = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/projects/tasks', {
        params: {
          per_page: 50,
          project_id: filterProject || undefined,
          assignee_id: filterAssignee || undefined,
          priority: filterPriority || undefined,
          label: filterLabel || undefined,
        },
      });
      const rows = normalizeListPayload(res.data) as TaskRow[];
      setListRows(rows);
    } catch {
      /* ignore */
    }
  }, [filterProject, filterAssignee, filterPriority, filterLabel]);

  const loadCalendar = useCallback(async () => {
    setViewsLoading(true);
    setError(null);
    try {
      const { start, end } = monthRange(viewMonth);
      const res = await apiClient.get('/v1/projects/tasks/calendar', {
        params: {
          start,
          end,
          project_id: filterProject || undefined,
        },
      });
      const rows = normalizeListPayload(res.data) as TaskRow[];
      setCalendarEvents(
        rows
          .filter((r) => r.due_at)
          .map((r) => ({
            id: Number(r.id),
            title: String(r.title ?? `#${r.id}`),
            due_at: String(r.due_at),
          }))
      );
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setViewsLoading(false);
    }
  }, [viewMonth, filterProject]);

  const loadGantt = useCallback(async () => {
    setViewsLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/tasks/gantt', {
        params: { project_id: filterProject || undefined },
      });
      const rows = normalizeListPayload(res.data) as TaskRow[];
      setGanttTasks(toGanttItems(rows));
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setViewsLoading(false);
    }
  }, [filterProject]);

  useEffect(() => {
    void loadKanban();
    void loadList();
  }, [loadKanban, loadList]);

  useEffect(() => {
    if (view === 'calendar') void loadCalendar();
    if (view === 'gantt') void loadGantt();
  }, [view, loadCalendar, loadGantt]);

  const columns = data?.columns ?? [];
  const cards = data?.cards ?? [];

  const stats = useMemo(() => {
    const byCol = new Map<number, number>();
    for (const c of columns) byCol.set(c.id, 0);
    for (const card of cards) {
      const cid = card.column_id ?? columns[0]?.id;
      if (cid == null) continue;
      byCol.set(cid, (byCol.get(cid) ?? 0) + 1);
    }
    return { total: cards.length, byCol };
  }, [cards, columns]);

  const cardsByCol = useMemo(() => {
    const m = new Map<number, typeof cards>();
    for (const c of columns) m.set(c.id, []);
    for (const card of cards) {
      const cid = card.column_id ?? columns[0]?.id;
      if (cid == null) continue;
      const arr = m.get(cid) ?? [];
      arr.push(card);
      m.set(cid, arr);
    }
    return m;
  }, [cards, columns]);

  async function onDragEnd(ev: DragEndEvent) {
    const overId = ev.over?.id?.toString();
    const activeId = ev.active?.id?.toString();
    if (!overId?.startsWith('col-') || !activeId?.startsWith('task-')) return;
    const taskId = Number(activeId.replace('task-', ''));
    const colId = Number(overId.replace('col-', ''));
    if (!taskId || !colId) return;
    try {
      await apiClient.patch(`/v1/projects/kanban/cards/${taskId}`, { workflow_status_id: colId });
      void loadKanban();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function deleteTask(taskId: number) {
    if (!confirm(t('confirmDelete'))) return;
    setError(null);
    try {
      await apiClient.delete(`/v1/projects/tasks/${taskId}`);
      void loadKanban();
      void loadList();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function quickAdd(columnId: number) {
    if (!quickTitle.trim()) return;
    setError(null);
    try {
      await apiClient.post('/v1/projects/kanban/cards', {
        title: quickTitle,
        column_id: columnId,
      });
      setQuickTitle('');
      void loadKanban();
      void loadList();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  function toggleSelect(id: number) {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }

  async function handleBulkStatus() {
    if (!bulkStatus || selectedIds.length === 0) return;
    setBulkBusy(true);
    setError(null);
    try {
      await apiClient.patch('/v1/projects/tasks/bulk', {
        ids: selectedIds,
        status: bulkStatus,
      });
      setSelectedIds([]);
      setBulkStatus('');
      void loadList();
      void loadKanban();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setBulkBusy(false);
    }
  }

  function refreshAll() {
    void loadKanban();
    void loadList();
    if (view === 'calendar') void loadCalendar();
    if (view === 'gantt') void loadGantt();
  }

  const viewOptions = [
    { id: 'kanban', label: t('viewKanban') },
    { id: 'list', label: t('viewList') },
    { id: 'calendar', label: t('viewCalendar') },
    { id: 'gantt', label: t('viewGantt') },
  ];

  const statusOptions = useMemo(() => {
    const set = new Set<string>();
    for (const r of listRows) {
      if (r.status) set.add(String(r.status));
    }
    for (const c of cards) {
      if (c.status) set.add(String(c.status));
    }
    if (set.size === 0) {
      ['open', 'in_progress', 'done', 'completed'].forEach((s) => set.add(s));
    }
    return Array.from(set);
  }, [listRows, cards]);

  return (
    <div
      className="wd-page-shell wd-tasks min-w-0 max-w-full space-y-3 overflow-x-hidden text-start sm:space-y-4"
      dir={isRtl ? 'rtl' : 'ltr'}
    >
      <PmPageHeader
        title={t('title')}
        description={t('pageDescription')}
        actions={
          <PmViewToggle
            value={view}
            options={viewOptions}
            onChange={(id) => setView(id as ViewMode)}
          />
        }
      />

      <Card variant="glass" className="min-w-0 overflow-hidden">
        <CardHeader className="space-y-3 p-3 sm:p-6">
          <CardTitle className="text-base">{t('filters')}</CardTitle>
          <PmFilterBar
            applyLabel={t('applyFilters')}
            onApply={() => {
              void loadKanban();
              void loadList();
              if (view === 'calendar') void loadCalendar();
              if (view === 'gantt') void loadGantt();
            }}
          >
            <label className="flex min-w-0 w-full flex-col gap-1 sm:w-[11rem] sm:max-w-[14rem]">
              <span className="text-xs font-medium text-muted-foreground">{t('filterProject')}</span>
              <Select
                value={filterProject || '__all__'}
                onValueChange={(v) => setFilterProject(v === '__all__' ? '' : v)}
              >
                <SelectTrigger className="h-9 w-full min-w-0">
                  <SelectValue placeholder={t('filterProject')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">{t('allProjects')}</SelectItem>
                  {projectOpts.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </label>
            <label className="flex min-w-0 w-full flex-col gap-1 sm:w-[11rem] sm:max-w-[14rem]">
              <span className="text-xs font-medium text-muted-foreground">{t('filterAssignee')}</span>
              <Select
                value={filterAssignee || '__all__'}
                onValueChange={(v) => setFilterAssignee(v === '__all__' ? '' : v)}
              >
                <SelectTrigger className="h-9 w-full min-w-0">
                  <SelectValue placeholder={t('filterAssignee')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">{t('allAssignees')}</SelectItem>
                  {userOpts.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </label>
            <label className="flex min-w-0 w-full flex-col gap-1 sm:w-[10rem] sm:max-w-[12rem]">
              <span className="text-xs font-medium text-muted-foreground">{t('filterPriority')}</span>
              <Select
                value={filterPriority || '__all__'}
                onValueChange={(v) => setFilterPriority(v === '__all__' ? '' : v)}
              >
                <SelectTrigger className="h-9 w-full min-w-0">
                  <SelectValue placeholder={t('filterPriority')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">{t('allPriorities')}</SelectItem>
                  <SelectItem value="low">{t('priorityLow')}</SelectItem>
                  <SelectItem value="normal">{t('priorityNormal')}</SelectItem>
                  <SelectItem value="high">{t('priorityHigh')}</SelectItem>
                  <SelectItem value="urgent">{t('priorityUrgent')}</SelectItem>
                </SelectContent>
              </Select>
            </label>
            <label className="flex min-w-0 w-full flex-col gap-1 sm:w-[9rem] sm:max-w-[12rem]">
              <span className="text-xs font-medium text-muted-foreground">{t('filterLabel')}</span>
              <Input
                placeholder={t('filterLabelPlaceholder')}
                className="h-9 w-full min-w-0"
                value={filterLabel}
                onChange={(e) => setFilterLabel(e.target.value)}
              />
            </label>
          </PmFilterBar>
          <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span>{t('tasksPerColumn', { count: formatDigits(stats.total) })}</span>
            {columns.map((c) => (
              <span key={c.id} className="rounded-md bg-muted/60 px-1.5 py-0.5">
                {t('columnCount', { name: c.name, count: formatDigits(stats.byCol.get(c.id) ?? 0) })}
              </span>
            ))}
          </div>
        </CardHeader>
      </Card>

      {selectedIds.length > 0 && view === 'list' ? (
        <Card className="min-w-0 overflow-hidden">
          <CardContent className="flex flex-col gap-3 pt-6 sm:flex-row sm:flex-wrap sm:items-center">
            <span className="text-sm">{t('bulkSelected', { count: formatDigits(selectedIds.length) })}</span>
            <Select value={bulkStatus} onValueChange={setBulkStatus}>
              <SelectTrigger className="h-9 w-full min-w-0 sm:w-[160px]">
                <SelectValue placeholder={t('bulkStatus')} />
              </SelectTrigger>
              <SelectContent>
                {statusOptions.map((s) => (
                  <SelectItem key={s} value={s}>
                    {statusLabel(s)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button size="sm" onClick={() => void handleBulkStatus()} disabled={!bulkStatus || bulkBusy}>
              {t('bulkApply')}
            </Button>
            <Button size="sm" variant="ghost" onClick={() => setSelectedIds([])}>
              {tc('cancel')}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      <PmAlerts error={error} onDismiss={() => setError(null)} />

      {view === 'kanban' ? (
        <Card className="min-w-0 overflow-hidden">
          <CardHeader className="flex flex-col gap-3 space-y-0 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <CardTitle className="text-base">{t('viewKanban')}</CardTitle>
            <div className="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
              <Input
                placeholder={t('quickTitle')}
                value={quickTitle}
                onChange={(e) => setQuickTitle(e.target.value)}
                className="h-9 w-full min-w-0 sm:w-56"
              />
              <div className="grid grid-cols-2 gap-2 sm:flex">
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  className="h-9"
                  onClick={() => columns[0] && void quickAdd(columns[0].id)}
                  disabled={!columns.length}
                >
                  {t('quickAdd')}
                </Button>
                <Button type="button" size="sm" variant="outline" className="h-9" onClick={() => void loadKanban()}>
                  {t('refresh')}
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent className="min-w-0 p-3 sm:p-6">
            {loading ? <p className="text-sm text-muted-foreground">{tc('loading')}</p> : null}
            <DndContext sensors={sensors} collisionDetection={closestCorners} onDragEnd={onDragEnd}>
              <div className="-mx-1 flex max-w-full gap-3 overflow-x-auto px-1 pb-2 [scrollbar-width:thin]">
                {columns.map((col) => {
                  const colCards = cardsByCol.get(col.id) ?? [];
                  const lanes = new Map<string, typeof colCards>();
                  for (const card of colCards) {
                    const lane = card.swimlane_key?.trim() || '';
                    const bucket = lanes.get(lane) ?? [];
                    bucket.push(card);
                    lanes.set(lane, bucket);
                  }
                  const wip =
                    col.wip_limit != null ? `${formatDigits(colCards.length)}/${formatDigits(col.wip_limit)}` : null;
                  return (
                    <ColumnDrop key={col.id} colId={col.id} name={col.name} color={col.color} wip={wip}>
                      {[...lanes.entries()].map(([lane, laneCards]) => (
                        <div key={lane || 'all'} className="space-y-2">
                          {lane ? <p className="text-[11px] text-muted-foreground">{lane}</p> : null}
                          {laneCards.map((c) => (
                            <DraggableTask
                              key={c.id}
                              id={c.id}
                              title={`${c.title ?? `#${formatDigits(c.id)}`}${c.priority ? ` · ${priorityLabel(c.priority)}` : ''}`}
                              onOpen={setDetailTaskId}
                              onDelete={deleteTask}
                            />
                          ))}
                        </div>
                      ))}
                    </ColumnDrop>
                  );
                })}
              </div>
            </DndContext>
          </CardContent>
        </Card>
      ) : null}

      {view === 'list' ? (
        <Card className="min-w-0 overflow-hidden">
          <CardContent className="min-w-0 p-3 pt-6 sm:p-6">
            {listRows.length === 0 ? (
              <p className="py-8 text-center text-muted-foreground">{t('noTasks')}</p>
            ) : (
              <>
                {/* Mobile cards */}
                <ul className="space-y-2 md:hidden">
                  {listRows.map((row) => {
                    const id = Number(row.id);
                    const uid = Number(row.assignee_id);
                    const u = userOpts.find((o) => o.id === uid);
                    return (
                      <li key={id} className="rounded-xl border bg-card p-3">
                        <div className="flex items-start gap-2">
                          <Checkbox
                            checked={selectedIds.includes(id)}
                            onCheckedChange={() => toggleSelect(id)}
                            className="mt-1"
                          />
                          <div className="min-w-0 flex-1 space-y-1.5">
                            <button
                              type="button"
                              className="block w-full truncate text-start text-sm font-medium hover:underline"
                              onClick={() => setDetailTaskId(id)}
                            >
                              {String(row.title ?? `#${formatDigits(id)}`)}
                            </button>
                            <div className="flex flex-wrap gap-1.5 text-[11px] text-muted-foreground">
                              <span className="rounded-md bg-muted px-1.5 py-0.5">
                                {row.status ? statusLabel(String(row.status)) : none}
                              </span>
                              <span className="rounded-md bg-muted px-1.5 py-0.5">
                                {row.priority ? priorityLabel(String(row.priority)) : none}
                              </span>
                              {row.label ? (
                                <span className="rounded-md bg-muted px-1.5 py-0.5">{String(row.label)}</span>
                              ) : null}
                            </div>
                            <div className="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                              <span className="truncate">{u?.name ?? (row.assignee_id ? formatDigits(row.assignee_id) : none)}</span>
                              <span className="shrink-0">{row.due_at ? formatDate(String(row.due_at)) || none : none}</span>
                            </div>
                          </div>
                        </div>
                      </li>
                    );
                  })}
                </ul>
                {/* Desktop table */}
                <div className="hidden max-w-full overflow-x-auto md:block">
                  <Table dir={isRtl ? 'rtl' : 'ltr'} className="min-w-[640px]">
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-10" />
                        <TableHead>{t('colTitle')}</TableHead>
                        <TableHead>{t('colStatus')}</TableHead>
                        <TableHead>{t('colPriority')}</TableHead>
                        <TableHead>{t('colLabel')}</TableHead>
                        <TableHead>{t('colAssignee')}</TableHead>
                        <TableHead>{t('colDue')}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {listRows.map((row) => {
                        const id = Number(row.id);
                        const uid = Number(row.assignee_id);
                        const u = userOpts.find((o) => o.id === uid);
                        return (
                          <TableRow key={id}>
                            <TableCell>
                              <Checkbox
                                checked={selectedIds.includes(id)}
                                onCheckedChange={() => toggleSelect(id)}
                              />
                            </TableCell>
                            <TableCell className="max-w-[16rem]">
                              <button
                                type="button"
                                className="block max-w-full truncate font-medium text-start hover:underline"
                                onClick={() => setDetailTaskId(id)}
                              >
                                {String(row.title ?? `#${formatDigits(id)}`)}
                              </button>
                            </TableCell>
                            <TableCell>
                              {row.status ? statusLabel(String(row.status)) : none}
                            </TableCell>
                            <TableCell>{row.priority ? priorityLabel(String(row.priority)) : none}</TableCell>
                            <TableCell className="max-w-[8rem] truncate">{String(row.label ?? none)}</TableCell>
                            <TableCell className="max-w-[8rem] truncate">{u?.name ?? (row.assignee_id ? formatDigits(row.assignee_id) : none)}</TableCell>
                            <TableCell className="whitespace-nowrap">
                              {row.due_at ? formatDate(String(row.due_at)) || none : none}
                            </TableCell>
                          </TableRow>
                        );
                      })}
                    </TableBody>
                  </Table>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      ) : null}

      {view === 'calendar' ? (
        viewsLoading ? (
          <p className="text-sm text-muted-foreground">{tc('loading')}</p>
        ) : (
          <TaskCalendarPanel
            events={calendarEvents}
            month={viewMonth}
            onMonthChange={setViewMonth}
            onSelectTask={setDetailTaskId}
          />
        )
      ) : null}

      {view === 'gantt' ? (
        <Card className="min-w-0 overflow-hidden">
          <CardContent className="min-w-0 p-3 pt-6 sm:p-6">
            {viewsLoading ? (
              <p className="text-sm text-muted-foreground">{tc('loading')}</p>
            ) : ganttTasks.length === 0 ? (
              <p className="py-8 text-center text-muted-foreground">{t('noTasks')}</p>
            ) : (
              <TaskGanttTimeline tasks={ganttTasks} onTaskClick={setDetailTaskId} />
            )}
          </CardContent>
        </Card>
      ) : null}

      <TaskDetailSheet
        taskId={detailTaskId}
        open={detailTaskId !== null}
        onOpenChange={(open) => !open && setDetailTaskId(null)}
        onUpdated={refreshAll}
      />
    </div>
  );
}
