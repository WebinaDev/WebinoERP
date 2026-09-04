'use client';

import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import { WIZARD_STEP_ICONS } from './illustrations';

type WizardShellProps = {
  step: number;
  steps: string[];
  title: string;
  subtitle?: string;
  error?: string | null;
  children: React.ReactNode;
  footer?: React.ReactNode;
};

export function WizardShell({ step, steps, title, subtitle, error, children, footer }: WizardShellProps) {
  const progress = ((step + 1) / steps.length) * 100;

  return (
    <div className="relative mx-auto max-w-5xl space-y-6 p-4 md:p-6" data-testid="site-wizard">
      <div
        className="pointer-events-none absolute inset-x-0 -top-10 -z-10 h-72 overflow-hidden"
        aria-hidden
      >
        <div className="bg-primary/15 absolute top-0 left-1/4 h-56 w-56 rounded-full blur-3xl" />
        <div className="bg-accent/20 absolute top-10 right-1/5 h-48 w-48 rounded-full blur-3xl" />
      </div>

      <header className="space-y-2">
        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
          {step + 1} / {steps.length}
        </p>
        <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">{title}</h1>
        {subtitle ? <p className="text-muted-foreground max-w-2xl text-sm md:text-base">{subtitle}</p> : null}
      </header>

      <nav aria-label="wizard steps" className="space-y-3">
        <div className="bg-muted/80 h-2 overflow-hidden rounded-full">
          <div
            className="from-primary to-primary/70 h-full rounded-full bg-gradient-to-r transition-all duration-500 ease-out"
            style={{ width: `${progress}%` }}
          />
        </div>
        <ol className="flex gap-2 overflow-x-auto pb-1">
          {steps.map((label, i) => {
            const Icon = WIZARD_STEP_ICONS[i];
            const done = i < step;
            const active = i === step;
            return (
              <li
                key={label}
                className={cn(
                  'flex min-w-[7.5rem] flex-1 items-center gap-2 rounded-xl border px-3 py-2 text-xs transition-all',
                  active && 'border-primary/50 bg-primary/10 shadow-sm scale-[1.02]',
                  done && 'border-primary/30 bg-primary/5 text-foreground',
                  !active && !done && 'border-border/60 bg-card/40 text-muted-foreground',
                )}
              >
                <span
                  className={cn(
                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border',
                    active && 'border-primary bg-primary text-primary-foreground',
                    done && 'border-primary/40 bg-primary/15 text-primary',
                    !active && !done && 'border-border bg-background',
                  )}
                >
                  {done ? <Check className="h-3.5 w-3.5" /> : Icon ? <Icon className="h-3.5 w-3.5" /> : i + 1}
                </span>
                <span className={cn('truncate font-medium', active && 'text-foreground')}>{label}</span>
              </li>
            );
          })}
        </ol>
      </nav>

      {error ? (
        <div
          className="border-destructive/40 bg-destructive/10 text-destructive animate-in fade-in slide-in-from-top-1 rounded-xl border px-4 py-3 text-sm"
          role="alert"
        >
          {error}
        </div>
      ) : null}

      <div
        key={step}
        className="animate-in fade-in slide-in-from-bottom-2 border-border/60 bg-card/55 rounded-3xl border p-4 shadow-xl backdrop-blur-md duration-300 md:p-6"
      >
        {children}
      </div>

      {footer ? <div className="sticky bottom-3 z-10">{footer}</div> : null}
    </div>
  );
}

export function WizardFooter({ children }: { children: React.ReactNode }) {
  return (
    <div className="border-border/60 bg-background/85 flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3 shadow-lg backdrop-blur-md">
      {children}
    </div>
  );
}

export function SelectableCard({
  selected,
  onClick,
  children,
  className,
  testId,
}: {
  selected?: boolean;
  onClick?: () => void;
  children: React.ReactNode;
  className?: string;
  testId?: string;
}) {
  return (
    <button
      type="button"
      data-testid={testId}
      onClick={onClick}
      className={cn(
        'group relative overflow-hidden rounded-2xl border p-4 text-start transition-all duration-200',
        'hover:-translate-y-0.5 hover:shadow-lg',
        selected
          ? 'border-primary ring-primary/30 bg-primary/5 ring-2 shadow-md'
          : 'border-border/70 bg-card/70 hover:border-primary/40',
        className,
      )}
    >
      {children}
    </button>
  );
}
