'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import type { LucideIcon } from 'lucide-react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  Cell,
  Pie,
  PieChart,
  PolarAngleAxis,
  RadialBar,
  RadialBarChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  CartesianGrid,
} from 'recharts';

/** Brand palette — formal blue-first, muted accents. */
export const WD_CHART = {
  blue: 'var(--color-chart-1)',
  ink: 'var(--wd-ink)',
  sky: 'color-mix(in oklch, var(--color-primary) 55%, var(--color-card))',
  teal: 'var(--color-chart-2)',
  amber: 'var(--color-chart-3)',
  rose: 'var(--color-chart-4)',
  violet: 'var(--color-chart-5)',
  slate: 'var(--color-muted-foreground)',
  grid: 'color-mix(in oklch, var(--color-border) 75%, transparent)',
};
export const WD_SERIES = [WD_CHART.blue, WD_CHART.teal, WD_CHART.amber, WD_CHART.violet, WD_CHART.rose, WD_CHART.sky];

const ICON_STROKE = 1.75;

export function compactNumber(value: number, locale: string): string {
  try {
    return new Intl.NumberFormat(locale === 'fa' ? 'fa-IR' : 'en-US', {
      notation: 'compact',
      maximumFractionDigits: 1,
    }).format(value);
  } catch {
    return String(value);
  }
}

/** Card shell: icon chip + title + optional link/aside. */
export function HomeCard({
  icon: Icon,
  title,
  tone = 'blue',
  href,
  aside,
  children,
  className = '',
  isRtl,
}: {
  icon: LucideIcon;
  title: string;
  tone?: keyof typeof WD_CHART;
  href?: string;
  aside?: ReactNode;
  children: ReactNode;
  className?: string;
  isRtl?: boolean;
}) {
  const Chevron = isRtl ? ChevronLeft : ChevronRight;
  return (
    <section className={`wd-hc ${className}`}>
      <header className="wd-hc-head">
        <span className="wd-hc-ic" style={{ color: WD_CHART[tone], background: `color-mix(in oklch, ${WD_CHART[tone]} 14%, transparent)` }}>
          <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} />
        </span>
        <h3 className="wd-hc-title">{title}</h3>
        <div className="ms-auto flex items-center gap-1">
          {aside}
          {href ? (
            <Link href={href} className="wd-hc-link" aria-label={title}>
              <Chevron className="size-4" strokeWidth={ICON_STROKE} />
            </Link>
          ) : null}
        </div>
      </header>
      <div className="wd-hc-body">{children}</div>
    </section>
  );
}

/** Small KPI tile: icon, value, label, optional sparkline/ring. */
export function KpiTile({
  icon: Icon,
  label,
  value,
  tone = 'blue',
  href,
  viz,
}: {
  icon: LucideIcon;
  label: string;
  value: string;
  tone?: keyof typeof WD_CHART;
  href?: string;
  viz?: ReactNode;
}) {
  const body = (
    <>
      <div className="flex items-start justify-between gap-2">
        <span className="wd-kpi-ic" style={{ color: WD_CHART[tone], background: `color-mix(in oklch, ${WD_CHART[tone]} 14%, transparent)` }}>
          <Icon absoluteStrokeWidth strokeWidth={ICON_STROKE} />
        </span>
        {viz ? <div className="wd-kpi-viz">{viz}</div> : null}
      </div>
      <div className="wd-kpi-v">{value}</div>
      <div className="wd-kpi-k">{label}</div>
    </>
  );
  return href ? (
    <Link href={href} className="wd-kpi">
      {body}
    </Link>
  ) : (
    <div className="wd-kpi">{body}</div>
  );
}

const tooltipStyle = {
  borderRadius: 10,
  border: '1px solid var(--color-border)',
  background: 'var(--color-popover)',
  color: 'var(--color-popover-foreground)',
  boxShadow: '0 6px 20px color-mix(in oklch, var(--color-foreground) 10%, transparent)',
  fontSize: 12,
  padding: '6px 10px',
};

export function TrendArea({
  data,
  height = 180,
  isRtl,
  format,
  color = WD_CHART.blue,
  axis = true,
}: {
  data: { label: string; value: number }[];
  height?: number;
  isRtl?: boolean;
  format: (n: number) => string;
  color?: string;
  axis?: boolean;
}) {
  const id = `wdg-${color.replace('#', '')}-${axis ? 'a' : 's'}`;
  return (
    <div dir="ltr" style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 6, right: 4, left: 4, bottom: 0 }}>
          <defs>
            <linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity={0.28} />
              <stop offset="100%" stopColor={color} stopOpacity={0} />
            </linearGradient>
          </defs>
          {axis ? <CartesianGrid vertical={false} stroke={WD_CHART.grid} strokeDasharray="3 4" /> : null}
          {axis ? (
            <XAxis
              dataKey="label"
              reversed={isRtl}
              tickLine={false}
              axisLine={false}
              tick={{ fontSize: 10, fill: WD_CHART.slate }}
              interval="preserveStartEnd"
              minTickGap={18}
            />
          ) : (
            <XAxis dataKey="label" hide reversed={isRtl} />
          )}
          <YAxis hide domain={[0, 'dataMax + 1']} />
          {axis ? <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [format(v), '']} separator="" /> : null}
          <Area
            type="monotone"
            dataKey="value"
            stroke={color}
            strokeWidth={2.2}
            fill={`url(#${id})`}
            dot={false}
            activeDot={axis ? { r: 4, strokeWidth: 0 } : false}
            isAnimationActive={false}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

export function Spark({ data, color }: { data: number[]; color?: string }) {
  return (
    <div style={{ width: 64, height: 28 }}>
      <TrendArea
        data={data.map((value, i) => ({ label: String(i), value }))}
        height={28}
        axis={false}
        format={String}
        color={color}
      />
    </div>
  );
}

/** Horizontal bars (funnel-ish). */
export function HBars({
  data,
  format,
  height,
}: {
  data: { label: string; value: number; color?: string }[];
  format: (n: number) => string;
  height?: number;
}) {
  const max = Math.max(1, ...data.map((d) => d.value));
  return (
    <ul className="space-y-2.5" style={height ? { minHeight: height } : undefined}>
      {data.map((d, i) => (
        <li key={d.label}>
          <div className="mb-1 flex items-center justify-between text-xs">
            <span className="text-muted-foreground">{d.label}</span>
            <span className="font-semibold tabular-nums">{format(d.value)}</span>
          </div>
          <div className="h-2 overflow-hidden rounded-full bg-muted/60">
            <div
              className="h-full rounded-full"
              style={{
                width: `${Math.max(3, (d.value / max) * 100)}%`,
                background: d.color ?? WD_SERIES[i % WD_SERIES.length],
              }}
            />
          </div>
        </li>
      ))}
    </ul>
  );
}

/** Vertical rounded column chart (recharts). */
export function Columns({
  data,
  isRtl,
  format,
  height = 160,
}: {
  data: { label: string; value: number }[];
  isRtl?: boolean;
  format: (n: number) => string;
  height?: number;
}) {
  return (
    <div dir="ltr" style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} margin={{ top: 8, right: 4, left: 4, bottom: 0 }} barCategoryGap="28%">
          <CartesianGrid vertical={false} stroke={WD_CHART.grid} strokeDasharray="3 4" />
          <XAxis
            dataKey="label"
            reversed={isRtl}
            tickLine={false}
            axisLine={false}
            tick={{ fontSize: 10, fill: WD_CHART.slate }}
            interval={0}
          />
          <YAxis hide />
          <Tooltip cursor={{ fill: 'color-mix(in oklch, var(--color-chart-1) 8%, transparent)' }} contentStyle={tooltipStyle} formatter={(v: number) => [format(v), '']} separator="" />
          <Bar dataKey="value" radius={[8, 8, 3, 3]} isAnimationActive={false}>
            {data.map((_, i) => (
              <Cell key={i} fill={WD_SERIES[i % WD_SERIES.length]} />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

/** Donut with center total + compact legend. */
export function Donut({
  data,
  format,
  center,
  size = 132,
}: {
  data: { label: string; value: number; color: string }[];
  format: (n: number) => string;
  center?: string;
  size?: number;
}) {
  const total = data.reduce((s, d) => s + d.value, 0);
  const pieData = total > 0 ? data : [{ label: '-', value: 1, color: 'var(--color-muted)' }];
  return (
    <div className="flex items-center gap-4">
      <div className="relative shrink-0" style={{ width: size, height: size }} dir="ltr">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={pieData}
              dataKey="value"
              nameKey="label"
              innerRadius="68%"
              outerRadius="100%"
              paddingAngle={total > 0 && data.filter((d) => d.value > 0).length > 1 ? 3 : 0}
              cornerRadius={6}
              stroke="none"
              isAnimationActive={false}
            >
              {pieData.map((d) => (
                <Cell key={d.label} fill={d.color} />
              ))}
            </Pie>
          </PieChart>
        </ResponsiveContainer>
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-lg font-semibold tabular-nums">{center ?? format(total)}</span>
        </div>
      </div>
      <ul className="min-w-0 flex-1 space-y-1.5 text-xs">
        {data.map((d) => (
          <li key={d.label} className="flex items-center justify-between gap-2">
            <span className="flex min-w-0 items-center gap-2">
              <span className="size-2 shrink-0 rounded-full" style={{ background: d.color }} />
              <span className="truncate text-muted-foreground">{d.label}</span>
            </span>
            <span className="font-semibold tabular-nums">{format(d.value)}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** Single progress ring (radial bar). */
export function Ring({
  value,
  max,
  color = WD_CHART.blue,
  size = 76,
  label,
}: {
  value: number;
  max: number;
  color?: string;
  size?: number;
  label?: string;
}) {
  const pct = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;
  return (
    <div className="relative" style={{ width: size, height: size }} dir="ltr">
      <ResponsiveContainer width="100%" height="100%">
        <RadialBarChart innerRadius="78%" outerRadius="100%" data={[{ v: pct }]} startAngle={90} endAngle={-270}>
          <PolarAngleAxis type="number" domain={[0, 100]} tick={false} />
          <RadialBar dataKey="v" cornerRadius={10} fill={color} background={{ fill: 'var(--color-muted)' }} isAnimationActive={false} />
        </RadialBarChart>
      </ResponsiveContainer>
      <div className="pointer-events-none absolute inset-0 flex items-center justify-center text-xs font-semibold tabular-nums">
        {label ?? `${pct}%`}
      </div>
    </div>
  );
}

/** Segmented stacked bar (health). */
export function Segments({
  data,
  format,
}: {
  data: { label: string; value: number; color: string }[];
  format: (n: number) => string;
}) {
  const total = data.reduce((s, d) => s + d.value, 0);
  return (
    <div className="space-y-3">
      <div className="flex h-3 w-full gap-1 overflow-hidden rounded-full bg-muted/60">
        {total > 0
          ? data
              .filter((d) => d.value > 0)
              .map((d) => (
                <div key={d.label} className="h-full rounded-full" style={{ flexGrow: d.value, background: d.color }} />
              ))
          : null}
      </div>
      <div className="grid grid-cols-3 gap-2">
        {data.map((d) => (
          <div key={d.label} className="min-w-0">
            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <span className="size-2 shrink-0 rounded-full" style={{ background: d.color }} />
              <span className="truncate">{d.label}</span>
            </div>
            <div className="text-base font-semibold tabular-nums">{format(d.value)}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
