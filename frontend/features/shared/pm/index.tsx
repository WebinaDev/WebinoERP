'use client';

import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
  title: string;
  description?: string;
  actions?: ReactNode;
};

export function PmPageHeader({ title, description, actions }: Props) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 className="text-lg font-semibold">{title}</h2>
        {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
      </div>
      {actions}
    </div>
  );
}

export function PmFilterBar({
  children,
  onApply,
}: {
  children: ReactNode;
  onApply?: () => void;
}) {
  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-card p-4 sm:flex-row sm:flex-wrap sm:items-end">
      <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap">{children}</div>
      {onApply ? (
        <Button type="button" variant="secondary" onClick={onApply}>
          Apply
        </Button>
      ) : null}
    </div>
  );
}

export function PmPagination({
  page,
  lastPage,
  onPage,
}: {
  page: number;
  lastPage: number;
  onPage: (p: number) => void;
}) {
  if (lastPage <= 1) return null;
  return (
    <div className="flex items-center justify-center gap-2">
      <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
        Prev
      </Button>
      <span className="text-sm text-muted-foreground">
        {page} / {lastPage}
      </span>
      <Button type="button" variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
        Next
      </Button>
    </div>
  );
}

export function PmViewToggle({
  value,
  options,
  onChange,
}: {
  value: string;
  options: { id: string; label: string }[];
  onChange: (id: string) => void;
}) {
  return (
    <div className="inline-flex rounded-md border p-1">
      {options.map((o) => (
        <Button
          key={o.id}
          type="button"
          size="sm"
          variant={value === o.id ? 'default' : 'ghost'}
          onClick={() => onChange(o.id)}
        >
          {o.label}
        </Button>
      ))}
    </div>
  );
}

export function PmAlerts({
  error,
  success,
  onDismiss,
}: {
  error?: string | null;
  success?: string | null;
  onDismiss?: () => void;
}) {
  if (!error && !success) return null;
  return (
    <div className="space-y-2">
      {error ? (
        <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
          {onDismiss ? (
            <button type="button" className="ms-2" onClick={onDismiss}>
              ×
            </button>
          ) : null}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-md border border-green-500/40 bg-green-500/10 px-3 py-2 text-sm">
          {success}
        </div>
      ) : null}
    </div>
  );
}

export function PmConfirmDialog({
  open,
  title,
  description,
  onConfirm,
  onCancel,
  pending,
}: {
  open: boolean;
  title: string;
  description?: string;
  onConfirm: () => void;
  onCancel: () => void;
  pending?: boolean;
}) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-md rounded-lg border bg-background p-6 shadow-lg">
        <h3 className="text-lg font-semibold">{title}</h3>
        {description ? <p className="mt-2 text-sm text-muted-foreground">{description}</p> : null}
        <div className="mt-4 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onCancel} disabled={pending}>
            Cancel
          </Button>
          <Button type="button" variant="destructive" onClick={onConfirm} disabled={pending}>
            Confirm
          </Button>
        </div>
      </div>
    </div>
  );
}

export function PmEmptyState({ title, description }: { title: string; description?: string }) {
  return (
    <div className="rounded-lg border border-dashed py-12 text-center">
      <p className="font-medium">{title}</p>
      {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
    </div>
  );
}
