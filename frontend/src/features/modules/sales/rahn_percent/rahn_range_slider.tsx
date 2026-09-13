'use client';

import { cn } from '@/lib/utils';

type Props = {
  min: number;
  max: number;
  step?: number;
  value: number;
  onValueChange: (value: number) => void;
  className?: string;
};

/** Lightweight range input (no radix-slider dependency). */
export function RahnRangeSlider({ min, max, step = 1, value, onValueChange, className }: Props) {
  const safeMax = Math.max(min, max);
  const clamped = Math.min(safeMax, Math.max(min, value));

  return (
    <input
      type="range"
      min={min}
      max={safeMax}
      step={step}
      value={clamped}
      onChange={(e) => onValueChange(Number(e.target.value))}
      className={cn('w-full accent-primary h-2 cursor-pointer', className)}
    />
  );
}
