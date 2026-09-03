'use client';

import { useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';
import type { TaskGanttItem } from './types';

type Props = {
  tasks: TaskGanttItem[];
  onTaskClick: (id: number) => void;
};

export function TaskGanttTimeline({ tasks, onTaskClick }: Props) {
  const t = useTranslations('pm.tasks');
  if (tasks.length === 0) return null;

  const parseDate = (s: string) => {
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? Date.now() : d.getTime();
  };

  const starts = tasks.map((task) => parseDate(task.start_date));
  const ends = tasks.map((task) => parseDate(task.end_date));
  const min = Math.min(...starts);
  const max = Math.max(...ends);
  const span = Math.max(max - min, 86400000);

  return (
    <div className="space-y-3 overflow-x-auto">
      <div className="grid min-w-[640px] grid-cols-[200px_1fr_80px_80px] gap-2 px-1 text-xs font-medium text-muted-foreground">
        <span>{t('colTitle')}</span>
        <span />
        <span>{t('durationDays')}</span>
        <span>{t('progress')}</span>
      </div>
      {tasks.map((task) => {
        const left = ((parseDate(task.start_date) - min) / span) * 100;
        const width = Math.max(4, ((parseDate(task.end_date) - parseDate(task.start_date)) / span) * 100);
        return (
          <button
            key={task.id}
            type="button"
            className="grid min-w-[640px] grid-cols-[200px_1fr_80px_80px] items-center gap-2 rounded-md border p-2 text-start transition-colors hover:bg-muted/50"
            onClick={() => onTaskClick(task.id)}
          >
            <span className="truncate text-sm font-medium">{task.text}</span>
            <div className="relative h-6 overflow-hidden rounded bg-muted">
              <div
                className={cn('absolute top-0 h-full rounded bg-primary/40')}
                style={{ left: `${left}%`, width: `${width}%` }}
              />
              <div
                className="absolute top-0 h-full rounded bg-primary"
                style={{ left: `${left}%`, width: `${width * Math.min(1, Math.max(0, task.progress))}%` }}
              />
            </div>
            <span className="text-xs text-muted-foreground">{task.duration}d</span>
            <span className="text-xs text-muted-foreground">{Math.round(task.progress * 100)}%</span>
          </button>
        );
      })}
    </div>
  );
}
