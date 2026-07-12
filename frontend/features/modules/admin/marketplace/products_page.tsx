'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { dashboardHref } from '@/lib/route-resolver';

export function ProductsPage() {
  const t = useTranslations('marketplace.products');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/marketplace/products');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout
      title={tNav('nav.erp.distribution.marketplaceProducts')}
      actions={
        <Button asChild>
          <Link href={dashboardHref(locale, 'admin/marketplace/modules/new')}>{tNav('common.add')}</Link>
        </Button>
      }
      {...layoutProps}
    >
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('name')}</TableHead>
                <TableHead>{t('sku')}</TableHead>
                <TableHead>{t('price')}</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.name ?? '')}</TableCell>
                  <TableCell>{String(r.sku ?? '')}</TableCell>
                  <TableCell>{String(r.price ?? '')}</TableCell>
                  <TableCell>
                    <Button variant="link" size="sm" asChild>
                      <Link href={dashboardHref(locale, `admin/marketplace/modules/${r.id}`)}>{tNav('common.edit')}</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
