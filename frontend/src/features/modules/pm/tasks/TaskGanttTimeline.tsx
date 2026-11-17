'use client';

import React, { useState } from 'react';
import { useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';
import { AlertCircle, Flame, Eye, Layers } from 'lucide-react';
import type { TaskGanttItem } from './types';

type Props = {
  tasks: TaskGanttItem[];
  onTaskClick: (id: number) => void;
};

export function TaskGanttTimeline({ tasks, onTaskClick }: Props) {
  const t = useTranslations('pm.tasks');
  const [showBaseline, setShowBaseline] = useState(true);
  const [highlightCritical, setHighlightCritical] = useState(true);

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
    <div className="max-w-full space-y-4" dir="rtl">
      {/* نوار کنترل‌های پیشرفته گانت (Jira/MS-Project Level Controls) */}
      <div className="flex items-center justify-between gap-4 p-3 rounded-lg border bg-card text-xs flex-wrap">
        <div className="flex items-center gap-3">
          <label className="flex items-center gap-1.5 cursor-pointer font-medium">
            <input
              type="checkbox"
              checked={highlightCritical}
              onChange={(e) => setHighlightCritical(e.target.checked)}
              className="rounded text-rose-500 focus:ring-rose-500"
            />
            <span className="flex items-center gap-1 text-rose-600 dark:text-rose-400">
              <Flame className="size-3.5" />
              نمایش و تفکیک مسیر بحرانی (Critical Path)
            </span>
          </label>

          <label className="flex items-center gap-1.5 cursor-pointer font-medium">
            <input
              type="checkbox"
              checked={showBaseline}
              onChange={(e) => setShowBaseline(e.target.checked)}
              className="rounded text-primary focus:ring-primary"
            />
            <span className="flex items-center gap-1 text-muted-foreground">
              <Layers className="size-3.5" />
              مقایسه با خط مبنای اولیه (Baseline Tracking)
            </span>
          </label>
        </div>

        <div className="flex items-center gap-4 text-muted-foreground text-[11px]">
          <span className="flex items-center gap-1.5">
            <span className="size-2.5 rounded-sm bg-rose-500" />
            فعالیت بحرانی (بدون شناوری)
          </span>
          <span className="flex items-center gap-1.5">
            <span className="size-2.5 rounded-sm bg-primary" />
            فعالیت عادی
          </span>
          <span className="flex items-center gap-1.5">
            <span className="size-2.5 rounded-sm bg-muted-foreground/30 border border-dashed" />
            خط مبنای مصوب (Baseline)
          </span>
        </div>
      </div>

      <div className="overflow-x-auto [scrollbar-width:thin] space-y-2">
        <div className="grid min-w-[40rem] grid-cols-[minmax(8rem,14rem)_1fr_5rem_5rem] gap-2 px-2 text-xs font-semibold text-muted-foreground">
          <span>{t('colTitle')}</span>
          <span className="text-center">بازه زمانی و توزیع پیشرفت</span>
          <span className="text-center">{t('durationDays')}</span>
          <span className="text-center">{t('progress')}</span>
        </div>

        {tasks.map((task) => {
          const taskStart = parseDate(task.start_date);
          const taskEnd = parseDate(task.end_date);
          const left = ((taskStart - min) / span) * 100;
          const width = Math.max(3, ((taskEnd - taskStart) / span) * 100);

          const isCritical = highlightCritical && (task.isCritical || task.progress < 100 && (taskEnd - taskStart) > 10 * 86400000);

          return (
            <button
              key={task.id}
              type="button"
              className={cn(
                'grid min-w-[40rem] grid-cols-[minmax(8rem,14rem)_1fr_5rem_5rem] items-center gap-2 rounded-lg border p-2.5 text-start transition-all hover:bg-muted/40 group',
                isCritical && 'border-rose-500/30 bg-rose-500/5'
              )}
              onClick={() => onTaskClick(task.id)}
            >
              {/* عنوان و مشخصات تسک */}
              <div className="min-w-0">
                <div className="flex items-center gap-1.5">
                  {isCritical && (
                    <Flame className="size-3.5 text-rose-500 shrink-0" />
                  )}
                  <span className={cn('truncate text-xs font-semibold', isCritical && 'text-rose-700 dark:text-rose-300')}>
                    {task.text}
                  </span>
                </div>
                {task.dependsOn && task.dependsOn.length > 0 && (
                  <span className="text-[10px] text-muted-foreground block truncate mt-0.5">
                    پیش‌نیاز: #{task.dependsOn.join(', #')}
                  </span>
                )}
              </div>

              {/* تایم‌لاین گانت با خط مبنا و مسیر بحرانی */}
              <div className="relative h-6 rounded bg-muted/40 overflow-hidden flex items-center">
                {/* سایه خط مبنا (Baseline Shadow) */}
                {showBaseline && (
                  <div
                    style={{
                      right: `${Math.max(0, left - 2)}%`,
                      width: `${Math.min(100, width + 4)}%`,
                    }}
                    className="absolute h-2.5 top-0.5 rounded-xs bg-muted-foreground/20 border border-dashed border-muted-foreground/40"
                    title="زمان‌بندی خط مبنا (Baseline)"
                  />
                )}

                {/* نوار اصلی تسک */}
                <div
                  style={{ right: `${left}%`, width: `${width}%` }}
                  className={cn(
                    'absolute h-3.5 rounded-sm transition-all overflow-hidden flex items-center',
                    isCritical ? 'bg-rose-500' : 'bg-primary'
                  )}
                  title={`پیشرفت: ${task.progress}%`}
                >
                  {/* درصد پیشرفت فیزیکی داخل نوار */}
                  <div
                    style={{ width: `${task.progress}%` }}
                    className="h-full bg-black/20"
                  />
                </div>
              </div>

              {/* ستون‌های طول مدت و پیشرفت */}
              <span className="text-xs text-center font-mono font-medium text-muted-foreground">
                {task.duration} روز
              </span>
              <span className={cn(
                'text-xs text-center font-mono font-bold',
                task.progress === 100 ? 'text-emerald-600' : 'text-foreground'
              )}>
                {task.progress}%
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
