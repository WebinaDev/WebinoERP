'use client';

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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ResourceListCard } from '@/components/dashboard/ResourceListCard';
import { normalizeListPayload } from '@/lib/list-utils';

type KanbanData = {
  columns?: { id: number; name: string; color?: string | null }[];
  cards?: {
    id: number;
    column_id?: number | null;
    title?: string;
    status?: string;
    priority?: string | null;
  }[];
};

function DraggableTask({
  id,
  title,
  onDelete,
}: {
  id: number;
  title: string;
  onDelete?: (taskId: number) => void;
}) {
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
        'cursor-grab rounded-md border bg-background p-2 text-sm shadow-sm active:cursor-grabbing',
        isDragging && 'opacity-60'
      )}
    >
      <div className="flex items-start justify-between gap-1">
        <span {...listeners} {...attributes} className="min-w-0 flex-1">
          {title}
        </span>
        {onDelete ? (
          <button
            type="button"
            className="shrink-0 rounded px-1 text-xs text-destructive hover:bg-destructive/10"
            onClick={(e) => {
              e.stopPropagation();
              onDelete(id);
            }}
          >
            حذف
          </button>
        ) : null}
      </div>
    </div>
  );
}

function ColumnDrop({ colId, children, name, color }: { colId: number; children: React.ReactNode; name: string; color?: string | null }) {
  const { setNodeRef, isOver } = useDroppable({ id: `col-${colId}` });
  return (
    <div
      ref={setNodeRef}
      className={cn('flex min-h-[280px] min-w-[220px] flex-1 flex-col gap-2 rounded-lg border bg-muted/30 p-2', isOver && 'ring-2 ring-primary/40')}
      style={color ? { borderTopColor: color, borderTopWidth: 3 } : undefined}
    >
      <p className="text-xs font-semibold text-muted-foreground">{name}</p>
      {children}
    </div>
  );
}

export function TasksKanbanPage() {
  const [data, setData] = useState<KanbanData | null>(null);
  const [listRows, setListRows] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [quickTitle, setQuickTitle] = useState('');
  const [filterProject, setFilterProject] = useState('');
  const [filterAssignee, setFilterAssignee] = useState('');
  const [filterPriority, setFilterPriority] = useState('');
  const [filterLabel, setFilterLabel] = useState('');
  const [projectOpts, setProjectOpts] = useState<{id: number; name: string}[]>([]);
  const [userOpts, setUserOpts] = useState<{id: number; name: string}[]>([]);

  useEffect(() => {
    apiClient.get('/v1/projects/projects', { params: { per_page: 200 } })
      .then((res) => {
        const body = res.data as { data?: { id: number; name: string }[] | { data?: { id: number; name: string }[] } };
        const list = Array.isArray(body.data) ? body.data : (body.data as { data?: { id: number; name: string }[] })?.data ?? [];
        setProjectOpts(list as { id: number; name: string }[]);
      })
      .catch(() => {});
    apiClient.get('/v1/core/users', { params: { per_page: 200 } })
      .then((res) => {
        const body = res.data as { data?: { id: number; name: string }[] | { data?: { id: number; name: string }[] } };
        const list = Array.isArray(body.data) ? body.data : (body.data as { data?: { id: number; name: string }[] })?.data ?? [];
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
      const body = res.data as { data?: KanbanData };
      setData(body.data ?? {});
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
      const body = res.data as { data?: unknown };
      setListRows(normalizeListPayload(body));
    } catch {
      /* ignore */
    }
  }, [filterProject, filterAssignee, filterPriority, filterLabel]);

  useEffect(() => {
    void loadKanban();
    void loadList();
  }, [loadKanban, loadList]);

  const columns = data?.columns ?? [];
  const cards = data?.cards ?? [];

  const stats = useMemo(() => {
    const byCol = new Map<number, number>();
    for (const c of columns) {
      byCol.set(c.id, 0);
    }
    for (const card of cards) {
      const cid = card.column_id ?? columns[0]?.id;
      if (cid == null) continue;
      byCol.set(cid, (byCol.get(cid) ?? 0) + 1);
    }
    return { total: cards.length, byCol };
  }, [cards, columns]);

  const cardsByCol = useMemo(() => {
    const m = new Map<number, typeof cards>();
    for (const c of columns) {
      m.set(c.id, []);
    }
    for (const card of cards) {
      const cid = card.column_id ?? columns[0]?.id;
      if (cid == null) {
        continue;
      }
      const arr = m.get(cid) ?? [];
      arr.push(card);
      m.set(cid, arr);
    }
    return m;
  }, [cards, columns]);

  async function onDragEnd(ev: DragEndEvent) {
    const overId = ev.over?.id?.toString();
    const activeId = ev.active?.id?.toString();
    if (!overId?.startsWith('col-') || !activeId?.startsWith('task-')) {
      return;
    }
    const taskId = Number(activeId.replace('task-', ''));
    const colId = Number(overId.replace('col-', ''));
    if (!taskId || !colId) {
      return;
    }
    try {
      await apiClient.patch(`/v1/projects/kanban/cards/${taskId}`, { workflow_status_id: colId });
      void loadKanban();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function deleteTask(taskId: number) {
    if (!confirm('حذف این وظیفه؟')) return;
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
    if (!quickTitle.trim()) {
      return;
    }
    setError(null);
    try {
      await apiClient.post('/v1/projects/kanban/cards', {
        title: quickTitle,
        column_id: columnId,
      });
      setQuickTitle('');
      void loadKanban();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  return (
    <div className="space-y-3">
      <Card>
        <CardHeader>
          <CardTitle className="text-base mb-2">فیلترها</CardTitle>
          <div className="flex flex-wrap gap-2">
            <Select value={filterProject || '__all__'} onValueChange={(v) => setFilterProject(v === '__all__' ? '' : v)}>
              <SelectTrigger className="w-[160px]">
                <SelectValue placeholder="پروژه" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">همه پروژه‌ها</SelectItem>
                {projectOpts.map((p) => (
                  <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={filterAssignee || '__all__'} onValueChange={(v) => setFilterAssignee(v === '__all__' ? '' : v)}>
              <SelectTrigger className="w-[160px]">
                <SelectValue placeholder="مسئول" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">همه کاربران</SelectItem>
                {userOpts.map((u) => (
                  <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={filterPriority || '__all__'} onValueChange={(v) => setFilterPriority(v === '__all__' ? '' : v)}>
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder="اولویت" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">همه</SelectItem>
                <SelectItem value="low">کم</SelectItem>
                <SelectItem value="normal">عادی</SelectItem>
                <SelectItem value="high">زیاد</SelectItem>
                <SelectItem value="urgent">فوری</SelectItem>
              </SelectContent>
            </Select>
            <Input placeholder="برچسب" className="w-32" value={filterLabel} onChange={(e) => setFilterLabel(e.target.value)} />
            <Button type="button" size="sm" variant="secondary" onClick={() => { void loadKanban(); void loadList(); }}>
              اعمال
            </Button>
          </div>
          <p className="text-xs text-muted-foreground mt-2">
            جمع وظایف: {stats.total} — per column:{' '}
            {columns.map((c) => (
              <span key={c.id} className="ms-2">
                {c.name}: {stats.byCol.get(c.id) ?? 0}
              </span>
            ))}
          </p>
        </CardHeader>
      </Card>
      <Tabs defaultValue="kanban">
        <TabsList>
          <TabsTrigger value="kanban">Kanban</TabsTrigger>
          <TabsTrigger value="list">لیست</TabsTrigger>
        </TabsList>
        <TabsContent value="kanban" className="space-y-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
            <CardTitle className="text-base">تابلو Kanban</CardTitle>
            <div className="flex gap-2">
              <Input placeholder="افزودن سریع (ستون اول)" value={quickTitle} onChange={(e) => setQuickTitle(e.target.value)} className="w-56" />
              <Button type="button" size="sm" variant="secondary" onClick={() => columns[0] && void quickAdd(columns[0].id)} disabled={!columns.length}>
                افزودن
              </Button>
              <Button type="button" size="sm" variant="outline" onClick={() => void loadKanban()}>
                بروزرسانی
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            {error ? <p className="mb-2 text-sm text-destructive">{error}</p> : null}
            {loading ? <p className="text-sm text-muted-foreground">در حال بارگذاری…</p> : null}
            <DndContext sensors={sensors} collisionDetection={closestCorners} onDragEnd={onDragEnd}>
              <div className="flex gap-3 overflow-x-auto pb-2">
                {columns.map((col) => (
                  <ColumnDrop key={col.id} colId={col.id} name={col.name} color={col.color}>
                    {(cardsByCol.get(col.id) ?? []).map((c) => (
                      <DraggableTask
                        key={c.id}
                        id={c.id}
                        title={`${c.title ?? `#${c.id}`}${c.priority ? ` · ${c.priority}` : ''}`}
                        onDelete={deleteTask}
                      />
                    ))}
                  </ColumnDrop>
                ))}
              </div>
            </DndContext>
          </CardContent>
        </Card>
        </TabsContent>
        <TabsContent value="list">
          <ResourceListCard
            title="وظایف (لیست)"
            description="GET /api/v1/projects/tasks"
            loading={false}
            error={null}
            rows={listRows}
            columns={[
              { header: 'شناسه', cell: (r) => String(r.id ?? '—') },
              { header: 'عنوان', cell: (r) => String(r.title ?? '—') },
              { header: 'اولویت', cell: (r) => String(r.priority ?? '—') },
              { header: 'وضعیت', cell: (r) => String(r.status ?? '—') },
              { header: 'برچسب', cell: (r) => String(r.label ?? '—') },
              {
                header: 'مسئول',
                cell: (r) => {
                  const uid = Number(r.assignee_id);
                  const u = userOpts.find((o) => o.id === uid);
                  return u?.name ?? (r.assignee_id ? String(r.assignee_id) : '—');
                },
              },
            ]}
          />
        </TabsContent>
      </Tabs>
    </div>
  );
}
