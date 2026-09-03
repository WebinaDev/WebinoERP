'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';

type ThemeRow = {
  id: number;
  slug: string;
  name_fa: string;
  name_en: string;
  preview_url?: string | null;
  business_types?: string[] | null;
  is_default?: boolean;
};

export function SiteThemesPage() {
  const t = useTranslations('marketplace.themes');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<ThemeRow[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/marketplace/themes');
      setRows(normalizeListPayload(res.data) as ThemeRow[]);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.distribution.marketplaceThemes')} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('slug')}</TableHead>
                <TableHead>{t('name')}</TableHead>
                <TableHead>{t('businessTypes')}</TableHead>
                <TableHead>{t('default')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.id}>
                  <TableCell className="font-mono text-xs">{r.slug}</TableCell>
                  <TableCell>{locale === 'fa' ? r.name_fa : r.name_en}</TableCell>
                  <TableCell>{(r.business_types ?? []).join(', ')}</TableCell>
                  <TableCell>{r.is_default ? t('yes') : t('no')}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
