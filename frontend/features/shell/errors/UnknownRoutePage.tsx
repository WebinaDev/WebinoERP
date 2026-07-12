'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboardHref } from '@/lib/route-resolver';

type Props = {
  path?: string;
};

export function UnknownRoutePage({ path }: Props) {
  const t = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('errors.notFoundTitle')}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {path ? (
          <p className="text-sm text-muted-foreground" dir="ltr">
            {path}
          </p>
        ) : null}
        <p className="text-sm text-muted-foreground">{t('errors.notFoundBody')}</p>
        <Button asChild variant="outline">
          <Link href={dashboardHref(locale, '')}>{t('nav.erp.dashboard')}</Link>
        </Button>
      </CardContent>
    </Card>
  );
}
