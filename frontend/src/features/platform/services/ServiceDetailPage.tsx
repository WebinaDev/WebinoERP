'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboardHref } from '@/lib/route-resolver';
import { fetchServiceTemplate, type PlatformServiceTemplate } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

type Props = { slug: string };

export function ServiceDetailPage({ slug }: Props) {
  const t = useTranslations('platform.services');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [row, setRow] = useState<PlatformServiceTemplate | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRow(await fetchServiceTemplate(slug));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [slug, tP]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <PlatformPageLayout
      title={row?.name ?? slug}
      subtitle={t('detailSubtitle')}
      error={error}
      actions={
        <>
          <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, 'admin/platform/services')}>{tP('back')}</Link></Button>
          <RefreshButton onClick={() => void load()} label={tP('refresh')} />
        </>
      }
    >
      {row ? (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <CardTitle className="text-base">{row.name}</CardTitle>
            <Button asChild size="sm">
              <Link href={dashboardHref(locale, `admin/platform/resources/new?template=${row.slug}`)}>{t('deployTemplate')}</Link>
            </Button>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <p className="text-muted-foreground">{row.description ?? '—'}</p>
            <pre className="max-h-96 overflow-auto rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{row.compose ?? t('noCompose')}</pre>
          </CardContent>
        </Card>
      ) : (
        <p className="text-sm text-muted-foreground">{tP('loading')}</p>
      )}
    </PlatformPageLayout>
  );
}
