'use client';

import type { ReactNode } from 'react';

type CrmPageLayoutProps = {
  title: string;
  description?: string;
  actions?: ReactNode;
  error?: string | null;
  success?: string | null;
  onDismissError?: () => void;
  onDismissSuccess?: () => void;
  children: ReactNode;
};

export function CrmPageLayout({
  title,
  description,
  actions,
  error,
  success,
  onDismissError,
  onDismissSuccess,
  children,
}: CrmPageLayoutProps) {
  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
        </div>
        {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
      </div>
      {error ? (
        <div className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {error}
          {onDismissError ? (
            <button type="button" className="ms-2 underline" onClick={onDismissError}>
              ×
            </button>
          ) : null}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-lg border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-700 dark:text-green-400">
          {success}
          {onDismissSuccess ? (
            <button type="button" className="ms-2 underline" onClick={onDismissSuccess}>
              ×
            </button>
          ) : null}
        </div>
      ) : null}
      {children}
    </div>
  );
}
