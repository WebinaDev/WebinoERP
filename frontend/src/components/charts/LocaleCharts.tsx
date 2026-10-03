'use client';

import { useState } from 'react';
import { useLocale } from '@/hooks/use-locale-next';

type BarPoint = { label: string; value: number };

export function LocaleBarChart({ data }: { data: BarPoint[] }) {
  const { formatNumber, isRtl } = useLocale();
  const [active, setActive] = useState<number | null>(null);
  const max = Math.max(...data.map((point) => point.value), 1);
  if (!data.length) return null;

  return (
    <div className="space-y-2" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="flex h-52 items-end gap-1.5">
        {data.map((point, index) => {
          const height = Math.max(4, (point.value / max) * 100);
          const shown = active === index;
          return (
            <button
              key={`${point.label}-${index}`}
              type="button"
              className="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
              onMouseEnter={() => setActive(index)}
              onFocus={() => setActive(index)}
              onMouseLeave={() => setActive(null)}
              onBlur={() => setActive(null)}
            >
              <span className={`text-[10px] tabular-nums text-foreground ${shown ? 'opacity-100' : 'opacity-0'} group-hover:opacity-100`}>
                {formatNumber(point.value)}
              </span>
              <span
                className="w-full rounded-t bg-primary/80"
                style={{ height: `${height}%` }}
                title={`${point.label} ${formatNumber(point.value)}`}
              />
            </button>
          );
        })}
      </div>
      <div className="flex gap-1.5">
        {data.map((point, index) => (
          <span key={`${point.label}-axis-${index}`} className="min-w-0 flex-1 truncate text-center text-[10px] text-muted-foreground">
            {point.label}
          </span>
        ))}
      </div>
      {active != null && data[active] ? (
        <p className="text-xs text-muted-foreground">
          {data[active].label}
          <span className="ms-2 font-medium tabular-nums text-foreground">{formatNumber(data[active].value)}</span>
        </p>
      ) : null}
    </div>
  );
}

type DonutSegment = { label: string; value: number; color: string };

export function LocaleDonutChart({ segments }: { segments: DonutSegment[] }) {
  const { formatNumber, isRtl } = useLocale();
  const [active, setActive] = useState<number | null>(null);
  const total = segments.reduce((sum, segment) => sum + segment.value, 0) || 1;
  let cursor = 0;
  const arcs = segments.map((segment) => {
    const start = cursor;
    const portion = segment.value / total;
    cursor += portion;
    return { ...segment, start, portion };
  });
  const radius = 42;
  const circ = 2 * Math.PI * radius;

  return (
    <div className="flex flex-wrap items-center gap-4" dir={isRtl ? 'rtl' : 'ltr'}>
      <svg viewBox="0 0 120 120" className="size-36 shrink-0" role="img">
        <circle cx="60" cy="60" r={radius} fill="none" stroke="currentColor" className="text-muted/40" strokeWidth="14" />
        {arcs.map((arc, index) => (
          <circle
            key={arc.label}
            cx="60"
            cy="60"
            r={radius}
            fill="none"
            stroke={arc.color}
            strokeWidth={active === index ? 18 : 14}
            strokeDasharray={`${arc.portion * circ} ${circ}`}
            strokeDashoffset={-arc.start * circ}
            transform="rotate(-90 60 60)"
            onMouseEnter={() => setActive(index)}
            onMouseLeave={() => setActive(null)}
          />
        ))}
        <text x="60" y="64" textAnchor="middle" className="fill-foreground text-[14px] font-semibold">
          {formatNumber(total === 1 && segments.every((segment) => segment.value === 0) ? 0 : segments.reduce((sum, segment) => sum + segment.value, 0))}
        </text>
      </svg>
      <ul className="min-w-0 flex-1 space-y-1 text-sm">
        {arcs.map((arc, index) => (
          <li key={arc.label} className="flex items-center justify-between gap-3" onMouseEnter={() => setActive(index)}>
            <span className="flex min-w-0 items-center gap-2">
              <span className="size-2.5 shrink-0 rounded-full" style={{ background: arc.color }} />
              <span className="truncate">{arc.label}</span>
            </span>
            <span className="tabular-nums">{formatNumber(arc.value)}</span>
          </li>
        ))}
      </ul>
      {active != null && arcs[active] ? (
        <p className="w-full text-xs text-muted-foreground">
          {arcs[active].label}
          <span className="ms-2 font-medium tabular-nums text-foreground">{formatNumber(arcs[active].value)}</span>
        </p>
      ) : null}
    </div>
  );
}
