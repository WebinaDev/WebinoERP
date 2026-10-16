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
    <div className="max-w-full space-y-3 overflow-x-auto [scrollbar-width:thin]">
      <div className="grid min-w-[36rem] grid-cols-[minmax(7rem,12rem)_1fr_4.5rem_4.5rem] gap-2 px-1 text-xs font-medium text-muted-foreground sm:min-w-[40rem]">
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
            className="grid min-w-[36rem] grid-cols-[minmax(7rem,12rem)_1fr_4.5rem_4.5rem] items-center gap-2 rounded-md border p-2 text-start transition-colors hover:bg-muted/50 sm:min-w-[40rem]"
            onClick={() => onTaskClick(task.id)}
          >
            <span className="min-w-0 truncate text-sm font-medium">
              {task.text}
              {task.dependsOn && task.dependsOn.length > 0 ? (
                <span className="mt-0.5 block truncate text-xs font-normal text-muted-foreground">
                  {t('dependsOn')} {task.dependsOn.map((id) => `#${id}`).join(' · ')}
                </span>
              ) : null}
            </span>
            <div className="relative h-6 min-w-0 overflow-hidden rounded bg-muted">
              <div
                className={cn('absolute top-0 h-full rounded bg-primary/40')}
                style={{ left: `${left}%`, width: `${width}%` }}
              />
              <div
                className="absolute top-0 h-full rounded bg-primary"
                style={{ left: `${left}%`, width: `${width * Math.min(1, Math.max(0, task.progress))}%` }}
              />
            </div>
            <span className="text-xs text-muted-foreground">{t('durationDaysShort', { count: task.duration })}</span>
            <span className="text-xs text-muted-foreground">{t('progressPercent', { value: Math.round(task.progress * 100) })}</span>
          </button>
        );
      })}
    </div>
  );
}
