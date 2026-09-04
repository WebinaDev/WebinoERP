'use client';

import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
  title: string;
  subtitle?: string;
  actions?: ReactNode;
  error?: string | null;
  children: ReactNode;
};

export function PlatformPageLayout({ title, subtitle, actions, error, children }: Props) {
  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">{title}</h1>
          {subtitle ? <p className="text-muted-foreground text-sm">{subtitle}</p> : null}
        </div>
        {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
      </div>
      {error ? <p className="text-destructive text-sm">{error}</p> : null}
      {children}
    </div>
  );
}

export function RefreshButton({ onClick, label }: { onClick: () => void; label: string }) {
  return (
    <Button type="button" size="sm" variant="outline" onClick={onClick}>
      {label}
    </Button>
  );
}
