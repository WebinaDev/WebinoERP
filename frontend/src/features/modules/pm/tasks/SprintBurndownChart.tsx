'use client';

import React from 'react';
import { useTranslations } from 'next-intl';
import { TrendingDown, Flame, CheckCircle2, Clock } from 'lucide-react';
import type { SprintBurndownDay } from './types';

interface SprintBurndownChartProps {
  sprintTitle: string;
  totalPoints: number;
  remainingPoints: number;
  data?: SprintBurndownDay[];
}

export function SprintBurndownChart({
  sprintTitle,
  totalPoints = 80,
  remainingPoints = 25,
  data,
}: SprintBurndownChartProps) {
  const t = useTranslations('pm.tasks');

  // داده‌های پیش‌فرض سوختن اسپرینت ۱۰ روزه در صورت نبود آرایه
  const days: SprintBurndownDay[] = data || [
    { day: 'روز ۱', idealRemaining: 80, actualRemaining: 80 },
    { day: 'روز ۲', idealRemaining: 72, actualRemaining: 76 },
    { day: 'روز ۳', idealRemaining: 64, actualRemaining: 68 },
    { day: 'روز ۴', idealRemaining: 56, actualRemaining: 55 },
    { day: 'روز ۵', idealRemaining: 48, actualRemaining: 50 },
    { day: 'روز ۶', idealRemaining: 40, actualRemaining: 42 },
    { day: 'روز ۷', idealRemaining: 32, actualRemaining: 36 },
    { day: 'روز ۸', idealRemaining: 24, actualRemaining: 28 },
    { day: 'روز ۹', idealRemaining: 16, actualRemaining: 25 },
    { day: 'روز ۱۰', idealRemaining: 0, actualRemaining: 0 },
  ];

  return (
    <div className="p-4 rounded-xl border bg-card shadow-sm space-y-4" dir="rtl">
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b pb-3">
        <div className="space-y-0.5">
          <div className="flex items-center gap-2">
            <TrendingDown className="size-4 text-primary" />
            <h3 className="font-bold text-sm">نمودار پیشرفت اسپرینت (Sprint Burndown Chart)</h3>
          </div>
          <p className="text-xs text-muted-foreground">{sprintTitle}</p>
        </div>

        <div className="flex items-center gap-4 text-xs">
          <div className="flex items-center gap-1.5">
            <span className="size-2.5 rounded-full bg-muted-foreground/40 border border-dashed" />
            <span className="text-muted-foreground">خط ایده‌آل</span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="size-2.5 rounded-full bg-rose-500" />
            <span className="font-semibold text-rose-600 dark:text-rose-400">باقی‌مانده واقعی</span>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div className="p-2.5 rounded-lg bg-muted/30 border space-y-1">
          <span className="text-muted-foreground">کل امتیازهای اسپرینت:</span>
          <div className="text-base font-bold font-mono">{totalPoints} استوری پوینت</div>
        </div>
        <div className="p-2.5 rounded-lg bg-muted/30 border space-y-1">
          <span className="text-muted-foreground">امتیازهای باقی‌مانده:</span>
          <div className="text-base font-bold font-mono text-rose-600">{remainingPoints} استوری پوینت</div>
        </div>
        <div className="p-2.5 rounded-lg bg-muted/30 border space-y-1">
          <span className="text-muted-foreground">تکمیل‌شده:</span>
          <div className="text-base font-bold font-mono text-emerald-600">{totalPoints - remainingPoints} پوینت</div>
        </div>
        <div className="p-2.5 rounded-lg bg-muted/30 border space-y-1">
          <span className="text-muted-foreground">سرعت تیم (Velocity):</span>
          <div className="text-base font-bold font-mono text-primary">۵.۵ پوینت/روز</div>
        </div>
      </div>

      {/* نمودار بصری ستونی و مقایسه‌ای روزانه */}
      <div className="h-40 flex items-end gap-2 pt-4 border-t border-muted/50">
        {days.map((item, idx) => (
          <div key={idx} className="flex-1 flex flex-col items-center gap-1.5 h-full justify-end">
            <div className="w-full flex items-end justify-center gap-1 h-28">
              {/* ستون ایده‌آل (خاکستری خط‌چین) */}
              <div
                style={{ height: `${(item.idealRemaining / totalPoints) * 100}%` }}
                className="w-2.5 bg-muted-foreground/20 rounded-t-xs"
                title={`ایده‌آل: ${item.idealRemaining} پوینت`}
              />
              {/* ستون واقعی (رنگی) */}
              <div
                style={{ height: `${(item.actualRemaining / totalPoints) * 100}%` }}
                className="w-3.5 bg-rose-500 rounded-t-xs transition-all hover:opacity-80"
                title={`واقعی: ${item.actualRemaining} پوینت`}
              />
            </div>
            <span className="text-[10px] text-muted-foreground font-mono">{item.day}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
