'use client';

import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

type Step = { id: number; label: string };

type Props = {
  steps: Step[];
  current: number;
  isRtl?: boolean;
};

export function WizardStepper({ steps, current, isRtl }: Props) {
  return (
    <div className="mb-6 flex items-center gap-2 overflow-x-auto border-b pb-4">
      {steps.map((step, idx) => (
        <div key={step.id} className="flex shrink-0 items-center gap-2">
          <div
            className={cn(
              'flex size-8 items-center justify-center rounded-full text-sm font-medium',
              current === step.id
                ? 'bg-primary text-primary-foreground'
                : current > step.id
                  ? 'bg-primary/20 text-primary'
                  : 'bg-muted text-muted-foreground',
            )}
          >
            {step.id}
          </div>
          <span
            className={cn(
              'whitespace-nowrap text-sm',
              current === step.id ? 'font-medium' : 'text-muted-foreground',
            )}
          >
            {step.label}
          </span>
          {idx < steps.length - 1 ? (
            isRtl ? (
              <ChevronLeft className="size-4 text-muted-foreground" />
            ) : (
              <ChevronRight className="size-4 text-muted-foreground" />
            )
          ) : null}
        </div>
      ))}
    </div>
  );
}
