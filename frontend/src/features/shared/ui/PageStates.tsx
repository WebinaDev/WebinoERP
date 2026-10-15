'use client';

import { useTranslations } from 'next-intl';
import { AlertCircle, Inbox, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

const ICON_STROKE = 1.75;

type PageEmptyStateProps = {
  title?: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
};

export function PageEmptyState({ title, description, actionLabel, onAction }: PageEmptyStateProps) {
  const t = useTranslations('common');
  return (
    <Card className="wd-page-state border-dashed">
      <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
        <Inbox className="text-muted-foreground h-7 w-7" absoluteStrokeWidth strokeWidth={ICON_STROKE} aria-hidden />
        <div>
          <p className="font-medium">{title ?? t('empty')}</p>
          {description ? <p className="text-muted-foreground mt-1 text-sm">{description}</p> : null}
        </div>
        {actionLabel && onAction ? (
          <Button type="button" variant="outline" onClick={onAction}>
            {actionLabel}
          </Button>
        ) : null}
      </CardContent>
    </Card>
  );
}

type PageErrorStateProps = {
  message?: string;
  onRetry?: () => void;
};

export function PageErrorState({ message, onRetry }: PageErrorStateProps) {
  const t = useTranslations('common');
  return (
    <Card className="wd-page-state border-destructive/40 bg-destructive/5">
      <CardContent className="flex flex-col items-center justify-center gap-3 py-10 text-center">
        <AlertCircle className="text-destructive h-7 w-7" absoluteStrokeWidth strokeWidth={ICON_STROKE} aria-hidden />
        <p className="text-destructive text-sm">{message ?? t('errorGeneric')}</p>
        {onRetry ? (
          <Button type="button" variant="outline" onClick={onRetry}>
            {t('retry')}
          </Button>
        ) : null}
      </CardContent>
    </Card>
  );
}

export function PageLoadingState({ label }: { label?: string }) {
  const t = useTranslations('common');
  return (
    <div
      className="wd-page-state text-muted-foreground flex items-center justify-center gap-2 py-16"
      role="status"
      aria-live="polite"
    >
      <Loader2 className="h-5 w-5 animate-spin" absoluteStrokeWidth strokeWidth={ICON_STROKE} aria-hidden />
      <span>{label ?? t('loading')}</span>
    </div>
  );
}
