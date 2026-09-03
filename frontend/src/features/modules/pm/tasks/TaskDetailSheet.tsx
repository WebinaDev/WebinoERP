'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Paperclip, Trash2, X } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type {
  TaskAttachmentRow,
  TaskChecklistItem,
  TaskCommentRow,
  TaskRow,
  TaskTimeLog,
} from './types';

type Props = {
  taskId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onUpdated?: () => void;
};

function normalizeChecklist(raw: unknown): TaskChecklistItem[] {
  if (!Array.isArray(raw)) return [];
  return raw.map((item, i) => {
    if (item && typeof item === 'object') {
      const o = item as Record<string, unknown>;
      return {
        id: String(o.id ?? `item-${i}`),
        text: String(o.text ?? o.title ?? ''),
        checked: Boolean(o.checked ?? o.done ?? false),
      };
    }
    return { id: `item-${i}`, text: String(item), checked: false };
  });
}

function normalizeTimeLogs(raw: unknown): TaskTimeLog[] {
  if (!Array.isArray(raw)) return [];
  return raw.map((item) => {
    const o = (item ?? {}) as Record<string, unknown>;
    return {
      minutes: Number(o.minutes ?? 0),
      note: o.note != null ? String(o.note) : null,
      at: o.at != null ? String(o.at) : null,
    };
  });
}

export function TaskDetailSheet({ taskId, open, onOpenChange, onUpdated }: Props) {
  const t = useTranslations('pm.tasks');
  const tc = useTranslations('common');
  const [task, setTask] = useState<TaskRow | null>(null);
  const [comments, setComments] = useState<TaskCommentRow[]>([]);
  const [attachments, setAttachments] = useState<TaskAttachmentRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [newChecklist, setNewChecklist] = useState('');
  const [newComment, setNewComment] = useState('');
  const [minutes, setMinutes] = useState('');
  const [timeNote, setTimeNote] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    if (!taskId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get(`/v1/projects/tasks/${taskId}`);
      const body = res.data as {
        data?: {
          task?: TaskRow;
          comments?: TaskCommentRow[];
          attachments?: TaskAttachmentRow[];
        };
      };
      const payload = body.data;
      if (payload?.task) {
        setTask({
          ...payload.task,
          checklist: normalizeChecklist(payload.task.checklist),
          time_logs: normalizeTimeLogs(payload.task.time_logs),
        });
        setComments(Array.isArray(payload.comments) ? payload.comments : []);
        setAttachments(Array.isArray(payload.attachments) ? payload.attachments : []);
      } else {
        setError(t('loadError'));
      }
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    } finally {
      setLoading(false);
    }
  }, [taskId, t]);

  useEffect(() => {
    if (open && taskId) {
      void load();
    } else if (!open) {
      setTask(null);
      setComments([]);
      setAttachments([]);
      setError(null);
    }
  }, [open, taskId, load]);

  const refresh = useCallback(async () => {
    await load();
    onUpdated?.();
  }, [load, onUpdated]);

  const persistChecklist = async (next: TaskChecklistItem[]) => {
    if (!taskId) return;
    setBusy(true);
    setError(null);
    try {
      await apiClient.post(`/v1/projects/tasks/${taskId}/checklist`, { checklist: next });
      await refresh();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  };

  const handleChecklistToggle = async (itemId: string) => {
    const list = normalizeChecklist(task?.checklist);
    const next = list.map((item) =>
      item.id === itemId ? { ...item, checked: !item.checked } : item
    );
    await persistChecklist(next);
  };

  const handleChecklistDelete = async (itemId: string) => {
    const list = normalizeChecklist(task?.checklist);
    await persistChecklist(list.filter((item) => item.id !== itemId));
  };

  const handleChecklistAdd = async () => {
    if (!newChecklist.trim()) return;
    const list = normalizeChecklist(task?.checklist);
    const next = [
      ...list,
      { id: `c-${Date.now()}`, text: newChecklist.trim(), checked: false },
    ];
    setNewChecklist('');
    await persistChecklist(next);
  };

  const handleCommentAdd = async () => {
    if (!taskId || !newComment.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await apiClient.post(`/v1/projects/tasks/${taskId}/comments`, { body: newComment.trim() });
      setNewComment('');
      await refresh();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  };

  const handleUpload = async (file: File) => {
    if (!taskId) return;
    setBusy(true);
    setError(null);
    try {
      const fd = new FormData();
      fd.append('file', file);
      await apiClient.post(`/v1/projects/tasks/${taskId}/attachments`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await refresh();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  };

  const handleDeleteAttachment = async (attachmentId: number) => {
    if (!taskId) return;
    setBusy(true);
    setError(null);
    try {
      await apiClient.delete(`/v1/projects/tasks/${taskId}/attachments/${attachmentId}`);
      await refresh();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  };

  const handleLogTime = async () => {
    if (!taskId) return;
    const m = parseInt(minutes, 10);
    if (!m || m <= 0) return;
    setBusy(true);
    setError(null);
    try {
      await apiClient.post(`/v1/projects/tasks/${taskId}/time-logs`, {
        minutes: m,
        note: timeNote.trim() || null,
      });
      setMinutes('');
      setTimeNote('');
      await refresh();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  };

  const checklist = normalizeChecklist(task?.checklist);
  const timeLogs = normalizeTimeLogs(task?.time_logs);
  const totalMinutes = timeLogs.reduce((sum, log) => sum + (log.minutes || 0), 0);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle className="pe-8 text-start">
            {task?.title ?? (loading ? '…' : `#${taskId}`)}
          </SheetTitle>
        </SheetHeader>

        {loading ? <p className="py-8 text-center text-sm text-muted-foreground">{tc('loading')}</p> : null}
        {error ? <p className="px-1 text-sm text-destructive">{error}</p> : null}

        {task && !loading ? (
          <div className="mt-4 space-y-6 pb-8">
            <div className="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <Label>{t('colStatus')}</Label>
                <p className="mt-1 text-muted-foreground">{task.status || '—'}</p>
              </div>
              <div>
                <Label>{t('colPriority')}</Label>
                <p className="mt-1 text-muted-foreground">{task.priority || '—'}</p>
              </div>
              <div>
                <Label>{t('colDue')}</Label>
                <p className="mt-1 text-muted-foreground">
                  {task.due_at ? String(task.due_at).slice(0, 10) : '—'}
                </p>
              </div>
              <div>
                <Label>{t('colLabel')}</Label>
                <p className="mt-1 text-muted-foreground">{task.label || '—'}</p>
              </div>
            </div>

            {task.content ? (
              <div>
                <Label>{t('description')}</Label>
                <div className="mt-1 rounded-md border bg-muted/30 p-3 text-sm whitespace-pre-wrap">
                  {task.content}
                </div>
              </div>
            ) : null}

            <Separator />

            <div>
              <Label className="mb-2 block">{t('checklist')}</Label>
              <ul className="space-y-2">
                {checklist.map((item) => (
                  <li key={item.id} className="flex items-center gap-2">
                    <Checkbox
                      checked={item.checked}
                      onCheckedChange={() => void handleChecklistToggle(item.id)}
                      disabled={busy}
                    />
                    <span
                      className={cn(
                        'flex-1 text-sm',
                        item.checked && 'text-muted-foreground line-through'
                      )}
                    >
                      {item.text}
                    </span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => void handleChecklistDelete(item.id)}
                      disabled={busy}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
              </ul>
              <div className="mt-2 flex gap-2">
                <Input
                  value={newChecklist}
                  onChange={(e) => setNewChecklist(e.target.value)}
                  placeholder={t('newChecklistItem')}
                  onKeyDown={(e) => e.key === 'Enter' && void handleChecklistAdd()}
                />
                <Button type="button" size="sm" onClick={() => void handleChecklistAdd()} disabled={busy}>
                  {tc('add')}
                </Button>
              </div>
            </div>

            <Separator />

            <div>
              <Label className="mb-2 block">{t('attachments')}</Label>
              <ul className="mb-2 space-y-2">
                {attachments.map((att) => (
                  <li key={att.id} className="flex items-center justify-between gap-2 text-sm">
                    {att.url ? (
                      <a href={att.url} target="_blank" rel="noopener noreferrer" className="truncate text-primary">
                        {att.original_name || att.path || `#${att.id}`}
                      </a>
                    ) : (
                      <span className="truncate">{att.original_name || att.path || `#${att.id}`}</span>
                    )}
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="shrink-0"
                      onClick={() => void handleDeleteAttachment(att.id)}
                      disabled={busy}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
              </ul>
              <input
                ref={fileRef}
                type="file"
                className="hidden"
                onChange={(e) => {
                  const f = e.target.files?.[0];
                  if (f) void handleUpload(f);
                  e.target.value = '';
                }}
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="gap-2"
                onClick={() => fileRef.current?.click()}
                disabled={busy}
              >
                <Paperclip className="h-4 w-4" />
                {t('uploadFile')}
              </Button>
            </div>

            <Separator />

            <div>
              <Label className="mb-2 block">{t('comments')}</Label>
              <ul className="mb-3 space-y-3">
                {comments.map((c) => (
                  <li key={c.id} className="text-sm">
                    <p className="font-medium">{c.user_name || `#${c.user_id}`}</p>
                    <p className="whitespace-pre-wrap text-muted-foreground">{c.body}</p>
                    {c.created_at ? (
                      <span className="text-xs text-muted-foreground">{c.created_at.slice(0, 16)}</span>
                    ) : null}
                  </li>
                ))}
                {comments.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('noComments')}</p>
                ) : null}
              </ul>
              <div className="space-y-2">
                <Textarea
                  value={newComment}
                  onChange={(e) => setNewComment(e.target.value)}
                  placeholder={t('newComment')}
                  rows={2}
                />
                <Button type="button" size="sm" onClick={() => void handleCommentAdd()} disabled={busy}>
                  {tc('save')}
                </Button>
              </div>
            </div>

            <Separator />

            <div>
              <Label className="mb-2 block">
                {t('logTime', { hours: (totalMinutes / 60).toFixed(2) })}
              </Label>
              {timeLogs.length > 0 ? (
                <ul className="mb-2 space-y-1 text-xs text-muted-foreground">
                  {timeLogs.slice(-5).map((log, i) => (
                    <li key={i}>
                      {log.minutes}m — {log.note || log.at || '—'}
                    </li>
                  ))}
                </ul>
              ) : null}
              <div className="flex flex-wrap gap-2">
                <Input
                  type="number"
                  min={1}
                  className="w-28"
                  value={minutes}
                  onChange={(e) => setMinutes(e.target.value)}
                  placeholder={t('minutesPlaceholder')}
                />
                <Input
                  className="min-w-[120px] flex-1"
                  value={timeNote}
                  onChange={(e) => setTimeNote(e.target.value)}
                  placeholder={t('timeNote')}
                />
                <Button type="button" size="sm" onClick={() => void handleLogTime()} disabled={busy}>
                  {tc('add')}
                </Button>
              </div>
            </div>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
