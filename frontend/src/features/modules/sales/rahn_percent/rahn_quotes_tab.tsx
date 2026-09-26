'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { toast } from 'sonner';
import { Copy, Trash2, RefreshCw } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { useLocale } from '@/hooks/use-locale-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { RAHN_API, type RahnQuote } from './types';
import { unwrapRahnList } from './unwrap';

export function RahnQuotesTab() {
  const t = useTranslations('sales.rahn');
  const tCommon = useTranslations('common');
  const { formatNumber, formatDate } = useLocale();
  const [quotes, setQuotes] = useState<RahnQuote[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get(`${RAHN_API}/quotes`, { params: { paged: 1 } });
      setQuotes(unwrapRahnList<RahnQuote>(res.data, 'quotes'));
    } catch {
      toast.error(t('quotesLoadError'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const copy = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      toast.success(t('linkCopied'));
    } catch {
      toast.message(url);
    }
  };

  const remove = async (id: number) => {
    try {
      const res = await apiClient.delete(`${RAHN_API}/quotes/${id}`);
      const body = res.data as { message?: string; data?: { message?: string } };
      toast.success(body?.data?.message || body?.message || t('deleted'));
      void load();
    } catch {
      toast.error(t('saveError'));
    }
  };

  if (loading) {
    return <p className="text-sm text-muted-foreground text-right" dir="rtl">{tCommon('loading')}</p>;
  }

  if (quotes.length === 0) {
    return <p className="text-sm text-muted-foreground text-right" dir="rtl">{t('noQuotes')}</p>;
  }

  return (
    <div className="space-y-3 text-right" dir="rtl">
      <div className="flex justify-start">
        <Button type="button" size="sm" variant="outline" onClick={() => void load()}>
          <RefreshCw className="h-4 w-4" />
          {tCommon('refresh')}
        </Button>
      </div>
      {quotes.map((q) => (
        <Card key={q.id}>
          <CardContent className="flex flex-wrap items-center gap-3 p-4" dir="rtl">
            <div className="min-w-0 flex-1 text-right">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{q.title}</span>
                <Badge variant="secondary">{q.status}</Badge>
              </div>
              {q.wizard?.business_name ? (
                <div className="mt-1 text-sm text-muted-foreground">{q.wizard.business_name}</div>
              ) : null}
              <div className="mt-1 text-sm text-muted-foreground">
                {formatNumber(Math.round(q.F))} {t('toman')} +{' '}
                {formatNumber(Math.round(q.p_percent * 100) / 100)}%
                {q.duration ? ` · ${q.duration} ${t('months')}` : null}
              </div>
              <div className="mt-1 text-xs text-muted-foreground">
                {q.created_at ? formatDate(q.created_at, { includeTime: true }) || q.created_at : null}
                {q.locked_at
                  ? ` · ${t('lockedAt')}: ${formatDate(q.locked_at, { includeTime: true }) || q.locked_at}`
                  : null}
              </div>
              <div className="mt-1 break-all text-left text-xs text-muted-foreground" dir="ltr">
                {q.share_url}
              </div>
            </div>
            <Button type="button" size="sm" variant="outline" onClick={() => void copy(q.share_url)}>
              <Copy className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              className="text-destructive"
              onClick={() => void remove(q.id)}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
