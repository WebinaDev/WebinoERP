'use client';

import type { ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { Inbox } from 'lucide-react';
import { Button } from '@/components/ui/button';

export { WizardStepper } from './WizardStepper';

const ICON_STROKE = 1.75;

type Props = {
  title: string;
  description?: string;
  actions?: ReactNode;
};

export function PmPageHeader({ title, description, actions }: Props) {
  return (
    <div className="wd-pm-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="min-w-0 space-y-1">
        <h2 className="text-lg font-semibold tracking-tight">{title}</h2>
        {description ? <p className="text-muted-foreground text-sm leading-relaxed">{description}</p> : null}
      </div>
      {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
    </div>
  );
}

export function PmFilterBar({
  children,
  onApply,
  applyLabel = 'Apply',
}: {
  children: ReactNode;
  onApply?: () => void;
  applyLabel?: string;
}) {
  return (
    <div className="wd-filter-bar flex flex-col gap-3 rounded-[12px] border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-end sm:p-4">
      <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap">{children}</div>
      {onApply ? (
        <Button type="button" variant="secondary" onClick={onApply}>
          {applyLabel}
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
  const t = useTranslations('common');
  if (lastPage <= 1) return null;
  return (
    <div className="flex items-center justify-center gap-2">
      <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
        {t('prev')}
      </Button>
      <span className="text-muted-foreground text-sm">
        {page} / {lastPage}
      </span>
      <Button type="button" variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
        {t('next')}
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
    <div className="wd-view-toggle inline-flex rounded-[10px] border p-1">
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
        <div className="border-destructive/40 bg-destructive/10 text-destructive rounded-[10px] border px-3 py-2 text-sm">
          {error}
          {onDismiss ? (
            <button type="button" className="ms-2" onClick={onDismiss}>
              ×
            </button>
          ) : null}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-[10px] border border-green-500/40 bg-green-500/10 px-3 py-2 text-sm">
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
  const t = useTranslations('common');
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4">
      <div
        role="alertdialog"
        className="bg-background w-full max-w-md rounded-t-[16px] border p-5 shadow-none sm:rounded-[14px] sm:p-6 sm:shadow-lg"
      >
        <h3 className="text-lg font-semibold tracking-tight">{title}</h3>
        {description ? <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{description}</p> : null}
        <div className="mt-4 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onCancel} disabled={pending}>
            {t('cancel')}
          </Button>
          <Button type="button" variant="destructive" onClick={onConfirm} disabled={pending}>
            {t('delete')}
          </Button>
        </div>
      </div>
    </div>
  );
}

export function PmEmptyState({ title, description }: { title: string; description?: string }) {
  return (
    <div className="wd-page-state rounded-[12px] border border-dashed py-12 text-center">
      <Inbox
        className="text-muted-foreground mx-auto mb-3 h-7 w-7"
        absoluteStrokeWidth
        strokeWidth={ICON_STROKE}
        aria-hidden
      />
      <p className="font-medium">{title}</p>
      {description ? <p className="text-muted-foreground mt-1 text-sm">{description}</p> : null}
    </div>
  );
}
