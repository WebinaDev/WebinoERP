"use client";

import dynamic from 'next/dynamic';

const chartLoading = () => (
  <div className="h-[220px] w-full animate-pulse rounded-md bg-muted" />
);

export const AccentBarChart = dynamic(
  () => import('@webina/ui').then((m) => m.AccentBarChart),
  { ssr: false, loading: chartLoading },
);

export const AccentDonutChart = dynamic(
  () => import('@webina/ui').then((m) => m.AccentDonutChart),
  { ssr: false, loading: chartLoading },
);

export { useChartColors, type MetricBarPoint } from '@webina/ui';
