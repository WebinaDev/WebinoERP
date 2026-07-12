'use client';

import { useTranslations } from 'next-intl';
import { AlertCircle, Inbox, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type PageEmptyStateProps = {
  title?: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
};

export function PageEmptyState({ title, description, actionLabel, onAction }: PageEmptyStateProps) {
  const t = useTranslations('common');
  return (
    <Card className="border-dashed">
      <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
        <Inbox className="h-10 w-10 text-muted-foreground" aria-hidden />
        <div>
          <p className="font-medium">{title ?? t('empty')}</p>
          {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
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
    <Card className="border-destructive/40 bg-destructive/5">
      <CardContent className="flex flex-col items-center justify-center gap-3 py-10 text-center">
        <AlertCircle className="h-10 w-10 text-destructive" aria-hidden />
        <p className="text-sm text-destructive">{message ?? t('errorGeneric')}</p>
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
    <div className="flex items-center justify-center gap-2 py-16 text-muted-foreground" role="status" aria-live="polite">
      <Loader2 className="h-5 w-5 animate-spin" aria-hidden />
      <span>{label ?? t('loading')}</span>
    </div>
  );
}
