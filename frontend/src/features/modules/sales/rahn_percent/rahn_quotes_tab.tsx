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

export function RahnQuotesTab() {
  const t = useTranslations('sales.rahn');
  const tCommon = useTranslations('common');
  const { formatNumber } = useLocale();
  const [quotes, setQuotes] = useState<RahnQuote[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get(`${RAHN_API}/quotes`, { params: { paged: 1 } });
      const data = res.data as { quotes?: RahnQuote[] };
      setQuotes(data?.quotes ?? []);
    } catch {
      toast.error(t('loadError'));
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
      const data = res.data as { message?: string };
      toast.success(data?.message || t('deleted'));
      void load();
    } catch {
      toast.error(t('saveError'));
    }
  };

  if (loading) {
    return <p className="text-sm text-muted-foreground">{tCommon('loading')}</p>;
  }

  if (quotes.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('noQuotes')}</p>;
  }

  return (
    <div className="space-y-3">
      <div className="flex justify-end">
        <Button type="button" size="sm" variant="outline" onClick={() => void load()}>
          <RefreshCw className="h-4 w-4" />
          {tCommon('refresh')}
        </Button>
      </div>
      {quotes.map((q) => (
        <Card key={q.id}>
          <CardContent className="flex flex-wrap items-center gap-3 p-4">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="font-medium">{q.title}</span>
                <Badge variant="secondary">{q.status}</Badge>
              </div>
              <div className="text-sm text-muted-foreground mt-1">
                {formatNumber(Math.round(q.F))} {t('toman')} +{' '}
                {formatNumber(Math.round(q.p_percent * 100) / 100)}%
              </div>
              <div className="text-xs text-muted-foreground break-all dir-ltr text-left mt-1">{q.share_url}</div>
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
